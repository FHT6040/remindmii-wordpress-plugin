<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Activator {
	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		Remindmii_Installer::install();

		if ( ! wp_next_scheduled( 'remindmii_process_notifications' ) ) {
			wp_schedule_event( time(), 'hourly', 'remindmii_process_notifications' );
		}
	}
}