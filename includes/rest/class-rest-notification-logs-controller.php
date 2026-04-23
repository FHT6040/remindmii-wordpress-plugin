<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST_Notification_Logs_Controller {
	/**
	 * Logs repository.
	 *
	 * @var Remindmii_Notification_Logs_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Remindmii_Notification_Logs_Repository $repository Repository instance.
	 */
	public function __construct( $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'remindmii/v1',
			'/notifications',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			'remindmii/v1',
			'/notifications/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Parse and normalize filter params.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{since_days:int,status:string,search:string}
	 */
	private function parse_filters( $request ) {
		$since_days = absint( $request->get_param( 'since_days' ) );
		$status     = sanitize_key( (string) $request->get_param( 'status' ) );
		$search     = sanitize_text_field( wp_unslash( (string) $request->get_param( 'q' ) ) );
		$since_days = in_array( $since_days, array( 7, 30 ), true ) ? $since_days : 0;
		$status     = in_array( $status, array( 'sent', 'failed', 'preview' ), true ) ? $status : '';

		return array(
			'since_days' => $since_days,
			'status'     => $status,
			'search'     => $search,
		);
	}

	/**
	 * Ensure authenticated access.
	 *
	 * @return bool|WP_Error
	 */
	public function permissions_check() {
		if ( is_user_logged_in() ) {
			return true;
		}

		return new WP_Error( 'remindmii_forbidden', __( 'Authentication required.', 'remindmii' ), array( 'status' => 401 ) );
	}

	/**
	 * Return recent notification logs for the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$limit      = absint( $request->get_param( 'limit' ) );
		$offset     = absint( $request->get_param( 'offset' ) );
		$filters    = $this->parse_filters( $request );
		$limit      = $limit > 0 ? $limit : 10;
		$offset     = $offset > 0 ? $offset : 0;
		$user_id    = get_current_user_id();
		$items      = $this->repository->get_recent_by_user( $user_id, $limit, $offset, $filters['since_days'], $filters['status'], $filters['search'] );
		$total      = $this->repository->count_recent_by_user( $user_id, $filters['since_days'], $filters['status'], $filters['search'] );
		$next_offset = $offset + count( $items );

		return rest_ensure_response(
			array(
				'items'      => $items,
				'count'      => count( $items ),
				'total_count'=> $total,
				'offset'     => $offset,
				'next_offset'=> $next_offset,
				'has_more'   => $next_offset < $total,
			)
		);
	}

	/**
	 * Export matching notification logs as CSV.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function export_items( $request ) {
		$filters  = $this->parse_filters( $request );
		$user_id  = get_current_user_id();
		$offset   = 0;
		$limit    = 100;
		$max_rows = 5000;
		$rows     = array();

		do {
			$batch = $this->repository->get_recent_by_user( $user_id, $limit, $offset, $filters['since_days'], $filters['status'], $filters['search'] );
			if ( empty( $batch ) ) {
				break;
			}

			$rows   = array_merge( $rows, $batch );
			$offset += count( $batch );
		} while ( count( $batch ) === $limit && count( $rows ) < $max_rows );

		if ( count( $rows ) > $max_rows ) {
			$rows = array_slice( $rows, 0, $max_rows );
		}

		$csv_lines   = array();
		$csv_lines[] = 'id,status,channel,title,message,sent_at,created_at,reminder_id,reminder_date';

		foreach ( $rows as $row ) {
			$csv_lines[] = implode(
				',',
				array(
					$this->csv_cell( $row['id'] ?? '' ),
					$this->csv_cell( $row['status'] ?? '' ),
					$this->csv_cell( $row['channel'] ?? '' ),
					$this->csv_cell( $row['title'] ?? '' ),
					$this->csv_cell( $row['message'] ?? '' ),
					$this->csv_cell( $row['sent_at'] ?? '' ),
					$this->csv_cell( $row['created_at'] ?? '' ),
					$this->csv_cell( $row['reminder_id'] ?? '' ),
					$this->csv_cell( $row['reminder_date'] ?? '' ),
				)
			);
		}

		$response = new WP_REST_Response( implode( "\n", $csv_lines ), 200 );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="remindmii-notifications-' . gmdate( 'Y-m-d' ) . '.csv"' );

		return $response;
	}

	/**
	 * Escape a CSV cell value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function csv_cell( $value ) {
		$text = (string) $value;

		return '"' . str_replace( '"', '""', $text ) . '"';
	}
}
