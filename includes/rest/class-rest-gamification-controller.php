<?php
/**
 * REST controller for gamification data.
 *
 * @package Remindmii
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Remindmii_REST_Gamification_Controller
 */
class Remindmii_REST_Gamification_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'remindmii/v1';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/gamification',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_data' ),
				'permission_callback' => function () { return is_user_logged_in(); },
			)
		);
	}

	/**
	 * Return stats + achievements for current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_data( $request ) {
		$user_id      = get_current_user_id();
		$stats        = Remindmii_Gamification::get_stats( $user_id );
		$achievements = Remindmii_Gamification::get_achievements( $user_id );
		$definitions  = array_values( Remindmii_Gamification::definitions() );

		// Merge earned flag into definitions list.
		$earned_keys = array_map( function ( $a ) { return $a['key']; }, $achievements );
		foreach ( $definitions as &$defn ) {
			$defn['earned'] = in_array( $defn['key'], $earned_keys, true );
		}
		unset( $defn );

		return rest_ensure_response(
			array(
				'stats'        => $stats ?: array(
					'total_reminders_created' => 0,
					'total_completed'         => 0,
					'current_streak'          => 0,
					'longest_streak'          => 0,
					'total_points'            => 0,
				),
				'achievements' => $achievements,
				'all_badges'   => $definitions,
			)
		);
	}
}
