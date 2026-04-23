<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Notification_Logs_Repository {
	/**
	 * Build SQL where clause and args for history queries.
	 *
	 * @param int $user_id User ID.
	 * @param int $since_days Optional date window.
	 * @param string $status Optional status filter.
	 * @param string $search Optional text query.
	 * @return array{where_clause:string, query_args:array<int, mixed>}
	 */
	private function build_filters( $user_id, $since_days, $status, $search ) {
		global $wpdb;

		$where_clause = 'WHERE user_id = %d';
		$query_args   = array( $user_id );

		if ( $since_days > 0 ) {
			$since_timestamp = gmdate( 'Y-m-d H:i:s', time() - ( $since_days * 86400 ) );
			$where_clause   .= ' AND created_at >= %s';
			$query_args[]    = $since_timestamp;
		}

		if ( '' !== $status ) {
			$where_clause .= ' AND status = %s';
			$query_args[]  = $status;
		}

		if ( '' !== $search ) {
			$like_value      = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clause   .= ' AND (message LIKE %s OR context LIKE %s)';
			$query_args[]    = $like_value;
			$query_args[]    = $like_value;
		}

		return array(
			'where_clause' => $where_clause,
			'query_args'   => $query_args,
		);
	}

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
	 * @param int $since_days Optional date window in days (0 for all).
	 * @param string $status Optional status filter.
	 * @param string $search Optional text search against message/context.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_by_user( $user_id, $limit = 10, $offset = 0, $since_days = 0, $status = '', $search = '' ) {
		global $wpdb;

		$user_id    = absint( $user_id );
		$limit      = max( 1, min( 50, absint( $limit ) ) );
		$offset     = max( 0, absint( $offset ) );
		$since_days = in_array( absint( $since_days ), array( 7, 30 ), true ) ? absint( $since_days ) : 0;
		$status     = in_array( (string) $status, array( 'sent', 'failed', 'preview' ), true ) ? (string) $status : '';
		$search     = trim( (string) $search );

		if ( $user_id <= 0 ) {
			return array();
		}

		$filters      = $this->build_filters( $user_id, $since_days, $status, $search );
		$where_clause = $filters['where_clause'];
		$query_args   = $filters['query_args'];

		$query_args[] = $limit;
		$query_args[] = $offset;

		$sql = "SELECT id, reminder_id, notification_type, channel, status, message, context, sent_at, created_at
				FROM {$this->table_name()}
				{$where_clause}
				ORDER BY id DESC
				LIMIT %d OFFSET %d";

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, ...$query_args ),
			ARRAY_A
		);

		return is_array( $results ) ? array_map( array( $this, 'map_record' ), $results ) : array();
	}

	/**
	 * Count matching notification log entries for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $since_days Optional date window in days (0 for all).
	 * @param string $status Optional status filter.
	 * @param string $search Optional text search against message/context.
	 * @return int
	 */
	public function count_recent_by_user( $user_id, $since_days = 0, $status = '', $search = '' ) {
		global $wpdb;

		$user_id    = absint( $user_id );
		$since_days = in_array( absint( $since_days ), array( 7, 30 ), true ) ? absint( $since_days ) : 0;
		$status     = in_array( (string) $status, array( 'sent', 'failed', 'preview' ), true ) ? (string) $status : '';
		$search     = trim( (string) $search );

		if ( $user_id <= 0 ) {
			return 0;
		}

		$filters      = $this->build_filters( $user_id, $since_days, $status, $search );
		$where_clause = $filters['where_clause'];
		$query_args   = $filters['query_args'];

		$sql = "SELECT COUNT(*)
				FROM {$this->table_name()}
				{$where_clause}";

		$count = $wpdb->get_var( $wpdb->prepare( $sql, ...$query_args ) );

		return null === $count ? 0 : (int) $count;
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
