<?php

/**
 * Plugin Name: Order Shield Pro
 * Description: Blocks multiple WooCommerce orders from the same device, IP, or phone number until previous orders are completed. Includes customizable popup alerts, CartFlows support, and an admin settings panel.
 * Version: 1.0.5
 * Author: MD.Ridwan
 * Author URI: https://dev-mdridwan.pantheonsite.io/
 * Plugin URI: https://dev-mdridwan.pantheonsite.io/
 * Text Domain: wcorder-blocker
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin version constant

define( 'RS_ORDER_SHIELD_PRO_VERSION', '1.0.5' );
define( 'RSOSP_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'RSOSP_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'RSOSP_DIR_FILE', __FILE__ );


// Load core plugin class.
require_once RSOSP_DIR_PATH . 'includes/class-activation.php';
require_once RSOSP_DIR_PATH . 'includes/class-plugin.php';



// Register activation hook to run the activation class
function rsosp_run_activation() {
	$activation = new \RS\OrderBlocker\Core\Activation();
	$activation->activate();
}
register_activation_hook( __FILE__, 'rsosp_run_activation' );









/**
 * Plugin Update Checker
 * This will check for updates from the specified GitHub repository.
 * Note: This requires the plugin-update-checker library to be included.
 */
require 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$my_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/ridwan-shakil/order-shield-pro',
	__FILE__,
	'order-shield-pro'
);

//Set the branch that contains the stable release.
$my_update_checker->setBranch( 'main' );




/**
 * Plugin uninstall hook (optional fallback)
 * Note: This hook won't run if uninstall.php is present.
 */
register_uninstall_hook( __FILE__, 'rs_order_blocker_uninstall' );

function rs_order_blocker_uninstall() {
	// This function will not execute if uninstall.php exists,
	// but it's defined here as a fallback.
}
