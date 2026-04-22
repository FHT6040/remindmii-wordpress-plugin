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

		$charset_collate = $wpdb->get_charset_collate();
		$reminders_table = $wpdb->prefix . 'remindmii_reminders';
		$categories_table = $wpdb->prefix . 'remindmii_categories';

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

		dbDelta( $categories_sql );
		dbDelta( $reminders_sql );
	}
}