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
		add_action( 'admin_menu', array( $this, 'myplugin_activation_admin_menu' ) );

		// Hook the form processing function early to ensure it runs before the page content is rendered
		add_action( 'admin_init', array( $this, 'myplugin_process_form_submission' ) );
	}


	// Add a new top-level menu for your plugin
	// To enhance the usability and accessibility of your WordPress plugin, it’s crucial to add a new top-level menu in the WordPress admin dashboard. This menu serves as the entry point for users to access and manage your plugin’s settings and features.
	public function myplugin_activation_admin_menu() {
		if ( $this->myplugin_is_active() ) {
			
			// Add the menu pages that show if the plugin is active here
		} else {
			// Add a new top-level menu for license key activation
			add_menu_page(
				esc_html__( 'License', 'wcorder-blocker' ),   // Page title
				esc_html__( 'License', 'wcorder-blocker' ),   // Menu title
				'manage_options',                                 // Capability required to see the menu
				'myplugin-activation-page',                       // Menu slug
				array( $this, 'myplugin_activation_page_content' ),     // Function to display the page content
				'dashicons-admin-generic',              // Icon URL or Dashicon class
				20                                      // Position in the menu order
			);
		}
	}

	//Function to display the content of the custom admin page
	// Once the top - level menu is in place, the next step is to create a function that defines the content displayed on your custom admin page . This page allows users to configure plugin settings and view relevant information .
	// Create the Admin Page Content: Define a function that outputs the HTML for your admin page .

	public function myplugin_process_form_submission() {
		// Check if the form was submitted
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['myplugin_license_key'] ) ) {
			$license_key = sanitize_text_field( $_POST['myplugin_license_key'] );
			$activation  = $this->myplugin_send_activation_request( $license_key );

			// Check if the activation attempt was successful
			if ( 'success' === $activation['result'] ) {
				update_option( 'myplugin_is_active_option', true );

				// Redirect to your plugin admin page
				wp_redirect( admin_url( 'admin.php?page=myplugin-admin-page' ) );
				exit;
			} else {
				// Set an error message to be displayed on the activation page
				add_option( 'myplugin_error_message', 'Error: ' . $activation['message'] );
			}
		}
	}

	// Function to display the content of the custom admin page
	public function myplugin_activation_page_content() {
		// Retrieve and delete the error message if it exists
		$error_message = get_option( 'myplugin_error_message', false );
		if ( $error_message ) {
			delete_option( 'myplugin_error_message' );
		}

		// Display the admin page content
		?>
	<div class="wrap">
	<h1><?php esc_html_e( 'Enter License Key', 'wcorder-blocker' ); ?></h1>

		<?php if ( isset( $error_message ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $error_message, 'wcorder-blocker' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="submitted_text"> <?php esc_html_e( 'License Key', 'wcorder-blocker' ); ?></label></th>
				<td><input type="text" id="license_key" name="myplugin_license_key" value="" class="regular-text" /></td>
			</tr>
		</table>
		<?php submit_button( 'Activate' ); ?>
	</form>
	</div>
		<?php
	}

	// Define the function that handles checking whether or not the license key is active
	public function myplugin_is_active() {
		return get_option( 'myplugin_is_active_option', false );
	}

	// Define the function that sends the license key activation request
	// Activating a license key is essential for enabling premium features and services within your plugin. This step covers how to create a function that handles the activation process, sending necessary data to the remote server for validation.

	// Create the Activation Function: This function will be responsible for sending the license key and other required parameters to the licensing server.
	public function myplugin_send_activation_request( $license_key ) {
		// The URL of your WordPress website where the plugin is installed
		$post_url = 'https://dev-ridwan2.pantheonsite.io/'; // Update this with your actual site URL

		// The parameters for the API request
		$parameters = array(
			'fslm_v2_api_request' => 'activate',
			'fslm_api_key'        => '0A9Q5OXT13in3LGjM9F3', // Your API key
			'license_key'         => $license_key,
			'device_id'           => 'userdomain.ltd', // Update this with your actual device ID
		);

		// Make the HTTP request
		$response = wp_remote_post(
			$post_url,
			array(
				'body'      => $parameters,
				'timeout'   => 15,
				'sslverify' => false,
			)
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			return array(
				'result'  => 'error',
				'message' => $response->get_error_message(),
			);
		}

		// Decode the response
		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		// Return the result
		return $result;
	}
}
