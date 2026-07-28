<?php
/**
 * eBay Sell API client — OAuth and token storage (Sprint 2).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * eBay OAuth 2.0 Authorization Code flow (sandbox or production).
 */
class SOM_Channel_Ebay {

	const SLUG = 'ebay';

	/**
	 * OAuth scopes needed for orders (and inventory for later sprints).
	 *
	 * @return string[]
	 */
	public static function scopes() {
		return array(
			'https://api.ebay.com/oauth/api_scope',
			'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
			'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
			'https://api.ebay.com/oauth/api_scope/sell.inventory',
			'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
		);
	}

	/**
	 * Build the consent URL and stash CSRF state.
	 *
	 * @return string|\WP_Error Authorization URL.
	 */
	public static function get_authorize_url() {
		$settings = SOM_Settings::get();
		$ebay     = $settings['ebay'];

		if ( '' === $ebay['client_id'] || '' === $ebay['runame'] ) {
			return new WP_Error(
				'som_ebay_config',
				__( 'Save eBay Client ID and RuName before connecting.', 'order-machine' )
			);
		}

		$state = wp_generate_password( 32, false, false );
		set_transient(
			'som_oauth_ebay_' . $state,
			array(
				'created' => time(),
			),
			15 * MINUTE_IN_SECONDS
		);

		$base = ( 'production' === $ebay['environment'] )
			? 'https://auth.ebay.com/oauth2/authorize'
			: 'https://auth.sandbox.ebay.com/oauth2/authorize';

		return add_query_arg(
			array(
				'client_id'     => $ebay['client_id'],
				'response_type' => 'code',
				'redirect_uri'  => $ebay['runame'],
				'scope'         => implode( ' ', self::scopes() ),
				'state'         => $state,
			),
			$base
		);
	}

	/**
	 * Exchange authorization code for tokens and store encrypted credentials.
	 *
	 * @param string $code  Authorization code from callback.
	 * @param string $state CSRF state.
	 * @return true|\WP_Error
	 */
	public static function handle_callback( $code, $state ) {
		$code  = (string) $code;
		$state = (string) $state;

		if ( '' === $code || '' === $state ) {
			return new WP_Error( 'som_ebay_oauth', __( 'Missing OAuth code or state.', 'order-machine' ) );
		}

		$transient_key = 'som_oauth_ebay_' . $state;
		$stored        = get_transient( $transient_key );
		delete_transient( $transient_key );

		if ( false === $stored ) {
			return new WP_Error( 'som_ebay_oauth', __( 'OAuth state expired or invalid. Try Connect again.', 'order-machine' ) );
		}

		$settings = SOM_Settings::get();
		$ebay     = $settings['ebay'];

		if ( '' === $ebay['client_id'] || '' === $ebay['client_secret'] || '' === $ebay['runame'] ) {
			return new WP_Error( 'som_ebay_config', __( 'eBay app credentials are incomplete.', 'order-machine' ) );
		}

		$token_url = ( 'production' === $ebay['environment'] )
			? 'https://api.ebay.com/identity/v1/oauth2/token'
			: 'https://api.sandbox.ebay.com/identity/v1/oauth2/token';

		$response = wp_remote_post(
			$token_url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Authorization' => 'Basic ' . base64_encode( $ebay['client_id'] . ':' . $ebay['client_secret'] ),
				),
				'body'    => array(
					'grant_type'   => 'authorization_code',
					'code'         => $code,
					'redirect_uri' => $ebay['runame'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code_http = (int) wp_remote_retrieve_response_code( $response );

		if ( $code_http < 200 || $code_http >= 300 || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] ) ? (string) $body['error_description'] : __( 'eBay token exchange failed.', 'order-machine' );
			return new WP_Error( 'som_ebay_token', $message );
		}

		$credentials = self::normalize_token_response( $body );
		if ( ! SOM_Channels::save_credentials( self::SLUG, $credentials ) ) {
			return new WP_Error( 'som_ebay_store', __( 'Could not store eBay credentials.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Proactively refresh the user access token when near expiry.
	 *
	 * @param bool $force Refresh even if not near expiry.
	 * @return true|\WP_Error
	 */
	public static function refresh_token_if_needed( $force = false ) {
		$creds = SOM_Channels::get_credentials( self::SLUG );
		if ( empty( $creds['refresh_token'] ) ) {
			return new WP_Error( 'som_ebay_refresh', __( 'No eBay refresh token stored.', 'order-machine' ) );
		}

		if ( ! $force && ! self::is_near_expiry( $creds ) ) {
			return true;
		}

		$settings = SOM_Settings::get();
		$ebay     = $settings['ebay'];

		if ( '' === $ebay['client_id'] || '' === $ebay['client_secret'] ) {
			return new WP_Error( 'som_ebay_config', __( 'eBay app credentials are incomplete.', 'order-machine' ) );
		}

		$token_url = ( 'production' === $ebay['environment'] )
			? 'https://api.ebay.com/identity/v1/oauth2/token'
			: 'https://api.sandbox.ebay.com/identity/v1/oauth2/token';

		$response = wp_remote_post(
			$token_url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Authorization' => 'Basic ' . base64_encode( $ebay['client_id'] . ':' . $ebay['client_secret'] ),
				),
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $creds['refresh_token'],
					'scope'         => implode( ' ', self::scopes() ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$code_http = (int) wp_remote_retrieve_response_code( $response );

		if ( $code_http < 200 || $code_http >= 300 || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] ) ? (string) $body['error_description'] : __( 'eBay token refresh failed.', 'order-machine' );
			return new WP_Error( 'som_ebay_refresh', $message );
		}

		// Keep existing refresh_token if response omits a new one.
		if ( empty( $body['refresh_token'] ) && ! empty( $creds['refresh_token'] ) ) {
			$body['refresh_token'] = $creds['refresh_token'];
		}

		$credentials = self::normalize_token_response( $body );
		if ( ! empty( $creds['environment'] ) ) {
			$credentials['environment'] = $creds['environment'];
		} else {
			$credentials['environment'] = $ebay['environment'];
		}

		if ( ! SOM_Channels::save_credentials( self::SLUG, $credentials ) ) {
			return new WP_Error( 'som_ebay_store', __( 'Could not store refreshed eBay credentials.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $body Token endpoint JSON.
	 * @return array<string, mixed>
	 */
	private static function normalize_token_response( array $body ) {
		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 7200;
		$settings   = SOM_Settings::get();

		return array(
			'access_token'  => (string) $body['access_token'],
			'refresh_token' => isset( $body['refresh_token'] ) ? (string) $body['refresh_token'] : '',
			'token_type'    => isset( $body['token_type'] ) ? (string) $body['token_type'] : 'Bearer',
			'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + max( 60, $expires_in ) ),
			'expires_in'    => $expires_in,
			'environment'   => $settings['ebay']['environment'],
			'dummy'         => ! empty( $body['dummy'] ),
		);
	}

	/**
	 * @param array<string, mixed> $creds Stored credentials.
	 * @return bool
	 */
	private static function is_near_expiry( array $creds ) {
		if ( empty( $creds['expires_at'] ) ) {
			return true;
		}

		$expires = strtotime( $creds['expires_at'] . ' UTC' );
		if ( false === $expires ) {
			return true;
		}

		// Refresh when fewer than 15 minutes remain.
		return ( $expires - time() ) < 15 * MINUTE_IN_SECONDS;
	}
}
