<?php
/**
 * Post-ship Board — order Kanban for aftercare (UP11-S2).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$scope       = isset( $_GET['som_scope'] ) ? sanitize_key( wp_unslash( $_GET['som_scope'] ) ) : SOM_Post_Ship::SCOPE_ACTIVE;
$scope       = SOM_Post_Ship::sanitize_scope( $scope );
$channel     = isset( $_GET['som_channel'] ) ? sanitize_key( wp_unslash( $_GET['som_channel'] ) ) : '';
$workflow_id = isset( $_GET['som_workflow'] ) ? (int) $_GET['som_workflow'] : 0;
$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$board = SOM_Post_Ship::query_board(
	array(
		'scope'                => $scope,
		'channel'              => $channel,
		'workflow_template_id' => $workflow_id,
		's'                    => $search,
	)
);

$orders         = $board['orders'];
$total          = (int) $board['total'];
$capped         = ! empty( $board['capped'] );
$warn           = ! empty( $board['warn'] );
$workflow_steps = isset( $board['workflow_steps'] ) ? $board['workflow_steps'] : array();
$columns        = SOM_Orders::board_columns( $orders );
$pinned_ids     = SOM_Orders::get_board_pinned_ids();
$pinned_set     = array_fill_keys( $pinned_ids, true );

if ( SOM_Post_Ship::SCOPE_UNSTARTED === $scope ) {
	$columns = array(
		array(
			'key'   => SOM_Post_Ship::BOARD_UNSTARTED_KEY,
			'label' => __( 'Unstarted', 'order-machine' ),
		),
	);
} elseif ( SOM_Post_Ship::SCOPE_COMPLETE === $scope ) {
	$columns = array(
		array(
			'key'   => SOM_Post_Ship::BOARD_FINISHED_KEY,
			'label' => __( 'Complete', 'order-machine' ),
		),
	);
} elseif ( $workflow_id > 0 && ! empty( $workflow_steps ) ) {
	$forced = array();
	foreach ( $workflow_steps as $step ) {
		$name = trim( (string) $step->name );
		if ( '' !== $name ) {
			$forced[ $name ] = array(
				'key'   => $name,
				'label' => $name,
			);
		}
	}
	foreach ( $columns as $col ) {
		if ( ! isset( $forced[ $col['key'] ] ) && SOM_Orders::BOARD_UNASSIGNED_KEY !== $col['key'] ) {
			$forced[ $col['key'] ] = $col;
		}
	}
	$has_unassigned = false;
	foreach ( $columns as $col ) {
		if ( SOM_Orders::BOARD_UNASSIGNED_KEY === $col['key'] ) {
			$has_unassigned = true;
			break;
		}
	}
	$columns = array_values( $forced );
	if ( $has_unassigned ) {
		array_unshift(
			$columns,
			array(
				'key'   => SOM_Orders::BOARD_UNASSIGNED_KEY,
				'label' => __( 'Unassigned', 'order-machine' ),
			)
		);
	}
}

$need_complete_zone = false;
if ( SOM_Post_Ship::SCOPE_ACTIVE === $scope ) {
	foreach ( $orders as $order ) {
		if ( ! empty( $order->can_advance ) && ! empty( $order->is_last_step ) ) {
			$need_complete_zone = true;
			break;
		}
	}
}

$by_column = array();
foreach ( $columns as $col ) {
	$by_column[ $col['key'] ] = array();
}
foreach ( $orders as $order ) {
	$key = isset( $order->column_key ) ? (string) $order->column_key : SOM_Orders::BOARD_UNASSIGNED_KEY;
	if ( ! isset( $by_column[ $key ] ) ) {
		$by_column[ $key ] = array();
		$columns[]         = array(
			'key'   => $key,
			'label' => SOM_Orders::BOARD_UNASSIGNED_KEY === $key
				? __( 'Unassigned', 'order-machine' )
				: $key,
		);
	}
	$by_column[ $key ][] = $order;
}

$workflows_q = SOM_Workflows::query(
	array(
		'status'   => 'active',
		'kind'     => 'post_ship',
		'per_page' => 200,
		'paged'    => 1,
	)
);
$workflows = $workflows_q['templates'];

$has_filters = ( SOM_Post_Ship::SCOPE_ACTIVE !== $scope || '' !== $channel || $workflow_id > 0 || '' !== $search );
$ps_tpl      = SOM_Post_Ship::default_template_id();
?>
<div class="wrap som-orders-board-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Post-ship Board', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=som-pack-board' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Pack Board', 'order-machine' ); ?>
	</a>
	<a href="<?php echo esc_url( SOM_Orders::board_url() ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Make Board', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<p class="description">
		<?php echo esc_html__( 'Aftercare after Ship: update buyer, confirm arrival, review reminder. Filter by Post-ship template to fix columns. Order complete = shipping details + arrival (not Pack Ship alone).', 'order-machine' ); ?>
	</p>

	<?php if ( $ps_tpl < 1 ) : ?>
		<div class="notice notice-warning inline">
			<p><?php echo esc_html__( 'No default Post-ship workflow template is set. Create a kind=Post-ship template and choose it under Settings.', 'order-machine' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $capped ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				printf(
					esc_html__( 'Showing the oldest %2$d of %1$d matching orders.', 'order-machine' ),
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
					esc_html__( '%1$d orders match these filters (warning at %2$d).', 'order-machine' ),
					(int) $total,
					(int) SOM_Orders::BOARD_WARN
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="get" class="som-orders-filters som-board-filters">
		<input type="hidden" name="page" value="som-post-ship-board" />

		<label class="screen-reader-text" for="som-ps-scope"><?php echo esc_html__( 'Scope', 'order-machine' ); ?></label>
		<select name="som_scope" id="som-ps-scope">
			<option value="<?php echo esc_attr( SOM_Post_Ship::SCOPE_ACTIVE ); ?>" <?php selected( $scope, SOM_Post_Ship::SCOPE_ACTIVE ); ?>>
				<?php echo esc_html__( 'Active', 'order-machine' ); ?>
			</option>
			<option value="<?php echo esc_attr( SOM_Post_Ship::SCOPE_UNSTARTED ); ?>" <?php selected( $scope, SOM_Post_Ship::SCOPE_UNSTARTED ); ?>>
				<?php echo esc_html__( 'Unstarted', 'order-machine' ); ?>
			</option>
			<option value="<?php echo esc_attr( SOM_Post_Ship::SCOPE_COMPLETE ); ?>" <?php selected( $scope, SOM_Post_Ship::SCOPE_COMPLETE ); ?>>
				<?php echo esc_html__( 'Complete', 'order-machine' ); ?>
			</option>
		</select>

		<label class="screen-reader-text" for="som-ps-channel"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></label>
		<select name="som_channel" id="som-ps-channel">
			<option value=""><?php echo esc_html__( 'All channels', 'order-machine' ); ?></option>
			<?php foreach ( SOM_Channels::known() as $slug => $name ) : ?>
				<?php if ( SOM_Production::CHANNEL_SLUG === $slug ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $channel, $slug ); ?>><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="som-ps-workflow"><?php echo esc_html__( 'Post-ship workflow', 'order-machine' ); ?></label>
		<select name="som_workflow" id="som-ps-workflow">
			<option value="0"><?php echo esc_html__( 'All Post-ship workflows', 'order-machine' ); ?></option>
			<?php foreach ( $workflows as $wf ) : ?>
				<option value="<?php echo esc_attr( (string) (int) $wf->id ); ?>" <?php selected( $workflow_id, (int) $wf->id ); ?>>
					<?php echo esc_html( (string) $wf->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Buyer, order ID…', 'order-machine' ); ?>" />
		<?php submit_button( __( 'Filter', 'order-machine' ), 'secondary', '', false ); ?>
		<?php if ( $has_filters ) : ?>
			<a class="button button-link" href="<?php echo esc_url( SOM_Post_Ship::board_url() ); ?>"><?php echo esc_html__( 'Reset', 'order-machine' ); ?></a>
		<?php endif; ?>

		<label class="som-board-pinned-toggle">
			<input type="checkbox" id="som-board-pinned-only" data-som-board-pinned-only />
			<?php echo esc_html__( 'Pinned only', 'order-machine' ); ?>
		</label>
	</form>

	<p class="som-orders-count description">
		<?php
		printf(
			/* translators: 1: cards shown, 2: total matching */
			esc_html__( 'Showing %1$d of %2$d matching orders', 'order-machine' ),
			(int) count( $orders ),
			(int) $total
		);
		?>
	</p>

	<?php if ( empty( $orders ) && ! $need_complete_zone ) : ?>
		<p><?php echo esc_html__( 'No post-ship orders match these filters.', 'order-machine' ); ?></p>
	<?php else : ?>
		<div class="som-board-scroll" data-som-board>
			<div class="som-board-row">
				<div class="som-board-columns" data-som-board-columns>
				<?php foreach ( $columns as $col ) : ?>
					<?php
					$col_key   = $col['key'];
					$col_cards = isset( $by_column[ $col_key ] ) ? $by_column[ $col_key ] : array();
					if ( empty( $col_cards ) && SOM_Post_Ship::SCOPE_ACTIVE === $scope
						&& ! in_array( $col_key, array( SOM_Orders::BOARD_UNASSIGNED_KEY, SOM_Post_Ship::BOARD_UNSTARTED_KEY, SOM_Post_Ship::BOARD_FINISHED_KEY ), true )
						&& $workflow_id < 1 ) {
						continue;
					}
					?>
					<section class="som-board-column" data-som-column-key="<?php echo esc_attr( $col_key ); ?>">
						<header class="som-board-column-header">
							<div class="som-board-column-title">
								<strong><?php echo esc_html( $col['label'] ); ?></strong>
								<span class="som-board-column-count"><?php echo esc_html( (string) count( $col_cards ) ); ?></span>
							</div>
							<?php if ( SOM_Post_Ship::SCOPE_ACTIVE === $scope ) : ?>
								<div class="som-board-column-actions">
									<button type="button" class="button button-small" data-som-col-move="left" title="<?php echo esc_attr__( 'Move column left', 'order-machine' ); ?>">←</button>
									<button type="button" class="button button-small" data-som-col-move="right" title="<?php echo esc_attr__( 'Move column right', 'order-machine' ); ?>">→</button>
								</div>
							<?php endif; ?>
						</header>
						<div class="som-board-cards" data-som-sortable-list>
							<?php foreach ( $col_cards as $order ) : ?>
								<?php
								$oid         = (int) $order->id;
								$detail_url  = SOM_Orders::detail_url( $oid ) . '#som-post-ship';
								$is_pinned   = isset( $pinned_set[ $oid ] );
								$can_advance = ! empty( $order->can_advance );
								$status      = (string) ( $order->progress_status ?? '' );
								$timer_ready = ! empty( $order->timer_ready );
								$timer_ends_ts = ! empty( $order->timer_ends_ts ) ? (int) $order->timer_ends_ts : 0;
								$display_status = $timer_ready ? 'timer_ready' : $status;
								$card_classes = 'som-board-card' . ( $is_pinned ? ' is-pinned' : '' );
								if ( SOM_Post_Ship::SCOPE_ACTIVE === $scope && ! $can_advance ) {
									$card_classes .= ' is-locked';
								}
								if ( $timer_ready ) {
									$card_classes .= ' is-timer-ready';
								}
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
									<?php if ( 'waiting_timer' === $status && $timer_ends_ts > 0 ) : ?>
										data-som-timer-ends-at="<?php echo esc_attr( (string) $timer_ends_ts ); ?>"
									<?php endif; ?>
									<?php echo ( SOM_Post_Ship::SCOPE_ACTIVE === $scope && ! $can_advance ) ? ' data-som-locked="1"' : ''; ?>
								>
									<div class="som-board-card-top">
										<button type="button" class="som-board-pin" data-som-board-pin aria-pressed="<?php echo $is_pinned ? 'true' : 'false'; ?>">★</button>
										<span class="som-badge som-badge-channel"><?php echo esc_html( (string) $order->channel_name ); ?></span>
										<?php if ( ! empty( $order->is_complete ) ) : ?>
											<span class="som-badge som-badge-complete"><?php echo esc_html__( 'Order complete', 'order-machine' ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $order->arrival_confirmed_at ) ) : ?>
											<span class="som-badge som-badge-complete"><?php echo esc_html__( 'Arrived', 'order-machine' ); ?></span>
										<?php endif; ?>
									</div>
									<div class="som-board-card-id">
										<a href="<?php echo esc_url( $detail_url ); ?>"><code><?php echo esc_html( (string) $order->external_order_id ); ?></code></a>
									</div>
									<div class="som-board-card-buyer"><?php echo esc_html( (string) $order->buyer_name ); ?></div>
									<?php if ( SOM_Post_Ship::SCOPE_ACTIVE === $scope && ( ! empty( $order->current_step_name ) || $display_status ) ) : ?>
										<div class="som-board-card-meta">
											<?php if ( ! empty( $order->current_step_name ) ) : ?>
												<span class="som-badge som-badge-open" data-som-card-step><?php echo esc_html( (string) $order->current_step_name ); ?></span>
											<?php endif; ?>
											<?php if ( $display_status ) : ?>
												<span class="som-badge som-badge-step-<?php echo esc_attr( preg_replace( '/[^a-z0-9_]/', '', $display_status ) ); ?>" data-som-card-status>
													<?php echo esc_html( SOM_Orders::progress_status_label( $display_status ) ); ?>
												</span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
									<?php if ( 'waiting_timer' === $status && $timer_ends_ts > 0 && ! $timer_ready ) : ?>
										<p class="som-board-timer description" data-som-board-countdown data-ends-at="<?php echo esc_attr( (string) $timer_ends_ts ); ?>">
											<?php echo esc_html__( 'Waiting (timer)', 'order-machine' ); ?>
										</p>
									<?php elseif ( $timer_ready ) : ?>
										<p class="som-board-timer som-timer-ready description" data-som-timer-ready-msg>
											<?php echo esc_html__( 'Timer ready', 'order-machine' ); ?>
										</p>
									<?php endif; ?>
									<div class="som-board-card-batch" data-som-card-batch hidden></div>
									<div class="som-board-card-actions">
										<?php if ( SOM_Post_Ship::SCOPE_UNSTARTED === $scope ) : ?>
											<?php
											$resolved = ! empty( $order->resolved_template_id ) ? (int) $order->resolved_template_id : 0;
											?>
											<?php if ( $resolved > 0 ) : ?>
												<form method="post" action="<?php echo esc_url( SOM_Orders::detail_url( $oid ) ); ?>" class="som-board-start-post-ship">
													<?php wp_nonce_field( 'som_start_post_ship', 'som_order_nonce' ); ?>
													<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) $oid ); ?>" />
													<input type="hidden" name="som_post_ship_template_id" value="<?php echo esc_attr( (string) $resolved ); ?>" />
													<input type="hidden" name="som_start_post_ship" value="1" />
													<input type="hidden" name="som_redirect_post_ship_board" value="1" />
													<?php submit_button( __( 'Start', 'order-machine' ), 'primary', 'submit', false ); ?>
												</form>
											<?php endif; ?>
											<a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html__( 'Open', 'order-machine' ); ?></a>
										<?php else : ?>
											<a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html__( 'Open', 'order-machine' ); ?></a>
										<?php endif; ?>
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
								<strong><?php echo esc_html__( 'Finish lane', 'order-machine' ); ?></strong>
								<span class="som-board-column-count">0</span>
							</div>
						</header>
						<div class="som-board-cards" data-som-sortable-list>
							<p class="som-muted som-board-complete-hint" data-som-complete-hint><?php echo esc_html__( 'Drop final post-ship steps here to finish the lane.', 'order-machine' ); ?></p>
						</div>
					</section>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
