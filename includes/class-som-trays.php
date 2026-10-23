<?php
/**
 * Order-level physical tray assignments (Package 8 / UP8-S1).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site tray pool (1 … max) and held/released assignments on orders.
 */
class SOM_Trays {

	const OPTION_MAX = 'som_tray_max';

	const DEFAULT_MAX = 10;

	const MIN_MAX = 1;

	const MAX_MAX = 20;

	const STATUS_HELD     = 'held';
	const STATUS_RELEASED = 'released';

	const REASON_MANUAL          = 'manual';
	const REASON_MANUAL_OVERRIDE = 'manual_override';
	const REASON_ORDER_COMPLETE  = 'order_complete';

	/**
	 * Register completion listener (auto-release held trays).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( SOM_Production::HOOK_ORDER_COMPLETED, array( __CLASS__, 'on_order_completed' ), 5, 1 );
	}

	/**
	 * Release all held trays when an order completes (including Internal Produce N).
	 *
	 * @param int $order_id Order PK.
	 * @return void
	 */
	public static function on_order_completed( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return;
		}
		if ( empty( self::held_for_order( $order_id ) ) ) {
			return;
		}
		$result = self::release( $order_id, array(), self::REASON_ORDER_COMPLETE, null );
		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[Order Machine] Tray auto-release failed for order %d: %s',
					$order_id,
					$result->get_error_message()
				)
			);
		}
	}

	/**
	 * Whether the order requires tray assignment.
	 *
	 * True when the bound Pack template has Uses trays, when a sellable line's
	 * make template has Uses trays, or when the order channel is Internal and
	 * that product's make template has Uses trays. A customer order skips an
	 * internal component line.
	 *
	 * @param object|int $order Order row from SOM_Orders::get() or order PK.
	 * @return bool
	 */
	public static function order_requires_trays( $order ) {
		if ( is_numeric( $order ) ) {
			$order = SOM_Orders::get( (int) $order );
		}
		if ( ! $order ) {
			return false;
		}

		if ( ! empty( $order->pack_workflow_template_id ) ) {
			$pack_tpl = SOM_Workflows::get( (int) $order->pack_workflow_template_id );
			if ( $pack_tpl && ! empty( $pack_tpl->uses_trays ) ) {
				return true;
			}
		}

		if ( empty( $order->items ) || ! is_array( $order->items ) ) {
			return false;
		}

		$channel           = isset( $order->channel_slug ) ? (string) $order->channel_slug : '';
		$is_internal_order = ( SOM_Production::CHANNEL_SLUG === $channel );

		foreach ( $order->items as $item ) {
			$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
			if ( $product_id < 1 ) {
				continue;
			}
			$product = SOM_Products::get( $product_id );
			if ( ! $product ) {
				continue;
			}
			// Not made on a customer order, so the line does not require a tray by itself.
			if ( ! empty( $product->is_internal ) && ! $is_internal_order ) {
				continue;
			}
			$wf_id = ! empty( $product->workflow_template_id ) ? (int) $product->workflow_template_id : 0;
			if ( $wf_id < 1 ) {
				continue;
			}
			$tpl = SOM_Workflows::get( $wf_id );
			if ( $tpl && ! empty( $tpl->uses_trays ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the order currently holds at least one tray.
	 *
	 * @param int $order_id Order PK.
	 * @return bool
	 */
	public static function order_has_held_trays( $order_id ) {
		return ! empty( self::held_numbers_for_order( (int) $order_id ) );
	}

	/**
	 * Whether trays gate is satisfied for Mark done (not required, or ≥1 held).
	 * Manual unblock is handled separately in can_mark_done.
	 *
	 * @param object|int $order Order or PK.
	 * @return bool
	 */
	public static function trays_gate_ok( $order ) {
		if ( is_numeric( $order ) ) {
			$order_id = (int) $order;
			$order    = SOM_Orders::get( $order_id );
		} else {
			$order_id = $order && isset( $order->id ) ? (int) $order->id : 0;
		}
		if ( ! $order || $order_id < 1 ) {
			return true;
		}
		if ( ! self::order_requires_trays( $order ) ) {
			return true;
		}
		return self::order_has_held_trays( $order_id );
	}

	/**
	 * Map order_id => held tray numbers (ascending) for board badges.
	 *
	 * @param array<int, int> $order_ids Order PKs.
	 * @return array<int, array<int, int>>
	 */
	public static function held_numbers_by_orders( array $order_ids ) {
		global $wpdb;

		$ids = array();
		foreach ( $order_ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}
		$ids = array_values( $ids );
		if ( empty( $ids ) ) {
			return array();
		}

		$table        = SOM_DB::table( 'tray_assignments' );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( self::STATUS_HELD ), $ids );
		$sql          = "SELECT order_id, tray_number FROM {$table}
			WHERE status = %s AND order_id IN ({$placeholders})
			ORDER BY order_id ASC, tray_number ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		$map  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$oid = (int) $row->order_id;
				if ( ! isset( $map[ $oid ] ) ) {
					$map[ $oid ] = array();
				}
				$map[ $oid ][] = (int) $row->tray_number;
			}
		}
		return $map;
	}

	/**
	 * Attach tray_numbers arrays onto board card objects (mutates).
	 *
	 * @param array<int, object> $cards Board cards with order_id or id.
	 * @return void
	 */
	public static function attach_board_tray_numbers( array $cards ) {
		$ids = array();
		foreach ( $cards as $card ) {
			if ( isset( $card->order_id ) && (int) $card->order_id > 0 ) {
				$ids[] = (int) $card->order_id;
			} elseif ( isset( $card->id ) && (int) $card->id > 0 ) {
				$ids[] = (int) $card->id;
			}
		}
		$map = self::held_numbers_by_orders( $ids );
		foreach ( $cards as $card ) {
			$oid = 0;
			if ( isset( $card->order_id ) && (int) $card->order_id > 0 ) {
				$oid = (int) $card->order_id;
			} elseif ( isset( $card->id ) && (int) $card->id > 0 ) {
				$oid = (int) $card->id;
			}
			$card->tray_numbers = isset( $map[ $oid ] ) ? $map[ $oid ] : array();
		}
	}

	/**
	 * Site max tray number (1–20).
	 *
	 * @return int
	 */
	public static function get_max() {
		$max = (int) get_option( self::OPTION_MAX, self::DEFAULT_MAX );
		if ( $max < self::MIN_MAX || $max > self::MAX_MAX ) {
			return self::DEFAULT_MAX;
		}
		return $max;
	}

	/**
	 * Persist site max (O4). Blocks lowering while a higher tray is still held (O5).
	 *
	 * @param int $max Desired max (1–20).
	 * @return true|WP_Error
	 */
	public static function set_max( $max ) {
		$max = (int) $max;
		if ( $max < self::MIN_MAX || $max > self::MAX_MAX ) {
			return new WP_Error(
				'som_tray_max_range',
				sprintf(
					/* translators: 1: min, 2: max */
					__( 'Max trays must be between %1$d and %2$d.', 'order-machine' ),
					self::MIN_MAX,
					self::MAX_MAX
				)
			);
		}

		$blocking = self::held_tray_numbers_above( $max );
		if ( ! empty( $blocking ) ) {
			return new WP_Error(
				'som_tray_max_held',
				sprintf(
					/* translators: %s: comma-separated tray numbers */
					__( 'Cannot lower max trays while tray(s) %s are still held. Release them first.', 'order-machine' ),
					implode( ', ', array_map( 'strval', $blocking ) )
				)
			);
		}

		update_option( self::OPTION_MAX, $max, false );
		return true;
	}

	/**
	 * Held tray numbers strictly greater than $max (for O5 checks).
	 *
	 * @param int $max Proposed max.
	 * @return array<int, int>
	 */
	public static function held_tray_numbers_above( $max ) {
		global $wpdb;

		$max   = (int) $max;
		$table = SOM_DB::table( 'tray_assignments' );
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tray_number FROM {$table}
				WHERE status = %s AND tray_number > %d
				ORDER BY tray_number ASC",
				self::STATUS_HELD,
				$max
			)
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map( 'intval', $rows );
	}

	/**
	 * Free tray numbers in 1 … site max (no held row).
	 *
	 * @return array<int, int>
	 */
	public static function free_tray_numbers() {
		$max  = self::get_max();
		$held = self::held_tray_numbers();
		$free = array();
		for ( $n = 1; $n <= $max; $n++ ) {
			if ( ! isset( $held[ $n ] ) ) {
				$free[] = $n;
			}
		}
		return $free;
	}

	/**
	 * Map of currently held tray_number => order_id.
	 *
	 * @return array<int, int>
	 */
	public static function held_tray_numbers() {
		global $wpdb;

		$table = SOM_DB::table( 'tray_assignments' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tray_number, order_id FROM {$table} WHERE status = %s",
				self::STATUS_HELD
			)
		);
		$map = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$map[ (int) $row->tray_number ] = (int) $row->order_id;
			}
		}
		return $map;
	}

	/**
	 * Held assignment rows for an order (tray_number ascending).
	 *
	 * @param int $order_id Order PK.
	 * @return array<int, object>
	 */
	public static function held_for_order( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return array();
		}

		$table = SOM_DB::table( 'tray_assignments' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE order_id = %d AND status = %s
				ORDER BY tray_number ASC, id ASC",
				$order_id,
				self::STATUS_HELD
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Held tray numbers for an order.
	 *
	 * @param int $order_id Order PK.
	 * @return array<int, int>
	 */
	public static function held_numbers_for_order( $order_id ) {
		$nums = array();
		foreach ( self::held_for_order( $order_id ) as $row ) {
			$nums[] = (int) $row->tray_number;
		}
		return $nums;
	}

	/**
	 * Whether a tray number is currently held (any order).
	 *
	 * @param int $tray_number Tray number.
	 * @return bool
	 */
	public static function is_held( $tray_number ) {
		$held = self::held_tray_numbers();
		return isset( $held[ (int) $tray_number ] );
	}

	/**
	 * Assign one or more free trays to an order. Rejects trays held by another order.
	 * Trays already held by this order are skipped (idempotent).
	 *
	 * @param int             $order_id      Order PK.
	 * @param array<int, int> $tray_numbers  Tray numbers to assign.
	 * @param int|null        $user_id       WP user id (defaults to current).
	 * @return true|WP_Error
	 */
	public static function assign( $order_id, array $tray_numbers, $user_id = null ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 || ! SOM_Orders::get( $order_id ) ) {
			return new WP_Error( 'som_tray_order', __( 'Order not found.', 'order-machine' ) );
		}

		$normalized = self::normalize_tray_numbers( $tray_numbers );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		if ( empty( $normalized ) ) {
			return new WP_Error( 'som_tray_empty', __( 'Select at least one free tray to assign.', 'order-machine' ) );
		}

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		$user_id = (int) $user_id;
		$user_id = $user_id > 0 ? $user_id : null;

		$max  = self::get_max();
		$held = self::held_tray_numbers();
		$mine = array_flip( self::held_numbers_for_order( $order_id ) );
		$to_insert = array();

		foreach ( $normalized as $num ) {
			if ( $num < 1 || $num > $max ) {
				return new WP_Error(
					'som_tray_range',
					sprintf(
						/* translators: 1: tray number, 2: site max */
						__( 'Tray %1$d is outside the site pool (1–%2$d).', 'order-machine' ),
						$num,
						$max
					)
				);
			}
			if ( isset( $mine[ $num ] ) ) {
				continue;
			}
			if ( isset( $held[ $num ] ) ) {
				return new WP_Error(
					'som_tray_in_use',
					sprintf(
						/* translators: 1: tray number, 2: order id */
						__( 'Tray %1$d is already held by order #%2$d.', 'order-machine' ),
						$num,
						$held[ $num ]
					)
				);
			}
			$to_insert[] = $num;
		}

		if ( empty( $to_insert ) ) {
			return true;
		}

		$now   = current_time( 'mysql', true );
		$table = SOM_DB::table( 'tray_assignments' );

		foreach ( $to_insert as $num ) {
			// Re-check immediately before insert to reduce double-book races.
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT order_id FROM {$table} WHERE status = %s AND tray_number = %d LIMIT 1",
					self::STATUS_HELD,
					$num
				)
			);
			if ( null !== $existing && '' !== $existing ) {
				$holder = (int) $existing;
				if ( $holder === $order_id ) {
					continue;
				}
				return new WP_Error(
					'som_tray_in_use',
					sprintf(
						/* translators: 1: tray number, 2: order id */
						__( 'Tray %1$d is already held by order #%2$d.', 'order-machine' ),
						$num,
						$holder
					)
				);
			}

			$row = array(
				'tray_number' => $num,
				'order_id'    => $order_id,
				'status'      => self::STATUS_HELD,
				'assigned_at' => $now,
			);
			$fmt = array( '%d', '%d', '%s', '%s' );
			if ( null !== $user_id ) {
				$row['assigned_by_user_id'] = $user_id;
				$fmt[]                      = '%d';
			}
			$ok = $wpdb->insert( $table, $row, $fmt );
			if ( ! $ok ) {
				return new WP_Error( 'som_tray_assign', __( 'Could not assign tray.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Manually release held trays for an order (retain rows for audit).
	 *
	 * @param int             $order_id     Order PK.
	 * @param array<int, int> $tray_numbers Tray numbers to release (empty = all held).
	 * @param string          $reason       release_reason (manual / manual_override / order_complete).
	 * @param int|null        $user_id      WP user id.
	 * @return true|WP_Error
	 */
	public static function release( $order_id, array $tray_numbers = array(), $reason = self::REASON_MANUAL, $user_id = null ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 || ! SOM_Orders::get( $order_id ) ) {
			return new WP_Error( 'som_tray_order', __( 'Order not found.', 'order-machine' ) );
		}

		$reason = sanitize_key( (string) $reason );
		if ( self::REASON_ORDER_COMPLETE !== $reason && self::REASON_MANUAL_OVERRIDE !== $reason ) {
			$reason = self::REASON_MANUAL;
		}

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		$user_id = (int) $user_id;
		$user_id = $user_id > 0 ? $user_id : null;

		$held_rows = self::held_for_order( $order_id );
		if ( empty( $held_rows ) ) {
			return new WP_Error( 'som_tray_none_held', __( 'This order has no held trays to release.', 'order-machine' ) );
		}

		$targets = array();
		if ( empty( $tray_numbers ) ) {
			foreach ( $held_rows as $row ) {
				$targets[] = (int) $row->tray_number;
			}
		} else {
			$normalized = self::normalize_tray_numbers( $tray_numbers );
			if ( is_wp_error( $normalized ) ) {
				return $normalized;
			}
			$held_set = array();
			foreach ( $held_rows as $row ) {
				$held_set[ (int) $row->tray_number ] = true;
			}
			foreach ( $normalized as $num ) {
				if ( empty( $held_set[ $num ] ) ) {
					return new WP_Error(
						'som_tray_not_held',
						sprintf(
							/* translators: %d: tray number */
							__( 'Tray %d is not held by this order.', 'order-machine' ),
							$num
						)
					);
				}
				$targets[] = $num;
			}
		}

		if ( empty( $targets ) ) {
			return new WP_Error( 'som_tray_empty', __( 'Select at least one tray to release.', 'order-machine' ) );
		}

		// Soft default O2: while trays are required and the order is open, keep ≥1 held.
		if ( self::REASON_MANUAL === $reason ) {
			$order = SOM_Orders::get( $order_id );
			if ( $order && empty( $order->is_complete ) && self::order_requires_trays( $order ) ) {
				$held_count = count( $held_rows );
				$release_n  = count( $targets );
				if ( $release_n >= $held_count ) {
					return new WP_Error(
						'som_tray_keep_one',
						__( 'This order requires trays. Keep at least one held tray until the order is complete (or use Unblock step to advance without trays).', 'order-machine' )
					);
				}
			}
		}

		$now   = current_time( 'mysql', true );
		$table = SOM_DB::table( 'tray_assignments' );
		$data  = array(
			'status'         => self::STATUS_RELEASED,
			'released_at'    => $now,
			'release_reason' => $reason,
		);
		$fmt   = array( '%s', '%s', '%s' );
		if ( null !== $user_id ) {
			$data['released_by_user_id'] = $user_id;
			$fmt[]                       = '%d';
		}

		foreach ( $targets as $num ) {
			$updated = $wpdb->update(
				$table,
				$data,
				array(
					'order_id'    => $order_id,
					'tray_number' => $num,
					'status'      => self::STATUS_HELD,
				),
				$fmt,
				array( '%d', '%d', '%s' )
			);
			if ( false === $updated ) {
				return new WP_Error( 'som_tray_release', __( 'Could not release tray.', 'order-machine' ) );
			}
		}

		if ( self::REASON_MANUAL_OVERRIDE === $reason ) {
			self::add_override_note( $order_id, $targets, $user_id );
		}

		return true;
	}

	/**
	 * Order note for a Release anyway bypass. Does not complete a step.
	 *
	 * @param int             $order_id     Order PK.
	 * @param array<int, int> $tray_numbers Released tray numbers.
	 * @param int|null        $user_id      WP user id, when known.
	 * @return void
	 */
	private static function add_override_note( $order_id, array $tray_numbers, $user_id ) {
		global $wpdb;

		$actor_id = (int) $user_id;
		$who      = $actor_id > 0 ? SOM_Order_Notes::author_label( $actor_id ) : __( 'System', 'order-machine' );
		if ( 1 === count( $tray_numbers ) ) {
			$note = sprintf(
				/* translators: 1: tray number, 2: user display name */
				__( 'Tray %1$d released by %2$s. The tray requirement was bypassed. This does not complete a step.', 'order-machine' ),
				(int) $tray_numbers[0],
				$who
			);
		} else {
			$note = sprintf(
				/* translators: 1: comma-separated tray numbers, 2: user display name */
				__( 'Trays %1$s released by %2$s. The tray requirement was bypassed. This does not complete a step.', 'order-machine' ),
				implode( ', ', array_map( 'strval', $tray_numbers ) ),
				$who
			);
		}

		$note_user = $actor_id;
		if ( $note_user < 1 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is $wpdb->users.
			$note_user = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY ID ASC LIMIT 1" );
		}
		if ( $note_user < 1 ) {
			return;
		}

		SOM_Order_Notes::add( $order_id, $note, $note_user );
	}

	/**
	 * Sanitize a list of tray numbers (unique, positive ints).
	 *
	 * @param array<int, mixed> $tray_numbers Raw numbers.
	 * @return array<int, int>|WP_Error
	 */
	private static function normalize_tray_numbers( array $tray_numbers ) {
		$out = array();
		foreach ( $tray_numbers as $raw ) {
			$num = (int) $raw;
			if ( $num < 1 ) {
				continue;
			}
			$out[ $num ] = $num;
		}
		$out = array_values( $out );
		sort( $out, SORT_NUMERIC );
		return $out;
	}
}
