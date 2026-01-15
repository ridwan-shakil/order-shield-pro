<?php
/**
 * This file will be responsible for this plugins license activation. on the main website there will be "WOOCOMMERCE LICENSE MANAGER" plugin which will handle license activations and deactivations.
 * This plugin will just make API calls to that plugin to activate/deactivate license on this site.
 */

namespace RS\OrderBlocker\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WLMAPI
 *
 * Handles WLM API calls.
 */
class WLMAPI {
	public function init() {
		add_action( 'admin_menu', array( $this, 'myplugin_license_activation_menu' ) );
	}


	// Add a new menu item to the WordPress admin dashboard. This menu item will link to the page where users can enter their credentials to activate their license.
	public function myplugin_license_activation_menu() {
		add_menu_page(
			'License Activation', // Page title
			'License Activation', // Menu title
			'manage_options',     // Capability
			'license-activation', // Menu slug
			'myplugin_license_activation_page' // Function to display the page content
		);
	}
}
