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
		add_action( 'admin_init', array( __CLASS__, 'handle_budgets_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_suppliers_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_purchase_orders_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_batches_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_workflows_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_listings_actions' ) );
		add_action( 'wp_ajax_som_preview_po_impact', array( __CLASS__, 'ajax_preview_po_impact' ) );
		add_action( 'wp_ajax_som_board_toggle_pin', array( __CLASS__, 'ajax_board_toggle_pin' ) );
		add_action( 'wp_ajax_som_board_save_columns', array( __CLASS__, 'ajax_board_save_columns' ) );
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
			__( 'Orders Board', 'order-machine' ),
			__( 'Orders Board', 'order-machine' ),
			'manage_options',
			'som-orders-board',
			array( __CLASS__, 'render_orders_board' )
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
			__( 'Budgets', 'order-machine' ),
			__( 'Budgets', 'order-machine' ),
			'manage_options',
			'som-budgets',
			array( __CLASS__, 'render_budgets' )
		);

		add_submenu_page(
			'som-orders',
			__( 'Suppliers', 'order-machine' ),
			__( 'Suppliers', 'order-machine' ),
			'manage_options',
			'som-suppliers',
			array( __CLASS__, 'render_suppliers' )
		);

		add_submenu_page(
			'som-orders',
			__( 'Purchase Orders', 'order-machine' ),
			__( 'Purchase Orders', 'order-machine' ),
			'manage_options',
			'som-purchase-orders',
			array( __CLASS__, 'render_purchase_orders' )
		);

		add_submenu_page(
			'som-orders',
			__( 'Batches', 'order-machine' ),
			__( 'Batches', 'order-machine' ),
			'manage_options',
			'som-batches',
			array( __CLASS__, 'render_batches' )
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
			__( 'Listings', 'order-machine' ),
			__( 'Listings', 'order-machine' ),
			'manage_options',
			'som-listings',
			array( __CLASS__, 'render_listings' )
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
		if ( ! in_array( $page, array( 'som-orders', 'som-orders-board', 'som-products', 'som-materials', 'som-budgets', 'som-suppliers', 'som-purchase-orders', 'som-batches', 'som-workflows', 'som-listings' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'som-admin',
			SOM_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			SOM_VERSION
		);

		if ( in_array( $page, array( 'som-orders', 'som-products', 'som-purchase-orders', 'som-batches', 'som-workflows', 'som-listings' ), true ) ) {
			wp_enqueue_script(
				'som-admin',
				SOM_PLUGIN_URL . 'admin/assets/js/admin.js',
				array(),
				SOM_VERSION,
				true
			);

			$localize = array();
			if ( 'som-orders' === $page ) {
				$localize['restUrl']   = esc_url_raw( rest_url( 'som/v1/' ) );
				$localize['restNonce'] = wp_create_nonce( 'wp_rest' );
			}
			if ( 'som-purchase-orders' === $page ) {
				$localize['ajaxUrl']      = admin_url( 'admin-ajax.php' );
				$localize['previewNonce'] = wp_create_nonce( 'som_preview_po_impact' );
			}
			if ( $localize ) {
				wp_localize_script( 'som-admin', 'somAdmin', $localize );
			}
		}

		if ( 'som-orders-board' === $page ) {
			wp_enqueue_script(
				'sortablejs',
				'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
				array(),
				'1.15.6',
				true
			);
			wp_enqueue_script(
				'som-orders-board',
				SOM_PLUGIN_URL . 'admin/assets/js/orders-board.js',
				array( 'sortablejs' ),
				SOM_VERSION,
				true
			);
			wp_localize_script(
				'som-orders-board',
				'somBoard',
				array(
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'som_orders_board' ),
					'restUrl'       => esc_url_raw( rest_url( 'som/v1/' ) ),
					'restNonce'     => wp_create_nonce( 'wp_rest' ),
					'completeKey'   => SOM_Orders::BOARD_COMPLETE_KEY,
					'unassignedKey' => SOM_Orders::BOARD_UNASSIGNED_KEY,
					'statusLabels'  => array(
						'pending'        => __( 'Pending', 'order-machine' ),
						'in_progress'    => __( 'In progress', 'order-machine' ),
						'waiting_timer'  => __( 'Waiting (timer)', 'order-machine' ),
						'waiting_script' => __( 'Waiting (script)', 'order-machine' ),
						'waiting_batch'  => __( 'Waiting (batch)', 'order-machine' ),
						'error'          => __( 'Error', 'order-machine' ),
						'done'           => __( 'Done', 'order-machine' ),
					),
					'i18n'          => array(
						'pin'           => __( 'Pin', 'order-machine' ),
						'unpin'         => __( 'Unpin', 'order-machine' ),
						'advanceError'  => __( 'Could not advance step.', 'order-machine' ),
						'networkError'  => __( 'Could not advance step (network error).', 'order-machine' ),
						'batchLabel'    => __( 'Batch #%3$d: %1$d of %2$d', 'order-machine' ),
					),
				)
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
	 * Order Board (Kanban read UI).
	 *
	 * @return void
	 */
	public static function render_orders_board() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/orders-board.php';
	}

	/**
	 * Mark done / retry script on order detail.
	 *
	 * @return void
	 */
	public static function handle_orders_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-orders' !== $page ) {
			return;
		}

		if ( isset( $_POST['som_retry_script'] ) ) {
			check_admin_referer( 'som_retry_script', 'som_order_nonce' );
			$order_id = isset( $_POST['som_order_id'] ) ? (int) $_POST['som_order_id'] : 0;
			$result   = SOM_Workflow_Engine::retry_script( $order_id );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_order_error' );
			} else {
				self::flash_notice( __( 'Script retry started.', 'order-machine' ), 'success', 'som_order_saved' );
			}
			wp_safe_redirect( SOM_Orders::detail_url( $order_id ) );
			exit;
		}

		if ( ! isset( $_POST['som_mark_step_done'] ) ) {
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
	 * Budgets list or edit.
	 *
	 * @return void
	 */
	public static function render_budgets() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$budget_id = isset( $_GET['budget_id'] ) ? sanitize_text_field( wp_unslash( $_GET['budget_id'] ) ) : '';

		if ( 'new' === $budget_id ) {
			$is_new = true;
			$budget = null;
			require SOM_PLUGIN_DIR . 'admin/views/budget-edit.php';
			return;
		}

		if ( '' !== $budget_id && is_numeric( $budget_id ) && (int) $budget_id > 0 ) {
			$budget = SOM_Budgets::get( (int) $budget_id );
			if ( ! $budget ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>';
				echo esc_html__( 'Budget not found.', 'order-machine' );
				echo '</p></div><p><a href="' . esc_url( SOM_Budgets::list_url() ) . '">';
				echo esc_html__( 'Back to budgets', 'order-machine' );
				echo '</a></p></div>';
				return;
			}
			$is_new = false;
			require SOM_PLUGIN_DIR . 'admin/views/budget-edit.php';
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/budgets-list.php';
	}

	/**
	 * Suppliers list or edit.
	 *
	 * @return void
	 */
	public static function render_suppliers() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$supplier_id = isset( $_GET['supplier_id'] ) ? sanitize_text_field( wp_unslash( $_GET['supplier_id'] ) ) : '';

		if ( 'new' === $supplier_id ) {
			$is_new   = true;
			$supplier = null;
			require SOM_PLUGIN_DIR . 'admin/views/supplier-edit.php';
			return;
		}

		if ( '' !== $supplier_id && is_numeric( $supplier_id ) && (int) $supplier_id > 0 ) {
			$supplier = SOM_Suppliers::get( (int) $supplier_id );
			if ( ! $supplier ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>';
				echo esc_html__( 'Supplier not found.', 'order-machine' );
				echo '</p></div><p><a href="' . esc_url( SOM_Suppliers::list_url() ) . '">';
				echo esc_html__( 'Back to suppliers', 'order-machine' );
				echo '</a></p></div>';
				return;
			}
			$is_new = false;
			require SOM_PLUGIN_DIR . 'admin/views/supplier-edit.php';
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/suppliers-list.php';
	}

	/**
	 * Purchase orders list, edit, or receive.
	 *
	 * @return void
	 */
	public static function render_purchase_orders() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$po_id = isset( $_GET['po_id'] ) ? sanitize_text_field( wp_unslash( $_GET['po_id'] ) ) : '';
		$view  = isset( $_GET['som_view'] ) ? sanitize_key( wp_unslash( $_GET['som_view'] ) ) : '';

		if ( 'new' === $po_id ) {
			$is_new = true;
			$order  = null;
			require SOM_PLUGIN_DIR . 'admin/views/purchase-order-edit.php';
			return;
		}

		if ( '' !== $po_id && is_numeric( $po_id ) && (int) $po_id > 0 ) {
			$order = SOM_Purchase_Orders::get( (int) $po_id );
			if ( ! $order ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>';
				echo esc_html__( 'Purchase order not found.', 'order-machine' );
				echo '</p></div><p><a href="' . esc_url( SOM_Purchase_Orders::list_url() ) . '">';
				echo esc_html__( 'Back to purchase orders', 'order-machine' );
				echo '</a></p></div>';
				return;
			}

			if ( 'receive' === $view ) {
				if ( empty( $order->can_receive ) ) {
					echo '<div class="wrap"><div class="notice notice-error"><p>';
					echo esc_html__( 'This purchase order cannot be received.', 'order-machine' );
					echo '</p></div><p><a href="' . esc_url( SOM_Purchase_Orders::detail_url( (int) $order->id ) ) . '">';
					echo esc_html__( 'Back to purchase order', 'order-machine' );
					echo '</a></p></div>';
					return;
				}
				require SOM_PLUGIN_DIR . 'admin/views/purchase-order-receive.php';
				return;
			}

			$is_new = false;
			require SOM_PLUGIN_DIR . 'admin/views/purchase-order-edit.php';
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/purchase-orders-list.php';
	}

	/**
	 * Batches list + batch groups editor.
	 *
	 * @return void
	 */
	public static function render_batches() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filter_status   = isset( $_GET['som_status'] ) ? sanitize_key( wp_unslash( $_GET['som_status'] ) ) : '';
		$filter_group_id = isset( $_GET['batch_group_id'] ) ? (int) $_GET['batch_group_id'] : 0;
		$include_done    = ! empty( $_GET['include_done'] );
		$focus_batch_id  = isset( $_GET['batch_id'] ) ? (int) $_GET['batch_id'] : 0;
		$paged           = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$batch_query = SOM_Batches::query(
			array(
				'status'         => $filter_status,
				'batch_group_id' => $filter_group_id,
				'include_done'   => $include_done || ( 'done' === $filter_status ),
				'paged'          => $paged,
			)
		);

		if ( $focus_batch_id > 0 ) {
			$found = false;
			foreach ( $batch_query['batches'] as $row ) {
				if ( (int) $row->id === $focus_batch_id ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$extra = SOM_Batches::get( $focus_batch_id );
				if ( $extra ) {
					$group = SOM_Batch_Groups::get( (int) $extra->batch_group_id );
					$extra->group_name        = $group ? (string) $group->display_name : '';
					$extra->group_key         = $group ? (string) $group->key : '';
					$extra->group_batch_size  = $group ? (int) $group->batch_size : 4;
					$extra->group_action_type = $group ? (string) $group->action_type : '';
					$extra->item_count        = count( SOM_Batches::get_items( $focus_batch_id ) );
					$extra->key               = $extra->group_key;
					array_unshift( $batch_query['batches'], $extra );
					++$batch_query['total'];
				}
			}
		}

		$batch_groups = SOM_Batch_Groups::list_all();
		require SOM_PLUGIN_DIR . 'admin/views/batches.php';
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
	 * Listings list or edit.
	 *
	 * @return void
	 */
	public static function render_listings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$listing_id = isset( $_GET['listing_id'] ) ? sanitize_text_field( wp_unslash( $_GET['listing_id'] ) ) : '';

		if ( 'new' === $listing_id ) {
			$is_new  = true;
			$listing = null;
			require SOM_PLUGIN_DIR . 'admin/views/listing-edit.php';
			return;
		}

		if ( '' !== $listing_id && is_numeric( $listing_id ) && (int) $listing_id > 0 ) {
			$listing = SOM_Listings::get( (int) $listing_id );
			if ( ! $listing ) {
				echo '<div class="wrap"><div class="notice notice-error"><p>';
				echo esc_html__( 'Listing not found.', 'order-machine' );
				echo '</p></div><p><a href="' . esc_url( SOM_Listings::list_url() ) . '">';
				echo esc_html__( 'Back to listings', 'order-machine' );
				echo '</a></p></div>';
				return;
			}
			$is_new = false;
			require SOM_PLUGIN_DIR . 'admin/views/listing-edit.php';
			return;
		}

		require SOM_PLUGIN_DIR . 'admin/views/listings.php';
	}

	/**
	 * Save / refresh / push listings.
	 *
	 * @return void
	 */
	public static function handle_listings_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-listings' !== $page ) {
			return;
		}

		if ( isset( $_POST['som_refresh_listing'] ) || isset( $_POST['som_push_listing'] ) ) {
			check_admin_referer( 'som_listing_channel', 'som_listing_nonce' );
			$listing_id = isset( $_POST['listing_id'] ) ? (int) $_POST['listing_id'] : 0;

			if ( isset( $_POST['som_refresh_listing'] ) ) {
				$result = SOM_Listings::refresh( $listing_id );
				if ( is_wp_error( $result ) ) {
					self::flash_notice( $result->get_error_message(), 'error', 'som_listing_error' );
				} else {
					self::flash_notice( __( 'Listing refreshed from channel.', 'order-machine' ), 'success', 'som_listing_refreshed' );
				}
			} else {
				$result = SOM_Listings::push( $listing_id );
				if ( is_wp_error( $result ) ) {
					self::flash_notice( $result->get_error_message(), 'error', 'som_listing_error' );
				} else {
					self::flash_notice( __( 'Listing pushed to channel.', 'order-machine' ), 'success', 'som_listing_pushed' );
				}
			}

			wp_safe_redirect( SOM_Listings::detail_url( $listing_id ) );
			exit;
		}

		if ( ! isset( $_POST['som_save_listing'] ) ) {
			return;
		}

		check_admin_referer( 'som_save_listing', 'som_listing_nonce' );

		$listing_id = isset( $_POST['listing_id'] ) ? (int) $_POST['listing_id'] : 0;
		$mode       = isset( $_POST['inventory_mode'] ) ? sanitize_key( wp_unslash( $_POST['inventory_mode'] ) ) : 'flat';
		$sku        = isset( $_POST['primary_sku'] ) ? sanitize_text_field( wp_unslash( $_POST['primary_sku'] ) ) : '';
		$inventory  = SOM_Listings::inventory_from_post(
			$mode,
			$sku,
			array(
				'som_var_sku'     => isset( $_POST['som_var_sku'] ) ? wp_unslash( $_POST['som_var_sku'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in inventory_from_post
				'som_var_qty'     => isset( $_POST['som_var_qty'] ) ? wp_unslash( $_POST['som_var_qty'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'som_var_options' => isset( $_POST['som_var_options'] ) ? wp_unslash( $_POST['som_var_options'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			)
		);

		$data = array(
			'product_id'         => isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0,
			'title'              => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'        => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '',
			'price'              => isset( $_POST['price'] ) ? (float) wp_unslash( $_POST['price'] ) : 0.0,
			'quantity_available' => isset( $_POST['quantity_available'] ) ? (int) $_POST['quantity_available'] : 0,
			'inventory'          => $inventory,
		);

		if ( $listing_id < 1 ) {
			$data['channel_slug']         = isset( $_POST['channel_slug'] ) ? sanitize_key( wp_unslash( $_POST['channel_slug'] ) ) : '';
			$data['external_listing_id']  = isset( $_POST['external_listing_id'] ) ? sanitize_text_field( wp_unslash( $_POST['external_listing_id'] ) ) : '';
			$result                       = SOM_Listings::create( $data );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_listing_error' );
				wp_safe_redirect( SOM_Listings::detail_url( 'new' ) );
				exit;
			}
			self::flash_notice( __( 'Listing map created.', 'order-machine' ), 'success', 'som_listing_saved' );
			wp_safe_redirect( SOM_Listings::detail_url( (int) $result ) );
			exit;
		}

		$result = SOM_Listings::update_local( $listing_id, $data );
		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_listing_error' );
		} else {
			self::flash_notice( __( 'Listing saved locally.', 'order-machine' ), 'success', 'som_listing_saved' );
		}

		wp_safe_redirect( SOM_Listings::detail_url( $listing_id ) );
		exit;
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

		$goal_materials  = isset( $_POST['som_goal_material'] ) && is_array( $_POST['som_goal_material'] ) ? wp_unslash( $_POST['som_goal_material'] ) : array();
		$goal_costs      = isset( $_POST['som_goal_cost'] ) && is_array( $_POST['som_goal_cost'] ) ? wp_unslash( $_POST['som_goal_cost'] ) : array();
		$goal_thresholds = isset( $_POST['som_goal_threshold'] ) && is_array( $_POST['som_goal_threshold'] ) ? wp_unslash( $_POST['som_goal_threshold'] ) : array();
		$goal_rows       = array();
		foreach ( $goal_materials as $key => $material_id ) {
			$goal_rows[] = array(
				'material_id'               => $material_id,
				'goal_unit_cost'            => isset( $goal_costs[ $key ] ) ? $goal_costs[ $key ] : '',
				'warning_threshold_percent' => isset( $goal_thresholds[ $key ] ) ? $goal_thresholds[ $key ] : 90,
			);
		}

		$goals_result = SOM_Workflow_Material_Goals::sync_for_workflow( $template_id, $goal_rows );
		if ( is_wp_error( $goals_result ) ) {
			self::flash_notice( $goals_result->get_error_message(), 'error', 'som_goals_error' );
			wp_safe_redirect( SOM_Workflows::editor_url( $template_id ) );
			exit;
		}

		self::flash_notice( __( 'Workflow template saved.', 'order-machine' ), 'success', 'som_workflow_saved' );
		wp_safe_redirect( SOM_Workflows::editor_url( $template_id ) );
		exit;
	}

	/**
	 * Save batch groups / release / mark done / retry.
	 *
	 * @return void
	 */
	public static function handle_batches_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-batches' !== $page || empty( $_POST ) ) {
			return;
		}

		if ( isset( $_POST['som_save_batch_groups'] ) ) {
			check_admin_referer( 'som_save_batch_groups', 'som_batches_nonce' );

			$ids   = isset( $_POST['som_group_id'] ) && is_array( $_POST['som_group_id'] ) ? wp_unslash( $_POST['som_group_id'] ) : array();
			$names = isset( $_POST['som_group_display_name'] ) && is_array( $_POST['som_group_display_name'] ) ? wp_unslash( $_POST['som_group_display_name'] ) : array();
			$sizes = isset( $_POST['som_group_batch_size'] ) && is_array( $_POST['som_group_batch_size'] ) ? wp_unslash( $_POST['som_group_batch_size'] ) : array();

			foreach ( $ids as $raw_id ) {
				$id = (int) $raw_id;
				if ( $id < 1 ) {
					continue;
				}
				$result = SOM_Batch_Groups::update(
					$id,
					array(
						'display_name' => isset( $names[ $id ] ) ? $names[ $id ] : '',
						'batch_size'   => isset( $sizes[ $id ] ) ? $sizes[ $id ] : 0,
					)
				);
				if ( is_wp_error( $result ) ) {
					self::flash_notice( $result->get_error_message(), 'error', 'som_batch_group_error' );
					wp_safe_redirect( SOM_Batches::list_url() );
					exit;
				}
			}

			self::flash_notice( __( 'Batch groups saved.', 'order-machine' ), 'success', 'som_batch_groups_saved' );
			wp_safe_redirect( SOM_Batches::list_url() );
			exit;
		}

		$batch_id = isset( $_POST['som_batch_id'] ) ? (int) $_POST['som_batch_id'] : 0;
		if ( $batch_id < 1 ) {
			return;
		}

		check_admin_referer( 'som_batch_action', 'som_batches_nonce' );

		$result = null;
		if ( isset( $_POST['som_batch_release'] ) ) {
			$result = SOM_Batches::release( $batch_id, true );
			$ok_msg = __( 'Batch released.', 'order-machine' );
		} elseif ( isset( $_POST['som_batch_mark_done'] ) ) {
			$result = SOM_Batches::mark_done( $batch_id );
			$ok_msg = __( 'Batch marked done.', 'order-machine' );
		} elseif ( isset( $_POST['som_batch_retry'] ) ) {
			$result = SOM_Batches::retry( $batch_id );
			$ok_msg = __( 'Batch retry started.', 'order-machine' );
		} else {
			return;
		}

		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_batch_action_error' );
		} else {
			self::flash_notice( $ok_msg, 'success', 'som_batch_action_ok' );
		}

		wp_safe_redirect( SOM_Batches::batch_url( $batch_id ) );
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
			'name'                  => isset( $_POST['som_product_name'] ) ? wp_unslash( $_POST['som_product_name'] ) : '',
			'sku'                   => isset( $_POST['som_product_sku'] ) ? wp_unslash( $_POST['som_product_sku'] ) : '',
			'workflow_template_id'  => isset( $_POST['som_workflow_template_id'] ) ? wp_unslash( $_POST['som_workflow_template_id'] ) : '',
			'target_selling_price'  => isset( $_POST['som_target_selling_price'] ) ? wp_unslash( $_POST['som_target_selling_price'] ) : '',
			'is_active'             => ! empty( $_POST['som_product_is_active'] ),
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

		if ( isset( $_POST['som_material_writeoff'] ) ) {
			check_admin_referer( 'som_material_writeoff', 'som_material_writeoff_nonce' );

			$material_id = isset( $_POST['material_id'] ) ? (int) $_POST['material_id'] : 0;
			$qty         = isset( $_POST['som_writeoff_qty'] ) ? (float) wp_unslash( $_POST['som_writeoff_qty'] ) : 0.0;
			$notes       = isset( $_POST['som_writeoff_notes'] ) ? wp_unslash( $_POST['som_writeoff_notes'] ) : '';

			$result = SOM_Budgets::write_off_material( $material_id, $qty, $notes );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_writeoff_error' );
			} else {
				self::flash_notice( __( 'R&D write-off recorded.', 'order-machine' ), 'success', 'som_writeoff_ok' );
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
			'name'                  => isset( $_POST['som_material_name'] ) ? wp_unslash( $_POST['som_material_name'] ) : '',
			'unit'                  => isset( $_POST['som_material_unit'] ) ? wp_unslash( $_POST['som_material_unit'] ) : '',
			'low_stock_threshold'   => isset( $_POST['som_low_stock_threshold'] ) ? wp_unslash( $_POST['som_low_stock_threshold'] ) : '',
			'unit_cost'             => isset( $_POST['som_unit_cost'] ) ? wp_unslash( $_POST['som_unit_cost'] ) : '',
			'preferred_supplier_id' => isset( $_POST['som_preferred_supplier'] ) ? wp_unslash( $_POST['som_preferred_supplier'] ) : '',
			'is_active'             => ! empty( $_POST['som_material_is_active'] ),
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
	 * Save budgets, manual adjustments, and R&D write-offs from the Budgets screens.
	 *
	 * @return void
	 */
	public static function handle_budgets_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-budgets' !== $page ) {
			return;
		}

		if ( isset( $_POST['som_budget_adjust'] ) ) {
			check_admin_referer( 'som_budget_adjust', 'som_budget_adjust_nonce' );

			$budget_id = isset( $_POST['budget_id'] ) ? (int) $_POST['budget_id'] : 0;
			$amount    = isset( $_POST['som_budget_adjust_amount'] ) ? (float) wp_unslash( $_POST['som_budget_adjust_amount'] ) : 0.0;
			$notes     = isset( $_POST['som_budget_adjust_notes'] ) ? trim( (string) wp_unslash( $_POST['som_budget_adjust_notes'] ) ) : '';

			if ( '' === $notes ) {
				self::flash_notice( __( 'Notes are required for a manual adjustment.', 'order-machine' ), 'error', 'som_budget_adjust_notes' );
				wp_safe_redirect( SOM_Budgets::detail_url( $budget_id ) );
				exit;
			}
			if ( 0.0 === $amount ) {
				self::flash_notice( __( 'Adjustment amount cannot be zero.', 'order-machine' ), 'error', 'som_budget_adjust_amount' );
				wp_safe_redirect( SOM_Budgets::detail_url( $budget_id ) );
				exit;
			}

			$result = SOM_Budgets::insert_ledger(
				$budget_id,
				$amount,
				array(
					'reason' => SOM_Budgets::REASON_MANUAL_ADJUSTMENT,
					'notes'  => $notes,
				)
			);

			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_budget_adjust_error' );
			} else {
				self::flash_notice( __( 'Adjustment recorded.', 'order-machine' ), 'success', 'som_budget_adjusted' );
			}

			wp_safe_redirect( SOM_Budgets::detail_url( $budget_id ) );
			exit;
		}

		if ( isset( $_POST['som_budget_writeoff'] ) ) {
			check_admin_referer( 'som_budget_writeoff', 'som_budget_writeoff_nonce' );

			$budget_id   = isset( $_POST['budget_id'] ) ? (int) $_POST['budget_id'] : 0;
			$material_id = isset( $_POST['material_id'] ) ? (int) $_POST['material_id'] : 0;
			$qty         = isset( $_POST['som_writeoff_qty'] ) ? (float) wp_unslash( $_POST['som_writeoff_qty'] ) : 0.0;
			$notes       = isset( $_POST['som_writeoff_notes'] ) ? wp_unslash( $_POST['som_writeoff_notes'] ) : '';

			$result = SOM_Budgets::write_off_material( $material_id, $qty, $notes );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_writeoff_error' );
			} else {
				self::flash_notice( __( 'R&D write-off recorded.', 'order-machine' ), 'success', 'som_writeoff_ok' );
			}

			wp_safe_redirect( SOM_Budgets::detail_url( $budget_id > 0 ? $budget_id : 'new' ) );
			exit;
		}

		if ( ! isset( $_POST['som_save_budget'] ) ) {
			return;
		}

		check_admin_referer( 'som_save_budget', 'som_budget_nonce' );

		$budget_id = isset( $_POST['budget_id'] ) ? (int) $_POST['budget_id'] : 0;
		$type      = isset( $_POST['som_budget_type'] ) ? sanitize_key( wp_unslash( $_POST['som_budget_type'] ) ) : '';

		$data = array(
			'name'                  => isset( $_POST['som_budget_name'] ) ? wp_unslash( $_POST['som_budget_name'] ) : '',
			'notes'                 => isset( $_POST['som_budget_notes'] ) ? wp_unslash( $_POST['som_budget_notes'] ) : '',
			'target_reserve_amount' => isset( $_POST['som_budget_target_reserve'] ) ? wp_unslash( $_POST['som_budget_target_reserve'] ) : '',
			'is_active'             => ! empty( $_POST['som_budget_is_active'] ),
		);

		$product_ids = array();
		if ( isset( $_POST['som_budget_product_ids'] ) && is_array( $_POST['som_budget_product_ids'] ) ) {
			foreach ( wp_unslash( $_POST['som_budget_product_ids'] ) as $pid ) {
				$pid = (int) $pid;
				if ( $pid > 0 ) {
					$product_ids[] = $pid;
				}
			}
		}

		$workflow_ids = array();
		if ( isset( $_POST['som_budget_workflow_ids'] ) && is_array( $_POST['som_budget_workflow_ids'] ) ) {
			foreach ( wp_unslash( $_POST['som_budget_workflow_ids'] ) as $wid ) {
				$wid = (int) $wid;
				if ( $wid > 0 ) {
					$workflow_ids[] = $wid;
				}
			}
		}

		if ( $budget_id > 0 ) {
			$existing = SOM_Budgets::get( $budget_id );
			if ( ! $existing ) {
				self::flash_notice( __( 'Budget not found.', 'order-machine' ), 'error', 'som_budget_missing' );
				wp_safe_redirect( SOM_Budgets::list_url() );
				exit;
			}

			if ( 'manual' === $existing->type ) {
				$data['funding_method'] = isset( $_POST['som_budget_funding_method'] ) ? wp_unslash( $_POST['som_budget_funding_method'] ) : $existing->funding_method;
				$data['funding_value']  = isset( $_POST['som_budget_funding_value'] ) ? wp_unslash( $_POST['som_budget_funding_value'] ) : $existing->funding_value;
			}

			$result = SOM_Budgets::update( $budget_id, $data );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_budget_error' );
				wp_safe_redirect( SOM_Budgets::detail_url( $budget_id ) );
				exit;
			}

			if ( 'manual' === $existing->type ) {
				$link_result = SOM_Budgets::set_product_links( $budget_id, $product_ids );
			} else {
				$link_result = SOM_Budgets::set_workflow_links( $budget_id, $workflow_ids );
			}
			if ( is_wp_error( $link_result ) ) {
				self::flash_notice( $link_result->get_error_message(), 'error', 'som_budget_links_error' );
				wp_safe_redirect( SOM_Budgets::detail_url( $budget_id ) );
				exit;
			}
		} else {
			$data['type'] = $type;
			if ( 'material' === $type ) {
				$data['material_id'] = isset( $_POST['som_budget_material_id'] ) ? (int) $_POST['som_budget_material_id'] : 0;
			} else {
				$data['funding_method'] = isset( $_POST['som_budget_funding_method'] ) ? wp_unslash( $_POST['som_budget_funding_method'] ) : '';
				$data['funding_value']  = isset( $_POST['som_budget_funding_value'] ) ? wp_unslash( $_POST['som_budget_funding_value'] ) : '';
			}

			$result = SOM_Budgets::create( $data );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_budget_error' );
				wp_safe_redirect( SOM_Budgets::detail_url( 'new' ) );
				exit;
			}

			$budget_id = (int) $result;

			if ( 'manual' === $type ) {
				$link_result = SOM_Budgets::set_product_links( $budget_id, $product_ids );
			} else {
				$link_result = SOM_Budgets::set_workflow_links( $budget_id, $workflow_ids );
			}
			if ( is_wp_error( $link_result ) ) {
				self::flash_notice( $link_result->get_error_message(), 'error', 'som_budget_links_error' );
				wp_safe_redirect( SOM_Budgets::detail_url( $budget_id ) );
				exit;
			}
		}

		self::flash_notice( __( 'Budget saved.', 'order-machine' ), 'success', 'som_budget_saved' );
		wp_safe_redirect( SOM_Budgets::detail_url( $budget_id ) );
		exit;
	}

	/**
	 * Save suppliers.
	 *
	 * @return void
	 */
	public static function handle_suppliers_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-suppliers' !== $page || ! isset( $_POST['som_save_supplier'] ) ) {
			return;
		}

		check_admin_referer( 'som_save_supplier', 'som_supplier_nonce' );

		$supplier_id = isset( $_POST['supplier_id'] ) ? (int) $_POST['supplier_id'] : 0;
		$data        = array(
			'name'         => isset( $_POST['som_supplier_name'] ) ? wp_unslash( $_POST['som_supplier_name'] ) : '',
			'website'      => isset( $_POST['som_supplier_website'] ) ? wp_unslash( $_POST['som_supplier_website'] ) : '',
			'contact_info' => isset( $_POST['som_supplier_contact'] ) ? wp_unslash( $_POST['som_supplier_contact'] ) : '',
			'notes'        => isset( $_POST['som_supplier_notes'] ) ? wp_unslash( $_POST['som_supplier_notes'] ) : '',
		);

		if ( $supplier_id > 0 ) {
			$result = SOM_Suppliers::update( $supplier_id, $data );
		} else {
			$result = SOM_Suppliers::create( $data );
			if ( ! is_wp_error( $result ) ) {
				$supplier_id = (int) $result;
			}
		}

		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_supplier_error' );
			wp_safe_redirect( $supplier_id > 0 ? SOM_Suppliers::detail_url( $supplier_id ) : SOM_Suppliers::detail_url( 'new' ) );
			exit;
		}

		self::flash_notice( __( 'Supplier saved.', 'order-machine' ), 'success', 'som_supplier_saved' );
		wp_safe_redirect( SOM_Suppliers::detail_url( $supplier_id ) );
		exit;
	}

	/**
	 * Save / receive / close purchase orders.
	 *
	 * @return void
	 */
	public static function handle_purchase_orders_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'som-purchase-orders' !== $page ) {
			return;
		}

		if ( isset( $_POST['som_receive_po'] ) ) {
			check_admin_referer( 'som_receive_po', 'som_receive_po_nonce' );
			$po_id  = isset( $_POST['po_id'] ) ? (int) $_POST['po_id'] : 0;
			$deltas = isset( $_POST['som_receive_qty'] ) && is_array( $_POST['som_receive_qty'] )
				? wp_unslash( $_POST['som_receive_qty'] )
				: array();

			$order_before = SOM_Purchase_Orders::get( $po_id );
			$result       = SOM_Purchase_Orders::receive( $po_id, $deltas );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_po_receive_error' );
				wp_safe_redirect( SOM_Purchase_Orders::receive_url( $po_id ) );
				exit;
			}

			self::flash_notice( __( 'Stock received.', 'order-machine' ), 'success', 'som_po_received' );

			$alert_lines = array();
			if ( $order_before && ! empty( $order_before->items ) ) {
				$seen = array();
				foreach ( $deltas as $item_id => $raw_delta ) {
					$item_id = (int) $item_id;
					$raw     = trim( (string) $raw_delta );
					if ( '' === $raw || ! is_numeric( $raw ) || (float) $raw <= 0 ) {
						continue;
					}
					foreach ( $order_before->items as $item ) {
						if ( (int) $item->id !== $item_id ) {
							continue;
						}
						$mid = (int) $item->material_id;
						if ( isset( $seen[ $mid ] ) ) {
							continue;
						}
						$seen[ $mid ] = true;
						foreach ( SOM_Material_Costing::goal_alerts_for_material( $mid ) as $alert ) {
							$alert_lines[] = sprintf(
								/* translators: 1: material name, 2: workflow name, 3: alert label */
								__( '%1$s — %2$s: %3$s', 'order-machine' ),
								$alert['material_name'],
								$alert['workflow_name'],
								SOM_Material_Costing::alert_label( $alert['level'] )
							);
						}
					}
				}
			}

			if ( $alert_lines ) {
				self::flash_notice(
					__( 'Cost goal alerts after receive:', 'order-machine' ) . ' ' . implode( '; ', $alert_lines ),
					'warning',
					'som_po_receive_alerts'
				);
			}

			$fresh = SOM_Purchase_Orders::get( $po_id );
			if ( $fresh && ! empty( $fresh->can_receive ) ) {
				wp_safe_redirect( SOM_Purchase_Orders::receive_url( $po_id ) );
			} else {
				wp_safe_redirect( SOM_Purchase_Orders::detail_url( $po_id ) );
			}
			exit;
		}

		if ( isset( $_POST['som_po_mark_received'] ) ) {
			check_admin_referer( 'som_po_mark_received', 'som_po_mark_received_nonce' );
			$po_id  = isset( $_POST['po_id'] ) ? (int) $_POST['po_id'] : 0;
			$result = SOM_Purchase_Orders::mark_received( $po_id );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_po_close_error' );
			} else {
				self::flash_notice( __( 'Purchase order marked as received.', 'order-machine' ), 'success', 'som_po_closed' );
			}
			wp_safe_redirect( SOM_Purchase_Orders::detail_url( $po_id ) );
			exit;
		}

		if ( isset( $_POST['som_po_cancel'] ) ) {
			check_admin_referer( 'som_po_cancel', 'som_po_cancel_nonce' );
			$po_id  = isset( $_POST['po_id'] ) ? (int) $_POST['po_id'] : 0;
			$result = SOM_Purchase_Orders::cancel( $po_id );
			if ( is_wp_error( $result ) ) {
				self::flash_notice( $result->get_error_message(), 'error', 'som_po_cancel_error' );
			} else {
				self::flash_notice( __( 'Purchase order cancelled.', 'order-machine' ), 'success', 'som_po_cancelled' );
			}
			wp_safe_redirect( SOM_Purchase_Orders::detail_url( $po_id ) );
			exit;
		}

		if ( ! isset( $_POST['som_save_po'] ) ) {
			return;
		}

		check_admin_referer( 'som_save_po', 'som_po_nonce' );

		$po_id = isset( $_POST['po_id'] ) ? (int) $_POST['po_id'] : 0;
		$existing = $po_id > 0 ? SOM_Purchase_Orders::get( $po_id ) : null;

		$data = array(
			'notes' => isset( $_POST['som_po_notes'] ) ? wp_unslash( $_POST['som_po_notes'] ) : '',
		);

		$can_edit_lines = ! $existing || ! empty( $existing->can_edit_lines );
		if ( $can_edit_lines ) {
			$data['supplier_id']   = isset( $_POST['som_po_supplier'] ) ? wp_unslash( $_POST['som_po_supplier'] ) : '';
			$data['order_date']    = isset( $_POST['som_po_order_date'] ) ? wp_unslash( $_POST['som_po_order_date'] ) : '';
			$data['shipping_cost'] = isset( $_POST['som_po_shipping'] ) ? wp_unslash( $_POST['som_po_shipping'] ) : '0';
			$data['other_cost']    = isset( $_POST['som_po_other'] ) ? wp_unslash( $_POST['som_po_other'] ) : '0';

			$materials = isset( $_POST['som_po_material'] ) && is_array( $_POST['som_po_material'] ) ? wp_unslash( $_POST['som_po_material'] ) : array();
			$qtys      = isset( $_POST['som_po_qty'] ) && is_array( $_POST['som_po_qty'] ) ? wp_unslash( $_POST['som_po_qty'] ) : array();
			$costs     = isset( $_POST['som_po_item_cost'] ) && is_array( $_POST['som_po_item_cost'] ) ? wp_unslash( $_POST['som_po_item_cost'] ) : array();
			$items     = array();
			foreach ( $materials as $key => $material_id ) {
				$items[] = array(
					'material_id'      => $material_id,
					'quantity_ordered' => isset( $qtys[ $key ] ) ? $qtys[ $key ] : '',
					'item_cost'        => isset( $costs[ $key ] ) ? $costs[ $key ] : '',
				);
			}
			$data['items'] = $items;
		}

		if ( $po_id > 0 ) {
			$result = SOM_Purchase_Orders::update( $po_id, $data );
		} else {
			$result = SOM_Purchase_Orders::create( $data );
			if ( ! is_wp_error( $result ) ) {
				$po_id = (int) $result;
			}
		}

		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_po_error' );
			wp_safe_redirect( $po_id > 0 ? SOM_Purchase_Orders::detail_url( $po_id ) : SOM_Purchase_Orders::detail_url( 'new' ) );
			exit;
		}

		self::flash_notice( __( 'Purchase order saved.', 'order-machine' ), 'success', 'som_po_saved' );
		wp_safe_redirect( SOM_Purchase_Orders::detail_url( $po_id ) );
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

		// Seed remove / restore (dev helpers).
		if ( isset( $_POST['som_remove_seed'] ) ) {
			self::handle_remove_seed();
			return;
		}
		if ( isset( $_POST['som_restore_seed'] ) ) {
			self::handle_restore_seed();
			return;
		}

		// Save settings form.
		if ( isset( $_POST['som_settings_nonce'] ) ) {
			self::handle_save_settings();
		}
	}

	/**
	 * Remove demo seed catalogue + related fixture orders.
	 *
	 * @return void
	 */
	private static function handle_remove_seed() {
		check_admin_referer( 'som_remove_seed', 'som_seed_nonce' );
		$result = SOM_Seed::remove_seed_data();
		$message = isset( $result['message'] ) ? (string) $result['message'] : __( 'Seed data removed.', 'order-machine' );
		self::flash_notice( $message, 'success', 'som_seed_removed' );
		wp_safe_redirect( admin_url( 'admin.php?page=som-settings' ) );
		exit;
	}

	/**
	 * Restore dummy credentials + seed catalogue.
	 *
	 * @return void
	 */
	private static function handle_restore_seed() {
		check_admin_referer( 'som_restore_seed', 'som_seed_nonce' );
		$result = SOM_Seed::restore_seed_data( true );
		if ( is_wp_error( $result ) ) {
			self::flash_notice( $result->get_error_message(), 'error', 'som_seed_restore_failed' );
		} else {
			$message = isset( $result['message'] ) ? (string) $result['message'] : __( 'Seed data restored.', 'order-machine' );
			self::flash_notice( $message, 'success', 'som_seed_restored' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=som-settings' ) );
		exit;
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

		$api_key = isset( $_POST['som_api_key'] ) ? (string) wp_unslash( $_POST['som_api_key'] ) : '';
		if ( ! empty( $_POST['som_regenerate_api_key'] ) ) {
			$api_key = wp_generate_password( 32, false, false );
		} elseif ( '' === trim( $api_key ) ) {
			$api_key = $prev['api_key'];
		}

		SOM_Settings::update(
			array(
				'n8n_base_url'                   => isset( $_POST['som_n8n_base_url'] ) ? wp_unslash( $_POST['som_n8n_base_url'] ) : '',
				'poll_interval_minutes'          => isset( $_POST['som_poll_interval'] ) ? (int) $_POST['som_poll_interval'] : 15,
				'engine_tick_interval_minutes'   => isset( $_POST['som_engine_tick_interval'] ) ? (int) $_POST['som_engine_tick_interval'] : 60,
				'token_refresh_interval_minutes' => isset( $_POST['som_token_refresh_interval'] ) ? (int) $_POST['som_token_refresh_interval'] : 30,
				'api_key'                        => $api_key,
				'python_binary'                  => isset( $_POST['som_python_binary'] ) ? wp_unslash( $_POST['som_python_binary'] ) : '',
				'mcp_enabled'                    => ! empty( $_POST['som_mcp_enabled'] ),
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
	 * AJAX: Preview Impact for an unsaved / in-progress PO form.
	 *
	 * @return void
	 */
	public static function ajax_preview_po_impact() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'order-machine' ) ), 403 );
		}

		check_ajax_referer( 'som_preview_po_impact', 'nonce' );

		$shipping  = isset( $_POST['shipping_cost'] ) ? wp_unslash( $_POST['shipping_cost'] ) : '0';
		$other     = isset( $_POST['other_cost'] ) ? wp_unslash( $_POST['other_cost'] ) : '0';
		$raw_items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		if ( is_string( $raw_items ) ) {
			$decoded   = json_decode( $raw_items, true );
			$raw_items = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw_items ) ) {
			$raw_items = array();
		}

		$items = array();
		foreach ( $raw_items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$items[] = array(
				'material_id'      => isset( $row['material_id'] ) ? (int) $row['material_id'] : 0,
				'quantity_ordered' => isset( $row['quantity_ordered'] ) ? $row['quantity_ordered'] : 0,
				'item_cost'        => isset( $row['item_cost'] ) ? $row['item_cost'] : 0,
			);
		}

		$preview = SOM_Material_Costing::preview_impact(
			array(
				'shipping_cost' => $shipping,
				'other_cost'    => $other,
				'items'         => $items,
			)
		);

		if ( is_wp_error( $preview ) ) {
			wp_send_json_error( array( 'message' => $preview->get_error_message() ) );
		}

		wp_send_json_success( $preview );
	}

	/**
	 * AJAX: toggle Order Board pin for current user.
	 *
	 * @return void
	 */
	public static function ajax_board_toggle_pin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'order-machine' ) ), 403 );
		}

		check_ajax_referer( 'som_orders_board', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		if ( $order_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'order-machine' ) ) );
		}

		$pinned = SOM_Orders::toggle_board_pin( $order_id );
		wp_send_json_success( array( 'pinned' => $pinned ) );
	}

	/**
	 * AJAX: save Order Board column order for current user.
	 *
	 * @return void
	 */
	public static function ajax_board_save_columns() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'order-machine' ) ), 403 );
		}

		check_ajax_referer( 'som_orders_board', 'nonce' );

		$raw = isset( $_POST['columns'] ) ? wp_unslash( $_POST['columns'] ) : array();
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$keys = array();
		foreach ( $raw as $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( '' !== $key ) {
				$keys[] = $key;
			}
		}

		SOM_Orders::set_board_column_order( $keys );
		wp_send_json_success( array( 'columns' => $keys ) );
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
