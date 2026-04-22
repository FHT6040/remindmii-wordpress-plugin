<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Cron {
	/**
	 * Register cron hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'remindmii_process_notifications', array( $this, 'process_notifications' ) );
	}

	/**
	 * Placeholder for future notification processing.
	 *
	 * @return void
	 */
	public function process_notifications() {
		return;
	}
}