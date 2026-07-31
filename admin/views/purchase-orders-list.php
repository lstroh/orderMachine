<?php
/**
 * Purchase orders list admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$status      = isset( $_GET['som_status'] ) ? sanitize_key( wp_unslash( $_GET['som_status'] ) ) : '';
$supplier_id = isset( $_GET['supplier_id'] ) ? (int) $_GET['supplier_id'] : 0;
$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result = SOM_Purchase_Orders::query(
	array(
		'status'      => $status,
		'supplier_id' => $supplier_id,
		's'           => $search,
		'paged'       => $paged,
	)
);
$orders    = $result['orders'];
$total     = $result['total'];
$pages     = $result['pages'];
$paged     = $result['paged'];
$suppliers = SOM_Suppliers::list_all();

$status_options = array_merge(
	array( '' => __( 'All statuses', 'order-machine' ) ),
	SOM_Purchase_Orders::status_labels()
);
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Purchase orders', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Purchase_Orders::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add purchase order', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<form method="get" class="som-catalog-filters">
		<input type="hidden" name="page" value="som-purchase-orders" />
		<label class="screen-reader-text" for="som-po-search"><?php echo esc_html__( 'Search purchase orders', 'order-machine' ); ?></label>
		<input type="search" id="som-po-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search ID, supplier, notes…', 'order-machine' ); ?>" />
		<select name="som_status">
			<?php foreach ( $status_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $status, (string) $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="supplier_id">
			<option value="0"><?php echo esc_html__( 'All suppliers', 'order-machine' ); ?></option>
			<?php foreach ( $suppliers as $supplier ) : ?>
				<option value="<?php echo esc_attr( (string) (int) $supplier->id ); ?>" <?php selected( $supplier_id, (int) $supplier->id ); ?>>
					<?php echo esc_html( (string) $supplier->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'order-machine' ); ?></button>
	</form>

	<p class="som-catalog-count">
		<?php
		printf(
			/* translators: %d: purchase order count */
			esc_html( _n( '%d purchase order', '%d purchase orders', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped som-catalog-table">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'ID', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Supplier', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Order date', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Received', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Shipping', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $orders ) ) : ?>
				<tr>
					<td colspan="6"><?php echo esc_html__( 'No purchase orders yet.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $orders as $order ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( SOM_Purchase_Orders::detail_url( (int) $order->id ) ); ?>">
								#<?php echo esc_html( (string) (int) $order->id ); ?>
							</a>
						</td>
						<td><?php echo esc_html( $order->supplier_name ? (string) $order->supplier_name : '—' ); ?></td>
						<td><?php echo esc_html( (string) $order->order_date ); ?></td>
						<td><?php echo esc_html( $order->received_date ? (string) $order->received_date : '—' ); ?></td>
						<td>
							<span class="som-badge som-badge-po-<?php echo esc_attr( sanitize_html_class( (string) $order->status ) ); ?>">
								<?php echo esc_html( SOM_Purchase_Orders::status_label( (string) $order->status ) ); ?>
							</span>
						</td>
						<td>£<?php echo esc_html( number_format_i18n( (float) $order->shipping_cost, 2 ) ); ?></td>
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
