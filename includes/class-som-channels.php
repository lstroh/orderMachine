<?php
/**
 * Channel row helpers for Order Machine.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ensures and updates `wp_som_channels` rows (ebay / etsy).
 */
class SOM_Channels {

	/**
	 * Known channel definitions.
	 *
	 * @return array<string, string> slug => display_name
	 */
	public static function known() {
		return array(
			'ebay' => 'eBay',
			'etsy' => 'Etsy',
		);
	}

	/**
	 * Ensure ebay + etsy rows exist (idempotent).
	 *
	 * @return void
	 */
	public static function ensure_rows() {
		global $wpdb;

		$table = SOM_DB::table( 'channels' );
		$now   = current_time( 'mysql', true );

		foreach ( self::known() as $slug => $display_name ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
					$slug
				)
			);

			if ( $existing ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'slug'          => $slug,
					'display_name'  => $display_name,
					'is_active'     => 0,
					'credentials'   => null,
					'last_synced_at'=> null,
					'created_at'    => $now,
					'updated_at'    => $now,
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Fetch a channel row by slug.
	 *
	 * @param string $slug Channel slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;

		$table = SOM_DB::table( 'channels' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slug = %s LIMIT 1",
				$slug
			)
		);

		return $row ?: null;
	}

	/**
	 * Decrypted credentials array for a channel.
	 *
	 * @param string $slug Channel slug.
	 * @return array<string, mixed>
	 */
	public static function get_credentials( $slug ) {
		$row = self::get_by_slug( $slug );
		if ( ! $row || empty( $row->credentials ) ) {
			return array();
		}
		return SOM_Crypto::decrypt_json( $row->credentials );
	}

	/**
	 * Store encrypted credentials and mark channel active when tokens present.
	 *
	 * @param string               $slug        Channel slug.
	 * @param array<string, mixed> $credentials Token payload.
	 * @return bool
	 */
	public static function save_credentials( $slug, array $credentials ) {
		global $wpdb;

		self::ensure_rows();
		$row = self::get_by_slug( $slug );
		if ( ! $row ) {
			return false;
		}

		$encrypted = SOM_Crypto::encrypt_json( $credentials );
		$has_token = ! empty( $credentials['access_token'] );
		$now       = current_time( 'mysql', true );

		$updated = $wpdb->update(
			SOM_DB::table( 'channels' ),
			array(
				'credentials' => $encrypted,
				'is_active'   => $has_token ? 1 : 0,
				'updated_at'  => $now,
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Clear credentials and deactivate channel.
	 *
	 * @param string $slug Channel slug.
	 * @return bool
	 */
	public static function disconnect( $slug ) {
		global $wpdb;

		$row = self::get_by_slug( $slug );
		if ( ! $row ) {
			return false;
		}

		$updated = $wpdb->update(
			SOM_DB::table( 'channels' ),
			array(
				'credentials' => null,
				'is_active'   => 0,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Whether the channel has a usable access token stored.
	 *
	 * @param string $slug Channel slug.
	 * @return bool
	 */
	public static function is_connected( $slug ) {
		$creds = self::get_credentials( $slug );
		return ! empty( $creds['access_token'] );
	}

	/**
	 * Whether credentials are dummy (fixture) tokens.
	 *
	 * @param string $slug Channel slug.
	 * @return bool
	 */
	public static function is_dummy( $slug ) {
		$creds = self::get_credentials( $slug );
		return ! empty( $creds['dummy'] );
	}

	/**
	 * Update last successful order sync timestamp (UTC).
	 *
	 * @param string $slug Channel slug.
	 * @param string $datetime MySQL UTC datetime.
	 * @return bool
	 */
	public static function set_last_synced_at( $slug, $datetime ) {
		global $wpdb;

		$row = self::get_by_slug( $slug );
		if ( ! $row ) {
			return false;
		}

		$updated = $wpdb->update(
			SOM_DB::table( 'channels' ),
			array(
				'last_synced_at' => $datetime,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}
}
