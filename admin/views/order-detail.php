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
					echo esc_html__( 'No workflow assigned: no matched product on this order (primary product rule).', 'order-machine' );
				} else {
					echo esc_html__( 'No workflow assigned: the primary product has no workflow template. Assign one on the product edit screen.', 'order-machine' );
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
	$shipment      = SOM_Shipments::get_by_order( (int) $order->id );
	$ship_defaults = SOM_Shipments::defaults();
	$ship_form     = array(
		'carrier'            => $shipment ? (string) $shipment->carrier : $ship_defaults['carrier'],
		'service'            => $shipment ? (string) $shipment->service : $ship_defaults['service'],
		'shipped_at'         => $shipment ? SOM_Shipments::shipped_at_date_local( (string) $shipment->shipped_at ) : $ship_defaults['shipped_at'],
		'postage_paid'       => $shipment ? (string) $shipment->postage_paid : $ship_defaults['postage_paid'],
		'tracking_number'    => $shipment && $shipment->tracking_number ? (string) $shipment->tracking_number : '',
		'click_and_drop_ref' => $shipment && $shipment->click_and_drop_ref ? (string) $shipment->click_and_drop_ref : '',
	);
	$ship_status = SOM_Shipments::status_key( (int) $order->id );
	?>
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

	<section class="som-panel som-panel-workflow">
		<h2><?php echo esc_html__( 'Workflow', 'order-machine' ); ?></h2>
		<?php if ( ! empty( $order->is_cancelled ) ) : ?>
			<p class="description"><?php echo esc_html__( 'Cancelled — workflow actions are blocked.', 'order-machine' ); ?></p>
		<?php elseif ( ! empty( $order->is_complete ) ) : ?>
			<p><span class="som-badge som-badge-complete"><?php echo esc_html__( 'Workflow complete', 'order-machine' ); ?></span></p>
		<?php elseif ( empty( $order->workflow_progress ) ) : ?>
			<p class="som-muted"><?php echo esc_html__( 'No workflow progress for this order.', 'order-machine' ); ?></p>
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
							<p class="description"><?php echo esc_html__( 'Tick each line once it is in the package.', 'order-machine' ); ?></p>
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

			<ol class="som-workflow-progress">
				<?php foreach ( $order->workflow_progress as $row ) : ?>
					<?php
					$is_current = (int) $row->workflow_step_id === (int) $order->current_step_id;
					$status     = (string) $row->status;
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

	<section class="som-panel som-panel-stock">
		<h2><?php echo esc_html__( 'Material stock', 'order-machine' ); ?></h2>
		<?php
		$stock = isset( $order->stock_summary ) && is_array( $order->stock_summary )
			? $order->stock_summary
			: array(
				'status'                 => 'none',
				'lines'                  => array(),
				'has_new_order'          => false,
				'has_cancelled_reversal' => false,
			);
		$stock_status = isset( $stock['status'] ) ? (string) $stock['status'] : 'none';
		$stock_lines  = isset( $stock['lines'] ) && is_array( $stock['lines'] ) ? $stock['lines'] : array();
		?>
		<?php if ( 'reserved' === $stock_status ) : ?>
			<p>
				<span class="som-badge som-badge-stock-reserved"><?php echo esc_html__( 'Stock reserved', 'order-machine' ); ?></span>
			</p>
			<table class="widefat striped som-stock-table">
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
			<?php if ( ! empty( $order->is_cancelled ) ) : ?>
				<p class="description">
					<?php echo esc_html__( 'Order is cancelled — stock reversal is not applied yet (waiting on confirmed live/sandbox cancel payloads).', 'order-machine' ); ?>
				</p>
			<?php endif; ?>
		<?php elseif ( 'reversed' === $stock_status ) : ?>
			<p>
				<span class="som-badge som-badge-stock-reversed"><?php echo esc_html__( 'Stock reversed', 'order-machine' ); ?></span>
			</p>
			<table class="widefat striped som-stock-table">
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

	<?php if ( '' !== $raw_pretty ) : ?>
		<details class="som-panel som-raw-payload">
			<summary><?php echo esc_html__( 'Raw payload (debug)', 'order-machine' ); ?></summary>
			<pre class="som-raw-json"><?php echo esc_html( $raw_pretty ); ?></pre>
		</details>
	<?php endif; ?>
</div>
