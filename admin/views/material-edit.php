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

$is_new           = ! empty( $is_new );
$material         = isset( $material ) ? $material : null;
$stock_log        = ( $material && ! empty( $material->stock_log ) ) ? $material->stock_log : array();
$purchase_history = ( $material && ! empty( $material->purchase_history ) ) ? $material->purchase_history : array();
$goal_alerts      = ( $material && ! empty( $material->goal_alerts ) ) ? $material->goal_alerts : array();
$wa               = $material ? (float) $material->weighted_average : 0.0;
$value_on_hand    = $material ? (float) $material->total_value_on_hand : 0.0;
$lead_days        = $material && null !== $material->average_lead_time_days ? (float) $material->average_lead_time_days : null;
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
			<h2><?php echo esc_html__( 'Stock &amp; costing', 'order-machine' ); ?></h2>
			<p class="som-stock-level">
				<?php echo esc_html( number_format_i18n( (float) $material->current_stock, 2 ) ); ?>
				<span class="som-muted"><?php echo esc_html( (string) $material->unit ); ?></span>
				<?php if ( ! empty( $material->is_low_stock ) ) : ?>
					<span class="som-badge som-badge-low-stock"><?php echo esc_html__( 'Low stock', 'order-machine' ); ?></span>
				<?php endif; ?>
			</p>
			<ul class="som-costing-summary">
				<li>
					<strong><?php echo esc_html__( 'Weighted average', 'order-machine' ); ?>:</strong>
					£<?php echo esc_html( number_format_i18n( $wa, 4 ) ); ?>
					<span class="som-muted"><?php echo esc_html__( 'per unit', 'order-machine' ); ?></span>
				</li>
				<li>
					<strong><?php echo esc_html__( 'Total value on hand', 'order-machine' ); ?>:</strong>
					£<?php echo esc_html( number_format_i18n( $value_on_hand, 2 ) ); ?>
				</li>
				<li>
					<strong><?php echo esc_html__( 'Average lead time', 'order-machine' ); ?>:</strong>
					<?php
					if ( null === $lead_days ) {
						echo '<span class="som-muted">' . esc_html__( 'No receive history yet', 'order-machine' ) . '</span>';
					} else {
						printf(
							/* translators: %s: number of days */
							esc_html__( '%s days', 'order-machine' ),
							esc_html( number_format_i18n( $lead_days, 1 ) )
						);
					}
					?>
				</li>
			</ul>
		</div>

		<?php if ( ! empty( $goal_alerts ) ) : ?>
			<div class="som-panel som-goal-alerts-panel">
				<h2><?php echo esc_html__( 'Goal-cost alerts', 'order-machine' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Workflows where this material’s weighted average is approaching or over the cost goal.', 'order-machine' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Workflow', 'order-machine' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Goal unit cost', 'order-machine' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Alert', 'order-machine' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $goal_alerts as $alert ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $alert['workflow_name'] ); ?></td>
								<td>£<?php echo esc_html( number_format_i18n( (float) $alert['goal_unit_cost'], 4 ) ); ?></td>
								<td>
									<span class="som-badge som-badge-goal-<?php echo esc_attr( sanitize_html_class( (string) $alert['level'] ) ); ?>">
										<?php echo esc_html( SOM_Material_Costing::alert_label( (string) $alert['level'] ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
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
				<th scope="row"><label for="som_unit_cost"><?php echo esc_html__( 'Unit cost override', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" step="0.0001" min="0" id="som_unit_cost" name="som_unit_cost" value="<?php echo esc_attr( $material && null !== $material->unit_cost ? (string) $material->unit_cost : '' ); ?>" class="small-text" />
					<p class="description">
						<?php echo esc_html__( 'Manual override. Saving a new value revalues total value on hand = current stock × unit cost and writes a correcting stock-log row. Purchases update this from the weighted average automatically.', 'order-machine' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_preferred_supplier"><?php echo esc_html__( 'Preferred supplier', 'order-machine' ); ?></label></th>
				<td>
					<?php
					$suppliers          = SOM_Suppliers::list_all();
					$preferred_supplier = $material && ! empty( $material->preferred_supplier_id ) ? (int) $material->preferred_supplier_id : 0;
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
		<h2><?php echo esc_html__( 'Purchase history', 'order-machine' ); ?></h2>
		<?php if ( empty( $purchase_history ) ) : ?>
			<p class="som-muted"><?php echo esc_html__( 'No received purchase lines yet.', 'order-machine' ); ?></p>
		<?php else : ?>
			<table class="widefat striped som-purchase-history-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Received', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'PO', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Supplier', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Qty received', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Landed unit cost', 'order-machine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $purchase_history as $row ) : ?>
						<tr>
							<td>
								<?php
								echo $row->received_date
									? esc_html( (string) $row->received_date )
									: '<span class="som-muted">—</span>';
								?>
							</td>
							<td>
								<a href="<?php echo esc_url( SOM_Purchase_Orders::detail_url( (int) $row->purchase_order_id ) ); ?>">
									#<?php echo esc_html( (string) (int) $row->purchase_order_id ); ?>
								</a>
							</td>
							<td><?php echo esc_html( (string) $row->supplier_name ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (float) $row->quantity_received, 2 ) ); ?></td>
							<td>
								<?php
								echo null !== $row->landed_unit_cost && '' !== $row->landed_unit_cost
									? '£' . esc_html( number_format_i18n( (float) $row->landed_unit_cost, 4 ) )
									: '<span class="som-muted">—</span>';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php echo esc_html__( 'Adjust stock', 'order-machine' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Enter a positive or negative delta (e.g. +10 or -2.5). Stock can go negative. This does not debit a material budget — use R&D write-off below for linked stock + budget debit.', 'order-machine' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-materials' ) ); ?>" class="som-stock-adjust-form">
			<?php wp_nonce_field( 'som_adjust_stock', 'som_adjust_stock_nonce' ); ?>
			<input type="hidden" name="som_adjust_stock" value="1" />
			<input type="hidden" name="material_id" value="<?php echo esc_attr( (string) (int) $material->id ); ?>" />
			<label for="som_stock_delta" class="screen-reader-text"><?php echo esc_html__( 'Adjustment amount', 'order-machine' ); ?></label>
			<input type="number" step="0.01" id="som_stock_delta" name="som_stock_delta" value="" class="small-text" required />
			<button type="submit" class="button"><?php echo esc_html__( 'Apply adjustment', 'order-machine' ); ?></button>
		</form>

		<h2><?php echo esc_html__( 'R&amp;D / non-sale write-off', 'order-machine' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Decrements stock and, if an active material budget exists, debits it by qty × weighted-average unit cost. Notes are required.', 'order-machine' ); ?>
		</p>
		<?php
		$linked_budget = SOM_Budgets::get_for_material( (int) $material->id, true );
		if ( $linked_budget ) :
			?>
			<p class="description">
				<?php echo esc_html__( 'Linked budget:', 'order-machine' ); ?>
				<a href="<?php echo esc_url( SOM_Budgets::detail_url( (int) $linked_budget->id ) ); ?>">
					<?php echo esc_html( (string) $linked_budget->name ); ?>
				</a>
			</p>
		<?php else : ?>
			<p class="description som-muted"><?php echo esc_html__( 'No active material budget — stock will still be reduced.', 'order-machine' ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-materials' ) ); ?>" class="som-budget-writeoff-form">
			<?php wp_nonce_field( 'som_material_writeoff', 'som_material_writeoff_nonce' ); ?>
			<input type="hidden" name="som_material_writeoff" value="1" />
			<input type="hidden" name="material_id" value="<?php echo esc_attr( (string) (int) $material->id ); ?>" />
			<label for="som_writeoff_qty" class="screen-reader-text"><?php echo esc_html__( 'Quantity', 'order-machine' ); ?></label>
			<input type="number" step="0.01" min="0.01" id="som_writeoff_qty" name="som_writeoff_qty" class="small-text" required />
			<span class="som-muted"><?php echo esc_html( (string) $material->unit ); ?></span>
			<label for="som_writeoff_notes" class="screen-reader-text"><?php echo esc_html__( 'Notes', 'order-machine' ); ?></label>
			<input type="text" id="som_writeoff_notes" name="som_writeoff_notes" class="regular-text" placeholder="<?php echo esc_attr__( 'e.g. R&D (required)', 'order-machine' ); ?>" required />
			<button type="submit" class="button"><?php echo esc_html__( 'Write off', 'order-machine' ); ?></button>
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
