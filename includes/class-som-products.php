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
	 * Max nesting depth through component (internal) materials in a recipe graph.
	 */
	const MAX_RECIPE_NESTING_DEPTH = 5;

	/**
	 * Query products for the admin list.
	 *
	 * @param array<string, mixed> $args Filters: status, type (all|sellable|internal), s, paged, per_page.
	 * @return array{products: array<int, object>, total: int, pages: int, paged: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => 'active',
			'type'     => 'all',
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

		$type = sanitize_key( (string) $args['type'] );
		if ( 'internal' === $type ) {
			$where[] = 'p.is_internal = 1';
		} elseif ( 'sellable' === $type ) {
			$where[] = 'p.is_internal = 0';
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
	 * @param array<string, mixed> $data Fields: name, sku, workflow_template_id, post_ship_workflow_template_id,
	 *                                   pack_workflow_template_id, is_active, is_internal,
	 *                                   weight_grams, package_id, package_id_multi, planned_shipping_gbp.
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		$prepared = self::prepare_create_row( $data );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$product_id = self::insert_prepared_product( $prepared );
		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		if ( ! empty( $prepared['is_internal'] ) ) {
			$linked = self::ensure_linked_material( $product_id );
			if ( is_wp_error( $linked ) ) {
				return $linked;
			}
		}

		return $product_id;
	}

	/**
	 * Copy a product into an independent product named "X (copy)".
	 *
	 * Copies product columns (same workflow template ids, including the Pack
	 * override, active state, price, and shipping), the material recipe, step
	 * instruction and task overrides, and budget links. SKU is left null. Listings, orders, stock logs, and
	 * fees are not copied. An internal clone gets a new output material.
	 *
	 * Validation matches create(). On any failure the copy is rolled back and
	 * then deleted, so no half product remains.
	 *
	 * @param int $product_id Source product PK.
	 * @return int|WP_Error New product id.
	 */
	public static function clone_product( $product_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		$source     = self::get( $product_id );
		if ( ! $source ) {
			return new WP_Error( 'som_product_missing', __( 'Product not found.', 'order-machine' ) );
		}

		$prepared = self::prepare_create_row( self::clone_source_data( $source ) );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$wpdb->query( 'START TRANSACTION' );

		$created = self::insert_prepared_product( $prepared );
		if ( is_wp_error( $created ) ) {
			self::abort_clone_write( 0 );
			return $created;
		}

		$new_id = (int) $created;

		$recipe = self::copy_recipe_rows( $product_id, $new_id );
		if ( is_wp_error( $recipe ) ) {
			self::abort_clone_write( $new_id );
			return $recipe;
		}

		$instructions = self::copy_step_instruction_rows( $product_id, $new_id );
		if ( is_wp_error( $instructions ) ) {
			self::abort_clone_write( $new_id );
			return $instructions;
		}

		$tasks = self::copy_step_task_rows( $product_id, $new_id );
		if ( is_wp_error( $tasks ) ) {
			self::abort_clone_write( $new_id );
			return $tasks;
		}

		$links = self::copy_budget_product_links( $product_id, $new_id );
		if ( is_wp_error( $links ) ) {
			self::abort_clone_write( $new_id );
			return $links;
		}

		if ( ! empty( $prepared['is_internal'] ) ) {
			$linked = self::ensure_linked_material( $new_id );
			if ( is_wp_error( $linked ) ) {
				self::abort_clone_write( $new_id );
				return $linked;
			}
		}

		$committed = $wpdb->query( 'COMMIT' );
		if ( false === $committed ) {
			self::abort_clone_write( $new_id );
			return new WP_Error( 'som_product_clone', __( 'Could not clone product.', 'order-machine' ) );
		}

		return $new_id;
	}

	/**
	 * Clone control: POST, nonce som_clone_product, manage_options is checked by the handler.
	 *
	 * @param int    $product_id   Product PK.
	 * @param string $return       editor|list — where to send the operator if clone fails.
	 * @param string $button_class Button class.
	 * @return void
	 */
	public static function render_clone_button( $product_id, $return = 'list', $button_class = 'button-link' ) {
		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return;
		}

		$return       = 'editor' === $return ? 'editor' : 'list';
		$button_class = sanitize_html_class( $button_class );
		if ( '' === $button_class ) {
			$button_class = 'button-link';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-products' ) ); ?>" class="som-clone-product-form">
			<?php wp_nonce_field( 'som_clone_product', 'som_product_clone_nonce' ); ?>
			<input type="hidden" name="som_clone_product" value="1" />
			<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product_id ); ?>" />
			<input type="hidden" name="som_clone_return" value="<?php echo esc_attr( $return ); ?>" />
			<button type="submit" class="<?php echo esc_attr( $button_class ); ?>"><?php echo esc_html__( 'Clone', 'order-machine' ); ?></button>
		</form>
		<?php
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

		if ( array_key_exists( 'post_ship_workflow_template_id', $data ) ) {
			$post_ship_id = self::sanitize_post_ship_workflow_id( $data );
			if ( is_wp_error( $post_ship_id ) ) {
				return $post_ship_id;
			}
			$fields['post_ship_workflow_template_id'] = $post_ship_id;
			$formats[]                                = null === $post_ship_id ? '%s' : '%d';
		}

		if ( array_key_exists( 'pack_workflow_template_id', $data ) ) {
			$pack_id = self::sanitize_pack_workflow_id( $data );
			if ( is_wp_error( $pack_id ) ) {
				return $pack_id;
			}
			$fields['pack_workflow_template_id'] = $pack_id;
			$formats[]                           = null === $pack_id ? '%s' : '%d';
		}

		if ( array_key_exists( 'is_active', $data ) ) {
			$want_active = (int) (bool) $data['is_active'];
			if ( 0 === $want_active && (int) $existing->is_active === 1 ) {
				$open = self::count_open_production_jobs( $product_id );
				if ( $open > 0 ) {
					return new WP_Error(
						'som_product_open_jobs',
						sprintf(
							/* translators: %d: open production order count */
							_n(
								'Cannot deactivate: %d open production job uses this product. Complete or cancel it first.',
								'Cannot deactivate: %d open production jobs use this product. Complete or cancel them first.',
								$open,
								'order-machine'
							),
							$open
						)
					);
				}
			}
			$fields['is_active'] = $want_active;
			$formats[]           = '%d';
		}

		if ( array_key_exists( 'target_selling_price', $data ) ) {
			$fields['target_selling_price'] = self::nullable_price( $data, 'target_selling_price' );
			$formats[]                      = '%s';
		}

		$became_internal = false;
		$will_be_internal = ! empty( $existing->is_internal );
		if ( array_key_exists( 'is_internal', $data ) ) {
			$is_internal = ! empty( $data['is_internal'] ) ? 1 : 0;
			$fields['is_internal'] = $is_internal;
			$formats[]             = '%d';
			$will_be_internal      = (bool) $is_internal;
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

		$shipping_keys = array( 'weight_grams', 'package_id', 'package_id_multi', 'planned_shipping_gbp' );
		$has_shipping  = false;
		foreach ( $shipping_keys as $sk ) {
			if ( array_key_exists( $sk, $data ) ) {
				$has_shipping = true;
				break;
			}
		}
		if ( $will_be_internal && ( $has_shipping || array_key_exists( 'is_internal', $data ) ) ) {
			foreach ( $shipping_keys as $sk ) {
				$fields[ $sk ] = null;
				$formats[]     = '%s';
			}
			$fields['post_ship_workflow_template_id'] = null;
			$formats[]                                = '%s';
			if ( ! array_key_exists( 'pack_workflow_template_id', $fields ) ) {
				$formats[] = '%s';
			}
			$fields['pack_workflow_template_id'] = null;
		} elseif ( ! $will_be_internal && $has_shipping ) {
			$shipping = self::sanitize_shipping_fields( $data, false );
			if ( is_wp_error( $shipping ) ) {
				return $shipping;
			}
			foreach ( $shipping_keys as $sk ) {
				if ( ! array_key_exists( $sk, $data ) ) {
					continue;
				}
				$fields[ $sk ] = $shipping[ $sk ];
				$formats[]     = '%s';
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

		$planned_shipping = null;
		if ( isset( $product->planned_shipping_gbp ) && null !== $product->planned_shipping_gbp && '' !== $product->planned_shipping_gbp ) {
			$planned_shipping = (float) $product->planned_shipping_gbp;
		}

		$profit     = $material_profit;
		$margin_pct = $material_margin;
		$fee_source = isset( $fee_costing['fee_source'] ) ? (string) $fee_costing['fee_source'] : 'none';
		if ( null !== $fee_costing['fee_aware_profit'] ) {
			$profit     = $fee_costing['fee_aware_profit'];
			$margin_pct = $fee_costing['fee_aware_margin'];
		}

		if ( null !== $planned_shipping && null !== $profit ) {
			$profit = SOM_Material_Costing::round4( (float) $profit - $planned_shipping );
			$rep    = null !== $fee_costing['representative_price']
				? (float) $fee_costing['representative_price']
				: $target;
			if ( null !== $rep && $rep > 0 ) {
				$margin_pct = round( ( $profit / $rep ) * 100, 2 );
			}
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
			'planned_shipping_gbp'    => $planned_shipping,
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
	 * Sanitize product shipping fields. Internal products always clear them.
	 *
	 * @param array<string, mixed> $data        Source.
	 * @param bool                 $is_internal Whether product is internal.
	 * @return array{weight_grams: string|null, package_id: int|null, package_id_multi: int|null, planned_shipping_gbp: string|null}|WP_Error
	 */
	private static function sanitize_shipping_fields( array $data, $is_internal ) {
		if ( $is_internal ) {
			return array(
				'weight_grams'         => null,
				'package_id'           => null,
				'package_id_multi'     => null,
				'planned_shipping_gbp' => null,
			);
		}

		$weight = null;
		if ( array_key_exists( 'weight_grams', $data ) ) {
			$raw = trim( (string) $data['weight_grams'] );
			if ( '' !== $raw ) {
				if ( ! is_numeric( $raw ) || (float) $raw < 0 ) {
					return new WP_Error(
						'som_product_weight',
						__( 'Goods weight must be zero or a positive number of grams.', 'order-machine' )
					);
				}
				$weight = number_format( (float) $raw, 2, '.', '' );
			}
		}

		$package_id = null;
		if ( array_key_exists( 'package_id', $data ) ) {
			$raw = trim( (string) $data['package_id'] );
			if ( '' !== $raw ) {
				$package_id = (int) $raw;
				if ( $package_id < 1 || ! SOM_Shipping_Packages::get( $package_id ) ) {
					return new WP_Error( 'som_product_package', __( 'Selected shipping package was not found.', 'order-machine' ) );
				}
			}
		}

		$package_id_multi = null;
		if ( array_key_exists( 'package_id_multi', $data ) ) {
			$raw = trim( (string) $data['package_id_multi'] );
			if ( '' !== $raw ) {
				$package_id_multi = (int) $raw;
				if ( $package_id_multi < 1 || ! SOM_Shipping_Packages::get( $package_id_multi ) ) {
					return new WP_Error( 'som_product_package_multi', __( 'Selected multi-pack shipping package was not found.', 'order-machine' ) );
				}
			}
		}

		$planned = null;
		if ( array_key_exists( 'planned_shipping_gbp', $data ) ) {
			$planned = self::nullable_price( $data, 'planned_shipping_gbp' );
		}

		return array(
			'weight_grams'         => $weight,
			'package_id'           => $package_id,
			'package_id_multi'     => $package_id_multi,
			'planned_shipping_gbp' => $planned,
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

		$graph = self::validate_recipe_nesting( $product_id, array_keys( $seen ) );
		if ( is_wp_error( $graph ) ) {
			return $graph;
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
	 * Count incomplete Internal-channel production orders for a product.
	 *
	 * @param int $product_id Product PK.
	 * @return int
	 */
	public static function count_open_production_jobs( $product_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return 0;
		}

		$orders_t = SOM_DB::table( 'orders' );
		$chan_t   = SOM_DB::table( 'channels' );
		$items_t  = SOM_DB::table( 'order_items' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.id, o.raw_payload
				FROM {$orders_t} o
				INNER JOIN {$chan_t} c ON c.id = o.channel_id AND c.slug = %s
				WHERE o.is_complete = 0
				AND EXISTS (
					SELECT 1 FROM {$items_t} oi
					WHERE oi.order_id = o.id AND oi.product_id = %d
				)",
				SOM_Production::CHANNEL_SLUG,
				$product_id
			)
		);
		if ( ! is_array( $rows ) || ! $rows ) {
			return 0;
		}

		$count = 0;
		foreach ( $rows as $row ) {
			if ( ! SOM_Orders::is_cancelled( $row->raw_payload, SOM_Production::CHANNEL_SLUG ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Owning internal product for a material (source_product_id or linked_material_id).
	 *
	 * @param int $material_id Material PK.
	 * @return int Product PK or 0.
	 */
	public static function internal_product_for_material( $material_id ) {
		global $wpdb;

		$material_id = (int) $material_id;
		if ( $material_id < 1 ) {
			return 0;
		}

		$material = SOM_Materials::get( $material_id );
		if ( $material && ! empty( $material->source_product_id ) ) {
			return (int) $material->source_product_id;
		}

		$products_t = SOM_DB::table( 'products' );
		$owner      = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$products_t} WHERE is_internal = 1 AND linked_material_id = %d LIMIT 1",
				$material_id
			)
		);

		return $owner > 0 ? $owner : 0;
	}

	/**
	 * Reject recipe cycles and nesting deeper than {@see MAX_RECIPE_NESTING_DEPTH}.
	 *
	 * Walks each recipe material through owning internal products' recipes.
	 *
	 * @param int   $product_id   Product whose recipe is being saved.
	 * @param int[] $material_ids Direct recipe material IDs.
	 * @return true|WP_Error
	 */
	public static function validate_recipe_nesting( $product_id, array $material_ids ) {
		$product_id = (int) $product_id;
		$max_depth  = self::MAX_RECIPE_NESTING_DEPTH;

		foreach ( $material_ids as $material_id ) {
			$material_id = (int) $material_id;
			if ( $material_id < 1 ) {
				continue;
			}

			$stack         = array( array( $material_id, 1 ) );
			$seen_products = array();

			while ( $stack ) {
				$frame = array_pop( $stack );
				$mid   = (int) $frame[0];
				$depth = (int) $frame[1];

				if ( $depth > $max_depth ) {
					return new WP_Error(
						'som_recipe_depth',
						sprintf(
							/* translators: %d: max nesting depth */
							__( 'Recipe nesting cannot exceed %d component levels.', 'order-machine' ),
							$max_depth
						)
					);
				}

				$owner = self::internal_product_for_material( $mid );
				if ( $owner < 1 ) {
					continue;
				}
				if ( $owner === $product_id ) {
					return new WP_Error(
						'som_recipe_cycle',
						__( 'This recipe would create a cycle through an internal product’s output.', 'order-machine' )
					);
				}
				if ( isset( $seen_products[ $owner ] ) ) {
					continue;
				}
				$seen_products[ $owner ] = true;

				foreach ( self::get_recipe( $owner ) as $row ) {
					$stack[] = array( (int) $row->material_id, $depth + 1 );
				}
			}
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
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, kind FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE id = %d LIMIT 1',
				$id
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'som_workflow_missing', __( 'Selected workflow template was not found.', 'order-machine' ) );
		}

		$kind = isset( $row->kind ) ? sanitize_key( (string) $row->kind ) : 'make';
		if ( 'make' !== $kind ) {
			return new WP_Error(
				'som_workflow_kind',
				__( 'Products can only use Make workflow templates.', 'order-machine' )
			);
		}

		return $id;
	}

	/**
	 * Optional per-product Post-ship template override.
	 *
	 * @param array<string, mixed> $data Form data.
	 * @return int|null|WP_Error
	 */
	private static function sanitize_post_ship_workflow_id( array $data ) {
		if ( ! array_key_exists( 'post_ship_workflow_template_id', $data ) ) {
			return null;
		}

		$raw = trim( (string) $data['post_ship_workflow_template_id'] );
		if ( '' === $raw || '0' === $raw ) {
			return null;
		}

		$id = (int) $raw;
		if ( $id < 1 ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, kind, is_active FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE id = %d LIMIT 1',
				$id
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'som_post_ship_missing', __( 'Selected Post-ship workflow template was not found.', 'order-machine' ) );
		}

		$kind = SOM_Workflows::sanitize_kind( isset( $row->kind ) ? $row->kind : '' );
		if ( 'post_ship' !== $kind ) {
			return new WP_Error(
				'som_post_ship_kind',
				__( 'Product Post-ship override must be a Post-ship workflow template.', 'order-machine' )
			);
		}

		return $id;
	}

	/**
	 * Optional per-product Pack template override.
	 *
	 * Blank means Settings → Default Pack workflow. An inactive Pack template
	 * may be kept; bind falls through to the site default until it is active
	 * and has steps.
	 *
	 * @param array<string, mixed> $data Form data.
	 * @return int|null|WP_Error
	 */
	private static function sanitize_pack_workflow_id( array $data ) {
		if ( ! array_key_exists( 'pack_workflow_template_id', $data ) ) {
			return null;
		}

		$raw = trim( (string) $data['pack_workflow_template_id'] );
		if ( '' === $raw || '0' === $raw ) {
			return null;
		}

		$id = (int) $raw;
		if ( $id < 1 ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, kind, is_active FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE id = %d LIMIT 1',
				$id
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'som_pack_missing', __( 'Selected Pack workflow template was not found.', 'order-machine' ) );
		}

		$kind = SOM_Workflows::sanitize_kind( isset( $row->kind ) ? $row->kind : '' );
		if ( 'pack' !== $kind ) {
			return new WP_Error(
				'som_pack_kind',
				__( 'Product Pack override must be a Pack workflow template.', 'order-machine' )
			);
		}

		return $id;
	}

	/**
	 * Active post_ship templates for product override dropdowns.
	 *
	 * @param int $include_id Keep selected inactive template visible.
	 * @return array<int, object>
	 */
	public static function list_post_ship_templates( $include_id = 0 ) {
		global $wpdb;

		$include_id = (int) $include_id;
		$table      = SOM_DB::table( 'workflow_templates' );
		if ( $include_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, is_active, kind FROM {$table}
					WHERE ( ( is_active = 1 AND kind = 'post_ship' ) OR id = %d )
					ORDER BY name ASC, id ASC",
					$include_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				"SELECT id, name, is_active, kind FROM {$table}
				WHERE is_active = 1 AND kind = 'post_ship'
				ORDER BY name ASC, id ASC"
			);
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Active Pack templates for product override dropdowns.
	 *
	 * @param int $include_id Keep selected inactive template visible.
	 * @return array<int, object>
	 */
	public static function list_pack_templates( $include_id = 0 ) {
		global $wpdb;

		$include_id = (int) $include_id;
		$table      = SOM_DB::table( 'workflow_templates' );
		if ( $include_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, is_active, kind FROM {$table}
					WHERE ( ( is_active = 1 AND kind = 'pack' ) OR id = %d )
					ORDER BY name ASC, id ASC",
					$include_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				"SELECT id, name, is_active, kind FROM {$table}
				WHERE is_active = 1 AND kind = 'pack'
				ORDER BY name ASC, id ASC"
			);
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Same checks and column values as create(), with no write.
	 *
	 * @param array<string, mixed> $data Fields create() accepts.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function prepare_create_row( array $data ) {
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

		$post_ship_id = self::sanitize_post_ship_workflow_id( $data );
		if ( is_wp_error( $post_ship_id ) ) {
			return $post_ship_id;
		}

		$pack_id = self::sanitize_pack_workflow_id( $data );
		if ( is_wp_error( $pack_id ) ) {
			return $pack_id;
		}

		$is_internal = ! empty( $data['is_internal'] ) ? 1 : 0;
		if ( $is_internal && null === $workflow_id ) {
			return new WP_Error(
				'som_product_internal_workflow',
				__( 'Internal products require a workflow template.', 'order-machine' )
			);
		}
		if ( $is_internal ) {
			$post_ship_id = null;
			$pack_id      = null;
		}

		$shipping = self::sanitize_shipping_fields( $data, (bool) $is_internal );
		if ( is_wp_error( $shipping ) ) {
			return $shipping;
		}

		return array(
			'name'                           => $name,
			'sku'                            => $sku,
			'workflow_template_id'           => $workflow_id,
			'post_ship_workflow_template_id' => $post_ship_id,
			'pack_workflow_template_id'      => $pack_id,
			'target_selling_price'           => self::nullable_price( $data, 'target_selling_price' ),
			'weight_grams'                   => $shipping['weight_grams'],
			'package_id'                     => $shipping['package_id'],
			'package_id_multi'               => $shipping['package_id_multi'],
			'planned_shipping_gbp'           => $shipping['planned_shipping_gbp'],
			'is_internal'                    => $is_internal,
			'is_active'                      => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
		);
	}

	/**
	 * Insert a product row. linked_material_id stays null until ensure_linked_material().
	 *
	 * @param array<string, mixed> $row Prepared columns from prepare_create_row().
	 * @return int|WP_Error New product id.
	 */
	private static function insert_prepared_product( array $row ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			SOM_DB::table( 'products' ),
			array(
				'name'                           => $row['name'],
				'sku'                            => $row['sku'],
				'workflow_template_id'           => $row['workflow_template_id'],
				'post_ship_workflow_template_id' => $row['post_ship_workflow_template_id'],
				'pack_workflow_template_id'      => $row['pack_workflow_template_id'],
				'target_selling_price'           => $row['target_selling_price'],
				'weight_grams'                   => $row['weight_grams'],
				'package_id'                     => $row['package_id'],
				'package_id_multi'               => $row['package_id_multi'],
				'planned_shipping_gbp'           => $row['planned_shipping_gbp'],
				'is_internal'                    => $row['is_internal'],
				'linked_material_id'             => null,
				'is_active'                      => $row['is_active'],
				'created_at'                     => $now,
				'updated_at'                     => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'som_product_create', __( 'Could not create product.', 'order-machine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * create() payload for a clone: "X (copy)", blank SKU, same workflows and settings.
	 *
	 * linked_material_id is omitted so the insert does not reuse the original material.
	 *
	 * @param object $source Source product row.
	 * @return array<string, mixed>
	 */
	private static function clone_source_data( $source ) {
		$price = isset( $source->target_selling_price ) ? $source->target_selling_price : null;
		$ship  = isset( $source->planned_shipping_gbp ) ? $source->planned_shipping_gbp : null;

		return array(
			'name'                           => self::clone_copy_name( isset( $source->name ) ? (string) $source->name : '' ),
			'sku'                            => '',
			'workflow_template_id'           => ! empty( $source->workflow_template_id ) ? (int) $source->workflow_template_id : '',
			'post_ship_workflow_template_id' => ! empty( $source->post_ship_workflow_template_id ) ? (int) $source->post_ship_workflow_template_id : '',
			'pack_workflow_template_id'      => ! empty( $source->pack_workflow_template_id ) ? (int) $source->pack_workflow_template_id : '',
			'target_selling_price'           => ( null !== $price && '' !== (string) $price ) ? (string) $price : '',
			'weight_grams'                   => ( isset( $source->weight_grams ) && null !== $source->weight_grams && '' !== (string) $source->weight_grams ) ? (string) $source->weight_grams : '',
			'package_id'                     => ! empty( $source->package_id ) ? (int) $source->package_id : '',
			'package_id_multi'               => ! empty( $source->package_id_multi ) ? (int) $source->package_id_multi : '',
			'planned_shipping_gbp'           => ( null !== $ship && '' !== (string) $ship ) ? (string) $ship : '',
			'is_internal'                    => ! empty( $source->is_internal ) ? 1 : 0,
			'is_active'                      => isset( $source->is_active ) ? (int) $source->is_active : 1,
		);
	}

	/**
	 * "X (copy)", trimmed so the result fits the 100-character name column.
	 *
	 * @param string $name Source product name.
	 * @return string
	 */
	private static function clone_copy_name( $name ) {
		$name   = sanitize_text_field( (string) $name );
		$suffix = ' (copy)';
		$limit  = 100;
		if ( self::text_length( $name . $suffix ) <= $limit ) {
			return $name . $suffix;
		}

		$keep = $limit - self::text_length( $suffix );
		$name = rtrim( self::text_cut( $name, $keep ) );
		return $name . $suffix;
	}

	/**
	 * Copy product_materials rows onto the new product.
	 *
	 * @param int $source_id Source product PK.
	 * @param int $new_id    Clone product PK.
	 * @return true|WP_Error
	 */
	private static function copy_recipe_rows( $source_id, $new_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'product_materials' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT material_id, quantity_per_unit FROM {$table} WHERE product_id = %d ORDER BY id ASC",
				(int) $source_id
			)
		);
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'som_recipe_clone', __( 'Could not copy the material recipe.', 'order-machine' ) );
		}

		foreach ( $rows as $row ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'product_id'        => (int) $new_id,
					'material_id'       => (int) $row->material_id,
					'quantity_per_unit' => (string) $row->quantity_per_unit,
				),
				array( '%d', '%d', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_recipe_clone', __( 'Could not copy the material recipe.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Copy product_step_instructions rows. Step ids stay the same (same workflow).
	 *
	 * @param int $source_id Source product PK.
	 * @param int $new_id    Clone product PK.
	 * @return true|WP_Error
	 */
	private static function copy_step_instruction_rows( $source_id, $new_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'product_step_instructions' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT workflow_step_id, instructions FROM {$table} WHERE product_id = %d ORDER BY id ASC",
				(int) $source_id
			)
		);
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'som_instructions_clone', __( 'Could not copy step instruction overrides.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		foreach ( $rows as $row ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'product_id'       => (int) $new_id,
					'workflow_step_id' => (int) $row->workflow_step_id,
					'instructions'     => (string) $row->instructions,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_instructions_clone', __( 'Could not copy step instruction overrides.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Copy product_step_tasks rows. Step ids stay the same (same workflow).
	 *
	 * @param int $source_id Source product PK.
	 * @param int $new_id    Clone product PK.
	 * @return true|WP_Error
	 */
	private static function copy_step_task_rows( $source_id, $new_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'product_step_tasks' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT workflow_step_id, task_list_json FROM {$table} WHERE product_id = %d ORDER BY id ASC",
				(int) $source_id
			)
		);
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'som_tasks_clone', __( 'Could not copy step task overrides.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		foreach ( $rows as $row ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'product_id'       => (int) $new_id,
					'workflow_step_id' => (int) $row->workflow_step_id,
					'task_list_json'   => (string) $row->task_list_json,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_tasks_clone', __( 'Could not copy step task overrides.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Copy budget_product_links onto the new product id.
	 *
	 * @param int $source_id Source product PK.
	 * @param int $new_id    Clone product PK.
	 * @return true|WP_Error
	 */
	private static function copy_budget_product_links( $source_id, $new_id ) {
		global $wpdb;

		$table = SOM_DB::table( 'budget_product_links' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT budget_id FROM {$table} WHERE product_id = %d ORDER BY id ASC",
				(int) $source_id
			)
		);
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'som_budget_clone', __( 'Could not copy budget links.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		foreach ( $rows as $row ) {
			$budget_id = (int) $row->budget_id;
			if ( $budget_id < 1 ) {
				continue;
			}
			$inserted = $wpdb->insert(
				$table,
				array(
					'budget_id'  => $budget_id,
					'product_id' => (int) $new_id,
					'created_at' => $now,
				),
				array( '%d', '%d', '%s' )
			);
			if ( ! $inserted ) {
				return new WP_Error( 'som_budget_clone', __( 'Could not copy budget links.', 'order-machine' ) );
			}
		}

		return true;
	}

	/**
	 * Undo a clone that failed after the new product row was inserted.
	 *
	 * ROLLBACK covers InnoDB. The delete covers engines that ignore the
	 * transaction, so a failed clone never leaves a half copy.
	 *
	 * @param int $product_id New product PK, or 0 when the insert did not happen.
	 * @return void
	 */
	private static function abort_clone_write( $product_id ) {
		global $wpdb;

		$wpdb->query( 'ROLLBACK' );
		self::delete_uncommitted_clone( (int) $product_id );
	}

	/**
	 * Remove a product whose recipe did not save.
	 *
	 * Product import calls this when save_recipe() fails after create(), and
	 * when create() inserted a row but did not return its id. Deletes that
	 * product and the output material created for it. Other products stay.
	 *
	 * @param int $product_id Product PK from create().
	 * @return void
	 */
	public static function delete_failed_import( $product_id ) {
		self::delete_uncommitted_clone( (int) $product_id );
	}

	/**
	 * Remove a clone product and the rows that belong only to it.
	 *
	 * Deletes the output material created for this product id. Does not touch
	 * another product's material. Import uses the same cleanup through
	 * delete_failed_import(). Orders are not removed.
	 *
	 * @param int $product_id Clone product PK.
	 * @return void
	 */
	private static function delete_uncommitted_clone( $product_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return;
		}

		$materials_t  = SOM_DB::table( 'materials' );
		$material_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$materials_t} WHERE source_product_id = %d",
				$product_id
			)
		);
		if ( is_array( $material_ids ) ) {
			foreach ( $material_ids as $material_id ) {
				$material_id = (int) $material_id;
				if ( $material_id < 1 ) {
					continue;
				}
				$wpdb->delete(
					SOM_DB::table( 'material_stock_log' ),
					array( 'material_id' => $material_id ),
					array( '%d' )
				);
				$wpdb->delete(
					$materials_t,
					array(
						'id'                => $material_id,
						'source_product_id' => $product_id,
					),
					array( '%d', '%d' )
				);
			}
		}

		foreach ( array( 'product_materials', 'product_step_instructions', 'product_step_tasks', 'budget_product_links', 'listings' ) as $suffix ) {
			$wpdb->delete(
				SOM_DB::table( $suffix ),
				array( 'product_id' => $product_id ),
				array( '%d' )
			);
		}

		$wpdb->delete(
			SOM_DB::table( 'products' ),
			array( 'id' => $product_id ),
			array( '%d' )
		);
	}

	/**
	 * @param string $text Text.
	 * @return int
	 */
	private static function text_length( $text ) {
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
	private static function text_cut( $text, $length ) {
		$text   = (string) $text;
		$length = max( 0, (int) $length );
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, $length );
		}
		return (string) substr( $text, 0, $length );
	}
}
