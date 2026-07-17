<?php
namespace RS\OrderBlocker;

use IdeoLogix\DigitalLicenseManagerClient\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the DLM PHP SDK manually
require_once plugin_dir_path( __FILE__ ) . '../dlm-php/autoload.php';


final class Plugin {

	private $dlm_api = null;

	public function __construct() {
		$this->load_dependencies();

		// Initialize the Admin Setup (Extracted Methods)
		new Admin_Setup();

		// Initialize the DLM SDK using restricted keys
		if ( class_exists( 'IdeoLogix\\DigitalLicenseManagerClient\\Service' ) ) {
			$this->dlm_api = new Service(
				'https://dev-mdridwan.pantheonsite.io', // Your DLM Server URL
				'ck_24ff5b2a95ea5ebe745ee12ef9427fc019c9b095', // Consumer Key
				'cs_82b23d4ad9acc7550ab7af72fb0d310c6f9a2777'  // Consumer Secret
			);
		}

		add_action( 'admin_init', array( $this, 'handle_redirects' ) );
		add_action( 'plugins_loaded', array( $this, 'boot_plugin_core' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
		add_action( 'wp_footer', array( '\\RS\\OrderBlocker\\Settings', 'render_abandon_popup_offer' ) );
	}

	/**
	 * Load all required core files.
	 */
	private function load_dependencies() {
		$files = array(
			'class-admin-setup.php',
			'class-settings.php',
			'class-checker.php',
			'class-assets.php',
			'class-incomplete-orders-tracker.php',
			'class-blocked-users.php',
		);

		foreach ( $files as $f ) {
			$file_path = plugin_dir_path( __FILE__ ) . $f;
			if ( file_exists( $file_path ) ) {
				include_once $file_path;
			}
		}
	}

	public function handle_redirects() {
		if ( ! is_admin() ) return;
		if ( get_option( 'rs_ob_redirect_to_license', 0 ) ) {
			delete_option( 'rs_ob_redirect_to_license' );
			wp_safe_redirect( admin_url( 'admin.php?page=rs-order-blocker' ) );
			exit;
		}
	}

	public function boot_plugin_core() {
		$this->perform_weekly_validation();

		if ( $this->is_license_valid() ) {
			foreach ( $this->get_core_classes() as $class_name ) {
				if ( class_exists( $class_name ) ) {
					new $class_name();
				}
			}
		}
	}

	public function register_admin_menus() {
		add_menu_page( 'Order Blocker', 'Order Blocker', 'manage_options', 'rs-order-blocker', array( $this, 'render_license_page' ), 'dashicons-shield', 25 );
		add_submenu_page( 'rs-order-blocker', 'License', 'License', 'manage_options', 'rs-order-blocker', array( $this, 'render_license_page' ) );

		if ( ! $this->is_license_valid() ) {
			$locked_pages = array(
				'Fake Order Blocker Settings' => 'rs-order-blocker-settings',
				'Failed Orders'          => 'rs-failed-orders',
				'Blocked Users'          => 'rs-blocked-users',
				'Fraud Analytics'        => 'rs-fraud-analytics',
			);
			foreach ( $locked_pages as $title => $slug ) {
				add_submenu_page( 'rs-order-blocker', $title, $title, 'manage_options', $slug, array( $this, 'render_locked_page' ) );
			}
		}
	}

	public function render_license_page() {
		$this->process_license_forms();

		$key    = get_option( 'rs_ob_license_key', '' );
		$status = get_option( 'rs_ob_license_status', '' );

		?>
		<div class="license-page" oncontextmenu="return false;" ondragstart="return false;" onselectstart="return false;">
			<div class="wrap ob-license-wrapper">
				<div class="license-heading">
					<h1 class="ob-license-title"><?php esc_html_e( 'Plugin License Activation', 'wcorder-blocker' ); ?></h1>
				</div>

				<?php if ( $status !== 'valid' ) : ?>
					<form method="post" class="ob-license-form">
						<?php wp_nonce_field( 'rs_license_action', 'rs_license_nonce' ); ?>
						<label for="rsk"><?php esc_html_e( 'Enter Your License Key:', 'wcorder-blocker' ); ?></label>
						<input name="rsk" id="rsk" type="text" value="" class="ob-license-input" placeholder="XXXX-XXXX-XXXX-XXXX" required />
						<button type="submit" class="ob-license-btn"><?php esc_html_e( 'Save & Activate', 'wcorder-blocker' ); ?></button>
					</form>
				<?php endif; ?>

				<?php if ( $key ) : ?>
					<form method="post" class="ob-license-remove-form" onsubmit="return confirm('Are you sure you want to remove this license?');">
						<input type="hidden" name="rsk_clear" value="1" />
						<button type="submit" class="button-secondary"><?php esc_html_e( 'Remove License', 'wcorder-blocker' ); ?></button>
					</form>
				<?php endif; ?>

				<?php if ( $status ) : ?>
					<div class="ob-license-status <?php echo ( $status === 'valid' ? 'ob-valid' : 'ob-invalid' ); ?>">
						<p>
							<?php esc_html_e( 'License status:', 'wcorder-blocker' ); ?>
							<strong>
								<?php echo esc_html( $status === 'valid' ? __( 'Active', 'wcorder-blocker' ) : ( $status === 'expired' ? __( 'Expired', 'wcorder-blocker' ) : ucfirst( $status ) ) ); ?>
							</strong>
						</p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function render_locked_page() {
		?>
		<div class="rs-license-blocker-wrap">
			<div class="rs-license-box">
				<h2><?php esc_html_e( '🔒 License Required', 'wcorder-blocker' ); ?></h2>
				<p><?php esc_html_e( 'Please activate your license key to unlock all features.', 'wcorder-blocker' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rs-order-blocker' ) ); ?>" class="rs-license-btn"><?php esc_html_e( 'Activate License', 'wcorder-blocker' ); ?></a>
			</div>
		</div>
		<?php
	}

	private function process_license_forms() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['rsk'] ) && check_admin_referer( 'rs_license_action', 'rs_license_nonce' ) ) {
			$new_key = sanitize_text_field( $_POST['rsk'] );
			$old_key = get_option( 'rs_ob_license_key', '' );
			
			if ( $new_key !== $old_key ) {
				update_option( 'rs_ob_license_status', '' );
				delete_option( 'rs_ob_activation_token' );
			}
			
			update_option( 'rs_ob_license_key', $new_key );
			$this->api_activate_license();
		}

		if ( isset( $_POST['rsk_clear'] ) ) {
			$this->api_deactivate_license();
			delete_option( 'rs_ob_license_key' );
			delete_option( 'rs_ob_license_status' );
			delete_option( 'rs_ob_already_activated' );
			delete_option( 'rs_ob_license_expires' );
			delete_option( 'rs_ob_activation_token' );
			wp_safe_redirect( admin_url( 'admin.php?page=rs-order-blocker' ) );
			exit;
		}
	}

	private function api_activate_license() {
		$key = get_option( 'rs_ob_license_key' );
		if ( ! $key || ! $this->dlm_api ) return;

		try {
			$response = $this->dlm_api->licenses()->activate( $key, array( 'label' => get_bloginfo( 'url' ) ) );
			$body     = $response->get_data();

			if ( ! empty( $body['token'] ) ) {
				update_option( 'rs_ob_already_activated', true );
				update_option( 'rs_ob_activation_token', $body['token'] ); 

				if ( ! empty( $body['license']['expires_at'] ) ) {
					update_option( 'rs_ob_license_expires', strtotime( $body['license']['expires_at'] ) );
				}
				$this->api_validate_license();
			} else {
				update_option( 'rs_ob_license_status', 'invalid' );
			}
		} catch ( \Exception $e ) {
			update_option( 'rs_ob_license_status', 'invalid' );
		}
	}

	private function api_validate_license() {
		$token = get_option( 'rs_ob_activation_token' );
		if ( ! $token || ! $this->dlm_api ) {
			update_option( 'rs_ob_license_status', 'invalid' );
			return;
		}

		try {
			$response = $this->dlm_api->licenses()->validate( $token );
			$body     = $response->get_data();

			$valid = ! empty( $body['id'] ) && empty( $body['deactivated_at'] );
			
			update_option( 'rs_ob_license_status', $valid ? 'valid' : 'invalid' );
			update_option( 'rs_ob_last_checked', time() );
		} catch ( \Exception $e ) {
			// Fail silently on timeout
		}
	}

	private function api_deactivate_license() {
		$token = get_option( 'rs_ob_activation_token' );
		if ( ! $token || ! $this->dlm_api ) return;

		try {
			$this->dlm_api->licenses()->deactivate( $token );
		} catch ( \Exception $e ) {
			// Fail silently if network fails during removal
		}
	}

	private function perform_weekly_validation() {
		if ( ! is_admin() ) return;

		$last_checked = get_option( 'rs_ob_last_checked', 0 );
		if ( time() - intval( $last_checked ) > WEEK_IN_SECONDS ) {
			$this->api_validate_license();
		}
	}

	private function is_license_valid() {
		return get_option( 'rs_ob_license_status' ) === 'valid';
	}

	private function get_core_classes() {
		return array(
			'RS\\OrderBlocker\\Settings',
			'RS\\OrderBlocker\\Assets',
			'RS\\OrderBlocker\\Checker',
			'RS\\OrderBlocker\\Tracker',
			'RS\\OrderBlocker\\BlockedUsers',
		);
	}
}

new Plugin();
