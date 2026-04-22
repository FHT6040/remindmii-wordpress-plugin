<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Categories_Repository {
	/**
	 * Return categories table name.
	 *
	 * @return string
	 */
	private function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'remindmii_categories';
	}

	/**
	 * Fetch all categories for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_by_user( $user_id ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE user_id = %d ORDER BY name ASC, id ASC",
				absint( $user_id )
			),
			ARRAY_A
		);

		return is_array( $results ) ? array_map( array( $this, 'map_record' ), $results ) : array();
	}

	/**
	 * Fetch a category by ID for a user.
	 *
	 * @param int $category_id Category ID.
	 * @param int $user_id     WordPress user ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $category_id, $user_id ) {
		global $wpdb;

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE id = %d AND user_id = %d LIMIT 1",
				absint( $category_id ),
				absint( $user_id )
			),
			ARRAY_A
		);

		return is_array( $record ) ? $this->map_record( $record ) : null;
	}

	/**
	 * Create a category.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $data    Category payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( $user_id, $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table_name(),
			array(
				'user_id'    => absint( $user_id ),
				'name'       => $data['name'],
				'color'      => $data['color'],
				'icon'       => $data['icon'],
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'remindmii_category_create_failed', __( 'Unable to create category.', 'remindmii' ), array( 'status' => 500 ) );
		}

		return $this->get_by_id( (int) $wpdb->insert_id, $user_id );
	}

	/**
	 * Delete a category for a user.
	 *
	 * @param int $category_id Category ID.
	 * @param int $user_id     WordPress user ID.
	 * @return bool|WP_Error
	 */
	public function delete( $category_id, $user_id ) {
		global $wpdb;

		$existing = $this->get_by_id( $category_id, $user_id );

		if ( null === $existing ) {
			return new WP_Error( 'remindmii_category_not_found', __( 'Category not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$deleted = $wpdb->delete(
			$this->table_name(),
			array(
				'id'      => absint( $category_id ),
				'user_id' => absint( $user_id ),
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'remindmii_category_delete_failed', __( 'Unable to delete category.', 'remindmii' ), array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Normalize database row.
	 *
	 * @param array<string, mixed> $record Raw row.
	 * @return array<string, mixed>
	 */
	private function map_record( $record ) {
		$record['id']      = (int) $record['id'];
		$record['user_id'] = (int) $record['user_id'];

		return $record;
	}
}
