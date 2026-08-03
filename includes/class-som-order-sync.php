<?php
/**
 * Order sync orchestrator — poll channels, de-dup, listing match.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syncs eBay/Etsy orders into `wp_som_orders` / `wp_som_order_items`.
 *
 * Re-sync updates order header + raw_payload only; line items are immutable after create.
 * Workflow assignment and material decrement run on create only (incremental sync;
 * Import history does not reserve stock).
 */
class SOM_Order_Sync {

	const STATUS_OPTION = 'som_sync_status';

	/** First incremental lookback when `last_synced_at` is null (days). */
	const DEFAULT_LOOKBACK_DAYS = 7;

	/**
	 * Run incremental sync (cron / Sync now).
	 *
	 * @return array<string, mixed> Summary.
	 */
	public static function sync_incremental() {
		return self::run( 'incremental', self::DEFAULT_LOOKBACK_DAYS );
	}

	/**
	 * Backfill older orders (Import history).
	 *
	 * @param int $days Lookback window (e.g. 30 or 90).
	 * @return array<string, mixed> Summary.
	 */
	public static function sync_history( $days ) {
		$days = max( 1, (int) $days );
		return self::run( 'history', $days );
	}

	/**
	 * Create an order from an external REST payload (Amazon-email / n8n groundwork).
	 *
	 * Default channel slug is `external`. Duplicate (channel + external_order_id) → WP_Error 409.
	 * On success: inserts items, assigns workflow, reserves materials (same as incremental sync create).
	 *
	 * @param array<string, mixed> $payload Request body.
	 * @return int|WP_Error New order ID.
	 */
	public static function create_from_external( array $payload ) {
		$channel_slug = isset( $payload['channel'] ) ? sanitize_key( (string) $payload['channel'] ) : 'external';
		if ( '' === $channel_slug ) {
			$channel_slug = 'external';
		}

		SOM_Channels::ensure_rows();
		$channel = SOM_Channels::get_by_slug( $channel_slug );
		if ( ! $channel ) {
			return new WP_Error(
				'som_unknown_channel',
				sprintf(
					/* translators: %s: channel slug */
					__( 'Unknown channel: %s', 'order-machine' ),
					$channel_slug
				),
				array( 'status' => 400 )
			);
		}

		$external_id = isset( $payload['external_order_id'] ) ? sanitize_text_field( (string) $payload['external_order_id'] ) : '';
		if ( '' === $external_id ) {
			return new WP_Error(
				'som_missing_external_order_id',
				__( 'external_order_id is required.', 'order-machine' ),
				array( 'status' => 400 )
			);
		}

		$items = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
		if ( ! $items ) {
			return new WP_Error(
				'som_missing_items',
				__( 'At least one order item is required.', 'order-machine' ),
				array( 'status' => 400 )
			);
		}

		$normalized = array(
			'external_order_id' => $external_id,
			'buyer_name'        => isset( $payload['buyer_name'] ) ? (string) $payload['buyer_name'] : '',
			'shipping_address'  => isset( $payload['shipping_address'] ) && is_array( $payload['shipping_address'] )
				? $payload['shipping_address']
				: array(),
			'order_date'        => isset( $payload['order_date'] ) ? (string) $payload['order_date'] : current_time( 'mysql', true ),
			'raw_payload'       => isset( $payload['raw_payload'] ) ? $payload['raw_payload'] : $payload,
			'items'             => array(),
		);

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$line = array(
				'external_listing_id'  => isset( $item['external_listing_id'] ) ? (string) $item['external_listing_id'] : '',
				'sku'                  => isset( $item['sku'] ) ? (string) $item['sku'] : '',
				'quantity'             => isset( $item['quantity'] ) ? (int) $item['quantity'] : 1,
				'personalisation_text' => isset( $item['personalisation_text'] ) ? $item['personalisation_text'] : null,
				'unit_price'           => array_key_exists( 'unit_price', $item ) ? $item['unit_price'] : null,
			);
			if ( isset( $item['product_id'] ) && (int) $item['product_id'] > 0 ) {
				$line['product_id'] = (int) $item['product_id'];
			}
			$normalized['items'][] = $line;
		}

		if ( ! $normalized['items'] ) {
			return new WP_Error(
				'som_missing_items',
				__( 'At least one valid order item is required.', 'order-machine' ),
				array( 'status' => 400 )
			);
		}

		global $wpdb;
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . SOM_DB::table( 'orders' ) . ' WHERE channel_id = %d AND external_order_id = %s LIMIT 1',
				(int) $channel->id,
				$external_id
			)
		);
		if ( $existing_id > 0 ) {
			return new WP_Error(
				'som_order_exists',
				__( 'An order with this external_order_id already exists for the channel.', 'order-machine' ),
				array( 'status' => 409 )
			);
		}

		$result = self::upsert_order( (int) $channel->id, $normalized, true );
		if ( 'created' !== $result ) {
			return new WP_Error(
				'som_order_create_failed',
				__( 'Could not create order.', 'order-machine' ),
				array( 'status' => 500 )
			);
		}

		$order_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . SOM_DB::table( 'orders' ) . ' WHERE channel_id = %d AND external_order_id = %s LIMIT 1',
				(int) $channel->id,
				$external_id
			)
		);
		if ( $order_id < 1 ) {
			return new WP_Error(
				'som_order_create_failed',
				__( 'Order was created but could not be loaded.', 'order-machine' ),
				array( 'status' => 500 )
			);
		}

		return $order_id;
	}

	/**
	 * Last sync status for Settings UI.
	 *
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
				'last_mode'    => null,
				'last_error'   => '',
				'last_summary' => '',
				'created'      => 0,
				'updated'      => 0,
				'skipped'      => 0,
			),
			$stored
		);
	}

	/**
	 * @param string $mode           incremental|history.
	 * @param int    $lookback_days  Days used when last_synced_at is null, or for history mode.
	 * @return array<string, mixed>
	 */
	private static function run( $mode, $lookback_days ) {
		$summary = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		// Material reservation only on incremental creates — not Import history backfill.
		$apply_stock = ( 'incremental' === $mode );

		foreach ( array( 'ebay', 'etsy' ) as $slug ) {
			$channel = SOM_Channels::get_by_slug( $slug );
			if ( ! $channel || ! (int) $channel->is_active ) {
				continue;
			}

			if ( ! SOM_Channels::is_connected( $slug ) ) {
				continue;
			}

			$window = self::resolve_window( $channel, $mode, $lookback_days );
			$result = self::sync_channel( $slug, (int) $channel->id, $window['from'], $window['to'], $apply_stock );

			if ( is_wp_error( $result ) ) {
				$summary['errors'][] = $slug . ': ' . $result->get_error_message();
				continue;
			}

			$summary['created'] += (int) $result['created'];
			$summary['updated'] += (int) $result['updated'];
			$summary['skipped'] += (int) $result['skipped'];

			SOM_Channels::set_last_synced_at( $slug, gmdate( 'Y-m-d H:i:s' ) );
		}

		$error_text = $summary['errors'] ? implode( '; ', $summary['errors'] ) : '';
		$message    = sprintf(
			/* translators: 1: created count, 2: updated count */
			__( 'Created %1$d, updated %2$d.', 'order-machine' ),
			$summary['created'],
			$summary['updated']
		);
		if ( $error_text ) {
			$message .= ' ' . $error_text;
		}

		update_option(
			self::STATUS_OPTION,
			array(
				'last_run_at'  => gmdate( 'Y-m-d H:i:s' ),
				'last_mode'    => $mode,
				'last_error'   => $error_text,
				'last_summary' => $message,
				'created'      => $summary['created'],
				'updated'      => $summary['updated'],
				'skipped'      => $summary['skipped'],
			),
			false
		);

		$summary['message'] = $message;
		$summary['ok']      = empty( $summary['errors'] );

		return $summary;
	}

	/**
	 * @param object $channel       Channel row.
	 * @param string $mode          incremental|history.
	 * @param int    $lookback_days Days.
	 * @return array{from:string,to:string} UTC datetimes Y-m-d H:i:s.
	 */
	private static function resolve_window( $channel, $mode, $lookback_days ) {
		$to = gmdate( 'Y-m-d H:i:s' );

		if ( 'history' === $mode ) {
			$from = gmdate( 'Y-m-d H:i:s', time() - ( $lookback_days * DAY_IN_SECONDS ) );
			return array(
				'from' => $from,
				'to'   => $to,
			);
		}

		if ( ! empty( $channel->last_synced_at ) ) {
			// Small overlap to avoid missing edge updates.
			$ts = strtotime( $channel->last_synced_at . ' UTC' );
			if ( false === $ts ) {
				$ts = time() - ( self::DEFAULT_LOOKBACK_DAYS * DAY_IN_SECONDS );
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
	 * @param string $slug        ebay|etsy.
	 * @param int    $channel_id  Channel PK.
	 * @param string $from        UTC datetime.
	 * @param string $to          UTC datetime.
	 * @param bool   $apply_stock Whether to reserve materials on create.
	 * @return array{created:int,updated:int,skipped:int}|\WP_Error
	 */
	private static function sync_channel( $slug, $channel_id, $from, $to, $apply_stock = true ) {
		if ( 'ebay' === $slug ) {
			$orders = SOM_Channel_Ebay::fetch_orders( $from, $to );
		} else {
			$orders = SOM_Channel_Etsy::fetch_orders( $from, $to );
		}

		if ( is_wp_error( $orders ) ) {
			return $orders;
		}

		$created = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $orders as $order ) {
			$result = self::upsert_order( $channel_id, $order, $apply_stock );
			if ( 'created' === $result ) {
				++$created;
			} elseif ( 'updated' === $result ) {
				++$updated;
			} else {
				++$skipped;
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
		);
	}

	/**
	 * Insert or update one normalized order.
	 *
	 * @param int                  $channel_id  Channel PK.
	 * @param array<string, mixed> $order       Normalized order.
	 * @param bool                 $apply_stock Whether to reserve materials on create.
	 * @return string created|updated|skipped
	 */
	private static function upsert_order( $channel_id, array $order, $apply_stock = true ) {
		global $wpdb;

		$external_id = isset( $order['external_order_id'] ) ? (string) $order['external_order_id'] : '';
		if ( '' === $external_id ) {
			return 'skipped';
		}

		$orders_table = SOM_DB::table( 'orders' );
		$now          = current_time( 'mysql', true );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$orders_table} WHERE channel_id = %d AND external_order_id = %s LIMIT 1",
				$channel_id,
				$external_id
			)
		);

		$shipping_json = wp_json_encode( isset( $order['shipping_address'] ) ? $order['shipping_address'] : array() );
		$raw_json      = wp_json_encode( isset( $order['raw_payload'] ) ? $order['raw_payload'] : $order );
		$buyer_name    = isset( $order['buyer_name'] ) ? sanitize_text_field( (string) $order['buyer_name'] ) : '';
		$order_date    = isset( $order['order_date'] ) ? (string) $order['order_date'] : $now;

		if ( $existing_id ) {
			$wpdb->update(
				$orders_table,
				array(
					'order_date'       => $order_date,
					'buyer_name'       => $buyer_name,
					'shipping_address' => $shipping_json,
					'raw_payload'      => $raw_json,
					'updated_at'       => $now,
				),
				array( 'id' => (int) $existing_id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			// Line items immutable after create.
			return 'updated';
		}

		$inserted = $wpdb->insert(
			$orders_table,
			array(
				'channel_id'         => $channel_id,
				'external_order_id'  => $external_id,
				'order_date'         => $order_date,
				'buyer_name'         => $buyer_name,
				'shipping_address'   => $shipping_json,
				'current_step_id'    => null,
				'is_complete'        => 0,
				'raw_payload'        => $raw_json,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return 'skipped';
		}

		$order_id = (int) $wpdb->insert_id;
		$items    = isset( $order['items'] ) && is_array( $order['items'] ) ? $order['items'] : array();

		foreach ( $items as $item ) {
			self::insert_item( $order_id, $channel_id, $item );
		}

		SOM_Workflow_Engine::assign_on_create( $order_id );

		if ( $apply_stock ) {
			SOM_Material_Stock::decrement_on_create( $order_id );
			SOM_Budgets::fund_on_create( $order_id );
		}

		return 'created';
	}

	/**
	 * @param int                  $order_id   New order PK.
	 * @param int                  $channel_id Channel PK.
	 * @param array<string, mixed> $item       Normalized line item.
	 * @return void
	 */
	private static function insert_item( $order_id, $channel_id, array $item ) {
		global $wpdb;

		$product_id = null;
		if ( isset( $item['product_id'] ) && (int) $item['product_id'] > 0 ) {
			$product_id = (int) $item['product_id'];
		} else {
			$listing_keys = array();
			if ( ! empty( $item['external_listing_id'] ) ) {
				$listing_keys[] = (string) $item['external_listing_id'];
			}
			if ( ! empty( $item['sku'] ) ) {
				$listing_keys[] = (string) $item['sku'];
			}
			$product_id = self::match_product_id( $channel_id, $listing_keys );
		}

		$wpdb->insert(
			SOM_DB::table( 'order_items' ),
			array(
				'order_id'             => $order_id,
				'product_id'           => $product_id,
				'quantity'             => max( 1, (int) ( $item['quantity'] ?? 1 ) ),
				'personalisation_text' => isset( $item['personalisation_text'] ) && null !== $item['personalisation_text'] && '' !== $item['personalisation_text']
					? (string) $item['personalisation_text']
					: null,
				'unit_price'           => array_key_exists( 'unit_price', $item ) && null !== $item['unit_price']
					? (float) $item['unit_price']
					: null,
			),
			array(
				'%d',
				null === $product_id ? '%s' : '%d',
				'%d',
				'%s',
				'%f',
			)
		);
	}

	/**
	 * Match listing external id (or SKU) to product via `wp_som_listings`.
	 *
	 * @param int      $channel_id Channel PK.
	 * @param string[] $keys       Candidate external_listing_id values.
	 * @return int|null
	 */
	private static function match_product_id( $channel_id, array $keys ) {
		global $wpdb;

		$keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );
		if ( ! $keys ) {
			return null;
		}

		$table = SOM_DB::table( 'listings' );
		foreach ( $keys as $key ) {
			$product_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT product_id FROM {$table} WHERE channel_id = %d AND external_listing_id = %s LIMIT 1",
					$channel_id,
					$key
				)
			);
			if ( $product_id ) {
				return (int) $product_id;
			}
		}

		return null;
	}
}
