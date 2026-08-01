<?php
/**
 * Workflow template + step editor admin view.
 *
 * @package OrderMachine
 *
 * @var object|null $template Template row or null when creating.
 * @var bool        $is_new   True when creating.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_new   = ! empty( $is_new );
$template = isset( $template ) ? $template : null;
$steps    = ( $template && ! empty( $template->steps ) ) ? $template->steps : array();
$in_use   = $template && ! empty( $template->in_use );
$products = ( $template && ! empty( $template->products ) ) ? $template->products : array();
$actions  = SOM_Workflows::local_action_choices();
$batch_groups = SOM_Batch_Groups::list_all();

/**
 * Render one step editor card.
 *
 * @param int         $index Form index.
 * @param object|null $step  Existing step or null for blank.
 * @return void
 */
$som_render_step = static function ( $index, $step = null ) use ( $actions, $batch_groups ) {
	$name     = $step ? (string) $step->name : '';
	$step_id  = $step ? (int) $step->id : 0;
	$manual   = $step ? (int) $step->requires_manual_confirm : 0;
	$timer    = SOM_Workflows::timer_for_display( $step && null !== $step->timer_seconds ? (int) $step->timer_seconds : null );
	$script   = SOM_Workflows::script_for_display( $step ? $step->script_config : null );
	$type     = $script['type'];
	$batch_id = $step && ! empty( $step->batch_group_id ) ? (int) $step->batch_group_id : 0;
	$prefix   = 'som_step[' . $index . ']';
	?>
	<div class="som-step-card" data-som-step>
		<div class="som-step-card-header">
			<span class="som-step-order-label"><?php echo esc_html__( 'Step', 'order-machine' ); ?></span>
			<input type="text" class="regular-text som-step-name" name="<?php echo esc_attr( $prefix ); ?>[name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr__( 'Step name', 'order-machine' ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( (string) $step_id ); ?>" />
			<span class="som-step-reorder">
				<button type="button" class="button som-step-up" aria-label="<?php echo esc_attr__( 'Move up', 'order-machine' ); ?>">&uarr;</button>
				<button type="button" class="button som-step-down" aria-label="<?php echo esc_attr__( 'Move down', 'order-machine' ); ?>">&darr;</button>
				<button type="button" class="button-link som-step-remove" aria-label="<?php echo esc_attr__( 'Remove step', 'order-machine' ); ?>">&times;</button>
			</span>
		</div>

		<div class="som-step-gates">
			<label class="som-step-gate">
				<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[requires_manual_confirm]" value="1" <?php checked( $manual ); ?> />
				<?php echo esc_html__( 'Requires manual confirm', 'order-machine' ); ?>
			</label>

			<div class="som-step-gate som-step-timer">
				<label><?php echo esc_html__( 'Timer', 'order-machine' ); ?></label>
				<input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( $prefix ); ?>[timer_value]" value="<?php echo esc_attr( $timer['value'] ); ?>" placeholder="—" />
				<select name="<?php echo esc_attr( $prefix ); ?>[timer_unit]">
					<option value="minutes" <?php selected( $timer['unit'], 'minutes' ); ?>><?php echo esc_html__( 'minutes', 'order-machine' ); ?></option>
					<option value="hours" <?php selected( $timer['unit'], 'hours' ); ?>><?php echo esc_html__( 'hours', 'order-machine' ); ?></option>
					<option value="days" <?php selected( $timer['unit'], 'days' ); ?>><?php echo esc_html__( 'days', 'order-machine' ); ?></option>
				</select>
				<span class="description"><?php echo esc_html__( 'Leave blank for no timer.', 'order-machine' ); ?></span>
			</div>

			<div class="som-step-gate som-step-batch" data-som-batch-gate>
				<label>
					<?php echo esc_html__( 'Batch group', 'order-machine' ); ?>
					<select name="<?php echo esc_attr( $prefix ); ?>[batch_group_id]" class="som-batch-group-select">
						<option value="0"><?php echo esc_html__( 'None', 'order-machine' ); ?></option>
						<?php foreach ( $batch_groups as $group ) : ?>
							<option value="<?php echo esc_attr( (string) (int) $group->id ); ?>" <?php selected( $batch_id, (int) $group->id ); ?>>
								<?php echo esc_html( (string) $group->display_name ); ?>
								(<?php echo esc_html( (string) $group->key ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<p class="description"><?php echo esc_html__( 'If set, this step is batch-only: clear manual, timer, and script gates. Shipping-label grouping is opt-in here.', 'order-machine' ); ?></p>
				<p class="som-batch-combo-warning notice notice-warning inline" data-som-batch-warning hidden>
					<?php echo esc_html__( 'Batch steps cannot also use manual, timer, or script gates. Save will be rejected until the other gates are cleared.', 'order-machine' ); ?>
				</p>
			</div>

			<div class="som-step-gate som-step-script" data-som-script>
				<label>
					<?php echo esc_html__( 'Script / API', 'order-machine' ); ?>
					<select name="<?php echo esc_attr( $prefix ); ?>[script_type]" class="som-script-type">
						<option value="none" <?php selected( $type, 'none' ); ?>><?php echo esc_html__( 'None', 'order-machine' ); ?></option>
						<option value="local" <?php selected( $type, 'local' ); ?>><?php echo esc_html__( 'Local action', 'order-machine' ); ?></option>
						<option value="api" <?php selected( $type, 'api' ); ?>><?php echo esc_html__( 'API', 'order-machine' ); ?></option>
						<option value="n8n" <?php selected( $type, 'n8n' ); ?>><?php echo esc_html__( 'n8n webhook', 'order-machine' ); ?></option>
					</select>
				</label>

				<div class="som-script-fields som-script-local" data-script-panel="local" <?php echo 'local' === $type ? '' : 'hidden'; ?>>
					<label>
						<?php echo esc_html__( 'Action', 'order-machine' ); ?>
						<select name="<?php echo esc_attr( $prefix ); ?>[script_action]">
							<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
							<?php foreach ( $actions as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $script['action'], $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<?php echo esc_html__( 'Params (JSON object, optional)', 'order-machine' ); ?>
						<textarea name="<?php echo esc_attr( $prefix ); ?>[script_params]" rows="3" class="large-text code"><?php echo esc_textarea( $script['params'] ); ?></textarea>
					</label>
				</div>

				<div class="som-script-fields som-script-api" data-script-panel="api" <?php echo 'api' === $type ? '' : 'hidden'; ?>>
					<label>
						<?php echo esc_html__( 'Method', 'order-machine' ); ?>
						<select name="<?php echo esc_attr( $prefix ); ?>[script_method]">
							<?php foreach ( array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $method ) : ?>
								<option value="<?php echo esc_attr( $method ); ?>" <?php selected( $script['method'], $method ); ?>><?php echo esc_html( $method ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<?php echo esc_html__( 'URL', 'order-machine' ); ?>
						<input type="url" class="large-text" name="<?php echo esc_attr( $prefix ); ?>[script_url]" value="<?php echo esc_attr( $script['url'] ); ?>" />
					</label>
					<label>
						<?php echo esc_html__( 'Body template (JSON)', 'order-machine' ); ?>
						<textarea name="<?php echo esc_attr( $prefix ); ?>[script_body]" rows="4" class="large-text code"><?php echo esc_textarea( $script['body'] ); ?></textarea>
					</label>
				</div>

				<div class="som-script-fields som-script-n8n" data-script-panel="n8n" <?php echo 'n8n' === $type ? '' : 'hidden'; ?>>
					<label>
						<?php echo esc_html__( 'Webhook URL', 'order-machine' ); ?>
						<input type="url" class="large-text" name="<?php echo esc_attr( $prefix ); ?>[script_webhook]" value="<?php echo esc_attr( $script['webhook'] ); ?>" />
					</label>
					<label>
						<?php echo esc_html__( 'Payload template (JSON)', 'order-machine' ); ?>
						<textarea name="<?php echo esc_attr( $prefix ); ?>[script_payload]" rows="4" class="large-text code"><?php echo esc_textarea( $script['payload'] ); ?></textarea>
					</label>
				</div>

				<label class="som-script-raw-toggle">
					<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[script_raw_mode]" value="1" class="som-script-raw-mode" <?php checked( ! empty( $script['raw_mode'] ) ); ?> />
					<?php echo esc_html__( 'Use raw JSON for script_config', 'order-machine' ); ?>
				</label>
				<div class="som-script-raw" data-script-raw <?php echo ! empty( $script['raw_mode'] ) ? '' : 'hidden'; ?>>
					<textarea name="<?php echo esc_attr( $prefix ); ?>[script_raw_json]" rows="6" class="large-text code"><?php echo esc_textarea( $script['raw_json'] ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'When checked, this JSON is stored as-is (must include type: local, api, or n8n). Form fields above are ignored.', 'order-machine' ); ?></p>
				</div>
			</div>
		</div>
	</div>
	<?php
};
?>
<div class="wrap som-catalog-wrap som-workflow-editor">
	<h1>
		<?php
		echo $is_new
			? esc_html__( 'Add workflow template', 'order-machine' )
			: esc_html__( 'Edit workflow template', 'order-machine' );
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( SOM_Workflows::list_url() ); ?>">&larr; <?php echo esc_html__( 'Back to workflows', 'order-machine' ); ?></a>
	</p>

	<?php if ( $in_use ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php echo esc_html__( 'Template in use.', 'order-machine' ); ?></strong>
				<?php
				printf(
					/* translators: %d: product count */
					esc_html( _n(
						'%d product is assigned this workflow. You can still edit steps; changes apply to new order assignments.',
						'%d products are assigned this workflow. You can still edit steps; changes apply to new order assignments.',
						count( $products ),
						'order-machine'
					) ),
					count( $products )
				);
				?>
			</p>
			<?php if ( ! empty( $products ) ) : ?>
				<ul class="ul-disc">
					<?php foreach ( $products as $product ) : ?>
						<li>
							<a href="<?php echo esc_url( SOM_Products::detail_url( (int) $product->id ) ); ?>">
								<?php echo esc_html( (string) $product->name ); ?>
							</a>
							<?php if ( empty( $product->is_active ) ) : ?>
								<span class="som-muted">(<?php echo esc_html__( 'inactive', 'order-machine' ); ?>)</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=som-workflows' ) ); ?>" class="som-workflow-form" id="som-workflow-form">
		<?php wp_nonce_field( 'som_save_workflow', 'som_workflow_nonce' ); ?>
		<input type="hidden" name="som_save_workflow" value="1" />
		<?php if ( ! $is_new && $template ) : ?>
			<input type="hidden" name="template_id" value="<?php echo esc_attr( (string) (int) $template->id ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_workflow_name"><?php echo esc_html__( 'Name', 'order-machine' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="som_workflow_name" name="som_workflow_name" value="<?php echo esc_attr( $template ? (string) $template->name : '' ); ?>" required />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_workflow_description"><?php echo esc_html__( 'Description', 'order-machine' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="3" id="som_workflow_description" name="som_workflow_description"><?php echo esc_textarea( $template && $template->description ? (string) $template->description : '' ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Status', 'order-machine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="som_workflow_is_active" value="1" <?php checked( ! $template || (int) $template->is_active ); ?> <?php disabled( $in_use && $template && (int) $template->is_active ); ?> />
						<?php echo esc_html__( 'Active', 'order-machine' ); ?>
					</label>
					<?php if ( $in_use && $template && (int) $template->is_active ) : ?>
						<input type="hidden" name="som_workflow_is_active" value="1" />
						<p class="description"><?php echo esc_html__( 'Deactivate is blocked while products use this template — reassign them first.', 'order-machine' ); ?></p>
					<?php else : ?>
						<p class="description"><?php echo esc_html__( 'Inactive templates are hidden from new product assignments.', 'order-machine' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'Steps', 'order-machine' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Steps run in order. Use manual confirm, timer, and/or script — or assign a batch group (batch-only; cannot combine with other gates).', 'order-machine' ); ?></p>

		<div id="som-step-list" class="som-step-list">
			<?php
			$index = 0;
			foreach ( $steps as $step ) {
				++$index;
				$som_render_step( $index, $step );
			}
			if ( 0 === $index ) {
				++$index;
				$som_render_step( $index, null );
			}
			?>
		</div>

		<p>
			<button type="button" class="button" id="som-step-add"><?php echo esc_html__( 'Add step', 'order-machine' ); ?></button>
		</p>

		<?php
		$goal_rows        = ( ! $is_new && $template ) ? SOM_Workflow_Material_Goals::list_for_workflow( (int) $template->id ) : array();
		$material_options = SOM_Materials::list_active();
		$goal_blank       = max( 1, 2 - count( $goal_rows ) );
		$goal_index       = 0;
		?>
		<h2><?php echo esc_html__( 'Material cost goals', 'order-machine' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Optional per-material cost ceilings for this workflow. Alerts fire when a material’s weighted average approaches or exceeds the goal. If a product’s workflow is reassigned later, its alerts follow the new workflow’s goals.', 'order-machine' ); ?>
		</p>
		<table class="widefat striped som-goal-table" id="som-goal-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Material', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Goal unit cost', 'order-machine' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Warn at % of goal', 'order-machine' ); ?></th>
					<th scope="col" class="som-recipe-actions-col"></th>
				</tr>
			</thead>
			<tbody id="som-goal-rows">
				<?php foreach ( $goal_rows as $goal ) : ?>
					<?php ++$goal_index; ?>
					<tr class="som-goal-row">
						<td>
							<select name="som_goal_material[<?php echo esc_attr( (string) $goal_index ); ?>]">
								<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
								<?php foreach ( $material_options as $material ) : ?>
									<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>" <?php selected( (int) $goal->material_id, (int) $material->id ); ?>>
										<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<input type="number" step="0.0001" min="0.0001" name="som_goal_cost[<?php echo esc_attr( (string) $goal_index ); ?>]" value="<?php echo esc_attr( (string) $goal->goal_unit_cost ); ?>" class="small-text" />
						</td>
						<td>
							<input type="number" step="0.01" min="0.01" max="100" name="som_goal_threshold[<?php echo esc_attr( (string) $goal_index ); ?>]" value="<?php echo esc_attr( (string) $goal->warning_threshold_percent ); ?>" class="small-text" />
						</td>
						<td class="som-recipe-actions-col">
							<button type="button" class="button-link som-goal-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php for ( $i = 0; $i < $goal_blank; $i++ ) : ?>
					<?php ++$goal_index; ?>
					<tr class="som-goal-row">
						<td>
							<select name="som_goal_material[<?php echo esc_attr( (string) $goal_index ); ?>]">
								<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
								<?php foreach ( $material_options as $material ) : ?>
									<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>">
										<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<input type="number" step="0.0001" min="0.0001" name="som_goal_cost[<?php echo esc_attr( (string) $goal_index ); ?>]" value="" class="small-text" />
						</td>
						<td>
							<input type="number" step="0.01" min="0.01" max="100" name="som_goal_threshold[<?php echo esc_attr( (string) $goal_index ); ?>]" value="90" class="small-text" />
						</td>
						<td class="som-recipe-actions-col">
							<button type="button" class="button-link som-goal-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
						</td>
					</tr>
				<?php endfor; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button" id="som-goal-add-row"><?php echo esc_html__( 'Add goal row', 'order-machine' ); ?></button>
		</p>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save template', 'order-machine' ); ?></button>
		</p>
	</form>
</div>

<template id="som-step-card-template">
	<?php $som_render_step( '__INDEX__', null ); ?>
</template>

<template id="som-goal-row-template">
	<tr class="som-goal-row">
		<td>
			<select name="som_goal_material[__INDEX__]">
				<option value=""><?php echo esc_html__( '— Select —', 'order-machine' ); ?></option>
				<?php foreach ( $material_options as $material ) : ?>
					<option value="<?php echo esc_attr( (string) (int) $material->id ); ?>">
						<?php echo esc_html( (string) $material->name ); ?> (<?php echo esc_html( (string) $material->unit ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<input type="number" step="0.0001" min="0.0001" name="som_goal_cost[__INDEX__]" value="" class="small-text" />
		</td>
		<td>
			<input type="number" step="0.01" min="0.01" max="100" name="som_goal_threshold[__INDEX__]" value="90" class="small-text" />
		</td>
		<td class="som-recipe-actions-col">
			<button type="button" class="button-link som-goal-remove" aria-label="<?php echo esc_attr__( 'Remove row', 'order-machine' ); ?>">&times;</button>
		</td>
	</tr>
</template>
