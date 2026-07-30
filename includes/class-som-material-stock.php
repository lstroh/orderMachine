<?php
/**
 * Material auto-decrement on new orders (Sprint 8).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reserves material stock when an order is created.
 *
 * Cancellation reversal is intentionally deferred until live/sandbox cancel
 * payloads confirm channel field shapes (open items D3 / A3).
 */
class SOM_Material_Stock {

	/**
	 * Decrement materials for a newly created order.
	 *
	 * Skips cancelled orders, unmatched/no-recipe items, and re-entry when
	 * `new_order` log rows already exist for this order.
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error True when applied or intentionally skipped; WP_Error on failure.
	 */
	public static function decrement_on_create( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return new WP_Error( 'som_stock_order', __( 'Invalid order.', 'order-machine' ) );
		}

		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$items_t    = SOM_DB::table( 'order_items' );

		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT o.*, c.slug AS channel_slug
				FROM {$orders_t} o
				INNER JOIN {$channels_t} c ON c.id = o.channel_id
				WHERE o.id = %d
				LIMIT 1",
				$order_id
			)
		);

		if ( ! $order ) {
			return new WP_Error( 'som_stock_order', __( 'Order not found.', 'order-machine' ) );
		}

		if ( SOM_Orders::is_cancelled( $order->raw_payload, $order->channel_slug ) ) {
			return true;
		}

		if ( self::has_log_reason( $order_id, 'new_order' ) ) {
			return true;
		}

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$items_t} WHERE order_id = %d ORDER BY id ASC",
				$order_id
			)
		);
		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$deltas = self::build_consumption_deltas( $items );
		if ( ! $deltas ) {
			return true;
		}

		foreach ( $deltas as $material_id => $qty ) {
			$result = SOM_Materials::adjust_stock(
				(int) $material_id,
				-1 * (float) $qty,
				array(
					'order_id' => $order_id,
					'reason'   => 'new_order',
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Placeholder for cancel reversal — not wired until live cancel payloads are confirmed.
	 *
	 * When enabled: reverse only if `new_order` logs exist and no `order_cancelled`
	 * logs yet; reverse from logged quantities (not current recipe).
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error
	 */
	public static function maybe_reverse_on_cancel( $order_id ) {
		unset( $order_id );
		return true;
	}

	/**
	 * Stock reservation summary for order detail UI.
	 *
	 * @param int $order_id Order PK.
	 * @return array{status:string,lines:array<int,object>,has_new_order:bool,has_cancelled_reversal:bool}
	 */
	public static function get_order_summary( $order_id ) {
		$order_id = (int) $order_id;
		$lines    = self::get_order_log_lines( $order_id );

		$has_new_order = false;
		$has_reversal  = false;
		foreach ( $lines as $line ) {
			if ( 'new_order' === $line->reason ) {
				$has_new_order = true;
			}
			if ( 'order_cancelled' === $line->reason ) {
				$has_reversal = true;
			}
		}

		$status = 'none';
		if ( $has_reversal ) {
			$status = 'reversed';
		} elseif ( $has_new_order ) {
			$status = 'reserved';
		}

		return array(
			'status'                 => $status,
			'lines'                  => $lines,
			'has_new_order'          => $has_new_order,
			'has_cancelled_reversal' => $has_reversal,
		);
	}

	/**
	 * Aggregated consumption by material_id from matched line items.
	 *
	 * @param array<int, object> $items Order items.
	 * @return array<int, float> material_id => total qty to consume (positive).
	 */
	private static function build_consumption_deltas( array $items ) {
		$deltas = array();

		foreach ( $items as $item ) {
			$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
			if ( $product_id < 1 ) {
				continue;
			}

			$qty = isset( $item->quantity ) ? max( 1, (int) $item->quantity ) : 1;
			$recipe = SOM_Products::get_recipe( $product_id );
			foreach ( $recipe as $row ) {
				$material_id = (int) $row->material_id;
				$per_unit    = (float) $row->quantity_per_unit;
				if ( $material_id < 1 || $per_unit <= 0 ) {
					continue;
				}
				if ( ! isset( $deltas[ $material_id ] ) ) {
					$deltas[ $material_id ] = 0.0;
				}
				$deltas[ $material_id ] += $per_unit * $qty;
			}
		}

		return $deltas;
	}

	/**
	 * @param int    $order_id Order PK.
	 * @param string $reason   Log reason code.
	 * @return bool
	 */
	private static function has_log_reason( $order_id, $reason ) {
		global $wpdb;

		$log_t = SOM_DB::table( 'material_stock_log' );
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$log_t} WHERE order_id = %d AND reason = %s LIMIT 1",
				(int) $order_id,
				sanitize_key( $reason )
			)
		);

		return ! empty( $found );
	}

	/**
	 * @param int $order_id Order PK.
	 * @return array<int, object>
	 */
	private static function get_order_log_lines( $order_id ) {
		global $wpdb;

		$log_t       = SOM_DB::table( 'material_stock_log' );
		$materials_t = SOM_DB::table( 'materials' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, m.name AS material_name, m.unit AS material_unit
				FROM {$log_t} l
				INNER JOIN {$materials_t} m ON m.id = l.material_id
				WHERE l.order_id = %d
				ORDER BY l.created_at ASC, l.id ASC",
				(int) $order_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}
}
