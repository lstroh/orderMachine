<?php
/**
 * Order file import — eBay Seller Hub, Amazon order flat file, Order Machine template.
 *
 * Detects the file from the header row. Preview parses only; orders are inserted
 * when the operator confirms Import, via SOM_Order_Sync::create_from_external().
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parses and commits marketplace order files.
 */
class SOM_Order_Import {

	/**
	 * Order Machine template header, in order.
	 *
	 * @var string[]
	 */
	const TEMPLATE_HEADERS = array(
		'channel',
		'order_number',
		'order_date',
		'buyer_name',
		'address_1',
		'address_2',
		'city',
		'county',
		'postcode',
		'country',
		'item_number',
		'sku',
		'quantity',
		'unit_price',
		'house_number',
		'street_name',
		'style',
	);

	/**
	 * Parse a file. Does not insert orders.
	 *
	 * @param string $contents File text. Comma or tab. A leading UTF-8 BOM is ignored.
	 * @return array{
	 *     format: string,
	 *     orders: array<int, array<string, mixed>>,
	 *     order_count: int,
	 *     line_count: int,
	 *     failed: int,
	 *     errors: array<int, array{row: int, order_number: string, code: string, message: string}>
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
			return self::empty_result(
				array( self::error_row( 1, '', 'unrecognised_header' ) )
			);
		}

		return self::build_orders( $chosen );
	}

	/**
	 * Insert parsed orders. Each order is saved on its own: a failure does not
	 * delete orders that already succeeded, and a duplicate is skipped.
	 *
	 * @param string $contents File text.
	 * @return array{created: int, skipped: int, failed: int, errors: array<int, array<string, mixed>>}
	 */
	public static function commit( $contents ) {
		$parsed  = self::parse( $contents );
		$created = 0;
		$skipped = 0;
		$failed  = (int) $parsed['failed'];

		foreach ( $parsed['orders'] as $order ) {
			$result = SOM_Order_Sync::create_from_external( $order );
			if ( is_wp_error( $result ) ) {
				if ( 'som_order_exists' === $result->get_error_code() ) {
					++$skipped;
				} else {
					++$failed;
				}
				continue;
			}
			++$created;
		}

		return array(
			'created' => $created,
			'skipped' => $skipped,
			'failed'  => $failed,
			'errors'  => $parsed['errors'],
		);
	}

	/**
	 * Read an uploaded order file. Does not insert orders.
	 *
	 * @param string $field $_FILES key.
	 * @return string|WP_Error File text.
	 */
	public static function read_upload( $field ) {
		// Caller (SOM_Admin_Menu) checks the preview nonce before this runs.
		if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return new WP_Error( 'som_import_file', __( 'Choose a file to import.', 'order-machine' ) );
		}

		$file = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp path is not slashed.
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
			return new WP_Error( 'som_import_file', __( 'That file is not a text order report.', 'order-machine' ) );
		}

		return $contents;
	}

	/**
	 * Keep the uploaded text so Import can commit the same file.
	 *
	 * This does not insert an order. The copy expires after 15 minutes.
	 *
	 * @param int    $user_id  Current user.
	 * @param string $contents File text.
	 * @return string Token.
	 */
	public static function remember( $user_id, $contents ) {
		$token = wp_generate_password( 32, false, false );
		set_transient( self::memory_key( (int) $user_id, $token ), $contents, 15 * MINUTE_IN_SECONDS );
		return $token;
	}

	/**
	 * @param int    $user_id Current user.
	 * @param string $token   Preview token.
	 * @return string File text, or empty when missing.
	 */
	public static function recall( $user_id, $token ) {
		$token = self::clean_token( $token );
		if ( '' === $token ) {
			return '';
		}
		$stored = get_transient( self::memory_key( (int) $user_id, $token ) );
		return is_string( $stored ) ? $stored : '';
	}

	/**
	 * @param int    $user_id Current user.
	 * @param string $token   Preview token.
	 * @return void
	 */
	public static function forget( $user_id, $token ) {
		$token = self::clean_token( $token );
		if ( '' === $token ) {
			return;
		}
		delete_transient( self::memory_key( (int) $user_id, $token ) );
	}

	/**
	 * Parse the file stored for a preview token. Does not insert orders.
	 *
	 * @param int    $user_id Current user.
	 * @param string $token   Preview token.
	 * @return array<string, mixed>|WP_Error Parsed file plus `token`, or an error.
	 */
	public static function preview_for_token( $user_id, $token ) {
		$token    = self::clean_token( $token );
		$contents = self::recall( $user_id, $token );
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
	 * @return array{format: string, rows: array<int, array<int, string|null>>, header_at: int, headers: array<int, string|null>, index: array<string, int>}
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
				'headers'   => array(),
				'index'     => array(),
			);
		}

		$headers = $rows[ $header_at ];
		$index   = self::header_index( $headers );
		$format  = '';
		if ( self::is_template_header( $headers ) ) {
			$format = 'template';
		} elseif ( self::is_ebay_header( $index ) ) {
			$format = 'ebay';
		} elseif ( self::is_amazon_header( $index ) ) {
			$format = 'amazon';
		}

		return array(
			'format'    => $format,
			'rows'      => $rows,
			'header_at' => $header_at,
			'headers'   => $headers,
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
	 * @param array<int, string|null> $headers Header cells.
	 * @return bool
	 */
	private static function is_template_header( array $headers ) {
		$cells = array();
		foreach ( $headers as $header ) {
			$cells[] = null === $header ? '' : trim( (string) $header );
		}
		while ( $cells && '' === $cells[ count( $cells ) - 1 ] ) {
			array_pop( $cells );
		}
		return $cells === self::TEMPLATE_HEADERS;
	}

	/**
	 * @param array<string, int> $index Lowercased header → column.
	 * @return bool
	 */
	private static function is_ebay_header( array $index ) {
		if ( ! isset( $index['order number'] ) ) {
			return false;
		}
		foreach ( array( 'item number', 'custom label', 'sold for', 'variation details' ) as $name ) {
			if ( isset( $index[ $name ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, int> $index Lowercased header → column.
	 * @return bool
	 */
	private static function is_amazon_header( array $index ) {
		if ( ! isset( $index['order-id'] ) ) {
			return false;
		}
		foreach ( array( 'item-price', 'quantity-purchased', 'sku', 'purchase-date' ) as $name ) {
			if ( isset( $index[ $name ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array{format: string, rows: array<int, array<int, string|null>>, header_at: int, headers: array<int, string|null>, index: array<string, int>} $table Parsed table.
	 * @return array<string, mixed>
	 */
	private static function build_orders( array $table ) {
		$format    = (string) $table['format'];
		$rows      = $table['rows'];
		$headers   = $table['headers'];
		$index     = $table['index'];
		$header_at = (int) $table['header_at'];
		$errors    = array();
		$groups    = array();
		$count     = count( $rows );

		for ( $i = $header_at + 1; $i < $count; $i++ ) {
			$row = $rows[ $i ];
			if ( ! is_array( $row ) || self::row_is_empty( $row ) ) {
				continue;
			}

			$row_number   = $i + 1;
			$parsed       = self::parse_data_row( $format, $row, $index );
			$order_number = $parsed['order_number'];
			$channel      = $parsed['channel'];
			$channel_ok   = in_array( $channel, array( 'ebay', 'amazon' ), true );

			if ( 'template' === $format && ! $channel_ok ) {
				$errors[] = self::error_row( $row_number, $order_number, 'unknown_channel' );
			}
			if ( '' === $order_number ) {
				$errors[] = self::error_row( $row_number, '', 'missing_order_number' );
			}
			if ( null === $parsed['quantity'] ) {
				$errors[] = self::error_row( $row_number, $order_number, 'bad_quantity' );
			}
			if ( ! $channel_ok || '' === $order_number ) {
				continue;
			}

			$key = $channel . "\0" . $order_number;
			if ( ! isset( $groups[ $key ] ) ) {
				// Buyer, date, and ship-to come from the first row of the order.
				$groups[ $key ] = array(
					'channel'           => $channel,
					'external_order_id' => $order_number,
					'buyer_name'        => $parsed['buyer_name'],
					'order_date'        => $parsed['order_date'],
					'shipping_address'  => $parsed['shipping_address'],
					'items'             => array(),
					'rows'              => array(),
					'invalid'           => false,
				);
			}
			if ( null === $parsed['quantity'] ) {
				$groups[ $key ]['invalid'] = true;
				continue;
			}

			$groups[ $key ]['items'][] = $parsed['item'];
			$groups[ $key ]['rows'][]  = self::assoc_row( $headers, $row );
		}

		$orders     = array();
		$line_count = 0;
		$failed     = 0;
		foreach ( $groups as $group ) {
			// One bad line drops the whole order. Other orders in the file remain.
			if ( ! empty( $group['invalid'] ) || empty( $group['items'] ) ) {
				++$failed;
				continue;
			}
			$orders[]    = self::order_payload( $format, $group );
			$line_count += count( $group['items'] );
		}

		return array(
			'format'      => $format,
			'orders'      => $orders,
			'order_count' => count( $orders ),
			'line_count'  => $line_count,
			'failed'      => $failed,
			'errors'      => $errors,
		);
	}

	/**
	 * @param string                  $format ebay|amazon|template.
	 * @param array<int, string|null> $row    Cells.
	 * @param array<string, int>      $index  Header index.
	 * @return array<string, mixed>
	 */
	private static function parse_data_row( $format, array $row, array $index ) {
		if ( 'amazon' === $format ) {
			return self::parse_amazon_row( $row, $index );
		}
		if ( 'template' === $format ) {
			return self::parse_template_row( $row, $index );
		}
		return self::parse_ebay_row( $row, $index );
	}

	/**
	 * @param array<int, string|null> $row   Cells.
	 * @param array<string, int>      $index Header index.
	 * @return array<string, mixed>
	 */
	private static function parse_ebay_row( array $row, array $index ) {
		$buyer   = self::clean_text( self::cell( $row, $index, 'Buyer Name' ), 150 );
		$post_to = self::clean_text( self::cell( $row, $index, 'Post To Name' ), 150 );
		if ( '' === $buyer ) {
			$buyer = $post_to;
		}
		$full_name = '' !== $post_to ? $post_to : $buyer;
		$qty        = self::positive_int( self::cell( $row, $index, 'Quantity' ) );
		$listing    = self::clean_text( self::cell( $row, $index, 'Item Number' ), 100 );
		$sku        = self::clean_text( self::cell( $row, $index, 'Custom Label' ), 100 );
		$structured = self::variation_fields(
			self::cell( $row, $index, 'Variation Details' ),
			self::cell( $row, $index, 'Item Title' )
		);

		$item = array(
			'quantity'             => null === $qty ? 0 : $qty,
			'unit_price'           => self::parse_money( self::cell( $row, $index, 'Sold For' ) ),
			'house_number'         => self::optional_text( $structured['house_number'], 64 ),
			'street_name'          => self::optional_text( $structured['street_name'], 191 ),
			'style'                => self::optional_text( $structured['style'], 191 ),
			'personalisation_json' => $structured['personalisation_json'],
		);
		if ( '' !== $listing ) {
			$item['external_listing_id'] = $listing;
		}
		if ( '' !== $sku ) {
			$item['sku'] = $sku;
		}

		return array(
			'channel'          => 'ebay',
			'order_number'     => self::clean_text( self::cell( $row, $index, 'Order Number' ), 100 ),
			'order_date'       => self::normalize_order_date( self::cell( $row, $index, 'Sale Date' ) ),
			'buyer_name'       => $buyer,
			'shipping_address' => self::shipping_address(
				$full_name,
				self::cell( $row, $index, 'Post To Address 1' ),
				self::cell( $row, $index, 'Post To Address 2' ),
				self::cell( $row, $index, 'Post To City' ),
				self::cell( $row, $index, 'Post To State' ),
				self::cell( $row, $index, 'Post To Postal Code' ),
				self::cell( $row, $index, 'Post To Country' )
			),
			'quantity'         => $qty,
			'item'             => $item,
		);
	}

	/**
	 * @param array<int, string|null> $row   Cells.
	 * @param array<string, int>      $index Header index.
	 * @return array<string, mixed>
	 */
	private static function parse_amazon_row( array $row, array $index ) {
		$buyer     = self::clean_text( self::cell( $row, $index, 'buyer-name' ), 150 );
		$recipient = self::clean_text( self::cell( $row, $index, 'recipient-name' ), 150 );
		if ( '' === $buyer ) {
			$buyer = $recipient;
		}
		$full_name  = '' !== $recipient ? $recipient : $buyer;
		$qty        = self::positive_int( self::cell( $row, $index, 'quantity-purchased' ) );
		$line_total = self::parse_money( self::cell( $row, $index, 'item-price' ) );
		$unit       = null;
		if ( null !== $line_total && null !== $qty && $qty > 0 ) {
			$unit = round( $line_total / $qty, 2 );
		}
		$sku = self::clean_text( self::cell( $row, $index, 'sku' ), 100 );

		$item = array(
			'quantity'     => null === $qty ? 0 : $qty,
			'unit_price'   => $unit,
			'house_number' => null,
			'street_name'  => null,
			'style'        => self::optional_text( self::cell( $row, $index, 'product-name' ), 191 ),
		);
		if ( '' !== $sku ) {
			$item['sku'] = $sku;
		}

		return array(
			'channel'          => 'amazon',
			'order_number'     => self::clean_text( self::cell( $row, $index, 'order-id' ), 100 ),
			'order_date'       => self::normalize_order_date( self::cell( $row, $index, 'purchase-date' ) ),
			'buyer_name'       => $buyer,
			'shipping_address' => self::shipping_address(
				$full_name,
				self::cell( $row, $index, 'ship-address-1' ),
				self::cell( $row, $index, 'ship-address-2' ),
				self::cell( $row, $index, 'ship-city' ),
				self::cell( $row, $index, 'ship-state' ),
				self::cell( $row, $index, 'ship-postal-code' ),
				self::cell( $row, $index, 'ship-country' ),
				self::cell( $row, $index, 'ship-address-3' )
			),
			'quantity'         => $qty,
			'item'             => $item,
		);
	}

	/**
	 * @param array<int, string|null> $row   Cells.
	 * @param array<string, int>      $index Header index.
	 * @return array<string, mixed>
	 */
	private static function parse_template_row( array $row, array $index ) {
		$buyer   = self::clean_text( self::cell( $row, $index, 'buyer_name' ), 150 );
		$qty     = self::positive_int( self::cell( $row, $index, 'quantity' ) );
		$listing = self::clean_text( self::cell( $row, $index, 'item_number' ), 100 );
		$sku     = self::clean_text( self::cell( $row, $index, 'sku' ), 100 );

		$item = array(
			'quantity'     => null === $qty ? 0 : $qty,
			'unit_price'   => self::parse_money( self::cell( $row, $index, 'unit_price' ) ),
			'house_number' => self::optional_text( self::cell( $row, $index, 'house_number' ), 64 ),
			'street_name'  => self::optional_text( self::cell( $row, $index, 'street_name' ), 191 ),
			'style'        => self::optional_text( self::cell( $row, $index, 'style' ), 191 ),
		);
		if ( '' !== $listing ) {
			$item['external_listing_id'] = $listing;
		}
		if ( '' !== $sku ) {
			$item['sku'] = $sku;
		}

		return array(
			'channel'          => sanitize_key( self::cell( $row, $index, 'channel' ) ),
			'order_number'     => self::clean_text( self::cell( $row, $index, 'order_number' ), 100 ),
			'order_date'       => self::normalize_order_date( self::cell( $row, $index, 'order_date' ) ),
			'buyer_name'       => $buyer,
			'shipping_address' => self::shipping_address(
				$buyer,
				self::cell( $row, $index, 'address_1' ),
				self::cell( $row, $index, 'address_2' ),
				self::cell( $row, $index, 'city' ),
				self::cell( $row, $index, 'county' ),
				self::cell( $row, $index, 'postcode' ),
				self::cell( $row, $index, 'country' )
			),
			'quantity'         => $qty,
			'item'             => $item,
		);
	}

	/**
	 * Split Variation Details the same way eBay aspect names are matched.
	 *
	 * Item Title becomes style only when no design/style name is present.
	 *
	 * @param string $details    Variation Details cell.
	 * @param string $item_title Item Title cell.
	 * @return array{house_number: ?string, street_name: ?string, style: ?string, personalisation_json: ?string}
	 */
	private static function variation_fields( $details, $item_title ) {
		$aspects = array();
		$details = str_replace( array( "\r\n", "\r" ), "\n", (string) $details );
		$pieces  = preg_split( '/[;\n]+/', $details );
		if ( ! is_array( $pieces ) ) {
			$pieces = array();
		}
		foreach ( $pieces as $piece ) {
			$piece = trim( (string) $piece );
			if ( '' === $piece ) {
				continue;
			}
			if ( ! preg_match( '/^(.+?)(?::|=)(.*)$/', $piece, $matches ) ) {
				continue;
			}
			$name  = trim( $matches[1] );
			$value = trim( $matches[2] );
			if ( '' === $name || '' === $value ) {
				continue;
			}
			$aspects[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}

		$line  = array(
			'variationAspects' => $aspects,
		);
		$title = trim( (string) $item_title );
		if ( '' !== $title ) {
			$line['title'] = $title;
		}

		return SOM_Channel_Ebay::extract_structured_fields( $line );
	}

	/**
	 * @param string $format ebay|amazon|template.
	 * @param array<string, mixed> $group Group accumulator.
	 * @return array<string, mixed>
	 */
	private static function order_payload( $format, array $group ) {
		$payload = array(
			'channel'           => $group['channel'],
			'external_order_id' => $group['external_order_id'],
			'buyer_name'        => $group['buyer_name'],
			'shipping_address'  => $group['shipping_address'],
			'items'             => $group['items'],
			'raw_payload'       => array(
				'source' => 'order_import',
				'format' => $format,
				'rows'   => $group['rows'],
			),
		);
		if ( '' !== $group['order_date'] ) {
			$payload['order_date'] = $group['order_date'];
		}
		return $payload;
	}

	/**
	 * @param array<int, string|null> $headers Header cells.
	 * @param array<int, string|null> $row     Data cells.
	 * @return array<string, string>
	 */
	private static function assoc_row( array $headers, array $row ) {
		$assoc = array();
		foreach ( $headers as $i => $header ) {
			if ( null === $header ) {
				continue;
			}
			$name = trim( (string) $header );
			if ( '' === $name ) {
				continue;
			}
			$value = isset( $row[ $i ] ) && null !== $row[ $i ] ? trim( (string) $row[ $i ] ) : '';
			if ( '' === $value ) {
				continue;
			}
			$assoc[ $name ] = $value;
		}
		return $assoc;
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
	 * Ship-to address. Address 3 is kept on line 2 so the order page can show it.
	 *
	 * @param string $full_name Recipient.
	 * @param string $line1     Address line 1.
	 * @param string $line2     Address line 2.
	 * @param string $city      City.
	 * @param string $state     State or county.
	 * @param string $postcode  Postcode.
	 * @param string $country   Country.
	 * @param string $line3     Address line 3.
	 * @return array<string, string>
	 */
	private static function shipping_address( $full_name, $line1, $line2, $city, $state, $postcode, $country, $line3 = '' ) {
		$line2 = self::clean_text( $line2 );
		$line3 = self::clean_text( $line3 );
		if ( '' !== $line3 ) {
			$line2 = '' === $line2 ? $line3 : $line2 . ', ' . $line3;
		}

		return array(
			'full_name' => self::clean_text( $full_name, 150 ),
			'line1'     => self::clean_text( $line1 ),
			'line2'     => $line2,
			'city'      => self::clean_text( $city ),
			'state'     => self::clean_text( $state ),
			'postcode'  => self::clean_text( $postcode ),
			'country'   => self::clean_text( $country, 100 ),
		);
	}

	/**
	 * @param string $raw Date text from the file.
	 * @return string MySQL datetime, or empty when blank or unparseable.
	 */
	private static function normalize_order_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches ) ) {
			if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
				return '';
			}
			return $raw . ' 00:00:00';
		}
		$timestamp = strtotime( $raw );
		if ( false === $timestamp ) {
			return '';
		}
		// WordPress sets PHP's default timezone to UTC, so this keeps the file's clock time.
		return gmdate( 'Y-m-d H:i:s', $timestamp );
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
	 * @param string $raw Quantity text.
	 * @return int|null Positive integer, or null.
	 */
	private static function positive_int( $raw ) {
		$raw = trim( (string) $raw );
		if ( ! preg_match( '/^[1-9][0-9]*$/', $raw ) ) {
			return null;
		}
		$value = (int) $raw;
		if ( $value < 1 || (string) $value !== $raw ) {
			return null;
		}
		return $value;
	}

	/**
	 * @param mixed $value Raw value.
	 * @param int   $max   Max length. 0 keeps the full string.
	 * @return string
	 */
	private static function clean_text( $value, $max = 0 ) {
		$text = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		$text = trim( $text );
		if ( $max > 0 && strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max );
		}
		return $text;
	}

	/**
	 * @param mixed $value Raw value.
	 * @param int   $max   Max length.
	 * @return string|null
	 */
	private static function optional_text( $value, $max ) {
		if ( null === $value ) {
			return null;
		}
		$text = self::clean_text( $value, $max );
		return '' === $text ? null : $text;
	}

	/**
	 * @param int    $row          1-based record number.
	 * @param string $order_number Order number, possibly empty.
	 * @param string $code         Error code.
	 * @return array{row: int, order_number: string, code: string, message: string}
	 */
	private static function error_row( $row, $order_number, $code ) {
		$messages = array(
			'unrecognised_header'  => __( 'Unrecognised header.', 'order-machine' ),
			'missing_order_number' => __( 'Missing order number.', 'order-machine' ),
			'bad_quantity'         => __( 'Quantity must be a positive integer.', 'order-machine' ),
			'unknown_channel'      => __( 'Unknown channel.', 'order-machine' ),
		);

		return array(
			'row'          => (int) $row,
			'order_number' => (string) $order_number,
			'code'         => $code,
			'message'      => isset( $messages[ $code ] ) ? $messages[ $code ] : $code,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $errors Errors.
	 * @param int                              $failed Failed order count.
	 * @return array<string, mixed>
	 */
	private static function empty_result( array $errors, $failed = 0 ) {
		return array(
			'format'      => '',
			'orders'      => array(),
			'order_count' => 0,
			'line_count'  => 0,
			'failed'      => (int) $failed,
			'errors'      => $errors,
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
	 * @param string $token Preview token.
	 * @return string
	 */
	private static function clean_token( $token ) {
		$token = is_string( $token ) ? $token : '';
		$token = preg_replace( '/[^A-Za-z0-9]/', '', $token );
		return is_string( $token ) ? $token : '';
	}

	/**
	 * @param int    $user_id Current user.
	 * @param string $token   Preview token.
	 * @return string
	 */
	private static function memory_key( $user_id, $token ) {
		return 'som_oimp_' . (int) $user_id . '_' . $token;
	}
}
