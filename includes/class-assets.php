<?php

namespace NinjaFakeOrder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Assets {

	public function init() {
		// Frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'Discount_offer_popup_assets' ) );
		// Admin Scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
	}


	public function frontend_styles() {
		// if (!is_checkout()) return;
		wp_enqueue_style( 'rs-blocker-style', plugin_dir_url( __DIR__ ) . 'assets/css/frontend.css', array(), NFOB_PLUGIN_VERSION );

		wp_enqueue_script( 'rs-order-blocker-script', plugin_dir_url( __DIR__ ) . 'assets/js/frontend.js', array( 'jquery' ), NFOB_PLUGIN_VERSION, true );
	}

	public function admin_styles( $hook ) {
		// Admin Faild orders page css
		if ( ! isset( $_GET['page'] ) || $_GET['page'] === 'rs-failed-orders' ) {
			wp_enqueue_style( 'rs-faild-orders-page-css', plugin_dir_url( __DIR__ ) . 'assets/css/admin-faild-orders-page.css', array(), NFOB_PLUGIN_VERSION );
		}

		// Admin settings page css
		if ( strpos( $hook, 'rs-order-blocker' ) === false ) {
			return;
		}
		wp_enqueue_style( 'rs-blocker-admin', plugin_dir_url( __DIR__ ) . 'assets/css/admin-settings.css', array(), NFOB_PLUGIN_VERSION );
	}


	public function Discount_offer_popup_assets() {
		global $post;

		if ( ! isset( $post ) ) {
			$post = get_post();
		}

		$current_url = home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ) );
		$current_url = strtolower( trailingslashit( $current_url ) );

		$should_load = false;

		// Detect standard WooCommerce cart/checkout
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$should_load = true;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$should_load = true;
		}

		// Detect Gutenberg blocks or shortcodes
		if ( is_singular() && isset( $post->post_content ) ) {
			$content = $post->post_content;

			if (
				has_shortcode( $content, 'woocommerce_cart' ) ||
				has_shortcode( $content, 'woocommerce_checkout' ) ||
				strpos( $content, 'wp:woocommerce/cart' ) !== false ||
				strpos( $content, 'wp:woocommerce/checkout' ) !== false
			) {
				$should_load = true;
			}

			// ✅ Detect CartFlows checkout step page
			if ( has_shortcode( $content, 'cartflows_step' ) || strpos( $content, 'wp:cartflows/step' ) !== false ) {
				$should_load = true;
			}
		}

		// URL fallback
		if (
			strpos( $current_url, '/cart/' ) !== false ||
			strpos( $current_url, '/checkout/' ) !== false ||
			strpos( $current_url, 'cartflows-step' ) !== false
		) {
			$should_load = true;
		}

		// Allow filter override
		$should_load = apply_filters( 'rs_popup_should_load_script', $should_load, $post );

		// Load assets if needed
		if ( $should_load ) {
			wp_enqueue_style(
				'rs-discount-popup-css',
				plugin_dir_url( __DIR__ ) . 'assets/css/frontend-discount-popup.css',
				array(),
				NFOB_PLUGIN_VERSION
			);

			wp_enqueue_script(
				'rs-discount-popup-js',
				plugin_dir_url( __DIR__ ) . 'assets/js/frontend-discount-popup.js',
				array( 'jquery' ),
				NFOB_PLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'rs-discount-popup-js',
				'rsPopupData',
				array(
					'cart_url'     => trailingslashit( wc_get_cart_url() ),
					'checkout_url' => trailingslashit( wc_get_checkout_url() ),
					'referrer'     => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '',
				)
			);
		}
	}
}
