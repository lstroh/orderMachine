<?php
/**
 * Budget create/edit admin view (detail, ledger, adjustments, R&D write-off).
 *
 * @package OrderMachine
 *
 * @var object|null $budget Budget row or null when creating.
 * @var bool        $is_new True when creating.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_new = ! empty( $is_new );
$budget = isset( $budget ) ? $budget : null;

$product_links  = ( ! $is_new && $budget ) ? SOM_Budgets::get_product_link_ids( (int) $budget->id ) : array();
$workflow_links = ( ! $is_new && $budget ) ? SOM_Budgets::get_workflow_link_ids( (int) $budget->id ) : array();
$ledger         = ( ! $is_new && $budget ) ? SOM_Budgets::get_ledger( (int) $budget->id, 50 ) : array();

$material_options = SOM_Budgets::materials_available_for_budget(
	( ! $is_new && $budget && 'material' === $budget->type ) ? (int) $budget->material_id : 0
);

$products_result = SOM_Products::query(
	array(
		'status'   => 'active',
		'per_page' => 500,
		'paged'    => 1,
	)
);
$product_options = $products_result['products'];
foreach ( $product_links as $pid ) {
	$found = false;
	foreach ( $product_options as $product ) {
		if ( (int) $product->id === (int) $pid ) {
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		$extra = SOM_Products::get( (int) $pid );
		if ( $extra ) {
			$product_options[] = $extra;
		}
	}
}

$workflow_options = SOM_Workflows::list_for_dropdown();
foreach ( $workflow_links as $wid ) {
	$found = false;
	foreach ( $workflow_options as $tpl ) {
		if ( (int) $tpl->id === (int) $wid ) {
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		$extra = SOM_Workflows::get( (int) $wid );
		if ( $extra ) {
			$workflow_options[] = (object) array(
				'id'        => (int) $extra->id,
				'name'      => (string) $extra->name,
				'is_active' => (int) $extra->is_active,
			);
		}
	}
}

$current_type = $is_new ? 'material' : (string) $budget->type;
$overspent    = ( ! $is_new && $budget ) ? SOM_Budgets::is_overspent( $budget ) : false;
$low_balance  = ( ! $is_new && $budget ) ? SOM_Budgets::is_low_balance( $budget ) : false;
$material_row = ( ! $is_new && $budget && 'material' === $budget->type && ! empty( $budget->material_id ) )
	? SOM_Materials::get( (int) $budget->material_id )
	: null;
?>
<div class="wrap som-catalog-wrap">
	<h1>
		<?php
		echo $is_new
			? esc_html__( 'Add budget', 'order-machine' )
			: esc_html__( 'Edit budget', 'order-machine' );
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( SOM_Budgets::list_url() ); ?>">&larr; <?php echo esc_html__( 'Back to budgets', 'order-machine' ); ?></a>
	</p>

	<?php if ( ! $is_new && $budget ) : ?>
		<div class="som-stock-summary som-panel">
			<h2><?php echo esc_html__( 'Balance', 'order-machine' ); ?></h2>
			<p class="som-stock-level">
				£<?php echo esc_html( number_format_i18n( (float) $budget->current_balance, 2 ) ); ?>
				<?php if ( $overspent ) : ?>
					<span class="som-badge som-badge-overspent"><?php echo esc_html__( 'Overspent', 'order-machine' ); ?></span>
				<?php elseif ( $low_balance ) : ?>
					<span class="som-badge som-badge-low-balance"><?php echo esc_html__( 'Low balance', 'order-machine' ); ?></span>
				<?php endif; ?>
			</p>
			<ul class="som-costing-summary">
				<li>
					<strong><?php echo esc_html__( 'Type', 'order-machine' ); ?>:</strong>
					<?php echo esc_html( SOM_Budgets::type_label( (string) $budget->type ) ); ?>
				</li>
				<li>
					<strong><?php echo esc_html__( 'Funding', 'order-machine' ); ?>:</strong>
					<?php echo esc_html( SOM_Budgets::funding_method_label( (string) $budget->funding_method ) ); ?>
					<?php if ( 'manual' === $budget->type && null !== $budget->funding_value && '' !== $budget->funding_value ) : ?>
						(<?php echo esc_html( number_format_i18n( (float) $budget->funding_value, 2 ) ); ?><?php echo in_array( $budget->funding_method, array( 'percent_of_price', 'percent_of_profit' ), true ) ? '%' : ''; ?>)
					<?php endif; ?>
				</li>
				<?php if ( $material_row ) : ?>
					<li>
						<strong><?php echo esc_html__( 'Material', 'order-machine' ); ?>:</strong>
						<a href="<?php echo esc_url( SOM_Materials::detail_url( (int) $material_row->id ) ); ?>">
							<?php echo esc_html( (string) $material_row->name ); ?>
						</a>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-budgets' ) ); ?>" class="som-budget-form" id="som-budget-form">
		<?php wp_nonce_field( 'som_save_budget', 'som_budget_nonce' ); ?>
		<input type="hidden" name="som_save_budget" value="1" />
		<?php if ( ! $is_new && $budget ) : ?>
			<input type="hidden" name="budget_id" value="<?php echo esc_attr( (string) (int) $budget->id ); ?>" />
			<input type="hidden" name="som_budget_type" value="<?php echo esc_attr( (string) $budget->type ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_budget_name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="som_budget_name" name="som_budget_name" value="<?php echo esc_attr( $budget ? (string) $budget->name : '' ); ?>" required />
				</td>
			</tr>

			<?php if ( $is_new ) : ?>
				<tr>
					<th scope="row"><label for="som_budget_type"><?php echo esc_html__( 'Type', 'order-machine' ); ?></label></th>
					<td>
						<select id="som_budget_type" name="som_budget_type">
							<option value="material" <?php selected( $current_type, 'material' ); ?>><?php echo esc_html__( 'Material', 'order-machine' ); ?></option>
							<option value="manual" <?php selected( $current_type, 'manual' ); ?>><?php echo esc_html__( 'Manual', 'order-machine' ); ?></option>
						</select>
						<p class="description">
							<?php echo esc_html__( 'Ink tip: track ink as a material on product recipes for automatic material-cost funding, or use a manual fixed-amount budget per unit sold.', 'order-machine' ); ?>
						</p>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Type', 'order-machine' ); ?></th>
					<td><?php echo esc_html( SOM_Budgets::type_label( (string) $budget->type ) ); ?></td>
				</tr>
			<?php endif; ?>

			<tr class="som-budget-field-material" <?php echo 'manual' === $current_type ? 'hidden' : ''; ?>>
				<th scope="row"><label for="som_budget_material_id"><?php echo esc_html__( 'Material', 'order-machine' ); ?></label></th>
				<td>
					<?php if ( $is_new ) : ?>
						<select id="som_budget_material_id" name="som_budget_material_id">
							<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
							<?php foreach ( $material_options as $material ) : ?>
								<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>">
									<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( empty( $material_options ) ) : ?>
							<p class="description"><?php echo esc_html__( 'No materials available — every active material already has a budget, or add a material first.', 'order-machine' ); ?></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'One material budget per material. Materials that already have a budget are hidden.', 'order-machine' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<?php echo $material_row ? esc_html( (string) $material_row->name ) : '<span class="som-muted">—</span>'; ?>
					<?php endif; ?>
				</td>
			</tr>

			<tr class="som-budget-field-manual" <?php echo 'material' === $current_type ? 'hidden' : ''; ?>>
				<th scope="row"><label for="som_budget_funding_method"><?php echo esc_html__( 'Funding method', 'order-machine' ); ?></label></th>
				<td>
					<select id="som_budget_funding_method" name="som_budget_funding_method">
						<?php
						$methods = array(
							'percent_of_price'  => __( '% of sale price', 'order-machine' ),
							'percent_of_profit' => __( '% of profit', 'order-machine' ),
							'fixed_amount'      => __( 'Fixed amount per unit', 'order-machine' ),
						);
						$selected_method = $budget && 'manual' === $budget->type ? (string) $budget->funding_method : 'percent_of_price';
						foreach ( $methods as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_method, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<tr class="som-budget-field-manual" <?php echo 'material' === $current_type ? 'hidden' : ''; ?>>
				<th scope="row"><label for="som_budget_funding_value"><?php echo esc_html__( 'Funding value', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" step="0.0001" min="0" id="som_budget_funding_value" name="som_budget_funding_value" class="small-text" value="<?php echo esc_attr( $budget && 'manual' === $budget->type && null !== $budget->funding_value ? (string) $budget->funding_value : '' ); ?>" />
					<p class="description"><?php echo esc_html__( 'Percentage (0–100) or fixed £ amount per unit sold, depending on method.', 'order-machine' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="som_budget_target_reserve"><?php echo esc_html__( 'Target reserve', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" step="0.01" min="0" id="som_budget_target_reserve" name="som_budget_target_reserve" class="small-text" value="<?php echo esc_attr( $budget && null !== $budget->target_reserve_amount ? (string) $budget->target_reserve_amount : '' ); ?>" />
					<p class="description"><?php echo esc_html__( 'Optional — shows a low-balance warning when the balance falls below this amount.', 'order-machine' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="som_budget_notes"><?php echo esc_html__( 'Notes', 'order-machine' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="3" id="som_budget_notes" name="som_budget_notes"><?php echo esc_textarea( $budget && null !== $budget->notes ? (string) $budget->notes : '' ); ?></textarea>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="som_budget_is_active" value="1" <?php checked( ! $budget || (int) $budget->is_active ); ?> />
						<?php echo esc_html__( 'Active', 'order-machine' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'Inactive budgets are kept for history but skipped for funding and draw-down.', 'order-machine' ); ?></p>
				</td>
			</tr>

			<tr class="som-budget-field-material" <?php echo 'manual' === $current_type ? 'hidden' : ''; ?>>
				<th scope="row"><?php echo esc_html__( 'Workflow scope', 'order-machine' ); ?></th>
				<td>
					<p class="description"><?php echo esc_html__( 'Leave all unchecked for global (any workflow). Check templates to fund only orders assigned to those workflows.', 'order-machine' ); ?></p>
					<?php if ( empty( $workflow_options ) ) : ?>
						<p class="som-muted"><?php echo esc_html__( 'No workflow templates yet.', 'order-machine' ); ?></p>
					<?php else : ?>
						<ul class="som-checkbox-list">
							<?php foreach ( $workflow_options as $tpl ) : ?>
								<li>
									<label>
										<input type="checkbox" name="som_budget_workflow_ids[]" value="<?php echo esc_attr( (string) (int) $tpl->id ); ?>" <?php checked( in_array( (int) $tpl->id, array_map( 'intval', $workflow_links ), true ) ); ?> />
										<?php echo esc_html( (string) $tpl->name ); ?>
										<?php if ( empty( $tpl->is_active ) ) : ?>
											<span class="som-muted">(<?php echo esc_html__( 'inactive', 'order-machine' ); ?>)</span>
										<?php endif; ?>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>

			<tr class="som-budget-field-manual" <?php echo 'material' === $current_type ? 'hidden' : ''; ?>>
				<th scope="row"><?php echo esc_html__( 'Product scope', 'order-machine' ); ?></th>
				<td>
					<p class="description"><?php echo esc_html__( 'Leave all unchecked for all products. Check products to limit funding to those SKUs.', 'order-machine' ); ?></p>
					<?php if ( empty( $product_options ) ) : ?>
						<p class="som-muted"><?php echo esc_html__( 'No active products yet.', 'order-machine' ); ?></p>
					<?php else : ?>
						<ul class="som-checkbox-list">
							<?php foreach ( $product_options as $product ) : ?>
								<li>
									<label>
										<input type="checkbox" name="som_budget_product_ids[]" value="<?php echo esc_attr( (string) (int) $product->id ); ?>" <?php checked( in_array( (int) $product->id, array_map( 'intval', $product_links ), true ) ); ?> />
										<?php echo esc_html( (string) $product->name ); ?>
										<?php if ( ! empty( $product->sku ) ) : ?>
											<span class="som-muted">(<?php echo esc_html( (string) $product->sku ); ?>)</span>
										<?php endif; ?>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save budget', 'order-machine' ); ?></button>
		</p>
	</form>

	<?php if ( ! $is_new && $budget ) : ?>
		<h2><?php echo esc_html__( 'Manual adjustment', 'order-machine' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Deposit (positive) or withdraw (negative). Notes are required. This does not change material stock.', 'order-machine' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-budgets' ) ); ?>" class="som-budget-adjust-form">
			<?php wp_nonce_field( 'som_budget_adjust', 'som_budget_adjust_nonce' ); ?>
			<input type="hidden" name="som_budget_adjust" value="1" />
			<input type="hidden" name="budget_id" value="<?php echo esc_attr( (string) (int) $budget->id ); ?>" />
			<label for="som_budget_adjust_amount" class="screen-reader-text"><?php echo esc_html__( 'Amount', 'order-machine' ); ?></label>
			<input type="number" step="0.0001" id="som_budget_adjust_amount" name="som_budget_adjust_amount" class="small-text" required />
			<label for="som_budget_adjust_notes" class="screen-reader-text"><?php echo esc_html__( 'Notes', 'order-machine' ); ?></label>
			<input type="text" id="som_budget_adjust_notes" name="som_budget_adjust_notes" class="regular-text" placeholder="<?php echo esc_attr__( 'Notes (required)', 'order-machine' ); ?>" required />
			<button type="submit" class="button"><?php echo esc_html__( 'Record adjustment', 'order-machine' ); ?></button>
		</form>

		<?php if ( 'material' === $budget->type && ! empty( $budget->material_id ) ) : ?>
			<h2><?php echo esc_html__( 'R&amp;D / non-sale write-off', 'order-machine' ); ?></h2>
			<p class="description">
				<?php echo esc_html__( 'Decrements material stock and debits this budget by qty × weighted-average unit cost. Notes are required.', 'order-machine' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-budgets' ) ); ?>" class="som-budget-writeoff-form">
				<?php wp_nonce_field( 'som_budget_writeoff', 'som_budget_writeoff_nonce' ); ?>
				<input type="hidden" name="som_budget_writeoff" value="1" />
				<input type="hidden" name="budget_id" value="<?php echo esc_attr( (string) (int) $budget->id ); ?>" />
				<input type="hidden" name="material_id" value="<?php echo esc_attr( (string) (int) $budget->material_id ); ?>" />
				<label for="som_writeoff_qty" class="screen-reader-text"><?php echo esc_html__( 'Quantity', 'order-machine' ); ?></label>
				<input type="number" step="0.01" min="0.01" id="som_writeoff_qty" name="som_writeoff_qty" class="small-text" required />
				<?php if ( $material_row ) : ?>
					<span class="som-muted"><?php echo esc_html( (string) $material_row->unit ); ?></span>
				<?php endif; ?>
				<label for="som_writeoff_notes" class="screen-reader-text"><?php echo esc_html__( 'Notes', 'order-machine' ); ?></label>
				<input type="text" id="som_writeoff_notes" name="som_writeoff_notes" class="regular-text" placeholder="<?php echo esc_attr__( 'e.g. R&D (required)', 'order-machine' ); ?>" required />
				<button type="submit" class="button"><?php echo esc_html__( 'Write off', 'order-machine' ); ?></button>
			</form>
		<?php endif; ?>

		<h2><?php echo esc_html__( 'Recent ledger', 'order-machine' ); ?></h2>
		<?php if ( empty( $ledger ) ) : ?>
			<p class="som-muted"><?php echo esc_html__( 'No ledger entries yet.', 'order-machine' ); ?></p>
		<?php else : ?>
			<table class="widefat striped som-budget-ledger-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Date', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Amount', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Reason', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Reference', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Notes', 'order-machine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ledger as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $entry->created_at ) ); ?></td>
							<td>
								<?php
								$amount = (float) $entry->change_amount;
								$sign   = $amount > 0 ? '+' : '';
								echo esc_html( $sign . '£' . number_format_i18n( $amount, 2 ) );
								?>
							</td>
							<td><?php echo esc_html( SOM_Budgets::reason_label( (string) $entry->reason ) ); ?></td>
							<td>
								<?php if ( ! empty( $entry->order_id ) ) : ?>
									<?php
									$order = SOM_Orders::get( (int) $entry->order_id );
									$label = $order && ! empty( $order->external_order_id )
										? (string) $order->external_order_id
										: '#' . (int) $entry->order_id;
									?>
									<a href="<?php echo esc_url( SOM_Orders::detail_url( (int) $entry->order_id ) ); ?>">
										<?php echo esc_html( $label ); ?>
									</a>
								<?php elseif ( ! empty( $entry->purchase_order_item_id ) ) : ?>
									<?php
									$poi = SOM_Purchase_Orders::get_item( (int) $entry->purchase_order_item_id );
									if ( $poi && ! empty( $poi->purchase_order_id ) ) :
										?>
										<a href="<?php echo esc_url( SOM_Purchase_Orders::detail_url( (int) $poi->purchase_order_id ) ); ?>">
											<?php
											printf(
												/* translators: %d: purchase order id */
												esc_html__( 'PO #%d', 'order-machine' ),
												(int) $poi->purchase_order_id
											);
											?>
										</a>
									<?php else : ?>
										<span class="som-muted">—</span>
									<?php endif; ?>
								<?php else : ?>
									<span class="som-muted">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								echo null !== $entry->notes && '' !== $entry->notes
									? esc_html( (string) $entry->notes )
									: '<span class="som-muted">—</span>';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php if ( $is_new ) : ?>
	<script>
	(function () {
		var typeSelect = document.getElementById('som_budget_type');
		if (!typeSelect) {
			return;
		}
		function sync() {
			var isMaterial = typeSelect.value === 'material';
			document.querySelectorAll('.som-budget-field-material').forEach(function (el) {
				el.hidden = !isMaterial;
			});
			document.querySelectorAll('.som-budget-field-manual').forEach(function (el) {
				el.hidden = isMaterial;
			});
		}
		typeSelect.addEventListener('change', sync);
		sync();
	})();
	</script>
<?php endif; ?>
