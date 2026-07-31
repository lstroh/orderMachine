<?php
/**
 * Database schema (dbDelta) and table helpers for Order Machine.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades custom `{$wpdb->prefix}som_*` tables.
 */
class SOM_DB {

	/**
	 * Current schema version stored in the `som_db_version` option.
	 *
	 * Bump when columns/indexes change so activation can migrate.
	 */
	const DB_VERSION = '1.3.0';

	/**
	 * Create or update all plugin tables via dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$p}som_channels (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(20) NOT NULL,
			display_name varchar(50) NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			credentials text NULL,
			last_synced_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_materials (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			unit varchar(20) NOT NULL,
			current_stock decimal(10,2) NOT NULL DEFAULT 0.00,
			low_stock_threshold decimal(10,2) NULL,
			unit_cost decimal(10,4) NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_workflow_templates (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			description text NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_products (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			sku varchar(50) NULL,
			workflow_template_id bigint(20) unsigned NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY workflow_template_id (workflow_template_id),
			KEY sku (sku)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_product_materials (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			material_id bigint(20) unsigned NOT NULL,
			quantity_per_unit decimal(10,2) NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY material_id (material_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_listings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			channel_id bigint(20) unsigned NOT NULL,
			external_listing_id varchar(100) NOT NULL,
			title varchar(255) NULL,
			description text NULL,
			price decimal(10,2) NOT NULL DEFAULT 0.00,
			quantity_available int(11) NOT NULL DEFAULT 0,
			inventory_json longtext NULL,
			last_synced_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY channel_id (channel_id),
			UNIQUE KEY channel_listing (channel_id,external_listing_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_workflow_steps (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_template_id bigint(20) unsigned NOT NULL,
			step_order int(11) NOT NULL DEFAULT 1,
			name varchar(100) NOT NULL,
			requires_manual_confirm tinyint(1) NOT NULL DEFAULT 0,
			timer_seconds int(11) NULL,
			script_config text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY workflow_template_id (workflow_template_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_orders (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			channel_id bigint(20) unsigned NOT NULL,
			external_order_id varchar(100) NOT NULL,
			order_date datetime NOT NULL,
			buyer_name varchar(150) NOT NULL DEFAULT '',
			shipping_address text NULL,
			current_step_id bigint(20) unsigned NULL,
			is_complete tinyint(1) NOT NULL DEFAULT 0,
			raw_payload longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY channel_order (channel_id,external_order_id),
			KEY order_date (order_date),
			KEY current_step_id (current_step_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_order_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NULL,
			quantity int(11) NOT NULL DEFAULT 1,
			personalisation_text text NULL,
			unit_price decimal(10,2) NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY product_id (product_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_order_step_progress (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			workflow_step_id bigint(20) unsigned NOT NULL,
			status enum('pending','in_progress','waiting_timer','waiting_script','error','done') NOT NULL DEFAULT 'pending',
			timer_ends_at datetime NULL,
			retry_count int(11) NOT NULL DEFAULT 0,
			last_error text NULL,
			started_at datetime NULL,
			completed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY workflow_step_id (workflow_step_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_material_stock_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			material_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NULL,
			change_qty decimal(10,2) NOT NULL,
			reason varchar(50) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY material_id (material_id),
			KEY order_id (order_id)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'som_db_version', self::DB_VERSION );
	}

	/**
	 * Ensure schema is up to date (activation or version bump).
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'som_db_version', '' );
		if ( $installed !== self::DB_VERSION ) {
			self::create_tables();
		}
	}

	/**
	 * Prefixed table name helper.
	 *
	 * @param string $suffix Table suffix without prefix (e.g. 'orders').
	 * @return string
	 */
	public static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'som_' . $suffix;
	}
}
