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

	/** Board column / filter: not yet started. */
	const BOARD_UNSTARTED_KEY = '__unstarted__';

	/** Board column / filter: all post-ship steps done. */
	const BOARD_FINISHED_KEY = '__finished__';

	/** Board scope: in-progress aftercare (default). */
	const SCOPE_ACTIVE = 'active';

	/** Board scope: shipped but post-ship not started. */
	const SCOPE_UNSTARTED = 'unstarted';

	/** Board scope: post-ship lane finished. */
	const SCOPE_COMPLETE = 'complete';

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
	 * Admin URL for the Post-ship board.
	 *
	 * @param array<string, scalar> $args Extra query args.
	 * @return string
	 */
	public static function board_url( array $args = array() ) {
		$args = array_merge( array( 'page' => 'som-post-ship-board' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Sanitize board scope filter.
	 *
	 * @param string $scope Raw scope.
	 * @return string active|unstarted|complete
	 */
	public static function sanitize_scope( $scope ) {
		$scope = sanitize_key( (string) $scope );
		if ( in_array( $scope, array( self::SCOPE_ACTIVE, self::SCOPE_UNSTARTED, self::SCOPE_COMPLETE ), true ) ) {
			return $scope;
		}
		return self::SCOPE_ACTIVE;
	}

	/**
	 * Post-ship board query — order cards by aftercare step (O5 template filter).
	 *
	 * Scopes:
	 * - active (default): started with at least one pending post-ship step (incl. complete orders on review)
	 * - unstarted: shipment recorded, pack lane finished, post-ship not started
	 * - complete: post-ship started and all post-ship steps done
	 *
	 * @param array<string, mixed> $args Filters: scope, channel, workflow_template_id, s.
	 * @return array{orders: array<int, object>, total: int, capped: bool, warn: bool, workflow_steps: array<int, object>, scope: string}
	 */
	public static function query_board( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'scope'                => self::SCOPE_ACTIVE,
			'channel'              => '',
			'workflow_template_id' => 0,
			's'                    => '',
		);
		$args     = wp_parse_args( $args, $defaults );
		$scope    = self::sanitize_scope( (string) $args['scope'] );

		$orders_t    = SOM_DB::table( 'orders' );
		$channels_t  = SOM_DB::table( 'channels' );
		$items_t     = SOM_DB::table( 'order_items' );
		$steps_t     = SOM_DB::table( 'workflow_steps' );
		$progress_t  = SOM_DB::table( 'order_step_progress' );
		$templates_t = SOM_DB::table( 'workflow_templates' );
		$ship_t      = SOM_DB::table( 'shipments' );

		$cancelled = SOM_Orders::cancelled_sql( 'o', 'c' );
		$where     = array(
			"NOT {$cancelled}",
			'c.slug <> %s',
		);
		$params    = array( SOM_Production::CHANNEL_SLUG );

		$channel = sanitize_key( (string) $args['channel'] );
		if ( $channel && isset( SOM_Channels::known()[ $channel ] ) && SOM_Production::CHANNEL_SLUG !== $channel ) {
			$where[]  = 'c.slug = %s';
			$params[] = $channel;
		}

		$products_t  = SOM_DB::table( 'products' );
		$workflow_id = (int) $args['workflow_template_id'];
		if ( $workflow_id > 0 ) {
			if ( self::SCOPE_UNSTARTED === $scope ) {
				// Primary product override, else site default (same resolve order as start()).
				$primary_override = "(
					SELECT p_ps.post_ship_workflow_template_id
					FROM {$items_t} oi_ps
					INNER JOIN {$products_t} p_ps ON p_ps.id = oi_ps.product_id
					WHERE oi_ps.order_id = o.id AND oi_ps.product_id IS NOT NULL
					ORDER BY oi_ps.id ASC
					LIMIT 1
				)";
				$where[]  = "(
					( {$primary_override} IS NOT NULL AND {$primary_override} > 0 AND {$primary_override} = %d )
					OR (
						( {$primary_override} IS NULL OR {$primary_override} = 0 )
						AND %d = %d
					)
				)";
				$default  = self::default_template_id();
				$params[] = $workflow_id;
				$params[] = $workflow_id;
				$params[] = $default;
			} else {
				$where[]  = 'o.post_ship_workflow_template_id = %d';
				$params[] = $workflow_id;
			}
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

		$pending_ps = "EXISTS (
			SELECT 1 FROM {$progress_t} p_pen
			INNER JOIN {$steps_t} s_pen ON s_pen.id = p_pen.workflow_step_id
			INNER JOIN {$templates_t} t_pen ON t_pen.id = s_pen.workflow_template_id
			WHERE p_pen.order_id = o.id AND t_pen.kind = 'post_ship' AND p_pen.status <> 'done'
		)";
		$any_ps     = "EXISTS (
			SELECT 1 FROM {$progress_t} p_any
			INNER JOIN {$steps_t} s_any ON s_any.id = p_any.workflow_step_id
			INNER JOIN {$templates_t} t_any ON t_any.id = s_any.workflow_template_id
			WHERE p_any.order_id = o.id AND t_any.kind = 'post_ship'
		)";
		$pending_pack = "EXISTS (
			SELECT 1 FROM {$progress_t} p_pk
			INNER JOIN {$steps_t} s_pk ON s_pk.id = p_pk.workflow_step_id
			INNER JOIN {$templates_t} t_pk ON t_pk.id = s_pk.workflow_template_id
			WHERE p_pk.order_id = o.id AND t_pk.kind = 'pack' AND p_pk.status <> 'done'
		)";
		$has_ship = "EXISTS (
			SELECT 1 FROM {$ship_t} sh
			WHERE sh.order_id = o.id
				AND sh.carrier <> ''
				AND sh.service <> ''
				AND sh.shipped_at IS NOT NULL AND sh.shipped_at <> ''
				AND sh.postage_paid IS NOT NULL
		)";

		if ( self::SCOPE_UNSTARTED === $scope ) {
			$where[] = 'o.post_ship_workflow_template_id IS NULL';
			$where[] = 'o.post_ship_started_at IS NULL';
			$where[] = $has_ship;
			$where[] = "NOT {$pending_pack}";
			$where[] = "NOT {$any_ps}";
		} elseif ( self::SCOPE_COMPLETE === $scope ) {
			$where[] = 'o.post_ship_workflow_template_id IS NOT NULL';
			$where[] = $any_ps;
			$where[] = "NOT {$pending_ps}";
		} else {
			// Active: started with work remaining (review may run after is_complete).
			$where[] = 'o.post_ship_workflow_template_id IS NOT NULL';
			$where[] = $pending_ps;
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
				cur.workflow_step_id AS board_step_id,
				cur.status AS progress_status,
				cur.timer_ends_at,
				cur.started_at AS step_started_at,
				s.name AS current_step_name,
				s.step_order AS current_step_order,
				s.confirmation_kind
			FROM {$orders_t} o
			INNER JOIN {$channels_t} c ON c.id = o.channel_id
			LEFT JOIN {$progress_t} cur ON cur.id = (
				SELECT p2.id FROM {$progress_t} p2
				INNER JOIN {$steps_t} s2 ON s2.id = p2.workflow_step_id
				INNER JOIN {$templates_t} t2 ON t2.id = s2.workflow_template_id
				WHERE p2.order_id = o.id AND t2.kind = 'post_ship' AND p2.status <> 'done'
				ORDER BY s2.step_order ASC, s2.id ASC
				LIMIT 1
			)
			LEFT JOIN {$steps_t} s ON s.id = cur.workflow_step_id
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
			$card = $row;
			$card->id = (int) $row->id;

			if ( self::SCOPE_UNSTARTED === $scope ) {
				$card->column_key = self::BOARD_UNSTARTED_KEY;
			} elseif ( self::SCOPE_COMPLETE === $scope ) {
				$card->column_key = self::BOARD_FINISHED_KEY;
			} else {
				$step_name = trim( (string) ( $card->current_step_name ?? '' ) );
				$card->column_key = ( empty( $card->board_step_id ) || '' === $step_name )
					? SOM_Orders::BOARD_UNASSIGNED_KEY
					: $step_name;
			}

			$card->batch          = null;
			$card->timer_ends_ts  = 0;
			$card->timer_ready    = false;
			$card->can_advance    = false;
			$card->next_step_name = '';
			$card->is_last_step   = false;
			$card->resolved_template_id = 0;

			if ( self::SCOPE_UNSTARTED === $scope ) {
				$full = SOM_Orders::get( (int) $card->id );
				$card->resolved_template_id = $full ? self::resolve_template_id( $full ) : 0;
			}

			if ( ! empty( $card->timer_ends_at ) ) {
				$ends = strtotime( (string) $card->timer_ends_at . ' UTC' );
				if ( ! $ends ) {
					$ends = strtotime( (string) $card->timer_ends_at );
				}
				$card->timer_ends_ts = $ends ? (int) $ends : 0;
			}

			if ( self::SCOPE_ACTIVE === $scope && ! empty( $card->board_step_id ) ) {
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

		$workflow_steps = array();
		if ( $workflow_id > 0 ) {
			$workflow_steps = SOM_Workflows::get_steps( $workflow_id );
		}

		return array(
			'orders'          => $cards,
			'total'           => $total,
			'capped'          => $total > $cap,
			'warn'            => $total >= SOM_Orders::BOARD_WARN,
			'workflow_steps'  => $workflow_steps,
			'scope'           => $scope,
		);
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
