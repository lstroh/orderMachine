<?php
/**
 * Listing-report import — eBay active listings and Amazon inventory.
 *
 * Detects the file from the header row. A row is inserted only when its SKU
 * matches a product and that channel plus external id is not already mapped.
 * Price and quantity are stored on the new listing row. Nothing is pushed.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parses and inserts marketplace listing reports.
 */
class SOM_Listing_Import {

	/**
	 * Parse a file. Does not insert listings.
	 *
	 * @param string $contents File text. Comma or tab. A leading UTF-8 BOM is ignored.
	 * @return array{
	 *     format: string,
	 *     rows: array<int, array{row: int, sku: string, external_listing_id: string, title: string, price: float, quantity_available: int}>
	 * }
	 */
	public static function parse( $contents ) {
		$contents   = self::normalize_contents( $contents );
		$delimiters = array( ',' );
		if ( false !== strpos( $contents, "\t" ) ) {
			$delimiters = array( "\t", ',' );
		}

		$chosen = null;
		foreach ( $delimiters as $delimiter ) {
			$attempt = self::split_table( $contents, $delimiter );
			if ( '' !== $attempt['format'] ) {
				$chosen = $attempt;
				break;
			}
		}

		if ( null === $chosen ) {
			return array(
				'format' => '',
				'rows'   => array(),
			);
		}

		return array(
			'format' => $chosen['format'],
			'rows'   => self::build_rows( $chosen ),
		);
	}

	/**
	 * Insert listing maps for rows whose SKU matches a product.
	 *
	 * An unknown SKU is reported and not inserted. An external id that is
	 * already mapped is skipped, and its price and quantity are left as they are.
	 * A row SOM_Listings::create() rejects is reported and not inserted.
	 *
	 * @param string $contents File text.
	 * @return array{format: string, created: int, skipped: int, unmatched: array<int, array<string, mixed>>}|WP_Error
	 */
	public static function commit( $contents ) {
		$parsed = self::parse( $contents );
		if ( '' === $parsed['format'] ) {
			return new WP_Error(
				'som_listing_import_format',
				__( 'That file is not an eBay active listings report or an Amazon inventory report.', 'order-machine' )
			);
		}

		$channel = SOM_Channels::get_by_slug( $parsed['format'] );
		if ( ! $channel ) {
			return new WP_Error( 'som_listing_import_channel', __( 'Channel not found.', 'order-machine' ) );
		}

		$channel_id = (int) $channel->id;
		$created    = 0;
		$skipped    = 0;
		$unmatched  = array();

		foreach ( $parsed['rows'] as $row ) {
			$product_id = self::product_id_for_sku( $row['sku'] );
			if ( $product_id < 1 ) {
				$unmatched[] = self::unmatched_row( $row, 'unmatched_sku', __( 'No product with this SKU.', 'order-machine' ) );
				continue;
			}

			$external = sanitize_text_field( (string) $row['external_listing_id'] );
			if ( '' !== $external && self::listing_exists( $channel_id, $external ) ) {
				++$skipped;
				continue;
			}

			$result = SOM_Listings::create(
				array(
					'product_id'          => $product_id,
					'channel_slug'        => $parsed['format'],
					'external_listing_id' => $external,
					'title'               => $row['title'],
					'price'               => $row['price'],
					'quantity_available'  => $row['quantity_available'],
				)
			);
			if ( is_wp_error( $result ) ) {
				$unmatched[] = self::unmatched_row( $row, $result->get_error_code(), $result->get_error_message() );
				continue;
			}
			++$created;
		}

		return array(
			'format'    => $parsed['format'],
			'created'   => $created,
			'skipped'   => $skipped,
			'unmatched' => $unmatched,
		);
	}

	/**
	 * Read an uploaded listing report. Does not insert listings.
	 *
	 * @param string $field $_FILES key.
	 * @return string|WP_Error File text.
	 */
	public static function read_upload( $field ) {
		// Caller (SOM_Admin_Menu) checks the import nonce before this runs.
		if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return new WP_Error( 'som_listing_import_file', __( 'Choose a file to import.', 'order-machine' ) );
		}

		$file  = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp path is not slashed.
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return new WP_Error( 'som_listing_import_file', __( 'Choose a file to import.', 'order-machine' ) );
		}
		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			return new WP_Error( 'som_listing_import_file', __( 'That file is too large.', 'order-machine' ) );
		}
		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'som_listing_import_file', __( 'Could not upload that file.', 'order-machine' ) );
		}

		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'som_listing_import_file', __( 'Could not upload that file.', 'order-machine' ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$cap  = self::max_bytes();
		if ( $size < 1 ) {
			return new WP_Error( 'som_listing_import_file', __( 'That file is empty.', 'order-machine' ) );
		}
		if ( $size > $cap ) {
			return new WP_Error( 'som_listing_import_file', __( 'That file is too large.', 'order-machine' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload tmp, not a remote URL.
		$contents = file_get_contents( $tmp );
		if ( ! is_string( $contents ) || '' === $contents ) {
			return new WP_Error( 'som_listing_import_file', __( 'That file is empty.', 'order-machine' ) );
		}
		if ( strlen( $contents ) > $cap ) {
			return new WP_Error( 'som_listing_import_file', __( 'That file is too large.', 'order-machine' ) );
		}
		if ( false !== strpos( $contents, "\0" ) ) {
			return new WP_Error( 'som_listing_import_file', __( 'That file is not a text listing report.', 'order-machine' ) );
		}

		return $contents;
	}

	/**
	 * Keep the import result so the listings screen can show it after redirect.
	 *
	 * @param int                  $user_id Current user.
	 * @param array<string, mixed> $result  commit() result.
	 * @return string Token.
	 */
	public static function remember_result( $user_id, array $result ) {
		$token = wp_generate_password( 32, false, false );
		set_transient( self::result_key( (int) $user_id, $token ), $result, 15 * MINUTE_IN_SECONDS );
		return $token;
	}

	/**
	 * @param int    $user_id Current user.
	 * @param string $token   Result token.
	 * @return array<string, mixed>|null
	 */
	public static function recall_result( $user_id, $token ) {
		$token = self::clean_token( $token );
		if ( '' === $token ) {
			return null;
		}
		$stored = get_transient( self::result_key( (int) $user_id, $token ) );
		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * @param string $contents File text.
	 * @return string
	 */
	private static function normalize_contents( $contents ) {
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
	 * @param string $contents  File text.
	 * @param string $delimiter Comma or tab.
	 * @return array{format: string, rows: array<int, array<int, string|null>>, header_at: int, index: array<string, int>}
	 */
	private static function split_table( $contents, $delimiter ) {
		$rows      = self::csv_rows( $contents, $delimiter );
		$header_at = null;
		foreach ( $rows as $i => $row ) {
			if ( is_array( $row ) && ! self::row_is_empty( $row ) ) {
				$header_at = (int) $i;
				break;
			}
		}

		if ( null === $header_at ) {
			return array(
				'format'    => '',
				'rows'      => $rows,
				'header_at' => 0,
				'index'     => array(),
			);
		}

		$index  = self::header_index( $rows[ $header_at ] );
		$format = '';
		if ( self::is_ebay_header( $index ) ) {
			$format = 'ebay';
		} elseif ( self::is_amazon_header( $index ) ) {
			$format = 'amazon';
		}

		return array(
			'format'    => $format,
			'rows'      => $rows,
			'header_at' => $header_at,
			'index'     => $index,
		);
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
	 * @param array<int, string|null> $row Cells.
	 * @return bool
	 */
	private static function row_is_empty( array $row ) {
		foreach ( $row as $cell ) {
			if ( null !== $cell && '' !== trim( (string) $cell ) ) {
				return false;
			}
		}
		return true;
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
	 * eBay active listings. An orders report also has these columns, plus Order Number.
	 *
	 * @param array<string, int> $index Lowercased header → column.
	 * @return bool
	 */
	private static function is_ebay_header( array $index ) {
		if ( isset( $index['order number'] ) ) {
			return false;
		}
		return isset( $index['item number'] ) && isset( $index['custom label'] );
	}

	/**
	 * Amazon inventory. An order report uses order-id and sku, not seller-sku.
	 *
	 * @param array<string, int> $index Lowercased header → column.
	 * @return bool
	 */
	private static function is_amazon_header( array $index ) {
		if ( isset( $index['order-id'] ) ) {
			return false;
		}
		return isset( $index['seller-sku'] );
	}

	/**
	 * @param array{format: string, rows: array<int, array<int, string|null>>, header_at: int, index: array<string, int>} $table Parsed table.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_rows( array $table ) {
		$format    = (string) $table['format'];
		$rows      = $table['rows'];
		$index     = $table['index'];
		$header_at = (int) $table['header_at'];
		$out       = array();
		$count     = count( $rows );

		for ( $i = $header_at + 1; $i < $count; $i++ ) {
			$row = $rows[ $i ];
			if ( ! is_array( $row ) || self::row_is_empty( $row ) ) {
				continue;
			}

			if ( 'ebay' === $format ) {
				$out[] = self::listing_row(
					$i + 1,
					self::cell( $row, $index, 'custom label' ),
					self::cell( $row, $index, 'item number' ),
					self::cell( $row, $index, 'title' ),
					self::first_cell( $row, $index, array( 'current price', 'start price', 'price' ) ),
					self::first_cell( $row, $index, array( 'available quantity', 'quantity' ) )
				);
				continue;
			}

			$out[] = self::listing_row(
				$i + 1,
				self::cell( $row, $index, 'seller-sku' ),
				self::first_cell( $row, $index, array( 'asin', 'seller-sku' ) ),
				self::cell( $row, $index, 'item-name' ),
				self::cell( $row, $index, 'price' ),
				self::cell( $row, $index, 'quantity' )
			);
		}

		return $out;
	}

	/**
	 * @param int    $row_number 1-based file row.
	 * @param string $sku        Product SKU from the file.
	 * @param string $external   External listing id from the file.
	 * @param string $title      Listing title.
	 * @param string $price_raw  Price text.
	 * @param string $qty_raw    Quantity text.
	 * @return array{row: int, sku: string, external_listing_id: string, title: string, price: float, quantity_available: int}
	 */
	private static function listing_row( $row_number, $sku, $external, $title, $price_raw, $qty_raw ) {
		return array(
			'row'                 => (int) $row_number,
			'sku'                 => $sku,
			'external_listing_id' => $external,
			'title'               => $title,
			'price'               => self::price_value( $price_raw ),
			'quantity_available'  => self::quantity_value( $qty_raw ),
		);
	}

	/**
	 * First non-empty column in priority order.
	 *
	 * @param array<int, string|null> $row   Cells.
	 * @param array<string, int>      $index Header index.
	 * @param string[]                $names Lowercased column names.
	 * @return string
	 */
	private static function first_cell( array $row, array $index, array $names ) {
		foreach ( $names as $name ) {
			if ( ! isset( $index[ $name ] ) ) {
				continue;
			}
			$value = self::cell( $row, $index, $name );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * @param array<int, string|null> $row   Cells.
	 * @param array<string, int>      $index Header index.
	 * @param string                  $name  Column name.
	 * @return string
	 */
	private static function cell( array $row, array $index, $name ) {
		$key = strtolower( trim( $name ) );
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
	 * Lowest product id with this SKU, including inactive and internal products.
	 * create() still refuses an internal product.
	 *
	 * @param string $sku SKU from the file.
	 * @return int
	 */
	private static function product_id_for_sku( $sku ) {
		global $wpdb;

		$sku = sanitize_text_field( (string) $sku );
		if ( '' === $sku ) {
			return 0;
		}

		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE sku = %s ORDER BY id ASC LIMIT 1',
				$sku
			)
		);

		return $id ? (int) $id : 0;
	}

	/**
	 * @param int    $channel_id Channel PK.
	 * @param string $external   Sanitized external listing id.
	 * @return bool
	 */
	private static function listing_exists( $channel_id, $external ) {
		global $wpdb;

		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . SOM_DB::table( 'listings' ) . ' WHERE channel_id = %d AND external_listing_id = %s LIMIT 1',
				(int) $channel_id,
				$external
			)
		);

		return (int) $id > 0;
	}

	/**
	 * @param array<string, mixed> $row     Parsed row.
	 * @param string               $code    Reason code.
	 * @param string               $message Operator-facing reason.
	 * @return array{row: int, sku: string, external_listing_id: string, code: string, message: string}
	 */
	private static function unmatched_row( array $row, $code, $message ) {
		return array(
			'row'                 => (int) $row['row'],
			'sku'                 => (string) $row['sku'],
			'external_listing_id' => (string) $row['external_listing_id'],
			'code'                => (string) $code,
			'message'             => (string) $message,
		);
	}

	/**
	 * @param string $raw Money text. Currency symbols are ignored.
	 * @return float
	 */
	private static function price_value( $raw ) {
		$parsed = self::parse_money( $raw );
		return null === $parsed ? 0.0 : $parsed;
	}

	/**
	 * @param string $raw Quantity text. Blank or unreadable becomes 0.
	 * @return int
	 */
	private static function quantity_value( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return 0;
		}
		$raw = str_replace( array( ',', ' ', "\xC2\xA0" ), '', $raw );
		if ( ! is_numeric( $raw ) ) {
			return 0;
		}
		return max( 0, (int) round( (float) $raw ) );
	}

	/**
	 * @param string $raw Money text. Currency symbols are ignored.
	 * @return float|null
	 */
	private static function parse_money( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}
		$raw = str_replace( array( '£', '$', '€', "\xC2\xA0", ' ' ), '', $raw );
		if ( preg_match( '/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $raw ) ) {
			$raw = str_replace( ',', '', $raw );
		} elseif ( preg_match( '/^-?\d+,\d{1,2}$/', $raw ) ) {
			$raw = str_replace( ',', '.', $raw );
		} else {
			$raw = str_replace( ',', '', $raw );
		}
		if ( ! is_numeric( $raw ) ) {
			return null;
		}
		return round( (float) $raw, 2 );
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
	 * @param string $token Result token.
	 * @return string
	 */
	private static function clean_token( $token ) {
		$token = is_string( $token ) ? $token : '';
		if ( ! preg_match( '/^[A-Za-z0-9]{20,64}$/', $token ) ) {
			return '';
		}
		return $token;
	}

	/**
	 * @param int    $user_id Current user.
	 * @param string $token   Result token.
	 * @return string
	 */
	private static function result_key( $user_id, $token ) {
		return 'som_lim_' . (int) $user_id . '_' . $token;
	}
}
