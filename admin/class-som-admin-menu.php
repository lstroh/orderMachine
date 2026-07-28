<?php
/**
 * Admin menu registration for Order Machine.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level Order Machine admin menu (stub pages for Sprint 1).
 */
class SOM_Admin_Menu {

	/**
	 * Hook into admin_menu.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register top-level menu and placeholder page.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Order Machine', 'order-machine' ),
			__( 'Order Machine', 'order-machine' ),
			'manage_options',
			'som-orders',
			array( __CLASS__, 'render_placeholder' ),
			'dashicons-clipboard',
			26
		);
	}

	/**
	 * Temporary placeholder until Sprint 4 orders UI.
	 *
	 * @return void
	 */
	public static function render_placeholder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Order Machine', 'order-machine' ); ?></h1>
			<p><?php echo esc_html__( 'Plugin foundation is active. Orders list and detail screens arrive in a later sprint.', 'order-machine' ); ?></p>
		</div>
		<?php
	}
}
