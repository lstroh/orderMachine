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
	 * Pull shop receipts in the given UTC window (or fixtures when dummy).
	 *
	 * @param string $from_utc Y-m-d H:i:s UTC.
	 * @param string $to_utc   Y-m-d H:i:s UTC.
	 * @return array<int, array<string, mixed>>|\WP_Error Normalized orders.
	 */
	public static function fetch_orders( $from_utc, $to_utc ) {
		if ( SOM_Channels::is_dummy( self::SLUG ) ) {
			return self::load_fixture_orders();
		}

		$refresh = self::refresh_token_if_needed( false );
		if ( is_wp_error( $refresh ) ) {
			return $refresh;
		}

		$creds    = SOM_Channels::get_credentials( self::SLUG );
		$settings = SOM_Settings::get();

		if ( empty( $creds['access_token'] ) ) {
			return new WP_Error( 'som_etsy_orders', __( 'Etsy is not connected.', 'order-machine' ) );
		}
		if ( empty( $creds['shop_id'] ) ) {
			return new WP_Error( 'som_etsy_orders', __( 'Etsy shop ID is missing. Reconnect Etsy.', 'order-machine' ) );
		}
		if ( '' === $settings['etsy']['client_id'] ) {
			return new WP_Error( 'som_etsy_config', __( 'Etsy API keystring is missing.', 'order-machine' ) );
		}

		$min_created = strtotime( $from_utc . ' UTC' );
		$max_created = strtotime( $to_utc . ' UTC' );
		if ( false === $min_created ) {
			$min_created = time() - ( 7 * DAY_IN_SECONDS );
		}
		if ( false === $max_created ) {
			$max_created = time();
		}

		$orders  = array();
		$offset  = 0;
		$limit   = 25;
		$safety  = 0;

		do {
			$url = add_query_arg(
				array(
					'min_created' => $min_created,
					'max_created' => $max_created,
					'limit'       => $limit,
					'offset'      => $offset,
				),
				'https://api.etsy.com/v3/application/shops/' . rawurlencode( (string) $creds['shop_id'] ) . '/receipts'
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 45,
					'headers' => array(
						'Authorization' => 'Bearer ' . $creds['access_token'],
						'x-api-key'     => $settings['etsy']['client_id'],
						'Accept'        => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
				$message = __( 'Etsy receipt pull failed.', 'order-machine' );
				if ( ! empty( $body['error'] ) ) {
					$message = (string) $body['error'];
				}
				return new WP_Error( 'som_etsy_orders', $message );
			}

			$batch = isset( $body['results'] ) && is_array( $body['results'] ) ? $body['results'] : array();
			foreach ( $batch as $raw ) {
				if ( is_array( $raw ) ) {
					$orders[] = self::normalize_order( $raw );
				}
			}

			$count   = isset( $body['count'] ) ? (int) $body['count'] : count( $batch );
			$offset += $limit;
			++$safety;
		} while ( $offset < $count && $safety < 40 );

		return $orders;
	}

	/**
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private static function load_fixture_orders() {
		$path = SOM_PLUGIN_DIR . 'tests/fixtures/etsy-orders.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'som_etsy_fixture', __( 'Etsy order fixture file missing.', 'order-machine' ) );
		}

		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture
		if ( ! is_array( $data ) || empty( $data['results'] ) || ! is_array( $data['results'] ) ) {
			return new WP_Error( 'som_etsy_fixture', __( 'Etsy order fixture is invalid.', 'order-machine' ) );
		}

		$orders = array();
		foreach ( $data['results'] as $raw ) {
			if ( is_array( $raw ) ) {
				$orders[] = self::normalize_order( $raw );
			}
		}

		return $orders;
	}

	/**
	 * Map Etsy receipt → internal shape.
	 *
	 * @param array<string, mixed> $raw API / fixture receipt.
	 * @return array<string, mixed>
	 */
	public static function normalize_order( array $raw ) {
		$address = array(
			'full_name' => isset( $raw['name'] ) ? (string) $raw['name'] : '',
			'line1'     => isset( $raw['first_line'] ) ? (string) $raw['first_line'] : '',
			'line2'     => isset( $raw['second_line'] ) ? (string) $raw['second_line'] : '',
			'city'      => isset( $raw['city'] ) ? (string) $raw['city'] : '',
			'state'     => isset( $raw['state'] ) ? (string) $raw['state'] : '',
			'postcode'  => isset( $raw['zip'] ) ? (string) $raw['zip'] : '',
			'country'   => isset( $raw['country_iso'] ) ? (string) $raw['country_iso'] : '',
		);

		$order_date = gmdate( 'Y-m-d H:i:s' );
		if ( ! empty( $raw['created_timestamp'] ) ) {
			$order_date = gmdate( 'Y-m-d H:i:s', (int) $raw['created_timestamp'] );
		}

		$items        = array();
		$transactions = isset( $raw['transactions'] ) && is_array( $raw['transactions'] ) ? $raw['transactions'] : array();
		foreach ( $transactions as $tx ) {
			if ( ! is_array( $tx ) ) {
				continue;
			}
			$unit_price = null;
			if ( isset( $tx['price']['amount'], $tx['price']['divisor'] ) && (int) $tx['price']['divisor'] > 0 ) {
				$unit_price = ( (float) $tx['price']['amount'] ) / ( (float) $tx['price']['divisor'] );
			}

			$items[] = array(
				'external_listing_id'  => ! empty( $tx['listing_id'] ) ? (string) $tx['listing_id'] : '',
				'sku'                  => '',
				'quantity'             => isset( $tx['quantity'] ) ? (int) $tx['quantity'] : 1,
				'unit_price'           => $unit_price,
				'personalisation_text' => self::extract_personalisation( $tx ),
			);
		}

		return array(
			'external_order_id' => isset( $raw['receipt_id'] ) ? (string) $raw['receipt_id'] : '',
			'order_date'        => $order_date,
			'buyer_name'        => $address['full_name'],
			'shipping_address'  => $address,
			'raw_payload'       => $raw,
			'items'             => $items,
		);
	}

	/**
	 * Best-effort personalisation from transaction variations.
	 *
	 * @param array<string, mixed> $tx Transaction.
	 * @return string|null
	 */
	private static function extract_personalisation( array $tx ) {
		$variations = isset( $tx['variations'] ) && is_array( $tx['variations'] ) ? $tx['variations'] : array();
		$preferred  = array( 'personalisation', 'personalization', 'custom text', 'customisation', 'customization', 'name', 'bin' );
		$parts      = array();

		foreach ( $variations as $variation ) {
			if ( ! is_array( $variation ) ) {
				continue;
			}
			$name  = isset( $variation['formatted_name'] ) ? strtolower( (string) $variation['formatted_name'] ) : '';
			$value = isset( $variation['formatted_value'] ) ? trim( (string) $variation['formatted_value'] ) : '';
			if ( '' === $value ) {
				continue;
			}
			foreach ( $preferred as $needle ) {
				if ( false !== strpos( $name, $needle ) ) {
					$parts[] = $value;
					break;
				}
			}
		}

		if ( $parts ) {
			return implode( ' / ', $parts );
		}

		$all = array();
		foreach ( $variations as $variation ) {
			if ( is_array( $variation ) && ! empty( $variation['formatted_value'] ) ) {
				$all[] = trim( (string) $variation['formatted_value'] );
			}
		}
		if ( $all && count( $all ) <= 3 ) {
			return implode( ' / ', $all );
		}

		return null;
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
