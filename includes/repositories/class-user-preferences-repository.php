<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_User_Preferences_Repository {

	/**
	 * Return table name.
	 *
	 * @return string
	 */
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'remindmii_user_preferences';
	}

	/**
	 * Allowed theme values.
	 *
	 * @var array<int, string>
	 */
	private $allowed_themes = array( 'default', 'light', 'dark', 'romantic' );

	/**
	 * Get preferences for a user. Creates defaults if missing.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, mixed>
	 */
	public function get( $user_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE user_id = %d LIMIT 1",
				absint( $user_id )
			),
			ARRAY_A
		);

		if ( ! $row ) {
			Remindmii_Installer::ensure_user_records( $user_id );
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->table()} WHERE user_id = %d LIMIT 1",
					absint( $user_id )
				),
				ARRAY_A
			);
		}

		return $row ? $this->map( $row ) : $this->defaults( $user_id );
	}

	/**
	 * Save preferences for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param array<string, mixed> $data Preferences payload.
	 * @return array<string, mixed>
	 */
	public function save( $user_id, $data ) {
		global $wpdb;

		$theme = isset( $data['theme'] ) && in_array( $data['theme'], $this->allowed_themes, true )
			? $data['theme']
			: 'default';

		$enable_location   = isset( $data['enable_location_reminders'] ) ? (int) (bool) $data['enable_location_reminders'] : 0;
		$enable_gamify     = isset( $data['enable_gamification'] ) ? (int) (bool) $data['enable_gamification'] : 1;
		$distracted_mode   = isset( $data['distracted_mode'] ) ? (int) (bool) $data['distracted_mode'] : 0;
		$now               = current_time( 'mysql', true );

		$existing = $this->get( $user_id );

		$wpdb->update(
			$this->table(),
			array(
				'theme'                      => $theme,
				'enable_location_reminders'  => $enable_location,
				'enable_gamification'        => $enable_gamify,
				'distracted_mode'            => $distracted_mode,
				'updated_at'                 => $now,
			),
			array( 'user_id' => absint( $user_id ) ),
			array( '%s', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		return $this->get( $user_id );
	}

	/**
	 * Map a DB row to a clean array.
	 *
	 * @param array<string, mixed> $row Raw DB row.
	 * @return array<string, mixed>
	 */
	private function map( $row ) {
		return array(
			'theme'                      => (string) ( $row['theme'] ?? 'default' ),
			'enable_location_reminders'  => (bool) $row['enable_location_reminders'],
			'enable_gamification'        => (bool) $row['enable_gamification'],
			'distracted_mode'            => (bool) $row['distracted_mode'],
		);
	}

	/**
	 * Default preference values.
	 *
	 * @param int $user_id User ID (unused here but left for clarity).
	 * @return array<string, mixed>
	 */
	private function defaults( $user_id ) {
		return array(
			'theme'                      => 'default',
			'enable_location_reminders'  => false,
			'enable_gamification'        => true,
			'distracted_mode'            => false,
		);
	}
}
