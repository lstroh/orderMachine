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
	 * Query templates for the admin list.
	 *
	 * @param array<string, mixed> $args Filters: status, s, paged, per_page.
	 * @return array{templates: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => 'active',
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
	 * Active templates for product assignment dropdowns.
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
					"SELECT id, name, is_active FROM {$table}
					WHERE is_active = 1 OR id = %d
					ORDER BY is_active DESC, name ASC, id ASC",
					$include_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				"SELECT id, name, is_active FROM {$table}
				WHERE is_active = 1
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
	 * @param array<string, mixed> $data Fields: name, description, is_active.
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'som_workflow_name', __( 'Template name is required.', 'order-machine' ) );
		}

		$description = isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '';
		$now         = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			SOM_DB::table( 'workflow_templates' ),
			array(
				'name'        => $name,
				'description' => '' !== $description ? $description : null,
				'is_active'   => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
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
	 * Replace-all save for ordered steps on a template.
	 *
	 * @param int                       $template_id Template PK.
	 * @param array<int, array<string, mixed>> $steps Step rows from the form.
	 * @return true|WP_Error
	 */
	public static function save_steps( $template_id, array $steps ) {
		global $wpdb;

		$template_id = (int) $template_id;
		if ( ! self::get( $template_id ) ) {
			return new WP_Error( 'som_workflow_missing', __( 'Workflow template not found.', 'order-machine' ) );
		}

		$normalized = array();
		$order      = 0;

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

			$script = self::sanitize_script_config( $row );
			if ( is_wp_error( $script ) ) {
				return $script;
			}

			$step_id        = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$batch_group_id = self::resolve_batch_group_id( $row, $step_id, $template_id );
			if ( is_wp_error( $batch_group_id ) ) {
				return $batch_group_id;
			}

			$manual = ! empty( $row['requires_manual_confirm'] ) ? 1 : 0;

			if ( $batch_group_id ) {
				$has_other = $manual || ( null !== $timer && (int) $timer > 0 ) || ( null !== $script && '' !== $script );
				if ( $has_other ) {
					return new WP_Error(
						'som_batch_only_step',
						__( 'A batch step cannot also have manual, timer, or script gates.', 'order-machine' )
					);
				}
				$manual = 0;
				$timer  = null;
				$script = null;
			}

			$normalized[] = array(
				'id'                      => $step_id,
				'step_order'              => $order,
				'name'                    => $name,
				'requires_manual_confirm' => $manual,
				'timer_seconds'           => $timer,
				'script_config'           => $script,
				'batch_group_id'          => $batch_group_id,
			);
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
				'script_config'           => $row['script_config'],
				'batch_group_id'          => $row['batch_group_id'],
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
					array( '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s' ),
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
				array( '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
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
	 * Convert friendlier timer fields to seconds (or null).
	 *
	 * Accepted keys: timer_seconds (raw), or timer_value + timer_unit (minutes|hours|days).
	 *
	 * @param array<string, mixed> $row Step form row.
	 * @return int|null|WP_Error
	 */
	public static function sanitize_timer_seconds( array $row ) {
		if ( isset( $row['timer_value'] ) || isset( $row['timer_unit'] ) ) {
			$raw = isset( $row['timer_value'] ) ? trim( (string) $row['timer_value'] ) : '';
			if ( '' === $raw ) {
				return null;
			}
			if ( ! is_numeric( $raw ) || (float) $raw <= 0 ) {
				return new WP_Error( 'som_timer_invalid', __( 'Timer value must be a positive number.', 'order-machine' ) );
			}

			$value = (float) $raw;
			$unit  = isset( $row['timer_unit'] ) ? sanitize_key( (string) $row['timer_unit'] ) : 'minutes';
			$mult  = 60;
			if ( 'hours' === $unit ) {
				$mult = HOUR_IN_SECONDS;
			} elseif ( 'days' === $unit ) {
				$mult = DAY_IN_SECONDS;
			} elseif ( 'minutes' !== $unit ) {
				return new WP_Error( 'som_timer_unit', __( 'Invalid timer unit.', 'order-machine' ) );
			}

			$seconds = (int) round( $value * $mult );
			if ( $seconds < 1 ) {
				return new WP_Error( 'som_timer_invalid', __( 'Timer value must be a positive number.', 'order-machine' ) );
			}

			return $seconds;
		}

		if ( ! array_key_exists( 'timer_seconds', $row ) || null === $row['timer_seconds'] || '' === $row['timer_seconds'] ) {
			return null;
		}

		$seconds = (int) $row['timer_seconds'];
		if ( $seconds < 1 ) {
			return null;
		}

		return $seconds;
	}

	/**
	 * Build script_config JSON string (or null).
	 *
	 * @param array<string, mixed> $row Step form row.
	 * @return string|null|WP_Error
	 */
	public static function sanitize_script_config( array $row ) {
		if ( ! empty( $row['script_raw_mode'] ) && isset( $row['script_raw_json'] ) ) {
			$raw = trim( (string) $row['script_raw_json'] );
			if ( '' === $raw ) {
				return null;
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				return new WP_Error( 'som_script_json', __( 'Script config raw JSON is invalid.', 'order-machine' ) );
			}
			if ( empty( $decoded['type'] ) || ! in_array( $decoded['type'], array( 'local', 'api', 'n8n' ), true ) ) {
				return new WP_Error( 'som_script_type', __( 'Script config JSON must include type: local, api, or n8n.', 'order-machine' ) );
			}
			if ( 'local' === $decoded['type'] ) {
				$action = isset( $decoded['action'] ) ? (string) $decoded['action'] : '';
				if ( ! array_key_exists( $action, self::local_action_choices() ) ) {
					return new WP_Error( 'som_script_action', __( 'Local script action is not on the allowlist.', 'order-machine' ) );
				}
			}
			return wp_json_encode( $decoded );
		}

		$type = isset( $row['script_type'] ) ? sanitize_key( (string) $row['script_type'] ) : '';
		if ( '' === $type || 'none' === $type ) {
			return null;
		}

		if ( 'local' === $type ) {
			$action = isset( $row['script_action'] ) ? sanitize_key( (string) $row['script_action'] ) : '';
			if ( ! array_key_exists( $action, self::local_action_choices() ) ) {
				return new WP_Error( 'som_script_action', __( 'Choose a local script action from the allowlist.', 'order-machine' ) );
			}
			$config = array(
				'type'   => 'local',
				'action' => $action,
				'params' => array(),
			);
			if ( isset( $row['script_params'] ) && '' !== trim( (string) $row['script_params'] ) ) {
				$params = json_decode( (string) $row['script_params'], true );
				if ( ! is_array( $params ) ) {
					return new WP_Error( 'som_script_params', __( 'Local action params must be valid JSON object.', 'order-machine' ) );
				}
				$config['params'] = $params;
			}
			return wp_json_encode( $config );
		}

		if ( 'api' === $type ) {
			$method = isset( $row['script_method'] ) ? strtoupper( sanitize_text_field( (string) $row['script_method'] ) ) : 'POST';
			if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
				$method = 'POST';
			}
			$url = isset( $row['script_url'] ) ? esc_url_raw( (string) $row['script_url'] ) : '';
			if ( '' === $url ) {
				return new WP_Error( 'som_script_url', __( 'API script steps need a URL.', 'order-machine' ) );
			}
			$body = array();
			if ( isset( $row['script_body'] ) && '' !== trim( (string) $row['script_body'] ) ) {
				$decoded = json_decode( (string) $row['script_body'], true );
				if ( ! is_array( $decoded ) ) {
					return new WP_Error( 'som_script_body', __( 'API body template must be valid JSON.', 'order-machine' ) );
				}
				$body = $decoded;
			}
			return wp_json_encode(
				array(
					'type'          => 'api',
					'method'        => $method,
					'url'           => $url,
					'body_template' => $body,
				)
			);
		}

		if ( 'n8n' === $type ) {
			$webhook = isset( $row['script_webhook'] ) ? esc_url_raw( (string) $row['script_webhook'] ) : '';
			if ( '' === $webhook ) {
				return new WP_Error( 'som_script_webhook', __( 'n8n script steps need a webhook URL.', 'order-machine' ) );
			}
			$payload = array();
			if ( isset( $row['script_payload'] ) && '' !== trim( (string) $row['script_payload'] ) ) {
				$decoded = json_decode( (string) $row['script_payload'], true );
				if ( ! is_array( $decoded ) ) {
					return new WP_Error( 'som_script_payload', __( 'n8n payload template must be valid JSON.', 'order-machine' ) );
				}
				$payload = $decoded;
			}
			return wp_json_encode(
				array(
					'type'             => 'n8n',
					'webhook_url'      => $webhook,
					'payload_template' => $payload,
				)
			);
		}

		return new WP_Error( 'som_script_type', __( 'Unknown script type.', 'order-machine' ) );
	}

	/**
	 * Split stored timer_seconds into value + unit for the editor.
	 *
	 * @param int|null $seconds Stored seconds.
	 * @return array{value: string, unit: string}
	 */
	public static function timer_for_display( $seconds ) {
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
