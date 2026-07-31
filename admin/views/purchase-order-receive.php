<?php
/**
 * Purchase order receive admin view.
 *
 * @package OrderMachine
 *
 * @var object $order PO with items.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$order = isset( $order ) ? $order : null;
if ( ! $order ) {
	return;
}
?>
<div class="wrap som-catalog-wrap">
	<h1>
		<?php
		printf(
			/* translators: %d: purchase order ID */
			esc_html__( 'Receive purchase order #%d', 'order-machine' ),
			(int) $order->id
		);
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( SOM_Purchase_Orders::detail_url( (int) $order->id ) ); ?>">&larr; <?php echo esc_html__( 'Back to purchase order', 'order-machine' ); ?></a>
	</p>

	<div class="som-panel">
		<p>
			<strong><?php echo esc_html( $order->supplier_name ? (string) $order->supplier_name : '' ); ?></strong>
			·
			<span class="som-badge som-badge-po-<?php echo esc_attr( sanitize_html_class( (string) $order->status ) ); ?>">
				<?php echo esc_html( SOM_Purchase_Orders::status_label( (string) $order->status ) ); ?>
			</span>
		</p>
		<p class="description">
			<?php echo esc_html__( 'Enter the additional quantity arriving in this shipment. Leave blank or 0 to skip a line. Over-receiving is allowed.', 'order-machine' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-purchase-orders' ) ); ?>">
		<?php wp_nonce_field( 'som_receive_po', 'som_receive_po_nonce' ); ?>
		<input type="hidden" name="som_receive_po" value="1" />
		<input type="hidden" name="po_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />

		<table class="widefat striped som-catalog-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Material', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Ordered', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Already received', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Receive now', 'order-machine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $order->items as $item ) : ?>
					<?php
					$already = null === $item->quantity_received || '' === $item->quantity_received
						? 0.0
						: (float) $item->quantity_received;
					$remaining = max( 0, (float) $item->quantity_ordered - $already );
					?>
					<tr>
						<td>
							<?php echo esc_html( $item->material_name ? (string) $item->material_name : '#' . (int) $item->material_id ); ?>
							<?php if ( ! empty( $item->material_unit ) ) : ?>
								<span class="som-muted">(<?php echo esc_html( (string) $item->material_unit ); ?>)</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (float) $item->quantity_ordered, 2 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $already, 2 ) ); ?></td>
						<td>
							<input
								type="number"
								step="0.01"
								min="0"
								name="som_receive_qty[<?php echo esc_attr( (string) (int) $item->id ); ?>]"
								value="<?php echo esc_attr( $remaining > 0 ? (string) $remaining : '' ); ?>"
								class="small-text"
							/>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Receive stock', 'order-machine' ); ?></button>
		</p>
	</form>
</div>
