<?php
namespace NinjaFakeOrder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Setup {

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'check_woocommerce_dependency' ) );
		add_action( 'admin_head', array( $this, 'block_default_admin_notices' ) );
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