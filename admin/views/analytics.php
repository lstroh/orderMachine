<?php
/**
 * Analytics dashboard admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$filters  = SOM_Analytics::parse_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$payload  = SOM_Analytics::dashboard_payload( $filters );
$channels = array();
foreach ( SOM_Channels::known() as $slug => $name ) {
	$row = SOM_Channels::get_by_slug( $slug );
	if ( $row ) {
		$channels[] = $row;
	}
}
$materials = SOM_Materials::list_active();

$range_options = array(
	'7'     => __( 'Last 7 days', 'order-machine' ),
	'30'    => __( 'Last 30 days', 'order-machine' ),
	'90'    => __( 'Last 90 days', 'order-machine' ),
	'year'  => __( 'This year', 'order-machine' ),
	'custom'=> __( 'Custom', 'order-machine' ),
);
$granularity_options = array(
	'daily'   => __( 'Daily', 'order-machine' ),
	'weekly'  => __( 'Weekly', 'order-machine' ),
	'monthly' => __( 'Monthly', 'order-machine' ),
);

$totals = $payload['totals'];
?>
<div class="wrap som-analytics-wrap">
	<h1><?php echo esc_html__( 'Analytics', 'order-machine' ); ?></h1>
	<hr class="wp-header-end" />

	<form method="get" class="som-catalog-filters som-analytics-filters">
		<input type="hidden" name="page" value="som-analytics" />

		<label for="som-range"><?php echo esc_html__( 'Date range', 'order-machine' ); ?></label>
		<select name="som_range" id="som-range">
			<?php foreach ( $range_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['range'], $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<span class="som-analytics-custom-dates" <?php echo 'custom' === $filters['range'] ? '' : 'hidden'; ?>>
			<label for="som-date-from"><?php echo esc_html__( 'From', 'order-machine' ); ?></label>
			<input type="date" id="som-date-from" name="som_date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			<label for="som-date-to"><?php echo esc_html__( 'To', 'order-machine' ); ?></label>
			<input type="date" id="som-date-to" name="som_date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
		</span>

		<label for="som-granularity"><?php echo esc_html__( 'Granularity', 'order-machine' ); ?></label>
		<select name="som_granularity" id="som-granularity">
			<?php foreach ( $granularity_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['granularity'], $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label for="som-channel"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></label>
		<select name="som_channel" id="som-channel">
			<option value="0"><?php echo esc_html__( 'All channels', 'order-machine' ); ?></option>
			<?php foreach ( $channels as $channel ) : ?>
				<option value="<?php echo esc_attr( (string) $channel->id ); ?>" <?php selected( (int) $filters['channel_id'], (int) $channel->id ); ?>>
					<?php echo esc_html( (string) $channel->display_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label for="som-materials"><?php echo esc_html__( 'Materials (stock chart)', 'order-machine' ); ?></label>
		<select name="som_materials[]" id="som-materials" multiple size="4">
			<?php foreach ( $materials as $material ) : ?>
				<option value="<?php echo esc_attr( (string) $material->id ); ?>" <?php echo in_array( (int) $material->id, $filters['material_ids'], true ) ? 'selected' : ''; ?>>
					<?php echo esc_html( $material->name . ' (' . $material->unit . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="submit" class="button button-primary"><?php echo esc_html__( 'Apply', 'order-machine' ); ?></button>
	</form>

	<p class="som-analytics-summary">
		<?php
		printf(
			/* translators: 1: sales total, 2: profit total, 3: order count */
			esc_html__( 'Sales £%1$s · Profit £%2$s · %3$d priced orders (cancelled/refunded excluded; lines without sold price dropped)', 'order-machine' ),
			esc_html( number_format_i18n( (float) $totals['sales'], 2 ) ),
			esc_html( number_format_i18n( (float) $totals['profit'], 2 ) ),
			(int) $totals['order_count']
		);
		?>
	</p>

	<div class="som-analytics-grid">
		<section class="som-analytics-chart-block">
			<h2><?php echo esc_html__( 'Sales over time', 'order-machine' ); ?></h2>
			<canvas id="som-chart-sales" height="120"></canvas>
		</section>
		<section class="som-analytics-chart-block">
			<h2><?php echo esc_html__( 'Profit over time', 'order-machine' ); ?></h2>
			<canvas id="som-chart-profit" height="120"></canvas>
		</section>
		<section class="som-analytics-chart-block">
			<h2><?php echo esc_html__( 'Average order value', 'order-machine' ); ?></h2>
			<canvas id="som-chart-aov" height="120"></canvas>
		</section>
		<section class="som-analytics-chart-block">
			<h2><?php echo esc_html__( 'Orders by channel', 'order-machine' ); ?></h2>
			<canvas id="som-chart-orders-channel" height="120"></canvas>
		</section>
		<section class="som-analytics-chart-block som-analytics-chart-block--wide">
			<h2><?php echo esc_html__( 'Material stock over time', 'order-machine' ); ?></h2>
			<?php if ( empty( $payload['stock']['series'] ) ) : ?>
				<p class="description"><?php echo esc_html__( 'Select one or more materials above and apply filters to plot stock.', 'order-machine' ); ?></p>
			<?php endif; ?>
			<canvas id="som-chart-stock" height="120" <?php echo empty( $payload['stock']['series'] ) ? 'hidden' : ''; ?>></canvas>
		</section>
	</div>

	<script type="application/json" id="som-analytics-data"><?php echo wp_json_encode( $payload ); ?></script>
</div>
