<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST_Profile_Controller {
	/**
	 * Profile repository.
	 *
	 * @var Remindmii_User_Profiles_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Remindmii_User_Profiles_Repository $repository Repository instance.
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
			'/profile',
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
			)
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
	 * Return current user profile.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item() {
		$user_id = get_current_user_id();
		$profile = $this->repository->get_by_user_id( $user_id );

		if ( null === $profile ) {
			Remindmii_Installer::ensure_user_records( $user_id );
			$profile = $this->repository->get_by_user_id( $user_id );
		}

		return rest_ensure_response( $profile ? $profile : array() );
	}

	/**
	 * Update current user profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$data = $this->prepare_payload( $request );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$updated = $this->repository->update_by_user_id( get_current_user_id(), $data );

		return is_wp_error( $updated ) ? $updated : rest_ensure_response( $updated );
	}

	/**
	 * Validate and sanitize profile payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	private function prepare_payload( $request ) {
		$email = (string) $request->get_param( 'email' );

		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'remindmii_invalid_email', __( 'A valid email is required.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$notification_hours = absint( $request->get_param( 'notification_hours' ) );
		$notification_hours = max( 1, min( 720, $notification_hours > 0 ? $notification_hours : 24 ) );

		$birth_date = (string) $request->get_param( 'birth_date' );
		if ( '' !== $birth_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birth_date ) ) {
			return new WP_Error( 'remindmii_invalid_birth_date', __( 'Birth date must use YYYY-MM-DD format.', 'remindmii' ), array( 'status' => 400 ) );
		}

		return array(
			'full_name'           => sanitize_text_field( (string) $request->get_param( 'full_name' ) ),
			'birth_date'          => '' !== $birth_date ? $birth_date : null,
			'gender'              => sanitize_text_field( (string) $request->get_param( 'gender' ) ),
			'pronouns'            => sanitize_text_field( (string) $request->get_param( 'pronouns' ) ),
			'email'               => '' !== $email ? sanitize_email( $email ) : null,
			'phone'               => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
			'email_notifications' => rest_sanitize_boolean( $request->get_param( 'email_notifications' ) ) ? 1 : 0,
			'notification_hours'  => $notification_hours,
		);
	}
}
