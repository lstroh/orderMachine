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
	 * Board volume: warn when matching open orders reach this count.
	 */
	const BOARD_WARN = 200;

	/**
	 * Board volume: hard cap on cards rendered (oldest kept).
	 */
	const BOARD_CAP = 500;

	/**
	 * User meta: pinned order IDs on the Order Board.
	 */
	const BOARD_PINNED_META = 'som_board_pinned_orders';

	/**
	 * User meta: manual column name order on the Order Board.
	 */
	const BOARD_COLUMN_META = 'som_board_column_order';

	/**
	 * Column key for incomplete orders with no current step.
	 */
	const BOARD_UNASSIGNED_KEY = '__unassigned__';

	/**
	 * Drop-zone key for completing the final workflow step (board DnD).
	 */
	const BOARD_COMPLETE_KEY = '__complete__';

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
	 * Distinct workflow step names for the orders list "Current step" filter.
	 *
	 * @return array<int, string>
	 */
	public static function step_name_options() {
		global $wpdb;

		$steps_t = SOM_DB::table( 'workflow_steps' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$names = $wpdb->get_col( "SELECT DISTINCT name FROM {$steps_t} WHERE name <> '' ORDER BY name ASC" );

		if ( ! is_array( $names ) ) {
			return array();
		}

		$out = array();
		foreach ( $names as $name ) {
			$name = trim( (string) $name );
			if ( '' !== $name ) {
				$out[] = $name;
			}
		}

		return $out;
	}

	/**
	 * Query orders for the admin list.
	 *
	 * @param array<string, mixed> $args Filters: status, current_step, channel, date_from, date_to, s, paged.
	 * @return array{orders: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'       => '',
			'current_step' => '',
			'channel'      => '',
			'date_from'    => '',
			'date_to'      => '',
			's'            => '',
			'paged'        => 1,
			'per_page'     => self::PER_PAGE,
		);
		$args     = wp_parse_args( $args, $defaults );

		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$items_t    = SOM_DB::table( 'order_items' );
		$products_t = SOM_DB::table( 'products' );
		$steps_t    = SOM_DB::table( 'workflow_steps' );

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

		$current_step = sanitize_text_field( (string) $args['current_step'] );
		if ( '' !== $current_step ) {
			$where[]  = "EXISTS (
				SELECT 1 FROM {$steps_t} s_cur
				WHERE s_cur.id = o.current_step_id AND s_cur.name = %s
			)";
			$params[] = $current_step;
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

		$order->stock_summary = SOM_Material_Stock::get_order_summary( $order_id );
		$order->platform_fees = SOM_Platform_Fee_Sync::list_order_fees( $order_id );

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
	 * Admin URL for the Order Board.
	 *
	 * @param array<string, scalar> $args Extra query args.
	 * @return string
	 */
	public static function board_url( array $args = array() ) {
		$args = array_merge( array( 'page' => 'som-orders-board' ), $args );
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
	 * Query incomplete non-cancelled orders for the Order Board (oldest first, capped).
	 *
	 * @param array<string, mixed> $args Filters: channel, product_id, workflow_template_id, s.
	 * @return array{orders: array<int, object>, total: int, capped: bool, warn: bool}
	 */
	public static function query_board( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'channel'              => '',
			'product_id'           => 0,
			'workflow_template_id' => 0,
			's'                    => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$items_t    = SOM_DB::table( 'order_items' );
		$products_t = SOM_DB::table( 'products' );
		$steps_t    = SOM_DB::table( 'workflow_steps' );
		$progress_t = SOM_DB::table( 'order_step_progress' );

		$cancelled = self::cancelled_sql( 'o', 'c' );
		$where     = array( 'o.is_complete = 0', "NOT {$cancelled}" );
		$params    = array();

		$channel = sanitize_key( (string) $args['channel'] );
		if ( $channel && isset( SOM_Channels::known()[ $channel ] ) ) {
			$where[]  = 'c.slug = %s';
			$params[] = $channel;
		}

		$product_id = (int) $args['product_id'];
		if ( $product_id > 0 ) {
			$where[]  = "EXISTS (
				SELECT 1 FROM {$items_t} oi_pf
				WHERE oi_pf.order_id = o.id AND oi_pf.product_id = %d
			)";
			$params[] = $product_id;
		}

		$workflow_id = (int) $args['workflow_template_id'];
		if ( $workflow_id > 0 ) {
			$where[]  = "EXISTS (
				SELECT 1 FROM {$items_t} oi_wf
				INNER JOIN {$products_t} p_wf ON p_wf.id = oi_wf.product_id
				WHERE oi_wf.order_id = o.id
					AND oi_wf.id = (
						SELECT MIN( oi_min.id ) FROM {$items_t} oi_min
						WHERE oi_min.order_id = o.id AND oi_min.product_id IS NOT NULL
					)
					AND p_wf.workflow_template_id = %d
			)";
			$params[] = $workflow_id;
		}

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = "( o.buyer_name LIKE %s OR o.external_order_id LIKE %s OR EXISTS (
				SELECT 1 FROM {$items_t} oi_s
				WHERE oi_s.order_id = o.id AND oi_s.personalisation_text LIKE %s
			) )";
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$orders_t} o
			INNER JOIN {$channels_t} c ON c.id = o.channel_id
			WHERE {$where_sql}";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$unmatched_label = __( 'Unmatched', 'order-machine' );
		$cap             = self::BOARD_CAP;

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
				( SELECT s.name FROM {$steps_t} s WHERE s.id = o.current_step_id LIMIT 1 ) AS current_step_name,
				( SELECT s.step_order FROM {$steps_t} s WHERE s.id = o.current_step_id LIMIT 1 ) AS current_step_order,
				( SELECT osp.status FROM {$progress_t} osp
					WHERE osp.order_id = o.id AND osp.workflow_step_id = o.current_step_id
					LIMIT 1 ) AS progress_status,
				( SELECT osp.started_at FROM {$progress_t} osp
					WHERE osp.order_id = o.id AND osp.workflow_step_id = o.current_step_id
					LIMIT 1 ) AS step_started_at,
				( SELECT oi_p.product_id FROM {$items_t} oi_p
					WHERE oi_p.order_id = o.id AND oi_p.product_id IS NOT NULL
					ORDER BY oi_p.id ASC LIMIT 1 ) AS primary_product_id,
				( SELECT p_prim.workflow_template_id FROM {$items_t} oi_prim
					INNER JOIN {$products_t} p_prim ON p_prim.id = oi_prim.product_id
					WHERE oi_prim.order_id = o.id AND oi_prim.product_id IS NOT NULL
					ORDER BY oi_prim.id ASC LIMIT 1 ) AS workflow_template_id,
				( SELECT GROUP_CONCAT( COALESCE( p.name, %s ) ORDER BY oi_p.id SEPARATOR ', ' )
					FROM {$items_t} oi_p
					LEFT JOIN {$products_t} p ON p.id = oi_p.product_id
					WHERE oi_p.order_id = o.id ) AS products_summary,
				( SELECT GROUP_CONCAT( NULLIF( oi_x.personalisation_text, '' ) ORDER BY oi_x.id SEPARATOR ' / ' )
					FROM {$items_t} oi_x
					WHERE oi_x.order_id = o.id ) AS personalisation_summary
			FROM {$orders_t} o
			INNER JOIN {$channels_t} c ON c.id = o.channel_id
			WHERE {$where_sql}
			ORDER BY o.order_date ASC, o.id ASC
			LIMIT %d";

		$list_params = array_merge( array( $unmatched_label ), $params, array( $cap ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$orders      = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );
		if ( ! is_array( $orders ) ) {
			$orders = array();
		}

		foreach ( $orders as $order ) {
			$step_name         = trim( (string) $order->current_step_name );
			$order->column_key = ( empty( $order->current_step_id ) || '' === $step_name )
				? self::BOARD_UNASSIGNED_KEY
				: $step_name;
			$order->batch      = null;
			if ( 'waiting_batch' === (string) $order->progress_status ) {
				$order->batch = SOM_Batches::find_for_order( (int) $order->id );
			}
			self::attach_board_dnd_meta( $order );
		}

		return array(
			'orders' => $orders,
			'total'  => $total,
			'capped' => $total > $cap,
			'warn'   => $total >= self::BOARD_WARN,
		);
	}

	/**
	 * Attach DnD gating fields used by the Order Board (U2-5).
	 *
	 * @param object $order Board order row (mutated).
	 * @return void
	 */
	public static function attach_board_dnd_meta( $order ) {
		$order->can_advance    = false;
		$order->next_step_name = '';
		$order->is_last_step   = false;

		if ( empty( $order->current_step_id ) ) {
			return;
		}

		// Fast path: engine only allows mark-done when status is in_progress.
		if ( 'in_progress' !== (string) $order->progress_status ) {
			return;
		}

		$meta = SOM_Workflow_Engine::board_dnd_meta( (int) $order->id );
		$order->can_advance    = ! empty( $meta['can_advance'] );
		$order->next_step_name = (string) $meta['next_step_name'];
		$order->is_last_step   = ! empty( $meta['is_last_step'] );
	}

	/**
	 * Lowest step_order seen per step name (for board column auto-order).
	 *
	 * @return array<string, int> step name => min step_order
	 */
	public static function board_step_order_map() {
		global $wpdb;

		$steps_t = SOM_DB::table( 'workflow_steps' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT name, MIN(step_order) AS min_order FROM {$steps_t} WHERE name <> '' GROUP BY name"
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$map = array();
		foreach ( $rows as $row ) {
			$name = trim( (string) $row->name );
			if ( '' !== $name ) {
				$map[ $name ] = (int) $row->min_order;
			}
		}

		return $map;
	}

	/**
	 * Build ordered board columns from orders + per-user manual order.
	 *
	 * @param array<int, object> $orders Board orders from query_board.
	 * @param int                $user_id User ID (0 = current).
	 * @return array<int, array{key: string, label: string}>
	 */
	public static function board_columns( array $orders, $user_id = 0 ) {
		$present = array();
		foreach ( $orders as $order ) {
			$key = isset( $order->column_key ) ? (string) $order->column_key : self::BOARD_UNASSIGNED_KEY;
			if ( ! isset( $present[ $key ] ) ) {
				$present[ $key ] = true;
			}
			// Prefill empty next-step columns for draggable cards (U2-5).
			if ( ! empty( $order->can_advance ) && empty( $order->is_last_step ) ) {
				$next = trim( (string) ( $order->next_step_name ?? '' ) );
				if ( '' !== $next ) {
					$present[ $next ] = true;
				}
			}
		}

		if ( empty( $present ) ) {
			return array();
		}

		$auto_map = self::board_step_order_map();
		$keys     = array_keys( $present );

		usort(
			$keys,
			static function ( $a, $b ) use ( $auto_map ) {
				if ( self::BOARD_UNASSIGNED_KEY === $a ) {
					return -1;
				}
				if ( self::BOARD_UNASSIGNED_KEY === $b ) {
					return 1;
				}
				$oa = isset( $auto_map[ $a ] ) ? (int) $auto_map[ $a ] : PHP_INT_MAX;
				$ob = isset( $auto_map[ $b ] ) ? (int) $auto_map[ $b ] : PHP_INT_MAX;
				if ( $oa === $ob ) {
					return strcasecmp( $a, $b );
				}
				return $oa <=> $ob;
			}
		);

		$saved = self::get_board_column_order( $user_id );
		if ( $saved ) {
			$ordered = array();
			foreach ( $saved as $key ) {
				if ( isset( $present[ $key ] ) ) {
					$ordered[] = $key;
					unset( $present[ $key ] );
				}
			}
			foreach ( $keys as $key ) {
				if ( isset( $present[ $key ] ) ) {
					$ordered[] = $key;
				}
			}
			$keys = $ordered;
		}

		$columns = array();
		foreach ( $keys as $key ) {
			$columns[] = array(
				'key'   => $key,
				'label' => self::BOARD_UNASSIGNED_KEY === $key
					? __( 'Unassigned', 'order-machine' )
					: $key,
			);
		}

		return $columns;
	}

	/**
	 * Human time spent in the current step (e.g. "2h", "3d").
	 *
	 * @param string|null $started_at MySQL datetime.
	 * @return string
	 */
	public static function format_time_in_step( $started_at ) {
		$started_at = trim( (string) $started_at );
		if ( '' === $started_at ) {
			return '—';
		}

		$start = strtotime( $started_at . ' GMT' );
		if ( ! $start ) {
			$start = strtotime( $started_at );
		}
		if ( ! $start ) {
			return '—';
		}

		$seconds = max( 0, time() - $start );
		if ( $seconds < HOUR_IN_SECONDS ) {
			$mins = max( 1, (int) floor( $seconds / MINUTE_IN_SECONDS ) );
			return $mins . 'm';
		}
		if ( $seconds < 2 * DAY_IN_SECONDS ) {
			$hours = max( 1, (int) floor( $seconds / HOUR_IN_SECONDS ) );
			return $hours . 'h';
		}
		$days = max( 1, (int) floor( $seconds / DAY_IN_SECONDS ) );
		return $days . 'd';
	}

	/**
	 * Truncate personalisation preview for board cards.
	 *
	 * @param string $text Personalisation text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	public static function truncate_personalisation( $text, $max = 80 ) {
		$text = trim( (string) $text );
		$max  = max( 10, (int) $max );
		$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( '' === $text || $len <= $max ) {
			return $text;
		}
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max - 1 ) : substr( $text, 0, $max - 1 );
		return rtrim( $cut ) . '…';
	}

	/**
	 * Progress status label for board badges.
	 *
	 * @param string $status Progress status.
	 * @return string
	 */
	public static function progress_status_label( $status ) {
		$labels = array(
			'pending'        => __( 'Pending', 'order-machine' ),
			'in_progress'    => __( 'In progress', 'order-machine' ),
			'waiting_timer'  => __( 'Waiting (timer)', 'order-machine' ),
			'waiting_script' => __( 'Waiting (script)', 'order-machine' ),
			'waiting_batch'  => __( 'Waiting (batch)', 'order-machine' ),
			'error'          => __( 'Error', 'order-machine' ),
			'done'           => __( 'Done', 'order-machine' ),
		);
		$status = (string) $status;
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * @param int $user_id User ID (0 = current).
	 * @return array<int, int>
	 */
	public static function get_board_pinned_ids( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$raw     = get_user_meta( $user_id, self::BOARD_PINNED_META, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$ids = array();
		foreach ( $raw as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param array<int, int> $ids Order IDs.
	 * @param int             $user_id User ID (0 = current).
	 * @return void
	 */
	public static function set_board_pinned_ids( array $ids, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$clean   = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}
		update_user_meta( $user_id, self::BOARD_PINNED_META, array_values( array_unique( $clean ) ) );
	}

	/**
	 * Toggle pin for one order. Returns new pinned state.
	 *
	 * @param int $order_id Order PK.
	 * @param int $user_id User ID (0 = current).
	 * @return bool True if now pinned.
	 */
	public static function toggle_board_pin( $order_id, $user_id = 0 ) {
		$order_id = (int) $order_id;
		$pinned   = self::get_board_pinned_ids( $user_id );
		$pos      = array_search( $order_id, $pinned, true );
		if ( false !== $pos ) {
			unset( $pinned[ $pos ] );
			self::set_board_pinned_ids( $pinned, $user_id );
			return false;
		}
		$pinned[] = $order_id;
		self::set_board_pinned_ids( $pinned, $user_id );
		return true;
	}

	/**
	 * @param int $user_id User ID (0 = current).
	 * @return array<int, string>
	 */
	public static function get_board_column_order( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$raw     = get_user_meta( $user_id, self::BOARD_COLUMN_META, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( '' !== $key ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * @param array<int, string> $keys Column keys in display order.
	 * @param int                $user_id User ID (0 = current).
	 * @return void
	 */
	public static function set_board_column_order( array $keys, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$clean   = array();
		foreach ( $keys as $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( '' !== $key ) {
				$clean[] = $key;
			}
		}
		update_user_meta( $user_id, self::BOARD_COLUMN_META, array_values( array_unique( $clean ) ) );
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
