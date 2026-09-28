<?php
/**
 * Per-line make workflow progress (Package 6 / UP6-S1).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Assign and advance make steps on order_items (sellable lines).
 */
class SOM_Item_Make {

	/**
	 * Assign per-line make progress for a newly created order.
	 *
	 * Pack bind is separate (`SOM_Pack::bind_on_create`). Skips if the order
	 * already has item make progress or legacy order_step_progress.
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

		if ( self::has_item_progress( $order_id ) || SOM_Workflow_Engine::has_progress( $order_id ) ) {
			return true;
		}

		if ( empty( $order->items ) || ! is_array( $order->items ) ) {
			return true;
		}

		$products_t = SOM_DB::table( 'products' );

		foreach ( $order->items as $item ) {
			$item_id    = (int) $item->id;
			$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
			if ( $item_id < 1 || $product_id < 1 ) {
				continue;
			}

			$product = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, workflow_template_id, is_internal FROM {$products_t} WHERE id = %d LIMIT 1",
					$product_id
				)
			);
			if ( ! $product ) {
				continue;
			}

			// Internal product lines are always pack-ready — no make progress.
			if ( ! empty( $product->is_internal ) ) {
				continue;
			}

			$template_id = (int) $product->workflow_template_id;
			if ( $template_id < 1 ) {
				continue;
			}

			$template = SOM_Workflows::get( $template_id );
			if ( ! $template ) {
				continue;
			}

			// Products may only use make templates; skip pack templates if mis-assigned.
			$kind = self::template_kind( $template );
			if ( 'pack' === $kind ) {
				continue;
			}

			$steps = self::filter_make_steps( isset( $template->steps ) ? $template->steps : array() );
			if ( empty( $steps ) ) {
				continue;
			}

			$result = self::insert_item_progress( $order_id, $item_id, $steps );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$first = (int) $steps[0]->id;
			$enter = self::enter_item_step( $order_id, $item_id, $first );
			if ( is_wp_error( $enter ) ) {
				return $enter;
			}
		}

		// Internal channel Produce N: assign make for the internal product line.
		$channel = isset( $order->channel_slug ) ? (string) $order->channel_slug : '';
		if ( SOM_Production::CHANNEL_SLUG === $channel ) {
			foreach ( $order->items as $item ) {
				$item_id    = (int) $item->id;
				$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
				if ( $item_id < 1 || $product_id < 1 ) {
					continue;
				}
				if ( self::item_has_progress( $item_id ) ) {
					continue;
				}

				$product = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, workflow_template_id, is_internal FROM {$products_t} WHERE id = %d LIMIT 1",
						$product_id
					)
				);
				if ( ! $product || empty( $product->is_internal ) ) {
					continue;
				}

				$template_id = (int) $product->workflow_template_id;
				if ( $template_id < 1 ) {
					continue;
				}

				$template = SOM_Workflows::get( $template_id );
				if ( ! $template ) {
					continue;
				}

				$steps = self::filter_make_steps( isset( $template->steps ) ? $template->steps : array() );
				if ( empty( $steps ) ) {
					continue;
				}

				$result = self::insert_item_progress( $order_id, $item_id, $steps );
				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$enter = self::enter_item_step( $order_id, $item_id, (int) $steps[0]->id );
				if ( is_wp_error( $enter ) ) {
					return $enter;
				}
			}
		}

		return true;
	}

	/**
	 * Truncate product template steps to make-only (interim until seed rewrite).
	 *
	 * Stops before packing_items / shipping_address, or Pack/Ship/Thank-you/Review names.
	 *
	 * @param array<int, object> $steps Template steps in order.
	 * @return array<int, object>
	 */
	public static function filter_make_steps( array $steps ) {
		$out = array();
		foreach ( $steps as $step ) {
			if ( self::is_pack_boundary_step( $step ) ) {
				break;
			}
			$out[] = $step;
		}
		return $out;
	}

	/**
	 * @param object $step Workflow step row.
	 * @return bool
	 */
	public static function is_pack_boundary_step( $step ) {
		$kind = isset( $step->confirmation_kind ) ? (string) $step->confirmation_kind : '';
		if ( in_array( $kind, array( 'packing_items', 'shipping_address' ), true ) ) {
			return true;
		}
		if ( ! empty( $step->batch_group_id ) ) {
			return true;
		}
		$name = strtolower( trim( (string) ( $step->name ?? '' ) ) );
		if ( '' === $name ) {
			return false;
		}
		if ( preg_match( '/^(confirm\s+)?pack\b/', $name ) ) {
			return true;
		}
		if ( preg_match( '/^(confirm\s+)?address\b/', $name ) ) {
			return true;
		}
		if ( preg_match( '/^ship\b/', $name ) ) {
			return true;
		}
		if ( preg_match( '/^thank-?you\b/', $name ) ) {
			return true;
		}
		if ( preg_match( '/^review\b/', $name ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param object|null $template Template row.
	 * @return string make|pack
	 */
	public static function template_kind( $template ) {
		if ( ! $template ) {
			return 'make';
		}
		$kind = isset( $template->kind ) ? sanitize_key( (string) $template->kind ) : 'make';
		return 'pack' === $kind ? 'pack' : 'make';
	}

	/**
	 * @param int $order_id Order PK.
	 * @return bool
	 */
	public static function has_item_progress( $order_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'order_item_step_progress' );
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE order_id = %d",
				(int) $order_id
			)
		);
		return $count > 0;
	}

	/**
	 * @param int $order_item_id Order item PK.
	 * @return bool
	 */
	public static function item_has_progress( $order_item_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'order_item_step_progress' );
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE order_item_id = %d",
				(int) $order_item_id
			)
		);
		return $count > 0;
	}

	/**
	 * Whether a sellable line is make-complete / pack-ready.
	 *
	 * Internal product lines → always true. Unmatched / no make progress → false.
	 *
	 * @param object      $item    Order item row (needs product_id, id).
	 * @param object|null $product Optional product row with is_internal.
	 * @return bool
	 */
	public static function item_is_make_complete( $item, $product = null ) {
		global $wpdb;

		if ( ! $item ) {
			return false;
		}

		$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
		if ( $product_id < 1 ) {
			return false;
		}

		if ( null === $product ) {
			$products_t = SOM_DB::table( 'products' );
			$product    = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, is_internal FROM {$products_t} WHERE id = %d LIMIT 1",
					$product_id
				)
			);
		}

		if ( $product && ! empty( $product->is_internal ) ) {
			return true;
		}

		$item_id = (int) $item->id;
		if ( $item_id < 1 || ! self::item_has_progress( $item_id ) ) {
			return false;
		}

		$table = SOM_DB::table( 'order_item_step_progress' );
		$open  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE order_item_id = %d AND status <> 'done'",
				$item_id
			)
		);
		return 0 === $open;
	}

	/**
	 * Progress rows for one order item, joined with step metadata.
	 *
	 * @param int $order_item_id Order item PK.
	 * @return array<int, object>
	 */
	public static function get_item_progress( $order_item_id ) {
		global $wpdb;

		$order_item_id = (int) $order_item_id;
		$progress_t    = SOM_DB::table( 'order_item_step_progress' );
		$steps_t       = SOM_DB::table( 'workflow_steps' );

		self::unlock_elapsed_for_item( $order_item_id );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, s.name AS step_name, s.step_order, s.requires_manual_confirm,
					s.timer_seconds, s.script_config, s.workflow_template_id, s.batch_group_id,
					s.confirmation_kind
				FROM {$progress_t} p
				INNER JOIN {$steps_t} s ON s.id = p.workflow_step_id
				WHERE p.order_item_id = %d
				ORDER BY s.step_order ASC, s.id ASC",
				$order_item_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Current (first non-done) make step for an item, or null if complete/none.
	 *
	 * @param int $order_item_id Order item PK.
	 * @return object|null Progress row with step metadata.
	 */
	public static function current_item_progress( $order_item_id ) {
		$rows = self::get_item_progress( $order_item_id );
		foreach ( $rows as $row ) {
			if ( 'done' !== (string) $row->status ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Mark the current make step done for an order line and advance.
	 *
	 * @param int $order_id      Order PK.
	 * @param int $order_item_id Order item PK.
	 * @return true|WP_Error
	 */
	public static function mark_done( $order_id, $order_item_id ) {
		global $wpdb;

		$order_id      = (int) $order_id;
		$order_item_id = (int) $order_item_id;

		self::unlock_elapsed_for_item( $order_item_id );

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

		$item = self::find_item( $order, $order_item_id );
		if ( ! $item ) {
			return new WP_Error( 'som_item_missing', __( 'Order line not found.', 'order-machine' ) );
		}

		$current = self::current_item_progress( $order_item_id );
		if ( ! $current ) {
			return new WP_Error( 'som_no_workflow', __( 'This line has no active make step.', 'order-machine' ) );
		}

		$step = self::get_step( (int) $current->workflow_step_id );
		if ( ! $step ) {
			return new WP_Error( 'som_step_missing', __( 'Current workflow step not found.', 'order-machine' ) );
		}

		if ( SOM_Step_Confirmations::has_confirmation( $step )
			&& ! SOM_Step_Confirmations::progress_is_complete( $current, $step, $order ) ) {
			return new WP_Error(
				'som_confirmation_required',
				__( 'Complete the confirmation checklist before marking this step done.', 'order-machine' )
			);
		}

		if ( ! SOM_Workflow_Engine::can_mark_done( $current, $step ) ) {
			return new WP_Error( 'som_step_locked', __( 'This step cannot be marked done yet.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		$wpdb->update(
			SOM_DB::table( 'order_item_step_progress' ),
			array(
				'status'       => 'done',
				'completed_at' => $now,
				'updated_at'   => $now,
			),
			array( 'id' => (int) $current->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return self::advance_after_item_step( $order_id, $order_item_id, (int) $step->id );
	}

	/**
	 * Board / API DnD meta for a make line.
	 *
	 * @param int $order_id      Order PK.
	 * @param int $order_item_id Order item PK.
	 * @return array{can_advance: bool, next_step_name: string, is_last_step: bool, make_complete: bool}
	 */
	public static function board_dnd_meta( $order_id, $order_item_id ) {
		$out = array(
			'can_advance'    => false,
			'next_step_name' => '',
			'is_last_step'   => false,
			'make_complete'  => false,
		);

		$order = SOM_Orders::get( (int) $order_id );
		if ( ! $order || ! empty( $order->is_cancelled ) || ! empty( $order->is_complete ) ) {
			return $out;
		}

		$current = self::current_item_progress( (int) $order_item_id );
		if ( ! $current ) {
			$out['make_complete'] = self::item_has_progress( (int) $order_item_id );
			return $out;
		}

		$step = self::get_step( (int) $current->workflow_step_id );
		if ( ! $step ) {
			return $out;
		}

		$next = self::next_item_step_after( (int) $order_item_id, (int) $current->workflow_step_id );
		if ( $next ) {
			$out['next_step_name'] = trim( (string) $next->name );
		} else {
			$out['is_last_step'] = true;
		}

		$out['can_advance'] = SOM_Workflow_Engine::can_mark_done( $current, $step );
		return $out;
	}

	/**
	 * Compact progress for REST (unlocks timer first).
	 *
	 * @param int $order_id      Order PK.
	 * @param int $order_item_id Order item PK.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function progress_status_for_api( $order_id, $order_item_id ) {
		$order_id      = (int) $order_id;
		$order_item_id = (int) $order_item_id;
		$unlocked      = self::unlock_elapsed_for_item( $order_item_id );

		$order = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		$current = self::current_item_progress( $order_item_id );
		$payload = array(
			'ok'                => true,
			'order_id'          => $order_id,
			'order_item_id'     => $order_item_id,
			'unlocked'          => (bool) $unlocked,
			'external_order_id' => (string) $order->external_order_id,
			'current_step_id'   => $current ? (int) $current->workflow_step_id : 0,
			'step_name'         => $current ? (string) $current->step_name : '',
			'status'            => $current ? (string) $current->status : '',
			'timer_ends_at'     => null,
			'timer_ready'       => false,
			'can_advance'       => false,
			'next_step_name'    => '',
			'is_last_step'      => false,
			'make_complete'     => ! $current && self::item_has_progress( $order_item_id ),
			'is_complete'       => ! empty( $order->is_complete ),
			'is_cancelled'      => ! empty( $order->is_cancelled ),
		);

		if ( ! $current ) {
			return $payload;
		}

		if ( ! empty( $current->timer_ends_at ) ) {
			$ends = strtotime( (string) $current->timer_ends_at . ' UTC' );
			if ( ! $ends ) {
				$ends = strtotime( (string) $current->timer_ends_at );
			}
			$payload['timer_ends_at'] = $ends ? (int) $ends : null;
		}

		$payload['timer_ready'] = SOM_Workflow_Engine::is_timer_ready( $current );
		$meta                   = self::board_dnd_meta( $order_id, $order_item_id );
		$payload['can_advance']    = ! empty( $meta['can_advance'] );
		$payload['next_step_name'] = (string) $meta['next_step_name'];
		$payload['is_last_step']   = ! empty( $meta['is_last_step'] );

		return $payload;
	}

	/**
	 * Unlock waiting_timer on an item's current make step.
	 *
	 * @param int $order_item_id Order item PK.
	 * @return bool
	 */
	public static function unlock_elapsed_for_item( $order_item_id ) {
		global $wpdb;

		$order_item_id = (int) $order_item_id;
		$progress_t    = SOM_DB::table( 'order_item_step_progress' );
		$now           = current_time( 'mysql', true );

		$progress = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.* FROM {$progress_t} p
				INNER JOIN " . SOM_DB::table( 'orders' ) . " o ON o.id = p.order_id
				WHERE p.order_item_id = %d
					AND p.status = 'waiting_timer'
					AND p.timer_ends_at IS NOT NULL
					AND p.timer_ends_at <= %s
					AND o.is_complete = 0
				ORDER BY p.id ASC
				LIMIT 1",
				$order_item_id,
				$now
			)
		);
		if ( ! $progress ) {
			return false;
		}

		$step = self::get_step( (int) $progress->workflow_step_id );
		if ( ! $step ) {
			return false;
		}

		// Make v1: no scripts on make steps after filter; still handle if present.
		if ( SOM_Script_Dispatch::has_script( $step ) ) {
			$wpdb->update(
				$progress_t,
				array(
					'status'        => 'waiting_script',
					'timer_ends_at' => null,
					'updated_at'    => $now,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return true;
		}

		$updated = $wpdb->update(
			$progress_t,
			array(
				'status'     => 'in_progress',
				'updated_at' => $now,
			),
			array(
				'id'     => (int) $progress->id,
				'status' => 'waiting_timer',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $updated && (int) $updated > 0;
	}

	/**
	 * Cron: unlock elapsed item make timers.
	 *
	 * @return int Number unlocked.
	 */
	public static function tick_unlock_timers() {
		global $wpdb;

		$progress_t = SOM_DB::table( 'order_item_step_progress' );
		$orders_t   = SOM_DB::table( 'orders' );
		$now        = current_time( 'mysql', true );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.order_item_id
				FROM {$progress_t} p
				INNER JOIN {$orders_t} o ON o.id = p.order_id
				WHERE p.status = 'waiting_timer'
					AND p.timer_ends_at IS NOT NULL
					AND p.timer_ends_at <= %s
					AND o.is_complete = 0",
				$now
			)
		);

		$unlocked = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( self::unlock_elapsed_for_item( (int) $row->order_item_id ) ) {
					++$unlocked;
				}
			}
		}
		return $unlocked;
	}

	/**
	 * Unassigned / flag reason for make on an order (multi-line aware).
	 *
	 * @param object $order Order from SOM_Orders::get().
	 * @return string empty|needs_mapping|no_template|partial
	 */
	public static function unassigned_reason( $order ) {
		if ( ! $order || ! empty( $order->is_complete ) ) {
			return '';
		}

		if ( self::has_item_progress( (int) $order->id ) ) {
			return '';
		}

		// Legacy monolithic progress counts as assigned. Pack progress does not —
		// unmatched lines must still flag needs_mapping after Pack bind.
		$pack_bound = ! empty( $order->pack_workflow_template_id );
		if ( ! $pack_bound && SOM_Workflow_Engine::has_progress( (int) $order->id ) ) {
			return '';
		}

		if ( empty( $order->items ) || ! is_array( $order->items ) ) {
			return 'needs_mapping';
		}

		$channel = isset( $order->channel_slug ) ? (string) $order->channel_slug : '';
		$has_sellable_match = false;
		$has_template       = false;
		$has_unmatched      = false;

		foreach ( $order->items as $item ) {
			$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
			if ( $product_id < 1 ) {
				$has_unmatched = true;
				continue;
			}

			$product = null;
			if ( ! empty( $item->is_internal ) || isset( $item->product_is_internal ) ) {
				$is_internal = ! empty( $item->is_internal ) || ! empty( $item->product_is_internal );
			} else {
				global $wpdb;
				$product = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT is_internal, workflow_template_id FROM ' . SOM_DB::table( 'products' ) . ' WHERE id = %d LIMIT 1',
						$product_id
					)
				);
				$is_internal = $product && ! empty( $product->is_internal );
			}

			if ( $is_internal && SOM_Production::CHANNEL_SLUG !== $channel ) {
				continue;
			}

			$has_sellable_match = true;
			if ( $product && (int) $product->workflow_template_id > 0 ) {
				$has_template = true;
			} elseif ( ! $product ) {
				global $wpdb;
				$tid = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT workflow_template_id FROM ' . SOM_DB::table( 'products' ) . ' WHERE id = %d LIMIT 1',
						$product_id
					)
				);
				if ( $tid > 0 ) {
					$has_template = true;
				}
			}
		}

		if ( ! $has_sellable_match && $has_unmatched ) {
			return 'needs_mapping';
		}
		if ( $has_sellable_match && ! $has_template ) {
			return 'no_template';
		}
		if ( $has_unmatched && $has_sellable_match ) {
			return 'partial';
		}

		return $has_sellable_match ? '' : 'needs_mapping';
	}

	/**
	 * @param int                $order_id Order PK.
	 * @param int                $order_item_id Order item PK.
	 * @param array<int, object> $steps Make steps.
	 * @return true|WP_Error
	 */
	private static function insert_item_progress( $order_id, $order_item_id, array $steps ) {
		global $wpdb;

		$now        = current_time( 'mysql', true );
		$progress_t = SOM_DB::table( 'order_item_step_progress' );

		foreach ( $steps as $step ) {
			$inserted = $wpdb->insert(
				$progress_t,
				array(
					'order_id'         => (int) $order_id,
					'order_item_id'    => (int) $order_item_id,
					'workflow_step_id' => (int) $step->id,
					'status'           => 'pending',
					'timer_ends_at'    => null,
					'retry_count'      => 0,
					'last_error'       => null,
					'started_at'       => null,
					'completed_at'     => null,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_progress_create', __( 'Could not create make progress rows.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Enter a make step for an order line.
	 *
	 * @param int $order_id Order PK.
	 * @param int $order_item_id Order item PK.
	 * @param int $step_id Workflow step PK.
	 * @return true|WP_Error
	 */
	private static function enter_item_step( $order_id, $order_item_id, $step_id ) {
		global $wpdb;

		$order_id      = (int) $order_id;
		$order_item_id = (int) $order_item_id;
		$step_id       = (int) $step_id;
		$step          = self::get_step( $step_id );
		$progress      = self::get_progress_for_item_step( $order_item_id, $step_id );

		if ( ! $step || ! $progress ) {
			return new WP_Error( 'som_step_missing', __( 'Workflow step not found.', 'order-machine' ) );
		}

		$now     = current_time( 'mysql', true );
		$timer   = isset( $step->timer_seconds ) ? (int) $step->timer_seconds : 0;
		$manual  = ! empty( $step->requires_manual_confirm );
		$script  = SOM_Script_Dispatch::has_script( $step );
		$confirm = SOM_Step_Confirmations::has_confirmation( $step );
		$table   = SOM_DB::table( 'order_item_step_progress' );

		// Batch on make is disallowed at save time; if present, treat as error state.
		if ( ! empty( $step->batch_group_id ) ) {
			$wpdb->update(
				$table,
				array(
					'status'     => 'error',
					'last_error' => __( 'Batch groups are not allowed on make steps.', 'order-machine' ),
					'updated_at' => $now,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return new WP_Error( 'som_make_batch', __( 'Batch groups are not allowed on make steps.', 'order-machine' ) );
		}

		if ( $confirm ) {
			$manual = true;
		}

		if ( $timer < 1 && ! $manual && ! $script ) {
			$wpdb->update(
				$table,
				array(
					'status'        => 'done',
					'started_at'    => $now,
					'completed_at'  => $now,
					'timer_ends_at' => null,
					'updated_at'    => $now,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return self::advance_after_item_step( $order_id, $order_item_id, $step_id );
		}

		if ( $timer > 0 ) {
			$timer_ends_at = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp', true ) + $timer );
			$wpdb->update(
				$table,
				array(
					'status'        => 'waiting_timer',
					'started_at'    => $now,
					'timer_ends_at' => $timer_ends_at,
					'retry_count'   => 0,
					'last_error'    => null,
					'updated_at'    => $now,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
			return true;
		}

		if ( $script ) {
			// Scripts on make are rare; park waiting_script without auto-dispatch complexity.
			$wpdb->update(
				$table,
				array(
					'status'        => 'waiting_script',
					'started_at'    => $now,
					'timer_ends_at' => null,
					'retry_count'   => 0,
					'last_error'    => null,
					'updated_at'    => $now,
				),
				array( 'id' => (int) $progress->id ),
				array( '%s', '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
			return true;
		}

		$wpdb->update(
			$table,
			array(
				'status'        => 'in_progress',
				'started_at'    => $now,
				'timer_ends_at' => null,
				'updated_at'    => $now,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * @param int $order_id Order PK.
	 * @param int $order_item_id Order item PK.
	 * @param int $step_id Completed step PK.
	 * @return true|WP_Error
	 */
	private static function advance_after_item_step( $order_id, $order_item_id, $step_id ) {
		$next = self::next_item_step_after( $order_item_id, $step_id );
		if ( $next ) {
			return self::enter_item_step( $order_id, $order_item_id, (int) $next->id );
		}

		// Line make-complete. Internal Produce N: complete order when all lines done.
		$order = SOM_Orders::get( (int) $order_id );
		if ( $order && SOM_Production::CHANNEL_SLUG === (string) ( $order->channel_slug ?? '' ) ) {
			$all_done = true;
			foreach ( $order->items as $item ) {
				if ( ! self::item_is_make_complete( $item ) ) {
					$all_done = false;
					break;
				}
			}
			if ( $all_done ) {
				global $wpdb;
				$now = current_time( 'mysql', true );
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
				do_action( SOM_Production::HOOK_ORDER_COMPLETED, (int) $order_id );
			}
		}

		return true;
	}

	/**
	 * @param int $order_item_id Order item PK.
	 * @param int $current_step_id Current workflow_steps.id.
	 * @return object|null
	 */
	private static function next_item_step_after( $order_item_id, $current_step_id ) {
		global $wpdb;

		$progress_t = SOM_DB::table( 'order_item_step_progress' );
		$steps_t    = SOM_DB::table( 'workflow_steps' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.step_order, s.name
				FROM {$progress_t} p
				INNER JOIN {$steps_t} s ON s.id = p.workflow_step_id
				WHERE p.order_item_id = %d
				ORDER BY s.step_order ASC, s.id ASC",
				(int) $order_item_id
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

	/**
	 * @param int $order_item_id Order item PK.
	 * @param int $step_id Workflow step PK.
	 * @return object|null
	 */
	private static function get_progress_for_item_step( $order_item_id, $step_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'order_item_step_progress' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_item_id = %d AND workflow_step_id = %d LIMIT 1",
				(int) $order_item_id,
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
	 * @param object $order Order with items.
	 * @param int    $order_item_id Item PK.
	 * @return object|null
	 */
	private static function find_item( $order, $order_item_id ) {
		if ( empty( $order->items ) || ! is_array( $order->items ) ) {
			return null;
		}
		foreach ( $order->items as $item ) {
			if ( (int) $item->id === (int) $order_item_id ) {
				return $item;
			}
		}
		return null;
	}
}
