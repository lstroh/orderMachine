<?php
/**
 * Workflow templates and steps (Sprint 6).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for workflow templates and ordered steps. Execution is Sprint 7+.
 */
class SOM_Workflows {

	/**
	 * Templates per page on the admin list.
	 */
	const PER_PAGE = 20;

	/**
	 * Provisional local action allowlist (handlers land in Sprint 9).
	 *
	 * @return array<string, string> action_key => label
	 */
	public static function local_action_choices() {
		return array(
			'run_thankyou_card_script' => __( 'Run thank-you card script', 'order-machine' ),
			'send_print_job'           => __( 'Send print job', 'order-machine' ),
		);
	}

	/**
	 * Allowed template kinds.
	 *
	 * @return array<string, string> kind => label
	 */
	public static function kind_choices() {
		return array(
			'make'      => __( 'Make', 'order-machine' ),
			'pack'      => __( 'Pack', 'order-machine' ),
			'post_ship' => __( 'Post-ship', 'order-machine' ),
		);
	}

	/**
	 * Sanitize template kind.
	 *
	 * @param mixed $kind Raw kind.
	 * @return string make|pack|post_ship
	 */
	public static function sanitize_kind( $kind ) {
		$kind = sanitize_key( (string) $kind );
		return isset( self::kind_choices()[ $kind ] ) ? $kind : 'make';
	}

	/**
	 * Lightweight kind lookup (no steps / products).
	 *
	 * @param int $template_id Template PK.
	 * @return string make|pack|post_ship
	 */
	public static function get_kind( $template_id ) {
		global $wpdb;

		$template_id = (int) $template_id;
		if ( $template_id < 1 ) {
			return 'make';
		}
		$kind = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT kind FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE id = %d LIMIT 1',
				$template_id
			)
		);
		return self::sanitize_kind( $kind );
	}

	/**
	 * Query templates for the admin list.
	 *
	 * @param array<string, mixed> $args Filters: status, kind, s, paged, per_page.
	 * @return array{templates: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => 'active',
			'kind'     => '',
			's'        => '',
			'paged'    => 1,
			'per_page' => self::PER_PAGE,
		);
		$args     = wp_parse_args( $args, $defaults );

		$templates_t = SOM_DB::table( 'workflow_templates' );
		$steps_t     = SOM_DB::table( 'workflow_steps' );
		$products_t  = SOM_DB::table( 'products' );

		$where  = array( '1=1' );
		$params = array();

		$status = sanitize_key( (string) $args['status'] );
		if ( 'active' === $status ) {
			$where[] = 't.is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where[] = 't.is_active = 0';
		}

		$kind = sanitize_key( (string) $args['kind'] );
		if ( isset( self::kind_choices()[ $kind ] ) ) {
			$where[]  = 't.kind = %s';
			$params[] = $kind;
		}

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( t.name LIKE %s OR t.description LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$templates_t} t WHERE {$where_sql}";
		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$paged    = max( 1, min( (int) $args['paged'], $pages ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$list_sql = "SELECT
				t.*,
				( SELECT COUNT(*) FROM {$steps_t} s WHERE s.workflow_template_id = t.id ) AS step_count,
				( SELECT COUNT(*) FROM {$products_t} p WHERE p.workflow_template_id = t.id ) AS product_count
			FROM {$templates_t} t
			WHERE {$where_sql}
			ORDER BY t.is_active DESC, t.name ASC, t.id ASC
			LIMIT %d OFFSET %d";

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$templates   = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );
		if ( ! is_array( $templates ) ) {
			$templates = array();
		}

		return array(
			'templates' => $templates,
			'total'     => $total,
			'pages'     => $pages,
			'paged'     => $paged,
		);
	}

	/**
	 * Active templates for product assignment dropdowns (make kind only).
	 *
	 * @param int $include_id Also include this template even if inactive (current assignment).
	 * @return array<int, object>
	 */
	public static function list_for_dropdown( $include_id = 0 ) {
		global $wpdb;

		$table      = SOM_DB::table( 'workflow_templates' );
		$include_id = (int) $include_id;

		if ( $include_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, is_active, kind FROM {$table}
					WHERE ( ( is_active = 1 AND kind = 'make' ) OR id = %d )
					ORDER BY is_active DESC, name ASC, id ASC",
					$include_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				"SELECT id, name, is_active, kind FROM {$table}
				WHERE is_active = 1 AND kind = 'make'
				ORDER BY name ASC, id ASC"
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Fetch one template with ordered steps and product usage count.
	 *
	 * @param int $template_id Template PK.
	 * @return object|null
	 */
	public static function get( $template_id ) {
		global $wpdb;

		$template_id = (int) $template_id;
		$table       = SOM_DB::table( 'workflow_templates' );
		$products_t  = SOM_DB::table( 'products' );

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.*,
					( SELECT COUNT(*) FROM {$products_t} p WHERE p.workflow_template_id = t.id ) AS product_count
				FROM {$table} t
				WHERE t.id = %d
				LIMIT 1",
				$template_id
			)
		);

		if ( ! $template ) {
			return null;
		}

		$template->steps     = self::get_steps( $template_id );
		$template->in_use    = (int) $template->product_count > 0;
		$template->products  = self::get_assigned_products( $template_id );

		return $template;
	}

	/**
	 * Ordered steps for a template.
	 *
	 * @param int $template_id Template PK.
	 * @return array<int, object>
	 */
	public static function get_steps( $template_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'workflow_steps' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE workflow_template_id = %d
				ORDER BY step_order ASC, id ASC",
				(int) $template_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Products currently assigned to this template.
	 *
	 * @param int $template_id Template PK.
	 * @return array<int, object>
	 */
	public static function get_assigned_products( $template_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'products' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, sku, is_active FROM {$table}
				WHERE workflow_template_id = %d
				ORDER BY name ASC, id ASC",
				(int) $template_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Create a template (optionally with initial steps).
	 *
	 * @param array<string, mixed> $data Fields: name, description, is_active, kind, uses_trays.
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'som_workflow_name', __( 'Template name is required.', 'order-machine' ) );
		}

		$description = isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '';
		$kind        = self::sanitize_kind( isset( $data['kind'] ) ? $data['kind'] : 'make' );
		// Post-ship never uses trays (Package 11).
		$uses_trays  = ( 'post_ship' !== $kind && ! empty( $data['uses_trays'] ) ) ? 1 : 0;
		$now         = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			SOM_DB::table( 'workflow_templates' ),
			array(
				'name'        => $name,
				'description' => '' !== $description ? $description : null,
				'kind'        => $kind,
				'uses_trays'  => $uses_trays,
				'is_active'   => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'som_workflow_create', __( 'Could not create workflow template.', 'order-machine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update template meta (name, description, is_active).
	 *
	 * @param int                  $template_id Template PK.
	 * @param array<string, mixed> $data        Fields to update.
	 * @return true|WP_Error
	 */
	public static function update( $template_id, array $data ) {
		global $wpdb;

		$template_id = (int) $template_id;
		$existing    = self::get( $template_id );
		if ( ! $existing ) {
			return new WP_Error( 'som_workflow_missing', __( 'Workflow template not found.', 'order-machine' ) );
		}

		$fields = array(
			'updated_at' => current_time( 'mysql', true ),
		);
		$format = array( '%s' );

		if ( array_key_exists( 'name', $data ) ) {
			$name = sanitize_text_field( (string) $data['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'som_workflow_name', __( 'Template name is required.', 'order-machine' ) );
			}
			$fields['name'] = $name;
			$format[]       = '%s';
		}

		if ( array_key_exists( 'description', $data ) ) {
			$description           = sanitize_textarea_field( (string) $data['description'] );
			$fields['description'] = '' !== $description ? $description : null;
			$format[]              = '%s';
		}

		if ( array_key_exists( 'is_active', $data ) ) {
			$want_active = (bool) $data['is_active'];
			if ( ! $want_active && (int) $existing->product_count > 0 ) {
				return new WP_Error(
					'som_workflow_in_use',
					__( 'Cannot deactivate: one or more products still use this template. Reassign those products first.', 'order-machine' )
				);
			}
			$fields['is_active'] = (int) $want_active;
			$format[]            = '%d';
		}

		if ( array_key_exists( 'kind', $data ) ) {
			$fields['kind'] = self::sanitize_kind( $data['kind'] );
			$format[]       = '%s';
			if ( in_array( $fields['kind'], array( 'pack', 'post_ship' ), true ) && (int) $existing->product_count > 0 ) {
				return new WP_Error(
					'som_workflow_kind_pack',
					__( 'Cannot set kind to Pack or Post-ship while products still use this template as Make.', 'order-machine' )
				);
			}
		}

		if ( array_key_exists( 'uses_trays', $data ) || array_key_exists( 'kind', $data ) ) {
			$kind_for_trays = array_key_exists( 'kind', $fields )
				? $fields['kind']
				: self::sanitize_kind( isset( $existing->kind ) ? $existing->kind : 'make' );
			$want_trays = array_key_exists( 'uses_trays', $data ) ? ! empty( $data['uses_trays'] ) : ! empty( $existing->uses_trays );
			$fields['uses_trays'] = ( 'post_ship' !== $kind_for_trays && $want_trays ) ? 1 : 0;
			$format[]             = '%d';
		}

		$updated = $wpdb->update(
			SOM_DB::table( 'workflow_templates' ),
			$fields,
			array( 'id' => $template_id ),
			$format,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'som_workflow_update', __( 'Could not update workflow template.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Error when a Post-ship template has no Package arrived step.
	 *
	 * @return string
	 */
	public static function post_ship_arrival_required_message() {
		return __( 'A Post-ship workflow needs at least one step with Confirmation checklist = Package arrived. Nothing was saved.', 'order-machine' );
	}

	/**
	 * Whether any step row uses Confirmation checklist = Package arrived.
	 *
	 * Accepts form rows, template steps, or order progress rows. Uses
	 * SOM_Post_Ship::is_arrival_step() so every surface shares one test.
	 *
	 * @param array<int, array<string, mixed>|object> $steps Step rows.
	 * @return bool
	 */
	public static function steps_include_arrival( array $steps ) {
		foreach ( $steps as $step ) {
			if ( ( is_array( $step ) || is_object( $step ) ) && SOM_Post_Ship::is_arrival_step( $step ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a stored template currently has a Package arrived step.
	 *
	 * False when the id is missing. Does not repair the row. Workflows list,
	 * the editor, and Settings use this to warn. Order detail checks the
	 * order's bound post-ship progress with steps_include_arrival() instead,
	 * because editing the template later does not rebind that order.
	 *
	 * @param int $template_id Template PK.
	 * @return bool
	 */
	public static function template_has_arrival_step( $template_id ) {
		$template_id = (int) $template_id;
		if ( $template_id < 1 ) {
			return false;
		}
		return self::steps_include_arrival( self::get_steps( $template_id ) );
	}

	/**
	 * Side-effect-free check of a step submission for the resulting kind.
	 *
	 * Normalises steps the same way save_steps() does (reads only), then
	 * requires a Package arrived step when that kind is post_ship. The active
	 * flag is not consulted. Call this before create/update. clone_template()
	 * calls it before inserting a copy, so a Post-ship template with no
	 * Package arrived step — and a template whose steps fail the combo
	 * checks — is rejected with nothing written.
	 *
	 * @param mixed                            $kind        Resulting template kind.
	 * @param array<int, array<string, mixed>> $steps       Form step rows.
	 * @param int                              $template_id Existing template, or 0 when creating.
	 * @return true|WP_Error
	 */
	public static function validate_submission( $kind, array $steps, $template_id = 0 ) {
		$kind       = self::sanitize_kind( $kind );
		$normalized = self::normalize_step_rows( (int) $template_id, $steps, $kind );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		return self::assert_post_ship_has_arrival( $kind, $normalized );
	}

	/**
	 * Validate, then create or update the template and replace its steps.
	 *
	 * On validation failure nothing is written: no template update, no new
	 * template row, and no step changes.
	 *
	 * @param int                              $template_id 0 to create.
	 * @param array<string, mixed>             $data        Template fields.
	 * @param array<int, array<string, mixed>> $steps       Form step rows.
	 * @return int|WP_Error Template id.
	 */
	public static function save_submission( $template_id, array $data, array $steps ) {
		$template_id = (int) $template_id;
		if ( array_key_exists( 'kind', $data ) ) {
			$kind = self::sanitize_kind( $data['kind'] );
		} elseif ( $template_id > 0 ) {
			$kind = self::get_kind( $template_id );
		} else {
			$kind = 'make';
		}
		$valid = self::validate_submission( $kind, $steps, $template_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $template_id > 0 ) {
			$updated = self::update( $template_id, $data );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		} else {
			$created = self::create( $data );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$template_id = (int) $created;
		}

		$saved = self::save_steps( $template_id, $steps );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return $template_id;
	}

	/**
	 * Copy a workflow template into an independent template named "X (copy)".
	 *
	 * Copies the template row (name, description, kind, uses_trays, is_active),
	 * every step in the same step_order (editor columns only — not legacy
	 * script_config), material goals, and budget links. Products, orders, and
	 * site Pack / Post-ship defaults are left on the original.
	 *
	 * Validation matches Save: validate_submission() (Post-ship arrival rule
	 * and the per-step combo checks). On any failure the copy is rolled back
	 * and then deleted, so no half template remains.
	 *
	 * @param int $template_id Source template PK.
	 * @return int|WP_Error New template id.
	 */
	public static function clone_template( $template_id ) {
		global $wpdb;

		$template_id = (int) $template_id;
		$source      = self::get( $template_id );
		if ( ! $source ) {
			return new WP_Error( 'som_workflow_missing', __( 'Workflow template not found.', 'order-machine' ) );
		}

		$kind = self::sanitize_kind( isset( $source->kind ) ? $source->kind : 'make' );
		$rows = array();
		foreach ( $source->steps as $step ) {
			if ( is_object( $step ) ) {
				$rows[] = self::step_to_submission_row( $step );
			}
		}

		$valid = self::validate_submission( $kind, $rows, $template_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$wpdb->query( 'START TRANSACTION' );

		$created = self::create(
			array(
				'name'        => self::clone_copy_name( (string) $source->name ),
				'description' => isset( $source->description ) ? (string) $source->description : '',
				'kind'        => $kind,
				'uses_trays'  => ! empty( $source->uses_trays ),
				'is_active'   => (int) $source->is_active,
			)
		);
		if ( is_wp_error( $created ) ) {
			self::abort_clone_write( 0 );
			return $created;
		}

		$new_id = (int) $created;
		$steps  = self::insert_cloned_steps( $new_id, $source->steps );
		if ( is_wp_error( $steps ) ) {
			self::abort_clone_write( $new_id );
			return $steps;
		}

		$goals = self::copy_material_goals( $template_id, $new_id );
		if ( is_wp_error( $goals ) ) {
			self::abort_clone_write( $new_id );
			return $goals;
		}

		$links = self::copy_budget_workflow_links( $template_id, $new_id );
		if ( is_wp_error( $links ) ) {
			self::abort_clone_write( $new_id );
			return $links;
		}

		$committed = $wpdb->query( 'COMMIT' );
		if ( false === $committed ) {
			self::abort_clone_write( $new_id );
			return new WP_Error( 'som_workflow_clone', __( 'Could not clone workflow template.', 'order-machine' ) );
		}

		return $new_id;
	}

	/**
	 * Clone control: POST, nonce som_clone_workflow, manage_options is checked by the handler.
	 *
	 * @param int    $template_id  Template PK.
	 * @param string $return       editor|list — where to send the operator if clone fails.
	 * @param string $button_class Button class.
	 * @return void
	 */
	public static function render_clone_button( $template_id, $return = 'list', $button_class = 'button-link' ) {
		$template_id = (int) $template_id;
		if ( $template_id < 1 ) {
			return;
		}

		$return       = 'editor' === $return ? 'editor' : 'list';
		$button_class = sanitize_html_class( $button_class );
		if ( '' === $button_class ) {
			$button_class = 'button-link';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-workflows' ) ); ?>" class="som-clone-workflow-form">
			<?php wp_nonce_field( 'som_clone_workflow', 'som_workflow_clone_nonce' ); ?>
			<input type="hidden" name="som_clone_workflow" value="1" />
			<input type="hidden" name="template_id" value="<?php echo esc_attr( (string) $template_id ); ?>" />
			<input type="hidden" name="som_clone_return" value="<?php echo esc_attr( $return ); ?>" />
			<button type="submit" class="<?php echo esc_attr( $button_class ); ?>"><?php echo esc_html__( 'Clone', 'order-machine' ); ?></button>
		</form>
		<?php
	}

	/**
	 * "X (copy)", trimmed so the result fits the 100-character name column.
	 *
	 * @param string $name Source template name.
	 * @return string
	 */
	private static function clone_copy_name( $name ) {
		$name   = sanitize_text_field( (string) $name );
		$suffix = ' (copy)';
		$limit  = 100;
		if ( self::text_length( $name . $suffix ) <= $limit ) {
			return $name . $suffix;
		}

		$keep = $limit - self::text_length( $suffix );
		$name = rtrim( self::text_cut( $name, $keep ) );
		return $name . $suffix;
	}

	/**
	 * Stored step as the form row validate_submission() already understands.
	 *
	 * Passing every editor field makes the combo checks see this step's
	 * stored gates. script_config is omitted so it is not treated as a gate.
	 *
	 * @param object $step Step row.
	 * @return array<string, mixed>
	 */
	private static function step_to_submission_row( $step ) {
		$tasks = array();
		if ( isset( $step->task_list_json ) && is_string( $step->task_list_json ) && '' !== trim( $step->task_list_json ) ) {
			$tasks = SOM_Step_Tasks::decode_list( $step->task_list_json );
		}

		return array(
			'id'                      => isset( $step->id ) ? (int) $step->id : 0,
			'name'                    => isset( $step->name ) ? (string) $step->name : '',
			'requires_manual_confirm' => ! empty( $step->requires_manual_confirm ) ? 1 : 0,
			'timer_seconds'           => self::nullable_db_int( isset( $step->timer_seconds ) ? $step->timer_seconds : null ),
			'planned_seconds'         => self::nullable_db_int( isset( $step->planned_seconds ) ? $step->planned_seconds : null ),
			'command_template'        => isset( $step->command_template ) ? $step->command_template : null,
			'batch_group_id'          => ! empty( $step->batch_group_id ) ? (int) $step->batch_group_id : 0,
			'confirmation_kind'       => isset( $step->confirmation_kind ) ? $step->confirmation_kind : '',
			'instructions'            => isset( $step->instructions ) && null !== $step->instructions ? (string) $step->instructions : '',
			'tasks'                   => $tasks,
		);
	}

	/**
	 * Insert new step rows for a clone. Legacy script_config is stored as null.
	 *
	 * @param int           $template_id New template PK.
	 * @param array<int, object> $steps  Source steps in step_order.
	 * @return true|WP_Error
	 */
	private static function insert_cloned_steps( $template_id, array $steps ) {
		global $wpdb;

		$table = SOM_DB::table( 'workflow_steps' );
		$now   = current_time( 'mysql', true );

		foreach ( $steps as $step ) {
			if ( ! is_object( $step ) ) {
				continue;
			}

			$name = isset( $step->name ) ? sanitize_text_field( (string) $step->name ) : '';
			if ( '' === $name ) {
				continue;
			}

			$batch = self::nullable_db_int( isset( $step->batch_group_id ) ? $step->batch_group_id : null );
			if ( null !== $batch && $batch < 1 ) {
				$batch = null;
			}

			$inserted = $wpdb->insert(
				$table,
				array(
					'workflow_template_id'    => (int) $template_id,
					'step_order'              => isset( $step->step_order ) ? (int) $step->step_order : 1,
					'name'                    => $name,
					'requires_manual_confirm' => ! empty( $step->requires_manual_confirm ) ? 1 : 0,
					'timer_seconds'           => self::nullable_db_int( isset( $step->timer_seconds ) ? $step->timer_seconds : null ),
					'planned_seconds'         => self::nullable_db_int( isset( $step->planned_seconds ) ? $step->planned_seconds : null ),
					'script_config'           => null,
					'command_template'        => self::nullable_db_text( isset( $step->command_template ) ? $step->command_template : null ),
					'batch_group_id'          => $batch,
					'confirmation_kind'       => self::nullable_db_text( isset( $step->confirmation_kind ) ? $step->confirmation_kind : null ),
					'instructions'            => self::nullable_db_text( isset( $step->instructions ) ? $step->instructions : null ),
					'task_list_json'          => self::nullable_db_text( isset( $step->task_list_json ) ? $step->task_list_json : null ),
					'created_at'              => $now,
					'updated_at'              => $now,
				),
				array( '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_step_create', __( 'Could not create a workflow step.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Copy workflow_material_goals onto the new template id.
	 *
	 * @param int $source_id Source template PK.
	 * @param int $new_id    Clone template PK.
	 * @return true|WP_Error
	 */
	private static function copy_material_goals( $source_id, $new_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'workflow_material_goals' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT material_id, goal_unit_cost, warning_threshold_percent FROM {$table} WHERE workflow_template_id = %d ORDER BY id ASC",
				(int) $source_id
			)
		);
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'som_goal_clone', __( 'Could not copy material goals.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		foreach ( $rows as $row ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'workflow_template_id'      => (int) $new_id,
					'material_id'               => (int) $row->material_id,
					'goal_unit_cost'            => $row->goal_unit_cost,
					'warning_threshold_percent' => $row->warning_threshold_percent,
					'created_at'                => $now,
					'updated_at'                => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_goal_clone', __( 'Could not copy material goals.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Copy budget_workflow_links onto the new template id.
	 *
	 * @param int $source_id Source template PK.
	 * @param int $new_id    Clone template PK.
	 * @return true|WP_Error
	 */
	private static function copy_budget_workflow_links( $source_id, $new_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'budget_workflow_links' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT budget_id FROM {$table} WHERE workflow_template_id = %d ORDER BY id ASC",
				(int) $source_id
			)
		);
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'som_budget_clone', __( 'Could not copy budget links.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		foreach ( $rows as $row ) {
			$budget_id = (int) $row->budget_id;
			if ( $budget_id < 1 ) {
				continue;
			}
			$inserted = $wpdb->insert(
				$table,
				array(
					'budget_id'            => $budget_id,
					'workflow_template_id' => (int) $new_id,
					'created_at'           => $now,
				),
				array( '%d', '%d', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_budget_clone', __( 'Could not copy budget links.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Undo a clone that failed after the new template row was inserted.
	 *
	 * ROLLBACK covers InnoDB. The delete covers engines that ignore the
	 * transaction, so a failed clone never leaves a half copy.
	 *
	 * @param int $template_id New template PK, or 0 when the insert did not happen.
	 * @return void
	 */
	private static function abort_clone_write( $template_id ) {
		global $wpdb;

		$wpdb->query( 'ROLLBACK' );
		self::delete_uncommitted_clone( (int) $template_id );
	}

	/**
	 * Remove a template whose steps did not save.
	 *
	 * Workflow import calls this when save_steps() fails after create().
	 * The same cleanup covers a failed clone. Other templates stay.
	 *
	 * @param int $template_id Template PK from create().
	 * @return void
	 */
	public static function delete_failed_create( $template_id ) {
		self::delete_uncommitted_clone( (int) $template_id );
	}

	/**
	 * Remove a clone template and the rows that belong only to it.
	 *
	 * @param int $template_id Clone template PK.
	 * @return void
	 */
	private static function delete_uncommitted_clone( $template_id ) {
		global $wpdb;

		$template_id = (int) $template_id;
		if ( $template_id < 1 ) {
			return;
		}

		$wpdb->delete(
			SOM_DB::table( 'workflow_steps' ),
			array( 'workflow_template_id' => $template_id ),
			array( '%d' )
		);
		$wpdb->delete(
			SOM_DB::table( 'workflow_material_goals' ),
			array( 'workflow_template_id' => $template_id ),
			array( '%d' )
		);
		$wpdb->delete(
			SOM_DB::table( 'budget_workflow_links' ),
			array( 'workflow_template_id' => $template_id ),
			array( '%d' )
		);
		$wpdb->delete(
			SOM_DB::table( 'workflow_templates' ),
			array( 'id' => $template_id ),
			array( '%d' )
		);
	}

	/**
	 * @param mixed $value DB value.
	 * @return int|null
	 */
	private static function nullable_db_int( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return (int) $value;
	}

	/**
	 * @param mixed $value DB value.
	 * @return string|null
	 */
	private static function nullable_db_text( $value ) {
		if ( null === $value ) {
			return null;
		}
		$text = (string) $value;
		return '' === $text ? null : $text;
	}

	/**
	 * @param string $text Text.
	 * @return int
	 */
	private static function text_length( $text ) {
		$text = (string) $text;
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $text );
		}
		return strlen( $text );
	}

	/**
	 * @param string $text   Text.
	 * @param int    $length Max characters.
	 * @return string
	 */
	private static function text_cut( $text, $length ) {
		$text   = (string) $text;
		$length = max( 0, (int) $length );
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, $length );
		}
		return (string) substr( $text, 0, $length );
	}

	/**
	 * Require a Package arrived step when the resulting kind is post_ship.
	 *
	 * @param string                           $kind       Sanitized kind.
	 * @param array<int, array<string, mixed>> $normalized Normalised steps.
	 * @return true|WP_Error
	 */
	private static function assert_post_ship_has_arrival( $kind, array $normalized ) {
		if ( 'post_ship' !== $kind || self::steps_include_arrival( $normalized ) ) {
			return true;
		}
		return new WP_Error(
			'som_post_ship_arrival_required',
			self::post_ship_arrival_required_message()
		);
	}

	/**
	 * Normalise form step rows the same way save_steps() does. No writes.
	 *
	 * Blank names are skipped. Gate exclusivity matches save_steps(). Callers
	 * must not write until this returns an array.
	 *
	 * @param int                              $template_id   Template PK (0 when creating).
	 * @param array<int, array<string, mixed>> $steps         Form step rows.
	 * @param string                           $template_kind Resulting kind.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function normalize_step_rows( $template_id, array $steps, $template_kind ) {
		global $wpdb;

		$template_id   = (int) $template_id;
		$template_kind = self::sanitize_kind( $template_kind );
		$normalized    = array();
		$order         = 0;

		foreach ( $steps as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			++$order;

			$timer = self::sanitize_timer_seconds( $row );
			if ( is_wp_error( $timer ) ) {
				return $timer;
			}

			$planned = self::sanitize_planned_seconds( $row );
			if ( is_wp_error( $planned ) ) {
				return $planned;
			}

			// UP9-S2: local / api / n8n removed — never persist script_config (clears legacy on save).
			$script = null;

			$step_id        = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$batch_group_id = self::resolve_batch_group_id( $row, $step_id, $template_id );
			if ( is_wp_error( $batch_group_id ) ) {
				return $batch_group_id;
			}

			$command_template = self::resolve_command_template( $row, $step_id, $template_id );
			if ( is_wp_error( $command_template ) ) {
				return $command_template;
			}

			if ( $batch_group_id && 'make' === $template_kind ) {
				return new WP_Error(
					'som_make_no_batch',
					__( 'Make templates cannot use batch groups. Remove the batch assignment from this step.', 'order-machine' )
				);
			}

			$manual = ! empty( $row['requires_manual_confirm'] ) ? 1 : 0;

			$confirmation_kind = SOM_Step_Confirmations::sanitize_kind(
				isset( $row['confirmation_kind'] ) ? $row['confirmation_kind'] : null
			);

			if ( $batch_group_id ) {
				$has_other = $manual || ( null !== $timer && (int) $timer > 0 ) || null !== $confirmation_kind || null !== $command_template;
				if ( $has_other ) {
					return new WP_Error(
						'som_batch_only_step',
						__( 'A batch step cannot also have manual, timer, confirmation, or copy-command gates.', 'order-machine' )
					);
				}
				$manual            = 0;
				$timer             = null;
				$confirmation_kind = null;
				$command_template  = null;
			}

			if ( null !== $confirmation_kind ) {
				$has_other = ( null !== $timer && (int) $timer > 0 ) || $batch_group_id;
				if ( $has_other ) {
					return new WP_Error(
						'som_confirmation_only_step',
						__( 'A confirmation step cannot also have timer or batch gates.', 'order-machine' )
					);
				}
				$manual = 1;
				$timer  = null;
			}

			// Copy-command: exclusive with timer / batch; may combine with manual confirm (O3).
			if ( null !== $command_template ) {
				$has_other = ( null !== $timer && (int) $timer > 0 ) || $batch_group_id;
				if ( $has_other ) {
					return new WP_Error(
						'som_copy_command_exclusive',
						__( 'A copy-command step cannot also have timer or batch gates.', 'order-machine' )
					);
				}
				$manual = 1;
				$timer  = null;
			}

			$instructions = '';
			if ( array_key_exists( 'instructions', $row ) ) {
				$instructions = SOM_Step_Instructions::sanitize( $row['instructions'] );
				if ( is_wp_error( $instructions ) ) {
					return $instructions;
				}
			}

			$task_list_json = null;
			if ( array_key_exists( 'tasks', $row ) ) {
				$tasks = SOM_Step_Tasks::sanitize_from_form( $row['tasks'] );
				if ( is_wp_error( $tasks ) ) {
					return $tasks;
				}
				$task_list_json = SOM_Step_Tasks::encode_list( $tasks );
			} elseif ( $step_id > 0 ) {
				// Preserve existing when the form omits tasks (e.g. partial saves).
				$existing_tasks = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT task_list_json FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d AND workflow_template_id = %d LIMIT 1',
						$step_id,
						$template_id
					)
				);
				$task_list_json = is_string( $existing_tasks ) && '' !== trim( $existing_tasks ) ? $existing_tasks : null;
			}

			$normalized[] = array(
				'id'                      => $step_id,
				'step_order'              => $order,
				'name'                    => $name,
				'requires_manual_confirm' => $manual,
				'timer_seconds'           => $timer,
				'planned_seconds'         => $planned,
				'script_config'           => $script,
				'command_template'        => $command_template,
				'batch_group_id'          => $batch_group_id,
				'confirmation_kind'       => $confirmation_kind,
				'instructions'            => '' !== $instructions ? $instructions : null,
				'task_list_json'          => $task_list_json,
			);
		}

		return $normalized;
	}

	/**
	 * Replace-all save for ordered steps on a template.
	 *
	 * @param int                       $template_id Template PK.
	 * @param array<int, array<string, mixed>> $steps Step rows from the form.
	 * @return true|WP_Error
	 */
	public static function save_steps( $template_id, array $steps ) {
		global $wpdb;

		$template_id = (int) $template_id;
		$template    = self::get( $template_id );
		if ( ! $template ) {
			return new WP_Error( 'som_workflow_missing', __( 'Workflow template not found.', 'order-machine' ) );
		}

		$template_kind = self::sanitize_kind( isset( $template->kind ) ? $template->kind : 'make' );
		$normalized    = self::normalize_step_rows( $template_id, $steps, $template_kind );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$arrival = self::assert_post_ship_has_arrival( $template_kind, $normalized );
		if ( is_wp_error( $arrival ) ) {
			return $arrival;
		}

		$existing_ids = array();
		foreach ( self::get_steps( $template_id ) as $step ) {
			$existing_ids[ (int) $step->id ] = true;
		}

		$kept_ids = array();
		$now      = current_time( 'mysql', true );
		$table    = SOM_DB::table( 'workflow_steps' );

		foreach ( $normalized as $row ) {
			$step_id = (int) $row['id'];

			$fields = array(
				'workflow_template_id'    => $template_id,
				'step_order'              => $row['step_order'],
				'name'                    => $row['name'],
				'requires_manual_confirm' => $row['requires_manual_confirm'],
				'timer_seconds'           => $row['timer_seconds'],
				'planned_seconds'         => $row['planned_seconds'],
				'script_config'           => $row['script_config'],
				'command_template'        => $row['command_template'],
				'batch_group_id'          => $row['batch_group_id'],
				'confirmation_kind'       => $row['confirmation_kind'],
				'instructions'            => $row['instructions'],
				'task_list_json'          => $row['task_list_json'],
				'updated_at'              => $now,
			);

			if ( $step_id > 0 && isset( $existing_ids[ $step_id ] ) ) {
				$updated = $wpdb->update(
					$table,
					$fields,
					array(
						'id'                   => $step_id,
						'workflow_template_id' => $template_id,
					),
					array( '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ),
					array( '%d', '%d' )
				);
				if ( false === $updated ) {
					return new WP_Error( 'som_step_update', __( 'Could not update a workflow step.', 'order-machine' ) );
				}
				$kept_ids[ $step_id ] = true;
				continue;
			}

			$fields['created_at'] = $now;
			$inserted             = $wpdb->insert(
				$table,
				$fields,
				array( '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_step_create', __( 'Could not create a workflow step.', 'order-machine' ) );
			}
			$kept_ids[ (int) $wpdb->insert_id ] = true;
		}

		foreach ( array_keys( $existing_ids ) as $old_id ) {
			if ( isset( $kept_ids[ $old_id ] ) ) {
				continue;
			}
			SOM_Step_Instructions::delete_for_step( (int) $old_id );
			SOM_Step_Tasks::delete_for_step( (int) $old_id );
			$wpdb->delete(
				$table,
				array(
					'id'                   => $old_id,
					'workflow_template_id' => $template_id,
				),
				array( '%d', '%d' )
			);
		}

		$wpdb->update(
			SOM_DB::table( 'workflow_templates' ),
			array( 'updated_at' => $now ),
			array( 'id' => $template_id ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Resolve batch_group_id for a step row.
	 *
	 * When the form omits batch_group_id, preserve the existing DB value so
	 * U1 thank-you converts are not wiped before the U6 editor ships.
	 *
	 * @param array<string, mixed> $row         Form row.
	 * @param int                  $step_id     Existing step PK or 0.
	 * @param int                  $template_id Template PK.
	 * @return int|null|WP_Error
	 */
	public static function resolve_batch_group_id( array $row, $step_id, $template_id ) {
		global $wpdb;

		if ( array_key_exists( 'batch_group_id', $row ) ) {
			$id = (int) $row['batch_group_id'];
			if ( $id < 1 ) {
				return null;
			}
			$group = SOM_Batch_Groups::get( $id );
			if ( ! $group ) {
				return new WP_Error( 'som_batch_group_missing', __( 'Batch group not found.', 'order-machine' ) );
			}
			return $id;
		}

		$step_id = (int) $step_id;
		if ( $step_id < 1 ) {
			return null;
		}

		$table    = SOM_DB::table( 'workflow_steps' );
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT batch_group_id FROM {$table} WHERE id = %d AND workflow_template_id = %d LIMIT 1",
				$step_id,
				(int) $template_id
			)
		);
		if ( null === $existing || '' === $existing ) {
			return null;
		}
		$id = (int) $existing;
		return $id > 0 ? $id : null;
	}

	/**
	 * Resolve command_template for a step row.
	 *
	 * When the form omits command_template, preserve the existing DB value.
	 *
	 * @param array<string, mixed> $row         Form row.
	 * @param int                  $step_id     Existing step PK or 0.
	 * @param int                  $template_id Template PK.
	 * @return string|null|WP_Error
	 */
	public static function resolve_command_template( array $row, $step_id, $template_id ) {
		global $wpdb;

		if ( array_key_exists( 'command_template', $row ) ) {
			return SOM_Copy_Command::sanitize_template( $row['command_template'] );
		}

		$step_id = (int) $step_id;
		if ( $step_id < 1 ) {
			return null;
		}

		$table    = SOM_DB::table( 'workflow_steps' );
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT command_template FROM {$table} WHERE id = %d AND workflow_template_id = %d LIMIT 1",
				$step_id,
				(int) $template_id
			)
		);
		if ( null === $existing || '' === trim( (string) $existing ) ) {
			return null;
		}
		return (string) $existing;
	}

	/**
	 * Convert friendlier timer fields to seconds (or null).
	 *
	 * Accepted keys: timer_seconds (raw), or timer_value + timer_unit (minutes|hours|days).
	 *
	 * @param array<string, mixed> $row Step form row.
	 * @return int|null|WP_Error
	 */
	public static function sanitize_timer_seconds( array $row ) {
		return self::sanitize_duration_seconds(
			$row,
			'timer_seconds',
			'timer_value',
			'timer_unit',
			'som_timer_invalid',
			__( 'Timer value must be a positive number.', 'order-machine' ),
			'som_timer_unit',
			__( 'Invalid timer unit.', 'order-machine' )
		);
	}

	/**
	 * Convert friendlier planned-duration fields to seconds (or null).
	 *
	 * Accepted keys: planned_seconds (raw), or planned_value + planned_unit (minutes|hours|days).
	 * Planned duration is a reporting target — separate from the timer hard-gate.
	 *
	 * @param array<string, mixed> $row Step form row.
	 * @return int|null|WP_Error
	 */
	public static function sanitize_planned_seconds( array $row ) {
		return self::sanitize_duration_seconds(
			$row,
			'planned_seconds',
			'planned_value',
			'planned_unit',
			'som_planned_invalid',
			__( 'Planned duration must be a positive number.', 'order-machine' ),
			'som_planned_unit',
			__( 'Invalid planned duration unit.', 'order-machine' )
		);
	}

	/**
	 * Shared value+unit → seconds sanitizer for timer / planned duration.
	 *
	 * @param array<string, mixed> $row           Step form row.
	 * @param string               $raw_key       Raw seconds key.
	 * @param string               $value_key     Friendly value key.
	 * @param string               $unit_key      Friendly unit key.
	 * @param string               $invalid_code  WP_Error code for bad value.
	 * @param string               $invalid_msg   WP_Error message for bad value.
	 * @param string               $unit_code     WP_Error code for bad unit.
	 * @param string               $unit_msg      WP_Error message for bad unit.
	 * @return int|null|WP_Error
	 */
	private static function sanitize_duration_seconds( array $row, $raw_key, $value_key, $unit_key, $invalid_code, $invalid_msg, $unit_code, $unit_msg ) {
		if ( isset( $row[ $value_key ] ) || isset( $row[ $unit_key ] ) ) {
			$raw = isset( $row[ $value_key ] ) ? trim( (string) $row[ $value_key ] ) : '';
			if ( '' === $raw ) {
				return null;
			}
			if ( ! is_numeric( $raw ) || (float) $raw <= 0 ) {
				return new WP_Error( $invalid_code, $invalid_msg );
			}

			$value = (float) $raw;
			$unit  = isset( $row[ $unit_key ] ) ? sanitize_key( (string) $row[ $unit_key ] ) : 'minutes';
			$mult  = 60;
			if ( 'hours' === $unit ) {
				$mult = HOUR_IN_SECONDS;
			} elseif ( 'days' === $unit ) {
				$mult = DAY_IN_SECONDS;
			} elseif ( 'minutes' !== $unit ) {
				return new WP_Error( $unit_code, $unit_msg );
			}

			$seconds = (int) round( $value * $mult );
			if ( $seconds < 1 ) {
				return new WP_Error( $invalid_code, $invalid_msg );
			}

			return $seconds;
		}

		if ( ! array_key_exists( $raw_key, $row ) || null === $row[ $raw_key ] || '' === $row[ $raw_key ] ) {
			return null;
		}

		$seconds = (int) $row[ $raw_key ];
		if ( $seconds < 1 ) {
			return null;
		}

		return $seconds;
	}

	/**
	 * Build script_config JSON string (or null).
	 *
	 * UP9-S2: local / api / n8n removed from the product — always returns null.
	 * Kept for callers / migration; editor no longer posts script fields.
	 *
	 * @param array<string, mixed> $row Step form row.
	 * @return string|null|WP_Error
	 */
	public static function sanitize_script_config( array $row ) {
		unset( $row );
		return null;
	}

	/**
	 * Split stored timer_seconds into value + unit for the editor.
	 *
	 * @param int|null $seconds Stored seconds.
	 * @return array{value: string, unit: string}
	 */
	public static function timer_for_display( $seconds ) {
		return self::duration_for_display( $seconds );
	}

	/**
	 * Split stored planned_seconds into value + unit for the editor.
	 *
	 * @param int|null $seconds Stored seconds.
	 * @return array{value: string, unit: string}
	 */
	public static function planned_for_display( $seconds ) {
		return self::duration_for_display( $seconds );
	}

	/**
	 * Sum of non-null planned_seconds for a step list + whether the plan is partial.
	 *
	 * @param array<int, object|array<string, mixed>> $steps Step rows (objects or arrays).
	 * @return array{total: int|null, planned_count: int, step_count: int, is_partial: bool}
	 */
	public static function planned_total_summary( array $steps ) {
		$total         = 0;
		$planned_count = 0;
		$step_count    = 0;

		foreach ( $steps as $step ) {
			++$step_count;
			$raw = null;
			if ( is_object( $step ) ) {
				$raw = isset( $step->planned_seconds ) ? $step->planned_seconds : null;
			} elseif ( is_array( $step ) ) {
				$raw = array_key_exists( 'planned_seconds', $step ) ? $step['planned_seconds'] : null;
			}
			if ( null === $raw || '' === $raw ) {
				continue;
			}
			$seconds = (int) $raw;
			if ( $seconds < 1 ) {
				continue;
			}
			++$planned_count;
			$total += $seconds;
		}

		return array(
			'total'         => $planned_count > 0 ? $total : null,
			'planned_count' => $planned_count,
			'step_count'    => $step_count,
			'is_partial'    => $planned_count > 0 && $planned_count < $step_count,
		);
	}

	/**
	 * Split stored duration seconds into value + unit for the editor.
	 *
	 * @param int|null $seconds Stored seconds.
	 * @return array{value: string, unit: string}
	 */
	private static function duration_for_display( $seconds ) {
		$seconds = null === $seconds || '' === $seconds ? 0 : (int) $seconds;
		if ( $seconds < 1 ) {
			return array(
				'value' => '',
				'unit'  => 'minutes',
			);
		}

		if ( 0 === $seconds % DAY_IN_SECONDS ) {
			return array(
				'value' => (string) (int) ( $seconds / DAY_IN_SECONDS ),
				'unit'  => 'days',
			);
		}
		if ( 0 === $seconds % HOUR_IN_SECONDS ) {
			return array(
				'value' => (string) (int) ( $seconds / HOUR_IN_SECONDS ),
				'unit'  => 'hours',
			);
		}
		if ( 0 === $seconds % 60 ) {
			return array(
				'value' => (string) (int) ( $seconds / 60 ),
				'unit'  => 'minutes',
			);
		}

		return array(
			'value' => (string) max( 1, (int) round( $seconds / 60 ) ),
			'unit'  => 'minutes',
		);
	}

	/**
	 * Decode script_config for the editor form.
	 *
	 * @param string|null $json Stored JSON.
	 * @return array<string, mixed>
	 */
	public static function script_for_display( $json ) {
		$defaults = array(
			'type'      => 'none',
			'action'    => '',
			'params'    => '',
			'method'    => 'POST',
			'url'       => '',
			'body'      => '',
			'webhook'   => '',
			'payload'   => '',
			'raw_json'  => '',
			'raw_mode'  => false,
		);

		if ( null === $json || '' === $json ) {
			return $defaults;
		}

		$decoded = json_decode( (string) $json, true );
		if ( ! is_array( $decoded ) || empty( $decoded['type'] ) ) {
			$defaults['raw_mode'] = true;
			$defaults['raw_json'] = (string) $json;
			return $defaults;
		}

		$type                 = (string) $decoded['type'];
		$defaults['type']     = $type;
		$defaults['raw_json'] = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( 'local' === $type ) {
			$defaults['action'] = isset( $decoded['action'] ) ? (string) $decoded['action'] : '';
			$params             = isset( $decoded['params'] ) && is_array( $decoded['params'] ) ? $decoded['params'] : array();
			$defaults['params'] = $params ? wp_json_encode( $params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : '';
			return $defaults;
		}

		if ( 'api' === $type ) {
			$defaults['method'] = isset( $decoded['method'] ) ? (string) $decoded['method'] : 'POST';
			$defaults['url']    = isset( $decoded['url'] ) ? (string) $decoded['url'] : '';
			$body               = isset( $decoded['body_template'] ) && is_array( $decoded['body_template'] ) ? $decoded['body_template'] : array();
			$defaults['body']   = $body ? wp_json_encode( $body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : '';
			return $defaults;
		}

		if ( 'n8n' === $type ) {
			$defaults['webhook'] = isset( $decoded['webhook_url'] ) ? (string) $decoded['webhook_url'] : '';
			$payload             = isset( $decoded['payload_template'] ) && is_array( $decoded['payload_template'] ) ? $decoded['payload_template'] : array();
			$defaults['payload'] = $payload ? wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : '';
			return $defaults;
		}

		$defaults['raw_mode'] = true;
		return $defaults;
	}

	/**
	 * Human-readable summary of step gates for list display.
	 *
	 * @param object $step Step row.
	 * @return string
	 */
	public static function step_gates_label( $step ) {
		$parts = array();
		if ( ! empty( $step->batch_group_id ) ) {
			$group = SOM_Batch_Groups::get( (int) $step->batch_group_id );
			$parts[] = $group
				? sprintf(
					/* translators: %s: batch group display name */
					__( 'batch:%s', 'order-machine' ),
					$group->display_name
				)
				: __( 'batch', 'order-machine' );
			return $parts[0];
		}
		if ( ! empty( $step->requires_manual_confirm ) ) {
			$parts[] = __( 'manual', 'order-machine' );
		}
		if ( ! empty( $step->timer_seconds ) ) {
			$display = self::timer_for_display( (int) $step->timer_seconds );
			$parts[] = sprintf(
				/* translators: 1: timer value, 2: unit */
				__( 'timer %1$s %2$s', 'order-machine' ),
				$display['value'],
				$display['unit']
			);
		}
		if ( ! empty( $step->script_config ) ) {
			$decoded = json_decode( (string) $step->script_config, true );
			$type    = is_array( $decoded ) && ! empty( $decoded['type'] ) ? (string) $decoded['type'] : 'script';
			$parts[] = sprintf(
				/* translators: %s: script type */
				__( 'script:%s', 'order-machine' ),
				$type
			);
		}
		if ( empty( $parts ) ) {
			return __( 'no gates', 'order-machine' );
		}
		return implode( ', ', $parts );
	}

	/**
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => 'som-workflows' ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param int|string $template_id Template PK or 'new'.
	 * @return string
	 */
	public static function editor_url( $template_id ) {
		return self::list_url( array( 'template_id' => $template_id ) );
	}
}
