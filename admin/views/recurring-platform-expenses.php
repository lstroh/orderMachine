<?php
/**
 * Recurring platform expenses list (Etsy listing fees, etc.).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$listing_filter = isset( $_GET['listing_id'] ) ? (int) $_GET['listing_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter
$channel_filter = isset( $_GET['channel_id'] ) ? (int) $_GET['channel_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$args = array( 'limit' => 200 );
if ( $listing_filter > 0 ) {
	$args['listing_id'] = $listing_filter;
}
if ( $channel_filter > 0 ) {
	$args['channel_id'] = $channel_filter;
}

$result = SOM_Platform_Fee_Sync::list_recurring( $args );
$rows   = $result['rows'];
$total  = $result['total'];

$channels = array();
foreach ( array( 'ebay', 'etsy' ) as $slug ) {
	$ch = SOM_Channels::get_by_slug( $slug );
	if ( $ch ) {
		$channels[ (int) $ch->id ] = $ch;
	}
}

global $wpdb;
$listings_for_filter = $wpdb->get_results(
	'SELECT id, title, external_listing_id, channel_id FROM ' . SOM_DB::table( 'listings' ) . ' ORDER BY title ASC LIMIT 500'
);
if ( ! is_array( $listings_for_filter ) ) {
	$listings_for_filter = array();
}
?>
<div class="wrap som-catalog-wrap">
	<h1><?php echo esc_html__( 'Recurring Platform Expenses', 'order-machine' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'Non-order-linked platform charges (mainly Etsy listing fees), synced from the channel ledger.', 'order-machine' ); ?>
	</p>

	<form method="get" action="" class="som-panel" style="margin-bottom:1em;padding:12px;">
		<input type="hidden" name="page" value="som-recurring-platform-expenses" />
		<label for="som_recurring_channel">
			<?php echo esc_html__( 'Channel', 'order-machine' ); ?>
			<select name="channel_id" id="som_recurring_channel">
				<option value="0"><?php echo esc_html__( 'All', 'order-machine' ); ?></option>
				<?php foreach ( $channels as $cid => $ch ) : ?>
					<option value="<?php echo esc_attr( (string) $cid ); ?>" <?php selected( $channel_filter, $cid ); ?>>
						<?php echo esc_html( (string) $ch->display_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<label for="som_recurring_listing" style="margin-left:12px;">
			<?php echo esc_html__( 'Listing', 'order-machine' ); ?>
			<select name="listing_id" id="som_recurring_listing">
				<option value="0"><?php echo esc_html__( 'All', 'order-machine' ); ?></option>
				<?php foreach ( $listings_for_filter as $listing ) : ?>
					<option value="<?php echo esc_attr( (string) (int) $listing->id ); ?>" <?php selected( $listing_filter, (int) $listing->id ); ?>>
						<?php
						echo esc_html(
							sprintf(
								'%s (%s)',
								(string) $listing->title,
								(string) $listing->external_listing_id
							)
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php submit_button( __( 'Filter', 'order-machine' ), 'secondary', '', false ); ?>
		<?php if ( $listing_filter || $channel_filter ) : ?>
			<a class="button" href="<?php echo esc_url( SOM_Platform_Fee_Sync::recurring_list_url() ); ?>">
				<?php echo esc_html__( 'Clear', 'order-machine' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<p>
		<?php
		printf(
			/* translators: %d: row count */
			esc_html( _n( '%d expense', '%d expenses', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Date', 'order-machine' ); ?></th>
				<th><?php echo esc_html__( 'Channel', 'order-machine' ); ?></th>
				<th><?php echo esc_html__( 'Listing', 'order-machine' ); ?></th>
				<th><?php echo esc_html__( 'Type', 'order-machine' ); ?></th>
				<th><?php echo esc_html__( 'Amount', 'order-machine' ); ?></th>
				<th><?php echo esc_html__( 'Notes', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr>
					<td colspan="6"><?php echo esc_html__( 'No recurring expenses synced yet.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $row->incurred_date ); ?></td>
						<td><?php echo esc_html( (string) $row->channel_name ); ?></td>
						<td>
							<?php if ( ! empty( $row->listing_id ) && ! empty( $row->listing_title ) ) : ?>
								<?php echo esc_html( (string) $row->listing_title ); ?>
								<br /><code><?php echo esc_html( (string) $row->external_listing_id ); ?></code>
							<?php else : ?>
								<span class="som-muted">—</span>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( (string) $row->fee_type ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( (float) $row->amount, 4 ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row->notes ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
