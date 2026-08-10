<?php
/**
 * Platform fee sync orchestrator — eBay Finances + Etsy Ledger.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syncs order-linked fees and recurring expenses. Own cursor (not channels.last_synced_at).
 */
class SOM_Platform_Fee_Sync {

	const STATUS_OPTION = 'som_fee_sync_status';
	const CURSOR_OPTION = 'som_fee_sync_cursor';

	/** First incremental lookback when channel cursor is null (days). */
	const DEFAULT_LOOKBACK_DAYS = 7;

	/**
	 * Run incremental fee sync (cron / Sync fees now).
	 *
	 * @return array<string, mixed> Summary.
	 */
	public static function sync_incremental() {
		return self::run( self::DEFAULT_LOOKBACK_DAYS );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_status() {
		$stored = get_option( self::STATUS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge(
			array(
				'last_run_at'  => null,
				'last_error'   => '',
				'last_summary' => '',
				'inserted'     => 0,
				'skipped'      => 0,
				'unmatched'    => 0,
				'ignored'      => 0,
			),
			$stored
		);
	}

	/**
	 * Per-channel fee sync cursors (UTC datetime strings).
	 *
	 * @return array<string, string|null>
	 */
	public static function get_cursors() {
		$stored = get_option( self::CURSOR_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array(
			'ebay' => isset( $stored['ebay'] ) ? (string) $stored['ebay'] : null,
			'etsy' => isset( $stored['etsy'] ) ? (string) $stored['etsy'] : null,
		);
	}

	/**
	 * Fee lines for an order (detail UI).
	 *
	 * @param int $order_id Order PK.
	 * @return object[]
	 */
	public static function list_order_fees( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return array();
		}

		$table = SOM_DB::table( 'order_platform_fees' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC",
				$order_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Recurring expenses list (admin).
	 *
	 * @param array<string, mixed> $args Optional listing_id, channel_id, limit, offset.
	 * @return array{rows:object[],total:int}
	 */
	public static function list_recurring( array $args = array() ) {
		global $wpdb;

		$table     = SOM_DB::table( 'recurring_platform_expenses' );
		$listings  = SOM_DB::table( 'listings' );
		$channels  = SOM_DB::table( 'channels' );
		$where     = array( '1=1' );
		$params    = array();

		if ( ! empty( $args['listing_id'] ) ) {
			$where[]  = 'e.listing_id = %d';
			$params[] = (int) $args['listing_id'];
		}
		if ( ! empty( $args['channel_id'] ) ) {
			$where[]  = 'e.channel_id = %d';
			$params[] = (int) $args['channel_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$limit     = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 100;
		$offset    = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$count_sql = "SELECT COUNT(*) FROM {$table} e WHERE {$where_sql}";
		$list_sql  = "SELECT e.*, c.display_name AS channel_name, c.slug AS channel_slug,
				l.title AS listing_title, l.external_listing_id
			FROM {$table} e
			INNER JOIN {$channels} c ON c.id = e.channel_id
			LEFT JOIN {$listings} l ON l.id = e.listing_id
			WHERE {$where_sql}
			ORDER BY e.incurred_date DESC, e.id DESC
			LIMIT %d OFFSET %d";

		$list_params = array_merge( $params, array( $limit, $offset ) );

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- where built with placeholders.
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Admin URL for recurring expenses.
	 *
	 * @param array<string, scalar> $args Extra query args.
	 * @return string
	 */
	public static function recurring_list_url( array $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => 'som-recurring-platform-expenses' ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param int $lookback_days Days when cursor is null.
	 * @return array<string, mixed>
	 */
	private static function run( $lookback_days ) {
		$summary = array(
			'inserted'  => 0,
			'skipped'   => 0,
			'unmatched' => 0,
			'ignored'   => 0,
			'errors'    => array(),
		);

		$cursors = self::get_cursors();
		$now     = gmdate( 'Y-m-d H:i:s' );

		foreach ( array( 'ebay', 'etsy' ) as $slug ) {
			$channel = SOM_Channels::get_by_slug( $slug );
			if ( ! $channel || ! (int) $channel->is_active ) {
				continue;
			}
			if ( ! SOM_Channels::is_connected( $slug ) ) {
				continue;
			}

			$window = self::resolve_window( $cursors[ $slug ] ?? null, $lookback_days );
			$result = self::sync_channel( $slug, (int) $channel->id, $window['from'], $window['to'] );

			if ( is_wp_error( $result ) ) {
				$summary['errors'][] = $slug . ': ' . $result->get_error_message();
				continue;
			}

			$summary['inserted']  += (int) $result['inserted'];
			$summary['skipped']   += (int) $result['skipped'];
			$summary['unmatched'] += (int) $result['unmatched'];
			$summary['ignored']   += (int) $result['ignored'];

			$cursors[ $slug ] = $now;
		}

		update_option( self::CURSOR_OPTION, $cursors, false );

		$error_text = $summary['errors'] ? implode( '; ', $summary['errors'] ) : '';
		$message    = sprintf(
			/* translators: 1: inserted, 2: skipped duplicates, 3: unmatched, 4: ignored */
			__( 'Fees: %1$d inserted, %2$d duplicates skipped, %3$d unmatched (retry later), %4$d ignored.', 'order-machine' ),
			$summary['inserted'],
			$summary['skipped'],
			$summary['unmatched'],
			$summary['ignored']
		);
		if ( $error_text ) {
			$message .= ' ' . $error_text;
		}

		update_option(
			self::STATUS_OPTION,
			array(
				'last_run_at'  => $now,
				'last_error'   => $error_text,
				'last_summary' => $message,
				'inserted'     => $summary['inserted'],
				'skipped'      => $summary['skipped'],
				'unmatched'    => $summary['unmatched'],
				'ignored'      => $summary['ignored'],
			),
			false
		);

		$summary['message'] = $message;
		$summary['ok']      = empty( $summary['errors'] );

		return $summary;
	}

	/**
	 * @param string|null $cursor         UTC datetime or null.
	 * @param int         $lookback_days  Days.
	 * @return array{from:string,to:string}
	 */
	private static function resolve_window( $cursor, $lookback_days ) {
		$to = gmdate( 'Y-m-d H:i:s' );

		if ( $cursor ) {
			$ts = strtotime( $cursor . ' UTC' );
			if ( false === $ts ) {
				$ts = time() - ( $lookback_days * DAY_IN_SECONDS );
			} else {
				$ts = max( 0, $ts - 5 * MINUTE_IN_SECONDS );
			}
			$from = gmdate( 'Y-m-d H:i:s', $ts );
		} else {
			$from = gmdate( 'Y-m-d H:i:s', time() - ( $lookback_days * DAY_IN_SECONDS ) );
		}

		return array(
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * @param string $slug       ebay|etsy.
	 * @param int    $channel_id Channel PK.
	 * @param string $from_utc   Window start.
	 * @param string $to_utc     Window end.
	 * @return array<string, int>|WP_Error
	 */
	private static function sync_channel( $slug, $channel_id, $from_utc, $to_utc ) {
		if ( 'ebay' === $slug ) {
			$entries = SOM_Channel_Ebay::fetch_platform_fees( $from_utc, $to_utc );
		} else {
			$entries = SOM_Channel_Etsy::fetch_platform_fees( $from_utc, $to_utc );
		}

		if ( is_wp_error( $entries ) ) {
			return $entries;
		}

		$counts = array(
			'inserted'  => 0,
			'skipped'   => 0,
			'unmatched' => 0,
			'ignored'   => 0,
		);

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$kind = isset( $entry['kind'] ) ? (string) $entry['kind'] : 'ignore';
			if ( 'ignore' === $kind ) {
				++$counts['ignored'];
				continue;
			}

			$external_entry_id = isset( $entry['external_entry_id'] ) ? (string) $entry['external_entry_id'] : '';
			if ( '' === $external_entry_id ) {
				++$counts['ignored'];
				continue;
			}

			if ( 'order' === $kind ) {
				$result = self::upsert_order_fee( $channel_id, $entry );
			} elseif ( 'recurring' === $kind ) {
				$result = self::upsert_recurring( $channel_id, $entry );
			} else {
				++$counts['ignored'];
				continue;
			}

			if ( 'inserted' === $result ) {
				++$counts['inserted'];
			} elseif ( 'skipped' === $result ) {
				++$counts['skipped'];
			} else {
				++$counts['unmatched'];
			}
		}

		return $counts;
	}

	/**
	 * @param int                  $channel_id Channel PK.
	 * @param array<string, mixed> $entry      Normalized entry.
	 * @return string inserted|skipped|unmatched
	 */
	private static function upsert_order_fee( $channel_id, array $entry ) {
		global $wpdb;

		$external_order_id = isset( $entry['external_order_id'] ) ? (string) $entry['external_order_id'] : '';
		if ( '' === $external_order_id ) {
			return 'unmatched';
		}

		$order_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . SOM_DB::table( 'orders' ) . ' WHERE channel_id = %d AND external_order_id = %s LIMIT 1',
				$channel_id,
				$external_order_id
			)
		);
		if ( $order_id < 1 ) {
			return 'unmatched';
		}

		$table             = SOM_DB::table( 'order_platform_fees' );
		$external_entry_id = (string) $entry['external_entry_id'];
		$exists            = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE channel_id = %d AND external_entry_id = %s LIMIT 1",
				$channel_id,
				$external_entry_id
			)
		);
		if ( $exists > 0 ) {
			return 'skipped';
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		$ok  = $wpdb->insert(
			$table,
			array(
				'order_id'          => $order_id,
				'channel_id'        => $channel_id,
				'external_entry_id' => $external_entry_id,
				'fee_type'          => sanitize_key( (string) ( $entry['fee_type'] ?? 'fee' ) ),
				'amount'            => (float) ( $entry['amount'] ?? 0 ),
				'currency'          => strtoupper( substr( (string) ( $entry['currency'] ?? 'GBP' ), 0, 3 ) ),
				'raw_payload'       => isset( $entry['raw'] ) ? wp_json_encode( $entry['raw'] ) : null,
				'synced_at'         => $now,
				'created_at'        => $now,
			),
			array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
		);

		return false === $ok ? 'skipped' : 'inserted';
	}

	/**
	 * @param int                  $channel_id Channel PK.
	 * @param array<string, mixed> $entry      Normalized entry.
	 * @return string inserted|skipped|unmatched
	 */
	private static function upsert_recurring( $channel_id, array $entry ) {
		global $wpdb;

		$table             = SOM_DB::table( 'recurring_platform_expenses' );
		$external_entry_id = (string) $entry['external_entry_id'];
		$exists            = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE channel_id = %d AND external_entry_id = %s LIMIT 1",
				$channel_id,
				$external_entry_id
			)
		);
		if ( $exists > 0 ) {
			return 'skipped';
		}

		$listing_id = null;
		if ( ! empty( $entry['external_listing_id'] ) ) {
			$found = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . SOM_DB::table( 'listings' ) . ' WHERE channel_id = %d AND external_listing_id = %s LIMIT 1',
					$channel_id,
					(string) $entry['external_listing_id']
				)
			);
			if ( $found > 0 ) {
				$listing_id = $found;
			}
		}

		$incurred = isset( $entry['incurred_date'] ) ? (string) $entry['incurred_date'] : gmdate( 'Y-m-d' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $incurred ) ) {
			$ts = strtotime( $incurred . ' UTC' );
			$incurred = $ts ? gmdate( 'Y-m-d', $ts ) : gmdate( 'Y-m-d' );
		}

		$row = array(
			'channel_id'        => $channel_id,
			'external_entry_id' => $external_entry_id,
			'fee_type'          => sanitize_key( (string) ( $entry['fee_type'] ?? 'listing_fee' ) ),
			'amount'            => (float) ( $entry['amount'] ?? 0 ),
			'incurred_date'     => $incurred,
			'notes'             => isset( $entry['notes'] ) ? (string) $entry['notes'] : null,
			'created_at'        => gmdate( 'Y-m-d H:i:s' ),
		);
		$formats = array( '%d', '%s', '%s', '%f', '%s', '%s', '%s' );
		if ( null !== $listing_id ) {
			$row['listing_id'] = $listing_id;
			$formats[]         = '%d';
		}

		$ok = $wpdb->insert( $table, $row, $formats );

		return false === $ok ? 'skipped' : 'inserted';
	}
}
