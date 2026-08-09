<?php
/**
 * Channel fee estimate components CRUD and default seed (Update Package 3 / Sprint 1).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Domain helpers for `wp_som_channel_fee_estimates`.
 *
 * Tier bands use half-open intervals: min inclusive, max exclusive.
 * NULL/NULL min+max means the component always applies.
 */
class SOM_Channel_Fee_Estimates {

	/**
	 * @param int $id Estimate PK.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = SOM_DB::table( 'channel_fee_estimates' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", (int) $id )
		);

		return $row ? $row : null;
	}

	/**
	 * All estimate rows, optionally filtered by channel.
	 *
	 * @param int|null $channel_id Channel PK or null for all.
	 * @return array<int, object>
	 */
	public static function list_all( $channel_id = null ) {
		global $wpdb;

		$table = SOM_DB::table( 'channel_fee_estimates' );

		if ( null !== $channel_id && (int) $channel_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE channel_id = %d
					ORDER BY fee_component ASC, order_value_min IS NOT NULL ASC, order_value_min ASC, id ASC",
					(int) $channel_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				"SELECT * FROM {$table}
				ORDER BY channel_id ASC, fee_component ASC, order_value_min IS NOT NULL ASC, order_value_min ASC, id ASC"
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Rows grouped by channel_id.
	 *
	 * @return array<int, array<int, object>> channel_id => rows
	 */
	public static function list_grouped_by_channel() {
		$grouped = array();
		foreach ( self::list_all() as $row ) {
			$cid = (int) $row->channel_id;
			if ( ! isset( $grouped[ $cid ] ) ) {
				$grouped[ $cid ] = array();
			}
			$grouped[ $cid ][] = $row;
		}
		return $grouped;
	}

	/**
	 * Create an estimate component row.
	 *
	 * @param array<string, mixed> $data Fields.
	 * @return int|WP_Error New ID.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$parsed = self::parse_fields( $data );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert(
			SOM_DB::table( 'channel_fee_estimates' ),
			array(
				'channel_id'      => $parsed['channel_id'],
				'fee_component'   => $parsed['fee_component'],
				'rate_type'       => $parsed['rate_type'],
				'rate_value'      => $parsed['rate_value'],
				'order_value_min' => $parsed['order_value_min'],
				'order_value_max' => $parsed['order_value_max'],
				'is_enabled'      => $parsed['is_enabled'],
				'notes'           => $parsed['notes'],
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_fee_estimate_insert', __( 'Could not create fee estimate.', 'order-machine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an estimate component row.
	 *
	 * @param int                  $id   Estimate PK.
	 * @param array<string, mixed> $data Fields.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$id  = (int) $id;
		$row = self::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'som_fee_estimate_missing', __( 'Fee estimate not found.', 'order-machine' ) );
		}

		$merged = array(
			'channel_id'      => array_key_exists( 'channel_id', $data ) ? $data['channel_id'] : $row->channel_id,
			'fee_component'   => array_key_exists( 'fee_component', $data ) ? $data['fee_component'] : $row->fee_component,
			'rate_type'       => array_key_exists( 'rate_type', $data ) ? $data['rate_type'] : $row->rate_type,
			'rate_value'      => array_key_exists( 'rate_value', $data ) ? $data['rate_value'] : $row->rate_value,
			'order_value_min' => array_key_exists( 'order_value_min', $data ) ? $data['order_value_min'] : $row->order_value_min,
			'order_value_max' => array_key_exists( 'order_value_max', $data ) ? $data['order_value_max'] : $row->order_value_max,
			'is_enabled'      => array_key_exists( 'is_enabled', $data ) ? $data['is_enabled'] : $row->is_enabled,
			'notes'           => array_key_exists( 'notes', $data ) ? $data['notes'] : $row->notes,
		);

		$parsed = self::parse_fields( $merged );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$ok = $wpdb->update(
			SOM_DB::table( 'channel_fee_estimates' ),
			array(
				'channel_id'      => $parsed['channel_id'],
				'fee_component'   => $parsed['fee_component'],
				'rate_type'       => $parsed['rate_type'],
				'rate_value'      => $parsed['rate_value'],
				'order_value_min' => $parsed['order_value_min'],
				'order_value_max' => $parsed['order_value_max'],
				'is_enabled'      => $parsed['is_enabled'],
				'notes'           => $parsed['notes'],
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_fee_estimate_update', __( 'Could not update fee estimate.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Delete an estimate component row.
	 *
	 * @param int $id Estimate PK.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id  = (int) $id;
		$row = self::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'som_fee_estimate_missing', __( 'Fee estimate not found.', 'order-machine' ) );
		}

		$ok = $wpdb->delete(
			SOM_DB::table( 'channel_fee_estimates' ),
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'som_fee_estimate_delete', __( 'Could not delete fee estimate.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Idempotent seed of eBay/Etsy defaults. Inserts missing rows only; never overwrites edits.
	 *
	 * @return void
	 */
	public static function ensure_defaults() {
		SOM_Channels::ensure_rows();

		$ebay = SOM_Channels::get_by_slug( 'ebay' );
		$etsy = SOM_Channels::get_by_slug( 'etsy' );
		if ( ! $ebay || ! $etsy ) {
			return;
		}

		$ebay_id = (int) $ebay->id;
		$etsy_id = (int) $etsy->id;

		$defaults = array(
			// eBay — half-open tiers: [NULL, 10) and [10, NULL).
			array(
				'channel_id'      => $ebay_id,
				'fee_component'   => 'final_value_fee',
				'rate_type'       => 'percent',
				'rate_value'      => 12.8,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => 'Category-dependent; 6.9–14.9% range. Seed midpoint for most categories.',
			),
			array(
				'channel_id'      => $ebay_id,
				'fee_component'   => 'per_order_fee',
				'rate_type'       => 'fixed',
				'rate_value'      => 0.30,
				'order_value_min' => null,
				'order_value_max' => 10.00,
				'is_enabled'      => 1,
				'notes'           => 'Orders under £10 (max exclusive).',
			),
			array(
				'channel_id'      => $ebay_id,
				'fee_component'   => 'per_order_fee',
				'rate_type'       => 'fixed',
				'rate_value'      => 0.40,
				'order_value_min' => 10.00,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => 'Orders at or above £10 (min inclusive).',
			),
			array(
				'channel_id'      => $ebay_id,
				'fee_component'   => 'regulatory_fee',
				'rate_type'       => 'percent',
				'rate_value'      => 0.4,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => 'Regulatory Operating Fee (~0.35–0.43%).',
			),
			array(
				'channel_id'      => $ebay_id,
				'fee_component'   => 'promoted_listings',
				'rate_type'       => 'percent',
				'rate_value'      => 3.0,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => 'Optional ads — included by default (conservative). Disable if unused.',
			),
			// Etsy.
			array(
				'channel_id'      => $etsy_id,
				'fee_component'   => 'listing_fee',
				'rate_type'       => 'fixed',
				'rate_value'      => 0.16,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => 'Per listing / renewal. Also tracked via recurring expenses when synced.',
			),
			array(
				'channel_id'      => $etsy_id,
				'fee_component'   => 'transaction_fee',
				'rate_type'       => 'percent',
				'rate_value'      => 6.5,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => null,
			),
			array(
				'channel_id'      => $etsy_id,
				'fee_component'   => 'payment_processing',
				'rate_type'       => 'percent',
				'rate_value'      => 4.0,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => 'Percent portion of Etsy Payments (pairs with payment_processing_fixed).',
			),
			array(
				'channel_id'      => $etsy_id,
				'fee_component'   => 'payment_processing_fixed',
				'rate_type'       => 'fixed',
				'rate_value'      => 0.20,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => 'Fixed £ portion of Etsy Payments (pairs with payment_processing).',
			),
			array(
				'channel_id'      => $etsy_id,
				'fee_component'   => 'regulatory_fee',
				'rate_type'       => 'percent',
				'rate_value'      => 0.32,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => null,
			),
			array(
				'channel_id'      => $etsy_id,
				'fee_component'   => 'vat_on_fees',
				'rate_type'       => 'percent',
				'rate_value'      => 20.0,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => 'VAT on fee totals (not order value). Applied when computing estimates (Sprint 3).',
			),
			array(
				'channel_id'      => $etsy_id,
				'fee_component'   => 'offsite_ads',
				'rate_type'       => 'percent',
				'rate_value'      => 15.0,
				'order_value_min' => null,
				'order_value_max' => null,
				'is_enabled'      => 1,
				'notes'           => 'Optional — included by default (conservative). Disable if opted out.',
			),
		);

		foreach ( $defaults as $seed ) {
			if ( self::find_matching( $seed ) ) {
				continue;
			}
			self::create( $seed );
		}
	}

	/**
	 * Find an existing row matching channel + component + tier bounds (NULL-safe).
	 *
	 * @param array<string, mixed> $seed Seed fields.
	 * @return object|null
	 */
	public static function find_matching( array $seed ) {
		global $wpdb;

		$table      = SOM_DB::table( 'channel_fee_estimates' );
		$channel_id = (int) $seed['channel_id'];
		$component  = sanitize_key( (string) $seed['fee_component'] );
		$min        = array_key_exists( 'order_value_min', $seed ) ? $seed['order_value_min'] : null;
		$max        = array_key_exists( 'order_value_max', $seed ) ? $seed['order_value_max'] : null;

		$sql    = "SELECT * FROM {$table} WHERE channel_id = %d AND fee_component = %s";
		$params = array( $channel_id, $component );

		if ( null === $min || '' === $min ) {
			$sql .= ' AND order_value_min IS NULL';
		} else {
			$sql     .= ' AND order_value_min = %f';
			$params[] = (float) $min;
		}

		if ( null === $max || '' === $max ) {
			$sql .= ' AND order_value_max IS NULL';
		} else {
			$sql     .= ' AND order_value_max = %f';
			$params[] = (float) $max;
		}

		$sql .= ' LIMIT 1';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ) );

		return $row ? $row : null;
	}

	/**
	 * Whether an order value falls in this estimate's half-open band.
	 *
	 * @param object     $row         Estimate row.
	 * @param float|null $order_value Order value in GBP.
	 * @return bool
	 */
	public static function matches_order_value( $row, $order_value ) {
		if ( null === $order_value || '' === $order_value ) {
			$min = $row->order_value_min;
			$max = $row->order_value_max;
			return ( null === $min || '' === $min ) && ( null === $max || '' === $max );
		}

		$value = (float) $order_value;
		$min   = ( null === $row->order_value_min || '' === $row->order_value_min ) ? null : (float) $row->order_value_min;
		$max   = ( null === $row->order_value_max || '' === $row->order_value_max ) ? null : (float) $row->order_value_max;

		if ( null !== $min && $value < $min ) {
			return false;
		}
		if ( null !== $max && $value >= $max ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $args Query args for list URL.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		$args = array_merge( array( 'page' => 'som-channel-fee-estimates' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * @param int|string $estimate_id Estimate PK or "new".
	 * @return string
	 */
	public static function detail_url( $estimate_id ) {
		return self::list_url( array( 'estimate_id' => $estimate_id ) );
	}

	/**
	 * @param int $estimate_id Estimate PK.
	 * @return string
	 */
	public static function delete_url( $estimate_id ) {
		return wp_nonce_url(
			self::list_url(
				array(
					'som_delete_fee_estimate' => (int) $estimate_id,
				)
			),
			'som_delete_fee_estimate_' . (int) $estimate_id
		);
	}

	/**
	 * Human label for rate_type + rate_value.
	 *
	 * @param object $row Estimate row.
	 * @return string
	 */
	public static function format_rate( $row ) {
		$value = (float) $row->rate_value;
		if ( 'percent' === $row->rate_type ) {
			return number_format_i18n( $value, 4 ) . '%';
		}
		return '£' . number_format_i18n( $value, 4 );
	}

	/**
	 * Human label for tier band.
	 *
	 * @param object $row Estimate row.
	 * @return string
	 */
	public static function format_tier( $row ) {
		$min = ( null === $row->order_value_min || '' === $row->order_value_min ) ? null : (float) $row->order_value_min;
		$max = ( null === $row->order_value_max || '' === $row->order_value_max ) ? null : (float) $row->order_value_max;

		if ( null === $min && null === $max ) {
			return __( 'Always', 'order-machine' );
		}
		if ( null === $min && null !== $max ) {
			return sprintf(
				/* translators: %s: max order value (exclusive) */
				__( 'Under £%s', 'order-machine' ),
				number_format_i18n( $max, 2 )
			);
		}
		if ( null !== $min && null === $max ) {
			return sprintf(
				/* translators: %s: min order value (inclusive) */
				__( '£%s and above', 'order-machine' ),
				number_format_i18n( $min, 2 )
			);
		}

		return sprintf(
			/* translators: 1: min inclusive, 2: max exclusive */
			__( '£%1$s – under £%2$s', 'order-machine' ),
			number_format_i18n( $min, 2 ),
			number_format_i18n( $max, 2 )
		);
	}

	/**
	 * Validate and normalise create/update fields.
	 *
	 * @param array<string, mixed> $data Raw fields.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function parse_fields( array $data ) {
		$channel_id = isset( $data['channel_id'] ) ? (int) $data['channel_id'] : 0;
		if ( $channel_id < 1 ) {
			return new WP_Error( 'som_fee_estimate_channel', __( 'Channel is required.', 'order-machine' ) );
		}

		$component = isset( $data['fee_component'] ) ? sanitize_key( (string) $data['fee_component'] ) : '';
		if ( '' === $component ) {
			return new WP_Error( 'som_fee_estimate_component', __( 'Fee component is required.', 'order-machine' ) );
		}

		$rate_type = isset( $data['rate_type'] ) ? sanitize_key( (string) $data['rate_type'] ) : '';
		if ( ! in_array( $rate_type, array( 'percent', 'fixed' ), true ) ) {
			return new WP_Error( 'som_fee_estimate_rate_type', __( 'Rate type must be percent or fixed.', 'order-machine' ) );
		}

		if ( ! isset( $data['rate_value'] ) || '' === $data['rate_value'] || ! is_numeric( $data['rate_value'] ) ) {
			return new WP_Error( 'som_fee_estimate_rate_value', __( 'Rate value is required.', 'order-machine' ) );
		}
		$rate_value = round( (float) $data['rate_value'], 4 );
		if ( $rate_value < 0 ) {
			return new WP_Error( 'som_fee_estimate_rate_value', __( 'Rate value cannot be negative.', 'order-machine' ) );
		}

		$min = self::parse_optional_decimal( isset( $data['order_value_min'] ) ? $data['order_value_min'] : null );
		if ( is_wp_error( $min ) ) {
			return $min;
		}
		$max = self::parse_optional_decimal( isset( $data['order_value_max'] ) ? $data['order_value_max'] : null );
		if ( is_wp_error( $max ) ) {
			return $max;
		}
		if ( null !== $min && null !== $max && $min >= $max ) {
			return new WP_Error(
				'som_fee_estimate_tier',
				__( 'Order value min must be less than max (max is exclusive).', 'order-machine' )
			);
		}

		$notes = isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '';
		if ( '' === $notes ) {
			$notes = null;
		}

		return array(
			'channel_id'      => $channel_id,
			'fee_component'   => $component,
			'rate_type'       => $rate_type,
			'rate_value'      => $rate_value,
			'order_value_min' => $min,
			'order_value_max' => $max,
			'is_enabled'      => ! empty( $data['is_enabled'] ) ? 1 : 0,
			'notes'           => $notes,
		);
	}

	/**
	 * @param mixed $value Raw decimal or empty.
	 * @return float|null|WP_Error
	 */
	private static function parse_optional_decimal( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_numeric( $value ) ) {
			return new WP_Error( 'som_fee_estimate_tier', __( 'Order value bounds must be numeric.', 'order-machine' ) );
		}
		$n = round( (float) $value, 2 );
		if ( $n < 0 ) {
			return new WP_Error( 'som_fee_estimate_tier', __( 'Order value bounds cannot be negative.', 'order-machine' ) );
		}
		return $n;
	}
}
