<?php
/**
 * Settings admin view — channels, n8n, polling.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$settings       = SOM_Settings::get();
$ebay_connected = SOM_Channels::is_connected( 'ebay' );
$etsy_connected = SOM_Channels::is_connected( 'etsy' );
$ebay_creds     = SOM_Channels::get_credentials( 'ebay' );
$etsy_creds     = SOM_Channels::get_credentials( 'etsy' );
$callback_ebay  = SOM_Settings::oauth_callback_url( 'ebay' );
$callback_etsy  = SOM_Settings::oauth_callback_url( 'etsy' );

$connect_ebay_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'        => 'som-settings',
			'som_connect' => 'ebay',
		),
		admin_url( 'admin.php' )
	),
	'som_connect_ebay'
);

$connect_etsy_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'        => 'som-settings',
			'som_connect' => 'etsy',
		),
		admin_url( 'admin.php' )
	),
	'som_connect_etsy'
);

$disconnect_ebay_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'           => 'som-settings',
			'som_disconnect' => 'ebay',
		),
		admin_url( 'admin.php' )
	),
	'som_disconnect_ebay'
);

$disconnect_etsy_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'           => 'som-settings',
			'som_disconnect' => 'etsy',
		),
		admin_url( 'admin.php' )
	),
	'som_disconnect_etsy'
);

$sync_now_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'         => 'som-settings',
			'som_sync_now' => '1',
		),
		admin_url( 'admin.php' )
	),
	'som_sync_now'
);

$sync_status  = SOM_Order_Sync::get_status();
$ebay_channel = SOM_Channels::get_by_slug( 'ebay' );
$etsy_channel = SOM_Channels::get_by_slug( 'etsy' );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Order Machine Settings', 'order-machine' ); ?></h1>

	<h2><?php echo esc_html__( 'Order sync', 'order-machine' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php echo esc_html__( 'Last sync', 'order-machine' ); ?></th>
			<td>
				<?php if ( ! empty( $sync_status['last_run_at'] ) ) : ?>
					<?php
					printf(
						/* translators: 1: UTC datetime, 2: mode */
						esc_html__( '%1$s UTC (%2$s)', 'order-machine' ),
						esc_html( (string) $sync_status['last_run_at'] ),
						esc_html( (string) $sync_status['last_mode'] )
					);
					?>
					<?php if ( ! empty( $sync_status['last_summary'] ) ) : ?>
						<p class="description"><?php echo esc_html( (string) $sync_status['last_summary'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $sync_status['last_error'] ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php echo esc_html( (string) $sync_status['last_error'] ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<?php echo esc_html__( 'Not run yet.', 'order-machine' ); ?>
				<?php endif; ?>
				<?php if ( $ebay_channel && ! empty( $ebay_channel->last_synced_at ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: UTC datetime */
							esc_html__( 'eBay channel last_synced_at: %s UTC', 'order-machine' ),
							esc_html( (string) $ebay_channel->last_synced_at )
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $etsy_channel && ! empty( $etsy_channel->last_synced_at ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: UTC datetime */
							esc_html__( 'Etsy channel last_synced_at: %s UTC', 'order-machine' ),
							esc_html( (string) $etsy_channel->last_synced_at )
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'Sync now', 'order-machine' ); ?></th>
			<td>
				<a class="button button-primary" href="<?php echo esc_url( $sync_now_url ); ?>"><?php echo esc_html__( 'Sync now', 'order-machine' ); ?></a>
				<p class="description"><?php echo esc_html__( 'Incremental poll: since last sync, or last 7 days on first run. Dummy credentials load fixtures instead of live APIs.', 'order-machine' ); ?></p>
			</td>
		</tr>
	</table>

	<form method="post" action="" style="margin-bottom:1.5em;">
		<?php wp_nonce_field( 'som_import_history', 'som_import_history_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_history_days"><?php echo esc_html__( 'Import history', 'order-machine' ); ?></label></th>
				<td>
					<select name="som_history_days" id="som_history_days">
						<option value="30"><?php echo esc_html__( 'Last 30 days', 'order-machine' ); ?></option>
						<option value="90"><?php echo esc_html__( 'Last 90 days', 'order-machine' ); ?></option>
					</select>
					<?php submit_button( __( 'Import history', 'order-machine' ), 'secondary', 'som_import_history', false ); ?>
					<p class="description"><?php echo esc_html__( 'Explicit backfill for live channels. After import, normal Sync now / cron continues from last_synced_at.', 'order-machine' ); ?></p>
				</td>
			</tr>
		</table>
	</form>

	<form method="post" action="">
		<?php wp_nonce_field( 'som_save_settings', 'som_settings_nonce' ); ?>

		<h2><?php echo esc_html__( 'General', 'order-machine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_n8n_base_url"><?php echo esc_html__( 'n8n base URL', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_n8n_base_url" id="som_n8n_base_url" type="url" class="regular-text" value="<?php echo esc_attr( $settings['n8n_base_url'] ); ?>" placeholder="https://n8n.example.com" />
					<p class="description"><?php echo esc_html__( 'Reference only — workflow steps use the full webhook URL in script_config.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_api_key"><?php echo esc_html__( 'REST API key', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_api_key" id="som_api_key" type="text" class="regular-text code" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="off" />
					<label style="margin-left:8px;">
						<input type="checkbox" name="som_regenerate_api_key" value="1" />
						<?php echo esc_html__( 'Generate new key on save', 'order-machine' ); ?>
					</label>
					<p class="description">
						<?php
						printf(
							/* translators: %s: REST route examples */
							esc_html__( 'Send as header X-SOM-API-Key (or Authorization: Bearer) for %s', 'order-machine' ),
							'<code>POST /wp-json/som/v1/orders</code>, <code>…/advance-step</code>, <code>…/workflow-callback/{token}</code>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'MCP (AI query)', 'order-machine' ); ?></th>
				<td>
					<label for="som_mcp_enabled">
						<input name="som_mcp_enabled" id="som_mcp_enabled" type="checkbox" value="1" <?php checked( ! empty( $settings['mcp_enabled'] ) ); ?> />
						<?php echo esc_html__( 'Enable read-only Abilities for MCP Adapter', 'order-machine' ); ?>
					</label>
					<p class="description">
						<?php
						echo esc_html__(
							'When off, Order Machine Abilities are not registered. Requires the WordPress MCP Adapter plugin. See MCP.md for Cursor / Claude setup.',
							'order-machine'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_python_binary"><?php echo esc_html__( 'Python binary', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_python_binary" id="som_python_binary" type="text" class="regular-text code" value="<?php echo esc_attr( $settings['python_binary'] ); ?>" placeholder="python" />
					<p class="description"><?php echo esc_html__( 'Optional. Leave blank to use “python” on PATH. Needed for the thank-you card local action.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_poll_interval"><?php echo esc_html__( 'Order polling interval (minutes)', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_poll_interval" id="som_poll_interval" type="number" min="1" step="1" value="<?php echo esc_attr( (string) $settings['poll_interval_minutes'] ); ?>" class="small-text" />
					<p class="description"><?php echo esc_html__( 'Suggested 10–15. Schedules som_sync_orders via WP-Cron.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_engine_tick_interval"><?php echo esc_html__( 'Workflow engine tick (minutes)', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_engine_tick_interval" id="som_engine_tick_interval" type="number" min="1" step="1" value="<?php echo esc_attr( (string) $settings['engine_tick_interval_minutes'] ); ?>" class="small-text" />
					<p class="description"><?php echo esc_html__( 'Unlocks elapsed timers and backs up script retries (som_engine_tick). Default 60; script retries also use single-event cron.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_token_refresh_interval"><?php echo esc_html__( 'Token refresh interval (minutes)', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_token_refresh_interval" id="som_token_refresh_interval" type="number" min="5" step="1" value="<?php echo esc_attr( (string) $settings['token_refresh_interval_minutes'] ); ?>" class="small-text" />
					<p class="description"><?php echo esc_html__( 'Suggested 30–60. Runs som_refresh_tokens via WP-Cron.', 'order-machine' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'eBay', 'order-machine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Connection', 'order-machine' ); ?></th>
				<td>
					<?php if ( $ebay_connected ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
						<?php
						if ( ! empty( $ebay_creds['dummy'] ) ) {
							echo esc_html__( 'Connected (dummy credentials)', 'order-machine' );
						} else {
							echo esc_html__( 'Connected', 'order-machine' );
							if ( ! empty( $ebay_creds['expires_at'] ) ) {
								echo ' — ';
								printf(
									/* translators: %s: UTC datetime */
									esc_html__( 'access token expires %s UTC', 'order-machine' ),
									esc_html( $ebay_creds['expires_at'] )
								);
							}
						}
						?>
						<p>
							<a class="button" href="<?php echo esc_url( $disconnect_ebay_url ); ?>"><?php echo esc_html__( 'Disconnect', 'order-machine' ); ?></a>
						</p>
					<?php else : ?>
						<span class="dashicons dashicons-warning" style="color:#dba617;"></span>
						<?php echo esc_html__( 'Not connected', 'order-machine' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_ebay_environment"><?php echo esc_html__( 'Environment', 'order-machine' ); ?></label></th>
				<td>
					<select name="som_ebay_environment" id="som_ebay_environment">
						<option value="sandbox" <?php selected( $settings['ebay']['environment'], 'sandbox' ); ?>><?php echo esc_html__( 'Sandbox', 'order-machine' ); ?></option>
						<option value="production" <?php selected( $settings['ebay']['environment'], 'production' ); ?>><?php echo esc_html__( 'Production', 'order-machine' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_ebay_client_id"><?php echo esc_html__( 'Client ID (App ID)', 'order-machine' ); ?></label></th>
				<td><input name="som_ebay_client_id" id="som_ebay_client_id" type="text" class="regular-text" value="<?php echo esc_attr( $settings['ebay']['client_id'] ); ?>" autocomplete="off" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="som_ebay_client_secret"><?php echo esc_html__( 'Client Secret (Cert ID)', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_ebay_client_secret" id="som_ebay_client_secret" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $settings['ebay']['client_secret'] ? '••••••••' : '' ); ?>" />
					<p class="description"><?php echo esc_html__( 'Leave blank to keep the current secret.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_ebay_runame"><?php echo esc_html__( 'RuName', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_ebay_runame" id="som_ebay_runame" type="text" class="regular-text" value="<?php echo esc_attr( $settings['ebay']['runame'] ); ?>" autocomplete="off" />
					<p class="description"><?php echo esc_html__( 'eBay OAuth redirect_uri value is the RuName, not a URL. Register the Auth Accepted URL below in the eBay developer portal.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Auth Accepted URL', 'order-machine' ); ?></th>
				<td>
					<code><?php echo esc_html( $callback_ebay ); ?></code>
					<p class="description"><?php echo esc_html__( 'Paste this into your eBay RuName “Auth Accepted URL” field (Local HTTPS recommended for real OAuth).', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Connect', 'order-machine' ); ?></th>
				<td>
					<a class="button button-secondary" href="<?php echo esc_url( $connect_ebay_url ); ?>"><?php echo esc_html__( 'Connect eBay', 'order-machine' ); ?></a>
					<p class="description"><?php echo esc_html__( 'Save settings first, then click Connect. Real OAuth works on Local; wp-env uses dummy credentials.', 'order-machine' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'Etsy', 'order-machine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Connection', 'order-machine' ); ?></th>
				<td>
					<?php if ( $etsy_connected ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
						<?php
						if ( ! empty( $etsy_creds['dummy'] ) ) {
							echo esc_html__( 'Connected (dummy credentials)', 'order-machine' );
						} else {
							echo esc_html__( 'Connected', 'order-machine' );
							if ( ! empty( $etsy_creds['expires_at'] ) ) {
								echo ' — ';
								printf(
									/* translators: %s: UTC datetime */
									esc_html__( 'access token expires %s UTC', 'order-machine' ),
									esc_html( $etsy_creds['expires_at'] )
								);
							}
							if ( ! empty( $etsy_creds['shop_id'] ) ) {
								echo ' — ';
								printf(
									/* translators: %s: shop id */
									esc_html__( 'shop ID %s', 'order-machine' ),
									esc_html( (string) $etsy_creds['shop_id'] )
								);
							}
						}
						?>
						<p>
							<a class="button" href="<?php echo esc_url( $disconnect_etsy_url ); ?>"><?php echo esc_html__( 'Disconnect', 'order-machine' ); ?></a>
						</p>
					<?php else : ?>
						<span class="dashicons dashicons-warning" style="color:#dba617;"></span>
						<?php echo esc_html__( 'Not connected', 'order-machine' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_etsy_client_id"><?php echo esc_html__( 'API keystring (client ID)', 'order-machine' ); ?></label></th>
				<td><input name="som_etsy_client_id" id="som_etsy_client_id" type="text" class="regular-text" value="<?php echo esc_attr( $settings['etsy']['client_id'] ); ?>" autocomplete="off" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="som_etsy_client_secret"><?php echo esc_html__( 'Shared secret (optional)', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_etsy_client_secret" id="som_etsy_client_secret" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $settings['etsy']['client_secret'] ? '••••••••' : '' ); ?>" />
					<p class="description"><?php echo esc_html__( 'Optional. Leave blank to keep the current secret. PKCE connect uses the keystring.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Redirect URI', 'order-machine' ); ?></th>
				<td>
					<code><?php echo esc_html( $callback_etsy ); ?></code>
					<p class="description"><?php echo esc_html__( 'Register this exact URL on your Etsy Seller App.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Connect', 'order-machine' ); ?></th>
				<td>
					<a class="button button-secondary" href="<?php echo esc_url( $connect_etsy_url ); ?>"><?php echo esc_html__( 'Connect Etsy', 'order-machine' ); ?></a>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'order-machine' ) ); ?>
	</form>
</div>
