<?php
/**
 * Material catalogue and stock helpers (Sprint 5).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD, deactivate, and manual stock adjustments for materials.
 */
class SOM_Materials {

	/**
	 * Materials per page on the admin list.
	 */
	const PER_PAGE = 20;

	/**
	 * Stock log rows shown on the material detail screen.
	 */
	const LOG_LIMIT = 10;

	/**
	 * Query materials for the admin list.
	 *
	 * @param array<string, mixed> $args Filters: status, s, paged, per_page.
	 * @return array{materials: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => 'active',
			's'        => '',
			'paged'    => 1,
			'per_page' => self::PER_PAGE,
		);
		$args     = wp_parse_args( $args, $defaults );

		$table  = SOM_DB::table( 'materials' );
		$where  = array( '1=1' );
		$params = array();

		$status = sanitize_key( (string) $args['status'] );
		if ( 'active' === $status ) {
			$where[] = 'm.is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where[] = 'm.is_active = 0';
		}

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( m.name LIKE %s OR m.unit LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} m WHERE {$where_sql}";
		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$paged    = max( 1, min( (int) $args['paged'], $pages ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$list_sql = "SELECT m.*
			FROM {$table} m
			WHERE {$where_sql}
			ORDER BY m.is_active DESC, m.name ASC, m.id ASC
			LIMIT %d OFFSET %d";

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$materials   = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );
		if ( ! is_array( $materials ) ) {
			$materials = array();
		}

		foreach ( $materials as $material ) {
			$material->is_low_stock = self::is_low_stock( $material );
		}

		return array(
			'materials' => $materials,
			'total'     => $total,
			'pages'     => $pages,
			'paged'     => $paged,
		);
	}

	/**
	 * All active materials for dropdowns (recipe editor).
	 *
	 * @return array<int, object>
	 */
	public static function list_active() {
		global $wpdb;

		$table = SOM_DB::table( 'materials' );
		$rows  = $wpdb->get_results(
			"SELECT id, name, unit FROM {$table} WHERE is_active = 1 ORDER BY name ASC, id ASC"
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Fetch one material with recent stock log entries.
	 *
	 * @param int $material_id Material PK.
	 * @return object|null
	 */
	public static function get( $material_id ) {
		global $wpdb;

		$material_id = (int) $material_id;
		$table       = SOM_DB::table( 'materials' );

		$material = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$material_id
			)
		);

		if ( ! $material ) {
			return null;
		}

		$material->is_low_stock = self::is_low_stock( $material );
		$material->stock_log    = self::get_stock_log( $material_id, self::LOG_LIMIT );

		return $material;
	}

	/**
	 * Recent stock log rows for a material.
	 *
	 * @param int $material_id Material PK.
	 * @param int $limit         Max rows.
	 * @return array<int, object>
	 */
	public static function get_stock_log( $material_id, $limit = 10 ) {
		global $wpdb;

		$log_t    = SOM_DB::table( 'material_stock_log' );
		$orders_t = SOM_DB::table( 'orders' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, o.external_order_id
				FROM {$log_t} l
				LEFT JOIN {$orders_t} o ON o.id = l.order_id
				WHERE l.material_id = %d
				ORDER BY l.created_at DESC, l.id DESC
				LIMIT %d",
				(int) $material_id,
				max( 1, (int) $limit )
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Create a material row.
	 *
	 * @param array<string, mixed> $data Fields: name, unit, low_stock_threshold, unit_cost, preferred_supplier_id, is_active.
	 * @return int|WP_Error New material ID or error.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$unit = isset( $data['unit'] ) ? sanitize_text_field( (string) $data['unit'] ) : '';

		if ( '' === $name ) {
			return new WP_Error( 'som_material_name', __( 'Material name is required.', 'order-machine' ) );
		}
		if ( '' === $unit ) {
			return new WP_Error( 'som_material_unit', __( 'Unit is required (e.g. sheet, pack).', 'order-machine' ) );
		}

		$preferred = self::nullable_supplier_id( $data );
		if ( is_wp_error( $preferred ) ) {
			return $preferred;
		}

		$now = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			SOM_DB::table( 'materials' ),
			array(
				'name'                  => $name,
				'unit'                  => $unit,
				'current_stock'         => 0,
				'low_stock_threshold'   => self::nullable_decimal( $data, 'low_stock_threshold' ),
				'unit_cost'             => self::nullable_decimal( $data, 'unit_cost', 4 ),
				'preferred_supplier_id' => $preferred,
				'is_active'             => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
				'created_at'            => $now,
				'updated_at'            => $now,
			),
			array( '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'som_material_create', __( 'Could not create material.', 'order-machine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update material metadata (not stock level).
	 *
	 * Manual `unit_cost` override revalues `total_value_on_hand = current_stock × unit_cost`
	 * and writes a stock-log row with `value_change` (correcting adjustment path).
	 *
	 * @param int                  $material_id Material PK.
	 * @param array<string, mixed> $data        Fields to update.
	 * @return true|WP_Error
	 */
	public static function update( $material_id, array $data ) {
		global $wpdb;

		$material_id = (int) $material_id;
		$before      = self::get( $material_id );
		if ( $material_id < 1 || ! $before ) {
			return new WP_Error( 'som_material_missing', __( 'Material not found.', 'order-machine' ) );
		}

		$fields = array(
			'updated_at' => current_time( 'mysql', true ),
		);
		$formats = array( '%s' );

		if ( array_key_exists( 'name', $data ) ) {
			$name = sanitize_text_field( (string) $data['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'som_material_name', __( 'Material name is required.', 'order-machine' ) );
			}
			$fields['name'] = $name;
			$formats[]      = '%s';
		}

		if ( array_key_exists( 'unit', $data ) ) {
			$unit = sanitize_text_field( (string) $data['unit'] );
			if ( '' === $unit ) {
				return new WP_Error( 'som_material_unit', __( 'Unit is required.', 'order-machine' ) );
			}
			$fields['unit'] = $unit;
			$formats[]      = '%s';
		}

		if ( array_key_exists( 'low_stock_threshold', $data ) ) {
			$fields['low_stock_threshold'] = self::nullable_decimal( $data, 'low_stock_threshold' );
			$formats[]                     = '%s';
		}

		$revalue_unit_cost = null;
		if ( array_key_exists( 'unit_cost', $data ) ) {
			$revalue_unit_cost = self::nullable_decimal( $data, 'unit_cost', 4 );
			$fields['unit_cost'] = $revalue_unit_cost;
			$formats[]           = '%s';
		}

		if ( array_key_exists( 'preferred_supplier_id', $data ) ) {
			$preferred = self::nullable_supplier_id( $data );
			if ( is_wp_error( $preferred ) ) {
				return $preferred;
			}
			$fields['preferred_supplier_id'] = $preferred;
			$formats[]                       = '%d';
		}

		if ( array_key_exists( 'is_active', $data ) ) {
			$fields['is_active'] = (int) (bool) $data['is_active'];
			$formats[]           = '%d';
		}

		$updated = $wpdb->update(
			SOM_DB::table( 'materials' ),
			$fields,
			array( 'id' => $material_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'som_material_update', __( 'Could not update material.', 'order-machine' ) );
		}

		if ( array_key_exists( 'unit_cost', $data ) ) {
			$revalued = self::revalue_from_unit_cost( $material_id, $revalue_unit_cost );
			if ( is_wp_error( $revalued ) ) {
				return $revalued;
			}
		}

		return true;
	}

	/**
	 * Revalue total_value_on_hand from current_stock × unit_cost (correcting adjustment).
	 *
	 * Writes a stock-log row with change_qty 0 and value_change = new − old value.
	 *
	 * @param int         $material_id Material PK.
	 * @param string|null $unit_cost   New unit cost (null clears cost and zeroes value).
	 * @return true|WP_Error
	 */
	public static function revalue_from_unit_cost( $material_id, $unit_cost ) {
		global $wpdb;

		$material_id = (int) $material_id;
		$table       = SOM_DB::table( 'materials' );
		$current     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, current_stock, total_value_on_hand, unit_cost FROM {$table} WHERE id = %d LIMIT 1",
				$material_id
			)
		);
		if ( ! $current ) {
			return new WP_Error( 'som_material_missing', __( 'Material not found.', 'order-machine' ) );
		}

		$stock      = (float) $current->current_stock;
		$old_value  = (float) $current->total_value_on_hand;
		$unit       = null === $unit_cost || '' === $unit_cost ? null : (float) $unit_cost;
		$new_value  = null === $unit ? 0.0 : SOM_Material_Costing::round4( $stock * $unit );
		$value_chg  = SOM_Material_Costing::round4( $new_value - $old_value );
		$unit_at    = null === $unit ? 0.0 : $unit;

		if ( abs( $value_chg ) < 0.0000001
			&& ( ( null === $unit && ( null === $current->unit_cost || '' === $current->unit_cost ) )
				|| ( null !== $unit && null !== $current->unit_cost && abs( (float) $current->unit_cost - $unit ) < 0.0000001 ) )
		) {
			return true;
		}

		$now = current_time( 'mysql', true );

		$log_ok = $wpdb->insert(
			SOM_DB::table( 'material_stock_log' ),
			array(
				'material_id'            => $material_id,
				'order_id'               => null,
				'change_qty'             => 0,
				'reason'                 => 'manual_adjustment',
				'purchase_order_item_id' => null,
				'unit_cost_at_time'      => $unit_at,
				'value_change'           => $value_chg,
				'created_at'             => $now,
			),
			array( '%d', '%s', '%f', '%s', '%s', '%f', '%f', '%s' )
		);

		if ( ! $log_ok ) {
			return new WP_Error( 'som_stock_log', __( 'Could not write stock log entry.', 'order-machine' ) );
		}

		$updated = $wpdb->update(
			$table,
			array(
				'unit_cost'           => null === $unit ? null : number_format( $unit, 4, '.', '' ),
				'total_value_on_hand' => $new_value,
				'updated_at'          => $now,
			),
			array( 'id' => $material_id ),
			array( '%s', '%f', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'som_stock_update', __( 'Could not update stock value.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Apply a delta stock adjustment and write the audit log row.
	 *
	 * Maintains `total_value_on_hand` and logs `unit_cost_at_time` / `value_change`.
	 * Optional args: order_id, reason, purchase_order_item_id, unit_cost_at_time,
	 * value_change, sync_unit_cost (bool — write new WA into materials.unit_cost).
	 *
	 * @param int                  $material_id Material PK.
	 * @param float                $delta       Positive or negative change.
	 * @param array<string, mixed> $args        Optional context.
	 * @return true|WP_Error
	 */
	public static function adjust_stock( $material_id, $delta, array $args = array() ) {
		global $wpdb;

		$material_id = (int) $material_id;
		$delta       = (float) $delta;
		$order_id    = array_key_exists( 'order_id', $args ) && null !== $args['order_id'] && '' !== $args['order_id']
			? (int) $args['order_id']
			: null;
		$poi_id      = array_key_exists( 'purchase_order_item_id', $args ) && null !== $args['purchase_order_item_id'] && '' !== $args['purchase_order_item_id']
			? (int) $args['purchase_order_item_id']
			: null;
		$reason      = isset( $args['reason'] ) ? sanitize_key( (string) $args['reason'] ) : 'manual_adjustment';
		if ( '' === $reason ) {
			$reason = 'manual_adjustment';
		}

		if ( 0.0 === $delta ) {
			return new WP_Error( 'som_stock_zero', __( 'Adjustment amount cannot be zero.', 'order-machine' ) );
		}

		$table = SOM_DB::table( 'materials' );

		$current = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, current_stock, total_value_on_hand, unit_cost FROM {$table} WHERE id = %d LIMIT 1",
				$material_id
			)
		);

		if ( ! $current ) {
			return new WP_Error( 'som_material_missing', __( 'Material not found.', 'order-machine' ) );
		}

		if ( array_key_exists( 'unit_cost_at_time', $args ) && null !== $args['unit_cost_at_time'] && '' !== $args['unit_cost_at_time'] ) {
			$unit_at = (float) $args['unit_cost_at_time'];
		} else {
			$unit_at = SOM_Material_Costing::unit_cost_for_consumption( $current );
		}

		if ( array_key_exists( 'value_change', $args ) && null !== $args['value_change'] && '' !== $args['value_change'] ) {
			$value_change = (float) $args['value_change'];
		} else {
			$value_change = $delta * $unit_at;
		}

		$unit_at      = SOM_Material_Costing::round4( $unit_at );
		$value_change = SOM_Material_Costing::round4( $value_change );
		$new_stock    = (float) $current->current_stock + $delta;
		$new_value    = SOM_Material_Costing::round4( (float) $current->total_value_on_hand + $value_change );
		$now          = current_time( 'mysql', true );

		$sync_unit = ! empty( $args['sync_unit_cost'] );
		$new_wa    = SOM_Material_Costing::weighted_average( $new_stock, $new_value, $unit_at );

		$material_fields = array(
			'current_stock'       => $new_stock,
			'total_value_on_hand' => $new_value,
			'updated_at'          => $now,
		);
		$material_formats = array( '%f', '%f', '%s' );

		if ( $sync_unit && null !== $new_wa ) {
			$material_fields['unit_cost'] = number_format( SOM_Material_Costing::round4( $new_wa ), 4, '.', '' );
			$material_formats[]           = '%s';
		}

		$log_ok = $wpdb->insert(
			SOM_DB::table( 'material_stock_log' ),
			array(
				'material_id'            => $material_id,
				'order_id'               => $order_id,
				'change_qty'             => $delta,
				'reason'                 => $reason,
				'purchase_order_item_id' => $poi_id,
				'unit_cost_at_time'      => $unit_at,
				'value_change'           => $value_change,
				'created_at'             => $now,
			),
			array( '%d', null === $order_id ? '%s' : '%d', '%f', '%s', null === $poi_id ? '%s' : '%d', '%f', '%f', '%s' )
		);

		if ( ! $log_ok ) {
			return new WP_Error( 'som_stock_log', __( 'Could not write stock log entry.', 'order-machine' ) );
		}

		$updated = $wpdb->update(
			$table,
			$material_fields,
			array( 'id' => $material_id ),
			$material_formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'som_stock_update', __( 'Could not update stock level.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Human-readable reason label for stock log rows.
	 *
	 * @param string $reason Stored reason code.
	 * @return string
	 */
	public static function reason_label( $reason ) {
		$labels = array(
			'manual_adjustment' => __( 'Manual adjustment', 'order-machine' ),
			'new_order'         => __( 'New order', 'order-machine' ),
			'order_cancelled'   => __( 'Order cancelled', 'order-machine' ),
			'restock'           => __( 'Restock', 'order-machine' ),
			'purchase_received' => __( 'Purchase received', 'order-machine' ),
		);

		$reason = sanitize_key( (string) $reason );
		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}

	/**
	 * @param object $material Material row.
	 * @return bool
	 */
	public static function is_low_stock( $material ) {
		if ( null === $material->low_stock_threshold || '' === $material->low_stock_threshold ) {
			return false;
		}
		return (float) $material->current_stock <= (float) $material->low_stock_threshold;
	}

	/**
	 * Admin URL for the materials list.
	 *
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		$args = array_merge( array( 'page' => 'som-materials' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Admin URL for a single material edit screen.
	 *
	 * @param int $material_id Material PK.
	 * @return string
	 */
	public static function detail_url( $material_id ) {
		return self::list_url( array( 'material_id' => (int) $material_id ) );
	}

	/**
	 * @param array<string, mixed> $data   Source array.
	 * @param string               $key    Field key.
	 * @param int                  $places Decimal places.
	 * @return string|null String for $wpdb or null when empty.
	 */
	private static function nullable_decimal( array $data, $key, $places = 2 ) {
		if ( ! array_key_exists( $key, $data ) ) {
			return null;
		}
		$raw = trim( (string) $data[ $key ] );
		if ( '' === $raw ) {
			return null;
		}
		if ( ! is_numeric( $raw ) ) {
			return null;
		}
		return number_format( (float) $raw, (int) $places, '.', '' );
	}

	/**
	 * @param array<string, mixed> $data Source with preferred_supplier_id.
	 * @return int|null|WP_Error
	 */
	private static function nullable_supplier_id( array $data ) {
		if ( ! array_key_exists( 'preferred_supplier_id', $data ) ) {
			return null;
		}
		$raw = $data['preferred_supplier_id'];
		if ( null === $raw || '' === $raw || 0 === $raw || '0' === $raw ) {
			return null;
		}
		$id = (int) $raw;
		if ( $id < 1 || ! SOM_Suppliers::get( $id ) ) {
			return new WP_Error( 'som_material_supplier', __( 'Preferred supplier is invalid.', 'order-machine' ) );
		}
		return $id;
	}
}
