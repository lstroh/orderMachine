<?php
/**
 * Materials CSV export, template, preview, and import.
 *
 * Creates rows through SOM_Materials::create. Does not update, and does not
 * set stock, a stock log, or source_product_id.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Materials catalog file.
 */
class SOM_Material_Import {

	/**
	 * Preview-token kind. Workflow and product files use their own kinds.
	 */
	const KIND = 'materials';

	/**
	 * Columns shared by export, template, and import.
	 *
	 * @var string[]
	 */
	const COLUMNS = array(
		'name',
		'unit',
		'low_stock_threshold',
		'unit_cost',
		'preferred_supplier',
		'is_active',
	);

	/**
	 * @var string[]
	 */
	const REQUIRED = array(
		'name',
		'unit',
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
	 * Current materials, omitting made-in-house output materials.
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
			$filename = 'materials.csv';
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
	 * Parse a file. Does not insert materials.
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
	 * Create the materials in a file. A failed row does not remove rows already saved.
	 *
	 * @param string $contents File text.
	 * @return array{created: int, failed: int, file_error: string, file_error_code: string}
	 */
	public static function commit( $contents ) {
		$prepared = self::prepare( $contents );
		if ( '' !== $prepared['file_error'] ) {
			return array(
				'created'          => 0,
				'failed'           => 0,
				'file_error'       => $prepared['file_error'],
				'file_error_code'  => $prepared['file_error_code'],
			);
		}

		$taken = SOM_Catalog_Import::load_taken_names( 'materials' );
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

			$data         = $row['data'];
			$data['name'] = $save;
			$result       = SOM_Materials::create( $data );
			if ( is_wp_error( $result ) ) {
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
	 * Parse the file stored for a preview token. Does not insert materials.
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

		$materials = SOM_DB::table( 'materials' );
		$suppliers = SOM_DB::table( 'suppliers' );
		$sql       = "SELECT m.name, m.unit, m.low_stock_threshold, m.unit_cost, s.name AS preferred_supplier, m.is_active
			FROM {$materials} m
			LEFT JOIN {$suppliers} s ON s.id = m.preferred_supplier_id
			WHERE m.source_product_id IS NULL
			ORDER BY m.name ASC, m.id ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names from SOM_DB::table, no user input.
		$rows = $wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				isset( $row->name ) ? (string) $row->name : '',
				isset( $row->unit ) ? (string) $row->unit : '',
				self::export_decimal( isset( $row->low_stock_threshold ) ? $row->low_stock_threshold : null ),
				self::export_decimal( isset( $row->unit_cost ) ? $row->unit_cost : null ),
				isset( $row->preferred_supplier ) ? (string) $row->preferred_supplier : '',
				(string) (int) ( isset( $row->is_active ) ? $row->is_active : 0 ),
			);
		}
		return $out;
	}

	/**
	 * Validate rows without reserving names.
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

		$suppliers = self::supplier_index();
		$rows      = array();
		$failed    = 0;
		$records   = $table['rows'];
		$count     = count( $records );
		$header_at = (int) $table['header_at'];
		$index     = $table['index'];

		for ( $i = $header_at + 1; $i < $count; $i++ ) {
			$record = $records[ $i ];
			if ( ! is_array( $record ) || SOM_Catalog_Import::row_is_empty( $record ) ) {
				continue;
			}

			$built = self::build_row( $i + 1, $record, $index, $suppliers );
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
	 * Fill save_name for rows that can be created. Preview only; commit allocates again.
	 *
	 * @param array<string, mixed> $prepared Prepared file.
	 * @return array<string, mixed>
	 */
	private static function with_names( array $prepared ) {
		$taken = SOM_Catalog_Import::load_taken_names( 'materials' );
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
	 * @param int                    $line      1-based file row.
	 * @param array<int, string|null> $record   Cells.
	 * @param array<string, int>     $index     Header index.
	 * @param array<string, int[]>   $suppliers Lowercased supplier name to ids.
	 * @return array<string, mixed>
	 */
	private static function build_row( $line, array $record, array $index, array $suppliers ) {
		$file_name = sanitize_text_field( SOM_Catalog_Import::cell( $record, $index, 'name' ) );
		$file_name = trim( $file_name );
		$unit      = sanitize_text_field( SOM_Catalog_Import::cell( $record, $index, 'unit' ) );
		$unit      = trim( $unit );

		$row = array(
			'row'       => (int) $line,
			'file_name' => $file_name,
			'save_name' => '',
			'message'   => '',
			'code'      => '',
			'ok'        => false,
		);

		if ( '' === $file_name ) {
			$row['code']    = 'missing_name';
			$row['message'] = __( 'Name is required.', 'order-machine' );
			return $row;
		}
		if ( '' === $unit ) {
			$row['code']    = 'missing_unit';
			$row['message'] = __( 'Unit is required.', 'order-machine' );
			return $row;
		}
		if ( SOM_Catalog_Import::text_length( $unit ) > 20 ) {
			$row['code']    = 'bad_unit';
			$row['message'] = __( 'Unit is too long.', 'order-machine' );
			return $row;
		}

		$threshold = self::optional_decimal( SOM_Catalog_Import::cell( $record, $index, 'low_stock_threshold' ), 2, 100000000 );
		if ( null === $threshold ) {
			$row['code']    = 'bad_low_stock_threshold';
			$row['message'] = __( 'Low-stock threshold must be a number.', 'order-machine' );
			return $row;
		}

		$unit_cost = self::optional_decimal( SOM_Catalog_Import::cell( $record, $index, 'unit_cost' ), 4, 1000000 );
		if ( null === $unit_cost ) {
			$row['code']    = 'bad_unit_cost';
			$row['message'] = __( 'Unit cost must be a number.', 'order-machine' );
			return $row;
		}

		$active = SOM_Catalog_Import::parse_bool( SOM_Catalog_Import::cell( $record, $index, 'is_active' ) );
		if ( is_wp_error( $active ) ) {
			$row['code']    = 'bad_is_active';
			$row['message'] = __( 'Active must be yes or no.', 'order-machine' );
			return $row;
		}

		$supplier_name = sanitize_text_field( SOM_Catalog_Import::cell( $record, $index, 'preferred_supplier' ) );
		$supplier_name = trim( $supplier_name );
		$supplier_id   = null;
		if ( '' !== $supplier_name ) {
			$key = SOM_Catalog_Import::name_key( $supplier_name );
			$ids = ( '' !== $key && isset( $suppliers[ $key ] ) ) ? $suppliers[ $key ] : array();
			if ( ! $ids ) {
				$row['code']    = 'unknown_supplier';
				$row['message'] = __( 'Unknown supplier.', 'order-machine' );
				return $row;
			}
			if ( count( $ids ) > 1 ) {
				$row['code']    = 'ambiguous_supplier';
				$row['message'] = __( 'Supplier name matches more than one supplier.', 'order-machine' );
				return $row;
			}
			$supplier_id = (int) $ids[0];
		}

		$data = array(
			'name'                => $file_name,
			'unit'                => $unit,
			'low_stock_threshold' => $threshold,
			'unit_cost'           => $unit_cost,
			'is_active'           => null === $active ? 1 : (int) $active,
		);
		if ( null !== $supplier_id ) {
			$data['preferred_supplier_id'] = $supplier_id;
		}

		$row['ok']   = true;
		$row['data'] = $data;
		return $row;
	}

	/**
	 * @return array<string, int[]>
	 */
	private static function supplier_index() {
		$index = array();
		foreach ( SOM_Suppliers::list_all() as $supplier ) {
			$key = SOM_Catalog_Import::name_key( isset( $supplier->name ) ? (string) $supplier->name : '' );
			if ( '' === $key ) {
				continue;
			}
			if ( ! isset( $index[ $key ] ) ) {
				$index[ $key ] = array();
			}
			$index[ $key ][] = (int) $supplier->id;
		}
		return $index;
	}

	/**
	 * @param string $raw            Cell text.
	 * @param int    $places         Decimal places.
	 * @param float  $max_exclusive  Absolute value must stay below this.
	 * @return string|null Empty string when blank, formatted number, or null when invalid.
	 */
	private static function optional_decimal( $raw, $places, $max_exclusive ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( ! is_numeric( $raw ) ) {
			return null;
		}
		$formatted = number_format( (float) $raw, (int) $places, '.', '' );
		if ( abs( (float) $formatted ) >= (float) $max_exclusive ) {
			return null;
		}
		return $formatted;
	}

	/**
	 * @param mixed $value Stored decimal or null.
	 * @return string
	 */
	private static function export_decimal( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}
		return (string) $value;
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
