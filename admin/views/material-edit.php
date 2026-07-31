<?php
/**
 * Material create/edit admin view.
 *
 * @package OrderMachine
 *
 * @var object|null $material Material row or null when creating.
 * @var bool        $is_new   True when creating.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_new   = ! empty( $is_new );
$material = isset( $material ) ? $material : null;
$stock_log = ( $material && ! empty( $material->stock_log ) ) ? $material->stock_log : array();
?>
<div class="wrap som-catalog-wrap">
	<h1>
		<?php
		echo $is_new
			? esc_html__( 'Add material', 'order-machine' )
			: esc_html__( 'Edit material', 'order-machine' );
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( SOM_Materials::list_url() ); ?>">&larr; <?php echo esc_html__( 'Back to materials', 'order-machine' ); ?></a>
	</p>

	<?php if ( ! $is_new && $material ) : ?>
		<div class="som-stock-summary som-panel">
			<h2><?php echo esc_html__( 'Current stock', 'order-machine' ); ?></h2>
			<p class="som-stock-level">
				<?php echo esc_html( number_format_i18n( (float) $material->current_stock, 2 ) ); ?>
				<span class="som-muted"><?php echo esc_html( (string) $material->unit ); ?></span>
				<?php if ( ! empty( $material->is_low_stock ) ) : ?>
					<span class="som-badge som-badge-low-stock"><?php echo esc_html__( 'Low stock', 'order-machine' ); ?></span>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-materials' ) ); ?>" class="som-material-form">
		<?php wp_nonce_field( 'som_save_material', 'som_material_nonce' ); ?>
		<input type="hidden" name="som_save_material" value="1" />
		<?php if ( ! $is_new && $material ) : ?>
			<input type="hidden" name="material_id" value="<?php echo esc_attr( (string) (int) $material->id ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_material_name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="som_material_name" name="som_material_name" value="<?php echo esc_attr( $material ? (string) $material->name : '' ); ?>" required />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_material_unit"><?php echo esc_html__( 'Unit', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="som_material_unit" name="som_material_unit" value="<?php echo esc_attr( $material ? (string) $material->unit : '' ); ?>" required />
					<p class="description"><?php echo esc_html__( 'e.g. sheet, pack, roll', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_low_stock_threshold"><?php echo esc_html__( 'Low-stock threshold', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" step="0.01" min="0" id="som_low_stock_threshold" name="som_low_stock_threshold" value="<?php echo esc_attr( $material && null !== $material->low_stock_threshold ? (string) $material->low_stock_threshold : '' ); ?>" class="small-text" />
					<p class="description"><?php echo esc_html__( 'Optional — shows a warning when stock falls to this level or below.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_unit_cost"><?php echo esc_html__( 'Unit cost', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" step="0.0001" min="0" id="som_unit_cost" name="som_unit_cost" value="<?php echo esc_attr( $material && null !== $material->unit_cost ? (string) $material->unit_cost : '' ); ?>" class="small-text" />
					<p class="description"><?php echo esc_html__( 'Optional — for cost reporting later.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_preferred_supplier"><?php echo esc_html__( 'Preferred supplier', 'order-machine' ); ?></label></th>
				<td>
					<?php
					$suppliers           = SOM_Suppliers::list_all();
					$preferred_supplier  = $material && ! empty( $material->preferred_supplier_id ) ? (int) $material->preferred_supplier_id : 0;
					?>
					<select id="som_preferred_supplier" name="som_preferred_supplier">
						<option value=""><?php echo esc_html__( '— None —', 'order-machine' ); ?></option>
						<?php foreach ( $suppliers as $supplier ) : ?>
							<option value="<?php echo esc_attr( (string) (int) $supplier->id ); ?>" <?php selected( $preferred_supplier, (int) $supplier->id ); ?>>
								<?php echo esc_html( (string) $supplier->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php echo esc_html__( 'Optional shortcut for reordering — not a hard constraint on purchase orders.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="som_material_is_active" value="1" <?php checked( ! $material || (int) $material->is_active ); ?> />
						<?php echo esc_html__( 'Active', 'order-machine' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'Inactive materials are kept for history but hidden from new recipes.', 'order-machine' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save material', 'order-machine' ); ?></button>
		</p>
	</form>

	<?php if ( ! $is_new && $material ) : ?>
		<h2><?php echo esc_html__( 'Adjust stock', 'order-machine' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Enter a positive or negative delta (e.g. +10 or -2.5). Stock can go negative.', 'order-machine' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-materials' ) ); ?>" class="som-stock-adjust-form">
			<?php wp_nonce_field( 'som_adjust_stock', 'som_adjust_stock_nonce' ); ?>
			<input type="hidden" name="som_adjust_stock" value="1" />
			<input type="hidden" name="material_id" value="<?php echo esc_attr( (string) (int) $material->id ); ?>" />
			<label for="som_stock_delta" class="screen-reader-text"><?php echo esc_html__( 'Adjustment amount', 'order-machine' ); ?></label>
			<input type="number" step="0.01" id="som_stock_delta" name="som_stock_delta" value="" class="small-text" required />
			<button type="submit" class="button"><?php echo esc_html__( 'Apply adjustment', 'order-machine' ); ?></button>
		</form>

		<h2><?php echo esc_html__( 'Recent stock log', 'order-machine' ); ?></h2>
		<?php if ( empty( $stock_log ) ) : ?>
			<p class="som-muted"><?php echo esc_html__( 'No stock changes recorded yet.', 'order-machine' ); ?></p>
		<?php else : ?>
			<table class="widefat striped som-stock-log-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Date', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Change', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Reason', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Order', 'order-machine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stock_log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $entry->created_at ) ); ?></td>
							<td>
								<?php
								$delta = (float) $entry->change_qty;
								$sign  = $delta > 0 ? '+' : '';
								echo esc_html( $sign . number_format_i18n( $delta, 2 ) );
								?>
							</td>
							<td><?php echo esc_html( SOM_Materials::reason_label( (string) $entry->reason ) ); ?></td>
							<td>
								<?php if ( ! empty( $entry->order_id ) ) : ?>
									<a href="<?php echo esc_url( SOM_Orders::detail_url( (int) $entry->order_id ) ); ?>">
										<?php echo esc_html( ! empty( $entry->external_order_id ) ? (string) $entry->external_order_id : '#' . (int) $entry->order_id ); ?>
									</a>
								<?php else : ?>
									<span class="som-muted">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</div>
