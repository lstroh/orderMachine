<?php
/**
 * Dev/seed helpers (wp-env dummy channel credentials).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads non-functional dummy OAuth payloads when `SOM_USE_DUMMY_CREDENTIALS` is true.
 */
class SOM_Seed {

	/**
	 * Ensure channel rows + dummy encrypted credentials (idempotent).
	 *
	 * @return void
	 */
	public static function maybe_load_dummy_credentials() {
		if ( ! defined( 'SOM_USE_DUMMY_CREDENTIALS' ) || ! SOM_USE_DUMMY_CREDENTIALS ) {
			return;
		}

		SOM_Channels::ensure_rows();

		foreach ( array( 'ebay', 'etsy' ) as $slug ) {
			$existing = SOM_Channels::get_credentials( $slug );
			if ( ! empty( $existing['access_token'] ) && empty( $existing['dummy'] ) ) {
				// Real tokens present — do not overwrite.
				continue;
			}
			if ( ! empty( $existing['dummy'] ) && ! empty( $existing['access_token'] ) ) {
				continue;
			}

			$payload = array(
				'access_token'  => 'dummy-access-' . $slug,
				'refresh_token' => 'dummy-refresh-' . $slug,
				'token_type'    => 'Bearer',
				'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS ),
				'expires_in'    => YEAR_IN_SECONDS,
				'dummy'         => true,
			);

			if ( 'etsy' === $slug ) {
				$payload['shop_id'] = '0';
			}

			if ( 'ebay' === $slug ) {
				$payload['environment'] = 'sandbox';
			}

			SOM_Channels::save_credentials( $slug, $payload );
		}
	}
}
