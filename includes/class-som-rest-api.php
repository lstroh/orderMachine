<?php
/**
 * Internal REST API (external order create, advance-step, workflow callbacks).
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
	 * POST /som/v1/orders — create order (default channel: external).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_order( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) ) {
			$body = array();
		}

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
		$order_id = (int) $request['id'];
		$result   = SOM_Workflow_Engine::mark_done( $order_id );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		$order = SOM_Orders::get( $order_id );
		return rest_ensure_response(
			array(
				'ok'                => true,
				'order_id'          => $order_id,
				'current_step_id'   => $order ? (int) $order->current_step_id : 0,
				'current_step_name' => $order ? (string) $order->current_step_name : '',
				'is_complete'       => $order ? (int) $order->is_complete : 0,
			)
		);
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
			$result->add_data( array( 'status' => 400 ) );
			return $result;
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
}
