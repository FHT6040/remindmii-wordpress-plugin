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
			notification_hours int(11) DEFAULT NULL,
			is_completed tinyint(1) NOT NULL DEFAULT 0,
			location_name varchar(191) DEFAULT NULL,
			location_lat decimal(10,7) DEFAULT NULL,
			location_lng decimal(10,7) DEFAULT NULL,
			location_radius int(11) NOT NULL DEFAULT 200,
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
			unsubscribe_token varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			KEY email (email),
			KEY unsubscribe_token (unsubscribe_token)
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
			slug varchar(191) DEFAULT NULL,
			is_public tinyint(1) NOT NULL DEFAULT 0,
			public_token varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			UNIQUE KEY public_token (public_token),
			UNIQUE KEY slug (slug)
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
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY wishlist_id (wishlist_id),
			KEY owner_id (owner_id),
			KEY shared_with_user_id (shared_with_user_id),
			UNIQUE KEY token (token)
		) {$charset_collate};";

		dbDelta( $wishlist_shares_sql );

		$achievements_table = $wpdb->prefix . 'remindmii_user_achievements';
		$achievements_sql   = "CREATE TABLE {$achievements_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			achievement_key varchar(64) NOT NULL,
			points int(11) NOT NULL DEFAULT 0,
			earned_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_achievement (user_id, achievement_key)
		) {$charset_collate};";

		$user_stats_table = $wpdb->prefix . 'remindmii_user_stats';
		$user_stats_sql   = "CREATE TABLE {$user_stats_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			total_reminders_created int(11) NOT NULL DEFAULT 0,
			total_completed int(11) NOT NULL DEFAULT 0,
			current_streak int(11) NOT NULL DEFAULT 0,
			longest_streak int(11) NOT NULL DEFAULT 0,
			total_points int(11) NOT NULL DEFAULT 0,
			last_activity_date date NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $achievements_sql );
		dbDelta( $user_stats_sql );

		$merchants_table = $wpdb->prefix . 'remindmii_merchants';
		$merchants_sql   = "CREATE TABLE {$merchants_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			category varchar(100) DEFAULT NULL,
			logo_url varchar(2083) DEFAULT NULL,
			website_url varchar(2083) DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$merchant_users_table = $wpdb->prefix . 'remindmii_merchant_users';
		$merchant_users_sql   = "CREATE TABLE {$merchant_users_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			merchant_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			role varchar(50) NOT NULL DEFAULT 'admin',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY merchant_user (merchant_id, user_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		$merchant_ads_table = $wpdb->prefix . 'remindmii_merchant_ads';
		$merchant_ads_sql   = "CREATE TABLE {$merchant_ads_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			merchant_id bigint(20) unsigned NOT NULL,
			title varchar(191) NOT NULL,
			description longtext NULL,
			image_url varchar(2083) DEFAULT NULL,
			background_color varchar(20) NOT NULL DEFAULT '#3B82F6',
			text_color varchar(20) NOT NULL DEFAULT '#FFFFFF',
			target_gender longtext NULL,
			target_age_min int(11) NOT NULL DEFAULT 0,
			target_age_max int(11) NOT NULL DEFAULT 120,
			target_categories longtext NULL,
			cta_text varchar(100) NOT NULL DEFAULT 'Se tilbud',
			cta_url varchar(2083) DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			impression_cap int(11) DEFAULT NULL,
			impressions int(11) NOT NULL DEFAULT 0,
			clicks int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY merchant_id (merchant_id),
			KEY is_active (is_active)
		) {$charset_collate};";

		dbDelta( $merchants_sql );
		dbDelta( $merchant_users_sql );
		dbDelta( $merchant_ads_sql );
	}
}