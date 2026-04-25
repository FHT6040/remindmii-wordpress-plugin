<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_REST_Ads_Controller {

	/**
	 * Register REST routes for targeted ads.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'remindmii/v1',
			'/ads',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_ads' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'remindmii/v1',
			'/ads/(?P<id>\d+)/click',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track_click' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}

	/**
	 * Return up to 3 targeted ads for the current user.
	 *
	 * @return WP_REST_Response
	 */
	public function get_ads( WP_REST_Request $request ) {
		global $wpdb;

		$user_id     = get_current_user_id();
		$today       = current_time( 'Y-m-d' );
		$ads_table   = $wpdb->prefix . 'remindmii_merchant_ads';
		$merch_table = $wpdb->prefix . 'remindmii_merchants';
		$prof_table  = $wpdb->prefix . 'remindmii_user_profiles';

		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT birth_date, gender FROM {$prof_table} WHERE user_id = %d",
				$user_id
			)
		);

		$age    = null;
		$gender = '';
		if ( $profile ) {
			if ( ! empty( $profile->birth_date ) ) {
				$dob = new DateTime( $profile->birth_date );
				$age = (int) $dob->diff( new DateTime() )->y;
			}
			$gender = sanitize_text_field( $profile->gender ?? '' );
		}

		$sql  = "SELECT a.*, m.name AS merchant_name, m.logo_url AS merchant_logo_url
			FROM {$ads_table} a
			JOIN {$merch_table} m ON m.id = a.merchant_id AND m.is_active = 1
			WHERE a.is_active = 1
			AND ( a.start_date IS NULL OR a.start_date <= %s )
			AND ( a.end_date IS NULL OR a.end_date >= %s )";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $today, $today ), ARRAY_A );
		if ( ! empty( $rows ) ) {
			shuffle( $rows );
		}

		if ( empty( $rows ) ) {
			return rest_ensure_response( array() );
		}

		$filtered = array_filter(
			$rows,
			function ( $ad ) use ( $age, $gender ) {
				if ( null !== $age ) {
					if ( $age < (int) $ad['target_age_min'] || $age > (int) $ad['target_age_max'] ) {
						return false;
					}
				}
				if ( '' !== $gender && ! empty( $ad['target_gender'] ) ) {
					$genders = json_decode( $ad['target_gender'], true );
					$genders = is_array( $genders ) ? $genders : array( 'all' );
					if ( ! in_array( 'all', $genders, true ) && ! in_array( $gender, $genders, true ) ) {
						return false;
					}
				}
				if ( null !== $ad['impression_cap'] && (int) $ad['impressions'] >= (int) $ad['impression_cap'] ) {
					return false;
				}
				return true;
			}
		);

		$output = array();
		foreach ( array_slice( array_values( $filtered ), 0, 3 ) as $ad ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$ads_table} SET impressions = impressions + 1 WHERE id = %d",
					(int) $ad['id']
				)
			);
			$ad['merchant'] = array(
				'name'     => $ad['merchant_name'],
				'logo_url' => $ad['merchant_logo_url'],
			);
			unset( $ad['merchant_name'], $ad['merchant_logo_url'] );
			$output[] = $ad;
		}

		return rest_ensure_response( $output );
	}

	/**
	 * Increment click counter for an ad.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function track_click( WP_REST_Request $request ) {
		global $wpdb;
		$id    = absint( $request->get_param( 'id' ) );
		$table = $wpdb->prefix . 'remindmii_merchant_ads';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET clicks = clicks + 1 WHERE id = %d AND is_active = 1",
				$id
			)
		);
		return rest_ensure_response( array( 'success' => true ) );
	}
}
