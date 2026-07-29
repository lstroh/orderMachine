<?php
/**
 * Admin menu registration for Order Machine.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level Order Machine admin menu and settings.
 */
class SOM_Admin_Menu {

	/**
	 * Hook into admin.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_settings_actions' ) );
	}

	/**
	 * Register top-level menu, Orders, and Settings.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Order Machine', 'order-machine' ),
			__( 'Order Machine', 'order-machine' ),
			'manage_options',
			'som-orders',
			array( __CLASS__, 'render_orders' ),
			'dashicons-clipboard',
			26
		);

		add_submenu_page(
			'som-orders',
			__( 'Orders', 'order-machine' ),
			__( 'Orders', 'order-machine' ),
			'manage_options',
			'som-orders',
			array( __CLASS__, 'render_orders' )
		);

		add_submenu_page(
			'som-orders',
			__( 'Settings', 'order-machine' ),
			__( 'Settings', 'order-machine' ),
			'manage_options',
			'som-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Admin CSS for Order Machine screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-orders' !== $page ) {
			return;
		}

		wp_enqueue_style(
			'som-admin',
			SOM_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			SOM_VERSION
		);
	}

	/**
	 * Orders list or detail.
	 *
	 * @return void
	 */
	public static function render_orders() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
		if ( $order_id > 0 ) {
			$order = SOM_Orders::get( $order_id );
			if ( ! $order ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>';
				echo esc_html__( 'Order not found.', 'order-machine' );
				echo '</p></div><p><a href="' . esc_url( SOM_Orders::list_url() ) . '">';
				echo esc_html__( 'Back to orders', 'order-machine' );
				echo '</a></p></div>';
				return;
			}
			require SOM_PLUGIN_DIR . 'admin/views/order-detail.php';
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/orders-list.php';
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Save settings, start OAuth, handle callbacks, disconnect.
	 *
	 * @return void
	 */
	public static function handle_settings_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-settings' !== $page ) {
			return;
		}

		// OAuth callback from eBay / Etsy.
		if ( isset( $_GET['som_oauth'] ) ) {
			self::handle_oauth_callback();
			return;
		}

		// Start connect redirect.
		if ( isset( $_GET['som_connect'] ) ) {
			self::handle_connect_redirect();
			return;
		}

		// Disconnect.
		if ( isset( $_GET['som_disconnect'] ) ) {
			self::handle_disconnect();
			return;
		}

		// Sync now (incremental).
		if ( isset( $_GET['som_sync_now'] ) ) {
			self::handle_sync_now();
			return;
		}

		// Import history backfill.
		if ( isset( $_POST['som_import_history'] ) ) {
			self::handle_import_history();
			return;
		}

		// Save settings form.
		if ( isset( $_POST['som_settings_nonce'] ) ) {
			self::handle_save_settings();
		}
	}

	/**
	 * @return void
	 */
	private static function handle_save_settings() {
		check_admin_referer( 'som_save_settings', 'som_settings_nonce' );

		$prev = SOM_Settings::get();

		$ebay_secret = isset( $_POST['som_ebay_client_secret'] ) ? (string) wp_unslash( $_POST['som_ebay_client_secret'] ) : '';
		$etsy_secret = isset( $_POST['som_etsy_client_secret'] ) ? (string) wp_unslash( $_POST['som_etsy_client_secret'] ) : '';
		if ( '' === $ebay_secret ) {
			$ebay_secret = $prev['ebay']['client_secret'];
		}
		if ( '' === $etsy_secret ) {
			$etsy_secret = $prev['etsy']['client_secret'];
		}

		SOM_Settings::update(
			array(
				'n8n_base_url'                   => isset( $_POST['som_n8n_base_url'] ) ? wp_unslash( $_POST['som_n8n_base_url'] ) : '',
				'poll_interval_minutes'          => isset( $_POST['som_poll_interval'] ) ? (int) $_POST['som_poll_interval'] : 15,
				'token_refresh_interval_minutes' => isset( $_POST['som_token_refresh_interval'] ) ? (int) $_POST['som_token_refresh_interval'] : 30,
				'ebay'                           => array(
					'client_id'     => isset( $_POST['som_ebay_client_id'] ) ? wp_unslash( $_POST['som_ebay_client_id'] ) : '',
					'client_secret' => $ebay_secret,
					'runame'        => isset( $_POST['som_ebay_runame'] ) ? wp_unslash( $_POST['som_ebay_runame'] ) : '',
					'environment'   => isset( $_POST['som_ebay_environment'] ) ? wp_unslash( $_POST['som_ebay_environment'] ) : 'sandbox',
				),
				'etsy'                           => array(
					'client_id'     => isset( $_POST['som_etsy_client_id'] ) ? wp_unslash( $_POST['som_etsy_client_id'] ) : '',
					'client_secret' => $etsy_secret,
				),
			)
		);

		$next = SOM_Settings::get();
		if (
			(int) $prev['token_refresh_interval_minutes'] !== (int) $next['token_refresh_interval_minutes']
			|| (int) $prev['poll_interval_minutes'] !== (int) $next['poll_interval_minutes']
		) {
			SOM_Cron::reschedule_events();
		}

		self::flash_notice( __( 'Settings saved.', 'order-machine' ), 'success', 'som_settings_saved' );
		wp_safe_redirect( admin_url( 'admin.php?page=som-settings&settings-updated=1' ) );
		exit;
	}

	/**
	 * Manual incremental sync.
	 *
	 * @return void
	 */
	private static function handle_sync_now() {
		check_admin_referer( 'som_sync_now' );

		$result = SOM_Order_Sync::sync_incremental();
		$type   = ! empty( $result['ok'] ) ? 'success' : 'warning';
		self::flash_notice(
			isset( $result['message'] ) ? (string) $result['message'] : __( 'Sync finished.', 'order-machine' ),
			$type,
			'som_sync_now'
		);
		wp_safe_redirect( admin_url( 'admin.php?page=som-settings' ) );
		exit;
	}

	/**
	 * Explicit history backfill (30 / 90 days).
	 *
	 * @return void
	 */
	private static function handle_import_history() {
		check_admin_referer( 'som_import_history', 'som_import_history_nonce' );

		$days = isset( $_POST['som_history_days'] ) ? (int) $_POST['som_history_days'] : 30;
		if ( ! in_array( $days, array( 30, 90 ), true ) ) {
			$days = 30;
		}

		$result = SOM_Order_Sync::sync_history( $days );
		$type   = ! empty( $result['ok'] ) ? 'success' : 'warning';
		self::flash_notice(
			isset( $result['message'] ) ? (string) $result['message'] : __( 'Import finished.', 'order-machine' ),
			$type,
			'som_import_history'
		);
		wp_safe_redirect( admin_url( 'admin.php?page=som-settings' ) );
		exit;
	}

	/**
	 * Queue an admin notice for the next settings page load.
	 *
	 * @param string $message Message text.
	 * @param string $type    success|error|warning|info.
	 * @param string $code    Error code.
	 * @return void
	 */
	private static function flash_notice( $message, $type = 'success', $code = 'som_notice' ) {
		add_settings_error( 'som_settings', $code, $message, $type );
		set_transient( 'som_settings_errors', get_settings_errors( 'som_settings' ), 30 );
	}

	/**
	 * @return void
	 */
	private static function handle_connect_redirect() {
		$channel = sanitize_key( wp_unslash( $_GET['som_connect'] ) );

		if ( 'ebay' === $channel ) {
			check_admin_referer( 'som_connect_ebay' );
			$url = SOM_Channel_Ebay::get_authorize_url();
		} elseif ( 'etsy' === $channel ) {
			check_admin_referer( 'som_connect_etsy' );
			$url = SOM_Channel_Etsy::get_authorize_url();
		} else {
			return;
		}

		if ( is_wp_error( $url ) ) {
			self::flash_notice( $url->get_error_message(), 'error', 'som_connect_error' );
			wp_safe_redirect( admin_url( 'admin.php?page=som-settings' ) );
			exit;
		}

		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external OAuth host
		exit;
	}

	/**
	 * @return void
	 */
	private static function handle_oauth_callback() {
		$channel = sanitize_key( wp_unslash( $_GET['som_oauth'] ) );
		$code    = isset( $_GET['code'] ) ? wp_unslash( $_GET['code'] ) : '';
		$state   = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$desc  = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : $error;
			self::flash_notice( $desc, 'error', 'som_oauth_denied' );
			wp_safe_redirect( admin_url( 'admin.php?page=som-settings' ) );
			exit;
		}

		if ( 'ebay' === $channel ) {
			$result = SOM_Channel_Ebay::handle_callback( $code, $state );
		} elseif ( 'etsy' === $channel ) {
			$result = SOM_Channel_Etsy::handle_callback( $code, $state );
		} else {
			return;
		}

		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_oauth_error' );
		} else {
			self::flash_notice(
				sprintf(
					/* translators: %s: channel name */
					__( '%s connected successfully.', 'order-machine' ),
					'ebay' === $channel ? 'eBay' : 'Etsy'
				),
				'success',
				'som_oauth_ok'
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=som-settings' ) );
		exit;
	}

	/**
	 * @return void
	 */
	private static function handle_disconnect() {
		$channel = sanitize_key( wp_unslash( $_GET['som_disconnect'] ) );

		if ( 'ebay' === $channel ) {
			check_admin_referer( 'som_disconnect_ebay' );
		} elseif ( 'etsy' === $channel ) {
			check_admin_referer( 'som_disconnect_etsy' );
		} else {
			return;
		}

		SOM_Channels::disconnect( $channel );

		self::flash_notice(
			sprintf(
				/* translators: %s: channel name */
				__( '%s disconnected.', 'order-machine' ),
				'ebay' === $channel ? 'eBay' : 'Etsy'
			),
			'success',
			'som_disconnected'
		);
		wp_safe_redirect( admin_url( 'admin.php?page=som-settings' ) );
		exit;
	}
}
