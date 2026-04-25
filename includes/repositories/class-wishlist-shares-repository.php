<?php
/**
 * Wishlist shares repository.
 *
 * @package Remindmii
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Remindmii_Wishlist_Shares_Repository
 */
class Remindmii_Wishlist_Shares_Repository {

	/**
	 * Table name (without wpdb prefix — prefix applied at runtime).
	 */
	const TABLE = 'remindmii_wishlist_shares';

	/**
	 * Create a share invitation.
	 *
	 * @param int    $wishlist_id        Wishlist ID.
	 * @param int    $owner_id           Owner user ID.
	 * @param string $shared_with_email  Recipient email.
	 * @param string $permission         'view' or 'edit'.
	 * @return int|false Insert ID or false.
	 */
	public function create( $wishlist_id, $owner_id, $shared_with_email, $permission = 'view', $expires_at = null ) {
		global $wpdb;

		// Resolve email to WP user ID if possible.
		$shared_user   = get_user_by( 'email', $shared_with_email );
		$shared_user_id = $shared_user ? (int) $shared_user->ID : null;

		$token = wp_generate_password( 32, false );

		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE,
			array(
				'wishlist_id'         => (int) $wishlist_id,
				'owner_id'            => (int) $owner_id,
				'shared_with_email'   => sanitize_email( $shared_with_email ),
				'shared_with_user_id' => $shared_user_id,
				'permission'          => in_array( $permission, array( 'view', 'edit' ), true ) ? $permission : 'view',
				'token'               => $token,
				'expires_at'          => $expires_at,
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all shares for a wishlist.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @return array
	 */
	public function get_by_wishlist( $wishlist_id ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE wishlist_id = %d ORDER BY created_at DESC", (int) $wishlist_id ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get shares where the current user is the recipient.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_shared_with_user( $user_id ) {
		global $wpdb;

		$shares_table   = $wpdb->prefix . self::TABLE;
		$wishlist_table = $wpdb->prefix . 'remindmii_wishlists';
		$user           = get_user_by( 'ID', $user_id );
		$email          = $user ? $user->user_email : '';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, w.title AS wishlist_title, w.description AS wishlist_description
				 FROM {$shares_table} s
				 INNER JOIN {$wishlist_table} w ON w.id = s.wishlist_id
				 WHERE ( s.shared_with_user_id = %d OR s.shared_with_email = %s )
				   AND ( s.expires_at IS NULL OR s.expires_at > %s )
				 ORDER BY s.created_at DESC",
				(int) $user_id,
				$email,
				current_time( 'mysql' )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get a single share by ID.
	 *
	 * @param int $share_id Share ID.
	 * @return array|null
	 */
	public function get_by_id( $share_id ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $share_id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Delete a share.
	 *
	 * @param int $share_id Share ID.
	 * @return bool
	 */
	public function delete( $share_id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->prefix . self::TABLE,
			array( 'id' => (int) $share_id ),
			array( '%d' )
		);

		return false !== $result;
	}
}
