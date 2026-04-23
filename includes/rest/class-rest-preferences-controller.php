<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST_Preferences_Controller {

	/** @var Remindmii_User_Preferences_Repository */
	private $repo;

	/**
	 * Constructor.
	 *
	 * @param Remindmii_User_Preferences_Repository $repo Preferences repository.
	 */
	public function __construct( $repo ) {
		$this->repo = $repo;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		$ns = 'remindmii/v1';

		register_rest_route(
			$ns,
			'/preferences',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_preferences' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_preferences' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
					'args'                => array(
						'theme' => array(
							'type' => 'string',
							'enum' => array( 'default', 'light', 'dark', 'romantic' ),
						),
						'enable_location_reminders' => array( 'type' => 'boolean' ),
						'enable_gamification'       => array( 'type' => 'boolean' ),
						'distracted_mode'           => array( 'type' => 'boolean' ),
					),
				),
			)
		);
	}

	/**
	 * GET /remindmii/v1/preferences
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_preferences( $request ) {
		$prefs = $this->repo->get( get_current_user_id() );
		return rest_ensure_response( $prefs );
	}

	/**
	 * PUT /remindmii/v1/preferences
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_preferences( $request ) {
		$data  = $request->get_json_params();
		$prefs = $this->repo->save( get_current_user_id(), $data );
		return rest_ensure_response( $prefs );
	}

	/**
	 * Permission: must be logged in.
	 *
	 * @return bool|WP_Error
	 */
	public function require_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'unauthorized', __( 'You must be logged in.', 'remindmii' ), array( 'status' => 401 ) );
		}
		return true;
	}
}
