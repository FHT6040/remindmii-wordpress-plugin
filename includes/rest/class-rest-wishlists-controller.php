<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST_Wishlists_Controller {

	/** @var Remindmii_Wishlists_Repository */
	private $repo;

	/**
	 * Constructor.
	 *
	 * @param Remindmii_Wishlists_Repository $repo Wishlists repository.
	 */
	public function __construct( $repo ) {
		$this->repo = $repo;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		$ns = 'remindmii/v1';

		// Collection.
		register_rest_route(
			$ns,
			'/wishlists',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_wishlists' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_wishlist' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
					'args'                => $this->wishlist_args( true ),
				),
			)
		);

		// Single wishlist.
		register_rest_route(
			$ns,
			'/wishlists/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_wishlist' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_wishlist' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
					'args'                => $this->wishlist_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_wishlist' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
				),
			)
		);

		// Items collection.
		register_rest_route(
			$ns,
			'/wishlists/(?P<wishlist_id>\d+)/items',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
					'args'                => $this->item_args( true ),
				),
			)
		);

		// Single item.
		register_rest_route(
			$ns,
			'/wishlists/(?P<wishlist_id>\d+)/items/(?P<item_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
					'args'                => $this->item_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'require_logged_in' ),
				),
			)
		);

		// Toggle purchased.
		register_rest_route(
			$ns,
			'/wishlists/(?P<wishlist_id>\d+)/items/(?P<item_id>\d+)/toggle',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'toggle_item' ),
				'permission_callback' => array( $this, 'require_logged_in' ),
			)
		);

		// Public view by share token (no auth).
		register_rest_route(
			$ns,
			'/public/wishlists/(?P<token>[a-f0-9]{32})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_public_wishlist' ),
				'permission_callback' => '__return_true',
			)
		);

		// Public view by slug (no auth).
		register_rest_route(
			$ns,
			'/public/wishlists/by-slug/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_public_wishlist_by_slug' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// -------------------------------------------------------------------------
	// Wishlist handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /wishlists
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_wishlists( $request ) {
		$user_id  = get_current_user_id();
		$all      = $this->repo->get_by_user( $user_id );
		$total    = count( $all );
		$per_page = max( 1, min( 200, absint( $request->get_param( 'per_page' ) ?: 100 ) ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$items    = array_slice( $all, ( $page - 1 ) * $per_page, $per_page );

		$response = rest_ensure_response( array( 'wishlists' => $items ) );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );
		return $response;
	}

	/**
	 * GET /wishlists/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_wishlist( $request ) {
		$wishlist = $this->repo->get_by_id( (int) $request['id'], get_current_user_id() );

		if ( ! $wishlist ) {
			return new WP_Error( 'not_found', __( 'Wishlist not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $wishlist );
	}

	/**
	 * POST /wishlists
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_wishlist( $request ) {
		$user_id = get_current_user_id();
		$data    = $request->get_json_params();

		if ( empty( $data['title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$new_id = $this->repo->create( $user_id, $data );

		if ( ! $new_id ) {
			return new WP_Error( 'create_failed', __( 'Could not create wishlist.', 'remindmii' ), array( 'status' => 500 ) );
		}

		$slug_base = sanitize_title( $data['title'] );
		$this->repo->set_slug( $new_id, $user_id, $slug_base . '-' . $new_id );

		$wishlist = $this->repo->get_by_id( $new_id, $user_id );

		return rest_ensure_response( $wishlist );
	}

	/**
	 * PUT /wishlists/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_wishlist( $request ) {
		$user_id = get_current_user_id();
		$id      = (int) $request['id'];
		$data    = $request->get_json_params();

		if ( empty( $data['title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$ok = $this->repo->update( $id, $user_id, $data );

		if ( ! $ok ) {
			return new WP_Error( 'not_found', __( 'Wishlist not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->repo->get_by_id( $id, $user_id ) );
	}

	/**
	 * DELETE /wishlists/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_wishlist( $request ) {
		$ok = $this->repo->delete( (int) $request['id'], get_current_user_id() );

		if ( ! $ok ) {
			return new WP_Error( 'not_found', __( 'Wishlist not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	// -------------------------------------------------------------------------
	// Item handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /wishlists/{wishlist_id}/items
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$user_id    = get_current_user_id();
		$wishlist_id = (int) $request['wishlist_id'];
		$items       = $this->repo->get_items( $wishlist_id, $user_id );

		if ( false === $items && ! $this->repo->get_by_id( $wishlist_id, $user_id ) ) {
			return new WP_Error( 'not_found', __( 'Wishlist not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * POST /wishlists/{wishlist_id}/items
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$user_id     = get_current_user_id();
		$wishlist_id = (int) $request['wishlist_id'];
		$data        = $request->get_json_params();

		if ( empty( $data['title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$new_id = $this->repo->create_item( $wishlist_id, $user_id, $data );

		if ( ! $new_id ) {
			return new WP_Error( 'not_found', __( 'Wishlist not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$items = $this->repo->get_items( $wishlist_id, $user_id );

		foreach ( $items as $item ) {
			if ( $item['id'] === $new_id ) {
				return rest_ensure_response( $item );
			}
		}

		return rest_ensure_response( array( 'id' => $new_id ) );
	}

	/**
	 * PUT /wishlists/{wishlist_id}/items/{item_id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$user_id     = get_current_user_id();
		$wishlist_id = (int) $request['wishlist_id'];
		$item_id     = (int) $request['item_id'];
		$data        = $request->get_json_params();

		if ( empty( $data['title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$ok = $this->repo->update_item( $item_id, $wishlist_id, $user_id, $data );

		if ( ! $ok ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$items = $this->repo->get_items( $wishlist_id, $user_id );

		foreach ( $items as $item ) {
			if ( $item['id'] === $item_id ) {
				return rest_ensure_response( $item );
			}
		}

		return rest_ensure_response( array( 'updated' => true ) );
	}

	/**
	 * DELETE /wishlists/{wishlist_id}/items/{item_id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$ok = $this->repo->delete_item(
			(int) $request['item_id'],
			(int) $request['wishlist_id'],
			get_current_user_id()
		);

		if ( ! $ok ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * PUT /wishlists/{wishlist_id}/items/{item_id}/toggle
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function toggle_item( $request ) {
		$user_id     = get_current_user_id();
		$wishlist_id = (int) $request['wishlist_id'];
		$item_id     = (int) $request['item_id'];

		$items = $this->repo->get_items( $wishlist_id, $user_id );

		$current_item = null;

		foreach ( $items as $item ) {
			if ( $item['id'] === $item_id ) {
				$current_item = $item;
				break;
			}
		}

		if ( ! $current_item ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$new_state = ! $current_item['is_purchased'];

		$this->repo->update_item(
			$item_id,
			$wishlist_id,
			$user_id,
			array_merge( $current_item, array( 'is_purchased' => $new_state ) )
		);

		$items = $this->repo->get_items( $wishlist_id, $user_id );

		foreach ( $items as $item ) {
			if ( $item['id'] === $item_id ) {
				return rest_ensure_response( $item );
			}
		}

		return rest_ensure_response( array( 'updated' => true ) );
	}

	// -------------------------------------------------------------------------
	// Permissions
	// -------------------------------------------------------------------------

	/**
	 * GET /public/wishlists/{token}
	 *
	 * Returns wishlist metadata and items for anonymous/public access.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_public_wishlist( $request ) {
		$token    = sanitize_text_field( (string) $request['token'] );
		$wishlist = $this->repo->get_by_token( $token );

		if ( ! $wishlist ) {
			return new WP_Error( 'not_found', __( 'Wishlist not found or not public.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$items = $this->repo->get_items_public( $wishlist['id'] );

		return rest_ensure_response(
			array(
				'wishlist' => $wishlist,
				'items'    => $items,
			)
		);
	}

	/**
	 * GET /public/wishlists/by-slug/{slug}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_public_wishlist_by_slug( $request ) {
		$slug     = sanitize_title( (string) $request['slug'] );
		$wishlist = $this->repo->get_by_slug( $slug );

		if ( ! $wishlist ) {
			return new WP_Error( 'not_found', __( 'Wishlist not found or not public.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$items = $this->repo->get_items_public( $wishlist['id'] );

		return rest_ensure_response(
			array(
				'wishlist' => $wishlist,
				'items'    => $items,
			)
		);
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

		return Remindmii_Security::check_rate_limit( get_current_user_id() );
	}

	// -------------------------------------------------------------------------
	// Argument schemas
	// -------------------------------------------------------------------------

	/**
	 * Argument schema for wishlist create/update.
	 *
	 * @param bool $required Whether title is required.
	 * @return array<string, mixed>
	 */
	private function wishlist_args( $required ) {
		return array(
			'title'       => array(
				'type'     => 'string',
				'required' => $required,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'is_public'   => array(
				'type' => 'boolean',
			),
		);
	}

	/**
	 * Argument schema for item create/update.
	 *
	 * @param bool $required Whether title is required.
	 * @return array<string, mixed>
	 */
	private function item_args( $required ) {
		return array(
			'title'        => array(
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'url'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'price'        => array(
				'type' => 'number',
			),
			'currency'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'is_purchased' => array(
				'type' => 'boolean',
			),
		);
	}
}
