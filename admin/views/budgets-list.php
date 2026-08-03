<?php
/**
 * Budgets list admin view.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$status = isset( $_GET['som_status'] ) ? sanitize_key( wp_unslash( $_GET['som_status'] ) ) : 'active';
$type   = isset( $_GET['som_type'] ) ? sanitize_key( wp_unslash( $_GET['som_type'] ) ) : '';
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

$is_active = '';
if ( 'active' === $status ) {
	$is_active = 1;
} elseif ( 'inactive' === $status ) {
	$is_active = 0;
} elseif ( 'all' === $status ) {
	$is_active = '';
} else {
	$status    = 'active';
	$is_active = 1;
}

$result  = SOM_Budgets::query(
	array(
		's'         => $search,
		'type'      => in_array( $type, array( 'material', 'manual' ), true ) ? $type : '',
		'is_active' => $is_active,
		'paged'     => $paged,
	)
);
$budgets = $result['budgets'];
$total   = $result['total'];
$pages   = $result['pages'];
$paged   = $result['paged'];

$status_options = array(
	'active'   => __( 'Active', 'order-machine' ),
	'inactive' => __( 'Inactive', 'order-machine' ),
	'all'      => __( 'All', 'order-machine' ),
);
$type_options   = array(
	''         => __( 'All types', 'order-machine' ),
	'material' => __( 'Material', 'order-machine' ),
	'manual'   => __( 'Manual', 'order-machine' ),
);
?>
<div class="wrap som-catalog-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Budgets', 'order-machine' ); ?></h1>
	<a href="<?php echo esc_url( SOM_Budgets::detail_url( 'new' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add budget', 'order-machine' ); ?>
	</a>
	<hr class="wp-header-end" />

	<form method="get" class="som-catalog-filters">
		<input type="hidden" name="page" value="som-budgets" />
		<label class="screen-reader-text" for="som-budget-search"><?php echo esc_html__( 'Search budgets', 'order-machine' ); ?></label>
		<input type="search" id="som-budget-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search name…', 'order-machine' ); ?>" />
		<select name="som_status">
			<?php foreach ( $status_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="som_type">
			<?php foreach ( $type_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'order-machine' ); ?></button>
	</form>

	<p class="som-catalog-count">
		<?php
		printf(
			/* translators: %d: budget count */
			esc_html( _n( '%d budget', '%d budgets', $total, 'order-machine' ) ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped som-catalog-table">
		<thead>
			<tr>
				<th scope="col" class="column-name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Type', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Funding', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Balance', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Target reserve', 'order-machine' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $budgets ) ) : ?>
				<tr>
					<td colspan="6"><?php echo esc_html__( 'No budgets found.', 'order-machine' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $budgets as $budget ) : ?>
					<?php
					$overspent = SOM_Budgets::is_overspent( $budget );
					$low       = SOM_Budgets::is_low_balance( $budget );
					?>
					<tr>
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( SOM_Budgets::detail_url( (int) $budget->id ) ); ?>">
									<?php echo esc_html( (string) $budget->name ); ?>
								</a>
							</strong>
							<?php if ( $overspent ) : ?>
								<br /><span class="som-badge som-badge-overspent"><?php echo esc_html__( 'Overspent', 'order-machine' ); ?></span>
							<?php elseif ( $low ) : ?>
								<br /><span class="som-badge som-badge-low-balance"><?php echo esc_html__( 'Low balance', 'order-machine' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( SOM_Budgets::type_label( (string) $budget->type ) ); ?></td>
						<td>
							<?php echo esc_html( SOM_Budgets::funding_method_label( (string) $budget->funding_method ) ); ?>
							<?php if ( 'manual' === $budget->type && null !== $budget->funding_value && '' !== $budget->funding_value ) : ?>
								<span class="som-muted">
									(<?php echo esc_html( number_format_i18n( (float) $budget->funding_value, 2 ) ); ?><?php echo in_array( $budget->funding_method, array( 'percent_of_price', 'percent_of_profit' ), true ) ? '%' : ''; ?>)
								</span>
							<?php endif; ?>
						</td>
						<td>
							£<?php echo esc_html( number_format_i18n( (float) $budget->current_balance, 2 ) ); ?>
						</td>
						<td>
							<?php
							echo null !== $budget->target_reserve_amount && '' !== $budget->target_reserve_amount
								? '£' . esc_html( number_format_i18n( (float) $budget->target_reserve_amount, 2 ) )
								: '<span class="som-muted">—</span>';
							?>
						</td>
						<td>
							<?php if ( (int) $budget->is_active ) : ?>
								<span class="som-badge som-badge-active"><?php echo esc_html__( 'Active', 'order-machine' ); ?></span>
							<?php else : ?>
								<span class="som-badge som-badge-inactive"><?php echo esc_html__( 'Inactive', 'order-machine' ); ?></span>
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
						/* translators: %d: item count */
						esc_html( _n( '%d item', '%d items', $total, 'order-machine' ) ),
						(int) $total
					);
					?>
				</span>
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $pages,
							'current'   => $paged,
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
