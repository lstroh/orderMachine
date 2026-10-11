<?php
/**
 * Analytics dashboard aggregations (Update Package 3 / Sprint 4 + UP10-S2 timing).
 *
 * Live queries only. Profit is order-level: revenue − material COGS − fees once.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chart series helpers for the Analytics admin page.
 */
class SOM_Analytics {

	/**
	 * Soft-default over-plan highlight: avg actual > planned × this ratio.
	 */
	const OVER_PLAN_RATIO = 1.2;

	/**
	 * Parse shared GET filters.
	 *
	 * @param array<string, mixed> $source Typically $_GET.
	 * @return array{
	 *   range: string,
	 *   date_from: string,
	 *   date_to: string,
	 *   granularity: string,
	 *   channel_id: int,
	 *   material_ids: int[],
	 *   timing_kind: string,
	 *   timing_template_id: int,
	 *   start: string,
	 *   end: string
	 * }
	 */
	public static function parse_filters( array $source ) {
		$range = isset( $source['som_range'] ) ? sanitize_key( wp_unslash( $source['som_range'] ) ) : '30';
		if ( ! in_array( $range, array( '7', '30', '90', 'year', 'custom' ), true ) ) {
			$range = '30';
		}

		$granularity = isset( $source['som_granularity'] ) ? sanitize_key( wp_unslash( $source['som_granularity'] ) ) : 'daily';
		if ( ! in_array( $granularity, array( 'daily', 'weekly', 'monthly' ), true ) ) {
			$granularity = 'daily';
		}

		$channel_id = isset( $source['som_channel'] ) ? (int) $source['som_channel'] : 0;

		$material_ids = array();
		if ( isset( $source['som_materials'] ) && is_array( $source['som_materials'] ) ) {
			foreach ( $source['som_materials'] as $mid ) {
				$mid = (int) $mid;
				if ( $mid > 0 ) {
					$material_ids[] = $mid;
				}
			}
			$material_ids = array_values( array_unique( $material_ids ) );
		}

		$date_from = isset( $source['som_date_from'] ) ? sanitize_text_field( wp_unslash( $source['som_date_from'] ) ) : '';
		$date_to   = isset( $source['som_date_to'] ) ? sanitize_text_field( wp_unslash( $source['som_date_to'] ) ) : '';

		$bounds = self::resolve_date_bounds( $range, $date_from, $date_to );

		$timing_kind = isset( $source['som_timing_kind'] ) ? sanitize_key( wp_unslash( $source['som_timing_kind'] ) ) : 'make';
		if ( ! in_array( $timing_kind, array( 'make', 'pack' ), true ) ) {
			$timing_kind = 'make';
		}

		$timing_template_id = isset( $source['som_timing_template'] ) ? (int) $source['som_timing_template'] : 0;

		return array(
			'range'               => $range,
			'date_from'           => $bounds['date_from'],
			'date_to'             => $bounds['date_to'],
			'granularity'         => $granularity,
			'channel_id'          => $channel_id,
			'material_ids'        => $material_ids,
			'timing_kind'         => $timing_kind,
			'timing_template_id'  => $timing_template_id,
			'start'               => $bounds['start'],
			'end'                 => $bounds['end'],
		);
	}

	/**
	 * Resolve inclusive calendar date bounds (site timezone) to UTC SQL datetimes.
	 *
	 * @param string $range     Preset key.
	 * @param string $date_from Y-m-d for custom.
	 * @param string $date_to   Y-m-d for custom.
	 * @return array{date_from: string, date_to: string, start: string, end: string}
	 */
	public static function resolve_date_bounds( $range, $date_from = '', $date_to = '' ) {
		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );

		if ( 'custom' === $range ) {
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
				$date_from = $now->modify( '-29 days' )->format( 'Y-m-d' );
			}
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
				$date_to = $now->format( 'Y-m-d' );
			}
			if ( $date_from > $date_to ) {
				$tmp       = $date_from;
				$date_from = $date_to;
				$date_to   = $tmp;
			}
		} elseif ( '7' === $range ) {
			$date_to   = $now->format( 'Y-m-d' );
			$date_from = $now->modify( '-6 days' )->format( 'Y-m-d' );
		} elseif ( '90' === $range ) {
			$date_to   = $now->format( 'Y-m-d' );
			$date_from = $now->modify( '-89 days' )->format( 'Y-m-d' );
		} elseif ( 'year' === $range ) {
			$date_from = $now->format( 'Y' ) . '-01-01';
			$date_to   = $now->format( 'Y-m-d' );
		} else {
			// Default 30.
			$date_to   = $now->format( 'Y-m-d' );
			$date_from = $now->modify( '-29 days' )->format( 'Y-m-d' );
		}

		$start_local = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_from . ' 00:00:00', $tz );
		$end_local   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_to . ' 23:59:59', $tz );
		if ( ! $start_local || ! $end_local ) {
			$start_local = $now->setTime( 0, 0, 0 )->modify( '-29 days' );
			$end_local   = $now->setTime( 23, 59, 59 );
			$date_from   = $start_local->format( 'Y-m-d' );
			$date_to     = $end_local->format( 'Y-m-d' );
		}

		$start_utc = $start_local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$end_utc   = $end_local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

		return array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'start'     => $start_utc,
			'end'       => $end_utc,
		);
	}

	/**
	 * SQL fragment for cancelled or refunded orders (best-effort on raw_payload).
	 *
	 * @param string $orders_alias   Orders alias.
	 * @param string $channels_alias Channels alias.
	 * @return string
	 */
	public static function excluded_orders_sql( $orders_alias = 'o', $channels_alias = 'c' ) {
		$cancelled = SOM_Orders::cancelled_sql( $orders_alias, $channels_alias );
		$refunded  = self::refunded_sql( $orders_alias, $channels_alias );
		return "( {$cancelled} OR {$refunded} )";
	}

	/**
	 * Best-effort refunded payload match (order-level refund status is not a DB column).
	 *
	 * @param string $orders_alias   Orders alias.
	 * @param string $channels_alias Channels alias.
	 * @return string
	 */
	public static function refunded_sql( $orders_alias = 'o', $channels_alias = 'c' ) {
		$o = preg_replace( '/[^a-z_]/', '', $orders_alias );
		$c = preg_replace( '/[^a-z_]/', '', $channels_alias );

		return "(
			( {$c}.slug = 'ebay' AND (
				{$o}.raw_payload LIKE '%\"orderPaymentStatus\":\"FULLY_REFUNDED\"%'
				OR {$o}.raw_payload LIKE '%\"orderPaymentStatus\":\"REFUNDED\"%'
			) )
			OR ( {$c}.slug = 'etsy' AND (
				{$o}.raw_payload LIKE '%\"status\":\"fully_refunded\"%'
				OR {$o}.raw_payload LIKE '%\"status\":\"partially_refunded\"%'
			) )
		)";
	}

	/**
	 * Bucket key for an order_date (stored UTC) in site timezone.
	 *
	 * @param string $order_date  MySQL datetime UTC.
	 * @param string $granularity daily|weekly|monthly.
	 * @return string|null
	 */
	public static function bucket_key( $order_date, $granularity ) {
		try {
			$dt = new DateTimeImmutable( (string) $order_date, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			return null;
		}
		$local = $dt->setTimezone( wp_timezone() );

		if ( 'monthly' === $granularity ) {
			return $local->format( 'Y-m' );
		}
		if ( 'weekly' === $granularity ) {
			// ISO week: Monday start.
			return $local->format( 'o-\WW' );
		}
		return $local->format( 'Y-m-d' );
	}

	/**
	 * Ordered label list covering [date_from, date_to] inclusive.
	 *
	 * @param string $date_from   Y-m-d.
	 * @param string $date_to     Y-m-d.
	 * @param string $granularity daily|weekly|monthly.
	 * @return string[]
	 */
	public static function bucket_labels( $date_from, $date_to, $granularity ) {
		$tz    = wp_timezone();
		$start = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_from, $tz );
		$end   = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_to, $tz );
		if ( ! $start || ! $end ) {
			return array();
		}

		$labels = array();
		if ( 'monthly' === $granularity ) {
			$cursor = $start->modify( 'first day of this month' );
			$last   = $end->modify( 'first day of this month' );
			while ( $cursor <= $last ) {
				$labels[] = $cursor->format( 'Y-m' );
				$cursor   = $cursor->modify( '+1 month' );
			}
			return $labels;
		}

		if ( 'weekly' === $granularity ) {
			$cursor = $start;
			$dow    = (int) $cursor->format( 'N' ); // 1=Mon.
			if ( $dow > 1 ) {
				$cursor = $cursor->modify( '-' . ( $dow - 1 ) . ' days' );
			}
			$end_week = $end;
			$end_dow  = (int) $end_week->format( 'N' );
			if ( $end_dow > 1 ) {
				$end_week = $end_week->modify( '-' . ( $end_dow - 1 ) . ' days' );
			}
			while ( $cursor <= $end_week ) {
				$labels[] = $cursor->format( 'o-\WW' );
				$cursor   = $cursor->modify( '+1 week' );
			}
			return array_values( array_unique( $labels ) );
		}

		$cursor = $start;
		while ( $cursor <= $end ) {
			$labels[] = $cursor->format( 'Y-m-d' );
			$cursor   = $cursor->modify( '+1 day' );
		}
		return $labels;
	}

	/**
	 * Full dashboard payload for Chart.js embed.
	 *
	 * @param array<string, mixed> $filters From parse_filters().
	 * @return array<string, mixed>
	 */
	public static function dashboard_payload( array $filters ) {
		$labels = self::bucket_labels( $filters['date_from'], $filters['date_to'], $filters['granularity'] );
		$orders = self::load_orders_with_items( $filters );

		$sales  = self::aggregate_sales_profit_aov( $orders, $filters, $labels );
		$by_ch  = self::aggregate_orders_by_channel( $orders, $filters );
		$stock  = self::aggregate_stock_series( $filters, $labels );
		$timing = self::timing_payload( $filters );

		return array(
			'filters'           => array(
				'range'              => $filters['range'],
				'date_from'          => $filters['date_from'],
				'date_to'            => $filters['date_to'],
				'granularity'        => $filters['granularity'],
				'channel_id'         => (int) $filters['channel_id'],
				'material_ids'       => $filters['material_ids'],
				'timing_kind'        => $filters['timing_kind'],
				'timing_template_id' => (int) $filters['timing_template_id'],
			),
			'labels'            => $labels,
			'sales'             => $sales['sales'],
			'profit'            => $sales['profit'],
			'aov'               => $sales['aov'],
			'orders_by_channel' => $by_ch,
			'stock'             => $stock,
			'totals'            => $sales['totals'],
			'timing'            => $timing,
		);
	}

	/**
	 * Active make/pack templates for the Analytics timing filter.
	 *
	 * @param string $kind make|pack.
	 * @return array<int, object> Rows with id, name, kind.
	 */
	public static function list_timing_templates( $kind = 'make' ) {
		$kind = SOM_Workflows::sanitize_kind( $kind );
		$result = SOM_Workflows::query(
			array(
				'status'   => 'active',
				'kind'     => $kind,
				'paged'    => 1,
				'per_page' => 200,
			)
		);
		$templates = isset( $result['templates'] ) && is_array( $result['templates'] ) ? $result['templates'] : array();
		$out       = array();
		foreach ( $templates as $row ) {
			$out[] = (object) array(
				'id'   => (int) $row->id,
				'name' => (string) $row->name,
				'kind' => isset( $row->kind ) ? (string) $row->kind : $kind,
			);
		}
		return $out;
	}

	/**
	 * Resolve timing template id (fallback to first active of kind when unset/invalid).
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array{kind: string, template_id: int, template: object|null, templates: array<int, object>}
	 */
	public static function resolve_timing_template( array $filters ) {
		$kind      = isset( $filters['timing_kind'] ) ? SOM_Workflows::sanitize_kind( $filters['timing_kind'] ) : 'make';
		$templates = self::list_timing_templates( $kind );
		$wanted    = isset( $filters['timing_template_id'] ) ? (int) $filters['timing_template_id'] : 0;
		$chosen    = null;

		foreach ( $templates as $tpl ) {
			if ( $wanted > 0 && (int) $tpl->id === $wanted ) {
				$chosen = $tpl;
				break;
			}
		}
		if ( ! $chosen && $templates ) {
			$chosen = $templates[0];
		}

		return array(
			'kind'        => $kind,
			'template_id' => $chosen ? (int) $chosen->id : 0,
			'template'    => $chosen,
			'templates'   => $templates,
		);
	}

	/**
	 * Workflow timing section payload (UP10-S2).
	 *
	 * @param array<string, mixed> $filters From parse_filters().
	 * @return array<string, mixed>
	 */
	public static function timing_payload( array $filters ) {
		$resolved = self::resolve_timing_template( $filters );
		$kind     = $resolved['kind'];
		$tid      = (int) $resolved['template_id'];

		$empty = array(
			'kind'               => $kind,
			'template_id'        => $tid,
			'template_name'      => $resolved['template'] ? (string) $resolved['template']->name : '',
			'templates'          => $resolved['templates'],
			'steps'              => array(),
			'workflow_totals'    => array(
				'count'            => 0,
				'avg_planned'      => null,
				'avg_actual'       => null,
				'planned_total'    => null,
				'is_partial_plan'  => false,
				'rows'             => array(),
			),
			'chart'              => array(
				'labels'        => array(),
				'avg_planned'   => array(),
				'avg_actual'    => array(),
			),
			'over_plan_ratio'    => self::OVER_PLAN_RATIO,
		);

		if ( $tid < 1 ) {
			return $empty;
		}

		$steps = self::aggregate_step_timing( $tid, $kind, $filters );
		$totals = self::aggregate_workflow_timing_totals( $tid, $kind, $filters );

		$chart_labels  = array();
		$chart_planned = array();
		$chart_actual  = array();
		foreach ( $steps as $step ) {
			$chart_labels[]  = (string) $step['name'];
			$chart_planned[] = null !== $step['avg_planned'] ? round( (float) $step['avg_planned'] / 60, 2 ) : null;
			$chart_actual[]  = null !== $step['avg_actual'] ? round( (float) $step['avg_actual'] / 60, 2 ) : null;
		}

		$empty['steps']           = $steps;
		$empty['workflow_totals'] = $totals;
		$empty['chart']           = array(
			'labels'      => $chart_labels,
			'avg_planned' => $chart_planned,
			'avg_actual'  => $chart_actual,
			'unit'        => 'minutes',
		);

		return $empty;
	}

	/**
	 * Whether avg actual exceeds planned × OVER_PLAN_RATIO.
	 *
	 * @param int|float|null $avg_actual  Average actual seconds.
	 * @param int|float|null $avg_planned Average / template planned seconds.
	 * @return bool
	 */
	public static function is_over_plan( $avg_actual, $avg_planned ) {
		if ( null === $avg_actual || null === $avg_planned || '' === $avg_actual || '' === $avg_planned ) {
			return false;
		}
		$planned = (float) $avg_planned;
		$actual  = (float) $avg_actual;
		if ( $planned <= 0 || $actual < 0 ) {
			return false;
		}
		return $actual > ( $planned * self::OVER_PLAN_RATIO );
	}

	/**
	 * Per-step average actual vs planned for a make or pack template.
	 *
	 * @param int                  $template_id Template PK.
	 * @param string               $kind        make|pack.
	 * @param array<string, mixed> $filters     Date (and channel) filters.
	 * @return array<int, array<string, mixed>>
	 */
	public static function aggregate_step_timing( $template_id, $kind, array $filters ) {
		global $wpdb;

		$template_id = (int) $template_id;
		if ( $template_id < 1 ) {
			return array();
		}

		$kind     = SOM_Workflows::sanitize_kind( $kind );
		$steps    = SOM_Workflows::get_steps( $template_id );
		if ( ! $steps ) {
			return array();
		}

		$progress_t = 'pack' === $kind
			? SOM_DB::table( 'order_step_progress' )
			: SOM_DB::table( 'order_item_step_progress' );
		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$excluded   = self::excluded_orders_sql( 'o', 'c' );

		$where  = array(
			'p.workflow_step_id = %d',
			"p.status = 'done'",
			'p.actual_seconds IS NOT NULL',
			'o.order_date >= %s',
			'o.order_date <= %s',
			"NOT {$excluded}",
			'c.slug <> %s',
		);
		$params_base = array( $filters['start'], $filters['end'], SOM_Production::CHANNEL_SLUG );

		if ( (int) $filters['channel_id'] > 0 ) {
			$where[]       = 'o.channel_id = %d';
			$params_base[] = (int) $filters['channel_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$out       = array();

		foreach ( $steps as $step ) {
			$step_id = (int) $step->id;
			$planned = isset( $step->planned_seconds ) && null !== $step->planned_seconds && '' !== $step->planned_seconds
				? (int) $step->planned_seconds
				: null;
			if ( null !== $planned && $planned < 1 ) {
				$planned = null;
			}

			$params = array_merge( array( $step_id ), $params_base );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names + settled fragments.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT AVG(p.actual_seconds) AS avg_actual, COUNT(p.id) AS sample_count
					FROM {$progress_t} p
					INNER JOIN {$orders_t} o ON o.id = p.order_id
					INNER JOIN {$channels_t} c ON c.id = o.channel_id
					WHERE {$where_sql}",
					$params
				)
			);

			$avg_actual = null;
			$samples    = 0;
			if ( $row && (int) $row->sample_count > 0 ) {
				$samples    = (int) $row->sample_count;
				$avg_actual = (int) round( (float) $row->avg_actual );
			}

			$out[] = array(
				'step_id'     => $step_id,
				'name'        => (string) $step->name,
				'sort_order'  => isset( $step->sort_order ) ? (int) $step->sort_order : 0,
				'avg_planned' => $planned,
				'avg_actual'  => $avg_actual,
				'sample_count'=> $samples,
				'over_plan'   => self::is_over_plan( $avg_actual, $planned ),
			);
		}

		return $out;
	}

	/**
	 * Completed workflow span totals (first start → last complete) for a template.
	 *
	 * @param int                  $template_id Template PK.
	 * @param string               $kind        make|pack.
	 * @param array<string, mixed> $filters     Date filters.
	 * @return array<string, mixed>
	 */
	public static function aggregate_workflow_timing_totals( $template_id, $kind, array $filters ) {
		global $wpdb;

		$template_id = (int) $template_id;
		$kind        = SOM_Workflows::sanitize_kind( $kind );
		$steps       = SOM_Workflows::get_steps( $template_id );
		$step_count  = is_array( $steps ) ? count( $steps ) : 0;
		$plan_summary = SOM_Workflows::planned_total_summary( is_array( $steps ) ? $steps : array() );
		$planned_total = $plan_summary['total'];

		$empty = array(
			'count'           => 0,
			'avg_planned'     => $planned_total,
			'avg_actual'      => null,
			'planned_total'   => $planned_total,
			'is_partial_plan' => ! empty( $plan_summary['is_partial'] ),
			'rows'            => array(),
		);

		if ( $template_id < 1 || $step_count < 1 ) {
			return $empty;
		}

		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$steps_t    = SOM_DB::table( 'workflow_steps' );
		$excluded   = self::excluded_orders_sql( 'o', 'c' );

		$channel_sql = '';
		$params      = array( $template_id, $filters['start'], $filters['end'], SOM_Production::CHANNEL_SLUG );
		if ( (int) $filters['channel_id'] > 0 ) {
			$channel_sql = ' AND o.channel_id = %d';
			$params[]    = (int) $filters['channel_id'];
		}
		$params[] = $step_count;

		if ( 'pack' === $kind ) {
			$progress_t = SOM_DB::table( 'order_step_progress' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT p.order_id AS group_id, p.order_id,
					MIN(p.started_at) AS first_start,
					MAX(p.completed_at) AS last_complete,
					COUNT(DISTINCT p.workflow_step_id) AS done_steps
				FROM {$progress_t} p
				INNER JOIN {$steps_t} s ON s.id = p.workflow_step_id
				INNER JOIN {$orders_t} o ON o.id = p.order_id
				INNER JOIN {$channels_t} c ON c.id = o.channel_id
				WHERE s.workflow_template_id = %d
					AND p.status = 'done'
					AND o.order_date >= %s
					AND o.order_date <= %s
					AND NOT {$excluded}
					AND c.slug <> %s
					{$channel_sql}
				GROUP BY p.order_id
				HAVING COUNT(DISTINCT p.workflow_step_id) >= %d
					AND MIN(p.started_at) IS NOT NULL
					AND MAX(p.completed_at) IS NOT NULL
				ORDER BY group_id ASC",
				$params
			);
		} else {
			$progress_t = SOM_DB::table( 'order_item_step_progress' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT p.order_item_id AS group_id, MIN(p.order_id) AS order_id,
					MIN(p.started_at) AS first_start,
					MAX(p.completed_at) AS last_complete,
					COUNT(DISTINCT p.workflow_step_id) AS done_steps
				FROM {$progress_t} p
				INNER JOIN {$steps_t} s ON s.id = p.workflow_step_id
				INNER JOIN {$orders_t} o ON o.id = p.order_id
				INNER JOIN {$channels_t} c ON c.id = o.channel_id
				WHERE s.workflow_template_id = %d
					AND p.status = 'done'
					AND o.order_date >= %s
					AND o.order_date <= %s
					AND NOT {$excluded}
					AND c.slug <> %s
					{$channel_sql}
				GROUP BY p.order_item_id
				HAVING COUNT(DISTINCT p.workflow_step_id) >= %d
					AND MIN(p.started_at) IS NOT NULL
					AND MAX(p.completed_at) IS NOT NULL
				ORDER BY group_id ASC",
				$params
			);
		}

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) || ! $rows ) {
			return $empty;
		}

		$detail_rows   = array();
		$sum_actual    = 0;
		$count_actual  = 0;

		foreach ( $rows as $row ) {
			$span = SOM_Orders::progress_span_seconds(
				array(
					(object) array(
						'started_at'   => $row->first_start,
						'completed_at' => $row->last_complete,
					),
				)
			);
			if ( null === $span ) {
				continue;
			}
			$sum_actual += $span;
			++$count_actual;
			$detail_rows[] = array(
				'group_id'        => (int) $row->group_id,
				'order_id'        => (int) $row->order_id,
				'actual_seconds'  => $span,
				'planned_seconds' => $planned_total,
				'over_plan'       => self::is_over_plan( $span, $planned_total ),
			);
		}

		$empty['count']       = $count_actual;
		$empty['avg_actual']  = $count_actual > 0 ? (int) round( $sum_actual / $count_actual ) : null;
		$empty['avg_planned'] = $planned_total;
		$empty['rows']        = $detail_rows;

		return $empty;
	}

	/**
	 * Load non-excluded orders in range (with items).
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, object>
	 */
	public static function load_orders_with_items( array $filters ) {
		global $wpdb;

		$orders_t   = SOM_DB::table( 'orders' );
		$channels_t = SOM_DB::table( 'channels' );
		$items_t    = SOM_DB::table( 'order_items' );
		$excluded   = self::excluded_orders_sql( 'o', 'c' );

		$where  = array(
			'o.order_date >= %s',
			'o.order_date <= %s',
			"NOT {$excluded}",
			'c.slug <> %s',
		);
		$params = array( $filters['start'], $filters['end'], SOM_Production::CHANNEL_SLUG );

		if ( (int) $filters['channel_id'] > 0 ) {
			$where[]  = 'o.channel_id = %d';
			$params[] = (int) $filters['channel_id'];
		}

		$where_sql = implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names + settled SQL fragments.
		$sql = $wpdb->prepare(
			"SELECT o.id, o.channel_id, o.order_date, o.external_order_id, c.slug AS channel_slug, c.display_name AS channel_name
			FROM {$orders_t} o
			INNER JOIN {$channels_t} c ON c.id = o.channel_id
			WHERE {$where_sql}
			ORDER BY o.order_date ASC, o.id ASC",
			$params
		);

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) || ! $rows ) {
			return array();
		}

		$ids = array_map(
			static function ( $row ) {
				return (int) $row->id;
			},
			$rows
		);

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$item_sql     = $wpdb->prepare(
			"SELECT id, order_id, product_id, quantity, unit_price
			FROM {$items_t}
			WHERE order_id IN ({$placeholders})
			ORDER BY id ASC",
			$ids
		);
		$item_rows = $wpdb->get_results( $item_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$by_order  = array();
		if ( is_array( $item_rows ) ) {
			foreach ( $item_rows as $item ) {
				$oid = (int) $item->order_id;
				if ( ! isset( $by_order[ $oid ] ) ) {
					$by_order[ $oid ] = array();
				}
				$by_order[ $oid ][] = $item;
			}
		}

		foreach ( $rows as $row ) {
			$row->items = isset( $by_order[ (int) $row->id ] ) ? $by_order[ (int) $row->id ] : array();
		}

		return $rows;
	}

	/**
	 * Eligible line items: unit_price must be set (sold price). Drop null/empty.
	 *
	 * @param object $order Order with items.
	 * @return array{items: object[], revenue: float, qty: int}
	 */
	public static function eligible_lines( $order ) {
		$items   = isset( $order->items ) && is_array( $order->items ) ? $order->items : array();
		$kept    = array();
		$revenue = 0.0;
		$qty     = 0;

		foreach ( $items as $item ) {
			if ( ! isset( $item->unit_price ) || null === $item->unit_price || '' === $item->unit_price ) {
				continue;
			}
			$line_qty = max( 1, (int) $item->quantity );
			$kept[]   = $item;
			$revenue += (float) $item->unit_price * $line_qty;
			$qty     += $line_qty;
		}

		return array(
			'items'   => $kept,
			'revenue' => SOM_Material_Costing::round4( $revenue ),
			'qty'     => $qty,
		);
	}

	/**
	 * Material COGS for an order from stock log snapshots (planned + extras).
	 *
	 * @param int $order_id Order PK.
	 * @return float
	 */
	public static function order_material_cogs( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return 0.0;
		}

		$log_t = SOM_DB::table( 'material_stock_log' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT change_qty, unit_cost_at_time, reason
				FROM {$log_t}
				WHERE order_id = %d AND reason IN (%s, %s, %s, %s, %s)",
				$order_id,
				'new_order',
				'order_usage_extra',
				'package_reserve',
				'package_unreserve',
				'package_usage_extra'
			)
		);

		$total = 0.0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				// Consumption (negative change_qty) adds COGS; unreserve (positive) reduces it.
				$total += -1 * (float) $row->change_qty * (float) $row->unit_cost_at_time;
			}
		}

		return SOM_Material_Costing::round4( max( 0.0, $total ) );
	}

	/**
	 * Order-level fee-aware profit for one order (eligible sold lines only).
	 *
	 * @param object $order Order row with items + channel_id.
	 * @return array{revenue: float, material: float, fees: float, profit: float, fee_source: string}|null Null if no eligible lines.
	 */
	public static function order_profit( $order ) {
		$eligible = self::eligible_lines( $order );
		if ( ! $eligible['items'] ) {
			return null;
		}

		$revenue  = $eligible['revenue'];
		$material = self::order_material_cogs( (int) $order->id );
		$fees     = SOM_Platform_Fees::fees_for_order( (int) $order->id, (int) $order->channel_id, $revenue );
		$profit   = SOM_Material_Costing::round4( $revenue - $material - (float) $fees['total'] );

		return array(
			'revenue'    => $revenue,
			'material'   => $material,
			'fees'       => (float) $fees['total'],
			'profit'     => $profit,
			'fee_source' => (string) $fees['source'],
		);
	}

	/**
	 * Sales / profit / AOV time series.
	 *
	 * @param array<int, object>   $orders  Loaded orders.
	 * @param array<string, mixed> $filters Filters.
	 * @param string[]             $labels  Bucket labels.
	 * @return array{sales: float[], profit: float[], aov: (float|null)[], totals: array<string, float|int>}
	 */
	public static function aggregate_sales_profit_aov( array $orders, array $filters, array $labels ) {
		$granularity = $filters['granularity'];
		$sales_map   = array_fill_keys( $labels, 0.0 );
		$profit_map  = array_fill_keys( $labels, 0.0 );
		$rev_map     = array_fill_keys( $labels, 0.0 );
		$count_map   = array_fill_keys( $labels, 0 );

		$total_sales  = 0.0;
		$total_profit = 0.0;
		$order_count  = 0;

		foreach ( $orders as $order ) {
			$metrics = self::order_profit( $order );
			if ( null === $metrics ) {
				continue;
			}

			$key = self::bucket_key( $order->order_date, $granularity );
			if ( null === $key || ! array_key_exists( $key, $sales_map ) ) {
				continue;
			}

			$sales_map[ $key ]  += $metrics['revenue'];
			$profit_map[ $key ] += $metrics['profit'];
			$rev_map[ $key ]    += $metrics['revenue'];
			++$count_map[ $key ];

			$total_sales  += $metrics['revenue'];
			$total_profit += $metrics['profit'];
			++$order_count;
		}

		$sales  = array();
		$profit = array();
		$aov    = array();
		foreach ( $labels as $label ) {
			$sales[]  = SOM_Material_Costing::round4( $sales_map[ $label ] );
			$profit[] = SOM_Material_Costing::round4( $profit_map[ $label ] );
			if ( $count_map[ $label ] > 0 ) {
				$aov[] = SOM_Material_Costing::round4( $rev_map[ $label ] / $count_map[ $label ] );
			} else {
				$aov[] = null;
			}
		}

		return array(
			'sales'  => $sales,
			'profit' => $profit,
			'aov'    => $aov,
			'totals' => array(
				'sales'       => SOM_Material_Costing::round4( $total_sales ),
				'profit'      => SOM_Material_Costing::round4( $total_profit ),
				'order_count' => $order_count,
				'aov'         => $order_count > 0 ? SOM_Material_Costing::round4( $total_sales / $order_count ) : null,
			),
		);
	}

	/**
	 * Orders by channel (bar counts for the full range).
	 *
	 * @param array<int, object>   $orders  Loaded orders.
	 * @param array<string, mixed> $filters Filters.
	 * @return array{labels: string[], counts: int[]}
	 */
	public static function aggregate_orders_by_channel( array $orders, array $filters ) {
		$counts = array();
		$names  = array();

		foreach ( $orders as $order ) {
			$cid = (int) $order->channel_id;
			if ( ! isset( $counts[ $cid ] ) ) {
				$counts[ $cid ] = 0;
				$names[ $cid ]  = isset( $order->channel_name ) ? (string) $order->channel_name : (string) $order->channel_slug;
			}
			++$counts[ $cid ];
		}

		// Ensure known channels appear when filter is "all".
		if ( (int) $filters['channel_id'] < 1 ) {
			foreach ( SOM_Channels::known() as $slug => $display ) {
				$row = SOM_Channels::get_by_slug( $slug );
				if ( ! $row ) {
					continue;
				}
				$cid = (int) $row->id;
				if ( ! isset( $counts[ $cid ] ) ) {
					$counts[ $cid ] = 0;
					$names[ $cid ]  = $display;
				}
			}
		}

		ksort( $counts );
		$labels = array();
		$values = array();
		foreach ( $counts as $cid => $count ) {
			$labels[] = $names[ $cid ];
			$values[] = (int) $count;
		}

		return array(
			'labels' => $labels,
			'counts' => $values,
		);
	}

	/**
	 * Material stock over time — walk backward from current_stock.
	 *
	 * Empty series when no materials selected.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param string[]             $labels  Bucket labels (date_from..date_to).
	 * @return array{series: array<int, array{material_id: int, name: string, unit: string, values: (float|null)[]}>}
	 */
	public static function aggregate_stock_series( array $filters, array $labels ) {
		$material_ids = isset( $filters['material_ids'] ) ? $filters['material_ids'] : array();
		if ( ! $material_ids || ! $labels ) {
			return array( 'series' => array() );
		}

		$series = array();
		foreach ( $material_ids as $material_id ) {
			$built = self::stock_series_for_material( (int) $material_id, $filters, $labels );
			if ( $built ) {
				$series[] = $built;
			}
		}

		return array( 'series' => $series );
	}

	/**
	 * Reconstruct balance-at-bucket for one material.
	 *
	 * Walks log backward from materials.current_stock, then samples balance at
	 * the end of each bucket within the selected range.
	 *
	 * @param int                  $material_id Material PK.
	 * @param array<string, mixed> $filters     Filters.
	 * @param string[]             $labels      Bucket labels.
	 * @return array{material_id: int, name: string, unit: string, values: (float|null)[]}|null
	 */
	public static function stock_series_for_material( $material_id, array $filters, array $labels ) {
		global $wpdb;

		$material_id = (int) $material_id;
		if ( $material_id < 1 ) {
			return null;
		}

		$materials_t = SOM_DB::table( 'materials' );
		$material    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, unit, current_stock FROM {$materials_t} WHERE id = %d LIMIT 1",
				$material_id
			)
		);
		if ( ! $material ) {
			return null;
		}

		$log_t = SOM_DB::table( 'material_stock_log' );
		$logs  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT change_qty, created_at
				FROM {$log_t}
				WHERE material_id = %d
				ORDER BY created_at DESC, id DESC",
				$material_id
			)
		);
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$granularity = $filters['granularity'];
		$values      = array();

		foreach ( $labels as $label ) {
			$bucket_end = self::bucket_end_utc( $label, $granularity, $filters['date_to'] );
			if ( null === $bucket_end ) {
				$values[] = null;
				continue;
			}

			// Balance at bucket_end = current_stock minus sum of changes strictly after bucket_end.
			$bal = (float) $material->current_stock;
			foreach ( $logs as $log ) {
				if ( (string) $log->created_at > $bucket_end ) {
					$bal -= (float) $log->change_qty;
				}
			}
			$values[] = round( $bal, 4 );
		}

		return array(
			'material_id' => $material_id,
			'name'        => (string) $material->name,
			'unit'        => (string) $material->unit,
			'values'      => $values,
		);
	}

	/**
	 * End-of-bucket datetime (UTC) for comparing against log created_at.
	 *
	 * @param string $label        Bucket key.
	 * @param string $granularity  daily|weekly|monthly.
	 * @param string $range_end    Inclusive Y-m-d of filter (cap).
	 * @return string|null Y-m-d H:i:s UTC.
	 */
	public static function bucket_end_utc( $label, $granularity, $range_end ) {
		$tz = wp_timezone();

		try {
			if ( 'monthly' === $granularity ) {
				$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $label . '-01 00:00:00', $tz );
				if ( ! $start ) {
					return null;
				}
				$end_local = $start->modify( 'last day of this month' )->setTime( 23, 59, 59 );
			} elseif ( 'weekly' === $granularity ) {
				// o-\WW e.g. 2026-W32
				if ( ! preg_match( '/^(\d{4})-W(\d{2})$/', $label, $m ) ) {
					return null;
				}
				$start = new DateTimeImmutable( 'now', $tz );
				$start = $start->setISODate( (int) $m[1], (int) $m[2] )->setTime( 0, 0, 0 );
				$end_local = $start->modify( '+6 days' )->setTime( 23, 59, 59 );
			} else {
				$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $label . ' 00:00:00', $tz );
				if ( ! $start ) {
					return null;
				}
				$end_local = $start->setTime( 23, 59, 59 );
			}
		} catch ( Exception $e ) {
			return null;
		}

		$cap = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $range_end . ' 23:59:59', $tz );
		if ( $cap && $end_local > $cap ) {
			$end_local = $cap;
		}

		return $end_local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
