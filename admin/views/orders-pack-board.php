<?php
/**
 * Pack Board — order Kanban for pack/ship (UP6-S2).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$channel = isset( $_GET['som_channel'] ) ? sanitize_key( wp_unslash( $_GET['som_channel'] ) ) : '';
$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$board  = SOM_Pack::query_board(
	array(
		'channel' => $channel,
		's'       => $search,
	)
);
$orders = $board['orders'];
$total  = (int) $board['total'];
$capped = ! empty( $board['capped'] );
$warn   = ! empty( $board['warn'] );

$columns = array(
	array(
		'key'   => SOM_Pack::BOARD_WAITING_MAKE,
		'label' => __( 'Waiting for make', 'order-machine' ),
	),
	array(
		'key'   => SOM_Pack::BOARD_HELD,
		'label' => __( 'Held', 'order-machine' ),
	),
);
$extra = SOM_Orders::board_columns( $orders );
foreach ( $extra as $col ) {
	if ( in_array( $col['key'], array( SOM_Pack::BOARD_WAITING_MAKE, SOM_Pack::BOARD_HELD ), true ) ) {
		continue;
	}
	$columns[] = $col;
}

$need_complete_zone = false;
foreach ( $orders as $order ) {
	if ( ! empty( $order->can_advance ) && ! empty( $order->is_last_step ) ) {
		$need_complete_zone = true;
		break;
	}
}

$by_column = array();
foreach ( $columns as $col ) {
	$by_column[ $col['key'] ] = array();
}
foreach ( $orders as $order ) {
	$key = isset( $order->column_key ) ? (string) $order->column_key : SOM_Orders::BOARD_UNASSIGNED_KEY;
	if ( ! isset( $by_column[ $key ] ) ) {
		$by_column[ $key ]   = array();
		$columns[]           = array(
			'key'   => $key,
			'label' => SOM_Orders::BOARD_UNASSIGNED_KEY === $key ? __( 'Unassigned', 'order-machine' ) : $key,
		);
	}
	$by_column[ $key ][] = $order;
}

$pinned_ids = SOM_Orders::get_board_pinned_ids();
$pinned_set = array_fill_keys( $pinned_ids, true );
$has_filters = ( '' !== $channel || '' !== $search );
$pack_tpl    = SOM_Pack::default_template_id();
?>
<div class="wrap som-orders-board-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Pack Board', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Orders::board_url() ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Make Board', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<p class="description">
		<?php echo esc_html__( 'Orders in pack & ship (not Internal). Waiting for make and Held are listed separately; Ship stays blocked until make-ready, package, checklist, and shipment are done.', 'order-machine' ); ?>
	</p>

	<?php if ( $pack_tpl < 1 ) : ?>
		<div class="notice notice-warning inline">
			<p><?php echo esc_html__( 'No default Pack workflow template is set. Create a kind=Pack template and choose it under Settings.', 'order-machine' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $capped ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				printf(
					esc_html__( 'Showing the oldest %2$d of %1$d matching open orders.', 'order-machine' ),
					(int) $total,
					(int) SOM_Orders::BOARD_CAP
				);
				?>
			</p>
		</div>
	<?php elseif ( $warn ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				printf(
					esc_html__( '%1$d open orders match these filters (warning at %2$d).', 'order-machine' ),
					(int) $total,
					(int) SOM_Orders::BOARD_WARN
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="get" class="som-orders-filters som-board-filters">
		<input type="hidden" name="page" value="som-pack-board" />
		<label class="screen-reader-text" for="som-pack-channel"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></label>
		<select name="som_channel" id="som-pack-channel">
			<option value=""><?php echo esc_html__( 'All channels', 'order-machine' ); ?></option>
			<?php foreach ( SOM_Channels::known() as $slug => $name ) : ?>
				<?php if ( SOM_Production::CHANNEL_SLUG === $slug ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $channel, $slug ); ?>><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Buyer, order ID…', 'order-machine' ); ?>" />
		<?php submit_button( __( 'Filter', 'order-machine' ), 'secondary', '', false ); ?>
		<?php if ( $has_filters ) : ?>
			<a class="button button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=som-pack-board' ) ); ?>"><?php echo esc_html__( 'Reset', 'order-machine' ); ?></a>
		<?php endif; ?>
	</form>

	<?php if ( empty( $orders ) && ! $need_complete_zone ) : ?>
		<p><?php echo esc_html__( 'No open pack orders match these filters.', 'order-machine' ); ?></p>
	<?php else : ?>
		<div class="som-board-scroll" data-som-board>
			<div class="som-board-row">
				<div class="som-board-columns" data-som-board-columns>
				<?php foreach ( $columns as $col ) : ?>
					<?php
					$col_key   = $col['key'];
					$col_cards = isset( $by_column[ $col_key ] ) ? $by_column[ $col_key ] : array();
					if ( empty( $col_cards ) && in_array( $col_key, array( SOM_Pack::BOARD_WAITING_MAKE, SOM_Pack::BOARD_HELD ), true ) ) {
						// Keep empty Waiting/Held columns visible.
					} elseif ( empty( $col_cards ) && SOM_Orders::BOARD_UNASSIGNED_KEY !== $col_key ) {
						// Skip empty dynamic step columns that aren't waiting/held.
						$skip = true;
						foreach ( $orders as $o ) {
							if ( (string) $o->column_key === (string) $col_key ) {
								$skip = false;
								break;
							}
						}
						if ( $skip && ! in_array( $col_key, array( SOM_Pack::BOARD_WAITING_MAKE, SOM_Pack::BOARD_HELD ), true ) ) {
							continue;
						}
					}
					?>
					<section class="som-board-column" data-som-column-key="<?php echo esc_attr( $col_key ); ?>">
						<header class="som-board-column-header">
							<div class="som-board-column-title">
								<strong><?php echo esc_html( $col['label'] ); ?></strong>
								<span class="som-board-column-count"><?php echo esc_html( (string) count( $col_cards ) ); ?></span>
							</div>
						</header>
						<div class="som-board-cards" data-som-sortable-list>
							<?php foreach ( $col_cards as $order ) : ?>
								<?php
								$oid         = (int) $order->id;
								$detail_url  = SOM_Orders::detail_url( $oid );
								$is_pinned   = isset( $pinned_set[ $oid ] );
								$can_advance = ! empty( $order->can_advance );
								$status      = (string) ( $order->progress_status ?? '' );
								$card_classes = 'som-board-card' . ( $is_pinned ? ' is-pinned' : '' ) . ( $can_advance ? '' : ' is-locked' );
								?>
								<article
									class="<?php echo esc_attr( $card_classes ); ?>"
									data-som-order-id="<?php echo esc_attr( (string) $oid ); ?>"
									data-som-order-item-id="0"
									data-som-pinned="<?php echo $is_pinned ? '1' : '0'; ?>"
									data-som-can-advance="<?php echo $can_advance ? '1' : '0'; ?>"
									data-som-is-last-step="<?php echo ! empty( $order->is_last_step ) ? '1' : '0'; ?>"
									data-som-next-step-name="<?php echo esc_attr( (string) ( $order->next_step_name ?? '' ) ); ?>"
									data-som-order-ref="<?php echo esc_attr( (string) $order->external_order_id ); ?>"
									data-som-step-name="<?php echo esc_attr( (string) ( $order->current_step_name ?? '' ) ); ?>"
									data-som-progress-status="<?php echo esc_attr( $status ); ?>"
								>
									<div class="som-board-card-top">
										<button type="button" class="som-board-pin" data-som-board-pin aria-pressed="<?php echo $is_pinned ? 'true' : 'false'; ?>">★</button>
										<span class="som-badge som-badge-channel"><?php echo esc_html( (string) $order->channel_name ); ?></span>
										<?php if ( ! empty( $order->is_held ) ) : ?>
											<span class="som-badge som-badge-error"><?php echo esc_html__( 'Held', 'order-machine' ); ?></span>
										<?php endif; ?>
										<?php if ( empty( $order->make_ready ) ) : ?>
											<span class="som-badge som-badge-needs-workflow"><?php echo esc_html__( 'Make', 'order-machine' ); ?></span>
										<?php endif; ?>
									</div>
									<div class="som-board-card-id">
										<a href="<?php echo esc_url( $detail_url ); ?>"><code><?php echo esc_html( (string) $order->external_order_id ); ?></code></a>
									</div>
									<div class="som-board-card-buyer"><?php echo esc_html( (string) $order->buyer_name ); ?></div>
									<?php if ( ! empty( $order->current_step_name ) && SOM_Pack::BOARD_WAITING_MAKE !== $col_key && SOM_Pack::BOARD_HELD !== $col_key ) : ?>
										<div class="som-board-card-meta">
											<span class="som-badge som-badge-open" data-som-card-step><?php echo esc_html( (string) $order->current_step_name ); ?></span>
										</div>
									<?php endif; ?>
									<div class="som-board-card-batch" data-som-card-batch hidden></div>
									<div class="som-board-card-actions">
										<a class="button button-small" href="<?php echo esc_url( $detail_url . '#som-pack' ); ?>"><?php echo esc_html__( 'Pack', 'order-machine' ); ?></a>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
				</div>
				<?php if ( $need_complete_zone ) : ?>
					<section class="som-board-column som-board-complete-zone" data-som-column-key="<?php echo esc_attr( SOM_Orders::BOARD_COMPLETE_KEY ); ?>" data-som-complete-zone>
						<header class="som-board-column-header">
							<div class="som-board-column-title">
								<strong><?php echo esc_html__( 'Complete', 'order-machine' ); ?></strong>
								<span class="som-board-column-count">0</span>
							</div>
						</header>
						<div class="som-board-cards" data-som-sortable-list>
							<p class="som-muted som-board-complete-hint" data-som-complete-hint><?php echo esc_html__( 'Drop final pack-step orders here to complete.', 'order-machine' ); ?></p>
						</div>
					</section>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
