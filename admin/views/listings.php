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
	'all'    => __( 'All channels', 'order-machine' ),
	'ebay'   => __( 'eBay', 'order-machine' ),
	'amazon' => __( 'Amazon', 'order-machine' ),
	'etsy'   => __( 'Etsy', 'order-machine' ),
);

$import_result       = null;
$import_result_error = '';
if ( isset( $_GET['som_listing_import'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only result token.
	$import_token  = sanitize_text_field( wp_unslash( $_GET['som_listing_import'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only result token.
	$import_loaded = SOM_Listing_Import::recall_result( get_current_user_id(), $import_token );
	if ( null === $import_loaded ) {
		$import_result_error = __( 'That import result expired. Upload the file again.', 'order-machine' );
	} else {
		$import_result = $import_loaded;
	}
}
$import_open = is_array( $import_result ) || '' !== $import_result_error;
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Listings', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Listings::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add listing map', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<details class="som-import-listings"<?php echo $import_open ? esc_attr( ' open' ) : ''; ?>>
		<summary><?php echo esc_html__( 'Import listing report', 'order-machine' ); ?></summary>
		<p class="description">
			<?php echo esc_html__( 'Upload an eBay active listings report or an Amazon inventory report. The file is recognised from its header row. A row is saved when its SKU matches a product and that listing id is not already mapped. Price and quantity are stored here and are not sent to eBay or Amazon.', 'order-machine' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( SOM_Listings::list_url() ); ?>" enctype="multipart/form-data" class="som-import-listings-form">
			<?php wp_nonce_field( 'som_import_listings', 'som_listing_nonce' ); ?>
			<p>
				<label for="som-listing-import-file"><?php echo esc_html__( 'Listing report', 'order-machine' ); ?></label>
				<input type="file" name="som_listing_import_file" id="som-listing-import-file" required />
			</p>
			<?php submit_button( __( 'Import', 'order-machine' ), 'secondary', 'som_import_listings', false ); ?>
		</form>

		<?php if ( '' !== $import_result_error ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $import_result_error ); ?></p></div>
		<?php endif; ?>

		<?php if ( is_array( $import_result ) ) : ?>
			<?php
			$import_created   = isset( $import_result['created'] ) ? (int) $import_result['created'] : 0;
			$import_skipped   = isset( $import_result['skipped'] ) ? (int) $import_result['skipped'] : 0;
			$import_unmatched = isset( $import_result['unmatched'] ) && is_array( $import_result['unmatched'] ) ? $import_result['unmatched'] : array();
			$import_format    = isset( $import_result['format'] ) ? (string) $import_result['format'] : '';
			?>
			<h3><?php echo esc_html__( 'Import result', 'order-machine' ); ?></h3>
			<?php if ( 'amazon' === $import_format ) : ?>
				<p><?php echo esc_html__( 'Amazon inventory', 'order-machine' ); ?></p>
			<?php elseif ( 'ebay' === $import_format ) : ?>
				<p><?php echo esc_html__( 'eBay active listings', 'order-machine' ); ?></p>
			<?php endif; ?>
			<p>
				<?php
				printf(
					/* translators: 1: created listing count, 2: skipped listing count */
					esc_html__( 'Created %1$d, skipped %2$d already mapped.', 'order-machine' ),
					$import_created,
					$import_skipped
				);
				?>
			</p>
			<h3><?php echo esc_html__( 'Unmatched rows', 'order-machine' ); ?></h3>
			<table class="widefat striped som-import-listings-unmatched">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Row', 'order-machine' ); ?></th>
						<th><?php echo esc_html__( 'SKU', 'order-machine' ); ?></th>
						<th><?php echo esc_html__( 'Listing ID', 'order-machine' ); ?></th>
						<th><?php echo esc_html__( 'Reason', 'order-machine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $import_unmatched ) ) : ?>
						<tr>
							<td colspan="4"><?php echo esc_html__( 'No unmatched rows.', 'order-machine' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $import_unmatched as $import_row ) : ?>
							<?php
							$import_sku      = isset( $import_row['sku'] ) ? (string) $import_row['sku'] : '';
							$import_external = isset( $import_row['external_listing_id'] ) ? (string) $import_row['external_listing_id'] : '';
							?>
							<tr>
								<td><?php echo esc_html( (string) (int) ( $import_row['row'] ?? 0 ) ); ?></td>
								<td>
									<?php if ( '' !== $import_sku ) : ?>
										<code><?php echo esc_html( $import_sku ); ?></code>
									<?php else : ?>
										<?php echo esc_html( '—' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( '' !== $import_external ) : ?>
										<code><?php echo esc_html( $import_external ); ?></code>
									<?php else : ?>
										<?php echo esc_html( '—' ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( isset( $import_row['message'] ) ? (string) $import_row['message'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</details>

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
