<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST_Reminders_Controller {
	/**
	 * Reminder repository.
	 *
	 * @var Remindmii_Reminders_Repository
	 */
	private $repository;

	/**
	 * Category repository.
	 *
	 * @var Remindmii_Categories_Repository
	 */
	private $categories_repository;

	/**
	 * Constructor.
	 *
	 * @param Remindmii_Reminders_Repository  $repository            Repository instance.
	 * @param Remindmii_Categories_Repository $categories_repository Categories repository instance.
	 */
	public function __construct( $repository, $categories_repository ) {
		$this->repository            = $repository;
		$this->categories_repository = $categories_repository;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'remindmii/v1',
			'/reminders',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			'remindmii/v1',
			'/reminders/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Ensure the request comes from an authenticated user.
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
	 * Return current user reminders.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		return rest_ensure_response( $this->repository->get_all_by_user( get_current_user_id() ) );
	}

	/**
	 * Return a single reminder.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$reminder = $this->repository->get_by_id( absint( $request['id'] ), get_current_user_id() );

		if ( null === $reminder ) {
			return new WP_Error( 'remindmii_reminder_not_found', __( 'Reminder not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $reminder );
	}

	/**
	 * Create a reminder.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$data = $this->prepare_payload( $request );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$created  = $this->repository->create( get_current_user_id(), $data );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$response = rest_ensure_response( $created );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Update a reminder.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$data = $this->prepare_payload( $request );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$updated = $this->repository->update( absint( $request['id'] ), get_current_user_id(), $data );

		return is_wp_error( $updated ) ? $updated : rest_ensure_response( $updated );
	}

	/**
	 * Delete a reminder.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$deleted = $this->repository->delete( absint( $request['id'] ), get_current_user_id() );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Validate and normalize reminder payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	private function prepare_payload( $request ) {
		$title = $request->get_param( 'title' );

		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			return new WP_Error( 'remindmii_invalid_title', __( 'A title is required.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$reminder_date = $request->get_param( 'reminder_date' );

		if ( ! is_string( $reminder_date ) || false === strtotime( $reminder_date ) ) {
			return new WP_Error( 'remindmii_invalid_reminder_date', __( 'A valid reminder date is required.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$category_id = $request->get_param( 'category_id' );
		$category_id = null === $category_id || '' === $category_id ? null : absint( $category_id );

		if ( null !== $category_id && null === $this->categories_repository->get_by_id( $category_id, get_current_user_id() ) ) {
			return new WP_Error( 'remindmii_invalid_category', __( 'The selected category is invalid.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$recurrence_interval = $request->get_param( 'recurrence_interval' );
		$recurrence_interval = is_string( $recurrence_interval ) ? sanitize_key( $recurrence_interval ) : null;
		$allowed_intervals   = array( 'daily', 'weekly', 'monthly', 'yearly' );

		if ( null !== $recurrence_interval && ! in_array( $recurrence_interval, $allowed_intervals, true ) ) {
			$recurrence_interval = null;
		}

		$is_recurring = rest_sanitize_boolean( $request->get_param( 'is_recurring' ) );

		if ( ! $is_recurring ) {
			$recurrence_interval = null;
		}

		return array(
			'category_id'         => $category_id,
			'title'               => sanitize_text_field( $title ),
			'description'         => sanitize_textarea_field( (string) $request->get_param( 'description' ) ),
			'reminder_date'       => gmdate( 'Y-m-d H:i:s', strtotime( $reminder_date ) ),
			'is_recurring'        => $is_recurring ? 1 : 0,
			'recurrence_interval' => $recurrence_interval,
			'is_completed'        => rest_sanitize_boolean( $request->get_param( 'is_completed' ) ) ? 1 : 0,
		);
	}
}
