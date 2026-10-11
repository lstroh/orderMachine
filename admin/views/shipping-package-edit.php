<?php
/**
 * Shipping package create/edit admin view.
 *
 * @package OrderMachine
 *
 * @var object|null $package Package row or null when creating.
 * @var bool        $is_new  True when creating.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_new  = ! empty( $is_new );
$package = isset( $package ) ? $package : null;
$refs    = ( ! $is_new && $package ) ? SOM_Shipping_Packages::count_product_refs( (int) $package->id ) : 0;
$material_rows    = ( ! $is_new && $package ) ? SOM_Shipping_Packages::get_materials( (int) $package->id ) : array();
$material_options = SOM_Materials::list_active();
$blank_rows       = max( 2, 3 - count( $material_rows ) );
?>
<div class="wrap som-catalog-wrap">
	<h1>
		<?php
		echo $is_new
			? esc_html__( 'Add shipping package', 'order-machine' )
			: esc_html__( 'Edit shipping package', 'order-machine' );
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( SOM_Shipping_Packages::list_url() ); ?>">&larr; <?php echo esc_html__( 'Back to packages', 'order-machine' ); ?></a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-shipping-packages' ) ); ?>" class="som-package-form">
		<?php wp_nonce_field( 'som_save_shipping_package', 'som_package_nonce' ); ?>
		<input type="hidden" name="som_save_shipping_package" value="1" />
		<?php if ( ! $is_new && $package ) : ?>
			<input type="hidden" name="package_id" value="<?php echo esc_attr( (string) (int) $package->id ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_package_name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="som_package_name" name="som_package_name" value="<?php echo esc_attr( $package ? (string) $package->name : '' ); ?>" required maxlength="100" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Outer dimensions', 'order-machine' ); ?></th>
				<td>
					<label>
						<?php echo esc_html__( 'L', 'order-machine' ); ?>
						<input type="number" class="small-text" name="som_package_length_mm" min="1" step="1" value="<?php echo esc_attr( $package ? (string) (int) $package->length_mm : '' ); ?>" required />
					</label>
					<label>
						<?php echo esc_html__( 'W', 'order-machine' ); ?>
						<input type="number" class="small-text" name="som_package_width_mm" min="1" step="1" value="<?php echo esc_attr( $package ? (string) (int) $package->width_mm : '' ); ?>" required />
					</label>
					<label>
						<?php echo esc_html__( 'H', 'order-machine' ); ?>
						<input type="number" class="small-text" name="som_package_height_mm" min="0" step="1" value="<?php echo esc_attr( $package ? (string) (int) $package->height_mm : '0' ); ?>" required />
					</label>
					<span class="som-muted"><?php echo esc_html__( 'mm (height may be 0 for flat envelopes; 10 mm = 1 cm)', 'order-machine' ); ?></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_package_tare"><?php echo esc_html__( 'Tare weight', 'order-machine' ); ?></label></th>
				<td>
					<input type="number" class="small-text" id="som_package_tare" name="som_package_tare_g" min="0" step="0.1" value="<?php echo esc_attr( $package ? (string) $package->tare_weight_grams : '0' ); ?>" />
					<span class="som-muted"><?php echo esc_html__( 'grams (empty package)', 'order-machine' ); ?></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Flags', 'order-machine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="som_package_is_active" value="1" <?php checked( ! $package || (int) $package->is_active ); ?> />
						<?php echo esc_html__( 'Active', 'order-machine' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="som_package_is_default" value="1" <?php checked( $package && (int) $package->is_default ); ?> />
						<?php echo esc_html__( 'Default for new products', 'order-machine' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'Only one package can be the default; saving this as default clears the previous one.', 'order-machine' ); ?></p>
				</td>
			</tr>
		</table>

		<?php if ( ! $is_new && $package ) : ?>
			<h2><?php echo esc_html__( 'Package materials', 'order-machine' ); ?></h2>
			<p class="description">
				<?php echo esc_html__( 'Materials reserved once when this package is selected on an order (thank-you card, envelope, box, …). Each material can only appear once.', 'order-machine' ); ?>
			</p>

			<table class="widefat striped som-recipe-table" id="som-recipe-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Material', 'order-machine' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Qty per package', 'order-machine' ); ?></th>
						<th scope="col" class="som-recipe-actions-col"></th>
					</tr>
				</thead>
				<tbody id="som-recipe-rows">
					<?php
					$index = 0;
					foreach ( $material_rows as $row ) :
						++$index;
						?>
						<tr class="som-recipe-row">
							<td>
								<select name="som_pkg_material[<?php echo esc_attr( (string) $index ); ?>]">
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
								<input type="number" step="0.01" min="0.01" name="som_pkg_qty[<?php echo esc_attr( (string) $index ); ?>]" value="<?php echo esc_attr( (string) $row->quantity_per_package ); ?>" class="small-text" />
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
								<select name="som_pkg_material[<?php echo esc_attr( (string) $index ); ?>]">
									<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
									<?php foreach ( $material_options as $material ) : ?>
										<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>">
											<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<input type="number" step="0.01" min="0.01" name="som_pkg_qty[<?php echo esc_attr( (string) $index ); ?>]" value="" class="small-text" />
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
							__( 'Add materials first under %s before building a package recipe.', 'order-machine' ),
							'<a href="' . esc_url( SOM_Materials::list_url() ) . '">' . esc_html__( 'Materials', 'order-machine' ) . '</a>'
						)
					);
					?>
				</p></div>
			<?php endif; ?>

			<template id="som-recipe-row-template">
				<tr class="som-recipe-row">
					<td>
						<select name="som_pkg_material[__INDEX__]">
							<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
							<?php foreach ( $material_options as $material ) : ?>
								<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>">
									<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<input type="number" step="0.01" min="0.01" name="som_pkg_qty[__INDEX__]" value="" class="small-text" />
					</td>
					<td class="som-recipe-actions-col">
						<button type="button" class="button-link som-recipe-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
					</td>
				</tr>
			</template>
		<?php else : ?>
			<p class="description"><?php echo esc_html__( 'Save the package first, then add packaging materials (thank-you, envelope, …).', 'order-machine' ); ?></p>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save package', 'order-machine' ); ?></button>
		</p>
	</form>

	<?php if ( ! $is_new && $package ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-shipping-packages' ) ); ?>" class="som-package-delete-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this package permanently?', 'order-machine' ) ); ?>');">
			<?php wp_nonce_field( 'som_delete_shipping_package', 'som_package_nonce' ); ?>
			<input type="hidden" name="som_delete_shipping_package" value="1" />
			<input type="hidden" name="package_id" value="<?php echo esc_attr( (string) (int) $package->id ); ?>" />
			<p class="description">
				<?php
				if ( $refs > 0 ) {
					printf(
						/* translators: %d: product count */
						esc_html( _n( '%d product references this package — delete is blocked; deactivate instead.', '%d products reference this package — delete is blocked; deactivate instead.', $refs, 'order-machine' ) ),
						(int) $refs
					);
				} else {
					echo esc_html__( 'Unused packages can be deleted. Prefer deactivating if you may reuse the name.', 'order-machine' );
				}
				?>
			</p>
			<?php if ( 0 === $refs ) : ?>
				<button type="submit" class="button button-link-delete"><?php echo esc_html__( 'Delete package', 'order-machine' ); ?></button>
			<?php endif; ?>
		</form>
	<?php endif; ?>
</div>
