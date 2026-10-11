<?php
/**
 * Order-level Pack & Ship (Package 6 / UP6-S2).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bind pack templates, ship gates, hold/package, pack board.
 */
class SOM_Pack {

	const OPTION_TEMPLATE = 'som_pack_workflow_template_id';

	const BOARD_WAITING_MAKE = '__waiting_make__';
	const BOARD_HELD         = '__held__';

	/**
	 * Site default Pack template ID (0 if unset).
	 *
	 * @return int
	 */
	public static function default_template_id() {
		return max( 0, (int) get_option( self::OPTION_TEMPLATE, 0 ) );
	}

	/**
	 * Persist site default Pack template (must be kind=pack when set).
	 *
	 * @param int $template_id Template PK or 0 to clear.
	 * @return true|WP_Error
	 */
	public static function set_default_template_id( $template_id ) {
		$template_id = (int) $template_id;
		if ( $template_id < 1 ) {
			delete_option( self::OPTION_TEMPLATE );
			return true;
		}
		$template = SOM_Workflows::get( $template_id );
		if ( ! $template ) {
			return new WP_Error( 'som_pack_template', __( 'Pack workflow template not found.', 'order-machine' ) );
		}
		$kind = SOM_Workflows::sanitize_kind( isset( $template->kind ) ? $template->kind : 'make' );
		if ( 'pack' !== $kind ) {
			return new WP_Error( 'som_pack_template_kind', __( 'Default Pack template must have kind Pack.', 'order-machine' ) );
		}
		update_option( self::OPTION_TEMPLATE, $template_id, false );
		return true;
	}

	/**
	 * Repair open non-Internal orders missing pack bind (UP6-S3 / O6).
	 *
	 * Skips Internal, complete/cancelled, already bound, and orders with
	 * legacy order-level progress (finish those manually — see migrate docs).
	 *
	 * @return array{repaired:int,skipped:int,errors:int}
	 */
	public static function repair_unbound_orders() {
		global $wpdb;

		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$cancelled  = SOM_Orders::cancelled_sql( 'o', 'c' );
		$slug       = esc_sql( SOM_Production::CHANNEL_SLUG );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- cancelled_sql is alias-safe; slug escaped.
		$rows = $wpdb->get_results(
			"SELECT o.id FROM {$orders_t} o
			INNER JOIN {$channels_t} c ON c.id = o.channel_id
			WHERE o.is_complete = 0
				AND NOT {$cancelled}
				AND c.slug <> '{$slug}'
				AND o.pack_workflow_template_id IS NULL
			ORDER BY o.id ASC"
		);
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$repaired = 0;
		$skipped  = 0;
		$errors   = 0;

		foreach ( $rows as $row ) {
			$order_id = (int) $row->id;
			if ( SOM_Workflow_Engine::has_progress( $order_id ) ) {
				++$skipped;
				continue;
			}
			$result = self::bind_on_create( $order_id );
			if ( is_wp_error( $result ) ) {
				++$errors;
				continue;
			}
			$order = SOM_Orders::get( $order_id );
			if ( $order && ! empty( $order->pack_workflow_template_id ) ) {
				++$repaired;
			} else {
				++$skipped; // Soft-flag: no default Pack template configured.
			}
		}

		return array(
			'repaired' => $repaired,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Bind pack workflow on create for non-internal orders.
	 *
	 * Soft-flags when no default Pack template (O14). Idempotent.
	 *
	 * @param int                  $order_id Order PK.
	 * @param array<string, mixed> $args     Optional: sync_package_materials (bool, default true).
	 * @return true|WP_Error
	 */
	public static function bind_on_create( $order_id, array $args = array() ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}
		if ( ! empty( $order->is_cancelled ) || ! empty( $order->is_complete ) ) {
			return true;
		}

		$channel = isset( $order->channel_slug ) ? (string) $order->channel_slug : '';
		if ( SOM_Production::CHANNEL_SLUG === $channel ) {
			return true;
		}

		if ( ! empty( $order->pack_workflow_template_id ) ) {
			return true;
		}

		// Already has order-level progress (legacy monolithic or prior bind attempt).
		if ( SOM_Workflow_Engine::has_progress( $order_id ) ) {
			return true;
		}

		$template_id = self::default_template_id();
		if ( $template_id < 1 ) {
			return true; // Soft flag in UI (O14).
		}

		$template = SOM_Workflows::get( $template_id );
		if ( ! $template || 'pack' !== SOM_Workflows::sanitize_kind( isset( $template->kind ) ? $template->kind : '' ) ) {
			return true;
		}

		$steps = isset( $template->steps ) ? $template->steps : array();
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
				return new WP_Error( 'som_pack_progress', __( 'Could not create pack progress rows.', 'order-machine' ) );
			}
		}

		$suggested = self::suggest_package_id( $order );
		$wpdb->update(
			SOM_DB::table( 'orders' ),
			array(
				'pack_workflow_template_id' => $template_id,
				'shipping_package_id'       => $suggested > 0 ? $suggested : null,
				'current_step_id'           => (int) $steps[0]->id,
				'is_complete'               => 0,
				'updated_at'                => $now,
			),
			array( 'id' => $order_id ),
			array( '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		$entered = SOM_Workflow_Engine::enter_step_public( $order_id, (int) $steps[0]->id );

		$sync_package = ! array_key_exists( 'sync_package_materials', $args ) || ! empty( $args['sync_package_materials'] );
		if ( $sync_package && $suggested > 0 ) {
			$reserved = SOM_Material_Stock::sync_package_reserve( $order_id );
			if ( is_wp_error( $reserved ) ) {
				return $reserved;
			}
		}

		return is_wp_error( $entered ) ? $entered : true;
	}

	/**
	 * Suggested package id (see suggest_package()).
	 *
	 * @param object $order Order with items.
	 * @return int
	 */
	public static function suggest_package_id( $order ) {
		$result = self::suggest_package( $order );
		return (int) $result['package_id'];
	}

	/**
	 * Whether Pack UI should flag mixed-product package conflict (O10).
	 *
	 * @param object $order Order with items.
	 * @return bool
	 */
	public static function has_mixed_package_conflict( $order ) {
		$result = self::suggest_package( $order );
		return ! empty( $result['mixed_conflict'] );
	}

	/**
	 * Suggest shipping package from order shape (UP7-S3).
	 *
	 * Rules (soft defaults O1/O2/O10):
	 * - Total sellable qty = sum of matched non-internal line quantities.
	 * - Qty 1 → that line's package_id.
	 * - Qty ≥ 2 → each line votes package_id_multi if set else package_id;
	 *   agreement → use it; conflict → site default + mixed_conflict flag.
	 *
	 * @param object $order Order with items.
	 * @return array{package_id:int,mixed_conflict:bool,total_sellable_qty:int}
	 */
	public static function suggest_package( $order ) {
		global $wpdb;

		$default_id = self::site_default_package_id();
		$result     = array(
			'package_id'          => $default_id,
			'mixed_conflict'      => false,
			'total_sellable_qty'  => 0,
		);

		if ( ! $order || empty( $order->items ) || ! is_array( $order->items ) ) {
			return $result;
		}

		$products_t = SOM_DB::table( 'products' );
		$lines      = array();
		$total_qty  = 0;

		foreach ( $order->items as $item ) {
			$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
			if ( $product_id < 1 ) {
				continue;
			}
			$qty = isset( $item->quantity ) ? (int) $item->quantity : 0;
			if ( $qty < 1 ) {
				continue;
			}

			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT package_id, package_id_multi, is_internal FROM {$products_t} WHERE id = %d LIMIT 1",
					$product_id
				)
			);
			if ( ! $row || ! empty( $row->is_internal ) ) {
				continue;
			}

			$total_qty += $qty;
			$lines[]    = $row;
		}

		$result['total_sellable_qty'] = $total_qty;

		if ( $total_qty < 1 || empty( $lines ) ) {
			return $result;
		}

		if ( 1 === $total_qty ) {
			$single = (int) ( $lines[0]->package_id ?? 0 );
			$result['package_id'] = $single > 0 ? $single : $default_id;
			return $result;
		}

		$candidates = array();
		foreach ( $lines as $row ) {
			$multi = ! empty( $row->package_id_multi ) ? (int) $row->package_id_multi : 0;
			$single = ! empty( $row->package_id ) ? (int) $row->package_id : 0;
			$vote   = $multi > 0 ? $multi : $single;
			if ( $vote > 0 ) {
				$candidates[] = $vote;
			}
		}

		if ( empty( $candidates ) ) {
			return $result;
		}

		$unique = array_values( array_unique( $candidates ) );
		if ( 1 === count( $unique ) ) {
			$result['package_id'] = (int) $unique[0];
			return $result;
		}

		$result['package_id']     = $default_id;
		$result['mixed_conflict'] = true;
		return $result;
	}

	/**
	 * Active site default shipping package id, or 0.
	 *
	 * @return int
	 */
	public static function site_default_package_id() {
		$default = SOM_Shipping_Packages::get_default();
		return $default ? (int) $default->id : 0;
	}

	/**
	 * All sellable matched lines make-complete (internal lines always OK).
	 *
	 * @param object $order Order from SOM_Orders::get().
	 * @return bool
	 */
	public static function order_is_make_ready( $order ) {
		if ( ! $order || empty( $order->items ) || ! is_array( $order->items ) ) {
			return false;
		}

		$has_sellable = false;
		foreach ( $order->items as $item ) {
			$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
			if ( $product_id < 1 ) {
				return false; // Unmatched blocks Ship (O1).
			}
			if ( ! empty( $item->product_is_internal ) ) {
				continue;
			}
			// Ensure is_internal loaded.
			if ( ! isset( $item->product_is_internal ) ) {
				global $wpdb;
				$is_int = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT is_internal FROM ' . SOM_DB::table( 'products' ) . ' WHERE id = %d LIMIT 1',
						$product_id
					)
				);
				if ( $is_int ) {
					continue;
				}
			}
			$has_sellable = true;
			if ( ! SOM_Item_Make::item_is_make_complete( $item ) ) {
				return false;
			}
		}

		return $has_sellable || self::only_internal_lines( $order );
	}

	/**
	 * @param object $order Order.
	 * @return bool
	 */
	private static function only_internal_lines( $order ) {
		if ( empty( $order->items ) ) {
			return false;
		}
		foreach ( $order->items as $item ) {
			$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
			if ( $product_id < 1 ) {
				return false;
			}
			if ( empty( $item->product_is_internal ) ) {
				global $wpdb;
				$is_int = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT is_internal FROM ' . SOM_DB::table( 'products' ) . ' WHERE id = %d LIMIT 1',
						$product_id
					)
				);
				if ( ! $is_int ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Whether pack hold is active.
	 *
	 * @param object $order Order row.
	 * @return bool
	 */
	public static function is_held( $order ) {
		return $order && ! empty( $order->pack_hold_reason ) && '' !== trim( (string) $order->pack_hold_reason );
	}

	/**
	 * Human-readable reason Ship is blocked, or empty if OK (aside from shipment row / step gates).
	 *
	 * @param object $order Order from get().
	 * @return string
	 */
	public static function ship_blocked_reason( $order ) {
		if ( ! $order ) {
			return __( 'Order not found.', 'order-machine' );
		}
		if ( empty( $order->pack_workflow_template_id ) ) {
			return __( 'No Pack workflow bound. Set a default Pack template in Settings.', 'order-machine' );
		}
		if ( ! self::order_is_make_ready( $order ) ) {
			return __( 'Waiting for make: not all sellable lines are ready to pack.', 'order-machine' );
		}
		if ( self::is_held( $order ) ) {
			return __( 'Pack is on hold.', 'order-machine' );
		}
		if ( empty( $order->shipping_package_id ) ) {
			return __( 'Select a shipping package before Ship.', 'order-machine' );
		}
		return '';
	}

	/**
	 * Set or clear pack hold.
	 *
	 * @param int         $order_id Order PK.
	 * @param string|null $reason   Non-empty to hold; empty/null to clear.
	 * @param int         $user_id  WP user.
	 * @return true|WP_Error
	 */
	public static function set_hold( $order_id, $reason, $user_id = 0 ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		$reason  = null !== $reason ? trim( (string) $reason ) : '';
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$now     = current_time( 'mysql', true );

		if ( '' === $reason ) {
			$fields = array(
				'pack_hold_reason' => null,
				'pack_held_at'     => null,
				'pack_held_by'     => null,
				'updated_at'       => $now,
			);
			$format = array( '%s', '%s', '%s', '%s' );
		} else {
			$fields = array(
				'pack_hold_reason' => $reason,
				'pack_held_at'     => $now,
				'pack_held_by'     => $user_id > 0 ? $user_id : null,
				'updated_at'       => $now,
			);
			$format = array( '%s', '%s', '%d', '%s' );
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'orders' ),
			$fields,
			array( 'id' => $order_id ),
			$format,
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_pack_hold', __( 'Could not update pack hold.', 'order-machine' ) );
		}
		return true;
	}

	/**
	 * Set selected shipping package on the order.
	 *
	 * @param int $order_id   Order PK.
	 * @param int $package_id Package PK (0 clears).
	 * @return true|WP_Error
	 */
	public static function set_shipping_package( $order_id, $package_id ) {
		global $wpdb;

		$order_id   = (int) $order_id;
		$package_id = (int) $package_id;
		if ( ! SOM_Orders::get( $order_id ) ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		if ( $package_id > 0 ) {
			$pkg = SOM_Shipping_Packages::get( $package_id );
			if ( ! $pkg || empty( $pkg->is_active ) ) {
				return new WP_Error( 'som_package', __( 'Selected shipping package is not available.', 'order-machine' ) );
			}
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'orders' ),
			array(
				'shipping_package_id' => $package_id > 0 ? $package_id : null,
				'updated_at'          => current_time( 'mysql', true ),
			),
			array( 'id' => $order_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_package_save', __( 'Could not save shipping package.', 'order-machine' ) );
		}

		$reserved = SOM_Material_Stock::sync_package_reserve( $order_id );
		if ( is_wp_error( $reserved ) ) {
			return $reserved;
		}

		return true;
	}

	/**
	 * Stamp packed_by / packed_at once when checklist first becomes complete.
	 *
	 * @param int $order_id Order PK.
	 * @return void
	 */
	public static function maybe_stamp_packed( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order || ! empty( $order->packed_at ) ) {
			return;
		}

		// Find packing_items progress on pack workflow.
		foreach ( SOM_Workflow_Engine::get_progress( $order_id ) as $row ) {
			$kind = SOM_Step_Confirmations::sanitize_kind(
				isset( $row->confirmation_kind ) ? $row->confirmation_kind : null
			);
			if ( SOM_Step_Confirmations::KIND_PACKING_ITEMS !== $kind ) {
				continue;
			}
			if ( ! SOM_Step_Confirmations::is_complete( $kind, SOM_Step_Confirmations::decode_state( $row ), $order ) ) {
				return;
			}
			$user_id = get_current_user_id();
			$wpdb->update(
				SOM_DB::table( 'orders' ),
				array(
					'packed_by_user_id' => $user_id > 0 ? $user_id : null,
					'packed_at'         => current_time( 'mysql', true ),
					'updated_at'        => current_time( 'mysql', true ),
				),
				array( 'id' => $order_id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}
	}

	/**
	 * Best-effort buyer / channel note from raw_payload.
	 *
	 * @param object $order Order.
	 * @return string
	 */
	public static function buyer_note( $order ) {
		if ( ! $order || empty( $order->raw_payload ) ) {
			return '';
		}
		$data = is_string( $order->raw_payload )
			? json_decode( (string) $order->raw_payload, true )
			: $order->raw_payload;
		if ( ! is_array( $data ) ) {
			return '';
		}
		$keys = array(
			'message_from_buyer',
			'buyer_message',
			'gift_message',
			'note',
			'buyer_note',
			'message',
		);
		foreach ( $keys as $key ) {
			if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				return trim( $data[ $key ] );
			}
		}
		if ( ! empty( $data['fulfillmentStartInstructions'] ) && is_array( $data['fulfillmentStartInstructions'] ) ) {
			foreach ( $data['fulfillmentStartInstructions'] as $block ) {
				if ( is_array( $block ) && ! empty( $block['shippingStep']['shipTo']['note'] ) ) {
					return trim( (string) $block['shippingStep']['shipTo']['note'] );
				}
			}
		}
		return '';
	}

	/**
	 * Pack board query — open non-internal orders with pack binding (or soft-flag missing).
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{orders: array<int, object>, total: int, capped: bool, warn: bool}
	 */
	public static function query_board( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'channel' => '',
			's'       => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$items_t    = SOM_DB::table( 'order_items' );
		$steps_t    = SOM_DB::table( 'workflow_steps' );
		$progress_t = SOM_DB::table( 'order_step_progress' );

		$cancelled = SOM_Orders::cancelled_sql( 'o', 'c' );
		$where     = array(
			'o.is_complete = 0',
			"NOT {$cancelled}",
			'c.slug <> %s',
		);
		$params    = array( SOM_Production::CHANNEL_SLUG );

		// Bound pack OR missing bind (soft flag) — exclude pure legacy without pack intent? Show pack-bound + unbound non-legacy with item make.
		$where[] = '( o.pack_workflow_template_id IS NOT NULL OR EXISTS (
			SELECT 1 FROM ' . SOM_DB::table( 'order_item_step_progress' ) . ' ip WHERE ip.order_id = o.id
		) )';

		$channel = sanitize_key( (string) $args['channel'] );
		if ( $channel && isset( SOM_Channels::known()[ $channel ] ) && SOM_Production::CHANNEL_SLUG !== $channel ) {
			$where[]  = 'c.slug = %s';
			$params[] = $channel;
		}

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( o.buyer_name LIKE %s OR o.external_order_id LIKE %s OR EXISTS (
				SELECT 1 FROM ' . $items_t . ' oi_s WHERE oi_s.order_id = o.id AND oi_s.personalisation_text LIKE %s
			) )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$cap       = SOM_Orders::BOARD_CAP;

		$count_sql = "SELECT COUNT(*) FROM {$orders_t} o
			INNER JOIN {$channels_t} c ON c.id = o.channel_id
			WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		$list_sql = "SELECT
				o.*,
				c.slug AS channel_slug,
				c.display_name AS channel_name,
				( SELECT s.name FROM {$steps_t} s WHERE s.id = o.current_step_id LIMIT 1 ) AS current_step_name,
				( SELECT s.step_order FROM {$steps_t} s WHERE s.id = o.current_step_id LIMIT 1 ) AS current_step_order,
				( SELECT s.confirmation_kind FROM {$steps_t} s WHERE s.id = o.current_step_id LIMIT 1 ) AS confirmation_kind,
				( SELECT osp.status FROM {$progress_t} osp
					WHERE osp.order_id = o.id AND osp.workflow_step_id = o.current_step_id
					LIMIT 1 ) AS progress_status,
				( SELECT osp.timer_ends_at FROM {$progress_t} osp
					WHERE osp.order_id = o.id AND osp.workflow_step_id = o.current_step_id
					LIMIT 1 ) AS timer_ends_at,
				( SELECT osp.started_at FROM {$progress_t} osp
					WHERE osp.order_id = o.id AND osp.workflow_step_id = o.current_step_id
					LIMIT 1 ) AS step_started_at
			FROM {$orders_t} o
			INNER JOIN {$channels_t} c ON c.id = o.channel_id
			WHERE {$where_sql}
			ORDER BY o.order_date ASC, o.id ASC
			LIMIT %d";

		$list_params = array_merge( $params, array( $cap ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$orders = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );
		if ( ! is_array( $orders ) ) {
			$orders = array();
		}

		$cards = array();
		foreach ( $orders as $row ) {
			$full = SOM_Orders::get( (int) $row->id );
			if ( ! $full ) {
				continue;
			}
			$card = $row;
			$card->make_ready = self::order_is_make_ready( $full );
			$card->is_held    = self::is_held( $full );
			$card->buyer_note = self::buyer_note( $full );

			if ( $card->is_held ) {
				$card->column_key = self::BOARD_HELD;
			} elseif ( ! $card->make_ready ) {
				$card->column_key = self::BOARD_WAITING_MAKE;
			} else {
				$step_name = trim( (string) ( $card->current_step_name ?? '' ) );
				$card->column_key = ( empty( $card->current_step_id ) || '' === $step_name )
					? SOM_Orders::BOARD_UNASSIGNED_KEY
					: $step_name;
			}

			$card->batch          = null;
			$card->timer_ends_ts  = 0;
			$card->timer_ready    = false;
			$card->can_advance    = false;
			$card->next_step_name = '';
			$card->is_last_step   = false;

			if ( ! empty( $card->timer_ends_at ) ) {
				$ends = strtotime( (string) $card->timer_ends_at . ' UTC' );
				if ( ! $ends ) {
					$ends = strtotime( (string) $card->timer_ends_at );
				}
				$card->timer_ends_ts = $ends ? (int) $ends : 0;
			}

			if ( $card->make_ready && ! $card->is_held && ! empty( $card->current_step_id ) ) {
				if ( 'waiting_timer' === (string) $card->progress_status
					&& $card->timer_ends_ts > 0
					&& time() >= $card->timer_ends_ts ) {
					$status = SOM_Workflow_Engine::progress_status_for_api( (int) $card->id );
					if ( ! is_wp_error( $status ) ) {
						$card->progress_status = (string) ( $status['status'] ?? $card->progress_status );
						$card->timer_ready     = ! empty( $status['timer_ready'] );
						$card->can_advance     = ! empty( $status['can_advance'] );
						$card->next_step_name  = (string) ( $status['next_step_name'] ?? '' );
						$card->is_last_step    = ! empty( $status['is_last_step'] );
					}
				} else {
					SOM_Orders::attach_board_dnd_meta( $card );
				}
			}

			$cards[] = $card;
		}

		SOM_Trays::attach_board_tray_numbers( $cards );

		return array(
			'orders' => $cards,
			'total'  => $total,
			'capped' => $total > $cap,
			'warn'   => $total >= SOM_Orders::BOARD_WARN,
		);
	}
}
