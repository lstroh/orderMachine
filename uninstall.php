<?php
/**
 * Uninstall handler for Order Machine.
 *
 * Tables and `som_db_version` are intentionally preserved so order, stock,
 * and workflow data survive plugin deletion. Reactivation reuses existing tables.
 *
 * @package OrderMachine
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// No table drops. No option cleanup that would orphan schema version from data.
// Future: if a "wipe all data" setting is added, gate destructive cleanup on it.
