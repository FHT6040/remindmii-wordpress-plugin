<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Installer {
	/**
	 * Install or update plugin schema.
	 *
	 * @return void
	 */
	public static function install() {
		Remindmii_DB_Schema::create_tables();
		self::backfill_existing_users();
		update_option( 'remindmii_db_version', REMINDMII_VERSION );
	}

	/**
	 * Ensure existing WordPress users have plugin profile records.
	 *
	 * @return void
	 */
	private static function backfill_existing_users() {
		$user_ids = get_users(
			array(
				'fields' => 'ID',
			)
		);

		if ( ! is_array( $user_ids ) || empty( $user_ids ) ) {
			return;
		}

		foreach ( $user_ids as $user_id ) {
			self::ensure_user_records( $user_id );
		}
	}

	/**
	 * Ensure the plugin profile and preferences records exist for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	public static function ensure_user_records( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		$profiles_table    = $wpdb->prefix . 'remindmii_user_profiles';
		$preferences_table = $wpdb->prefix . 'remindmii_user_preferences';
		$timestamp         = current_time( 'mysql' );

		$profile_exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$profiles_table} WHERE user_id = %d LIMIT 1",
				$user_id
			)
		);

		if ( 0 === $profile_exists ) {
			$wpdb->insert(
				$profiles_table,
				array(
					'user_id'             => $user_id,
					'full_name'           => $user->display_name,
					'email'               => $user->user_email,
					'email_notifications' => 1,
					'notification_hours'  => 24,
					'created_at'          => $timestamp,
					'updated_at'          => $timestamp,
				),
				array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
		}

		$preferences_exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$preferences_table} WHERE user_id = %d LIMIT 1",
				$user_id
			)
		);

		if ( 0 === $preferences_exists ) {
			$wpdb->insert(
				$preferences_table,
				array(
					'user_id'                   => $user_id,
					'theme'                     => 'system',
					'color_scheme'              => wp_json_encode( array() ),
					'enable_location_reminders' => 0,
					'enable_gamification'       => 1,
					'distracted_mode'           => 0,
					'created_at'                => $timestamp,
					'updated_at'                => $timestamp,
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
			);
		}
	}
}