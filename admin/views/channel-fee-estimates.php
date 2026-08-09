<?php
/**
 * Channel fee estimates list / create / edit admin view.
 *
 * @package OrderMachine
 *
 * @var object|null $estimate Estimate row when editing.
 * @var bool        $is_new   True when creating.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_edit  = isset( $estimate ) || ! empty( $is_new );
$is_new   = ! empty( $is_new );
$estimate = isset( $estimate ) ? $estimate : null;

$channels = array();
foreach ( array( 'ebay', 'etsy' ) as $slug ) {
	$row = SOM_Channels::get_by_slug( $slug );
	if ( $row ) {
		$channels[ (int) $row->id ] = $row;
	}
}

if ( $is_edit ) {
	$channel_id = $is_new ? 0 : (int) $estimate->channel_id;
	$component  = $is_new ? '' : (string) $estimate->fee_component;
	$rate_type  = $is_new ? 'percent' : (string) $estimate->rate_type;
	$rate_value = $is_new ? '' : (string) $estimate->rate_value;
	$min_val    = ( $is_new || null === $estimate->order_value_min || '' === $estimate->order_value_min )
		? ''
		: (string) $estimate->order_value_min;
	$max_val    = ( $is_new || null === $estimate->order_value_max || '' === $estimate->order_value_max )
		? ''
		: (string) $estimate->order_value_max;
	$is_enabled = $is_new ? true : (bool) $estimate->is_enabled;
	$notes      = $is_new ? '' : (string) ( $estimate->notes ?? '' );
	?>
	<div class="wrap som-catalog-wrap">
		<h1>
			<?php
			echo $is_new
				? esc_html__( 'Add fee estimate', 'order-machine' )
				: esc_html__( 'Edit fee estimate', 'order-machine' );
			?>
		</h1>
		<p>
			<a href="<?php echo esc_url( SOM_Channel_Fee_Estimates::list_url() ); ?>">
				&larr; <?php echo esc_html__( 'Back to channel fee estimates', 'order-machine' ); ?>
			</a>
		</p>

		<form method="post" action="<?php echo esc_url( SOM_Channel_Fee_Estimates::list_url() ); ?>" class="som-panel">
			<?php wp_nonce_field( 'som_save_fee_estimate', 'som_fee_estimate_nonce' ); ?>
			<input type="hidden" name="estimate_id" value="<?php echo $is_new ? '0' : esc_attr( (string) (int) $estimate->id ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="som_fee_channel_id"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></label></th>
					<td>
						<select name="som_fee_channel_id" id="som_fee_channel_id" required>
							<option value=""><?php echo esc_html__( 'Select…', 'order-machine' ); ?></option>
							<?php foreach ( $channels as $cid => $ch ) : ?>
								<option value="<?php echo esc_attr( (string) $cid ); ?>" <?php selected( $channel_id, $cid ); ?>>
									<?php echo esc_html( (string) $ch->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="som_fee_component"><?php echo esc_html__( 'Fee component', 'order-machine' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="som_fee_component" id="som_fee_component" value="<?php echo esc_attr( $component ); ?>" required pattern="[a-z0-9_]+" />
						<p class="description"><?php echo esc_html__( 'Lowercase key, e.g. final_value_fee, per_order_fee, payment_processing_fixed.', 'order-machine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="som_fee_rate_type"><?php echo esc_html__( 'Rate type', 'order-machine' ); ?></label></th>
					<td>
						<select name="som_fee_rate_type" id="som_fee_rate_type">
							<option value="percent" <?php selected( $rate_type, 'percent' ); ?>><?php echo esc_html__( 'Percent', 'order-machine' ); ?></option>
							<option value="fixed" <?php selected( $rate_type, 'fixed' ); ?>><?php echo esc_html__( 'Fixed (£)', 'order-machine' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="som_fee_rate_value"><?php echo esc_html__( 'Rate value', 'order-machine' ); ?></label></th>
					<td>
						<input type="number" step="0.0001" min="0" class="regular-text" name="som_fee_rate_value" id="som_fee_rate_value" value="<?php echo esc_attr( $rate_value ); ?>" required />
						<p class="description"><?php echo esc_html__( 'Percentage (0–100) or fixed £ amount, depending on rate type.', 'order-machine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Order value tier', 'order-machine' ); ?></th>
					<td>
						<label for="som_fee_order_value_min"><?php echo esc_html__( 'Min (inclusive)', 'order-machine' ); ?></label>
						<input type="number" step="0.01" min="0" class="small-text" name="som_fee_order_value_min" id="som_fee_order_value_min" value="<?php echo esc_attr( $min_val ); ?>" />
						&nbsp;
						<label for="som_fee_order_value_max"><?php echo esc_html__( 'Max (exclusive)', 'order-machine' ); ?></label>
						<input type="number" step="0.01" min="0" class="small-text" name="som_fee_order_value_max" id="som_fee_order_value_max" value="<?php echo esc_attr( $max_val ); ?>" />
						<p class="description"><?php echo esc_html__( 'Leave both blank for always applies. Example: under £10 → max 10; £10+ → min 10.', 'order-machine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Enabled', 'order-machine' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="som_fee_is_enabled" value="1" <?php checked( $is_enabled ); ?> />
							<?php echo esc_html__( 'Include this component in estimates', 'order-machine' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="som_fee_notes"><?php echo esc_html__( 'Notes', 'order-machine' ); ?></label></th>
					<td>
						<textarea name="som_fee_notes" id="som_fee_notes" class="large-text" rows="3"><?php echo esc_textarea( $notes ); ?></textarea>
					</td>
				</tr>
			</table>

			<?php submit_button( $is_new ? __( 'Add estimate', 'order-machine' ) : __( 'Save estimate', 'order-machine' ), 'primary', 'som_save_fee_estimate' ); ?>
		</form>
	</div>
	<?php
	return;
}

$grouped = SOM_Channel_Fee_Estimates::list_grouped_by_channel();
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Channel Fee Estimates', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Channel_Fee_Estimates::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add estimate', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<p class="description">
		<?php echo esc_html__( 'Estimated fee components used for pricing before real platform fees sync. Optional ads are included by default — uncheck Enabled to exclude them. Order-value tiers use min inclusive / max exclusive.', 'order-machine' ); ?>
	</p>

	<?php if ( empty( $channels ) ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html__( 'No channels found. Activate the plugin to seed eBay/Etsy channel rows.', 'order-machine' ); ?></p></div>
	<?php endif; ?>

	<?php foreach ( $channels as $cid => $ch ) : ?>
		<?php $rows = isset( $grouped[ $cid ] ) ? $grouped[ $cid ] : array(); ?>
		<div class="som-panel" style="margin-top:1.5em;">
			<h2><?php echo esc_html( (string) $ch->display_name ); ?></h2>
			<?php if ( empty( $rows ) ) : ?>
				<p><?php echo esc_html__( 'No estimate components yet.', 'order-machine' ); ?></p>
			<?php else : ?>
				<table class="widefat striped som-catalog-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Component', 'order-machine' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Rate', 'order-machine' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Tier', 'order-machine' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Enabled', 'order-machine' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Notes', 'order-machine' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Actions', 'order-machine' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><code><?php echo esc_html( (string) $row->fee_component ); ?></code></td>
								<td><?php echo esc_html( SOM_Channel_Fee_Estimates::format_rate( $row ) ); ?></td>
								<td><?php echo esc_html( SOM_Channel_Fee_Estimates::format_tier( $row ) ); ?></td>
								<td>
									<?php
									echo ! empty( $row->is_enabled )
										? esc_html__( 'Yes', 'order-machine' )
										: esc_html__( 'No', 'order-machine' );
									?>
								</td>
								<td><?php echo esc_html( (string) ( $row->notes ?? '' ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( SOM_Channel_Fee_Estimates::detail_url( (int) $row->id ) ); ?>">
										<?php echo esc_html__( 'Edit', 'order-machine' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( SOM_Channel_Fee_Estimates::delete_url( (int) $row->id ) ); ?>"
										onclick="return confirm('<?php echo esc_js( __( 'Delete this fee estimate?', 'order-machine' ) ); ?>');">
										<?php echo esc_html__( 'Delete', 'order-machine' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
