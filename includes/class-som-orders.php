<?php
/**
 * Order query helpers for admin list/detail.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only order lookups and display helpers (Sprint 4).
 */
class SOM_Orders {

	/**
	 * Orders per page on the admin list.
	 */
	const PER_PAGE = 20;

	/**
	 * Whether a stored raw payload indicates cancellation.
	 *
	 * @param string|array|null $raw_payload JSON string or decoded array.
	 * @param string            $channel_slug ebay|etsy.
	 * @return bool
	 */
	public static function is_cancelled( $raw_payload, $channel_slug ) {
		$data = $raw_payload;
		if ( is_string( $raw_payload ) ) {
			$data = json_decode( $raw_payload, true );
		}
		if ( ! is_array( $data ) ) {
			return false;
		}

		$slug = sanitize_key( $channel_slug );

		if ( 'ebay' === $slug ) {
			$status = isset( $data['orderFulfillmentStatus'] ) ? strtoupper( (string) $data['orderFulfillmentStatus'] ) : '';
			if ( 'CANCELLED' === $status || 'CANCELED' === $status ) {
				return true;
			}
			$cancel_state = isset( $data['cancelStatus']['cancelState'] ) ? strtoupper( (string) $data['cancelStatus']['cancelState'] ) : '';
			return in_array( $cancel_state, array( 'CANCELED', 'CANCELLED' ), true );
		}

		if ( 'etsy' === $slug ) {
			$status = isset( $data['status'] ) ? strtolower( (string) $data['status'] ) : '';
			return in_array( $status, array( 'canceled', 'cancelled' ), true );
		}

		return false;
	}

	/**
	 * SQL fragment matching cancelled payloads (best-effort on stored JSON).
	 *
	 * @param string $orders_alias   Orders table alias.
	 * @param string $channels_alias Channels table alias.
	 * @return string
	 */
	public static function cancelled_sql( $orders_alias = 'o', $channels_alias = 'c' ) {
		$o = preg_replace( '/[^a-z_]/', '', $orders_alias );
		$c = preg_replace( '/[^a-z_]/', '', $channels_alias );

		return "(
			( {$c}.slug = 'ebay' AND (
				{$o}.raw_payload LIKE '%\"orderFulfillmentStatus\":\"CANCELLED\"%'
				OR {$o}.raw_payload LIKE '%\"orderFulfillmentStatus\":\"CANCELED\"%'
				OR {$o}.raw_payload LIKE '%\"cancelState\":\"CANCELED\"%'
				OR {$o}.raw_payload LIKE '%\"cancelState\":\"CANCELLED\"%'
			) )
			OR ( {$c}.slug = 'etsy' AND (
				{$o}.raw_payload LIKE '%\"status\":\"canceled\"%'
				OR {$o}.raw_payload LIKE '%\"status\":\"cancelled\"%'
			) )
		)";
	}

	/**
	 * Format shipping address JSON for display (multiline).
	 *
	 * @param string|array|null $address JSON or array.
	 * @return string
	 */
	public static function format_address( $address ) {
		$data = $address;
		if ( is_string( $address ) ) {
			$data = json_decode( $address, true );
		}
		if ( ! is_array( $data ) ) {
			return '';
		}

		$lines = array();
		foreach ( array( 'full_name', 'line1', 'line2', 'city', 'state', 'postcode', 'country' ) as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				$lines[] = (string) $data[ $key ];
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Query orders for the admin list.
	 *
	 * @param array<string, mixed> $args Filters: status, channel, date_from, date_to, s, paged.
	 * @return array{orders: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'    => '',
			'channel'   => '',
			'date_from' => '',
			'date_to'   => '',
			's'         => '',
			'paged'     => 1,
			'per_page'  => self::PER_PAGE,
		);
		$args     = wp_parse_args( $args, $defaults );

		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$items_t    = SOM_DB::table( 'order_items' );
		$products_t = SOM_DB::table( 'products' );

		$where  = array( '1=1' );
		$params = array();

		$status = sanitize_key( (string) $args['status'] );
		if ( 'open' === $status ) {
			$where[] = 'o.is_complete = 0';
		} elseif ( 'complete' === $status ) {
			$where[] = 'o.is_complete = 1';
		} elseif ( 'needs_mapping' === $status ) {
			$where[] = "EXISTS ( SELECT 1 FROM {$items_t} oi_um WHERE oi_um.order_id = o.id AND oi_um.product_id IS NULL )";
		} elseif ( 'needs_workflow' === $status ) {
			$cancelled = self::cancelled_sql( 'o', 'c' );
			$progress_t = SOM_DB::table( 'order_step_progress' );
			$where[]    = "o.is_complete = 0 AND o.current_step_id IS NULL
				AND NOT EXISTS ( SELECT 1 FROM {$progress_t} osp WHERE osp.order_id = o.id )
				AND NOT {$cancelled}";
		} elseif ( 'cancelled' === $status ) {
			$where[] = self::cancelled_sql( 'o', 'c' );
		}

		$channel = sanitize_key( (string) $args['channel'] );
		if ( $channel && isset( SOM_Channels::known()[ $channel ] ) ) {
			$where[]  = 'c.slug = %s';
			$params[] = $channel;
		}

		$date_from = self::sanitize_date( (string) $args['date_from'] );
		if ( $date_from ) {
			$where[]  = 'o.order_date >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		$date_to = self::sanitize_date( (string) $args['date_to'] );
		if ( $date_to ) {
			$where[]  = 'o.order_date <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( o.buyer_name LIKE %s OR o.external_order_id LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$orders_t} o
			INNER JOIN {$channels_t} c ON c.id = o.channel_id
			WHERE {$where_sql}";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE built with placeholders.
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$paged    = max( 1, min( (int) $args['paged'], $pages ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$unmatched_label = __( 'Unmatched', 'order-machine' );
		$cancelled_expr  = self::cancelled_sql( 'o', 'c' );

		$list_sql = "SELECT
				o.id,
				o.channel_id,
				o.external_order_id,
				o.order_date,
				o.buyer_name,
				o.is_complete,
				o.current_step_id,
				c.slug AS channel_slug,
				c.display_name AS channel_name,
				( SELECT COUNT(*) FROM {$items_t} oi_c WHERE oi_c.order_id = o.id AND oi_c.product_id IS NULL ) AS unmatched_count,
				( SELECT COUNT(*) FROM " . SOM_DB::table( 'order_step_progress' ) . " osp_c WHERE osp_c.order_id = o.id ) AS progress_count,
				( SELECT s.name FROM " . SOM_DB::table( 'workflow_steps' ) . " s WHERE s.id = o.current_step_id LIMIT 1 ) AS current_step_name,
				( SELECT GROUP_CONCAT( COALESCE( p.name, %s ) ORDER BY oi_p.id SEPARATOR ', ' )
					FROM {$items_t} oi_p
					LEFT JOIN {$products_t} p ON p.id = oi_p.product_id
					WHERE oi_p.order_id = o.id ) AS products_summary,
				( SELECT GROUP_CONCAT( NULLIF( oi_x.personalisation_text, '' ) ORDER BY oi_x.id SEPARATOR ' / ' )
					FROM {$items_t} oi_x
					WHERE oi_x.order_id = o.id ) AS personalisation_summary,
				CASE WHEN {$cancelled_expr} THEN 1 ELSE 0 END AS is_cancelled
			FROM {$orders_t} o
			INNER JOIN {$channels_t} c ON c.id = o.channel_id
			WHERE {$where_sql}
			ORDER BY o.order_date DESC, o.id DESC
			LIMIT %d OFFSET %d";

		$list_params   = array_merge( array( $unmatched_label ), $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$orders        = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );
		if ( ! is_array( $orders ) ) {
			$orders = array();
		}

		return array(
			'orders' => $orders,
			'total'  => $total,
			'pages'  => $pages,
			'paged'  => $paged,
		);
	}

	/**
	 * Fetch one order with items for the detail screen.
	 *
	 * @param int $order_id Order PK.
	 * @return object|null Order object with items[] or null.
	 */
	public static function get( $order_id ) {
		global $wpdb;

		$order_id   = (int) $order_id;
		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$items_t    = SOM_DB::table( 'order_items' );
		$products_t = SOM_DB::table( 'products' );

		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT o.*, c.slug AS channel_slug, c.display_name AS channel_name
				FROM {$orders_t} o
				INNER JOIN {$channels_t} c ON c.id = o.channel_id
				WHERE o.id = %d
				LIMIT 1",
				$order_id
			)
		);

		if ( ! $order ) {
			return null;
		}

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.*, p.name AS product_name, p.sku AS product_sku
				FROM {$items_t} oi
				LEFT JOIN {$products_t} p ON p.id = oi.product_id
				WHERE oi.order_id = %d
				ORDER BY oi.id ASC",
				$order_id
			)
		);

		$order->items         = is_array( $items ) ? $items : array();
		$order->is_cancelled  = self::is_cancelled( $order->raw_payload, $order->channel_slug );
		$order->has_unmatched = false;
		foreach ( $order->items as $item ) {
			if ( null === $item->product_id || '' === $item->product_id ) {
				$order->has_unmatched = true;
				break;
			}
		}

		$order->workflow_progress   = SOM_Workflow_Engine::get_progress( $order_id );
		$order->workflow_unassigned = SOM_Workflow_Engine::unassigned_reason( $order );
		$order->current_step_name   = '';
		if ( ! empty( $order->current_step_id ) ) {
			foreach ( $order->workflow_progress as $row ) {
				if ( (int) $row->workflow_step_id === (int) $order->current_step_id ) {
					$order->current_step_name = (string) $row->step_name;
					break;
				}
			}
		}

		return $order;
	}

	/**
	 * Admin URL for the orders list with optional query args.
	 *
	 * @param array<string, scalar> $args Extra query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		$args = array_merge( array( 'page' => 'som-orders' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Admin URL for a single order detail.
	 *
	 * @param int $order_id Order PK.
	 * @return string
	 */
	public static function detail_url( $order_id ) {
		return self::list_url( array( 'order_id' => (int) $order_id ) );
	}

	/**
	 * Validate Y-m-d date string.
	 *
	 * @param string $date Date string.
	 * @return string Empty if invalid.
	 */
	private static function sanitize_date( $date ) {
		$date = trim( $date );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}
		$parts = explode( '-', $date );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return '';
		}
		return $date;
	}
}
