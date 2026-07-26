<?php
namespace NinjaFakeOrder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NinjaFakeOrder\Admin_Setup;

final class Plugin {


	public function __construct() {
		// Initialize the Admin Setup (Extracted Methods)
		// new Admin_Setup();

		add_action( 'plugins_loaded', array( $this, 'boot_plugin_core' ) );
		add_action( 'wp_footer', array( '\\NinjaFakeOrder\\Settings', 'render_abandon_popup_offer' ) );
	}

	public function boot_plugin_core() {

		foreach ( $this->get_core_classes() as $class_name ) {
			if ( class_exists( $class_name ) ) {
				new $class_name();
			}
		}

	}

	private function get_core_classes() {
		return array(
			'NinjaFakeOrder\\Settings',
			'NinjaFakeOrder\\Assets',
			'NinjaFakeOrder\\Checker',
			'NinjaFakeOrder\\Tracker',
			'NinjaFakeOrder\\BlockedUsers',
		);
	}
}

new Plugin();
