<?php
/**
 * Purchase order CRUD and receive status machine (Sprint U2 + U3 costing).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD and receive flow for `wp_som_purchase_orders` + items.
 */
class SOM_Purchase_Orders {

	const PER_PAGE = 20;

	/**
	 * @return array<string, string>
	 */
	public static function status_labels() {
		return array(
			'ordered'            => __( 'Ordered', 'order-machine' ),
			'partially_received' => __( 'Partially received', 'order-machine' ),
			'received'           => __( 'Received', 'order-machine' ),
			'cancelled'          => __( 'Cancelled', 'order-machine' ),
		);
	}

	/**
	 * @param string $status Status code.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = self::status_labels();
		$key    = sanitize_key( (string) $status );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}

	/**
	 * @param array<string, mixed> $args Filters: status, supplier_id, s, paged, per_page.
	 * @return array{orders: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'      => '',
				'supplier_id' => 0,
				's'           => '',
				'paged'       => 1,
				'per_page'    => self::PER_PAGE,
			)
		);

		$po_t       = SOM_DB::table( 'purchase_orders' );
		$supplier_t = SOM_DB::table( 'suppliers' );
		$where      = array( '1=1' );
		$params     = array();

		$status = sanitize_key( (string) $args['status'] );
		if ( '' !== $status && isset( self::status_labels()[ $status ] ) ) {
			$where[]  = 'po.status = %s';
			$params[] = $status;
		}

		$supplier_id = (int) $args['supplier_id'];
		if ( $supplier_id > 0 ) {
			$where[]  = 'po.supplier_id = %d';
			$params[] = $supplier_id;
		}

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( s.name LIKE %s OR po.notes LIKE %s OR CAST(po.id AS CHAR) LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$po_t} po LEFT JOIN {$supplier_t} s ON s.id = po.supplier_id WHERE {$where_sql}";
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

		$list_sql    = "SELECT po.*, s.name AS supplier_name
			FROM {$po_t} po
			LEFT JOIN {$supplier_t} s ON s.id = po.supplier_id
			WHERE {$where_sql}
			ORDER BY po.order_date DESC, po.id DESC
			LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows        = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'orders' => is_array( $rows ) ? $rows : array(),
			'total'  => $total,
			'pages'  => $pages,
			'paged'  => $paged,
		);
	}

	/**
	 * @param int $id Purchase order PK.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id < 1 ) {
			return null;
		}

		$po_t       = SOM_DB::table( 'purchase_orders' );
		$supplier_t = SOM_DB::table( 'suppliers' );

		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT po.*, s.name AS supplier_name
				FROM {$po_t} po
				LEFT JOIN {$supplier_t} s ON s.id = po.supplier_id
				WHERE po.id = %d
				LIMIT 1",
				$id
			)
		);

		if ( ! $order ) {
			return null;
		}

		$order->items           = self::get_items( $id );
		$order->lines_locked    = self::has_any_receipt( $order );
		$order->can_receive     = self::can_receive( $order );
		$order->can_mark_received = self::can_mark_received( $order );
		$order->can_cancel      = self::can_cancel( $order );
		$order->can_edit_lines  = ( 'ordered' === $order->status && ! $order->lines_locked );

		return $order;
	}

	/**
	 * @param int $purchase_order_id PO PK.
	 * @return array<int, object>
	 */
	public static function get_items( $purchase_order_id ) {
		global $wpdb;

		$items_t = SOM_DB::table( 'purchase_order_items' );
		$mat_t   = SOM_DB::table( 'materials' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, m.name AS material_name, m.unit AS material_unit
				FROM {$items_t} i
				LEFT JOIN {$mat_t} m ON m.id = i.material_id
				WHERE i.purchase_order_id = %d
				ORDER BY i.id ASC",
				(int) $purchase_order_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Create a PO in `ordered` status.
	 *
	 * @param array<string, mixed> $data Header + items[].
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;

		$header = self::validate_header( $data, true );
		if ( is_wp_error( $header ) ) {
			return $header;
		}

		$items = self::normalize_items( isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array() );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			SOM_DB::table( 'purchase_orders' ),
			array(
				'supplier_id'   => $header['supplier_id'],
				'order_date'    => $header['order_date'],
				'received_date' => null,
				'status'        => 'ordered',
				'shipping_cost' => $header['shipping_cost'],
				'other_cost'    => $header['other_cost'],
				'notes'         => $header['notes'],
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return new WP_Error( 'som_po_create', __( 'Could not create purchase order.', 'order-machine' ) );
		}

		$po_id = (int) $wpdb->insert_id;
		$saved = self::replace_items( $po_id, $items );
		if ( is_wp_error( $saved ) ) {
			$wpdb->delete( SOM_DB::table( 'purchase_orders' ), array( 'id' => $po_id ), array( '%d' ) );
			return $saved;
		}

		return $po_id;
	}

	/**
	 * Update PO. Line/cost fields only while still `ordered` with no receipts.
	 *
	 * @param int                  $id   PO PK.
	 * @param array<string, mixed> $data Fields.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$order = self::get( (int) $id );
		if ( ! $order ) {
			return new WP_Error( 'som_po_missing', __( 'Purchase order not found.', 'order-machine' ) );
		}

		if ( in_array( $order->status, array( 'received', 'cancelled' ), true ) ) {
			return new WP_Error( 'som_po_locked', __( 'This purchase order can no longer be edited.', 'order-machine' ) );
		}

		$fields  = array( 'updated_at' => current_time( 'mysql', true ) );
		$formats = array( '%s' );

		if ( $order->can_edit_lines ) {
			$header = self::validate_header( $data, false );
			if ( is_wp_error( $header ) ) {
				return $header;
			}

			if ( array_key_exists( 'supplier_id', $data ) ) {
				$fields['supplier_id'] = $header['supplier_id'];
				$formats[]             = '%d';
			}
			if ( array_key_exists( 'order_date', $data ) ) {
				$fields['order_date'] = $header['order_date'];
				$formats[]            = '%s';
			}
			if ( array_key_exists( 'shipping_cost', $data ) ) {
				$fields['shipping_cost'] = $header['shipping_cost'];
				$formats[]               = '%f';
			}
			if ( array_key_exists( 'other_cost', $data ) ) {
				$fields['other_cost'] = $header['other_cost'];
				$formats[]            = '%f';
			}

			if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
				$items = self::normalize_items( $data['items'] );
				if ( is_wp_error( $items ) ) {
					return $items;
				}
				$replaced = self::replace_items( (int) $order->id, $items );
				if ( is_wp_error( $replaced ) ) {
					return $replaced;
				}
			}
		} elseif (
			array_key_exists( 'supplier_id', $data )
			|| array_key_exists( 'order_date', $data )
			|| array_key_exists( 'shipping_cost', $data )
			|| array_key_exists( 'other_cost', $data )
			|| ( isset( $data['items'] ) && is_array( $data['items'] ) )
		) {
			return new WP_Error(
				'som_po_lines_locked',
				__( 'Line items and costs are locked after the first receipt. Correct stock via a separate adjustment.', 'order-machine' )
			);
		}

		if ( array_key_exists( 'notes', $data ) ) {
			$notes = trim( (string) $data['notes'] );
			$fields['notes'] = '' === $notes ? null : sanitize_textarea_field( $notes );
			$formats[]       = '%s';
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'purchase_orders' ),
			$fields,
			array( 'id' => (int) $order->id ),
			$formats,
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_po_update', __( 'Could not update purchase order.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Apply a receive: deltas are additional qty this shipment per item id.
	 *
	 * @param int                    $id     PO PK.
	 * @param array<int|string,mixed> $deltas item_id => qty delta.
	 * @return true|WP_Error
	 */
	public static function receive( $id, array $deltas ) {
		global $wpdb;

		$order = self::get( (int) $id );
		if ( ! $order ) {
			return new WP_Error( 'som_po_missing', __( 'Purchase order not found.', 'order-machine' ) );
		}
		if ( ! self::can_receive( $order ) ) {
			return new WP_Error( 'som_po_cannot_receive', __( 'This purchase order cannot be received.', 'order-machine' ) );
		}

		$items_by_id = array();
		foreach ( $order->items as $item ) {
			$items_by_id[ (int) $item->id ] = $item;
		}

		$changes = array();
		foreach ( $deltas as $item_id => $raw_delta ) {
			$item_id = (int) $item_id;
			if ( ! isset( $items_by_id[ $item_id ] ) ) {
				return new WP_Error( 'som_po_item_missing', __( 'Unknown purchase order line.', 'order-machine' ) );
			}
			$raw = trim( (string) $raw_delta );
			if ( '' === $raw ) {
				continue;
			}
			if ( ! is_numeric( $raw ) ) {
				return new WP_Error( 'som_po_qty', __( 'Receive quantity must be numeric.', 'order-machine' ) );
			}
			$delta = (float) $raw;
			if ( $delta < 0 ) {
				return new WP_Error( 'som_po_qty', __( 'Receive quantity cannot be negative.', 'order-machine' ) );
			}
			if ( 0.0 === $delta ) {
				continue;
			}
			$changes[ $item_id ] = $delta;
		}

		if ( ! $changes ) {
			return new WP_Error( 'som_po_empty_receive', __( 'Enter at least one quantity to receive.', 'order-machine' ) );
		}

		$alloc = SOM_Material_Costing::write_allocations_for_order( $order );
		if ( is_wp_error( $alloc ) ) {
			return $alloc;
		}

		$landed_by_item = array();
		foreach ( $order->items as $index => $item ) {
			$landed_by_item[ (int) $item->id ] = $alloc['allocations'][ $index ]['landed_unit_cost'];
		}

		$now      = current_time( 'mysql', true );
		$recv_day = current_time( 'Y-m-d' );

		foreach ( $changes as $item_id => $delta ) {
			$item     = $items_by_id[ $item_id ];
			$previous = null === $item->quantity_received || '' === $item->quantity_received
				? 0.0
				: (float) $item->quantity_received;
			$new_qty = $previous + $delta;
			$landed  = isset( $landed_by_item[ $item_id ] ) ? (float) $landed_by_item[ $item_id ] : 0.0;

			$updated = $wpdb->update(
				SOM_DB::table( 'purchase_order_items' ),
				array(
					'quantity_received' => $new_qty,
					'updated_at'        => $now,
				),
				array( 'id' => $item_id ),
				array( '%f', '%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				return new WP_Error( 'som_po_item_update', __( 'Could not update received quantity.', 'order-machine' ) );
			}

			$value_change = SOM_Material_Costing::round4( $delta * $landed );
			$stock        = SOM_Materials::adjust_stock(
				(int) $item->material_id,
				$delta,
				array(
					'reason'                 => 'purchase_received',
					'purchase_order_item_id' => $item_id,
					'unit_cost_at_time'      => $landed,
					'value_change'           => $value_change,
					'sync_unit_cost'         => true,
				)
			);
			if ( is_wp_error( $stock ) ) {
				return $stock;
			}

			SOM_Budgets::drawdown_on_receive( $item_id, $delta, $landed );
		}

		$fresh_items = self::get_items( (int) $order->id );
		$status      = self::compute_status_after_receipts( $fresh_items );

		$ok = $wpdb->update(
			SOM_DB::table( 'purchase_orders' ),
			array(
				'status'        => $status,
				'received_date' => $recv_day,
				'updated_at'    => $now,
			),
			array( 'id' => (int) $order->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_po_status', __( 'Could not update purchase order status.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Accept shortfall: mark PO fully received without further stock.
	 *
	 * @param int $id PO PK.
	 * @return true|WP_Error
	 */
	public static function mark_received( $id ) {
		global $wpdb;

		$order = self::get( (int) $id );
		if ( ! $order ) {
			return new WP_Error( 'som_po_missing', __( 'Purchase order not found.', 'order-machine' ) );
		}
		if ( ! self::can_mark_received( $order ) ) {
			return new WP_Error( 'som_po_cannot_close', __( 'Only partially received orders can be closed as received.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->update(
			SOM_DB::table( 'purchase_orders' ),
			array(
				'status'        => 'received',
				'received_date' => current_time( 'Y-m-d' ),
				'updated_at'    => $now,
			),
			array( 'id' => (int) $order->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_po_close', __( 'Could not mark purchase order as received.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Cancel an open PO (no stock reversal for qty already received).
	 *
	 * @param int $id PO PK.
	 * @return true|WP_Error
	 */
	public static function cancel( $id ) {
		global $wpdb;

		$order = self::get( (int) $id );
		if ( ! $order ) {
			return new WP_Error( 'som_po_missing', __( 'Purchase order not found.', 'order-machine' ) );
		}
		if ( ! self::can_cancel( $order ) ) {
			return new WP_Error( 'som_po_cannot_cancel', __( 'This purchase order cannot be cancelled.', 'order-machine' ) );
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'purchase_orders' ),
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $order->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_po_cancel', __( 'Could not cancel purchase order.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'som-purchase-orders' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * @param int|string $id PO PK or "new".
	 * @return string
	 */
	public static function detail_url( $id ) {
		return self::list_url( array( 'po_id' => $id ) );
	}

	/**
	 * @param int $id PO PK.
	 * @return string
	 */
	public static function receive_url( $id ) {
		return self::list_url(
			array(
				'po_id'   => (int) $id,
				'som_view' => 'receive',
			)
		);
	}

	/**
	 * @param object $order PO row (with items optional).
	 * @return bool
	 */
	public static function can_receive( $order ) {
		return in_array( $order->status, array( 'ordered', 'partially_received' ), true );
	}

	/**
	 * @param object $order PO row.
	 * @return bool
	 */
	public static function can_mark_received( $order ) {
		return 'partially_received' === $order->status;
	}

	/**
	 * @param object $order PO row.
	 * @return bool
	 */
	public static function can_cancel( $order ) {
		return in_array( $order->status, array( 'ordered', 'partially_received' ), true );
	}

	/**
	 * @param object $order PO with optional items.
	 * @return bool
	 */
	private static function has_any_receipt( $order ) {
		$items = isset( $order->items ) ? $order->items : self::get_items( (int) $order->id );
		foreach ( $items as $item ) {
			if ( null !== $item->quantity_received && '' !== $item->quantity_received && (float) $item->quantity_received > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fully received when every line has quantity_received >= quantity_ordered.
	 *
	 * @param array<int, object> $items Line items.
	 * @return string
	 */
	private static function compute_status_after_receipts( array $items ) {
		if ( ! $items ) {
			return 'ordered';
		}

		$any_received = false;
		$all_met      = true;

		foreach ( $items as $item ) {
			$received = null === $item->quantity_received || '' === $item->quantity_received
				? 0.0
				: (float) $item->quantity_received;
			$ordered = (float) $item->quantity_ordered;
			if ( $received > 0 ) {
				$any_received = true;
			}
			if ( $received < $ordered ) {
				$all_met = false;
			}
		}

		if ( $all_met ) {
			return 'received';
		}
		if ( $any_received ) {
			return 'partially_received';
		}
		return 'ordered';
	}

	/**
	 * @param array<string, mixed> $data     Input.
	 * @param bool                 $creating Require all header fields.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function validate_header( array $data, $creating ) {
		$supplier_id = isset( $data['supplier_id'] ) ? (int) $data['supplier_id'] : 0;
		if ( $creating || array_key_exists( 'supplier_id', $data ) ) {
			if ( $supplier_id < 1 || ! SOM_Suppliers::get( $supplier_id ) ) {
				return new WP_Error( 'som_po_supplier', __( 'A valid supplier is required.', 'order-machine' ) );
			}
		}

		$order_date = isset( $data['order_date'] ) ? trim( (string) $data['order_date'] ) : '';
		if ( $creating || array_key_exists( 'order_date', $data ) ) {
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $order_date ) ) {
				return new WP_Error( 'som_po_date', __( 'Order date is required (YYYY-MM-DD).', 'order-machine' ) );
			}
		}

		$shipping = 0.0;
		if ( $creating || array_key_exists( 'shipping_cost', $data ) ) {
			$raw = isset( $data['shipping_cost'] ) ? trim( (string) $data['shipping_cost'] ) : '0';
			if ( '' === $raw ) {
				$raw = '0';
			}
			if ( ! is_numeric( $raw ) || (float) $raw < 0 ) {
				return new WP_Error( 'som_po_shipping', __( 'Shipping cost must be zero or greater.', 'order-machine' ) );
			}
			$shipping = (float) $raw;
		}

		$other = 0.0;
		if ( $creating || array_key_exists( 'other_cost', $data ) ) {
			$raw = isset( $data['other_cost'] ) ? trim( (string) $data['other_cost'] ) : '0';
			if ( '' === $raw ) {
				$raw = '0';
			}
			if ( ! is_numeric( $raw ) || (float) $raw < 0 ) {
				return new WP_Error( 'som_po_other', __( 'Other cost must be zero or greater.', 'order-machine' ) );
			}
			$other = (float) $raw;
		}

		$notes = null;
		if ( array_key_exists( 'notes', $data ) ) {
			$raw   = trim( (string) $data['notes'] );
			$notes = '' === $raw ? null : sanitize_textarea_field( $raw );
		}

		return array(
			'supplier_id'   => $supplier_id,
			'order_date'    => $order_date,
			'shipping_cost' => $shipping,
			'other_cost'    => $other,
			'notes'         => $notes,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Raw item rows.
	 * @return array<int, array{material_id:int,quantity_ordered:float,item_cost:float}>|WP_Error
	 */
	private static function normalize_items( array $rows ) {
		$normalized = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$material_id = isset( $row['material_id'] ) ? (int) $row['material_id'] : 0;
			$qty_raw     = isset( $row['quantity_ordered'] ) ? trim( (string) $row['quantity_ordered'] ) : '';
			$cost_raw    = isset( $row['item_cost'] ) ? trim( (string) $row['item_cost'] ) : '';

			if ( $material_id < 1 && '' === $qty_raw && '' === $cost_raw ) {
				continue;
			}

			if ( $material_id < 1 || ! SOM_Materials::get( $material_id ) ) {
				return new WP_Error( 'som_po_material', __( 'Each line needs a valid material.', 'order-machine' ) );
			}
			if ( '' === $qty_raw || ! is_numeric( $qty_raw ) || (float) $qty_raw <= 0 ) {
				return new WP_Error( 'som_po_qty_ordered', __( 'Quantity ordered must be greater than zero.', 'order-machine' ) );
			}
			if ( '' === $cost_raw || ! is_numeric( $cost_raw ) || (float) $cost_raw < 0 ) {
				return new WP_Error( 'som_po_item_cost', __( 'Item cost (line total) must be zero or greater.', 'order-machine' ) );
			}

			$normalized[] = array(
				'material_id'       => $material_id,
				'quantity_ordered'  => (float) $qty_raw,
				'item_cost'         => (float) $cost_raw,
			);
		}

		if ( ! $normalized ) {
			return new WP_Error( 'som_po_items', __( 'Add at least one line item.', 'order-machine' ) );
		}

		return $normalized;
	}

	/**
	 * Replace all line items for an ordered PO.
	 *
	 * @param int                                                              $po_id PO PK.
	 * @param array<int, array{material_id:int,quantity_ordered:float,item_cost:float}> $items Lines.
	 * @return true|WP_Error
	 */
	private static function replace_items( $po_id, array $items ) {
		global $wpdb;

		$po_id   = (int) $po_id;
		$table   = SOM_DB::table( 'purchase_order_items' );
		$deleted = $wpdb->delete( $table, array( 'purchase_order_id' => $po_id ), array( '%d' ) );
		if ( false === $deleted ) {
			return new WP_Error( 'som_po_items_clear', __( 'Could not update purchase order lines.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		foreach ( $items as $item ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'purchase_order_id'       => $po_id,
					'material_id'             => $item['material_id'],
					'quantity_ordered'        => $item['quantity_ordered'],
					'quantity_received'       => null,
					'item_cost'               => $item['item_cost'],
					'allocated_shipping_cost' => null,
					'allocated_other_cost'    => null,
					'landed_unit_cost'        => null,
					'created_at'              => $now,
					'updated_at'              => $now,
				),
				array( '%d', '%d', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( ! $ok ) {
				return new WP_Error( 'som_po_item_insert', __( 'Could not save purchase order lines.', 'order-machine' ) );
			}
		}

		return true;
	}
}
