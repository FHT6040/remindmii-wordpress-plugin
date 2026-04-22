<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST_Categories_Controller {
	/**
	 * Category repository.
	 *
	 * @var Remindmii_Categories_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Remindmii_Categories_Repository $repository Repository instance.
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
			'/categories',
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
			'/categories/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
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
	 * Return current user categories.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		return rest_ensure_response( $this->repository->get_all_by_user( get_current_user_id() ) );
	}

	/**
	 * Create category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$data = $this->prepare_payload( $request );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$created = $this->repository->create( get_current_user_id(), $data );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$response = rest_ensure_response( $created );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Delete category.
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
	 * Validate category payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, string>|WP_Error
	 */
	private function prepare_payload( $request ) {
		$name = $request->get_param( 'name' );

		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			return new WP_Error( 'remindmii_invalid_category_name', __( 'A category name is required.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$color = $request->get_param( 'color' );
		$color = is_string( $color ) ? sanitize_hex_color( $color ) : '';

		if ( empty( $color ) ) {
			$color = '#3B82F6';
		}

		$icon = $request->get_param( 'icon' );
		$icon = is_string( $icon ) ? sanitize_key( $icon ) : 'tag';

		if ( '' === $icon ) {
			$icon = 'tag';
		}

		return array(
			'name'  => sanitize_text_field( $name ),
			'color' => $color,
			'icon'  => $icon,
		);
	}
}
