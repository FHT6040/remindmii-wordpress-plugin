<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Security {
	/**
	 * Register security hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'plugin_action_links_' . plugin_basename( REMINDMII_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add settings link on plugins page.
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=remindmii' ) ) . '">' . esc_html__( 'Settings', 'remindmii' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Enforce a per-user rate limit using transients.
	 *
	 * Returns a WP_Error when the limit is exceeded, true otherwise.
	 *
	 * @param int $user_id      WordPress user ID.
	 * @param int $limit        Max requests allowed in the window.
	 * @param int $window       Window length in seconds.
	 * @return true|WP_Error
	 */
	public static function check_rate_limit( $user_id, $limit = 120, $window = 60 ) {
		$key   = 'remindmii_rl_' . absint( $user_id );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Too many requests. Please slow down.', 'remindmii' ),
				array( 'status' => 429 )
			);
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, $window );
		} else {
			set_transient( $key, $count + 1, $window );
		}

		return true;
	}
}