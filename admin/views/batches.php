<?php
/**
 * Batches admin list (expandable) + batch groups editor.
 *
 * @package OrderMachine
 *
 * @var array{batches: array<int, object>, total: int, pages: int, paged: int} $batch_query
 * @var array<int, object> $batch_groups
 * @var int                $focus_batch_id
 * @var string             $filter_status
 * @var int                $filter_group_id
 * @var bool               $include_done
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$batch_query     = isset( $batch_query ) ? $batch_query : array( 'batches' => array(), 'total' => 0, 'pages' => 1, 'paged' => 1 );
$batch_groups    = isset( $batch_groups ) ? $batch_groups : array();
$focus_batch_id  = isset( $focus_batch_id ) ? (int) $focus_batch_id : 0;
$filter_status   = isset( $filter_status ) ? (string) $filter_status : '';
$filter_group_id = isset( $filter_group_id ) ? (int) $filter_group_id : 0;
$include_done    = ! empty( $include_done );
$batches         = $batch_query['batches'];
$total           = (int) $batch_query['total'];
$pages           = (int) $batch_query['pages'];
$paged           = (int) $batch_query['paged'];

$status_options = array_merge(
	array( '' => __( 'Open batches', 'order-machine' ) ),
	SOM_Batches::status_labels()
);
?>
<div class="wrap som-catalog-wrap som-batches-wrap">
	<h1><?php echo esc_html__( 'Batches', 'order-machine' ); ?></h1>
	<hr class="wp-header-end" />

	<h2><?php echo esc_html__( 'Batch groups', 'order-machine' ); ?></h2>
	<p class="description">
		<?php echo esc_html__( 'Key and action type are fixed. Edit display name and target batch size here.', 'order-machine' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( SOM_Batches::list_url() ); ?>" class="som-batch-groups-form">
		<?php wp_nonce_field( 'som_save_batch_groups', 'som_batches_nonce' ); ?>
		<input type="hidden" name="som_save_batch_groups" value="1" />
		<table class="widefat striped som-catalog-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Key', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Display name', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Action', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Batch size', 'order-machine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $batch_groups ) ) : ?>
					<tr>
						<td colspan="4"><?php echo esc_html__( 'No batch groups found.', 'order-machine' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $batch_groups as $group ) : ?>
						<tr>
							<td>
								<code><?php echo esc_html( (string) $group->key ); ?></code>
								<input type="hidden" name="som_group_id[]" value="<?php echo esc_attr( (string) (int) $group->id ); ?>" />
							</td>
							<td>
								<input type="text" class="regular-text" name="som_group_display_name[<?php echo esc_attr( (string) (int) $group->id ); ?>]" value="<?php echo esc_attr( (string) $group->display_name ); ?>" required />
							</td>
							<td><?php echo esc_html( (string) $group->action_type ); ?></td>
							<td>
								<input type="number" class="small-text" min="1" step="1" name="som_group_batch_size[<?php echo esc_attr( (string) (int) $group->id ); ?>]" value="<?php echo esc_attr( (string) (int) $group->batch_size ); ?>" required />
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php if ( ! empty( $batch_groups ) ) : ?>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save groups', 'order-machine' ); ?></button>
			</p>
		<?php endif; ?>
	</form>

	<h2><?php echo esc_html__( 'Open batches', 'order-machine' ); ?></h2>
	<form method="get" class="som-catalog-filters">
		<input type="hidden" name="page" value="som-batches" />
		<select name="som_status">
			<?php foreach ( $status_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $filter_status, (string) $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="batch_group_id">
			<option value="0"><?php echo esc_html__( 'All groups', 'order-machine' ); ?></option>
			<?php foreach ( $batch_groups as $group ) : ?>
				<option value="<?php echo esc_attr( (string) (int) $group->id ); ?>" <?php selected( $filter_group_id, (int) $group->id ); ?>>
					<?php echo esc_html( (string) $group->display_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<label>
			<input type="checkbox" name="include_done" value="1" <?php checked( $include_done ); ?> />
			<?php echo esc_html__( 'Include done', 'order-machine' ); ?>
		</label>
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'order-machine' ); ?></button>
	</form>

	<p class="som-catalog-count">
		<?php
		printf(
			/* translators: %d: batch count */
			esc_html( _n( '%d batch', '%d batches', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<?php if ( empty( $batches ) ) : ?>
		<p class="som-muted"><?php echo esc_html__( 'No batches match these filters.', 'order-machine' ); ?></p>
	<?php else : ?>
		<div class="som-batch-list" data-som-batch-list data-focus-batch="<?php echo esc_attr( (string) $focus_batch_id ); ?>">
			<?php foreach ( $batches as $batch ) : ?>
				<?php
				$batch_id   = (int) $batch->id;
				$count      = (int) $batch->item_count;
				$size       = max( 1, (int) $batch->group_batch_size );
				$status     = (string) $batch->status;
				$action     = (string) $batch->group_action_type;
				$is_focus   = $focus_batch_id === $batch_id;
				$members    = SOM_Batches::get_items_with_orders( $batch_id );
				$can_release = ( 'collecting' === $status );
				$can_done    = ( 'ready' === $status && 'manual_confirm' === $action );
				$can_retry   = ( 'error' === $status && 'script' === $action );
				?>
				<div class="som-batch-card<?php echo $is_focus ? ' is-focus' : ''; ?>" id="som-batch-<?php echo esc_attr( (string) $batch_id ); ?>" data-som-batch data-batch-id="<?php echo esc_attr( (string) $batch_id ); ?>">
					<div class="som-batch-card-header">
						<button type="button" class="button-link som-batch-toggle" aria-expanded="<?php echo $is_focus ? 'true' : 'false'; ?>" data-som-batch-toggle>
							<span class="som-batch-toggle-icon" aria-hidden="true"><?php echo $is_focus ? '▼' : '▶'; ?></span>
							<strong>#<?php echo esc_html( (string) $batch_id ); ?></strong>
							<?php echo esc_html( (string) $batch->group_name ); ?>
							<span class="som-badge som-badge-batch-<?php echo esc_attr( sanitize_html_class( $status ) ); ?>">
								<?php echo esc_html( SOM_Batches::status_label( $status ) ); ?>
							</span>
							<span class="som-batch-count">
								<?php
								printf(
									/* translators: 1: current count, 2: batch size */
									esc_html__( '%1$d of %2$d', 'order-machine' ),
									$count,
									$size
								);
								?>
							</span>
							<?php if ( ! empty( $batch->released_manually ) ) : ?>
								<span class="description"><?php echo esc_html__( 'Released manually', 'order-machine' ); ?></span>
							<?php endif; ?>
						</button>
						<div class="som-batch-actions">
							<?php if ( $can_release ) : ?>
								<form method="post" action="<?php echo esc_url( SOM_Batches::list_url() ); ?>" class="som-inline-form">
									<?php wp_nonce_field( 'som_batch_action', 'som_batches_nonce' ); ?>
									<input type="hidden" name="som_batch_id" value="<?php echo esc_attr( (string) $batch_id ); ?>" />
									<input type="hidden" name="som_batch_release" value="1" />
									<?php submit_button( __( 'Release batch now', 'order-machine' ), 'secondary', 'submit', false ); ?>
								</form>
							<?php endif; ?>
							<?php if ( $can_done ) : ?>
								<form method="post" action="<?php echo esc_url( SOM_Batches::list_url() ); ?>" class="som-inline-form">
									<?php wp_nonce_field( 'som_batch_action', 'som_batches_nonce' ); ?>
									<input type="hidden" name="som_batch_id" value="<?php echo esc_attr( (string) $batch_id ); ?>" />
									<input type="hidden" name="som_batch_mark_done" value="1" />
									<?php submit_button( __( 'Mark batch done', 'order-machine' ), 'primary', 'submit', false ); ?>
								</form>
							<?php endif; ?>
							<?php if ( $can_retry ) : ?>
								<form method="post" action="<?php echo esc_url( SOM_Batches::list_url() ); ?>" class="som-inline-form">
									<?php wp_nonce_field( 'som_batch_action', 'som_batches_nonce' ); ?>
									<input type="hidden" name="som_batch_id" value="<?php echo esc_attr( (string) $batch_id ); ?>" />
									<input type="hidden" name="som_batch_retry" value="1" />
									<?php submit_button( __( 'Retry', 'order-machine' ), 'secondary', 'submit', false ); ?>
								</form>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( ! empty( $batch->last_error ) && in_array( $status, array( 'error', 'processing' ), true ) ) : ?>
						<p class="som-script-error"><?php echo esc_html( (string) $batch->last_error ); ?></p>
						<?php if ( (int) $batch->retry_count > 0 ) : ?>
							<p class="description">
								<?php
								printf(
									/* translators: %d: retry count */
									esc_html__( 'Attempts so far: %d', 'order-machine' ),
									(int) $batch->retry_count
								);
								?>
								<?php if ( ! empty( $batch->retry_after ) && 'processing' === $status ) : ?>
									—
									<?php
									printf(
										/* translators: %s: datetime */
										esc_html__( 'next retry after %s UTC', 'order-machine' ),
										esc_html( (string) $batch->retry_after )
									);
									?>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					<?php endif; ?>

					<div class="som-batch-members" data-som-batch-members <?php echo $is_focus ? '' : 'hidden'; ?>>
						<table class="widefat striped">
							<thead>
								<tr>
									<th scope="col"><?php echo esc_html__( 'Order', 'order-machine' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Buyer', 'order-machine' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Address', 'order-machine' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $members ) ) : ?>
									<tr>
										<td colspan="3"><?php echo esc_html__( 'No orders in this batch.', 'order-machine' ); ?></td>
									</tr>
								<?php else : ?>
									<?php foreach ( $members as $member ) : ?>
										<?php
										$address = SOM_Orders::format_address( $member->shipping_address );
										?>
										<tr class="som-batch-member">
											<td>
												<a href="<?php echo esc_url( SOM_Orders::detail_url( (int) $member->order_id ) ); ?>">
													<?php echo esc_html( (string) $member->external_order_id ); ?>
												</a>
											</td>
											<td><?php echo esc_html( (string) $member->buyer_name ); ?></td>
											<td>
												<button type="button" class="button-link som-batch-address-toggle" data-som-address-toggle aria-expanded="false">
													<?php echo esc_html__( 'Show address', 'order-machine' ); ?>
												</button>
												<pre class="som-batch-address" hidden><?php echo esc_html( $address ? $address : '—' ); ?></pre>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
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
