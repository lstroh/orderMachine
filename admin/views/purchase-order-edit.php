<?php
/**
 * Purchase order create/edit admin view.
 *
 * @package OrderMachine
 *
 * @var object|null $order  PO row or null when creating.
 * @var bool        $is_new True when creating.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_new           = ! empty( $is_new );
$order            = isset( $order ) ? $order : null;
$suppliers        = SOM_Suppliers::list_all();
$material_options = SOM_Materials::list_active();
$items            = ( $order && ! empty( $order->items ) ) ? $order->items : array();
$can_edit_lines   = $is_new || ( $order && ! empty( $order->can_edit_lines ) );
$lines_locked     = $order && ! empty( $order->lines_locked );
$blank_rows       = $is_new || empty( $items ) ? 1 : 0;

$default_date = current_time( 'Y-m-d' );
?>
<div class="wrap som-catalog-wrap">
	<h1>
		<?php
		if ( $is_new ) {
			echo esc_html__( 'Add purchase order', 'order-machine' );
		} else {
			printf(
				/* translators: %d: purchase order ID */
				esc_html__( 'Purchase order #%d', 'order-machine' ),
				(int) $order->id
			);
		}
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( SOM_Purchase_Orders::list_url() ); ?>">&larr; <?php echo esc_html__( 'Back to purchase orders', 'order-machine' ); ?></a>
	</p>

	<?php if ( ! $is_new && $order ) : ?>
		<div class="som-panel">
			<p>
				<span class="som-badge som-badge-po-<?php echo esc_attr( sanitize_html_class( (string) $order->status ) ); ?>">
					<?php echo esc_html( SOM_Purchase_Orders::status_label( (string) $order->status ) ); ?>
				</span>
				<?php if ( $lines_locked ) : ?>
					<span class="description">
						<?php echo esc_html__( 'Line items and costs are locked after the first receipt.', 'order-machine' ); ?>
					</span>
				<?php endif; ?>
			</p>
			<p class="som-po-actions">
				<?php if ( ! empty( $order->can_receive ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( SOM_Purchase_Orders::receive_url( (int) $order->id ) ); ?>">
						<?php echo esc_html__( 'Receive stock', 'order-machine' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( ! empty( $order->can_mark_received ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-purchase-orders' ) ); ?>" class="som-inline-form">
						<?php wp_nonce_field( 'som_po_mark_received', 'som_po_mark_received_nonce' ); ?>
						<input type="hidden" name="som_po_mark_received" value="1" />
						<input type="hidden" name="po_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
						<button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Accept shortfall and mark this PO as fully received?', 'order-machine' ) ); ?>');">
							<?php echo esc_html__( 'Mark received (accept shortfall)', 'order-machine' ); ?>
						</button>
					</form>
				<?php endif; ?>
				<?php if ( ! empty( $order->can_cancel ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-purchase-orders' ) ); ?>" class="som-inline-form">
						<?php wp_nonce_field( 'som_po_cancel', 'som_po_cancel_nonce' ); ?>
						<input type="hidden" name="som_po_cancel" value="1" />
						<input type="hidden" name="po_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
						<button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Cancel this purchase order? Already-received stock is kept.', 'order-machine' ) ); ?>');">
							<?php echo esc_html__( 'Cancel PO', 'order-machine' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $suppliers ) ) : ?>
		<div class="notice notice-warning"><p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: suppliers admin link */
					__( 'Add a %s before creating a purchase order.', 'order-machine' ),
					'<a href="' . esc_url( SOM_Suppliers::detail_url( 'new' ) ) . '">' . esc_html__( 'supplier', 'order-machine' ) . '</a>'
				)
			);
			?>
		</p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-purchase-orders' ) ); ?>" class="som-po-form">
		<?php wp_nonce_field( 'som_save_po', 'som_po_nonce' ); ?>
		<input type="hidden" name="som_save_po" value="1" />
		<?php if ( ! $is_new && $order ) : ?>
			<input type="hidden" name="po_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_po_supplier"><?php echo esc_html__( 'Supplier', 'order-machine' ); ?></label></th>
				<td>
					<select id="som_po_supplier" name="som_po_supplier" <?php disabled( ! $can_edit_lines ); ?> <?php echo $can_edit_lines ? 'required' : ''; ?>>
						<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
						<?php foreach ( $suppliers as $supplier ) : ?>
							<option value="<?php echo esc_attr( (string) (int) $supplier->id ); ?>" <?php selected( $order ? (int) $order->supplier_id : 0, (int) $supplier->id ); ?>>
								<?php echo esc_html( (string) $supplier->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_po_order_date"><?php echo esc_html__( 'Order date', 'order-machine' ); ?></label></th>
				<td>
					<input type="date" id="som_po_order_date" name="som_po_order_date" value="<?php echo esc_attr( $order ? (string) $order->order_date : $default_date ); ?>" <?php disabled( ! $can_edit_lines ); ?> <?php echo $can_edit_lines ? 'required' : ''; ?> />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_po_shipping"><?php echo esc_html__( 'Shipping cost (£)', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" step="0.01" min="0" id="som_po_shipping" name="som_po_shipping" class="small-text" value="<?php echo esc_attr( $order ? (string) $order->shipping_cost : '0' ); ?>" <?php disabled( ! $can_edit_lines ); ?> />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_po_other"><?php echo esc_html__( 'Other cost (£)', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" step="0.01" min="0" id="som_po_other" name="som_po_other" class="small-text" value="<?php echo esc_attr( $order && null !== $order->other_cost ? (string) $order->other_cost : '0' ); ?>" <?php disabled( ! $can_edit_lines ); ?> />
					<p class="description"><?php echo esc_html__( 'Tax, handling, etc. Allocated with shipping when landed cost is calculated.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_po_notes"><?php echo esc_html__( 'Notes', 'order-machine' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="3" id="som_po_notes" name="som_po_notes" <?php disabled( $order && in_array( $order->status, array( 'received', 'cancelled' ), true ) ); ?>><?php echo esc_textarea( $order && $order->notes ? (string) $order->notes : '' ); ?></textarea>
				</td>
			</tr>
			<?php if ( ! $is_new && $order && $order->received_date ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Received date', 'order-machine' ); ?></th>
					<td><?php echo esc_html( (string) $order->received_date ); ?></td>
				</tr>
			<?php endif; ?>
		</table>

		<h2><?php echo esc_html__( 'Line items', 'order-machine' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Item cost is the line total (not unit price), in GBP.', 'order-machine' ); ?></p>

		<table class="widefat striped som-recipe-table" id="som-po-lines-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Material', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Qty ordered', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Qty received', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Item cost (£)', 'order-machine' ); ?></th>
					<?php if ( $can_edit_lines ) : ?>
						<th scope="col" class="som-recipe-actions-col"></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody id="som-po-line-rows">
				<?php
				$index = 0;
				foreach ( $items as $item ) :
					++$index;
					?>
					<tr class="som-po-line-row">
						<td>
							<?php if ( $can_edit_lines ) : ?>
								<select name="som_po_material[<?php echo esc_attr( (string) $index ); ?>]">
									<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
									<?php foreach ( $material_options as $material ) : ?>
										<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>" <?php selected( (int) $item->material_id, (int) $material->id ); ?>>
											<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<?php echo esc_html( $item->material_name ? (string) $item->material_name : '#' . (int) $item->material_id ); ?>
								<?php if ( ! empty( $item->material_unit ) ) : ?>
									<span class="som-muted">(<?php echo esc_html( (string) $item->material_unit ); ?>)</span>
								<?php endif; ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $can_edit_lines ) : ?>
								<input type="number" step="0.01" min="0.01" name="som_po_qty[<?php echo esc_attr( (string) $index ); ?>]" value="<?php echo esc_attr( (string) $item->quantity_ordered ); ?>" class="small-text" />
							<?php else : ?>
								<?php echo esc_html( number_format_i18n( (float) $item->quantity_ordered, 2 ) ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php
							echo null === $item->quantity_received || '' === $item->quantity_received
								? esc_html__( '—', 'order-machine' )
								: esc_html( number_format_i18n( (float) $item->quantity_received, 2 ) );
							?>
						</td>
						<td>
							<?php if ( $can_edit_lines ) : ?>
								<input type="number" step="0.01" min="0" name="som_po_item_cost[<?php echo esc_attr( (string) $index ); ?>]" value="<?php echo esc_attr( (string) $item->item_cost ); ?>" class="small-text" />
							<?php else : ?>
								£<?php echo esc_html( number_format_i18n( (float) $item->item_cost, 2 ) ); ?>
							<?php endif; ?>
						</td>
						<?php if ( $can_edit_lines ) : ?>
							<td class="som-recipe-actions-col">
								<button type="button" class="button-link som-po-line-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>

				<?php if ( $can_edit_lines ) : ?>
					<?php for ( $i = 0; $i < $blank_rows; $i++ ) : ?>
						<?php ++$index; ?>
						<tr class="som-po-line-row">
							<td>
								<select name="som_po_material[<?php echo esc_attr( (string) $index ); ?>]">
									<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
									<?php foreach ( $material_options as $material ) : ?>
										<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>">
											<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<input type="number" step="0.01" min="0.01" name="som_po_qty[<?php echo esc_attr( (string) $index ); ?>]" value="" class="small-text" />
							</td>
							<td><span class="som-muted">—</span></td>
							<td>
								<input type="number" step="0.01" min="0" name="som_po_item_cost[<?php echo esc_attr( (string) $index ); ?>]" value="" class="small-text" />
							</td>
							<td class="som-recipe-actions-col">
								<button type="button" class="button-link som-po-line-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
							</td>
						</tr>
					<?php endfor; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $can_edit_lines ) : ?>
			<p>
				<button type="button" class="button" id="som-po-add-line"><?php echo esc_html__( 'Add line', 'order-machine' ); ?></button>
			</p>
		<?php endif; ?>

		<?php if ( ! $order || ! in_array( $order->status, array( 'received', 'cancelled' ), true ) ) : ?>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save purchase order', 'order-machine' ); ?></button>
			</p>
		<?php endif; ?>
	</form>
</div>

<?php if ( $can_edit_lines ) : ?>
<template id="som-po-line-template">
	<tr class="som-po-line-row">
		<td>
			<select name="som_po_material[__INDEX__]">
				<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
				<?php foreach ( $material_options as $material ) : ?>
					<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>">
						<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<input type="number" step="0.01" min="0.01" name="som_po_qty[__INDEX__]" value="" class="small-text" />
		</td>
		<td><span class="som-muted">—</span></td>
		<td>
			<input type="number" step="0.01" min="0" name="som_po_item_cost[__INDEX__]" value="" class="small-text" />
		</td>
		<td class="som-recipe-actions-col">
			<button type="button" class="button-link som-po-line-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
		</td>
	</tr>
</template>
<?php endif; ?>
