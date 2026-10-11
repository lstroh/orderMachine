<?php
/**
 * Material auto-decrement on new orders + package BOM reserve (Sprint 8 / UP7-S2).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reserves material stock when an order is created or a shipping package is set.
 *
 * Cancellation reversal is intentionally deferred until live/sandbox cancel
 * payloads confirm channel field shapes (open items D3 / A3).
 */
class SOM_Material_Stock {

	const SOURCE_RECIPE  = 'recipe';
	const SOURCE_PACKAGE = 'package';

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
	 * Sync package BOM reserves to the order’s current `shipping_package_id`.
	 *
	 * Idempotent when net package reserves already match the selected package BOM.
	 * On package change: reverse prior `package_reserve` then reserve the new BOM (O4).
	 * Skips cancelled orders and Internal Produce N.
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error
	 */
	public static function sync_package_reserve( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return new WP_Error( 'som_stock_order', __( 'Invalid order.', 'order-machine' ) );
		}

		$order = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_stock_order', __( 'Order not found.', 'order-machine' ) );
		}

		if ( ! empty( $order->is_cancelled ) ) {
			return true;
		}

		$channel = isset( $order->channel_slug ) ? (string) $order->channel_slug : '';
		if ( SOM_Production::CHANNEL_SLUG === $channel ) {
			return true;
		}

		$package_id = ! empty( $order->shipping_package_id ) ? (int) $order->shipping_package_id : 0;
		$desired    = array();
		if ( $package_id > 0 ) {
			foreach ( SOM_Shipping_Packages::get_materials( $package_id ) as $row ) {
				$material_id = (int) $row->material_id;
				$qty         = (float) $row->quantity_per_package;
				if ( $material_id < 1 || $qty <= 0 ) {
					continue;
				}
				$desired[ $material_id ] = SOM_Material_Costing::round4( $qty );
			}
		}

		$current = self::net_package_reserves( $order_id );
		if ( self::qty_maps_equal( $current, $desired ) ) {
			return true;
		}

		if ( $current ) {
			$reversed = self::unreserve_package_materials( $order_id, $current );
			if ( is_wp_error( $reversed ) ) {
				return $reversed;
			}
		}

		if ( ! $desired ) {
			return true;
		}

		$log_ids = array();
		foreach ( $desired as $material_id => $qty ) {
			$result = SOM_Materials::adjust_stock(
				(int) $material_id,
				-1 * (float) $qty,
				array(
					'order_id' => $order_id,
					'reason'   => 'package_reserve',
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$log_ids[] = (int) $result;
		}

		if ( $log_ids ) {
			SOM_Budgets::fund_package_reserves( $order_id, $log_ids );
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
		$has_package   = false;
		$has_reversal  = false;
		foreach ( $lines as $line ) {
			if ( 'new_order' === $line->reason ) {
				$has_new_order = true;
			}
			if ( 'package_reserve' === $line->reason ) {
				$has_package = true;
			}
			if ( 'order_cancelled' === $line->reason ) {
				$has_reversal = true;
			}
		}

		$status = 'none';
		if ( $has_reversal ) {
			$status = 'reversed';
		} elseif ( $has_new_order || $has_package ) {
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
	 * Planned vs actual material usage for an order, separate rows by source (O5).
	 *
	 * @param int $order_id Order PK.
	 * @return array<int, object> List of { source, material_id, material_name, material_unit, planned, extra, actual }.
	 */
	public static function get_usage_by_material( $order_id ) {
		$order_id = (int) $order_id;
		$lines    = self::get_order_log_lines( $order_id );
		$out      = array();

		foreach ( $lines as $line ) {
			$reason = (string) $line->reason;
			$source = '';
			$field  = '';

			if ( 'new_order' === $reason ) {
				$source = self::SOURCE_RECIPE;
				$field  = 'planned';
			} elseif ( 'order_usage_extra' === $reason ) {
				$source = self::SOURCE_RECIPE;
				$field  = 'extra';
			} elseif ( 'package_reserve' === $reason ) {
				$source = self::SOURCE_PACKAGE;
				$field  = 'planned';
			} elseif ( 'package_unreserve' === $reason ) {
				$source = self::SOURCE_PACKAGE;
				$field  = 'planned_reverse';
			} elseif ( 'package_usage_extra' === $reason ) {
				$source = self::SOURCE_PACKAGE;
				$field  = 'extra';
			} else {
				continue;
			}

			$material_id = (int) $line->material_id;
			if ( $material_id < 1 ) {
				continue;
			}

			$key = $source . ':' . $material_id;
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = (object) array(
					'source'        => $source,
					'material_id'   => $material_id,
					'material_name' => (string) $line->material_name,
					'material_unit' => (string) $line->material_unit,
					'planned'       => 0.0,
					'extra'         => 0.0,
					'actual'        => 0.0,
				);
			}

			$qty = abs( (float) $line->change_qty );
			if ( 'planned' === $field ) {
				$out[ $key ]->planned += $qty;
			} elseif ( 'planned_reverse' === $field ) {
				$out[ $key ]->planned -= $qty;
			} else {
				$out[ $key ]->extra += $qty;
			}
		}

		$rows = array();
		foreach ( $out as $row ) {
			$row->planned = SOM_Material_Costing::round4( max( 0.0, (float) $row->planned ) );
			$row->extra   = SOM_Material_Costing::round4( max( 0.0, (float) $row->extra ) );
			$row->actual  = SOM_Material_Costing::round4( $row->planned + $row->extra );
			if ( $row->planned <= 0 && $row->extra <= 0 ) {
				continue;
			}
			$rows[] = $row;
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				$source_cmp = strcmp( (string) $a->source, (string) $b->source );
				if ( 0 !== $source_cmp ) {
					// recipe before package.
					if ( self::SOURCE_RECIPE === $a->source ) {
						return -1;
					}
					if ( self::SOURCE_RECIPE === $b->source ) {
						return 1;
					}
					return $source_cmp;
				}
				return (int) $a->material_id <=> (int) $b->material_id;
			}
		);

		return $rows;
	}

	/**
	 * Human-readable Materials used source label.
	 *
	 * @param string $source recipe|package.
	 * @return string
	 */
	public static function usage_source_label( $source ) {
		$source = sanitize_key( (string) $source );
		if ( self::SOURCE_PACKAGE === $source ) {
			return __( 'Package', 'order-machine' );
		}
		return __( 'Recipe', 'order-machine' );
	}

	/**
	 * Raise actual material usage on an order (increase only; never below planned or current actual).
	 *
	 * @param int                                $order_id Order PK.
	 * @param array<string, array<int, float>>   $actuals  source => ( material_id => desired actual qty ).
	 *                                                     Legacy flat material_id => qty is treated as recipe.
	 * @return true|WP_Error
	 */
	public static function apply_overuse( $order_id, array $actuals ) {
		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return new WP_Error( 'som_stock_order', __( 'Invalid order.', 'order-machine' ) );
		}

		$order = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_stock_order', __( 'Order not found.', 'order-machine' ) );
		}

		$usage = self::get_usage_by_material( $order_id );
		if ( ! $usage ) {
			return new WP_Error(
				'som_overuse_none',
				__( 'No materials were reserved for this order, so usage cannot be adjusted.', 'order-machine' )
			);
		}

		$by_source = self::normalize_overuse_actuals( $actuals );
		$log_ids   = array();
		$epsilon   = 0.0000001;

		foreach ( $usage as $row ) {
			$source      = (string) $row->source;
			$material_id = (int) $row->material_id;
			if ( ! isset( $by_source[ $source ] ) || ! array_key_exists( $material_id, $by_source[ $source ] ) ) {
				continue;
			}
			$desired = (float) $by_source[ $source ][ $material_id ];
			if ( $desired + $epsilon < (float) $row->planned ) {
				return new WP_Error(
					'som_overuse_below_planned',
					sprintf(
						/* translators: %s: material name */
						__( 'Actual usage for %s cannot be less than the planned amount.', 'order-machine' ),
						(string) $row->material_name
					)
				);
			}
			if ( $desired + $epsilon < (float) $row->actual ) {
				return new WP_Error(
					'som_overuse_decrease',
					sprintf(
						/* translators: %s: material name */
						__( 'Actual usage for %s can only stay the same or increase.', 'order-machine' ),
						(string) $row->material_name
					)
				);
			}
			$delta = SOM_Material_Costing::round4( $desired - (float) $row->actual );
			if ( $delta <= $epsilon ) {
				continue;
			}

			$reason = self::SOURCE_PACKAGE === $source ? 'package_usage_extra' : 'order_usage_extra';
			$result = SOM_Materials::adjust_stock(
				$material_id,
				-1 * $delta,
				array(
					'order_id' => $order_id,
					'reason'   => $reason,
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$log_ids[] = (int) $result;
		}

		if ( $log_ids ) {
			SOM_Budgets::fund_usage_extras( $order_id, $log_ids );
		}

		return true;
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

			$qty    = isset( $item->quantity ) ? max( 1, (int) $item->quantity ) : 1;
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
	 * Net package BOM consumption still held for an order (positive = reserved).
	 *
	 * @param int $order_id Order PK.
	 * @return array<int, float> material_id => qty.
	 */
	private static function net_package_reserves( $order_id ) {
		$nets = array();
		foreach ( self::get_order_log_lines( (int) $order_id ) as $line ) {
			$reason = (string) $line->reason;
			if ( 'package_reserve' !== $reason && 'package_unreserve' !== $reason ) {
				continue;
			}
			$material_id = (int) $line->material_id;
			if ( $material_id < 1 ) {
				continue;
			}
			if ( ! isset( $nets[ $material_id ] ) ) {
				$nets[ $material_id ] = 0.0;
			}
			// package_reserve is negative change_qty; unreserve is positive.
			$nets[ $material_id ] += -1 * (float) $line->change_qty;
		}

		$out = array();
		foreach ( $nets as $material_id => $qty ) {
			$qty = SOM_Material_Costing::round4( (float) $qty );
			if ( $qty > 0.0000001 ) {
				$out[ (int) $material_id ] = $qty;
			}
		}
		return $out;
	}

	/**
	 * Write package_unreserve stock returns + reverse material budget funding.
	 *
	 * @param int                $order_id Order PK.
	 * @param array<int, float>  $nets     material_id => qty to return (positive).
	 * @return true|WP_Error
	 */
	private static function unreserve_package_materials( $order_id, array $nets ) {
		$order_id = (int) $order_id;
		$log_ids  = array();

		foreach ( $nets as $material_id => $qty ) {
			$qty = (float) $qty;
			if ( $qty <= 0 ) {
				continue;
			}
			$result = SOM_Materials::adjust_stock(
				(int) $material_id,
				$qty,
				array(
					'order_id' => $order_id,
					'reason'   => 'package_unreserve',
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$log_ids[] = (int) $result;
		}

		if ( $log_ids ) {
			SOM_Budgets::reverse_package_reserves( $order_id, $log_ids );
		}

		return true;
	}

	/**
	 * @param array<int, float> $a Map A.
	 * @param array<int, float> $b Map B.
	 * @return bool
	 */
	private static function qty_maps_equal( array $a, array $b ) {
		if ( count( $a ) !== count( $b ) ) {
			return false;
		}
		foreach ( $a as $material_id => $qty ) {
			if ( ! isset( $b[ $material_id ] ) ) {
				return false;
			}
			if ( abs( (float) $qty - (float) $b[ $material_id ] ) > 0.0000001 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Normalize overuse payload to source => material_id => qty.
	 *
	 * @param array<string|int, mixed> $actuals Raw actuals.
	 * @return array<string, array<int, float>>
	 */
	private static function normalize_overuse_actuals( array $actuals ) {
		$out = array(
			self::SOURCE_RECIPE  => array(),
			self::SOURCE_PACKAGE => array(),
		);

		$looks_nested = false;
		foreach ( $actuals as $key => $value ) {
			if ( self::SOURCE_RECIPE === (string) $key || self::SOURCE_PACKAGE === (string) $key ) {
				$looks_nested = true;
				break;
			}
		}

		if ( $looks_nested ) {
			foreach ( array( self::SOURCE_RECIPE, self::SOURCE_PACKAGE ) as $source ) {
				if ( empty( $actuals[ $source ] ) || ! is_array( $actuals[ $source ] ) ) {
					continue;
				}
				foreach ( $actuals[ $source ] as $material_id => $qty ) {
					$material_id = (int) $material_id;
					if ( $material_id < 1 || ! is_numeric( $qty ) ) {
						continue;
					}
					$out[ $source ][ $material_id ] = (float) $qty;
				}
			}
			return $out;
		}

		// Legacy flat map → recipe.
		foreach ( $actuals as $material_id => $qty ) {
			$material_id = (int) $material_id;
			if ( $material_id < 1 || ! is_numeric( $qty ) ) {
				continue;
			}
			$out[ self::SOURCE_RECIPE ][ $material_id ] = (float) $qty;
		}

		return $out;
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
