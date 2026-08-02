<?php
/**
 * Dev/seed helpers (wp-env dummy credentials + sample catalogue).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads non-functional dummy OAuth payloads and small product/listing seed data
 * when `SOM_USE_DUMMY_CREDENTIALS` is true.
 */
class SOM_Seed {

	/** Sample product SKU used by fixtures for matched lines. */
	const SAMPLE_PRODUCT_SKU = 'BIN-SET-4PK';

	/** eBay legacyItemId in ebay-orders.json (matched). */
	const EBAY_LISTING_ID = '110000000001';

	/** Etsy listing_id in etsy-orders.json (matched). */
	const ETSY_LISTING_ID = '220000000001';

	/** eBay multi-variation sample listing (Sprint 10 fixtures). */
	const EBAY_VARIATION_LISTING_ID = '110000000002';

	/**
	 * One-shot bypass for maybe_seed_catalogue dummy gate (restore path).
	 *
	 * @var bool
	 */
	private static $force_seed = false;

	/**
	 * Ensure channel rows + dummy encrypted credentials (idempotent).
	 *
	 * @return void
	 */
	public static function maybe_load_dummy_credentials() {
		if ( ! defined( 'SOM_USE_DUMMY_CREDENTIALS' ) || ! SOM_USE_DUMMY_CREDENTIALS ) {
			return;
		}

		SOM_Channels::ensure_rows();

		foreach ( array( 'ebay', 'etsy' ) as $slug ) {
			$existing = SOM_Channels::get_credentials( $slug );
			if ( ! empty( $existing['access_token'] ) && empty( $existing['dummy'] ) ) {
				// Real tokens present — do not overwrite.
				continue;
			}
			if ( ! empty( $existing['dummy'] ) && ! empty( $existing['access_token'] ) ) {
				continue;
			}

			$payload = array(
				'access_token'  => 'dummy-access-' . $slug,
				'refresh_token' => 'dummy-refresh-' . $slug,
				'token_type'    => 'Bearer',
				'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS ),
				'expires_in'    => YEAR_IN_SECONDS,
				'dummy'         => true,
			);

			if ( 'etsy' === $slug ) {
				$payload['shop_id'] = '0';
			}

			if ( 'ebay' === $slug ) {
				$payload['environment'] = 'sandbox';
			}

			SOM_Channels::save_credentials( $slug, $payload );
		}

		self::maybe_seed_catalogue();
	}

	/**
	 * Seed one sample product + matched listings for fixture order sync demos.
	 *
	 * @return void
	 */
	public static function maybe_seed_catalogue() {
		if ( ! self::$force_seed && ( ! defined( 'SOM_USE_DUMMY_CREDENTIALS' ) || ! SOM_USE_DUMMY_CREDENTIALS ) ) {
			return;
		}

		global $wpdb;

		$products   = SOM_DB::table( 'products' );
		$now        = current_time( 'mysql', true );
		$product_id = (int) get_option( 'som_seed_product_id', 0 );

		if ( $product_id > 0 ) {
			$still_there = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$products} WHERE id = %d LIMIT 1",
					$product_id
				)
			);
			if ( ! $still_there ) {
				$product_id = 0;
				delete_option( 'som_seed_product_id' );
			}
		}

		if ( $product_id < 1 ) {
			$product_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$products} WHERE sku = %s ORDER BY id ASC LIMIT 1",
					self::SAMPLE_PRODUCT_SKU
				)
			);
		}

		if ( $product_id < 1 ) {
			$wpdb->insert(
				$products,
				array(
					'name'                 => 'Bin Sticker Set — 100x140mm 4-pack (sample)',
					'sku'                  => self::SAMPLE_PRODUCT_SKU,
					'workflow_template_id' => null,
					'is_active'            => 1,
					'created_at'           => $now,
					'updated_at'           => $now,
				),
				array( '%s', '%s', '%s', '%d', '%s', '%s' )
			);
			$product_id = (int) $wpdb->insert_id;
			if ( $product_id > 0 ) {
				update_option( 'som_seed_product_id', $product_id, false );
			}
		} else {
			update_option( 'som_seed_product_id', $product_id, false );
		}

		if ( $product_id < 1 ) {
			return;
		}

		self::ensure_listing(
			$product_id,
			'ebay',
			self::EBAY_LISTING_ID,
			12.99,
			array(
				'title'       => 'Bin Sticker Set — 100x140mm 4-pack',
				'description' => 'Waterproof vinyl bin stickers with laminate. Set of 4.',
				'inventory'   => array(
					'mode'       => 'flat',
					'sku'        => self::SAMPLE_PRODUCT_SKU,
					'variations' => array(),
				),
			)
		);
		self::ensure_listing(
			$product_id,
			'ebay',
			self::SAMPLE_PRODUCT_SKU,
			12.99,
			array(
				'title'       => 'Bin Sticker Set — 100x140mm 4-pack (SKU key)',
				'description' => 'Same listing keyed by SKU for order matching.',
				'inventory'   => array(
					'mode'       => 'flat',
					'sku'        => self::SAMPLE_PRODUCT_SKU,
					'variations' => array(),
				),
			)
		);
		self::ensure_listing(
			$product_id,
			'ebay',
			self::EBAY_VARIATION_LISTING_ID,
			13.50,
			array(
				'title'               => 'Bin Stickers — Colour variations',
				'description'         => 'Multi-SKU eBay inventory sample.',
				'quantity_available'  => 18,
				'inventory'           => array(
					'mode'       => 'variations',
					'sku'        => 'BIN-VAR-NAVY',
					'variations' => array(
						array(
							'sku'      => 'BIN-VAR-NAVY',
							'quantity' => 8,
							'options'  => array( 'Colour' => 'Navy' ),
							'price'    => 13.50,
						),
						array(
							'sku'      => 'BIN-VAR-SAGE',
							'quantity' => 6,
							'options'  => array( 'Colour' => 'Sage' ),
							'price'    => 13.50,
						),
						array(
							'sku'      => 'BIN-VAR-TERRACOTTA',
							'quantity' => 4,
							'options'  => array( 'Colour' => 'Terracotta' ),
							'price'    => 13.50,
						),
					),
				),
			)
		);
		self::ensure_listing(
			$product_id,
			'etsy',
			self::ETSY_LISTING_ID,
			14.99,
			array(
				'title'              => 'Personalised Bin Sticker Set',
				'description'        => 'Etsy listing with size variations.',
				'quantity_available' => 15,
				'inventory'          => array(
					'mode'       => 'variations',
					'sku'        => '',
					'variations' => array(
						array(
							'sku'         => 'ETSY-BIN-S',
							'quantity'    => 5,
							'options'     => array( 'Size' => 'Small' ),
							'external_id' => '9001',
							'price'       => 14.99,
						),
						array(
							'sku'         => 'ETSY-BIN-M',
							'quantity'    => 7,
							'options'     => array( 'Size' => 'Medium' ),
							'external_id' => '9002',
							'price'       => 14.99,
						),
						array(
							'sku'         => 'ETSY-BIN-L',
							'quantity'    => 3,
							'options'     => array( 'Size' => 'Large' ),
							'external_id' => '9003',
							'price'       => 16.99,
						),
					),
				),
			)
		);

		self::maybe_seed_materials( $product_id );
		self::maybe_seed_workflow( $product_id );
	}

	/** Seed workflow template name. */
	const WORKFLOW_NAME = 'Bin Sticker Production';

	/**
	 * Seed sample workflow template + steps and assign to the demo product.
	 *
	 * @param int $product_id Product PK.
	 * @return void
	 */
	public static function maybe_seed_workflow( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return;
		}

		global $wpdb;

		$templates_t = SOM_DB::table( 'workflow_templates' );
		$steps_t     = SOM_DB::table( 'workflow_steps' );
		$products_t  = SOM_DB::table( 'products' );

		$template_id = (int) get_option( 'som_seed_workflow_id', 0 );
		if ( $template_id > 0 ) {
			$still = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$templates_t} WHERE id = %d LIMIT 1",
					$template_id
				)
			);
			if ( ! $still ) {
				$template_id = 0;
				delete_option( 'som_seed_workflow_id' );
			}
		}

		if ( $template_id < 1 ) {
			$template_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$templates_t} WHERE name = %s ORDER BY id ASC LIMIT 1",
					self::WORKFLOW_NAME
				)
			);
		}

		$now = current_time( 'mysql', true );

		if ( $template_id < 1 ) {
			$wpdb->insert(
				$templates_t,
				array(
					'name'        => self::WORKFLOW_NAME,
					'description' => 'Sample production workflow for bin sticker sets (seed).',
					'is_active'   => 1,
					'created_at'  => $now,
					'updated_at'  => $now,
				),
				array( '%s', '%s', '%d', '%s', '%s' )
			);
			$template_id = (int) $wpdb->insert_id;
		}

		if ( $template_id < 1 ) {
			return;
		}

		update_option( 'som_seed_workflow_id', $template_id, false );

		$step_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$steps_t} WHERE workflow_template_id = %d",
				$template_id
			)
		);

		if ( $step_count < 1 ) {
			$thankyou = wp_json_encode(
				array(
					'type'   => 'local',
					'action' => 'run_thankyou_card_script',
					'params' => array(),
				)
			);

			$seed_steps = array(
				array( 'Print', 1, null, null ),
				array( 'Dry', 0, 15 * MINUTE_IN_SECONDS, null ),
				array( 'Laminate', 1, null, null ),
				array( 'Cut', 1, null, null ),
				array( 'Pack', 1, null, null ),
				array( 'Ship', 1, null, null ),
				array( 'Thank-you', 0, null, $thankyou ),
				array( 'Review reminder', 1, 7 * DAY_IN_SECONDS, null ),
			);

			$order = 0;
			foreach ( $seed_steps as $row ) {
				++$order;
				$wpdb->insert(
					$steps_t,
					array(
						'workflow_template_id'    => $template_id,
						'step_order'              => $order,
						'name'                    => $row[0],
						'requires_manual_confirm' => $row[1],
						'timer_seconds'           => $row[2],
						'script_config'           => $row[3],
						'created_at'              => $now,
						'updated_at'              => $now,
					),
					array( '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
				);
			}
		}

		$current = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, workflow_template_id FROM {$products_t} WHERE id = %d LIMIT 1",
				$product_id
			)
		);

		if ( $current && empty( $current->workflow_template_id ) ) {
			$wpdb->update(
				$products_t,
				array(
					'workflow_template_id' => $template_id,
					'updated_at'           => $now,
				),
				array( 'id' => $product_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	/** Seed material name: vinyl sheet. */
	const MATERIAL_VINYL = 'A4 Glossy White Vinyl';

	/** Seed material name: laminate sheet. */
	const MATERIAL_LAMINATE = 'A4 Glossy Laminate';

	/**
	 * Seed sample materials + recipe on the demo product.
	 *
	 * @param int $product_id Product PK.
	 * @return void
	 */
	public static function maybe_seed_materials( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id < 1 ) {
			return;
		}

		$vinyl_id    = self::ensure_material( self::MATERIAL_VINYL, 'sheet', 25, 5, 1.25 );
		$laminate_id = self::ensure_material( self::MATERIAL_LAMINATE, 'sheet', 25, 5, 0.85 );

		if ( $vinyl_id < 1 || $laminate_id < 1 ) {
			return;
		}

		global $wpdb;
		$recipe_t = SOM_DB::table( 'product_materials' );
		$count    = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$recipe_t} WHERE product_id = %d",
				$product_id
			)
		);

		if ( $count > 0 ) {
			return;
		}

		$wpdb->insert(
			$recipe_t,
			array(
				'product_id'         => $product_id,
				'material_id'        => $vinyl_id,
				'quantity_per_unit'  => 1.0,
			),
			array( '%d', '%d', '%f' )
		);
		$wpdb->insert(
			$recipe_t,
			array(
				'product_id'         => $product_id,
				'material_id'        => $laminate_id,
				'quantity_per_unit'  => 1.0,
			),
			array( '%d', '%d', '%f' )
		);
	}

	/**
	 * @param string $name      Material name.
	 * @param string $unit      Unit label.
	 * @param float  $stock     Starting stock.
	 * @param float  $threshold Low-stock threshold.
	 * @param float  $cost      Unit cost.
	 * @return int Material ID.
	 */
	private static function ensure_material( $name, $unit, $stock, $threshold, $cost ) {
		global $wpdb;

		$table = SOM_DB::table( 'materials' );
		$id    = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE name = %s ORDER BY id ASC LIMIT 1",
				$name
			)
		);

		if ( $id > 0 ) {
			return $id;
		}

		$now   = current_time( 'mysql', true );
		$value = round( (float) $stock * (float) $cost, 4 );
		$wpdb->insert(
			$table,
			array(
				'name'                 => $name,
				'unit'                 => $unit,
				'current_stock'        => $stock,
				'low_stock_threshold'  => $threshold,
				'unit_cost'            => $cost,
				'total_value_on_hand'  => $value,
				'is_active'            => 1,
				'created_at'           => $now,
				'updated_at'           => $now,
			),
			array( '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $product_id   Product PK.
	 * @param string               $channel_slug ebay|etsy.
	 * @param string               $external_id  Listing / SKU key stored in listings.
	 * @param float                $price        Sample price.
	 * @param array<string, mixed> $extra        Optional title, description, quantity_available, inventory.
	 * @return void
	 */
	private static function ensure_listing( $product_id, $channel_slug, $external_id, $price, array $extra = array() ) {
		global $wpdb;

		$channel = SOM_Channels::get_by_slug( $channel_slug );
		if ( ! $channel ) {
			return;
		}

		$listings   = SOM_DB::table( 'listings' );
		$channel_id = (int) $channel->id;
		$inventory  = isset( $extra['inventory'] ) && is_array( $extra['inventory'] )
			? SOM_Listings::sanitize_inventory( $extra['inventory'] )
			: array(
				'mode'       => 'flat',
				'sku'        => '',
				'variations' => array(),
			);
		$qty = isset( $extra['quantity_available'] )
			? (int) $extra['quantity_available']
			: SOM_Listings::quantity_from_inventory( $inventory, 10 );
		$title       = isset( $extra['title'] ) ? sanitize_text_field( (string) $extra['title'] ) : null;
		$description = isset( $extra['description'] ) ? (string) $extra['description'] : null;

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, product_id, inventory_json FROM {$listings} WHERE channel_id = %d AND external_listing_id = %s LIMIT 1",
				$channel_id,
				$external_id
			)
		);

		if ( $existing ) {
			$fields  = array( 'updated_at' => current_time( 'mysql', true ) );
			$formats = array( '%s' );

			if ( (int) $existing->product_id !== (int) $product_id ) {
				$fields['product_id'] = (int) $product_id;
				$formats[]            = '%d';
			}

			// Backfill Sprint 10 columns when still empty (idempotent enrich).
			if ( empty( $existing->inventory_json ) && ! empty( $extra ) ) {
				$fields['title']              = $title;
				$fields['description']        = $description;
				$fields['price']              = $price;
				$fields['quantity_available'] = $qty;
				$fields['inventory_json']     = wp_json_encode( $inventory );
				$formats                      = array_merge( $formats, array( '%s', '%s', '%f', '%d', '%s' ) );
			}

			$wpdb->update(
				$listings,
				$fields,
				array( 'id' => (int) $existing->id ),
				$formats,
				array( '%d' )
			);
			return;
		}

		$now = current_time( 'mysql', true );
		$wpdb->suppress_errors( true );
		$wpdb->insert(
			$listings,
			array(
				'product_id'          => $product_id,
				'channel_id'          => $channel_id,
				'external_listing_id' => $external_id,
				'title'               => $title,
				'description'         => $description,
				'price'               => $price,
				'quantity_available'  => $qty,
				'inventory_json'      => wp_json_encode( $inventory ),
				'last_synced_at'      => null,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s' )
		);
		$wpdb->suppress_errors( false );
	}

	/**
	 * Whether dummy credential mode is enabled in wp-config.
	 *
	 * @return bool
	 */
	public static function is_dummy_mode() {
		return defined( 'SOM_USE_DUMMY_CREDENTIALS' ) && SOM_USE_DUMMY_CREDENTIALS;
	}

	/**
	 * Resolve known seed entity IDs (product, workflow, materials, listings).
	 *
	 * @return array{
	 *   product_id:int,
	 *   workflow_id:int,
	 *   material_ids:array<int,int>,
	 *   listing_ids:array<int,int>,
	 *   step_ids:array<int,int>
	 * }
	 */
	public static function resolve_seed_ids() {
		global $wpdb;

		$product_id = (int) get_option( 'som_seed_product_id', 0 );
		if ( $product_id < 1 ) {
			$product_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE sku = %s ORDER BY id ASC LIMIT 1',
					self::SAMPLE_PRODUCT_SKU
				)
			);
		}

		$workflow_id = (int) get_option( 'som_seed_workflow_id', 0 );
		if ( $workflow_id < 1 ) {
			$workflow_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE name = %s ORDER BY id ASC LIMIT 1',
					self::WORKFLOW_NAME
				)
			);
		}

		$material_ids = array();
		foreach ( array( self::MATERIAL_VINYL, self::MATERIAL_LAMINATE ) as $name ) {
			$id = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . SOM_DB::table( 'materials' ) . ' WHERE name = %s ORDER BY id ASC LIMIT 1',
					$name
				)
			);
			if ( $id > 0 ) {
				$material_ids[] = $id;
			}
		}

		$listing_ids = array();
		$externals   = array(
			self::EBAY_LISTING_ID,
			self::SAMPLE_PRODUCT_SKU,
			self::EBAY_VARIATION_LISTING_ID,
			self::ETSY_LISTING_ID,
		);
		foreach ( $externals as $ext ) {
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT id FROM ' . SOM_DB::table( 'listings' ) . ' WHERE external_listing_id = %s',
					$ext
				)
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $lid ) {
					$listing_ids[] = (int) $lid;
				}
			}
		}
		$listing_ids = array_values( array_unique( array_filter( $listing_ids ) ) );

		$step_ids = array();
		if ( $workflow_id > 0 ) {
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT id FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE workflow_template_id = %d',
					$workflow_id
				)
			);
			if ( is_array( $rows ) ) {
				$step_ids = array_map( 'intval', $rows );
			}
		}

		return array(
			'product_id'   => $product_id,
			'workflow_id'  => $workflow_id,
			'material_ids' => $material_ids,
			'listing_ids'  => $listing_ids,
			'step_ids'     => $step_ids,
		);
	}

	/**
	 * Remove demo seed catalogue, related orders/progress/stock, and dummy channel tokens.
	 *
	 * Does not delete user-created products, suppliers, or purchase orders.
	 * Does not drop batch_groups.
	 *
	 * @return array<string,int|string> Summary counts + message.
	 */
	public static function remove_seed_data() {
		global $wpdb;

		$ids     = self::resolve_seed_ids();
		$summary = array(
			'orders'      => 0,
			'listings'    => 0,
			'products'    => 0,
			'materials'   => 0,
			'workflows'   => 0,
			'disconnected'=> 0,
		);

		$order_ids = self::collect_seed_related_order_ids( $ids );
		$summary['orders'] = self::delete_orders_cascade( $order_ids );

		// Listings.
		foreach ( $ids['listing_ids'] as $listing_id ) {
			$wpdb->delete( SOM_DB::table( 'listings' ), array( 'id' => $listing_id ), array( '%d' ) );
			++$summary['listings'];
		}

		// Goals on seed workflow.
		if ( $ids['workflow_id'] > 0 ) {
			$wpdb->delete(
				SOM_DB::table( 'workflow_material_goals' ),
				array( 'workflow_template_id' => $ids['workflow_id'] ),
				array( '%d' )
			);
		}

		// Recipe then product.
		if ( $ids['product_id'] > 0 ) {
			$wpdb->delete(
				SOM_DB::table( 'product_materials' ),
				array( 'product_id' => $ids['product_id'] ),
				array( '%d' )
			);
			$wpdb->delete( SOM_DB::table( 'products' ), array( 'id' => $ids['product_id'] ), array( '%d' ) );
			$summary['products'] = 1;
		}

		// Workflow steps then template (progress already cleared with orders).
		if ( $ids['workflow_id'] > 0 ) {
			if ( ! empty( $ids['step_ids'] ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query(
					'DELETE FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE workflow_template_id = ' . (int) $ids['workflow_id']
				);
			}
			$wpdb->delete(
				SOM_DB::table( 'workflow_templates' ),
				array( 'id' => $ids['workflow_id'] ),
				array( '%d' )
			);
			$summary['workflows'] = 1;
		}

		// Materials (only if unused by other recipes / POs).
		foreach ( $ids['material_ids'] as $material_id ) {
			$in_recipe = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . SOM_DB::table( 'product_materials' ) . ' WHERE material_id = %d',
					$material_id
				)
			);
			$in_po = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . SOM_DB::table( 'purchase_order_items' ) . ' WHERE material_id = %d',
					$material_id
				)
			);
			if ( $in_recipe > 0 || $in_po > 0 ) {
				continue;
			}
			$wpdb->delete(
				SOM_DB::table( 'material_stock_log' ),
				array( 'material_id' => $material_id ),
				array( '%d' )
			);
			$wpdb->delete(
				SOM_DB::table( 'workflow_material_goals' ),
				array( 'material_id' => $material_id ),
				array( '%d' )
			);
			$wpdb->delete( SOM_DB::table( 'materials' ), array( 'id' => $material_id ), array( '%d' ) );
			++$summary['materials'];
		}

		// Clear dummy channel credentials only (leave real OAuth alone).
		foreach ( array( 'ebay', 'etsy' ) as $slug ) {
			$creds = SOM_Channels::get_credentials( $slug );
			if ( ! empty( $creds['dummy'] ) ) {
				SOM_Channels::disconnect( $slug );
				++$summary['disconnected'];
			}
		}

		delete_option( 'som_seed_product_id' );
		delete_option( 'som_seed_workflow_id' );

		$summary['message'] = sprintf(
			/* translators: 1: orders, 2: listings, 3: products, 4: materials, 5: workflows */
			__( 'Removed seed data: %1$d orders, %2$d listings, %3$d product(s), %4$d material(s), %5$d workflow(s). Dummy channel credentials disconnected where present.', 'order-machine' ),
			(int) $summary['orders'],
			(int) $summary['listings'],
			(int) $summary['products'],
			(int) $summary['materials'],
			(int) $summary['workflows']
		);

		return $summary;
	}

	/**
	 * Re-create dummy credentials + sample catalogue (requires SOM_USE_DUMMY_CREDENTIALS).
	 *
	 * @param bool $remove_first When true, run remove_seed_data() first for a clean reseed.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function restore_seed_data( $remove_first = true ) {
		if ( ! self::is_dummy_mode() ) {
			return new WP_Error(
				'som_seed_dummy_required',
				__( 'Restore seed requires SOM_USE_DUMMY_CREDENTIALS in wp-config.php.', 'order-machine' )
			);
		}

		$removed = null;
		if ( $remove_first ) {
			$removed = self::remove_seed_data();
		}

		// Force dummy credentials even if rows were disconnected.
		SOM_Channels::ensure_rows();
		foreach ( array( 'ebay', 'etsy' ) as $slug ) {
			$payload = array(
				'access_token'  => 'dummy-access-' . $slug,
				'refresh_token' => 'dummy-refresh-' . $slug,
				'token_type'    => 'Bearer',
				'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS ),
				'expires_in'    => YEAR_IN_SECONDS,
				'dummy'         => true,
			);
			if ( 'etsy' === $slug ) {
				$payload['shop_id'] = '0';
			}
			if ( 'ebay' === $slug ) {
				$payload['environment'] = 'sandbox';
			}
			SOM_Channels::save_credentials( $slug, $payload );
		}

		self::seed_catalogue_now();
		if ( class_exists( 'SOM_Batch_Groups' ) ) {
			SOM_Batch_Groups::ensure_rows();
			SOM_Batch_Groups::convert_thankyou_steps();
		}

		$ids = self::resolve_seed_ids();
		return array(
			'removed'     => $removed,
			'product_id'  => $ids['product_id'],
			'workflow_id' => $ids['workflow_id'],
			'message'     => __( 'Seed catalogue and dummy channel credentials restored. Use Sync now to reload fixture orders.', 'order-machine' ),
		);
	}

	/**
	 * Seed catalogue without the dummy-mode gate (caller must check policy).
	 *
	 * @return void
	 */
	public static function seed_catalogue_now() {
		self::$force_seed = true;
		self::maybe_seed_catalogue();
		self::$force_seed = false;
	}

	/**
	 * Order IDs tied to seed product, seed workflow steps, or dummy ebay/etsy channels.
	 *
	 * @param array<string,mixed> $ids From resolve_seed_ids().
	 * @return array<int,int>
	 */
	private static function collect_seed_related_order_ids( array $ids ) {
		global $wpdb;

		$order_ids = array();
		$orders_t  = SOM_DB::table( 'orders' );
		$items_t   = SOM_DB::table( 'order_items' );

		if ( $ids['product_id'] > 0 ) {
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT order_id FROM {$items_t} WHERE product_id = %d",
					$ids['product_id']
				)
			);
			if ( is_array( $rows ) ) {
				$order_ids = array_merge( $order_ids, array_map( 'intval', $rows ) );
			}
		}

		if ( ! empty( $ids['step_ids'] ) ) {
			$in = implode( ',', array_map( 'intval', $ids['step_ids'] ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_col(
				"SELECT DISTINCT id FROM {$orders_t} WHERE current_step_id IN ({$in})"
			);
			if ( is_array( $rows ) ) {
				$order_ids = array_merge( $order_ids, array_map( 'intval', $rows ) );
			}
			$progress_t = SOM_DB::table( 'order_step_progress' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_col(
				"SELECT DISTINCT order_id FROM {$progress_t} WHERE workflow_step_id IN ({$in})"
			);
			if ( is_array( $rows ) ) {
				$order_ids = array_merge( $order_ids, array_map( 'intval', $rows ) );
			}
		}

		foreach ( array( 'ebay', 'etsy' ) as $slug ) {
			$creds = SOM_Channels::get_credentials( $slug );
			if ( empty( $creds['dummy'] ) ) {
				continue;
			}
			$channel = SOM_Channels::get_by_slug( $slug );
			if ( ! $channel ) {
				continue;
			}
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$orders_t} WHERE channel_id = %d",
					(int) $channel->id
				)
			);
			if ( is_array( $rows ) ) {
				$order_ids = array_merge( $order_ids, array_map( 'intval', $rows ) );
			}
		}

		return array_values( array_unique( array_filter( $order_ids ) ) );
	}

	/**
	 * Delete orders and dependent rows.
	 *
	 * @param array<int,int> $order_ids Order PKs.
	 * @return int Number of orders deleted.
	 */
	private static function delete_orders_cascade( array $order_ids ) {
		global $wpdb;

		$order_ids = array_values( array_unique( array_filter( array_map( 'intval', $order_ids ) ) ) );
		if ( empty( $order_ids ) ) {
			return 0;
		}

		$in = implode( ',', $order_ids );

		$batch_items_t = SOM_DB::table( 'step_batch_items' );
		$batches_t     = SOM_DB::table( 'step_batches' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$batch_ids = $wpdb->get_col( "SELECT DISTINCT batch_id FROM {$batch_items_t} WHERE order_id IN ({$in})" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$batch_items_t} WHERE order_id IN ({$in})" );
		if ( is_array( $batch_ids ) ) {
			foreach ( $batch_ids as $batch_id ) {
				$batch_id = (int) $batch_id;
				$left     = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$batch_items_t} WHERE batch_id = %d",
						$batch_id
					)
				);
				if ( 0 === $left ) {
					$wpdb->delete( $batches_t, array( 'id' => $batch_id ), array( '%d' ) );
				}
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . SOM_DB::table( 'order_step_progress' ) . " WHERE order_id IN ({$in})" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . SOM_DB::table( 'material_stock_log' ) . " WHERE order_id IN ({$in})" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . SOM_DB::table( 'order_items' ) . " WHERE order_id IN ({$in})" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . SOM_DB::table( 'orders' ) . " WHERE id IN ({$in})" );

		return count( $order_ids );
	}
}