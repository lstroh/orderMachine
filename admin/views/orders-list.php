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

$status    = isset( $_GET['som_status'] ) ? sanitize_key( wp_unslash( $_GET['som_status'] ) ) : '';
$channel   = isset( $_GET['som_channel'] ) ? sanitize_key( wp_unslash( $_GET['som_channel'] ) ) : '';
$date_from = isset( $_GET['som_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['som_date_from'] ) ) : '';
$date_to   = isset( $_GET['som_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['som_date_to'] ) ) : '';
$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$result = SOM_Orders::query(
	array(
		'status'    => $status,
		'channel'   => $channel,
		'date_from' => $date_from,
		'date_to'   => $date_to,
		's'         => $search,
		'paged'     => $paged,
	)
);

$orders = $result['orders'];
$total  = $result['total'];
$pages  = $result['pages'];
$paged  = $result['paged'];

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

		<?php if ( $status || $channel || $date_from || $date_to || $search ) : ?>
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
					$detail_url = SOM_Orders::detail_url( (int) $order->id );
					$has_unmatched   = (int) $order->unmatched_count > 0;
					$is_cancelled    = ! empty( $order->is_cancelled );
					$is_complete     = ! empty( $order->is_complete );
					$needs_workflow  = ! $is_complete && ! $is_cancelled && empty( $order->current_step_id ) && (int) $order->progress_count < 1;
					$person          = trim( (string) $order->personalisation_summary );
					?>
					<tr>
						<td class="column-date">
							<a href="<?php echo esc_url( $detail_url ); ?>">
								<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order->order_date ) ); ?>
							</a>
						</td>
						<td class="column-channel"><?php echo esc_html( (string) $order->channel_name ); ?></td>
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
							<?php elseif ( ! empty( $order->current_step_name ) ) : ?>
								<span class="som-badge som-badge-open"><?php echo esc_html( (string) $order->current_step_name ); ?></span>
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
						'som_status'    => $status,
						'som_channel'   => $channel,
						'som_date_from' => $date_from,
						'som_date_to'   => $date_to,
						's'             => $search,
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
