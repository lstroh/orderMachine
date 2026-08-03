<?php
/**
 * Internal REST API (orders, purchasing, batches, workflow callbacks).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers som/v1 routes.
 */
class SOM_REST_API {

	/**
	 * Wire rest_api_init.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'som/v1',
			'/orders',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_order' ),
				'permission_callback' => array( __CLASS__, 'check_api_key' ),
			)
		);

		register_rest_route(
			'som/v1',
			'/orders/(?P<id>\d+)/advance-step',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'advance_step' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'som/v1',
			'/workflow-callback/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'workflow_callback' ),
				'permission_callback' => array( __CLASS__, 'check_api_key' ),
				'args'                => array(
					'token'   => array(
						'required' => true,
						'type'     => 'string',
					),
					'success' => array(
						'required' => false,
						'default'  => true,
					),
					'error'   => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);

		// --- Suppliers ---
		register_rest_route(
			'som/v1',
			'/suppliers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_suppliers' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_supplier' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				),
			)
		);

		register_rest_route(
			'som/v1',
			'/suppliers/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_supplier' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
					'args'                => self::id_arg(),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'update_supplier' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
					'args'                => self::id_arg(),
				),
			)
		);

		// --- Purchase orders ---
		register_rest_route(
			'som/v1',
			'/purchase-orders',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_purchase_orders' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_purchase_order' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				),
			)
		);

		register_rest_route(
			'som/v1',
			'/purchase-orders/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'preview_purchase_order' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
			)
		);

		register_rest_route(
			'som/v1',
			'/purchase-orders/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_purchase_order' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
					'args'                => self::id_arg(),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'update_purchase_order' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
					'args'                => self::id_arg(),
				),
			)
		);

		register_rest_route(
			'som/v1',
			'/purchase-orders/(?P<id>\d+)/receive',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'receive_purchase_order' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				'args'                => self::id_arg(),
			)
		);

		register_rest_route(
			'som/v1',
			'/purchase-orders/(?P<id>\d+)/mark-received',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'mark_received_purchase_order' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				'args'                => self::id_arg(),
			)
		);

		register_rest_route(
			'som/v1',
			'/purchase-orders/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'cancel_purchase_order' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				'args'                => self::id_arg(),
			)
		);

		// --- Workflow material goals ---
		register_rest_route(
			'som/v1',
			'/workflow-material-goals',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_workflow_material_goals' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'upsert_workflow_material_goal' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				),
			)
		);

		register_rest_route(
			'som/v1',
			'/workflow-material-goals/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_workflow_material_goal' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
					'args'                => self::id_arg(),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'update_workflow_material_goal' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
					'args'                => self::id_arg(),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_workflow_material_goal' ),
					'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
					'args'                => self::id_arg(),
				),
			)
		);

		// --- Batches ---
		register_rest_route(
			'som/v1',
			'/batches',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_batches' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
			)
		);

		register_rest_route(
			'som/v1',
			'/batches/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_batch' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				'args'                => self::id_arg(),
			)
		);

		register_rest_route(
			'som/v1',
			'/batches/(?P<id>\d+)/release',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'release_batch' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				'args'                => self::id_arg(),
			)
		);

		register_rest_route(
			'som/v1',
			'/batches/(?P<id>\d+)/mark-done',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'mark_done_batch' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				'args'                => self::id_arg(),
			)
		);

		register_rest_route(
			'som/v1',
			'/batches/(?P<id>\d+)/retry',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'retry_batch' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				'args'                => self::id_arg(),
			)
		);

		// --- Batch groups (read-only) ---
		register_rest_route(
			'som/v1',
			'/batch-groups',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_batch_groups' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
			)
		);

		register_rest_route(
			'som/v1',
			'/batch-groups/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_batch_group' ),
				'permission_callback' => array( __CLASS__, 'check_api_key_or_admin' ),
				'args'                => self::id_arg(),
			)
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function id_arg() {
		return array(
			'id' => array(
				'required' => true,
				'type'     => 'integer',
			),
		);
	}

	/**
	 * Require X-SOM-API-Key matching settings (or Authorization: Bearer).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function check_api_key( $request ) {
		$settings = SOM_Settings::get();
		$expected = isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
		if ( '' === $expected ) {
			return new WP_Error(
				'som_api_key_unset',
				__( 'REST API key is not configured in Order Machine settings.', 'order-machine' ),
				array( 'status' => 503 )
			);
		}

		$provided = $request->get_header( 'x-som-api-key' );
		if ( ! $provided ) {
			$auth = $request->get_header( 'authorization' );
			if ( is_string( $auth ) && 0 === stripos( $auth, 'Bearer ' ) ) {
				$provided = trim( substr( $auth, 7 ) );
			}
		}

		if ( ! is_string( $provided ) || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error(
				'som_forbidden',
				__( 'Invalid API key.', 'order-machine' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * API key (external) or logged-in admin (Mark done from wp-admin).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function check_api_key_or_admin( $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return self::check_api_key( $request );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private static function request_body( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		return is_array( $body ) ? $body : array();
	}

	/**
	 * @param WP_Error $error Error.
	 * @param int      $status HTTP status.
	 * @return WP_Error
	 */
	private static function error_response( $error, $status = 400 ) {
		$data = $error->get_error_data();
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		if ( empty( $data['status'] ) ) {
			$data['status'] = $status;
		}
		$error->add_data( $data );
		return $error;
	}

	/**
	 * POST /som/v1/orders — create order (default channel: external).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_order( $request ) {
		$body = self::request_body( $request );

		$order_id = SOM_Order_Sync::create_from_external( $body );
		if ( is_wp_error( $order_id ) ) {
			$data = $order_id->get_error_data();
			if ( ! is_array( $data ) || empty( $data['status'] ) ) {
				$order_id->add_data( array( 'status' => 400 ) );
			}
			return $order_id;
		}

		$order = SOM_Orders::get( (int) $order_id );
		return rest_ensure_response(
			array(
				'ok'       => true,
				'order_id' => (int) $order_id,
				'order'    => $order ? self::order_to_rest( $order ) : null,
			)
		);
	}

	/**
	 * POST /som/v1/orders/{id}/advance-step — same as admin Mark done.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function advance_step( $request ) {
		global $wpdb;

		$order_id = (int) $request['id'];
		$result   = SOM_Workflow_Engine::mark_done( $order_id );
		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		$order   = SOM_Orders::get( $order_id );
		$payload = array(
			'ok'                => true,
			'order_id'          => $order_id,
			'current_step_id'   => $order ? (int) $order->current_step_id : 0,
			'current_step_name' => $order ? (string) $order->current_step_name : '',
			'is_complete'       => $order ? (int) $order->is_complete : 0,
			'progress_status'   => '',
			'batch'             => null,
			'can_advance'       => false,
			'next_step_name'    => '',
			'is_last_step'      => false,
		);

		if ( $order && empty( $order->is_complete ) && ! empty( $order->current_step_id ) ) {
			$progress_t = SOM_DB::table( 'order_step_progress' );
			$status     = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT status FROM {$progress_t} WHERE order_id = %d AND workflow_step_id = %d LIMIT 1",
					$order_id,
					(int) $order->current_step_id
				)
			);
			$payload['progress_status'] = is_string( $status ) ? $status : '';

			if ( 'waiting_batch' === $payload['progress_status'] ) {
				$batch = SOM_Batches::find_for_order( $order_id );
				if ( $batch ) {
					$payload['batch'] = array(
						'id'               => (int) $batch->id,
						'item_count'       => (int) $batch->item_count,
						'group_batch_size' => (int) $batch->group_batch_size,
						'url'              => SOM_Batches::batch_url( (int) $batch->id ),
					);
				}
			}

			$dnd = SOM_Workflow_Engine::board_dnd_meta( $order_id );
			$payload['can_advance']    = ! empty( $dnd['can_advance'] );
			$payload['next_step_name'] = (string) $dnd['next_step_name'];
			$payload['is_last_step']   = ! empty( $dnd['is_last_step'] );
		}

		return rest_ensure_response( $payload );
	}

	/**
	 * n8n / automation callback to complete a waiting_script step.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function workflow_callback( $request ) {
		$token = (string) $request['token'];
		$bind  = SOM_Script_Dispatch::resolve_callback_token( $token );
		if ( ! $bind ) {
			return new WP_Error(
				'som_bad_token',
				__( 'Unknown or expired callback token.', 'order-machine' ),
				array( 'status' => 404 )
			);
		}

		$success = $request['success'];
		if ( is_string( $success ) ) {
			$success = ! in_array( strtolower( $success ), array( '0', 'false', 'no' ), true );
		} else {
			$success = (bool) $success;
		}

		$error = $request->get_param( 'error' );
		$body  = $request->get_json_params();
		if ( is_array( $body ) ) {
			if ( array_key_exists( 'success', $body ) ) {
				$success = (bool) $body['success'];
			}
			if ( ! empty( $body['error'] ) && is_string( $body['error'] ) ) {
				$error = $body['error'];
			}
		}

		$result = SOM_Workflow_Engine::complete_from_callback(
			$bind['order_id'],
			$bind['progress_id'],
			$success,
			is_string( $error ) ? $error : null
		);

		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}

		SOM_Script_Dispatch::consume_callback_token( $token );

		return rest_ensure_response(
			array(
				'ok'       => true,
				'order_id' => $bind['order_id'],
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_suppliers( $request ) {
		$result = SOM_Suppliers::query(
			array(
				's'        => (string) $request->get_param( 's' ),
				'paged'    => max( 1, (int) $request->get_param( 'paged' ) ),
				'per_page' => max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) ),
			)
		);

		$items = array();
		foreach ( $result['suppliers'] as $row ) {
			$items[] = self::supplier_to_rest( $row );
		}

		return rest_ensure_response(
			array(
				'suppliers' => $items,
				'total'     => (int) $result['total'],
				'pages'     => (int) $result['pages'],
				'paged'     => (int) $result['paged'],
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_supplier( $request ) {
		$row = SOM_Suppliers::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'som_supplier_missing', __( 'Supplier not found.', 'order-machine' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::supplier_to_rest( $row ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_supplier( $request ) {
		$id = SOM_Suppliers::create( self::request_body( $request ) );
		if ( is_wp_error( $id ) ) {
			return self::error_response( $id );
		}
		$row = SOM_Suppliers::get( (int) $id );
		return rest_ensure_response(
			array(
				'ok'       => true,
				'id'       => (int) $id,
				'supplier' => $row ? self::supplier_to_rest( $row ) : null,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_supplier( $request ) {
		$result = SOM_Suppliers::update( (int) $request['id'], self::request_body( $request ) );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_supplier_missing' === $code ? 404 : 400 );
		}
		$row = SOM_Suppliers::get( (int) $request['id'] );
		return rest_ensure_response(
			array(
				'ok'       => true,
				'supplier' => $row ? self::supplier_to_rest( $row ) : null,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_purchase_orders( $request ) {
		$result = SOM_Purchase_Orders::query(
			array(
				'status'      => (string) $request->get_param( 'status' ),
				'supplier_id' => (int) $request->get_param( 'supplier_id' ),
				's'           => (string) $request->get_param( 's' ),
				'paged'       => max( 1, (int) $request->get_param( 'paged' ) ),
				'per_page'    => max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) ),
			)
		);

		$items = array();
		foreach ( $result['orders'] as $row ) {
			$items[] = self::purchase_order_summary_to_rest( $row );
		}

		return rest_ensure_response(
			array(
				'purchase_orders' => $items,
				'total'           => (int) $result['total'],
				'pages'           => (int) $result['pages'],
				'paged'           => (int) $result['paged'],
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_purchase_order( $request ) {
		$order = SOM_Purchase_Orders::get( (int) $request['id'] );
		if ( ! $order ) {
			return new WP_Error( 'som_po_missing', __( 'Purchase order not found.', 'order-machine' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::purchase_order_to_rest( $order ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_purchase_order( $request ) {
		$id = SOM_Purchase_Orders::create( self::request_body( $request ) );
		if ( is_wp_error( $id ) ) {
			return self::error_response( $id );
		}
		$order = SOM_Purchase_Orders::get( (int) $id );
		return rest_ensure_response(
			array(
				'ok'             => true,
				'id'             => (int) $id,
				'purchase_order' => $order ? self::purchase_order_to_rest( $order ) : null,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_purchase_order( $request ) {
		$result = SOM_Purchase_Orders::update( (int) $request['id'], self::request_body( $request ) );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_po_missing' === $code ? 404 : 400 );
		}
		$order = SOM_Purchase_Orders::get( (int) $request['id'] );
		return rest_ensure_response(
			array(
				'ok'             => true,
				'purchase_order' => $order ? self::purchase_order_to_rest( $order ) : null,
			)
		);
	}

	/**
	 * In-memory preview (unsaved form fields). Body: shipping_cost, other_cost, items[].
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function preview_purchase_order( $request ) {
		$preview = SOM_Material_Costing::preview_impact( self::request_body( $request ) );
		if ( is_wp_error( $preview ) ) {
			return self::error_response( $preview );
		}
		return rest_ensure_response(
			array(
				'ok'      => true,
				'preview' => $preview,
			)
		);
	}

	/**
	 * Body: deltas map of item_id => qty, or items[] with id + quantity.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function receive_purchase_order( $request ) {
		$body   = self::request_body( $request );
		$deltas = array();
		if ( isset( $body['deltas'] ) && is_array( $body['deltas'] ) ) {
			$deltas = $body['deltas'];
		} elseif ( isset( $body['items'] ) && is_array( $body['items'] ) ) {
			foreach ( $body['items'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$item_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
				if ( $item_id < 1 ) {
					continue;
				}
				$qty = isset( $row['quantity'] ) ? $row['quantity'] : ( $row['quantity_receive'] ?? null );
				if ( null === $qty ) {
					continue;
				}
				$deltas[ $item_id ] = $qty;
			}
		}

		$result = SOM_Purchase_Orders::receive( (int) $request['id'], $deltas );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_po_missing' === $code ? 404 : 400 );
		}

		$order = SOM_Purchase_Orders::get( (int) $request['id'] );
		return rest_ensure_response(
			array(
				'ok'             => true,
				'purchase_order' => $order ? self::purchase_order_to_rest( $order ) : null,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function mark_received_purchase_order( $request ) {
		$result = SOM_Purchase_Orders::mark_received( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_po_missing' === $code ? 404 : 400 );
		}
		$order = SOM_Purchase_Orders::get( (int) $request['id'] );
		return rest_ensure_response(
			array(
				'ok'             => true,
				'purchase_order' => $order ? self::purchase_order_to_rest( $order ) : null,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function cancel_purchase_order( $request ) {
		$result = SOM_Purchase_Orders::cancel( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_po_missing' === $code ? 404 : 400 );
		}
		$order = SOM_Purchase_Orders::get( (int) $request['id'] );
		return rest_ensure_response(
			array(
				'ok'             => true,
				'purchase_order' => $order ? self::purchase_order_to_rest( $order ) : null,
			)
		);
	}

	/**
	 * GET ?workflow_template_id= or ?material_id= (one required).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_workflow_material_goals( $request ) {
		$workflow_id = (int) $request->get_param( 'workflow_template_id' );
		$material_id = (int) $request->get_param( 'material_id' );

		if ( $workflow_id > 0 ) {
			$rows = SOM_Workflow_Material_Goals::list_for_workflow( $workflow_id );
		} elseif ( $material_id > 0 ) {
			$rows = SOM_Workflow_Material_Goals::list_for_material( $material_id );
		} else {
			return new WP_Error(
				'som_goals_filter',
				__( 'Provide workflow_template_id or material_id.', 'order-machine' ),
				array( 'status' => 400 )
			);
		}

		$goals = array();
		foreach ( $rows as $row ) {
			$goals[] = self::goal_to_rest( $row );
		}

		return rest_ensure_response( array( 'goals' => $goals ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_workflow_material_goal( $request ) {
		$row = SOM_Workflow_Material_Goals::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'som_goal_missing', __( 'Material goal not found.', 'order-machine' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::goal_to_rest( $row ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upsert_workflow_material_goal( $request ) {
		$id = SOM_Workflow_Material_Goals::upsert( self::request_body( $request ) );
		if ( is_wp_error( $id ) ) {
			return self::error_response( $id );
		}
		$row = SOM_Workflow_Material_Goals::get( (int) $id );
		return rest_ensure_response(
			array(
				'ok'   => true,
				'id'   => (int) $id,
				'goal' => $row ? self::goal_to_rest( $row ) : null,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_workflow_material_goal( $request ) {
		$result = SOM_Workflow_Material_Goals::update( (int) $request['id'], self::request_body( $request ) );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_goal_missing' === $code ? 404 : 400 );
		}
		$row = SOM_Workflow_Material_Goals::get( (int) $request['id'] );
		return rest_ensure_response(
			array(
				'ok'   => true,
				'goal' => $row ? self::goal_to_rest( $row ) : null,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_workflow_material_goal( $request ) {
		$result = SOM_Workflow_Material_Goals::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_goal_missing' === $code ? 404 : 400 );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_batches( $request ) {
		$include_done = $request->get_param( 'include_done' );
		if ( is_string( $include_done ) ) {
			$include_done = in_array( strtolower( $include_done ), array( '1', 'true', 'yes' ), true );
		}

		$result = SOM_Batches::query(
			array(
				'status'         => (string) $request->get_param( 'status' ),
				'batch_group_id' => (int) $request->get_param( 'batch_group_id' ),
				'include_done'   => (bool) $include_done,
				'paged'          => max( 1, (int) $request->get_param( 'paged' ) ),
				'per_page'       => max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 50 ) ) ),
			)
		);

		$items = array();
		foreach ( $result['batches'] as $row ) {
			$items[] = self::batch_to_rest( $row, false );
		}

		return rest_ensure_response(
			array(
				'batches' => $items,
				'total'   => (int) $result['total'],
				'pages'   => (int) $result['pages'],
				'paged'   => (int) $result['paged'],
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_batch( $request ) {
		$batch = SOM_Batches::get( (int) $request['id'] );
		if ( ! $batch ) {
			return new WP_Error( 'som_batch_missing', __( 'Batch not found.', 'order-machine' ), array( 'status' => 404 ) );
		}
		$group = SOM_Batch_Groups::get( (int) $batch->batch_group_id );
		if ( $group ) {
			$batch->group_name        = $group->display_name;
			$batch->group_key         = $group->key;
			$batch->group_batch_size  = $group->batch_size;
			$batch->group_action_type = $group->action_type;
			$batch->key               = $group->key;
		}
		$batch->item_count = count( SOM_Batches::get_items( (int) $batch->id ) );
		return rest_ensure_response( self::batch_to_rest( $batch, true ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function release_batch( $request ) {
		$body   = self::request_body( $request );
		$manual = ! array_key_exists( 'manual', $body ) || (bool) $body['manual'];
		$result = SOM_Batches::release( (int) $request['id'], $manual );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_batch_missing' === $code ? 404 : 400 );
		}
		return self::get_batch( $request );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function mark_done_batch( $request ) {
		$result = SOM_Batches::mark_done( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_batch_missing' === $code ? 404 : 400 );
		}
		return self::get_batch( $request );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function retry_batch( $request ) {
		$result = SOM_Batches::retry( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			return self::error_response( $result, 'som_batch_missing' === $code ? 404 : 400 );
		}
		return self::get_batch( $request );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_batch_groups( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$groups = array();
		foreach ( SOM_Batch_Groups::list_all() as $row ) {
			$groups[] = self::batch_group_to_rest( $row );
		}
		return rest_ensure_response( array( 'batch_groups' => $groups ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_batch_group( $request ) {
		$row = SOM_Batch_Groups::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'som_batch_group_missing', __( 'Batch group not found.', 'order-machine' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::batch_group_to_rest( $row ) );
	}

	/**
	 * Compact order payload for create response (no credentials).
	 *
	 * @param object $order Order from SOM_Orders::get().
	 * @return array<string, mixed>
	 */
	private static function order_to_rest( $order ) {
		$items = array();
		if ( ! empty( $order->items ) && is_array( $order->items ) ) {
			foreach ( $order->items as $item ) {
				$items[] = array(
					'id'                   => (int) $item->id,
					'product_id'           => null !== $item->product_id && '' !== $item->product_id ? (int) $item->product_id : null,
					'product_name'         => isset( $item->product_name ) ? (string) $item->product_name : '',
					'quantity'             => (int) $item->quantity,
					'personalisation_text' => $item->personalisation_text,
					'unit_price'           => $item->unit_price,
				);
			}
		}

		return array(
			'id'                => (int) $order->id,
			'channel_slug'      => (string) $order->channel_slug,
			'external_order_id' => (string) $order->external_order_id,
			'buyer_name'        => (string) $order->buyer_name,
			'order_date'        => (string) $order->order_date,
			'current_step_id'   => $order->current_step_id ? (int) $order->current_step_id : null,
			'current_step_name' => isset( $order->current_step_name ) ? (string) $order->current_step_name : '',
			'is_complete'       => (int) $order->is_complete,
			'items'             => $items,
		);
	}

	/**
	 * @param object $row Supplier row.
	 * @return array<string, mixed>
	 */
	private static function supplier_to_rest( $row ) {
		return array(
			'id'           => (int) $row->id,
			'name'         => (string) $row->name,
			'website'      => isset( $row->website ) ? $row->website : null,
			'contact_info' => isset( $row->contact_info ) ? $row->contact_info : null,
			'notes'        => isset( $row->notes ) ? $row->notes : null,
			'created_at'   => isset( $row->created_at ) ? (string) $row->created_at : '',
			'updated_at'   => isset( $row->updated_at ) ? (string) $row->updated_at : '',
		);
	}

	/**
	 * @param object $row PO list row.
	 * @return array<string, mixed>
	 */
	private static function purchase_order_summary_to_rest( $row ) {
		return array(
			'id'            => (int) $row->id,
			'supplier_id'   => (int) $row->supplier_id,
			'supplier_name' => isset( $row->supplier_name ) ? (string) $row->supplier_name : '',
			'order_date'    => (string) $row->order_date,
			'received_date' => isset( $row->received_date ) ? $row->received_date : null,
			'status'        => (string) $row->status,
			'shipping_cost' => null !== $row->shipping_cost && '' !== $row->shipping_cost ? (float) $row->shipping_cost : 0.0,
			'other_cost'    => null !== $row->other_cost && '' !== $row->other_cost ? (float) $row->other_cost : 0.0,
			'notes'         => isset( $row->notes ) ? $row->notes : null,
		);
	}

	/**
	 * @param object $order PO detail from get().
	 * @return array<string, mixed>
	 */
	private static function purchase_order_to_rest( $order ) {
		$items = array();
		if ( ! empty( $order->items ) && is_array( $order->items ) ) {
			foreach ( $order->items as $item ) {
				$items[] = array(
					'id'                      => (int) $item->id,
					'material_id'             => (int) $item->material_id,
					'material_name'           => isset( $item->material_name ) ? (string) $item->material_name : '',
					'material_unit'           => isset( $item->material_unit ) ? (string) $item->material_unit : '',
					'quantity_ordered'        => (float) $item->quantity_ordered,
					'quantity_received'       => null !== $item->quantity_received && '' !== $item->quantity_received
						? (float) $item->quantity_received
						: 0.0,
					'item_cost'               => (float) $item->item_cost,
					'allocated_shipping_cost' => null !== $item->allocated_shipping_cost && '' !== $item->allocated_shipping_cost
						? (float) $item->allocated_shipping_cost
						: null,
					'allocated_other_cost'    => null !== $item->allocated_other_cost && '' !== $item->allocated_other_cost
						? (float) $item->allocated_other_cost
						: null,
					'landed_unit_cost'        => null !== $item->landed_unit_cost && '' !== $item->landed_unit_cost
						? (float) $item->landed_unit_cost
						: null,
				);
			}
		}

		$summary = self::purchase_order_summary_to_rest( $order );
		return array_merge(
			$summary,
			array(
				'items'             => $items,
				'lines_locked'      => ! empty( $order->lines_locked ),
				'can_receive'       => ! empty( $order->can_receive ),
				'can_mark_received' => ! empty( $order->can_mark_received ),
				'can_cancel'        => ! empty( $order->can_cancel ),
				'can_edit_lines'    => ! empty( $order->can_edit_lines ),
			)
		);
	}

	/**
	 * @param object $row Goal row.
	 * @return array<string, mixed>
	 */
	private static function goal_to_rest( $row ) {
		return array(
			'id'                        => (int) $row->id,
			'workflow_template_id'      => (int) $row->workflow_template_id,
			'material_id'               => (int) $row->material_id,
			'goal_unit_cost'            => (float) $row->goal_unit_cost,
			'warning_threshold_percent' => (float) $row->warning_threshold_percent,
			'material_name'             => isset( $row->material_name ) ? (string) $row->material_name : '',
			'material_unit'             => isset( $row->material_unit ) ? (string) $row->material_unit : '',
			'workflow_name'             => isset( $row->workflow_name ) ? (string) $row->workflow_name : '',
		);
	}

	/**
	 * @param object $row   Batch row.
	 * @param bool   $detail Include members.
	 * @return array<string, mixed>
	 */
	private static function batch_to_rest( $row, $detail = false ) {
		$data = array(
			'id'               => (int) $row->id,
			'batch_group_id'   => (int) $row->batch_group_id,
			'group_name'       => isset( $row->group_name ) ? (string) $row->group_name : '',
			'group_key'        => isset( $row->group_key ) ? (string) $row->group_key : ( isset( $row->key ) ? (string) $row->key : '' ),
			'action_type'      => isset( $row->group_action_type ) ? (string) $row->group_action_type : '',
			'batch_size'       => isset( $row->group_batch_size ) ? (int) $row->group_batch_size : null,
			'status'           => (string) $row->status,
			'item_count'       => isset( $row->item_count ) ? (int) $row->item_count : null,
			'released_manually'=> isset( $row->released_manually ) ? (int) $row->released_manually : 0,
			'released_at'      => isset( $row->released_at ) ? $row->released_at : null,
			'completed_at'     => isset( $row->completed_at ) ? $row->completed_at : null,
			'retry_count'      => isset( $row->retry_count ) ? (int) $row->retry_count : 0,
			'retry_after'      => isset( $row->retry_after ) ? $row->retry_after : null,
			'last_error'       => isset( $row->last_error ) ? $row->last_error : null,
		);

		if ( $detail ) {
			$members = array();
			foreach ( SOM_Batches::get_items_with_orders( (int) $row->id ) as $item ) {
				$members[] = array(
					'id'                => (int) $item->id,
					'order_id'          => (int) $item->order_id,
					'workflow_step_id'  => (int) $item->workflow_step_id,
					'external_order_id' => isset( $item->external_order_id ) ? (string) $item->external_order_id : '',
					'buyer_name'        => isset( $item->buyer_name ) ? (string) $item->buyer_name : '',
					'is_complete'       => isset( $item->is_complete ) ? (int) $item->is_complete : 0,
				);
			}
			$data['members'] = $members;
		}

		return $data;
	}

	/**
	 * @param object $row Batch group.
	 * @return array<string, mixed>
	 */
	private static function batch_group_to_rest( $row ) {
		return array(
			'id'           => (int) $row->id,
			'key'          => (string) $row->key,
			'display_name' => (string) $row->display_name,
			'batch_size'   => (int) $row->batch_size,
			'action_type'  => (string) $row->action_type,
		);
	}
}
