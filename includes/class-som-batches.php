<?php
/**
 * Step batch collecting, release, script run, and mark-done.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Batch Processing state machine (pooled across workflows per batch group).
 */
class SOM_Batches {

	const HOOK_BATCH_ATTEMPT = 'som_batch_attempt';

	const OPEN_STATUSES = array( 'collecting', 'ready', 'processing', 'error' );

	/**
	 * @param int $id Batch PK.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = SOM_DB::table( 'step_batches' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				(int) $id
			)
		);

		return $row ? $row : null;
	}

	/**
	 * @param int $batch_id Batch PK.
	 * @return array<int, object>
	 */
	public static function get_items( $batch_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'step_batch_items' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE batch_id = %d ORDER BY id ASC",
				(int) $batch_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Batch items with order display fields for admin UI.
	 *
	 * @param int $batch_id Batch PK.
	 * @return array<int, object>
	 */
	public static function get_items_with_orders( $batch_id ) {
		global $wpdb;

		$items_t  = SOM_DB::table( 'step_batch_items' );
		$orders_t = SOM_DB::table( 'orders' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, o.external_order_id, o.buyer_name, o.shipping_address, o.is_complete
				FROM {$items_t} i
				INNER JOIN {$orders_t} o ON o.id = i.order_id
				WHERE i.batch_id = %d
				ORDER BY i.id ASC",
				(int) $batch_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * List batches for admin (default: open statuses only).
	 *
	 * @param array<string, mixed> $args Filters: status, batch_group_id, include_done, paged, per_page.
	 * @return array{batches: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'         => '',
			'batch_group_id' => 0,
			'include_done'   => false,
			'paged'          => 1,
			'per_page'       => 50,
		);
		$args     = wp_parse_args( $args, $defaults );

		$batches_t = SOM_DB::table( 'step_batches' );
		$groups_t  = SOM_DB::table( 'batch_groups' );
		$items_t   = SOM_DB::table( 'step_batch_items' );

		$where  = array( '1=1' );
		$params = array();

		$status = sanitize_key( (string) $args['status'] );
		if ( $status ) {
			$where[]  = 'b.status = %s';
			$params[] = $status;
		} elseif ( empty( $args['include_done'] ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( self::OPEN_STATUSES ), '%s' ) );
			$where[]      = "b.status IN ({$placeholders})";
			foreach ( self::OPEN_STATUSES as $open ) {
				$params[] = $open;
			}
		}

		$group_id = (int) $args['batch_group_id'];
		if ( $group_id > 0 ) {
			$where[]  = 'b.batch_group_id = %d';
			$params[] = $group_id;
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, (int) $args['per_page'] );
		$paged     = max( 1, (int) $args['paged'] );
		$offset    = ( $paged - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$batches_t} b WHERE {$where_sql}";
		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$pages = max( 1, (int) ceil( $total / $per_page ) );
		if ( $paged > $pages ) {
			$paged  = $pages;
			$offset = ( $paged - 1 ) * $per_page;
		}

		$list_sql = "SELECT b.*, g.display_name AS group_name, g.group_key AS group_key,
				g.batch_size AS group_batch_size, g.action_type AS group_action_type,
				(SELECT COUNT(*) FROM {$items_t} i WHERE i.batch_id = b.id) AS item_count
			FROM {$batches_t} b
			INNER JOIN {$groups_t} g ON g.id = b.batch_group_id
			WHERE {$where_sql}
			ORDER BY FIELD(b.status, 'error', 'processing', 'ready', 'collecting', 'done'), b.id DESC
			LIMIT %d OFFSET %d";

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		foreach ( $rows as $row ) {
			$row->key = isset( $row->group_key ) ? $row->group_key : '';
		}

		return array(
			'batches' => $rows,
			'total'   => $total,
			'pages'   => $pages,
			'paged'   => $paged,
		);
	}

	/**
	 * Find the most relevant open batch containing an order (for order detail).
	 *
	 * @param int $order_id Order PK.
	 * @return object|null Batch row with group fields + item_count, or null.
	 */
	public static function find_for_order( $order_id ) {
		global $wpdb;

		$order_id  = (int) $order_id;
		$batches_t = SOM_DB::table( 'step_batches' );
		$groups_t  = SOM_DB::table( 'batch_groups' );
		$items_t   = SOM_DB::table( 'step_batch_items' );

		$placeholders = implode( ', ', array_fill( 0, count( self::OPEN_STATUSES ), '%s' ) );
		$params       = array_merge( array( $order_id ), self::OPEN_STATUSES );

		$sql = "SELECT b.*, g.display_name AS group_name, g.group_key AS group_key,
				g.batch_size AS group_batch_size, g.action_type AS group_action_type,
				(SELECT COUNT(*) FROM {$items_t} i2 WHERE i2.batch_id = b.id) AS item_count
			FROM {$items_t} i
			INNER JOIN {$batches_t} b ON b.id = i.batch_id
			INNER JOIN {$groups_t} g ON g.id = b.batch_group_id
			WHERE i.order_id = %d
				AND b.status IN ({$placeholders})
			ORDER BY FIELD(b.status, 'error', 'processing', 'ready', 'collecting'), b.id DESC
			LIMIT 1";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
		if ( $row && isset( $row->group_key ) ) {
			$row->key = $row->group_key;
		}

		return $row ? $row : null;
	}

	/**
	 * Status labels for admin badges.
	 *
	 * @return array<string, string>
	 */
	public static function status_labels() {
		return array(
			'collecting' => __( 'Collecting', 'order-machine' ),
			'ready'      => __( 'Ready', 'order-machine' ),
			'processing' => __( 'Processing', 'order-machine' ),
			'done'       => __( 'Done', 'order-machine' ),
			'error'      => __( 'Error', 'order-machine' ),
		);
	}

	/**
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = self::status_labels();
		$status = (string) $status;
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Admin Batches list URL.
	 *
	 * @param array<string, scalar> $args Extra query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		$args = array_merge( array( 'page' => 'som-batches' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Deep-link URL that expands a specific batch on the list.
	 *
	 * @param int $batch_id Batch PK.
	 * @return string
	 */
	public static function batch_url( $batch_id ) {
		return self::list_url( array( 'batch_id' => (int) $batch_id ) );
	}

	/**
	 * Open collecting batch for a group, if any.
	 *
	 * @param int $batch_group_id Group PK.
	 * @return object|null
	 */
	public static function get_collecting( $batch_group_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'step_batches' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE batch_group_id = %d AND status = 'collecting'
				ORDER BY id ASC
				LIMIT 1",
				(int) $batch_group_id
			)
		);
	}

	/**
	 * Enqueue an order into the group's collecting batch (create if needed).
	 * Called from enter_step when the step has batch_group_id.
	 *
	 * @param int    $order_id Order PK.
	 * @param object $step     Workflow step row (must include batch_group_id).
	 * @return true|WP_Error
	 */
	public static function enqueue( $order_id, $step ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$group_id = isset( $step->batch_group_id ) ? (int) $step->batch_group_id : 0;
		$step_id  = (int) $step->id;

		if ( $group_id < 1 ) {
			return new WP_Error( 'som_batch_group', __( 'Step has no batch group.', 'order-machine' ) );
		}

		$group = SOM_Batch_Groups::get( $group_id );
		if ( ! $group ) {
			return new WP_Error( 'som_batch_group_missing', __( 'Batch group not found.', 'order-machine' ) );
		}

		$progress = self::progress_for_step( $order_id, $step_id );
		if ( ! $progress ) {
			return new WP_Error( 'som_progress_missing', __( 'Progress row not found.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array(
				'status'        => 'waiting_batch',
				'started_at'    => $now,
				'timer_ends_at' => null,
				'retry_count'   => 0,
				'last_error'    => null,
			),
			array( 'id' => (int) $progress->id ),
			array( '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		$batch = self::get_collecting( $group_id );
		if ( ! $batch ) {
			$inserted = $wpdb->insert(
				SOM_DB::table( 'step_batches' ),
				array(
					'batch_group_id'    => $group_id,
					'status'            => 'collecting',
					'released_manually' => 0,
					'released_at'       => null,
					'completed_at'      => null,
					'last_error'        => null,
					'retry_count'       => 0,
					'retry_after'       => null,
					'created_at'        => $now,
					'updated_at'        => $now,
				),
				array( '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_batch_create', __( 'Could not create step batch.', 'order-machine' ) );
			}
			$batch = self::get( (int) $wpdb->insert_id );
			if ( ! $batch ) {
				return new WP_Error( 'som_batch_create', __( 'Could not create step batch.', 'order-machine' ) );
			}
		}

		$item_ok = $wpdb->insert(
			SOM_DB::table( 'step_batch_items' ),
			array(
				'batch_id'         => (int) $batch->id,
				'order_id'         => $order_id,
				'workflow_step_id' => $step_id,
				'added_at'         => $now,
			),
			array( '%d', '%d', '%d', '%s' )
		);
		if ( ! $item_ok ) {
			return new WP_Error( 'som_batch_item', __( 'Could not add order to batch.', 'order-machine' ) );
		}

		$count = count( self::get_items( (int) $batch->id ) );
		$size  = max( 1, (int) $group->batch_size );
		if ( $count >= $size ) {
			return self::release( (int) $batch->id, false );
		}

		return true;
	}

	/**
	 * Move a collecting batch to ready (and process script groups immediately).
	 *
	 * @param int  $batch_id Batch PK.
	 * @param bool $manual   Whether released via "Release now".
	 * @return true|WP_Error
	 */
	public static function release( $batch_id, $manual = true ) {
		global $wpdb;

		$batch = self::get( (int) $batch_id );
		if ( ! $batch ) {
			return new WP_Error( 'som_batch_missing', __( 'Batch not found.', 'order-machine' ) );
		}
		if ( 'collecting' !== (string) $batch->status ) {
			return new WP_Error( 'som_batch_not_collecting', __( 'Batch is not collecting.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->update(
			SOM_DB::table( 'step_batches' ),
			array(
				'status'            => 'ready',
				'released_manually' => $manual ? 1 : 0,
				'released_at'       => $now,
				'updated_at'        => $now,
			),
			array( 'id' => (int) $batch->id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_batch_release', __( 'Could not release batch.', 'order-machine' ) );
		}

		$batch = self::get( (int) $batch->id );
		return self::on_ready( $batch );
	}

	/**
	 * Mark a ready manual_confirm batch done and advance all members.
	 *
	 * @param int $batch_id Batch PK.
	 * @return true|WP_Error
	 */
	public static function mark_done( $batch_id ) {
		$batch = self::get( (int) $batch_id );
		if ( ! $batch ) {
			return new WP_Error( 'som_batch_missing', __( 'Batch not found.', 'order-machine' ) );
		}
		if ( 'ready' !== (string) $batch->status ) {
			return new WP_Error( 'som_batch_not_ready', __( 'Batch is not ready to mark done.', 'order-machine' ) );
		}

		$group = SOM_Batch_Groups::get( (int) $batch->batch_group_id );
		if ( ! $group || 'manual_confirm' !== (string) $group->action_type ) {
			return new WP_Error( 'som_batch_not_manual', __( 'Only manual_confirm batches use mark done.', 'order-machine' ) );
		}

		return self::complete_batch( $batch );
	}

	/**
	 * Manual retry after batch script error (resets retry budget and re-runs).
	 *
	 * @param int $batch_id Batch PK.
	 * @return true|WP_Error
	 */
	public static function retry( $batch_id ) {
		global $wpdb;

		$batch = self::get( (int) $batch_id );
		if ( ! $batch ) {
			return new WP_Error( 'som_batch_missing', __( 'Batch not found.', 'order-machine' ) );
		}
		if ( 'error' !== (string) $batch->status ) {
			return new WP_Error( 'som_batch_not_error', __( 'Batch is not in error state.', 'order-machine' ) );
		}

		$group = SOM_Batch_Groups::get( (int) $batch->batch_group_id );
		if ( ! $group || 'script' !== (string) $group->action_type ) {
			return new WP_Error( 'som_batch_not_script', __( 'Only script batches support retry.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		$wpdb->update(
			SOM_DB::table( 'step_batches' ),
			array(
				'status'      => 'processing',
				'retry_count' => 0,
				'retry_after' => null,
				'last_error'  => null,
				'updated_at'  => $now,
			),
			array( 'id' => (int) $batch->id ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		self::set_members_status( (int) $batch->id, 'waiting_batch', null );

		$batch = self::get( (int) $batch->id );
		return self::run_script( $batch, $group );
	}

	/**
	 * Cron / single-event: attempt a script batch due for retry.
	 *
	 * @param int $batch_id Batch PK.
	 * @return void
	 */
	public static function attempt_by_id( $batch_id ) {
		$batch = self::get( (int) $batch_id );
		if ( ! $batch || 'processing' !== (string) $batch->status ) {
			return;
		}
		if ( ! empty( $batch->retry_after ) && strtotime( (string) $batch->retry_after . ' UTC' ) > time() ) {
			return;
		}

		$group = SOM_Batch_Groups::get( (int) $batch->batch_group_id );
		if ( ! $group || 'script' !== (string) $group->action_type ) {
			return;
		}

		self::run_script( $batch, $group );
	}

	/**
	 * Process script batches whose retry_after has elapsed (engine tick).
	 *
	 * @return int Attempts started.
	 */
	public static function process_due_retries() {
		global $wpdb;

		$table = SOM_DB::table( 'step_batches' );
		$rows  = $wpdb->get_results(
			"SELECT id FROM {$table}
			WHERE status = 'processing'
				AND retry_after IS NOT NULL
				AND retry_after <= UTC_TIMESTAMP()"
		);
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $rows as $row ) {
			self::attempt_by_id( (int) $row->id );
			++$count;
		}
		return $count;
	}

	/**
	 * @param object|null $batch Batch row.
	 * @return true|WP_Error
	 */
	private static function on_ready( $batch ) {
		if ( ! $batch ) {
			return new WP_Error( 'som_batch_missing', __( 'Batch not found.', 'order-machine' ) );
		}

		$group = SOM_Batch_Groups::get( (int) $batch->batch_group_id );
		if ( ! $group ) {
			return new WP_Error( 'som_batch_group_missing', __( 'Batch group not found.', 'order-machine' ) );
		}

		if ( 'manual_confirm' === (string) $group->action_type ) {
			return true;
		}

		if ( 'script' !== (string) $group->action_type ) {
			return new WP_Error( 'som_batch_action', __( 'Unknown batch action type.', 'order-machine' ) );
		}

		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->update(
			SOM_DB::table( 'step_batches' ),
			array(
				'status'     => 'processing',
				'updated_at' => $now,
			),
			array( 'id' => (int) $batch->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$batch = self::get( (int) $batch->id );
		return self::run_script( $batch, $group );
	}

	/**
	 * @param object $batch Batch row.
	 * @param object $group Group row.
	 * @return true|WP_Error
	 */
	private static function run_script( $batch, $group ) {
		$raw = isset( $group->script_config ) ? trim( (string) $group->script_config ) : '';
		if ( '' === $raw ) {
			return self::complete_batch( $batch );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['type'] ) ) {
			return self::on_script_failure( $batch, __( 'Invalid batch script_config JSON.', 'order-machine' ), true );
		}

		$type = (string) $decoded['type'];
		if ( 'local' !== $type ) {
			return self::on_script_failure(
				$batch,
				__( 'Batch scripts only support type=local in this version.', 'order-machine' ),
				true
			);
		}

		$action = isset( $decoded['action'] ) ? (string) $decoded['action'] : '';
		$params = isset( $decoded['params'] ) && is_array( $decoded['params'] ) ? $decoded['params'] : array();
		$params['_batch_id'] = (int) $batch->id;

		$orders = array();
		foreach ( self::get_items( (int) $batch->id ) as $item ) {
			$order = SOM_Orders::get( (int) $item->order_id );
			if ( $order ) {
				$orders[] = $order;
			}
		}

		if ( empty( $orders ) ) {
			return self::on_script_failure( $batch, __( 'Batch has no orders to process.', 'order-machine' ), true );
		}

		$result = SOM_Local_Actions::run_for_orders( $action, $params, $orders );
		if ( true === $result ) {
			return self::complete_batch( $batch );
		}

		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Batch script failed.', 'order-machine' );
		return self::on_script_failure( $batch, $message, true );
	}

	/**
	 * @param object $batch   Batch row.
	 * @param string $message Error message.
	 * @param bool   $auto_retry Schedule backoff.
	 * @return true|WP_Error
	 */
	private static function on_script_failure( $batch, $message, $auto_retry ) {
		global $wpdb;

		$retry_count = (int) $batch->retry_count + 1;
		$message     = substr( (string) $message, 0, 2000 );
		$now         = current_time( 'mysql', true );

		if ( $auto_retry && $retry_count < SOM_Script_Dispatch::MAX_ATTEMPTS ) {
			$delay = ( 1 === $retry_count ) ? MINUTE_IN_SECONDS : ( 5 * MINUTE_IN_SECONDS );
			$next  = gmdate( 'Y-m-d H:i:s', time() + $delay );

			$wpdb->update(
				SOM_DB::table( 'step_batches' ),
				array(
					'status'      => 'processing',
					'retry_count' => $retry_count,
					'last_error'  => $message,
					'retry_after' => $next,
					'updated_at'  => $now,
				),
				array( 'id' => (int) $batch->id ),
				array( '%s', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);

			wp_schedule_single_event( time() + $delay, self::HOOK_BATCH_ATTEMPT, array( (int) $batch->id ) );
			return true;
		}

		$wpdb->update(
			SOM_DB::table( 'step_batches' ),
			array(
				'status'      => 'error',
				'retry_count' => $retry_count,
				'last_error'  => $message,
				'retry_after' => null,
				'updated_at'  => $now,
			),
			array( 'id' => (int) $batch->id ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		self::set_members_status( (int) $batch->id, 'error', $message );

		return new WP_Error( 'som_batch_script_error', $message );
	}

	/**
	 * @param object $batch Batch row.
	 * @return true|WP_Error
	 */
	private static function complete_batch( $batch ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->update(
			SOM_DB::table( 'step_batches' ),
			array(
				'status'       => 'done',
				'completed_at' => $now,
				'last_error'   => null,
				'retry_after'  => null,
				'updated_at'   => $now,
			),
			array( 'id' => (int) $batch->id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$items  = self::get_items( (int) $batch->id );
		$errors = array();
		foreach ( $items as $item ) {
			$result = SOM_Workflow_Engine::complete_batch_member( (int) $item->order_id, (int) $item->workflow_step_id );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'som_batch_advance',
				implode( '; ', array_slice( $errors, 0, 3 ) )
			);
		}

		return true;
	}

	/**
	 * @param int         $batch_id Batch PK.
	 * @param string      $status   Progress status.
	 * @param string|null $error    Optional last_error.
	 * @return void
	 */
	private static function set_members_status( $batch_id, $status, $error ) {
		global $wpdb;

		$items      = self::get_items( (int) $batch_id );
		$progress_t = SOM_DB::table( 'order_step_progress' );
		foreach ( $items as $item ) {
			$wpdb->update(
				$progress_t,
				array(
					'status'     => $status,
					'last_error' => $error,
				),
				array(
					'order_id'         => (int) $item->order_id,
					'workflow_step_id' => (int) $item->workflow_step_id,
				),
				array( '%s', '%s' ),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * @param int $order_id Order PK.
	 * @param int $step_id  Step PK.
	 * @return object|null
	 */
	private static function progress_for_step( $order_id, $step_id ) {
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
}
