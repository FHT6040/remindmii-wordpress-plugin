<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Wishlists_Repository {

	/**
	 * Return wishlists table name.
	 *
	 * @return string
	 */
	private function wishlists_table() {
		global $wpdb;
		return $wpdb->prefix . 'remindmii_wishlists';
	}

	/**
	 * Return wishlist items table name.
	 *
	 * @return string
	 */
	private function items_table() {
		global $wpdb;
		return $wpdb->prefix . 'remindmii_wishlist_items';
	}

	/**
	 * Get all wishlists for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_user( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, title, description, is_public, public_token, created_at, updated_at
				FROM {$this->wishlists_table()}
				WHERE user_id = %d
				ORDER BY created_at DESC",
				$user_id
			),
			ARRAY_A
		);

		return is_array( $results ) ? array_map( array( $this, 'map_wishlist' ), $results ) : array();
	}

	/**
	 * Get a single wishlist by ID and user.
	 *
	 * @param int $id Wishlist ID.
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id, $user_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, user_id, title, description, is_public, public_token, created_at, updated_at
				FROM {$this->wishlists_table()}
				WHERE id = %d AND user_id = %d",
				absint( $id ),
				absint( $user_id )
			),
			ARRAY_A
		);

		return $row ? $this->map_wishlist( $row ) : null;
	}

	/**
	 * Create a wishlist.
	 *
	 * @param int $user_id User ID.
	 * @param array<string, mixed> $data Wishlist fields.
	 * @return int|false New wishlist ID or false on failure.
	 */
	public function create( $user_id, $data ) {
		global $wpdb;

		$is_public    = ! empty( $data['is_public'] );
		$public_token = $is_public ? bin2hex( random_bytes( 16 ) ) : null;
		$now          = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$this->wishlists_table(),
			array(
				'user_id'      => absint( $user_id ),
				'title'        => sanitize_text_field( (string) $data['title'] ),
				'description'  => isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '',
				'is_public'    => $is_public ? 1 : 0,
				'public_token' => $public_token,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a wishlist.
	 *
	 * @param int $id Wishlist ID.
	 * @param int $user_id User ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public function update( $id, $user_id, $data ) {
		global $wpdb;

		$existing = $this->get_by_id( $id, $user_id );

		if ( ! $existing ) {
			return false;
		}

		$is_public    = ! empty( $data['is_public'] );
		$public_token = $existing['public_token'];

		if ( $is_public && ! $public_token ) {
			$public_token = bin2hex( random_bytes( 16 ) );
		} elseif ( ! $is_public ) {
			$public_token = null;
		}

		$updated = $wpdb->update(
			$this->wishlists_table(),
			array(
				'title'        => sanitize_text_field( (string) $data['title'] ),
				'description'  => isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '',
				'is_public'    => $is_public ? 1 : 0,
				'public_token' => $public_token,
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ), 'user_id' => absint( $user_id ) ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete a wishlist and all its items.
	 *
	 * @param int $id Wishlist ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function delete( $id, $user_id ) {
		global $wpdb;

		$existing = $this->get_by_id( $id, $user_id );

		if ( ! $existing ) {
			return false;
		}

		$wpdb->delete( $this->items_table(), array( 'wishlist_id' => absint( $id ) ), array( '%d' ) );
		$deleted = $wpdb->delete( $this->wishlists_table(), array( 'id' => absint( $id ), 'user_id' => absint( $user_id ) ), array( '%d', '%d' ) );

		return (bool) $deleted;
	}

	/**
	 * Get all items for a wishlist.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @param int $user_id User ID (ownership check).
	 * @return array<int, array<string, mixed>>
	 */
	public function get_items( $wishlist_id, $user_id ) {
		global $wpdb;

		$wishlist = $this->get_by_id( $wishlist_id, $user_id );

		if ( ! $wishlist ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, wishlist_id, user_id, title, description, url, price, currency, is_purchased, created_at, updated_at
				FROM {$this->items_table()}
				WHERE wishlist_id = %d
				ORDER BY created_at ASC",
				absint( $wishlist_id )
			),
			ARRAY_A
		);

		return is_array( $results ) ? array_map( array( $this, 'map_item' ), $results ) : array();
	}

	/**
	 * Add an item to a wishlist.
	 *
	 * @param int $wishlist_id Wishlist ID.
	 * @param int $user_id User ID.
	 * @param array<string, mixed> $data Item fields.
	 * @return int|false New item ID or false on failure.
	 */
	public function create_item( $wishlist_id, $user_id, $data ) {
		global $wpdb;

		$wishlist = $this->get_by_id( $wishlist_id, $user_id );

		if ( ! $wishlist ) {
			return false;
		}

		$price = isset( $data['price'] ) && '' !== (string) $data['price'] ? (float) $data['price'] : null;
		$now   = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$this->items_table(),
			array(
				'wishlist_id'  => absint( $wishlist_id ),
				'user_id'      => absint( $user_id ),
				'title'        => sanitize_text_field( (string) $data['title'] ),
				'description'  => isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '',
				'url'          => isset( $data['url'] ) ? esc_url_raw( (string) $data['url'] ) : '',
				'price'        => $price,
				'currency'     => isset( $data['currency'] ) ? strtoupper( substr( sanitize_text_field( (string) $data['currency'] ), 0, 10 ) ) : 'DKK',
				'is_purchased' => ! empty( $data['is_purchased'] ) ? 1 : 0,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', $price !== null ? '%f' : '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an item.
	 *
	 * @param int $item_id Item ID.
	 * @param int $wishlist_id Wishlist ID.
	 * @param int $user_id User ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public function update_item( $item_id, $wishlist_id, $user_id, $data ) {
		global $wpdb;

		$wishlist = $this->get_by_id( $wishlist_id, $user_id );

		if ( ! $wishlist ) {
			return false;
		}

		$price = isset( $data['price'] ) && '' !== (string) $data['price'] ? (float) $data['price'] : null;

		$updated = $wpdb->update(
			$this->items_table(),
			array(
				'title'        => sanitize_text_field( (string) $data['title'] ),
				'description'  => isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '',
				'url'          => isset( $data['url'] ) ? esc_url_raw( (string) $data['url'] ) : '',
				'price'        => $price,
				'currency'     => isset( $data['currency'] ) ? strtoupper( substr( sanitize_text_field( (string) $data['currency'] ), 0, 10 ) ) : 'DKK',
				'is_purchased' => ! empty( $data['is_purchased'] ) ? 1 : 0,
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $item_id ), 'wishlist_id' => absint( $wishlist_id ), 'user_id' => absint( $user_id ) ),
			array( '%s', '%s', '%s', $price !== null ? '%f' : '%s', '%s', '%d', '%s' ),
			array( '%d', '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete a wishlist item.
	 *
	 * @param int $item_id Item ID.
	 * @param int $wishlist_id Wishlist ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function delete_item( $item_id, $wishlist_id, $user_id ) {
		global $wpdb;

		$wishlist = $this->get_by_id( $wishlist_id, $user_id );

		if ( ! $wishlist ) {
			return false;
		}

		$deleted = $wpdb->delete(
			$this->items_table(),
			array( 'id' => absint( $item_id ), 'wishlist_id' => absint( $wishlist_id ), 'user_id' => absint( $user_id ) ),
			array( '%d', '%d', '%d' )
		);

		return (bool) $deleted;
	}

	/**
	 * Normalize a wishlist row for API output.
	 *
	 * @param array<string, mixed> $row Raw DB row.
	 * @return array<string, mixed>
	 */
	private function map_wishlist( $row ) {
		return array(
			'id'           => (int) $row['id'],
			'user_id'      => (int) $row['user_id'],
			'title'        => (string) $row['title'],
			'description'  => (string) ( $row['description'] ?? '' ),
			'is_public'    => (bool) $row['is_public'],
			'public_token' => $row['is_public'] ? (string) $row['public_token'] : null,
			'created_at'   => (string) $row['created_at'],
			'updated_at'   => (string) $row['updated_at'],
		);
	}

	/**
	 * Normalize a wishlist item row for API output.
	 *
	 * @param array<string, mixed> $row Raw DB row.
	 * @return array<string, mixed>
	 */
	private function map_item( $row ) {
		return array(
			'id'           => (int) $row['id'],
			'wishlist_id'  => (int) $row['wishlist_id'],
			'user_id'      => (int) $row['user_id'],
			'title'        => (string) $row['title'],
			'description'  => (string) ( $row['description'] ?? '' ),
			'url'          => (string) ( $row['url'] ?? '' ),
			'price'        => null !== $row['price'] ? (float) $row['price'] : null,
			'currency'     => (string) ( $row['currency'] ?? 'DKK' ),
			'is_purchased' => (bool) $row['is_purchased'],
			'created_at'   => (string) $row['created_at'],
			'updated_at'   => (string) $row['updated_at'],
		);
	}
}
