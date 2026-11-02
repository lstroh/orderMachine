<?php
/**
 * UP15-S2 smoke: sticky order header, per-field copy, and line-field correction.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up15-s2-smoke.php
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

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();

$out( 'plugin', SOM_VERSION );
$assert( '1.26.0' === SOM_DB::DB_VERSION, 'schema_stays_1_26_0' );

require_once ABSPATH . 'wp-admin/includes/template.php';

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin || ! user_can( $admin, 'manage_options' ) ) {
	$created = wp_insert_user(
		array(
			'user_login' => 'up15s2admin',
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'up15s2admin@example.com',
			'role'       => 'administrator',
		)
	);
	$assert( ! is_wp_error( $created ) && (int) $created > 0, 'create_admin' );
	$admin = is_wp_error( $created ) ? null : get_user_by( 'id', (int) $created );
}
$assert( $admin && user_can( $admin, 'manage_options' ), 'admin_can_manage' );
if ( $admin ) {
	wp_set_current_user( (int) $admin->ID );
}

$detail_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$menu_src   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$css_src    = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/css/admin.css' );
$js_src     = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );

$assert( false === strpos( $detail_src, 'Line fields (house / street / style)' ), 'source_make_form_removed' );
$assert( false !== strpos( $detail_src, 'som-personalisation-snippet' ), 'source_snippet_stays' );
$assert( false !== strpos( $detail_src, 'som_save_line_fields' ), 'source_save_path_stays' );
$assert( false !== strpos( $detail_src, 'data-som-sticky-header' ), 'source_sticky_header' );

$save_at  = strpos( $menu_src, "isset( \$_POST['som_save_line_fields'] )" );
$note_at  = strpos( $menu_src, "isset( \$_POST['som_add_order_note'] )" );
$save_src = ( false !== $save_at && false !== $note_at && $note_at > $save_at )
	? substr( $menu_src, $save_at, $note_at - $save_at )
	: '';
$assert( '' !== $save_src && false === strpos( $save_src, 'personalisation_text' ), 'handler_skips_personalisation_text' );
$assert( '' !== $save_src && false === strpos( $save_src, 'personalisation_json' ), 'handler_skips_personalisation_json' );
$assert( false !== strpos( $save_src, 'update_item_structured_fields' ), 'handler_uses_structured_update' );

$assert( false !== strpos( $css_src, '.som-order-sticky' ) && false !== strpos( $css_src, 'position: sticky' ), 'css_sticky' );
$assert( false !== strpos( $css_src, 'top: 32px' ), 'css_sticky_top' );
$assert( false !== strpos( $css_src, 'top: 46px' ), 'css_sticky_top_narrow' );
$assert( false !== strpos( $css_src, '.som-order-sticky-lines' ) && false !== strpos( $css_src, 'max-height: 18rem' ), 'css_lines_scroll' );

$assert( false !== strpos( $js_src, 'data-som-copy-inputs' ), 'js_copy_inputs' );
$assert( false !== strpos( $js_src, "el.tagName === 'INPUT'" ) && false !== strpos( $js_src, 'el.value' ), 'js_input_copies_value' );
$assert( false !== strpos( $js_src, "btn.textContent = 'Copied'" ), 'js_copied_label' );
$assert( false !== strpos( $js_src, "window.prompt( 'Copy:', text )" ), 'js_prompt_fallback' );

$render_detail = static function ( $order_id ) {
	$_GET['order_id'] = (int) $order_id;
	ob_start();
	SOM_Admin_Menu::render_orders();
	return (string) ob_get_clean();
};

$sticky_html = static function ( $html ) {
	$start = strpos( $html, 'data-som-sticky-header' );
	$end   = strpos( $html, 'wp-header-end' );
	if ( false === $start || false === $end || $end <= $start ) {
		return '';
	}
	return substr( $html, $start, $end - $start );
};

$make_html = static function ( $html ) {
	$start = strpos( $html, 'class="som-panel som-panel-make"' );
	if ( false === $start ) {
		return '';
	}
	$end = strlen( $html );
	foreach ( array( 'class="som-panel som-panel-pack"', 'class="som-panel som-panel-workflow"' ) as $marker ) {
		$at = strpos( $html, $marker, $start + 10 );
		if ( false !== $at && $at < $end ) {
			$end = $at;
		}
	}
	return substr( $html, $start, $end - $start );
};

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$workflow = SOM_Workflows::create(
	array(
		'name'       => 'UP15-S2 make ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 1,
	)
);
$assert( ! is_wp_error( $workflow ) && (int) $workflow > 0, 'create_workflow' );
$workflow_id = is_wp_error( $workflow ) ? 0 : (int) $workflow;
if ( $workflow_id > 0 ) {
	$saved_steps = SOM_Workflows::save_steps(
		$workflow_id,
		array(
			array(
				'name'                    => 'Cut',
				'requires_manual_confirm' => 1,
			),
		)
	);
	$assert( true === $saved_steps, 'save_steps' );
}

$product_id = 0;
if ( $workflow_id > 0 ) {
	$product = SOM_Products::create(
		array(
			'name'                 => 'UP15-S2 sticker ' . $suffix,
			'sku'                  => 'UP15S2-' . $suffix,
			'workflow_template_id' => $workflow_id,
			'is_active'            => 1,
		)
	);
	$assert( ! is_wp_error( $product ) && (int) $product > 0, 'create_product' );
	$product_id = is_wp_error( $product ) ? 0 : (int) $product;
}

$external_id = 'up15s2-' . $suffix;
$snippet     = 'UP15S2 snippet text';
$one_id      = 0;
if ( $product_id > 0 ) {
	$created_one = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => $external_id,
			'buyer_name'        => 'Priya Buyer',
			'shipping_address'  => array(
				'full_name' => 'Priya Buyer',
				'line1'     => '12 High Street',
				'line2'     => 'Flat 2',
				'city'      => 'London',
				'state'     => 'Greater London',
				'postcode'  => 'SW1A 1AA',
				'country'   => 'GB',
			),
			'items'             => array(
				array(
					'product_id'           => $product_id,
					'quantity'             => 2,
					'unit_price'           => 4,
					'house_number'         => '36',
					'street_name'          => 'Grove Street',
					'style'                => 'Classic Scrolls',
					'personalisation_text' => $snippet,
					'personalisation_json' => array(
						'house number' => '36',
						'street name'  => 'Grove Street',
						'design'       => 'Classic Scrolls',
						'child name'   => 'Sam',
					),
				),
			),
		)
	);
	$assert( ! is_wp_error( $created_one ) && (int) $created_one > 0, 'create_one_line_order' );
	$one_id = is_wp_error( $created_one ) ? 0 : (int) $created_one;
}

$two_id = 0;
if ( $product_id > 0 ) {
	$created_two = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => 'up15s2-two-' . $suffix,
			'buyer_name'        => 'Two Lines',
			'items'             => array(
				array(
					'product_id'   => $product_id,
					'quantity'     => 1,
					'house_number' => '10',
					'street_name'  => 'Alpha Road',
					'style'        => 'Oak',
				),
				array(
					'product_id'   => $product_id,
					'quantity'     => 3,
					'house_number' => '11',
					'street_name'  => 'Beta Road',
					'style'        => 'Pine',
				),
			),
		)
	);
	$assert( ! is_wp_error( $created_two ) && (int) $created_two > 0, 'create_two_line_order' );
	$two_id = is_wp_error( $created_two ) ? 0 : (int) $created_two;
}

$free = array_map( 'intval', SOM_Trays::free_tray_numbers() );
$pick = array();
foreach ( array( 2, 5 ) as $wanted ) {
	if ( in_array( $wanted, $free, true ) ) {
		$pick[] = $wanted;
	}
}
if ( 2 !== count( $pick ) ) {
	$pick = array();
	foreach ( $free as $number ) {
		$pick[] = (int) $number;
		if ( 2 === count( $pick ) ) {
			break;
		}
	}
}
$assert( 2 === count( $pick ), 'two_trays_available' );
$out( 'tray_numbers', $pick );

$assigned = ( $one_id > 0 && 2 === count( $pick ) ) ? SOM_Trays::assign( $one_id, $pick ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $assigned, 'assign_two_trays' );
$held = $one_id > 0 ? SOM_Trays::held_numbers_for_order( $one_id ) : array();
$expect_held = $pick;
sort( $expect_held, SORT_NUMERIC );
$assert( $expect_held === $held, 'held_numbers_match' );

$one      = $one_id > 0 ? SOM_Orders::get( $one_id ) : null;
$one_item = ( $one && ! empty( $one->items[0]->id ) ) ? $one->items[0] : null;
$item_id  = $one_item ? (int) $one_item->id : 0;
$assert( $item_id > 0, 'one_line_item' );

$one_html = $one_id > 0 ? $render_detail( $one_id ) : '';
$one_bar  = $sticky_html( $one_html );
$one_make = $make_html( $one_html );
$back_at  = strpos( $one_html, 'Back to orders' );
$bar_at   = strpos( $one_html, 'data-som-sticky-header' );
$panel_at = strpos( $one_html, 'som-panel-personalisation' );
$assert( '' !== $one_bar, 'sticky_bar_rendered' );
$assert( false !== $back_at && false !== $bar_at && false !== $panel_at && $back_at < $bar_at && $bar_at < $panel_at, 'sticky_after_back_link' );
$assert( 1 === substr_count( $one_bar, 'class="som-sticky-line"' ), 'one_line_block' );

$assert( false !== strpos( $one_bar, 'data-som-sticky-copy="external_order_id" data-som-copy-text="' . $external_id . '"' ), 'copy_external_order_id' );
$assert( false !== strpos( $one_bar, 'data-som-sticky-copy="postcode" data-som-copy-text="SW1A 1AA"' ), 'copy_postcode' );
$assert( false !== strpos( $one_bar, 'data-som-sticky-copy="line1" data-som-copy-text="12 High Street"' ), 'line1_not_split' );
$assert( $item_id > 0 && false !== strpos( $one_bar, 'data-som-sticky-copy="house_number" data-som-copy-from="som-sticky-house-' . $item_id . '"' ), 'copy_house_number' );
$assert( $item_id > 0 && false !== strpos( $one_bar, 'data-som-sticky-copy="street_name" data-som-copy-from="som-sticky-street-' . $item_id . '"' ), 'copy_street_name' );
$assert( $item_id > 0 && false !== strpos( $one_bar, 'data-som-sticky-copy="design" data-som-copy-from="som-sticky-style-' . $item_id . '"' ), 'copy_design' );
$assert( false !== strpos( $one_bar, 'value="36"' ) && false !== strpos( $one_bar, 'value="Grove Street"' ) && false !== strpos( $one_bar, 'value="Classic Scrolls"' ), 'line_inputs_show_stored_values' );

foreach ( $held as $tray_number ) {
	$assert(
		false !== strpos( $one_bar, 'data-som-sticky-copy="tray" data-som-copy-text="' . (int) $tray_number . '"' ),
		'copy_tray_' . (int) $tray_number
	);
}
$assert( count( $held ) === substr_count( $one_bar, 'data-som-sticky-copy="tray"' ), 'tray_copy_count' );
$assert( false === strpos( $one_bar, 'No tray' ), 'held_order_hides_no_tray' );
$assert( false === strpos( $one_bar, 'som_assign_trays' ) && false === strpos( $one_bar, 'som_release_trays' ), 'bar_has_no_assign_or_release' );
$assert( 1 === substr_count( $one_bar, 'data-som-sticky-copy="aspect"' ), 'one_extra_aspect_chip' );
$assert( false !== strpos( $one_bar, 'data-som-sticky-copy="aspect" data-som-copy-text="Sam"' ), 'aspect_copies_value' );
$assert( false !== strpos( $one_bar, 'child name' ), 'aspect_uses_stored_name' );

$assert( false === strpos( $one_make, 'som_line[house_number]' ), 'make_does_not_post_house_number' );
$assert( false !== strpos( $one_make, 'som-personalisation-snippet' ) && false !== strpos( $one_make, $snippet ), 'make_keeps_personalisation_snippet' );
$assert( false === strpos( $one_html, 'name="som_line[personalisation_text]"' ), 'form_does_not_post_personalisation_text' );
$assert( false !== strpos( $one_bar, 'name="som_line[house_number]"' ), 'header_posts_house_number' );

$two      = $two_id > 0 ? SOM_Orders::get( $two_id ) : null;
$two_html = $two_id > 0 ? $render_detail( $two_id ) : '';
$two_bar  = $sticky_html( $two_html );
$assert( $two && SOM_Trays::order_requires_trays( $two ), 'two_line_requires_trays' );
$assert( $two_id > 0 && array() === SOM_Trays::held_numbers_for_order( $two_id ), 'two_line_holds_no_tray' );
$assert( 2 === substr_count( $two_bar, 'class="som-sticky-line"' ), 'two_line_blocks' );
$assert( false !== strpos( $two_bar, 'value="10"' ) && false !== strpos( $two_bar, 'value="11"' ), 'two_line_values' );
$assert( false !== strpos( $two_bar, 'No tray' ), 'no_tray_text' );
$assert( false === strpos( $two_bar, 'data-som-sticky-copy="tray"' ), 'no_tray_copy_button' );
$assert( false !== strpos( $two_bar, 'class="som-sticky-no-tray" href="#som-trays"' ), 'no_tray_links_panel' );
$assert( false !== strpos( $two_bar, 'No shipping address stored.' ), 'empty_address_muted_line' );

$before_text = $one_item ? (string) $one_item->personalisation_text : '';
$before_json = $one_item ? (string) $one_item->personalisation_json : '';
$saved       = ( $one_id > 0 && $item_id > 0 )
	? SOM_Orders::update_item_structured_fields(
		$one_id,
		$item_id,
		array(
			'house_number' => '99',
			'street_name'  => 'Acacia Avenue',
			'style'        => 'Botanical',
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $saved, 'save_line_fields' );
$saved_order = $one_id > 0 ? SOM_Orders::get( $one_id ) : null;
$saved_line  = ( $saved_order && ! empty( $saved_order->items[0] ) ) ? $saved_order->items[0] : null;
$assert( $saved_line && '99' === (string) $saved_line->house_number, 'stored_house_number' );
$assert( $saved_line && 'Acacia Avenue' === (string) $saved_line->street_name, 'stored_street_name' );
$assert( $saved_line && 'Botanical' === (string) $saved_line->style, 'stored_design' );
$assert( $saved_line && $before_text === (string) $saved_line->personalisation_text, 'personalisation_text_unchanged' );
$assert( $saved_line && $before_json === (string) $saved_line->personalisation_json, 'personalisation_json_unchanged' );

$cleared = ( $one_id > 0 && $item_id > 0 )
	? SOM_Orders::update_item_structured_fields(
		$one_id,
		$item_id,
		array(
			'house_number' => '',
			'street_name'  => 'Acacia Avenue',
			'style'        => '',
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $cleared, 'blank_clears_columns' );
$cleared_order = $one_id > 0 ? SOM_Orders::get( $one_id ) : null;
$cleared_line  = ( $cleared_order && ! empty( $cleared_order->items[0] ) ) ? $cleared_order->items[0] : null;
$assert( $cleared_line && ( null === $cleared_line->house_number || '' === (string) $cleared_line->house_number ), 'blank_house_cleared' );
$assert( $cleared_line && 'Acacia Avenue' === (string) $cleared_line->street_name, 'street_kept' );
$assert( $cleared_line && ( null === $cleared_line->style || '' === (string) $cleared_line->style ), 'blank_design_cleared' );
$assert( $cleared_line && $before_text === (string) $cleared_line->personalisation_text, 'clear_leaves_personalisation_text' );

if ( $fail > 0 ) {
	echo "FAIL — UP15-S2 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP15-S2 smoke\n";
