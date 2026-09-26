<?php
/**
 * Workflow step instructions (defaults + per-product overrides).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve and persist plain-text step instructions (Package 4 / UP4-S1).
 */
class SOM_Step_Instructions {

	/**
	 * Soft max length for instruction text.
	 */
	const MAX_LENGTH = 5000;

	/**
	 * Effective instructions for a product + step (override → step default → null).
	 *
	 * @param int $product_id Product PK (0 = defaults only).
	 * @param int $step_id    Workflow step PK.
	 * @return string|null Non-empty text or null when nothing to show.
	 */
	public static function effective( $product_id, $step_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		$step_id    = (int) $step_id;
		if ( $step_id < 1 ) {
			return null;
		}

		if ( $product_id > 0 ) {
			$override_t = SOM_DB::table( 'product_step_instructions' );
			$override   = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT instructions FROM {$override_t} WHERE product_id = %d AND workflow_step_id = %d LIMIT 1",
					$product_id,
					$step_id
				)
			);
			if ( is_string( $override ) ) {
				$override = trim( $override );
				if ( '' !== $override ) {
					return $override;
				}
			}
		}

		$steps_t = SOM_DB::table( 'workflow_steps' );
		$default = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT instructions FROM {$steps_t} WHERE id = %d LIMIT 1",
				$step_id
			)
		);
		if ( ! is_string( $default ) ) {
			return null;
		}
		$default = trim( $default );
		return '' !== $default ? $default : null;
	}

	/**
	 * Sanitize instruction text (empty string allowed = clear).
	 *
	 * @param mixed $raw Raw input.
	 * @return string|WP_Error Sanitized text, or WP_Error when over max length.
	 */
	public static function sanitize( $raw ) {
		$text = sanitize_textarea_field( (string) $raw );
		$text = trim( $text );
		if ( strlen( $text ) > self::MAX_LENGTH ) {
			return new WP_Error(
				'som_instructions_length',
				sprintf(
					/* translators: %d: max character count */
					__( 'Instructions cannot exceed %d characters.', 'order-machine' ),
					self::MAX_LENGTH
				)
			);
		}
		return $text;
	}

	/**
	 * Overrides keyed by workflow_step_id for a product.
	 *
	 * @param int $product_id Product PK.
	 * @return array<int, string>
	 */
	public static function get_overrides_for_product( $product_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return array();
		}

		$table = SOM_DB::table( 'product_step_instructions' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT workflow_step_id, instructions FROM {$table} WHERE product_id = %d",
				$product_id
			)
		);

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (int) $row->workflow_step_id ] = (string) $row->instructions;
			}
		}
		return $out;
	}

	/**
	 * Replace overrides for a product from form rows (step_id => text).
	 *
	 * Empty text deletes the override. Only steps belonging to $template_id are accepted.
	 * Then removes any remaining overrides whose steps are not on that template.
	 *
	 * @param int                  $product_id  Product PK.
	 * @param int                  $template_id Current workflow template PK (0 = wipe all).
	 * @param array<int|string, mixed> $rows    Map of workflow_step_id => instructions text.
	 * @return true|WP_Error
	 */
	public static function sync_for_product( $product_id, $template_id, array $rows ) {
		$product_id  = (int) $product_id;
		$template_id = (int) $template_id;
		if ( $product_id < 1 ) {
			return new WP_Error( 'som_product_missing', __( 'Product not found.', 'order-machine' ) );
		}

		if ( $template_id < 1 ) {
			self::delete_all_for_product( $product_id );
			return true;
		}

		$allowed = array();
		foreach ( SOM_Workflows::get_steps( $template_id ) as $step ) {
			$allowed[ (int) $step->id ] = true;
		}

		foreach ( $rows as $step_id => $raw ) {
			$step_id = (int) $step_id;
			if ( $step_id < 1 || ! isset( $allowed[ $step_id ] ) ) {
				continue;
			}
			$text = self::sanitize( $raw );
			if ( is_wp_error( $text ) ) {
				return $text;
			}
			if ( '' === $text ) {
				self::delete_override( $product_id, $step_id );
				continue;
			}
			$result = self::upsert_override( $product_id, $step_id, $text );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		self::delete_orphans_for_product( $product_id, $template_id );
		return true;
	}

	/**
	 * Delete overrides for steps not on the product's current template.
	 *
	 * @param int $product_id  Product PK.
	 * @param int $template_id Workflow template PK (0 deletes all).
	 * @return void
	 */
	public static function delete_orphans_for_product( $product_id, $template_id ) {
		global $wpdb;

		$product_id  = (int) $product_id;
		$template_id = (int) $template_id;
		if ( $product_id < 1 ) {
			return;
		}

		if ( $template_id < 1 ) {
			self::delete_all_for_product( $product_id );
			return;
		}

		$override_t = SOM_DB::table( 'product_step_instructions' );
		$steps_t    = SOM_DB::table( 'workflow_steps' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from helper.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE o FROM {$override_t} o
				LEFT JOIN {$steps_t} s ON s.id = o.workflow_step_id AND s.workflow_template_id = %d
				WHERE o.product_id = %d AND s.id IS NULL",
				$template_id,
				$product_id
			)
		);
	}

	/**
	 * @param int $product_id Product PK.
	 * @return void
	 */
	public static function delete_all_for_product( $product_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return;
		}

		$wpdb->delete(
			SOM_DB::table( 'product_step_instructions' ),
			array( 'product_id' => $product_id ),
			array( '%d' )
		);
	}

	/**
	 * When a workflow step row is removed, drop all product overrides for it.
	 *
	 * @param int $step_id Workflow step PK.
	 * @return void
	 */
	public static function delete_for_step( $step_id ) {
		global $wpdb;

		$step_id = (int) $step_id;
		if ( $step_id < 1 ) {
			return;
		}

		$wpdb->delete(
			SOM_DB::table( 'product_step_instructions' ),
			array( 'workflow_step_id' => $step_id ),
			array( '%d' )
		);
	}

	/**
	 * @param int    $product_id Product PK.
	 * @param int    $step_id    Step PK.
	 * @param string $text       Non-empty instructions.
	 * @return true|WP_Error
	 */
	private static function upsert_override( $product_id, $step_id, $text ) {
		global $wpdb;

		$table = SOM_DB::table( 'product_step_instructions' );
		$now   = current_time( 'mysql', true );
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND workflow_step_id = %d LIMIT 1",
				$product_id,
				$step_id
			)
		);

		if ( $existing > 0 ) {
			$updated = $wpdb->update(
				$table,
				array(
					'instructions' => $text,
					'updated_at'   => $now,
				),
				array( 'id' => $existing ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				return new WP_Error( 'som_instructions_update', __( 'Could not update step instructions.', 'order-machine' ) );
			}
			return true;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'product_id'       => $product_id,
				'workflow_step_id' => $step_id,
				'instructions'     => $text,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new WP_Error( 'som_instructions_create', __( 'Could not save step instructions.', 'order-machine' ) );
		}
		return true;
	}

	/**
	 * @param int $product_id Product PK.
	 * @param int $step_id    Step PK.
	 * @return void
	 */
	private static function delete_override( $product_id, $step_id ) {
		global $wpdb;

		$wpdb->delete(
			SOM_DB::table( 'product_step_instructions' ),
			array(
				'product_id'       => (int) $product_id,
				'workflow_step_id' => (int) $step_id,
			),
			array( '%d', '%d' )
		);
	}
}
