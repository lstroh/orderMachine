<?php
/**
 * Per-(workflow, material) cost ceiling goals (Sprint U3).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for `wp_som_workflow_material_goals`.
 */
class SOM_Workflow_Material_Goals {

	/**
	 * @param int $id Goal PK.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = SOM_DB::table( 'workflow_material_goals' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", (int) $id )
		);

		return $row ? $row : null;
	}

	/**
	 * Goals for one workflow template.
	 *
	 * @param int $workflow_template_id Template PK.
	 * @return array<int, object>
	 */
	public static function list_for_workflow( $workflow_template_id ) {
		global $wpdb;

		$goals_t     = SOM_DB::table( 'workflow_material_goals' );
		$materials_t = SOM_DB::table( 'materials' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.*, m.name AS material_name, m.unit AS material_unit
				FROM {$goals_t} g
				INNER JOIN {$materials_t} m ON m.id = g.material_id
				WHERE g.workflow_template_id = %d
				ORDER BY m.name ASC, g.id ASC",
				(int) $workflow_template_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Goals referencing one material (any workflow).
	 *
	 * @param int $material_id Material PK.
	 * @return array<int, object>
	 */
	public static function list_for_material( $material_id ) {
		global $wpdb;

		$goals_t     = SOM_DB::table( 'workflow_material_goals' );
		$templates_t = SOM_DB::table( 'workflow_templates' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.*, wt.name AS workflow_name
				FROM {$goals_t} g
				INNER JOIN {$templates_t} wt ON wt.id = g.workflow_template_id
				WHERE g.material_id = %d
				ORDER BY wt.name ASC, g.id ASC",
				(int) $material_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Create or replace a goal for a (workflow, material) pair.
	 *
	 * @param array<string, mixed> $data Fields.
	 * @return int|WP_Error Goal ID.
	 */
	public static function upsert( array $data ) {
		global $wpdb;

		$workflow_id = isset( $data['workflow_template_id'] ) ? (int) $data['workflow_template_id'] : 0;
		$material_id = isset( $data['material_id'] ) ? (int) $data['material_id'] : 0;

		if ( $workflow_id < 1 || ! SOM_Workflows::get( $workflow_id ) ) {
			return new WP_Error( 'som_goal_workflow', __( 'Workflow template is required.', 'order-machine' ) );
		}
		if ( $material_id < 1 || ! SOM_Materials::get( $material_id ) ) {
			return new WP_Error( 'som_goal_material', __( 'Material is required.', 'order-machine' ) );
		}

		$goal_cost = self::parse_positive_decimal( $data, 'goal_unit_cost', 4 );
		if ( is_wp_error( $goal_cost ) ) {
			return $goal_cost;
		}

		$threshold = 90.0;
		if ( array_key_exists( 'warning_threshold_percent', $data ) && '' !== trim( (string) $data['warning_threshold_percent'] ) ) {
			$raw = trim( (string) $data['warning_threshold_percent'] );
			if ( ! is_numeric( $raw ) || (float) $raw <= 0 || (float) $raw > 100 ) {
				return new WP_Error( 'som_goal_threshold', __( 'Warning threshold must be between 0 and 100.', 'order-machine' ) );
			}
			$threshold = (float) $raw;
		}

		$table = SOM_DB::table( 'workflow_material_goals' );
		$now   = current_time( 'mysql', true );

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE workflow_template_id = %d AND material_id = %d LIMIT 1",
				$workflow_id,
				$material_id
			)
		);

		if ( $existing_id > 0 ) {
			$ok = $wpdb->update(
				$table,
				array(
					'goal_unit_cost'             => $goal_cost,
					'warning_threshold_percent'  => number_format( $threshold, 2, '.', '' ),
					'updated_at'                 => $now,
				),
				array( 'id' => $existing_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $ok ) {
				return new WP_Error( 'som_goal_update', __( 'Could not update material goal.', 'order-machine' ) );
			}
			return $existing_id;
		}

		$ok = $wpdb->insert(
			$table,
			array(
				'workflow_template_id'      => $workflow_id,
				'material_id'               => $material_id,
				'goal_unit_cost'            => $goal_cost,
				'warning_threshold_percent' => number_format( $threshold, 2, '.', '' ),
				'created_at'                => $now,
				'updated_at'                => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return new WP_Error( 'som_goal_create', __( 'Could not create material goal.', 'order-machine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $id   Goal PK.
	 * @param array<string, mixed> $data Fields to update.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$row = self::get( (int) $id );
		if ( ! $row ) {
			return new WP_Error( 'som_goal_missing', __( 'Material goal not found.', 'order-machine' ) );
		}

		$fields  = array(
			'updated_at' => current_time( 'mysql', true ),
		);
		$formats = array( '%s' );

		if ( array_key_exists( 'goal_unit_cost', $data ) ) {
			$goal_cost = self::parse_positive_decimal( $data, 'goal_unit_cost', 4 );
			if ( is_wp_error( $goal_cost ) ) {
				return $goal_cost;
			}
			$fields['goal_unit_cost'] = $goal_cost;
			$formats[]                = '%s';
		}

		if ( array_key_exists( 'warning_threshold_percent', $data ) ) {
			$raw = trim( (string) $data['warning_threshold_percent'] );
			if ( ! is_numeric( $raw ) || (float) $raw <= 0 || (float) $raw > 100 ) {
				return new WP_Error( 'som_goal_threshold', __( 'Warning threshold must be between 0 and 100.', 'order-machine' ) );
			}
			$fields['warning_threshold_percent'] = number_format( (float) $raw, 2, '.', '' );
			$formats[]                           = '%s';
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'workflow_material_goals' ),
			$fields,
			array( 'id' => (int) $id ),
			$formats,
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_goal_update', __( 'Could not update material goal.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * @param int $id Goal PK.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id < 1 || ! self::get( $id ) ) {
			return new WP_Error( 'som_goal_missing', __( 'Material goal not found.', 'order-machine' ) );
		}

		$ok = $wpdb->delete(
			SOM_DB::table( 'workflow_material_goals' ),
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_goal_delete', __( 'Could not delete material goal.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Replace all goals for a workflow from editor rows.
	 *
	 * Empty material rows are skipped. Goals not present in $rows are deleted.
	 *
	 * @param int                      $workflow_template_id Template PK.
	 * @param array<int, array<string, mixed>> $rows Each: material_id, goal_unit_cost, warning_threshold_percent.
	 * @return true|WP_Error
	 */
	public static function sync_for_workflow( $workflow_template_id, array $rows ) {
		$workflow_template_id = (int) $workflow_template_id;
		if ( $workflow_template_id < 1 || ! SOM_Workflows::get( $workflow_template_id ) ) {
			return new WP_Error( 'som_goal_workflow', __( 'Workflow template is required.', 'order-machine' ) );
		}

		$keep_material_ids = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$material_id = isset( $row['material_id'] ) ? (int) $row['material_id'] : 0;
			$goal_raw    = isset( $row['goal_unit_cost'] ) ? trim( (string) $row['goal_unit_cost'] ) : '';
			if ( $material_id < 1 || '' === $goal_raw ) {
				continue;
			}
			if ( isset( $keep_material_ids[ $material_id ] ) ) {
				return new WP_Error( 'som_goal_dup', __( 'Each material can only have one goal per workflow.', 'order-machine' ) );
			}
			$result = self::upsert(
				array(
					'workflow_template_id'      => $workflow_template_id,
					'material_id'               => $material_id,
					'goal_unit_cost'            => $goal_raw,
					'warning_threshold_percent' => isset( $row['warning_threshold_percent'] ) ? $row['warning_threshold_percent'] : 90,
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$keep_material_ids[ $material_id ] = true;
		}

		foreach ( self::list_for_workflow( $workflow_template_id ) as $existing ) {
			if ( ! isset( $keep_material_ids[ (int) $existing->material_id ] ) ) {
				$deleted = self::delete( (int) $existing->id );
				if ( is_wp_error( $deleted ) ) {
					return $deleted;
				}
			}
		}

		return true;
	}

	/**
	 * Evaluate alert level for a goal against a weighted-average cost.
	 *
	 * @param object $goal Goal row.
	 * @param float  $wa   Current weighted average.
	 * @return string ''|approaching|over
	 */
	public static function alert_level( $goal, $wa ) {
		$goal_cost = (float) $goal->goal_unit_cost;
		if ( $goal_cost <= 0 ) {
			return '';
		}

		$wa = (float) $wa;
		if ( $wa >= $goal_cost ) {
			return 'over';
		}

		$pct = isset( $goal->warning_threshold_percent ) ? (float) $goal->warning_threshold_percent : 90.0;
		if ( $pct <= 0 ) {
			$pct = 90.0;
		}
		$warn_at = $goal_cost * ( $pct / 100.0 );
		if ( $wa >= $warn_at ) {
			return 'approaching';
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $data   Source.
	 * @param string               $key    Field.
	 * @param int                  $places Decimals.
	 * @return string|WP_Error
	 */
	private static function parse_positive_decimal( array $data, $key, $places = 4 ) {
		$raw = isset( $data[ $key ] ) ? trim( (string) $data[ $key ] ) : '';
		if ( '' === $raw || ! is_numeric( $raw ) || (float) $raw <= 0 ) {
			return new WP_Error( 'som_goal_cost', __( 'Goal unit cost must be greater than zero.', 'order-machine' ) );
		}
		return number_format( (float) $raw, (int) $places, '.', '' );
	}
}
