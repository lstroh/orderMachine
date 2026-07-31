<?php
/**
 * Listings list admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$channel = isset( $_GET['som_channel'] ) ? sanitize_key( wp_unslash( $_GET['som_channel'] ) ) : 'all';
$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result   = SOM_Listings::query(
	array(
		'channel' => $channel,
		's'       => $search,
		'paged'   => $paged,
	)
);
$listings = $result['listings'];
$total    = $result['total'];
$pages    = $result['pages'];
$paged    = $result['paged'];

$channel_options = array(
	'all'  => __( 'All channels', 'order-machine' ),
	'ebay' => __( 'eBay', 'order-machine' ),
	'etsy' => __( 'Etsy', 'order-machine' ),
);
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Listings', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Listings::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add listing map', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<form method="get" class="som-catalog-filters">
		<input type="hidden" name="page" value="som-listings" />
		<label class="screen-reader-text" for="som-listing-search"><?php echo esc_html__( 'Search listings', 'order-machine' ); ?></label>
		<input type="search" id="som-listing-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search ID, title, product…', 'order-machine' ); ?>" />
		<select name="som_channel">
			<?php foreach ( $channel_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $channel, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'order-machine' ); ?></button>
	</form>

	<p class="som-catalog-count">
		<?php
		printf(
			/* translators: %d: listing count */
			esc_html( _n( '%d listing', '%d listings', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped som-catalog-table">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Listing', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Product', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Price', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Qty', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Inventory', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Last synced', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $listings ) ) : ?>
				<tr>
					<td colspan="7"><?php echo esc_html__( 'No listings yet. Add a map or seed dummy catalogue.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $listings as $listing ) : ?>
					<?php
					$inv   = isset( $listing->inventory ) ? $listing->inventory : array( 'mode' => 'flat' );
					$mode  = isset( $inv['mode'] ) ? (string) $inv['mode'] : 'flat';
					$title = ! empty( $listing->title ) ? (string) $listing->title : (string) $listing->external_listing_id;
					?>
					<tr>
						<td>
							<span class="som-badge som-badge-channel"><?php echo esc_html( (string) $listing->channel_name ); ?></span>
						</td>
						<td>
							<a href="<?php echo esc_url( SOM_Listings::detail_url( (int) $listing->id ) ); ?>">
								<strong><?php echo esc_html( $title ); ?></strong>
							</a>
							<br />
							<code><?php echo esc_html( (string) $listing->external_listing_id ); ?></code>
						</td>
						<td>
							<a href="<?php echo esc_url( SOM_Products::detail_url( (int) $listing->product_id ) ); ?>">
								<?php echo esc_html( (string) $listing->product_name ); ?>
							</a>
						</td>
						<td><?php echo esc_html( number_format_i18n( (float) $listing->price, 2 ) ); ?></td>
						<td><?php echo esc_html( (string) (int) $listing->quantity_available ); ?></td>
						<td>
							<?php if ( 'variations' === $mode ) : ?>
								<span class="som-badge som-badge-variations">
									<?php
									printf(
										/* translators: %d: variation count */
										esc_html__( '%d variations', 'order-machine' ),
										count( $inv['variations'] ?? array() )
									);
									?>
								</span>
							<?php else : ?>
								<span class="som-muted"><?php echo esc_html__( 'Flat', 'order-machine' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							echo $listing->last_synced_at
								? esc_html( (string) $listing->last_synced_at )
								: esc_html__( 'Never', 'order-machine' );
							?>
						</td>
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
							'base'      => add_query_arg( 'paged', '%#%' ),
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
