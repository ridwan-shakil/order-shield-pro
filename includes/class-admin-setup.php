<?php
namespace RS\OrderBlocker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Setup {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'check_woocommerce_dependency' ) );
		add_action( 'admin_head', array( $this, 'block_default_admin_notices' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		$p     = $_GET['page'] ?? '';
		$files = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/';
		if ( $hook === 'toplevel_page_rs-order-blocker' || in_array( $p, array( 'rs-order-blocker-settings', 'rs-failed-orders', 'rs-blocked-users', 'rs-fraud-analytics' ) ) ) {
			wp_enqueue_style( 'ob-ui', $files . 'admin-license.css', array(), RS_ORDER_SHIELD_PRO_VERSION );
			wp_enqueue_style( 'ob-alert', $files . 'admin-license-alert.css', array(), RS_ORDER_SHIELD_PRO_VERSION );
		}
	}

	public function block_default_admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen ) return;

		$target_screens = array(
			'toplevel_page_rs-order-blocker',
			'order-blocker_page_rs-failed-orders',
			'order-blocker_page_rs-fraud-analytics',
			'order-blocker_page_rs-order-blocker-settings',
			'order-blocker_page_rs-blocked-users',
		);

		if ( in_array( $screen->id, $target_screens, true ) ) {
			echo '<style>.notice:not(.rs-notice), .update-nag { display: none !important; }</style>';
		}
	}

	public function check_woocommerce_dependency() {
		if ( class_exists( 'WooCommerce' ) ) return;
		
		$nonce = 'install-plugin_woocommerce';
		$slug  = 'woocommerce';
		$url   = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' )
			? wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=woocommerce' ), $nonce )
			: wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=' . $slug ), $nonce );
			
		echo "<div class='notice notice-error is-dismissible'><p><strong>Order Blocker:</strong> WooCommerce is required. </p><p><a href='" . esc_url( $url ) . "' class='button button-primary'>" . ( strpos( $url, 'activate' ) ? 'Activate' : 'Install' ) . ' WooCommerce</a></p></div>';
	}
}