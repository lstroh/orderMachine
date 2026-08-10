<?php
/**
 * Budgets CRUD, ledger balance updates, scope helpers, and sale/purchase hooks (U2-1 / U2-2).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Domain helpers for `wp_som_budgets` and related tables.
 *
 * `current_balance` is mutated only via {@see self::insert_ledger()}.
 */
class SOM_Budgets {

	const PER_PAGE = 20;

	const REASON_SALE_FUNDING       = 'sale_funding';
	const REASON_PURCHASE_SPEND     = 'purchase_spend';
	const REASON_MANUAL_ADJUSTMENT  = 'manual_adjustment';

	/**
	 * @param int $id Budget PK.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = SOM_DB::table( 'budgets' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", (int) $id )
		);

		return $row ? $row : null;
	}

	/**
	 * Active material budget for a material (at most one by unique material_id).
	 *
	 * @param int  $material_id Material PK.
	 * @param bool $active_only When true, require is_active = 1.
	 * @return object|null
	 */
	public static function get_for_material( $material_id, $active_only = true ) {
		global $wpdb;

		$material_id = (int) $material_id;
		if ( $material_id < 1 ) {
			return null;
		}

		$table = SOM_DB::table( 'budgets' );
		$sql   = "SELECT * FROM {$table} WHERE type = 'material' AND material_id = %d";
		$args  = array( $material_id );

		if ( $active_only ) {
			$sql .= ' AND is_active = 1';
		}

		$sql .= ' LIMIT 1';

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $args ) );

		return $row ? $row : null;
	}

	/**
	 * @param array<string, mixed> $args Filters: s, type, is_active, paged, per_page.
	 * @return array{budgets: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				's'         => '',
				'type'      => '',
				'is_active' => '',
				'paged'     => 1,
				'per_page'  => self::PER_PAGE,
			)
		);

		$table  = SOM_DB::table( 'budgets' );
		$where  = array( '1=1' );
		$params = array();

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = 'name LIKE %s';
			$params[] = $like;
		}

		$type = sanitize_key( (string) $args['type'] );
		if ( in_array( $type, array( 'material', 'manual' ), true ) ) {
			$where[]  = 'type = %s';
			$params[] = $type;
		}

		if ( '' !== $args['is_active'] && null !== $args['is_active'] ) {
			$where[]  = 'is_active = %d';
			$params[] = (int) (bool) $args['is_active'];
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
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

		$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY name ASC, id ASC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'budgets' => is_array( $rows ) ? $rows : array(),
			'total'   => $total,
			'pages'   => $pages,
			'paged'   => $paged,
		);
	}

	/**
	 * Create a budget. Balance always starts at 0.
	 *
	 * @param array<string, mixed> $data Fields.
	 * @return int|WP_Error New budget ID.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'som_budget_name', __( 'Budget name is required.', 'order-machine' ) );
		}

		$type = isset( $data['type'] ) ? sanitize_key( (string) $data['type'] ) : '';
		if ( ! in_array( $type, array( 'material', 'manual' ), true ) ) {
			return new WP_Error( 'som_budget_type', __( 'Budget type must be material or manual.', 'order-machine' ) );
		}

		$material_id    = null;
		$funding_method = '';
		$funding_value  = null;

		if ( 'material' === $type ) {
			$material_id = isset( $data['material_id'] ) ? (int) $data['material_id'] : 0;
			if ( $material_id < 1 || ! SOM_Materials::get( $material_id ) ) {
				return new WP_Error( 'som_budget_material', __( 'A valid material is required for material budgets.', 'order-machine' ) );
			}
			if ( self::get_for_material( $material_id, false ) ) {
				return new WP_Error( 'som_budget_material_dup', __( 'A budget already exists for this material.', 'order-machine' ) );
			}
			$funding_method = 'material_cost';
			$funding_value  = null;
		} else {
			$funding_method = isset( $data['funding_method'] ) ? sanitize_key( (string) $data['funding_method'] ) : '';
			if ( ! in_array( $funding_method, array( 'percent_of_price', 'percent_of_profit', 'fixed_amount' ), true ) ) {
				return new WP_Error( 'som_budget_funding', __( 'Choose a funding method for the manual budget.', 'order-machine' ) );
			}
			$funding_value = self::parse_funding_value( $data, $funding_method );
			if ( is_wp_error( $funding_value ) ) {
				return $funding_value;
			}
			$material_id = null;
		}

		$target = self::nullable_decimal( $data, 'target_reserve_amount', 2 );
		$notes  = array_key_exists( 'notes', $data ) ? sanitize_textarea_field( (string) $data['notes'] ) : null;
		if ( '' === $notes ) {
			$notes = null;
		}

		$now = current_time( 'mysql', true );

		$row = array(
			'name'                  => $name,
			'type'                  => $type,
			'material_id'           => $material_id,
			'funding_method'        => $funding_method,
			'funding_value'         => $funding_value,
			'target_reserve_amount' => $target,
			'current_balance'       => 0,
			'notes'                 => $notes,
			'is_active'             => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'created_at'            => $now,
			'updated_at'            => $now,
		);

		$formats = array(
			'%s',
			'%s',
			null === $material_id ? '%s' : '%d',
			'%s',
			null === $funding_value ? '%s' : '%s',
			null === $target ? '%s' : '%s',
			'%f',
			'%s',
			'%d',
			'%s',
			'%s',
		);

		$inserted = $wpdb->insert( SOM_DB::table( 'budgets' ), $row, $formats );

		if ( ! $inserted ) {
			return new WP_Error( 'som_budget_create', __( 'Could not create budget.', 'order-machine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update budget metadata (not balance). Type and material_id are immutable.
	 *
	 * @param int                  $budget_id Budget PK.
	 * @param array<string, mixed> $data      Fields.
	 * @return true|WP_Error
	 */
	public static function update( $budget_id, array $data ) {
		global $wpdb;

		$budget_id = (int) $budget_id;
		$before    = self::get( $budget_id );
		if ( $budget_id < 1 || ! $before ) {
			return new WP_Error( 'som_budget_missing', __( 'Budget not found.', 'order-machine' ) );
		}

		$fields  = array(
			'updated_at' => current_time( 'mysql', true ),
		);
		$formats = array( '%s' );

		if ( array_key_exists( 'name', $data ) ) {
			$name = sanitize_text_field( (string) $data['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'som_budget_name', __( 'Budget name is required.', 'order-machine' ) );
			}
			$fields['name'] = $name;
			$formats[]      = '%s';
		}

		if ( array_key_exists( 'notes', $data ) ) {
			$notes = sanitize_textarea_field( (string) $data['notes'] );
			$fields['notes'] = '' === $notes ? null : $notes;
			$formats[]       = '%s';
		}

		if ( array_key_exists( 'target_reserve_amount', $data ) ) {
			$fields['target_reserve_amount'] = self::nullable_decimal( $data, 'target_reserve_amount', 2 );
			$formats[]                       = '%s';
		}

		if ( array_key_exists( 'is_active', $data ) ) {
			$fields['is_active'] = (int) (bool) $data['is_active'];
			$formats[]           = '%d';
		}

		if ( 'manual' === $before->type ) {
			$method = $before->funding_method;
			if ( array_key_exists( 'funding_method', $data ) ) {
				$method = sanitize_key( (string) $data['funding_method'] );
				if ( ! in_array( $method, array( 'percent_of_price', 'percent_of_profit', 'fixed_amount' ), true ) ) {
					return new WP_Error( 'som_budget_funding', __( 'Invalid funding method.', 'order-machine' ) );
				}
				$fields['funding_method'] = $method;
				$formats[]                = '%s';
			}

			if ( array_key_exists( 'funding_value', $data ) || array_key_exists( 'funding_method', $data ) ) {
				$payload = $data;
				if ( ! array_key_exists( 'funding_value', $payload ) ) {
					$payload['funding_value'] = $before->funding_value;
				}
				$value = self::parse_funding_value( $payload, $method );
				if ( is_wp_error( $value ) ) {
					return $value;
				}
				$fields['funding_value'] = $value;
				$formats[]               = '%s';
			}
		}

		$updated = $wpdb->update(
			SOM_DB::table( 'budgets' ),
			$fields,
			array( 'id' => $budget_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'som_budget_update', __( 'Could not update budget.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Soft-activate or deactivate a budget.
	 *
	 * @param int  $budget_id Budget PK.
	 * @param bool $active    Active flag.
	 * @return true|WP_Error
	 */
	public static function set_active( $budget_id, $active ) {
		return self::update( $budget_id, array( 'is_active' => (int) (bool) $active ) );
	}

	/**
	 * Insert a ledger row and update `current_balance` by the same amount.
	 *
	 * This is the only supported way to change balance.
	 *
	 * @param int                  $budget_id Budget PK.
	 * @param float                $change_amount Positive = fund, negative = spend.
	 * @param array<string, mixed> $args Optional: order_id, purchase_order_item_id, reason, notes.
	 * @return int|WP_Error Ledger row ID.
	 */
	public static function insert_ledger( $budget_id, $change_amount, array $args = array() ) {
		global $wpdb;

		$budget_id     = (int) $budget_id;
		$change_amount = SOM_Material_Costing::round4( $change_amount );

		if ( $budget_id < 1 || ! self::get( $budget_id ) ) {
			return new WP_Error( 'som_budget_missing', __( 'Budget not found.', 'order-machine' ) );
		}

		if ( abs( $change_amount ) < 0.0000001 ) {
			return new WP_Error( 'som_budget_ledger_zero', __( 'Ledger change amount cannot be zero.', 'order-machine' ) );
		}

		$reason = isset( $args['reason'] ) ? sanitize_key( (string) $args['reason'] ) : self::REASON_MANUAL_ADJUSTMENT;
		if ( '' === $reason ) {
			$reason = self::REASON_MANUAL_ADJUSTMENT;
		}

		$order_id = array_key_exists( 'order_id', $args ) && null !== $args['order_id'] && '' !== $args['order_id']
			? (int) $args['order_id']
			: null;
		$poi_id   = array_key_exists( 'purchase_order_item_id', $args ) && null !== $args['purchase_order_item_id'] && '' !== $args['purchase_order_item_id']
			? (int) $args['purchase_order_item_id']
			: null;

		$notes = array_key_exists( 'notes', $args ) ? sanitize_textarea_field( (string) $args['notes'] ) : null;
		if ( '' === $notes ) {
			$notes = null;
		}

		$now = current_time( 'mysql', true );

		$log_ok = $wpdb->insert(
			SOM_DB::table( 'budget_ledger' ),
			array(
				'budget_id'              => $budget_id,
				'order_id'               => $order_id,
				'purchase_order_item_id' => $poi_id,
				'change_amount'          => $change_amount,
				'reason'                 => $reason,
				'notes'                  => $notes,
				'created_at'             => $now,
			),
			array(
				'%d',
				null === $order_id ? '%s' : '%d',
				null === $poi_id ? '%s' : '%d',
				'%f',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( ! $log_ok ) {
			return new WP_Error( 'som_budget_ledger', __( 'Could not write budget ledger entry.', 'order-machine' ) );
		}

		$ledger_id = (int) $wpdb->insert_id;
		$table     = SOM_DB::table( 'budgets' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$balanced = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET current_balance = current_balance + %f,
				    updated_at = %s
				WHERE id = %d",
				$change_amount,
				$now,
				$budget_id
			)
		);

		if ( false === $balanced ) {
			return new WP_Error( 'som_budget_balance', __( 'Could not update budget balance.', 'order-machine' ) );
		}

		return $ledger_id;
	}

	/**
	 * Ledger rows for a budget (newest first).
	 *
	 * @param int $budget_id Budget PK.
	 * @param int $limit     Max rows.
	 * @return array<int, object>
	 */
	public static function get_ledger( $budget_id, $limit = 50 ) {
		global $wpdb;

		$table = SOM_DB::table( 'budget_ledger' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE budget_id = %d ORDER BY id DESC LIMIT %d",
				(int) $budget_id,
				max( 1, (int) $limit )
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Product IDs linked to a budget (empty = all products for manual scope semantics).
	 *
	 * @param int $budget_id Budget PK.
	 * @return array<int, int>
	 */
	public static function get_product_link_ids( $budget_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'budget_product_links' );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT product_id FROM {$table} WHERE budget_id = %d ORDER BY product_id ASC",
				(int) $budget_id
			)
		);

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Replace product scope links for a budget.
	 *
	 * @param int        $budget_id   Budget PK.
	 * @param array<int> $product_ids Product PKs (empty clears = global for manual).
	 * @return true|WP_Error
	 */
	public static function set_product_links( $budget_id, array $product_ids ) {
		global $wpdb;

		$budget_id = (int) $budget_id;
		if ( $budget_id < 1 || ! self::get( $budget_id ) ) {
			return new WP_Error( 'som_budget_missing', __( 'Budget not found.', 'order-machine' ) );
		}

		$clean = array();
		foreach ( $product_ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid < 1 ) {
				continue;
			}
			if ( ! SOM_Products::get( $pid ) ) {
				return new WP_Error( 'som_budget_product', __( 'One or more products were not found.', 'order-machine' ) );
			}
			$clean[ $pid ] = $pid;
		}
		$clean = array_values( $clean );

		$table = SOM_DB::table( 'budget_product_links' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE budget_id = %d", $budget_id ) );

		$now = current_time( 'mysql', true );
		foreach ( $clean as $pid ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'budget_id'  => $budget_id,
					'product_id' => $pid,
					'created_at' => $now,
				),
				array( '%d', '%d', '%s' )
			);
			if ( ! $ok ) {
				return new WP_Error( 'som_budget_product_link', __( 'Could not save product links.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Workflow template IDs linked to a budget (empty = global for material scope semantics).
	 *
	 * @param int $budget_id Budget PK.
	 * @return array<int, int>
	 */
	public static function get_workflow_link_ids( $budget_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'budget_workflow_links' );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT workflow_template_id FROM {$table} WHERE budget_id = %d ORDER BY workflow_template_id ASC",
				(int) $budget_id
			)
		);

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Replace workflow scope links for a budget.
	 *
	 * @param int        $budget_id             Budget PK.
	 * @param array<int> $workflow_template_ids Template PKs (empty clears = global).
	 * @return true|WP_Error
	 */
	public static function set_workflow_links( $budget_id, array $workflow_template_ids ) {
		global $wpdb;

		$budget_id = (int) $budget_id;
		if ( $budget_id < 1 || ! self::get( $budget_id ) ) {
			return new WP_Error( 'som_budget_missing', __( 'Budget not found.', 'order-machine' ) );
		}

		$clean = array();
		foreach ( $workflow_template_ids as $tid ) {
			$tid = (int) $tid;
			if ( $tid < 1 ) {
				continue;
			}
			if ( ! SOM_Workflows::get( $tid ) ) {
				return new WP_Error( 'som_budget_workflow', __( 'One or more workflow templates were not found.', 'order-machine' ) );
			}
			$clean[ $tid ] = $tid;
		}
		$clean = array_values( $clean );

		$table = SOM_DB::table( 'budget_workflow_links' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE budget_id = %d", $budget_id ) );

		$now = current_time( 'mysql', true );
		foreach ( $clean as $tid ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'budget_id'             => $budget_id,
					'workflow_template_id'  => $tid,
					'created_at'            => $now,
				),
				array( '%d', '%d', '%s' )
			);
			if ( ! $ok ) {
				return new WP_Error( 'som_budget_workflow_link', __( 'Could not save workflow links.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Whether a budget’s product scope includes a product (empty links = all).
	 *
	 * @param int $budget_id  Budget PK.
	 * @param int $product_id Product PK.
	 * @return bool
	 */
	public static function applies_to_product( $budget_id, $product_id ) {
		$links = self::get_product_link_ids( $budget_id );
		if ( ! $links ) {
			return true;
		}
		return in_array( (int) $product_id, $links, true );
	}

	/**
	 * Whether a budget’s workflow scope includes a template (empty links = all).
	 *
	 * @param int $budget_id            Budget PK.
	 * @param int $workflow_template_id Template PK (0 / missing = not in a scoped set).
	 * @return bool
	 */
	public static function applies_to_workflow( $budget_id, $workflow_template_id ) {
		$links = self::get_workflow_link_ids( $budget_id );
		if ( ! $links ) {
			return true;
		}
		$workflow_template_id = (int) $workflow_template_id;
		if ( $workflow_template_id < 1 ) {
			return false;
		}
		return in_array( $workflow_template_id, $links, true );
	}

	/**
	 * Fund budgets when a new order is created (same `$apply_stock` gate as stock decrement).
	 *
	 * Material amounts come from `material_stock_log` (`new_order`). Manual amounts are
	 * computed per order item. Skips cancelled orders, inactive budgets, and re-entry when
	 * any `sale_funding` row already exists for the order.
	 *
	 * @param int $order_id Order PK.
	 * @return true Always true (ledger failures are logged; sync must not abort).
	 */
	public static function fund_on_create( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return true;
		}

		$order = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return true;
		}

		if ( ! empty( $order->is_cancelled ) ) {
			return true;
		}

		if ( self::has_sale_funding_for_order( $order_id ) ) {
			return true;
		}

		$workflow_id = self::order_workflow_template_id( $order );

		self::fund_material_from_stock_log( $order_id, $workflow_id );
		self::fund_manual_from_order_items( $order, $order_id );

		return true;
	}

	/**
	 * Draw down a material budget after a successful PO line receive.
	 *
	 * No-op when no active material budget exists or amount rounds to zero.
	 * Ledger failures are logged; caller should continue the receive loop.
	 *
	 * @param int   $purchase_order_item_id PO line PK.
	 * @param float $qty_received           Positive qty just received.
	 * @param float $landed_unit_cost       Landed unit cost for the line.
	 * @return true|WP_Error True on success/no-op; WP_Error on ledger failure (already logged).
	 */
	public static function drawdown_on_receive( $purchase_order_item_id, $qty_received, $landed_unit_cost ) {
		global $wpdb;

		$purchase_order_item_id = (int) $purchase_order_item_id;
		$qty_received           = (float) $qty_received;
		$landed_unit_cost       = (float) $landed_unit_cost;

		if ( $purchase_order_item_id < 1 || $qty_received <= 0 ) {
			return true;
		}

		$items_t = SOM_DB::table( 'purchase_order_items' );
		$material_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT material_id FROM {$items_t} WHERE id = %d LIMIT 1",
				$purchase_order_item_id
			)
		);
		if ( $material_id < 1 ) {
			return true;
		}

		$budget = self::get_for_material( $material_id, true );
		if ( ! $budget ) {
			return true;
		}

		$amount = SOM_Material_Costing::round4( -( $qty_received * $landed_unit_cost ) );
		if ( abs( $amount ) < 0.0000001 ) {
			return true;
		}

		$result = self::insert_ledger(
			(int) $budget->id,
			$amount,
			array(
				'purchase_order_item_id' => $purchase_order_item_id,
				'reason'                 => self::REASON_PURCHASE_SPEND,
			)
		);

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[Order Machine] Budget draw-down failed for PO item %d (budget %d): %s',
					$purchase_order_item_id,
					(int) $budget->id,
					$result->get_error_message()
				)
			);
			return $result;
		}

		return true;
	}

	/**
	 * Linked R&D / non-sale write-off: decrement stock and debit material budget.
	 *
	 * Budget debit is skipped (stock still adjusted) when no active material budget exists.
	 *
	 * @param int    $material_id Material PK.
	 * @param float  $qty         Quantity to remove (must be > 0).
	 * @param string $notes       Reason notes (required).
	 * @return array{stock_adjusted:bool,budget_id:?int,ledger_id:?int,change_amount:float}|WP_Error
	 */
	public static function write_off_material( $material_id, $qty, $notes ) {
		$material_id = (int) $material_id;
		$qty         = (float) $qty;
		$notes       = trim( sanitize_textarea_field( (string) $notes ) );

		if ( $material_id < 1 ) {
			return new WP_Error( 'som_budget_material', __( 'Material is required.', 'order-machine' ) );
		}
		if ( $qty <= 0 ) {
			return new WP_Error( 'som_budget_writeoff_qty', __( 'Write-off quantity must be greater than zero.', 'order-machine' ) );
		}
		if ( '' === $notes ) {
			return new WP_Error( 'som_budget_writeoff_notes', __( 'Notes are required for a write-off (e.g. R&D).', 'order-machine' ) );
		}

		$material = SOM_Materials::get( $material_id );
		if ( ! $material ) {
			return new WP_Error( 'som_material_missing', __( 'Material not found.', 'order-machine' ) );
		}

		$unit_cost = SOM_Material_Costing::unit_cost_for_consumption( $material );
		$debit     = SOM_Material_Costing::round4( $qty * $unit_cost );

		$stock = SOM_Materials::adjust_stock(
			$material_id,
			-$qty,
			array(
				'reason' => self::REASON_MANUAL_ADJUSTMENT,
			)
		);
		if ( is_wp_error( $stock ) ) {
			return $stock;
		}

		$budget    = self::get_for_material( $material_id, true );
		$budget_id = null;
		$ledger_id = null;

		if ( $budget && $debit > 0 ) {
			$budget_id = (int) $budget->id;
			$ledger_id = self::insert_ledger(
				$budget_id,
				-$debit,
				array(
					'reason' => self::REASON_MANUAL_ADJUSTMENT,
					'notes'  => $notes,
				)
			);
			if ( is_wp_error( $ledger_id ) ) {
				return $ledger_id;
			}
		}

		return array(
			'stock_adjusted' => true,
			'budget_id'      => $budget_id,
			'ledger_id'      => is_int( $ledger_id ) ? $ledger_id : null,
			'change_amount'  => $budget_id ? -$debit : 0.0,
		);
	}

	/**
	 * @param object $budget Budget row.
	 * @return bool
	 */
	public static function is_overspent( $budget ) {
		return (float) $budget->current_balance < 0;
	}

	/**
	 * @param object $budget Budget row.
	 * @return bool
	 */
	public static function is_low_balance( $budget ) {
		if ( null === $budget->target_reserve_amount || '' === $budget->target_reserve_amount ) {
			return false;
		}
		return (float) $budget->current_balance < (float) $budget->target_reserve_amount;
	}

	/**
	 * @param string $reason Stored reason code.
	 * @return string
	 */
	public static function reason_label( $reason ) {
		$labels = array(
			self::REASON_SALE_FUNDING      => __( 'Sale funding', 'order-machine' ),
			self::REASON_PURCHASE_SPEND    => __( 'Purchase spend', 'order-machine' ),
			self::REASON_MANUAL_ADJUSTMENT => __( 'Manual adjustment', 'order-machine' ),
		);

		$reason = sanitize_key( (string) $reason );
		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}

	/**
	 * @param string $type Budget type.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'material' => __( 'Material', 'order-machine' ),
			'manual'   => __( 'Manual', 'order-machine' ),
		);
		$type = sanitize_key( (string) $type );
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * @param string $method Funding method code.
	 * @return string
	 */
	public static function funding_method_label( $method ) {
		$labels = array(
			'material_cost'     => __( 'Material cost (auto)', 'order-machine' ),
			'percent_of_price'  => __( '% of sale price', 'order-machine' ),
			'percent_of_profit' => __( '% of profit', 'order-machine' ),
			'fixed_amount'      => __( 'Fixed amount per unit', 'order-machine' ),
		);
		$method = sanitize_key( (string) $method );
		return isset( $labels[ $method ] ) ? $labels[ $method ] : $method;
	}

	/**
	 * Active materials that do not yet have a material budget (optionally keep one selected).
	 *
	 * @param int $include_material_id Always include this material id if set.
	 * @return array<int, object>
	 */
	public static function materials_available_for_budget( $include_material_id = 0 ) {
		$include_material_id = (int) $include_material_id;
		$active              = SOM_Materials::list_active();
		$out                 = array();

		foreach ( $active as $material ) {
			$mid = (int) $material->id;
			if ( $include_material_id > 0 && $mid === $include_material_id ) {
				$out[] = $material;
				continue;
			}
			if ( ! self::get_for_material( $mid, false ) ) {
				$out[] = $material;
			}
		}

		if ( $include_material_id > 0 ) {
			$have = false;
			foreach ( $out as $row ) {
				if ( (int) $row->id === $include_material_id ) {
					$have = true;
					break;
				}
			}
			if ( ! $have ) {
				$extra = SOM_Materials::get( $include_material_id );
				if ( $extra ) {
					$out[] = (object) array(
						'id'   => (int) $extra->id,
						'name' => (string) $extra->name,
						'unit' => (string) $extra->unit,
					);
				}
			}
		}

		return $out;
	}

	/**
	 * Admin list URL.
	 *
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		$args = array_merge( array( 'page' => 'som-budgets' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Admin create/edit URL.
	 *
	 * @param int|string $budget_id Budget PK or "new".
	 * @return string
	 */
	public static function detail_url( $budget_id ) {
		return self::list_url( array( 'budget_id' => $budget_id ) );
	}

	/**
	 * @param int $order_id Order PK.
	 * @return bool
	 */
	private static function has_sale_funding_for_order( $order_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'budget_ledger' );
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE order_id = %d AND reason = %s LIMIT 1",
				(int) $order_id,
				self::REASON_SALE_FUNDING
			)
		);

		return ! empty( $found );
	}

	/**
	 * Workflow template for the order’s primary product (0 if unassigned).
	 *
	 * @param object $order Order with items.
	 * @return int
	 */
	private static function order_workflow_template_id( $order ) {
		$product_id = SOM_Workflow_Engine::primary_product_id( $order );
		if ( $product_id < 1 ) {
			return 0;
		}

		$product = SOM_Products::get( $product_id );
		if ( ! $product || empty( $product->workflow_template_id ) ) {
			return 0;
		}

		return (int) $product->workflow_template_id;
	}

	/**
	 * Material budgets: one sale_funding row per new_order stock-log line.
	 *
	 * @param int $order_id    Order PK.
	 * @param int $workflow_id Primary-product workflow template ID (0 = none).
	 * @return void
	 */
	private static function fund_material_from_stock_log( $order_id, $workflow_id ) {
		global $wpdb;

		$log_t = SOM_DB::table( 'material_stock_log' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT material_id, change_qty, unit_cost_at_time
				FROM {$log_t}
				WHERE order_id = %d AND reason = %s
				ORDER BY id ASC",
				(int) $order_id,
				'new_order'
			)
		);

		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$material_id = (int) $row->material_id;
			$qty         = abs( (float) $row->change_qty );
			$unit        = (float) $row->unit_cost_at_time;
			$amount      = SOM_Material_Costing::round4( $qty * $unit );

			if ( $material_id < 1 || abs( $amount ) < 0.0000001 ) {
				continue;
			}

			$budget = self::get_for_material( $material_id, true );
			if ( ! $budget ) {
				continue;
			}

			if ( ! self::applies_to_workflow( (int) $budget->id, (int) $workflow_id ) ) {
				continue;
			}

			$result = self::insert_ledger(
				(int) $budget->id,
				$amount,
				array(
					'order_id' => (int) $order_id,
					'reason'   => self::REASON_SALE_FUNDING,
				)
			);

			if ( is_wp_error( $result ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[Order Machine] Budget sale funding failed for order %d material %d (budget %d): %s',
						(int) $order_id,
						$material_id,
						(int) $budget->id,
						$result->get_error_message()
					)
				);
			}
		}
	}

	/**
	 * Manual budgets: one sale_funding row per order item × matching budget.
	 * Workflow links on manual budgets are ignored.
	 *
	 * @param object $order    Order with items.
	 * @param int    $order_id Order PK.
	 * @return void
	 */
	private static function fund_manual_from_order_items( $order, $order_id ) {
		$items = ! empty( $order->items ) && is_array( $order->items ) ? $order->items : array();
		if ( ! $items ) {
			return;
		}

		$listed = self::query(
			array(
				'type'      => 'manual',
				'is_active' => 1,
				'paged'     => 1,
				'per_page'  => 10000,
			)
		);
		$budgets = $listed['budgets'];
		if ( ! $budgets ) {
			return;
		}

		foreach ( $items as $item ) {
			$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
			if ( $product_id < 1 ) {
				continue;
			}

			$qty = isset( $item->quantity ) ? max( 1, (int) $item->quantity ) : 1;
			$product = SOM_Products::get( $product_id );
			if ( ! $product ) {
				continue;
			}

			$sold = self::effective_sold_price( $item, $product );
			$costing = null;

			foreach ( $budgets as $budget ) {
				if ( ! self::applies_to_product( (int) $budget->id, $product_id ) ) {
					continue;
				}

				$amount = self::manual_funding_amount( $budget, $sold, $qty, $product_id, $costing, $order );
				if ( null === $amount || abs( $amount ) < 0.0000001 ) {
					continue;
				}

				$result = self::insert_ledger(
					(int) $budget->id,
					$amount,
					array(
						'order_id' => (int) $order_id,
						'reason'   => self::REASON_SALE_FUNDING,
					)
				);

				if ( is_wp_error( $result ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						sprintf(
							'[Order Machine] Budget sale funding failed for order %d item product %d (budget %d): %s',
							(int) $order_id,
							$product_id,
							(int) $budget->id,
							$result->get_error_message()
						)
					);
				}
			}
		}
	}

	/**
	 * Effective sold unit price: unit_price when set (including 0), else target_selling_price.
	 *
	 * @param object $item    Order item row.
	 * @param object $product Product row.
	 * @return float|null Null when neither is available.
	 */
	private static function effective_sold_price( $item, $product ) {
		if ( isset( $item->unit_price ) && null !== $item->unit_price && '' !== $item->unit_price ) {
			return (float) $item->unit_price;
		}
		if ( isset( $product->target_selling_price ) && null !== $product->target_selling_price && '' !== $product->target_selling_price ) {
			return (float) $product->target_selling_price;
		}
		return null;
	}

	/**
	 * @param object      $budget     Manual budget row.
	 * @param float|null  $sold       Effective sold unit price.
	 * @param int         $qty        Line quantity.
	 * @param int         $product_id Product PK.
	 * @param array|null  $costing    Cached recipe_costing result (by ref).
	 * @param object|null $order      Order row (channel_id + id) for fee-aware profit.
	 * @return float|null Rounded amount, or null when not computable / zero-skip for missing price.
	 */
	private static function manual_funding_amount( $budget, $sold, $qty, $product_id, &$costing, $order = null ) {
		$method = (string) $budget->funding_method;
		$value  = (float) $budget->funding_value;
		$qty    = max( 1, (int) $qty );

		if ( 'fixed_amount' === $method ) {
			return SOM_Material_Costing::round4( $value * $qty );
		}

		if ( null === $sold ) {
			return null;
		}

		if ( 'percent_of_price' === $method ) {
			return SOM_Material_Costing::round4( $sold * $qty * ( $value / 100 ) );
		}

		if ( 'percent_of_profit' === $method ) {
			if ( null === $costing ) {
				$costing = SOM_Products::recipe_costing( (int) $product_id );
			}
			$material_cost = ( $costing && isset( $costing['material_cost'] ) )
				? (float) $costing['material_cost']
				: 0.0;

			if ( $order && ! empty( $order->id ) && ! empty( $order->channel_id ) ) {
				$line = SOM_Platform_Fees::line_profit(
					(float) $sold,
					$qty,
					$material_cost,
					(int) $order->id,
					(int) $order->channel_id
				);
				return SOM_Material_Costing::round4( (float) $line['profit'] * ( $value / 100 ) );
			}

			$profit = $sold - $material_cost;
			return SOM_Material_Costing::round4( $profit * $qty * ( $value / 100 ) );
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $data   Input.
	 * @param string               $method Funding method.
	 * @return string|WP_Error Decimal string.
	 */
	private static function parse_funding_value( array $data, $method ) {
		if ( ! array_key_exists( 'funding_value', $data ) || '' === trim( (string) $data['funding_value'] ) ) {
			return new WP_Error( 'som_budget_funding_value', __( 'Funding value is required.', 'order-machine' ) );
		}

		$raw = trim( (string) $data['funding_value'] );
		if ( ! is_numeric( $raw ) ) {
			return new WP_Error( 'som_budget_funding_value', __( 'Funding value must be numeric.', 'order-machine' ) );
		}

		$value = (float) $raw;
		if ( $value < 0 ) {
			return new WP_Error( 'som_budget_funding_value', __( 'Funding value cannot be negative.', 'order-machine' ) );
		}

		if ( in_array( $method, array( 'percent_of_price', 'percent_of_profit' ), true ) && $value > 100 ) {
			return new WP_Error( 'som_budget_funding_value', __( 'Percentage funding value must be between 0 and 100.', 'order-machine' ) );
		}

		return number_format( $value, 4, '.', '' );
	}

	/**
	 * @param array<string, mixed> $data  Input.
	 * @param string               $key   Field key.
	 * @param int                  $scale Decimal places.
	 * @return string|null
	 */
	private static function nullable_decimal( array $data, $key, $scale = 2 ) {
		if ( ! array_key_exists( $key, $data ) || null === $data[ $key ] || '' === trim( (string) $data[ $key ] ) ) {
			return null;
		}
		if ( ! is_numeric( $data[ $key ] ) ) {
			return null;
		}
		return number_format( (float) $data[ $key ], $scale, '.', '' );
	}
}
