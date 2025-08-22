<?php

namespace RS\OrderBlocker;

if (! defined('ABSPATH')) {
    exit;
}

class Checker {

    public function __construct() {
        // Set device cookie early
        add_action('init', [$this, 'maybe_set_device_cookie']);

        // Classic checkout (shortcode)
        add_action('woocommerce_checkout_process', [$this, 'check_block']);

        // Save meta as soon as order object exists (BEFORE blocking check)
        add_action('woocommerce_checkout_create_order', [$this, 'save_meta'], 5, 1); // classic
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'save_meta'], 5, 1); // blocks (first arg is WC_Order)
        // add_action('woocommerce_store_api_checkout_update_order_from_request', function ($order, $request) {
        //     if (isset($request['billing']['phone'])) {
        //         $phone = sanitize_text_field($request['billing']['phone']);
        //         update_post_meta($order->get_id(), '_billing_phone', $phone);
        //     }
        // }, 10, 2);

        // CartFlows AJAX
        add_action('wp_ajax_wc_cartflows_submit_checkout',        [$this, 'check_block_ajax']);
        add_action('wp_ajax_nopriv_wc_cartflows_submit_checkout', [$this, 'check_block_ajax']);

        // Store API: run the blocker after the order is created from request (but before finalization)
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'block_checkout_after_order'], 10, 2);
    }



    /*----------------------------- PHONE VALIDATION BEFORE CHECKOUT --------------------*/
    private function is_valid_phone_bd($phone_raw): bool {
        $p = preg_replace('/\D+/', '', (string) $phone_raw); // digits only

        // normalize country code variations
        if (strpos($p, '00880') === 0) {
            $p = substr($p, 5);
        } elseif (strpos($p, '880') === 0) {
            $p = substr($p, 3);
        }

        // ensure leading 0 when user typed 1xxxxxxxxx
        if (strpos($p, '01') !== 0 && strpos($p, '1') === 0) {
            $p = '0' . $p;
        }

        // valid BD local format: 01[3-9]XXXXXXXX (11 digits)
        return (bool) preg_match('/^01[3-9]\d{8}$/', $p);
    }















    /* -------------------- SETTINGS & NORMALIZERS -------------------- */

    private function get_settings() {
        return get_option('rs_order_blocker_settings', []);
    }

    private function normalize_phone_bd($phone) {
        $p = preg_replace('/\D+/', '', (string) $phone); // keep digits only

        // Handle country codes
        if (strpos($p, '00880') === 0) {
            $p = substr($p, 5);
        } elseif (strpos($p, '880') === 0) {
            $p = substr($p, 3);
        }

        // Ensure leading 0
        if (strpos($p, '01') !== 0 && strpos($p, '1') === 0) {
            $p = '0' . $p;
        }

        // Final check
        if (preg_match('/^01[3-9]\d{8}$/', $p)) {
            return $p; // valid BD number
        }

        // ❗ fallback: return whatever cleaned digits we got
        return $p;
    }


    private function normalize_ip($ip) {
        if ($ip === '::1') {
            return '127.0.0.1';
        }
        return (string) $ip;
    }

    private function block_window_seconds($settings) {
        $days    = intval($settings['cookie_expire_days']    ?? 0);
        $hours   = intval($settings['cookie_expire_hours']   ?? 0);
        $minutes = intval($settings['cookie_expire_minutes'] ?? 0);
        return ($days * DAY_IN_SECONDS) + ($hours * HOUR_IN_SECONDS) + ($minutes * MINUTE_IN_SECONDS);
    }

    /* -------------------- CLASSIC CHECKOUT -------------------- */

    public function check_block() {
        $settings = $this->get_settings();
        if (empty($settings['enable_blocking'])) {
            return;
        }

        $email     = sanitize_email($_POST['billing_email'] ?? '');
        $phone_raw = sanitize_text_field($_POST['billing_phone'] ?? '');
        $phone     = $this->normalize_phone_bd($phone_raw);
        $ip        = $this->normalize_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown_ip');
        $device_id = sanitize_text_field($_COOKIE['device_id'] ?? '');

        // Optional phone validity (BD)
        // if (! empty($settings['enable_blocking']) && $phone_raw !== '' && $phone === '') {
        //     $msg = esc_html($settings['invalid_phone_alert'] ?? '');
        //     wc_add_notice($this->wrap_error_notice($msg ?: 'দুঃখিত, আপনি একটি ভুল নাম্বার লিখেছেন। দয়া করে সঠিক বাংলাদেশি নাম্বার লিখুন:', $settings, 'rs-invalid-phone-alert'), 'error');
        //     return;
        // }


        // ✅ Only validate when the setting is ON
        if (! empty($settings['enable_number_validation']) && $phone_raw !== '') {
            if (! $this->is_valid_phone_bd($phone_raw)) {
                $msg = esc_html($settings['invalid_phone_alert'] ?? '');
                wc_add_notice(
                    $this->wrap_error_notice(
                        $msg ?: 'দুঃখিত, আপনি একটি ভুল নাম্বার লিখেছেন। দয়া করে সঠিক বাংলাদেশি নাম্বার লিখুন:',
                        $settings,
                        'rs-invalid-phone-alert'
                    ),
                    'error'
                );
                return; // stop checkout
            }
        }

        error_log('[OrderBlocker] Phone raw=' . ($phone_raw ?? '') . ' valid=' . (int) $this->is_valid_phone_bd($phone_raw)); //------------------------------ test



        if ($this->is_blocked($phone, $email, $ip, $device_id, $settings)) {
            wc_add_notice($this->get_popup_html($settings), 'error');
            return;
        }
    }

    /* -------------------- CARTFLOWS AJAX -------------------- */

    public function check_block_ajax() {
        $settings = $this->get_settings();
        if (empty($settings['enable_blocking'])) {
            wp_send_json_success();
        }

        $email     = sanitize_email($_POST['billing_email'] ?? '');
        $phone     = $this->normalize_phone_bd(sanitize_text_field($_POST['billing_phone'] ?? ''));
        $ip        = $this->normalize_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown_ip');
        $device_id = sanitize_text_field($_COOKIE['device_id'] ?? '');

        if ($this->is_blocked($phone, $email, $ip, $device_id, $settings)) {
            wp_send_json_error(['messages' => $this->get_popup_html($settings)]);
        }

        wp_send_json_success();
    }

    /* -------------------- STORE API (BLOCKS) -------------------- */

    public function block_checkout_after_order($order, $request) {
        error_log('Order Blocker Checker: block_checkout_after_order called');

        $settings = $this->get_settings();
        if (empty($settings['enable_blocking'])) {
            return;
        }

        error_log('[OrderBlocker] Phone raw=' . ($phone_raw ?? '') . ' valid=' . (int) $this->is_valid_phone_bd($phone_raw));

        // ✅ Validate phone for Blocks
        if (! empty($settings['enable_number_validation'])) {
            $phone_raw = (string) $order->get_billing_phone();
            if ($phone_raw !== '' && ! $this->is_valid_phone_bd($phone_raw)) {
                $msg = esc_html($settings['invalid_phone_alert'] ?? '');
                throw new \WC_REST_Exception(
                    'rs_invalid_phone',
                    $msg ?: __('দুঃখিত, আপনি একটি ভুল নাম্বার লিখেছেন। দয়া করে সঠিক বাংলাদেশি নাম্বার লিখুন:', 'rs-order-blocker'),
                    400
                );
            }
        }



        // Normalize live request values
        $phone     = $this->normalize_phone_bd($order->get_billing_phone());
        $email     = sanitize_email($order->get_billing_email());
        $ip        = $this->normalize_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown_ip');
        $device_id = sanitize_text_field($_COOKIE['device_id'] ?? '');

        if ($this->is_blocked($phone, $email, $ip, $device_id, $settings)) {
            throw new \WC_REST_Exception('rs_order_blocked', $this->get_popup_html($settings), 400);
        }
    }

    /* -------------------- CORE MATCHING (HPOS-SAFE) -------------------- */

    private function is_blocked($phone, $email, $ip, $device_id, $settings) {
        $window   = $this->block_window_seconds($settings);
        $now      = time();

        $orders = wc_get_orders([
            'limit'  => -1,
            'status' => ['pending', 'processing', 'on-hold'],
            // Optionally filter by date to reduce scan size:
            // 'date_created' => '>' . gmdate( 'Y-m-d H:i:s', $now - $window ),
        ]);

        foreach ($orders as $o) {
            $order_phone = $this->normalize_phone_bd($o->get_billing_phone()); // past order

            $order_email  = sanitize_email($o->get_billing_email());

            // HPOS-safe meta access; fallback to recorded IP if meta missing
            $order_ip     = $this->normalize_ip((string) ($o->get_meta('_user_ip', true) ?: $o->get_customer_ip_address()));
            $order_device = (string) $o->get_meta('_device_id', true);
            $set_time     = (int) ($o->get_meta('_device_set_time', true) ?: $o->get_date_created()->getTimestamp());

            // Skip outside window
            if (($now - $set_time) > $window) {
                continue;
            }

            error_log("Comparing: phone={$phone}|{$order_phone}, email={$email}|{$order_email}, ip={$ip}|{$order_ip}, device={$device_id}|{$order_device}");

            if (
                ($phone && $order_phone && $phone === $order_phone) //||
                // ($email && $order_email && $email === $order_email)  ||
                // ($ip    && $order_ip    && $ip    === $order_ip) ||
                // ($device_id && $order_device && $device_id === $order_device)
            ) {
                return true;
            }
        }

        return false;
    }

    /* -------------------- PERSISTENCE -------------------- */

    public function maybe_set_device_cookie() {
        if (is_admin()) {
            return;
        }
        if (empty($_COOKIE['device_id'])) {
            $new_id = bin2hex(random_bytes(10));
            // Path `/` so it's visible on checkout; default SameSite=Lax works for same-site POSTs.
            setcookie('device_id', $new_id, time() + (30 * DAY_IN_SECONDS), '/');
            $_COOKIE['device_id'] = $new_id; // make available in this request
        }
    }

    // Accepts order ID or WC_Order (works with both classic & HPOS)
    public function save_meta($order_or_obj) {
        $settings = $this->get_settings();
        if (empty($settings['enable_blocking'])) {
            return;
        }

        $order = wc_get_order($order_or_obj);
        if (! $order) {
            return;
        }

        $ip        = $this->normalize_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown_ip');
        $device_id = sanitize_text_field($_COOKIE['device_id'] ?? '');

        $order->update_meta_data('_user_ip', $ip);
        $order->update_meta_data('_device_id', $device_id);
        $order->update_meta_data('_device_set_time', time());
        $order->save();
    }

    /* -------------------- UI -------------------- */

    private function wrap_error_notice($text, $settings, $extra_class = '') {
        $text_color = esc_attr($settings['popup_text_color'] ?? '#000');
        $bg_color   = esc_attr($settings['popup_bg_color'] ?? '#fff');
        $font       = esc_attr($settings['popup_font_family'] ?? 'Segoe UI');
        $font_size  = esc_attr($settings['popup_font_size'] ?? '16');
        $padding    = esc_attr($settings['popup_padding'] ?? '20');
        $margin     = esc_attr($settings['popup_margin'] ?? '10');
        $shadow     = esc_attr($settings['popup_box_shadow'] ?? '2px 2px 6px rgba(0,0,0,0.1)');

        $class = 'rs-block-msg ' . sanitize_html_class($extra_class);
        return "<div class='{$class}' style='position:relative;background:{$bg_color};color:{$text_color};font-family:{$font};font-size:{$font_size}px;padding:{$padding}px;margin:{$margin}px;box-shadow:{$shadow};border-radius:10px;text-align:center;'>
            <div class='rs-close-btn'>&times;</div>
            <p>{$text}</p>
        </div>";
    }

    private function get_popup_html($opts) {
        $msg        = esc_html($opts['popup_message'] ?? 'দুঃখিত, আপনার অলরেডি একটি অর্ডার সাবমিট করা আছে। আরেকটি অর্ডার করতে দয়া করে নীচের নাম্বারে যোগাযোগ করুন:');
        $show_call  = ! empty($opts['enable_call_button']);
        $show_wa    = ! empty($opts['enable_whatsapp_button']);

        $call_text  = esc_html($opts['call_btn_text'] ?? '📞 Call Us');
        $call       = esc_attr($opts['call_number'] ?? '');
        $wa_text    = esc_html($opts['whatsapp_btn_text'] ?? '💬 WhatsApp');
        $wa         = esc_attr($opts['whatsapp_number'] ?? '');

        $text_col   = esc_attr($opts['popup_text_color'] ?? 'white');
        $bg_col     = esc_attr($opts['popup_bg_color'] ?? 'red');
        $btn_txt    = esc_attr($opts['popup_button_text_color'] ?? 'black');
        $btn_bg     = esc_attr($opts['popup_button_color'] ?? 'white');
        $font       = esc_attr($opts['popup_font_family'] ?? 'Segoe UI');
        $font_size  = esc_attr($opts['popup_font_size'] ?? '14');
        $padding    = esc_attr($opts['popup_padding'] ?? '20');
        $margin     = esc_attr($opts['popup_margin'] ?? '10');
        $shadow     = esc_attr($opts['popup_box_shadow'] ?? '2px 2px 6px rgba(0,0,0,0.1)');

        $buttons = '';
        if ($show_call && $call) {
            $buttons .= "<a href='tel:{$call}' class='rs-call' style='background:{$btn_bg};color:{$btn_txt};padding:.6em 1em;border-radius:8px;display:inline-block;margin:.25em;'>{$call_text}</a>";
        }
        if ($show_wa && $wa) {
            $buttons .= "<a href='https://wa.me/{$wa}' class='rs-wa' style='background:{$btn_bg};color:{$btn_txt};padding:.6em 1em;border-radius:8px;display:inline-block;margin:.25em;'>{$wa_text}</a>";
        }

        return "<div class='rs-block-msg' style='position:relative;background:{$bg_col};color:{$text_col};font-family:{$font};font-size:{$font_size}px;padding:{$padding}px;margin:{$margin}px;box-shadow:{$shadow};border-radius:10px;text-align:center;'>
            <div class='rs-close-btn'>&times;</div>
            <p>{$msg}</p>
            <div class='rs-btns'>{$buttons}</div>
        </div>";
    }
}
