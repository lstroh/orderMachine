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
$timing   = isset( $payload['timing'] ) && is_array( $payload['timing'] ) ? $payload['timing'] : array();
$channels = array();
foreach ( SOM_Channels::known() as $slug => $name ) {
	if ( SOM_Production::CHANNEL_SLUG === $slug ) {
		continue; // Production jobs are excluded from sales / AOV / profit charts.
	}
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

$totals          = $payload['totals'];
$timing_kind     = isset( $timing['kind'] ) ? (string) $timing['kind'] : 'make';
$timing_tpl_id   = isset( $timing['template_id'] ) ? (int) $timing['template_id'] : 0;
$timing_templates = isset( $timing['templates'] ) && is_array( $timing['templates'] ) ? $timing['templates'] : array();
$timing_steps    = isset( $timing['steps'] ) && is_array( $timing['steps'] ) ? $timing['steps'] : array();
$workflow_totals = isset( $timing['workflow_totals'] ) && is_array( $timing['workflow_totals'] ) ? $timing['workflow_totals'] : array();
$wf_count        = isset( $workflow_totals['count'] ) ? (int) $workflow_totals['count'] : 0;
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

		<input type="hidden" name="som_timing_kind" value="<?php echo esc_attr( $timing_kind ); ?>" />
		<input type="hidden" name="som_timing_template" value="<?php echo esc_attr( (string) $timing_tpl_id ); ?>" />

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

	<section class="som-analytics-timing" id="som-analytics-timing">
		<h2><?php echo esc_html__( 'Workflow timing', 'order-machine' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Average wall-clock actual vs planned duration. Actual includes timer waits. Workflow totals use first step start → last step complete.', 'order-machine' ); ?>
		</p>

		<?php
		$timing_base_args = array(
			'page'            => 'som-analytics',
			'som_range'       => $filters['range'],
			'som_date_from'   => $filters['date_from'],
			'som_date_to'     => $filters['date_to'],
			'som_granularity' => $filters['granularity'],
			'som_channel'     => (string) (int) $filters['channel_id'],
		);
		if ( ! empty( $filters['material_ids'] ) ) {
			$timing_base_args['som_materials'] = array_map( 'strval', $filters['material_ids'] );
		}
		$make_tab_url = add_query_arg(
			array_merge(
				$timing_base_args,
				array(
					'som_timing_kind' => 'make',
				)
			),
			admin_url( 'admin.php' )
		);
		$pack_tab_url = add_query_arg(
			array_merge(
				$timing_base_args,
				array(
					'som_timing_kind' => 'pack',
				)
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="som-analytics-timing-tabs" role="tablist">
			<a href="<?php echo esc_url( $make_tab_url ); ?>" class="button <?php echo 'make' === $timing_kind ? 'button-primary' : ''; ?>" role="tab" aria-selected="<?php echo 'make' === $timing_kind ? 'true' : 'false'; ?>">
				<?php echo esc_html__( 'Make', 'order-machine' ); ?>
			</a>
			<a href="<?php echo esc_url( $pack_tab_url ); ?>" class="button <?php echo 'pack' === $timing_kind ? 'button-primary' : ''; ?>" role="tab" aria-selected="<?php echo 'pack' === $timing_kind ? 'true' : 'false'; ?>">
				<?php echo esc_html__( 'Pack', 'order-machine' ); ?>
			</a>
		</div>

		<form method="get" class="som-catalog-filters som-analytics-timing-filters">
			<input type="hidden" name="page" value="som-analytics" />
			<input type="hidden" name="som_range" value="<?php echo esc_attr( $filters['range'] ); ?>" />
			<input type="hidden" name="som_date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			<input type="hidden" name="som_date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
			<input type="hidden" name="som_granularity" value="<?php echo esc_attr( $filters['granularity'] ); ?>" />
			<input type="hidden" name="som_channel" value="<?php echo esc_attr( (string) (int) $filters['channel_id'] ); ?>" />
			<?php foreach ( $filters['material_ids'] as $mid ) : ?>
				<input type="hidden" name="som_materials[]" value="<?php echo esc_attr( (string) (int) $mid ); ?>" />
			<?php endforeach; ?>
			<input type="hidden" name="som_timing_kind" value="<?php echo esc_attr( $timing_kind ); ?>" />

			<label for="som-timing-template"><?php echo esc_html__( 'Template', 'order-machine' ); ?></label>
			<select name="som_timing_template" id="som-timing-template">
				<?php if ( ! $timing_templates ) : ?>
					<option value="0"><?php echo esc_html__( 'No active templates', 'order-machine' ); ?></option>
				<?php else : ?>
					<?php foreach ( $timing_templates as $tpl ) : ?>
						<option value="<?php echo esc_attr( (string) (int) $tpl->id ); ?>" <?php selected( $timing_tpl_id, (int) $tpl->id ); ?>>
							<?php echo esc_html( (string) $tpl->name ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
			<button type="submit" class="button"><?php echo esc_html__( 'Apply template', 'order-machine' ); ?></button>
		</form>

		<?php if ( $timing_tpl_id < 1 ) : ?>
			<p class="description"><?php echo esc_html__( 'Create an active Make or Pack workflow template to see timing.', 'order-machine' ); ?></p>
		<?php else : ?>
			<div class="som-analytics-timing-grid">
				<section class="som-analytics-chart-block som-analytics-chart-block--wide">
					<h3><?php echo esc_html__( 'Average actual vs planned by step', 'order-machine' ); ?></h3>
					<canvas id="som-chart-timing-steps" height="140"></canvas>
				</section>

				<section class="som-analytics-timing-table-wrap">
					<h3><?php echo esc_html__( 'Step averages', 'order-machine' ); ?></h3>
					<table class="widefat striped som-analytics-timing-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Step', 'order-machine' ); ?></th>
								<th><?php echo esc_html__( 'Avg planned', 'order-machine' ); ?></th>
								<th><?php echo esc_html__( 'Avg actual', 'order-machine' ); ?></th>
								<th><?php echo esc_html__( 'Samples', 'order-machine' ); ?></th>
								<th><?php echo esc_html__( 'Notes', 'order-machine' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! $timing_steps ) : ?>
								<tr>
									<td colspan="5"><?php echo esc_html__( 'No steps on this template.', 'order-machine' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $timing_steps as $step ) : ?>
									<?php
									$row_class = ! empty( $step['over_plan'] ) ? 'som-timing-over-plan' : '';
									?>
									<tr class="<?php echo esc_attr( $row_class ); ?>">
										<td><?php echo esc_html( (string) $step['name'] ); ?></td>
										<td><?php echo esc_html( SOM_Orders::format_duration_seconds( $step['avg_planned'] ) ); ?></td>
										<td><?php echo esc_html( SOM_Orders::format_duration_seconds( $step['avg_actual'] ) ); ?></td>
										<td><?php echo esc_html( (string) (int) $step['sample_count'] ); ?></td>
										<td>
											<?php
											if ( ! empty( $step['over_plan'] ) ) {
												echo esc_html__( 'Often over plan (>120%)', 'order-machine' );
											} else {
												echo '—';
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</section>

				<section class="som-analytics-timing-table-wrap">
					<h3>
						<?php
						echo 'pack' === $timing_kind
							? esc_html__( 'Pack workflow totals', 'order-machine' )
							: esc_html__( 'Make-line workflow totals', 'order-machine' );
						?>
					</h3>
					<p class="som-analytics-summary">
						<?php
						printf(
							/* translators: 1: completed count, 2: avg planned, 3: avg actual */
							esc_html__( '%1$d completed · Avg planned %2$s · Avg actual %3$s', 'order-machine' ),
							$wf_count,
							esc_html( SOM_Orders::format_duration_seconds( isset( $workflow_totals['avg_planned'] ) ? $workflow_totals['avg_planned'] : null ) ),
							esc_html( SOM_Orders::format_duration_seconds( isset( $workflow_totals['avg_actual'] ) ? $workflow_totals['avg_actual'] : null ) )
						);
						if ( ! empty( $workflow_totals['is_partial_plan'] ) ) {
							echo ' · ' . esc_html__( 'partial plan', 'order-machine' );
						}
						?>
					</p>
					<?php if ( $wf_count < 1 ) : ?>
						<p class="description"><?php echo esc_html__( 'No fully completed workflows for this template in the selected date range.', 'order-machine' ); ?></p>
					<?php else : ?>
						<table class="widefat striped som-analytics-timing-table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Order', 'order-machine' ); ?></th>
									<?php if ( 'make' === $timing_kind ) : ?>
										<th><?php echo esc_html__( 'Line', 'order-machine' ); ?></th>
									<?php endif; ?>
									<th><?php echo esc_html__( 'Planned', 'order-machine' ); ?></th>
									<th><?php echo esc_html__( 'Actual (span)', 'order-machine' ); ?></th>
									<th><?php echo esc_html__( 'Notes', 'order-machine' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$detail_rows = isset( $workflow_totals['rows'] ) && is_array( $workflow_totals['rows'] ) ? $workflow_totals['rows'] : array();
								// Cap display rows for readability; averages above use the full set.
								$shown = 0;
								foreach ( $detail_rows as $drow ) :
									if ( $shown >= 50 ) {
										break;
									}
									++$shown;
									$row_class = ! empty( $drow['over_plan'] ) ? 'som-timing-over-plan' : '';
									$order_url = add_query_arg(
										array(
											'page'     => 'som-orders',
											'order_id' => (int) $drow['order_id'],
										),
										admin_url( 'admin.php' )
									);
									?>
									<tr class="<?php echo esc_attr( $row_class ); ?>">
										<td>
											<a href="<?php echo esc_url( $order_url ); ?>">
												#<?php echo esc_html( (string) (int) $drow['order_id'] ); ?>
											</a>
										</td>
										<?php if ( 'make' === $timing_kind ) : ?>
											<td><?php echo esc_html( (string) (int) $drow['group_id'] ); ?></td>
										<?php endif; ?>
										<td><?php echo esc_html( SOM_Orders::format_duration_seconds( $drow['planned_seconds'] ) ); ?></td>
										<td><?php echo esc_html( SOM_Orders::format_duration_seconds( $drow['actual_seconds'] ) ); ?></td>
										<td>
											<?php
											echo ! empty( $drow['over_plan'] )
												? esc_html__( 'Over plan (>120%)', 'order-machine' )
												: '—';
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php if ( count( $detail_rows ) > 50 ) : ?>
							<p class="description">
								<?php
								printf(
									/* translators: %d: total completed workflows */
									esc_html__( 'Showing first 50 of %d completed workflows. Averages above use the full set.', 'order-machine' ),
									count( $detail_rows )
								);
								?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</section>
			</div>
		<?php endif; ?>
	</section>

	<script type="application/json" id="som-analytics-data"><?php echo wp_json_encode( $payload ); ?></script>
</div>
