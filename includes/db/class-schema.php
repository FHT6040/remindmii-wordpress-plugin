<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_DB_Schema {
	/**
	 * Create the initial plugin tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate        = $wpdb->get_charset_collate();
		$reminders_table        = $wpdb->prefix . 'remindmii_reminders';
		$categories_table       = $wpdb->prefix . 'remindmii_categories';
		$user_profiles_table    = $wpdb->prefix . 'remindmii_user_profiles';
		$user_preferences_table = $wpdb->prefix . 'remindmii_user_preferences';
		$notifications_log      = $wpdb->prefix . 'remindmii_notifications_log';
		$wishlists_table        = $wpdb->prefix . 'remindmii_wishlists';
		$wishlist_items_table   = $wpdb->prefix . 'remindmii_wishlist_items';

		$categories_sql = "CREATE TABLE {$categories_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			color varchar(20) NOT NULL DEFAULT '#3B82F6',
			icon varchar(100) NOT NULL DEFAULT 'tag',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) {$charset_collate};";

		$reminders_sql = "CREATE TABLE {$reminders_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			category_id bigint(20) unsigned DEFAULT NULL,
			title varchar(191) NOT NULL,
			description longtext NULL,
			reminder_date datetime NOT NULL,
			is_recurring tinyint(1) NOT NULL DEFAULT 0,
			recurrence_interval varchar(50) DEFAULT NULL,
			notification_sent tinyint(1) NOT NULL DEFAULT 0,
			is_completed tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY category_id (category_id),
			KEY reminder_date (reminder_date)
		) {$charset_collate};";

		$user_profiles_sql = "CREATE TABLE {$user_profiles_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			full_name varchar(191) DEFAULT NULL,
			birth_date date DEFAULT NULL,
			gender varchar(50) DEFAULT NULL,
			pronouns varchar(100) DEFAULT NULL,
			email varchar(191) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			push_token longtext NULL,
			email_notifications tinyint(1) NOT NULL DEFAULT 1,
			notification_hours int(11) NOT NULL DEFAULT 24,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			KEY email (email)
		) {$charset_collate};";

		$user_preferences_sql = "CREATE TABLE {$user_preferences_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			theme varchar(50) DEFAULT NULL,
			color_scheme longtext NULL,
			enable_location_reminders tinyint(1) NOT NULL DEFAULT 0,
			enable_gamification tinyint(1) NOT NULL DEFAULT 1,
			distracted_mode tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id)
		) {$charset_collate};";

		$notifications_log_sql = "CREATE TABLE {$notifications_log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			reminder_id bigint(20) unsigned DEFAULT NULL,
			notification_type varchar(50) NOT NULL,
			channel varchar(50) NOT NULL,
			status varchar(50) NOT NULL,
			message longtext NULL,
			context longtext NULL,
			sent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY reminder_id (reminder_id),
			KEY notification_type (notification_type),
			KEY status (status)
		) {$charset_collate};";

		$wishlists_table       = $wpdb->prefix . 'remindmii_wishlists';
		$wishlist_items_table  = $wpdb->prefix . 'remindmii_wishlist_items';

		$wishlists_sql = "CREATE TABLE {$wishlists_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			title varchar(191) NOT NULL,
			description longtext NULL,
			is_public tinyint(1) NOT NULL DEFAULT 0,
			public_token varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			UNIQUE KEY public_token (public_token)
		) {$charset_collate};";

		$wishlist_items_sql = "CREATE TABLE {$wishlist_items_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wishlist_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			title varchar(191) NOT NULL,
			description longtext NULL,
			url varchar(2083) DEFAULT NULL,
			price decimal(10,2) DEFAULT NULL,
			currency varchar(10) NOT NULL DEFAULT 'DKK',
			is_purchased tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY wishlist_id (wishlist_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $categories_sql );
		dbDelta( $reminders_sql );
		dbDelta( $user_profiles_sql );
		dbDelta( $user_preferences_sql );
		dbDelta( $notifications_log_sql );
		dbDelta( $wishlists_sql );
		dbDelta( $wishlist_items_sql );

		$wishlist_shares_table = $wpdb->prefix . 'remindmii_wishlist_shares';
		$wishlist_shares_sql   = "CREATE TABLE {$wishlist_shares_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wishlist_id bigint(20) unsigned NOT NULL,
			owner_id bigint(20) unsigned NOT NULL,
			shared_with_email varchar(191) NOT NULL,
			shared_with_user_id bigint(20) unsigned NULL,
			permission varchar(20) NOT NULL DEFAULT 'view',
			token varchar(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY wishlist_id (wishlist_id),
			KEY owner_id (owner_id),
			KEY shared_with_user_id (shared_with_user_id),
			UNIQUE KEY token (token)
		) {$charset_collate};";

		dbDelta( $wishlist_shares_sql );
	}
}