<?php
/**
 * Product CSV export, template, preview, and import.
 *
 * One row per recipe line. Rows with the same name are one product. Creates
 * through SOM_Products::create and SOM_Products::save_recipe. Does not update,
 * and does not create listings, materials, workflows, or packages.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product catalog file.
 */
class SOM_Product_Import {

	/**
	 * Preview-token kind. Materials and workflows use their own kinds.
	 */
	const KIND = 'products';

	/**
	 * products.sku width.
	 */
	const SKU_LIMIT = 50;

	/**
	 * Columns shared by export, template, and import.
	 *
	 * pack_workflow_name is not a column. UP14-S9 adds it after the schema column exists.
	 *
	 * @var string[]
	 */
	const COLUMNS = array(
		'name',
		'sku',
		'is_internal',
		'workflow_name',
		'post_ship_workflow_name',
		'target_selling_price',
		'weight_grams',
		'package_name',
		'package_name_multi',
		'planned_shipping_gbp',
		'is_active',
		'material_name',
		'quantity_per_unit',
	);

	/**
	 * @var string[]
	 */
	const REQUIRED = array(
		'name',
	);

	/**
	 * Product fields repeated on every recipe row. They must agree.
	 *
	 * @var string[]
	 */
	const REPEATED = array(
		'name',
		'sku',
		'is_internal',
		'workflow_name',
		'post_ship_workflow_name',
		'target_selling_price',
		'weight_grams',
		'package_name',
		'package_name_multi',
		'planned_shipping_gbp',
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
	 * Current products, one row per recipe line.
	 *
	 * A product with no recipe is one row with material_name and quantity_per_unit empty.
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
			$filename = 'products.csv';
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
	 * Parse a file. Does not insert products.
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
	 * Create the products in a file. A failed product does not remove products already saved.
	 *
	 * @param string $contents File text.
	 * @return array{created: int, failed: int, warnings: int, file_error: string, file_error_code: string}
	 */
	public static function commit( $contents ) {
		$prepared = self::prepare( $contents );
		if ( '' !== $prepared['file_error'] ) {
			return array(
				'created'         => 0,
				'failed'          => 0,
				'warnings'        => 0,
				'file_error'      => $prepared['file_error'],
				'file_error_code' => $prepared['file_error_code'],
			);
		}

		$taken = SOM_Catalog_Import::load_taken_names( 'products' );
		if ( is_wp_error( $taken ) ) {
			return array(
				'created'         => 0,
				'failed'          => 0,
				'warnings'        => 0,
				'file_error'      => $taken->get_error_message(),
				'file_error_code' => $taken->get_error_code(),
			);
		}

		$skus     = self::load_sku_keys();
		$created  = 0;
		$failed   = 0;
		$warnings = 0;
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

			$data   = $row['data'];
			$recipe = isset( $data['recipe'] ) && is_array( $data['recipe'] ) ? $data['recipe'] : array();
			unset( $data['recipe'] );
			$data['name'] = $save;

			$product_id = SOM_Products::create( $data );
			if ( is_wp_error( $product_id ) || (int) $product_id < 1 ) {
				self::abandon_named( $save );
				SOM_Catalog_Import::release_name( $save, $taken );
				++$failed;
				continue;
			}

			$product_id = (int) $product_id;
			$saved      = SOM_Products::save_recipe( $product_id, $recipe );
			if ( is_wp_error( $saved ) ) {
				SOM_Products::delete_failed_import( $product_id );
				SOM_Catalog_Import::release_name( $save, $taken );
				++$failed;
				continue;
			}

			$sku_key = self::sku_key( isset( $data['sku'] ) ? (string) $data['sku'] : '' );
			if ( '' !== $sku_key && isset( $skus[ $sku_key ] ) ) {
				++$warnings;
			}
			if ( '' !== $sku_key ) {
				$skus[ $sku_key ] = true;
			}
			++$created;
		}

		return array(
			'created'         => $created,
			'failed'          => $failed,
			'warnings'        => $warnings,
			'file_error'      => '',
			'file_error_code' => '',
		);
	}

	/**
	 * Parse the file stored for a preview token. Does not insert products.
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

		$products  = SOM_DB::table( 'products' );
		$templates = SOM_DB::table( 'workflow_templates' );
		$packages  = SOM_DB::table( 'shipping_packages' );
		$recipe    = SOM_DB::table( 'product_materials' );
		$materials = SOM_DB::table( 'materials' );
		$sql       = "SELECT
				p.name,
				p.sku,
				p.is_internal,
				p.is_active,
				p.target_selling_price,
				p.weight_grams,
				p.planned_shipping_gbp,
				wt.name AS workflow_name,
				ps.name AS post_ship_workflow_name,
				pkg.name AS package_name,
				pkgm.name AS package_name_multi,
				m.name AS material_name,
				pm.quantity_per_unit
			FROM {$products} p
			LEFT JOIN {$templates} wt ON wt.id = p.workflow_template_id
			LEFT JOIN {$templates} ps ON ps.id = p.post_ship_workflow_template_id
			LEFT JOIN {$packages} pkg ON pkg.id = p.package_id
			LEFT JOIN {$packages} pkgm ON pkgm.id = p.package_id_multi
			LEFT JOIN {$recipe} pm ON pm.product_id = p.id
			LEFT JOIN {$materials} m ON m.id = pm.material_id
			ORDER BY p.name ASC, p.id ASC, pm.id ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names from SOM_DB::table, no user input.
		$rows = $wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				isset( $row->name ) ? (string) $row->name : '',
				self::export_text( isset( $row->sku ) ? $row->sku : null ),
				(string) (int) ( isset( $row->is_internal ) ? $row->is_internal : 0 ),
				self::export_text( isset( $row->workflow_name ) ? $row->workflow_name : null ),
				self::export_text( isset( $row->post_ship_workflow_name ) ? $row->post_ship_workflow_name : null ),
				self::export_text( isset( $row->target_selling_price ) ? $row->target_selling_price : null ),
				self::export_text( isset( $row->weight_grams ) ? $row->weight_grams : null ),
				self::export_text( isset( $row->package_name ) ? $row->package_name : null ),
				self::export_text( isset( $row->package_name_multi ) ? $row->package_name_multi : null ),
				self::export_text( isset( $row->planned_shipping_gbp ) ? $row->planned_shipping_gbp : null ),
				(string) (int) ( isset( $row->is_active ) ? $row->is_active : 0 ),
				self::export_text( isset( $row->material_name ) ? $row->material_name : null ),
				self::export_text( isset( $row->quantity_per_unit ) ? $row->quantity_per_unit : null ),
			);
		}
		return $out;
	}

	/**
	 * Validate products without reserving names.
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

		$refs    = self::reference_index();
		$grouped = self::collect_groups( $table['rows'], (int) $table['header_at'], $table['index'] );
		$rows    = array();
		$failed  = 0;
		foreach ( $grouped['order'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['type'] ) ) {
				continue;
			}
			if ( 'row' === $item['type'] ) {
				$row = isset( $item['row'] ) && is_array( $item['row'] ) ? $item['row'] : self::failed_product( 0, '', 'missing_name', __( 'Name is required.', 'order-machine' ) );
				if ( empty( $row['ok'] ) ) {
					++$failed;
				}
				$rows[] = $row;
				continue;
			}

			$key   = isset( $item['key'] ) ? (string) $item['key'] : '';
			$group = ( '' !== $key && isset( $grouped['groups'][ $key ] ) ) ? $grouped['groups'][ $key ] : null;
			if ( ! is_array( $group ) ) {
				continue;
			}
			$built = self::build_product( $group, $table['index'], $refs );
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
	 * Fill save_name for products that can be created. Preview only; commit allocates again.
	 *
	 * @param array<string, mixed> $prepared Prepared file.
	 * @return array<string, mixed>
	 */
	private static function with_names( array $prepared ) {
		$taken = SOM_Catalog_Import::load_taken_names( 'products' );
		if ( is_wp_error( $taken ) ) {
			return self::file_failure( $taken->get_error_message(), $taken->get_error_code() );
		}

		$skus   = self::load_sku_keys();
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
				$row['warning']   = '';
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

			$sku_key = self::sku_key( isset( $row['data']['sku'] ) ? (string) $row['data']['sku'] : '' );
			if ( '' !== $sku_key && isset( $skus[ $sku_key ] ) ) {
				$warning          = __( 'SKU is already used by another product.', 'order-machine' );
				$row['warning']   = $warning;
				$row['message']   = $warning;
			}
			if ( '' !== $sku_key ) {
				$skus[ $sku_key ] = true;
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
	 * @param array<int, array<int, string|null>> $records  File rows.
	 * @param int                                 $header_at Header index.
	 * @param array<string, int>                  $index     Header index.
	 * @return array{groups: array<string, array<string, mixed>>, order: array<int, array<string, mixed>>}
	 */
	private static function collect_groups( array $records, $header_at, array $index ) {
		$groups = array();
		$order  = array();
		$count  = count( $records );
		for ( $i = $header_at + 1; $i < $count; $i++ ) {
			$record = $records[ $i ];
			if ( ! is_array( $record ) || SOM_Catalog_Import::row_is_empty( $record ) ) {
				continue;
			}

			$line      = $i + 1;
			$file_name = trim( sanitize_text_field( SOM_Catalog_Import::cell( $record, $index, 'name' ) ) );
			if ( '' === $file_name ) {
				$order[] = array(
					'type' => 'row',
					'row'  => self::failed_product(
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
	 * @param array<string, mixed>              $group Group from collect_groups().
	 * @param array<string, int>                $index Header index.
	 * @param array<string, array<string, int[]>> $refs  Name indexes.
	 * @return array<string, mixed>
	 */
	private static function build_product( array $group, array $index, array $refs ) {
		$file_name = (string) $group['file_name'];
		$line      = (int) $group['line'];
		$records   = isset( $group['records'] ) && is_array( $group['records'] ) ? $group['records'] : array();
		$first     = isset( $records[0]['record'] ) && is_array( $records[0]['record'] ) ? $records[0]['record'] : array();

		foreach ( self::REPEATED as $column ) {
			$expected = self::repeated_value( $first, $index, $column );
			foreach ( $records as $item ) {
				$record = isset( $item['record'] ) && is_array( $item['record'] ) ? $item['record'] : array();
				if ( $expected !== self::repeated_value( $record, $index, $column ) ) {
					return self::failed_product(
						$line,
						$file_name,
						'header_disagreement',
						sprintf(
							/* translators: %s: column label */
							__( '%s does not match on every recipe line.', 'order-machine' ),
							self::column_label( $column )
						)
					);
				}
			}
		}

		$internal = SOM_Catalog_Import::parse_bool( SOM_Catalog_Import::cell( $first, $index, 'is_internal' ) );
		if ( is_wp_error( $internal ) ) {
			return self::failed_product( $line, $file_name, 'bad_is_internal', __( 'Internal must be yes or no.', 'order-machine' ) );
		}

		$active = SOM_Catalog_Import::parse_bool( SOM_Catalog_Import::cell( $first, $index, 'is_active' ) );
		if ( is_wp_error( $active ) ) {
			return self::failed_product( $line, $file_name, 'bad_is_active', __( 'Active must be yes or no.', 'order-machine' ) );
		}

		$sku = trim( sanitize_text_field( SOM_Catalog_Import::cell( $first, $index, 'sku' ) ) );
		if ( SOM_Catalog_Import::text_length( $sku ) > self::SKU_LIMIT ) {
			return self::failed_product( $line, $file_name, 'sku_length', __( 'SKU is too long.', 'order-machine' ) );
		}

		$workflow_name = trim( sanitize_text_field( SOM_Catalog_Import::cell( $first, $index, 'workflow_name' ) ) );
		if ( '' === $workflow_name ) {
			if ( true === $internal ) {
				return self::failed_product(
					$line,
					$file_name,
					'missing_workflow',
					__( 'Internal products require a make workflow.', 'order-machine' )
				);
			}
			$workflow_id = 0;
		} else {
			$workflow_id = self::resolve_id(
				$workflow_name,
				isset( $refs['make'] ) ? $refs['make'] : array(),
				'unknown_workflow',
				__( 'Unknown workflow.', 'order-machine' ),
				'ambiguous_workflow',
				__( 'Workflow name matches more than one make template.', 'order-machine' )
			);
			if ( is_wp_error( $workflow_id ) ) {
				return self::failed_product( $line, $file_name, $workflow_id->get_error_code(), $workflow_id->get_error_message() );
			}
		}

		$post_ship_id = self::optional_ref(
			SOM_Catalog_Import::cell( $first, $index, 'post_ship_workflow_name' ),
			isset( $refs['post_ship'] ) ? $refs['post_ship'] : array(),
			'unknown_post_ship',
			__( 'Unknown Post-ship workflow.', 'order-machine' ),
			'ambiguous_post_ship',
			__( 'Post-ship workflow name matches more than one template.', 'order-machine' )
		);
		if ( is_wp_error( $post_ship_id ) ) {
			return self::failed_product( $line, $file_name, $post_ship_id->get_error_code(), $post_ship_id->get_error_message() );
		}

		$package_id = self::optional_ref(
			SOM_Catalog_Import::cell( $first, $index, 'package_name' ),
			isset( $refs['packages'] ) ? $refs['packages'] : array(),
			'unknown_package',
			__( 'Unknown shipping package.', 'order-machine' ),
			'ambiguous_package',
			__( 'Package name matches more than one package.', 'order-machine' )
		);
		if ( is_wp_error( $package_id ) ) {
			return self::failed_product( $line, $file_name, $package_id->get_error_code(), $package_id->get_error_message() );
		}

		$package_multi_id = self::optional_ref(
			SOM_Catalog_Import::cell( $first, $index, 'package_name_multi' ),
			isset( $refs['packages'] ) ? $refs['packages'] : array(),
			'unknown_package_multi',
			__( 'Unknown multi-pack package.', 'order-machine' ),
			'ambiguous_package_multi',
			__( 'Multi-pack package name matches more than one package.', 'order-machine' )
		);
		if ( is_wp_error( $package_multi_id ) ) {
			return self::failed_product( $line, $file_name, $package_multi_id->get_error_code(), $package_multi_id->get_error_message() );
		}

		$price = self::optional_decimal( SOM_Catalog_Import::cell( $first, $index, 'target_selling_price' ) );
		if ( null === $price ) {
			return self::failed_product( $line, $file_name, 'bad_price', __( 'Target selling price must be a number.', 'order-machine' ) );
		}

		$weight = self::optional_decimal( SOM_Catalog_Import::cell( $first, $index, 'weight_grams' ) );
		if ( null === $weight ) {
			return self::failed_product( $line, $file_name, 'bad_weight', __( 'Goods weight must be a number of grams.', 'order-machine' ) );
		}

		$shipping = self::optional_decimal( SOM_Catalog_Import::cell( $first, $index, 'planned_shipping_gbp' ) );
		if ( null === $shipping ) {
			return self::failed_product( $line, $file_name, 'bad_shipping', __( 'Planned shipping must be a number.', 'order-machine' ) );
		}

		$recipe = self::build_recipe( $records, $index, isset( $refs['materials'] ) ? $refs['materials'] : array() );
		if ( is_wp_error( $recipe ) ) {
			return self::failed_product( $line, $file_name, $recipe->get_error_code(), $recipe->get_error_message() );
		}

		return array(
			'row'       => $line,
			'file_name' => $file_name,
			'save_name' => '',
			'message'   => '',
			'warning'   => '',
			'code'      => '',
			'ok'        => true,
			'data'      => array(
				'name'                           => $file_name,
				'sku'                            => $sku,
				'is_internal'                    => true === $internal ? 1 : 0,
				'workflow_template_id'           => $workflow_id > 0 ? (int) $workflow_id : '',
				'post_ship_workflow_template_id' => $post_ship_id > 0 ? (int) $post_ship_id : '',
				'target_selling_price'           => $price,
				'weight_grams'                   => $weight,
				'package_id'                     => $package_id > 0 ? (int) $package_id : '',
				'package_id_multi'               => $package_multi_id > 0 ? (int) $package_multi_id : '',
				'planned_shipping_gbp'           => $shipping,
				'is_active'                      => null === $active ? 1 : (int) $active,
				'recipe'                         => $recipe,
			),
		);
	}

	/**
	 * Recipe lines for one product. An all-blank line is a product with no recipe.
	 *
	 * @param array<int, array<string, mixed>> $records   Group rows.
	 * @param array<string, int>               $index     Header index.
	 * @param array<string, int[]>             $materials Material name index.
	 * @return array<int, array{material_id: int, quantity_per_unit: string}>|WP_Error
	 */
	private static function build_recipe( array $records, array $index, array $materials ) {
		$recipe    = array();
		$seen      = array();
		$any_line  = false;
		$any_blank = false;

		foreach ( $records as $item ) {
			$record        = isset( $item['record'] ) && is_array( $item['record'] ) ? $item['record'] : array();
			$material_name = trim( sanitize_text_field( SOM_Catalog_Import::cell( $record, $index, 'material_name' ) ) );
			$qty_raw       = SOM_Catalog_Import::cell( $record, $index, 'quantity_per_unit' );

			if ( '' === $material_name && '' === $qty_raw ) {
				$any_blank = true;
				continue;
			}
			$any_line = true;

			if ( '' === $material_name ) {
				return new WP_Error(
					'missing_material',
					__( 'Material name is required when quantity is set.', 'order-machine' )
				);
			}
			if ( '' === $qty_raw ) {
				return new WP_Error(
					'missing_quantity',
					__( 'Quantity per unit is required when a material is set.', 'order-machine' )
				);
			}

			$qty = self::quantity( $qty_raw );
			if ( null === $qty ) {
				return new WP_Error(
					'bad_quantity',
					__( 'Quantity per unit must be greater than 0.', 'order-machine' )
				);
			}

			$material_id = self::resolve_id(
				$material_name,
				$materials,
				'unknown_material',
				__( 'Unknown material.', 'order-machine' ),
				'ambiguous_material',
				__( 'Material name matches more than one material.', 'order-machine' )
			);
			if ( is_wp_error( $material_id ) ) {
				return $material_id;
			}
			if ( $material_id < 1 ) {
				return new WP_Error( 'unknown_material', __( 'Unknown material.', 'order-machine' ) );
			}
			if ( isset( $seen[ $material_id ] ) ) {
				return new WP_Error(
					'som_recipe_duplicate',
					__( 'Each material can only appear once in a recipe.', 'order-machine' )
				);
			}

			$seen[ $material_id ] = true;
			$recipe[]             = array(
				'material_id'       => (int) $material_id,
				'quantity_per_unit' => $qty,
			);
		}

		if ( $any_line && $any_blank ) {
			return new WP_Error(
				'incomplete_recipe',
				__( 'A recipe line needs a material and a quantity, or both cells empty.', 'order-machine' )
			);
		}

		if ( $recipe ) {
			$ids   = array();
			foreach ( $recipe as $line ) {
				$ids[] = (int) $line['material_id'];
			}
			$graph = SOM_Products::validate_recipe_nesting( 0, $ids );
			if ( is_wp_error( $graph ) ) {
				return $graph;
			}
		}

		return $recipe;
	}

	/**
	 * Blank stays unset. A filled name must match exactly one record.
	 *
	 * @param string               $raw              Cell text.
	 * @param array<string, int[]> $index            Name index.
	 * @param string               $unknown_code     Error code.
	 * @param string               $unknown_message  Error text.
	 * @param string               $ambiguous_code   Error code.
	 * @param string               $ambiguous_message Error text.
	 * @return int|WP_Error 0 when blank.
	 */
	private static function optional_ref( $raw, array $index, $unknown_code, $unknown_message, $ambiguous_code, $ambiguous_message ) {
		$name = trim( sanitize_text_field( (string) $raw ) );
		if ( '' === $name ) {
			return 0;
		}
		return self::resolve_id( $name, $index, $unknown_code, $unknown_message, $ambiguous_code, $ambiguous_message );
	}

	/**
	 * @param string               $name              Display name.
	 * @param array<string, int[]> $index             Name index.
	 * @param string               $unknown_code      Error code.
	 * @param string               $unknown_message   Error text.
	 * @param string               $ambiguous_code    Error code.
	 * @param string               $ambiguous_message Error text.
	 * @return int|WP_Error
	 */
	private static function resolve_id( $name, array $index, $unknown_code, $unknown_message, $ambiguous_code, $ambiguous_message ) {
		$key = SOM_Catalog_Import::name_key( $name );
		$ids = ( '' !== $key && isset( $index[ $key ] ) ) ? $index[ $key ] : array();
		if ( ! $ids ) {
			return new WP_Error( $unknown_code, $unknown_message );
		}
		if ( count( $ids ) > 1 ) {
			return new WP_Error( $ambiguous_code, $ambiguous_message );
		}
		return (int) $ids[0];
	}

	/**
	 * @return array<string, array<string, int[]>>
	 */
	private static function reference_index() {
		return array(
			'materials' => self::ids_by_name( 'materials' ),
			'make'      => self::ids_by_name( 'workflow_templates', 'make' ),
			'post_ship' => self::ids_by_name( 'workflow_templates', 'post_ship' ),
			'packages'  => self::ids_by_name( 'shipping_packages' ),
		);
	}

	/**
	 * @param string $table materials|workflow_templates|shipping_packages.
	 * @param string $kind  Workflow kind, or empty.
	 * @return array<string, int[]>
	 */
	private static function ids_by_name( $table, $kind = '' ) {
		global $wpdb;

		$allowed = array( 'materials', 'workflow_templates', 'shipping_packages' );
		if ( ! in_array( $table, $allowed, true ) ) {
			return array();
		}

		$sql = 'SELECT id, name FROM ' . SOM_DB::table( $table );
		if ( '' !== $kind && 'workflow_templates' === $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is allowlisted; kind is prepared.
			$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' WHERE kind = %s', $kind ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is allowlisted, no user input.
			$rows = $wpdb->get_results( $sql );
		}
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$index = array();
		foreach ( $rows as $row ) {
			$key = SOM_Catalog_Import::name_key( isset( $row->name ) ? (string) $row->name : '' );
			if ( '' === $key ) {
				continue;
			}
			if ( ! isset( $index[ $key ] ) ) {
				$index[ $key ] = array();
			}
			$index[ $key ][] = (int) $row->id;
		}
		return $index;
	}

	/**
	 * SKUs already stored, keyed for a case-insensitive match.
	 *
	 * Listing import matches SKU with the column collation, which treats capitals
	 * as the same SKU. A blank SKU is not a match.
	 *
	 * @return array<string, bool>
	 */
	private static function load_sku_keys() {
		global $wpdb;

		$sql  = 'SELECT sku FROM ' . SOM_DB::table( 'products' ) . " WHERE sku IS NOT NULL AND sku <> ''";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from SOM_DB::table, no user input.
		$rows = $wpdb->get_col( $sql );
		$keys = array();
		if ( ! is_array( $rows ) ) {
			return $keys;
		}
		foreach ( $rows as $sku ) {
			$key = self::sku_key( (string) $sku );
			if ( '' !== $key ) {
				$keys[ $key ] = true;
			}
		}
		return $keys;
	}

	/**
	 * @param string $sku SKU.
	 * @return string
	 */
	private static function sku_key( $sku ) {
		return SOM_Catalog_Import::name_key( $sku );
	}

	/**
	 * Delete a product row left behind when create() did not return its id.
	 *
	 * @param string $name Allocated product name.
	 * @return void
	 */
	private static function abandon_named( $name ) {
		global $wpdb;

		$name = trim( sanitize_text_field( (string) $name ) );
		if ( '' === $name ) {
			return;
		}

		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE name = %s ORDER BY id DESC LIMIT 1',
				$name
			)
		);
		if ( $id < 1 ) {
			return;
		}
		SOM_Products::delete_failed_import( $id );
	}

	/**
	 * @param array<int, string|null> $record Cells.
	 * @param array<string, int>      $index  Header index.
	 * @param string                  $column Column name.
	 * @return string
	 */
	private static function repeated_value( array $record, array $index, $column ) {
		$raw = SOM_Catalog_Import::cell( $record, $index, $column );
		if ( in_array( $column, array( 'is_internal', 'is_active' ), true ) ) {
			return strtolower( $raw );
		}
		if ( in_array( $column, array( 'name', 'sku', 'workflow_name', 'post_ship_workflow_name', 'package_name', 'package_name_multi' ), true ) ) {
			return trim( sanitize_text_field( $raw ) );
		}
		return $raw;
	}

	/**
	 * @param string $column Column name.
	 * @return string
	 */
	private static function column_label( $column ) {
		$labels = array(
			'name'                     => __( 'Name', 'order-machine' ),
			'sku'                      => __( 'SKU', 'order-machine' ),
			'is_internal'              => __( 'Internal', 'order-machine' ),
			'workflow_name'            => __( 'Workflow', 'order-machine' ),
			'post_ship_workflow_name'  => __( 'Post-ship workflow', 'order-machine' ),
			'target_selling_price'     => __( 'Target selling price', 'order-machine' ),
			'weight_grams'             => __( 'Goods weight', 'order-machine' ),
			'package_name'             => __( 'Package', 'order-machine' ),
			'package_name_multi'       => __( 'Multi-pack package', 'order-machine' ),
			'planned_shipping_gbp'     => __( 'Planned shipping', 'order-machine' ),
			'is_active'                => __( 'Active', 'order-machine' ),
		);
		return isset( $labels[ $column ] ) ? $labels[ $column ] : (string) $column;
	}

	/**
	 * Blank stays blank. A filled cell must be zero or positive and fit decimal(10,2).
	 *
	 * @param string $raw Cell text.
	 * @return string|null Empty string when blank, formatted number, or null when invalid.
	 */
	private static function optional_decimal( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( ! is_numeric( $raw ) || (float) $raw < 0 ) {
			return null;
		}
		$formatted = number_format( (float) $raw, 2, '.', '' );
		if ( (float) $formatted >= 100000000 ) {
			return null;
		}
		return $formatted;
	}

	/**
	 * Quantity greater than 0, stored to 2 decimal places.
	 *
	 * @param string $raw Cell text.
	 * @return string|null
	 */
	private static function quantity( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw || ! is_numeric( $raw ) || (float) $raw <= 0 ) {
			return null;
		}
		$formatted = number_format( (float) $raw, 2, '.', '' );
		if ( (float) $formatted <= 0 || (float) $formatted >= 100000000 ) {
			return null;
		}
		return $formatted;
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
	 * @param int    $line      1-based file row.
	 * @param string $file_name Product name from the file.
	 * @param string $code      Error code.
	 * @param string $message   Error text.
	 * @return array<string, mixed>
	 */
	private static function failed_product( $line, $file_name, $code, $message ) {
		return array(
			'row'       => (int) $line,
			'file_name' => (string) $file_name,
			'save_name' => '',
			'message'   => (string) $message,
			'warning'   => '',
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
