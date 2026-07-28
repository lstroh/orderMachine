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
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Order Machine Settings', 'order-machine' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'som_save_settings', 'som_settings_nonce' ); ?>

		<h2><?php echo esc_html__( 'General', 'order-machine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="som_n8n_base_url"><?php echo esc_html__( 'n8n base URL', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_n8n_base_url" id="som_n8n_base_url" type="url" class="regular-text" value="<?php echo esc_attr( $settings['n8n_base_url'] ); ?>" placeholder="https://n8n.example.com" />
					<p class="description"><?php echo esc_html__( 'Used later for workflow script steps that call n8n webhooks.', 'order-machine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="som_poll_interval"><?php echo esc_html__( 'Order polling interval (minutes)', 'order-machine' ); ?></label></th>
				<td>
					<input name="som_poll_interval" id="som_poll_interval" type="number" min="1" step="1" value="<?php echo esc_attr( (string) $settings['poll_interval_minutes'] ); ?>" class="small-text" />
					<p class="description"><?php echo esc_html__( 'Suggested 10–15. Cron job for sync is registered in Sprint 3.', 'order-machine' ); ?></p>
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
