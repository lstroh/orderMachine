<?php
/**
 * Allowlisted local workflow actions (no arbitrary shell from DB).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Named local handlers for script_config type=local.
 */
class SOM_Local_Actions {

	/**
	 * Dispatch a named allowlisted action.
	 *
	 * @param string               $action Action key from script_config.
	 * @param array<string, mixed> $params Params from script_config.
	 * @param object               $order  Order from SOM_Orders::get().
	 * @return true|WP_Error
	 */
	public static function run( $action, array $params, $order ) {
		$action = sanitize_key( (string) $action );

		switch ( $action ) {
			case 'run_thankyou_card_script':
				return self::run_thankyou_card_script( $params, $order );
			case 'send_print_job':
				return new WP_Error(
					'som_print_not_configured',
					__( 'Send print job is not configured yet.', 'order-machine' )
				);
			default:
				return new WP_Error(
					'som_unknown_local_action',
					sprintf(
						/* translators: %s: action name */
						__( 'Unknown local action: %s', 'order-machine' ),
						$action
					)
				);
		}
	}

	/**
	 * Run thankyou_card_cli.py with --json / --out.
	 *
	 * Fails clearly when Python/reportlab are missing — retryable from the UI.
	 *
	 * @param array<string, mixed> $params Optional overrides (paper, flower_color, …).
	 * @param object               $order  Order row.
	 * @return true|WP_Error
	 */
	private static function run_thankyou_card_script( array $params, $order ) {
		$python = self::resolve_python_binary();
		$cli    = SOM_PLUGIN_DIR . 'stikerts/Thank you/thankyou_card_cli.py';

		if ( ! is_readable( $cli ) ) {
			return new WP_Error(
				'som_thankyou_cli_missing',
				__( 'Thank-you card CLI wrapper was not found.', 'order-machine' )
			);
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'som_upload_dir', (string) $upload['error'] );
		}

		$dir = trailingslashit( $upload['basedir'] ) . 'som-thankyou';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error(
				'som_thankyou_dir',
				__( 'Could not create thank-you PDF directory.', 'order-machine' )
			);
		}

		$order_id  = (int) $order->id;
		$json_path = $dir . '/order-' . $order_id . '-input.json';
		$out_path  = $dir . '/order-' . $order_id . '.pdf';

		$payload = array( 'orders' => array( self::build_thankyou_order_dict( $params, $order ) ) );
		$written = file_put_contents( $json_path, wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		if ( false === $written ) {
			return new WP_Error(
				'som_thankyou_json',
				__( 'Could not write thank-you input JSON.', 'order-machine' )
			);
		}

		$cmd = array( $python, $cli, '--json', $json_path, '--out', $out_path );
		$out = self::proc_run( $cmd, dirname( $cli ) );

		if ( is_wp_error( $out ) ) {
			return $out;
		}

		if ( 0 !== (int) $out['code'] ) {
			$err = trim( (string) $out['stderr'] );
			if ( '' === $err ) {
				$err = trim( (string) $out['stdout'] );
			}
			if ( '' === $err ) {
				$err = __( 'Thank-you card script failed (is Python and reportlab installed?).', 'order-machine' );
			}
			return new WP_Error( 'som_thankyou_failed', $err );
		}

		if ( ! is_readable( $out_path ) ) {
			return new WP_Error(
				'som_thankyou_pdf_missing',
				__( 'Thank-you script exited OK but PDF was not created.', 'order-machine' )
			);
		}

		return true;
	}

	/**
	 * Map order + params into thankyou_card order dict (defaults OK until script is finalised).
	 *
	 * @param array<string, mixed> $params Step params.
	 * @param object               $order  Order.
	 * @return array<string, mixed>
	 */
	private static function build_thankyou_order_dict( array $params, $order ) {
		$product_name = '';
		$care_line    = isset( $params['care_line'] ) ? (string) $params['care_line'] : 'laminated for weatherproof durability';

		if ( ! empty( $order->items ) && is_array( $order->items ) ) {
			foreach ( $order->items as $item ) {
				if ( ! empty( $item->product_name ) ) {
					$product_name = (string) $item->product_name;
					break;
				}
			}
			if ( '' === $product_name ) {
				foreach ( $order->items as $item ) {
					if ( ! empty( $item->personalisation_text ) ) {
						$product_name = 'custom order';
						break;
					}
				}
			}
		}
		if ( '' === $product_name ) {
			$product_name = isset( $params['product'] ) ? (string) $params['product'] : 'order';
		}

		$buyer = (string) $order->buyer_name;
		$first = trim( strtok( $buyer, ' ' ) );
		if ( '' === $first ) {
			$first = $buyer;
		}

		$channel = isset( $order->channel_slug ) ? (string) $order->channel_slug : 'website';
		if ( ! in_array( $channel, array( 'ebay', 'etsy', 'website' ), true ) ) {
			$channel = 'website';
		}

		$paper = isset( $params['paper'] ) ? (string) $params['paper'] : 'white';
		if ( ! in_array( $paper, array( 'white', 'kraft' ), true ) ) {
			$paper = 'white';
		}

		$flower = isset( $params['flower_color'] ) ? (string) $params['flower_color'] : 'blush';

		return array(
			'buyer_name'     => $first,
			'product'        => $product_name,
			'care_line'      => $care_line,
			'channel'        => $channel,
			'paper'          => $paper,
			'flower_color'   => $flower,
			'brand_name'     => isset( $params['brand_name'] ) ? (string) $params['brand_name'] : 'Kerbside Craft Co.',
			'handle'         => isset( $params['handle'] ) ? (string) $params['handle'] : 'kerbsidecraftco',
			'discount_code'  => array_key_exists( 'discount_code', $params ) ? $params['discount_code'] : null,
		);
	}

	/**
	 * @return string Absolute path or command name for Python.
	 */
	private static function resolve_python_binary() {
		$settings = SOM_Settings::get();
		$configured = isset( $settings['python_binary'] ) ? trim( (string) $settings['python_binary'] ) : '';
		if ( '' !== $configured ) {
			return $configured;
		}
		return 'python';
	}

	/**
	 * Run a fixed argv list via proc_open (no shell interpolation).
	 *
	 * @param array<int, string> $cmd Command + args.
	 * @param string             $cwd Working directory.
	 * @return array{code:int,stdout:string,stderr:string}|WP_Error
	 */
	private static function proc_run( array $cmd, $cwd ) {
		if ( ! function_exists( 'proc_open' ) ) {
			return new WP_Error(
				'som_proc_open',
				__( 'proc_open is disabled; cannot run local scripts.', 'order-machine' )
			);
		}

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- intentional probe
		$process = @proc_open( $cmd, $descriptors, $pipes, $cwd, null, array( 'bypass_shell' => true ) );
		if ( ! is_resource( $process ) ) {
			return new WP_Error(
				'som_proc_failed',
				__( 'Could not start Python process (is Python installed and on PATH?).', 'order-machine' )
			);
		}

		fclose( $pipes[0] );
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$code = proc_close( $process );

		return array(
			'code'   => (int) $code,
			'stdout' => is_string( $stdout ) ? $stdout : '',
			'stderr' => is_string( $stderr ) ? $stderr : '',
		);
	}
}
