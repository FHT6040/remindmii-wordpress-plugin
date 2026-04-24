<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST_Merchants_Controller {

	/**
	 * Register REST routes for the merchant portal.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'remindmii/v1',
			'/merchant/profile',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_profile' ),
				'permission_callback' => array( $this, 'merchant_permissions' ),
			)
		);

		register_rest_route(
			'remindmii/v1',
			'/merchant/ads',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_ads' ),
					'permission_callback' => array( $this, 'merchant_permissions' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_ad' ),
					'permission_callback' => array( $this, 'merchant_permissions' ),
				),
			)
		);

		register_rest_route(
			'remindmii/v1',
			'/merchant/ads/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_ad' ),
					'permission_callback' => array( $this, 'merchant_permissions' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_ad' ),
					'permission_callback' => array( $this, 'merchant_permissions' ),
				),
			)
		);

		register_rest_route(
			'remindmii/v1',
			'/merchant/ads/(?P<id>\d+)/toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'toggle_ad' ),
				'permission_callback' => array( $this, 'merchant_permissions' ),
			)
		);
	}

	/**
	 * Require that the current user is linked to a merchant.
	 *
	 * @return bool
	 */
	public function merchant_permissions() {
		return is_user_logged_in() && $this->get_merchant_id() > 0;
	}

	/**
	 * Retrieve the merchant ID for the current WP user.
	 *
	 * @return int
	 */
	private function get_merchant_id() {
		global $wpdb;
		$table = $wpdb->prefix . 'remindmii_merchant_users';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT merchant_id FROM {$table} WHERE user_id = %d LIMIT 1",
				get_current_user_id()
			)
		);
	}

	/**
	 * Return the current merchant's profile.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_profile( WP_REST_Request $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'remindmii_merchants';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $this->get_merchant_id() ),
			ARRAY_A
		);
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Merchant not found.', 'remindmii' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	/**
	 * List all ads for the current merchant.
	 *
	 * @return WP_REST_Response
	 */
	public function list_ads( WP_REST_Request $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'remindmii_merchant_ads';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE merchant_id = %d ORDER BY created_at DESC",
				$this->get_merchant_id()
			),
			ARRAY_A
		);
		return rest_ensure_response( $rows ?: array() );
	}

	/**
	 * Create a new ad.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_ad( WP_REST_Request $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'remindmii_merchant_ads';
		$now   = current_time( 'mysql' );

		$row = $this->prepare_ad_row( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$row['merchant_id'] = $this->get_merchant_id();
		$row['impressions'] = 0;
		$row['clicks']      = 0;
		$row['created_at']  = $now;
		$row['updated_at']  = $now;

		$wpdb->insert( $table, $row );
		$id     = (int) $wpdb->insert_id;
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return rest_ensure_response( $result );
	}

	/**
	 * Update an existing ad.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_ad( WP_REST_Request $request ) {
		global $wpdb;
		$table       = $wpdb->prefix . 'remindmii_merchant_ads';
		$id          = absint( $request->get_param( 'id' ) );
		$merchant_id = $this->get_merchant_id();

		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d AND merchant_id = %d", $id, $merchant_id )
		);
		if ( ! $exists ) {
			return new WP_Error( 'not_found', __( 'Ad not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$row = $this->prepare_ad_row( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$row['updated_at'] = current_time( 'mysql' );

		$wpdb->update( $table, $row, array( 'id' => $id, 'merchant_id' => $merchant_id ) );
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return rest_ensure_response( $result );
	}

	/**
	 * Delete an ad.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_ad( WP_REST_Request $request ) {
		global $wpdb;
		$table       = $wpdb->prefix . 'remindmii_merchant_ads';
		$id          = absint( $request->get_param( 'id' ) );
		$merchant_id = $this->get_merchant_id();

		$deleted = $wpdb->delete( $table, array( 'id' => $id, 'merchant_id' => $merchant_id ), array( '%d', '%d' ) );
		if ( ! $deleted ) {
			return new WP_Error( 'not_found', __( 'Ad not found.', 'remindmii' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Toggle an ad's active status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function toggle_ad( WP_REST_Request $request ) {
		global $wpdb;
		$table       = $wpdb->prefix . 'remindmii_merchant_ads';
		$id          = absint( $request->get_param( 'id' ) );
		$merchant_id = $this->get_merchant_id();

		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d AND merchant_id = %d", $id, $merchant_id )
		);
		if ( null === $current ) {
			return new WP_Error( 'not_found', __( 'Ad not found.', 'remindmii' ), array( 'status' => 404 ) );
		}

		$new_status = $current ? 0 : 1;
		$wpdb->update(
			$table,
			array( 'is_active' => $new_status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id, 'merchant_id' => $merchant_id )
		);
		return rest_ensure_response( array( 'is_active' => (bool) $new_status ) );
	}

	/**
	 * Sanitize and build an ad data row from the request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array<string,mixed>|WP_Error
	 */
	private function prepare_ad_row( WP_REST_Request $request ) {
		$title = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'invalid_param', __( 'Title is required.', 'remindmii' ), array( 'status' => 400 ) );
		}

		$target_gender     = array_map( 'sanitize_text_field', (array) ( $request->get_param( 'target_gender' ) ?? array( 'all' ) ) );
		$target_categories = array_map( 'sanitize_text_field', (array) ( $request->get_param( 'target_categories' ) ?? array( 'all' ) ) );

		return array(
			'title'             => $title,
			'description'       => sanitize_textarea_field( (string) ( $request->get_param( 'description' ) ?? '' ) ),
			'image_url'         => esc_url_raw( (string) ( $request->get_param( 'image_url' ) ?? '' ) ),
			'background_color'  => sanitize_hex_color( (string) ( $request->get_param( 'background_color' ) ?? '#3B82F6' ) ) ?: '#3B82F6',
			'text_color'        => sanitize_hex_color( (string) ( $request->get_param( 'text_color' ) ?? '#FFFFFF' ) ) ?: '#FFFFFF',
			'target_gender'     => wp_json_encode( $target_gender ),
			'target_age_min'    => max( 0, min( 120, absint( $request->get_param( 'target_age_min' ) ?? 0 ) ) ),
			'target_age_max'    => max( 0, min( 120, absint( $request->get_param( 'target_age_max' ) ?? 120 ) ) ),
			'target_categories' => wp_json_encode( $target_categories ),
			'cta_text'          => sanitize_text_field( (string) ( $request->get_param( 'cta_text' ) ?? 'Se tilbud' ) ),
			'cta_url'           => esc_url_raw( (string) ( $request->get_param( 'cta_url' ) ?? '' ) ),
			'is_active'         => (int) (bool) $request->get_param( 'is_active' ),
			'start_date'        => $this->sanitize_date( $request->get_param( 'start_date' ) ),
			'end_date'          => $this->sanitize_date( $request->get_param( 'end_date' ) ),
		);
	}

	/**
	 * Sanitize a Y-m-d date value.
	 *
	 * @param mixed $val Value from request.
	 * @return string|null
	 */
	private function sanitize_date( $val ) {
		if ( empty( $val ) ) {
			return null;
		}
		$d = DateTime::createFromFormat( 'Y-m-d', sanitize_text_field( (string) $val ) );
		return $d ? $d->format( 'Y-m-d' ) : null;
	}
}
