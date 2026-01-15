<?php

namespace RS\OrderBlocker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class blockedUsers {
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enque_admin_blocked_users_scripts' ) );
		add_action( 'admin_menu', array( $this, 'add_blocked_users_menu' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'stop_checkout_if_blocked_by_admin' ) );

		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'add_block_this_user_btn_to_edit_orders_page' ) );
		add_action( 'admin_init', array( $this, 'handle_block_this_uer_btn_action' ) );
		add_action( 'admin_notices', array( $this, 'show_success_notice_after_blocking' ) );
		add_action( 'admin_init', array( $this, 'maybe_cleanup_expired_blocks' ) );
	}

	public function enque_admin_blocked_users_scripts( $hook ) {
		$page = $_GET['page'] ?? '';
		if ( $page === 'rs-blocked-users' ) {
			wp_enqueue_style( 'ob-blocked-css', plugin_dir_url( __FILE__ ) . '../assets/css/admin-blocked-users.css', array(), '1.0' );
		}
	}

	public function add_block_this_user_btn_to_edit_orders_page( $order ) {
		$order_id = $order->get_id();
		$url      = wp_nonce_url(
			admin_url( "admin.php?action=rs_block_user&order_id={$order_id}" ),
			'rs_block_user_' . $order_id
		);
		echo '<div>';
		echo '<a href="' . esc_url( $url ) . '" class="button button-secondary" style="margin-top: 15px;">🔒 Block this user</a>';
		echo '</div>';
	}

	public function handle_block_this_uer_btn_action() {
		if (
			! current_user_can( 'manage_woocommerce' ) ||
			! isset( $_GET['action'] ) || $_GET['action'] !== 'rs_block_user' ||
			! isset( $_GET['order_id'] ) ||
			! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'rs_block_user_' . $_GET['order_id'] )
		) {
			return;
		}

		$order_id = absint( $_GET['order_id'] );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$blocked = get_option( 'rs_ob_blocked_users', array() );
		$email   = $order->get_billing_email();
		$mobile  = $order->get_billing_phone();
		$ip      = $order->get_customer_ip_address();

		// ✅ First check if user is already blocked
		foreach ( $blocked as $entry ) {
			if (
				( $email && $entry['email'] === $email ) ||
				( $mobile && $entry['mobile'] === $mobile )
			) {
				wp_redirect( admin_url( "post.php?post={$order_id}&action=edit&rs_user_blocked=exists" ) );
				exit;
			}
		}

		// ✅ Then add to the block list
		$blocked[] = array(
			'ip'         => $ip,
			'email'      => $email,
			'mobile'     => $mobile,
			'reason'     => __( 'Blocked from order page', 'wcorder-blocker' ),
			'created_at' => time(),
			'expires_at' => 0,
		);

		update_option( 'rs_ob_blocked_users', $blocked );

		wp_redirect( admin_url( "post.php?post={$order_id}&action=edit&rs_user_blocked=1" ) );
		exit;
	}


	public function show_success_notice_after_blocking() {
		if ( ! isset( $_GET['rs_user_blocked'] ) ) {
			return;
		}

		if ( $_GET['rs_user_blocked'] == '1' ) {
			echo '<div class="notice notice-success is-dismissible"><p>✅ User has been successfully blocked.</p></div>';
		} elseif ( $_GET['rs_user_blocked'] == 'exists' ) {
			echo '<div class="notice notice-warning is-dismissible"><p>⚠️ This user is already in the blocked list.</p></div>';
		}
	}





	public function add_blocked_users_menu() {
		add_submenu_page(
			'rs-order-blocker',
			__( 'Blocked List', 'wcorder-blocker' ),
			__( 'Blocked List', 'wcorder-blocker' ),
			'manage_options',
			'rs-blocked-users',
			array( $this, 'render_blocked_users_page' )
		);
	}


	public function stop_checkout_if_blocked_by_admin() {
		$blocked = get_option( 'rs_ob_blocked_users', array() );
		$now     = time();

		$ip     = $_SERVER['REMOTE_ADDR'];
		$email  = sanitize_email( $_POST['billing_email'] ?? '' );
		$mobile = sanitize_text_field( $_POST['billing_phone'] ?? '' );

		foreach ( $blocked as $item ) {
			$expired = $item['expires_at'] && $item['expires_at'] < $now;

			if ( $expired ) {
				continue;
			}

			if (
				( $item['mobile'] && $item['mobile'] === $mobile ) ||
				( $item['email'] && $item['email'] === $email ) ||
				( $item['ip'] && strpos( $ip, $item['ip'] ) !== false )
			) {
				wc_add_notice( __( 'Your order has been temporarily blocked due to suspicious activity. If you believe this is a mistake, please contact our support team for assistance. ' ), 'error' );
				break;
			}
		}
	}


	// remove expired blocked users
	public function maybe_cleanup_expired_blocks() {
		$list = get_option( 'rs_ob_blocked_users', array() );
		$now  = time();

		$filtered = array_filter(
			$list,
			function ( $item ) use ( $now ) {
				return $item['expires_at'] === 0 || $item['expires_at'] > $now;
			}
		);

		if ( count( $filtered ) !== count( $list ) ) {
			update_option( 'rs_ob_blocked_users', array_values( $filtered ) );
		}
	}





	public function render_blocked_users_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$list = get_option( 'rs_ob_blocked_users', array() );

		// Add a user to the blocklist
		if ( isset( $_POST['rs_ob_add_block'] ) ) {
			check_admin_referer( 'rs_ob_add_block' );
			$mobile  = sanitize_text_field( $_POST['mobile'] );
			$email   = sanitize_email( $_POST['email'] );
			$ip      = sanitize_text_field( $_POST['ip'] );
			$reason  = sanitize_textarea_field( $_POST['reason'] );
			$len     = intval( $_POST['length'] );
			$unit    = sanitize_text_field( $_POST['unit'] );
			$expires = 0;
			if ( $len > 0 ) {
				switch ( $unit ) {
					case 'minutes':
						$expires = time() + $len * 60;
						break;
					case 'hours':
						$expires = time() + $len * 3600;
						break;
					case 'days':
						$expires = time() + $len * DAY_IN_SECONDS;
						break;
				}
			}

			$has_error = false;

			if ( ! $mobile && ! $email && ! $ip ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Enter mobile, email or IP', 'wcorder-blocker' ) . '</p></div>';
				// return;
			} else {
				foreach ( $list as $existing ) {
					if (
						( $mobile && $existing['mobile'] === $mobile ) ||
						( $email && $existing['email'] === $email ) ||
						( $ip && $existing['ip'] === $ip )
					) {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'This user is already in the blocked list.', 'wcorder-blocker' ) . '</p></div>';
						$has_error = true;
						break;
					}
				}

				if ( ! $has_error ) {
					$list[] = array(
						'mobile'     => $mobile,
						'email'      => $email,
						'ip'         => $ip,
						'reason'     => $reason,
						'created_at' => time(),
						'expires_at' => $expires,
					);
					update_option( 'rs_ob_blocked_users', $list );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'User has been blocked! ', 'wcorder-blocker' ) . '</p></div>';
				}
			}
		}

		// Remove a user from the blocklist
		if ( isset( $_POST['rs_ob_remove'] ) && isset( $_POST['idx'] ) ) {
			check_admin_referer( 'rs_ob_remove' );
			$idx = intval( $_POST['idx'] );
			if ( isset( $list[ $idx ] ) ) {
				unset( $list[ $idx ] );
				update_option( 'rs_ob_blocked_users', array_values( $list ) );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Removed from block list.', 'wcorder-blocker' ) . '</p></div>';
			}
		}

		// Clear the full Block list
		if ( isset( $_POST['rs_ob_clear_all'] ) ) {
			check_admin_referer( 'rs_ob_clear_all' );
			update_option( 'rs_ob_blocked_users', array() );
			wp_redirect( admin_url( 'admin.php?page=rs-blocked-users&cleared=1' ) );
			exit;
		}
		// // success notice after deliting the blocklist
		// if (isset($_GET['cleared']) && $_GET['cleared'] == 1) {
		//     echo '<div class="notice notice-success is-dismissible"><p>' . __('Blocked list cleared.', 'wcorder-blocker') . '</p></div>';
		// }

		// stats
		$total = esc_html( count( $list ) );

		?>
		<div class="wrap ">

			<div class="blocking-stats">
				<h1></h1>
				<!-- notice here  -->

			</div>

			<div class="ob_block_any_user">
				<div class="ob_block_any_user_heading">
					<h2><?php esc_html_e( 'Block a User', 'wcorder-blocker' ); ?></h2>
					<p class="total-blocked">
						⊘ <?php printf( 'Total blocked: <strong>%d</strong>', $total ); ?>
					</p>
				</div>


				<form method="post">
					<?php wp_nonce_field( 'rs_ob_add_block' ); ?>
					<div class="form-table">
						<div class="form-left">
							<label for="mobile" class="label"> <?php esc_html_e( 'Mobile', 'wcorder-blocker' ); ?> </label>
							<input name="mobile" type="text" class="regular-text" id="mobile" placeholder="e.g. 01234567890">


							<label for="email" class="label"> <?php esc_html_e( 'Email', 'wcorder-blocker' ); ?> </label>
							<input name="email" type="email" class="regular-text" autocomplete="email" id="email" placeholder="e.g. user@example.com">


							<label for="ip" class="label"> <?php esc_html_e( 'IP Address', 'wcorder-blocker' ); ?> </label>
							<input name="ip" type="text" class="regular-text" id="ip" placeholder="e.g. 192.168.1.1">
						</div>

						<div class="form-right">
							<label for="reason" class="label"> <?php esc_html_e( 'Reason', 'wcorder-blocker' ); ?> </label>
							<textarea name="reason" rows="4" class="regular-text" id="reason"></textarea>


							<label for="duration" class="label"> <?php esc_html_e( 'Duration', 'wcorder-blocker' ); ?> </label>
							<div style="display: flex;">
								<input name="length" type="number" value="0" min="0" class="Ob_duration_input" id="duration">
								<select name="unit" class="Ob_block_expire_selector">
									<option value="minutes"><?php esc_html_e( 'Minutes', 'wcorder-blocker' ); ?></option>
									<option value="hours"><?php esc_html_e( 'Hours', 'wcorder-blocker' ); ?></option>
									<option value="days" selected><?php esc_html_e( 'Days', 'wcorder-blocker' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Set 0 for permanent block', 'wcorder-blocker' ); ?></p>
							</div>

							<p><button class="button button-primary" name="rs_ob_add_block" type="submit">⊘<?php esc_html_e( ' Block User', 'wcorder-blocker' ); ?></button></p>
						</div>
					</div>

				</form>

			</div>


			<!-- Blocked list  -->
			<h2 class="Ob_blocked_list_heading"><?php esc_html_e( 'Blocked List', 'wcorder-blocker' ); ?></h2>
			<div class="rs-blocked-list">

				<table class="widefat striped">
					<thead>
						<tr class="Ob_blocked_users_th">
							<th style="padding-left: 20px;"><?php esc_html_e( 'SL:', 'wcorder-blocke' ); ?></th>
							<th><?php esc_html_e( 'Mobile', 'wcorder-blocker' ); ?></th>
							<th><?php esc_html_e( 'Email', 'wcorder-blocker' ); ?></th>
							<th><?php esc_html_e( 'IP Address', 'wcorder-blocker' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'wcorder-blocker' ); ?></th>
							<th><?php esc_html_e( 'Blocked On', 'wcorder-blocker' ); ?></th>
							<th><?php esc_html_e( 'Expires At', 'wcorder-blocker' ); ?></th>
							<th><?php esc_html_e( 'Action', 'wcorder-blocker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$count = '';
						foreach ( $list as $i => $item ) :
							?>
							<tr>
								<td style="padding-left: 20px;"><?php echo esc_html( ++$count ); ?></td>
								<td><?php echo esc_html( $item['mobile'] ); ?></td>
								<td><?php echo esc_html( $item['email'] ); ?></td>
								<td><?php echo esc_html( $item['ip'] ); ?></td>
								<td><?php echo esc_html( $item['reason'] ); ?></td>
								<td>
									<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] ) ) ); ?>
								</td>

								<?php
								//change background color if the user is blocked Never
								if ( $item['expires_at'] === 0 ) {
									$expiry_bg_color = '#ddefe2';
									$expiry_color    = '#006118ff';
								} else {
									$expiry_bg_color = '#efe2faff';
									$expiry_color    = '#43007aff';
								}
								?>

								<td class="Ob_blocked_expires_at">
									<span class="block-expiry" style="background-color: <?php echo esc_attr( $expiry_bg_color ); ?>; color: <?php echo esc_attr( $expiry_color ); ?>">
										<?php
										if ( $item['expires_at'] === 0 ) {
											esc_html_e( '∞︎︎ Never', 'wcorder-blocker' );
										} else {
											echo esc_html( date_i18n( get_option( 'date_format' ), $item['expires_at'] ) );
										}
										?>
									</span>
								</td>

								<td>
									<form method="post" style="display:inline" class="remove-a-blocked-user">
										<?php wp_nonce_field( 'rs_ob_remove' ); ?>
										<input type="hidden" name="idx" value="<?php echo esc_attr( $i ); ?>">
										<button class="button-link delete" name="rs_ob_remove" type="submit" onclick="return confirm('<?php esc_html_e( 'Confirm unblocking this user?', 'wcorder-blocker' ); ?>')">
											<?php esc_html_e( 'Remove', 'wcorder-blocker' ); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						<?php if ( empty( $list ) ) : ?>
							<tr>
								<td colspan="7"><?php echo esc_html_e( 'No blocks yet.', 'wcorder-blocker' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
				<!-- Clear Block list Btn -->
				<form method="post" style="margin-top: 15px;">
					<?php wp_nonce_field( 'rs_ob_clear_all' ); ?>
					<button class="button button-secondary" name="rs_ob_clear_all" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear the entire blocked list?', 'wcorder-blocker' ); ?>');">
						🧹 <?php esc_html_e( 'Clear Blocked List', 'wcorder-blocker' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
	}
}
