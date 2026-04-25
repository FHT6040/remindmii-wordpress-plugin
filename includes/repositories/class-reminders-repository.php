<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Reminders_Repository {
	/**
	 * Return the reminders table name.
	 *
	 * @return string
	 */
	private function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'remindmii_reminders';
	}

	/**
	 * Fetch all reminders for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_by_user( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE user_id = %d ORDER BY reminder_date ASC, id DESC",
				$user_id
			),
			ARRAY_A
		);

		return is_array( $results ) ? array_map( array( $this, 'map_record' ), $results ) : array();
	}

	/**
	 * Fetch reminders that should trigger notifications now.
	 *
	 * @param int $limit Maximum reminders to fetch.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_due_for_notifications( $limit = 50 ) {
		global $wpdb;

		$limit          = max( 1, absint( $limit ) );
		$profiles_table = $wpdb->prefix . 'remindmii_user_profiles';
		$now            = current_time( 'mysql' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT reminders.*, profiles.email AS profile_email, profiles.full_name AS profile_full_name,
				COALESCE(reminders.notification_hours, profiles.notification_hours) AS notification_hours
				FROM {$this->table_name()} AS reminders
				INNER JOIN {$profiles_table} AS profiles ON profiles.user_id = reminders.user_id
				WHERE reminders.notification_sent = 0
					AND reminders.is_completed = 0
					AND profiles.email_notifications = 1
					AND profiles.email IS NOT NULL
					AND profiles.email <> ''
					AND reminders.reminder_date <= DATE_ADD( %s, INTERVAL COALESCE(reminders.notification_hours, profiles.notification_hours) HOUR )
				ORDER BY reminders.reminder_date ASC, reminders.id ASC
				LIMIT %d",
				$now,
				$limit
			),
			ARRAY_A
		);

		return is_array( $results ) ? array_map( array( $this, 'map_notification_record' ), $results ) : array();
	}

	/**
	 * Fetch a single reminder for a user.
	 *
	 * @param int $reminder_id Reminder ID.
	 * @param int $user_id     WordPress user ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $reminder_id, $user_id ) {
		global $wpdb;

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE id = %d AND user_id = %d LIMIT 1",
				absint( $reminder_id ),
				absint( $user_id )
			),
			ARRAY_A
		);

		return is_array( $record ) ? $this->map_record( $record ) : null;
	}

	/**
	 * Create a reminder.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $data    Validated reminder payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( $user_id, $data ) {
		global $wpdb;

		$timestamp = current_time( 'mysql' );
		$inserted  = $wpdb->insert(
			$this->table_name(),
			array(
				'user_id'             => absint( $user_id ),
				'category_id'         => $data['category_id'],
				'title'               => $data['title'],
				'description'         => $data['description'],
				'reminder_date'       => $data['reminder_date'],
				'is_recurring'        => $data['is_recurring'],
				'recurrence_interval' => $data['recurrence_interval'],
				'notification_sent'   => 0,
				'is_completed'        => $data['is_completed'],
				'location_name'       => isset( $data['location_name'] ) ? $data['location_name'] : null,
				'location_lat'        => isset( $data['location_lat'] )  ? $data['location_lat']  : null,
				'location_lng'        => isset( $data['location_lng'] )  ? $data['location_lng']  : null,
				'location_radius'     => isset( $data['location_radius'] ) ? (int) $data['location_radius'] : 200,
				'notification_hours'  => isset( $data['notification_hours'] ) && null !== $data['notification_hours'] ? absint( $data['notification_hours'] ) : null,
				'created_at'          => $timestamp,
				'updated_at'          => $timestamp,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'remindmii_reminder_create_failed', __( 'Unable to create reminder.', 'remindmii' ), array( 'status' => 500 ) );
		}

		return $this->get_by_id( (int) $wpdb->insert_id, $user_id );
	}

	/**
	 * Update a reminder.
	 *
	 * @param int   $reminder_id Reminder ID.
	 * @param int   $user_id     WordPress user ID.
	 * @param array $data        Validated reminder payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( $reminder_id, $user_id, $data ) {
		global $wpdb;

		$existing = $this->get_by_id( $reminder_id, $user_id );

		if ( null === $existing ) {
			return new WP_Error( 'remindmii_reminder_not_found', __( 'Reminder not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$updated = $wpdb->update(
			$this->table_name(),
			array(
				'category_id'         => $data['category_id'],
				'title'               => $data['title'],
				'description'         => $data['description'],
				'reminder_date'       => $data['reminder_date'],
				'is_recurring'        => $data['is_recurring'],
				'recurrence_interval' => $data['recurrence_interval'],
				'is_completed'        => $data['is_completed'],
				'location_name'       => isset( $data['location_name'] ) ? $data['location_name'] : null,
				'location_lat'        => isset( $data['location_lat'] )  ? $data['location_lat']  : null,
				'location_lng'        => isset( $data['location_lng'] )  ? $data['location_lng']  : null,
				'location_radius'     => isset( $data['location_radius'] ) ? (int) $data['location_radius'] : 200,
				'notification_hours'  => isset( $data['notification_hours'] ) && null !== $data['notification_hours'] ? absint( $data['notification_hours'] ) : null,
				'updated_at'          => current_time( 'mysql' ),
			),
			array(
				'id'      => absint( $reminder_id ),
				'user_id' => absint( $user_id ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%f', '%f', '%d', '%d', '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'remindmii_reminder_update_failed', __( 'Unable to update reminder.', 'remindmii' ), array( 'status' => 500 ) );
		}

		return $this->get_by_id( $reminder_id, $user_id );
	}

	/**
	 * Delete a reminder.
	 *
	 * @param int $reminder_id Reminder ID.
	 * @param int $user_id     WordPress user ID.
	 * @return bool|WP_Error
	 */
	public function delete( $reminder_id, $user_id ) {
		global $wpdb;

		$existing = $this->get_by_id( $reminder_id, $user_id );

		if ( null === $existing ) {
			return new WP_Error( 'remindmii_reminder_not_found', __( 'Reminder not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$deleted = $wpdb->delete(
			$this->table_name(),
			array(
				'id'      => absint( $reminder_id ),
				'user_id' => absint( $user_id ),
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'remindmii_reminder_delete_failed', __( 'Unable to delete reminder.', 'remindmii' ), array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Mark a reminder notification as sent.
	 *
	 * @param int $reminder_id Reminder ID.
	 * @param int $user_id     WordPress user ID.
	 * @return bool
	 */
	public function mark_notification_sent( $reminder_id, $user_id ) {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table_name(),
			array(
				'notification_sent' => 1,
				'updated_at'        => current_time( 'mysql' ),
			),
			array(
				'id'      => absint( $reminder_id ),
				'user_id' => absint( $user_id ),
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Move a recurring reminder to its next date and reset notification state.
	 *
	 * @param array<string, mixed> $reminder Reminder payload.
	 * @return bool
	 */
	public function reschedule_recurring( $reminder ) {
		global $wpdb;

		$next_date = $this->get_next_occurrence( (string) $reminder['reminder_date'], (string) $reminder['recurrence_interval'] );

		if ( null === $next_date ) {
			return $this->mark_notification_sent( (int) $reminder['id'], (int) $reminder['user_id'] );
		}

		$updated = $wpdb->update(
			$this->table_name(),
			array(
				'reminder_date'      => $next_date,
				'notification_sent'  => 0,
				'updated_at'         => current_time( 'mysql' ),
			),
			array(
				'id'      => absint( $reminder['id'] ),
				'user_id' => absint( $reminder['user_id'] ),
			),
			array( '%s', '%d', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Normalize a database row for API output.
	 *
	 * @param array<string, mixed> $record Raw database row.
	 * @return array<string, mixed>
	 */
	private function map_record( $record ) {
		$record['id']                = (int) $record['id'];
		$record['user_id']           = (int) $record['user_id'];
		$record['category_id']       = null !== $record['category_id'] ? (int) $record['category_id'] : null;
		$record['is_recurring']      = (bool) $record['is_recurring'];
		$record['notification_sent'] = (bool) $record['notification_sent'];
		$record['is_completed']      = (bool) $record['is_completed'];
		$record['notification_hours'] = isset( $record['notification_hours'] ) && null !== $record['notification_hours'] ? (int) $record['notification_hours'] : null;

		return $record;
	}

	/**
	 * Normalize a reminder row joined with notification profile data.
	 *
	 * @param array<string, mixed> $record Raw database row.
	 * @return array<string, mixed>
	 */
	private function map_notification_record( $record ) {
		$record                       = $this->map_record( $record );
		$record['profile_email']      = (string) $record['profile_email'];
		$record['profile_full_name']  = null !== $record['profile_full_name'] ? (string) $record['profile_full_name'] : '';
		$record['notification_hours'] = (int) $record['notification_hours'];

		return $record;
	}

	/**
	 * Calculate the next reminder occurrence.
	 *
	 * @param string $reminder_date Current reminder date.
	 * @param string $interval      Recurrence interval.
	 * @return string|null
	 */
	private function get_next_occurrence( $reminder_date, $interval ) {
		$timestamp = strtotime( $reminder_date );

		if ( false === $timestamp ) {
			return null;
		}

		switch ( strtolower( $interval ) ) {
			case 'daily':
					return date( 'Y-m-d H:i:s', strtotime( '+1 day', $timestamp ) );
			case 'weekly':
					return date( 'Y-m-d H:i:s', strtotime( '+1 week', $timestamp ) );
			case 'monthly':
					return date( 'Y-m-d H:i:s', strtotime( '+1 month', $timestamp ) );
			case 'yearly':
					return date( 'Y-m-d H:i:s', strtotime( '+1 year', $timestamp ) );
			default:
				return null;
		}
	}
}
