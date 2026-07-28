<?php
/**
 * Plugin settings (options) for Order Machine.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads/writes `som_settings` option (n8n URL, poll interval, channel app keys).
 */
class SOM_Settings {

	const OPTION_KEY = 'som_settings';

	/**
	 * Default settings shape.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'n8n_base_url'                   => '',
			'poll_interval_minutes'          => 15,
			'token_refresh_interval_minutes' => 30,
			'ebay'                           => array(
				'client_id'     => '',
				'client_secret' => '',
				'runame'        => '',
				'environment'   => 'sandbox',
			),
			'etsy'                           => array(
				'client_id'     => '',
				'client_secret' => '',
			),
		);
	}

	/**
	 * Get merged settings (secrets decrypted for admin use).
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_replace_recursive( self::defaults(), $stored );

		$settings['ebay']['client_secret'] = self::maybe_decrypt_secret( $settings['ebay']['client_secret'] );
		$settings['etsy']['client_secret'] = self::maybe_decrypt_secret( $settings['etsy']['client_secret'] );

		$settings['poll_interval_minutes']          = max( 1, (int) $settings['poll_interval_minutes'] );
		$settings['token_refresh_interval_minutes'] = max( 5, (int) $settings['token_refresh_interval_minutes'] );

		if ( ! in_array( $settings['ebay']['environment'], array( 'sandbox', 'production' ), true ) ) {
			$settings['ebay']['environment'] = 'sandbox';
		}

		return $settings;
	}

	/**
	 * Persist settings; encrypt client secrets before write.
	 *
	 * @param array<string, mixed> $input Raw form / merged values (secrets plaintext).
	 * @return array<string, mixed> Saved settings with secrets decrypted (same as get()).
	 */
	public static function update( array $input ) {
		$current = self::get();
		$next    = array_replace_recursive( $current, $input );

		$next['n8n_base_url']                   = esc_url_raw( (string) ( $next['n8n_base_url'] ?? '' ) );
		$next['poll_interval_minutes']          = max( 1, (int) ( $next['poll_interval_minutes'] ?? 15 ) );
		$next['token_refresh_interval_minutes'] = max( 5, (int) ( $next['token_refresh_interval_minutes'] ?? 30 ) );

		$next['ebay']['client_id']     = sanitize_text_field( (string) ( $next['ebay']['client_id'] ?? '' ) );
		$next['ebay']['runame']        = sanitize_text_field( (string) ( $next['ebay']['runame'] ?? '' ) );
		$next['ebay']['environment']   = in_array( $next['ebay']['environment'] ?? '', array( 'sandbox', 'production' ), true )
			? $next['ebay']['environment']
			: 'sandbox';
		$next['ebay']['client_secret'] = sanitize_text_field( (string) ( $next['ebay']['client_secret'] ?? '' ) );

		$next['etsy']['client_id']     = sanitize_text_field( (string) ( $next['etsy']['client_id'] ?? '' ) );
		$next['etsy']['client_secret'] = sanitize_text_field( (string) ( $next['etsy']['client_secret'] ?? '' ) );

		$to_store                         = $next;
		$to_store['ebay']['client_secret'] = self::encrypt_secret( $next['ebay']['client_secret'] );
		$to_store['etsy']['client_secret'] = self::encrypt_secret( $next['etsy']['client_secret'] );

		update_option( self::OPTION_KEY, $to_store, false );

		return $next;
	}

	/**
	 * OAuth callback URL used for both channels (query distinguishes them).
	 *
	 * @param string $channel Slug: ebay|etsy.
	 * @return string
	 */
	public static function oauth_callback_url( $channel ) {
		return add_query_arg(
			array(
				'page'       => 'som-settings',
				'som_oauth'  => sanitize_key( $channel ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param string $secret Plaintext secret.
	 * @return string Encrypted or empty.
	 */
	private static function encrypt_secret( $secret ) {
		$secret = (string) $secret;
		if ( '' === $secret ) {
			return '';
		}
		return SOM_Crypto::encrypt( $secret );
	}

	/**
	 * @param string $value Stored secret (possibly encrypted).
	 * @return string Plaintext.
	 */
	private static function maybe_decrypt_secret( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		if ( 0 === strpos( $value, SOM_Crypto::PREFIX ) ) {
			return SOM_Crypto::decrypt( $value );
		}
		return $value;
	}
}
