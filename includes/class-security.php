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
}