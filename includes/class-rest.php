<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST {
	/**
	 * Notification logs controller instance.
	 *
	 * @var Remindmii_REST_Notification_Logs_Controller
	 */
	private $notification_logs_controller;

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
	 * Wishlists controller instance.
	 *
	 * @var Remindmii_REST_Wishlists_Controller
	 */
	private $wishlists_controller;

	/**
	 * Templates controller instance.
	 *
	 * @var Remindmii_REST_Templates_Controller
	 */
	private $templates_controller;

	/**
	 * Preferences controller instance.
	 *
	 * @var Remindmii_REST_Preferences_Controller
	 */
	private $preferences_controller;

	/**
	 * Wishlist shares controller instance.
	 *
	 * @var Remindmii_REST_Wishlist_Shares_Controller
	 */
	private $wishlist_shares_controller;

	/**
	 * Gamification controller instance.
	 *
	 * @var Remindmii_REST_Gamification_Controller
	 */
	private $gamification_controller;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->notification_logs_controller = new Remindmii_REST_Notification_Logs_Controller( new Remindmii_Notification_Logs_Repository() );
		$profile_repository          = new Remindmii_User_Profiles_Repository();
		$this->profile_controller    = new Remindmii_REST_Profile_Controller( $profile_repository );
		$categories_repository      = new Remindmii_Categories_Repository();
		$this->categories_controller = new Remindmii_REST_Categories_Controller( $categories_repository );
		$this->reminders_controller = new Remindmii_REST_Reminders_Controller(
			new Remindmii_Reminders_Repository(),
			$categories_repository
		);
		$this->wishlists_controller  = new Remindmii_REST_Wishlists_Controller( new Remindmii_Wishlists_Repository() );
		$this->templates_controller  = new Remindmii_REST_Templates_Controller();
		$this->preferences_controller     = new Remindmii_REST_Preferences_Controller( new Remindmii_User_Preferences_Repository() );
		$wishlists_repo                    = new Remindmii_Wishlists_Repository();
		$this->wishlist_shares_controller = new Remindmii_REST_Wishlist_Shares_Controller( new Remindmii_Wishlist_Shares_Repository(), $wishlists_repo );
		$this->gamification_controller   = new Remindmii_REST_Gamification_Controller();
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
		$this->notification_logs_controller->register_routes();
		$this->profile_controller->register_routes();
		$this->categories_controller->register_routes();
		$this->reminders_controller->register_routes();
		$this->wishlists_controller->register_routes();
		$this->templates_controller->register_routes();
		$this->preferences_controller->register_routes();
		$this->wishlist_shares_controller->register_routes();
		$this->gamification_controller->register_routes();

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
