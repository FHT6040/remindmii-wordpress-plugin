<?php
/**
 * Plugin Name: Remindmii
 * Plugin URI: https://github.com/FHT6040/remindmii-wordpress-plugin
 * Description: WordPress-native foundation for the Remindmii reminder platform.
 * Version: 0.1.0
 * Author: FHT6040
 * Text Domain: remindmii
 * Domain Path: /languages
 * Requires at least: 6.9
 * Requires PHP: 8.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REMINDMII_VERSION', '0.1.0' );
define( 'REMINDMII_PLUGIN_FILE', __FILE__ );
define( 'REMINDMII_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REMINDMII_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once REMINDMII_PLUGIN_DIR . 'includes/class-activator.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/class-installer.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/class-admin.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/class-frontend.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/class-rest.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/class-cron.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/class-security.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/repositories/class-categories-repository.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/repositories/class-user-profiles-repository.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/repositories/class-reminders-repository.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/rest/class-rest-categories-controller.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/rest/class-rest-profile-controller.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/rest/class-rest-reminders-controller.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/frontend/class-shortcodes.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/db/class-schema.php';
require_once REMINDMII_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Remindmii_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Remindmii_Deactivator', 'deactivate' ) );

function remindmii_run_plugin() {
	$plugin = new Remindmii_Plugin();
	$plugin->run();
}

remindmii_run_plugin();