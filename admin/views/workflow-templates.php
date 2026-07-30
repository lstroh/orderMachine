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
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result    = SOM_Workflows::query(
	array(
		'status' => $status,
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
				<th scope="col" class="column-steps"><?php echo esc_html__( 'Steps', 'order-machine' ); ?></th>
				<th scope="col" class="column-products"><?php echo esc_html__( 'Products', 'order-machine' ); ?></th>
				<th scope="col" class="column-status"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $templates ) ) : ?>
				<tr>
					<td colspan="4"><?php echo esc_html__( 'No workflow templates found.', 'order-machine' ); ?></td>
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
