<?php
/**
 * Materials list admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$status = isset( $_GET['som_status'] ) ? sanitize_key( wp_unslash( $_GET['som_status'] ) ) : 'active';
if ( 'all' === $status ) {
	$status = '';
}
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result    = SOM_Materials::query(
	array(
		'status' => '' === $status ? 'all' : $status,
		's'      => $search,
		'paged'  => $paged,
	)
);
$materials = $result['materials'];
$total     = $result['total'];
$pages     = $result['pages'];
$paged     = $result['paged'];

$status_options = array(
	'active'   => __( 'Active', 'order-machine' ),
	'inactive' => __( 'Inactive', 'order-machine' ),
	'all'      => __( 'All', 'order-machine' ),
);
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Materials', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Materials::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add material', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php
	$import_preview       = null;
	$import_preview_error = '';
	if ( isset( $_GET['som_import_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview token.
		$import_token  = sanitize_text_field( wp_unslash( $_GET['som_import_preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview token.
		$import_loaded = SOM_Material_Import::preview_for_token( get_current_user_id(), $import_token );
		if ( is_wp_error( $import_loaded ) ) {
			$import_preview_error = $import_loaded->get_error_message();
		} elseif ( is_array( $import_loaded ) ) {
			$import_preview = $import_loaded;
		}
	}
	$import_open = is_array( $import_preview ) || '' !== $import_preview_error;
	?>
	<details class="som-import-materials"<?php echo $import_open ? esc_attr( ' open' ) : ''; ?>>
		<summary><?php echo esc_html__( 'Import materials', 'order-machine' ); ?></summary>
		<p class="description">
			<?php echo esc_html__( 'Comma or tab-separated. Preview shows the name that will be saved and does not write anything. A name that already exists is created as Name (2). Stock on a new material stays 0. Made-in-house materials are left out of the export.', 'order-machine' ); ?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( SOM_Materials::list_url( array( 'som_material_export' => '1' ) ), 'som_material_export' ) ); ?>">
				<?php echo esc_html__( 'Export CSV', 'order-machine' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( SOM_Materials::list_url( array( 'som_material_template' => '1' ) ), 'som_material_template' ) ); ?>">
				<?php echo esc_html__( 'Download template', 'order-machine' ); ?>
			</a>
		</p>
		<form method="post" action="<?php echo esc_url( SOM_Materials::list_url() ); ?>" enctype="multipart/form-data" class="som-import-materials-form">
			<?php wp_nonce_field( 'som_import_materials_preview', 'som_material_nonce' ); ?>
			<p>
				<label for="som-material-import-file"><?php echo esc_html__( 'Materials file', 'order-machine' ); ?></label>
				<input type="file" name="som_import_file" id="som-material-import-file" required />
			</p>
			<?php submit_button( __( 'Preview', 'order-machine' ), 'secondary', 'som_preview_materials', false ); ?>
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
					/* translators: 1: rows that will be created, 2: rows that will fail */
					esc_html__( 'Ready to create: %1$d. Failed: %2$d. Nothing is saved until you choose Import.', 'order-machine' ),
					(int) $import_preview['ready'],
					(int) $import_preview['failed']
				);
				?>
			</p>
			<table class="widefat striped som-import-materials-preview">
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
							<td colspan="3"><?php echo esc_html__( 'No material rows in that file.', 'order-machine' ); ?></td>
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
				<form method="post" action="<?php echo esc_url( SOM_Materials::list_url() ); ?>">
					<?php wp_nonce_field( 'som_import_materials_commit', 'som_material_nonce' ); ?>
					<input type="hidden" name="som_import_token" value="<?php echo esc_attr( (string) $import_preview['token'] ); ?>" />
					<?php submit_button( __( 'Import', 'order-machine' ), 'primary', 'som_import_materials', false ); ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</details>

	<form method="get" class="som-catalog-filters">
		<input type="hidden" name="page" value="som-materials" />
		<label class="screen-reader-text" for="som-material-search"><?php echo esc_html__( 'Search materials', 'order-machine' ); ?></label>
		<input type="search" id="som-material-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search name or unit…', 'order-machine' ); ?>" />
		<select name="som_status">
			<?php foreach ( $status_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( '' === $status ? 'all' : $status, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'order-machine' ); ?></button>
	</form>

	<p class="som-catalog-count">
		<?php
		printf(
			/* translators: %d: material count */
			esc_html( _n( '%d material', '%d materials', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped som-catalog-table">
		<thead>
			<tr>
				<th scope="col" class="column-name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></th>
				<th scope="col" class="column-unit"><?php echo esc_html__( 'Unit', 'order-machine' ); ?></th>
				<th scope="col" class="column-stock"><?php echo esc_html__( 'Current stock', 'order-machine' ); ?></th>
				<th scope="col" class="column-threshold"><?php echo esc_html__( 'Low-stock at', 'order-machine' ); ?></th>
				<th scope="col" class="column-cost"><?php echo esc_html__( 'WA cost', 'order-machine' ); ?></th>
				<th scope="col" class="column-value"><?php echo esc_html__( 'Value on hand', 'order-machine' ); ?></th>
				<th scope="col" class="column-status"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $materials ) ) : ?>
				<tr>
					<td colspan="7"><?php echo esc_html__( 'No materials found.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $materials as $material ) : ?>
					<tr>
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( SOM_Materials::detail_url( (int) $material->id ) ); ?>">
									<?php echo esc_html( (string) $material->name ); ?>
								</a>
							</strong>
							<?php if ( ! empty( $material->source_product_id ) ) : ?>
								<br /><span class="som-badge som-badge-made-in-house"><?php echo esc_html__( 'Made in-house', 'order-machine' ); ?></span>
								<?php if ( ! empty( $material->is_low_stock ) ) : ?>
									— <a href="<?php echo esc_url( SOM_Materials::detail_url( (int) $material->id ) ); ?>#som-produce-panel"><?php echo esc_html__( 'Produce', 'order-machine' ); ?></a>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( ! empty( $material->is_low_stock ) ) : ?>
								<br /><span class="som-badge som-badge-low-stock"><?php echo esc_html__( 'Low stock', 'order-machine' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $material->goal_alert_level ) ) : ?>
								<br />
								<span class="som-badge som-badge-goal-<?php echo esc_attr( sanitize_html_class( (string) $material->goal_alert_level ) ); ?>">
									<?php echo esc_html( SOM_Material_Costing::alert_label( (string) $material->goal_alert_level ) ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="column-unit"><?php echo esc_html( (string) $material->unit ); ?></td>
						<td class="column-stock">
							<?php echo esc_html( number_format_i18n( (float) $material->current_stock, 2 ) ); ?>
						</td>
						<td class="column-threshold">
							<?php
							echo null !== $material->low_stock_threshold && '' !== $material->low_stock_threshold
								? esc_html( number_format_i18n( (float) $material->low_stock_threshold, 2 ) )
								: '<span class="som-muted">—</span>';
							?>
						</td>
						<td class="column-cost">
							£<?php echo esc_html( number_format_i18n( (float) $material->weighted_average, 4 ) ); ?>
						</td>
						<td class="column-value">
							£<?php echo esc_html( number_format_i18n( (float) $material->total_value_on_hand, 2 ) ); ?>
						</td>
						<td class="column-status">
							<?php if ( (int) $material->is_active ) : ?>
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
						/* translators: %d: material count */
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
