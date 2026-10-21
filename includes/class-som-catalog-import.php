<?php
/**
 * Shared catalog CSV helpers for materials, workflows, and products.
 *
 * Name suffix and preview-token storage. Workflow and product importers are separate.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reusable pieces of catalog import. Does not insert catalog rows.
 */
class SOM_Catalog_Import {

	/**
	 * Name column width on materials, workflow templates, and products.
	 */
	const NAME_LIMIT = 100;

	/**
	 * Tables whose names can collide on import.
	 *
	 * @var string[]
	 */
	const NAME_TABLES = array(
		'materials',
		'workflow_templates',
		'products',
	);

	/**
	 * Case-insensitive key for a catalog name.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	public static function name_key( $name ) {
		$name = sanitize_text_field( (string) $name );
		$name = trim( $name );
		if ( '' === $name ) {
			return '';
		}
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $name, 'UTF-8' );
		}
		return strtolower( $name );
	}

	/**
	 * @param string $text Text.
	 * @return int
	 */
	public static function text_length( $text ) {
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
	public static function text_cut( $text, $length ) {
		$text   = (string) $text;
		$length = max( 0, (int) $length );
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, $length );
		}
		return (string) substr( $text, 0, $length );
	}

	/**
	 * Names already stored, keyed by name_key().
	 *
	 * @param string $table materials|workflow_templates|products.
	 * @return array<string, bool>|WP_Error
	 */
	public static function load_taken_names( $table ) {
		global $wpdb;

		$table = (string) $table;
		if ( ! in_array( $table, self::NAME_TABLES, true ) ) {
			return new WP_Error( 'som_catalog_table', __( 'Unknown catalog.', 'order-machine' ) );
		}

		$sql  = 'SELECT name FROM ' . SOM_DB::table( $table );
		$rows = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is allowlisted.
		$taken = array();
		if ( ! is_array( $rows ) ) {
			return $taken;
		}
		foreach ( $rows as $name ) {
			$key = self::name_key( (string) $name );
			if ( '' !== $key ) {
				$taken[ $key ] = true;
			}
		}
		return $taken;
	}

	/**
	 * Next catalog name. Does not write a row.
	 *
	 * A free name keeps the file's capitalization. A taken name becomes "Name (2)",
	 * then "Name (3)". The base is trimmed from the right when the suffix would
	 * pass 100 characters. The chosen key is added to $taken.
	 *
	 * @param string               $name  File name.
	 * @param array<string, bool>  $taken Taken keys. Updated on success.
	 * @return string|WP_Error
	 */
	public static function allocate_name( $name, array &$taken ) {
		$base = sanitize_text_field( (string) $name );
		$base = trim( $base );
		if ( '' === $base ) {
			return new WP_Error( 'som_catalog_name_empty', __( 'Name is required.', 'order-machine' ) );
		}

		if ( ! self::is_taken( $base, $taken ) ) {
			if ( self::text_length( $base ) > self::NAME_LIMIT ) {
				return new WP_Error( 'som_catalog_name_length', __( 'Name does not fit the 100-character limit.', 'order-machine' ) );
			}
			$taken[ self::name_key( $base ) ] = true;
			return $base;
		}

		for ( $n = 2; $n <= 10000; $n++ ) {
			$candidate = self::fit_with_suffix( $base, ' (' . $n . ')' );
			if ( null === $candidate ) {
				return new WP_Error( 'som_catalog_name_length', __( 'Name does not fit the 100-character limit.', 'order-machine' ) );
			}
			if ( ! self::is_taken( $candidate, $taken ) ) {
				$taken[ self::name_key( $candidate ) ] = true;
				return $candidate;
			}
		}

		return new WP_Error( 'som_catalog_name_length', __( 'Name does not fit the 100-character limit.', 'order-machine' ) );
	}

	/**
	 * Drop a name reserved by allocate_name() when the insert did not happen.
	 *
	 * @param string              $name  Name returned by allocate_name().
	 * @param array<string, bool> $taken Taken keys.
	 * @return void
	 */
	public static function release_name( $name, array &$taken ) {
		$key = self::name_key( $name );
		if ( '' === $key ) {
			return;
		}
		unset( $taken[ $key ] );
	}

	/**
	 * Blank, yes/no, or an error when the cell is filled with something else.
	 *
	 * Accepts 1/0, yes/no, and true/false. Blank returns null so the caller can apply its default.
	 *
	 * @param string $raw Cell text.
	 * @return bool|null|WP_Error
	 */
	public static function parse_bool( $raw ) {
		$raw = strtolower( trim( (string) $raw ) );
		if ( '' === $raw ) {
			return null;
		}
		if ( in_array( $raw, array( '1', 'yes', 'true' ), true ) ) {
			return true;
		}
		if ( in_array( $raw, array( '0', 'no', 'false' ), true ) ) {
			return false;
		}
		return new WP_Error( 'som_catalog_bool', __( 'Use yes or no.', 'order-machine' ) );
	}

	/**
	 * Keep an uploaded file so Import can commit the same text.
	 *
	 * Does not insert a catalog row. The copy expires after 15 minutes.
	 * $kind separates materials, workflows, and products for the same user.
	 *
	 * @param string $kind     Catalog kind, such as materials.
	 * @param int    $user_id  Current user.
	 * @param string $contents File text.
	 * @return string Token.
	 */
	public static function remember( $kind, $user_id, $contents ) {
		$token = wp_generate_password( 32, false, false );
		set_transient(
			self::memory_key( $kind, (int) $user_id, $token ),
			(string) $contents,
			15 * MINUTE_IN_SECONDS
		);
		return $token;
	}

	/**
	 * @param string $kind    Catalog kind.
	 * @param int    $user_id Current user.
	 * @param string $token   Preview token.
	 * @return string File text, or empty when missing.
	 */
	public static function recall( $kind, $user_id, $token ) {
		$token = self::clean_token( $token );
		if ( '' === $token ) {
			return '';
		}
		$stored = get_transient( self::memory_key( $kind, (int) $user_id, $token ) );
		return is_string( $stored ) ? $stored : '';
	}

	/**
	 * @param string $kind    Catalog kind.
	 * @param int    $user_id Current user.
	 * @param string $token   Preview token.
	 * @return void
	 */
	public static function forget( $kind, $user_id, $token ) {
		$token = self::clean_token( $token );
		if ( '' === $token ) {
			return;
		}
		delete_transient( self::memory_key( $kind, (int) $user_id, $token ) );
	}

	/**
	 * @param string $token Preview token.
	 * @return string
	 */
	public static function clean_token( $token ) {
		$token = is_string( $token ) ? $token : '';
		$token = preg_replace( '/[^A-Za-z0-9]/', '', $token );
		return is_string( $token ) ? $token : '';
	}

	/**
	 * Read an uploaded catalog file. Does not insert rows.
	 *
	 * @param string $field $_FILES key.
	 * @return string|WP_Error File text.
	 */
	public static function read_upload( $field ) {
		// Caller checks the preview nonce before this runs.
		if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return new WP_Error( 'som_import_file', __( 'Choose a file to import.', 'order-machine' ) );
		}

		$file  = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp path is not slashed.
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return new WP_Error( 'som_import_file', __( 'Choose a file to import.', 'order-machine' ) );
		}
		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			return new WP_Error( 'som_import_file', __( 'That file is too large.', 'order-machine' ) );
		}
		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'som_import_file', __( 'Could not upload that file.', 'order-machine' ) );
		}

		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'som_import_file', __( 'Could not upload that file.', 'order-machine' ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$cap  = self::max_bytes();
		if ( $size < 1 ) {
			return new WP_Error( 'som_import_file', __( 'That file is empty.', 'order-machine' ) );
		}
		if ( $size > $cap ) {
			return new WP_Error( 'som_import_file', __( 'That file is too large.', 'order-machine' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload tmp, not a remote URL.
		$contents = file_get_contents( $tmp );
		if ( ! is_string( $contents ) || '' === $contents ) {
			return new WP_Error( 'som_import_file', __( 'That file is empty.', 'order-machine' ) );
		}
		if ( strlen( $contents ) > $cap ) {
			return new WP_Error( 'som_import_file', __( 'That file is too large.', 'order-machine' ) );
		}
		if ( false !== strpos( $contents, "\0" ) ) {
			return new WP_Error( 'som_import_file', __( 'That file is not a text file.', 'order-machine' ) );
		}

		return $contents;
	}

	/**
	 * Split a catalog file. The first non-empty row is the header.
	 *
	 * Delimiter comes from that header line: a tab means tab-separated, otherwise comma.
	 * A leading UTF-8 BOM is removed. Header match is left to the caller.
	 *
	 * @param string $contents File text.
	 * @return array{
	 *     delimiter: string,
	 *     rows: array<int, array<int, string|null>>,
	 *     header_at: int,
	 *     headers: array<int, string|null>,
	 *     index: array<string, int>,
	 *     error: string,
	 *     error_code: string
	 * }
	 */
	public static function read_table( $contents ) {
		$contents = self::normalize_contents( $contents );
		if ( '' === trim( $contents ) ) {
			return self::empty_table();
		}

		$delimiter = self::delimiter_from_header( $contents );
		$rows      = self::csv_rows( $contents, $delimiter );
		$header_at = null;
		foreach ( $rows as $i => $row ) {
			if ( is_array( $row ) && ! self::row_is_empty( $row ) ) {
				$header_at = (int) $i;
				break;
			}
		}
		if ( null === $header_at ) {
			return self::empty_table();
		}

		$headers = isset( $rows[ $header_at ] ) && is_array( $rows[ $header_at ] ) ? $rows[ $header_at ] : array();

		return array(
			'delimiter'  => $delimiter,
			'rows'       => $rows,
			'header_at'  => $header_at,
			'headers'    => $headers,
			'index'      => self::header_index( $headers ),
			'error'      => '',
			'error_code' => '',
		);
	}

	/**
	 * Required headers that are absent. Match is case-insensitive.
	 *
	 * @param array<string, int> $index    Header index from read_table().
	 * @param string[]           $required Column names.
	 * @return string[]
	 */
	public static function missing_columns( array $index, array $required ) {
		$missing = array();
		foreach ( $required as $name ) {
			$key = strtolower( trim( (string) $name ) );
			if ( '' === $key || isset( $index[ $key ] ) ) {
				continue;
			}
			$missing[] = (string) $name;
		}
		return $missing;
	}

	/**
	 * @param array<int, string|null> $row   Cells.
	 * @param array<string, int>      $index Header index.
	 * @param string                  $name  Column name.
	 * @return string
	 */
	public static function cell( array $row, array $index, $name ) {
		$key = strtolower( trim( (string) $name ) );
		if ( ! isset( $index[ $key ] ) ) {
			return '';
		}
		$i = (int) $index[ $key ];
		if ( ! array_key_exists( $i, $row ) || null === $row[ $i ] ) {
			return '';
		}
		return trim( (string) $row[ $i ] );
	}

	/**
	 * @param array<int, string|null> $row Cells.
	 * @return bool
	 */
	public static function row_is_empty( array $row ) {
		foreach ( $row as $cell ) {
			if ( null !== $cell && '' !== trim( (string) $cell ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * CSV text for a header and data rows. Includes a UTF-8 BOM for spreadsheet apps.
	 *
	 * @param string[]              $header Column names.
	 * @param array<int, string[]>  $rows   Data rows, in column order.
	 * @return string
	 */
	public static function to_csv( array $header, array $rows ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory CSV, not the filesystem.
		$handle = fopen( 'php://temp', 'r+' );
		if ( false === $handle ) {
			return '';
		}
		fputcsv( $handle, $header, ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, ',', '"', '' );
		}
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! is_string( $csv ) ) {
			return '';
		}
		return "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * @param string $contents File text.
	 * @return string
	 */
	public static function normalize_contents( $contents ) {
		if ( ! is_string( $contents ) ) {
			return '';
		}
		if ( str_starts_with( $contents, "\xEF\xBB\xBF" ) ) {
			$contents = substr( $contents, 3 );
		}
		$contents = str_replace( "\r\n", "\n", $contents );
		$contents = str_replace( "\r", "\n", $contents );
		return $contents;
	}

	/**
	 * @param string               $name  Candidate.
	 * @param array<string, bool>  $taken Taken keys.
	 * @return bool
	 */
	private static function is_taken( $name, array $taken ) {
		$key = self::name_key( $name );
		if ( '' === $key ) {
			return false;
		}
		return isset( $taken[ $key ] );
	}

	/**
	 * Append a suffix, trimming the base from the right so the result fits.
	 *
	 * @param string $base   File name.
	 * @param string $suffix Suffix including the leading space, such as " (2)".
	 * @return string|null Null when the suffix itself does not fit.
	 */
	private static function fit_with_suffix( $base, $suffix ) {
		$limit = self::NAME_LIMIT;
		if ( self::text_length( $suffix ) > $limit ) {
			return null;
		}
		if ( self::text_length( $base . $suffix ) <= $limit ) {
			return $base . $suffix;
		}

		$keep = $limit - self::text_length( $suffix );
		$base = rtrim( self::text_cut( $base, $keep ) );
		return $base . $suffix;
	}

	/**
	 * @param string $contents Normalized file text.
	 * @return string Comma or tab.
	 */
	private static function delimiter_from_header( $contents ) {
		$lines = explode( "\n", $contents );
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			if ( false !== strpos( $line, "\t" ) ) {
				return "\t";
			}
			return ',';
		}
		return ',';
	}

	/**
	 * @param string $contents  File text.
	 * @param string $delimiter Comma or tab.
	 * @return array<int, array<int, string|null>>
	 */
	private static function csv_rows( $contents, $delimiter ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory CSV parse, not the filesystem.
		$handle = fopen( 'php://temp', 'r+' );
		if ( false === $handle ) {
			return array();
		}
		fwrite( $handle, $contents );
		rewind( $handle );

		$rows = array();
		while ( true ) {
			$row = fgetcsv( $handle, 0, $delimiter, '"', '' );
			if ( false === $row ) {
				break;
			}
			$rows[] = $row;
		}
		fclose( $handle );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}

	/**
	 * @param array<int, string|null> $headers Header cells.
	 * @return array<string, int>
	 */
	private static function header_index( array $headers ) {
		$index = array();
		foreach ( $headers as $i => $header ) {
			if ( null === $header ) {
				continue;
			}
			$name = strtolower( trim( (string) $header ) );
			if ( '' === $name ) {
				continue;
			}
			$index[ $name ] = (int) $i;
		}
		return $index;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_table() {
		return array(
			'delimiter'  => ',',
			'rows'       => array(),
			'header_at'  => 0,
			'headers'    => array(),
			'index'      => array(),
			'error'      => __( 'That file has no header row.', 'order-machine' ),
			'error_code' => 'empty_file',
		);
	}

	/**
	 * @return int
	 */
	private static function max_bytes() {
		$cap = defined( 'MB_IN_BYTES' ) ? 8 * (int) MB_IN_BYTES : 8388608;
		if ( function_exists( 'wp_max_upload_size' ) ) {
			$upload = (int) wp_max_upload_size();
			if ( $upload > 0 && $upload < $cap ) {
				$cap = $upload;
			}
		}
		return $cap;
	}

	/**
	 * @param string $kind    Catalog kind.
	 * @param int    $user_id Current user.
	 * @param string $token   Preview token.
	 * @return string
	 */
	private static function memory_key( $kind, $user_id, $token ) {
		$kind = sanitize_key( (string) $kind );
		if ( '' === $kind ) {
			$kind = 'catalog';
		}
		return 'som_cimp_' . $kind . '_' . (int) $user_id . '_' . $token;
	}
}
