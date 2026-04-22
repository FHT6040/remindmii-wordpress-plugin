<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST {
	/**
	 * Profile controller instance.
	 *
	 * @var Remindmii_REST_Profile_Controller
	 */
	private $profile_controller;

	/**
	 * Category controller instance.
	 *
	 * @var Remindmii_REST_Categories_Controller
	 */
	private $categories_controller;

	/**
	 * Reminder controller instance.
	 *
	 * @var Remindmii_REST_Reminders_Controller
	 */
	private $reminders_controller;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$profile_repository          = new Remindmii_User_Profiles_Repository();
		$this->profile_controller    = new Remindmii_REST_Profile_Controller( $profile_repository );
		$categories_repository      = new Remindmii_Categories_Repository();
		$this->categories_controller = new Remindmii_REST_Categories_Controller( $categories_repository );
		$this->reminders_controller = new Remindmii_REST_Reminders_Controller(
			new Remindmii_Reminders_Repository(),
			$categories_repository
		);
	}

	/**
	 * Register REST hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register placeholder routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->profile_controller->register_routes();
		$this->categories_controller->register_routes();
		$this->reminders_controller->register_routes();

		register_rest_route(
			'remindmii/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return plugin health status.
	 *
	 * @return WP_REST_Response
	 */
	public function health_check() {
		return rest_ensure_response(
			array(
				'status'  => 'ok',
				'plugin'  => 'remindmii',
				'version' => REMINDMII_VERSION,
			)
		);
	}
}
