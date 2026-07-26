<?php

/**
 * Plugin Name: Ninja Fake Order Blocker
 * Description: Blocks multiple WooCommerce orders from the same device, IP, or phone number until previous orders are completed. Includes customizable popup alerts, CartFlows support, and an admin settings panel.
 * Version: 1.0.5
 * Author: MD.Ridwan
 * Author URI: https://dev-mdridwan.pantheonsite.io/
 * Plugin URI: https://dev-mdridwan.pantheonsite.io/
 * Text Domain: ninja-fake-order-blocker
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// namespace NinjaFakeOrder;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin constants
define( 'NFOB_PLUGIN_VERSION', '1.0.5' );
define( 'NFOB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NFOB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NFOB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'NFOB_PLUGIN_FILE', __FILE__ );


// Load core plugin class
require_once NFOB_PLUGIN_DIR . 'includes/class-plugin.php';

require_once NFOB_PLUGIN_DIR . 'includes/class-settings.php';
require_once NFOB_PLUGIN_DIR . 'includes/class-checker.php';
require_once NFOB_PLUGIN_DIR . 'includes/class-assets.php';
require_once NFOB_PLUGIN_DIR . 'includes/class-incomplete-orders-tracker.php';
require_once NFOB_PLUGIN_DIR . 'includes/class-blocked-users.php';
require_once NFOB_PLUGIN_DIR . 'includes/class-admin-setup.php';

// load admin classes
require_once NFOB_PLUGIN_DIR . 'includes/admin/class-failed-orders-table.php';
require_once NFOB_PLUGIN_DIR . 'includes/admin/class-failed-orders-page.php';



// Add "Settings" link to plugin action links
add_filter(
	'plugin_action_links_' . NFOB_PLUGIN_BASENAME,
	function ( $links ) {
		$settings_url  = admin_url( 'admin.php?page=rs-order-blocker' );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'ninja-fake-order-blocker' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);

/**
 * Runs on plugin activation
 * Creates default settings if not already present
 */
register_activation_hook(
	__FILE__,
	function () {
		$option_name  = 'rs_order_blocker_settings';
		$default_opts = ( new \NinjaFakeOrder\Settings() )->get_default_options();

		if ( ! get_option( $option_name ) ) {
			add_option( $option_name, $default_opts );
		}
		//For redirection upon activation
		update_option( 'rs_ob_redirect_to_license', true );
	}
);


/**
 * Create table on plugin activation to store "incomplete orders"
 */
register_activation_hook(
	__FILE__,
	function () {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nfob_incomplete_orders';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(191) NOT NULL,
        status ENUM('cart', 'checkout', 'payment_failed') NOT NULL,
        customer_name VARCHAR(191),
        email VARCHAR(191),
        mobile VARCHAR(50),
        cart_contents LONGTEXT,
        total DECIMAL(10,2) DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX (session_id),
        INDEX (email),
        INDEX (status)
    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
);



/**
 * Plugin uninstall hook (optional fallback)
 * Note: This hook won't run if uninstall.php is present.
 */
register_uninstall_hook( __FILE__, 'rs_order_blocker_uninstall' );

function rs_order_blocker_uninstall() {
	// This function will not execute if uninstall.php exists,
	// but it's defined here as a fallback.
}
