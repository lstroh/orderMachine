<?php
/**
 * Shipping package catalogue CRUD.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for `wp_som_shipping_packages`.
 */
class SOM_Shipping_Packages {

	const PER_PAGE = 20;

	/**
	 * @param array<string, mixed> $args Filters: s, status (all|active|inactive), paged, per_page.
	 * @return array{packages: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				's'        => '',
				'status'   => 'all',
				'paged'    => 1,
				'per_page' => self::PER_PAGE,
			)
		);

		$table  = SOM_DB::table( 'shipping_packages' );
		$where  = array( '1=1' );
		$params = array();

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = 'name LIKE %s';
			$params[] = $like;
		}

		$status = sanitize_key( (string) $args['status'] );
		if ( 'active' === $status ) {
			$where[] = 'is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where[] = 'is_active = 0';
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
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

		$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY is_default DESC, name ASC, id ASC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'packages' => is_array( $rows ) ? $rows : array(),
			'total'    => $total,
			'pages'    => $pages,
			'paged'    => $paged,
		);
	}

	/**
	 * Active packages for product select (default first).
	 *
	 * @return array<int, object>
	 */
	public static function list_active() {
		global $wpdb;
		$table = SOM_DB::table( 'shipping_packages' );
		$rows  = $wpdb->get_results(
			"SELECT id, name, length_mm, width_mm, height_mm, tare_weight_grams, is_default
			FROM {$table}
			WHERE is_active = 1
			ORDER BY is_default DESC, name ASC, id ASC"
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return object|null
	 */
	public static function get_default() {
		global $wpdb;
		$table = SOM_DB::table( 'shipping_packages' );
		$row   = $wpdb->get_row(
			"SELECT * FROM {$table} WHERE is_default = 1 AND is_active = 1 ORDER BY id ASC LIMIT 1"
		);
		return $row ? $row : null;
	}

	/**
	 * @param int $id Package PK.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = SOM_DB::table( 'shipping_packages' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", (int) $id )
		);
		return $row ? $row : null;
	}

	/**
	 * @param array<string, mixed> $data Fields.
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;

		$parsed = self::parse_fields( $data, true );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			SOM_DB::table( 'shipping_packages' ),
			array(
				'name'               => $parsed['name'],
				'length_mm'          => $parsed['length_mm'],
				'width_mm'           => $parsed['width_mm'],
				'height_mm'          => $parsed['height_mm'],
				'tare_weight_grams'  => $parsed['tare_weight_grams'],
				'is_active'          => $parsed['is_active'],
				'is_default'         => $parsed['is_default'],
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( ! $ok ) {
			if ( self::is_duplicate_name_error() ) {
				return new WP_Error( 'som_package_name_dup', __( 'A package with that name already exists.', 'order-machine' ) );
			}
			return new WP_Error( 'som_package_create', __( 'Could not create package.', 'order-machine' ) );
		}

		$id = (int) $wpdb->insert_id;
		if ( $parsed['is_default'] ) {
			self::clear_other_defaults( $id );
		}

		return $id;
	}

	/**
	 * @param int                  $id   Package PK.
	 * @param array<string, mixed> $data Fields.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id < 1 || ! self::get( $id ) ) {
			return new WP_Error( 'som_package_missing', __( 'Package not found.', 'order-machine' ) );
		}

		$parsed = self::parse_fields( $data, false );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$fields  = array( 'updated_at' => current_time( 'mysql', true ) );
		$formats = array( '%s' );

		foreach ( array( 'name', 'length_mm', 'width_mm', 'height_mm', 'tare_weight_grams', 'is_active', 'is_default' ) as $key ) {
			if ( ! array_key_exists( $key, $parsed ) ) {
				continue;
			}
			$fields[ $key ] = $parsed[ $key ];
			if ( in_array( $key, array( 'length_mm', 'width_mm', 'height_mm', 'is_active', 'is_default' ), true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'shipping_packages' ),
			$fields,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		if ( false === $ok ) {
			if ( self::is_duplicate_name_error() ) {
				return new WP_Error( 'som_package_name_dup', __( 'A package with that name already exists.', 'order-machine' ) );
			}
			return new WP_Error( 'som_package_update', __( 'Could not update package.', 'order-machine' ) );
		}

		if ( ! empty( $parsed['is_default'] ) ) {
			self::clear_other_defaults( $id );
		}

		return true;
	}

	/**
	 * Hard-delete only when no products reference the package.
	 *
	 * @param int $id Package PK.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id < 1 || ! self::get( $id ) ) {
			return new WP_Error( 'som_package_missing', __( 'Package not found.', 'order-machine' ) );
		}

		$refs = self::count_product_refs( $id );
		if ( $refs > 0 ) {
			return new WP_Error(
				'som_package_in_use',
				sprintf(
					/* translators: %d: product count */
					_n(
						'Cannot delete: %d product uses this package. Deactivate it instead.',
						'Cannot delete: %d products use this package. Deactivate it instead.',
						$refs,
						'order-machine'
					),
					$refs
				)
			);
		}

		$ok = $wpdb->delete( SOM_DB::table( 'shipping_packages' ), array( 'id' => $id ), array( '%d' ) );
		if ( ! $ok ) {
			return new WP_Error( 'som_package_delete', __( 'Could not delete package.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * @param int $package_id Package PK.
	 * @return int
	 */
	public static function count_product_refs( $package_id ) {
		global $wpdb;
		$products = SOM_DB::table( 'products' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$products} WHERE package_id = %d",
				(int) $package_id
			)
		);
	}

	/**
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'som-shipping-packages' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * @param int|string $id Package PK or "new".
	 * @return string
	 */
	public static function detail_url( $id ) {
		return self::list_url( array( 'package_id' => $id ) );
	}

	/**
	 * @param array<string, mixed> $data   Raw fields.
	 * @param bool                 $create Require all core fields.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function parse_fields( array $data, $create ) {
		$out = array();

		if ( $create || array_key_exists( 'name', $data ) ) {
			$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
			if ( '' === $name ) {
				return new WP_Error( 'som_package_name', __( 'Package name is required.', 'order-machine' ) );
			}
			$out['name'] = $name;
		}

		foreach ( array( 'length_mm', 'width_mm', 'height_mm' ) as $dim ) {
			if ( ! $create && ! array_key_exists( $dim, $data ) ) {
				continue;
			}
			$raw = isset( $data[ $dim ] ) ? trim( (string) $data[ $dim ] ) : '';
			if ( '' === $raw || ! is_numeric( $raw ) ) {
				return new WP_Error(
					'som_package_dim',
					__( 'Length, width, and height must be whole millimetres (height may be 0).', 'order-machine' )
				);
			}
			$val = (int) $raw;
			if ( $val < 0 || ( 'height_mm' !== $dim && $val < 1 ) ) {
				return new WP_Error(
					'som_package_dim',
					__( 'Length and width must be at least 1 mm; height may be 0 for flat envelopes.', 'order-machine' )
				);
			}
			$out[ $dim ] = $val;
		}

		if ( $create || array_key_exists( 'tare_weight_grams', $data ) ) {
			$raw = isset( $data['tare_weight_grams'] ) ? trim( (string) $data['tare_weight_grams'] ) : '0';
			if ( '' === $raw ) {
				$raw = '0';
			}
			if ( ! is_numeric( $raw ) || (float) $raw < 0 ) {
				return new WP_Error( 'som_package_tare', __( 'Tare weight must be zero or a positive number of grams.', 'order-machine' ) );
			}
			$out['tare_weight_grams'] = number_format( (float) $raw, 2, '.', '' );
		}

		if ( $create || array_key_exists( 'is_active', $data ) ) {
			$out['is_active'] = ! empty( $data['is_active'] ) ? 1 : 0;
		}

		if ( $create || array_key_exists( 'is_default', $data ) ) {
			$out['is_default'] = ! empty( $data['is_default'] ) ? 1 : 0;
		}

		if ( $create ) {
			if ( ! isset( $out['is_active'] ) ) {
				$out['is_active'] = 1;
			}
			if ( ! isset( $out['is_default'] ) ) {
				$out['is_default'] = 0;
			}
			if ( ! isset( $out['height_mm'] ) ) {
				$out['height_mm'] = 0;
			}
		}

		return $out;
	}

	/**
	 * Ensure only one default package.
	 *
	 * @param int $keep_id Package PK to keep as default.
	 * @return void
	 */
	private static function clear_other_defaults( $keep_id ) {
		global $wpdb;
		$table = SOM_DB::table( 'shipping_packages' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_default = 0 WHERE id <> %d AND is_default = 1",
				(int) $keep_id
			)
		);
	}

	/**
	 * @return bool
	 */
	private static function is_duplicate_name_error() {
		global $wpdb;
		return is_string( $wpdb->last_error ) && false !== stripos( $wpdb->last_error, 'Duplicate' );
	}
}
