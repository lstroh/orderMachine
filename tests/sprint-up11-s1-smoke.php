<?php
/**
 * UP11-S1 smoke: post_ship kind, Ship does not complete, auto-start, arrival→complete, product override, Internal skip.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up11-s1-smoke.php
 *
 * @package OrderMachine
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

$fail = 0;
$out  = static function ( $label, $value ) {
	echo $label . ': ' . ( is_string( $value ) ? $value : wp_json_encode( $value ) ) . "\n";
};
$assert = static function ( $ok, $label ) use ( &$fail, $out ) {
	if ( $ok ) {
		$out( $label, 'PASS' );
		return;
	}
	++$fail;
	$out( $label, 'FAIL' );
};

global $wpdb;

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();
SOM_Seed::maybe_seed_catalogue();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.24.0', '>=' ), 'db_gte_1.24.0' );
$assert( class_exists( 'SOM_Post_Ship' ), 'class_SOM_Post_Ship' );
$assert( isset( SOM_Workflows::kind_choices()['post_ship'] ), 'kind_choices_post_ship' );
$assert( in_array( SOM_Step_Confirmations::KIND_PACKAGE_ARRIVED, SOM_Step_Confirmations::kinds(), true ), 'confirmation_package_arrived' );

$orders_t   = SOM_DB::table( 'orders' );
$products_t = SOM_DB::table( 'products' );
$order_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$orders_t}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
foreach ( array( 'post_ship_workflow_template_id', 'post_ship_started_at', 'arrival_confirmed_at', 'arrival_confirmed_by' ) as $col ) {
	$assert( in_array( $col, $order_cols, true ), 'orders_col_' . $col );
}
$product_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$products_t}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( in_array( 'post_ship_workflow_template_id', $product_cols, true ), 'products_col_post_ship' );

$tpl_t = SOM_DB::table( 'workflow_templates' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$kind_col = $wpdb->get_row( "SHOW COLUMNS FROM {$tpl_t} LIKE 'kind'" );
$assert( $kind_col && false !== stripos( (string) $kind_col->Type, 'post_ship' ), 'enum_includes_post_ship' );

$suffix = wp_generate_password( 4, false, false );

// --- Templates ---
$make_wf = SOM_Workflows::create(
	array(
		'name'      => 'UP11-S1 Make ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $make_wf ), 'create_make' );
$make_wf_id = (int) $make_wf;
SOM_Workflows::save_steps(
	$make_wf_id,
	array(
		array(
			'name'                    => 'Print',
			'requires_manual_confirm' => 1,
		),
	)
);

$pack_wf = SOM_Workflows::create(
	array(
		'name'      => 'UP11-S1 Pack ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $pack_wf ), 'create_pack' );
$pack_wf_id = (int) $pack_wf;
SOM_Workflows::save_steps(
	$pack_wf_id,
	array(
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Ship',
			'requires_manual_confirm' => 1,
		),
	)
);

$ps_default = SOM_Workflows::create(
	array(
		'name'      => 'UP11-S1 Aftercare default ' . $suffix,
		'kind'      => 'post_ship',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $ps_default ), 'create_post_ship_default' );
$ps_default_id = (int) $ps_default;
SOM_Workflows::save_steps(
	$ps_default_id,
	array(
		array(
			'name'                    => 'Update buyer',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Confirm arrived',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'package_arrived',
		),
		array(
			'name'                    => 'Review reminder',
			'requires_manual_confirm' => 1,
			'timer_seconds'           => 60,
		),
	)
);

$ps_override = SOM_Workflows::create(
	array(
		'name'      => 'UP11-S1 Aftercare override ' . $suffix,
		'kind'      => 'post_ship',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $ps_override ), 'create_post_ship_override' );
$ps_override_id = (int) $ps_override;
SOM_Workflows::save_steps(
	$ps_override_id,
	array(
		array(
			'name'                    => 'Confirm arrived',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'package_arrived',
		),
	)
);

$assert( 'post_ship' === SOM_Workflows::sanitize_kind( 'post_ship' ), 'sanitize_kind_post_ship' );
$assert( 'post_ship' === SOM_Workflows::get_kind( $ps_default_id ), 'get_kind_post_ship' );

$prev_pack = SOM_Pack::default_template_id();
$prev_ps   = SOM_Post_Ship::default_template_id();
$prev_auto = SOM_Post_Ship::auto_start_enabled();

$set_pack = SOM_Pack::set_default_template_id( $pack_wf_id );
$assert( true === $set_pack, 'set_default_pack' );
$set_ps = SOM_Post_Ship::set_default_template_id( $ps_default_id );
$assert( true === $set_ps, 'set_default_post_ship' );
$reject_pack_as_ps = SOM_Post_Ship::set_default_template_id( $pack_wf_id );
$assert( is_wp_error( $reject_pack_as_ps ), 'reject_pack_as_post_ship_default' );
SOM_Post_Ship::set_default_template_id( $ps_default_id );
SOM_Post_Ship::set_auto_start_enabled( true );
$assert( SOM_Post_Ship::auto_start_enabled(), 'auto_start_on' );

// Sellable product (site default post-ship).
$prod_default = SOM_Products::create(
	array(
		'name'                 => 'UP11-S1 Sellable ' . $suffix,
		'sku'                  => 'UP11S1-' . $suffix,
		'workflow_template_id' => $make_wf_id,
		'is_active'            => 1,
		'is_internal'          => 0,
		'weight_grams'         => 50,
	)
);
$assert( ! is_wp_error( $prod_default ), 'create_sellable' );
$prod_default_id = (int) $prod_default;

// Sellable with product override.
$prod_override = SOM_Products::create(
	array(
		'name'                           => 'UP11-S1 Override ' . $suffix,
		'sku'                            => 'UP11S1O-' . $suffix,
		'workflow_template_id'           => $make_wf_id,
		'post_ship_workflow_template_id' => $ps_override_id,
		'is_active'                      => 1,
		'is_internal'                    => 0,
		'weight_grams'                   => 50,
	)
);
$assert( ! is_wp_error( $prod_override ), 'create_override_product' );
$prod_override_id = (int) $prod_override;
$ov_row           = SOM_Products::get( $prod_override_id );
$assert( $ov_row && (int) $ov_row->post_ship_workflow_template_id === $ps_override_id, 'product_override_saved' );

// Shipping package.
$packages = SOM_Shipping_Packages::list_active();
if ( empty( $packages ) ) {
	$pkg_id = SOM_Shipping_Packages::create(
		array(
			'name'              => 'UP11-S1 Mailer ' . $suffix,
			'length_mm'         => 100,
			'width_mm'          => 80,
			'height_mm'         => 20,
			'tare_weight_grams' => 15,
			'is_default'        => 1,
			'is_active'         => 1,
		)
	);
	$assert( ! is_wp_error( $pkg_id ), 'create_package' );
	$packages = SOM_Shipping_Packages::list_active();
}
$assert( ! empty( $packages ), 'has_package' );
$package_id = (int) $packages[0]->id;

/**
 * Soft-complete make lines via direct DB update.
 *
 * @param int $order_id Order PK.
 */
$soft_complete_make = static function ( $order_id ) use ( $wpdb ) {
	$item_prog_t = SOM_DB::table( 'order_item_step_progress' );
	$now         = current_time( 'mysql', true );
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$item_prog_t} SET status = 'done', started_at = COALESCE(started_at, %s), completed_at = %s WHERE order_id = %d AND status <> 'done'",
			$now,
			$now,
			(int) $order_id
		)
	);
};

/**
 * Advance pack to Ship and record shipment.
 *
 * @param int $order_id Order PK.
 * @return true|WP_Error
 */
$finish_to_ship = static function ( $order_id ) use ( $package_id, $soft_complete_make, $suffix ) {
	$soft_complete_make( $order_id );
	SOM_Pack::set_shipping_package( $order_id, $package_id );

	$order = SOM_Orders::get( $order_id );
	if ( ! $order || empty( $order->current_step_id ) ) {
		return new WP_Error( 'no_step', 'no current pack step' );
	}

	// Mark Confirm pack done if that is current.
	$step = null;
	foreach ( $order->workflow_progress as $row ) {
		if ( (int) $row->workflow_step_id === (int) $order->current_step_id ) {
			$step = $row;
			break;
		}
	}
	if ( $step && 'Ship' !== (string) $step->step_name ) {
		$done = SOM_Workflow_Engine::mark_done( $order_id );
		if ( is_wp_error( $done ) ) {
			return $done;
		}
	}

	$ship_ok = SOM_Shipments::upsert(
		$order_id,
		array(
			'carrier'         => 'Royal Mail',
			'service'         => 'Tracked 48',
			'shipped_at'      => gmdate( 'Y-m-d H:i:s' ),
			'postage_paid'    => '3.50',
			'tracking_number' => 'UP11S1' . $suffix . $order_id,
		)
	);
	if ( is_wp_error( $ship_ok ) ) {
		return $ship_ok;
	}

	return SOM_Workflow_Engine::mark_done( $order_id );
};

// --- A: Ship does not complete; auto-start binds default post-ship ---
$ext_a    = 'up11s1a-' . gmdate( 'YmdHis' ) . '-' . $suffix;
$order_a  = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $ext_a,
		'buyer_name'        => 'UP11-S1 A',
		'shipping_address'  => array(
			'line1'    => '1 Test St',
			'city'     => 'London',
			'postcode' => 'E1 1AA',
			'country'  => 'GB',
		),
		'items'             => array(
			array(
				'product_id' => $prod_default_id,
				'quantity'   => 1,
				'unit_price' => 12.0,
			),
		),
	)
);
$assert( ! is_wp_error( $order_a ), 'create_order_a' );
$order_a_id = (int) $order_a;

$ship_a = $finish_to_ship( $order_a_id );
$assert( ! is_wp_error( $ship_a ), 'ship_mark_done_a' );
if ( is_wp_error( $ship_a ) ) {
	$out( 'ship_a_err', $ship_a->get_error_message() );
}

$order_a = SOM_Orders::get( $order_a_id );
$assert( empty( $order_a->is_complete ), 'ship_does_not_complete_order' );
$assert( ! empty( $order_a->post_ship_workflow_template_id ), 'auto_start_bound' );
$assert( (int) $order_a->post_ship_workflow_template_id === $ps_default_id, 'auto_start_uses_site_default' );
$assert( ! empty( $order_a->post_ship_started_at ), 'auto_start_started_at' );
$assert( ! empty( $order_a->current_step_id ), 'entered_first_post_ship_step' );

$ps_rows = SOM_Post_Ship::filter_progress_by_kind( $order_a->workflow_progress, 'post_ship' );
$assert( count( $ps_rows ) >= 3, 'post_ship_progress_rows' );
$first_ps = null;
foreach ( $ps_rows as $row ) {
	if ( (int) $row->workflow_step_id === (int) $order_a->current_step_id ) {
		$first_ps = $row;
		break;
	}
}
$assert( $first_ps && 'Update buyer' === (string) $first_ps->step_name, 'current_is_first_post_ship' );

// Advance to arrival confirm.
$done_buyer = SOM_Workflow_Engine::mark_done( $order_a_id );
$assert( ! is_wp_error( $done_buyer ), 'mark_update_buyer' );
$order_a = SOM_Orders::get( $order_a_id );
$assert( empty( $order_a->is_complete ), 'still_incomplete_before_arrival' );

// Block mark done without checklist.
$blocked = SOM_Workflow_Engine::mark_done( $order_a_id );
$assert( is_wp_error( $blocked ) && 'som_confirmation_required' === $blocked->get_error_code(), 'arrival_checklist_gates' );

$saved = SOM_Step_Confirmations::save_for_order(
	$order_a_id,
	array( 'package_arrived' => 1 )
);
$assert( ! is_wp_error( $saved ), 'save_arrival_checklist' );

$done_arrive = SOM_Workflow_Engine::mark_done( $order_a_id );
$assert( ! is_wp_error( $done_arrive ), 'mark_arrival_done' );

$order_a = SOM_Orders::get( $order_a_id );
$assert( ! empty( $order_a->arrival_confirmed_at ), 'arrival_confirmed_at_set' );
$assert( ! empty( $order_a->is_complete ), 'arrival_plus_shipment_completes' );
$assert( ! empty( $order_a->current_step_id ), 'review_step_still_current_after_complete' );

// Review reminder can still Mark done on a complete order (O1).
$order_a = SOM_Orders::get( $order_a_id );
$review  = null;
foreach ( SOM_Post_Ship::filter_progress_by_kind( $order_a->workflow_progress, 'post_ship' ) as $row ) {
	if ( (int) $row->workflow_step_id === (int) $order_a->current_step_id ) {
		$review = $row;
		break;
	}
}
$assert( $review && 'Review reminder' === (string) $review->step_name, 'on_review_reminder' );
// Unblock timer if waiting, then mark done.
if ( $review && 'waiting_timer' === (string) $review->status ) {
	$unblocked = SOM_Workflow_Engine::unblock_step( $order_a_id );
	$assert( ! is_wp_error( $unblocked ), 'unblock_review_timer' );
}
$done_review = SOM_Workflow_Engine::mark_done( $order_a_id );
$assert( ! is_wp_error( $done_review ), 'mark_review_after_complete' );
$order_a = SOM_Orders::get( $order_a_id );
$assert( ! empty( $order_a->is_complete ), 'still_complete_after_review' );

// --- B: Product override wins ---
$ext_b   = 'up11s1b-' . gmdate( 'YmdHis' ) . '-' . $suffix;
$order_b = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $ext_b,
		'buyer_name'        => 'UP11-S1 B',
		'shipping_address'  => array(
			'line1'    => '2 Test St',
			'city'     => 'London',
			'postcode' => 'E1 1AA',
			'country'  => 'GB',
		),
		'items'             => array(
			array(
				'product_id' => $prod_override_id,
				'quantity'   => 1,
				'unit_price' => 15.0,
			),
		),
	)
);
$assert( ! is_wp_error( $order_b ), 'create_order_b' );
$order_b_id = (int) $order_b;
$resolved   = SOM_Post_Ship::resolve_template_id( SOM_Orders::get( $order_b_id ) );
$assert( $resolved === $ps_override_id, 'resolve_product_override_wins' );

$ship_b = $finish_to_ship( $order_b_id );
$assert( ! is_wp_error( $ship_b ), 'ship_mark_done_b' );
$order_b = SOM_Orders::get( $order_b_id );
$assert( (int) $order_b->post_ship_workflow_template_id === $ps_override_id, 'auto_start_bound_override' );
$assert( empty( $order_b->is_complete ), 'override_order_not_complete_at_ship' );

// --- C: Internal Produce N — no post-ship ---
$internal = $wpdb->get_row(
	"SELECT p.id FROM {$products_t} p
	INNER JOIN {$tpl_t} t ON t.id = p.workflow_template_id AND t.kind = 'make'
	WHERE p.is_active = 1 AND p.is_internal = 1
	ORDER BY p.id ASC LIMIT 1"
);
$assert( $internal && (int) $internal->id > 0, 'has_internal_product' );

$produced = SOM_Production::produce( (int) $internal->id, 1 );
$assert( ! is_wp_error( $produced ), 'produce_internal' );
$internal_order_id = (int) $produced;
$internal_order    = SOM_Orders::get( $internal_order_id );
$assert( empty( $internal_order->pack_workflow_template_id ), 'internal_no_pack' );
$assert( empty( $internal_order->post_ship_workflow_template_id ), 'internal_no_post_ship_bind' );
$start_internal = SOM_Post_Ship::start( $internal_order_id );
$assert( ! is_wp_error( $start_internal ), 'start_internal_soft_ok' );
$internal_order = SOM_Orders::get( $internal_order_id );
$assert( empty( $internal_order->post_ship_workflow_template_id ), 'internal_still_no_post_ship' );

// --- D: Manual start when auto-start off ---
SOM_Post_Ship::set_auto_start_enabled( false );
$ext_d   = 'up11s1d-' . gmdate( 'YmdHis' ) . '-' . $suffix;
$order_d = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $ext_d,
		'buyer_name'        => 'UP11-S1 D',
		'shipping_address'  => array(
			'line1'    => '4 Test St',
			'city'     => 'London',
			'postcode' => 'E1 1AA',
			'country'  => 'GB',
		),
		'items'             => array(
			array(
				'product_id' => $prod_default_id,
				'quantity'   => 1,
				'unit_price' => 12.0,
			),
		),
	)
);
$assert( ! is_wp_error( $order_d ), 'create_order_d' );
$order_d_id = (int) $order_d;
$ship_d     = $finish_to_ship( $order_d_id );
$assert( ! is_wp_error( $ship_d ), 'ship_mark_done_d' );
$order_d = SOM_Orders::get( $order_d_id );
$assert( empty( $order_d->post_ship_started_at ), 'no_auto_start_when_off' );
$assert( empty( $order_d->is_complete ), 'still_incomplete_manual_path' );
$manual = SOM_Post_Ship::start( $order_d_id );
$assert( ! is_wp_error( $manual ), 'manual_start' );
$order_d = SOM_Orders::get( $order_d_id );
$assert( ! empty( $order_d->post_ship_started_at ), 'manual_started_at' );
$assert( (int) $order_d->post_ship_workflow_template_id === $ps_default_id, 'manual_bound_default' );

// Restore settings.
SOM_Pack::set_default_template_id( $prev_pack );
SOM_Post_Ship::set_default_template_id( $prev_ps );
SOM_Post_Ship::set_auto_start_enabled( $prev_auto );

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "OK\n";
