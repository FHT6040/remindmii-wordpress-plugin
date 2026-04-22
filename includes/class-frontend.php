<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Frontend {
	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'remindmii-frontend',
			REMINDMII_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			REMINDMII_VERSION
		);

		wp_register_script(
			'remindmii-frontend',
			REMINDMII_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			REMINDMII_VERSION,
			true
		);
	}
}