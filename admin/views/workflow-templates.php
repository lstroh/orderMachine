<?php
/**
 * Workflow templates list admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$status = isset( $_GET['som_status'] ) ? sanitize_key( wp_unslash( $_GET['som_status'] ) ) : 'active';
$kind   = isset( $_GET['som_kind'] ) ? sanitize_key( wp_unslash( $_GET['som_kind'] ) ) : '';
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result    = SOM_Workflows::query(
	array(
		'status' => $status,
		'kind'   => $kind,
		's'      => $search,
		'paged'  => $paged,
	)
);
$templates = $result['templates'];
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
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Workflows', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Workflows::editor_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add template', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php
	$import_preview       = null;
	$import_preview_error = '';
	if ( isset( $_GET['som_import_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview token.
		$import_token  = sanitize_text_field( wp_unslash( $_GET['som_import_preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview token.
		$import_loaded = SOM_Workflow_Import::preview_for_token( get_current_user_id(), $import_token );
		if ( is_wp_error( $import_loaded ) ) {
			$import_preview_error = $import_loaded->get_error_message();
		} elseif ( is_array( $import_loaded ) ) {
			$import_preview = $import_loaded;
		}
	}
	$import_open = is_array( $import_preview ) || '' !== $import_preview_error;
	?>
	<details class="som-import-workflows"<?php echo $import_open ? esc_attr( ' open' ) : ''; ?>>
		<summary><?php echo esc_html__( 'Import workflows', 'order-machine' ); ?></summary>
		<p class="description">
			<?php echo esc_html__( 'Comma or tab-separated. One row per step; rows with the same name are one template. Preview shows the name that will be saved and does not write anything. A name that already exists is created as Name (2). A Post-ship workflow needs a Package arrived step. Import does not update an existing template.', 'order-machine' ); ?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( SOM_Workflows::list_url( array( 'som_workflow_export' => '1' ) ), 'som_workflow_export' ) ); ?>">
				<?php echo esc_html__( 'Export CSV', 'order-machine' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( SOM_Workflows::list_url( array( 'som_workflow_template' => '1' ) ), 'som_workflow_template' ) ); ?>">
				<?php echo esc_html__( 'Download template', 'order-machine' ); ?>
			</a>
		</p>
		<form method="post" action="<?php echo esc_url( SOM_Workflows::list_url() ); ?>" enctype="multipart/form-data" class="som-import-workflows-form">
			<?php wp_nonce_field( 'som_import_workflows_preview', 'som_workflow_nonce' ); ?>
			<p>
				<label for="som-workflow-import-file"><?php echo esc_html__( 'Workflows file', 'order-machine' ); ?></label>
				<input type="file" name="som_import_file" id="som-workflow-import-file" required />
			</p>
			<?php submit_button( __( 'Preview', 'order-machine' ), 'secondary', 'som_preview_workflows', false ); ?>
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
					/* translators: 1: workflows that will be created, 2: workflows that will fail */
					esc_html__( 'Ready to create: %1$d. Failed: %2$d. Nothing is saved until you choose Import.', 'order-machine' ),
					(int) $import_preview['ready'],
					(int) $import_preview['failed']
				);
				?>
			</p>
			<table class="widefat striped som-import-workflows-preview">
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
							<td colspan="3"><?php echo esc_html__( 'No workflow rows in that file.', 'order-machine' ); ?></td>
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

			<?php if ( ( ! empty( $import_preview['ready'] ) || ! empty( $import_preview['failed'] ) ) && ! empty( $import_preview['token'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( SOM_Workflows::list_url() ); ?>">
					<?php wp_nonce_field( 'som_import_workflows_commit', 'som_workflow_nonce' ); ?>
					<input type="hidden" name="som_import_token" value="<?php echo esc_attr( (string) $import_preview['token'] ); ?>" />
					<?php submit_button( __( 'Import', 'order-machine' ), 'primary', 'som_import_workflows', false ); ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</details>

	<form method="get" class="som-catalog-filters">
		<input type="hidden" name="page" value="som-workflows" />
		<label class="screen-reader-text" for="som-workflow-search"><?php echo esc_html__( 'Search workflows', 'order-machine' ); ?></label>
		<input type="search" id="som-workflow-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search name or description…', 'order-machine' ); ?>" />
		<select name="som_status">
			<?php foreach ( $status_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="som_kind">
			<option value=""><?php echo esc_html__( 'All kinds', 'order-machine' ); ?></option>
			<?php foreach ( SOM_Workflows::kind_choices() as $kind_key => $kind_label ) : ?>
				<option value="<?php echo esc_attr( $kind_key ); ?>" <?php selected( $kind, $kind_key ); ?>>
					<?php echo esc_html( $kind_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'order-machine' ); ?></button>
	</form>

	<p class="som-catalog-count">
		<?php
		printf(
			/* translators: %d: template count */
			esc_html( _n( '%d template', '%d templates', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped som-catalog-table">
		<thead>
			<tr>
				<th scope="col" class="column-name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></th>
				<th scope="col" class="column-kind"><?php echo esc_html__( 'Kind', 'order-machine' ); ?></th>
				<th scope="col" class="column-steps"><?php echo esc_html__( 'Steps', 'order-machine' ); ?></th>
				<th scope="col" class="column-products"><?php echo esc_html__( 'Products', 'order-machine' ); ?></th>
				<th scope="col" class="column-status"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $templates ) ) : ?>
				<tr>
					<td colspan="5"><?php echo esc_html__( 'No workflow templates found.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $templates as $template ) : ?>
					<tr>
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( SOM_Workflows::editor_url( (int) $template->id ) ); ?>">
									<?php echo esc_html( (string) $template->name ); ?>
								</a>
							</strong>
							<?php if ( ! empty( $template->description ) ) : ?>
								<p class="description som-muted"><?php echo esc_html( wp_trim_words( (string) $template->description, 12 ) ); ?></p>
							<?php endif; ?>
							<div class="som-row-clone">
								<?php SOM_Workflows::render_clone_button( (int) $template->id, 'list', 'button-link' ); ?>
							</div>
						</td>
						<td class="column-kind">
							<?php
							$tk = SOM_Workflows::sanitize_kind( isset( $template->kind ) ? $template->kind : 'make' );
							$labels = SOM_Workflows::kind_choices();
							echo esc_html( isset( $labels[ $tk ] ) ? $labels[ $tk ] : $tk );
							if ( 'post_ship' === $tk && ! SOM_Workflows::template_has_arrival_step( (int) $template->id ) ) {
								echo ' <span class="som-badge som-badge-no-arrival">' . esc_html__( 'No arrival step', 'order-machine' ) . '</span>';
							}
							?>
						</td>
						<td class="column-steps">
							<?php echo esc_html( (string) (int) $template->step_count ); ?>
						</td>
						<td class="column-products">
							<?php
							$count = (int) $template->product_count;
							echo esc_html( (string) $count );
							if ( $count > 0 ) {
								echo ' <span class="som-badge som-badge-in-use">' . esc_html__( 'In use', 'order-machine' ) . '</span>';
							}
							?>
						</td>
						<td class="column-status">
							<?php if ( (int) $template->is_active ) : ?>
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
						/* translators: %d: template count */
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
