<?php
/**
 * Internal REST API (workflow callbacks for n8n / external automations).
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
}
