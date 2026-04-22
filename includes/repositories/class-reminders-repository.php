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
				'created_at'          => $timestamp,
				'updated_at'          => $timestamp,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
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
				'updated_at'          => current_time( 'mysql' ),
			),
			array(
				'id'      => absint( $reminder_id ),
				'user_id' => absint( $user_id ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ),
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

		return $record;
	}
}
