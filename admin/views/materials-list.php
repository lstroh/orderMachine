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
				<th scope="col" class="column-cost"><?php echo esc_html__( 'Unit cost', 'order-machine' ); ?></th>
				<th scope="col" class="column-status"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $materials ) ) : ?>
				<tr>
					<td colspan="6"><?php echo esc_html__( 'No materials found.', 'order-machine' ); ?></td>
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
							<?php if ( ! empty( $material->is_low_stock ) ) : ?>
								<br /><span class="som-badge som-badge-low-stock"><?php echo esc_html__( 'Low stock', 'order-machine' ); ?></span>
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
							<?php
							echo null !== $material->unit_cost && '' !== $material->unit_cost
								? esc_html( number_format_i18n( (float) $material->unit_cost, 4 ) )
								: '<span class="som-muted">—</span>';
							?>
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
