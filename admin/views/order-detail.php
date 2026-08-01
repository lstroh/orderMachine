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

	<section class="som-panel som-panel-workflow">
		<h2><?php echo esc_html__( 'Workflow', 'order-machine' ); ?></h2>
		<?php if ( ! empty( $order->is_cancelled ) ) : ?>
			<p class="description"><?php echo esc_html__( 'Cancelled — workflow actions are blocked.', 'order-machine' ); ?></p>
		<?php elseif ( ! empty( $order->is_complete ) ) : ?>
			<p><span class="som-badge som-badge-complete"><?php echo esc_html__( 'Workflow complete', 'order-machine' ); ?></span></p>
		<?php elseif ( empty( $order->workflow_progress ) ) : ?>
			<p class="som-muted"><?php echo esc_html__( 'No workflow progress for this order.', 'order-machine' ); ?></p>
		<?php else : ?>
			<ol class="som-workflow-progress">
				<?php foreach ( $order->workflow_progress as $row ) : ?>
					<?php
					$is_current = (int) $row->workflow_step_id === (int) $order->current_step_id;
					$status     = (string) $row->status;
					$step_obj   = (object) array(
						'timer_seconds'           => $row->timer_seconds,
						'requires_manual_confirm' => $row->requires_manual_confirm,
						'script_config'           => $row->script_config,
					);
					$can_done   = $is_current && empty( $order->is_cancelled ) && SOM_Workflow_Engine::can_mark_done( $row, $step_obj );
					$can_retry  = $is_current && empty( $order->is_cancelled ) && SOM_Workflow_Engine::can_retry_script( $row, $step_obj );
					$timer_ends = ! empty( $row->timer_ends_at ) ? (string) $row->timer_ends_at : '';
					$ends_ts    = $timer_ends ? strtotime( $timer_ends . ' UTC' ) : 0;
					if ( ! $ends_ts && $timer_ends ) {
						$ends_ts = strtotime( $timer_ends );
					}
					$last_error = isset( $row->last_error ) ? (string) $row->last_error : '';
					$waiting_cb = ( 0 === strpos( $last_error, 'waiting_callback:' ) );
					$display_err = ( $last_error && ! $waiting_cb ) ? $last_error : '';
					?>
					<li class="som-workflow-step<?php echo $is_current ? ' is-current' : ''; ?> status-<?php echo esc_attr( $status ); ?>">
						<div class="som-workflow-step-main">
							<strong><?php echo esc_html( (string) $row->step_name ); ?></strong>
							<span class="som-badge som-badge-step-<?php echo esc_attr( $status ); ?>">
								<?php
								$labels = array(
									'pending'        => __( 'Pending', 'order-machine' ),
									'in_progress'    => __( 'In progress', 'order-machine' ),
									'waiting_timer'  => __( 'Waiting (timer)', 'order-machine' ),
									'waiting_script' => __( 'Waiting (script)', 'order-machine' ),
									'waiting_batch'  => __( 'Waiting (batch)', 'order-machine' ),
									'error'          => __( 'Error', 'order-machine' ),
									'done'           => __( 'Done', 'order-machine' ),
								);
								echo esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status );
								?>
							</span>
						</div>
						<?php if ( $is_current && 'waiting_timer' === $status && $ends_ts ) : ?>
							<p class="som-timer-countdown description"
								data-som-countdown
								data-ends-at="<?php echo esc_attr( (string) $ends_ts ); ?>">
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

	<?php if ( '' !== $raw_pretty ) : ?>
		<details class="som-panel som-raw-payload">
			<summary><?php echo esc_html__( 'Raw payload (debug)', 'order-machine' ); ?></summary>
			<pre class="som-raw-json"><?php echo esc_html( $raw_pretty ); ?></pre>
		</details>
	<?php endif; ?>
</div>
