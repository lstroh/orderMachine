<?php
/**
 * Sprint U5 smoke: batch gate collect → release → advance; script retry.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u5-smoke.php
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
SOM_Batch_Groups::ensure_rows();

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( version_compare( SOM_VERSION, '0.16.0', '>=' ), 'SOM_VERSION_gte_0.16.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.5.0', '>=' ), 'som_db_version_gte_1.5.0' );

$batches_t = SOM_DB::table( 'step_batches' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$retry_col = $wpdb->get_row( "SHOW COLUMNS FROM {$batches_t} LIKE 'retry_count'" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$after_col = $wpdb->get_row( "SHOW COLUMNS FROM {$batches_t} LIKE 'retry_after'" );
$assert( ! empty( $retry_col ), 'step_batches_retry_count' );
$assert( ! empty( $after_col ), 'step_batches_retry_after' );

$ship_group = SOM_Batch_Groups::get_by_key( 'shipping_label' );
$ty_group   = SOM_Batch_Groups::get_by_key( 'thank_you_card' );
$assert( $ship_group && 'manual_confirm' === (string) $ship_group->action_type, 'shipping_label_group' );
$assert( $ty_group && 'script' === (string) $ty_group->action_type, 'thank_you_group' );

$settings = SOM_Settings::get();
if ( '' === (string) $settings['api_key'] ) {
	SOM_Settings::update( array( 'api_key' => 'sprint-u5-test-key' ) );
	$settings = SOM_Settings::get();
}
$api_key = (string) $settings['api_key'];

/**
 * Create a one-step batch workflow + product; return product_id and step_id.
 *
 * @param string $name     Workflow name.
 * @param int    $group_id Batch group PK.
 * @return array{product_id:int,step_id:int,template_id:int}|null
 */
$make_batch_product = static function ( $name, $group_id ) {
	$wf = SOM_Workflows::create(
		array(
			'name'      => $name,
			'is_active' => 1,
		)
	);
	if ( is_wp_error( $wf ) ) {
		return null;
	}
	$wf = (int) $wf;

	$saved = SOM_Workflows::save_steps(
		$wf,
		array(
			array(
				'name'           => 'Batch gate',
				'batch_group_id' => (int) $group_id,
			),
		)
	);
	if ( is_wp_error( $saved ) ) {
		return null;
	}

	$steps = SOM_Workflows::get_steps( $wf );
	if ( empty( $steps ) || empty( $steps[0]->batch_group_id ) ) {
		return null;
	}

	$product = SOM_Products::create(
		array(
			'name'                 => $name . ' Product',
			'workflow_template_id' => $wf,
			'is_active'            => 1,
		)
	);
	if ( is_wp_error( $product ) ) {
		return null;
	}

	return array(
		'product_id'  => (int) $product,
		'step_id'     => (int) $steps[0]->id,
		'template_id' => $wf,
	);
};

/**
 * Create an order for a product via REST (assigns workflow).
 *
 * @param int    $product_id Product PK.
 * @param string $suffix     External id suffix.
 * @return int Order PK or 0.
 */
$create_order = static function ( $product_id, $suffix ) use ( $api_key ) {
	$external_id = 'u5-' . $suffix . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
	$req         = new WP_REST_Request( 'POST', '/som/v1/orders' );
	$req->set_header( 'x-som-api-key', $api_key );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body(
		wp_json_encode(
			array(
				'external_order_id' => $external_id,
				'buyer_name'        => 'U5 Buyer ' . $suffix,
				'shipping_address'  => array(
					'line1'    => '1 Test Street',
					'city'     => 'London',
					'postcode' => 'E1 1AA',
					'country'  => 'GB',
				),
				'items'             => array(
					array(
						'product_id' => (int) $product_id,
						'quantity'   => 1,
					),
				),
			)
		)
	);
	$res  = rest_do_request( $req );
	$data = $res->get_data();
	return isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
};

// --- Batch-only validation ---
$bad = SOM_Workflows::create( array( 'name' => 'U5 Bad Combo', 'is_active' => 1 ) );
$assert( ! is_wp_error( $bad ), 'bad_combo_workflow_create' );
$bad = (int) $bad;
$combo = SOM_Workflows::save_steps(
	$bad,
	array(
		array(
			'name'                    => 'Illegal',
			'batch_group_id'          => (int) $ship_group->id,
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( is_wp_error( $combo ) && 'som_batch_only_step' === $combo->get_error_code(), 'batch_only_validation' );

// --- Preserve batch_group_id when form omits it ---
$preserve = $make_batch_product( 'U5 Preserve', (int) $ship_group->id );
$assert( null !== $preserve, 'preserve_setup' );
if ( $preserve ) {
	$steps_before = SOM_Workflows::get_steps( $preserve['template_id'] );
	$step_row     = $steps_before[0];
	$resave       = SOM_Workflows::save_steps(
		$preserve['template_id'],
		array(
			array(
				'id'   => (int) $step_row->id,
				'name' => 'Batch gate renamed',
			),
		)
	);
	$steps_after = SOM_Workflows::get_steps( $preserve['template_id'] );
	$assert(
		true === $resave
		&& ! empty( $steps_after[0]->batch_group_id )
		&& (int) $steps_after[0]->batch_group_id === (int) $ship_group->id,
		'preserve_batch_group_id_on_save'
	);
}

// --- Manual confirm: collect under size, release, mark done ---
$manual_setup = $make_batch_product( 'U5 Manual Ship', (int) $ship_group->id );
$assert( null !== $manual_setup, 'manual_setup' );

$order_ids = array();
if ( $manual_setup ) {
	for ( $i = 1; $i <= 3; $i++ ) {
		$oid = $create_order( $manual_setup['product_id'], 'm' . $i );
		$order_ids[] = $oid;
		$assert( $oid > 0, 'manual_order_' . $i );
	}

	$batch = SOM_Batches::get_collecting( (int) $ship_group->id );
	$assert( $batch && 'collecting' === (string) $batch->status, 'manual_collecting_batch' );
	$assert( 3 === count( SOM_Batches::get_items( (int) $batch->id ) ), 'manual_three_members' );

	foreach ( $order_ids as $oid ) {
		if ( $oid < 1 ) {
			continue;
		}
		$order = SOM_Orders::get( $oid );
		$prog  = null;
		foreach ( (array) $order->workflow_progress as $row ) {
			if ( (int) $row->workflow_step_id === (int) $manual_setup['step_id'] ) {
				$prog = $row;
				break;
			}
		}
		$assert( $prog && 'waiting_batch' === (string) $prog->status, 'manual_waiting_batch_' . $oid );
	}

	$released = SOM_Batches::release( (int) $batch->id, true );
	$assert( true === $released, 'manual_release' );
	$batch = SOM_Batches::get( (int) $batch->id );
	$assert( $batch && 'ready' === (string) $batch->status && 1 === (int) $batch->released_manually, 'manual_ready' );

	$done = SOM_Batches::mark_done( (int) $batch->id );
	$assert( true === $done, 'manual_mark_done' );
	$batch = SOM_Batches::get( (int) $batch->id );
	$assert( $batch && 'done' === (string) $batch->status, 'manual_batch_done' );

	foreach ( $order_ids as $oid ) {
		if ( $oid < 1 ) {
			continue;
		}
		$order = SOM_Orders::get( $oid );
		$assert( ! empty( $order->is_complete ), 'manual_order_complete_' . $oid );
	}
}

// --- Auto-ready at size 4 (manual_confirm) ---
$auto_setup = $make_batch_product( 'U5 Auto Ship', (int) $ship_group->id );
$assert( null !== $auto_setup, 'auto_setup' );
$auto_orders = array();
$auto_batch_id = 0;
if ( $auto_setup ) {
	for ( $i = 1; $i <= 4; $i++ ) {
		$oid = $create_order( $auto_setup['product_id'], 'a' . $i );
		$auto_orders[] = $oid;
		$assert( $oid > 0, 'auto_order_' . $i );
		if ( 4 === $i && $oid > 0 ) {
			// Last enqueue should have released; find batch via items.
			$items_t = SOM_DB::table( 'step_batch_items' );
			$auto_batch_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT batch_id FROM {$items_t} WHERE order_id = %d ORDER BY id DESC LIMIT 1",
					$oid
				)
			);
		}
	}
	$auto_batch = $auto_batch_id ? SOM_Batches::get( $auto_batch_id ) : null;
	$assert( $auto_batch && 'ready' === (string) $auto_batch->status, 'auto_ready_at_4' );
	$assert( 0 === (int) $auto_batch->released_manually, 'auto_not_manual_flag' );
	$assert( null === SOM_Batches::get_collecting( (int) $ship_group->id ), 'no_collecting_after_auto' );

	$done = SOM_Batches::mark_done( $auto_batch_id );
	$assert( true === $done, 'auto_mark_done' );
}

// --- Script path: empty script_config → same-request complete ---
$orig_script = $ty_group->script_config;
$wpdb->update(
	SOM_DB::table( 'batch_groups' ),
	array(
		'script_config' => null,
		'updated_at'    => current_time( 'mysql', true ),
	),
	array( 'id' => (int) $ty_group->id ),
	array( '%s', '%s' ),
	array( '%d' )
);

$script_setup = $make_batch_product( 'U5 Script TY', (int) $ty_group->id );
$assert( null !== $script_setup, 'script_setup' );
$script_batch_id = 0;
if ( $script_setup ) {
	$sids = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$oid = $create_order( $script_setup['product_id'], 's' . $i );
		$sids[] = $oid;
		$assert( $oid > 0, 'script_order_' . $i );
		if ( 4 === $i && $oid > 0 ) {
			$items_t = SOM_DB::table( 'step_batch_items' );
			$script_batch_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT batch_id FROM {$items_t} WHERE order_id = %d ORDER BY id DESC LIMIT 1",
					$oid
				)
			);
		}
	}
	$sb = $script_batch_id ? SOM_Batches::get( $script_batch_id ) : null;
	$assert( $sb && 'done' === (string) $sb->status, 'script_auto_done' );
	foreach ( $sids as $oid ) {
		if ( $oid < 1 ) {
			continue;
		}
		$order = SOM_Orders::get( $oid );
		$assert( ! empty( $order->is_complete ), 'script_order_complete_' . $oid );
	}
}

// --- Script failure → error members → domain retry ---
$wpdb->update(
	SOM_DB::table( 'batch_groups' ),
	array(
		'script_config' => wp_json_encode(
			array(
				'type'   => 'local',
				'action' => 'not_a_real_action',
				'params' => array(),
			)
		),
		'updated_at'    => current_time( 'mysql', true ),
	),
	array( 'id' => (int) $ty_group->id ),
	array( '%s', '%s' ),
	array( '%d' )
);

$fail_setup = $make_batch_product( 'U5 Script Fail', (int) $ty_group->id );
$assert( null !== $fail_setup, 'fail_setup' );
$fail_batch_id = 0;
if ( $fail_setup ) {
	$oid = $create_order( $fail_setup['product_id'], 'f1' );
	$assert( $oid > 0, 'fail_order' );
	$batch = SOM_Batches::get_collecting( (int) $ty_group->id );
	$assert( (bool) $batch, 'fail_collecting' );
	if ( $batch ) {
		SOM_Batches::release( (int) $batch->id, true );
		$fail_batch_id = (int) $batch->id;
		$batch = SOM_Batches::get( $fail_batch_id );
		$assert( $batch && 'processing' === (string) $batch->status && (int) $batch->retry_count >= 1, 'fail_backoff_1' );

		// Force remaining attempts without waiting.
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$batch = SOM_Batches::get( $fail_batch_id );
			if ( ! $batch || 'error' === (string) $batch->status ) {
				break;
			}
			$wpdb->update(
				SOM_DB::table( 'step_batches' ),
				array( 'retry_after' => gmdate( 'Y-m-d H:i:s', time() - 10 ) ),
				array( 'id' => $fail_batch_id ),
				array( '%s' ),
				array( '%d' )
			);
			SOM_Batches::attempt_by_id( $fail_batch_id );
		}

		$batch = SOM_Batches::get( $fail_batch_id );
		$assert( $batch && 'error' === (string) $batch->status, 'fail_batch_error' );

		$order = SOM_Orders::get( $oid );
		$member_err = false;
		foreach ( (array) $order->workflow_progress as $row ) {
			if ( (int) $row->workflow_step_id === (int) $fail_setup['step_id'] && 'error' === (string) $row->status ) {
				$member_err = true;
			}
		}
		$assert( $member_err, 'fail_member_error' );

		// Clear script so retry succeeds.
		$wpdb->update(
			SOM_DB::table( 'batch_groups' ),
			array(
				'script_config' => null,
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $ty_group->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$retried = SOM_Batches::retry( $fail_batch_id );
		$assert( true === $retried, 'domain_retry' );
		$batch = SOM_Batches::get( $fail_batch_id );
		$assert( $batch && 'done' === (string) $batch->status, 'retry_batch_done' );
		$order = SOM_Orders::get( $oid );
		$assert( ! empty( $order->is_complete ), 'retry_order_complete' );
	}
}

// Restore thank-you group script_config.
$wpdb->update(
	SOM_DB::table( 'batch_groups' ),
	array(
		'script_config' => $orig_script,
		'updated_at'    => current_time( 'mysql', true ),
	),
	array( 'id' => (int) $ty_group->id ),
	array( '%s', '%s' ),
	array( '%d' )
);

if ( $fail > 0 ) {
	echo "FAIL — Sprint U5 smoke ({$fail} assertion(s))\n";
	exit( 1 );
}

echo "PASS — Sprint U5 smoke\n";
