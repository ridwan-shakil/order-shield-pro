<?php

namespace RS\OrderBlocker;

require_once plugin_dir_path( __FILE__ ) . 'admin/class-failed-orders-table.php';

require_once plugin_dir_path( __FILE__ ) . 'admin/class-failed-orders-page.php';

require_once plugin_dir_path( __FILE__ ) . 'admin/class-fraud-analytics-page.php';

// require_once plugin_dir_path( __FILE__ ) . 'admin/class-admin-workflow-page.php';


use RS\OrderBlocker\Admin\FailedOrdersPage;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	private $option_group = 'rs_order_blocker_settings_group';
	private $option_name  = 'rs_order_blocker_settings';
	private $default_opts = array();

	public function __construct() {
		// Default settings
		$this->default_opts = array(
			'enable_blocking'           => 1,
			'enable_call_button'        => 1,
			'enable_whatsapp_button'    => 1,
			'enable_number_validation'  => 0,
			'popup_message'             => 'দুঃখিত, আপনার অলরেডি একটি অর্ডার সাবমিট করা আছে। আরেকটি অর্ডার করতে দয়া করে নীচের নাম্বারে যোগাযোগ করুন:',
			'cookie_expire_days'        => 0,
			'cookie_expire_hours'       => 0,
			'cookie_expire_minutes'     => 30,
			'call_number'               => '017********',
			'whatsapp_number'           => '017********',
			'call_btn_text'             => '📞 Call Us',
			'whatsapp_btn_text'         => '💬 WhatsApp',
			'popup_text_color'          => '#ffffff',
			'popup_bg_color'            => '#ff0000',
			'popup_button_text_color'   => '#000000',
			'popup_button_color'        => '#ffffff',
			'popup_font_family'         => 'Segoe UI',
			'popup_font_size'           => '18',
			'popup_padding'             => '20',
			'popup_margin'              => '10',
			'popup_box_shadow'          => '2px 2px 6px #131313ff',
			'invalid_phone_alert'       => 'দুঃখিত, আপনি একটি ভুল নাম্বার লিখেছেন। দয়া করে ১১ ডিজিটের সঠিক নাম্বারটি লিখুন:',
			'show_abandon_popup'        => 0,
			'abandon_popup_message'     => 'Wait! Don’t leave — here’s a special offer!',
			'abandon_popup_coupon'      => 'SAVE10',
			'abandon_popup_logo'        => '',
			'abandon_popup_text_color'  => '#ffffff',
			'abandon_popup_bg_color'    => '#00ff6676',
			'abandon_popup_font_family' => 'Segoe UI',
			'abandon_popup_font_size'   => '18',
			'abandon_popup_padding'     => '20',
			'abandon_popup_box_shadow'  => '2px 2px 6px #00000076',

		);

		// Hook into admin
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	// Add submenu page under WooCommerce
	public function add_settings_page() {

		add_submenu_page(
			'rs-order-blocker',
			__( 'Order Blocker Settings', 'wcorder-blocker' ),
			__( 'Order Blocker Settings', 'wcorder-blocker' ),
			'manage_woocommerce',
			'rs-order-blocker-settings',
			array( $this, 'render_settings_page' ),
		);

		add_submenu_page(
			'rs-order-blocker',
			__( 'Incomplete Orders', 'wcorder-blocker' ),
			__( 'Incomplete Orders', 'wcorder-blocker' ),
			'manage_woocommerce',
			'rs-failed-orders',
			array( FailedOrdersPage::class, 'render' )
		);

		add_submenu_page(
			'rs-order-blocker',
			__( 'Fraud Analytics', 'wcorder-blocker' ),
			__( 'Fraud Analytics', 'wcorder-blocker' ),
			'manage_woocommerce',
			'rs-fraud-analytics',
			array( \RS\OrderBlocker\Admin\FraudAnalyticsPage::class, 'render' )
		);

		// Add export action for product risk list table
		add_action(
			'admin_init',
			function () {
				if (
				isset( $_GET['page'], $_GET['action'] ) &&
				$_GET['page'] === 'rs-fraud-analytics' &&
				$_GET['action'] === 'export_product_risk'
				) {
					// ✅ Load the class manually BEFORE calling it
					require_once plugin_dir_path( __FILE__ ) . '/admin/class-product-risk-list-table.php';

					\RS\OrderBlocker\Admin\Product_Risk_List_Table::handle_export();
				}
			}
		);

		add_action(
			'admin_init',
			function () {
				if (
				isset( $_GET['page'], $_GET['action'] ) &&
				$_GET['page'] === 'rs-fraud-analytics' &&
				$_GET['action'] === 'export_customer_risk'
				) {
					require_once plugin_dir_path( __FILE__ ) . 'admin/class-customer-risk-list-table.php';
					\RS\OrderBlocker\Admin\Customer_Risk_List_Table::handle_export();
				}
			}
		);
	}

	// Render the settings form
	public function render_settings_page() {
		// Default tab (if none stored).
		$active_tab = 'rs_block_section';
		?>
		<div class="wrap rs-admin-wrapper">
			<h1>🛡️ <?php echo esc_html__( 'Order Shield Pro', 'wcorder-blocker' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="#rs_block_section" class="nav-tab nav-tab-active">
					🚫 <?php esc_html_e( 'Order Blocking Rules', 'wcorder-blocker' ); ?>
				</a>
				<a href="#popup_design_section" class="nav-tab">

					🎨 <?php esc_html_e( 'Order Blocking Alert Design', 'wcorder-blocker' ); ?>
				</a>
				<a href="#abandon_popup_section" class="nav-tab">
					🏷️ <?php esc_html_e( 'Discount Offer Popup', 'wcorder-blocker' ); ?>
				</a>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( $this->option_group ); ?>

				<!-- Will be set dynamically via JS -->
				<input type="hidden" name="rs_active_tab" id="rs_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

				<?php
				// Local helper for 2-col layout.
				$render_section_2col = function ( $page, $section_id ) {
					global $wp_settings_sections, $wp_settings_fields;

					if ( empty( $wp_settings_sections[ $page ][ $section_id ] ) ) {
						return;
					}

					$section = $wp_settings_sections[ $page ][ $section_id ];

					if ( ! empty( $section['title'] ) ) {
						echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
					}

					if ( ! empty( $section['callback'] ) && is_callable( $section['callback'] ) ) {
						call_user_func( $section['callback'], $section );
					}

					ob_start();
					echo '<table class="form-table"><tbody>';
					do_settings_fields( $page, $section_id );
					echo '</tbody></table>';
					$table_html = ob_get_clean();

					$rows = array();
					if ( preg_match_all( '/<tr\b[^>]*>[\s\S]*?<\/tr>/i', $table_html, $m ) ) {
						$rows = $m[0];
					}

					if ( empty( $rows ) ) {
						echo $table_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						return;
					}

					$total = count( $rows );
					$half  = (int) ceil( $total / 2 );

					$left_rows  = array_slice( $rows, 0, $half );
					$right_rows = array_slice( $rows, $half );

					echo '<div class="rs-two-col-wrap">';
					echo '  <table class="form-table"><tbody>' . implode('', $left_rows) . '</tbody></table>';   // phpcs:ignore
					echo '  <table class="form-table"><tbody>' . implode('', $right_rows) . '</tbody></table>'; // phpcs:ignore
					echo '</div>';
				};
		?>

				<div id="rs_block_section" class="rs-settings-tab-content" style="display:block;">
					<?php $render_section_2col( 'rs-order-blocker', 'rs_block_section' ); ?>
				</div>

				<div id="popup_design_section" class="rs-settings-tab-content" style="display:none;">
					<?php $render_section_2col( 'rs-order-blocker', 'popup_design_section' ); ?>
				</div>

				<div id="abandon_popup_section" class="rs-settings-tab-content" style="display:none;">
					<?php $render_section_2col( 'rs-order-blocker', 'abandon_popup_section' ); ?>
				</div>

				<?php submit_button( __( '⎙ Save Settings', 'wcorder-blocker' ) ); ?>
			</form>
		</div>

		<script>
			jQuery(function($) {
				// Handle tab click
				$('.nav-tab-wrapper a').on('click', function(e) {
					e.preventDefault();
					var tabId = $(this).attr('href');

					// Switch active tab
					$('.nav-tab-wrapper a').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active');

					// Show matching section
					$('.rs-settings-tab-content').hide();
					$(tabId).show();

					// Update hidden input
					$('#rs_active_tab').val(tabId);

					// Save in localStorage
					localStorage.setItem('rs_active_tab', tabId);
				});

				// Restore last active tab after reload
				var savedTab = localStorage.getItem('rs_active_tab');
				if (savedTab && $(savedTab).length) {
					$('.nav-tab-wrapper a[href="' + savedTab + '"]').trigger('click');
				}
			});
		</script>

		<style>
			.rs-two-col-wrap {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 24px;
				align-items: start;
				margin-top: 8px;
			}

			.rs-two-col-wrap .form-table {
				margin-top: 0;
				width: 100%;
			}

			@media (max-width: 1024px) {
				.rs-two-col-wrap {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<?php
	}













	// Register settings and UI fields
	public function register_settings() {
		// Register the setting with default values and sanitize callback
		register_setting(
			$this->option_group,
			$this->option_name,
			array(
				'default'           => $this->default_opts,
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);

		// Section for blocking configuration
		add_settings_section(
			'rs_block_section',
			'Order Blocking Rules :',
			function () {
				echo '<h4>After placing an order user can\'t place another order for below period of time:</h4>';
			},
			'rs-order-blocker'
		);

		// Toggle switches
		$this->add_toggle_field( 'enable_number_validation', 'Enable Number Validation', 'This will ensure users enter a valid Bangladeshi number.' );

		$this->add_toggle_field( 'enable_blocking', 'Enable Blocking', 'Block duplicate orders by ( Phone, Email, IP, and Device ) ' );

		// Time limit fields
		$this->add_number_field( 'cookie_expire_days', 'Block Orders For (Days)', 0, 30, 'rs_block_section' );
		$this->add_number_field( 'cookie_expire_hours', 'Block Orders For (Hours)', 0, 23, 'rs_block_section' );
		$this->add_number_field( 'cookie_expire_minutes', 'Block Orders For (Minutes)', 0, 59, 'rs_block_section' );

		// Contact fields
		$this->add_toggle_field( 'enable_call_button', 'Enable Call Button' );
		$this->add_text_field( 'call_number', 'Phone Number', 'e.g. 017********' );
		$this->add_text_field( 'call_btn_text', 'Call Button Text', 'e.g. 📞 Call Us' );

		$this->add_toggle_field( 'enable_whatsapp_button', 'Enable WhatsApp Button' );
		$this->add_text_field( 'whatsapp_number', 'WhatsApp Number', 'e.g. 017********' );
		$this->add_text_field( 'whatsapp_btn_text', 'WhatsApp Button Text', 'e.g. 💬 WhatsApp' );

		// -------------------Design section-------------------
		add_settings_section(
			'popup_design_section',
			'Order Blocking Alert Design :',
			function () {
				echo '<h4>Customize the alert style, that will be shown for duplicate orders & invalid phone numbers.</h4>';
			},
			'rs-order-blocker'
		);

		// Textarea for popup message
		$this->add_field(
			'popup_message',
			'Block Order Alert Text',
			function () {
				$opts        = get_option( $this->option_name, array() );
				$default_msg = $this->default_opts['popup_message'];
				?>
			<textarea name="<?php echo $this->option_name; ?>[popup_message]" rows="3" cols="50"
				placeholder="<?php echo esc_attr( $default_msg ); ?>"><?php echo esc_textarea( $opts['popup_message'] ?? '' ); ?></textarea>
			<p class="description"> <?php esc_html_e( 'Leave blank to use default message.', 'wcorder-blocker' ); ?> </p>
				<?php
			}
		);

		// Color pickers
		$this->add_color_field( 'popup_text_color', 'Popup Text Color', '#ffffff' );
		$this->add_color_field( 'popup_bg_color', 'Popup Background Color', '#ff0000' );
		$this->add_color_field( 'popup_button_text_color', 'Popup Button Text Color', '#ffffff' );
		$this->add_color_field( 'popup_button_color', 'Popup Button Color', '#000000' );

		$this->add_text_field( 'popup_box_shadow', 'Popup Box Shadow (CSS)', 'e.g. 2px 2px 6px black' );

		// Textarea for invalid phone number alert
		$this->add_wrong_number_textarea_field( 'invalid_phone_alert', 'Invalid Phone Alert Text', 'popup_design_section' );

		// Additional design fields
		$this->add_text_field( 'popup_font_family', 'Font Family', 'e.g. Segoe UI, Arial' );
		$this->add_number_field( 'popup_font_size', 'Font Size (px)', 10, 30 );
		$this->add_number_field( 'popup_padding', 'Popup Padding (px)', 0, 100 );
		$this->add_number_field( 'popup_margin', 'Popup Margin (px)', 0, 100 );

		// -------------Discount offer popup section--------------
		add_settings_section(
			'abandon_popup_section',
			__( 'Discount Offer Popup :', 'wcorder-blocker' ),
			function () {
				echo '<h4>' . esc_html__( 'Show a coupon popup when users try to exit the cart or checkout without ordering.', 'wcorder-blocker' ) . '</h4>';
			},
			'rs-order-blocker'
		);

		$this->add_toggle_field( 'show_abandon_popup', __( 'Enable Exit Popup Offer', 'wcorder-blocker' ) );
		$this->add_text_field( 'abandon_popup_message', __( 'Popup Message', 'wcorder-blocker' ), 'e.g. Wait! Here’s a special offer just for you' );
		$this->add_text_field( 'abandon_popup_coupon', __( 'Coupon Code', 'wcorder-blocker' ), 'e.g. SAVE10' );
		$this->add_text_field( 'abandon_popup_logo', __( 'Popup Image/Logo URL', 'wcorder-blocker' ), 'Paste image URL (media or hosted)' );

		$this->add_text_field( 'abandon_popup_font_family', 'Font Family', 'e.g. Segoe UI, Arial' );

		// Color pickers
		$this->add_color_field( 'abandon_popup_text_color', 'Popup Text Color', '#ffffff' );
		$this->add_color_field( 'abandon_popup_bg_color', 'Popup Background Color', '#0e852eff' );

		// Additional design fields
		$this->add_number_field( 'abandon_popup_font_size', 'Font Size (px)', 10, 30 );
		$this->add_number_field( 'abandon_popup_padding', 'Popup Padding (px)', 0, 100 );
		$this->add_text_field( 'abandon_popup_box_shadow', 'Popup Box Shadow (CSS)', 'e.g. 2px 2px 6px black' );
	}




	// Sanitize and validate settings
	public function sanitize_options( $input ) {
		$output = array();
		foreach ( $this->default_opts as $key => $default ) {
			if ( in_array( $key, array( 'enable_blocking', 'enable_call_button', 'enable_whatsapp_button', 'enable_number_validation' ) ) ) {

				$output[ $key ] = isset( $input[ $key ] ) ? 1 : 0;
			} elseif ( in_array( $key, array( 'cookie_expire_days', 'cookie_expire_hours', 'cookie_expire_minutes', 'popup_font_size', 'popup_padding', 'popup_margin' ) ) ) {
				$output[ $key ] = isset( $input[ $key ] ) ? intval( $input[ $key ] ) : intval( $default );
			} else {
				$output[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $default;
			}
		}
		return $output;
	}

	public function get_default_options() {
		return $this->default_opts;
	}

	// Helper to add textarea for invalid phone message
	private function add_wrong_number_textarea_field( $id, $label, $section ) {
		$this->add_field(
			$id,
			$label,
			function () use ( $id ) {
				$opts = get_option( $this->option_name, array() );
				?>
			<textarea name="<?php echo $this->option_name; ?>[<?php echo $id; ?>]" rows="3" cols="50"> <?php echo esc_textarea( $opts[ $id ] ?? '' ); ?> </textarea>
			<p class="description"><?php esc_html_e( 'Leave blank to use default message.', 'wcorder-blocker' ); ?> </p>
				<?php
			},
			$section
		);
	}

	// Toggle field UI
	private function add_toggle_field( $id, $label, $description = '', $section = null ) {
		$this->add_field(
			$id,
			$label,
			function () use ( $id, $description ) {
				$opts       = get_option( $this->option_name, array() );
				$default    = isset( $this->default_opts[ $id ] ) ? $this->default_opts[ $id ] : 0;
				$is_enabled = isset( $opts[ $id ] ) ? (bool) $opts[ $id ] : (bool) $default;
				$checked    = $is_enabled ? 'checked' : '';
				?>
			<label class="rs-toggle-switch">
				<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $id ); ?>]" value="1" <?php echo $checked; ?> />
				<span class="rs-slider"></span>
			</label>
				<?php if ( $description ) : ?>
				<span style="margin-left:10px;"> <?php echo esc_html( $description ); ?> </span>
			<?php endif; ?>
				<?php
			},
			$section
		);
	}

	// Main wrapper for all fields
	private function add_field( $id, $title, $callback, $section = null ) {
		if ( ! $section ) {
			if ( strpos( $id, 'popup_' ) === 0 ) {
				$section = 'popup_design_section';
			} elseif ( strpos( $id, 'abandon_' ) === 0 || strpos( $id, 'show_abandon' ) === 0 ) {
				$section = 'abandon_popup_section';
			} else {
				$section = 'rs_block_section';
			}
		}

		add_settings_field( $id, $title, $callback, 'rs-order-blocker', $section );
	}


	// Text field
	private function add_text_field( $id, $label, $placeholder = '', $section = null ) {
		$this->add_field(
			$id,
			$label,
			function () use ( $id, $placeholder ) {
				$opts    = get_option( $this->option_name, array() );
				$default = $this->default_opts[ $id ] ?? '';
				$value   = $opts[ $id ] ?? $default;
				?>
			<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $id ); ?>]"
				value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
				<?php
			},
			$section
		);
	}

	// Color field
	private function add_color_field( $id, $label, $default_color, $section = null ) {
		$this->add_field(
			$id,
			$label,
			function () use ( $id, $default_color ) {
				$opts  = get_option( $this->option_name, array() );
				$value = isset( $opts[ $id ] ) && trim( $opts[ $id ] ) !== '' ? $opts[ $id ] : $default_color;
				?>
			<input type="color" name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $id ); ?>]"
				value="<?php echo esc_attr( $value ); ?>">
				<?php
			},
			$section
		);
	}

	// Number field
	private function add_number_field( $id, $label, $min = 0, $max = 100, $section = null ) {
		$this->add_field(
			$id,
			$label,
			function () use ( $id, $min, $max ) {
				$opts    = get_option( $this->option_name, array() );
				$default = $this->default_opts[ $id ] ?? 0;
				$value   = isset( $opts[ $id ] ) ? $opts[ $id ] : $default;
				?>
			<input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $id ); ?>]"
				value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>">
				<?php
			},
			$section
		);
	}


	// Discount offer popup on cart & checkout page
	public static function render_abandon_popup_offer() {
		if ( is_admin() || ( ! is_cart() && ! is_checkout() ) ) {
			return;
		}

		$opts = get_option( 'rs_order_blocker_settings', array() );

		if ( empty( $opts['show_abandon_popup'] ) ) {
			return;
		}

		$msg    = esc_html( $opts['abandon_popup_message'] ?? 'Wait! Don’t leave — here’s a special offer!' );
		$coupon = esc_html( $opts['abandon_popup_coupon'] ?? 'SAVE10' );
		$logo   = esc_url( $opts['abandon_popup_logo'] ?? '' );

		// Styling
		$text_color  = esc_attr( $opts['abandon_popup_text_color'] ?? '#ffffff' );
		$bg_color    = esc_attr( $opts['abandon_popup_bg_color'] ?? '#00ff9954' );
		$font_family = esc_attr( $opts['abandon_popup_font_family'] ?? 'Segoe UI' );
		$font_size   = intval( $opts['abandon_popup_font_size'] ?? 18 );
		$padding     = intval( $opts['abandon_popup_padding'] ?? 20 );
		$box_shadow  = esc_attr( $opts['abandon_popup_box_shadow'] ?? '2px 2px 6px #141414ff' );

		?>
		<style>
			#rs-popup {
				background: <?php echo esc_attr( $bg_color ); ?>;
				color: <?php echo esc_attr( $text_color ); ?>;
				font-family: <?php echo esc_attr( $font_family ); ?>;
				font-size: <?php echo absint( $font_size ); ?>px;
				padding: <?php echo absint( $padding ); ?>px;
				box-shadow: <?php echo esc_attr( $box_shadow ); ?>;
				width: 90%;
				max-width: 400px;
				margin: 100px auto;
				text-align: center;
				border-radius: 8px;
			}
		</style>

		<div id="rs-popup-wrapper">
			<div id="rs-popup">
				<?php if ( $logo ) : ?>
					<img src="<?php echo $logo; ?>" alt="Offer">
				<?php endif; ?>

				<div class="rs-popup-msg"><?php esc_html_e( $msg ); ?></div>

				<div class="rs-popup-code" style="margin: 10px 0; font-size: 20px; font-weight: bold;">
					<?php esc_html_e( $coupon ); ?>
				</div>

				<button class="rs-copy-btn"><?php esc_html_e( 'Copy Coupon', 'wcorder-blocker' ); ?></button>
			</div>

		</div>
		<?php
	}
}
