<?php
/**
 * Workflow engine — assign templates, advance steps, timer hard-gates.
 *
 * Script/API execution is deferred to Sprint 9; script gates are treated as
 * satisfied so seed workflows can progress through thank-you steps.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order workflow state machine (manual confirm + timer).
 */
class SOM_Workflow_Engine {

	/**
	 * Assign workflow progress for a newly created order (primary product rule).
	 *
	 * Does nothing for cancelled orders, already-assigned orders, unmatched
	 * primary products, or products with no template.
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error True on assign or intentional skip; WP_Error on failure.
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

		$products_t = SOM_DB::table( 'products' );
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

		$now       = current_time( 'mysql', true );
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
			return new WP_Error( 'som_step_locked', __( 'This step is still locked (timer not elapsed).', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		global $wpdb;

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

		$next = self::next_step_after( $order_id, $current_step_id );
		if ( ! $next ) {
			$wpdb->update(
				SOM_DB::table( 'orders' ),
				array(
					'current_step_id' => null,
					'is_complete'     => 1,
					'updated_at'      => $now,
				),
				array( 'id' => $order_id ),
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
			array( 'id' => $order_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return self::enter_step( $order_id, (int) $next->id );
	}

	/**
	 * Cron tick: unlock elapsed timers (does not auto-advance).
	 *
	 * @return int Number of steps unlocked.
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

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return 0;
		}

		$unlocked = 0;
		foreach ( $rows as $row ) {
			$order = SOM_Orders::get( (int) $row->order_id );
			if ( ! $order || ! empty( $order->is_cancelled ) ) {
				continue;
			}

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

		return $unlocked;
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

		$status = (string) $progress->status;
		if ( ! in_array( $status, array( 'in_progress', 'waiting_script' ), true ) ) {
			return false;
		}

		$timer = isset( $step->timer_seconds ) ? (int) $step->timer_seconds : 0;
		if ( $timer > 0 && ! empty( $progress->timer_ends_at ) && ! self::timer_elapsed( $progress->timer_ends_at ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Progress rows for an order, joined with step metadata, in step order.
	 *
	 * Also unlocks the current step if its timer has already elapsed (lazy unlock
	 * so the UI does not wait solely on the cron interval).
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
					s.timer_seconds, s.script_config, s.workflow_template_id
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
	 * Unlock waiting_timer rows for one order when timer_ends_at has passed.
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
	 * Whether the order has any step progress rows.
	 *
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
	 * Unassigned workflow reason for UI, or empty if assigned/complete.
	 *
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
	 * First order item with a product_id (primary product rule).
	 *
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
	 * Enter a step: start timer, set status, or auto-complete pass-through steps.
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

		// Sprint 7: script gates do not block. Zero-gate / script-only → auto-advance.
		if ( $timer < 1 && ! $manual ) {
			$wpdb->update(
				SOM_DB::table( 'order_step_progress' ),
				array(
					'status'       => 'done',
					'started_at'   => $now,
					'completed_at' => $now,
					'timer_ends_at'=> null,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			$next = self::next_step_after( $order_id, $step_id );
			if ( ! $next ) {
				$wpdb->update(
					SOM_DB::table( 'orders' ),
					array(
						'current_step_id' => null,
						'is_complete'     => 1,
						'updated_at'      => $now,
					),
					array( 'id' => $order_id ),
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
				array( 'id' => $order_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);

			return self::enter_step( $order_id, (int) $next->id );
		}

		$status        = 'in_progress';
		$timer_ends_at = null;

		if ( $timer > 0 ) {
			$status        = 'waiting_timer';
			$timer_ends_at = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp', true ) + $timer );
		}

		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'        => $status,
				'started_at'    => $now,
				'timer_ends_at' => $timer_ends_at,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return true;
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
	 * @param int $order_id         Order PK.
	 * @param int $current_step_id  Current workflow_steps.id.
	 * @return object|null Step row (id, step_order, …).
	 */
	private static function next_step_after( $order_id, $current_step_id ) {
		$rows = self::get_progress( $order_id );
		$found = false;
		foreach ( $rows as $row ) {
			if ( $found ) {
				return (object) array(
					'id'         => (int) $row->workflow_step_id,
					'step_order' => (int) $row->step_order,
					'name'       => $row->step_name,
				);
			}
			if ( (int) $row->workflow_step_id === (int) $current_step_id ) {
				$found = true;
			}
		}
		return null;
	}
}
