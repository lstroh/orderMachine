<?php
/**
 * Marketplace listings — cache, refresh, and push price/qty/description.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD + channel sync for `wp_som_listings`.
 *
 * Inventory shape in `inventory_json`:
 * {
 *   "mode": "flat"|"variations",
 *   "sku": "optional primary SKU (eBay flat)",
 *   "variations": [
 *     { "sku": "...", "quantity": 5, "options": { "Colour": "Red" }, "price": null }
 *   ]
 * }
 */
class SOM_Listings {

	/**
	 * Admin list URL.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'som-listings' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Admin detail/edit URL.
	 *
	 * @param int|string $listing_id Listing PK or "new".
	 * @return string
	 */
	public static function detail_url( $listing_id ) {
		return self::list_url( array( 'listing_id' => $listing_id ) );
	}

	/**
	 * Paginated listing query.
	 *
	 * @param array<string, mixed> $args channel, s, paged, per_page.
	 * @return array{listings: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$channel   = isset( $args['channel'] ) ? sanitize_key( (string) $args['channel'] ) : 'all';
		$search    = isset( $args['s'] ) ? trim( (string) $args['s'] ) : '';
		$paged     = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$per_page  = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$offset    = ( $paged - 1 ) * $per_page;

		$listings_t = SOM_DB::table( 'listings' );
		$channels_t = SOM_DB::table( 'channels' );
		$products_t = SOM_DB::table( 'products' );

		$where  = array( '1=1' );
		$params = array();

		if ( in_array( $channel, array( 'ebay', 'etsy' ), true ) ) {
			$where[]  = 'c.slug = %s';
			$params[] = $channel;
		}

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( l.external_listing_id LIKE %s OR l.title LIKE %s OR p.name LIKE %s OR p.sku LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$listings_t} l
			INNER JOIN {$channels_t} c ON c.id = l.channel_id
			INNER JOIN {$products_t} p ON p.id = l.product_id
			WHERE {$where_sql}";

		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$pages = max( 1, (int) ceil( $total / $per_page ) );
		if ( $paged > $pages ) {
			$paged  = $pages;
			$offset = ( $paged - 1 ) * $per_page;
		}

		$list_sql = "SELECT l.*, c.slug AS channel_slug, c.display_name AS channel_name,
				p.name AS product_name, p.sku AS product_sku
			FROM {$listings_t} l
			INNER JOIN {$channels_t} c ON c.id = l.channel_id
			INNER JOIN {$products_t} p ON p.id = l.product_id
			WHERE {$where_sql}
			ORDER BY c.slug ASC, l.external_listing_id ASC
			LIMIT %d OFFSET %d";

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		foreach ( $rows as $row ) {
			$row->inventory = self::decode_inventory( $row->inventory_json ?? null );
		}

		return array(
			'listings' => $rows,
			'total'    => $total,
			'pages'    => $pages,
			'paged'    => $paged,
		);
	}

	/**
	 * Fetch one listing with channel + product labels.
	 *
	 * @param int $listing_id Listing PK.
	 * @return object|null
	 */
	public static function get( $listing_id ) {
		global $wpdb;

		$listings_t = SOM_DB::table( 'listings' );
		$channels_t = SOM_DB::table( 'channels' );
		$products_t = SOM_DB::table( 'products' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT l.*, c.slug AS channel_slug, c.display_name AS channel_name,
					p.name AS product_name, p.sku AS product_sku
				FROM {$listings_t} l
				INNER JOIN {$channels_t} c ON c.id = l.channel_id
				INNER JOIN {$products_t} p ON p.id = l.product_id
				WHERE l.id = %d
				LIMIT 1",
				(int) $listing_id
			)
		);

		if ( ! $row ) {
			return null;
		}

		$row->inventory = self::decode_inventory( $row->inventory_json ?? null );
		return $row;
	}

	/**
	 * Create a manual listing ↔ product map row.
	 *
	 * @param array<string, mixed> $data product_id, channel_slug, external_listing_id, title, price, quantity_available, description, inventory.
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;

		$product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
		$slug       = isset( $data['channel_slug'] ) ? sanitize_key( (string) $data['channel_slug'] ) : '';
		$external   = isset( $data['external_listing_id'] ) ? sanitize_text_field( (string) $data['external_listing_id'] ) : '';

		if ( $product_id < 1 ) {
			return new WP_Error( 'som_listing_product', __( 'Product is required.', 'order-machine' ) );
		}
		if ( ! in_array( $slug, array( 'ebay', 'etsy' ), true ) ) {
			return new WP_Error( 'som_listing_channel', __( 'Channel is required.', 'order-machine' ) );
		}
		if ( '' === $external ) {
			return new WP_Error( 'som_listing_external', __( 'External listing ID is required.', 'order-machine' ) );
		}

		$channel = SOM_Channels::get_by_slug( $slug );
		if ( ! $channel ) {
			return new WP_Error( 'som_listing_channel', __( 'Channel not found.', 'order-machine' ) );
		}

		$inventory = self::sanitize_inventory( isset( $data['inventory'] ) ? $data['inventory'] : null );
		$qty       = self::quantity_from_inventory( $inventory, isset( $data['quantity_available'] ) ? (int) $data['quantity_available'] : 0 );
		$now       = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			SOM_DB::table( 'listings' ),
			array(
				'product_id'          => $product_id,
				'channel_id'          => (int) $channel->id,
				'external_listing_id' => $external,
				'title'               => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : null,
				'description'         => isset( $data['description'] ) ? wp_kses_post( (string) $data['description'] ) : null,
				'price'               => isset( $data['price'] ) ? (float) $data['price'] : 0.0,
				'quantity_available'  => $qty,
				'inventory_json'      => wp_json_encode( $inventory ),
				'last_synced_at'      => null,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'som_listing_create', __( 'Could not create listing (duplicate external ID?).', 'order-machine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update local cache fields (no channel push).
	 *
	 * @param int                  $listing_id Listing PK.
	 * @param array<string, mixed> $data       title, description, price, quantity_available, inventory, product_id.
	 * @return true|WP_Error
	 */
	public static function update_local( $listing_id, array $data ) {
		global $wpdb;

		$listing = self::get( $listing_id );
		if ( ! $listing ) {
			return new WP_Error( 'som_listing_missing', __( 'Listing not found.', 'order-machine' ) );
		}

		$fields  = array( 'updated_at' => current_time( 'mysql', true ) );
		$formats = array( '%s' );

		if ( array_key_exists( 'product_id', $data ) ) {
			$pid = (int) $data['product_id'];
			if ( $pid < 1 ) {
				return new WP_Error( 'som_listing_product', __( 'Product is required.', 'order-machine' ) );
			}
			$fields['product_id'] = $pid;
			$formats[]            = '%d';
		}

		if ( array_key_exists( 'title', $data ) ) {
			$title            = sanitize_text_field( (string) $data['title'] );
			$fields['title']  = '' === $title ? null : $title;
			$formats[]        = '%s';
		}

		if ( array_key_exists( 'description', $data ) ) {
			$fields['description'] = wp_kses_post( (string) $data['description'] );
			$formats[]             = '%s';
		}

		if ( array_key_exists( 'price', $data ) ) {
			$fields['price'] = (float) $data['price'];
			$formats[]       = '%f';
		}

		$inventory = null;
		if ( array_key_exists( 'inventory', $data ) ) {
			$inventory                 = self::sanitize_inventory( $data['inventory'] );
			$fields['inventory_json']  = wp_json_encode( $inventory );
			$formats[]                 = '%s';
			$fields['quantity_available'] = self::quantity_from_inventory(
				$inventory,
				isset( $data['quantity_available'] ) ? (int) $data['quantity_available'] : (int) $listing->quantity_available
			);
			$formats[] = '%d';
		} elseif ( array_key_exists( 'quantity_available', $data ) ) {
			$fields['quantity_available'] = max( 0, (int) $data['quantity_available'] );
			$formats[]                    = '%d';
			// Keep flat inventory qty in sync when mode is flat.
			$current = self::decode_inventory( $listing->inventory_json ?? null );
			if ( 'flat' === $current['mode'] ) {
				$current['sku']               = isset( $current['sku'] ) ? $current['sku'] : '';
				$fields['inventory_json']     = wp_json_encode( $current );
				$formats[]                    = '%s';
			}
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'listings' ),
			$fields,
			array( 'id' => (int) $listing_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_listing_update', __( 'Could not update listing.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Pull listing state from the channel into the local cache.
	 *
	 * @param int $listing_id Listing PK.
	 * @return true|WP_Error
	 */
	public static function refresh( $listing_id ) {
		$listing = self::get( $listing_id );
		if ( ! $listing ) {
			return new WP_Error( 'som_listing_missing', __( 'Listing not found.', 'order-machine' ) );
		}

		$remote = self::channel_fetch( $listing );
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}

		return self::apply_remote( $listing_id, $remote );
	}

	/**
	 * Push local price / description / inventory to the channel.
	 *
	 * @param int $listing_id Listing PK.
	 * @return true|WP_Error
	 */
	public static function push( $listing_id ) {
		$listing = self::get( $listing_id );
		if ( ! $listing ) {
			return new WP_Error( 'som_listing_missing', __( 'Listing not found.', 'order-machine' ) );
		}

		$payload = self::listing_to_payload( $listing );
		$result  = self::channel_push( $listing, $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		global $wpdb;
		$wpdb->update(
			SOM_DB::table( 'listings' ),
			array(
				'last_synced_at' => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $listing_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Decode inventory_json to a normalized array.
	 *
	 * @param string|null $json Raw JSON.
	 * @return array{mode: string, sku: string, variations: array<int, array<string, mixed>>}
	 */
	public static function decode_inventory( $json ) {
		$empty = array(
			'mode'       => 'flat',
			'sku'        => '',
			'variations' => array(),
		);

		if ( null === $json || '' === $json ) {
			return $empty;
		}

		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			return $empty;
		}

		return self::sanitize_inventory( $data );
	}

	/**
	 * @param mixed $raw Incoming inventory.
	 * @return array{mode: string, sku: string, variations: array<int, array<string, mixed>>}
	 */
	public static function sanitize_inventory( $raw ) {
		$out = array(
			'mode'       => 'flat',
			'sku'        => '',
			'variations' => array(),
		);

		if ( ! is_array( $raw ) ) {
			return $out;
		}

		$mode = isset( $raw['mode'] ) ? sanitize_key( (string) $raw['mode'] ) : 'flat';
		if ( ! in_array( $mode, array( 'flat', 'variations' ), true ) ) {
			$mode = 'flat';
		}
		$out['mode'] = $mode;
		$out['sku']  = isset( $raw['sku'] ) ? sanitize_text_field( (string) $raw['sku'] ) : '';

		$variations = isset( $raw['variations'] ) && is_array( $raw['variations'] ) ? $raw['variations'] : array();
		foreach ( $variations as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$options = array();
			if ( ! empty( $row['options'] ) && is_array( $row['options'] ) ) {
				foreach ( $row['options'] as $k => $v ) {
					$options[ sanitize_text_field( (string) $k ) ] = sanitize_text_field( (string) $v );
				}
			}
			$var = array(
				'sku'      => isset( $row['sku'] ) ? sanitize_text_field( (string) $row['sku'] ) : '',
				'quantity' => isset( $row['quantity'] ) ? max( 0, (int) $row['quantity'] ) : 0,
				'options'  => $options,
			);
			if ( isset( $row['price'] ) && '' !== $row['price'] && null !== $row['price'] ) {
				$var['price'] = (float) $row['price'];
			}
			if ( ! empty( $row['external_id'] ) ) {
				$var['external_id'] = sanitize_text_field( (string) $row['external_id'] );
			}
			$out['variations'][] = $var;
		}

		if ( ! empty( $out['variations'] ) ) {
			$out['mode'] = 'variations';
		}

		return $out;
	}

	/**
	 * Build inventory from POST variation fields.
	 *
	 * @param string               $mode        flat|variations.
	 * @param string               $sku         Flat / primary SKU.
	 * @param array<string, mixed> $post_vars   som_var_sku, som_var_qty, som_var_options.
	 * @return array{mode: string, sku: string, variations: array<int, array<string, mixed>>}
	 */
	public static function inventory_from_post( $mode, $sku, array $post_vars ) {
		$mode = sanitize_key( (string) $mode );
		if ( 'variations' !== $mode ) {
			return self::sanitize_inventory(
				array(
					'mode'       => 'flat',
					'sku'        => $sku,
					'variations' => array(),
				)
			);
		}

		$skus     = isset( $post_vars['som_var_sku'] ) && is_array( $post_vars['som_var_sku'] ) ? $post_vars['som_var_sku'] : array();
		$qtys     = isset( $post_vars['som_var_qty'] ) && is_array( $post_vars['som_var_qty'] ) ? $post_vars['som_var_qty'] : array();
		$opts     = isset( $post_vars['som_var_options'] ) && is_array( $post_vars['som_var_options'] ) ? $post_vars['som_var_options'] : array();
		$rows     = array();

		foreach ( $skus as $i => $var_sku ) {
			$option_str = isset( $opts[ $i ] ) ? (string) $opts[ $i ] : '';
			$options    = self::parse_options_string( $option_str );
			$rows[]     = array(
				'sku'      => (string) $var_sku,
				'quantity' => isset( $qtys[ $i ] ) ? (int) $qtys[ $i ] : 0,
				'options'  => $options,
			);
		}

		return self::sanitize_inventory(
			array(
				'mode'       => 'variations',
				'sku'        => $sku,
				'variations' => $rows,
			)
		);
	}

	/**
	 * "Colour=Red; Size=Large" → associative options.
	 *
	 * @param string $raw Options string.
	 * @return array<string, string>
	 */
	public static function parse_options_string( $raw ) {
		$out  = array();
		$parts = preg_split( '/\s*;\s*/', trim( (string) $raw ) );
		if ( ! is_array( $parts ) ) {
			return $out;
		}
		foreach ( $parts as $part ) {
			if ( '' === $part || false === strpos( $part, '=' ) ) {
				continue;
			}
			list( $k, $v ) = array_map( 'trim', explode( '=', $part, 2 ) );
			if ( '' !== $k ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Options → "Colour=Red; Size=Large".
	 *
	 * @param array<string, string> $options Options map.
	 * @return string
	 */
	public static function format_options_string( array $options ) {
		$parts = array();
		foreach ( $options as $k => $v ) {
			$parts[] = $k . '=' . $v;
		}
		return implode( '; ', $parts );
	}

	/**
	 * @param array{mode: string, sku: string, variations: array} $inventory Inventory.
	 * @param int                                                   $fallback Flat qty.
	 * @return int
	 */
	public static function quantity_from_inventory( array $inventory, $fallback = 0 ) {
		if ( 'variations' === $inventory['mode'] && ! empty( $inventory['variations'] ) ) {
			$sum = 0;
			foreach ( $inventory['variations'] as $row ) {
				$sum += isset( $row['quantity'] ) ? (int) $row['quantity'] : 0;
			}
			return $sum;
		}
		return max( 0, (int) $fallback );
	}

	/**
	 * Active products for the create/edit dropdown.
	 *
	 * @return array<int, object>
	 */
	public static function product_options() {
		global $wpdb;
		$table = SOM_DB::table( 'products' );
		$rows  = $wpdb->get_results(
			"SELECT id, name, sku FROM {$table} WHERE is_active = 1 ORDER BY name ASC"
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param object $listing Listing row.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function channel_fetch( $listing ) {
		$slug = (string) $listing->channel_slug;
		$hint = array(
			'external_listing_id' => (string) $listing->external_listing_id,
			'inventory'           => self::decode_inventory( $listing->inventory_json ?? null ),
			'product_sku'         => isset( $listing->product_sku ) ? (string) $listing->product_sku : '',
		);

		if ( 'ebay' === $slug ) {
			return SOM_Channel_Ebay::fetch_listing( $hint );
		}
		if ( 'etsy' === $slug ) {
			return SOM_Channel_Etsy::fetch_listing( $hint );
		}

		return new WP_Error( 'som_listing_channel', __( 'Unknown channel.', 'order-machine' ) );
	}

	/**
	 * @param object               $listing Listing row.
	 * @param array<string, mixed> $payload Normalized payload.
	 * @return true|WP_Error
	 */
	private static function channel_push( $listing, array $payload ) {
		$slug = (string) $listing->channel_slug;

		if ( 'ebay' === $slug ) {
			return SOM_Channel_Ebay::push_listing( $payload );
		}
		if ( 'etsy' === $slug ) {
			return SOM_Channel_Etsy::push_listing( $payload );
		}

		return new WP_Error( 'som_listing_channel', __( 'Unknown channel.', 'order-machine' ) );
	}

	/**
	 * @param object $listing Listing with inventory decoded.
	 * @return array<string, mixed>
	 */
	private static function listing_to_payload( $listing ) {
		$inventory = isset( $listing->inventory ) && is_array( $listing->inventory )
			? $listing->inventory
			: self::decode_inventory( $listing->inventory_json ?? null );

		return array(
			'external_listing_id' => (string) $listing->external_listing_id,
			'title'               => isset( $listing->title ) ? (string) $listing->title : '',
			'description'         => isset( $listing->description ) ? (string) $listing->description : '',
			'price'               => (float) $listing->price,
			'quantity_available'  => (int) $listing->quantity_available,
			'inventory'           => $inventory,
			'product_sku'         => isset( $listing->product_sku ) ? (string) $listing->product_sku : '',
		);
	}

	/**
	 * @param int                  $listing_id Listing PK.
	 * @param array<string, mixed> $remote     Normalized remote data.
	 * @return true|WP_Error
	 */
	private static function apply_remote( $listing_id, array $remote ) {
		global $wpdb;

		$inventory = self::sanitize_inventory( isset( $remote['inventory'] ) ? $remote['inventory'] : null );
		$qty       = isset( $remote['quantity_available'] )
			? (int) $remote['quantity_available']
			: self::quantity_from_inventory( $inventory, 0 );

		$fields = array(
			'price'              => isset( $remote['price'] ) ? (float) $remote['price'] : 0.0,
			'quantity_available' => $qty,
			'inventory_json'     => wp_json_encode( $inventory ),
			'last_synced_at'     => current_time( 'mysql', true ),
			'updated_at'         => current_time( 'mysql', true ),
		);
		$formats = array( '%f', '%d', '%s', '%s', '%s' );

		if ( array_key_exists( 'title', $remote ) ) {
			$title           = sanitize_text_field( (string) $remote['title'] );
			$fields['title'] = '' === $title ? null : $title;
			$formats[]       = '%s';
		}
		if ( array_key_exists( 'description', $remote ) ) {
			$fields['description'] = wp_kses_post( (string) $remote['description'] );
			$formats[]             = '%s';
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'listings' ),
			$fields,
			array( 'id' => (int) $listing_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_listing_refresh', __( 'Could not save refreshed listing.', 'order-machine' ) );
		}

		return true;
	}
}
