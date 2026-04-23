<?php
/**
 * Gamification service — awards points and badges, records stats.
 *
 * @package Remindmii
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Remindmii_Gamification
 */
class Remindmii_Gamification {

	// Point values.
	const POINTS_CREATE   = 5;
	const POINTS_COMPLETE = 10;
	const POINTS_STREAK   = 20;

	// Achievement keys.
	const ACH_FIRST_REMINDER  = 'first_reminder';
	const ACH_STREAK_7        = 'streak_7';
	const ACH_STREAK_30       = 'streak_30';
	const ACH_COMPLETE_10     = 'complete_10';
	const ACH_COMPLETE_50     = 'complete_50';
	const ACH_COMPLETE_100    = 'complete_100';

	/**
	 * All defined achievements.
	 *
	 * @return array
	 */
	public static function definitions() {
		return array(
			self::ACH_FIRST_REMINDER  => array(
				'key'         => self::ACH_FIRST_REMINDER,
				'name'        => __( 'First step', 'remindmii' ),
				'description' => __( 'Created your first reminder.', 'remindmii' ),
				'icon'        => '🎉',
				'points'      => 10,
			),
			self::ACH_STREAK_7  => array(
				'key'         => self::ACH_STREAK_7,
				'name'        => __( '7-day streak', 'remindmii' ),
				'description' => __( 'Used Remindmii 7 days in a row.', 'remindmii' ),
				'icon'        => '🔥',
				'points'      => 25,
			),
			self::ACH_STREAK_30 => array(
				'key'         => self::ACH_STREAK_30,
				'name'        => __( '30-day streak', 'remindmii' ),
				'description' => __( 'Used Remindmii 30 days in a row.', 'remindmii' ),
				'icon'        => '🏆',
				'points'      => 100,
			),
			self::ACH_COMPLETE_10  => array(
				'key'         => self::ACH_COMPLETE_10,
				'name'        => __( 'Getting things done', 'remindmii' ),
				'description' => __( 'Completed 10 reminders.', 'remindmii' ),
				'icon'        => '✅',
				'points'      => 20,
			),
			self::ACH_COMPLETE_50  => array(
				'key'         => self::ACH_COMPLETE_50,
				'name'        => __( 'On a roll', 'remindmii' ),
				'description' => __( 'Completed 50 reminders.', 'remindmii' ),
				'icon'        => '💪',
				'points'      => 50,
			),
			self::ACH_COMPLETE_100 => array(
				'key'         => self::ACH_COMPLETE_100,
				'name'        => __( 'Legend', 'remindmii' ),
				'description' => __( 'Completed 100 reminders.', 'remindmii' ),
				'icon'        => '🌟',
				'points'      => 100,
			),
		);
	}

	/**
	 * Called when a reminder is created.
	 *
	 * @param int $user_id User ID.
	 */
	public static function on_reminder_created( $user_id ) {
		global $wpdb;

		$stats_table = $wpdb->prefix . 'remindmii_user_stats';

		// Upsert stats row.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$stats_table} (user_id, total_reminders_created, total_completed, current_streak, longest_streak, total_points, last_activity_date, created_at, updated_at)
				 VALUES (%d, 1, 0, 1, 1, %d, CURDATE(), NOW(), NOW())
				 ON DUPLICATE KEY UPDATE
				   total_reminders_created = total_reminders_created + 1,
				   total_points = total_points + %d,
				   current_streak = IF(last_activity_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) OR last_activity_date = CURDATE(), current_streak + IF(last_activity_date = CURDATE(), 0, 1), 1),
				   longest_streak = GREATEST(longest_streak, IF(last_activity_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) OR last_activity_date = CURDATE(), current_streak + IF(last_activity_date = CURDATE(), 0, 1), 1)),
				   last_activity_date = CURDATE(),
				   updated_at = NOW()",
				(int) $user_id,
				self::POINTS_CREATE,
				self::POINTS_CREATE
			)
		);

		// Check first reminder achievement.
		$stats = self::get_stats( $user_id );
		if ( $stats && (int) $stats['total_reminders_created'] === 1 ) {
			self::award_achievement( $user_id, self::ACH_FIRST_REMINDER );
		}

		// Streak achievements.
		self::check_streak_achievements( $user_id, $stats );
	}

	/**
	 * Called when a reminder is completed.
	 *
	 * @param int $user_id User ID.
	 */
	public static function on_reminder_completed( $user_id ) {
		global $wpdb;

		$stats_table = $wpdb->prefix . 'remindmii_user_stats';

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$stats_table} (user_id, total_reminders_created, total_completed, current_streak, longest_streak, total_points, last_activity_date, created_at, updated_at)
				 VALUES (%d, 0, 1, 1, 1, %d, CURDATE(), NOW(), NOW())
				 ON DUPLICATE KEY UPDATE
				   total_completed  = total_completed + 1,
				   total_points     = total_points + %d,
				   last_activity_date = CURDATE(),
				   updated_at = NOW()",
				(int) $user_id,
				self::POINTS_COMPLETE,
				self::POINTS_COMPLETE
			)
		);

		$stats = self::get_stats( $user_id );

		// Completion milestone achievements.
		if ( $stats ) {
			$completed = (int) $stats['total_completed'];
			foreach ( array( 10 => self::ACH_COMPLETE_10, 50 => self::ACH_COMPLETE_50, 100 => self::ACH_COMPLETE_100 ) as $milestone => $key ) {
				if ( $completed >= $milestone ) {
					self::award_achievement( $user_id, $key );
				}
			}
		}
	}

	/**
	 * Get user stats.
	 *
	 * @param int $user_id User ID.
	 * @return array|null
	 */
	public static function get_stats( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'remindmii_user_stats';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", (int) $user_id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Get earned achievements for user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_achievements( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'remindmii_user_achievements';
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY earned_at DESC", (int) $user_id ),
			ARRAY_A
		);

		$definitions = self::definitions();
		$earned      = array();

		foreach ( ( is_array( $rows ) ? $rows : array() ) as $row ) {
			$key  = $row['achievement_key'];
			$defn = isset( $definitions[ $key ] ) ? $definitions[ $key ] : array();
			$earned[] = array_merge( $defn, array( 'earned_at' => $row['earned_at'] ) );
		}

		return $earned;
	}

	/**
	 * Award an achievement (idempotent).
	 *
	 * @param int    $user_id         User ID.
	 * @param string $achievement_key Achievement key.
	 */
	private static function award_achievement( $user_id, $achievement_key ) {
		global $wpdb;

		$table = $wpdb->prefix . 'remindmii_user_achievements';

		// Skip if already earned.
		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d AND achievement_key = %s", (int) $user_id, $achievement_key )
		);

		if ( $exists ) {
			return;
		}

		$definitions = self::definitions();
		$points      = isset( $definitions[ $achievement_key ]['points'] ) ? (int) $definitions[ $achievement_key ]['points'] : 0;

		$wpdb->insert(
			$table,
			array(
				'user_id'         => (int) $user_id,
				'achievement_key' => $achievement_key,
				'points'          => $points,
				'earned_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s' )
		);

		// Add achievement points to stats.
		if ( $points > 0 ) {
			$stats_table = $wpdb->prefix . 'remindmii_user_stats';
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$stats_table} SET total_points = total_points + %d WHERE user_id = %d",
					$points,
					(int) $user_id
				)
			);
		}
	}

	/**
	 * Check and award streak achievements.
	 *
	 * @param int        $user_id User ID.
	 * @param array|null $stats   Current stats row.
	 */
	private static function check_streak_achievements( $user_id, $stats ) {
		if ( ! $stats ) { return; }
		$streak = (int) $stats['current_streak'];
		if ( $streak >= 7 )  { self::award_achievement( $user_id, self::ACH_STREAK_7 ); }
		if ( $streak >= 30 ) { self::award_achievement( $user_id, self::ACH_STREAK_30 ); }
	}
}
