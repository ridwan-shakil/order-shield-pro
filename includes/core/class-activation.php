<?php
/**
 * Plugin Activation Handler
 *
 * Responsible for:
 * - Creating required database tables
 * - Setting default plugin options
 * - Setting activation flags (redirect, etc.)
 *
 * @package OrderShieldPro
 */

namespace RS\OrderBlocker\Core;

use RS\OrderBlocker\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activation
 *
 * Handles all tasks that should run on plugin activation.
 *
 * IMPORTANT:
 * - No hooks should be registered here (activation runs once)
 * - Only idempotent operations (safe to run multiple times)
 */
class Activation {

	/**
	 * Run plugin activation tasks.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_default_options();
		self::create_incomplete_orders_table();
		self::set_activation_flags();
	}

	/**
	 * Create default plugin options if not already present.
	 *
	 * @return void
	 */
	private static function create_default_options(): void {
		$option_name = 'rs_order_blocker_settings';

		if ( false !== get_option( $option_name ) ) {
			return;
		}

		$settings     = new Settings();
		$default_opts = $settings->get_default_options();

		add_option( $option_name, $default_opts, '', false );
	}

	/**
	 * Set activation-only flags (e.g. redirect after activation).
	 *
	 * @return void
	 */
	private static function set_activation_flags(): void {
		update_option( 'rs_ob_redirect_to_license', true, false );
	}

	/**
	 * Create the incomplete orders database table.
	 *
	 * @return void
	 */
	private static function create_incomplete_orders_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'rs_order_shield_incomplete_orders';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(191) NOT NULL,
			status VARCHAR(20) NOT NULL,
			customer_name VARCHAR(191) NULL,
			email VARCHAR(191) NULL,
			mobile VARCHAR(50) NULL,
			cart_contents LONGTEXT NULL,
			total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY email (email),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
