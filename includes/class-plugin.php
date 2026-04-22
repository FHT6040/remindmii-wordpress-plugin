<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Plugin {
	/**
	 * Boot plugin components.
	 *
	 * @return void
	 */
	public function run() {
		load_plugin_textdomain( 'remindmii', false, dirname( plugin_basename( REMINDMII_PLUGIN_FILE ) ) . '/languages' );

		$admin      = new Remindmii_Admin();
		$frontend   = new Remindmii_Frontend();
		$rest       = new Remindmii_REST();
		$cron       = new Remindmii_Cron();
		$security   = new Remindmii_Security();
		$shortcodes = new Remindmii_Shortcodes();

		$admin->register_hooks();
		$frontend->register_hooks();
		$rest->register_hooks();
		$cron->register_hooks();
		$security->register_hooks();
		$shortcodes->register_hooks();

		add_action( 'user_register', array( $this, 'bootstrap_user_records' ) );
	}

	/**
	 * Create default plugin records for a new WordPress user.
	 *
	 * @param int $user_id Newly created WordPress user ID.
	 * @return void
	 */
	public function bootstrap_user_records( $user_id ) {
		Remindmii_Installer::ensure_user_records( $user_id );
	}
}