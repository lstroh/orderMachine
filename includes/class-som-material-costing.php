<?php
/**
 * Landed cost, weighted average, preview, and goal checks (Sprint U3).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared costing math for PO receive and Preview Impact.
 */
class SOM_Material_Costing {

	/**
	 * Allocate PO shipping/other across lines by item_cost share.
	 *
	 * When total item_cost is 0 and shipping/other > 0, allocations stay 0 and a warning is returned.
	 *
	 * @param float                $shipping_cost PO shipping.
	 * @param float                $other_cost    PO other.
	 * @param array<int, object|array{item_cost:float,quantity_ordered:float}> $items Lines.
	 * @return array{allocations: array<int, array{allocated_shipping_cost:float,allocated_other_cost:float,landed_unit_cost:float}>, warnings: array<int, string>}
	 */
	public static function allocate_line_costs( $shipping_cost, $other_cost, array $items ) {
		$shipping_cost = (float) $shipping_cost;
		$other_cost    = (float) $other_cost;
		$warnings      = array();

		$total_line = 0.0;
		$normalized = array();
		foreach ( $items as $index => $item ) {
			$item_cost = (float) ( is_object( $item ) ? $item->item_cost : $item['item_cost'] );
			$qty       = (float) ( is_object( $item ) ? $item->quantity_ordered : $item['quantity_ordered'] );
			$total_line += $item_cost;
			$normalized[ $index ] = array(
				'item_cost'        => $item_cost,
				'quantity_ordered' => $qty,
			);
		}

		$can_allocate = $total_line > 0.0;
		if ( ! $can_allocate && ( $shipping_cost > 0.0 || $other_cost > 0.0 ) ) {
			$warnings[] = __( 'Shipping/other cost could not be allocated because all line costs are zero.', 'order-machine' );
		}

		$allocations = array();
		foreach ( $normalized as $index => $row ) {
			$ship = 0.0;
			$oth  = 0.0;
			if ( $can_allocate ) {
				$share = $row['item_cost'] / $total_line;
				$ship  = $shipping_cost * $share;
				$oth   = $other_cost * $share;
			}

			$qty    = $row['quantity_ordered'];
			$landed = 0.0;
			if ( $qty > 0.0 ) {
				$landed = ( $row['item_cost'] + $ship + $oth ) / $qty;
			}

			$allocations[ $index ] = array(
				'allocated_shipping_cost' => self::round4( $ship ),
				'allocated_other_cost'    => self::round4( $oth ),
				'landed_unit_cost'        => self::round4( $landed ),
			);
		}

		return array(
			'allocations' => $allocations,
			'warnings'    => $warnings,
		);
	}

	/**
	 * Project new stock / value / WA after receiving qty at a landed unit cost.
	 *
	 * @param float $current_stock Current qty.
	 * @param float $total_value   Current total_value_on_hand.
	 * @param float $delta_qty     Qty being received (positive).
	 * @param float $landed_unit   Inbound landed unit cost.
	 * @return array{current_stock:float,total_value_on_hand:float,unit_cost:float}
	 */
	public static function project_receive( $current_stock, $total_value, $delta_qty, $landed_unit ) {
		$new_stock = (float) $current_stock + (float) $delta_qty;
		$new_value = (float) $total_value + ( (float) $delta_qty * (float) $landed_unit );
		$wa        = self::weighted_average( $new_stock, $new_value, null );

		return array(
			'current_stock'        => self::round4( $new_stock ),
			'total_value_on_hand'  => self::round4( $new_value ),
			'unit_cost'            => null === $wa ? null : self::round4( $wa ),
		);
	}

	/**
	 * Weighted average = total_value / current_stock (guard divide-by-zero).
	 *
	 * @param float      $stock     Current stock.
	 * @param float      $value     Total value on hand.
	 * @param float|null $fallback  Used when stock is 0 (typically materials.unit_cost).
	 * @return float|null
	 */
	public static function weighted_average( $stock, $value, $fallback = null ) {
		$stock = (float) $stock;
		if ( 0.0 !== $stock ) {
			return (float) $value / $stock;
		}
		if ( null !== $fallback && '' !== $fallback ) {
			return (float) $fallback;
		}
		return null;
	}

	/**
	 * Unit cost to use when decrementing / adjusting without an explicit inbound landed cost.
	 *
	 * @param object $material Material row with current_stock, total_value_on_hand, unit_cost.
	 * @return float
	 */
	public static function unit_cost_for_consumption( $material ) {
		$stock = (float) $material->current_stock;
		$value = isset( $material->total_value_on_hand ) ? (float) $material->total_value_on_hand : 0.0;
		$wa    = self::weighted_average(
			$stock,
			$value,
			isset( $material->unit_cost ) && null !== $material->unit_cost && '' !== $material->unit_cost
				? (float) $material->unit_cost
				: 0.0
		);
		return null === $wa ? 0.0 : (float) $wa;
	}

	/**
	 * Persist allocated_* and landed_unit_cost on every PO line (same totals on later receives).
	 *
	 * @param object $order PO from SOM_Purchase_Orders::get().
	 * @return array{allocations: array<int, array<string, float>>, warnings: array<int, string>}|WP_Error
	 */
	public static function write_allocations_for_order( $order ) {
		global $wpdb;

		if ( ! $order || empty( $order->items ) ) {
			return new WP_Error( 'som_cost_po', __( 'Purchase order has no lines.', 'order-machine' ) );
		}

		$result = self::allocate_line_costs(
			(float) $order->shipping_cost,
			(float) ( null !== $order->other_cost && '' !== $order->other_cost ? $order->other_cost : 0 ),
			$order->items
		);

		$now = current_time( 'mysql', true );
		foreach ( $order->items as $index => $item ) {
			$alloc = $result['allocations'][ $index ];
			$ok    = $wpdb->update(
				SOM_DB::table( 'purchase_order_items' ),
				array(
					'allocated_shipping_cost' => $alloc['allocated_shipping_cost'],
					'allocated_other_cost'    => $alloc['allocated_other_cost'],
					'landed_unit_cost'        => $alloc['landed_unit_cost'],
					'updated_at'              => $now,
				),
				array( 'id' => (int) $item->id ),
				array( '%f', '%f', '%f', '%s' ),
				array( '%d' )
			);
			if ( false === $ok ) {
				return new WP_Error( 'som_cost_alloc', __( 'Could not save landed cost allocation.', 'order-machine' ) );
			}
		}

		return $result;
	}

	/**
	 * Goal alert checks for a material at a given (or current) WA.
	 *
	 * @param int        $material_id Material PK.
	 * @param float|null $wa          Override WA; null = read from material.
	 * @return array<int, array{goal_id:int,workflow_template_id:int,workflow_name:string,goal_unit_cost:float,level:string}>
	 */
	public static function goal_alerts_for_material( $material_id, $wa = null ) {
		$material_id = (int) $material_id;
		if ( null === $wa ) {
			$material = SOM_Materials::get( $material_id );
			if ( ! $material ) {
				return array();
			}
			$wa = self::unit_cost_for_consumption( $material );
		}

		$alerts = array();
		foreach ( SOM_Workflow_Material_Goals::list_for_material( $material_id ) as $goal ) {
			$level = SOM_Workflow_Material_Goals::alert_level( $goal, (float) $wa );
			if ( '' === $level ) {
				continue;
			}
			$alerts[] = array(
				'goal_id'              => (int) $goal->id,
				'workflow_template_id' => (int) $goal->workflow_template_id,
				'workflow_name'        => isset( $goal->workflow_name ) ? (string) $goal->workflow_name : '',
				'goal_unit_cost'       => (float) $goal->goal_unit_cost,
				'level'                => $level,
			);
		}

		return $alerts;
	}

	/**
	 * In-memory preview of receiving a PO (or hypothetical lines) — no DB writes.
	 *
	 * @param array<string, mixed> $data shipping_cost, other_cost, items[{material_id, quantity_ordered, item_cost, quantity_receive?}].
	 * @return array{warnings:array<int,string>,lines:array<int,array<string,mixed>>,products:array<int,array<string,mixed>>}|WP_Error
	 */
	public static function preview_impact( array $data ) {
		$shipping = isset( $data['shipping_cost'] ) ? (float) $data['shipping_cost'] : 0.0;
		$other    = isset( $data['other_cost'] ) ? (float) $data['other_cost'] : 0.0;
		$items    = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

		if ( ! $items ) {
			return new WP_Error( 'som_preview_items', __( 'Add at least one line item to preview.', 'order-machine' ) );
		}

		$alloc = self::allocate_line_costs( $shipping, $other, $items );
		$lines = array();
		$affected_material_ids = array();
		$projected_wa          = array();

		foreach ( $items as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$material_id = isset( $row['material_id'] ) ? (int) $row['material_id'] : 0;
			$material    = $material_id > 0 ? SOM_Materials::get( $material_id ) : null;
			if ( ! $material ) {
				return new WP_Error( 'som_preview_material', __( 'Each preview line needs a valid material.', 'order-machine' ) );
			}

			$qty_ordered = isset( $row['quantity_ordered'] ) ? (float) $row['quantity_ordered'] : 0.0;
			$recv        = array_key_exists( 'quantity_receive', $row ) && '' !== $row['quantity_receive']
				? (float) $row['quantity_receive']
				: $qty_ordered;
			if ( $recv < 0 ) {
				return new WP_Error( 'som_preview_qty', __( 'Preview receive quantity cannot be negative.', 'order-machine' ) );
			}

			$landed = $alloc['allocations'][ $index ]['landed_unit_cost'];
			$proj   = self::project_receive(
				(float) $material->current_stock,
				(float) $material->total_value_on_hand,
				$recv,
				$landed
			);

			$new_wa = null !== $proj['unit_cost'] ? (float) $proj['unit_cost'] : self::unit_cost_for_consumption( $material );
			$alerts = self::goal_alerts_for_material( $material_id, $new_wa );

			$lines[] = array(
				'material_id'             => $material_id,
				'material_name'           => (string) $material->name,
				'quantity_receive'        => $recv,
				'allocated_shipping_cost' => $alloc['allocations'][ $index ]['allocated_shipping_cost'],
				'allocated_other_cost'    => $alloc['allocations'][ $index ]['allocated_other_cost'],
				'landed_unit_cost'        => $landed,
				'current_unit_cost'       => self::round4( self::unit_cost_for_consumption( $material ) ),
				'projected_unit_cost'     => null !== $proj['unit_cost'] ? $proj['unit_cost'] : self::round4( self::unit_cost_for_consumption( $material ) ),
				'projected_stock'         => $proj['current_stock'],
				'projected_value'         => $proj['total_value_on_hand'],
				'goal_alerts'             => $alerts,
			);

			$affected_material_ids[ $material_id ] = true;
			$projected_wa[ $material_id ]          = $new_wa;
		}

		return array(
			'warnings' => $alloc['warnings'],
			'lines'    => $lines,
			'products' => self::product_impacts_for_materials( array_keys( $affected_material_ids ), $projected_wa ),
		);
	}

	/**
	 * Products whose recipes include any of the materials, with cost/margin under projected WAs.
	 *
	 * @param array<int, int>           $material_ids Material PKs.
	 * @param array<int, float>         $wa_overrides material_id => projected WA.
	 * @return array<int, array<string, mixed>>
	 */
	public static function product_impacts_for_materials( array $material_ids, array $wa_overrides = array() ) {
		global $wpdb;

		$material_ids = array_values( array_unique( array_map( 'intval', $material_ids ) ) );
		$material_ids = array_filter( $material_ids );
		if ( ! $material_ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $material_ids ), '%d' ) );
		$recipe_t     = SOM_DB::table( 'product_materials' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT product_id FROM {$recipe_t} WHERE material_id IN ({$placeholders})",
				$material_ids
			)
		);

		$out = array();
		foreach ( (array) $product_ids as $product_id ) {
			$costing = SOM_Products::recipe_costing( (int) $product_id, $wa_overrides );
			if ( ! $costing ) {
				continue;
			}
			$out[] = $costing;
		}

		return $out;
	}

	/**
	 * @param float $n Number.
	 * @return float
	 */
	public static function round4( $n ) {
		return round( (float) $n, 4 );
	}
}
