<?php
/**
 * Products list admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$status = isset( $_GET['som_status'] ) ? sanitize_key( wp_unslash( $_GET['som_status'] ) ) : 'active';
$type   = isset( $_GET['som_type'] ) ? sanitize_key( wp_unslash( $_GET['som_type'] ) ) : 'all';
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result   = SOM_Products::query(
	array(
		'status' => $status,
		'type'   => $type,
		's'      => $search,
		'paged'  => $paged,
	)
);
$products = $result['products'];
$total    = $result['total'];
$pages    = $result['pages'];
$paged    = $result['paged'];

$status_options = array(
	'active'   => __( 'Active', 'order-machine' ),
	'inactive' => __( 'Inactive', 'order-machine' ),
	'all'      => __( 'All', 'order-machine' ),
);
$type_options = array(
	'all'      => __( 'All types', 'order-machine' ),
	'sellable' => __( 'Sellable', 'order-machine' ),
	'internal' => __( 'Internal', 'order-machine' ),
);
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Products', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Products::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add product', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php
	$import_preview       = null;
	$import_preview_error = '';
	if ( isset( $_GET['som_import_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview token.
		$import_token  = sanitize_text_field( wp_unslash( $_GET['som_import_preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview token.
		$import_loaded = SOM_Product_Import::preview_for_token( get_current_user_id(), $import_token );
		if ( is_wp_error( $import_loaded ) ) {
			$import_preview_error = $import_loaded->get_error_message();
		} elseif ( is_array( $import_loaded ) ) {
			$import_preview = $import_loaded;
		}
	}
	$import_open = is_array( $import_preview ) || '' !== $import_preview_error;
	?>
	<details class="som-import-products"<?php echo $import_open ? esc_attr( ' open' ) : ''; ?>>
		<summary><?php echo esc_html__( 'Import products', 'order-machine' ); ?></summary>
		<p class="description">
			<?php echo esc_html__( 'Comma or tab-separated. One row per recipe line; rows with the same name are one product. Preview shows the name that will be saved and does not write anything. A name that already exists is created as Name (2). The SKU in the file is kept. A SKU that another product already uses is listed and the product is still created. Import does not update an existing product or create listings.', 'order-machine' ); ?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( SOM_Products::list_url( array( 'som_product_export' => '1' ) ), 'som_product_export' ) ); ?>">
				<?php echo esc_html__( 'Export CSV', 'order-machine' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( SOM_Products::list_url( array( 'som_product_template' => '1' ) ), 'som_product_template' ) ); ?>">
				<?php echo esc_html__( 'Download template', 'order-machine' ); ?>
			</a>
		</p>
		<form method="post" action="<?php echo esc_url( SOM_Products::list_url() ); ?>" enctype="multipart/form-data" class="som-import-products-form">
			<?php wp_nonce_field( 'som_import_products_preview', 'som_product_nonce' ); ?>
			<p>
				<label for="som-product-import-file"><?php echo esc_html__( 'Products file', 'order-machine' ); ?></label>
				<input type="file" name="som_import_file" id="som-product-import-file" required />
			</p>
			<?php submit_button( __( 'Preview', 'order-machine' ), 'secondary', 'som_preview_products', false ); ?>
		</form>

		<?php if ( '' !== $import_preview_error ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $import_preview_error ); ?></p></div>
		<?php endif; ?>

		<?php if ( is_array( $import_preview ) && '' !== (string) $import_preview['file_error'] ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( (string) $import_preview['file_error'] ); ?></p></div>
		<?php endif; ?>

		<?php if ( is_array( $import_preview ) && '' === (string) $import_preview['file_error'] ) : ?>
			<h3><?php echo esc_html__( 'Preview', 'order-machine' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: 1: products that will be created, 2: products that will fail */
					esc_html__( 'Ready to create: %1$d. Failed: %2$d. Nothing is saved until you choose Import.', 'order-machine' ),
					(int) $import_preview['ready'],
					(int) $import_preview['failed']
				);
				?>
			</p>
			<table class="widefat striped som-import-products-preview">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'File name', 'order-machine' ); ?></th>
						<th><?php echo esc_html__( 'Name that will be saved', 'order-machine' ); ?></th>
						<th><?php echo esc_html__( 'Error', 'order-machine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $import_preview['rows'] ) ) : ?>
						<tr>
							<td colspan="3"><?php echo esc_html__( 'No product rows in that file.', 'order-machine' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $import_preview['rows'] as $import_row ) : ?>
							<tr>
								<td><?php echo esc_html( isset( $import_row['file_name'] ) ? (string) $import_row['file_name'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $import_row['save_name'] ) ? (string) $import_row['save_name'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $import_row['message'] ) ? (string) $import_row['message'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $import_preview['ready'] ) && ! empty( $import_preview['token'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( SOM_Products::list_url() ); ?>">
					<?php wp_nonce_field( 'som_import_products_commit', 'som_product_nonce' ); ?>
					<input type="hidden" name="som_import_token" value="<?php echo esc_attr( (string) $import_preview['token'] ); ?>" />
					<?php submit_button( __( 'Import', 'order-machine' ), 'primary', 'som_import_products', false ); ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</details>

	<form method="get" class="som-catalog-filters">
		<input type="hidden" name="page" value="som-products" />
		<label class="screen-reader-text" for="som-product-search"><?php echo esc_html__( 'Search products', 'order-machine' ); ?></label>
		<input type="search" id="som-product-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search name or SKU…', 'order-machine' ); ?>" />
		<select name="som_status">
			<?php foreach ( $status_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="som_type">
			<?php foreach ( $type_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'order-machine' ); ?></button>
	</form>

	<p class="som-catalog-count">
		<?php
		printf(
			/* translators: %d: product count */
			esc_html( _n( '%d product', '%d products', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped som-catalog-table">
		<thead>
			<tr>
				<th scope="col" class="column-name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></th>
				<th scope="col" class="column-sku"><?php echo esc_html__( 'SKU', 'order-machine' ); ?></th>
				<th scope="col" class="column-workflow"><?php echo esc_html__( 'Workflow', 'order-machine' ); ?></th>
				<th scope="col" class="column-target"><?php echo esc_html__( 'Target price', 'order-machine' ); ?></th>
				<th scope="col" class="column-material-cost"><?php echo esc_html__( 'Material cost', 'order-machine' ); ?></th>
				<th scope="col" class="column-margin"><?php echo esc_html__( 'Margin', 'order-machine' ); ?></th>
				<th scope="col" class="column-status"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $products ) ) : ?>
				<tr>
					<td colspan="7"><?php echo esc_html__( 'No products found.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $products as $product ) : ?>
					<?php
					$costing     = SOM_Products::recipe_costing( (int) $product->id );
					$alert_level = $costing ? SOM_Material_Costing::worst_alert_level( $costing['goal_alerts'] ) : '';
					?>
					<tr>
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( SOM_Products::detail_url( (int) $product->id ) ); ?>">
									<?php echo esc_html( (string) $product->name ); ?>
								</a>
							</strong>
							<?php if ( ! empty( $product->is_internal ) ) : ?>
								<br /><span class="som-badge som-badge-internal"><?php echo esc_html__( 'Internal', 'order-machine' ); ?></span>
							<?php endif; ?>
							<?php if ( $alert_level ) : ?>
								<br />
								<span class="som-badge som-badge-goal-<?php echo esc_attr( sanitize_html_class( $alert_level ) ); ?>">
									<?php echo esc_html( SOM_Material_Costing::alert_label( $alert_level ) ); ?>
								</span>
							<?php endif; ?>
							<div class="som-row-clone">
								<?php SOM_Products::render_clone_button( (int) $product->id, 'list', 'button-link' ); ?>
							</div>
						</td>
						<td class="column-sku">
							<?php if ( ! empty( $product->sku ) ) : ?>
								<code><?php echo esc_html( (string) $product->sku ); ?></code>
							<?php else : ?>
								<span class="som-muted">—</span>
							<?php endif; ?>
						</td>
						<td class="column-workflow">
							<?php
							if ( ! empty( $product->workflow_name ) ) {
								echo esc_html( (string) $product->workflow_name );
							} else {
								echo '<span class="som-muted">' . esc_html__( 'Not assigned', 'order-machine' ) . '</span>';
							}
							?>
						</td>
						<td class="column-target">
							<?php
							if ( $costing && null !== $costing['target_selling_price'] ) {
								echo '£' . esc_html( number_format_i18n( (float) $costing['target_selling_price'], 2 ) );
							} else {
								echo '<span class="som-muted">—</span>';
							}
							?>
						</td>
						<td class="column-material-cost">
							<?php
							if ( $costing ) {
								echo '£' . esc_html( number_format_i18n( (float) $costing['material_cost'], 4 ) );
							} else {
								echo '<span class="som-muted">—</span>';
							}
							?>
						</td>
						<td class="column-margin">
							<?php
							if ( $costing && null !== $costing['margin_percent'] ) {
								echo esc_html( number_format_i18n( (float) $costing['margin_percent'], 1 ) ) . '%';
								if ( ! empty( $costing['fee_source'] ) && 'none' !== $costing['fee_source'] ) {
									echo ' <span class="som-badge som-badge-fee-' . esc_attr( sanitize_html_class( (string) $costing['fee_source'] ) ) . '">';
									echo esc_html( 'actual' === $costing['fee_source'] ? __( 'Actual fees', 'order-machine' ) : __( 'Est. fees', 'order-machine' ) );
									echo '</span>';
								}
							} else {
								echo '<span class="som-muted">—</span>';
							}
							?>
						</td>
						<td class="column-status">
							<?php if ( (int) $product->is_active ) : ?>
								<span class="som-badge som-badge-active"><?php echo esc_html__( 'Active', 'order-machine' ); ?></span>
							<?php else : ?>
								<span class="som-badge som-badge-inactive"><?php echo esc_html__( 'Inactive', 'order-machine' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %d: product count */
						esc_html( _n( '%d item', '%d items', $total, 'order-machine' ) ),
						(int) $total
					);
					?>
				</span>
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $pages,
							'current'   => $paged,
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
