<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST {
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