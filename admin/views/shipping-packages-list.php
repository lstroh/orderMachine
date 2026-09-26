<?php
/**
 * Shipping packages list admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status = isset( $_GET['som_status'] ) ? sanitize_key( wp_unslash( $_GET['som_status'] ) ) : 'all';
if ( ! in_array( $status, array( 'all', 'active', 'inactive' ), true ) ) {
	$status = 'all';
}
$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result   = SOM_Shipping_Packages::query(
	array(
		's'      => $search,
		'status' => $status,
		'paged'  => $paged,
	)
);
$packages = $result['packages'];
$total    = $result['total'];
$pages    = $result['pages'];
$paged    = $result['paged'];
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Shipping packages', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Shipping_Packages::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add package', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<p class="description">
		<?php echo esc_html__( 'Outer mailers and boxes (dimensions in mm, tare weight in grams). Assign a default package and planned postage on each sellable product.', 'order-machine' ); ?>
	</p>

	<form method="get" class="som-catalog-filters">
		<input type="hidden" name="page" value="som-shipping-packages" />
		<label class="screen-reader-text" for="som-package-search"><?php echo esc_html__( 'Search packages', 'order-machine' ); ?></label>
		<input type="search" id="som-package-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search name…', 'order-machine' ); ?>" />
		<label class="screen-reader-text" for="som-package-status"><?php echo esc_html__( 'Status', 'order-machine' ); ?></label>
		<select id="som-package-status" name="som_status">
			<option value="all" <?php selected( $status, 'all' ); ?>><?php echo esc_html__( 'All statuses', 'order-machine' ); ?></option>
			<option value="active" <?php selected( $status, 'active' ); ?>><?php echo esc_html__( 'Active', 'order-machine' ); ?></option>
			<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php echo esc_html__( 'Inactive', 'order-machine' ); ?></option>
		</select>
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'order-machine' ); ?></button>
	</form>

	<p class="som-catalog-count">
		<?php
		printf(
			/* translators: %d: package count */
			esc_html( _n( '%d package', '%d packages', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped som-catalog-table">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Name', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Outer size (mm)', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Tare (g)', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $packages ) ) : ?>
				<tr>
					<td colspan="4"><?php echo esc_html__( 'No packages yet.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $packages as $package ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( SOM_Shipping_Packages::detail_url( (int) $package->id ) ); ?>">
								<?php echo esc_html( (string) $package->name ); ?>
							</a>
							<?php if ( ! empty( $package->is_default ) ) : ?>
								<span class="som-badge"><?php echo esc_html__( 'Default', 'order-machine' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							echo esc_html(
								sprintf(
									'%d × %d × %d',
									(int) $package->length_mm,
									(int) $package->width_mm,
									(int) $package->height_mm
								)
							);
							?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (float) $package->tare_weight_grams, 1 ) ); ?></td>
						<td>
							<?php
							echo ! empty( $package->is_active )
								? esc_html__( 'Active', 'order-machine' )
								: esc_html__( 'Inactive', 'order-machine' );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
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
