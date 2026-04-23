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
		$limit = absint( $request->get_param( 'limit' ) );
		$items = $this->repository->get_recent_by_user( get_current_user_id(), $limit > 0 ? $limit : 10 );

		return rest_ensure_response( $items );
	}
}
