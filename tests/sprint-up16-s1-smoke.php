<?php
/**
 * UP16-S1 smoke: required step photos, Mark done, and Unblock.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up16-s1-smoke.php
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
SOM_Batch_Groups::ensure_rows();

$out( 'plugin', SOM_VERSION );
$assert( '0.36.0' === SOM_VERSION, 'plugin_stays_0_36_0' );
$assert( '1.27.0' === SOM_DB::DB_VERSION, 'schema_1_27_0' );

global $wpdb;

$steps_table = SOM_DB::table( 'workflow_steps' );
$photo_table = SOM_DB::table( 'step_photos' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$step_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$steps_table}", 0 );
$assert( is_array( $step_cols ) && in_array( 'photo_required', $step_cols, true ), 'column_photo_required' );
$assert( is_array( $step_cols ) && in_array( 'photo_label', $step_cols, true ), 'column_photo_label' );
$assert(
	$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $photo_table ) ) === $photo_table,
	'table_som_step_photos'
);

require_once ABSPATH . 'wp-admin/includes/template.php';

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin || ! user_can( $admin, 'manage_options' ) ) {
	$created = wp_insert_user(
		array(
			'user_login' => 'up16s1admin',
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'up16s1admin@example.com',
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

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$template_id_by_name = static function ( $name ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT id FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE name = %s ORDER BY id DESC LIMIT 1',
			(string) $name
		)
	);
};

$make_id = SOM_Workflows::create(
	array(
		'name'      => 'UP16-S1 make ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $make_id ) && (int) $make_id > 0, 'create_make_template' );
$make_id = is_wp_error( $make_id ) ? 0 : (int) $make_id;
$after   = array();

$long_label = str_repeat( 'A', SOM_Step_Photos::LABEL_MAX + 1 );
if ( $make_id > 0 ) {
	$too_long = SOM_Workflows::save_steps(
		$make_id,
		array(
			array(
				'name'           => 'Photograph',
				'photo_required' => 1,
				'photo_label'    => $long_label,
			),
		)
	);
	$assert( is_wp_error( $too_long ), 'long_label_fails' );
	$assert( array() === SOM_Workflows::get_steps( $make_id ), 'long_label_writes_nothing' );

	$saved = SOM_Workflows::save_steps(
		$make_id,
		array(
			array(
				'name'           => 'Photograph',
				'photo_required' => 1,
				'photo_label'    => 'Laminated sheet',
			),
		)
	);
	$assert( true === $saved, 'save_photo_step' );
	$stored = SOM_Workflows::get_steps( $make_id );
	$assert( 1 === count( $stored ), 'one_photo_step' );
	$assert( ! empty( $stored[0]->photo_required ), 'stored_photo_required' );
	$assert( 'Laminated sheet' === (string) $stored[0]->photo_label, 'stored_photo_label' );
	$assert( ! empty( $stored[0]->requires_manual_confirm ), 'photo_forces_manual' );
	$assert( false !== strpos( SOM_Workflows::step_gates_label( $stored[0] ), 'photo' ), 'gates_label_photo' );

	$kept_id = (int) $stored[0]->id;
	$again   = SOM_Workflows::save_steps(
		$make_id,
		array(
			array(
				'id'             => $kept_id,
				'name'           => 'Photograph',
				'photo_required' => 1,
				'photo_label'    => $long_label,
			),
		)
	);
	$assert( is_wp_error( $again ), 'long_label_update_fails' );
	$after = SOM_Workflows::get_steps( $make_id );
	$assert(
		1 === count( $after ) && 'Laminated sheet' === (string) $after[0]->photo_label && (int) $after[0]->id === $kept_id,
		'long_label_update_writes_nothing'
	);
}

$group = SOM_Batch_Groups::get_by_key( 'shipping_label' );
$assert( $group && ! empty( $group->id ), 'batch_group_exists' );
$pack_id = SOM_Workflows::create(
	array(
		'name'      => 'UP16-S1 pack ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $pack_id ) && (int) $pack_id > 0, 'create_pack_template' );
$pack_id = is_wp_error( $pack_id ) ? 0 : (int) $pack_id;
if ( $pack_id > 0 && $group ) {
	$batch_photo = SOM_Workflows::save_steps(
		$pack_id,
		array(
			array(
				'name'           => 'Labels',
				'batch_group_id' => (int) $group->id,
				'photo_required' => 1,
				'photo_label'    => 'Label photo',
			),
		)
	);
	$assert( is_wp_error( $batch_photo ), 'batch_plus_photo_rejected' );
	$assert( array() === SOM_Workflows::get_steps( $pack_id ), 'batch_plus_photo_writes_nothing' );

	$pack_saved = SOM_Workflows::save_steps(
		$pack_id,
		array(
			array(
				'name'           => 'Pack photo',
				'photo_required' => 1,
				'photo_label'    => 'Packed parcel',
			),
		)
	);
	$assert( true === $pack_saved, 'save_pack_photo_step' );
}

$clone_id = $make_id > 0 ? SOM_Workflows::clone_template( $make_id ) : 0;
$assert( ! is_wp_error( $clone_id ) && (int) $clone_id > 0, 'clone_template' );
$clone_id = is_wp_error( $clone_id ) ? 0 : (int) $clone_id;
if ( $clone_id > 0 ) {
	$cloned = SOM_Workflows::get_steps( $clone_id );
	$assert(
		! empty( $cloned[0] )
		&& ! empty( $cloned[0]->photo_required )
		&& 'Laminated sheet' === (string) $cloned[0]->photo_label,
		'clone_keeps_photo_fields'
	);
}

$omit_name = 'UP16-S1 omit ' . $suffix;
$keep_name = 'UP16-S1 csv ' . $suffix;
$bad_name  = 'UP16-S1 batch csv ' . $suffix;
$omit_csv  = "name,kind,step_order,step_name\n{$omit_name},make,1,Cut\n";
$keep_csv  = "name,kind,step_order,step_name,photo_required,photo_label\n{$keep_name},make,1,Shoot,1,Sheet photo\n";
$bad_csv   = "name,kind,step_order,step_name,photo_required,batch_group_key\n{$bad_name},pack,1,Labels,1,shipping_label\n";

$omit_result = SOM_Workflow_Import::commit( $omit_csv );
$assert( 1 === (int) $omit_result['created'], 'csv_omit_creates' );
$omit_id = $template_id_by_name( $omit_name );
$omit_steps = $omit_id > 0 ? SOM_Workflows::get_steps( $omit_id ) : array();
$assert(
	! empty( $omit_steps[0] ) && empty( $omit_steps[0]->photo_required ) && ( null === $omit_steps[0]->photo_label || '' === (string) $omit_steps[0]->photo_label ),
	'csv_omit_not_required'
);

$keep_result = SOM_Workflow_Import::commit( $keep_csv );
$assert( 1 === (int) $keep_result['created'], 'csv_keep_creates' );
$keep_id = $template_id_by_name( $keep_name );
$keep_steps = $keep_id > 0 ? SOM_Workflows::get_steps( $keep_id ) : array();
$assert(
	! empty( $keep_steps[0] )
	&& ! empty( $keep_steps[0]->photo_required )
	&& 'Sheet photo' === (string) $keep_steps[0]->photo_label,
	'csv_keeps_photo_fields'
);

$bad_result = SOM_Workflow_Import::commit( $bad_csv );
$assert( 0 === (int) $bad_result['created'], 'csv_batch_photo_creates_none' );
$assert( 0 === $template_id_by_name( $bad_name ), 'csv_batch_photo_no_template' );

$map_step = ! empty( $after[0] ) ? $after[0] : null;
if ( $map_step ) {
	$method = new ReflectionMethod( 'SOM_Abilities', 'map_workflow_step' );
	$method->setAccessible( true );
	$mapped = $method->invoke( null, $map_step );
	$assert( is_array( $mapped ) && 1 === (int) $mapped['photo_required'], 'ability_photo_required' );
	$assert( is_array( $mapped ) && 'Laminated sheet' === $mapped['photo_label'], 'ability_photo_label' );
	$assert( is_array( $mapped ) && ! array_key_exists( 'attachment_id', $mapped ), 'ability_omits_attachment' );
	$encoded = is_array( $mapped ) ? wp_json_encode( $mapped ) : '';
	$assert( is_string( $encoded ) && false === strpos( $encoded, 'step-photos' ), 'ability_omits_file_url' );
} else {
	$assert( false, 'ability_photo_required' );
	$assert( false, 'ability_photo_label' );
	$assert( false, 'ability_omits_attachment' );
	$assert( false, 'ability_omits_file_url' );
}

$product_id = 0;
if ( $make_id > 0 && $pack_id > 0 ) {
	$product = SOM_Products::create(
		array(
			'name'                      => 'UP16-S1 sticker ' . $suffix,
			'sku'                       => 'UP16S1-' . $suffix,
			'workflow_template_id'      => $make_id,
			'pack_workflow_template_id' => $pack_id,
			'is_active'                 => 1,
		)
	);
	$assert( ! is_wp_error( $product ) && (int) $product > 0, 'create_product' );
	$product_id = is_wp_error( $product ) ? 0 : (int) $product;
}

$create_order = static function ( $external_id ) use ( $product_id ) {
	if ( $product_id < 1 ) {
		return 0;
	}
	$created = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => $external_id,
			'buyer_name'        => 'Photo Buyer',
			'shipping_address'  => array(
				'full_name' => 'Photo Buyer',
				'line1'     => '1 High Street',
				'city'      => 'London',
				'postcode'  => 'SW1A 1AA',
				'country'   => 'GB',
			),
			'items'             => array(
				array(
					'product_id' => $product_id,
					'quantity'   => 1,
					'unit_price' => 3,
				),
			),
		)
	);
	return is_wp_error( $created ) ? 0 : (int) $created;
};

$render_detail = static function ( $order_id ) {
	$_GET['order_id'] = (int) $order_id;
	ob_start();
	SOM_Admin_Menu::render_orders();
	return (string) ob_get_clean();
};

$make_html = static function ( $html ) {
	$start = strpos( $html, 'class="som-panel som-panel-make"' );
	if ( false === $start ) {
		return '';
	}
	$end = strlen( $html );
	foreach ( array( 'class="som-panel som-panel-pack"', 'class="som-panel som-panel-workflow"', 'class="som-panel som-panel-post-ship"' ) as $marker ) {
		$at = strpos( $html, $marker, $start + 10 );
		if ( false !== $at && $at < $end ) {
			$end = $at;
		}
	}
	return substr( $html, $start, $end - $start );
};

$done_step_html = static function ( $make ) {
	$at = strpos( $make, 'status-done' );
	if ( false === $at ) {
		return '';
	}
	$head  = substr( $make, 0, $at );
	$start = strrpos( $head, '<li' );
	if ( false === $start ) {
		$start = $at;
	}
	$next = strpos( $make, '<li class="som-workflow-step', $at );
	$end  = false === $next ? strlen( $make ) : $next;
	return substr( $make, $start, $end - $start );
};

$unblock_id = $create_order( 'up16s1-unblock-' . $suffix );
$assert( $unblock_id > 0, 'create_unblock_order' );
if ( $unblock_id > 0 ) {
	$unblock_order = SOM_Orders::get( $unblock_id );
	$item_id       = ( $unblock_order && ! empty( $unblock_order->items[0]->id ) ) ? (int) $unblock_order->items[0]->id : 0;
	$current       = $item_id > 0 ? SOM_Item_Make::current_item_progress( $item_id ) : null;
	$assert( $current && 'in_progress' === (string) $current->status, 'photo_only_stays_in_progress' );
	$assert( $current && ! SOM_Workflow_Engine::can_mark_done( $current, $current ), 'mark_done_false_without_photo' );

	$unblocked = ( $item_id > 0 ) ? SOM_Workflow_Engine::unblock_step( $unblock_id, $item_id ) : new WP_Error( 'skipped', 'skipped' );
	$assert( true === $unblocked, 'unblock_photo_step' );
	$after_unblock = $item_id > 0 ? SOM_Item_Make::current_item_progress( $item_id ) : null;
	$assert( $after_unblock && 'in_progress' === (string) $after_unblock->status, 'unblock_does_not_mark_done' );
	$assert( $after_unblock && SOM_Workflow_Engine::can_mark_done( $after_unblock, $after_unblock ), 'mark_done_true_after_unblock' );
	$gates = $after_unblock && isset( $after_unblock->manual_unblock_gates ) ? (string) $after_unblock->manual_unblock_gates : '';
	$assert( false !== strpos( $gates, 'photo' ), 'unblock_gates_include_photo' );
	$noted = false;
	foreach ( SOM_Order_Notes::list_for_order( $unblock_id ) as $note ) {
		if ( isset( $note->body ) && false !== strpos( (string) $note->body, 'photo' ) ) {
			$noted = true;
			break;
		}
	}
	$assert( $noted, 'unblock_note_lists_photo' );
}

$file_id = $create_order( 'up16s1-file-' . $suffix );
$assert( $file_id > 0, 'create_file_order' );
if ( $file_id > 0 ) {
	$before_html = $make_html( $render_detail( $file_id ) );
	$assert( false !== strpos( $before_html, 'type="file"' ), 'current_photo_step_file_input' );
	$assert( false !== strpos( $before_html, 'accept="image/jpeg,image/png"' ), 'current_photo_step_accept' );

	$file_order = SOM_Orders::get( $file_id );
	$item_id    = ( $file_order && ! empty( $file_order->items[0]->id ) ) ? (int) $file_order->items[0]->id : 0;
	$make_prog  = $item_id > 0 ? SOM_Item_Make::current_item_progress( $item_id ) : null;
	$pack_prog  = null;
	if ( $file_order && ! empty( $file_order->current_step_id ) ) {
		foreach ( SOM_Workflow_Engine::get_progress( $file_id ) as $row ) {
			if ( (int) $row->workflow_step_id === (int) $file_order->current_step_id ) {
				$pack_prog = $row;
				break;
			}
		}
	}
	$assert( $make_prog && $pack_prog && (int) $make_prog->id !== (int) $pack_prog->id, 'make_and_pack_progress' );

	$jpeg = '';
	if ( function_exists( 'imagejpeg' ) ) {
		$image = imagecreatetruecolor( 8, 8 );
		if ( $image ) {
			$color = imagecolorallocate( $image, 180, 30, 30 );
			imagefill( $image, 0, 0, $color );
			ob_start();
			imagejpeg( $image, null, 90 );
			$jpeg = (string) ob_get_clean();
			imagedestroy( $image );
		}
	}
	$assert( strlen( $jpeg ) > 20, 'jpeg_bytes' );

	$store = static function ( $order_id, $item_id, $filename, $bytes ) {
		$tmp = wp_tempnam( $filename );
		file_put_contents( $tmp, $bytes );
		$_FILES['som_step_photo_file'] = array(
			'name'     => $filename,
			'type'     => 'application/octet-stream',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => strlen( $bytes ),
		);
		$result = SOM_Step_Photos::add_from_upload( $order_id, $item_id );
		unset( $_FILES['som_step_photo_file'] );
		if ( is_string( $tmp ) && file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		return $result;
	};

	$stored_jpeg = ( $make_prog && '' !== $jpeg ) ? $store( $file_id, $item_id, 'sheet.jpg', $jpeg ) : new WP_Error( 'skipped', 'skipped' );
	$assert( true === $stored_jpeg, 'jpeg_stored' );
	$make_photos = $make_prog ? SOM_Step_Photos::list_for_item_progress( (int) $make_prog->id ) : array();
	$assert( 1 === count( $make_photos ), 'jpeg_on_make_progress' );
	$make_attachment = ! empty( $make_photos[0]->attachment_id ) ? (int) $make_photos[0]->attachment_id : 0;
	$make_path       = $make_attachment > 0 ? (string) get_attached_file( $make_attachment ) : '';
	$assert( false !== strpos( wp_normalize_path( $make_path ), 'order-machine/step-photos' ), 'jpeg_in_step_photos_dir' );

	$pdf = ( $make_prog ) ? $store( $file_id, $item_id, 'notes.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n" ) : new WP_Error( 'skipped', 'skipped' );
	$assert( is_wp_error( $pdf ), 'pdf_rejected' );
	$assert( 1 === count( SOM_Step_Photos::list_for_item_progress( $make_prog ? (int) $make_prog->id : 0 ) ), 'pdf_adds_no_row' );

	$stored_pack = ( $pack_prog && '' !== $jpeg ) ? $store( $file_id, 0, 'parcel.jpg', $jpeg ) : new WP_Error( 'skipped', 'skipped' );
	$assert( true === $stored_pack, 'pack_jpeg_stored' );
	$pack_photos = $pack_prog ? SOM_Step_Photos::list_for_order_progress( (int) $pack_prog->id ) : array();
	$assert( 1 === count( $pack_photos ), 'jpeg_on_order_progress' );
	$pack_attachment = ! empty( $pack_photos[0]->attachment_id ) ? (int) $pack_photos[0]->attachment_id : 0;
	$assert( $make_attachment > 0 && $pack_attachment > 0 && $make_attachment !== $pack_attachment, 'make_and_pack_photos_differ' );
	$assert( array() === SOM_Step_Photos::list_for_order_progress( $make_prog ? (int) $make_prog->id : 0 ), 'make_id_not_on_order_photos' );
	$assert( array() === SOM_Step_Photos::list_for_item_progress( $pack_prog ? (int) $pack_prog->id : 0 ), 'pack_id_not_on_make_photos' );

	global $wpdb;
	$make_row = $make_attachment > 0
		? $wpdb->get_row(
			$wpdb->prepare(
				'SELECT order_step_progress_id, order_item_step_progress_id FROM ' . SOM_DB::table( 'step_photos' ) . ' WHERE attachment_id = %d LIMIT 1',
				$make_attachment
			)
		)
		: null;
	$pack_row = $pack_attachment > 0
		? $wpdb->get_row(
			$wpdb->prepare(
				'SELECT order_step_progress_id, order_item_step_progress_id FROM ' . SOM_DB::table( 'step_photos' ) . ' WHERE attachment_id = %d LIMIT 1',
				$pack_attachment
			)
		)
		: null;
	$assert(
		$make_row && $make_prog && empty( $make_row->order_step_progress_id ) && (int) $make_row->order_item_step_progress_id === (int) $make_prog->id,
		'make_row_one_progress_id'
	);
	$assert(
		$pack_row && $pack_prog && empty( $pack_row->order_item_step_progress_id ) && (int) $pack_row->order_step_progress_id === (int) $pack_prog->id,
		'pack_row_one_progress_id'
	);

	$marked = ( $item_id > 0 ) ? SOM_Item_Make::mark_done( $file_id, $item_id ) : new WP_Error( 'skipped', 'skipped' );
	$assert( true === $marked, 'mark_done_after_photo' );
	$done_html = $done_step_html( $make_html( $render_detail( $file_id ) ) );
	$assert( false !== strpos( $done_html, 'som-step-photo-thumb' ), 'done_step_keeps_thumbnail' );
	$assert( '' !== $done_html && false === strpos( $done_html, 'type="file"' ), 'done_step_hides_file_input' );
}

if ( $fail > 0 ) {
	echo "FAIL — UP16-S1 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP16-S1 smoke\n";
