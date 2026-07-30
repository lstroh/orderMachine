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
		add_action( 'admin_init', array( __CLASS__, 'handle_orders_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_products_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_materials_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_workflows_actions' ) );
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
			__( 'Products', 'order-machine' ),
			__( 'Products', 'order-machine' ),
			'manage_options',
			'som-products',
			array( __CLASS__, 'render_products' )
		);

		add_submenu_page(
			'som-orders',
			__( 'Materials', 'order-machine' ),
			__( 'Materials', 'order-machine' ),
			'manage_options',
			'som-materials',
			array( __CLASS__, 'render_materials' )
		);

		add_submenu_page(
			'som-orders',
			__( 'Workflows', 'order-machine' ),
			__( 'Workflows', 'order-machine' ),
			'manage_options',
			'som-workflows',
			array( __CLASS__, 'render_workflows' )
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
		if ( ! in_array( $page, array( 'som-orders', 'som-products', 'som-materials', 'som-workflows' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'som-admin',
			SOM_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			SOM_VERSION
		);

		if ( in_array( $page, array( 'som-orders', 'som-products', 'som-workflows' ), true ) ) {
			wp_enqueue_script(
				'som-admin',
				SOM_PLUGIN_URL . 'admin/assets/js/admin.js',
				array(),
				SOM_VERSION,
				true
			);
		}
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
	 * Mark done on order detail.
	 *
	 * @return void
	 */
	public static function handle_orders_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-orders' !== $page || ! isset( $_POST['som_mark_step_done'] ) ) {
			return;
		}

		check_admin_referer( 'som_mark_step_done', 'som_order_nonce' );

		$order_id = isset( $_POST['som_order_id'] ) ? (int) $_POST['som_order_id'] : 0;
		$result   = SOM_Workflow_Engine::mark_done( $order_id );

		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_order_error' );
		} else {
			self::flash_notice( __( 'Step marked done.', 'order-machine' ), 'success', 'som_order_saved' );
		}

		wp_safe_redirect( SOM_Orders::detail_url( $order_id ) );
		exit;
	}

	/**
	 * Products list or edit.
	 *
	 * @return void
	 */
	public static function render_products() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$product_id = isset( $_GET['product_id'] ) ? sanitize_text_field( wp_unslash( $_GET['product_id'] ) ) : '';

		if ( 'new' === $product_id ) {
			$is_new  = true;
			$product = null;
			require SOM_PLUGIN_DIR . 'admin/views/product-edit.php';
			return;
		}

		if ( '' !== $product_id && is_numeric( $product_id ) && (int) $product_id > 0 ) {
			$product = SOM_Products::get( (int) $product_id );
			if ( ! $product ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>';
				echo esc_html__( 'Product not found.', 'order-machine' );
				echo '</p></div><p><a href="' . esc_url( SOM_Products::list_url() ) . '">';
				echo esc_html__( 'Back to products', 'order-machine' );
				echo '</a></p></div>';
				return;
			}
			$is_new = false;
			require SOM_PLUGIN_DIR . 'admin/views/product-edit.php';
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/products-list.php';
	}

	/**
	 * Materials list or edit.
	 *
	 * @return void
	 */
	public static function render_materials() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$material_id = isset( $_GET['material_id'] ) ? sanitize_text_field( wp_unslash( $_GET['material_id'] ) ) : '';

		if ( 'new' === $material_id ) {
			$is_new   = true;
			$material = null;
			require SOM_PLUGIN_DIR . 'admin/views/material-edit.php';
			return;
		}

		if ( '' !== $material_id && is_numeric( $material_id ) && (int) $material_id > 0 ) {
			$material = SOM_Materials::get( (int) $material_id );
			if ( ! $material ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>';
				echo esc_html__( 'Material not found.', 'order-machine' );
				echo '</p></div><p><a href="' . esc_url( SOM_Materials::list_url() ) . '">';
				echo esc_html__( 'Back to materials', 'order-machine' );
				echo '</a></p></div>';
				return;
			}
			$is_new = false;
			require SOM_PLUGIN_DIR . 'admin/views/material-edit.php';
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/materials-list.php';
	}

	/**
	 * Workflow templates list or step editor.
	 *
	 * @return void
	 */
	public static function render_workflows() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$template_id = isset( $_GET['template_id'] ) ? sanitize_text_field( wp_unslash( $_GET['template_id'] ) ) : '';

		if ( 'new' === $template_id ) {
			$is_new    = true;
			$template  = null;
			require SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php';
			return;
		}

		if ( '' !== $template_id && is_numeric( $template_id ) && (int) $template_id > 0 ) {
			$template = SOM_Workflows::get( (int) $template_id );
			if ( ! $template ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>';
				echo esc_html__( 'Workflow template not found.', 'order-machine' );
				echo '</p></div><p><a href="' . esc_url( SOM_Workflows::list_url() ) . '">';
				echo esc_html__( 'Back to workflows', 'order-machine' );
				echo '</a></p></div>';
				return;
			}
			$is_new = false;
			require SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php';
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/workflow-templates.php';
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
	 * Save workflow templates and steps.
	 *
	 * @return void
	 */
	public static function handle_workflows_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-workflows' !== $page || ! isset( $_POST['som_save_workflow'] ) ) {
			return;
		}

		check_admin_referer( 'som_save_workflow', 'som_workflow_nonce' );

		$template_id = isset( $_POST['template_id'] ) ? (int) $_POST['template_id'] : 0;
		$data        = array(
			'name'        => isset( $_POST['som_workflow_name'] ) ? wp_unslash( $_POST['som_workflow_name'] ) : '',
			'description' => isset( $_POST['som_workflow_description'] ) ? wp_unslash( $_POST['som_workflow_description'] ) : '',
			'is_active'   => ! empty( $_POST['som_workflow_is_active'] ),
		);

		if ( $template_id > 0 ) {
			$result = SOM_Workflows::update( $template_id, $data );
		} else {
			$result = SOM_Workflows::create( $data );
			if ( ! is_wp_error( $result ) ) {
				$template_id = (int) $result;
			}
		}

		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_workflow_error' );
			wp_safe_redirect( $template_id > 0 ? SOM_Workflows::editor_url( $template_id ) : SOM_Workflows::editor_url( 'new' ) );
			exit;
		}

		$step_rows = isset( $_POST['som_step'] ) && is_array( $_POST['som_step'] ) ? wp_unslash( $_POST['som_step'] ) : array();
		$steps     = array();
		foreach ( $step_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$steps[] = $row;
		}

		$steps_result = SOM_Workflows::save_steps( $template_id, $steps );
		if ( is_wp_error( $steps_result ) ) {
			self::flash_notice( $steps_result->get_error_message(), 'error', 'som_steps_error' );
			wp_safe_redirect( SOM_Workflows::editor_url( $template_id ) );
			exit;
		}

		self::flash_notice( __( 'Workflow template saved.', 'order-machine' ), 'success', 'som_workflow_saved' );
		wp_safe_redirect( SOM_Workflows::editor_url( $template_id ) );
		exit;
	}

	/**
	 * Save products and recipes.
	 *
	 * @return void
	 */
	public static function handle_products_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-products' !== $page || ! isset( $_POST['som_save_product'] ) ) {
			return;
		}

		check_admin_referer( 'som_save_product', 'som_product_nonce' );

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$data       = array(
			'name'                 => isset( $_POST['som_product_name'] ) ? wp_unslash( $_POST['som_product_name'] ) : '',
			'sku'                  => isset( $_POST['som_product_sku'] ) ? wp_unslash( $_POST['som_product_sku'] ) : '',
			'workflow_template_id' => isset( $_POST['som_workflow_template_id'] ) ? wp_unslash( $_POST['som_workflow_template_id'] ) : '',
			'is_active'            => ! empty( $_POST['som_product_is_active'] ),
		);

		if ( $product_id > 0 ) {
			$result = SOM_Products::update( $product_id, $data );
		} else {
			$result = SOM_Products::create( $data );
			if ( ! is_wp_error( $result ) ) {
				$product_id = (int) $result;
			}
		}

		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_product_error' );
			wp_safe_redirect( $product_id > 0 ? SOM_Products::detail_url( $product_id ) : SOM_Products::detail_url( 'new' ) );
			exit;
		}

		$recipe_rows = array();
		$materials   = isset( $_POST['som_recipe_material'] ) && is_array( $_POST['som_recipe_material'] ) ? wp_unslash( $_POST['som_recipe_material'] ) : array();
		$quantities  = isset( $_POST['som_recipe_qty'] ) && is_array( $_POST['som_recipe_qty'] ) ? wp_unslash( $_POST['som_recipe_qty'] ) : array();

		foreach ( $materials as $key => $material_id ) {
			$recipe_rows[] = array(
				'material_id'       => $material_id,
				'quantity_per_unit' => isset( $quantities[ $key ] ) ? $quantities[ $key ] : '',
			);
		}

		$recipe_result = SOM_Products::save_recipe( $product_id, $recipe_rows );
		if ( is_wp_error( $recipe_result ) ) {
			self::flash_notice( $recipe_result->get_error_message(), 'error', 'som_recipe_error' );
			wp_safe_redirect( SOM_Products::detail_url( $product_id ) );
			exit;
		}

		self::flash_notice( __( 'Product saved.', 'order-machine' ), 'success', 'som_product_saved' );
		wp_safe_redirect( SOM_Products::detail_url( $product_id ) );
		exit;
	}

	/**
	 * Save materials and manual stock adjustments.
	 *
	 * @return void
	 */
	public static function handle_materials_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-materials' !== $page ) {
			return;
		}

		if ( isset( $_POST['som_adjust_stock'] ) ) {
			check_admin_referer( 'som_adjust_stock', 'som_adjust_stock_nonce' );

			$material_id = isset( $_POST['material_id'] ) ? (int) $_POST['material_id'] : 0;
			$delta       = isset( $_POST['som_stock_delta'] ) ? (float) wp_unslash( $_POST['som_stock_delta'] ) : 0.0;

			$result = SOM_Materials::adjust_stock( $material_id, $delta );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_stock_error' );
			} else {
				self::flash_notice( __( 'Stock adjusted.', 'order-machine' ), 'success', 'som_stock_adjusted' );
			}

			wp_safe_redirect( SOM_Materials::detail_url( $material_id ) );
			exit;
		}

		if ( ! isset( $_POST['som_save_material'] ) ) {
			return;
		}

		check_admin_referer( 'som_save_material', 'som_material_nonce' );

		$material_id = isset( $_POST['material_id'] ) ? (int) $_POST['material_id'] : 0;
		$data        = array(
			'name'                => isset( $_POST['som_material_name'] ) ? wp_unslash( $_POST['som_material_name'] ) : '',
			'unit'                => isset( $_POST['som_material_unit'] ) ? wp_unslash( $_POST['som_material_unit'] ) : '',
			'low_stock_threshold' => isset( $_POST['som_low_stock_threshold'] ) ? wp_unslash( $_POST['som_low_stock_threshold'] ) : '',
			'unit_cost'           => isset( $_POST['som_unit_cost'] ) ? wp_unslash( $_POST['som_unit_cost'] ) : '',
			'is_active'           => ! empty( $_POST['som_material_is_active'] ),
		);

		if ( $material_id > 0 ) {
			$result = SOM_Materials::update( $material_id, $data );
		} else {
			$result = SOM_Materials::create( $data );
			if ( ! is_wp_error( $result ) ) {
				$material_id = (int) $result;
			}
		}

		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_material_error' );
			wp_safe_redirect( $material_id > 0 ? SOM_Materials::detail_url( $material_id ) : SOM_Materials::detail_url( 'new' ) );
			exit;
		}

		self::flash_notice( __( 'Material saved.', 'order-machine' ), 'success', 'som_material_saved' );
		wp_safe_redirect( SOM_Materials::detail_url( $material_id ) );
		exit;
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
				'engine_tick_interval_minutes'   => isset( $_POST['som_engine_tick_interval'] ) ? (int) $_POST['som_engine_tick_interval'] : 60,
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
			|| (int) $prev['engine_tick_interval_minutes'] !== (int) $next['engine_tick_interval_minutes']
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
		add_settings_error( 'som_admin', $code, $message, $type );
		set_transient( 'som_admin_errors', get_settings_errors( 'som_admin' ), 30 );

		// Keep settings page notices working via legacy transient.
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
