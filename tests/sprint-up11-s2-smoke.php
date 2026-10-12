<?php
/**
 * UP11-S2 smoke: Post-ship board, seed aftercare, review off Pack, docs paths.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up11-s2-smoke.php
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

$assert( class_exists( 'SOM_Post_Ship' ), 'class_SOM_Post_Ship' );
$assert( method_exists( 'SOM_Post_Ship', 'query_board' ), 'query_board_method' );
$assert( method_exists( 'SOM_Post_Ship', 'board_url' ), 'board_url_method' );

$board_view = SOM_PLUGIN_DIR . 'admin/views/orders-post-ship-board.php';
$assert( is_readable( $board_view ), 'board_view_exists' );
$board_src = file_get_contents( $board_view );
$assert( is_string( $board_src ) && false !== strpos( $board_src, 'som-post-ship-board' ), 'board_view_page_slug' );
$assert( is_string( $board_src ) && false !== strpos( $board_src, 'som_workflow' ), 'board_template_filter_o5' );
$assert( is_string( $board_src ) && false !== strpos( $board_src, 'som_scope' ), 'board_scope_filter' );

$menu_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$assert( is_string( $menu_src ) && false !== strpos( $menu_src, 'som-post-ship-board' ), 'menu_registers_post_ship_board' );
$assert( is_string( $menu_src ) && false !== strpos( $menu_src, 'render_post_ship_board' ), 'menu_render_callback' );

$detail_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$assert( is_string( $detail_src ) && false !== strpos( $detail_src, 'Open Post-ship Board' ), 'detail_board_link' );
$assert( is_string( $detail_src ) && false !== strpos( $detail_src, 'som-copy-cmd-ps-' ), 'detail_copy_command' );

// --- Seed shape ---
SOM_Seed::maybe_seed_post_ship_workflow();
$ids = SOM_Seed::resolve_seed_ids();
$ps_id = (int) ( $ids['post_ship_workflow_id'] ?? 0 );
$assert( $ps_id > 0, 'seed_post_ship_template_id' );

$ps_tpl = SOM_Workflows::get( $ps_id );
$assert( $ps_tpl && 'post_ship' === SOM_Workflows::sanitize_kind( $ps_tpl->kind ), 'seed_post_ship_kind' );
$ps_name_ok = $ps_tpl && (
	false !== stripos( (string) $ps_tpl->name, 'Aftercare' )
	|| false !== stripos( (string) $ps_tpl->name, 'Post-ship' )
);
$assert( $ps_name_ok, 'seed_post_ship_name' );

$ps_steps = SOM_Workflows::get_steps( $ps_id );
$assert( count( $ps_steps ) >= 4, 'seed_post_ship_step_count_gte_4' );

$ps_names = array_map(
	static function ( $s ) {
		return (string) $s->name;
	},
	$ps_steps
);
$assert( in_array( 'Confirm package arrived', $ps_names, true ), 'seed_has_arrival_step' );
$assert( in_array( 'Review reminder', $ps_names, true ), 'seed_has_review_on_post_ship' );

$arrival = null;
foreach ( $ps_steps as $s ) {
	if ( 'Confirm package arrived' === (string) $s->name ) {
		$arrival = $s;
		break;
	}
}
$assert(
	$arrival && SOM_Step_Confirmations::KIND_PACKAGE_ARRIVED === SOM_Step_Confirmations::sanitize_kind( $arrival->confirmation_kind ),
	'seed_arrival_confirmation_kind'
);

$review_ps = null;
foreach ( $ps_steps as $s ) {
	if ( 'Review reminder' === (string) $s->name ) {
		$review_ps = $s;
		break;
	}
}
$assert( $review_ps && (int) $review_ps->timer_seconds > 0, 'seed_review_has_timer' );
$assert( $review_ps && ! empty( $review_ps->requires_manual_confirm ), 'seed_review_manual' );

$buyer_step = null;
foreach ( $ps_steps as $s ) {
	if ( false !== stripos( (string) $s->name, 'buyer' ) || false !== stripos( (string) $s->name, 'tracking' ) ) {
		$buyer_step = $s;
		break;
	}
}
$assert( $buyer_step && SOM_Copy_Command::has_template( $buyer_step ), 'seed_buyer_has_copy_command' );

$assert( SOM_Post_Ship::default_template_id() === $ps_id || SOM_Post_Ship::default_template_id() > 0, 'site_default_post_ship_set' );

// Pack seed must not include Review reminder (fresh or stripped).
$pack_id = (int) ( $ids['pack_workflow_id'] ?? 0 );
$assert( $pack_id > 0, 'seed_pack_exists' );
SOM_Seed::maybe_strip_pack_review_reminder( $pack_id );

// Smoke-only: clear leftover open progress on seed Pack Review reminder so strip can run
// (production migrate keeps the step while in-flight rows exist).
$pack_review = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT id FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE workflow_template_id = %d AND name = %s ORDER BY id ASC LIMIT 1',
		$pack_id,
		'Review reminder'
	)
);
if ( $pack_review ) {
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM ' . SOM_DB::table( 'order_step_progress' ) . ' WHERE workflow_step_id = %d AND status <> %s',
			(int) $pack_review->id,
			'done'
		)
	);
	SOM_Seed::maybe_strip_pack_review_reminder( $pack_id );
}

$pack_steps = SOM_Workflows::get_steps( $pack_id );
$pack_names = array_map(
	static function ( $s ) {
		return (string) $s->name;
	},
	$pack_steps
);
$assert( ! in_array( 'Review reminder', $pack_names, true ), 'pack_seed_no_review_reminder' );
$assert( in_array( 'Ship', $pack_names, true ), 'pack_seed_still_has_ship' );

// --- Board query basics ---
$suffix = wp_generate_password( 4, false, false );
$ebay   = SOM_Channels::get_by_slug( 'ebay' );
$assert( (bool) $ebay, 'ebay_channel' );

$product_id = (int) ( $ids['product_id'] ?? 0 );
if ( $product_id < 1 ) {
	$product_id = (int) $wpdb->get_var(
		'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE is_internal = 0 ORDER BY id ASC LIMIT 1'
	);
}
$assert( $product_id > 0, 'product_for_board' );

$make_wf = SOM_Workflows::create(
	array(
		'name'      => 'UP11-S2 Make ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $make_wf ), 'create_make' );
SOM_Workflows::save_steps(
	(int) $make_wf,
	array(
		array(
			'name'                    => 'Cut',
			'requires_manual_confirm' => 1,
		),
	)
);

$pack_wf = SOM_Workflows::create(
	array(
		'name'      => 'UP11-S2 Pack ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $pack_wf ), 'create_pack' );
SOM_Workflows::save_steps(
	(int) $pack_wf,
	array(
		array(
			'name'                    => 'Ship',
			'requires_manual_confirm' => 1,
		),
	)
);

$ps_wf = SOM_Workflows::create(
	array(
		'name'      => 'UP11-S2 Aftercare ' . $suffix,
		'kind'      => 'post_ship',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $ps_wf ), 'create_post_ship' );
$ps_wf_id = (int) $ps_wf;
SOM_Workflows::save_steps(
	$ps_wf_id,
	array(
		array(
			'name'                    => 'Update buyer',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Confirm package arrived',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'package_arrived',
		),
		array(
			'name'                    => 'Review reminder',
			'requires_manual_confirm' => 1,
			'timer_value'             => 1,
			'timer_unit'              => 'minutes',
		),
	)
);

$prev_ps_default = SOM_Post_Ship::default_template_id();
SOM_Post_Ship::set_default_template_id( $ps_wf_id );
SOM_Post_Ship::set_auto_start_enabled( false );

$ext = 'UP11S2-' . $suffix;
$now = current_time( 'mysql', true );
$wpdb->insert(
	SOM_DB::table( 'orders' ),
	array(
		'channel_id'                => (int) $ebay->id,
		'external_order_id'         => $ext,
		'order_date'                => $now,
		'buyer_name'                => 'UP11 S2 Buyer',
		'is_complete'               => 0,
		'pack_workflow_template_id' => (int) $pack_wf,
		// Non-NULL so cancelled_sql() (raw_payload LIKE …) does not drop the row.
		'raw_payload'               => '{}',
		'created_at'                => $now,
		'updated_at'                => $now,
	),
	array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
);
$order_id = (int) $wpdb->insert_id;
$assert( $order_id > 0, 'insert_order' );

$wpdb->insert(
	SOM_DB::table( 'order_items' ),
	array(
		'order_id'             => $order_id,
		'product_id'           => $product_id,
		'quantity'             => 1,
		'unit_price'           => 10,
		'personalisation_text' => 'S2',
	),
	array( '%d', '%d', '%d', '%f', '%s' )
);

// Pack done + shipment → unstarted board.
$pack_steps_created = SOM_Workflows::get_steps( (int) $pack_wf );
foreach ( $pack_steps_created as $ps ) {
	$wpdb->insert(
		SOM_DB::table( 'order_step_progress' ),
		array(
			'order_id'         => $order_id,
			'workflow_step_id' => (int) $ps->id,
			'status'           => 'done',
			'started_at'       => $now,
			'completed_at'     => $now,
		),
		array( '%d', '%d', '%s', '%s', '%s' )
	);
}

$ship_ok = SOM_Shipments::upsert(
	$order_id,
	array(
		'carrier'      => 'Royal Mail',
		'service'      => 'Tracked 48',
		'shipped_at'   => gmdate( 'Y-m-d H:i:s' ),
		'postage_paid' => '1.50',
	)
);
$assert( ! is_wp_error( $ship_ok ), 'shipment_upsert' );

$unstarted = SOM_Post_Ship::query_board(
	array(
		'scope' => SOM_Post_Ship::SCOPE_UNSTARTED,
		's'     => $ext,
	)
);
$assert( ! empty( $unstarted['orders'] ), 'board_unstarted_has_order' );
$found_un = false;
foreach ( $unstarted['orders'] as $card ) {
	if ( (int) $card->id === $order_id ) {
		$found_un = true;
		$assert( SOM_Post_Ship::BOARD_UNSTARTED_KEY === (string) $card->column_key, 'unstarted_column_key' );
		break;
	}
}
$assert( $found_un, 'board_unstarted_includes_order' );

$started = SOM_Post_Ship::start( $order_id, $ps_wf_id );
$assert( true === $started, 'start_post_ship' );

$active = SOM_Post_Ship::query_board(
	array(
		'scope'                => SOM_Post_Ship::SCOPE_ACTIVE,
		'workflow_template_id' => $ps_wf_id,
		's'                    => $ext,
	)
);
$assert( ! empty( $active['orders'] ), 'board_active_has_order' );
$assert( ! empty( $active['workflow_steps'] ), 'board_template_filter_returns_steps' );
$found_active = false;
foreach ( $active['orders'] as $card ) {
	if ( (int) $card->id === $order_id ) {
		$found_active = true;
		$assert( 'Update buyer' === (string) $card->column_key || 'Update buyer' === (string) $card->current_step_name, 'active_column_by_step' );
		break;
	}
}
$assert( $found_active, 'board_active_includes_order' );

// Docs paths.
$docs = array(
	SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-GUIDE.md',
	SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-REFERENCE.md',
	SOM_PLUGIN_DIR . 'stikerts/wordpress/FEATURES-AND-TESTING.md',
	SOM_PLUGIN_DIR . 'stikerts/wordpress v12/Update-11-Sprint-Progress.md',
);
foreach ( $docs as $doc ) {
	$assert( is_readable( $doc ), 'doc_exists_' . basename( $doc ) );
}

$guide = file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-GUIDE.md' );
$assert( is_string( $guide ) && false !== stripos( $guide, 'Post-ship' ), 'user_guide_mentions_post_ship' );
$ref = file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-REFERENCE.md' );
$assert( is_string( $ref ) && false !== stripos( $ref, 'Post-ship Board' ), 'user_reference_post_ship_board' );
$feat = file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/FEATURES-AND-TESTING.md' );
$assert( is_string( $feat ) && false !== stripos( $feat, 'som-post-ship-board' ), 'features_board_slug' );

// Cleanup defaults.
SOM_Post_Ship::set_default_template_id( $prev_ps_default );

$out( 'fail_count', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "UP11-S2 smoke OK\n";
