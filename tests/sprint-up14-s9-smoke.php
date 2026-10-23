<?php
/**
 * UP14-S9 smoke: a sellable product can override the site Pack workflow.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s9-smoke.php
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

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$products = SOM_DB::table( 'products' );
$col      = $wpdb->get_row( "SHOW COLUMNS FROM {$products} LIKE 'pack_workflow_template_id'" );
$assert( $col && false !== strpos( (string) $col->Type, 'bigint' ) && false !== strpos( strtolower( (string) $col->Type ), 'unsigned' ), 'column_bigint_unsigned' );
$assert( $col && 'YES' === (string) $col->Null && ( null === $col->Default || '' === (string) $col->Default ), 'column_nullable' );
$indexes = $wpdb->get_results( "SHOW INDEX FROM {$products}" );
$indexed = false;
if ( is_array( $indexes ) ) {
	foreach ( $indexes as $idx ) {
		if ( isset( $idx->Key_name, $idx->Column_name )
			&& 'pack_workflow_template_id' === $idx->Key_name
			&& 'pack_workflow_template_id' === $idx->Column_name ) {
			$indexed = true;
			break;
		}
	}
}
$assert( $indexed, 'column_indexed' );
$assert( '1.26.0' === SOM_DB::DB_VERSION, 'schema_constant_1_26_0' );
$assert( '1.26.0' === (string) get_option( 'som_db_version' ), 'schema_option_1_26_0' );

$step = static function ( $name ) {
	return array(
		'name'                    => $name,
		'requires_manual_confirm' => 1,
	);
};

$make = SOM_Workflows::create(
	array(
		'name'      => 'UP14-S9 Make ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$default_pack = SOM_Workflows::create(
	array(
		'name'      => 'UP14-S9 Default ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$override_pack = SOM_Workflows::create(
	array(
		'name'      => 'UP14-S9 Override ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$inactive_pack = SOM_Workflows::create(
	array(
		'name'      => 'UP14-S9 Inactive ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 0,
	)
);
$assert( ! is_wp_error( $make ) && (int) $make > 0, 'create_make' );
$assert( ! is_wp_error( $default_pack ) && (int) $default_pack > 0, 'create_default_pack' );
$assert( ! is_wp_error( $override_pack ) && (int) $override_pack > 0, 'create_override_pack' );
$assert( ! is_wp_error( $inactive_pack ) && (int) $inactive_pack > 0, 'create_inactive_pack' );
$make_id     = is_wp_error( $make ) ? 0 : (int) $make;
$default_id  = is_wp_error( $default_pack ) ? 0 : (int) $default_pack;
$override_id = is_wp_error( $override_pack ) ? 0 : (int) $override_pack;
$inactive_id = is_wp_error( $inactive_pack ) ? 0 : (int) $inactive_pack;

if ( $make_id > 0 ) {
	$assert( true === SOM_Workflows::save_steps( $make_id, array( $step( 'Cut' ) ) ), 'save_make_steps' );
}
if ( $default_id > 0 ) {
	$assert( true === SOM_Workflows::save_steps( $default_id, array( $step( 'Pack default' ) ) ), 'save_default_steps' );
}
if ( $override_id > 0 ) {
	$assert( true === SOM_Workflows::save_steps( $override_id, array( $step( 'Pack override' ) ) ), 'save_override_steps' );
}
if ( $inactive_id > 0 ) {
	$assert( true === SOM_Workflows::save_steps( $inactive_id, array( $step( 'Pack inactive' ) ) ), 'save_inactive_steps' );
}
$assert( $override_id > 0 && SOM_Pack::template_is_usable( $override_id ), 'override_template_usable' );
$assert( $inactive_id > 0 && ! SOM_Pack::template_is_usable( $inactive_id ), 'inactive_template_not_usable' );

$override_product = SOM_Products::create(
	array(
		'name'                      => 'UP14-S9 Override product ' . $suffix,
		'workflow_template_id'      => '',
		'pack_workflow_template_id' => $override_id,
		'is_internal'               => 0,
		'is_active'                 => 1,
	)
);
$plain_product = SOM_Products::create(
	array(
		'name'         => 'UP14-S9 Plain product ' . $suffix,
		'is_internal'  => 0,
		'is_active'    => 1,
	)
);
$inactive_product = SOM_Products::create(
	array(
		'name'                      => 'UP14-S9 Inactive product ' . $suffix,
		'pack_workflow_template_id' => $inactive_id,
		'is_internal'               => 0,
		'is_active'                 => 1,
	)
);
$internal_product = SOM_Products::create(
	array(
		'name'                      => 'UP14-S9 Internal product ' . $suffix,
		'workflow_template_id'      => $make_id,
		'pack_workflow_template_id' => $override_id,
		'is_internal'               => 1,
		'is_active'                 => 1,
	)
);
$assert( ! is_wp_error( $override_product ) && (int) $override_product > 0, 'create_override_product' );
$assert( ! is_wp_error( $plain_product ) && (int) $plain_product > 0, 'create_plain_product' );
$assert( ! is_wp_error( $inactive_product ) && (int) $inactive_product > 0, 'create_inactive_product' );
$assert( ! is_wp_error( $internal_product ) && (int) $internal_product > 0, 'create_internal_product' );
$override_product_id = is_wp_error( $override_product ) ? 0 : (int) $override_product;
$plain_id            = is_wp_error( $plain_product ) ? 0 : (int) $plain_product;
$inactive_product_id = is_wp_error( $inactive_product ) ? 0 : (int) $inactive_product;
$internal_id         = is_wp_error( $internal_product ) ? 0 : (int) $internal_product;

$override_row = $override_product_id > 0 ? SOM_Products::get( $override_product_id ) : null;
$plain_row    = $plain_id > 0 ? SOM_Products::get( $plain_id ) : null;
$inactive_row = $inactive_product_id > 0 ? SOM_Products::get( $inactive_product_id ) : null;
$internal_row = $internal_id > 0 ? SOM_Products::get( $internal_id ) : null;
$assert( $override_row && $override_id === (int) $override_row->pack_workflow_template_id, 'product_stores_override' );
$assert( $plain_row && empty( $plain_row->pack_workflow_template_id ), 'plain_product_stays_null' );
$assert( $inactive_row && $inactive_id === (int) $inactive_row->pack_workflow_template_id, 'inactive_override_stored' );
$assert( $internal_row && empty( $internal_row->pack_workflow_template_id ), 'internal_product_leaves_override_null' );

$listed_ids = array();
foreach ( SOM_Products::list_pack_templates( $inactive_id ) as $tpl ) {
	$listed_ids[] = (int) $tpl->id;
}
$active_ids = array();
foreach ( SOM_Products::list_pack_templates( 0 ) as $tpl ) {
	$active_ids[] = (int) $tpl->id;
}
$assert( in_array( $inactive_id, $listed_ids, true ) && in_array( $override_id, $listed_ids, true ), 'dropdown_keeps_saved_inactive' );
$assert( $inactive_id > 0 && ! in_array( $inactive_id, $active_ids, true ), 'dropdown_omits_other_inactive' );

$prev_default = SOM_Pack::default_template_id();
$set_default  = SOM_Pack::set_default_template_id( $default_id );
$assert( true === $set_default && $default_id === SOM_Pack::default_template_id(), 'set_site_default' );

$create_order = static function ( $external_id, array $items, $channel = 'external' ) {
	return SOM_Order_Sync::create_from_external(
		array(
			'channel'           => $channel,
			'external_order_id' => $external_id,
			'buyer_name'        => 'UP14-S9',
			'shipping_address'  => array(
				'line1'    => '1 Test St',
				'city'     => 'London',
				'postcode' => 'E1 1AA',
				'country'  => 'GB',
			),
			'items'             => $items,
		)
	);
};

$line = static function ( $product_id ) {
	return array(
		'product_id' => (int) $product_id,
		'quantity'   => 1,
		'unit_price' => 5,
	);
};

$override_order = $create_order( 'up14s9-ov-' . $suffix, array( $line( $override_product_id ) ) );
$assert( ! is_wp_error( $override_order ) && (int) $override_order > 0, 'create_override_order' );
if ( is_wp_error( $override_order ) ) {
	$out( 'create_override_order_err', $override_order->get_error_message() );
}
$override_order_id = is_wp_error( $override_order ) ? 0 : (int) $override_order;
$override_bound    = $override_order_id > 0 ? SOM_Orders::get( $override_order_id ) : null;
$assert( $override_bound && $override_id === (int) $override_bound->pack_workflow_template_id, 'override_binds_on_create' );
$bound_step = $override_bound ? (int) $override_bound->current_step_id : 0;

$plain_order = $create_order( 'up14s9-plain-' . $suffix, array( $line( $plain_id ) ) );
$assert( ! is_wp_error( $plain_order ) && (int) $plain_order > 0, 'create_plain_order' );
$plain_bound = ( ! is_wp_error( $plain_order ) && (int) $plain_order > 0 ) ? SOM_Orders::get( (int) $plain_order ) : null;
$assert( $plain_bound && $default_id === (int) $plain_bound->pack_workflow_template_id, 'blank_override_binds_site_default' );

$multi_order = $create_order(
	'up14s9-multi-' . $suffix,
	array(
		$line( $plain_id ),
		$line( $override_product_id ),
	)
);
$assert( ! is_wp_error( $multi_order ) && (int) $multi_order > 0, 'create_multi_order' );
$multi_bound = ( ! is_wp_error( $multi_order ) && (int) $multi_order > 0 ) ? SOM_Orders::get( (int) $multi_order ) : null;
$assert( $multi_bound && $default_id === (int) $multi_bound->pack_workflow_template_id, 'second_line_override_ignored' );

$inactive_order = $create_order( 'up14s9-off-' . $suffix, array( $line( $inactive_product_id ) ) );
$assert( ! is_wp_error( $inactive_order ) && (int) $inactive_order > 0, 'create_inactive_order' );
$inactive_bound = ( ! is_wp_error( $inactive_order ) && (int) $inactive_order > 0 ) ? SOM_Orders::get( (int) $inactive_order ) : null;
$assert( $inactive_bound && $default_id === (int) $inactive_bound->pack_workflow_template_id, 'inactive_override_falls_through' );

$internal_order = $create_order( 'up14s9-int-' . $suffix, array( $line( $internal_id ) ), 'internal' );
$assert( ! is_wp_error( $internal_order ) && (int) $internal_order > 0, 'create_internal_order' );
$internal_bound = ( ! is_wp_error( $internal_order ) && (int) $internal_order > 0 ) ? SOM_Orders::get( (int) $internal_order ) : null;
$assert( $internal_bound && 'internal' === (string) $internal_bound->channel_slug && empty( $internal_bound->pack_workflow_template_id ), 'internal_order_stays_unbound' );

$cloned = $override_product_id > 0 ? SOM_Products::clone_product( $override_product_id ) : 0;
$assert( ! is_wp_error( $cloned ) && (int) $cloned > 0 && (int) $cloned !== $override_product_id, 'clone_override_product' );
$copy = ( ! is_wp_error( $cloned ) && (int) $cloned > 0 ) ? SOM_Products::get( (int) $cloned ) : null;
$assert( $copy && $override_id === (int) $copy->pack_workflow_template_id, 'clone_copies_override' );

$csv_line = static function ( array $values ) {
	$cells = array();
	foreach ( SOM_Product_Import::COLUMNS as $column ) {
		$cells[] = isset( $values[ $column ] ) ? (string) $values[ $column ] : '';
	}
	return implode( ',', $cells );
};
$override_name = 'UP14-S9 Override ' . $suffix;
$make_name     = 'UP14-S9 Make ' . $suffix;
$header        = implode( ',', SOM_Product_Import::COLUMNS );
$assert( false !== strpos( SOM_Product_Import::template_csv(), 'pack_workflow_name' ), 'csv_template_has_column' );

$good_csv = $header . "\n"
	. $csv_line(
		array(
			'name'               => 'UP14-S9 CSV Override ' . $suffix,
			'pack_workflow_name' => $override_name,
			'is_internal'        => 'no',
		)
	) . "\n"
	. $csv_line(
		array(
			'name'               => 'UP14-S9 CSV Blank ' . $suffix,
			'pack_workflow_name' => '',
			'is_internal'        => 'no',
		)
	) . "\n"
	. $csv_line(
		array(
			'name'               => 'UP14-S9 CSV Internal ' . $suffix,
			'workflow_name'      => $make_name,
			'pack_workflow_name' => $override_name,
			'is_internal'        => 'yes',
		)
	) . "\n";
$committed = SOM_Product_Import::commit( $good_csv );
$assert( 3 === (int) $committed['created'] && 0 === (int) $committed['failed'], 'csv_commit_three' );
if ( 3 !== (int) $committed['created'] ) {
	$out( 'csv_commit', $committed );
}

$by_name = static function ( $name ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT id, pack_workflow_template_id, is_internal FROM ' . SOM_DB::table( 'products' ) . ' WHERE name = %s ORDER BY id DESC LIMIT 1',
			$name
		)
	);
};
$csv_override = $by_name( 'UP14-S9 CSV Override ' . $suffix );
$csv_blank    = $by_name( 'UP14-S9 CSV Blank ' . $suffix );
$csv_internal = $by_name( 'UP14-S9 CSV Internal ' . $suffix );
$assert( $csv_override && $override_id === (int) $csv_override->pack_workflow_template_id, 'csv_name_sets_override' );
$assert( $csv_blank && empty( $csv_blank->pack_workflow_template_id ), 'csv_blank_means_no_override' );
$assert( $csv_internal && ! empty( $csv_internal->is_internal ) && empty( $csv_internal->pack_workflow_template_id ), 'csv_internal_ignores_cell' );

$unknown = SOM_Product_Import::parse(
	$header . "\n" . $csv_line(
		array(
			'name'               => 'UP14-S9 CSV Unknown ' . $suffix,
			'pack_workflow_name' => 'UP14-S9 Missing ' . $suffix,
		)
	) . "\n"
);
$unknown_code = ( isset( $unknown['rows'][0]['code'] ) ) ? (string) $unknown['rows'][0]['code'] : '';
$assert( 1 === (int) $unknown['failed'] && 'unknown_pack' === $unknown_code, 'csv_unknown_pack_fails' );

$twin_name = 'UP14-S9 Twin ' . $suffix;
$twin_a    = SOM_Workflows::create(
	array(
		'name'      => $twin_name,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$twin_b = SOM_Workflows::create(
	array(
		'name'      => $twin_name,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $twin_a ) && ! is_wp_error( $twin_b ), 'create_twin_pack_names' );
$ambiguous = SOM_Product_Import::parse(
	$header . "\n" . $csv_line(
		array(
			'name'               => 'UP14-S9 CSV Ambiguous ' . $suffix,
			'pack_workflow_name' => $twin_name,
		)
	) . "\n"
);
$ambiguous_code = ( isset( $ambiguous['rows'][0]['code'] ) ) ? (string) $ambiguous['rows'][0]['code'] : '';
$assert( 1 === (int) $ambiguous['failed'] && 'ambiguous_pack' === $ambiguous_code, 'csv_ambiguous_pack_fails' );

$old_file = SOM_Product_Import::parse( "name\nUP14-S9 CSV Old " . $suffix . "\n" );
$old_pack = ( isset( $old_file['rows'][0]['data']['pack_workflow_template_id'] ) ) ? $old_file['rows'][0]['data']['pack_workflow_template_id'] : 'missing';
$assert( ! empty( $old_file['rows'][0]['ok'] ) && '' === (string) $old_pack, 'csv_without_column_is_blank' );

$cleared = $override_product_id > 0
	? SOM_Products::update(
		$override_product_id,
		array(
			'pack_workflow_template_id' => '',
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $cleared, 'clear_product_override' );
$moved_default = SOM_Pack::set_default_template_id( $inactive_id );
$assert( true === $moved_default, 'move_site_default' );
$repair = SOM_Pack::repair_unbound_orders();
$assert( is_array( $repair ), 'repair_ran' );
$still = $override_order_id > 0 ? SOM_Orders::get( $override_order_id ) : null;
$assert(
	$still
		&& $override_id === (int) $still->pack_workflow_template_id
		&& $bound_step === (int) $still->current_step_id,
	'already_bound_unchanged_by_repair'
);

SOM_Pack::set_default_template_id( $prev_default );

if ( $fail > 0 ) {
	echo "FAIL — UP14-S9 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S9 smoke\n";
