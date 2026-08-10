<?php
/**
 * Platform fee estimates, actuals, and fee-aware profit (Update Package 3 / Sprint 3).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared estimate → actual fee math for Costing, budgets, and (later) Analytics.
 *
 * Locked Sprint 3 rules:
 * - Representative price: target for estimates; live listing price when linked.
 * - One fee-aware profit path (estimate until order/product actuals exist).
 * - Actual Costing attribution: full order fee total when the product appears on the order (B).
 * - vat_on_fees: percent of other estimated fee £ totals.
 * - Include listing_fee in estimates; optional ads when is_enabled.
 * - Variance highlight at ≥ 2 percentage points.
 * - Fee cost = abs(amount).
 */
class SOM_Platform_Fees {

	/**
	 * Absolute percentage-point gap that triggers variance highlight.
	 */
	const VARIANCE_PP_THRESHOLD = 2.0;

	/**
	 * Fee component that applies VAT on other fee totals (not on order value).
	 */
	const VAT_COMPONENT = 'vat_on_fees';

	/**
	 * Marketplace channels that participate in fee costing.
	 *
	 * @return array<int, object>
	 */
	public static function fee_channels() {
		$out = array();
		foreach ( array( 'ebay', 'etsy' ) as $slug ) {
			$row = SOM_Channels::get_by_slug( $slug );
			if ( $row ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Apply enabled estimate components for a channel against an order/sale value.
	 *
	 * @param int   $channel_id  Channel PK.
	 * @param float $order_value Representative sale value (GBP).
	 * @return array{total: float, percent: float|null, order_value: float, components: array<int, array<string, mixed>>}
	 */
	public static function estimate_total( $channel_id, $order_value ) {
		$channel_id  = (int) $channel_id;
		$order_value = max( 0.0, (float) $order_value );
		$rows        = SOM_Channel_Fee_Estimates::list_all( $channel_id );

		$base_components = array();
		$vat_row         = null;
		$seen_components = array();

		foreach ( $rows as $row ) {
			if ( empty( $row->is_enabled ) ) {
				continue;
			}
			if ( ! SOM_Channel_Fee_Estimates::matches_order_value( $row, $order_value ) ) {
				continue;
			}
			if ( self::VAT_COMPONENT === (string) $row->fee_component ) {
				if ( null === $vat_row ) {
					$vat_row = $row;
				}
				continue;
			}

			$component = (string) $row->fee_component;
			// Guard against accidental duplicate seed rows for the same component/tier match.
			if ( isset( $seen_components[ $component ] ) ) {
				continue;
			}
			$seen_components[ $component ] = true;

			$amount = self::component_amount( $row, $order_value );
			$base_components[] = array(
				'fee_component' => $component,
				'rate_type'     => (string) $row->rate_type,
				'rate_value'    => (float) $row->rate_value,
				'amount'        => $amount,
			);
		}

		$base_total = 0.0;
		foreach ( $base_components as $comp ) {
			$base_total += (float) $comp['amount'];
		}
		$base_total = SOM_Material_Costing::round4( $base_total );

		$components = $base_components;
		$total      = $base_total;

		if ( $vat_row && 'percent' === (string) $vat_row->rate_type ) {
			$vat_amount   = SOM_Material_Costing::round4( $base_total * ( (float) $vat_row->rate_value / 100 ) );
			$components[] = array(
				'fee_component' => self::VAT_COMPONENT,
				'rate_type'     => 'percent',
				'rate_value'    => (float) $vat_row->rate_value,
				'amount'        => $vat_amount,
			);
			$total = SOM_Material_Costing::round4( $base_total + $vat_amount );
		}

		$percent = null;
		if ( $order_value > 0 ) {
			$percent = round( ( $total / $order_value ) * 100, 2 );
		}

		return array(
			'total'       => $total,
			'percent'     => $percent,
			'order_value' => SOM_Material_Costing::round4( $order_value ),
			'components'  => $components,
		);
	}

	/**
	 * Absolute sum of synced fee lines for an order, or null when none.
	 *
	 * @param int $order_id Order PK.
	 * @return float|null
	 */
	public static function order_actual_fee_total( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return null;
		}

		$table = SOM_DB::table( 'order_platform_fees' );
		$sum   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(ABS(amount)) FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		if ( null === $sum || '' === $sum ) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE order_id = %d",
					$order_id
				)
			);
			return $count > 0 ? 0.0 : null;
		}

		return SOM_Material_Costing::round4( (float) $sum );
	}

	/**
	 * Fees for an order: actual if synced, else estimate against $order_value.
	 *
	 * @param int   $order_id    Order PK.
	 * @param int   $channel_id  Channel PK.
	 * @param float $order_value Value used for estimate (typically line revenue).
	 * @return array{total: float, percent: float|null, source: string, order_value: float}
	 */
	public static function fees_for_order( $order_id, $channel_id, $order_value ) {
		$order_value = max( 0.0, (float) $order_value );
		$actual      = self::order_actual_fee_total( (int) $order_id );

		if ( null !== $actual ) {
			$percent = null;
			if ( $order_value > 0 ) {
				$percent = round( ( $actual / $order_value ) * 100, 2 );
			}
			return array(
				'total'       => $actual,
				'percent'     => $percent,
				'source'      => 'actual',
				'order_value' => SOM_Material_Costing::round4( $order_value ),
			);
		}

		$est = self::estimate_total( (int) $channel_id, $order_value );
		return array(
			'total'       => $est['total'],
			'percent'     => $est['percent'],
			'source'      => 'estimate',
			'order_value' => $est['order_value'],
		);
	}

	/**
	 * Line-level fee-aware profit (for budgets percent_of_profit).
	 *
	 * @param float $sold_unit     Effective sold unit price.
	 * @param int   $qty           Line quantity.
	 * @param float $material_unit Recipe material cost per unit.
	 * @param int   $order_id      Order PK.
	 * @param int   $channel_id    Channel PK.
	 * @return array{profit: float, fees: float, fee_source: string, revenue: float}
	 */
	public static function line_profit( $sold_unit, $qty, $material_unit, $order_id, $channel_id ) {
		$qty      = max( 1, (int) $qty );
		$revenue  = SOM_Material_Costing::round4( (float) $sold_unit * $qty );
		$material = SOM_Material_Costing::round4( (float) $material_unit * $qty );
		$fees     = self::fees_for_order( (int) $order_id, (int) $channel_id, $revenue );

		$profit = SOM_Material_Costing::round4( $revenue - $material - (float) $fees['total'] );

		return array(
			'profit'     => $profit,
			'fees'       => (float) $fees['total'],
			'fee_source' => (string) $fees['source'],
			'revenue'    => $revenue,
		);
	}

	/**
	 * Actual fee aggregates for a product on a channel (rule B: full order fees).
	 *
	 * @param int $product_id Product PK.
	 * @param int $channel_id Channel PK.
	 * @return array{order_count: int, total_fees: float, total_revenue: float, total_qty: float, avg_fee_per_unit: float|null, percent: float|null}
	 */
	public static function product_channel_actuals( $product_id, $channel_id ) {
		global $wpdb;

		$product_id = (int) $product_id;
		$channel_id = (int) $channel_id;
		$empty      = array(
			'order_count'       => 0,
			'total_fees'        => 0.0,
			'total_revenue'     => 0.0,
			'total_qty'         => 0.0,
			'avg_fee_per_unit'  => null,
			'percent'           => null,
		);

		if ( $product_id < 1 || $channel_id < 1 ) {
			return $empty;
		}

		$orders_t = SOM_DB::table( 'orders' );
		$items_t  = SOM_DB::table( 'order_items' );
		$fees_t   = SOM_DB::table( 'order_platform_fees' );
		$prod_t   = SOM_DB::table( 'products' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					o.id AS order_id,
					(
						SELECT SUM(ABS(f.amount))
						FROM {$fees_t} f
						WHERE f.order_id = o.id
					) AS fee_total,
					SUM(
						oi.quantity * CASE
							WHEN oi.unit_price IS NOT NULL THEN oi.unit_price
							WHEN p.target_selling_price IS NOT NULL THEN p.target_selling_price
							ELSE 0
						END
					) AS product_revenue,
					SUM(oi.quantity) AS product_qty
				FROM {$orders_t} o
				INNER JOIN {$items_t} oi ON oi.order_id = o.id AND oi.product_id = %d
				INNER JOIN {$prod_t} p ON p.id = oi.product_id
				WHERE o.channel_id = %d
					AND EXISTS (
						SELECT 1 FROM {$fees_t} f2 WHERE f2.order_id = o.id
					)
				GROUP BY o.id
				HAVING fee_total IS NOT NULL",
				$product_id,
				$channel_id
			)
		);

		if ( ! is_array( $rows ) || ! $rows ) {
			return $empty;
		}

		$total_fees    = 0.0;
		$total_revenue = 0.0;
		$total_qty     = 0.0;
		$order_count   = 0;

		foreach ( $rows as $row ) {
			++$order_count;
			$total_fees    += (float) $row->fee_total;
			$total_revenue += (float) $row->product_revenue;
			$total_qty     += (float) $row->product_qty;
		}

		$total_fees    = SOM_Material_Costing::round4( $total_fees );
		$total_revenue = SOM_Material_Costing::round4( $total_revenue );
		$avg_fee       = $total_qty > 0 ? SOM_Material_Costing::round4( $total_fees / $total_qty ) : null;
		$percent       = $total_revenue > 0 ? round( ( $total_fees / $total_revenue ) * 100, 2 ) : null;

		return array(
			'order_count'      => $order_count,
			'total_fees'       => $total_fees,
			'total_revenue'    => $total_revenue,
			'total_qty'        => $total_qty,
			'avg_fee_per_unit' => $avg_fee,
			'percent'          => $percent,
		);
	}

	/**
	 * Per-channel fee comparison + preferred fee-aware profit for a product.
	 *
	 * @param int        $product_id    Product PK.
	 * @param float      $material_cost Recipe material cost.
	 * @param float|null $target_price  Target selling price.
	 * @return array<string, mixed>
	 */
	public static function product_fee_costing( $product_id, $material_cost, $target_price ) {
		$product_id    = (int) $product_id;
		$material_cost = SOM_Material_Costing::round4( (float) $material_cost );
		$target        = null !== $target_price && '' !== $target_price ? (float) $target_price : null;

		$listings_by_channel = array();
		foreach ( SOM_Products::get_listings( $product_id ) as $listing ) {
			$cid = (int) $listing->channel_id;
			if ( ! isset( $listings_by_channel[ $cid ] ) ) {
				$listings_by_channel[ $cid ] = $listing;
			}
		}

		$channels = array();
		foreach ( self::fee_channels() as $channel ) {
			$cid     = (int) $channel->id;
			$listing = isset( $listings_by_channel[ $cid ] ) ? $listings_by_channel[ $cid ] : null;

			$rep_price = $target;
			$rep_source = 'target';
			if ( $listing && null !== $listing->price && '' !== $listing->price ) {
				$rep_price  = (float) $listing->price;
				$rep_source = 'listing';
			}

			$estimated = null;
			if ( null !== $rep_price ) {
				$estimated = self::estimate_total( $cid, $rep_price );
			}

			$actuals = self::product_channel_actuals( $product_id, $cid );

			$fee_for_profit = null;
			$fee_source     = 'none';
			if ( $actuals['order_count'] > 0 && null !== $actuals['avg_fee_per_unit'] ) {
				$fee_for_profit = (float) $actuals['avg_fee_per_unit'];
				$fee_source     = 'actual';
			} elseif ( $estimated ) {
				$fee_for_profit = (float) $estimated['total'];
				$fee_source     = 'estimate';
			}

			$profit = null;
			$margin = null;
			if ( null !== $rep_price && null !== $fee_for_profit ) {
				$profit = SOM_Material_Costing::round4( $rep_price - $material_cost - $fee_for_profit );
				if ( $rep_price > 0 ) {
					$margin = round( ( $profit / $rep_price ) * 100, 2 );
				}
			}

			$est_pct    = $estimated ? $estimated['percent'] : null;
			$act_pct    = $actuals['percent'];
			$variance   = ( null !== $est_pct && null !== $act_pct ) ? round( $act_pct - $est_pct, 2 ) : null;
			$highlight  = null !== $variance && abs( $variance ) >= self::VARIANCE_PP_THRESHOLD;

			$channels[] = array(
				'channel_id'            => $cid,
				'channel_slug'          => (string) $channel->slug,
				'channel_name'          => (string) $channel->display_name,
				'representative_price'  => $rep_price,
				'representative_source' => $rep_source,
				'has_listing'           => (bool) $listing,
				'estimated_total'       => $estimated ? (float) $estimated['total'] : null,
				'estimated_percent'     => $est_pct,
				'estimated_components'  => $estimated ? $estimated['components'] : array(),
				'actual_total'          => $actuals['order_count'] > 0 ? (float) $actuals['total_fees'] : null,
				'actual_percent'        => $act_pct,
				'actual_order_count'    => (int) $actuals['order_count'],
				'actual_avg_fee_unit'   => $actuals['avg_fee_per_unit'],
				'fees_for_profit'       => $fee_for_profit,
				'fee_source'            => $fee_source,
				'profit'                => $profit,
				'margin_percent'        => $margin,
				'variance_pp'           => $variance,
				'variance_highlight'    => $highlight,
			);
		}

		$preferred = self::prefer_channel_row( $channels );

		return array(
			'channels'              => $channels,
			'preferred_channel'     => $preferred,
			'platform_fees'         => $preferred && null !== $preferred['fees_for_profit'] ? (float) $preferred['fees_for_profit'] : null,
			'fee_source'            => $preferred ? (string) $preferred['fee_source'] : 'none',
			'fee_aware_profit'      => $preferred ? $preferred['profit'] : null,
			'fee_aware_margin'      => $preferred ? $preferred['margin_percent'] : null,
			'representative_price'  => $preferred ? $preferred['representative_price'] : $target,
		);
	}

	/**
	 * Pick the channel row used for list/MCP single profit figures.
	 *
	 * Prefers actuals (highest n), then a linked listing, then first estimate.
	 *
	 * @param array<int, array<string, mixed>> $channels Channel fee rows.
	 * @return array<string, mixed>|null
	 */
	public static function prefer_channel_row( array $channels ) {
		$best_actual = null;
		$best_n      = -1;
		foreach ( $channels as $row ) {
			if ( 'actual' === $row['fee_source'] && (int) $row['actual_order_count'] > $best_n ) {
				$best_n      = (int) $row['actual_order_count'];
				$best_actual = $row;
			}
		}
		if ( $best_actual ) {
			return $best_actual;
		}

		foreach ( $channels as $row ) {
			if ( ! empty( $row['has_listing'] ) && 'estimate' === $row['fee_source'] ) {
				return $row;
			}
		}

		foreach ( $channels as $row ) {
			if ( 'estimate' === $row['fee_source'] ) {
				return $row;
			}
		}

		return ! empty( $channels ) ? $channels[0] : null;
	}

	/**
	 * Human label for fee source.
	 *
	 * @param string $source estimate|actual|none.
	 * @return string
	 */
	public static function fee_source_label( $source ) {
		switch ( (string) $source ) {
			case 'actual':
				return __( 'Actual fees', 'order-machine' );
			case 'estimate':
				return __( 'Estimated fees', 'order-machine' );
			default:
				return __( 'No fees', 'order-machine' );
		}
	}

	/**
	 * @param object $row         Estimate row.
	 * @param float  $order_value Sale value.
	 * @return float
	 */
	private static function component_amount( $row, $order_value ) {
		if ( 'percent' === (string) $row->rate_type ) {
			return SOM_Material_Costing::round4( (float) $order_value * ( (float) $row->rate_value / 100 ) );
		}
		return SOM_Material_Costing::round4( (float) $row->rate_value );
	}
}
