<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST_Templates_Controller {

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'remindmii/v1',
			'/templates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_templates' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /remindmii/v1/templates
	 *
	 * @return WP_REST_Response
	 */
	public function get_templates() {
		return rest_ensure_response( array( 'templates' => Remindmii_Templates::get_all() ) );
	}
}
