<?php
/**
 * Workflow engine — assign templates, advance steps, timers, script dispatch.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order workflow state machine (manual confirm + timer + script/API/n8n).
 */
class SOM_Workflow_Engine {

	const HOOK_SCRIPT_ATTEMPT = 'som_script_attempt';

	/**
	 * Assign workflow progress for a newly created order (primary product rule).
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error
	 */
	public static function assign_on_create( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		if ( ! empty( $order->is_cancelled ) ) {
			return true;
		}

		if ( self::has_progress( $order_id ) ) {
			return true;
		}

		$product_id = self::primary_product_id( $order );
		if ( ! $product_id ) {
			return true;
		}

		$products_t  = SOM_DB::table( 'products' );
		$template_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT workflow_template_id FROM {$products_t} WHERE id = %d LIMIT 1",
				$product_id
			)
		);

		if ( $template_id < 1 ) {
			return true;
		}

		$steps = SOM_Workflows::get_steps( $template_id );
		if ( empty( $steps ) ) {
			return true;
		}

		$now        = current_time( 'mysql', true );
		$progress_t = SOM_DB::table( 'order_step_progress' );

		foreach ( $steps as $step ) {
			$inserted = $wpdb->insert(
				$progress_t,
				array(
					'order_id'         => $order_id,
					'workflow_step_id' => (int) $step->id,
					'status'           => 'pending',
					'timer_ends_at'    => null,
					'retry_count'      => 0,
					'last_error'       => null,
					'started_at'       => null,
					'completed_at'     => null,
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_progress_create', __( 'Could not create workflow progress rows.', 'order-machine' ) );
			}
		}

		$first_step_id = (int) $steps[0]->id;
		$wpdb->update(
			SOM_DB::table( 'orders' ),
			array(
				'current_step_id' => $first_step_id,
				'is_complete'     => 0,
				'updated_at'      => $now,
			),
			array( 'id' => $order_id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);

		return self::enter_step( $order_id, $first_step_id );
	}

	/**
	 * Mark the current step done and advance (or complete the order).
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error
	 */
	public static function mark_done( $order_id ) {
		$order_id = (int) $order_id;
		self::unlock_elapsed_for_order( $order_id );

		$order = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		if ( ! empty( $order->is_cancelled ) ) {
			return new WP_Error( 'som_order_cancelled', __( 'Cancelled orders cannot advance.', 'order-machine' ) );
		}

		if ( ! empty( $order->is_complete ) ) {
			return new WP_Error( 'som_order_complete', __( 'Order workflow is already complete.', 'order-machine' ) );
		}

		$current_step_id = (int) $order->current_step_id;
		if ( $current_step_id < 1 ) {
			return new WP_Error( 'som_no_workflow', __( 'This order has no workflow assigned.', 'order-machine' ) );
		}

		$progress = self::get_progress_for_step( $order_id, $current_step_id );
		$step     = self::get_step( $current_step_id );
		if ( ! $progress || ! $step ) {
			return new WP_Error( 'som_step_missing', __( 'Current workflow step not found.', 'order-machine' ) );
		}

		if ( ! self::can_mark_done( $progress, $step ) ) {
			return new WP_Error( 'som_step_locked', __( 'This step cannot be marked done yet.', 'order-machine' ) );
		}

		return self::complete_current_and_advance( $order_id, $progress, $step );
	}

	/**
	 * Manual retry after script error (resets retry budget and re-runs).
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error
	 */
	public static function retry_script( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		if ( ! empty( $order->is_cancelled ) ) {
			return new WP_Error( 'som_order_cancelled', __( 'Cancelled orders cannot retry scripts.', 'order-machine' ) );
		}

		$current_step_id = (int) $order->current_step_id;
		if ( $current_step_id < 1 ) {
			return new WP_Error( 'som_no_workflow', __( 'This order has no workflow assigned.', 'order-machine' ) );
		}

		$progress = self::get_progress_for_step( $order_id, $current_step_id );
		$step     = self::get_step( $current_step_id );
		if ( ! $progress || ! $step ) {
			return new WP_Error( 'som_step_missing', __( 'Current workflow step not found.', 'order-machine' ) );
		}

		if ( 'error' !== (string) $progress->status && 'waiting_script' !== (string) $progress->status ) {
			return new WP_Error( 'som_not_retriable', __( 'Current step is not waiting on a script retry.', 'order-machine' ) );
		}

		if ( ! SOM_Script_Dispatch::has_script( $step ) ) {
			return new WP_Error( 'som_no_script', __( 'Current step has no script_config.', 'order-machine' ) );
		}

		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'      => 'waiting_script',
				'retry_count' => 0,
				'last_error'  => null,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		$progress = self::get_progress_for_step( $order_id, $current_step_id );
		return self::attempt_script( $order_id, $step, $progress );
	}

	/**
	 * Complete a waiting_script step from an external callback (success or failure).
	 *
	 * @param int         $order_id    Order PK.
	 * @param int         $progress_id Progress PK.
	 * @param bool        $success     Whether the external job succeeded.
	 * @param string|null $error       Error message when unsuccessful.
	 * @return true|WP_Error
	 */
	public static function complete_from_callback( $order_id, $progress_id, $success, $error = null ) {
		global $wpdb;

		$order_id    = (int) $order_id;
		$progress_id = (int) $progress_id;
		$order       = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		$progress = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . SOM_DB::table( 'order_step_progress' ) . ' WHERE id = %d AND order_id = %d LIMIT 1',
				$progress_id,
				$order_id
			)
		);
		if ( ! $progress ) {
			return new WP_Error( 'som_progress_missing', __( 'Progress row not found.', 'order-machine' ) );
		}

		if ( 'done' === (string) $progress->status ) {
			return true;
		}

		if ( (int) $order->current_step_id !== (int) $progress->workflow_step_id ) {
			return new WP_Error( 'som_not_current', __( 'Callback does not match the current workflow step.', 'order-machine' ) );
		}

		if ( ! in_array( (string) $progress->status, array( 'waiting_script', 'error' ), true ) ) {
			return new WP_Error( 'som_bad_status', __( 'Step is not waiting for a script callback.', 'order-machine' ) );
		}

		$step = self::get_step( (int) $progress->workflow_step_id );
		if ( ! $step ) {
			return new WP_Error( 'som_step_missing', __( 'Workflow step not found.', 'order-machine' ) );
		}

		if ( $success ) {
			return self::on_script_success( $order_id, $step, $progress );
		}

		$message = $error ? (string) $error : __( 'External workflow reported failure.', 'order-machine' );
		return self::on_script_failure( $order_id, $step, $progress, $message, false );
	}

	/**
	 * Cron / single-event: attempt a script for a progress row.
	 *
	 * @param int $progress_id Progress PK.
	 * @return void
	 */
	public static function attempt_script_by_progress_id( $progress_id ) {
		global $wpdb;

		$progress_id = (int) $progress_id;
		$progress    = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . SOM_DB::table( 'order_step_progress' ) . ' WHERE id = %d LIMIT 1',
				$progress_id
			)
		);
		if ( ! $progress || 'waiting_script' !== (string) $progress->status ) {
			return;
		}

		$order = SOM_Orders::get( (int) $progress->order_id );
		if ( ! $order || ! empty( $order->is_cancelled ) || ! empty( $order->is_complete ) ) {
			return;
		}
		if ( (int) $order->current_step_id !== (int) $progress->workflow_step_id ) {
			return;
		}

		$step = self::get_step( (int) $progress->workflow_step_id );
		if ( ! $step || ! SOM_Script_Dispatch::has_script( $step ) ) {
			return;
		}

		self::attempt_script( (int) $progress->order_id, $step, $progress );
	}

	/**
	 * Cron tick: unlock elapsed timers + scan waiting_script rows due for retry.
	 *
	 * @return array{unlocked:int,scripts:int}
	 */
	public static function tick() {
		global $wpdb;

		$progress_t = SOM_DB::table( 'order_step_progress' );
		$orders_t   = SOM_DB::table( 'orders' );
		$now        = current_time( 'mysql', true );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id AS progress_id, p.order_id, p.workflow_step_id
				FROM {$progress_t} p
				INNER JOIN {$orders_t} o ON o.id = p.order_id
				WHERE p.status = 'waiting_timer'
					AND p.timer_ends_at IS NOT NULL
					AND p.timer_ends_at <= %s
					AND o.is_complete = 0
					AND o.current_step_id = p.workflow_step_id",
				$now
			)
		);

		$unlocked = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$order = SOM_Orders::get( (int) $row->order_id );
				if ( ! $order || ! empty( $order->is_cancelled ) ) {
					continue;
				}

				$step = self::get_step( (int) $row->workflow_step_id );
				if ( ! $step ) {
					continue;
				}

				if ( SOM_Script_Dispatch::has_script( $step ) ) {
					$wpdb->update(
						$progress_t,
						array(
							'status'        => 'waiting_script',
							'timer_ends_at' => null,
						),
						array( 'id' => (int) $row->progress_id ),
						array( '%s', '%s' ),
						array( '%d' )
					);
					$progress = self::get_progress_for_step( (int) $row->order_id, (int) $row->workflow_step_id );
					if ( $progress ) {
						self::attempt_script( (int) $row->order_id, $step, $progress );
					}
					++$unlocked;
				} else {
					$updated = $wpdb->update(
						$progress_t,
						array( 'status' => 'in_progress' ),
						array( 'id' => (int) $row->progress_id ),
						array( '%s' ),
						array( '%d' )
					);
					if ( false !== $updated ) {
						++$unlocked;
					}
				}
			}
		}

		$scripts = 0;
		$waiting = $wpdb->get_results(
			"SELECT p.id
			FROM {$progress_t} p
			INNER JOIN {$orders_t} o ON o.id = p.order_id
			WHERE p.status = 'waiting_script'
				AND o.is_complete = 0
				AND o.current_step_id = p.workflow_step_id
				AND (p.timer_ends_at IS NULL OR p.timer_ends_at <= UTC_TIMESTAMP())"
		);
		if ( is_array( $waiting ) ) {
			foreach ( $waiting as $row ) {
				// Skip rows parked for async callback (timer_ends_at far future sentinel unused —
				// async wait uses last_error prefix).
				$full = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$progress_t} WHERE id = %d LIMIT 1",
						(int) $row->id
					)
				);
				if ( $full && is_string( $full->last_error ) && 0 === strpos( $full->last_error, 'waiting_callback:' ) ) {
					continue;
				}
				self::attempt_script_by_progress_id( (int) $row->id );
				++$scripts;
			}
		}

		$batches = SOM_Batches::process_due_retries();

		return array(
			'unlocked' => $unlocked,
			'scripts'  => $scripts,
			'batches'  => $batches,
		);
	}

	/**
	 * Complete a waiting_batch (or error) member step and advance that order.
	 *
	 * Used when a batch finishes successfully (script or mark-done).
	 *
	 * @param int $order_id Order PK.
	 * @param int $step_id  Workflow step PK for this member.
	 * @return true|WP_Error
	 */
	public static function complete_batch_member( $order_id, $step_id ) {
		$order_id = (int) $order_id;
		$step_id  = (int) $step_id;

		$order = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		// Cancelled orders stay in the batch but do not advance.
		if ( ! empty( $order->is_cancelled ) ) {
			return true;
		}

		if ( ! empty( $order->is_complete ) ) {
			return true;
		}

		$progress = self::get_progress_for_step( $order_id, $step_id );
		$step     = self::get_step( $step_id );
		if ( ! $progress || ! $step ) {
			return new WP_Error( 'som_step_missing', __( 'Workflow step not found.', 'order-machine' ) );
		}

		$status = (string) $progress->status;
		if ( ! in_array( $status, array( 'waiting_batch', 'error' ), true ) ) {
			if ( 'done' === $status ) {
				return true;
			}
			return new WP_Error( 'som_not_batch', __( 'Order is not waiting on this batch step.', 'order-machine' ) );
		}

		if ( (int) $order->current_step_id !== $step_id ) {
			return new WP_Error( 'som_not_current', __( 'Batch step is not the order current step.', 'order-machine' ) );
		}

		return self::complete_current_and_advance( $order_id, $progress, $step );
	}

	/**
	 * Whether Mark done is allowed for the current progress + step definition.
	 *
	 * @param object $progress Progress row.
	 * @param object $step     Workflow step row.
	 * @return bool
	 */
	public static function can_mark_done( $progress, $step ) {
		if ( ! $progress || ! $step ) {
			return false;
		}

		if ( 'in_progress' !== (string) $progress->status ) {
			return false;
		}

		$timer = isset( $step->timer_seconds ) ? (int) $step->timer_seconds : 0;
		if ( $timer > 0 && ! empty( $progress->timer_ends_at ) && ! self::timer_elapsed( $progress->timer_ends_at ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether Retry now should show.
	 *
	 * @param object $progress Progress row.
	 * @param object $step     Step row.
	 * @return bool
	 */
	public static function can_retry_script( $progress, $step ) {
		if ( ! $progress || ! $step || ! SOM_Script_Dispatch::has_script( $step ) ) {
			return false;
		}
		$status = (string) $progress->status;
		if ( 'error' === $status ) {
			return true;
		}
		if ( 'waiting_script' !== $status ) {
			return false;
		}
		// Allow retry after a failed attempt or while waiting on async callback.
		return ! empty( $progress->last_error ) || (int) $progress->retry_count > 0;
	}

	/**
	 * Progress rows for an order, joined with step metadata, in step order.
	 *
	 * @param int $order_id Order PK.
	 * @return array<int, object>
	 */
	public static function get_progress( $order_id ) {
		global $wpdb;

		$order_id   = (int) $order_id;
		$progress_t = SOM_DB::table( 'order_step_progress' );
		$steps_t    = SOM_DB::table( 'workflow_steps' );

		self::unlock_elapsed_for_order( $order_id );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, s.name AS step_name, s.step_order, s.requires_manual_confirm,
					s.timer_seconds, s.script_config, s.workflow_template_id, s.batch_group_id
				FROM {$progress_t} p
				INNER JOIN {$steps_t} s ON s.id = p.workflow_step_id
				WHERE p.order_id = %d
				ORDER BY s.step_order ASC, s.id ASC",
				$order_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Unlock waiting_timer for one order; start script if configured.
	 *
	 * @param int $order_id Order PK.
	 * @return void
	 */
	private static function unlock_elapsed_for_order( $order_id ) {
		global $wpdb;

		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$order      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT o.id, o.current_step_id, o.is_complete, o.raw_payload, c.slug AS channel_slug
				FROM {$orders_t} o
				INNER JOIN {$channels_t} c ON c.id = o.channel_id
				WHERE o.id = %d
				LIMIT 1",
				(int) $order_id
			)
		);
		if ( ! $order || ! empty( $order->is_complete ) || empty( $order->current_step_id ) ) {
			return;
		}

		if ( SOM_Orders::is_cancelled( $order->raw_payload, $order->channel_slug ) ) {
			return;
		}

		$now        = current_time( 'mysql', true );
		$progress_t = SOM_DB::table( 'order_step_progress' );
		$progress   = self::get_progress_for_step( (int) $order_id, (int) $order->current_step_id );
		if ( ! $progress || 'waiting_timer' !== (string) $progress->status ) {
			return;
		}
		if ( empty( $progress->timer_ends_at ) || ! self::timer_elapsed( $progress->timer_ends_at ) ) {
			return;
		}

		$step = self::get_step( (int) $order->current_step_id );
		if ( ! $step ) {
			return;
		}

		if ( SOM_Script_Dispatch::has_script( $step ) ) {
			$wpdb->update(
				$progress_t,
				array(
					'status'        => 'waiting_script',
					'timer_ends_at' => null,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			$progress = self::get_progress_for_step( (int) $order_id, (int) $order->current_step_id );
			if ( $progress ) {
				self::attempt_script( (int) $order_id, $step, $progress );
			}
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$progress_t}
				SET status = 'in_progress'
				WHERE order_id = %d
					AND workflow_step_id = %d
					AND status = 'waiting_timer'
					AND timer_ends_at IS NOT NULL
					AND timer_ends_at <= %s",
				(int) $order_id,
				(int) $order->current_step_id,
				$now
			)
		);
	}

	/**
	 * @param int $order_id Order PK.
	 * @return bool
	 */
	public static function has_progress( $order_id ) {
		global $wpdb;

		$progress_t = SOM_DB::table( 'order_step_progress' );
		$count      = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$progress_t} WHERE order_id = %d",
				(int) $order_id
			)
		);

		return $count > 0;
	}

	/**
	 * @param object $order Order from SOM_Orders::get().
	 * @return string needs_mapping|no_template|empty
	 */
	public static function unassigned_reason( $order ) {
		if ( ! empty( $order->is_complete ) || self::has_progress( (int) $order->id ) ) {
			return '';
		}

		$product_id = self::primary_product_id( $order );
		if ( ! $product_id ) {
			return 'needs_mapping';
		}

		global $wpdb;
		$products_t  = SOM_DB::table( 'products' );
		$template_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT workflow_template_id FROM {$products_t} WHERE id = %d LIMIT 1",
				$product_id
			)
		);

		return $template_id > 0 ? '' : 'no_template';
	}

	/**
	 * @param object $order Order with items[].
	 * @return int Product ID or 0.
	 */
	public static function primary_product_id( $order ) {
		if ( empty( $order->items ) || ! is_array( $order->items ) ) {
			return 0;
		}

		foreach ( $order->items as $item ) {
			if ( null !== $item->product_id && '' !== $item->product_id && (int) $item->product_id > 0 ) {
				return (int) $item->product_id;
			}
		}

		return 0;
	}

	/**
	 * Enter a step: timer, script, manual, or auto-complete zero-gate.
	 *
	 * @param int $order_id Order PK.
	 * @param int $step_id  Workflow step PK.
	 * @return true|WP_Error
	 */
	private static function enter_step( $order_id, $step_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$step_id  = (int) $step_id;
		$step     = self::get_step( $step_id );
		$progress = self::get_progress_for_step( $order_id, $step_id );

		if ( ! $step || ! $progress ) {
			return new WP_Error( 'som_step_missing', __( 'Workflow step not found.', 'order-machine' ) );
		}

		$now    = current_time( 'mysql', true );
		$timer  = isset( $step->timer_seconds ) ? (int) $step->timer_seconds : 0;
		$manual = ! empty( $step->requires_manual_confirm );
		$script = SOM_Script_Dispatch::has_script( $step );

		// Batch gate (batch-only in v1 — ignore other gates on the same step).
		if ( ! empty( $step->batch_group_id ) ) {
			return SOM_Batches::enqueue( $order_id, $step );
		}

		// Zero-gate (no timer, no manual, no script) → auto-advance.
		if ( $timer < 1 && ! $manual && ! $script ) {
			$wpdb->update(
				SOM_DB::table( 'order_step_progress' ),
				array(
					'status'        => 'done',
					'started_at'    => $now,
					'completed_at'  => $now,
					'timer_ends_at' => null,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return self::advance_after_step( $order_id, $step_id );
		}

		// Timer hard-gate first.
		if ( $timer > 0 ) {
			$timer_ends_at = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp', true ) + $timer );
			$wpdb->update(
				SOM_DB::table( 'order_step_progress' ),
				array(
					'status'        => 'waiting_timer',
					'started_at'    => $now,
					'timer_ends_at' => $timer_ends_at,
					'retry_count'   => 0,
					'last_error'    => null,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
			return true;
		}

		// Script gate (no timer, or after timer unlock handled elsewhere).
		if ( $script ) {
			$wpdb->update(
				SOM_DB::table( 'order_step_progress' ),
				array(
					'status'        => 'waiting_script',
					'started_at'    => $now,
					'timer_ends_at' => null,
					'retry_count'   => 0,
					'last_error'    => null,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
			$progress = self::get_progress_for_step( $order_id, $step_id );
			return self::attempt_script( $order_id, $step, $progress );
		}

		// Manual-only.
		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'        => 'in_progress',
				'started_at'    => $now,
				'timer_ends_at' => null,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Attempt script execution for current waiting_script progress.
	 *
	 * @param int    $order_id Order PK.
	 * @param object $step     Step.
	 * @param object $progress Progress.
	 * @return true|WP_Error
	 */
	private static function attempt_script( $order_id, $step, $progress ) {
		if ( ! $progress ) {
			return new WP_Error( 'som_progress_missing', __( 'Progress row not found.', 'order-machine' ) );
		}

		// Already parked waiting for async callback — do not re-fire.
		if ( is_string( $progress->last_error ) && 0 === strpos( $progress->last_error, 'waiting_callback:' ) ) {
			return true;
		}

		$order = SOM_Orders::get( (int) $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		$result = SOM_Script_Dispatch::execute( $order, $step, $progress );

		if ( 'waiting_callback' === $result ) {
			global $wpdb;
			$wpdb->update(
				SOM_DB::table( 'order_step_progress' ),
				array(
					'status'     => 'waiting_script',
					'last_error' => 'waiting_callback:pending',
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return true;
		}

		if ( true === $result ) {
			return self::on_script_success( $order_id, $step, $progress );
		}

		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Script step failed.', 'order-machine' );
		return self::on_script_failure( $order_id, $step, $progress, $message, true );
	}

	/**
	 * @param int    $order_id Order PK.
	 * @param object $step     Step.
	 * @param object $progress Progress.
	 * @return true|WP_Error
	 */
	private static function on_script_success( $order_id, $step, $progress ) {
		global $wpdb;

		$manual = ! empty( $step->requires_manual_confirm );
		$now    = current_time( 'mysql', true );

		if ( $manual ) {
			$wpdb->update(
				SOM_DB::table( 'order_step_progress' ),
				array(
					'status'     => 'in_progress',
					'last_error' => null,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return true;
		}

		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'       => 'done',
				'completed_at' => $now,
				'last_error'   => null,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return self::advance_after_step( $order_id, (int) $step->id );
	}

	/**
	 * @param int    $order_id   Order PK.
	 * @param object $step       Step.
	 * @param object $progress   Progress.
	 * @param string $message    Error message.
	 * @param bool   $auto_retry Schedule backoff retries.
	 * @return true|WP_Error
	 */
	private static function on_script_failure( $order_id, $step, $progress, $message, $auto_retry ) {
		global $wpdb;

		$retry_count = (int) $progress->retry_count + 1;
		$message     = substr( (string) $message, 0, 2000 );

		if ( $auto_retry && $retry_count < SOM_Script_Dispatch::MAX_ATTEMPTS ) {
			$delay = ( 1 === $retry_count ) ? MINUTE_IN_SECONDS : ( 5 * MINUTE_IN_SECONDS );
			$next  = gmdate( 'Y-m-d H:i:s', time() + $delay );

			$wpdb->update(
				SOM_DB::table( 'order_step_progress' ),
				array(
					'status'        => 'waiting_script',
					'retry_count'   => $retry_count,
					'last_error'    => $message,
					'timer_ends_at' => $next,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);

			wp_schedule_single_event( time() + $delay, self::HOOK_SCRIPT_ATTEMPT, array( (int) $progress->id ) );
			return true;
		}

		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'        => 'error',
				'retry_count'   => $retry_count,
				'last_error'    => $message,
				'timer_ends_at' => null,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return new WP_Error( 'som_script_error', $message );
	}

	/**
	 * @param int    $order_id Order PK.
	 * @param object $progress Progress.
	 * @param object $step     Step.
	 * @return true|WP_Error
	 */
	private static function complete_current_and_advance( $order_id, $progress, $step ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'       => 'done',
				'completed_at' => $now,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return self::advance_after_step( $order_id, (int) $step->id );
	}

	/**
	 * Move to next step or mark order complete.
	 *
	 * @param int $order_id Order PK.
	 * @param int $step_id  Completed step PK.
	 * @return true|WP_Error
	 */
	private static function advance_after_step( $order_id, $step_id ) {
		global $wpdb;

		$now  = current_time( 'mysql', true );
		$next = self::next_step_after( $order_id, $step_id );
		if ( ! $next ) {
			$wpdb->update(
				SOM_DB::table( 'orders' ),
				array(
					'current_step_id' => null,
					'is_complete'     => 1,
					'updated_at'      => $now,
				),
				array( 'id' => (int) $order_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
			return true;
		}

		$wpdb->update(
			SOM_DB::table( 'orders' ),
			array(
				'current_step_id' => (int) $next->id,
				'updated_at'      => $now,
			),
			array( 'id' => (int) $order_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return self::enter_step( (int) $order_id, (int) $next->id );
	}

	/**
	 * @param string $timer_ends_at GMT datetime.
	 * @return bool
	 */
	private static function timer_elapsed( $timer_ends_at ) {
		$ends = strtotime( (string) $timer_ends_at . ' UTC' );
		if ( ! $ends ) {
			$ends = strtotime( (string) $timer_ends_at );
		}
		if ( ! $ends ) {
			return false;
		}
		return time() >= $ends;
	}

	/**
	 * @param int $order_id Order PK.
	 * @param int $step_id  Workflow step PK.
	 * @return object|null
	 */
	private static function get_progress_for_step( $order_id, $step_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'order_step_progress' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d AND workflow_step_id = %d LIMIT 1",
				(int) $order_id,
				(int) $step_id
			)
		);
	}

	/**
	 * @param int $step_id Workflow step PK.
	 * @return object|null
	 */
	private static function get_step( $step_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'workflow_steps' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				(int) $step_id
			)
		);
	}

	/**
	 * Next step in this order's progress after the given step (by step_order).
	 *
	 * @param int $order_id        Order PK.
	 * @param int $current_step_id Current workflow_steps.id.
	 * @return object|null
	 */
	private static function next_step_after( $order_id, $current_step_id ) {
		global $wpdb;

		$progress_t = SOM_DB::table( 'order_step_progress' );
		$steps_t    = SOM_DB::table( 'workflow_steps' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.step_order, s.name
				FROM {$progress_t} p
				INNER JOIN {$steps_t} s ON s.id = p.workflow_step_id
				WHERE p.order_id = %d
				ORDER BY s.step_order ASC, s.id ASC",
				(int) $order_id
			)
		);

		$found = false;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( $found ) {
					return $row;
				}
				if ( (int) $row->id === (int) $current_step_id ) {
					$found = true;
				}
			}
		}
		return null;
	}
}
