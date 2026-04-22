<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Installer {
	/**
	 * Install or update plugin schema.
	 *
	 * @return void
	 */
	public static function install() {
		Remindmii_DB_Schema::create_tables();
		update_option( 'remindmii_db_version', REMINDMII_VERSION );
	}
}