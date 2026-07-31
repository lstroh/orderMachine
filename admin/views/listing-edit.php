<?php
/**
 * Listing create / edit admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_new          = ! empty( $is_new );
$listing         = $is_new ? null : $listing;
$product_options = SOM_Listings::product_options();
$inventory       = $listing && isset( $listing->inventory )
	? $listing->inventory
	: array(
		'mode'       => 'flat',
		'sku'        => '',
		'variations' => array(),
	);
$mode = isset( $inventory['mode'] ) ? (string) $inventory['mode'] : 'flat';
$sku  = isset( $inventory['sku'] ) ? (string) $inventory['sku'] : '';
$vars = ! empty( $inventory['variations'] ) && is_array( $inventory['variations'] ) ? $inventory['variations'] : array();

if ( empty( $vars ) && 'variations' === $mode ) {
	$vars = array(
		array(
			'sku'      => '',
			'quantity' => 0,
			'options'  => array(),
		),
	);
}
?>
<div class="wrap som-catalog-wrap som-listing-edit">
	<h1>
		<?php
		echo $is_new
			? esc_html__( 'Add listing map', 'order-machine' )
			: esc_html__( 'Edit listing', 'order-machine' );
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( SOM_Listings::list_url() ); ?>">&larr; <?php echo esc_html__( 'Back to listings', 'order-machine' ); ?></a>
	</p>

	<?php if ( ! $is_new && $listing ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-listings&listing_id=' . (int) $listing->id ) ); ?>" class="som-listing-actions">
			<?php wp_nonce_field( 'som_listing_channel', 'som_listing_nonce' ); ?>
			<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) (int) $listing->id ); ?>" />
			<button type="submit" name="som_refresh_listing" value="1" class="button">
				<?php echo esc_html__( 'Refresh from channel', 'order-machine' ); ?>
			</button>
			<button type="submit" name="som_push_listing" value="1" class="button button-primary">
				<?php echo esc_html__( 'Push to channel', 'order-machine' ); ?>
			</button>
			<p class="description">
				<?php echo esc_html__( 'Refresh pulls live (or fixture) state into the cache. Push sends the saved local price, description, and quantities.', 'order-machine' ); ?>
			</p>
		</form>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-listings' . ( $is_new || ! $listing ? '' : '&listing_id=' . (int) $listing->id ) ) ); ?>" class="som-listing-form">
		<?php wp_nonce_field( 'som_save_listing', 'som_listing_nonce' ); ?>
		<input type="hidden" name="som_save_listing" value="1" />
		<?php if ( ! $is_new && $listing ) : ?>
			<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) (int) $listing->id ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_product_id"><?php echo esc_html__( 'Product', 'order-machine' ); ?></label></th>
				<td>
					<select name="product_id" id="som_product_id" required>
						<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
						<?php foreach ( $product_options as $product ) : ?>
							<option value="<?php echo esc_attr( (string) (int) $product->id ); ?>" <?php selected( $listing ? (int) $listing->product_id : 0, (int) $product->id ); ?>>
								<?php
								$label = (string) $product->name;
								if ( ! empty( $product->sku ) ) {
									$label .= ' (' . $product->sku . ')';
								}
								echo esc_html( $label );
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<?php if ( $is_new ) : ?>
				<tr>
					<th scope="row"><label for="som_channel_slug"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></label></th>
					<td>
						<select name="channel_slug" id="som_channel_slug" required>
							<option value="ebay"><?php echo esc_html__( 'eBay', 'order-machine' ); ?></option>
							<option value="etsy"><?php echo esc_html__( 'Etsy', 'order-machine' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="som_external_listing_id"><?php echo esc_html__( 'External listing ID', 'order-machine' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="external_listing_id" id="som_external_listing_id" required />
						<p class="description"><?php echo esc_html__( 'eBay item ID or SKU key; Etsy listing ID.', 'order-machine' ); ?></p>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></th>
					<td>
						<span class="som-badge som-badge-channel"><?php echo esc_html( (string) $listing->channel_name ); ?></span>
						<code><?php echo esc_html( (string) $listing->external_listing_id ); ?></code>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="som_listing_title"><?php echo esc_html__( 'Title', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="large-text" name="title" id="som_listing_title" value="<?php echo esc_attr( $listing ? (string) $listing->title : '' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_listing_description"><?php echo esc_html__( 'Description', 'order-machine' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="6" name="description" id="som_listing_description"><?php echo esc_textarea( $listing ? (string) $listing->description : '' ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_listing_price"><?php echo esc_html__( 'Price', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" step="0.01" min="0" class="small-text" name="price" id="som_listing_price" value="<?php echo esc_attr( $listing ? (string) $listing->price : '0' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_inventory_mode"><?php echo esc_html__( 'Inventory mode', 'order-machine' ); ?></label></th>
				<td>
					<select name="inventory_mode" id="som_inventory_mode">
						<option value="flat" <?php selected( $mode, 'flat' ); ?>><?php echo esc_html__( 'Flat quantity', 'order-machine' ); ?></option>
						<option value="variations" <?php selected( $mode, 'variations' ); ?>><?php echo esc_html__( 'Variations', 'order-machine' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="som-flat-qty-row">
				<th scope="row"><label for="som_quantity_available"><?php echo esc_html__( 'Quantity available', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" class="small-text" name="quantity_available" id="som_quantity_available" value="<?php echo esc_attr( $listing ? (string) (int) $listing->quantity_available : '0' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_primary_sku"><?php echo esc_html__( 'Primary SKU', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="primary_sku" id="som_primary_sku" value="<?php echo esc_attr( $sku ); ?>" />
					<p class="description"><?php echo esc_html__( 'Required for eBay Inventory API (flat listings). Variation rows can have their own SKUs.', 'order-machine' ); ?></p>
				</td>
			</tr>
		</table>

		<div class="som-variations-panel" <?php echo 'variations' === $mode ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html__( 'Variations', 'order-machine' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Options format: Colour=Navy; Size=Large', 'order-machine' ); ?></p>
			<table class="widefat striped som-variations-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'SKU', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Qty', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Options', 'order-machine' ); ?></th>
						<th scope="col" class="som-recipe-actions-col"></th>
					</tr>
				</thead>
				<tbody id="som-variations-body">
					<?php foreach ( $vars as $i => $var ) : ?>
						<tr class="som-variation-row">
							<td>
								<input type="text" name="som_var_sku[]" value="<?php echo esc_attr( isset( $var['sku'] ) ? (string) $var['sku'] : '' ); ?>" class="regular-text" />
							</td>
							<td>
								<input type="number" min="0" step="1" name="som_var_qty[]" value="<?php echo esc_attr( isset( $var['quantity'] ) ? (string) (int) $var['quantity'] : '0' ); ?>" class="small-text" />
							</td>
							<td>
								<input type="text" name="som_var_options[]" value="<?php echo esc_attr( SOM_Listings::format_options_string( isset( $var['options'] ) && is_array( $var['options'] ) ? $var['options'] : array() ) ); ?>" class="large-text" />
							</td>
							<td class="som-recipe-actions-col">
								<button type="button" class="button-link som-variation-remove" aria-label="<?php echo esc_attr__( 'Remove variation', 'order-machine' ); ?>">&times;</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="som-variation-add"><?php echo esc_html__( 'Add variation', 'order-machine' ); ?></button>
			</p>
		</div>

		<?php submit_button( $is_new ? __( 'Create listing map', 'order-machine' ) : __( 'Save locally', 'order-machine' ) ); ?>
	</form>
</div>

<template id="som-variation-row-template">
	<tr class="som-variation-row">
		<td><input type="text" name="som_var_sku[]" value="" class="regular-text" /></td>
		<td><input type="number" min="0" step="1" name="som_var_qty[]" value="0" class="small-text" /></td>
		<td><input type="text" name="som_var_options[]" value="" class="large-text" /></td>
		<td class="som-recipe-actions-col">
			<button type="button" class="button-link som-variation-remove" aria-label="<?php echo esc_attr__( 'Remove variation', 'order-machine' ); ?>">&times;</button>
		</td>
	</tr>
</template>
