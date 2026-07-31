<?php
/**
 * Supplier catalogue CRUD.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for `wp_som_suppliers`.
 */
class SOM_Suppliers {

	const PER_PAGE = 20;

	/**
	 * @param array<string, mixed> $args Filters: s, paged, per_page.
	 * @return array{suppliers: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				's'        => '',
				'paged'    => 1,
				'per_page' => self::PER_PAGE,
			)
		);

		$table  = SOM_DB::table( 'suppliers' );
		$where  = array( '1=1' );
		$params = array();

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( name LIKE %s OR website LIKE %s OR contact_info LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
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

		$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY name ASC, id ASC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'suppliers' => is_array( $rows ) ? $rows : array(),
			'total'     => $total,
			'pages'     => $pages,
			'paged'     => $paged,
		);
	}

	/**
	 * @return array<int, object>
	 */
	public static function list_all() {
		global $wpdb;
		$table = SOM_DB::table( 'suppliers' );
		$rows  = $wpdb->get_results( "SELECT id, name FROM {$table} ORDER BY name ASC, id ASC" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int $id Supplier PK.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = SOM_DB::table( 'suppliers' );
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

		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'som_supplier_name', __( 'Supplier name is required.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			SOM_DB::table( 'suppliers' ),
			array(
				'name'         => $name,
				'website'      => self::nullable_text( $data, 'website', true ),
				'contact_info' => self::nullable_text( $data, 'contact_info' ),
				'notes'        => self::nullable_text( $data, 'notes' ),
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return new WP_Error( 'som_supplier_create', __( 'Could not create supplier.', 'order-machine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $id   Supplier PK.
	 * @param array<string, mixed> $data Fields.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id < 1 || ! self::get( $id ) ) {
			return new WP_Error( 'som_supplier_missing', __( 'Supplier not found.', 'order-machine' ) );
		}

		$fields  = array( 'updated_at' => current_time( 'mysql', true ) );
		$formats = array( '%s' );

		if ( array_key_exists( 'name', $data ) ) {
			$name = sanitize_text_field( (string) $data['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'som_supplier_name', __( 'Supplier name is required.', 'order-machine' ) );
			}
			$fields['name'] = $name;
			$formats[]      = '%s';
		}
		if ( array_key_exists( 'website', $data ) ) {
			$fields['website'] = self::nullable_text( $data, 'website', true );
			$formats[]         = '%s';
		}
		if ( array_key_exists( 'contact_info', $data ) ) {
			$fields['contact_info'] = self::nullable_text( $data, 'contact_info' );
			$formats[]              = '%s';
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$fields['notes'] = self::nullable_text( $data, 'notes' );
			$formats[]       = '%s';
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'suppliers' ),
			$fields,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_supplier_update', __( 'Could not update supplier.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'som-suppliers' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * @param int $id Supplier PK.
	 * @return string
	 */
	public static function detail_url( $id ) {
		return self::list_url( array( 'supplier_id' => (int) $id ) );
	}

	/**
	 * @param array<string, mixed> $data   Source.
	 * @param string               $key    Key.
	 * @param bool                 $url    Whether to sanitize as URL.
	 * @return string|null
	 */
	private static function nullable_text( array $data, $key, $url = false ) {
		if ( ! array_key_exists( $key, $data ) ) {
			return null;
		}
		$raw = trim( (string) $data[ $key ] );
		if ( '' === $raw ) {
			return null;
		}
		return $url ? esc_url_raw( $raw ) : sanitize_textarea_field( $raw );
	}
}
