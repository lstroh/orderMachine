<?php
/**
 * Order-level Post-ship workflow (Package 11 / UP11-S1).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bind/start post-ship templates, arrival confirm → order complete.
 */
class SOM_Post_Ship {

	const OPTION_TEMPLATE   = 'som_post_ship_workflow_template_id';
	const OPTION_AUTO_START = 'som_post_ship_auto_start';

	/**
	 * Site default Post-ship template ID (0 if unset).
	 *
	 * @return int
	 */
	public static function default_template_id() {
		return max( 0, (int) get_option( self::OPTION_TEMPLATE, 0 ) );
	}

	/**
	 * Persist site default Post-ship template (must be kind=post_ship when set).
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
			return new WP_Error( 'som_post_ship_template', __( 'Post-ship workflow template not found.', 'order-machine' ) );
		}
		$kind = SOM_Workflows::sanitize_kind( isset( $template->kind ) ? $template->kind : 'make' );
		if ( 'post_ship' !== $kind ) {
			return new WP_Error( 'som_post_ship_template_kind', __( 'Default Post-ship template must have kind Post-ship.', 'order-machine' ) );
		}
		update_option( self::OPTION_TEMPLATE, $template_id, false );
		return true;
	}

	/**
	 * Whether auto-start post-ship after Pack Ship is enabled (default on — O2).
	 *
	 * @return bool
	 */
	public static function auto_start_enabled() {
		$raw = get_option( self::OPTION_AUTO_START, '1' );
		if ( false === $raw || null === $raw || '' === $raw ) {
			return true;
		}
		return (bool) (int) $raw;
	}

	/**
	 * Persist auto-start toggle.
	 *
	 * @param bool $enabled Enabled.
	 * @return void
	 */
	public static function set_auto_start_enabled( $enabled ) {
		update_option( self::OPTION_AUTO_START, $enabled ? '1' : '0', false );
	}

	/**
	 * Resolve post-ship template for an order (O4).
	 *
	 * 1. Primary sellable product override if active kind post_ship
	 * 2. Else site default
	 * 3. Else 0 (unbound)
	 *
	 * @param object $order Order from SOM_Orders::get().
	 * @return int
	 */
	public static function resolve_template_id( $order ) {
		$product_id = SOM_Workflow_Engine::primary_product_id( $order );
		if ( $product_id > 0 ) {
			global $wpdb;
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT post_ship_workflow_template_id FROM ' . SOM_DB::table( 'products' ) . ' WHERE id = %d LIMIT 1',
					$product_id
				)
			);
			$override = $row && ! empty( $row->post_ship_workflow_template_id )
				? (int) $row->post_ship_workflow_template_id
				: 0;
			if ( $override > 0 && self::template_is_usable( $override ) ) {
				return $override;
			}
		}

		$default = self::default_template_id();
		if ( $default > 0 && self::template_is_usable( $default ) ) {
			return $default;
		}

		return 0;
	}

	/**
	 * @param int $template_id Template PK.
	 * @return bool
	 */
	public static function template_is_usable( $template_id ) {
		$template_id = (int) $template_id;
		if ( $template_id < 1 ) {
			return false;
		}
		$template = SOM_Workflows::get( $template_id );
		if ( ! $template || empty( $template->is_active ) ) {
			return false;
		}
		$kind = SOM_Workflows::sanitize_kind( isset( $template->kind ) ? $template->kind : '' );
		if ( 'post_ship' !== $kind ) {
			return false;
		}
		return ! empty( $template->steps );
	}

	/**
	 * Whether post-ship has been started on this order.
	 *
	 * @param object $order Order row.
	 * @return bool
	 */
	public static function is_started( $order ) {
		return $order && ( ! empty( $order->post_ship_started_at ) || ! empty( $order->post_ship_workflow_template_id ) );
	}

	/**
	 * Auto-start after pack finishes when setting is on.
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error
	 */
	public static function maybe_auto_start_after_pack( $order_id ) {
		if ( ! self::auto_start_enabled() ) {
			return true;
		}
		return self::start( (int) $order_id, 0 );
	}

	/**
	 * Bind + start post-ship workflow (manual or auto).
	 *
	 * Idempotent when already started. Soft-succeeds when no template resolved
	 * (manual can pass an explicit template_id).
	 *
	 * @param int $order_id    Order PK.
	 * @param int $template_id Optional explicit template (0 = resolve).
	 * @return true|WP_Error
	 */
	public static function start( $order_id, $template_id = 0 ) {
		global $wpdb;

		$order_id    = (int) $order_id;
		$template_id = (int) $template_id;
		$order       = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}
		if ( ! empty( $order->is_cancelled ) ) {
			return new WP_Error( 'som_order_cancelled', __( 'Cancelled orders cannot start post-ship.', 'order-machine' ) );
		}

		$channel = isset( $order->channel_slug ) ? (string) $order->channel_slug : '';
		if ( SOM_Production::CHANNEL_SLUG === $channel ) {
			return true; // Internal Produce N — no post-ship.
		}

		if ( self::is_started( $order ) ) {
			// Already bound; ensure current_step is set if still mid post-ship.
			if ( empty( $order->current_step_id ) && ! empty( $order->post_ship_workflow_template_id ) ) {
				$first = self::first_pending_step_id( $order_id, (int) $order->post_ship_workflow_template_id );
				if ( $first > 0 ) {
					$wpdb->update(
						SOM_DB::table( 'orders' ),
						array(
							'current_step_id' => $first,
							'updated_at'      => current_time( 'mysql', true ),
						),
						array( 'id' => $order_id ),
						array( '%d', '%s' ),
						array( '%d' )
					);
					return SOM_Workflow_Engine::enter_step_public( $order_id, $first );
				}
			}
			return true;
		}

		if ( $template_id < 1 ) {
			$template_id = self::resolve_template_id( $order );
		}
		if ( $template_id < 1 ) {
			return true; // Soft flag — unbound; UI offers manual pick.
		}
		if ( ! self::template_is_usable( $template_id ) ) {
			return new WP_Error( 'som_post_ship_template', __( 'Selected Post-ship template is not available.', 'order-machine' ) );
		}

		$template = SOM_Workflows::get( $template_id );
		$steps    = isset( $template->steps ) ? $template->steps : array();
		if ( empty( $steps ) ) {
			return new WP_Error( 'som_post_ship_steps', __( 'Post-ship template has no steps.', 'order-machine' ) );
		}

		$now        = current_time( 'mysql', true );
		$progress_t = SOM_DB::table( 'order_step_progress' );

		foreach ( $steps as $step ) {
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$progress_t} WHERE order_id = %d AND workflow_step_id = %d LIMIT 1",
					$order_id,
					(int) $step->id
				)
			);
			if ( $exists > 0 ) {
				continue;
			}
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
				return new WP_Error( 'som_post_ship_progress', __( 'Could not create post-ship progress rows.', 'order-machine' ) );
			}
		}

		$first_id = (int) $steps[0]->id;
		$wpdb->update(
			SOM_DB::table( 'orders' ),
			array(
				'post_ship_workflow_template_id' => $template_id,
				'post_ship_started_at'           => $now,
				'current_step_id'                => $first_id,
				'updated_at'                     => $now,
			),
			array( 'id' => $order_id ),
			array( '%d', '%s', '%d', '%s' ),
			array( '%d' )
		);

		$entered = SOM_Workflow_Engine::enter_step_public( $order_id, $first_id );
		return is_wp_error( $entered ) ? $entered : true;
	}

	/**
	 * Record package arrived; set is_complete when shipment exists (O1).
	 *
	 * @param int $order_id Order PK.
	 * @param int $user_id  WP user (0 = current).
	 * @return true|WP_Error
	 */
	public static function confirm_arrival( $order_id, $user_id = 0 ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}
		if ( ! empty( $order->is_cancelled ) ) {
			return new WP_Error( 'som_order_cancelled', __( 'Cancelled orders cannot confirm arrival.', 'order-machine' ) );
		}

		$user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
		$now     = current_time( 'mysql', true );
		$fields  = array( 'updated_at' => $now );
		$format  = array( '%s' );

		if ( empty( $order->arrival_confirmed_at ) ) {
			$fields['arrival_confirmed_at'] = $now;
			$fields['arrival_confirmed_by'] = $user_id > 0 ? $user_id : null;
			$format[]                       = '%s';
			$format[]                       = '%d';
		}

		$newly_complete = false;
		if ( empty( $order->is_complete ) && SOM_Shipments::has_required( $order_id ) ) {
			$fields['is_complete'] = 1;
			$format[]              = '%d';
			$newly_complete        = true;
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'orders' ),
			$fields,
			array( 'id' => $order_id ),
			$format,
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_arrival_confirm', __( 'Could not confirm package arrival.', 'order-machine' ) );
		}

		if ( $newly_complete ) {
			/**
			 * Fires after an order workflow is marked complete.
			 *
			 * @param int $order_id Order PK.
			 */
			do_action( SOM_Production::HOOK_ORDER_COMPLETED, $order_id );
		}

		return true;
	}

	/**
	 * Whether a step belongs to a post_ship template.
	 *
	 * @param object|null $step Step row (needs workflow_template_id or template_kind).
	 * @return bool
	 */
	public static function is_post_ship_step( $step ) {
		if ( ! $step ) {
			return false;
		}
		if ( isset( $step->template_kind ) ) {
			return 'post_ship' === SOM_Workflows::sanitize_kind( $step->template_kind );
		}
		$tid = isset( $step->workflow_template_id ) ? (int) $step->workflow_template_id : 0;
		if ( $tid < 1 ) {
			return false;
		}
		return 'post_ship' === SOM_Workflows::get_kind( $tid );
	}

	/**
	 * Whether a confirmation step is the arrival gate.
	 *
	 * @param object|null $step Step row.
	 * @return bool
	 */
	public static function is_arrival_step( $step ) {
		return SOM_Step_Confirmations::KIND_PACKAGE_ARRIVED === SOM_Step_Confirmations::kind_from_step( $step );
	}

	/**
	 * Progress rows for post-ship (or pack) by template kind.
	 *
	 * @param array<int, object> $progress Progress rows with template_kind or workflow_template_id.
	 * @param string             $kind     make|pack|post_ship.
	 * @return array<int, object>
	 */
	public static function filter_progress_by_kind( array $progress, $kind ) {
		$kind   = SOM_Workflows::sanitize_kind( $kind );
		$out    = array();
		$cache  = array();
		foreach ( $progress as $row ) {
			$row_kind = '';
			if ( isset( $row->template_kind ) ) {
				$row_kind = SOM_Workflows::sanitize_kind( $row->template_kind );
			} else {
				$tid = isset( $row->workflow_template_id ) ? (int) $row->workflow_template_id : 0;
				if ( $tid > 0 ) {
					if ( ! isset( $cache[ $tid ] ) ) {
						$cache[ $tid ] = SOM_Workflows::get_kind( $tid );
					}
					$row_kind = $cache[ $tid ];
				}
			}
			if ( $row_kind === $kind ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * First pending (or any) post-ship step id for re-enter.
	 *
	 * @param int $order_id    Order PK.
	 * @param int $template_id Template PK.
	 * @return int
	 */
	private static function first_pending_step_id( $order_id, $template_id ) {
		global $wpdb;

		$progress_t = SOM_DB::table( 'order_step_progress' );
		$steps_t    = SOM_DB::table( 'workflow_steps' );

		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT s.id FROM {$progress_t} p
				INNER JOIN {$steps_t} s ON s.id = p.workflow_step_id
				WHERE p.order_id = %d AND s.workflow_template_id = %d AND p.status <> 'done'
				ORDER BY s.step_order ASC, s.id ASC
				LIMIT 1",
				(int) $order_id,
				(int) $template_id
			)
		);
		return $id;
	}
}
