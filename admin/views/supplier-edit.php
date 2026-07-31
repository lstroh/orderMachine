<?php
/**
 * Supplier create/edit admin view.
 *
 * @package OrderMachine
 *
 * @var object|null $supplier Supplier row or null when creating.
 * @var bool        $is_new   True when creating.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_new   = ! empty( $is_new );
$supplier = isset( $supplier ) ? $supplier : null;
?>
<div class="wrap som-catalog-wrap">
	<h1>
		<?php
		echo $is_new
			? esc_html__( 'Add supplier', 'order-machine' )
			: esc_html__( 'Edit supplier', 'order-machine' );
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( SOM_Suppliers::list_url() ); ?>">&larr; <?php echo esc_html__( 'Back to suppliers', 'order-machine' ); ?></a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-suppliers' ) ); ?>" class="som-supplier-form">
		<?php wp_nonce_field( 'som_save_supplier', 'som_supplier_nonce' ); ?>
		<input type="hidden" name="som_save_supplier" value="1" />
		<?php if ( ! $is_new && $supplier ) : ?>
			<input type="hidden" name="supplier_id" value="<?php echo esc_attr( (string) (int) $supplier->id ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_supplier_name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="som_supplier_name" name="som_supplier_name" value="<?php echo esc_attr( $supplier ? (string) $supplier->name : '' ); ?>" required />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_supplier_website"><?php echo esc_html__( 'Website', 'order-machine' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="som_supplier_website" name="som_supplier_website" value="<?php echo esc_attr( $supplier && $supplier->website ? (string) $supplier->website : '' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_supplier_contact"><?php echo esc_html__( 'Contact info', 'order-machine' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="3" id="som_supplier_contact" name="som_supplier_contact"><?php echo esc_textarea( $supplier && $supplier->contact_info ? (string) $supplier->contact_info : '' ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'Email, phone, or other contact details.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_supplier_notes"><?php echo esc_html__( 'Notes', 'order-machine' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="4" id="som_supplier_notes" name="som_supplier_notes"><?php echo esc_textarea( $supplier && $supplier->notes ? (string) $supplier->notes : '' ); ?></textarea>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save supplier', 'order-machine' ); ?></button>
		</p>
	</form>
</div>
