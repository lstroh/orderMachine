<?php
/**
 * Optional workflow step task lists (defaults + per-product overrides).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve and persist optional step tasks (Package 9 / UP9-S4).
 *
 * Tasks never gate Mark done. Confirmation checklists remain the hard gates.
 */
class SOM_Step_Tasks {

	/**
	 * Soft max tasks per list.
	 */
	const MAX_TASKS = 50;

	/**
	 * Soft max label length.
	 */
	const MAX_LABEL_LENGTH = 200;

	/**
	 * Decode a task list JSON string or array into normalized rows.
	 *
	 * @param mixed $raw JSON string, array, or null.
	 * @return array<int, array{id: string, label: string}>
	 */
	public static function decode_list( $raw ) {
		if ( is_array( $raw ) ) {
			$list = $raw;
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$list    = is_array( $decoded ) ? $decoded : array();
		} else {
			return array();
		}

		$out = array();
		foreach ( $list as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$label = trim( $label );
			if ( '' === $label ) {
				continue;
			}
			if ( strlen( $label ) > self::MAX_LABEL_LENGTH ) {
				$label = substr( $label, 0, self::MAX_LABEL_LENGTH );
			}
			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
			if ( '' === $id ) {
				$id = 't' . ( (int) $index + 1 );
			}
			$out[] = array(
				'id'    => $id,
				'label' => $label,
			);
			if ( count( $out ) >= self::MAX_TASKS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Encode a normalized task list to JSON (null when empty).
	 *
	 * @param array<int, array{id?: string, label?: string}> $tasks Tasks.
	 * @return string|null
	 */
	public static function encode_list( array $tasks ) {
		$normalized = self::decode_list( $tasks );
		if ( empty( $normalized ) ) {
			return null;
		}
		return wp_json_encode( $normalized );
	}

	/**
	 * Sanitize task rows from a form (associative list of id/label).
	 *
	 * Empty labels are dropped. Missing ids get stable generated keys.
	 *
	 * @param mixed $rows Raw form rows.
	 * @return array<int, array{id: string, label: string}>|WP_Error
	 */
	public static function sanitize_from_form( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out   = array();
		$seen  = array();
		$index = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$label = trim( $label );
			if ( '' === $label ) {
				continue;
			}
			if ( strlen( $label ) > self::MAX_LABEL_LENGTH ) {
				return new WP_Error(
					'som_task_label_length',
					sprintf(
						/* translators: %d: max character count */
						__( 'Task labels cannot exceed %d characters.', 'order-machine' ),
						self::MAX_LABEL_LENGTH
					)
				);
			}
			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = self::generate_id( $index );
			}
			$seen[ $id ] = true;
			$out[]       = array(
				'id'    => $id,
				'label' => $label,
			);
			++$index;
			if ( count( $out ) > self::MAX_TASKS ) {
				return new WP_Error(
					'som_task_limit',
					sprintf(
						/* translators: %d: max task count */
						__( 'A step cannot have more than %d tasks.', 'order-machine' ),
						self::MAX_TASKS
					)
				);
			}
		}
		return $out;
	}

	/**
	 * Generate a stable-looking task id.
	 *
	 * @param int $index Row index hint.
	 * @return string
	 */
	public static function generate_id( $index = 0 ) {
		return 't' . ( (int) $index + 1 ) . '_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 );
	}

	/**
	 * Effective task list for a product + step (override → step default → none).
	 *
	 * Pass product_id 0 for pack (template only — O13).
	 *
	 * @param int $product_id Product PK (0 = defaults only).
	 * @param int $step_id    Workflow step PK.
	 * @return array<int, array{id: string, label: string}>
	 */
	public static function effective( $product_id, $step_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		$step_id    = (int) $step_id;
		if ( $step_id < 1 ) {
			return array();
		}

		if ( $product_id > 0 ) {
			$override_t = SOM_DB::table( 'product_step_tasks' );
			$override   = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT task_list_json FROM {$override_t} WHERE product_id = %d AND workflow_step_id = %d LIMIT 1",
					$product_id,
					$step_id
				)
			);
			if ( is_string( $override ) && '' !== trim( $override ) ) {
				$list = self::decode_list( $override );
				if ( ! empty( $list ) ) {
					return $list;
				}
			}
		}

		$steps_t = SOM_DB::table( 'workflow_steps' );
		$default = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT task_list_json FROM {$steps_t} WHERE id = %d LIMIT 1",
				$step_id
			)
		);
		return self::decode_list( $default );
	}

	/**
	 * Overrides keyed by workflow_step_id for a product.
	 *
	 * @param int $product_id Product PK.
	 * @return array<int, array<int, array{id: string, label: string}>>
	 */
	public static function get_overrides_for_product( $product_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return array();
		}

		$table = SOM_DB::table( 'product_step_tasks' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT workflow_step_id, task_list_json FROM {$table} WHERE product_id = %d",
				$product_id
			)
		);

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$list = self::decode_list( $row->task_list_json );
				if ( ! empty( $list ) ) {
					$out[ (int) $row->workflow_step_id ] = $list;
				}
			}
		}
		return $out;
	}

	/**
	 * Replace task overrides for a product from form rows (step_id => task rows).
	 *
	 * Empty list deletes the override (full replace — O12). Only steps on $template_id accepted.
	 *
	 * @param int                      $product_id  Product PK.
	 * @param int                      $template_id Current workflow template PK (0 = wipe all).
	 * @param array<int|string, mixed> $rows        Map of workflow_step_id => task row arrays.
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
			$tasks = self::sanitize_from_form( $raw );
			if ( is_wp_error( $tasks ) ) {
				return $tasks;
			}
			if ( empty( $tasks ) ) {
				self::delete_override( $product_id, $step_id );
				continue;
			}
			$result = self::upsert_override( $product_id, $step_id, $tasks );
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

		$override_t = SOM_DB::table( 'product_step_tasks' );
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
			SOM_DB::table( 'product_step_tasks' ),
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
			SOM_DB::table( 'product_step_tasks' ),
			array( 'workflow_step_id' => $step_id ),
			array( '%d' )
		);
	}

	/**
	 * Decode task_checks_json from a progress row into id => bool map.
	 *
	 * @param object|string|array|null $progress_or_json Progress row or raw JSON.
	 * @return array<string, bool>
	 */
	public static function decode_checks( $progress_or_json ) {
		$raw = $progress_or_json;
		if ( is_object( $progress_or_json ) ) {
			$raw = isset( $progress_or_json->task_checks_json ) ? $progress_or_json->task_checks_json : null;
		}
		if ( is_array( $raw ) ) {
			$map = $raw;
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$map     = is_array( $decoded ) ? $decoded : array();
		} else {
			return array();
		}

		$out = array();
		foreach ( $map as $id => $val ) {
			$key = sanitize_key( (string) $id );
			if ( '' === $key ) {
				continue;
			}
			$out[ $key ] = ! empty( $val );
		}
		return $out;
	}

	/**
	 * Whether a task id is ticked in the checks map.
	 *
	 * @param array<string, bool> $checks Checks map.
	 * @param string              $task_id Task id.
	 * @return bool
	 */
	public static function is_checked( array $checks, $task_id ) {
		$key = sanitize_key( (string) $task_id );
		return '' !== $key && ! empty( $checks[ $key ] );
	}

	/**
	 * Persist task ticks for a make or pack progress row.
	 *
	 * Never affects can_mark_done (O11).
	 *
	 * @param int                  $order_id         Order PK.
	 * @param int                  $workflow_step_id Step PK.
	 * @param array<string, mixed> $input            Posted ticks keyed by task id.
	 * @param int                  $order_item_id    Optional order item PK for make lines.
	 * @return true|WP_Error
	 */
	public static function save_for_order( $order_id, $workflow_step_id, array $input, $order_item_id = 0 ) {
		global $wpdb;

		$order_id         = (int) $order_id;
		$workflow_step_id = (int) $workflow_step_id;
		$order_item_id    = (int) $order_item_id;

		$order = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}
		if ( ! empty( $order->is_cancelled ) ) {
			return new WP_Error( 'som_order_cancelled', __( 'Cancelled orders cannot update tasks.', 'order-machine' ) );
		}

		if ( $workflow_step_id < 1 ) {
			return new WP_Error( 'som_step_missing', __( 'Workflow step not found.', 'order-machine' ) );
		}

		$product_id = 0;
		if ( $order_item_id > 0 ) {
			$item = null;
			if ( ! empty( $order->items ) && is_array( $order->items ) ) {
				foreach ( $order->items as $candidate ) {
					if ( (int) $candidate->id === $order_item_id ) {
						$item = $candidate;
						break;
					}
				}
			}
			if ( ! $item ) {
				return new WP_Error( 'som_item_missing', __( 'Order line not found.', 'order-machine' ) );
			}
			$product_id = ! empty( $item->product_id ) ? (int) $item->product_id : 0;
			$table      = SOM_DB::table( 'order_item_step_progress' );
			$progress   = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE order_id = %d AND order_item_id = %d AND workflow_step_id = %d LIMIT 1",
					$order_id,
					$order_item_id,
					$workflow_step_id
				)
			);
		} else {
			$table    = SOM_DB::table( 'order_step_progress' );
			$progress = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE order_id = %d AND workflow_step_id = %d LIMIT 1",
					$order_id,
					$workflow_step_id
				)
			);
		}

		if ( ! $progress ) {
			return new WP_Error( 'som_no_progress', __( 'No progress row for this step.', 'order-machine' ) );
		}

		$tasks = self::effective( $product_id, $workflow_step_id );
		if ( empty( $tasks ) ) {
			return new WP_Error( 'som_no_tasks', __( 'This step has no task list.', 'order-machine' ) );
		}

		$allowed = array();
		foreach ( $tasks as $task ) {
			$allowed[ $task['id'] ] = true;
		}

		$checks = array();
		foreach ( $input as $id => $val ) {
			$key = sanitize_key( (string) $id );
			if ( '' === $key || ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			if ( ! empty( $val ) ) {
				$checks[ $key ] = true;
			}
		}

		$json = empty( $checks ) ? null : wp_json_encode( $checks );
		$ok   = $wpdb->update(
			$table,
			array( 'task_checks_json' => $json ),
			array( 'id' => (int) $progress->id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_tasks_save', __( 'Could not save task ticks.', 'order-machine' ) );
		}
		return true;
	}

	/**
	 * @param int                                              $product_id Product PK.
	 * @param int                                              $step_id    Step PK.
	 * @param array<int, array{id: string, label: string}> $tasks      Non-empty list.
	 * @return true|WP_Error
	 */
	private static function upsert_override( $product_id, $step_id, array $tasks ) {
		global $wpdb;

		$table = SOM_DB::table( 'product_step_tasks' );
		$json  = self::encode_list( $tasks );
		if ( null === $json ) {
			self::delete_override( $product_id, $step_id );
			return true;
		}

		$now      = current_time( 'mysql', true );
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
					'task_list_json' => $json,
					'updated_at'     => $now,
				),
				array( 'id' => $existing ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				return new WP_Error( 'som_tasks_update', __( 'Could not update step tasks.', 'order-machine' ) );
			}
			return true;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'product_id'       => $product_id,
				'workflow_step_id' => $step_id,
				'task_list_json'   => $json,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new WP_Error( 'som_tasks_create', __( 'Could not save step tasks.', 'order-machine' ) );
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
			SOM_DB::table( 'product_step_tasks' ),
			array(
				'product_id'       => (int) $product_id,
				'workflow_step_id' => (int) $step_id,
			),
			array( '%d', '%d' )
		);
	}
}
