<?php
/**
 * Suppliers list admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result    = SOM_Suppliers::query(
	array(
		's'     => $search,
		'paged' => $paged,
	)
);
$suppliers = $result['suppliers'];
$total     = $result['total'];
$pages     = $result['pages'];
$paged     = $result['paged'];
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Suppliers', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Suppliers::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add supplier', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<form method="get" class="som-catalog-filters">
		<input type="hidden" name="page" value="som-suppliers" />
		<label class="screen-reader-text" for="som-supplier-search"><?php echo esc_html__( 'Search suppliers', 'order-machine' ); ?></label>
		<input type="search" id="som-supplier-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search name, website, contact…', 'order-machine' ); ?>" />
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'order-machine' ); ?></button>
	</form>

	<p class="som-catalog-count">
		<?php
		printf(
			/* translators: %d: supplier count */
			esc_html( _n( '%d supplier', '%d suppliers', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped som-catalog-table">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Name', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Website', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Contact', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $suppliers ) ) : ?>
				<tr>
					<td colspan="3"><?php echo esc_html__( 'No suppliers yet.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $suppliers as $supplier ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( SOM_Suppliers::detail_url( (int) $supplier->id ) ); ?>">
								<?php echo esc_html( (string) $supplier->name ); ?>
							</a>
						</td>
						<td>
							<?php if ( ! empty( $supplier->website ) ) : ?>
								<a href="<?php echo esc_url( (string) $supplier->website ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( (string) $supplier->website ); ?>
								</a>
							<?php else : ?>
								<span class="som-muted">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $supplier->contact_info ? (string) $supplier->contact_info : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => esc_url_raw( add_query_arg( 'paged', '%#%' ) ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
