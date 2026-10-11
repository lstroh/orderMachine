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
	 * Fired after a waiting_timer progress row unlocks (to in_progress or waiting_script).
	 *
	 * @var string
	 */
	const HOOK_TIMER_UNLOCKED = 'som_timer_unlocked';

	/**
	 * Assign workflow progress for a newly created order.
	 *
	 * Package 6: per-line make + order-level pack bind.
	 *
	 * @param int                  $order_id Order PK.
	 * @param array<string, mixed> $args     Passed to pack bind (e.g. sync_package_materials).
	 * @return true|WP_Error
	 */
	public static function assign_on_create( $order_id, array $args = array() ) {
		$result = SOM_Item_Make::assign_on_create( $order_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return SOM_Pack::bind_on_create( $order_id, $args );
	}

	/**
	 * Public wrapper to enter a step (used by pack bind).
	 *
	 * @param int $order_id Order PK.
	 * @param int $step_id  Workflow step PK.
	 * @return true|WP_Error
	 */
	public static function enter_step_public( $order_id, $step_id ) {
		return self::enter_step( (int) $order_id, (int) $step_id );
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

		$current_step_id = (int) $order->current_step_id;
		if ( $current_step_id < 1 ) {
			return new WP_Error( 'som_no_workflow', __( 'This order has no workflow assigned.', 'order-machine' ) );
		}

		$progress = self::get_progress_for_step( $order_id, $current_step_id );
		$step     = self::get_step( $current_step_id );
		if ( ! $progress || ! $step ) {
			return new WP_Error( 'som_step_missing', __( 'Current workflow step not found.', 'order-machine' ) );
		}

		// O1: post-ship steps (e.g. Review reminder) may continue after is_complete.
		if ( ! empty( $order->is_complete ) && ! SOM_Post_Ship::is_post_ship_step( $step ) ) {
			return new WP_Error( 'som_order_complete', __( 'Order workflow is already complete.', 'order-machine' ) );
		}

		$unblocked = self::has_manual_unblock( $progress );

		if ( ! $unblocked && SOM_Shipments::is_ship_step( $step ) ) {
			$blocked = SOM_Pack::ship_blocked_reason( $order );
			if ( '' !== $blocked ) {
				return new WP_Error( 'som_ship_blocked', $blocked );
			}
			if ( ! SOM_Shipments::has_required( $order_id ) ) {
				return new WP_Error(
					'som_shipment_required',
					__( 'Record the shipment (carrier, service, ship date, postage) before marking Ship done.', 'order-machine' )
				);
			}
		}

		if ( ! $unblocked && SOM_Step_Confirmations::has_confirmation( $step )
			&& ! SOM_Step_Confirmations::progress_is_complete( $progress, $step, $order ) ) {
			return new WP_Error(
				'som_confirmation_required',
				__( 'Complete the confirmation checklist before marking this step done.', 'order-machine' )
			);
		}

		// Post-ship never uses trays; skip gate so pack trays do not block aftercare.
		if ( ! $unblocked && ! SOM_Post_Ship::is_post_ship_step( $step ) && ! SOM_Trays::trays_gate_ok( $order ) ) {
			return new WP_Error(
				'som_trays_required',
				__( 'Assign at least one tray to this order before marking the step done.', 'order-machine' )
			);
		}

		if ( ! self::can_mark_done( $progress, $step ) ) {
			return new WP_Error( 'som_step_locked', __( 'This step cannot be marked done yet.', 'order-machine' ) );
		}

		if ( SOM_Post_Ship::is_arrival_step( $step ) ) {
			$arrived = SOM_Post_Ship::confirm_arrival( $order_id );
			if ( is_wp_error( $arrived ) ) {
				return $arrived;
			}
		}

		$result = self::complete_current_and_advance( $order_id, $progress, $step );
		if ( ! is_wp_error( $result ) && SOM_Shipments::is_ship_step( $step ) ) {
			SOM_Shipments::maybe_push_after_ship( $order_id );
		}
		return $result;
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

		// UP9-S2: scripts removed — open step for Mark done instead of re-dispatching.
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
		if ( ! $step ) {
			return;
		}

		// UP9-S2: open for Mark done; never re-dispatch.
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
				if ( self::unlock_elapsed_for_order( (int) $row->order_id ) ) {
					++$unlocked;
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
				// UP9-S2: open all waiting_script rows (including former async callbacks).
				self::attempt_script_by_progress_id( (int) $row->id );
				++$scripts;
			}
		}

		$batches = SOM_Batches::process_due_retries();
		$unlocked += SOM_Item_Make::tick_unlock_timers();
		$unlocked += SOM_Item_Make::recover_waiting_script();

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

		if ( SOM_Shipments::is_ship_step( $step ) && ! SOM_Shipments::has_required( $order_id ) ) {
			return new WP_Error(
				'som_shipment_required',
				sprintf(
					/* translators: %d: order id */
					__( 'Order #%d is missing a shipment record.', 'order-machine' ),
					$order_id
				)
			);
		}

		$result = self::complete_current_and_advance( $order_id, $progress, $step );
		if ( ! is_wp_error( $result ) && SOM_Shipments::is_ship_step( $step ) ) {
			SOM_Shipments::maybe_push_after_ship( $order_id );
		}
		return $result;
	}

	/**
	 * Whether Mark done is allowed for the current progress + step definition.
	 *
	 * When manual_unblock_* is set on the progress row, timer / checklist / script /
	 * batch / ship / package / hold / trays gates are treated as satisfied (UP8-S2).
	 *
	 * @param object $progress Progress row.
	 * @param object $step     Workflow step row.
	 * @return bool
	 */
	public static function can_mark_done( $progress, $step ) {
		if ( ! $progress || ! $step ) {
			return false;
		}

		$status = (string) $progress->status;
		// UP9-S2: leftover waiting_script is treated as manual in_progress (scripts removed).
		if ( 'in_progress' !== $status && 'waiting_script' !== $status ) {
			return false;
		}

		if ( self::has_manual_unblock( $progress ) ) {
			return true;
		}

		$timer = isset( $step->timer_seconds ) ? (int) $step->timer_seconds : 0;
		if ( $timer > 0 && ! empty( $progress->timer_ends_at ) && ! self::timer_elapsed( $progress->timer_ends_at ) ) {
			return false;
		}

		$order_id = isset( $progress->order_id ) ? (int) $progress->order_id : 0;
		$order    = $order_id > 0 ? SOM_Orders::get( $order_id ) : null;

		if ( SOM_Shipments::is_ship_step( $step ) ) {
			if ( ! $order || '' !== SOM_Pack::ship_blocked_reason( $order ) ) {
				return false;
			}
			if ( ! SOM_Shipments::has_required( $order_id ) ) {
				return false;
			}
		}

		if ( SOM_Step_Confirmations::has_confirmation( $step ) ) {
			if ( ! $order || ! SOM_Step_Confirmations::progress_is_complete( $progress, $step, $order ) ) {
				return false;
			}
		}

		if ( $order && ! SOM_Trays::trays_gate_ok( $order ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the progress row has a manual unblock audit stamp.
	 *
	 * @param object $progress Progress row.
	 * @return bool
	 */
	public static function has_manual_unblock( $progress ) {
		return $progress && ! empty( $progress->manual_unblock_at );
	}

	/**
	 * Gate keys currently blocking Mark done for this progress/step (for UI + audit).
	 *
	 * @param object $progress Progress row.
	 * @param object $step     Step row.
	 * @param object $order    Order from get().
	 * @return array<int, string>
	 */
	public static function blocking_gate_keys( $progress, $step, $order ) {
		$gates  = array();
		$status = $progress ? (string) $progress->status : '';

		if ( in_array( $status, array( 'waiting_timer' ), true ) ) {
			$gates[] = 'timer';
		} elseif ( $step && isset( $step->timer_seconds ) && (int) $step->timer_seconds > 0
			&& ! empty( $progress->timer_ends_at )
			&& ! self::timer_elapsed( $progress->timer_ends_at ) ) {
			$gates[] = 'timer';
		}

		// UP9-S2: waiting_script is no longer a blocking script gate (ignored / Mark done).
		if ( 'error' === $status ) {
			$gates[] = 'script';
		}

		if ( 'waiting_batch' === $status ) {
			$gates[] = 'batch';
		}

		if ( $step && SOM_Step_Confirmations::has_confirmation( $step ) && $order
			&& ! SOM_Step_Confirmations::progress_is_complete( $progress, $step, $order ) ) {
			$gates[] = 'checklist';
		}

		if ( $step && SOM_Shipments::is_ship_step( $step ) && $order ) {
			if ( SOM_Pack::is_held( $order ) ) {
				$gates[] = 'hold';
			}
			if ( empty( $order->shipping_package_id ) ) {
				$gates[] = 'package';
			}
			if ( ! SOM_Shipments::has_required( (int) $order->id ) ) {
				$gates[] = 'ship';
			}
			if ( ! SOM_Pack::order_is_make_ready( $order ) ) {
				$gates[] = 'make_ready';
			}
			if ( empty( $order->pack_workflow_template_id ) ) {
				$gates[] = 'pack_bind';
			}
		}

		if ( $order && ! SOM_Trays::trays_gate_ok( $order ) ) {
			$gates[] = 'trays';
		}

		return array_values( array_unique( $gates ) );
	}

	/**
	 * Whether Unblock step should be offered on order detail for this current progress.
	 *
	 * @param object $progress Progress row.
	 * @param object $step     Step row.
	 * @param object $order    Order.
	 * @return bool
	 */
	public static function can_unblock_step( $progress, $step, $order ) {
		if ( ! $progress || ! $step || ! $order ) {
			return false;
		}
		if ( ! empty( $order->is_cancelled ) ) {
			return false;
		}
		if ( ! empty( $order->is_complete ) && ! SOM_Post_Ship::is_post_ship_step( $step ) ) {
			return false;
		}
		$status = (string) $progress->status;
		if ( in_array( $status, array( 'pending', 'done' ), true ) ) {
			return false;
		}
		if ( self::can_mark_done( $progress, $step ) ) {
			return false;
		}
		$gates = self::blocking_gate_keys( $progress, $step, $order );
		if ( ! empty( $gates ) ) {
			return true;
		}
		// Waiting statuses that hide Mark done even when gate list is empty.
		return in_array( $status, array( 'waiting_timer', 'waiting_script', 'waiting_batch', 'error' ), true );
	}

	/**
	 * Unblock the current make or pack step (all gates). Does not Mark done.
	 *
	 * @param int $order_id      Order PK.
	 * @param int $order_item_id Make line PK (0 = pack / order-level progress).
	 * @param int $user_id       WP user id (0 = current).
	 * @return true|WP_Error
	 */
	public static function unblock_step( $order_id, $order_item_id = 0, $user_id = 0 ) {
		global $wpdb;

		$order_id      = (int) $order_id;
		$order_item_id = (int) $order_item_id;
		$user_id       = $user_id > 0 ? (int) $user_id : get_current_user_id();

		$order = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}
		if ( ! empty( $order->is_cancelled ) ) {
			return new WP_Error( 'som_order_cancelled', __( 'Cancelled orders cannot be unblocked.', 'order-machine' ) );
		}

		if ( $order_item_id > 0 ) {
			if ( ! empty( $order->is_complete ) ) {
				return new WP_Error( 'som_order_complete', __( 'Order workflow is already complete.', 'order-machine' ) );
			}
			$progress = SOM_Item_Make::current_item_progress( $order_item_id );
			$table    = SOM_DB::table( 'order_item_step_progress' );
		} else {
			$current_step_id = (int) $order->current_step_id;
			if ( $current_step_id < 1 ) {
				return new WP_Error( 'som_no_workflow', __( 'This order has no workflow assigned.', 'order-machine' ) );
			}
			$progress = self::get_progress_for_step( $order_id, $current_step_id );
			$table    = SOM_DB::table( 'order_step_progress' );
		}

		if ( ! $progress ) {
			return new WP_Error( 'som_no_workflow', __( 'This order has no active step to unblock.', 'order-machine' ) );
		}

		$step = self::get_step( (int) $progress->workflow_step_id );
		if ( ! $step ) {
			return new WP_Error( 'som_step_missing', __( 'Current workflow step not found.', 'order-machine' ) );
		}

		if ( ! empty( $order->is_complete ) && $order_item_id < 1 && ! SOM_Post_Ship::is_post_ship_step( $step ) ) {
			return new WP_Error( 'som_order_complete', __( 'Order workflow is already complete.', 'order-machine' ) );
		}

		$status = (string) $progress->status;
		if ( in_array( $status, array( 'pending', 'done' ), true ) ) {
			return new WP_Error(
				'som_unblock_not_current',
				__( 'Only the current active step can be unblocked.', 'order-machine' )
			);
		}

		if ( self::can_mark_done( $progress, $step ) ) {
			return new WP_Error(
				'som_unblock_unneeded',
				__( 'Mark done is already available for this step.', 'order-machine' )
			);
		}

		$gates = self::blocking_gate_keys( $progress, $step, $order );
		if ( empty( $gates ) && in_array( $status, array( 'waiting_timer', 'waiting_script', 'waiting_batch', 'error' ), true ) ) {
			$gates = array( $status );
		}
		if ( empty( $gates ) ) {
			$gates = array( 'gates' );
		}

		// O9: remove from collecting/ready batch when unblocking waiting_batch.
		if ( 'waiting_batch' === $status || in_array( 'batch', $gates, true ) ) {
			$removed = SOM_Batches::remove_order_from_open_batch( $order_id );
			if ( is_wp_error( $removed ) ) {
				return $removed;
			}
		}

		$now       = current_time( 'mysql', true );
		$gates_csv = implode( ',', $gates );
		$update    = array(
			'status'               => 'in_progress',
			'timer_ends_at'        => null,
			'last_error'           => null,
			'manual_unblock_at'    => $now,
			'manual_unblock_gates' => $gates_csv,
		);
		$formats   = array( '%s', '%s', '%s', '%s', '%s' );
		if ( $user_id > 0 ) {
			$update['manual_unblock_by'] = $user_id;
			$formats[]                   = '%d';
		} else {
			$update['manual_unblock_by'] = null;
			$formats[]                   = '%s';
		}
		if ( $order_item_id > 0 ) {
			$update['updated_at'] = $now;
			$formats[]            = '%s';
		}

		$ok = $wpdb->update(
			$table,
			$update,
			array( 'id' => (int) $progress->id ),
			$formats,
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_unblock_save', __( 'Could not unblock step.', 'order-machine' ) );
		}

		// Soft default O7: short order note.
		$step_name = isset( $step->name ) ? trim( (string) $step->name ) : '';
		if ( '' === $step_name && isset( $progress->step_name ) ) {
			$step_name = trim( (string) $progress->step_name );
		}
		$who = $user_id > 0 ? SOM_Order_Notes::author_label( $user_id ) : __( 'System', 'order-machine' );
		$note = sprintf(
			/* translators: 1: step name, 2: user display name, 3: comma-separated gate keys */
			__( 'Step %1$s unblocked by %2$s (%3$s). Allows Mark done without meeting gates; does not complete the step.', 'order-machine' ),
			'' !== $step_name ? $step_name : '#' . (int) $progress->workflow_step_id,
			$who,
			$gates_csv
		);
		if ( $user_id > 0 ) {
			SOM_Order_Notes::add( $order_id, $note, $user_id );
		} else {
			// CLI / system: still persist note under user 1 when available.
			$fallback = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY ID ASC LIMIT 1" );
			if ( $fallback > 0 ) {
				SOM_Order_Notes::add( $order_id, $note, $fallback );
			}
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
		unset( $progress, $step );
		// UP9-S2: script retry UI removed — leftover waiting_script uses Mark done / Unblock.
		return false;
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

		$templates_t = SOM_DB::table( 'workflow_templates' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, s.name AS step_name, s.step_order, s.requires_manual_confirm,
					s.timer_seconds, s.planned_seconds, s.script_config, s.command_template, s.workflow_template_id, s.batch_group_id,
					s.confirmation_kind, t.kind AS template_kind
				FROM {$progress_t} p
				INNER JOIN {$steps_t} s ON s.id = p.workflow_step_id
				LEFT JOIN {$templates_t} t ON t.id = s.workflow_template_id
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
	 * @return bool True when status was flipped from waiting_timer.
	 */
	public static function unlock_elapsed_for_order( $order_id ) {
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
		if ( ! $order || empty( $order->current_step_id ) ) {
			return false;
		}

		if ( SOM_Orders::is_cancelled( $order->raw_payload, $order->channel_slug ) ) {
			return false;
		}

		$now        = current_time( 'mysql', true );
		$progress_t = SOM_DB::table( 'order_step_progress' );
		$progress   = self::get_progress_for_step( (int) $order_id, (int) $order->current_step_id );
		if ( ! $progress || 'waiting_timer' !== (string) $progress->status ) {
			return false;
		}
		if ( empty( $progress->timer_ends_at ) || ! self::timer_elapsed( $progress->timer_ends_at ) ) {
			return false;
		}

		$step = self::get_step( (int) $order->current_step_id );
		if ( ! $step ) {
			return false;
		}

		// Timers on post-ship may unlock after is_complete (O1).
		if ( ! empty( $order->is_complete ) && ! SOM_Post_Ship::is_post_ship_step( $step ) ) {
			return false;
		}

		$progress_id = (int) $progress->id;

		// UP9-S2: never unlock into waiting_script / dispatch.

		$updated = $wpdb->query(
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
		if ( false === $updated || 0 === (int) $updated ) {
			return false;
		}

		/**
		 * After a waiting_timer step unlocks.
		 *
		 * @param int $order_id    Order PK.
		 * @param int $progress_id order_step_progress PK.
		 */
		do_action( self::HOOK_TIMER_UNLOCKED, (int) $order_id, $progress_id );
		return true;
	}

	/**
	 * Compact current-step progress for admin REST (unlocks elapsed timer first).
	 *
	 * @param int $order_id Order PK.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function progress_status_for_api( $order_id ) {
		$order_id = (int) $order_id;
		$unlocked = self::unlock_elapsed_for_order( $order_id );

		$order = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		$payload = array(
			'ok'                => true,
			'order_id'          => $order_id,
			'unlocked'          => (bool) $unlocked,
			'external_order_id' => (string) $order->external_order_id,
			'current_step_id'   => (int) $order->current_step_id,
			'step_name'         => (string) ( $order->current_step_name ?? '' ),
			'status'            => '',
			'timer_ends_at'     => null,
			'timer_ready'       => false,
			'can_advance'       => false,
			'next_step_name'    => '',
			'is_last_step'      => false,
			'is_complete'       => ! empty( $order->is_complete ),
			'is_cancelled'      => ! empty( $order->is_cancelled ),
		);

		if ( ! empty( $order->is_cancelled ) || empty( $order->current_step_id ) ) {
			return $payload;
		}

		$progress = self::get_progress_for_step( $order_id, (int) $order->current_step_id );
		$step     = self::get_step( (int) $order->current_step_id );
		if ( ! $progress || ! $step ) {
			return $payload;
		}

		if ( ! empty( $order->is_complete ) && ! SOM_Post_Ship::is_post_ship_step( $step ) ) {
			return $payload;
		}

		$payload['status'] = (string) $progress->status;
		if ( ! empty( $progress->timer_ends_at ) ) {
			$ends = strtotime( (string) $progress->timer_ends_at . ' UTC' );
			if ( ! $ends ) {
				$ends = strtotime( (string) $progress->timer_ends_at );
			}
			$payload['timer_ends_at'] = $ends ? (int) $ends : null;
		}

		$payload['timer_ready'] = self::is_timer_ready( $progress );
		$meta                   = self::board_dnd_meta( $order_id );
		$payload['can_advance']    = ! empty( $meta['can_advance'] );
		$payload['next_step_name'] = (string) $meta['next_step_name'];
		$payload['is_last_step']   = ! empty( $meta['is_last_step'] );

		return $payload;
	}

	/**
	 * Whether progress is unlocked after a timer and ready to Mark done (UI hint).
	 *
	 * @param object $progress Progress row.
	 * @return bool
	 */
	public static function is_timer_ready( $progress ) {
		if ( ! $progress || 'in_progress' !== (string) $progress->status ) {
			return false;
		}
		if ( empty( $progress->timer_ends_at ) ) {
			return false;
		}
		return self::timer_elapsed( $progress->timer_ends_at );
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
	 * @return string needs_mapping|no_template|partial|empty
	 */
	public static function unassigned_reason( $order ) {
		return SOM_Item_Make::unassigned_reason( $order );
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
		$confirm = SOM_Step_Confirmations::has_confirmation( $step );
		$copy    = SOM_Copy_Command::has_template( $step );
		// UP9-S2 O5: ignore legacy script_config — never waiting_script / dispatch.
		$legacy_script = SOM_Script_Dispatch::has_script( $step );

		// Batch gate (batch-only in v1 — ignore other gates on the same step).
		if ( ! empty( $step->batch_group_id ) ) {
			return SOM_Batches::enqueue( $order_id, $step );
		}

		// Confirmation / copy-command / legacy script → manual Mark done (no dispatch).
		if ( $confirm || $copy || $legacy_script ) {
			$manual = true;
		}

		// Zero-gate (no timer, no manual) → auto-advance.
		if ( $timer < 1 && ! $manual ) {
			$wpdb->update(
				SOM_DB::table( 'order_step_progress' ),
				array(
					'status'         => 'done',
					'started_at'     => $now,
					'completed_at'   => $now,
					'actual_seconds' => 0,
					'timer_ends_at'  => null,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s', '%d', '%s' ),
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

		// Manual-only (includes legacy script_config treated as Mark done).
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
	 * Legacy waiting_script recovery (UP9-S2): never dispatch; open for Mark done.
	 *
	 * @param int    $order_id Order PK.
	 * @param object $step     Step.
	 * @param object $progress Progress.
	 * @return true|WP_Error
	 */
	private static function attempt_script( $order_id, $step, $progress ) {
		unset( $order_id, $step );
		if ( ! $progress ) {
			return new WP_Error( 'som_progress_missing', __( 'Progress row not found.', 'order-machine' ) );
		}

		global $wpdb;
		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'     => 'in_progress',
				'last_error' => __( 'Script steps are removed. Use Mark done (or Copy command).', 'order-machine' ),
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return true;
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

		$started = isset( $progress->started_at ) ? $progress->started_at : $now;
		$actual  = self::wall_clock_seconds( $started, $now );
		if ( null === $actual ) {
			$started = $now;
			$actual  = 0;
		}

		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'         => 'done',
				'started_at'     => $started,
				'completed_at'   => $now,
				'actual_seconds' => $actual,
				'last_error'     => null,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%s', '%s', '%d', '%s' ),
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
	 * Wall-clock seconds between two MySQL datetimes (completed − started).
	 *
	 * Includes timer waits. Returns null when either timestamp is missing/invalid.
	 *
	 * @param string|null $started_at   MySQL datetime.
	 * @param string|null $completed_at MySQL datetime.
	 * @return int|null
	 */
	public static function wall_clock_seconds( $started_at, $completed_at ) {
		$start = SOM_Orders::mysql_to_timestamp( $started_at );
		$end   = SOM_Orders::mysql_to_timestamp( $completed_at );
		if ( null === $start || null === $end ) {
			return null;
		}
		return max( 0, $end - $start );
	}

	/**
	 * @param int    $order_id Order PK.
	 * @param object $progress Progress.
	 * @param object $step     Step.
	 * @return true|WP_Error
	 */
	private static function complete_current_and_advance( $order_id, $progress, $step ) {
		global $wpdb;

		$now    = current_time( 'mysql', true );
		$started = isset( $progress->started_at ) ? $progress->started_at : null;
		$actual  = self::wall_clock_seconds( $started, $now );
		// If started_at was never set (legacy), stamp both ends so actual is 0 rather than null.
		if ( null === $actual ) {
			$started = $now;
			$actual  = 0;
		}

		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'         => 'done',
				'started_at'     => $started,
				'completed_at'   => $now,
				'actual_seconds' => $actual,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		return self::advance_after_step( $order_id, (int) $step->id );
	}

	/**
	 * Move to next step within the same template, or finish the lane.
	 *
	 * Pack finish (customer): does not set is_complete; may auto-start post-ship.
	 * Post-ship finish: clears current_step_id; is_complete only via arrival (O1).
	 * Legacy order-level: still sets is_complete when the lane ends.
	 *
	 * @param int $order_id Order PK.
	 * @param int $step_id  Completed step PK.
	 * @return true|WP_Error
	 */
	private static function advance_after_step( $order_id, $step_id ) {
		global $wpdb;

		$now  = current_time( 'mysql', true );
		$step = self::get_step( (int) $step_id );
		$kind = $step ? SOM_Workflows::get_kind( (int) $step->workflow_template_id ) : 'make';
		$next = self::next_step_after( $order_id, $step_id );
		if ( ! $next ) {
			if ( 'pack' === $kind ) {
				$wpdb->update(
					SOM_DB::table( 'orders' ),
					array(
						'current_step_id' => null,
						'updated_at'      => $now,
					),
					array( 'id' => (int) $order_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				// Auto-start post-ship when Pack lane ends (typically after Ship).
				return SOM_Post_Ship::maybe_auto_start_after_pack( (int) $order_id );
			}

			if ( 'post_ship' === $kind ) {
				// Do not force is_complete here — arrival confirm owns complete (O1).
				$wpdb->update(
					SOM_DB::table( 'orders' ),
					array(
						'current_step_id' => null,
						'updated_at'      => $now,
					),
					array( 'id' => (int) $order_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				return true;
			}

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
			/**
			 * Fires after an order workflow is marked complete.
			 *
			 * @param int $order_id Order PK.
			 */
			do_action( SOM_Production::HOOK_ORDER_COMPLETED, (int) $order_id );
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
	 * Board DnD metadata: whether Mark done / advance-step is allowed, and the next step name.
	 *
	 * @param int $order_id Order PK.
	 * @return array{can_advance: bool, next_step_name: string, is_last_step: bool}
	 */
	public static function board_dnd_meta( $order_id ) {
		$order_id = (int) $order_id;
		$out      = array(
			'can_advance'    => false,
			'next_step_name' => '',
			'is_last_step'   => false,
		);

		$order = SOM_Orders::get( $order_id );
		if ( ! $order || ! empty( $order->is_cancelled ) ) {
			return $out;
		}

		$current_step_id = (int) $order->current_step_id;
		if ( $current_step_id < 1 ) {
			return $out;
		}

		$progress = self::get_progress_for_step( $order_id, $current_step_id );
		$step     = self::get_step( $current_step_id );
		if ( ! $progress || ! $step ) {
			return $out;
		}

		// Post-ship may continue after is_complete (O1 review reminder).
		if ( ! empty( $order->is_complete ) && ! SOM_Post_Ship::is_post_ship_step( $step ) ) {
			return $out;
		}

		$next = self::next_step_after( $order_id, $current_step_id );
		if ( $next ) {
			$out['next_step_name'] = trim( (string) $next->name );
		} else {
			$out['is_last_step'] = true;
		}

		$out['can_advance'] = self::can_mark_done( $progress, $step );
		return $out;
	}

	/**
	 * Next step in the same workflow template after the given step (by step_order).
	 *
	 * Scoped by template so pack and post-ship progress rows do not interleave.
	 *
	 * @param int $order_id        Order PK.
	 * @param int $current_step_id Current workflow_steps.id.
	 * @return object|null
	 */
	private static function next_step_after( $order_id, $current_step_id ) {
		global $wpdb;

		$progress_t  = SOM_DB::table( 'order_step_progress' );
		$steps_t     = SOM_DB::table( 'workflow_steps' );
		$template_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT workflow_template_id FROM {$steps_t} WHERE id = %d LIMIT 1",
				(int) $current_step_id
			)
		);
		if ( $template_id < 1 ) {
			return null;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.step_order, s.name
				FROM {$progress_t} p
				INNER JOIN {$steps_t} s ON s.id = p.workflow_step_id
				WHERE p.order_id = %d AND s.workflow_template_id = %d
				ORDER BY s.step_order ASC, s.id ASC",
				(int) $order_id,
				$template_id
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
