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
		if ( ! defined( 'SOM_USE_DUMMY_CREDENTIALS' ) || ! SOM_USE_DUMMY_CREDENTIALS ) {
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

		self::ensure_listing( $product_id, 'ebay', self::EBAY_LISTING_ID, 12.99 );
		self::ensure_listing( $product_id, 'ebay', self::SAMPLE_PRODUCT_SKU, 12.99 );
		self::ensure_listing( $product_id, 'etsy', self::ETSY_LISTING_ID, 14.99 );

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

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$table,
			array(
				'name'                => $name,
				'unit'                => $unit,
				'current_stock'       => $stock,
				'low_stock_threshold' => $threshold,
				'unit_cost'           => $cost,
				'is_active'           => 1,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%s', '%s', '%f', '%f', '%f', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int    $product_id Product PK.
	 * @param string $channel_slug ebay|etsy.
	 * @param string $external_id  Listing / SKU key stored in listings.
	 * @param float  $price        Sample price.
	 * @return void
	 */
	private static function ensure_listing( $product_id, $channel_slug, $external_id, $price ) {
		global $wpdb;

		$channel = SOM_Channels::get_by_slug( $channel_slug );
		if ( ! $channel ) {
			return;
		}

		$listings   = SOM_DB::table( 'listings' );
		$channel_id = (int) $channel->id;

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$listings} WHERE channel_id = %d AND external_listing_id = %s LIMIT 1",
				$channel_id,
				$external_id
			)
		);

		if ( $existing ) {
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
				'price'               => $price,
				'quantity_available'  => 10,
				'last_synced_at'      => null,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%s', '%f', '%d', '%s', '%s', '%s' )
		);
		$wpdb->suppress_errors( false );
	}
}