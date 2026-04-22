<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Deactivator {
	/**
	 * Clear scheduled plugin events.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'remindmii_process_notifications' );

		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'remindmii_process_notifications' );
		}
	}
}