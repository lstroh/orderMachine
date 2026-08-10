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
	 * Pull fee / listing ledger entries (or fixtures when dummy).
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
			return new WP_Error( 'som_etsy_fees', __( 'Etsy is not connected.', 'order-machine' ) );
		}

		$settings = SOM_Settings::get();
		$api_key  = $settings['etsy']['client_id'];
		$token    = (string) $creds['access_token'];
		$shop_id  = ! empty( $creds['shop_id'] ) ? (string) $creds['shop_id'] : '';

		if ( '' === $shop_id ) {
			$shop_id = self::fetch_shop_id( $token, $api_key );
			if ( is_wp_error( $shop_id ) ) {
				return $shop_id;
			}
			if ( '' === $shop_id ) {
				return new WP_Error( 'som_etsy_shop', __( 'Could not resolve Etsy shop ID.', 'order-machine' ) );
			}
		}

		$min_created = strtotime( $from_utc . ' UTC' ) ?: ( time() - 7 * DAY_IN_SECONDS );
		$max_created = strtotime( $to_utc . ' UTC' ) ?: time();

		$raw_entries = array();
		$offset      = 0;
		$limit       = 100;
		$safety      = 0;

		do {
			$url = add_query_arg(
				array(
					'min_created' => $min_created,
					'max_created' => $max_created,
					'limit'       => $limit,
					'offset'      => $offset,
				),
				'https://openapi.etsy.com/v3/application/shops/' . rawurlencode( $shop_id ) . '/payment-account/ledger-entries'
			);

			$body = self::etsy_get_json( $url, $token, $api_key );
			if ( is_wp_error( $body ) ) {
				return $body;
			}

			$batch = isset( $body['results'] ) && is_array( $body['results'] ) ? $body['results'] : array();
			foreach ( $batch as $row ) {
				if ( is_array( $row ) ) {
					$raw_entries[] = $row;
				}
			}

			$count   = isset( $body['count'] ) ? (int) $body['count'] : count( $batch );
			$offset += $limit;
			++$safety;
		} while ( $offset < $count && $safety < 40 );

		$receipt_map = self::map_ledger_entry_receipts( $shop_id, $token, $api_key, $raw_entries );
		if ( is_wp_error( $receipt_map ) ) {
			return $receipt_map;
		}

		$entries = array();
		foreach ( $raw_entries as $raw ) {
			$entry_id = isset( $raw['entry_id'] ) ? (string) $raw['entry_id'] : '';
			$receipt  = ( $entry_id && isset( $receipt_map[ $entry_id ] ) ) ? $receipt_map[ $entry_id ] : null;
			$entries[] = self::normalize_ledger_entry( $raw, $receipt );
		}

		return $entries;
	}

	/**
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private static function load_fixture_platform_fees() {
		$path = SOM_PLUGIN_DIR . 'tests/fixtures/etsy-platform-fees.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'som_etsy_fee_fixture', __( 'Etsy platform fee fixture file missing.', 'order-machine' ) );
		}

		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture
		if ( ! is_array( $data ) || empty( $data['entries'] ) || ! is_array( $data['entries'] ) ) {
			return new WP_Error( 'som_etsy_fee_fixture', __( 'Etsy platform fee fixture is invalid.', 'order-machine' ) );
		}

		$out = array();
		foreach ( $data['entries'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			// Fixture may already be normalized.
			if ( isset( $row['kind'], $row['external_entry_id'] ) ) {
				$out[] = $row;
				continue;
			}
			$receipt = null;
			if ( ! empty( $row['receipt_id'] ) ) {
				$receipt = array( 'receipt_id' => $row['receipt_id'] );
			}
			$out[] = self::normalize_ledger_entry( $row, $receipt );
		}

		return $out;
	}

	/**
	 * Batch-resolve ledger entry → receipt_id via Payments API.
	 *
	 * @param string                     $shop_id Shop ID.
	 * @param string                     $token   Access token.
	 * @param string                     $api_key API key.
	 * @param array<int, array<string, mixed>> $entries Ledger rows.
	 * @return array<string, array<string, mixed>>|WP_Error Map entry_id => payment fragment.
	 */
	private static function map_ledger_entry_receipts( $shop_id, $token, $api_key, array $entries ) {
		$ids = array();
		foreach ( $entries as $row ) {
			if ( ! empty( $row['entry_id'] ) ) {
				$ids[] = (string) $row['entry_id'];
			}
		}
		$ids = array_values( array_unique( $ids ) );
		$map = array();

		foreach ( array_chunk( $ids, 50 ) as $chunk ) {
			$url = add_query_arg(
				array(
					'ledger_entry_ids' => implode( ',', $chunk ),
				),
				'https://openapi.etsy.com/v3/application/shops/' . rawurlencode( $shop_id ) . '/payment-account/ledger-entries/payments'
			);

			$body = self::etsy_get_json( $url, $token, $api_key );
			if ( is_wp_error( $body ) ) {
				return $body;
			}

			$results = isset( $body['results'] ) && is_array( $body['results'] ) ? $body['results'] : ( is_array( $body ) ? $body : array() );
			foreach ( $results as $payment ) {
				if ( ! is_array( $payment ) ) {
					continue;
				}
				$receipt_id = isset( $payment['receipt_id'] ) ? (string) $payment['receipt_id'] : '';
				$ledger_ids = array();
				if ( ! empty( $payment['ledger_entry_id'] ) ) {
					$ledger_ids[] = (string) $payment['ledger_entry_id'];
				}
				if ( ! empty( $payment['ledger_entry_ids'] ) && is_array( $payment['ledger_entry_ids'] ) ) {
					foreach ( $payment['ledger_entry_ids'] as $lid ) {
						$ledger_ids[] = (string) $lid;
					}
				}
				foreach ( $ledger_ids as $lid ) {
					$map[ $lid ] = array(
						'receipt_id' => $receipt_id,
						'payment'    => $payment,
					);
				}
			}
		}

		return $map;
	}

	/**
	 * Classify a ledger row: order fee, recurring listing fee, or ignore.
	 *
	 * @param array<string, mixed>      $raw     Ledger entry.
	 * @param array<string, mixed>|null $receipt Linked payment info.
	 * @return array<string, mixed>
	 */
	private static function normalize_ledger_entry( array $raw, $receipt ) {
		$entry_id = isset( $raw['entry_id'] ) ? (string) $raw['entry_id'] : ( 'etsy:' . md5( wp_json_encode( $raw ) ) );
		$desc     = strtolower( (string) ( $raw['description'] ?? '' ) );

		if ( isset( $raw['amount_as_returned'] ) ) {
			$amount = (float) $raw['amount_as_returned'];
		} else {
			// Live ledger entries use minor currency units (cents).
			$amount = isset( $raw['amount'] ) ? ( (float) $raw['amount'] / 100 ) : 0.0;
		}

		$currency = isset( $raw['currency'] ) ? (string) $raw['currency'] : 'GBP';
		$created  = isset( $raw['create_date'] ) ? (int) $raw['create_date'] : time();
		$incurred = gmdate( 'Y-m-d', $created );

		$receipt_id = '';
		if ( is_array( $receipt ) && ! empty( $receipt['receipt_id'] ) ) {
			$receipt_id = (string) $receipt['receipt_id'];
		} elseif ( ! empty( $raw['receipt_id'] ) ) {
			$receipt_id = (string) $raw['receipt_id'];
		}

		$listing_id = isset( $raw['listing_id'] ) ? (string) $raw['listing_id'] : '';

		// Ignore payouts, refunds, shipping labels, taxes.
		if ( self::etsy_ledger_is_ignored( $desc ) ) {
			return array(
				'kind'              => 'ignore',
				'external_entry_id' => $entry_id,
				'raw'               => $raw,
			);
		}

		$fee_type = self::etsy_fee_type_from_description( $desc );
		if ( null === $fee_type ) {
			return array(
				'kind'              => 'ignore',
				'external_entry_id' => $entry_id,
				'raw'               => $raw,
			);
		}

		if ( '' !== $receipt_id && 'listing_fee' !== $fee_type ) {
			return array(
				'kind'               => 'order',
				'external_entry_id'  => $entry_id,
				'external_order_id'  => $receipt_id,
				'fee_type'           => $fee_type,
				'amount'             => $amount,
				'currency'           => $currency,
				'raw'                => $raw,
			);
		}

		if ( 'listing_fee' === $fee_type ) {
			return array(
				'kind'                 => 'recurring',
				'external_entry_id'    => $entry_id,
				'external_listing_id'  => $listing_id,
				'fee_type'             => 'listing_fee',
				'amount'               => $amount,
				'currency'             => $currency,
				'incurred_date'        => $incurred,
				'notes'                => isset( $raw['description'] ) ? (string) $raw['description'] : null,
				'raw'                  => $raw,
			);
		}

		// Fee-like but no receipt yet — leave unmatched via order path with empty id → unmatched retry.
		if ( '' === $receipt_id ) {
			return array(
				'kind'              => 'order',
				'external_entry_id' => $entry_id,
				'external_order_id' => '',
				'fee_type'          => $fee_type,
				'amount'            => $amount,
				'currency'          => $currency,
				'raw'               => $raw,
			);
		}

		return array(
			'kind'               => 'order',
			'external_entry_id'  => $entry_id,
			'external_order_id'  => $receipt_id,
			'fee_type'           => $fee_type,
			'amount'             => $amount,
			'currency'           => $currency,
			'raw'                => $raw,
		);
	}

	/**
	 * @param string $desc Lowercased description.
	 * @return bool
	 */
	private static function etsy_ledger_is_ignored( $desc ) {
		$needles = array(
			'payout',
			'deposit',
			'refund',
			'shipping label',
			'postage',
			'sales tax',
			'vat on',
			'tax on sale',
			'gift wrap',
		);
		foreach ( $needles as $n ) {
			if ( false !== strpos( $desc, $n ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $desc Lowercased description.
	 * @return string|null Fee type key or null if not a tracked fee.
	 */
	private static function etsy_fee_type_from_description( $desc ) {
		if ( false !== strpos( $desc, 'listing' ) ) {
			return 'listing_fee';
		}
		if ( false !== strpos( $desc, 'transaction' ) || false !== strpos( $desc, 'commission' ) ) {
			return 'transaction_fee';
		}
		if ( false !== strpos( $desc, 'processing' ) || false !== strpos( $desc, 'payment fee' ) ) {
			return 'payment_processing';
		}
		if ( false !== strpos( $desc, 'regulatory' ) ) {
			return 'regulatory_fee';
		}
		if ( false !== strpos( $desc, 'offsite' ) || false !== strpos( $desc, 'ads' ) ) {
			return 'offsite_ads';
		}
		return null;
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
	 * Fetch listing + inventory (or fixture when dummy).
	 *
	 * @param array<string, mixed> $hint external_listing_id, inventory.
	 * @return array<string, mixed>|WP_Error
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
			return new WP_Error( 'som_etsy_listing', __( 'Etsy is not connected.', 'order-machine' ) );
		}

		$settings = SOM_Settings::get();
		$api_key  = $settings['etsy']['client_id'];
		$token    = (string) $creds['access_token'];
		$shop_id  = ! empty( $creds['shop_id'] ) ? (string) $creds['shop_id'] : '';

		if ( '' === $shop_id ) {
			$shop_id = self::fetch_shop_id( $token, $api_key );
			if ( is_wp_error( $shop_id ) ) {
				return $shop_id;
			}
			if ( '' === $shop_id ) {
				return new WP_Error( 'som_etsy_shop', __( 'Could not resolve Etsy shop ID.', 'order-machine' ) );
			}
		}

		$listing = self::etsy_get_json(
			'https://openapi.etsy.com/v3/application/listings/' . rawurlencode( $external_id ),
			$token,
			$api_key
		);
		if ( is_wp_error( $listing ) ) {
			return $listing;
		}

		$inv = self::etsy_get_json(
			'https://openapi.etsy.com/v3/application/listings/' . rawurlencode( $external_id ) . '/inventory',
			$token,
			$api_key
		);
		if ( is_wp_error( $inv ) ) {
			return $inv;
		}

		$normalized = self::normalize_etsy_inventory( $external_id, $listing, $inv );
		unset( $shop_id ); // used for auth path; listing endpoints above are listing-scoped.
		return $normalized;
	}

	/**
	 * Push price / description / inventory to Etsy.
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
			return new WP_Error( 'som_etsy_listing', __( 'Etsy is not connected.', 'order-machine' ) );
		}

		$settings    = SOM_Settings::get();
		$api_key     = $settings['etsy']['client_id'];
		$token       = (string) $creds['access_token'];
		$external_id = isset( $payload['external_listing_id'] ) ? (string) $payload['external_listing_id'] : '';
		$shop_id     = ! empty( $creds['shop_id'] ) ? (string) $creds['shop_id'] : '';

		if ( '' === $shop_id ) {
			$shop_id = self::fetch_shop_id( $token, $api_key );
			if ( is_wp_error( $shop_id ) || '' === $shop_id ) {
				return is_wp_error( $shop_id ) ? $shop_id : new WP_Error( 'som_etsy_shop', __( 'Could not resolve Etsy shop ID.', 'order-machine' ) );
			}
		}

		$patch_body = array();
		if ( isset( $payload['title'] ) && '' !== (string) $payload['title'] ) {
			$patch_body['title'] = (string) $payload['title'];
		}
		if ( array_key_exists( 'description', $payload ) ) {
			$patch_body['description'] = (string) $payload['description'];
		}
		if ( isset( $payload['price'] ) ) {
			// Etsy expects price as a string amount in some versions; send float in listing PATCH.
			$patch_body['price'] = (float) $payload['price'];
		}

		if ( $patch_body ) {
			$response = wp_remote_request(
				'https://openapi.etsy.com/v3/application/shops/' . rawurlencode( $shop_id ) . '/listings/' . rawurlencode( $external_id ),
				array(
					'method'  => 'PATCH',
					'timeout' => 45,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'x-api-key'     => $api_key,
						'Content-Type'  => 'application/json',
						'Accept'        => 'application/json',
					),
					'body'    => wp_json_encode( $patch_body ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				$err     = json_decode( wp_remote_retrieve_body( $response ), true );
				$message = __( 'Etsy listing update failed.', 'order-machine' );
				if ( ! empty( $err['error'] ) ) {
					$message = (string) $err['error'];
				}
				return new WP_Error( 'som_etsy_listing', $message );
			}
		}

		$current = self::etsy_get_json(
			'https://openapi.etsy.com/v3/application/listings/' . rawurlencode( $external_id ) . '/inventory',
			$token,
			$api_key
		);
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$updated = self::apply_qty_to_etsy_inventory( $current, $payload );
		$response = wp_remote_request(
			'https://openapi.etsy.com/v3/application/listings/' . rawurlencode( $external_id ) . '/inventory',
			array(
				'method'  => 'PUT',
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'x-api-key'     => $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $updated ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$err     = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = __( 'Etsy inventory update failed.', 'order-machine' );
			if ( ! empty( $err['error'] ) ) {
				$message = (string) $err['error'];
			}
			return new WP_Error( 'som_etsy_listing', $message );
		}

		return true;
	}

	/**
	 * @param string               $external_id Listing ID.
	 * @param array<string, mixed> $listing     Listing GET body.
	 * @param array<string, mixed> $inv         Inventory GET body.
	 * @return array<string, mixed>
	 */
	private static function normalize_etsy_inventory( $external_id, array $listing, array $inv ) {
		$products   = isset( $inv['products'] ) && is_array( $inv['products'] ) ? $inv['products'] : array();
		$variations = array();
		$total_qty  = 0;

		foreach ( $products as $product ) {
			if ( ! is_array( $product ) ) {
				continue;
			}
			$options = array();
			if ( ! empty( $product['property_values'] ) && is_array( $product['property_values'] ) ) {
				foreach ( $product['property_values'] as $pv ) {
					if ( ! is_array( $pv ) ) {
						continue;
					}
					$name = isset( $pv['property_name'] ) ? (string) $pv['property_name'] : '';
					$vals = isset( $pv['values'] ) && is_array( $pv['values'] ) ? $pv['values'] : array();
					if ( '' !== $name && isset( $vals[0] ) ) {
						$options[ $name ] = (string) $vals[0];
					}
				}
			}

			$qty = 0;
			if ( ! empty( $product['offerings'][0]['quantity'] ) ) {
				$qty = (int) $product['offerings'][0]['quantity'];
			}
			$total_qty += $qty;

			$sku = isset( $product['sku'] ) ? (string) $product['sku'] : '';
			$row = array(
				'sku'      => $sku,
				'quantity' => $qty,
				'options'  => $options,
			);
			if ( ! empty( $product['product_id'] ) ) {
				$row['external_id'] = (string) $product['product_id'];
			}
			if ( ! empty( $product['offerings'][0]['price']['amount'] ) && ! empty( $product['offerings'][0]['price']['divisor'] ) ) {
				$row['price'] = (float) $product['offerings'][0]['price']['amount'] / (float) $product['offerings'][0]['price']['divisor'];
			}
			$variations[] = $row;
		}

		$mode = count( $variations ) > 1 ? 'variations' : 'flat';
		$price = isset( $listing['price']['amount'], $listing['price']['divisor'] )
			? ( (float) $listing['price']['amount'] / (float) $listing['price']['divisor'] )
			: 0.0;

		if ( empty( $variations ) && isset( $listing['quantity'] ) ) {
			$total_qty = (int) $listing['quantity'];
		}

		return array(
			'external_listing_id' => (string) $external_id,
			'title'               => isset( $listing['title'] ) ? (string) $listing['title'] : '',
			'description'         => isset( $listing['description'] ) ? (string) $listing['description'] : '',
			'price'               => $price,
			'quantity_available'  => $total_qty,
			'inventory'           => array(
				'mode'       => $mode,
				'sku'        => ! empty( $variations[0]['sku'] ) ? $variations[0]['sku'] : '',
				'variations' => 'variations' === $mode ? $variations : array(),
			),
		);
	}

	/**
	 * Merge local quantities into an Etsy inventory payload for PUT.
	 *
	 * @param array<string, mixed> $current Current inventory JSON.
	 * @param array<string, mixed> $payload Local listing payload.
	 * @return array<string, mixed>
	 */
	private static function apply_qty_to_etsy_inventory( array $current, array $payload ) {
		$inventory = isset( $payload['inventory'] ) && is_array( $payload['inventory'] ) ? $payload['inventory'] : array();
		$mode      = isset( $inventory['mode'] ) ? (string) $inventory['mode'] : 'flat';
		$products  = isset( $current['products'] ) && is_array( $current['products'] ) ? $current['products'] : array();

		if ( 'variations' === $mode && ! empty( $inventory['variations'] ) && is_array( $inventory['variations'] ) ) {
			$by_sku = array();
			$by_id  = array();
			foreach ( $inventory['variations'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( ! empty( $row['sku'] ) ) {
					$by_sku[ (string) $row['sku'] ] = (int) $row['quantity'];
				}
				if ( ! empty( $row['external_id'] ) ) {
					$by_id[ (string) $row['external_id'] ] = (int) $row['quantity'];
				}
			}

			foreach ( $products as $i => $product ) {
				if ( ! is_array( $product ) ) {
					continue;
				}
				$qty = null;
				$pid = isset( $product['product_id'] ) ? (string) $product['product_id'] : '';
				$sku = isset( $product['sku'] ) ? (string) $product['sku'] : '';
				if ( '' !== $pid && array_key_exists( $pid, $by_id ) ) {
					$qty = $by_id[ $pid ];
				} elseif ( '' !== $sku && array_key_exists( $sku, $by_sku ) ) {
					$qty = $by_sku[ $sku ];
				}
				if ( null === $qty ) {
					continue;
				}
				if ( empty( $products[ $i ]['offerings'][0] ) || ! is_array( $products[ $i ]['offerings'][0] ) ) {
					$products[ $i ]['offerings'][0] = array();
				}
				$products[ $i ]['offerings'][0]['quantity'] = max( 0, (int) $qty );
			}
		} else {
			$qty = isset( $payload['quantity_available'] ) ? (int) $payload['quantity_available'] : 0;
			foreach ( $products as $i => $product ) {
				if ( empty( $products[ $i ]['offerings'][0] ) || ! is_array( $products[ $i ]['offerings'][0] ) ) {
					$products[ $i ]['offerings'][0] = array();
				}
				$products[ $i ]['offerings'][0]['quantity'] = max( 0, $qty );
			}
		}

		$current['products'] = $products;
		return $current;
	}

	/**
	 * @param string $url     Absolute URL.
	 * @param string $token   Access token.
	 * @param string $api_key x-api-key.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function etsy_get_json( $url, $token, $api_key ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'x-api-key'     => $api_key,
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
			$message = __( 'Etsy listing request failed.', 'order-machine' );
			if ( ! empty( $body['error'] ) ) {
				$message = (string) $body['error'];
			}
			return new WP_Error( 'som_etsy_listing', $message );
		}

		return $body;
	}

	/**
	 * @param string $external_id Listing ID.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function load_fixture_listing( $external_id ) {
		$pushed = get_option( 'som_dummy_etsy_listings', array() );
		if ( is_array( $pushed ) && isset( $pushed[ $external_id ] ) && is_array( $pushed[ $external_id ] ) ) {
			return $pushed[ $external_id ];
		}

		$path = SOM_PLUGIN_DIR . 'tests/fixtures/etsy-listings.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'som_etsy_fixture', __( 'Etsy listing fixture file missing.', 'order-machine' ) );
		}

		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture
		if ( ! is_array( $data ) || empty( $data['listings'] ) || ! is_array( $data['listings'] ) ) {
			return new WP_Error( 'som_etsy_fixture', __( 'Etsy listing fixture is invalid.', 'order-machine' ) );
		}

		foreach ( $data['listings'] as $row ) {
			if ( is_array( $row ) && isset( $row['external_listing_id'] ) && (string) $row['external_listing_id'] === (string) $external_id ) {
				return $row;
			}
		}

		return new WP_Error(
			'som_etsy_fixture',
			sprintf(
				/* translators: %s: external listing id */
				__( 'No Etsy fixture for listing %s.', 'order-machine' ),
				$external_id
			)
		);
	}

	/**
	 * @param array<string, mixed> $payload Listing payload.
	 * @return true
	 */
	private static function store_dummy_push( array $payload ) {
		$key    = isset( $payload['external_listing_id'] ) ? (string) $payload['external_listing_id'] : '';
		$stored = get_option( 'som_dummy_etsy_listings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		if ( '' !== $key ) {
			$stored[ $key ] = $payload;
			update_option( 'som_dummy_etsy_listings', $stored, false );
		}
		return true;
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
