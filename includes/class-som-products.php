<?php
/**
 * Product catalogue and recipe helpers (Sprint 5).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD, recipes, and listing lookups for products.
 */
class SOM_Products {

	/**
	 * Products per page on the admin list.
	 */
	const PER_PAGE = 20;

	/**
	 * Query products for the admin list.
	 *
	 * @param array<string, mixed> $args Filters: status, s, paged, per_page.
	 * @return array{products: array<int, object>, total: int, pages: int, paged: int}
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

		$products_t  = SOM_DB::table( 'products' );
		$templates_t = SOM_DB::table( 'workflow_templates' );
		$recipe_t    = SOM_DB::table( 'product_materials' );
		$listings_t  = SOM_DB::table( 'listings' );

		$where  = array( '1=1' );
		$params = array();

		$status = sanitize_key( (string) $args['status'] );
		if ( 'active' === $status ) {
			$where[] = 'p.is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where[] = 'p.is_active = 0';
		}

		$search = trim( (string) $args['s'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( p.name LIKE %s OR p.sku LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$products_t} p WHERE {$where_sql}";
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
				p.*,
				wt.name AS workflow_name,
				( SELECT COUNT(*) FROM {$recipe_t} pm WHERE pm.product_id = p.id ) AS recipe_count,
				( SELECT COUNT(*) FROM {$listings_t} l WHERE l.product_id = p.id ) AS listing_count
			FROM {$products_t} p
			LEFT JOIN {$templates_t} wt ON wt.id = p.workflow_template_id
			WHERE {$where_sql}
			ORDER BY p.is_active DESC, p.name ASC, p.id ASC
			LIMIT %d OFFSET %d";

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$products    = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );
		if ( ! is_array( $products ) ) {
			$products = array();
		}

		return array(
			'products' => $products,
			'total'    => $total,
			'pages'    => $pages,
			'paged'    => $paged,
		);
	}

	/**
	 * Fetch one product with recipe rows and linked listings.
	 *
	 * @param int $product_id Product PK.
	 * @return object|null
	 */
	public static function get( $product_id ) {
		global $wpdb;

		$product_id  = (int) $product_id;
		$products_t  = SOM_DB::table( 'products' );
		$templates_t = SOM_DB::table( 'workflow_templates' );

		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.*, wt.name AS workflow_name
				FROM {$products_t} p
				LEFT JOIN {$templates_t} wt ON wt.id = p.workflow_template_id
				WHERE p.id = %d
				LIMIT 1",
				$product_id
			)
		);

		if ( ! $product ) {
			return null;
		}

		$product->recipe   = self::get_recipe( $product_id );
		$product->listings = self::get_listings( $product_id );

		return $product;
	}

	/**
	 * Recipe rows for a product.
	 *
	 * @param int $product_id Product PK.
	 * @return array<int, object>
	 */
	public static function get_recipe( $product_id ) {
		global $wpdb;

		$recipe_t    = SOM_DB::table( 'product_materials' );
		$materials_t = SOM_DB::table( 'materials' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.*, m.name AS material_name, m.unit AS material_unit, m.is_active AS material_is_active
				FROM {$recipe_t} pm
				INNER JOIN {$materials_t} m ON m.id = pm.material_id
				WHERE pm.product_id = %d
				ORDER BY pm.id ASC",
				(int) $product_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Linked marketplace listings for a product.
	 *
	 * @param int $product_id Product PK.
	 * @return array<int, object>
	 */
	public static function get_listings( $product_id ) {
		global $wpdb;

		$listings_t = SOM_DB::table( 'listings' );
		$channels_t = SOM_DB::table( 'channels' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, c.slug AS channel_slug, c.display_name AS channel_name
				FROM {$listings_t} l
				INNER JOIN {$channels_t} c ON c.id = l.channel_id
				WHERE l.product_id = %d
				ORDER BY c.slug ASC, l.external_listing_id ASC",
				(int) $product_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Workflow templates for assignment dropdown (active + current assignment).
	 *
	 * @param int $include_id Also include this template even if inactive.
	 * @return array<int, object>
	 */
	public static function list_workflow_templates( $include_id = 0 ) {
		return SOM_Workflows::list_for_dropdown( $include_id );
	}

	/**
	 * Create a product.
	 *
	 * @param array<string, mixed> $data Fields: name, sku, workflow_template_id, is_active, is_internal.
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'som_product_name', __( 'Product name is required.', 'order-machine' ) );
		}

		$sku = isset( $data['sku'] ) ? sanitize_text_field( (string) $data['sku'] ) : '';
		if ( '' === $sku ) {
			$sku = null;
		}

		$workflow_id = self::sanitize_workflow_id( $data );
		if ( is_wp_error( $workflow_id ) ) {
			return $workflow_id;
		}

		$is_internal = ! empty( $data['is_internal'] ) ? 1 : 0;
		if ( $is_internal && null === $workflow_id ) {
			return new WP_Error(
				'som_product_internal_workflow',
				__( 'Internal products require a workflow template.', 'order-machine' )
			);
		}

		$now = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			SOM_DB::table( 'products' ),
			array(
				'name'                  => $name,
				'sku'                   => $sku,
				'workflow_template_id'  => $workflow_id,
				'target_selling_price'  => self::nullable_price( $data, 'target_selling_price' ),
				'is_internal'           => $is_internal,
				'linked_material_id'    => null,
				'is_active'             => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
				'created_at'            => $now,
				'updated_at'            => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'som_product_create', __( 'Could not create product.', 'order-machine' ) );
		}

		$product_id = (int) $wpdb->insert_id;

		if ( $is_internal ) {
			$linked = self::ensure_linked_material( $product_id );
			if ( is_wp_error( $linked ) ) {
				return $linked;
			}
		}

		return $product_id;
	}

	/**
	 * Update product metadata (not recipe).
	 *
	 * @param int                  $product_id Product PK.
	 * @param array<string, mixed> $data       Fields to update.
	 * @return true|WP_Error
	 */
	public static function update( $product_id, array $data ) {
		global $wpdb;

		$product_id = (int) $product_id;
		$existing   = self::get( $product_id );
		if ( $product_id < 1 || ! $existing ) {
			return new WP_Error( 'som_product_missing', __( 'Product not found.', 'order-machine' ) );
		}

		$prev_workflow = isset( $existing->workflow_template_id ) ? (int) $existing->workflow_template_id : 0;

		$fields  = array(
			'updated_at' => current_time( 'mysql', true ),
		);
		$formats = array( '%s' );

		if ( array_key_exists( 'name', $data ) ) {
			$name = sanitize_text_field( (string) $data['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'som_product_name', __( 'Product name is required.', 'order-machine' ) );
			}
			$fields['name'] = $name;
			$formats[]      = '%s';
		}

		if ( array_key_exists( 'sku', $data ) ) {
			$sku = sanitize_text_field( (string) $data['sku'] );
			$fields['sku'] = '' === $sku ? null : $sku;
			$formats[]     = '%s';
		}

		$new_workflow = null;
		if ( array_key_exists( 'workflow_template_id', $data ) ) {
			$workflow_id = self::sanitize_workflow_id( $data );
			if ( is_wp_error( $workflow_id ) ) {
				return $workflow_id;
			}
			$fields['workflow_template_id'] = $workflow_id;
			$formats[]                      = null === $workflow_id ? '%s' : '%d';
			$new_workflow                   = null === $workflow_id ? 0 : (int) $workflow_id;
		}

		if ( array_key_exists( 'is_active', $data ) ) {
			$fields['is_active'] = (int) (bool) $data['is_active'];
			$formats[]           = '%d';
		}

		if ( array_key_exists( 'target_selling_price', $data ) ) {
			$fields['target_selling_price'] = self::nullable_price( $data, 'target_selling_price' );
			$formats[]                      = '%s';
		}

		$became_internal = false;
		if ( array_key_exists( 'is_internal', $data ) ) {
			$is_internal = ! empty( $data['is_internal'] ) ? 1 : 0;
			$fields['is_internal'] = $is_internal;
			$formats[]             = '%d';
			if ( $is_internal ) {
				$wf_check = array_key_exists( 'workflow_template_id', $fields )
					? $fields['workflow_template_id']
					: $existing->workflow_template_id;
				if ( empty( $wf_check ) ) {
					return new WP_Error(
						'som_product_internal_workflow',
						__( 'Internal products require a workflow template.', 'order-machine' )
					);
				}
				$became_internal = empty( $existing->is_internal ) || empty( $existing->linked_material_id );
			}
		}

		$updated = $wpdb->update(
			SOM_DB::table( 'products' ),
			$fields,
			array( 'id' => $product_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'som_product_update', __( 'Could not update product.', 'order-machine' ) );
		}

		if ( null !== $new_workflow && $new_workflow !== $prev_workflow ) {
			SOM_Step_Instructions::delete_orphans_for_product( $product_id, $new_workflow );
		}

		$after = self::get( $product_id );
		if ( $after && ! empty( $after->is_internal ) && ( $became_internal || empty( $after->linked_material_id ) ) ) {
			$linked = self::ensure_linked_material( $product_id );
			if ( is_wp_error( $linked ) ) {
				return $linked;
			}
		}

		if ( $after && ! empty( $after->is_internal ) && ! empty( $after->linked_material_id ) && array_key_exists( 'name', $fields ) ) {
			self::sync_linked_material_name( $product_id );
		}

		return true;
	}

	/**
	 * Ensure an internal product has a 1:1 linked output material.
	 *
	 * @param int $product_id Product PK.
	 * @return int|WP_Error Linked material ID.
	 */
	public static function ensure_linked_material( $product_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		$product    = self::get( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'som_product_missing', __( 'Product not found.', 'order-machine' ) );
		}
		if ( empty( $product->is_internal ) ) {
			return new WP_Error(
				'som_product_not_internal',
				__( 'Only internal products have a linked output material.', 'order-machine' )
			);
		}

		$linked_id = ! empty( $product->linked_material_id ) ? (int) $product->linked_material_id : 0;
		if ( $linked_id > 0 ) {
			$material = SOM_Materials::get( $linked_id );
			if ( $material ) {
				$wpdb->update(
					SOM_DB::table( 'materials' ),
					array(
						'source_product_id' => $product_id,
						'updated_at'        => current_time( 'mysql', true ),
					),
					array( 'id' => $linked_id ),
					array( '%d', '%s' ),
					array( '%d' )
				);
				self::sync_linked_material_name( $product_id );
				return $linked_id;
			}
		}

		// Prefer an existing material already pointing at this product.
		$materials_t = SOM_DB::table( 'materials' );
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$materials_t} WHERE source_product_id = %d LIMIT 1",
				$product_id
			)
		);
		if ( $existing_id > 0 ) {
			$wpdb->update(
				SOM_DB::table( 'products' ),
				array(
					'linked_material_id' => $existing_id,
					'updated_at'         => current_time( 'mysql', true ),
				),
				array( 'id' => $product_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			self::sync_linked_material_name( $product_id );
			return $existing_id;
		}

		$created = SOM_Materials::create(
			array(
				'name'              => (string) $product->name,
				'unit'              => 'each',
				'is_active'         => 1,
				'source_product_id' => $product_id,
			)
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$material_id = (int) $created;
		$ok          = $wpdb->update(
			SOM_DB::table( 'products' ),
			array(
				'linked_material_id' => $material_id,
				'updated_at'         => current_time( 'mysql', true ),
			),
			array( 'id' => $product_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_product_link', __( 'Could not link output material.', 'order-machine' ) );
		}

		return $material_id;
	}

	/**
	 * Keep linked material name in sync with the internal product name.
	 *
	 * @param int $product_id Product PK.
	 * @return void
	 */
	public static function sync_linked_material_name( $product_id ) {
		$product = self::get( (int) $product_id );
		if ( ! $product || empty( $product->linked_material_id ) ) {
			return;
		}
		SOM_Materials::update(
			(int) $product->linked_material_id,
			array( 'name' => (string) $product->name )
		);
	}

	/**
	 * Live recipe material cost and margin against target_selling_price.
	 *
	 * @param int                  $product_id   Product PK.
	 * @param array<int, float>    $wa_overrides Optional material_id => unit cost overrides (preview).
	 * @return array<string, mixed>|null
	 */
	public static function recipe_costing( $product_id, array $wa_overrides = array() ) {
		$product = self::get( (int) $product_id );
		if ( ! $product ) {
			return null;
		}

		$material_cost = 0.0;
		$lines         = array();
		foreach ( $product->recipe as $row ) {
			$mid = (int) $row->material_id;
			$qty = (float) $row->quantity_per_unit;
			if ( isset( $wa_overrides[ $mid ] ) ) {
				$unit = (float) $wa_overrides[ $mid ];
			} else {
				$material = SOM_Materials::get( $mid );
				$unit     = $material ? SOM_Material_Costing::unit_cost_for_consumption( $material ) : 0.0;
			}
			$line_cost      = SOM_Material_Costing::round4( $qty * $unit );
			$material_cost += $line_cost;
			$lines[]        = array(
				'material_id'        => $mid,
				'material_name'      => isset( $row->material_name ) ? (string) $row->material_name : '',
				'quantity_per_unit'  => $qty,
				'unit_cost'          => SOM_Material_Costing::round4( $unit ),
				'line_cost'          => $line_cost,
			);
		}

		$material_cost = SOM_Material_Costing::round4( $material_cost );
		$target        = null !== $product->target_selling_price && '' !== $product->target_selling_price
			? (float) $product->target_selling_price
			: null;

		$material_profit = null;
		$material_margin = null;
		if ( null !== $target ) {
			$material_profit = SOM_Material_Costing::round4( $target - $material_cost );
			if ( $target > 0 ) {
				$material_margin = round( ( $material_profit / $target ) * 100, 2 );
			}
		}

		$fee_costing = SOM_Platform_Fees::product_fee_costing( (int) $product->id, $material_cost, $target );

		$profit     = $material_profit;
		$margin_pct = $material_margin;
		$fee_source = isset( $fee_costing['fee_source'] ) ? (string) $fee_costing['fee_source'] : 'none';
		if ( null !== $fee_costing['fee_aware_profit'] ) {
			$profit     = $fee_costing['fee_aware_profit'];
			$margin_pct = $fee_costing['fee_aware_margin'];
		}

		$goal_alerts = array();
		if ( ! empty( $product->workflow_template_id ) ) {
			foreach ( $product->recipe as $row ) {
				$mid = (int) $row->material_id;
				$wa  = isset( $wa_overrides[ $mid ] )
					? (float) $wa_overrides[ $mid ]
					: null;
				foreach ( SOM_Material_Costing::goal_alerts_for_material( $mid, $wa ) as $alert ) {
					if ( (int) $alert['workflow_template_id'] !== (int) $product->workflow_template_id ) {
						continue;
					}
					$goal_alerts[] = $alert;
				}
			}
		}

		return array(
			'product_id'              => (int) $product->id,
			'product_name'            => (string) $product->name,
			'workflow_template_id'    => $product->workflow_template_id ? (int) $product->workflow_template_id : null,
			'target_selling_price'    => $target,
			'material_cost'           => $material_cost,
			'material_only_profit'    => $material_profit,
			'material_only_margin'    => $material_margin,
			'platform_fees'           => $fee_costing['platform_fees'],
			'fee_source'              => $fee_source,
			'fee_channels'            => $fee_costing['channels'],
			'preferred_fee_channel'   => $fee_costing['preferred_channel'],
			'representative_price'    => $fee_costing['representative_price'],
			'profit'                  => $profit,
			'margin_percent'          => $margin_pct,
			'lines'                   => $lines,
			'goal_alerts'             => $goal_alerts,
		);
	}

	/**
	 * @param array<string, mixed> $data Source.
	 * @param string               $key  Field.
	 * @return string|null
	 */
	private static function nullable_price( array $data, $key ) {
		if ( ! array_key_exists( $key, $data ) ) {
			return null;
		}
		$raw = trim( (string) $data[ $key ] );
		if ( '' === $raw ) {
			return null;
		}
		if ( ! is_numeric( $raw ) || (float) $raw < 0 ) {
			return null;
		}
		return number_format( (float) $raw, 2, '.', '' );
	}

	/**
	 * Replace the full material recipe for a product.
	 *
	 * @param int                      $product_id Product PK.
	 * @param array<int, array<string, mixed>> $rows       Each: material_id, quantity_per_unit.
	 * @return true|WP_Error
	 */
	public static function save_recipe( $product_id, array $rows ) {
		global $wpdb;

		$product_id = (int) $product_id;
		$product    = $product_id > 0 ? self::get( $product_id ) : null;
		if ( $product_id < 1 || ! $product ) {
			return new WP_Error( 'som_product_missing', __( 'Product not found.', 'order-machine' ) );
		}

		$linked_material_id = ! empty( $product->linked_material_id ) ? (int) $product->linked_material_id : 0;

		$seen       = array();
		$normalized = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$material_id = isset( $row['material_id'] ) ? (int) $row['material_id'] : 0;
			$qty_raw     = isset( $row['quantity_per_unit'] ) ? trim( (string) $row['quantity_per_unit'] ) : '';

			if ( $material_id < 1 || '' === $qty_raw ) {
				continue;
			}

			if ( ! is_numeric( $qty_raw ) || (float) $qty_raw <= 0 ) {
				return new WP_Error(
					'som_recipe_qty',
					__( 'Each recipe line needs a material and a quantity greater than zero.', 'order-machine' )
				);
			}

			if ( $linked_material_id > 0 && $material_id === $linked_material_id ) {
				return new WP_Error(
					'som_recipe_self',
					__( 'An internal product cannot consume its own output material.', 'order-machine' )
				);
			}

			if ( isset( $seen[ $material_id ] ) ) {
				return new WP_Error(
					'som_recipe_duplicate',
					__( 'Each material can only appear once in a recipe.', 'order-machine' )
				);
			}

			$seen[ $material_id ] = true;
			$normalized[]         = array(
				'material_id'        => $material_id,
				'quantity_per_unit'  => number_format( (float) $qty_raw, 2, '.', '' ),
			);
		}

		$recipe_t = SOM_DB::table( 'product_materials' );
		$wpdb->delete( $recipe_t, array( 'product_id' => $product_id ), array( '%d' ) );

		foreach ( $normalized as $line ) {
			$wpdb->insert(
				$recipe_t,
				array(
					'product_id'          => $product_id,
					'material_id'         => $line['material_id'],
					'quantity_per_unit'   => $line['quantity_per_unit'],
				),
				array( '%d', '%d', '%f' )
			);
		}

		return true;
	}

	/**
	 * Admin URL for the products list.
	 *
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		$args = array_merge( array( 'page' => 'som-products' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Admin URL for product edit (existing or new).
	 *
	 * @param int|string $product_id Product PK or "new".
	 * @return string
	 */
	public static function detail_url( $product_id ) {
		return self::list_url( array( 'product_id' => $product_id ) );
	}

	/**
	 * @param array<string, mixed> $data Form data.
	 * @return int|null|WP_Error
	 */
	private static function sanitize_workflow_id( array $data ) {
		if ( ! array_key_exists( 'workflow_template_id', $data ) ) {
			return null;
		}

		$raw = trim( (string) $data['workflow_template_id'] );
		if ( '' === $raw || '0' === $raw ) {
			return null;
		}

		$id = (int) $raw;
		if ( $id < 1 ) {
			return null;
		}

		global $wpdb;
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE id = %d LIMIT 1',
				$id
			)
		);

		if ( ! $exists ) {
			return new WP_Error( 'som_workflow_missing', __( 'Selected workflow template was not found.', 'order-machine' ) );
		}

		return $id;
	}
}
