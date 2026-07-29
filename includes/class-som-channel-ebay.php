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
	 * Pull orders modified in the given UTC window (or fixtures when dummy).
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

		$creds = SOM_Channels::get_credentials( self::SLUG );
		if ( empty( $creds['access_token'] ) ) {
			return new WP_Error( 'som_ebay_orders', __( 'eBay is not connected.', 'order-machine' ) );
		}

		$settings = SOM_Settings::get();
		$base     = ( 'production' === ( $creds['environment'] ?? $settings['ebay']['environment'] ) )
			? 'https://api.ebay.com'
			: 'https://api.sandbox.ebay.com';

		$from_iso = gmdate( 'Y-m-d\TH:i:s.000\Z', strtotime( $from_utc . ' UTC' ) ?: time() );
		$to_iso   = gmdate( 'Y-m-d\TH:i:s.000\Z', strtotime( $to_utc . ' UTC' ) ?: time() );
		$filter   = 'lastmodifieddate:[' . $from_iso . '..' . $to_iso . ']';

		$orders   = array();
		$offset   = 0;
		$limit    = 50;
		$safety   = 0;

		do {
			$url = add_query_arg(
				array(
					'filter' => $filter,
					'limit'  => $limit,
					'offset' => $offset,
				),
				$base . '/sell/fulfillment/v1/order'
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 45,
					'headers' => array(
						'Authorization' => 'Bearer ' . $creds['access_token'],
						'Content-Type'  => 'application/json',
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
				$message = __( 'eBay order pull failed.', 'order-machine' );
				if ( ! empty( $body['errors'][0]['message'] ) ) {
					$message = (string) $body['errors'][0]['message'];
				}
				return new WP_Error( 'som_ebay_orders', $message );
			}

			$batch = isset( $body['orders'] ) && is_array( $body['orders'] ) ? $body['orders'] : array();
			foreach ( $batch as $raw ) {
				if ( is_array( $raw ) ) {
					$orders[] = self::normalize_order( $raw );
				}
			}

			$total  = isset( $body['total'] ) ? (int) $body['total'] : count( $batch );
			$offset += $limit;
			++$safety;
		} while ( $offset < $total && $safety < 40 );

		return $orders;
	}

	/**
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private static function load_fixture_orders() {
		$path = SOM_PLUGIN_DIR . 'tests/fixtures/ebay-orders.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'som_ebay_fixture', __( 'eBay order fixture file missing.', 'order-machine' ) );
		}

		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture
		if ( ! is_array( $data ) || empty( $data['orders'] ) || ! is_array( $data['orders'] ) ) {
			return new WP_Error( 'som_ebay_fixture', __( 'eBay order fixture is invalid.', 'order-machine' ) );
		}

		$orders = array();
		foreach ( $data['orders'] as $raw ) {
			if ( is_array( $raw ) ) {
				$orders[] = self::normalize_order( $raw );
			}
		}

		return $orders;
	}

	/**
	 * Map eBay Fulfillment order → internal shape.
	 *
	 * @param array<string, mixed> $raw API / fixture order.
	 * @return array<string, mixed>
	 */
	public static function normalize_order( array $raw ) {
		$ship_to = array();
		if ( ! empty( $raw['fulfillmentStartInstructions'][0]['shippingStep']['shipTo'] ) ) {
			$ship_to = $raw['fulfillmentStartInstructions'][0]['shippingStep']['shipTo'];
		}

		$address = array();
		if ( is_array( $ship_to ) ) {
			$contact = isset( $ship_to['contactAddress'] ) && is_array( $ship_to['contactAddress'] )
				? $ship_to['contactAddress']
				: array();
			$address = array(
				'full_name' => isset( $ship_to['fullName'] ) ? (string) $ship_to['fullName'] : '',
				'line1'     => isset( $contact['addressLine1'] ) ? (string) $contact['addressLine1'] : '',
				'line2'     => isset( $contact['addressLine2'] ) ? (string) $contact['addressLine2'] : '',
				'city'      => isset( $contact['city'] ) ? (string) $contact['city'] : '',
				'state'     => isset( $contact['stateOrProvince'] ) ? (string) $contact['stateOrProvince'] : '',
				'postcode'  => isset( $contact['postalCode'] ) ? (string) $contact['postalCode'] : '',
				'country'   => isset( $contact['countryCode'] ) ? (string) $contact['countryCode'] : '',
			);
		}

		$buyer_name = '';
		if ( ! empty( $address['full_name'] ) ) {
			$buyer_name = $address['full_name'];
		} elseif ( ! empty( $raw['buyer']['username'] ) ) {
			$buyer_name = (string) $raw['buyer']['username'];
		}

		$order_date = gmdate( 'Y-m-d H:i:s' );
		if ( ! empty( $raw['creationDate'] ) ) {
			$ts = strtotime( (string) $raw['creationDate'] );
			if ( false !== $ts ) {
				$order_date = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		$items = array();
		$lines = isset( $raw['lineItems'] ) && is_array( $raw['lineItems'] ) ? $raw['lineItems'] : array();
		foreach ( $lines as $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}
			$items[] = array(
				'external_listing_id'  => ! empty( $line['legacyItemId'] ) ? (string) $line['legacyItemId'] : '',
				'sku'                  => ! empty( $line['sku'] ) ? (string) $line['sku'] : '',
				'quantity'             => isset( $line['quantity'] ) ? (int) $line['quantity'] : 1,
				'unit_price'           => isset( $line['lineItemCost']['value'] ) ? (float) $line['lineItemCost']['value'] : null,
				'personalisation_text' => self::extract_personalisation( $line ),
			);
		}

		return array(
			'external_order_id' => isset( $raw['orderId'] ) ? (string) $raw['orderId'] : '',
			'order_date'        => $order_date,
			'buyer_name'        => $buyer_name,
			'shipping_address'  => $address,
			'raw_payload'       => $raw,
			'items'             => $items,
		);
	}

	/**
	 * Best-effort personalisation from variation aspects / properties.
	 *
	 * @param array<string, mixed> $line Line item.
	 * @return string|null
	 */
	private static function extract_personalisation( array $line ) {
		$aspects = array();
		if ( ! empty( $line['variationAspects'] ) && is_array( $line['variationAspects'] ) ) {
			$aspects = $line['variationAspects'];
		} elseif ( ! empty( $line['properties'] ) && is_array( $line['properties'] ) ) {
			$aspects = $line['properties'];
		}

		$preferred = array( 'personalisation', 'personalization', 'custom text', 'customisation', 'customization', 'name', 'bin' );
		$parts     = array();

		foreach ( $aspects as $aspect ) {
			if ( ! is_array( $aspect ) ) {
				continue;
			}
			$name  = isset( $aspect['name'] ) ? strtolower( (string) $aspect['name'] ) : '';
			$value = isset( $aspect['value'] ) ? trim( (string) $aspect['value'] ) : '';
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

		// Fallback: join all aspect values if few.
		$all = array();
		foreach ( $aspects as $aspect ) {
			if ( is_array( $aspect ) && ! empty( $aspect['value'] ) ) {
				$all[] = trim( (string) $aspect['value'] );
			}
		}
		if ( $all && count( $all ) <= 3 ) {
			return implode( ' / ', $all );
		}

		return null;
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
