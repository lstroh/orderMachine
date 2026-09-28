<?php
/**
 * Make Board (Kanban of order lines) — Package 6 / UP6-S1.
 *
 * Route slug remains som-orders-board.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$channel     = isset( $_GET['som_channel'] ) ? sanitize_key( wp_unslash( $_GET['som_channel'] ) ) : '';
$product_id  = isset( $_GET['som_product'] ) ? (int) $_GET['som_product'] : 0;
$workflow_id = isset( $_GET['som_workflow'] ) ? (int) $_GET['som_workflow'] : 0;
$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$board = SOM_Orders::query_make_board(
	array(
		'channel'              => $channel,
		'product_id'           => $product_id,
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

// When a make template is selected, show all its make steps as columns (O7).
if ( $workflow_id > 0 && ! empty( $workflow_steps ) ) {
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
	// Keep unassigned first if present.
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
		$by_column[ $key ] = array();
	}
	$by_column[ $key ][] = $order;
}

$products_q = SOM_Products::query(
	array(
		'status'   => 'active',
		'per_page' => 500,
		'paged'    => 1,
	)
);
$products = $products_q['products'];

$workflows_q = SOM_Workflows::query(
	array(
		'status'   => 'active',
		'kind'     => 'make',
		'per_page' => 200,
		'paged'    => 1,
	)
);
$workflows = $workflows_q['templates'];

$has_filters = ( '' !== $channel || $product_id > 0 || $workflow_id > 0 || '' !== $search );
?>
<div class="wrap som-orders-board-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Make Board', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Orders::list_url() ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Orders list', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<p class="description">
		<?php echo esc_html__( 'Production queue by order line. Filter by make workflow to fix columns to that template’s steps. Pack & Ship lands in a later sprint — make-complete lines leave this board.', 'order-machine' ); ?>
		<a href="<?php echo esc_url( SOM_Orders::list_url( array( 'som_status' => 'complete' ) ) ); ?>">
			<?php echo esc_html__( 'View history', 'order-machine' ); ?>
		</a>
	</p>

	<?php if ( $capped ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				printf(
					/* translators: 1: total matching lines, 2: hard cap */
					esc_html__( 'Showing the oldest %2$d of %1$d matching open lines. Narrow filters to see the rest.', 'order-machine' ),
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
					/* translators: 1: matching line count, 2: warn threshold */
					esc_html__( '%1$d open lines match these filters (warning at %2$d). Consider narrowing filters.', 'order-machine' ),
					(int) $total,
					(int) SOM_Orders::BOARD_WARN
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="get" class="som-orders-filters som-board-filters">
		<input type="hidden" name="page" value="som-orders-board" />

		<label class="screen-reader-text" for="som-board-channel"><?php echo esc_html__( 'Channel', 'order-machine' ); ?></label>
		<select name="som_channel" id="som-board-channel">
			<option value=""><?php echo esc_html__( 'All channels', 'order-machine' ); ?></option>
			<?php foreach ( SOM_Channels::known() as $slug => $name ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $channel, $slug ); ?>>
					<?php echo esc_html( $name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="som-board-product"><?php echo esc_html__( 'Product', 'order-machine' ); ?></label>
		<select name="som_product" id="som-board-product">
			<option value="0"><?php echo esc_html__( 'All products', 'order-machine' ); ?></option>
			<?php foreach ( $products as $product ) : ?>
				<option value="<?php echo esc_attr( (string) (int) $product->id ); ?>" <?php selected( $product_id, (int) $product->id ); ?>>
					<?php echo esc_html( (string) $product->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="som-board-workflow"><?php echo esc_html__( 'Make workflow', 'order-machine' ); ?></label>
		<select name="som_workflow" id="som-board-workflow">
			<option value="0"><?php echo esc_html__( 'All make workflows', 'order-machine' ); ?></option>
			<?php foreach ( $workflows as $wf ) : ?>
				<option value="<?php echo esc_attr( (string) (int) $wf->id ); ?>" <?php selected( $workflow_id, (int) $wf->id ); ?>>
					<?php echo esc_html( (string) $wf->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="som-board-search"><?php echo esc_html__( 'Search', 'order-machine' ); ?></label>
		<input
			type="search"
			name="s"
			id="som-board-search"
			value="<?php echo esc_attr( $search ); ?>"
			placeholder="<?php echo esc_attr__( 'Buyer, order ID, product, personalisation…', 'order-machine' ); ?>"
		/>

		<?php submit_button( __( 'Filter', 'order-machine' ), 'secondary', '', false ); ?>

		<?php if ( $has_filters ) : ?>
			<a class="button button-link" href="<?php echo esc_url( SOM_Orders::board_url() ); ?>">
				<?php echo esc_html__( 'Reset', 'order-machine' ); ?>
			</a>
		<?php endif; ?>

		<label class="som-board-pinned-toggle">
			<input type="checkbox" id="som-board-pinned-only" data-som-board-pinned-only />
			<?php echo esc_html__( 'Pinned only', 'order-machine' ); ?>
		</label>
	</form>

	<p class="som-orders-count description">
		<?php
		$shown = count( $orders );
		printf(
			/* translators: 1: cards shown, 2: total matching */
			esc_html__( 'Showing %1$d of %2$d matching open lines', 'order-machine' ),
			(int) $shown,
			(int) $total
		);
		?>
	</p>

	<?php if ( empty( $columns ) && ! $need_complete_zone ) : ?>
		<p><?php echo esc_html__( 'No open make lines match these filters.', 'order-machine' ); ?></p>
	<?php else : ?>
		<div class="som-board-scroll" data-som-board>
			<div class="som-board-row">
				<div class="som-board-columns" data-som-board-columns>
				<?php foreach ( $columns as $col ) : ?>
					<?php
					$col_key   = $col['key'];
					$col_cards = isset( $by_column[ $col_key ] ) ? $by_column[ $col_key ] : array();
					?>
					<section class="som-board-column" data-som-column-key="<?php echo esc_attr( $col_key ); ?>">
						<header class="som-board-column-header">
							<div class="som-board-column-title">
								<strong><?php echo esc_html( $col['label'] ); ?></strong>
								<span class="som-board-column-count"><?php echo esc_html( (string) count( $col_cards ) ); ?></span>
							</div>
							<div class="som-board-column-actions">
								<button type="button" class="button button-small" data-som-col-move="left" title="<?php echo esc_attr__( 'Move column left', 'order-machine' ); ?>">←</button>
								<button type="button" class="button button-small" data-som-col-move="right" title="<?php echo esc_attr__( 'Move column right', 'order-machine' ); ?>">→</button>
							</div>
						</header>
						<div class="som-board-cards" data-som-sortable-list>
							<?php foreach ( $col_cards as $order ) : ?>
								<?php
								$oid             = (int) $order->order_id;
								$item_id         = (int) $order->order_item_id;
								$detail_url      = SOM_Orders::detail_url( $oid );
								$is_pinned       = isset( $pinned_set[ $oid ] );
								$person_src      = isset( $order->personalisation_text ) ? (string) $order->personalisation_text : '';
								$person          = SOM_Orders::truncate_personalisation( $person_src );
								$status          = (string) $order->progress_status;
								$timer_ready     = ! empty( $order->timer_ready );
								$timer_ends_ts   = ! empty( $order->timer_ends_ts ) ? (int) $order->timer_ends_ts : 0;
								$display_status  = $timer_ready ? 'timer_ready' : $status;
								$display_slug    = preg_replace( '/[^a-z0-9_]/', '', $display_status );
								$time_label      = SOM_Orders::format_time_in_step( $order->step_started_at );
								$product_id_card = ! empty( $order->product_id ) ? (int) $order->product_id : 0;
								$product_url     = $product_id_card > 0 ? SOM_Products::detail_url( $product_id_card ) : '';
								$product_label   = isset( $order->product_name ) ? (string) $order->product_name : '';
								$can_advance     = ! empty( $order->can_advance );
								$is_last_step    = ! empty( $order->is_last_step );
								$next_step_name  = (string) ( $order->next_step_name ?? '' );
								$search_blob     = strtolower(
									trim(
										(string) $order->buyer_name . ' ' .
										(string) $order->external_order_id . ' ' .
										$product_label . ' ' .
										$person_src
									)
								);
								$card_classes = 'som-board-card';
								if ( $is_pinned ) {
									$card_classes .= ' is-pinned';
								}
								if ( ! $can_advance ) {
									$card_classes .= ' is-locked';
								}
								if ( $timer_ready ) {
									$card_classes .= ' is-timer-ready';
								}
								?>
								<article
									class="<?php echo esc_attr( $card_classes ); ?>"
									data-som-order-id="<?php echo esc_attr( (string) $oid ); ?>"
									data-som-order-item-id="<?php echo esc_attr( (string) $item_id ); ?>"
									data-som-pinned="<?php echo $is_pinned ? '1' : '0'; ?>"
									data-som-channel="<?php echo esc_attr( (string) $order->channel_slug ); ?>"
									data-som-search="<?php echo esc_attr( $search_blob ); ?>"
									data-som-progress-status="<?php echo esc_attr( $status ); ?>"
									data-som-can-advance="<?php echo $can_advance ? '1' : '0'; ?>"
									data-som-is-last-step="<?php echo $is_last_step ? '1' : '0'; ?>"
									data-som-next-step-name="<?php echo esc_attr( $next_step_name ); ?>"
									data-som-order-ref="<?php echo esc_attr( (string) $order->external_order_id ); ?>"
									data-som-step-name="<?php echo esc_attr( (string) $order->current_step_name ); ?>"
									<?php if ( 'waiting_timer' === $status && $timer_ends_ts > 0 ) : ?>
										data-som-timer-ends-at="<?php echo esc_attr( (string) $timer_ends_ts ); ?>"
									<?php endif; ?>
									<?php echo $can_advance ? '' : ' data-som-locked="1"'; ?>
								>
									<div class="som-board-card-top">
										<button
											type="button"
											class="som-board-pin"
											data-som-board-pin
											aria-pressed="<?php echo $is_pinned ? 'true' : 'false'; ?>"
											title="<?php echo esc_attr( $is_pinned ? __( 'Unpin', 'order-machine' ) : __( 'Pin', 'order-machine' ) ); ?>"
										>★</button>
										<span class="som-badge som-badge-channel"><?php echo esc_html( (string) $order->channel_name ); ?></span>
										<span class="som-board-time" title="<?php echo esc_attr__( 'Time in current step', 'order-machine' ); ?>">
											<?php echo esc_html( $time_label ); ?>
										</span>
									</div>

									<div class="som-board-card-id">
										<a href="<?php echo esc_url( $detail_url ); ?>">
											<code><?php echo esc_html( (string) $order->external_order_id ); ?></code>
										</a>
										<?php if ( $item_id > 0 ) : ?>
											<span class="som-muted"> · <?php echo esc_html( sprintf( /* translators: %d: line id */ __( 'Line #%d', 'order-machine' ), $item_id ) ); ?></span>
										<?php else : ?>
											<span class="som-muted"> · <?php echo esc_html__( 'Legacy', 'order-machine' ); ?></span>
										<?php endif; ?>
									</div>

									<div class="som-board-card-buyer"><?php echo esc_html( (string) $order->buyer_name ); ?></div>

									<div class="som-board-card-product">
										<?php if ( $product_url ) : ?>
											<a href="<?php echo esc_url( $product_url ); ?>">
												<?php echo esc_html( $product_label ); ?>
											</a>
										<?php else : ?>
											<?php echo esc_html( $product_label !== '' ? $product_label : __( 'Unmatched', 'order-machine' ) ); ?>
										<?php endif; ?>
										<?php if ( ! empty( $order->quantity ) && (int) $order->quantity > 1 ) : ?>
											<span class="som-muted"> ×<?php echo esc_html( (string) (int) $order->quantity ); ?></span>
										<?php endif; ?>
									</div>

									<?php if ( '' !== $person ) : ?>
										<div class="som-personalisation-snippet"><?php echo esc_html( $person ); ?></div>
									<?php endif; ?>

									<div class="som-board-card-meta">
										<?php if ( SOM_Orders::BOARD_UNASSIGNED_KEY === $col_key ) : ?>
											<span class="som-badge som-badge-needs-workflow"><?php echo esc_html__( 'Unassigned', 'order-machine' ); ?></span>
										<?php else : ?>
											<span class="som-badge som-badge-open" data-som-card-step><?php echo esc_html( (string) $order->current_step_name ); ?></span>
										<?php endif; ?>
										<?php if ( $display_slug ) : ?>
											<span class="som-badge som-badge-step-<?php echo esc_attr( $display_slug ); ?>" data-som-card-status>
												<?php echo esc_html( SOM_Orders::progress_status_label( $display_status ) ); ?>
											</span>
										<?php endif; ?>
									</div>

									<?php if ( 'waiting_timer' === $status && $timer_ends_ts > 0 && ! $timer_ready ) : ?>
										<p class="som-board-timer description" data-som-board-countdown data-ends-at="<?php echo esc_attr( (string) $timer_ends_ts ); ?>">
											<?php echo esc_html__( 'Waiting (timer)', 'order-machine' ); ?>
										</p>
									<?php elseif ( $timer_ready ) : ?>
										<p class="som-board-timer som-timer-ready description" data-som-timer-ready-msg>
											<?php echo esc_html__( 'Timer ready', 'order-machine' ); ?>
										</p>
									<?php endif; ?>

									<?php if ( ! empty( $order->batch ) ) : ?>
										<?php $batch = $order->batch; ?>
										<div class="som-board-card-batch" data-som-card-batch>
											<a href="<?php echo esc_url( SOM_Batches::batch_url( (int) $batch->id ) ); ?>">
												<?php
												printf(
													/* translators: 1: current count, 2: batch size, 3: batch id */
													esc_html__( 'Batch #%3$d: %1$d of %2$d', 'order-machine' ),
													(int) $batch->item_count,
													max( 1, (int) $batch->group_batch_size ),
													(int) $batch->id
												);
												?>
											</a>
										</div>
									<?php else : ?>
										<div class="som-board-card-batch" data-som-card-batch hidden></div>
									<?php endif; ?>

									<div class="som-board-card-actions">
										<a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>">
											<?php echo esc_html__( 'View', 'order-machine' ); ?>
										</a>
									</div>
									<?php
									$needs_confirm = ! $can_advance
										&& 'in_progress' === $status
										&& SOM_Step_Confirmations::sanitize_kind(
											isset( $order->confirmation_kind ) ? $order->confirmation_kind : null
										);
									?>
									<?php if ( $needs_confirm ) : ?>
										<p class="som-board-confirm-hint description">
											<a href="<?php echo esc_url( $detail_url ); ?>">
												<?php echo esc_html__( 'Confirm on order', 'order-machine' ); ?>
											</a>
										</p>
									<?php endif; ?>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
				</div>

				<?php if ( $need_complete_zone ) : ?>
					<section
						class="som-board-column som-board-complete-zone"
						data-som-column-key="<?php echo esc_attr( SOM_Orders::BOARD_COMPLETE_KEY ); ?>"
						data-som-complete-zone
					>
						<header class="som-board-column-header">
							<div class="som-board-column-title">
								<strong><?php echo esc_html__( 'Ready to pack', 'order-machine' ); ?></strong>
								<span class="som-board-column-count">0</span>
							</div>
						</header>
						<div class="som-board-cards" data-som-sortable-list>
							<p class="som-muted som-board-complete-hint" data-som-complete-hint><?php echo esc_html__( 'Drop final make-step lines here to mark make-complete.', 'order-machine' ); ?></p>
						</div>
					</section>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
