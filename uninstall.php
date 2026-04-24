<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'remindmii_db_version' );
delete_option( 'remindmii_settings' );

// Unschedule cron jobs.
wp_clear_scheduled_hook( 'remindmii_process_notifications' );

// Drop all plugin tables (data deletion).
// Uncomment the following lines only if you want to drop tables on uninstall.
// By default, tables are preserved for data safety.

/*
$tables = array(
	$wpdb->prefix . 'remindmii_reminders',
	$wpdb->prefix . 'remindmii_categories',
	$wpdb->prefix . 'remindmii_wishlists',
	$wpdb->prefix . 'remindmii_wishlist_items',
	$wpdb->prefix . 'remindmii_user_profiles',
	$wpdb->prefix . 'remindmii_user_preferences',
	$wpdb->prefix . 'remindmii_user_notifications',
	$wpdb->prefix . 'remindmii_shared_lists',
	$wpdb->prefix . 'remindmii_shared_lists_users',
	$wpdb->prefix . 'remindmii_achievements',
	$wpdb->prefix . 'remindmii_notification_logs',
	$wpdb->prefix . 'remindmii_merchants',
	$wpdb->prefix . 'remindmii_merchant_users',
	$wpdb->prefix . 'remindmii_merchant_ads',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
*/