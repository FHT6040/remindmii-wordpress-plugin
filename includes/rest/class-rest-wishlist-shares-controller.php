<?php
/**
 * REST controller for wishlist shares.
 *
 * @package Remindmii
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Remindmii_REST_Wishlist_Shares_Controller
 */
class Remindmii_REST_Wishlist_Shares_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'remindmii/v1';

	/**
	 * Repository instance.
	 *
	 * @var Remindmii_Wishlist_Shares_Repository
	 */
	private $repo;

	/**
	 * Wishlists repository (for ownership check).
	 *
	 * @var Remindmii_Wishlists_Repository
	 */
	private $wishlists_repo;

	/**
	 * Constructor.
	 *
	 * @param Remindmii_Wishlist_Shares_Repository $repo           Shares repo.
	 * @param Remindmii_Wishlists_Repository       $wishlists_repo Wishlists repo.
	 */
	public function __construct( Remindmii_Wishlist_Shares_Repository $repo, Remindmii_Wishlists_Repository $wishlists_repo ) {
		$this->repo           = $repo;
		$this->wishlists_repo = $wishlists_repo;
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// List / create shares for a wishlist.
		register_rest_route(
			self::NAMESPACE,
			'/wishlists/(?P<wishlist_id>\d+)/shares',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_shares' ),
					'permission_callback' => array( $this, 'owner_permission_check' ),
					'args'                => array( 'wishlist_id' => array( 'required' => true, 'type' => 'integer' ) ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_share' ),
					'permission_callback' => array( $this, 'owner_permission_check' ),
					'args'                => array(
						'wishlist_id'        => array( 'required' => true, 'type' => 'integer' ),
						'shared_with_email'  => array( 'required' => true, 'type' => 'string', 'format' => 'email' ),
						'permission'         => array( 'type' => 'string', 'enum' => array( 'view', 'edit' ), 'default' => 'view' ),
						'expires_at'         => array( 'type' => 'string', 'format' => 'date-time' ),
					),
				),
			)
		);

		// Delete a share.
		register_rest_route(
			self::NAMESPACE,
			'/wishlists/(?P<wishlist_id>\d+)/shares/(?P<share_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_share' ),
				'permission_callback' => array( $this, 'owner_permission_check' ),
				'args'                => array(
					'wishlist_id' => array( 'required' => true, 'type' => 'integer' ),
					'share_id'    => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);

		// Get wishlists shared with current user.
		register_rest_route(
			self::NAMESPACE,
			'/shared-with-me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'shared_with_me' ),
				'permission_callback' => function () { return is_user_logged_in(); },
			)
		);
	}

	/**
	 * Permission check: logged in and owns the wishlist.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function owner_permission_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in.', 'remindmii' ), array( 'status' => 401 ) );
		}

		$wishlist = $this->wishlists_repo->get_by_id( (int) $request['wishlist_id'], get_current_user_id() );

		if ( ! $wishlist ) {
			return new WP_Error( 'rest_not_found', __( 'Wishlist not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		return true;
	}

	/**
	 * List shares for a wishlist.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_shares( $request ) {
		$shares = $this->repo->get_by_wishlist( (int) $request['wishlist_id'] );
		return rest_ensure_response( array( 'shares' => $shares ) );
	}

	/**
	 * Create a share.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_share( $request ) {
		$expires_raw = $request->get_param( 'expires_at' );
		$expires_at  = null;
		if ( ! empty( $expires_raw ) ) {
			$ts = strtotime( sanitize_text_field( (string) $expires_raw ) );
			if ( $ts ) {
				$expires_at = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		$id = $this->repo->create(
			(int) $request['wishlist_id'],
			get_current_user_id(),
			$request['shared_with_email'],
			$request->get_param('permission') ?: 'view',
			$expires_at
		);

		if ( false === $id ) {
			return new WP_Error( 'db_error', __( 'Could not create share.', 'remindmii' ), array( 'status' => 500 ) );
		}

		$share = $this->repo->get_by_id( $id );
		return rest_ensure_response( $share );
	}

	/**
	 * Delete a share.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_share( $request ) {
		$share = $this->repo->get_by_id( (int) $request['share_id'] );

		if ( ! $share || (int) $share['wishlist_id'] !== (int) $request['wishlist_id'] ) {
			return new WP_Error( 'rest_not_found', __( 'Share not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$this->repo->delete( (int) $request['share_id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Get wishlists shared with current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function shared_with_me( $request ) {
		$shares = $this->repo->get_shared_with_user( get_current_user_id() );
		return rest_ensure_response( array( 'shares' => $shares ) );
	}
}
