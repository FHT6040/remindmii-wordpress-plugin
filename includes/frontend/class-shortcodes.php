<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Shortcodes {
	/**
	 * Register shortcode hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'remindmii_app', array( $this, 'render_app' ) );
	}

	/**
	 * Render frontend app shell.
	 *
	 * @return string
	 */
	public function render_app() {
		wp_enqueue_style( 'remindmii-frontend' );
		wp_enqueue_script( 'remindmii-frontend' );

		ob_start();
		require REMINDMII_PLUGIN_DIR . 'templates/frontend/app-shell.php';
		return (string) ob_get_clean();
	}
}