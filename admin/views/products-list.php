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
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result   = SOM_Products::query(
	array(
		'status' => $status,
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
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Products', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Products::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add product', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

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
				<th scope="col" class="column-recipe"><?php echo esc_html__( 'Recipe', 'order-machine' ); ?></th>
				<th scope="col" class="column-listings"><?php echo esc_html__( 'Listings', 'order-machine' ); ?></th>
				<th scope="col" class="column-status"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $products ) ) : ?>
				<tr>
					<td colspan="6"><?php echo esc_html__( 'No products found.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $products as $product ) : ?>
					<tr>
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( SOM_Products::detail_url( (int) $product->id ) ); ?>">
									<?php echo esc_html( (string) $product->name ); ?>
								</a>
							</strong>
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
						<td class="column-recipe">
							<?php
							printf(
								/* translators: %d: material count */
								esc_html( _n( '%d material', '%d materials', (int) $product->recipe_count, 'order-machine' ) ),
								(int) $product->recipe_count
							);
							?>
						</td>
						<td class="column-listings">
							<?php echo esc_html( (string) (int) $product->listing_count ); ?>
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
