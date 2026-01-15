<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'rs_order_blocker_settings' );

// Delete Blocked users
delete_option( 'rs_ob_blocked_users' );

// Deleate incompleate orders table
global $wpdb;
$table_name = $wpdb->prefix . 'rs_order_shield_incomplete_orders';
$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS {$table_name}" ) );
