<?php
/**
 * Orders list admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$status       = isset( $_GET['som_status'] ) ? sanitize_key( wp_unslash( $_GET['som_status'] ) ) : '';
$current_step = isset( $_GET['som_current_step'] ) ? sanitize_text_field( wp_unslash( $_GET['som_current_step'] ) ) : '';
$channel      = isset( $_GET['som_channel'] ) ? sanitize_key( wp_unslash( $_GET['som_channel'] ) ) : '';
$date_from    = isset( $_GET['som_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['som_date_from'] ) ) : '';
$date_to      = isset( $_GET['som_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['som_date_to'] ) ) : '';
$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged        = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result = SOM_Orders::query(
	array(
		'status'       => $status,
		'current_step' => $current_step,
		'channel'      => $channel,
		'date_from'    => $date_from,
		'date_to'      => $date_to,
		's'            => $search,
		'paged'        => $paged,
	)
);

$orders     = $result['orders'];
$total      = $result['total'];
$pages      = $result['pages'];
$paged      = $result['paged'];
$step_names = SOM_Orders::step_name_options();

$products_for_test = SOM_Products::query(
	array(
		'status'   => 'active',
		'per_page' => 500,
		'paged'    => 1,
	)
);
$test_products = isset( $products_for_test['products'] ) && is_array( $products_for_test['products'] )
	? $products_for_test['products']
	: array();

$add_order_product_options = '';
foreach ( $test_products as $product ) {
	if ( ! empty( $product->is_internal ) ) {
		continue;
	}
	$add_order_product_options .= '<option value="' . esc_attr( (string) $product->id ) . '">' . esc_html(
		sprintf(
			/* translators: 1: product name, 2: SKU */
			__( '%1$s (%2$s)', 'order-machine' ),
			(string) $product->name,
			(string) $product->sku
		)
	) . '</option>';
}
$known_channels     = SOM_Channels::known();
$add_order_channels = array(
	'ebay'   => isset( $known_channels['ebay'] ) ? $known_channels['ebay'] : 'eBay',
	'amazon' => isset( $known_channels['amazon'] ) ? $known_channels['amazon'] : 'Amazon',
);

$status_options = array(
	''               => __( 'All statuses', 'order-machine' ),
	'open'           => __( 'Open', 'order-machine' ),
	'complete'       => __( 'Complete', 'order-machine' ),
	'needs_mapping'  => __( 'Needs mapping', 'order-machine' ),
	'needs_workflow' => __( 'Needs workflow', 'order-machine' ),
	'cancelled'      => __( 'Cancelled', 'order-machine' ),
);
?>
<div class="wrap som-orders-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Orders', 'order-machine' ); ?></h1>
	<hr class="wp-header-end" />

	<details class="som-create-test-order">
		<summary><?php echo esc_html__( 'Create test order', 'order-machine' ); ?></summary>
		<p class="description">
			<?php echo esc_html__( 'Creates an External-channel order (same path as REST / Sync create): workflow assignment, stock reservation, and budget funding when applicable.', 'order-machine' ); ?>
		</p>
		<?php if ( ! $test_products ) : ?>
			<p class="description"><?php echo esc_html__( 'Add an active product first, then create a test order.', 'order-machine' ); ?></p>
		<?php else : ?>
			<?php
			$product_option_html = '';
			foreach ( $test_products as $product ) {
				$product_option_html .= '<option value="' . esc_attr( (string) $product->id ) . '">' . esc_html(
					sprintf(
						/* translators: 1: product name, 2: SKU */
						__( '%1$s (%2$s)', 'order-machine' ),
						(string) $product->name,
						(string) $product->sku
					)
				) . '</option>';
			}
			?>
			<form method="post" action="<?php echo esc_url( SOM_Orders::list_url() ); ?>" class="som-create-test-order-form">
				<?php wp_nonce_field( 'som_create_test_order', 'som_order_nonce' ); ?>

				<label for="som-test-buyer-name"><?php echo esc_html__( 'Buyer', 'order-machine' ); ?></label>
				<input type="text" name="som_test_buyer_name" id="som-test-buyer-name" value="" placeholder="<?php echo esc_attr__( 'Test Buyer', 'order-machine' ); ?>" />

				<table class="widefat striped som-test-order-lines" id="som-test-order-lines">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Product', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Qty', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Unit price', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Personalisation', 'order-machine' ); ?></th>
							<th class="som-test-order-actions-col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'order-machine' ); ?></span></th>
						</tr>
					</thead>
					<tbody id="som-test-order-rows">
						<tr class="som-test-order-row" data-index="0">
							<td>
								<select name="som_test_lines[0][product_id]" required>
									<option value=""><?php echo esc_html__( 'Select product…', 'order-machine' ); ?></option>
									<?php echo $product_option_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr/esc_html above. ?>
								</select>
							</td>
							<td>
								<input type="number" name="som_test_lines[0][quantity]" value="1" min="1" step="1" />
							</td>
							<td>
								<input type="number" name="som_test_lines[0][unit_price]" value="" min="0" step="0.01" placeholder="0.00" />
							</td>
							<td>
								<input type="text" name="som_test_lines[0][personalisation]" value="" placeholder="<?php echo esc_attr__( 'Optional', 'order-machine' ); ?>" />
							</td>
							<td>
								<button type="button" class="button-link-delete som-test-order-remove"><?php echo esc_html__( 'Remove', 'order-machine' ); ?></button>
							</td>
						</tr>
					</tbody>
				</table>
				<p>
					<button type="button" class="button" id="som-test-order-add-row"><?php echo esc_html__( 'Add line', 'order-machine' ); ?></button>
					<span class="description"><?php echo esc_html__( 'Up to 5 lines. Different products and quantities supported.', 'order-machine' ); ?></span>
				</p>

				<template id="som-test-order-row-template">
					<tr class="som-test-order-row" data-index="__INDEX__">
						<td>
							<select name="som_test_lines[__INDEX__][product_id]">
								<option value=""><?php echo esc_html__( 'Select product…', 'order-machine' ); ?></option>
								<?php echo $product_option_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr/esc_html above. ?>
							</select>
						</td>
						<td>
							<input type="number" name="som_test_lines[__INDEX__][quantity]" value="1" min="1" step="1" />
						</td>
						<td>
							<input type="number" name="som_test_lines[__INDEX__][unit_price]" value="" min="0" step="0.01" placeholder="0.00" />
						</td>
						<td>
							<input type="text" name="som_test_lines[__INDEX__][personalisation]" value="" placeholder="<?php echo esc_attr__( 'Optional', 'order-machine' ); ?>" />
						</td>
						<td>
							<button type="button" class="button-link-delete som-test-order-remove"><?php echo esc_html__( 'Remove', 'order-machine' ); ?></button>
						</td>
					</tr>
				</template>

				<?php submit_button( __( 'Create test order', 'order-machine' ), 'secondary', 'som_create_test_order', false ); ?>
			</form>
		<?php endif; ?>
	</details>

	<details class="som-add-order">
		<summary><?php echo esc_html__( 'Add order', 'order-machine' ); ?></summary>
		<p class="description">
			<?php echo esc_html__( 'eBay or Amazon order, typed in by hand. Same create path as Sync now: a matched line gets a workflow, stock reservation, and budget funding. An unmatched line still saves. The same channel and order number is rejected.', 'order-machine' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( SOM_Orders::list_url() ); ?>" class="som-add-order-form">
			<?php wp_nonce_field( 'som_add_order', 'som_order_nonce' ); ?>

			<div class="som-add-order-fields">
				<p>
					<label for="som-add-channel"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></label>
					<select name="som_add_channel" id="som-add-channel" required>
						<?php foreach ( $add_order_channels as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="som-add-order-number"><?php echo esc_html__( 'Order number', 'order-machine' ); ?></label>
					<input type="text" name="som_add_order_number" id="som-add-order-number" value="" maxlength="100" required />
				</p>
				<p>
					<label for="som-add-order-date"><?php echo esc_html__( 'Order date', 'order-machine' ); ?></label>
					<input type="date" name="som_add_order_date" id="som-add-order-date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
				</p>
				<p>
					<label for="som-add-buyer-name"><?php echo esc_html__( 'Buyer name', 'order-machine' ); ?></label>
					<input type="text" name="som_add_buyer_name" id="som-add-buyer-name" value="" />
				</p>
				<p>
					<label for="som-add-ship-name"><?php echo esc_html__( 'Ship-to name', 'order-machine' ); ?></label>
					<input type="text" name="som_add_ship_name" id="som-add-ship-name" value="" />
				</p>
				<p>
					<label for="som-add-address-1"><?php echo esc_html__( 'Address 1', 'order-machine' ); ?></label>
					<input type="text" name="som_add_address_1" id="som-add-address-1" value="" />
				</p>
				<p>
					<label for="som-add-address-2"><?php echo esc_html__( 'Address 2', 'order-machine' ); ?></label>
					<input type="text" name="som_add_address_2" id="som-add-address-2" value="" />
				</p>
				<p>
					<label for="som-add-city"><?php echo esc_html__( 'City', 'order-machine' ); ?></label>
					<input type="text" name="som_add_city" id="som-add-city" value="" />
				</p>
				<p>
					<label for="som-add-county"><?php echo esc_html__( 'County', 'order-machine' ); ?></label>
					<input type="text" name="som_add_county" id="som-add-county" value="" />
				</p>
				<p>
					<label for="som-add-postcode"><?php echo esc_html__( 'Postcode', 'order-machine' ); ?></label>
					<input type="text" name="som_add_postcode" id="som-add-postcode" value="" />
				</p>
				<p>
					<label for="som-add-country"><?php echo esc_html__( 'Country', 'order-machine' ); ?></label>
					<input type="text" name="som_add_country" id="som-add-country" value="GB" maxlength="100" />
				</p>
			</div>

			<div class="som-add-order-lines-wrap">
				<table class="widefat striped som-add-order-lines" id="som-add-order-lines">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Product', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Item number', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'SKU', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Qty', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Unit price', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'House number', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Street name', 'order-machine' ); ?></th>
							<th><?php echo esc_html__( 'Style', 'order-machine' ); ?></th>
							<th class="som-add-order-actions-col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'order-machine' ); ?></span></th>
						</tr>
					</thead>
					<tbody id="som-add-order-rows">
						<tr class="som-add-order-row" data-index="0">
							<td>
								<select name="som_add_lines[0][product_id]">
									<option value=""><?php echo esc_html__( 'Select product…', 'order-machine' ); ?></option>
									<?php echo $add_order_product_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr/esc_html above. ?>
								</select>
							</td>
							<td>
								<input type="text" name="som_add_lines[0][item_number]" value="" maxlength="100" />
							</td>
							<td>
								<input type="text" name="som_add_lines[0][sku]" value="" maxlength="100" />
							</td>
							<td>
								<input type="number" name="som_add_lines[0][quantity]" value="1" min="1" step="1" />
							</td>
							<td>
								<input type="number" name="som_add_lines[0][unit_price]" value="" min="0" step="0.01" placeholder="0.00" />
							</td>
							<td>
								<input type="text" name="som_add_lines[0][house_number]" value="" maxlength="64" />
							</td>
							<td>
								<input type="text" name="som_add_lines[0][street_name]" value="" maxlength="191" />
							</td>
							<td>
								<input type="text" name="som_add_lines[0][style]" value="" maxlength="191" />
							</td>
							<td>
								<button type="button" class="button-link-delete som-add-order-remove"><?php echo esc_html__( 'Remove', 'order-machine' ); ?></button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<p>
				<button type="button" class="button" id="som-add-order-add-row"><?php echo esc_html__( 'Add line', 'order-machine' ); ?></button>
				<span class="description"><?php echo esc_html__( '1 to 10 lines. Each line needs a product, an item number, or a SKU. A chosen product is used directly. Item number and SKU are matched to a listing map.', 'order-machine' ); ?></span>
			</p>

			<template id="som-add-order-row-template">
				<tr class="som-add-order-row" data-index="__INDEX__">
					<td>
						<select name="som_add_lines[__INDEX__][product_id]">
							<option value=""><?php echo esc_html__( 'Select product…', 'order-machine' ); ?></option>
							<?php echo $add_order_product_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr/esc_html above. ?>
						</select>
					</td>
					<td>
						<input type="text" name="som_add_lines[__INDEX__][item_number]" value="" maxlength="100" />
					</td>
					<td>
						<input type="text" name="som_add_lines[__INDEX__][sku]" value="" maxlength="100" />
					</td>
					<td>
						<input type="number" name="som_add_lines[__INDEX__][quantity]" value="1" min="1" step="1" />
					</td>
					<td>
						<input type="number" name="som_add_lines[__INDEX__][unit_price]" value="" min="0" step="0.01" placeholder="0.00" />
					</td>
					<td>
						<input type="text" name="som_add_lines[__INDEX__][house_number]" value="" maxlength="64" />
					</td>
					<td>
						<input type="text" name="som_add_lines[__INDEX__][street_name]" value="" maxlength="191" />
					</td>
					<td>
						<input type="text" name="som_add_lines[__INDEX__][style]" value="" maxlength="191" />
					</td>
					<td>
						<button type="button" class="button-link-delete som-add-order-remove"><?php echo esc_html__( 'Remove', 'order-machine' ); ?></button>
					</td>
				</tr>
			</template>

			<?php submit_button( __( 'Add order', 'order-machine' ), 'secondary', 'som_add_order', false ); ?>
		</form>
	</details>

	<form method="get" class="som-orders-filters">
		<input type="hidden" name="page" value="som-orders" />

		<label class="screen-reader-text" for="som-status"><?php echo esc_html__( 'Status', 'order-machine' ); ?></label>
		<select name="som_status" id="som-status">
			<?php foreach ( $status_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="som-current-step"><?php echo esc_html__( 'Current step', 'order-machine' ); ?></label>
		<select name="som_current_step" id="som-current-step">
			<option value=""><?php echo esc_html__( 'All steps', 'order-machine' ); ?></option>
			<?php foreach ( $step_names as $step_name ) : ?>
				<option value="<?php echo esc_attr( $step_name ); ?>" <?php selected( $current_step, $step_name ); ?>>
					<?php echo esc_html( $step_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="som-channel"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></label>
		<select name="som_channel" id="som-channel">
			<option value=""><?php echo esc_html__( 'All channels', 'order-machine' ); ?></option>
			<?php foreach ( SOM_Channels::known() as $slug => $name ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $channel, $slug ); ?>>
					<?php echo esc_html( $name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="som-date-from"><?php echo esc_html__( 'From date', 'order-machine' ); ?></label>
		<input type="date" name="som_date_from" id="som-date-from" value="<?php echo esc_attr( $date_from ); ?>" />

		<label class="screen-reader-text" for="som-date-to"><?php echo esc_html__( 'To date', 'order-machine' ); ?></label>
		<input type="date" name="som_date_to" id="som-date-to" value="<?php echo esc_attr( $date_to ); ?>" />

		<label class="screen-reader-text" for="som-orders-search"><?php echo esc_html__( 'Search', 'order-machine' ); ?></label>
		<input
			type="search"
			name="s"
			id="som-orders-search"
			value="<?php echo esc_attr( $search ); ?>"
			placeholder="<?php echo esc_attr__( 'Buyer or order ID…', 'order-machine' ); ?>"
		/>

		<?php submit_button( __( 'Filter', 'order-machine' ), 'secondary', '', false ); ?>

		<?php if ( $status || $current_step || $channel || $date_from || $date_to || $search ) : ?>
			<a class="button button-link" href="<?php echo esc_url( SOM_Orders::list_url() ); ?>">
				<?php echo esc_html__( 'Reset', 'order-machine' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<p class="som-orders-count description">
		<?php
		printf(
			/* translators: %d: number of orders */
			esc_html( _n( '%d order', '%d orders', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="wp-list-table widefat fixed striped som-orders-table">
		<thead>
			<tr>
				<th scope="col" class="column-date"><?php echo esc_html__( 'Date', 'order-machine' ); ?></th>
				<th scope="col" class="column-channel"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></th>
				<th scope="col" class="column-external"><?php echo esc_html__( 'Order ID', 'order-machine' ); ?></th>
				<th scope="col" class="column-buyer"><?php echo esc_html__( 'Buyer', 'order-machine' ); ?></th>
				<th scope="col" class="column-products"><?php echo esc_html__( 'Products / personalisation', 'order-machine' ); ?></th>
				<th scope="col" class="column-flags"><?php echo esc_html__( 'Flags', 'order-machine' ); ?></th>
				<th scope="col" class="column-status"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $orders ) ) : ?>
				<tr>
					<td colspan="7"><?php echo esc_html__( 'No orders found.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $orders as $order ) : ?>
					<?php
					$detail_url       = SOM_Orders::detail_url( (int) $order->id );
					$has_unmatched    = (int) $order->unmatched_count > 0;
					$is_cancelled     = ! empty( $order->is_cancelled );
					$is_complete      = ! empty( $order->is_complete );
					$needs_workflow   = 1 === (int) $order->needs_workflow;
					$order_level_step = trim( (string) ( $order->current_step_name ?? '' ) );
					$make_step_name   = trim( (string) ( $order->current_make_step_name ?? '' ) );
					$status_step      = '' !== $order_level_step ? $order_level_step : $make_step_name;
					$person           = trim( (string) $order->personalisation_summary );
					?>
					<tr>
						<td class="column-date">
							<a href="<?php echo esc_url( $detail_url ); ?>">
								<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order->order_date ) ); ?>
							</a>
						</td>
						<td class="column-channel">
							<?php echo esc_html( (string) $order->channel_name ); ?>
							<?php if ( isset( $order->channel_slug ) && 'internal' === (string) $order->channel_slug ) : ?>
								<br /><span class="som-badge som-badge-production"><?php echo esc_html__( 'Production', 'order-machine' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-external">
							<a href="<?php echo esc_url( $detail_url ); ?>">
								<code><?php echo esc_html( (string) $order->external_order_id ); ?></code>
							</a>
						</td>
						<td class="column-buyer"><?php echo esc_html( (string) $order->buyer_name ); ?></td>
						<td class="column-products">
							<span class="som-products-summary"><?php echo esc_html( (string) $order->products_summary ); ?></span>
							<?php if ( '' !== $person ) : ?>
								<br />
								<span class="som-personalisation-snippet"><?php echo esc_html( $person ); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-flags">
							<?php if ( $has_unmatched ) : ?>
								<span class="som-badge som-badge-unmatched"><?php echo esc_html__( 'Unmatched', 'order-machine' ); ?></span>
							<?php endif; ?>
							<?php if ( $needs_workflow ) : ?>
								<span class="som-badge som-badge-needs-workflow"><?php echo esc_html__( 'No workflow', 'order-machine' ); ?></span>
							<?php endif; ?>
							<?php if ( $is_cancelled ) : ?>
								<span class="som-badge som-badge-cancelled"><?php echo esc_html__( 'Cancelled', 'order-machine' ); ?></span>
							<?php endif; ?>
							<?php if ( ! $has_unmatched && ! $needs_workflow && ! $is_cancelled ) : ?>
								<span class="som-muted">—</span>
							<?php endif; ?>
						</td>
						<td class="column-status">
							<?php if ( $is_complete ) : ?>
								<span class="som-badge som-badge-complete"><?php echo esc_html__( 'Complete', 'order-machine' ); ?></span>
							<?php elseif ( '' !== $status_step ) : ?>
								<span class="som-badge som-badge-open"><?php echo esc_html( $status_step ); ?></span>
							<?php else : ?>
								<span class="som-badge som-badge-open"><?php echo esc_html__( 'Open', 'order-machine' ); ?></span>
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
						/* translators: %d: number of orders */
						esc_html( _n( '%d item', '%d items', $total, 'order-machine' ) ),
						(int) $total
					);
					?>
				</span>
				<span class="pagination-links">
					<?php
					$base_args = array(
						'som_status'       => $status,
						'som_current_step' => $current_step,
						'som_channel'      => $channel,
						'som_date_from'    => $date_from,
						'som_date_to'      => $date_to,
						's'                => $search,
					);
					$base_args = array_filter(
						$base_args,
						static function ( $v ) {
							return '' !== $v && null !== $v;
						}
					);

					if ( $paged > 1 ) {
						$prev = SOM_Orders::list_url( array_merge( $base_args, array( 'paged' => $paged - 1 ) ) );
						echo '<a class="prev-page button" href="' . esc_url( $prev ) . '"><span class="screen-reader-text">' . esc_html__( 'Previous page', 'order-machine' ) . '</span><span aria-hidden="true">‹</span></a> ';
					} else {
						echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span> ';
					}

					printf(
						'<span class="paging-input">%1$s / <span class="total-pages">%2$s</span></span> ',
						(int) $paged,
						(int) $pages
					);

					if ( $paged < $pages ) {
						$next = SOM_Orders::list_url( array_merge( $base_args, array( 'paged' => $paged + 1 ) ) );
						echo '<a class="next-page button" href="' . esc_url( $next ) . '"><span class="screen-reader-text">' . esc_html__( 'Next page', 'order-machine' ) . '</span><span aria-hidden="true">›</span></a>';
					} else {
						echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
					}
					?>
				</span>
			</div>
		</div>
	<?php endif; ?>
</div>
