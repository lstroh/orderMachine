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
