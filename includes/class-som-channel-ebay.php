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
			'https://api.ebay.com/oauth/api_scope/sell.finances',
		);
	}

	/**
	 * True when a live eBay connection predates the sell.finances scope (must reconnect).
	 *
	 * @return bool
	 */
	public static function needs_finances_reconnect() {
		if ( ! SOM_Channels::is_connected( self::SLUG ) || SOM_Channels::is_dummy( self::SLUG ) ) {
			return false;
		}
		$creds = SOM_Channels::get_credentials( self::SLUG );
		return empty( $creds['finances_scope'] );
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
		// Refresh does not expand scopes — preserve finances flag from prior consent.
		$credentials['finances_scope'] = ! empty( $creds['finances_scope'] );
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
	 * Pull platform fee lines from Finances API (or fixtures when dummy).
	 *
	 * Normalized entries: kind order|ignore, external_entry_id, external_order_id, fee_type, amount, currency, raw.
	 *
	 * @param string $from_utc Y-m-d H:i:s UTC.
	 * @param string $to_utc   Y-m-d H:i:s UTC.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function fetch_platform_fees( $from_utc, $to_utc ) {
		if ( SOM_Channels::is_dummy( self::SLUG ) ) {
			return self::load_fixture_platform_fees();
		}

		$refresh = self::refresh_token_if_needed( false );
		if ( is_wp_error( $refresh ) ) {
			return $refresh;
		}

		$creds = SOM_Channels::get_credentials( self::SLUG );
		if ( empty( $creds['access_token'] ) ) {
			return new WP_Error( 'som_ebay_fees', __( 'eBay is not connected.', 'order-machine' ) );
		}
		if ( empty( $creds['finances_scope'] ) ) {
			return new WP_Error(
				'som_ebay_fees_scope',
				__( 'Reconnect eBay to grant the Finances (sell.finances) scope before syncing fees.', 'order-machine' )
			);
		}

		$base     = self::finances_api_base( $creds );
		$token    = (string) $creds['access_token'];
		$from_iso = gmdate( 'Y-m-d\TH:i:s.000\Z', strtotime( $from_utc . ' UTC' ) ?: time() );
		$to_iso   = gmdate( 'Y-m-d\TH:i:s.000\Z', strtotime( $to_utc . ' UTC' ) ?: time() );
		$filter   = 'transactionDate:[' . $from_iso . '..' . $to_iso . ']';

		$entries = array();
		$offset  = 0;
		$limit   = 100;
		$safety  = 0;

		do {
			$url = add_query_arg(
				array(
					'filter' => $filter,
					'limit'  => $limit,
					'offset' => $offset,
				),
				$base . '/sell/finances/v1/transaction'
			);

			$body = self::api_get_json( $url, $token );
			if ( is_wp_error( $body ) ) {
				return $body;
			}

			$batch = isset( $body['transactions'] ) && is_array( $body['transactions'] ) ? $body['transactions'] : array();
			foreach ( $batch as $raw ) {
				if ( is_array( $raw ) ) {
					foreach ( self::normalize_fee_transactions( $raw ) as $entry ) {
						$entries[] = $entry;
					}
				}
			}

			$total   = isset( $body['total'] ) ? (int) $body['total'] : count( $batch );
			$offset += $limit;
			++$safety;
		} while ( $offset < $total && $safety < 40 );

		return $entries;
	}

	/**
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private static function load_fixture_platform_fees() {
		$path = SOM_PLUGIN_DIR . 'tests/fixtures/ebay-platform-fees.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'som_ebay_fee_fixture', __( 'eBay platform fee fixture file missing.', 'order-machine' ) );
		}

		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture
		if ( ! is_array( $data ) || empty( $data['transactions'] ) || ! is_array( $data['transactions'] ) ) {
			return new WP_Error( 'som_ebay_fee_fixture', __( 'eBay platform fee fixture is invalid.', 'order-machine' ) );
		}

		$entries = array();
		foreach ( $data['transactions'] as $raw ) {
			if ( is_array( $raw ) ) {
				foreach ( self::normalize_fee_transactions( $raw ) as $entry ) {
					$entries[] = $entry;
				}
			}
		}

		return $entries;
	}

	/**
	 * Extract includable fee lines from one Finances transaction; other types → ignore.
	 *
	 * @param array<string, mixed> $txn Raw transaction.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_fee_transactions( array $txn ) {
		$allow = array(
			'FINAL_VALUE_FEE'                 => true,
			'FINAL_VALUE_FEE_FIXED_PER_ORDER' => true,
			'INTERNATIONAL_FEE'               => true,
			'REGULATORY_OPERATING_FEE'        => true,
			'AD_FEE'                          => true,
			'PROMOTED_LISTINGS_FEE'           => true,
		);

		$type = isset( $txn['transactionType'] ) ? strtoupper( (string) $txn['transactionType'] ) : '';
		$out  = array();

		if ( 'SALE' === $type ) {
			$order_id = isset( $txn['orderId'] ) ? (string) $txn['orderId'] : '';
			$txn_id   = isset( $txn['transactionId'] ) ? (string) $txn['transactionId'] : '';
			$lines    = isset( $txn['orderLineItems'] ) && is_array( $txn['orderLineItems'] ) ? $txn['orderLineItems'] : array();

			foreach ( $lines as $line ) {
				if ( ! is_array( $line ) ) {
					continue;
				}
				$line_id = isset( $line['lineItemId'] ) ? (string) $line['lineItemId'] : '0';
				$fees    = isset( $line['marketplaceFees'] ) && is_array( $line['marketplaceFees'] ) ? $line['marketplaceFees'] : array();
				foreach ( $fees as $fee ) {
					if ( ! is_array( $fee ) ) {
						continue;
					}
					$fee_type = isset( $fee['feeType'] ) ? strtoupper( (string) $fee['feeType'] ) : '';
					if ( empty( $allow[ $fee_type ] ) ) {
						$out[] = array(
							'kind'              => 'ignore',
							'external_entry_id' => $txn_id . ':' . $line_id . ':' . $fee_type,
							'raw'               => $fee,
						);
						continue;
					}
					$amount   = isset( $fee['amount']['value'] ) ? (float) $fee['amount']['value'] : 0.0;
					$currency = isset( $fee['amount']['currency'] ) ? (string) $fee['amount']['currency'] : 'GBP';
					$out[]    = array(
						'kind'               => '' !== $order_id ? 'order' : 'ignore',
						'external_entry_id'  => $txn_id . ':' . $line_id . ':' . $fee_type,
						'external_order_id'  => $order_id,
						'fee_type'           => strtolower( $fee_type ),
						'amount'             => $amount,
						'currency'           => $currency,
						'raw'                => $fee,
					);
				}
			}

			return $out;
		}

		if ( 'NON_SALE_CHARGE' === $type ) {
			$fee_type = isset( $txn['feeType'] ) ? strtoupper( (string) $txn['feeType'] ) : '';
			$txn_id   = isset( $txn['transactionId'] ) ? (string) $txn['transactionId'] : '';
			if ( empty( $allow[ $fee_type ] ) ) {
				return array(
					array(
						'kind'              => 'ignore',
						'external_entry_id' => $txn_id ?: ( 'nsc:' . md5( wp_json_encode( $txn ) ) ),
						'raw'               => $txn,
					),
				);
			}
			$amount   = isset( $txn['amount']['value'] ) ? (float) $txn['amount']['value'] : 0.0;
			$currency = isset( $txn['amount']['currency'] ) ? (string) $txn['amount']['currency'] : 'GBP';
			$order_id = isset( $txn['orderId'] ) ? (string) $txn['orderId'] : '';
			return array(
				array(
					'kind'              => '' !== $order_id ? 'order' : 'ignore',
					'external_entry_id' => $txn_id . ':' . $fee_type,
					'external_order_id' => $order_id,
					'fee_type'          => strtolower( $fee_type ),
					'amount'            => $amount,
					'currency'          => $currency,
					'raw'               => $txn,
				),
			);
		}

		$txn_id = isset( $txn['transactionId'] ) ? (string) $txn['transactionId'] : ( 'txn:' . md5( wp_json_encode( $txn ) ) );
		return array(
			array(
				'kind'              => 'ignore',
				'external_entry_id' => $txn_id,
				'raw'               => $txn,
			),
		);
	}

	/**
	 * Finances API host (apiz.*).
	 *
	 * @param array<string, mixed> $creds Credentials.
	 * @return string
	 */
	private static function finances_api_base( array $creds ) {
		$settings = SOM_Settings::get();
		$env      = $creds['environment'] ?? $settings['ebay']['environment'];
		return ( 'production' === $env ) ? 'https://apiz.ebay.com' : 'https://apiz.sandbox.ebay.com';
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
	 * Fetch listing inventory / offer data (or fixture when dummy).
	 *
	 * @param array<string, mixed> $hint external_listing_id, inventory, product_sku.
	 * @return array<string, mixed>|WP_Error Normalized listing payload.
	 */
	public static function fetch_listing( array $hint ) {
		$external_id = isset( $hint['external_listing_id'] ) ? (string) $hint['external_listing_id'] : '';

		if ( SOM_Channels::is_dummy( self::SLUG ) ) {
			return self::load_fixture_listing( $external_id );
		}

		$refresh = self::refresh_token_if_needed( false );
		if ( is_wp_error( $refresh ) ) {
			return $refresh;
		}

		$creds = SOM_Channels::get_credentials( self::SLUG );
		if ( empty( $creds['access_token'] ) ) {
			return new WP_Error( 'som_ebay_listing', __( 'eBay is not connected.', 'order-machine' ) );
		}

		$inventory = isset( $hint['inventory'] ) && is_array( $hint['inventory'] ) ? $hint['inventory'] : array();
		$skus      = self::skus_from_hint( $hint );

		if ( empty( $skus ) ) {
			return new WP_Error(
				'som_ebay_sku',
				__( 'eBay Inventory API needs a SKU. Set a primary SKU or variation SKUs on this listing.', 'order-machine' )
			);
		}

		$base    = self::api_base( $creds );
		$token   = (string) $creds['access_token'];
		$variations = array();
		$title      = '';
		$description = '';
		$price       = 0.0;
		$total_qty   = 0;

		foreach ( $skus as $sku ) {
			$item = self::api_get_json( $base . '/sell/inventory/v1/inventory_item/' . rawurlencode( $sku ), $token );
			if ( is_wp_error( $item ) ) {
				return $item;
			}

			$qty = 0;
			if ( isset( $item['availability']['shipToLocationAvailability']['quantity'] ) ) {
				$qty = (int) $item['availability']['shipToLocationAvailability']['quantity'];
			}
			$total_qty += $qty;

			$options = array();
			if ( ! empty( $item['product']['aspects'] ) && is_array( $item['product']['aspects'] ) ) {
				foreach ( $item['product']['aspects'] as $aspect_name => $values ) {
					if ( is_array( $values ) && isset( $values[0] ) ) {
						$options[ (string) $aspect_name ] = (string) $values[0];
					} elseif ( is_string( $values ) ) {
						$options[ (string) $aspect_name ] = $values;
					}
				}
			}

			if ( '' === $title && ! empty( $item['product']['title'] ) ) {
				$title = (string) $item['product']['title'];
			}
			if ( '' === $description && ! empty( $item['product']['description'] ) ) {
				$description = (string) $item['product']['description'];
			}

			$variations[] = array(
				'sku'      => $sku,
				'quantity' => $qty,
				'options'  => $options,
			);

			$offer_price = self::fetch_offer_price( $base, $token, $sku );
			if ( null !== $offer_price ) {
				$price = $offer_price;
				$variations[ count( $variations ) - 1 ]['price'] = $offer_price;
			}
		}

		$mode = ( count( $variations ) > 1 || ( ! empty( $inventory['mode'] ) && 'variations' === $inventory['mode'] ) )
			? 'variations'
			: 'flat';

		return array(
			'external_listing_id' => $external_id,
			'title'               => $title,
			'description'         => $description,
			'price'               => $price,
			'quantity_available'  => $total_qty,
			'inventory'           => array(
				'mode'       => $mode,
				'sku'        => $skus[0],
				'variations' => 'variations' === $mode ? $variations : array(),
			),
		);
	}

	/**
	 * Push price / quantity / description to eBay Inventory + Offer APIs.
	 *
	 * @param array<string, mixed> $payload Normalized listing payload.
	 * @return true|WP_Error
	 */
	public static function push_listing( array $payload ) {
		if ( SOM_Channels::is_dummy( self::SLUG ) ) {
			return self::store_dummy_push( $payload );
		}

		$refresh = self::refresh_token_if_needed( false );
		if ( is_wp_error( $refresh ) ) {
			return $refresh;
		}

		$creds = SOM_Channels::get_credentials( self::SLUG );
		if ( empty( $creds['access_token'] ) ) {
			return new WP_Error( 'som_ebay_listing', __( 'eBay is not connected.', 'order-machine' ) );
		}

		$inventory = isset( $payload['inventory'] ) && is_array( $payload['inventory'] ) ? $payload['inventory'] : array();
		$mode      = isset( $inventory['mode'] ) ? (string) $inventory['mode'] : 'flat';
		$base      = self::api_base( $creds );
		$token     = (string) $creds['access_token'];

		if ( 'variations' === $mode && ! empty( $inventory['variations'] ) && is_array( $inventory['variations'] ) ) {
			foreach ( $inventory['variations'] as $row ) {
				if ( ! is_array( $row ) || empty( $row['sku'] ) ) {
					continue;
				}
				$sku  = (string) $row['sku'];
				$qty  = isset( $row['quantity'] ) ? (int) $row['quantity'] : 0;
				$put  = self::put_inventory_quantity( $base, $token, $sku, $qty, $payload );
				if ( is_wp_error( $put ) ) {
					return $put;
				}
				$var_price = isset( $row['price'] ) ? (float) $row['price'] : (float) $payload['price'];
				$offer     = self::update_offer_price( $base, $token, $sku, $var_price );
				if ( is_wp_error( $offer ) ) {
					return $offer;
				}
			}
			return true;
		}

		$sku = ! empty( $inventory['sku'] ) ? (string) $inventory['sku'] : '';
		if ( '' === $sku && ! empty( $payload['product_sku'] ) ) {
			$sku = (string) $payload['product_sku'];
		}
		if ( '' === $sku ) {
			$sku = (string) $payload['external_listing_id'];
		}
		if ( '' === $sku ) {
			return new WP_Error( 'som_ebay_sku', __( 'eBay push needs a SKU on this listing.', 'order-machine' ) );
		}

		$qty = isset( $payload['quantity_available'] ) ? (int) $payload['quantity_available'] : 0;
		$put = self::put_inventory_quantity( $base, $token, $sku, $qty, $payload );
		if ( is_wp_error( $put ) ) {
			return $put;
		}

		return self::update_offer_price( $base, $token, $sku, (float) $payload['price'] );
	}

	/**
	 * @param array<string, mixed> $hint Hint with inventory / product_sku.
	 * @return string[]
	 */
	private static function skus_from_hint( array $hint ) {
		$skus      = array();
		$inventory = isset( $hint['inventory'] ) && is_array( $hint['inventory'] ) ? $hint['inventory'] : array();

		if ( ! empty( $inventory['variations'] ) && is_array( $inventory['variations'] ) ) {
			foreach ( $inventory['variations'] as $row ) {
				if ( is_array( $row ) && ! empty( $row['sku'] ) ) {
					$skus[] = (string) $row['sku'];
				}
			}
		}
		if ( empty( $skus ) && ! empty( $inventory['sku'] ) ) {
			$skus[] = (string) $inventory['sku'];
		}
		if ( empty( $skus ) && ! empty( $hint['product_sku'] ) ) {
			$skus[] = (string) $hint['product_sku'];
		}
		if ( empty( $skus ) && ! empty( $hint['external_listing_id'] ) ) {
			// Seed maps SKU as external_listing_id for order matching.
			$skus[] = (string) $hint['external_listing_id'];
		}

		return array_values( array_unique( array_filter( $skus ) ) );
	}

	/**
	 * @param array<string, mixed> $creds Credentials.
	 * @return string
	 */
	private static function api_base( array $creds ) {
		$settings = SOM_Settings::get();
		$env      = $creds['environment'] ?? $settings['ebay']['environment'];
		return ( 'production' === $env ) ? 'https://api.ebay.com' : 'https://api.sandbox.ebay.com';
	}

	/**
	 * @param string $url   Absolute URL.
	 * @param string $token Access token.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function api_get_json( $url, $token ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization'     => 'Bearer ' . $token,
					'Content-Type'      => 'application/json',
					'Accept'            => 'application/json',
					'Content-Language'  => 'en-GB',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$message = __( 'eBay inventory request failed.', 'order-machine' );
			if ( ! empty( $body['errors'][0]['message'] ) ) {
				$message = (string) $body['errors'][0]['message'];
			}
			return new WP_Error( 'som_ebay_listing', $message );
		}

		return $body;
	}

	/**
	 * @param string               $base    API host.
	 * @param string               $token   Access token.
	 * @param string               $sku     Inventory SKU.
	 * @param int                  $qty     Quantity.
	 * @param array<string, mixed> $payload Full payload for title/description merge.
	 * @return true|WP_Error
	 */
	private static function put_inventory_quantity( $base, $token, $sku, $qty, array $payload ) {
		$existing = self::api_get_json( $base . '/sell/inventory/v1/inventory_item/' . rawurlencode( $sku ), $token );
		if ( is_wp_error( $existing ) ) {
			// Create a minimal inventory item if missing.
			$existing = array(
				'product' => array(
					'title' => ! empty( $payload['title'] ) ? (string) $payload['title'] : $sku,
				),
				'condition' => 'NEW',
			);
		}

		if ( ! isset( $existing['availability'] ) || ! is_array( $existing['availability'] ) ) {
			$existing['availability'] = array();
		}
		if ( ! isset( $existing['availability']['shipToLocationAvailability'] ) || ! is_array( $existing['availability']['shipToLocationAvailability'] ) ) {
			$existing['availability']['shipToLocationAvailability'] = array();
		}
		$existing['availability']['shipToLocationAvailability']['quantity'] = max( 0, (int) $qty );

		if ( ! empty( $payload['title'] ) ) {
			if ( ! isset( $existing['product'] ) || ! is_array( $existing['product'] ) ) {
				$existing['product'] = array();
			}
			$existing['product']['title'] = (string) $payload['title'];
		}
		if ( array_key_exists( 'description', $payload ) && '' !== (string) $payload['description'] ) {
			if ( ! isset( $existing['product'] ) || ! is_array( $existing['product'] ) ) {
				$existing['product'] = array();
			}
			$existing['product']['description'] = (string) $payload['description'];
		}

		$response = wp_remote_request(
			$base . '/sell/inventory/v1/inventory_item/' . rawurlencode( $sku ),
			array(
				'method'  => 'PUT',
				'timeout' => 45,
				'headers' => array(
					'Authorization'    => 'Bearer ' . $token,
					'Content-Type'     => 'application/json',
					'Accept'           => 'application/json',
					'Content-Language' => 'en-GB',
				),
				'body'    => wp_json_encode( $existing ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = __( 'eBay inventory update failed.', 'order-machine' );
			if ( ! empty( $body['errors'][0]['message'] ) ) {
				$message = (string) $body['errors'][0]['message'];
			}
			return new WP_Error( 'som_ebay_listing', $message );
		}

		return true;
	}

	/**
	 * @param string $base  API host.
	 * @param string $token Access token.
	 * @param string $sku   SKU.
	 * @return float|null
	 */
	private static function fetch_offer_price( $base, $token, $sku ) {
		$url  = add_query_arg( array( 'sku' => $sku ), $base . '/sell/inventory/v1/offer' );
		$body = self::api_get_json( $url, $token );
		if ( is_wp_error( $body ) ) {
			return null;
		}
		if ( empty( $body['offers'][0]['pricingSummary']['price']['value'] ) ) {
			return null;
		}
		return (float) $body['offers'][0]['pricingSummary']['price']['value'];
	}

	/**
	 * @param string $base  API host.
	 * @param string $token Access token.
	 * @param string $sku   SKU.
	 * @param float  $price New price.
	 * @return true|WP_Error
	 */
	private static function update_offer_price( $base, $token, $sku, $price ) {
		$url  = add_query_arg( array( 'sku' => $sku ), $base . '/sell/inventory/v1/offer' );
		$body = self::api_get_json( $url, $token );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		if ( empty( $body['offers'][0]['offerId'] ) ) {
			return new WP_Error( 'som_ebay_offer', __( 'No eBay offer found for this SKU — create/publish the offer on eBay first.', 'order-machine' ) );
		}

		$offer    = $body['offers'][0];
		$offer_id = (string) $offer['offerId'];
		$currency = isset( $offer['pricingSummary']['price']['currency'] )
			? (string) $offer['pricingSummary']['price']['currency']
			: 'GBP';

		if ( ! isset( $offer['pricingSummary'] ) || ! is_array( $offer['pricingSummary'] ) ) {
			$offer['pricingSummary'] = array();
		}
		$offer['pricingSummary']['price'] = array(
			'value'    => number_format( (float) $price, 2, '.', '' ),
			'currency' => $currency,
		);

		$response = wp_remote_request(
			$base . '/sell/inventory/v1/offer/' . rawurlencode( $offer_id ),
			array(
				'method'  => 'PUT',
				'timeout' => 45,
				'headers' => array(
					'Authorization'    => 'Bearer ' . $token,
					'Content-Type'     => 'application/json',
					'Accept'           => 'application/json',
					'Content-Language' => 'en-GB',
				),
				'body'    => wp_json_encode( $offer ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$err     = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = __( 'eBay offer price update failed.', 'order-machine' );
			if ( ! empty( $err['errors'][0]['message'] ) ) {
				$message = (string) $err['errors'][0]['message'];
			}
			return new WP_Error( 'som_ebay_offer', $message );
		}

		return true;
	}

	/**
	 * @param string $external_id Listing / SKU key.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function load_fixture_listing( $external_id ) {
		$pushed = get_option( 'som_dummy_ebay_listings', array() );
		if ( is_array( $pushed ) && isset( $pushed[ $external_id ] ) && is_array( $pushed[ $external_id ] ) ) {
			return $pushed[ $external_id ];
		}

		$path = SOM_PLUGIN_DIR . 'tests/fixtures/ebay-listings.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'som_ebay_fixture', __( 'eBay listing fixture file missing.', 'order-machine' ) );
		}

		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture
		if ( ! is_array( $data ) || empty( $data['listings'] ) || ! is_array( $data['listings'] ) ) {
			return new WP_Error( 'som_ebay_fixture', __( 'eBay listing fixture is invalid.', 'order-machine' ) );
		}

		foreach ( $data['listings'] as $row ) {
			if ( is_array( $row ) && isset( $row['external_listing_id'] ) && (string) $row['external_listing_id'] === (string) $external_id ) {
				return $row;
			}
		}

		return new WP_Error(
			'som_ebay_fixture',
			sprintf(
				/* translators: %s: external listing id */
				__( 'No eBay fixture for listing %s.', 'order-machine' ),
				$external_id
			)
		);
	}

	/**
	 * Persist dummy push so a subsequent Refresh returns the pushed state.
	 *
	 * @param array<string, mixed> $payload Listing payload.
	 * @return true
	 */
	private static function store_dummy_push( array $payload ) {
		$key    = isset( $payload['external_listing_id'] ) ? (string) $payload['external_listing_id'] : '';
		$stored = get_option( 'som_dummy_ebay_listings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		if ( '' !== $key ) {
			$stored[ $key ] = $payload;
			update_option( 'som_dummy_ebay_listings', $stored, false );
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
			'access_token'   => (string) $body['access_token'],
			'refresh_token'  => isset( $body['refresh_token'] ) ? (string) $body['refresh_token'] : '',
			'token_type'     => isset( $body['token_type'] ) ? (string) $body['token_type'] : 'Bearer',
			'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + max( 60, $expires_in ) ),
			'expires_in'     => $expires_in,
			'environment'    => $settings['ebay']['environment'],
			'dummy'          => ! empty( $body['dummy'] ),
			'finances_scope' => true,
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
