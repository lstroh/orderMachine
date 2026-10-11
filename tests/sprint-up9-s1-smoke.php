<?php
/**
 * UP9-S1 smoke: structured eBay fields + copy-command render (no script dispatch).
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up9-s1-smoke.php
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
$assert( class_exists( 'SOM_Copy_Command' ), 'class_SOM_Copy_Command' );
$assert( '1.21.0' === (string) get_option( 'som_db_version', '' ), 'db_version_1.21.0' );

$steps_t = SOM_DB::table( 'workflow_steps' );
$items_t = SOM_DB::table( 'order_items' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$steps_t} LIKE 'command_template'" ), 'col_command_template' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$items_t} LIKE 'house_number'" ), 'col_house_number' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$items_t} LIKE 'street_name'" ), 'col_street_name' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$items_t} LIKE 'style'" ), 'col_style' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$items_t} LIKE 'personalisation_json'" ), 'col_personalisation_json' );

$editor_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$assert( false !== strpos( $editor_src, 'Copy command template' ), 'editor_copy_template_label' );
$assert( false !== strpos( $editor_src, 'command_template' ), 'editor_command_template_field' );
$assert( false !== strpos( $editor_src, 'Script / API (legacy' ), 'editor_script_deemphasised' );

$detail_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$assert( false !== strpos( $detail_src, 'som-copy-command-panel' ), 'detail_copy_panel' );
$assert( false !== strpos( $detail_src, 'data-som-copy-from' ), 'detail_copy_button' );
$assert( false !== strpos( $detail_src, 'som_save_line_fields' ), 'detail_line_fields_form' );

$js_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$assert( false !== strpos( $js_src, 'data-som-copy-from' ), 'js_copy_from' );
$assert( false !== strpos( $js_src, 'som-command-template' ), 'js_command_template_gate' );
$assert( false !== strpos( $js_src, 'syncCopyWarning' ), 'js_copy_warning' );

// --- Placeholder render unit checks ---
$template = "/make-sticker-order Order number: {{order_number}}\nHouse number: {{house_number}}\nStreet: {{street_name}}\neBay design: {{style}}\nBuyer name: {{buyer_name}}";
$rendered = SOM_Copy_Command::render(
	$template,
	array(
		'order_number'    => '12-34567-89012',
		'house_number'    => '36',
		'street_name'     => 'Grove Street',
		'style'           => 'Classic Scrolls',
		'buyer_name'      => 'Priya',
		'buyer_full_name' => 'Priya Buyer',
		'product_name'    => 'Small Stickers',
		'personalisation' => '',
	)
);
$assert( is_array( $rendered ) && false !== strpos( $rendered['command'], '12-34567-89012' ), 'render_order_number' );
$assert( false !== strpos( $rendered['command'], 'House number: 36' ), 'render_house' );
$assert( false !== strpos( $rendered['command'], 'Street: Grove Street' ), 'render_street' );
$assert( false !== strpos( $rendered['command'], 'eBay design: Classic Scrolls' ), 'render_style' );
$assert( false !== strpos( $rendered['command'], 'Buyer name: Priya' ), 'render_buyer' );
$assert( empty( $rendered['missing'] ), 'render_no_missing' );

$missing_render = SOM_Copy_Command::render(
	$template,
	array(
		'order_number'    => 'X-1',
		'house_number'    => '',
		'street_name'     => '',
		'style'           => 'Classic Scrolls',
		'buyer_name'      => 'Sam',
		'buyer_full_name' => 'Sam Unmatched',
		'product_name'    => '',
		'personalisation' => '',
	)
);
$assert( in_array( 'house_number', $missing_render['missing'], true ), 'missing_house' );
$assert( in_array( 'street_name', $missing_render['missing'], true ), 'missing_street' );
$notice = SOM_Copy_Command::missing_notice( $missing_render['missing'] );
$assert( 0 === strpos( $notice, 'Missing:' ), 'missing_notice_prefix' );
$assert( preg_match( '/House number:\s*\n/', $missing_render['command'] ), 'missing_empty_value' );

$unknown = SOM_Copy_Command::render(
	'Hello {{unknown_token}} / {{order_number}}',
	array(
		'order_number'    => '1',
		'house_number'    => '',
		'street_name'     => '',
		'style'           => '',
		'buyer_name'      => '',
		'buyer_full_name' => '',
		'product_name'    => '',
		'personalisation' => '',
	)
);
$assert( false !== strpos( $unknown['command'], '{{unknown_token}}' ), 'unknown_placeholder_literal' );
$assert( 'Priya' === SOM_Copy_Command::buyer_first_name( 'Priya Buyer' ), 'buyer_first_name' );
$assert( 'Priya' === SOM_Copy_Command::buyer_first_name( 'Priya' ), 'buyer_first_name_single' );

// --- eBay fixture structured extract (O1 aliases) ---
$fixture_path = SOM_PLUGIN_DIR . 'tests/fixtures/ebay-orders.json';
$fixture      = json_decode( (string) file_get_contents( $fixture_path ), true );
$assert( is_array( $fixture ) && ! empty( $fixture['orders'][0] ), 'fixture_loaded' );
$normalized = SOM_Channel_Ebay::normalize_order( $fixture['orders'][0] );
$assert( 'Priya Buyer' === ( $normalized['buyer_name'] ?? '' ), 'fixture_buyer_full' );
$line0 = $normalized['items'][0] ?? array();
$assert( '36' === ( $line0['house_number'] ?? null ), 'ebay_house' );
$assert( 'Grove Street' === ( $line0['street_name'] ?? null ), 'ebay_street' );
$assert( 'Classic Scrolls' === ( $line0['style'] ?? null ), 'ebay_style' );
$assert( ! empty( $line0['personalisation_json'] ), 'ebay_personalisation_json' );

// Style fallback to title when no design aspect.
$style_fallback = SOM_Channel_Ebay::extract_structured_fields(
	array(
		'title'            => 'Fallback Title Design',
		'variationAspects' => array(
			array(
				'name'  => 'House Number',
				'value' => '1',
			),
		),
	)
);
$assert( 'Fallback Title Design' === ( $style_fallback['style'] ?? null ), 'style_title_fallback' );

// Case-insensitive aliases.
$alias = SOM_Channel_Ebay::extract_structured_fields(
	array(
		'variationAspects' => array(
			array(
				'name'  => 'HOUSE NO',
				'value' => '9A',
			),
			array(
				'name'  => 'Road Name',
				'value' => 'Mill Lane',
			),
			array(
				'name'  => 'eBay Design',
				'value' => 'Flourish',
			),
		),
	)
);
$assert( '9A' === ( $alias['house_number'] ?? null ), 'alias_house_no' );
$assert( 'Mill Lane' === ( $alias['street_name'] ?? null ), 'alias_road_name' );
$assert( 'Flourish' === ( $alias['style'] ?? null ), 'alias_ebay_design' );

$suffix = wp_generate_password( 4, false, false );

// --- Runtime: copy-command step enters in_progress (not waiting_script); no dispatch ---
$wf = SOM_Workflows::create(
	array(
		'name'      => 'UP9-S1 copy ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf ), 'create_copy_template' );
$wf_id = (int) $wf;

$example_template = "/make-sticker-order Order number: {{order_number}}\nHouse number: {{house_number}}\nStreet: {{street_name}}\neBay design: {{style}}\nBuyer name: {{buyer_name}}";
$saved            = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'name'             => 'Make stickers',
			'command_template' => $example_template,
		),
	)
);
$assert( true === $saved, 'save_copy_steps' );

$steps = SOM_Workflows::get_steps( $wf_id );
$assert( 1 === count( $steps ), 'one_step' );
$assert( ! empty( $steps[0]->command_template ), 'step_has_command_template' );
$assert( 1 === (int) $steps[0]->requires_manual_confirm, 'copy_forces_manual' );
$assert( empty( $steps[0]->script_config ), 'copy_no_script_config' );

// Exclusive: reject copy + timer.
$reject = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'id'               => (int) $steps[0]->id,
			'name'             => 'Make stickers',
			'command_template' => $example_template,
			'timer_value'      => '5',
			'timer_unit'       => 'minutes',
		),
	)
);
$assert( is_wp_error( $reject ) && 'som_copy_command_exclusive' === $reject->get_error_code(), 'reject_copy_plus_timer' );

// Restore clean copy step after rejected save attempt (previous save may have been rejected before write).
$saved2 = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'id'               => (int) $steps[0]->id,
			'name'             => 'Make stickers',
			'command_template' => $example_template,
		),
	)
);
$assert( true === $saved2, 'restore_copy_steps' );

$product = SOM_Products::create(
	array(
		'name'                 => 'UP9-S1 product ' . $suffix,
		'sku'                  => 'UP9S1-' . $suffix,
		'workflow_template_id' => $wf_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product ), 'create_product' );
$product_id = (int) $product;

$order_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up9-s1-' . $suffix,
		'buyer_name'        => 'Priya Buyer',
		'items'             => array(
			array(
				'product_id'           => $product_id,
				'quantity'             => 1,
				'house_number'         => '36',
				'street_name'          => 'Grove Street',
				'style'                => 'Classic Scrolls',
				'personalisation_text' => '36 / Grove Street',
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;

$item = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'order_items' ) . ' WHERE order_id = %d ORDER BY id ASC LIMIT 1',
		$order_id
	)
);
$assert( $item && '36' === (string) $item->house_number, 'persisted_house' );
$assert( $item && 'Grove Street' === (string) $item->street_name, 'persisted_street' );
$assert( $item && 'Classic Scrolls' === (string) $item->style, 'persisted_style' );

$order = SOM_Orders::get( $order_id );
$assert( $order && ! empty( $order->make_lines ), 'order_make_lines' );
$make_item = $order->make_lines[0];
$current   = isset( $make_item->make_current ) ? $make_item->make_current : null;
$assert( $current && 'in_progress' === (string) $current->status, 'copy_step_in_progress' );
$assert( 'waiting_script' !== (string) $current->status, 'not_waiting_script' );

$values = SOM_Copy_Command::values_from_order_item( $order, $make_item );
$assert( 'Priya' === $values['buyer_name'], 'values_buyer_first' );
$assert( 'Priya Buyer' === $values['buyer_full_name'], 'values_buyer_full' );
$live = SOM_Copy_Command::render( $example_template, $values );
$assert( false !== strpos( $live['command'], '/make-sticker-order' ), 'live_render_skill' );
$assert( false !== strpos( $live['command'], 'Grove Street' ), 'live_render_street' );
$assert( empty( $live['missing'] ), 'live_render_complete' );

// Editable structured fields (O4).
$upd = SOM_Orders::update_item_structured_fields(
	$order_id,
	(int) $item->id,
	array(
		'house_number' => '99',
		'street_name'  => 'Oak Avenue',
		'style'        => 'Classic Scrolls',
	)
);
$assert( true === $upd, 'update_line_fields' );
$item2 = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'order_items' ) . ' WHERE id = %d',
		(int) $item->id
	)
);
$assert( $item2 && '99' === (string) $item2->house_number, 'updated_house' );
$order2  = SOM_Orders::get( $order_id );
$values2 = SOM_Copy_Command::values_from_order_item( $order2, $order2->make_lines[0] );
$live2   = SOM_Copy_Command::render( $example_template, $values2 );
$assert( false !== strpos( $live2['command'], 'House number: 99' ), 'rerender_after_edit' );
$assert( false !== strpos( $live2['command'], 'Oak Avenue' ), 'rerender_street_after_edit' );

// No script dispatch from copy-command: status stayed in_progress; Script_Dispatch not required.
$assert( ! SOM_Script_Dispatch::has_script( $steps[0] ), 'has_script_false_on_copy_step' );
$dispatched = $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'order_item_step_progress' ) . " WHERE order_item_id = %d AND status = 'waiting_script'",
		(int) $item->id
	)
);
$assert( 0 === (int) $dispatched, 'no_waiting_script_rows' );

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "UP9-S1 smoke OK\n";
