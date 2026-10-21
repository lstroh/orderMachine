<?php
/**
 * Workflow CSV export, template, preview, and import.
 *
 * One row per step. Rows with the same name are one template. Creates through
 * SOM_Workflows::validate_submission, create, and save_steps. Does not update.
 * script_config is not a column; the step writer stores it empty.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Workflow catalog file.
 */
class SOM_Workflow_Import {

	/**
	 * Preview-token kind. Materials and products use their own kinds.
	 */
	const KIND = 'workflows';

	/**
	 * MySQL signed INT upper bound for step order and second counts.
	 */
	const INT_MAX = 2147483647;

	/**
	 * Columns shared by export, template, and import.
	 *
	 * @var string[]
	 */
	const COLUMNS = array(
		'name',
		'description',
		'kind',
		'uses_trays',
		'is_active',
		'step_order',
		'step_name',
		'requires_manual_confirm',
		'timer_seconds',
		'planned_seconds',
		'confirmation_kind',
		'instructions',
		'tasks',
		'command_template',
		'batch_group_key',
	);

	/**
	 * @var string[]
	 */
	const REQUIRED = array(
		'name',
		'kind',
		'step_order',
		'step_name',
	);

	/**
	 * Template fields repeated on every step row. They must agree.
	 *
	 * @var string[]
	 */
	const REPEATED = array(
		'name',
		'description',
		'kind',
		'uses_trays',
		'is_active',
	);

	/**
	 * Header row only.
	 *
	 * @return string
	 */
	public static function template_csv() {
		return SOM_Catalog_Import::to_csv( self::COLUMNS, array() );
	}

	/**
	 * Current templates, one row per step.
	 *
	 * @return string
	 */
	public static function export_csv() {
		return SOM_Catalog_Import::to_csv( self::COLUMNS, self::export_rows() );
	}

	/**
	 * Send a CSV download and stop.
	 *
	 * @param string $filename File name.
	 * @param string $csv      File text.
	 * @return void
	 */
	public static function send_csv( $filename, $csv ) {
		$filename = preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $filename );
		if ( ! is_string( $filename ) || '' === $filename ) {
			$filename = 'workflows.csv';
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( (string) $csv ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, not HTML.
		echo (string) $csv;
		exit;
	}

	/**
	 * Parse a file. Does not insert templates.
	 *
	 * @param string $contents File text. Comma or tab. A leading UTF-8 BOM is ignored.
	 * @return array{
	 *     rows: array<int, array<string, mixed>>,
	 *     ready: int,
	 *     failed: int,
	 *     file_error: string,
	 *     file_error_code: string
	 * }
	 */
	public static function parse( $contents ) {
		$prepared = self::prepare( $contents );
		if ( '' !== $prepared['file_error'] ) {
			return $prepared;
		}
		return self::with_names( $prepared );
	}

	/**
	 * Create the workflows in a file. A failed workflow does not remove workflows already saved.
	 *
	 * @param string $contents File text.
	 * @return array{created: int, failed: int, file_error: string, file_error_code: string}
	 */
	public static function commit( $contents ) {
		$prepared = self::prepare( $contents );
		if ( '' !== $prepared['file_error'] ) {
			return array(
				'created'         => 0,
				'failed'          => 0,
				'file_error'      => $prepared['file_error'],
				'file_error_code' => $prepared['file_error_code'],
			);
		}

		$taken = SOM_Catalog_Import::load_taken_names( 'workflow_templates' );
		if ( is_wp_error( $taken ) ) {
			return array(
				'created'         => 0,
				'failed'          => 0,
				'file_error'      => $taken->get_error_message(),
				'file_error_code' => $taken->get_error_code(),
			);
		}

		$created = 0;
		$failed  = 0;
		foreach ( $prepared['rows'] as $row ) {
			if ( empty( $row['ok'] ) || empty( $row['data'] ) || ! is_array( $row['data'] ) ) {
				++$failed;
				continue;
			}

			$save = SOM_Catalog_Import::allocate_name( (string) $row['file_name'], $taken );
			if ( is_wp_error( $save ) ) {
				++$failed;
				continue;
			}

			$data  = $row['data'];
			$steps = isset( $data['steps'] ) && is_array( $data['steps'] ) ? $data['steps'] : array();
			unset( $data['steps'] );
			if ( ! $steps ) {
				SOM_Catalog_Import::release_name( $save, $taken );
				++$failed;
				continue;
			}

			$data['name'] = $save;
			$kind         = isset( $data['kind'] ) ? (string) $data['kind'] : '';
			$valid        = SOM_Workflows::validate_submission( $kind, $steps, 0 );
			if ( is_wp_error( $valid ) ) {
				SOM_Catalog_Import::release_name( $save, $taken );
				++$failed;
				continue;
			}

			$template_id = SOM_Workflows::create( $data );
			if ( is_wp_error( $template_id ) || (int) $template_id < 1 ) {
				SOM_Catalog_Import::release_name( $save, $taken );
				++$failed;
				continue;
			}

			$template_id = (int) $template_id;
			$saved       = SOM_Workflows::save_steps( $template_id, $steps );
			if ( is_wp_error( $saved ) ) {
				SOM_Workflows::delete_failed_create( $template_id );
				SOM_Catalog_Import::release_name( $save, $taken );
				++$failed;
				continue;
			}
			++$created;
		}

		return array(
			'created'         => $created,
			'failed'          => $failed,
			'file_error'      => '',
			'file_error_code' => '',
		);
	}

	/**
	 * Parse the file stored for a preview token. Does not insert templates.
	 *
	 * @param int    $user_id Current user.
	 * @param string $token   Preview token.
	 * @return array<string, mixed>|WP_Error Parsed file plus `token`, or an error.
	 */
	public static function preview_for_token( $user_id, $token ) {
		$token    = SOM_Catalog_Import::clean_token( $token );
		$contents = SOM_Catalog_Import::recall( self::KIND, $user_id, $token );
		if ( '' === $contents ) {
			return new WP_Error(
				'som_import_expired',
				__( 'That preview expired. Upload the file again.', 'order-machine' )
			);
		}

		$parsed          = self::parse( $contents );
		$parsed['token'] = $token;
		return $parsed;
	}

	/**
	 * @return array<int, string[]>
	 */
	private static function export_rows() {
		global $wpdb;

		$templates = SOM_DB::table( 'workflow_templates' );
		$steps     = SOM_DB::table( 'workflow_steps' );
		$groups    = SOM_DB::table( 'batch_groups' );
		$sql       = "SELECT t.name, t.description, t.kind, t.uses_trays, t.is_active,
				s.step_order, s.name AS step_name, s.requires_manual_confirm,
				s.timer_seconds, s.planned_seconds, s.confirmation_kind,
				s.instructions, s.task_list_json, s.command_template,
				g.group_key AS batch_group_key
			FROM {$templates} t
			INNER JOIN {$steps} s ON s.workflow_template_id = t.id
			LEFT JOIN {$groups} g ON g.id = s.batch_group_id
			ORDER BY t.name ASC, t.id ASC, s.step_order ASC, s.id ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names from SOM_DB::table, no user input.
		$rows = $wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				isset( $row->name ) ? (string) $row->name : '',
				self::export_text( isset( $row->description ) ? $row->description : null ),
				isset( $row->kind ) ? (string) $row->kind : '',
				(string) (int) ( isset( $row->uses_trays ) ? $row->uses_trays : 0 ),
				(string) (int) ( isset( $row->is_active ) ? $row->is_active : 0 ),
				isset( $row->step_order ) ? (string) (int) $row->step_order : '',
				isset( $row->step_name ) ? (string) $row->step_name : '',
				(string) (int) ( isset( $row->requires_manual_confirm ) ? $row->requires_manual_confirm : 0 ),
				self::export_int( isset( $row->timer_seconds ) ? $row->timer_seconds : null ),
				self::export_int( isset( $row->planned_seconds ) ? $row->planned_seconds : null ),
				self::export_text( isset( $row->confirmation_kind ) ? $row->confirmation_kind : null ),
				self::export_text( isset( $row->instructions ) ? $row->instructions : null ),
				self::export_tasks( isset( $row->task_list_json ) ? $row->task_list_json : null ),
				self::export_text( isset( $row->command_template ) ? $row->command_template : null ),
				self::export_text( isset( $row->batch_group_key ) ? $row->batch_group_key : null ),
			);
		}
		return $out;
	}

	/**
	 * Validate workflows without reserving names.
	 *
	 * @param string $contents File text.
	 * @return array<string, mixed>
	 */
	private static function prepare( $contents ) {
		$table = SOM_Catalog_Import::read_table( $contents );
		if ( '' !== $table['error'] ) {
			return self::file_failure( (string) $table['error'], (string) $table['error_code'] );
		}

		$missing = SOM_Catalog_Import::missing_columns( $table['index'], self::REQUIRED );
		if ( $missing ) {
			return self::file_failure(
				sprintf(
					/* translators: %s: comma-separated column names */
					__( 'Missing required column: %s.', 'order-machine' ),
					implode( ', ', $missing )
				),
				'missing_column'
			);
		}

		$collected = self::collect_groups( $table['rows'], (int) $table['header_at'], $table['index'] );
		$rows      = array();
		$failed    = 0;
		foreach ( $collected['order'] as $item ) {
			if ( 'row' === $item['type'] ) {
				$built = $item['row'];
			} else {
				$key   = (string) $item['key'];
				$built = self::build_workflow( $collected['groups'][ $key ], $table['index'] );
			}
			if ( empty( $built['ok'] ) ) {
				++$failed;
			}
			$rows[] = $built;
		}

		return array(
			'rows'            => $rows,
			'ready'           => count( $rows ) - $failed,
			'failed'          => $failed,
			'file_error'      => '',
			'file_error_code' => '',
		);
	}

	/**
	 * Fill save_name for workflows that can be created. Preview only; commit allocates again.
	 *
	 * @param array<string, mixed> $prepared Prepared file.
	 * @return array<string, mixed>
	 */
	private static function with_names( array $prepared ) {
		$taken = SOM_Catalog_Import::load_taken_names( 'workflow_templates' );
		if ( is_wp_error( $taken ) ) {
			return self::file_failure( $taken->get_error_message(), $taken->get_error_code() );
		}

		$ready  = 0;
		$failed = 0;
		$rows   = array();
		foreach ( $prepared['rows'] as $row ) {
			if ( empty( $row['ok'] ) ) {
				++$failed;
				$rows[] = $row;
				continue;
			}

			$save = SOM_Catalog_Import::allocate_name( (string) $row['file_name'], $taken );
			if ( is_wp_error( $save ) ) {
				++$failed;
				$row['ok']        = false;
				$row['save_name'] = '';
				$row['code']      = $save->get_error_code();
				$row['message']   = $save->get_error_message();
				unset( $row['data'] );
				$rows[] = $row;
				continue;
			}

			$row['save_name'] = $save;
			if ( isset( $row['data'] ) && is_array( $row['data'] ) ) {
				$row['data']['name'] = $save;
			}
			++$ready;
			$rows[] = $row;
		}

		$prepared['rows']   = $rows;
		$prepared['ready']  = $ready;
		$prepared['failed'] = $failed;
		return $prepared;
	}

	/**
	 * Group step rows by template name, in file order.
	 *
	 * @param array<int, array<int, string|null>> $records   Table rows.
	 * @param int                                  $header_at Header index.
	 * @param array<string, int>                   $index     Header index.
	 * @return array{groups: array<string, array<string, mixed>>, order: array<int, array<string, mixed>>}
	 */
	private static function collect_groups( array $records, $header_at, array $index ) {
		$groups = array();
		$order  = array();
		$count  = count( $records );

		for ( $i = (int) $header_at + 1; $i < $count; $i++ ) {
			$record = $records[ $i ];
			if ( ! is_array( $record ) || SOM_Catalog_Import::row_is_empty( $record ) ) {
				continue;
			}

			$line      = $i + 1;
			$file_name = trim( sanitize_text_field( SOM_Catalog_Import::cell( $record, $index, 'name' ) ) );
			if ( '' === $file_name ) {
				$order[] = array(
					'type' => 'row',
					'row'  => self::failed_workflow(
						$line,
						'',
						'missing_name',
						__( 'Name is required.', 'order-machine' )
					),
				);
				continue;
			}

			$key = SOM_Catalog_Import::name_key( $file_name );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'file_name' => $file_name,
					'line'      => $line,
					'records'   => array(),
				);
				$order[]        = array(
					'type' => 'group',
					'key'  => $key,
				);
			}
			$groups[ $key ]['records'][] = array(
				'line'   => $line,
				'record' => $record,
			);
		}

		return array(
			'groups' => $groups,
			'order'  => $order,
		);
	}

	/**
	 * @param array<string, mixed> $group Group from collect_groups().
	 * @param array<string, int>   $index Header index.
	 * @return array<string, mixed>
	 */
	private static function build_workflow( array $group, array $index ) {
		$file_name = (string) $group['file_name'];
		$line      = (int) $group['line'];
		$records   = isset( $group['records'] ) && is_array( $group['records'] ) ? $group['records'] : array();
		$first     = isset( $records[0]['record'] ) && is_array( $records[0]['record'] ) ? $records[0]['record'] : array();

		foreach ( self::REPEATED as $column ) {
			$expected = self::repeated_value( $first, $index, $column );
			foreach ( $records as $item ) {
				$record = isset( $item['record'] ) && is_array( $item['record'] ) ? $item['record'] : array();
				if ( $expected !== self::repeated_value( $record, $index, $column ) ) {
					return self::failed_workflow(
						$line,
						$file_name,
						'header_disagreement',
						sprintf(
							/* translators: %s: column label */
							__( '%s does not match on every step.', 'order-machine' ),
							self::column_label( $column )
						)
					);
				}
			}
		}

		$kind_raw = SOM_Catalog_Import::cell( $first, $index, 'kind' );
		$kind     = strtolower( $kind_raw );
		if ( ! isset( SOM_Workflows::kind_choices()[ $kind ] ) ) {
			return self::failed_workflow(
				$line,
				$file_name,
				'bad_kind',
				__( 'Kind must be make, pack, or post_ship.', 'order-machine' )
			);
		}

		$uses_trays = SOM_Catalog_Import::parse_bool( SOM_Catalog_Import::cell( $first, $index, 'uses_trays' ) );
		if ( is_wp_error( $uses_trays ) ) {
			return self::failed_workflow(
				$line,
				$file_name,
				'bad_uses_trays',
				__( 'Uses trays must be yes or no.', 'order-machine' )
			);
		}

		$active = SOM_Catalog_Import::parse_bool( SOM_Catalog_Import::cell( $first, $index, 'is_active' ) );
		if ( is_wp_error( $active ) ) {
			return self::failed_workflow(
				$line,
				$file_name,
				'bad_is_active',
				__( 'Active must be yes or no.', 'order-machine' )
			);
		}

		$steps = array();
		foreach ( $records as $item ) {
			$record = isset( $item['record'] ) && is_array( $item['record'] ) ? $item['record'] : array();
			$built  = self::build_step( $record, $index, isset( $item['line'] ) ? (int) $item['line'] : $line );
			if ( is_wp_error( $built ) ) {
				return self::failed_workflow( $line, $file_name, $built->get_error_code(), $built->get_error_message() );
			}
			$steps[] = $built;
		}

		if ( ! $steps ) {
			return self::failed_workflow(
				$line,
				$file_name,
				'missing_step',
				__( 'A workflow needs at least one step.', 'order-machine' )
			);
		}

		usort(
			$steps,
			static function ( $a, $b ) {
				$a_order = isset( $a['step_order'] ) ? (int) $a['step_order'] : 0;
				$b_order = isset( $b['step_order'] ) ? (int) $b['step_order'] : 0;
				if ( $a_order === $b_order ) {
					$a_line = isset( $a['line'] ) ? (int) $a['line'] : 0;
					$b_line = isset( $b['line'] ) ? (int) $b['line'] : 0;
					return $a_line <=> $b_line;
				}
				return $a_order <=> $b_order;
			}
		);

		$form = array();
		foreach ( $steps as $step ) {
			unset( $step['step_order'], $step['line'] );
			$form[] = $step;
		}

		$valid = SOM_Workflows::validate_submission( $kind, $form, 0 );
		if ( is_wp_error( $valid ) ) {
			return self::failed_workflow( $line, $file_name, $valid->get_error_code(), $valid->get_error_message() );
		}

		return array(
			'row'       => $line,
			'file_name' => $file_name,
			'save_name' => '',
			'message'   => '',
			'code'      => '',
			'ok'        => true,
			'data'      => array(
				'name'        => $file_name,
				'description' => trim( sanitize_textarea_field( SOM_Catalog_Import::cell( $first, $index, 'description' ) ) ),
				'kind'        => $kind,
				'uses_trays'  => null === $uses_trays ? 0 : (int) $uses_trays,
				'is_active'   => null === $active ? 1 : (int) $active,
				'steps'       => $form,
			),
		);
	}

	/**
	 * @param array<int, string|null> $record Cells.
	 * @param array<string, int>      $index  Header index.
	 * @param int                      $line   1-based file row.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function build_step( array $record, array $index, $line ) {
		$step_name = trim( sanitize_text_field( SOM_Catalog_Import::cell( $record, $index, 'step_name' ) ) );
		if ( '' === $step_name ) {
			return new WP_Error( 'missing_step_name', __( 'Step name is required.', 'order-machine' ) );
		}
		if ( SOM_Catalog_Import::text_length( $step_name ) > SOM_Catalog_Import::NAME_LIMIT ) {
			return new WP_Error( 'step_name_length', __( 'Step name is too long.', 'order-machine' ) );
		}

		$step_order = self::whole_number(
			SOM_Catalog_Import::cell( $record, $index, 'step_order' ),
			-2147483647,
			self::INT_MAX
		);
		if ( '' === $step_order || null === $step_order ) {
			return new WP_Error( 'bad_step_order', __( 'Step order must be a whole number.', 'order-machine' ) );
		}

		$manual = SOM_Catalog_Import::parse_bool( SOM_Catalog_Import::cell( $record, $index, 'requires_manual_confirm' ) );
		if ( is_wp_error( $manual ) ) {
			return new WP_Error( 'bad_manual_confirm', __( 'Manual confirm must be yes or no.', 'order-machine' ) );
		}

		$timer = self::whole_number( SOM_Catalog_Import::cell( $record, $index, 'timer_seconds' ), 1, self::INT_MAX );
		if ( null === $timer ) {
			return new WP_Error( 'bad_timer', __( 'Timer must be a whole number of seconds.', 'order-machine' ) );
		}

		$planned = self::whole_number( SOM_Catalog_Import::cell( $record, $index, 'planned_seconds' ), 1, self::INT_MAX );
		if ( null === $planned ) {
			return new WP_Error( 'bad_planned', __( 'Planned duration must be a whole number of seconds.', 'order-machine' ) );
		}

		$confirmation = self::parse_confirmation( SOM_Catalog_Import::cell( $record, $index, 'confirmation_kind' ) );
		if ( is_wp_error( $confirmation ) ) {
			return $confirmation;
		}

		$tasks = self::parse_tasks( SOM_Catalog_Import::cell( $record, $index, 'tasks' ) );
		if ( is_wp_error( $tasks ) ) {
			return $tasks;
		}

		$batch = self::resolve_batch_key( SOM_Catalog_Import::cell( $record, $index, 'batch_group_key' ) );
		if ( is_wp_error( $batch ) ) {
			return $batch;
		}

		return array(
			'line'                    => (int) $line,
			'step_order'              => (int) $step_order,
			'name'                    => $step_name,
			'requires_manual_confirm' => null === $manual ? 0 : (int) $manual,
			'timer_seconds'           => $timer,
			'planned_seconds'         => $planned,
			'confirmation_kind'       => $confirmation,
			'instructions'            => SOM_Catalog_Import::cell( $record, $index, 'instructions' ),
			'tasks'                   => $tasks,
			'command_template'        => SOM_Catalog_Import::cell( $record, $index, 'command_template' ),
			'batch_group_id'          => (int) $batch,
		);
	}

	/**
	 * @param array<int, string|null> $record Cells.
	 * @param array<string, int>      $index  Header index.
	 * @param string                   $column Column name.
	 * @return string
	 */
	private static function repeated_value( array $record, array $index, $column ) {
		$raw = SOM_Catalog_Import::cell( $record, $index, $column );
		if ( 'name' === $column ) {
			return trim( sanitize_text_field( $raw ) );
		}
		if ( 'description' === $column ) {
			return trim( sanitize_textarea_field( $raw ) );
		}
		return $raw;
	}

	/**
	 * @param string $column Column name.
	 * @return string
	 */
	private static function column_label( $column ) {
		$labels = array(
			'name'        => __( 'Name', 'order-machine' ),
			'description' => __( 'Description', 'order-machine' ),
			'kind'        => __( 'Kind', 'order-machine' ),
			'uses_trays'  => __( 'Uses trays', 'order-machine' ),
			'is_active'   => __( 'Active', 'order-machine' ),
		);
		return isset( $labels[ $column ] ) ? $labels[ $column ] : (string) $column;
	}

	/**
	 * Task labels joined with " | ". A label that contains | fails the workflow.
	 *
	 * @param string $raw Cell text.
	 * @return array<int, array{label: string}>|WP_Error
	 */
	private static function parse_tasks( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}

		$tasks = array();
		foreach ( explode( ' | ', $raw ) as $part ) {
			$label = trim( $part );
			if ( '' === $label ) {
				continue;
			}
			if ( false !== strpos( $label, '|' ) ) {
				return new WP_Error(
					'bad_task_label',
					__( 'A task label cannot contain a | character.', 'order-machine' )
				);
			}
			$tasks[] = array(
				'label' => $label,
			);
		}
		return $tasks;
	}

	/**
	 * @param string $raw Cell text.
	 * @return string|WP_Error Blank, or a known confirmation kind.
	 */
	private static function parse_confirmation( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$kind = sanitize_key( $raw );
		if ( ! in_array( $kind, SOM_Step_Confirmations::kinds(), true ) ) {
			return new WP_Error( 'bad_confirmation_kind', __( 'Unknown confirmation kind.', 'order-machine' ) );
		}
		return $kind;
	}

	/**
	 * Existing batch group id, or 0 when the cell is blank. Does not create a group.
	 *
	 * @param string $raw Cell text.
	 * @return int|WP_Error
	 */
	private static function resolve_batch_key( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return 0;
		}

		$ids = self::batch_ids( $raw );
		if ( ! $ids ) {
			return new WP_Error( 'unknown_batch_group', __( 'Unknown batch group.', 'order-machine' ) );
		}
		if ( count( $ids ) > 1 ) {
			return new WP_Error(
				'ambiguous_batch_group',
				__( 'Batch group key matches more than one batch group.', 'order-machine' )
			);
		}
		return (int) $ids[0];
	}

	/**
	 * @param string $key Group key from the file.
	 * @return int[]
	 */
	private static function batch_ids( $key ) {
		global $wpdb;

		$table = SOM_DB::table( 'batch_groups' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from SOM_DB::table.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE LOWER(group_key) = LOWER(%s)",
				$key
			)
		);
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_map( 'intval', $ids );
	}

	/**
	 * Blank stays blank. A filled cell must be a whole number inside the range.
	 *
	 * @param string $raw Cell text.
	 * @param int    $min Inclusive.
	 * @param int    $max Inclusive.
	 * @return int|string|null Integer, empty string when blank, null when invalid.
	 */
	private static function whole_number( $raw, $min, $max ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( ! preg_match( '/^[+-]?\d+$/', $raw ) ) {
			return null;
		}

		$n = (int) $raw;
		if ( $n < (int) $min || $n > (int) $max ) {
			return null;
		}

		$digits = ltrim( $raw, '+-' );
		$digits = ltrim( $digits, '0' );
		if ( '' === $digits ) {
			$digits = '0';
		}
		$expect = ( $n < 0 ? '-' : '' ) . $digits;
		if ( $expect !== (string) $n ) {
			return null;
		}
		return $n;
	}

	/**
	 * @param mixed $raw Stored JSON or null.
	 * @return string
	 */
	private static function export_tasks( $raw ) {
		$labels = array();
		foreach ( SOM_Step_Tasks::decode_list( $raw ) as $task ) {
			if ( isset( $task['label'] ) && '' !== (string) $task['label'] ) {
				$labels[] = (string) $task['label'];
			}
		}
		return implode( ' | ', $labels );
	}

	/**
	 * @param mixed $value Stored text or null.
	 * @return string
	 */
	private static function export_text( $value ) {
		if ( null === $value ) {
			return '';
		}
		return (string) $value;
	}

	/**
	 * @param mixed $value Stored integer or null.
	 * @return string
	 */
	private static function export_int( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}
		return (string) (int) $value;
	}

	/**
	 * @param int    $line      1-based file row.
	 * @param string $file_name Template name from the file.
	 * @param string $code      Error code.
	 * @param string $message   Error text.
	 * @return array<string, mixed>
	 */
	private static function failed_workflow( $line, $file_name, $code, $message ) {
		return array(
			'row'       => (int) $line,
			'file_name' => (string) $file_name,
			'save_name' => '',
			'message'   => (string) $message,
			'code'      => (string) $code,
			'ok'        => false,
		);
	}

	/**
	 * @param string $message Error text.
	 * @param string $code    Error code.
	 * @return array<string, mixed>
	 */
	private static function file_failure( $message, $code ) {
		return array(
			'rows'            => array(),
			'ready'           => 0,
			'failed'          => 0,
			'file_error'      => (string) $message,
			'file_error_code' => (string) $code,
		);
	}
}
