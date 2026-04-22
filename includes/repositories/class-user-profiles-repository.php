<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_User_Profiles_Repository {
	/**
	 * Return user profiles table name.
	 *
	 * @return string
	 */
	private function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'remindmii_user_profiles';
	}

	/**
	 * Fetch a profile by WordPress user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_user_id( $user_id ) {
		global $wpdb;

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE user_id = %d LIMIT 1",
				absint( $user_id )
			),
			ARRAY_A
		);

		return is_array( $record ) ? $this->map_record( $record ) : null;
	}

	/**
	 * Update a profile for a user.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $data    Sanitized profile payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_by_user_id( $user_id, $data ) {
		global $wpdb;

		$profile = $this->get_by_user_id( $user_id );

		if ( null === $profile ) {
			Remindmii_Installer::ensure_user_records( $user_id );
			$profile = $this->get_by_user_id( $user_id );
		}

		if ( null === $profile ) {
			return new WP_Error( 'remindmii_profile_not_found', __( 'Profile not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$updated = $wpdb->update(
			$this->table_name(),
			array(
				'full_name'           => $data['full_name'],
				'birth_date'          => $data['birth_date'],
				'gender'              => $data['gender'],
				'pronouns'            => $data['pronouns'],
				'email'               => $data['email'],
				'phone'               => $data['phone'],
				'email_notifications' => $data['email_notifications'],
				'notification_hours'  => $data['notification_hours'],
				'updated_at'          => current_time( 'mysql' ),
			),
			array(
				'user_id' => absint( $user_id ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'remindmii_profile_update_failed', __( 'Unable to update profile.', 'remindmii' ), array( 'status' => 500 ) );
		}

		return $this->get_by_user_id( $user_id );
	}

	/**
	 * Normalize profile row.
	 *
	 * @param array<string, mixed> $record Raw database row.
	 * @return array<string, mixed>
	 */
	private function map_record( $record ) {
		$record['id']                  = (int) $record['id'];
		$record['user_id']             = (int) $record['user_id'];
		$record['email_notifications'] = (bool) $record['email_notifications'];
		$record['notification_hours']  = (int) $record['notification_hours'];

		return $record;
	}
}
