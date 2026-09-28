<?php
/**
 * Order detail admin view.
 *
 * @package OrderMachine
 *
 * @var object $order Order row from SOM_Orders::get().
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) || empty( $order ) ) {
	return;
}

$address_text = SOM_Orders::format_address( $order->shipping_address );
$back_url     = SOM_Orders::list_url();

$raw_pretty = '';
if ( ! empty( $order->raw_payload ) ) {
	$decoded = json_decode( (string) $order->raw_payload, true );
	if ( is_array( $decoded ) ) {
		$raw_pretty = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	} else {
		$raw_pretty = (string) $order->raw_payload;
	}
}
?>
<div class="wrap som-order-detail-wrap">
	<p>
		<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php echo esc_html__( 'Back to orders', 'order-machine' ); ?></a>
	</p>

	<h1>
		<?php
		printf(
			/* translators: 1: channel name, 2: external order id */
			esc_html__( '%1$s order %2$s', 'order-machine' ),
			esc_html( (string) $order->channel_name ),
			esc_html( (string) $order->external_order_id )
		);
		?>
	</h1>

	<?php
	$marketplace_order_url = SOM_Step_Confirmations::marketplace_order_url(
		(string) $order->channel_slug,
		(string) $order->external_order_id
	);
	?>
	<?php if ( $marketplace_order_url ) : ?>
		<p class="som-marketplace-links">
			<a class="button button-secondary" href="<?php echo esc_url( $marketplace_order_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php
				printf(
					/* translators: %s: channel display name */
					esc_html__( 'Open on %s', 'order-machine' ),
					esc_html( (string) $order->channel_name )
				);
				?>
			</a>
		</p>
	<?php endif; ?>

	<div class="som-order-meta">
		<span class="som-badge som-badge-channel"><?php echo esc_html( (string) $order->channel_name ); ?></span>
		<?php if ( ! empty( $order->is_complete ) ) : ?>
			<span class="som-badge som-badge-complete"><?php echo esc_html__( 'Complete', 'order-machine' ); ?></span>
		<?php else : ?>
			<span class="som-badge som-badge-open"><?php echo esc_html__( 'Open', 'order-machine' ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $order->is_cancelled ) ) : ?>
			<span class="som-badge som-badge-cancelled"><?php echo esc_html__( 'Cancelled', 'order-machine' ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $order->has_unmatched ) ) : ?>
			<span class="som-badge som-badge-unmatched"><?php echo esc_html__( 'Unmatched', 'order-machine' ); ?></span>
		<?php endif; ?>
		<span class="som-order-date">
			<?php
			printf(
				/* translators: %s: formatted datetime */
				esc_html__( 'Ordered %s', 'order-machine' ),
				esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order->order_date ) )
			);
			?>
		</span>
	</div>

	<?php if ( ! empty( $order->has_unmatched ) ) : ?>
		<div class="notice notice-warning inline som-unmatched-notice">
			<p><?php echo esc_html__( 'One or more line items could not be matched to a product. Map the listing under Listings (later sprint) or ignore if intentional.', 'order-machine' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $order->workflow_unassigned ) ) : ?>
		<div class="notice notice-warning inline som-workflow-notice">
			<p>
				<?php
				if ( 'needs_mapping' === $order->workflow_unassigned ) {
					echo esc_html__( 'No make workflow: one or more lines have no matched product.', 'order-machine' );
				} elseif ( 'partial' === $order->workflow_unassigned ) {
					echo esc_html__( 'Some lines are unmatched or missing a make template — Ship will stay blocked for those lines until fixed.', 'order-machine' );
				} else {
					echo esc_html__( 'No make workflow: matched products have no make template. Assign one on the product edit screen.', 'order-machine' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="som-order-highlight">
		<section class="som-panel som-panel-personalisation">
			<h2><?php echo esc_html__( 'Personalisation', 'order-machine' ); ?></h2>
			<?php
			$person_bits = array();
			foreach ( $order->items as $item ) {
				$text = isset( $item->personalisation_text ) ? trim( (string) $item->personalisation_text ) : '';
				if ( '' !== $text ) {
					$person_bits[] = $text;
				}
			}
			?>
			<?php if ( $person_bits ) : ?>
				<ul class="som-personalisation-list">
					<?php foreach ( $person_bits as $text ) : ?>
						<li><?php echo esc_html( $text ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="som-muted"><?php echo esc_html__( 'No personalisation text on this order.', 'order-machine' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="som-panel som-panel-shipping">
			<h2><?php echo esc_html__( 'Shipping address', 'order-machine' ); ?></h2>
			<?php if ( '' !== $address_text ) : ?>
				<address class="som-shipping-address">
					<?php echo nl2br( esc_html( $address_text ) ); ?>
				</address>
			<?php else : ?>
				<p class="som-muted"><?php echo esc_html__( 'No shipping address stored.', 'order-machine' ); ?></p>
			<?php endif; ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: buyer name */
					esc_html__( 'Buyer: %s', 'order-machine' ),
					esc_html( (string) $order->buyer_name )
				);
				?>
			</p>
		</section>
	</div>

	<?php
	$shipment      = ! empty( $order->shipment ) ? $order->shipment : SOM_Shipments::get_by_order( (int) $order->id );
	$ship_defaults = SOM_Shipments::defaults();
	$ship_form     = array(
		'carrier'            => $shipment ? (string) $shipment->carrier : $ship_defaults['carrier'],
		'service'            => $shipment ? (string) $shipment->service : $ship_defaults['service'],
		'shipped_at'         => $shipment ? SOM_Shipments::shipped_at_date_local( (string) $shipment->shipped_at ) : $ship_defaults['shipped_at'],
		'postage_paid'       => $shipment ? (string) $shipment->postage_paid : $ship_defaults['postage_paid'],
		'pack_weight_grams'  => $shipment && isset( $shipment->pack_weight_grams ) && null !== $shipment->pack_weight_grams && '' !== $shipment->pack_weight_grams
			? (string) $shipment->pack_weight_grams
			: '',
		'tracking_number'    => $shipment && $shipment->tracking_number ? (string) $shipment->tracking_number : '',
		'click_and_drop_ref' => $shipment && $shipment->click_and_drop_ref ? (string) $shipment->click_and_drop_ref : '',
	);
	$ship_status = SOM_Shipments::status_key( (int) $order->id );

	$planned_raw = isset( $order->planned_shipping_gbp ) && null !== $order->planned_shipping_gbp && '' !== $order->planned_shipping_gbp
		? (string) $order->planned_shipping_gbp
		: '';
	$planned_val = '' !== $planned_raw ? (float) $planned_raw : null;
	$actual_val  = $shipment ? (float) $shipment->postage_paid : null;
	$variance    = ( null !== $planned_val && null !== $actual_val )
		? round( $actual_val - $planned_val, 2 )
		: null;
	?>
	<section class="som-panel som-panel-planned-shipping" id="som-planned-shipping">
		<h2><?php echo esc_html__( 'Planned shipping', 'order-machine' ); ?></h2>
		<?php if ( 'internal' === (string) $order->channel_slug ) : ?>
			<p class="som-muted"><?php echo esc_html__( 'Internal production orders do not use planned postage.', 'order-machine' ); ?></p>
		<?php else : ?>
			<form method="post" action="" class="som-planned-shipping-form">
				<?php wp_nonce_field( 'som_save_planned_shipping', 'som_order_nonce' ); ?>
				<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
				<input type="hidden" name="som_save_planned_shipping" value="1" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="som_planned_shipping_gbp"><?php echo esc_html__( 'Planned (GBP)', 'order-machine' ); ?></label></th>
						<td>
							<input name="som_planned_shipping_gbp" id="som_planned_shipping_gbp" type="number" step="0.01" min="0" class="small-text" value="<?php echo esc_attr( $planned_raw ); ?>" />
							<p class="description"><?php echo esc_html__( 'Expected postage for this order. Seeded from product defaults on create; editing here is not overwritten by re-sync.', 'order-machine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Actual postage', 'order-machine' ); ?></th>
						<td>
							<?php if ( null !== $actual_val ) : ?>
								£<?php echo esc_html( number_format_i18n( $actual_val, 2 ) ); ?>
								<span class="som-muted"><?php echo esc_html__( '(from shipment)', 'order-machine' ); ?></span>
							<?php else : ?>
								<span class="som-muted"><?php echo esc_html__( 'Not recorded yet', 'order-machine' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( null !== $variance ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Variance', 'order-machine' ); ?></th>
							<td>
								<span class="<?php echo $variance > 0 ? 'som-shipping-over' : ( $variance < 0 ? 'som-shipping-under' : '' ); ?>">
									<?php
									printf(
										/* translators: %s: signed GBP variance */
										esc_html__( '%s (actual − planned)', 'order-machine' ),
										esc_html( ( $variance > 0 ? '+' : '' ) . '£' . number_format_i18n( $variance, 2 ) )
									);
									?>
								</span>
							</td>
						</tr>
					<?php endif; ?>
				</table>
				<?php submit_button( __( 'Save planned shipping', 'order-machine' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</section>

	<section class="som-panel som-panel-shipment">
		<h2><?php echo esc_html__( 'Shipment', 'order-machine' ); ?></h2>
		<?php if ( ! $shipment ) : ?>
			<p class="som-muted"><?php echo esc_html__( 'No shipment recorded yet. After Click & Drop, save what you posted.', 'order-machine' ); ?></p>
		<?php else : ?>
			<p>
				<span class="som-badge som-badge-shipment-<?php echo esc_attr( $ship_status ); ?>">
					<?php echo esc_html( SOM_Shipments::status_label( $ship_status ) ); ?>
				</span>
				<?php if ( ! empty( $shipment->tracking_pushed_at ) ) : ?>
					<span class="description">
						<?php
						printf(
							/* translators: %s: datetime */
							esc_html__( 'Pushed %s UTC', 'order-machine' ),
							esc_html( (string) $shipment->tracking_pushed_at )
						);
						?>
					</span>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $shipment->tracking_push_error ) ) : ?>
				<p class="som-script-error"><?php echo esc_html( (string) $shipment->tracking_push_error ); ?></p>
			<?php endif; ?>
		<?php endif; ?>

		<form method="post" action="" enctype="multipart/form-data" class="som-shipment-form">
			<?php wp_nonce_field( 'som_save_shipment', 'som_order_nonce' ); ?>
			<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
			<input type="hidden" name="som_save_shipment" value="1" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="som_ship_carrier"><?php echo esc_html__( 'Carrier', 'order-machine' ); ?></label></th>
					<td><input name="som_ship_carrier" id="som_ship_carrier" type="text" class="regular-text" value="<?php echo esc_attr( $ship_form['carrier'] ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="som_ship_service"><?php echo esc_html__( 'Service', 'order-machine' ); ?></label></th>
					<td><input name="som_ship_service" id="som_ship_service" type="text" class="regular-text" value="<?php echo esc_attr( $ship_form['service'] ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="som_ship_shipped_at"><?php echo esc_html__( 'Ship date', 'order-machine' ); ?></label></th>
					<td><input name="som_ship_shipped_at" id="som_ship_shipped_at" type="date" value="<?php echo esc_attr( $ship_form['shipped_at'] ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="som_ship_postage"><?php echo esc_html__( 'Postage paid (GBP)', 'order-machine' ); ?></label></th>
					<td><input name="som_ship_postage_paid" id="som_ship_postage" type="number" step="0.01" min="0" class="small-text" value="<?php echo esc_attr( $ship_form['postage_paid'] ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="som_ship_pack_weight"><?php echo esc_html__( 'Pack weight (g)', 'order-machine' ); ?></label></th>
					<td>
						<input name="som_ship_pack_weight_grams" id="som_ship_pack_weight" type="number" step="0.01" min="0" class="small-text" value="<?php echo esc_attr( $ship_form['pack_weight_grams'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Optional actual packed weight (goods + package). Does not change postage.', 'order-machine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="som_ship_tracking"><?php echo esc_html__( 'Tracking number', 'order-machine' ); ?></label></th>
					<td>
						<input name="som_ship_tracking_number" id="som_ship_tracking" type="text" class="regular-text" value="<?php echo esc_attr( $ship_form['tracking_number'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Leave blank for untracked 2nd Class. When set, tracking can be pushed to eBay/Etsy after Ship.', 'order-machine' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="som_ship_cad"><?php echo esc_html__( 'Click & Drop ref', 'order-machine' ); ?></label></th>
					<td><input name="som_ship_click_and_drop_ref" id="som_ship_cad" type="text" class="regular-text" value="<?php echo esc_attr( $ship_form['click_and_drop_ref'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="som_shipment_proof"><?php echo esc_html__( 'Proof of posting', 'order-machine' ); ?></label></th>
					<td>
						<?php if ( $shipment && ! empty( $shipment->proof_attachment_id ) ) : ?>
							<?php
							$proof_url = wp_get_attachment_url( (int) $shipment->proof_attachment_id );
							?>
							<p>
								<?php if ( $proof_url ) : ?>
									<a href="<?php echo esc_url( $proof_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'View current proof', 'order-machine' ); ?></a>
								<?php else : ?>
									<?php echo esc_html__( 'Proof attached.', 'order-machine' ); ?>
								<?php endif; ?>
							</p>
							<label>
								<input type="checkbox" name="som_ship_remove_proof" value="1" />
								<?php echo esc_html__( 'Remove proof', 'order-machine' ); ?>
							</label>
							<br /><br />
						<?php endif; ?>
						<input type="file" name="som_shipment_proof" id="som_shipment_proof" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" />
						<p class="description"><?php echo esc_html__( 'Optional JPEG, PNG, or PDF (max 5 MB). Not required for v1.', 'order-machine' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( $shipment ? __( 'Update shipment', 'order-machine' ) : __( 'Save shipment', 'order-machine' ), 'primary', 'submit', false ); ?>
		</form>

		<?php if ( $shipment && '' !== trim( (string) ( $shipment->tracking_number ?? '' ) ) ) : ?>
			<form method="post" action="" class="som-inline-form" style="margin-top:12px;">
				<?php wp_nonce_field( 'som_push_tracking', 'som_order_nonce' ); ?>
				<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
				<input type="hidden" name="som_push_tracking" value="1" />
				<?php
				$push_label = ! empty( $shipment->tracking_pushed_at )
					? __( 'Re-push tracking', 'order-machine' )
					: ( ! empty( $shipment->tracking_push_error ) ? __( 'Retry push tracking', 'order-machine' ) : __( 'Push tracking to channel', 'order-machine' ) );
				submit_button( $push_label, 'secondary', 'submit', false );
				?>
			</form>
		<?php endif; ?>
	</section>

	<section class="som-panel som-panel-make">
		<h2><?php echo esc_html__( 'Make', 'order-machine' ); ?></h2>
		<p class="description">
			<a href="<?php echo esc_url( SOM_Orders::board_url() ); ?>"><?php echo esc_html__( 'Open Make Board', 'order-machine' ); ?></a>
			— <?php echo esc_html__( 'Pack & Ship controls arrive in UP6-S2.', 'order-machine' ); ?>
		</p>
		<?php if ( ! empty( $order->is_cancelled ) ) : ?>
			<p class="description"><?php echo esc_html__( 'Cancelled — make actions are blocked.', 'order-machine' ); ?></p>
		<?php elseif ( empty( $order->make_lines ) ) : ?>
			<p class="som-muted"><?php echo esc_html__( 'No line items on this order.', 'order-machine' ); ?></p>
		<?php else : ?>
			<?php foreach ( $order->make_lines as $make_item ) : ?>
				<?php
				$line_id       = (int) $make_item->id;
				$line_product  = (string) ( $make_item->product_name ?? '' );
				$line_person   = isset( $make_item->personalisation_text ) ? trim( (string) $make_item->personalisation_text ) : '';
				$is_internal_l = ! empty( $make_item->product_is_internal );
				$line_complete = ! empty( $make_item->make_complete );
				$line_progress = isset( $make_item->make_progress ) && is_array( $make_item->make_progress ) ? $make_item->make_progress : array();
				$line_current  = isset( $make_item->make_current ) ? $make_item->make_current : null;
				$product_id_l  = isset( $make_item->product_id ) ? (int) $make_item->product_id : 0;
				?>
				<div class="som-make-line" data-som-order-item-id="<?php echo esc_attr( (string) $line_id ); ?>">
					<h3>
						<?php
						printf(
							/* translators: 1: quantity, 2: product name */
							esc_html__( '%1$d× %2$s', 'order-machine' ),
							(int) $make_item->quantity,
							$line_product !== '' ? $line_product : __( 'Unmatched item', 'order-machine' )
						);
						?>
						<?php if ( $is_internal_l ) : ?>
							<span class="som-badge som-badge-complete"><?php echo esc_html__( 'Pack-ready (internal)', 'order-machine' ); ?></span>
						<?php elseif ( $line_complete ) : ?>
							<span class="som-badge som-badge-complete"><?php echo esc_html__( 'Ready to pack', 'order-machine' ); ?></span>
						<?php elseif ( empty( $line_progress ) ) : ?>
							<span class="som-badge som-badge-needs-workflow"><?php echo esc_html__( 'No make progress', 'order-machine' ); ?></span>
						<?php endif; ?>
					</h3>
					<?php if ( '' !== $line_person ) : ?>
						<p class="som-personalisation-snippet"><?php echo esc_html( $line_person ); ?></p>
					<?php endif; ?>

					<?php if ( $is_internal_l || $line_complete || empty( $line_progress ) ) : ?>
						<?php /* status badge above is enough */ ?>
					<?php else : ?>
						<?php
						$line_confirm_kind  = null;
						$line_confirm_state = array();
						if ( $line_current ) {
							$line_confirm_kind  = SOM_Step_Confirmations::sanitize_kind(
								isset( $line_current->confirmation_kind ) ? $line_current->confirmation_kind : null
							);
							$line_confirm_state = SOM_Step_Confirmations::decode_state( $line_current );
						}
						?>
						<?php if ( $line_confirm_kind && empty( $order->is_cancelled ) && empty( $order->is_complete ) ) : ?>
							<div class="som-confirmation-panel">
								<h4><?php echo esc_html__( 'Confirmation checklist', 'order-machine' ); ?></h4>
								<form method="post" action="<?php echo esc_url( SOM_Orders::detail_url( (int) $order->id ) ); ?>">
									<?php wp_nonce_field( 'som_save_confirmation', 'som_order_nonce' ); ?>
									<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
									<input type="hidden" name="som_order_item_id" value="<?php echo esc_attr( (string) $line_id ); ?>" />
									<input type="hidden" name="som_save_confirmation" value="1" />
									<?php if ( SOM_Step_Confirmations::KIND_PRINT_VS_REQUEST === $line_confirm_kind ) : ?>
										<label class="som-confirm-check">
											<input type="checkbox" name="som_confirm[print_matches_request]" value="1" <?php checked( ! empty( $line_confirm_state['print_matches_request'] ) ); ?> />
											<?php echo esc_html__( 'I confirmed the print matches the client request', 'order-machine' ); ?>
										</label>
									<?php endif; ?>
									<?php
									submit_button( __( 'Save checklist', 'order-machine' ), 'secondary', 'submit', false );
									$line_confirm_ok = $line_current && SOM_Step_Confirmations::is_complete( $line_confirm_kind, $line_confirm_state, $order );
									?>
									<?php if ( $line_confirm_ok ) : ?>
										<span class="som-badge som-badge-complete"><?php echo esc_html__( 'Checklist complete', 'order-machine' ); ?></span>
									<?php endif; ?>
								</form>
							</div>
						<?php endif; ?>

						<ol class="som-workflow-progress">
							<?php foreach ( $line_progress as $row ) : ?>
								<?php
								$is_current = $line_current && (int) $row->id === (int) $line_current->id;
								$status     = (string) $row->status;
								$step_instructions = SOM_Step_Instructions::effective(
									$product_id_l,
									(int) $row->workflow_step_id
								);
								$step_obj = (object) array(
									'name'                    => $row->step_name,
									'timer_seconds'           => $row->timer_seconds,
									'requires_manual_confirm' => $row->requires_manual_confirm,
									'script_config'           => $row->script_config,
									'batch_group_id'          => isset( $row->batch_group_id ) ? $row->batch_group_id : null,
									'confirmation_kind'       => isset( $row->confirmation_kind ) ? $row->confirmation_kind : null,
								);
								$can_done    = $is_current && empty( $order->is_cancelled ) && empty( $order->is_complete ) && SOM_Workflow_Engine::can_mark_done( $row, $step_obj );
								$timer_ends  = ! empty( $row->timer_ends_at ) ? (string) $row->timer_ends_at : '';
								$ends_ts     = $timer_ends ? strtotime( $timer_ends . ' UTC' ) : 0;
								if ( ! $ends_ts && $timer_ends ) {
									$ends_ts = strtotime( $timer_ends );
								}
								$timer_ready  = $is_current && SOM_Workflow_Engine::is_timer_ready( $row );
								$badge_status = $timer_ready ? 'timer_ready' : $status;
								$step_classes = 'som-workflow-step';
								if ( $is_current ) {
									$step_classes .= ' is-current';
								}
								$step_classes .= ' status-' . $status;
								?>
								<li class="<?php echo esc_attr( $step_classes ); ?>">
									<div class="som-workflow-step-main">
										<strong><?php echo esc_html( (string) $row->step_name ); ?></strong>
										<span class="som-badge som-badge-step-<?php echo esc_attr( preg_replace( '/[^a-z0-9_]/', '', $badge_status ) ); ?>">
											<?php echo esc_html( SOM_Orders::progress_status_label( $badge_status ) ); ?>
										</span>
									</div>
									<?php if ( null !== $step_instructions && '' !== $step_instructions ) : ?>
										<div class="som-step-instructions">
											<strong class="som-step-instructions-label"><?php echo esc_html__( 'Instructions', 'order-machine' ); ?></strong>
											<div class="som-step-instructions-body"><?php echo esc_html( $step_instructions ); ?></div>
										</div>
									<?php endif; ?>
									<?php if ( $is_current && $timer_ready ) : ?>
										<p class="description"><?php echo esc_html__( 'Timer ready — you can Mark done.', 'order-machine' ); ?></p>
									<?php elseif ( $is_current && 'waiting_timer' === $status && $ends_ts ) : ?>
										<p class="description">
											<?php
											printf(
												/* translators: %s: datetime */
												esc_html__( 'Unlocks at %s UTC', 'order-machine' ),
												esc_html( gmdate( 'Y-m-d H:i:s', $ends_ts ) )
											);
											?>
										</p>
									<?php endif; ?>
									<?php if ( $is_current && empty( $order->is_cancelled ) && empty( $order->is_complete ) && 'waiting_script' !== $status ) : ?>
										<form method="post" action="<?php echo esc_url( SOM_Orders::detail_url( (int) $order->id ) ); ?>" class="som-mark-done-form">
											<?php wp_nonce_field( 'som_mark_step_done', 'som_order_nonce' ); ?>
											<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
											<input type="hidden" name="som_order_item_id" value="<?php echo esc_attr( (string) $line_id ); ?>" />
											<input type="hidden" name="som_mark_step_done" value="1" />
											<?php
											submit_button(
												__( 'Mark done', 'order-machine' ),
												'primary',
												'submit',
												false,
												$can_done ? array() : array( 'disabled' => 'disabled' )
											);
											?>
										</form>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>

	<?php
	$is_pack_bound = ! empty( $order->pack_workflow_template_id );
	$buyer_note    = SOM_Pack::buyer_note( $order );
	$ship_block    = $is_pack_bound || SOM_Production::CHANNEL_SLUG !== (string) $order->channel_slug
		? SOM_Pack::ship_blocked_reason( $order )
		: '';
	$packages_active = SOM_Shipping_Packages::list_active();
	?>
	<?php if ( SOM_Production::CHANNEL_SLUG !== (string) $order->channel_slug ) : ?>
	<section class="som-panel som-panel-pack" id="som-pack">
		<h2><?php echo esc_html__( 'Pack & Ship', 'order-machine' ); ?></h2>
		<p class="description">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=som-pack-board' ) ); ?>"><?php echo esc_html__( 'Open Pack Board', 'order-machine' ); ?></a>
		</p>

		<?php if ( empty( $order->pack_workflow_template_id ) ) : ?>
			<div class="notice notice-warning inline">
				<p><?php echo esc_html__( 'No Pack workflow bound. Choose a default Pack template under Settings (kind Pack), then create new orders — or wait for the migrate repair in UP6-S3.', 'order-machine' ); ?></p>
			</div>
		<?php endif; ?>

		<p>
			<?php if ( SOM_Pack::order_is_make_ready( $order ) ) : ?>
				<span class="som-badge som-badge-complete"><?php echo esc_html__( 'Make ready', 'order-machine' ); ?></span>
			<?php else : ?>
				<span class="som-badge som-badge-needs-workflow"><?php echo esc_html__( 'Waiting for make', 'order-machine' ); ?></span>
			<?php endif; ?>
			<?php if ( SOM_Pack::is_held( $order ) ) : ?>
				<span class="som-badge som-badge-error"><?php echo esc_html__( 'Held', 'order-machine' ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $ship_block ) : ?>
				<span class="description"><?php echo esc_html( $ship_block ); ?></span>
			<?php endif; ?>
		</p>

		<?php if ( ! empty( $order->packed_at ) ) : ?>
			<p class="description">
				<?php
				$packer = ! empty( $order->packed_by_user_id ) ? get_userdata( (int) $order->packed_by_user_id ) : null;
				printf(
					/* translators: 1: user display name, 2: datetime */
					esc_html__( 'Packed by %1$s at %2$s UTC', 'order-machine' ),
					esc_html( $packer ? $packer->display_name : __( 'Unknown', 'order-machine' ) ),
					esc_html( (string) $order->packed_at )
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( '' !== $buyer_note ) : ?>
			<div class="som-pack-buyer-note">
				<h3><?php echo esc_html__( 'Buyer / channel note', 'order-machine' ); ?></h3>
				<p><?php echo nl2br( esc_html( $buyer_note ) ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="som-pack-package-form">
			<?php wp_nonce_field( 'som_save_pack_package', 'som_order_nonce' ); ?>
			<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
			<input type="hidden" name="som_save_pack_package" value="1" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="som_shipping_package_id"><?php echo esc_html__( 'Shipping package', 'order-machine' ); ?></label></th>
					<td>
						<select name="som_shipping_package_id" id="som_shipping_package_id">
							<option value="0"><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
							<?php foreach ( $packages_active as $pkg ) : ?>
								<option value="<?php echo esc_attr( (string) (int) $pkg->id ); ?>" <?php selected( (int) ( $order->shipping_package_id ?? 0 ), (int) $pkg->id ); ?>>
									<?php echo esc_html( (string) $pkg->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php echo esc_html__( 'Required before Ship. Product default is a suggestion only.', 'order-machine' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save package', 'order-machine' ), 'secondary', 'submit', false ); ?>
		</form>

		<form method="post" action="" class="som-pack-hold-form" style="margin-top:1em;">
			<?php wp_nonce_field( 'som_save_pack_hold', 'som_order_nonce' ); ?>
			<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
			<?php if ( SOM_Pack::is_held( $order ) ) : ?>
				<input type="hidden" name="som_clear_pack_hold" value="1" />
				<p><strong><?php echo esc_html__( 'Hold reason:', 'order-machine' ); ?></strong> <?php echo esc_html( (string) $order->pack_hold_reason ); ?></p>
				<?php submit_button( __( 'Clear hold', 'order-machine' ), 'secondary', 'submit', false ); ?>
			<?php else : ?>
				<input type="hidden" name="som_set_pack_hold" value="1" />
				<label for="som_pack_hold_reason"><?php echo esc_html__( 'Hold pack', 'order-machine' ); ?></label>
				<input type="text" class="regular-text" name="som_pack_hold_reason" id="som_pack_hold_reason" placeholder="<?php echo esc_attr__( 'Reason (required)', 'order-machine' ); ?>" />
				<?php submit_button( __( 'Hold pack', 'order-machine' ), 'secondary', 'submit', false ); ?>
			<?php endif; ?>
		</form>

		<div class="som-pack-print" style="margin-top:1.5em;">
			<button type="button" class="button" onclick="window.print();"><?php echo esc_html__( 'Print pack list', 'order-machine' ); ?></button>
			<div class="som-pack-print-sheet">
				<h3><?php echo esc_html__( 'Pack list', 'order-machine' ); ?></h3>
				<p>
					<code><?php echo esc_html( (string) $order->external_order_id ); ?></code>
					· <?php echo esc_html( (string) $order->channel_name ); ?>
					· <?php echo esc_html( (string) $order->buyer_name ); ?>
				</p>
				<?php if ( '' !== $address_text ) : ?>
					<address><?php echo nl2br( esc_html( $address_text ) ); ?></address>
				<?php endif; ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Qty', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Item', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Personalisation', 'order-machine' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $order->items as $item ) : ?>
							<tr>
								<td><?php echo esc_html( (string) (int) $item->quantity ); ?></td>
								<td><?php echo esc_html( ! empty( $item->product_name ) ? (string) $item->product_name : __( 'Unmatched', 'order-machine' ) ); ?></td>
								<td><?php echo esc_html( isset( $item->personalisation_text ) ? (string) $item->personalisation_text : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>☐ <?php echo esc_html__( 'Thank-you included', 'order-machine' ); ?></p>
				<?php if ( '' !== $buyer_note ) : ?>
					<p><strong><?php echo esc_html__( 'Buyer note:', 'order-machine' ); ?></strong> <?php echo esc_html( $buyer_note ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<style>
		@media print {
			body * { visibility: hidden !important; }
			.som-pack-print-sheet, .som-pack-print-sheet * { visibility: visible !important; }
			.som-pack-print-sheet { position: absolute; left: 0; top: 0; width: 100%; }
		}
	</style>
	<?php endif; ?>

	<?php if ( ! empty( $order->workflow_progress ) ) : ?>
	<section class="som-panel som-panel-workflow">
		<h2>
			<?php
			echo esc_html(
				$is_pack_bound
					? __( 'Pack workflow', 'order-machine' )
					: __( 'Workflow (legacy order-level)', 'order-machine' )
			);
			?>
		</h2>
		<?php if ( ! empty( $order->is_cancelled ) ) : ?>
			<p class="description"><?php echo esc_html__( 'Cancelled — workflow actions are blocked.', 'order-machine' ); ?></p>
		<?php elseif ( ! empty( $order->is_complete ) ) : ?>
			<p><span class="som-badge som-badge-complete"><?php echo esc_html__( 'Workflow complete', 'order-machine' ); ?></span></p>
		<?php else : ?>
			<?php
			$current_confirm_kind  = null;
			$current_confirm_state = array();
			$current_confirm_row   = null;
			foreach ( $order->workflow_progress as $prog_row ) {
				if ( (int) $prog_row->workflow_step_id === (int) $order->current_step_id ) {
					$current_confirm_kind  = SOM_Step_Confirmations::sanitize_kind(
						isset( $prog_row->confirmation_kind ) ? $prog_row->confirmation_kind : null
					);
					$current_confirm_state = SOM_Step_Confirmations::decode_state( $prog_row );
					$current_confirm_row   = $prog_row;
					break;
				}
			}
			?>
			<?php if ( $current_confirm_kind && empty( $order->is_cancelled ) ) : ?>
				<div class="som-confirmation-panel" data-som-confirmation-kind="<?php echo esc_attr( $current_confirm_kind ); ?>">
					<h3><?php echo esc_html__( 'Confirmation checklist', 'order-machine' ); ?></h3>
					<form method="post" action="<?php echo esc_url( SOM_Orders::detail_url( (int) $order->id ) ); ?>" class="som-confirm-step-form" data-som-confirm-step data-order-id="<?php echo esc_attr( (string) (int) $order->id ); ?>">
						<?php wp_nonce_field( 'som_save_confirmation', 'som_order_nonce' ); ?>
						<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
						<input type="hidden" name="som_save_confirmation" value="1" />

						<?php if ( SOM_Step_Confirmations::KIND_PRINT_VS_REQUEST === $current_confirm_kind ) : ?>
							<p class="description"><?php echo esc_html__( 'Compare what you will print with the client request on the marketplace order.', 'order-machine' ); ?></p>
							<ul class="som-confirm-compare-list">
								<?php foreach ( $order->items as $item ) : ?>
									<?php
									$listing_id  = SOM_Step_Confirmations::listing_id_for_item( $item, $order );
									$listing_url = SOM_Step_Confirmations::marketplace_listing_url( (string) $order->channel_slug, $listing_id );
									$pname       = ! empty( $item->product_name ) ? (string) $item->product_name : __( 'Unmatched item', 'order-machine' );
									$person      = isset( $item->personalisation_text ) ? trim( (string) $item->personalisation_text ) : '';
									?>
									<li>
										<strong><?php echo esc_html( (string) (int) $item->quantity ); ?>× <?php echo esc_html( $pname ); ?></strong>
										<?php if ( ! empty( $item->product_sku ) ) : ?>
											<code><?php echo esc_html( (string) $item->product_sku ); ?></code>
										<?php endif; ?>
										<?php if ( '' !== $person ) : ?>
											<div class="som-confirm-person"><?php echo esc_html( $person ); ?></div>
										<?php else : ?>
											<div class="som-muted"><?php echo esc_html__( 'No personalisation text.', 'order-machine' ); ?></div>
										<?php endif; ?>
										<p class="som-confirm-links">
											<?php if ( $marketplace_order_url ) : ?>
												<a href="<?php echo esc_url( $marketplace_order_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open marketplace order', 'order-machine' ); ?></a>
											<?php endif; ?>
											<?php if ( $listing_url ) : ?>
												<?php if ( $marketplace_order_url ) : ?> · <?php endif; ?>
												<a href="<?php echo esc_url( $listing_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open listing', 'order-machine' ); ?></a>
											<?php endif; ?>
										</p>
									</li>
								<?php endforeach; ?>
							</ul>
							<div class="som-confirm-files som-muted">
								<p><?php echo esc_html__( 'Print files are not attached yet — they will appear here later.', 'order-machine' ); ?></p>
							</div>
							<label class="som-confirm-check">
								<input type="checkbox" name="som_confirm[print_matches_request]" value="1" <?php checked( ! empty( $current_confirm_state['print_matches_request'] ) ); ?> />
								<?php echo esc_html__( 'I confirmed the print matches the client request', 'order-machine' ); ?>
							</label>

						<?php elseif ( SOM_Step_Confirmations::KIND_SHIPPING_ADDRESS === $current_confirm_kind ) : ?>
							<p class="description"><?php echo esc_html__( 'Compare the shipping address you will print (e.g. Click & Drop) with the marketplace order.', 'order-machine' ); ?></p>
							<?php if ( '' !== $address_text ) : ?>
								<div class="som-confirm-address-block">
									<address class="som-shipping-address"><?php echo nl2br( esc_html( $address_text ) ); ?></address>
									<button type="button" class="button button-small" data-som-copy-text="<?php echo esc_attr( $address_text ); ?>">
										<?php echo esc_html__( 'Copy address', 'order-machine' ); ?>
									</button>
								</div>
							<?php else : ?>
								<p class="som-muted"><?php echo esc_html__( 'No shipping address stored.', 'order-machine' ); ?></p>
							<?php endif; ?>
							<p>
								<?php
								printf(
									/* translators: %s: buyer name */
									esc_html__( 'Buyer: %s', 'order-machine' ),
									esc_html( (string) $order->buyer_name )
								);
								?>
							</p>
							<?php if ( $marketplace_order_url ) : ?>
								<p>
									<a class="button button-secondary" href="<?php echo esc_url( $marketplace_order_url ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html__( 'Open marketplace order', 'order-machine' ); ?>
									</a>
								</p>
							<?php endif; ?>
							<label class="som-confirm-check">
								<input type="checkbox" name="som_confirm[address_matches_marketplace]" value="1" <?php checked( ! empty( $current_confirm_state['address_matches_marketplace'] ) ); ?> />
								<?php echo esc_html__( 'I confirmed the shipping address matches the marketplace', 'order-machine' ); ?>
							</label>

						<?php elseif ( SOM_Step_Confirmations::KIND_PACKING_ITEMS === $current_confirm_kind ) : ?>
							<p class="description"><?php echo esc_html__( 'Tick each line once it is in the package, plus thank-you included.', 'order-machine' ); ?></p>
							<?php
							$items_checked = isset( $current_confirm_state['items'] ) && is_array( $current_confirm_state['items'] )
								? $current_confirm_state['items']
								: array();
							?>
							<ul class="som-confirm-pack-list">
								<?php foreach ( $order->items as $item ) : ?>
									<?php $iid = (string) (int) $item->id; ?>
									<li>
										<label class="som-confirm-check">
											<input type="checkbox" name="som_confirm[items][<?php echo esc_attr( $iid ); ?>]" value="1" <?php checked( ! empty( $items_checked[ $iid ] ) ); ?> />
											<?php echo esc_html( SOM_Step_Confirmations::packing_item_label( $item ) ); ?>
										</label>
									</li>
								<?php endforeach; ?>
								<li>
									<label class="som-confirm-check">
										<input type="checkbox" name="som_confirm[thank_you_included]" value="1" <?php checked( ! empty( $current_confirm_state['thank_you_included'] ) ); ?> />
										<?php echo esc_html__( 'Thank-you included', 'order-machine' ); ?>
									</label>
								</li>
							</ul>
						<?php endif; ?>

						<?php
						submit_button(
							__( 'Save checklist', 'order-machine' ),
							'secondary',
							'submit',
							false
						);
						$confirm_complete = $current_confirm_row
							&& SOM_Step_Confirmations::is_complete( $current_confirm_kind, $current_confirm_state, $order );
						?>
						<?php if ( $confirm_complete ) : ?>
							<span class="som-badge som-badge-complete"><?php echo esc_html__( 'Checklist complete', 'order-machine' ); ?></span>
						<?php else : ?>
							<span class="description"><?php echo esc_html__( 'Mark done unlocks after every required box is ticked and saved.', 'order-machine' ); ?></span>
						<?php endif; ?>
					</form>
				</div>
			<?php endif; ?>

			<?php
			$primary_product_id = SOM_Workflow_Engine::primary_product_id( $order );
			?>
			<ol class="som-workflow-progress">
				<?php foreach ( $order->workflow_progress as $row ) : ?>
					<?php
					$is_current = (int) $row->workflow_step_id === (int) $order->current_step_id;
					$status     = (string) $row->status;
					$step_instructions = SOM_Step_Instructions::effective(
						$primary_product_id ? (int) $primary_product_id : 0,
						(int) $row->workflow_step_id
					);
					$step_obj   = (object) array(
						'name'                    => $row->step_name,
						'timer_seconds'           => $row->timer_seconds,
						'requires_manual_confirm' => $row->requires_manual_confirm,
						'script_config'           => $row->script_config,
						'batch_group_id'          => isset( $row->batch_group_id ) ? $row->batch_group_id : null,
						'confirmation_kind'       => isset( $row->confirmation_kind ) ? $row->confirmation_kind : null,
					);
					$can_done   = $is_current && empty( $order->is_cancelled ) && SOM_Workflow_Engine::can_mark_done( $row, $step_obj );
					$can_retry  = $is_current && empty( $order->is_cancelled ) && SOM_Workflow_Engine::can_retry_script( $row, $step_obj );
					$timer_ends = ! empty( $row->timer_ends_at ) ? (string) $row->timer_ends_at : '';
					$ends_ts    = $timer_ends ? strtotime( $timer_ends . ' UTC' ) : 0;
					if ( ! $ends_ts && $timer_ends ) {
						$ends_ts = strtotime( $timer_ends );
					}
					$timer_ready = $is_current && SOM_Workflow_Engine::is_timer_ready( $row );
					$last_error = isset( $row->last_error ) ? (string) $row->last_error : '';
					$waiting_cb = ( 0 === strpos( $last_error, 'waiting_callback:' ) );
					$display_err = ( $last_error && ! $waiting_cb ) ? $last_error : '';
					$badge_status = $timer_ready ? 'timer_ready' : $status;
					$step_classes = 'som-workflow-step';
					if ( $is_current ) {
						$step_classes .= ' is-current';
					}
					$step_classes .= ' status-' . $status;
					if ( $timer_ready ) {
						$step_classes .= ' is-timer-ready';
					}
					?>
					<li class="<?php echo esc_attr( $step_classes ); ?>">
						<div class="som-workflow-step-main">
							<strong><?php echo esc_html( (string) $row->step_name ); ?></strong>
							<span class="som-badge som-badge-step-<?php echo esc_attr( $badge_status ); ?>" data-som-step-status-badge>
								<?php
								$labels = array(
									'pending'        => __( 'Pending', 'order-machine' ),
									'in_progress'    => __( 'In progress', 'order-machine' ),
									'waiting_timer'  => __( 'Waiting (timer)', 'order-machine' ),
									'waiting_script' => __( 'Waiting (script)', 'order-machine' ),
									'waiting_batch'  => __( 'Waiting (batch)', 'order-machine' ),
									'timer_ready'    => __( 'Timer ready', 'order-machine' ),
									'error'          => __( 'Error', 'order-machine' ),
									'done'           => __( 'Done', 'order-machine' ),
								);
								echo esc_html( isset( $labels[ $badge_status ] ) ? $labels[ $badge_status ] : $status );
								?>
							</span>
							<?php if ( ! empty( $row->confirmation_kind ) ) : ?>
								<span class="som-badge som-badge-confirm"><?php echo esc_html__( 'Confirm', 'order-machine' ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( null !== $step_instructions && '' !== $step_instructions ) : ?>
							<div class="som-step-instructions">
								<strong class="som-step-instructions-label"><?php echo esc_html__( 'Instructions', 'order-machine' ); ?></strong>
								<div class="som-step-instructions-body"><?php echo esc_html( $step_instructions ); ?></div>
							</div>
						<?php endif; ?>
						<?php if ( $is_current && $timer_ready ) : ?>
							<p class="som-timer-countdown som-timer-ready description" data-som-timer-ready-msg>
								<?php echo esc_html__( 'Timer ready — you can Mark done.', 'order-machine' ); ?>
							</p>
						<?php elseif ( $is_current && 'waiting_timer' === $status && $ends_ts ) : ?>
							<p class="som-timer-countdown description"
								data-som-countdown
								data-ends-at="<?php echo esc_attr( (string) $ends_ts ); ?>"
								data-order-id="<?php echo esc_attr( (string) (int) $order->id ); ?>"
								data-step-name="<?php echo esc_attr( (string) $row->step_name ); ?>"
								data-order-ref="<?php echo esc_attr( (string) $order->external_order_id ); ?>"
								data-ready-label="<?php echo esc_attr__( 'Timer ready — you can Mark done.', 'order-machine' ); ?>"
								data-ready-badge="<?php echo esc_attr__( 'Timer ready', 'order-machine' ); ?>">
								<?php
								printf(
									/* translators: %s: datetime */
									esc_html__( 'Unlocks at %s UTC', 'order-machine' ),
									esc_html( gmdate( 'Y-m-d H:i:s', $ends_ts ) )
								);
								?>
							</p>
						<?php endif; ?>
						<?php if ( $is_current && 'waiting_batch' === $status ) : ?>
							<?php
							$order_batch = SOM_Batches::find_for_order( (int) $order->id );
							?>
							<?php if ( $order_batch ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: 1: batch group name, 2: current count, 3: batch size, 4: batch id */
										esc_html__( 'In batch #%4$d (%1$s): %2$d of %3$d.', 'order-machine' ),
										esc_html( (string) $order_batch->group_name ),
										(int) $order_batch->item_count,
										max( 1, (int) $order_batch->group_batch_size ),
										(int) $order_batch->id
									);
									?>
									<a href="<?php echo esc_url( SOM_Batches::batch_url( (int) $order_batch->id ) ); ?>">
										<?php echo esc_html__( 'View batch', 'order-machine' ); ?>
									</a>
								</p>
							<?php else : ?>
								<p class="description"><?php echo esc_html__( 'Waiting for a batch (batch record not found).', 'order-machine' ); ?></p>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( $is_current && $waiting_cb ) : ?>
							<p class="description"><?php echo esc_html__( 'Waiting for external callback (n8n / API).', 'order-machine' ); ?></p>
						<?php endif; ?>
						<?php if ( $is_current && $display_err ) : ?>
							<p class="som-script-error"><?php echo esc_html( $display_err ); ?></p>
							<?php if ( (int) $row->retry_count > 0 ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %d: retry count */
										esc_html__( 'Attempts so far: %d', 'order-machine' ),
										(int) $row->retry_count
									);
									?>
								</p>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( $is_current && empty( $order->is_cancelled ) ) : ?>
							<?php if ( $can_retry ) : ?>
								<form method="post" action="<?php echo esc_url( SOM_Orders::detail_url( (int) $order->id ) ); ?>" class="som-retry-script-form">
									<?php wp_nonce_field( 'som_retry_script', 'som_order_nonce' ); ?>
									<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
									<input type="hidden" name="som_retry_script" value="1" />
									<?php
									submit_button(
										__( 'Retry now', 'order-machine' ),
										'secondary',
										'submit',
										false
									);
									?>
								</form>
							<?php endif; ?>
							<?php if ( 'error' !== $status && 'waiting_script' !== $status && 'waiting_batch' !== $status ) : ?>
								<form method="post" action="<?php echo esc_url( SOM_Orders::detail_url( (int) $order->id ) ); ?>" class="som-mark-done-form" data-som-advance-step data-order-id="<?php echo esc_attr( (string) (int) $order->id ); ?>">
									<?php wp_nonce_field( 'som_mark_step_done', 'som_order_nonce' ); ?>
									<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
									<input type="hidden" name="som_mark_step_done" value="1" />
									<?php
									submit_button(
										__( 'Mark done', 'order-machine' ),
										'primary',
										'submit',
										false,
										$can_done ? array() : array( 'disabled' => 'disabled' )
									);
									?>
								</form>
							<?php elseif ( 'waiting_batch' === $status ) : ?>
								<p class="description"><?php echo esc_html__( 'This step advances when the batch is released / marked done — not per order.', 'order-machine' ); ?></p>
							<?php endif; ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<section class="som-panel som-panel-stock">
		<h2><?php echo esc_html__( 'Materials used', 'order-machine' ); ?></h2>
		<?php
		$stock = isset( $order->stock_summary ) && is_array( $order->stock_summary )
			? $order->stock_summary
			: array(
				'status'                 => 'none',
				'lines'                  => array(),
				'has_new_order'          => false,
				'has_cancelled_reversal' => false,
			);
		$stock_status   = isset( $stock['status'] ) ? (string) $stock['status'] : 'none';
		$stock_lines    = isset( $stock['lines'] ) && is_array( $stock['lines'] ) ? $stock['lines'] : array();
		$materials_used = isset( $order->materials_used ) && is_array( $order->materials_used ) ? $order->materials_used : array();
		?>
		<?php if ( ! empty( $materials_used ) ) : ?>
			<p>
				<?php if ( 'reserved' === $stock_status ) : ?>
					<span class="som-badge som-badge-stock-reserved"><?php echo esc_html__( 'Stock reserved', 'order-machine' ); ?></span>
				<?php elseif ( 'reversed' === $stock_status ) : ?>
					<span class="som-badge som-badge-stock-reversed"><?php echo esc_html__( 'Stock reversed', 'order-machine' ); ?></span>
				<?php endif; ?>
			</p>
			<p class="description">
				<?php echo esc_html__( 'Planned amounts come from the product recipe. You can raise Actual if you used more — stock, order profit, and material budgets update by the extra. You cannot go below planned or reduce a previous actual.', 'order-machine' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-orders&order_id=' . (int) $order->id ) ); ?>" class="som-materials-used-form">
				<?php wp_nonce_field( 'som_save_materials_used', 'som_order_nonce' ); ?>
				<input type="hidden" name="som_save_materials_used" value="1" />
				<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
				<table class="widefat striped som-stock-table som-materials-used-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Material', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Planned', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Extra', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Actual', 'order-machine' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $materials_used as $usage_row ) : ?>
							<tr>
								<td>
									<?php echo esc_html( (string) $usage_row->material_name ); ?>
									<?php if ( ! empty( $usage_row->material_unit ) ) : ?>
										<span class="som-muted">(<?php echo esc_html( (string) $usage_row->material_unit ); ?>)</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format_i18n( (float) $usage_row->planned, 2 ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $usage_row->extra, 2 ) ); ?></td>
								<td>
									<input
										type="number"
										step="0.01"
										min="<?php echo esc_attr( (string) (float) $usage_row->actual ); ?>"
										name="som_material_actual[<?php echo esc_attr( (string) (int) $usage_row->material_id ); ?>]"
										value="<?php echo esc_attr( (string) (float) $usage_row->actual ); ?>"
										class="small-text"
										<?php disabled( 'reversed' === $stock_status ); ?>
									/>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( 'reversed' !== $stock_status ) : ?>
					<p class="submit" style="margin-top:12px;">
						<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save materials used', 'order-machine' ); ?></button>
					</p>
				<?php endif; ?>
			</form>
			<?php if ( ! empty( $stock_lines ) ) : ?>
				<details class="som-stock-log-details" style="margin-top:12px;">
					<summary><?php echo esc_html__( 'Stock log for this order', 'order-machine' ); ?></summary>
					<table class="widefat striped som-stock-table" style="margin-top:8px;">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Material', 'order-machine' ); ?></th>
								<th><?php echo esc_html__( 'Change', 'order-machine' ); ?></th>
								<th><?php echo esc_html__( 'Reason', 'order-machine' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stock_lines as $line ) : ?>
								<tr>
									<td>
										<?php echo esc_html( (string) $line->material_name ); ?>
										<?php if ( ! empty( $line->material_unit ) ) : ?>
											<span class="som-muted">(<?php echo esc_html( (string) $line->material_unit ); ?>)</span>
										<?php endif; ?>
									</td>
									<td>
										<?php
										$change = (float) $line->change_qty;
										echo esc_html( ( $change > 0 ? '+' : '' ) . number_format_i18n( $change, 2 ) );
										?>
									</td>
									<td><?php echo esc_html( SOM_Materials::reason_label( (string) $line->reason ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</details>
			<?php endif; ?>
			<?php if ( ! empty( $order->is_cancelled ) && 'reversed' !== $stock_status ) : ?>
				<p class="description">
					<?php echo esc_html__( 'Order is cancelled — stock reversal is not applied yet (waiting on confirmed live/sandbox cancel payloads).', 'order-machine' ); ?>
				</p>
			<?php endif; ?>
		<?php else : ?>
			<p class="som-muted">
				<?php
				if ( ! empty( $order->is_cancelled ) ) {
					echo esc_html__( 'No materials reserved (order was cancelled when imported).', 'order-machine' );
				} elseif ( ! empty( $order->has_unmatched ) ) {
					echo esc_html__( 'No materials reserved — unmatched line items have no product recipe.', 'order-machine' );
				} else {
					echo esc_html__( 'No materials reserved for this order.', 'order-machine' );
				}
				?>
			</p>
		<?php endif; ?>
	</section>

	<section class="som-panel som-panel-items">
		<h2><?php echo esc_html__( 'Line items', 'order-machine' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Product', 'order-machine' ); ?></th>
					<th><?php echo esc_html__( 'Qty', 'order-machine' ); ?></th>
					<th><?php echo esc_html__( 'Personalisation', 'order-machine' ); ?></th>
					<th><?php echo esc_html__( 'Unit price', 'order-machine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $order->items as $item ) : ?>
					<?php
					$matched = null !== $item->product_id && '' !== $item->product_id;
					$label   = $matched
						? (string) $item->product_name
						: __( 'Unmatched listing', 'order-machine' );
					?>
					<tr>
						<td>
							<?php echo esc_html( $label ); ?>
							<?php if ( $matched && ! empty( $item->product_sku ) ) : ?>
								<br /><code><?php echo esc_html( (string) $item->product_sku ); ?></code>
							<?php endif; ?>
							<?php if ( ! $matched ) : ?>
								<span class="som-badge som-badge-unmatched"><?php echo esc_html__( 'Unmatched', 'order-machine' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) (int) $item->quantity ); ?></td>
						<td>
							<?php
							$pt = isset( $item->personalisation_text ) ? trim( (string) $item->personalisation_text ) : '';
							if ( '' !== $pt ) {
								echo esc_html( $pt );
							} else {
								echo '<span class="som-muted">—</span>';
							}
							?>
						</td>
						<td>
							<?php
							if ( null !== $item->unit_price && '' !== $item->unit_price ) {
								echo esc_html( number_format_i18n( (float) $item->unit_price, 2 ) );
							} else {
								echo '<span class="som-muted">—</span>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>

	<?php
	$platform_fees = isset( $order->platform_fees ) && is_array( $order->platform_fees ) ? $order->platform_fees : array();
	?>
	<section class="som-panel som-panel-fees">
		<h2><?php echo esc_html__( 'Platform fees', 'order-machine' ); ?></h2>
		<?php if ( empty( $platform_fees ) ) : ?>
			<p class="description"><?php echo esc_html__( 'No synced platform fees for this order yet.', 'order-machine' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Type', 'order-machine' ); ?></th>
						<th><?php echo esc_html__( 'Amount', 'order-machine' ); ?></th>
						<th><?php echo esc_html__( 'Currency', 'order-machine' ); ?></th>
						<th><?php echo esc_html__( 'Synced', 'order-machine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $platform_fees as $fee ) : ?>
						<tr>
							<td><code><?php echo esc_html( (string) $fee->fee_type ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( (float) $fee->amount, 4 ) ); ?></td>
							<td><?php echo esc_html( (string) $fee->currency ); ?></td>
							<td><?php echo esc_html( (string) $fee->synced_at ); ?> UTC</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<?php
	$order_notes = SOM_Order_Notes::list_for_order( (int) $order->id );
	?>
	<section class="som-panel som-panel-notes" id="som-order-notes">
		<h2><?php echo esc_html__( 'Notes', 'order-machine' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Admin-only log for this order. Notes cannot be edited or deleted.', 'order-machine' ); ?>
		</p>
		<?php if ( empty( $order_notes ) ) : ?>
			<p class="description som-muted"><?php echo esc_html__( 'No notes yet.', 'order-machine' ); ?></p>
		<?php else : ?>
			<ul class="som-order-notes-list">
				<?php foreach ( $order_notes as $note ) : ?>
					<li class="som-order-note">
						<div class="som-order-note-meta">
							<strong><?php echo esc_html( (string) $note->author_name ); ?></strong>
							<span class="som-muted">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: UTC datetime */
										__( '%s UTC', 'order-machine' ),
										(string) $note->created_at
									)
								);
								?>
							</span>
						</div>
						<div class="som-order-note-body"><?php echo esc_html( (string) $note->body ); ?></div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-orders&order_id=' . (int) $order->id ) ); ?>" class="som-order-note-form">
			<?php wp_nonce_field( 'som_add_order_note', 'som_order_nonce' ); ?>
			<input type="hidden" name="som_add_order_note" value="1" />
			<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
			<label for="som_order_note_body" class="screen-reader-text"><?php echo esc_html__( 'New note', 'order-machine' ); ?></label>
			<textarea id="som_order_note_body" name="som_order_note_body" class="large-text" rows="3" maxlength="<?php echo esc_attr( (string) SOM_Order_Notes::MAX_LENGTH ); ?>" required placeholder="<?php echo esc_attr__( 'Add a note…', 'order-machine' ); ?>"></textarea>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: max characters */
						__( 'Plain text, up to %d characters.', 'order-machine' ),
						SOM_Order_Notes::MAX_LENGTH
					)
				);
				?>
			</p>
			<?php submit_button( __( 'Add note', 'order-machine' ), 'secondary', 'submit', false ); ?>
		</form>
	</section>

	<?php if ( '' !== $raw_pretty ) : ?>
		<details class="som-panel som-raw-payload">
			<summary><?php echo esc_html__( 'Raw payload (debug)', 'order-machine' ); ?></summary>
			<pre class="som-raw-json"><?php echo esc_html( $raw_pretty ); ?></pre>
		</details>
	<?php endif; ?>
</div>
