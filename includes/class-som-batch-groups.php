<?php
/**
 * Batch group definitions (thank-you card, shipping label).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ensures and reads `wp_som_batch_groups` rows.
 */
class SOM_Batch_Groups {

	/**
	 * Default groups seeded on upgrade / activate.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function defaults() {
		$thankyou = wp_json_encode(
			array(
				'type'   => 'local',
				'action' => 'run_thankyou_card_script',
				'params' => array(
					'paper'        => 'white',
					'flower_color' => 'blush',
				),
			)
		);

		return array(
			array(
				'key'           => 'thank_you_card',
				'display_name'  => 'Thank-you card printing',
				'batch_size'    => 4,
				'action_type'   => 'script',
				'script_config' => $thankyou,
			),
			array(
				'key'           => 'shipping_label',
				'display_name'  => 'Shipping label grouping',
				'batch_size'    => 4,
				'action_type'   => 'manual_confirm',
				'script_config' => null,
			),
		);
	}

	/**
	 * Ensure default batch groups exist (idempotent).
	 *
	 * @return void
	 */
	public static function ensure_rows() {
		global $wpdb;

		$table = SOM_DB::table( 'batch_groups' );
		$now   = current_time( 'mysql', true );

		foreach ( self::defaults() as $row ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE group_key = %s LIMIT 1",
					$row['key']
				)
			);
			if ( $existing ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'group_key'     => $row['key'],
					'display_name'  => $row['display_name'],
					'batch_size'    => (int) $row['batch_size'],
					'action_type'   => $row['action_type'],
					'script_config' => $row['script_config'],
					'created_at'    => $now,
					'updated_at'    => $now,
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * @param string $key Group key.
	 * @return object|null
	 */
	public static function get_by_key( $key ) {
		global $wpdb;

		$table = SOM_DB::table( 'batch_groups' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE group_key = %s LIMIT 1",
				sanitize_key( (string) $key )
			)
		);

		return self::normalize_row( $row );
	}

	/**
	 * @param int $id Group PK.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = SOM_DB::table( 'batch_groups' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				(int) $id
			)
		);

		return self::normalize_row( $row );
	}

	/**
	 * @return array<int, object>
	 */
	public static function list_all() {
		global $wpdb;

		$table = SOM_DB::table( 'batch_groups' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'normalize_row' ), $rows );
	}

	/**
	 * Update editable fields (display_name, batch_size). Key and action_type stay fixed.
	 *
	 * @param int                  $id   Group PK.
	 * @param array<string, mixed> $data Fields.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$id    = (int) $id;
		$group = self::get( $id );
		if ( ! $group ) {
			return new WP_Error( 'som_batch_group_missing', __( 'Batch group not found.', 'order-machine' ) );
		}

		$display_name = isset( $data['display_name'] ) ? sanitize_text_field( (string) $data['display_name'] ) : (string) $group->display_name;
		$display_name = trim( $display_name );
		if ( '' === $display_name ) {
			return new WP_Error( 'som_batch_group_name', __( 'Display name is required.', 'order-machine' ) );
		}

		$batch_size = isset( $data['batch_size'] ) ? (int) $data['batch_size'] : (int) $group->batch_size;
		if ( $batch_size < 1 ) {
			return new WP_Error( 'som_batch_group_size', __( 'Batch size must be at least 1.', 'order-machine' ) );
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'batch_groups' ),
			array(
				'display_name' => $display_name,
				'batch_size'   => $batch_size,
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_batch_group_update', __( 'Could not update batch group.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Normalize row so callers can use ->key (maps from group_key).
	 *
	 * @param object|null $row DB row.
	 * @return object|null
	 */
	private static function normalize_row( $row ) {
		if ( ! $row ) {
			return null;
		}
		if ( isset( $row->group_key ) && ! isset( $row->key ) ) {
			$row->key = $row->group_key;
		}
		return $row;
	}

	/**
	 * Convert existing thank-you script steps to batch_group_id (idempotent).
	 *
	 * @return int Number of steps converted.
	 */
	public static function convert_thankyou_steps() {
		global $wpdb;

		self::ensure_rows();
		$group = self::get_by_key( 'thank_you_card' );
		if ( ! $group ) {
			return 0;
		}

		$steps_t = SOM_DB::table( 'workflow_steps' );
		$steps   = $wpdb->get_results(
			"SELECT id, script_config, batch_group_id FROM {$steps_t}"
		);
		if ( ! is_array( $steps ) ) {
			return 0;
		}

		$converted = 0;
		$now       = current_time( 'mysql', true );
		$group_id  = (int) $group->id;

		foreach ( $steps as $step ) {
			if ( ! empty( $step->batch_group_id ) ) {
				continue;
			}
			$raw = isset( $step->script_config ) ? (string) $step->script_config : '';
			if ( '' === $raw ) {
				continue;
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$action = isset( $decoded['action'] ) ? (string) $decoded['action'] : '';
			if ( 'run_thankyou_card_script' !== $action ) {
				continue;
			}

			$ok = $wpdb->update(
				$steps_t,
				array(
					'batch_group_id'          => $group_id,
					'requires_manual_confirm' => 0,
					'timer_seconds'           => null,
					'script_config'           => null,
					'updated_at'              => $now,
				),
				array( 'id' => (int) $step->id ),
				array( '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false !== $ok ) {
				++$converted;
			}
		}

		return $converted;
	}
}
