<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Notification_Logs_Repository {
	/**
	 * Return notification logs table name.
	 *
	 * @return string
	 */
	private function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'remindmii_notifications_log';
	}

	/**
	 * Fetch recent notification log entries for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $limit   Number of items to return.
	 * @param int $offset  Offset for pagination.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_by_user( $user_id, $limit = 10, $offset = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$limit   = max( 1, min( 50, absint( $limit ) ) );
		$offset  = max( 0, absint( $offset ) );

		if ( $user_id <= 0 ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, reminder_id, notification_type, channel, status, message, context, sent_at, created_at
				FROM {$this->table_name()}
				WHERE user_id = %d
				ORDER BY id DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return is_array( $results ) ? array_map( array( $this, 'map_record' ), $results ) : array();
	}

	/**
	 * Normalize log row for API output.
	 *
	 * @param array<string, mixed> $record Raw row.
	 * @return array<string, mixed>
	 */
	private function map_record( $record ) {
		$context = array();

		if ( ! empty( $record['context'] ) ) {
			$decoded = json_decode( (string) $record['context'], true );
			$context = is_array( $decoded ) ? $decoded : array();
		}

		return array(
			'id'                => (int) $record['id'],
			'reminder_id'       => null !== $record['reminder_id'] ? (int) $record['reminder_id'] : null,
			'notification_type' => (string) $record['notification_type'],
			'channel'           => (string) $record['channel'],
			'status'            => (string) $record['status'],
			'message'           => (string) $record['message'],
			'sent_at'           => $record['sent_at'],
			'created_at'        => (string) $record['created_at'],
			'title'             => isset( $context['title'] ) ? (string) $context['title'] : '',
			'reminder_date'     => isset( $context['reminder_date'] ) ? (string) $context['reminder_date'] : '',
		);
	}
}
