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
$batch_groups = SOM_Batch_Groups::list_all();

/**
 * Render one step editor card.
 *
 * @param int         $index Form index.
 * @param object|null $step  Existing step or null for blank.
 * @return void
 */
$som_render_step = static function ( $index, $step = null ) use ( $batch_groups ) {
	$name     = $step ? (string) $step->name : '';
	$step_id  = $step ? (int) $step->id : 0;
	// New / blank step cards default to manual confirm (UP7-S1) so they do not auto-complete by accident.
	$manual   = $step ? (int) $step->requires_manual_confirm : 1;
	$timer    = SOM_Workflows::timer_for_display( $step && null !== $step->timer_seconds ? (int) $step->timer_seconds : null );
	$planned  = SOM_Workflows::planned_for_display( $step && isset( $step->planned_seconds ) && null !== $step->planned_seconds ? (int) $step->planned_seconds : null );
	$batch_id = $step && ! empty( $step->batch_group_id ) ? (int) $step->batch_group_id : 0;
	$confirm  = $step && ! empty( $step->confirmation_kind )
		? SOM_Step_Confirmations::sanitize_kind( $step->confirmation_kind )
		: null;
	$instructions = $step && ! empty( $step->instructions ) ? (string) $step->instructions : '';
	$command_template = $step && ! empty( $step->command_template ) ? (string) $step->command_template : '';
	$task_rows = $step && ! empty( $step->task_list_json )
		? SOM_Step_Tasks::decode_list( $step->task_list_json )
		: array();
	if ( empty( $task_rows ) ) {
		$task_rows = array(
			array(
				'id'    => '',
				'label' => '',
			),
		);
	}
	$has_legacy_script = $step && SOM_Script_Dispatch::has_script( $step );
	$prefix   = 'som_step[' . $index . ']';
	$confirm_choices = SOM_Step_Confirmations::kind_choices();
	$placeholder_help = '{{order_number}}, {{house_number}}, {{street_name}}, {{style}}, {{buyer_name}}, {{buyer_full_name}}, {{product_name}}, {{personalisation}}';
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

		<div class="som-step-instructions-field" data-som-md-editor>
			<label class="som-md-field-label" for="som-md-instructions-<?php echo esc_attr( (string) $index ); ?>"><?php echo esc_html__( 'Instructions (default)', 'order-machine' ); ?></label>
			<?php SOM_Step_Instructions::render_editor_toolbar(); ?>
			<textarea id="som-md-instructions-<?php echo esc_attr( (string) $index ); ?>" class="large-text som-md-source" rows="3" name="<?php echo esc_attr( $prefix ); ?>[instructions]" maxlength="<?php echo esc_attr( (string) SOM_Step_Instructions::MAX_LENGTH ); ?>" placeholder="<?php echo esc_attr__( 'Optional Markdown guidance for this step…', 'order-machine' ); ?>"><?php echo esc_textarea( $instructions ); ?></textarea>
			<p class="description"><?php echo esc_html__( 'Shown on order detail for every product using this template, unless a product sets its own override. Leave blank for no default.', 'order-machine' ); ?></p>
			<?php SOM_Step_Instructions::render_editor_help(); ?>
		</div>

		<div class="som-step-tasks-field" data-som-task-list>
			<label class="som-step-tasks-label"><?php echo esc_html__( 'Tasks (optional)', 'order-machine' ); ?></label>
			<p class="description"><?php echo esc_html__( 'Optional checklist for operators. Does not block Mark done.', 'order-machine' ); ?></p>
			<div class="som-task-rows" data-som-task-rows>
				<?php foreach ( $task_rows as $t_index => $task ) : ?>
					<div class="som-task-row" data-som-task-row>
						<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[tasks][<?php echo esc_attr( (string) $t_index ); ?>][id]" value="<?php echo esc_attr( (string) ( $task['id'] ?? '' ) ); ?>" />
						<input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[tasks][<?php echo esc_attr( (string) $t_index ); ?>][label]" value="<?php echo esc_attr( (string) ( $task['label'] ?? '' ) ); ?>" maxlength="<?php echo esc_attr( (string) SOM_Step_Tasks::MAX_LABEL_LENGTH ); ?>" placeholder="<?php echo esc_attr__( 'Task label…', 'order-machine' ); ?>" />
						<button type="button" class="button-link som-task-remove" aria-label="<?php echo esc_attr__( 'Remove task', 'order-machine' ); ?>">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button-link" data-som-task-add><?php echo esc_html__( 'Add task', 'order-machine' ); ?></button>
			</p>
			<p class="description"><?php echo esc_html__( 'Leave empty for no Tasks panel on the order. Products may override the whole list (full replace). Pack uses template tasks only.', 'order-machine' ); ?></p>
		</div>

		<div class="som-step-gates">
			<label class="som-step-gate">
				<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[requires_manual_confirm]" value="1" <?php checked( $manual ); ?> data-som-manual-confirm />
				<?php echo esc_html__( 'Requires manual confirm', 'order-machine' ); ?>
			</label>
			<p class="som-zero-gate-warning notice notice-warning inline" data-som-zero-gate-warning hidden>
				<?php echo esc_html__( 'This step will auto-complete when entered (no manual confirm, checklist, timer, copy command, or batch).', 'order-machine' ); ?>
			</p>

			<?php if ( $has_legacy_script ) : ?>
				<div class="som-step-gate som-step-script-legacy-warning notice notice-warning inline" data-som-legacy-script-warning>
					<p>
						<strong><?php echo esc_html__( 'Legacy script_config (disabled)', 'order-machine' ); ?></strong>
						<?php echo esc_html__( 'Local / API / n8n script steps are removed. This stored config is ignored at runtime and will be cleared when you save. Use Copy command instead.', 'order-machine' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="som-step-gate som-step-copy-command" data-som-copy-command-gate>
				<label class="som-copy-command-label" for="som-command-template-<?php echo esc_attr( (string) $index ); ?>"><?php echo esc_html__( 'Copy command template', 'order-machine' ); ?></label>
				<?php SOM_Copy_Command::render_placeholder_picker(); ?>
				<textarea
					id="som-command-template-<?php echo esc_attr( (string) $index ); ?>"
					class="large-text code som-command-template"
					rows="5"
					name="<?php echo esc_attr( $prefix ); ?>[command_template]"
					maxlength="<?php echo esc_attr( (string) SOM_Copy_Command::MAX_TEMPLATE_LENGTH ); ?>"
					placeholder="<?php echo esc_attr__( '/make-sticker-order Order number: {{order_number}}', 'order-machine' ); ?>"
				><?php echo esc_textarea( $command_template ); ?></textarea>
				<p class="description">
					<?php
					printf(
						/* translators: %s: placeholder list */
						esc_html__( 'Shown on the make line with a Copy button (not run by WordPress). Placeholders: %s. Cannot combine with timer or batch; Mark done stays manual.', 'order-machine' ),
						esc_html( $placeholder_help )
					);
					?>
				</p>
				<p class="som-copy-combo-warning notice notice-warning inline" data-som-copy-warning hidden>
					<?php echo esc_html__( 'Copy-command steps cannot also use timer or batch gates. Save will be rejected until those are cleared.', 'order-machine' ); ?>
				</p>
			</div>

			<div class="som-step-gate som-step-confirmation" data-som-confirmation-gate>
				<label>
					<?php echo esc_html__( 'Confirmation checklist', 'order-machine' ); ?>
					<select name="<?php echo esc_attr( $prefix ); ?>[confirmation_kind]" class="som-confirmation-kind">
						<option value=""><?php echo esc_html__( 'None', 'order-machine' ); ?></option>
						<?php foreach ( $confirm_choices as $kind_key => $kind_label ) : ?>
							<option value="<?php echo esc_attr( $kind_key ); ?>" <?php selected( $confirm, $kind_key ); ?>>
								<?php echo esc_html( $kind_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<p class="description"><?php echo esc_html__( 'If set, Mark done stays locked until the checklist on the order is completed. Cannot combine with timer or batch.', 'order-machine' ); ?></p>
				<p class="som-confirm-combo-warning notice notice-warning inline" data-som-confirm-warning hidden>
					<?php echo esc_html__( 'Confirmation steps cannot also use timer or batch gates. Save will be rejected until those are cleared.', 'order-machine' ); ?>
				</p>
			</div>

			<div class="som-step-gate som-step-timer">
				<label><?php echo esc_html__( 'Timer', 'order-machine' ); ?></label>
				<input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( $prefix ); ?>[timer_value]" value="<?php echo esc_attr( $timer['value'] ); ?>" placeholder="—" />
				<select name="<?php echo esc_attr( $prefix ); ?>[timer_unit]">
					<option value="minutes" <?php selected( $timer['unit'], 'minutes' ); ?>><?php echo esc_html__( 'minutes', 'order-machine' ); ?></option>
					<option value="hours" <?php selected( $timer['unit'], 'hours' ); ?>><?php echo esc_html__( 'hours', 'order-machine' ); ?></option>
					<option value="days" <?php selected( $timer['unit'], 'days' ); ?>><?php echo esc_html__( 'days', 'order-machine' ); ?></option>
				</select>
				<span class="description"><?php echo esc_html__( 'Hard gate — leave blank for no timer.', 'order-machine' ); ?></span>
			</div>

			<div class="som-step-gate som-step-planned">
				<label><?php echo esc_html__( 'Planned duration', 'order-machine' ); ?></label>
				<input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( $prefix ); ?>[planned_value]" value="<?php echo esc_attr( $planned['value'] ); ?>" placeholder="—" data-som-planned-value />
				<select name="<?php echo esc_attr( $prefix ); ?>[planned_unit]" data-som-planned-unit>
					<option value="minutes" <?php selected( $planned['unit'], 'minutes' ); ?>><?php echo esc_html__( 'minutes', 'order-machine' ); ?></option>
					<option value="hours" <?php selected( $planned['unit'], 'hours' ); ?>><?php echo esc_html__( 'hours', 'order-machine' ); ?></option>
					<option value="days" <?php selected( $planned['unit'], 'days' ); ?>><?php echo esc_html__( 'days', 'order-machine' ); ?></option>
				</select>
				<span class="description"><?php echo esc_html__( 'Target wall-clock for reporting (not a gate). Separate from timer.', 'order-machine' ); ?></span>
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
				<p class="description"><?php echo esc_html__( 'If set, this step is batch-only: clear manual and timer gates. Shipping-label grouping is opt-in here. Script batch groups are disabled (soft-fail on release).', 'order-machine' ); ?></p>
				<p class="som-batch-combo-warning notice notice-warning inline" data-som-batch-warning hidden>
					<?php echo esc_html__( 'Batch steps cannot also use manual, timer, confirmation, or copy-command gates. Save will be rejected until the other gates are cleared.', 'order-machine' ); ?>
				</p>
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
				<th scope="row"><label for="som_workflow_kind"><?php echo esc_html__( 'Kind', 'order-machine' ); ?></label></th>
				<td>
					<?php
					$current_kind = $template && ! empty( $template->kind )
						? SOM_Workflows::sanitize_kind( $template->kind )
						: 'make';
					?>
					<select id="som_workflow_kind" name="som_workflow_kind" <?php disabled( $in_use && 'make' === $current_kind ); ?>>
						<?php foreach ( SOM_Workflows::kind_choices() as $kind_key => $kind_label ) : ?>
							<option value="<?php echo esc_attr( $kind_key ); ?>" <?php selected( $current_kind, $kind_key ); ?>>
								<?php echo esc_html( $kind_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ( $in_use && 'make' === $current_kind ) : ?>
						<input type="hidden" name="som_workflow_kind" value="make" />
						<p class="description"><?php echo esc_html__( 'Kind is locked to Make while products use this template.', 'order-machine' ); ?></p>
					<?php else : ?>
						<p class="description"><?php echo esc_html__( 'Make templates assign to products. Pack is for Pack & Ship. Post-ship is aftercare after Ship (Package 11).', 'order-machine' ); ?></p>
					<?php endif; ?>
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
			<?php if ( 'post_ship' !== $current_kind ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Trays', 'order-machine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="som_workflow_uses_trays" value="1" <?php checked( $template && ! empty( $template->uses_trays ) ); ?> />
						<?php echo esc_html__( 'Uses trays', 'order-machine' ); ?>
					</label>
					<p class="description">
						<?php echo esc_html__( 'When any make or Pack template on an order uses trays, operators must assign numbered trays to that order before Mark done / advance (shared by all make lines and pack).', 'order-machine' ); ?>
					</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<h2><?php echo esc_html__( 'Steps', 'order-machine' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Steps run in order. Use manual confirm, confirmation checklist, copy command, and/or timer — or assign a batch group (batch-only; cannot combine with other gates). Copy command and confirmation checklists cannot combine with timer or batch. Local / API / n8n script steps are removed — use Copy command. Steps with no gates auto-complete when entered (pass-through); Save warns but does not block. Planned duration is a reporting target only (not a gate).', 'order-machine' ); ?></p>

		<?php
		$planned_summary = SOM_Workflows::planned_total_summary( $steps );
		$planned_total_label = SOM_Orders::format_duration_seconds( $planned_summary['total'] );
		?>
		<p class="som-template-planned-total description" id="som-template-planned-total" data-som-planned-total>
			<?php echo esc_html__( 'Template planned total:', 'order-machine' ); ?>
			<strong data-som-planned-total-value><?php echo esc_html( $planned_total_label ); ?></strong>
			<span class="som-timing-partial" data-som-planned-partial <?php echo ! empty( $planned_summary['is_partial'] ) ? '' : 'hidden'; ?>>
				(<?php echo esc_html__( 'partial plan — some steps have no planned duration', 'order-machine' ); ?>)
			</span>
		</p>

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

<template id="som-task-row-template">
	<div class="som-task-row" data-som-task-row>
		<input type="hidden" name="som_step[__STEP__][tasks][__INDEX__][id]" value="" />
		<input type="text" class="regular-text" name="som_step[__STEP__][tasks][__INDEX__][label]" value="" maxlength="<?php echo esc_attr( (string) SOM_Step_Tasks::MAX_LABEL_LENGTH ); ?>" placeholder="<?php echo esc_attr__( 'Task label…', 'order-machine' ); ?>" />
		<button type="button" class="button-link som-task-remove" aria-label="<?php echo esc_attr__( 'Remove task', 'order-machine' ); ?>">&times;</button>
	</div>
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
