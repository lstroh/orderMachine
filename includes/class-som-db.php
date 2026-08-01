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
	const DB_VERSION = '1.5.0';

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

		$sql[] = "CREATE TABLE {$p}som_suppliers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			website varchar(255) NULL,
			contact_info text NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_materials (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			unit varchar(20) NOT NULL,
			current_stock decimal(10,2) NOT NULL DEFAULT 0.00,
			low_stock_threshold decimal(10,2) NULL,
			unit_cost decimal(10,4) NULL,
			total_value_on_hand decimal(12,4) NOT NULL DEFAULT 0.0000,
			preferred_supplier_id bigint(20) unsigned NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY preferred_supplier_id (preferred_supplier_id)
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
			target_selling_price decimal(10,2) NULL,
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

		$sql[] = "CREATE TABLE {$p}som_batch_groups (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_key varchar(50) NOT NULL,
			display_name varchar(100) NOT NULL,
			batch_size int(11) NOT NULL DEFAULT 4,
			action_type enum('script','manual_confirm') NOT NULL DEFAULT 'manual_confirm',
			script_config text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY group_key (group_key)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_workflow_steps (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_template_id bigint(20) unsigned NOT NULL,
			step_order int(11) NOT NULL DEFAULT 1,
			name varchar(100) NOT NULL,
			requires_manual_confirm tinyint(1) NOT NULL DEFAULT 0,
			timer_seconds int(11) NULL,
			script_config text NULL,
			batch_group_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY workflow_template_id (workflow_template_id),
			KEY batch_group_id (batch_group_id)
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
			status enum('pending','in_progress','waiting_timer','waiting_script','waiting_batch','error','done') NOT NULL DEFAULT 'pending',
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

		$sql[] = "CREATE TABLE {$p}som_purchase_orders (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			supplier_id bigint(20) unsigned NOT NULL,
			order_date date NOT NULL,
			received_date date NULL,
			status enum('ordered','received','partially_received','cancelled') NOT NULL DEFAULT 'ordered',
			shipping_cost decimal(10,2) NOT NULL DEFAULT 0.00,
			other_cost decimal(10,2) NULL DEFAULT 0.00,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY supplier_id (supplier_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_purchase_order_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			purchase_order_id bigint(20) unsigned NOT NULL,
			material_id bigint(20) unsigned NOT NULL,
			quantity_ordered decimal(10,2) NOT NULL,
			quantity_received decimal(10,2) NULL,
			item_cost decimal(10,2) NOT NULL,
			allocated_shipping_cost decimal(10,4) NULL,
			allocated_other_cost decimal(10,4) NULL,
			landed_unit_cost decimal(10,4) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY purchase_order_id (purchase_order_id),
			KEY material_id (material_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_workflow_material_goals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_template_id bigint(20) unsigned NOT NULL,
			material_id bigint(20) unsigned NOT NULL,
			goal_unit_cost decimal(10,4) NOT NULL,
			warning_threshold_percent decimal(5,2) NOT NULL DEFAULT 90.00,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY workflow_material (workflow_template_id,material_id),
			KEY material_id (material_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_step_batches (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			batch_group_id bigint(20) unsigned NOT NULL,
			status enum('collecting','ready','processing','done','error') NOT NULL DEFAULT 'collecting',
			released_manually tinyint(1) NOT NULL DEFAULT 0,
			released_at datetime NULL,
			completed_at datetime NULL,
			last_error text NULL,
			retry_count int(11) NOT NULL DEFAULT 0,
			retry_after datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY batch_group_id (batch_group_id),
			KEY status (status),
			KEY retry_after (retry_after)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_step_batch_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			batch_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			workflow_step_id bigint(20) unsigned NOT NULL,
			added_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY batch_id (batch_id),
			KEY order_id (order_id),
			KEY workflow_step_id (workflow_step_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$p}som_material_stock_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			material_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NULL,
			change_qty decimal(10,2) NOT NULL,
			reason varchar(50) NOT NULL,
			purchase_order_item_id bigint(20) unsigned NULL,
			unit_cost_at_time decimal(10,4) NULL,
			value_change decimal(12,4) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY material_id (material_id),
			KEY order_id (order_id),
			KEY purchase_order_item_id (purchase_order_item_id)
		) {$charset_collate};";

		// Migrate reserved `key` → group_key before dbDelta so UNIQUE is not applied to empty placeholders.
		self::upgrade_batch_groups_key_column();

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::upgrade_progress_status_enum();
		self::upgrade_batch_groups_key_column();
		self::backfill_material_total_value();

		update_option( 'som_db_version', self::DB_VERSION );
	}

	/**
	 * Explicit ENUM ALTER — dbDelta does not reliably extend ENUM values.
	 *
	 * @return void
	 */
	private static function upgrade_progress_status_enum() {
		global $wpdb;

		$table = self::table( 'order_step_progress' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'status'" );
		if ( ! $col || ! isset( $col->Type ) ) {
			return;
		}

		$type = (string) $col->Type;
		if ( false !== strpos( $type, 'waiting_batch' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"ALTER TABLE {$table} MODIFY COLUMN status enum('pending','in_progress','waiting_timer','waiting_script','waiting_batch','error','done') NOT NULL DEFAULT 'pending'"
		);
	}

	/**
	 * Rename reserved `key` column to `group_key` (dbDelta-safe) if needed.
	 *
	 * @return void
	 */
	private static function upgrade_batch_groups_key_column() {
		global $wpdb;

		$table = self::table( 'batch_groups' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has_group_key = (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'group_key'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has_key       = (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'key'" );

		if ( $has_key && ! $has_group_key ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$table} CHANGE COLUMN `key` group_key varchar(50) NOT NULL" );
			$has_group_key = true;
			$has_key       = false;
		} elseif ( $has_key && $has_group_key ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"UPDATE {$table}
				SET group_key = `key`
				WHERE ( group_key IS NULL OR group_key = '' ) AND `key` IS NOT NULL AND `key` <> ''"
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$table} DROP COLUMN `key`" );
			$has_key = false;
		}

		// Drop legacy unique index name if present (from early WIP).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}" );
		if ( is_array( $indexes ) ) {
			foreach ( $indexes as $idx ) {
				if ( isset( $idx->Key_name ) && 'batch_group_key' === $idx->Key_name ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( "ALTER TABLE {$table} DROP INDEX batch_group_key" );
					break;
				}
			}
		}

		if ( $has_group_key ) {
			$has_unique = false;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}" );
			if ( is_array( $indexes ) ) {
				foreach ( $indexes as $idx ) {
					if ( isset( $idx->Key_name, $idx->Non_unique, $idx->Column_name )
						&& 'group_key' === $idx->Key_name
						&& 0 === (int) $idx->Non_unique
						&& 'group_key' === $idx->Column_name ) {
						$has_unique = true;
						break;
					}
				}
			}
			if ( ! $has_unique ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY group_key (group_key)" );
			}
		}
	}

	/**
	 * Seed total_value_on_hand from existing unit_cost × current_stock (idempotent).
	 *
	 * Only rows still at the column default (0) with a known unit_cost are updated,
	 * so later weighted-average values are not overwritten.
	 *
	 * @return void
	 */
	private static function backfill_material_total_value() {
		global $wpdb;

		$table = self::table( 'materials' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"UPDATE {$table}
			SET total_value_on_hand = ROUND( current_stock * unit_cost, 4 )
			WHERE total_value_on_hand = 0
			  AND unit_cost IS NOT NULL
			  AND current_stock <> 0"
		);
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
			return;
		}

		// Idempotent repairs when already on current version (e.g. mid-WIP column rename).
		self::upgrade_batch_groups_key_column();
		self::upgrade_progress_status_enum();
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
