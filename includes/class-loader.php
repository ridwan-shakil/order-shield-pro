<?php
/**
 * Main Loader class for NinjaFakeOrder.
 *
 * This file handles the loading and initialization of all core components
 * of the NinjaFakeOrder plugin, following the dependency injection pattern
 * by initializing various functional classes.
 *
 * @package ninja-fake-order-blocker
 * @since 1.0.0
 * @author MD.Ridwan <ridwansweb@email.com>
 */

namespace NinjaFakeOrder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * The main entry point and loader class for the NinjaFakeOrder plugin.
 *
 * This class instantiates and manages the core functional classes
 * responsible for activation, custom post types, admin interface,
 * assets, and AJAX handling.
 */
class Loader {

	protected $assets;
	protected $checker;
	protected $settings;
	protected $tracker;
	protected $blocked_users;
	protected $admin_setup;

	/**
	 * Initializes all core component classes.
	 *
	 * The constructor instantiates all dependent functional classes
	 * and assigns them to their respective properties.
	 * * @return void
	 */
	public function __construct() {
		$this->settings      = new Settings();
		$this->assets        = new Assets();
		$this->checker       = new Checker();
		$this->tracker       = new Tracker();
		$this->blocked_users = new BlockedUsers();
		$this->admin_setup   = new Admin_Setup();
	}


	/**
	 * Executes the core initialization routine for the plugin.
	 *
	 * This method calls the primary setup/init methods on each
	 * instantiated component class to register hooks and functionality.
	 * * @return void
	 */
	public function run() {
		$this->settings->init();
		$this->assets->init();
		$this->checker->init();
		$this->tracker->init();
		$this->blocked_users->init();
		$this->admin_setup->init();
	}
}
