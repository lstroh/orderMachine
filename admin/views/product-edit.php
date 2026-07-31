<?php
/**
 * Product create/edit admin view.
 *
 * @package OrderMachine
 *
 * @var object|null $product Product row or null when creating.
 * @var bool        $is_new  True when creating.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_new             = ! empty( $is_new );
$product            = isset( $product ) ? $product : null;
$workflow_templates = SOM_Products::list_workflow_templates( $product ? (int) $product->workflow_template_id : 0 );
$material_options   = SOM_Materials::list_active();
$recipe_rows        = ( $product && ! empty( $product->recipe ) ) ? $product->recipe : array();
$listings           = ( $product && ! empty( $product->listings ) ) ? $product->listings : array();

$blank_rows = max( 2, 3 - count( $recipe_rows ) );
?>
<div class="wrap som-catalog-wrap">
	<h1>
		<?php
		echo $is_new
			? esc_html__( 'Add product', 'order-machine' )
			: esc_html__( 'Edit product', 'order-machine' );
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( SOM_Products::list_url() ); ?>">&larr; <?php echo esc_html__( 'Back to products', 'order-machine' ); ?></a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-products' ) ); ?>" class="som-product-form">
		<?php wp_nonce_field( 'som_save_product', 'som_product_nonce' ); ?>
		<input type="hidden" name="som_save_product" value="1" />
		<?php if ( ! $is_new && $product ) : ?>
			<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) (int) $product->id ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_product_name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="som_product_name" name="som_product_name" value="<?php echo esc_attr( $product ? (string) $product->name : '' ); ?>" required />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_product_sku"><?php echo esc_html__( 'SKU', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="som_product_sku" name="som_product_sku" value="<?php echo esc_attr( $product && $product->sku ? (string) $product->sku : '' ); ?>" />
					<p class="description"><?php echo esc_html__( 'Optional internal reference.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_workflow_template_id"><?php echo esc_html__( 'Workflow template', 'order-machine' ); ?></label></th>
				<td>
					<select id="som_workflow_template_id" name="som_workflow_template_id">
						<option value=""><?php echo esc_html__( '— Not assigned —', 'order-machine' ); ?></option>
						<?php foreach ( $workflow_templates as $template ) : ?>
							<option value="<?php echo esc_attr( (string) (int) $template->id ); ?>" <?php selected( $product ? (int) $product->workflow_template_id : 0, (int) $template->id ); ?>>
								<?php
								echo esc_html( (string) $template->name );
								if ( empty( $template->is_active ) ) {
									echo ' (' . esc_html__( 'inactive', 'order-machine' ) . ')';
								}
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ( empty( $workflow_templates ) ) : ?>
						<p class="description">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: workflows admin link */
									__( 'Create a template under %s, then assign it here.', 'order-machine' ),
									'<a href="' . esc_url( SOM_Workflows::list_url() ) . '">' . esc_html__( 'Workflows', 'order-machine' ) . '</a>'
								)
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="som_product_is_active" value="1" <?php checked( ! $product || (int) $product->is_active ); ?> />
						<?php echo esc_html__( 'Active', 'order-machine' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'Inactive products are hidden from new assignments but kept for order history.', 'order-machine' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'Material recipe', 'order-machine' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Materials consumed per unit sold. Each material can only appear once.', 'order-machine' ); ?></p>

		<table class="widefat striped som-recipe-table" id="som-recipe-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Material', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Qty per unit', 'order-machine' ); ?></th>
					<th scope="col" class="som-recipe-actions-col"></th>
				</tr>
			</thead>
			<tbody id="som-recipe-rows">
				<?php
				$index = 0;
				foreach ( $recipe_rows as $row ) :
					++$index;
					?>
					<tr class="som-recipe-row">
						<td>
							<select name="som_recipe_material[<?php echo esc_attr( (string) $index ); ?>]">
								<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
								<?php foreach ( $material_options as $material ) : ?>
									<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>" <?php selected( (int) $row->material_id, (int) $material->id ); ?>>
										<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
									</option>
								<?php endforeach; ?>
								<?php if ( empty( $row->material_is_active ) ) : ?>
									<option value="<?php echo esc_attr( (string) (int) $row->material_id ); ?>" selected>
										<?php echo esc_html( (string) $row->material_name ); ?> (<?php echo esc_html__( 'inactive', 'order-machine' ); ?>)
									</option>
								<?php endif; ?>
							</select>
						</td>
						<td>
							<input type="number" step="0.01" min="0.01" name="som_recipe_qty[<?php echo esc_attr( (string) $index ); ?>]" value="<?php echo esc_attr( (string) $row->quantity_per_unit ); ?>" class="small-text" />
						</td>
						<td class="som-recipe-actions-col">
							<button type="button" class="button-link som-recipe-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
						</td>
					</tr>
				<?php endforeach; ?>

				<?php for ( $i = 0; $i < $blank_rows; $i++ ) : ?>
					<?php ++$index; ?>
					<tr class="som-recipe-row">
						<td>
							<select name="som_recipe_material[<?php echo esc_attr( (string) $index ); ?>]">
								<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
								<?php foreach ( $material_options as $material ) : ?>
									<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>">
										<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<input type="number" step="0.01" min="0.01" name="som_recipe_qty[<?php echo esc_attr( (string) $index ); ?>]" value="" class="small-text" />
						</td>
						<td class="som-recipe-actions-col">
							<button type="button" class="button-link som-recipe-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
						</td>
					</tr>
				<?php endfor; ?>
			</tbody>
		</table>

		<p>
			<button type="button" class="button" id="som-recipe-add-row"><?php echo esc_html__( 'Add material row', 'order-machine' ); ?></button>
		</p>

		<?php if ( empty( $material_options ) ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: materials admin link */
						__( 'Add materials first under %s before building a recipe.', 'order-machine' ),
						'<a href="' . esc_url( SOM_Materials::list_url() ) . '">' . esc_html__( 'Materials', 'order-machine' ) . '</a>'
					)
				);
				?>
			</p></div>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save product', 'order-machine' ); ?></button>
		</p>
	</form>

	<?php if ( ! $is_new && ! empty( $listings ) ) : ?>
		<h2><?php echo esc_html__( 'Linked listings', 'order-machine' ); ?></h2>
		<p class="description">
			<a href="<?php echo esc_url( SOM_Listings::list_url() ); ?>"><?php echo esc_html__( 'Manage listings', 'order-machine' ); ?></a>
		</p>
		<table class="widefat striped som-listings-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'External listing ID', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Price', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Qty available', 'order-machine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $listings as $listing ) : ?>
					<tr>
						<td>
							<span class="som-badge som-badge-channel"><?php echo esc_html( (string) $listing->channel_name ); ?></span>
						</td>
						<td>
							<a href="<?php echo esc_url( SOM_Listings::detail_url( (int) $listing->id ) ); ?>">
								<code><?php echo esc_html( (string) $listing->external_listing_id ); ?></code>
							</a>
						</td>
						<td><?php echo esc_html( number_format_i18n( (float) $listing->price, 2 ) ); ?></td>
						<td><?php echo esc_html( (string) (int) $listing->quantity_available ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php elseif ( ! $is_new ) : ?>
		<h2><?php echo esc_html__( 'Linked listings', 'order-machine' ); ?></h2>
		<p class="som-muted">
			<?php echo esc_html__( 'No listings linked to this product yet.', 'order-machine' ); ?>
			<a href="<?php echo esc_url( SOM_Listings::detail_url( 'new' ) ); ?>"><?php echo esc_html__( 'Add listing map', 'order-machine' ); ?></a>
		</p>
	<?php endif; ?>
</div>

<template id="som-recipe-row-template">
	<tr class="som-recipe-row">
		<td>
			<select name="som_recipe_material[__INDEX__]">
				<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
				<?php foreach ( $material_options as $material ) : ?>
					<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>">
						<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<input type="number" step="0.01" min="0.01" name="som_recipe_qty[__INDEX__]" value="" class="small-text" />
		</td>
		<td class="som-recipe-actions-col">
			<button type="button" class="button-link som-recipe-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
		</td>
	</tr>
</template>
