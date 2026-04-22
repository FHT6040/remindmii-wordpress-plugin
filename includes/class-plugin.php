<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Plugin {
	/**
	 * Boot plugin components.
	 *
	 * @return void
	 */
	public function run() {
		load_plugin_textdomain( 'remindmii', false, dirname( plugin_basename( REMINDMII_PLUGIN_FILE ) ) . '/languages' );

		$admin      = new Remindmii_Admin();
		$frontend   = new Remindmii_Frontend();
		$rest       = new Remindmii_REST();
		$cron       = new Remindmii_Cron();
		$security   = new Remindmii_Security();
		$shortcodes = new Remindmii_Shortcodes();

		$admin->register_hooks();
		$frontend->register_hooks();
		$rest->register_hooks();
		$cron->register_hooks();
		$security->register_hooks();
		$shortcodes->register_hooks();

		add_action( 'user_register', array( $this, 'bootstrap_user_records' ) );
	}

	/**
	 * Create default plugin records for a new WordPress user.
	 *
	 * @param int $user_id Newly created WordPress user ID.
	 * @return void
	 */
	public function bootstrap_user_records( $user_id ) {
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