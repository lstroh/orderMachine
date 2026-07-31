<?php
/**
 * Execute workflow script_config (local / api / n8n) with placeholder resolution.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Script/API/n8n runners for the workflow engine.
 */
class SOM_Script_Dispatch {

	const MAX_ATTEMPTS = 3;

	/**
	 * Run script_config for an order. Returns:
	 * - true: completed synchronously
	 * - 'waiting_callback': HTTP accepted; wait for REST callback
	 * - WP_Error: failure
	 *
	 * @param object $order  Order from SOM_Orders::get().
	 * @param object $step   Workflow step row (script_config).
	 * @param object $progress Progress row (for callback token binding).
	 * @return true|string|WP_Error
	 */
	public static function execute( $order, $step, $progress ) {
		$config = self::decode_config( $step );
		if ( is_wp_error( $config ) ) {
			return $config;
		}
		if ( null === $config ) {
			return true;
		}

		$type = isset( $config['type'] ) ? (string) $config['type'] : '';

		switch ( $type ) {
			case 'local':
				$action = isset( $config['action'] ) ? (string) $config['action'] : '';
				$params = isset( $config['params'] ) && is_array( $config['params'] ) ? $config['params'] : array();
				return SOM_Local_Actions::run( $action, $params, $order );

			case 'api':
				return self::run_http( $config, $order, $progress, false );

			case 'n8n':
				return self::run_http( $config, $order, $progress, true );

			default:
				return new WP_Error(
					'som_script_type',
					__( 'Unsupported script_config type.', 'order-machine' )
				);
		}
	}

	/**
	 * @param object $step Step row.
	 * @return array<string, mixed>|null|WP_Error
	 */
	public static function decode_config( $step ) {
		$raw = isset( $step->script_config ) ? trim( (string) $step->script_config ) : '';
		if ( '' === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['type'] ) ) {
			return new WP_Error( 'som_script_config', __( 'Invalid script_config JSON.', 'order-machine' ) );
		}
		return $decoded;
	}

	/**
	 * Whether the step has a non-empty script_config.
	 *
	 * @param object $step Step row.
	 * @return bool
	 */
	public static function has_script( $step ) {
		$config = self::decode_config( $step );
		return is_array( $config );
	}

	/**
	 * HTTP call for api / n8n configs.
	 *
	 * Sync: 2xx → success (unless wait_for_callback).
	 * Async: wait_for_callback true → 2xx keeps step waiting for REST callback.
	 *
	 * @param array<string, mixed> $config   Decoded script_config.
	 * @param object               $order    Order.
	 * @param object               $progress Progress row.
	 * @param bool                 $is_n8n   Use n8n webhook_url / payload_template keys.
	 * @return true|string|WP_Error
	 */
	private static function run_http( array $config, $order, $progress, $is_n8n ) {
		$wait = ! empty( $config['wait_for_callback'] );

		if ( $is_n8n ) {
			$url  = isset( $config['webhook_url'] ) ? (string) $config['webhook_url'] : '';
			$body = isset( $config['payload_template'] ) ? $config['payload_template'] : array();
			$method = 'POST';
		} else {
			$url    = isset( $config['url'] ) ? (string) $config['url'] : '';
			$body   = isset( $config['body_template'] ) ? $config['body_template'] : array();
			$method = isset( $config['method'] ) ? strtoupper( (string) $config['method'] ) : 'POST';
		}

		if ( '' === $url ) {
			return new WP_Error( 'som_script_url', __( 'Script step is missing a URL.', 'order-machine' ) );
		}

		$placeholders = self::placeholder_map( $order );
		$url          = self::replace_placeholders_string( $url, $placeholders );
		$body         = self::replace_placeholders_deep( $body, $placeholders );

		$token = '';
		if ( $wait || $is_n8n ) {
			$token = self::issue_callback_token( (int) $order->id, (int) $progress->id );
			if ( is_array( $body ) ) {
				$body['callback_url']   = rest_url( 'som/v1/workflow-callback/' . $token );
				$body['callback_token'] = $token;
				$body['order_id']       = (int) $order->id;
			}
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if ( ! empty( $config['headers'] ) && is_array( $config['headers'] ) ) {
			foreach ( $config['headers'] as $hk => $hv ) {
				$args['headers'][ (string) $hk ] = self::replace_placeholders_string( (string) $hv, $placeholders );
			}
		}

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$snippet = substr( (string) wp_remote_retrieve_body( $response ), 0, 300 );
			return new WP_Error(
				'som_http_failed',
				sprintf(
					/* translators: 1: HTTP status, 2: body snippet */
					__( 'HTTP %1$d from script endpoint. %2$s', 'order-machine' ),
					$code,
					$snippet
				)
			);
		}

		if ( $wait ) {
			return 'waiting_callback';
		}

		return true;
	}

	/**
	 * Fixed placeholder set from order (no full templating language).
	 *
	 * @param object $order Order.
	 * @return array<string, string>
	 */
	public static function placeholder_map( $order ) {
		$product_name = '';
		$personalisation = '';
		if ( ! empty( $order->items ) && is_array( $order->items ) ) {
			foreach ( $order->items as $item ) {
				if ( '' === $product_name && ! empty( $item->product_name ) ) {
					$product_name = (string) $item->product_name;
				}
				if ( '' === $personalisation && ! empty( $item->personalisation_text ) ) {
					$personalisation = (string) $item->personalisation_text;
				}
			}
		}

		$tracking = '';
		if ( ! empty( $order->raw_payload ) ) {
			$raw = json_decode( (string) $order->raw_payload, true );
			if ( is_array( $raw ) ) {
				if ( ! empty( $raw['tracking_number'] ) ) {
					$tracking = (string) $raw['tracking_number'];
				} elseif ( ! empty( $raw['trackingNumber'] ) ) {
					$tracking = (string) $raw['trackingNumber'];
				}
			}
		}

		return array(
			'order_id'             => (string) (int) $order->id,
			'external_order_id'    => (string) $order->external_order_id,
			'buyer_name'           => (string) $order->buyer_name,
			'product_name'         => $product_name,
			'personalisation_text' => $personalisation,
			'tracking_number'      => $tracking,
			'channel'              => isset( $order->channel_slug ) ? (string) $order->channel_slug : '',
		);
	}

	/**
	 * @param string                $text String with {{keys}}.
	 * @param array<string, string> $map  Placeholders.
	 * @return string
	 */
	private static function replace_placeholders_string( $text, array $map ) {
		return preg_replace_callback(
			'/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
			static function ( $m ) use ( $map ) {
				$key = strtolower( $m[1] );
				return array_key_exists( $key, $map ) ? $map[ $key ] : $m[0];
			},
			(string) $text
		);
	}

	/**
	 * @param mixed                 $value Value tree.
	 * @param array<string, string> $map   Placeholders.
	 * @return mixed
	 */
	private static function replace_placeholders_deep( $value, array $map ) {
		if ( is_string( $value ) ) {
			return self::replace_placeholders_string( $value, $map );
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::replace_placeholders_deep( $v, $map );
			}
			return $out;
		}
		return $value;
	}

	/**
	 * Create a one-time callback token bound to progress row.
	 *
	 * @param int $order_id    Order PK.
	 * @param int $progress_id Progress PK.
	 * @return string
	 */
	public static function issue_callback_token( $order_id, $progress_id ) {
		$token = wp_generate_password( 32, false, false );
		set_transient(
			'som_wcb_' . $token,
			array(
				'order_id'    => (int) $order_id,
				'progress_id' => (int) $progress_id,
			),
			WEEK_IN_SECONDS
		);
		return $token;
	}

	/**
	 * Resolve callback token → binding.
	 *
	 * @param string $token Token.
	 * @return array{order_id:int,progress_id:int}|null
	 */
	public static function resolve_callback_token( $token ) {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token );
		if ( strlen( $token ) < 16 ) {
			return null;
		}
		$data = get_transient( 'som_wcb_' . $token );
		if ( ! is_array( $data ) || empty( $data['order_id'] ) || empty( $data['progress_id'] ) ) {
			return null;
		}
		return array(
			'order_id'    => (int) $data['order_id'],
			'progress_id' => (int) $data['progress_id'],
		);
	}

	/**
	 * Consume (delete) a callback token.
	 *
	 * @param string $token Token.
	 * @return void
	 */
	public static function consume_callback_token( $token ) {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token );
		delete_transient( 'som_wcb_' . $token );
	}
}
