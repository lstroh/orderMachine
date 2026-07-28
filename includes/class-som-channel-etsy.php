<?php
/**
 * Etsy Open API v3 client — OAuth PKCE and token storage (Sprint 2).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Etsy OAuth 2.0 Authorization Code + PKCE flow.
 */
class SOM_Channel_Etsy {

	const SLUG = 'etsy';

	/**
	 * Scopes for receipts now; listings for later sprints.
	 *
	 * @return string[]
	 */
	public static function scopes() {
		return array(
			'transactions_r',
			'shops_r',
			'listings_r',
			'listings_w',
		);
	}

	/**
	 * Build consent URL; store PKCE verifier + state in a transient.
	 *
	 * @return string|\WP_Error
	 */
	public static function get_authorize_url() {
		$settings = SOM_Settings::get();
		$etsy     = $settings['etsy'];

		if ( '' === $etsy['client_id'] ) {
			return new WP_Error(
				'som_etsy_config',
				__( 'Save Etsy API keystring (client ID) before connecting.', 'order-machine' )
			);
		}

		$state         = wp_generate_password( 32, false, false );
		$code_verifier = self::generate_code_verifier();
		$challenge     = self::code_challenge_s256( $code_verifier );

		set_transient(
			'som_oauth_etsy_' . $state,
			array(
				'code_verifier' => $code_verifier,
				'created'       => time(),
			),
			15 * MINUTE_IN_SECONDS
		);

		return add_query_arg(
			array(
				'response_type'         => 'code',
				'client_id'             => $etsy['client_id'],
				'redirect_uri'          => SOM_Settings::oauth_callback_url( self::SLUG ),
				'scope'                 => implode( ' ', self::scopes() ),
				'state'                 => $state,
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
			),
			'https://www.etsy.com/oauth/connect'
		);
	}

	/**
	 * Exchange code (+ PKCE verifier) for tokens and store encrypted credentials.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state CSRF state.
	 * @return true|\WP_Error
	 */
	public static function handle_callback( $code, $state ) {
		$code  = (string) $code;
		$state = (string) $state;

		if ( '' === $code || '' === $state ) {
			return new WP_Error( 'som_etsy_oauth', __( 'Missing OAuth code or state.', 'order-machine' ) );
		}

		$transient_key = 'som_oauth_etsy_' . $state;
		$stored        = get_transient( $transient_key );
		delete_transient( $transient_key );

		if ( ! is_array( $stored ) || empty( $stored['code_verifier'] ) ) {
			return new WP_Error( 'som_etsy_oauth', __( 'OAuth state expired or invalid. Try Connect again.', 'order-machine' ) );
		}

		$settings = SOM_Settings::get();
		$etsy     = $settings['etsy'];

		if ( '' === $etsy['client_id'] ) {
			return new WP_Error( 'som_etsy_config', __( 'Etsy app credentials are incomplete.', 'order-machine' ) );
		}

		$response = wp_remote_post(
			'https://api.etsy.com/v3/public/oauth/token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type'    => 'authorization_code',
					'client_id'     => $etsy['client_id'],
					'redirect_uri'  => SOM_Settings::oauth_callback_url( self::SLUG ),
					'code'          => $code,
					'code_verifier' => $stored['code_verifier'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$code_http = (int) wp_remote_retrieve_response_code( $response );

		if ( $code_http < 200 || $code_http >= 300 || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$message = isset( $body['error'] ) ? (string) $body['error'] : __( 'Etsy token exchange failed.', 'order-machine' );
			if ( ! empty( $body['error_description'] ) ) {
				$message = (string) $body['error_description'];
			}
			return new WP_Error( 'som_etsy_token', $message );
		}

		$credentials = self::normalize_token_response( $body );

		$shop_id = self::fetch_shop_id( $credentials['access_token'], $etsy['client_id'] );
		if ( ! is_wp_error( $shop_id ) && $shop_id ) {
			$credentials['shop_id'] = $shop_id;
		}

		if ( ! SOM_Channels::save_credentials( self::SLUG, $credentials ) ) {
			return new WP_Error( 'som_etsy_store', __( 'Could not store Etsy credentials.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Refresh access token. Etsy rotates refresh tokens — always persist the new one.
	 *
	 * @param bool $force Refresh even if not near expiry.
	 * @return true|\WP_Error
	 */
	public static function refresh_token_if_needed( $force = false ) {
		$creds = SOM_Channels::get_credentials( self::SLUG );
		if ( empty( $creds['refresh_token'] ) ) {
			return new WP_Error( 'som_etsy_refresh', __( 'No Etsy refresh token stored.', 'order-machine' ) );
		}

		if ( ! $force && ! self::is_near_expiry( $creds ) ) {
			return true;
		}

		$settings = SOM_Settings::get();
		$etsy     = $settings['etsy'];

		if ( '' === $etsy['client_id'] ) {
			return new WP_Error( 'som_etsy_config', __( 'Etsy app credentials are incomplete.', 'order-machine' ) );
		}

		$response = wp_remote_post(
			'https://api.etsy.com/v3/public/oauth/token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => $etsy['client_id'],
					'refresh_token' => $creds['refresh_token'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$code_http = (int) wp_remote_retrieve_response_code( $response );

		if ( $code_http < 200 || $code_http >= 300 || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] ) ? (string) $body['error_description'] : __( 'Etsy token refresh failed.', 'order-machine' );
			return new WP_Error( 'som_etsy_refresh', $message );
		}

		$credentials = self::normalize_token_response( $body );
		if ( ! empty( $creds['shop_id'] ) ) {
			$credentials['shop_id'] = $creds['shop_id'];
		}

		if ( ! SOM_Channels::save_credentials( self::SLUG, $credentials ) ) {
			return new WP_Error( 'som_etsy_store', __( 'Could not store refreshed Etsy credentials.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Best-effort shop id lookup after connect.
	 *
	 * @param string $access_token User token.
	 * @param string $api_key      Etsy keystring.
	 * @return string|\WP_Error Empty string if not found.
	 */
	private static function fetch_shop_id( $access_token, $api_key ) {
		$response = wp_remote_get(
			'https://api.etsy.com/v3/application/users/me/shops',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'x-api-key'     => $api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return '';
		}

		if ( ! empty( $body['shop_id'] ) ) {
			return (string) $body['shop_id'];
		}

		if ( ! empty( $body['results'][0]['shop_id'] ) ) {
			return (string) $body['results'][0]['shop_id'];
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $body Token JSON.
	 * @return array<string, mixed>
	 */
	private static function normalize_token_response( array $body ) {
		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;

		return array(
			'access_token'  => (string) $body['access_token'],
			'refresh_token' => isset( $body['refresh_token'] ) ? (string) $body['refresh_token'] : '',
			'token_type'    => isset( $body['token_type'] ) ? (string) $body['token_type'] : 'Bearer',
			'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + max( 60, $expires_in ) ),
			'expires_in'    => $expires_in,
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

		return ( $expires - time() ) < 10 * MINUTE_IN_SECONDS;
	}

	/**
	 * @return string
	 */
	private static function generate_code_verifier() {
		$bytes = random_bytes( 32 );
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	/**
	 * @param string $verifier Code verifier.
	 * @return string
	 */
	private static function code_challenge_s256( $verifier ) {
		$hash = hash( 'sha256', $verifier, true );
		return rtrim( strtr( base64_encode( $hash ), '+/', '-_' ), '=' );
	}
}
