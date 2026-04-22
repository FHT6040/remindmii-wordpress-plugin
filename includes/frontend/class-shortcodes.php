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
		wp_localize_script(
			'remindmii-frontend',
			'remindmiiFrontend',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'remindmii/v1/reminders' ) ),
				'categoriesUrl'=> esc_url_raw( rest_url( 'remindmii/v1/categories' ) ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'isLoggedIn'   => is_user_logged_in(),
				'loginUrl'     => esc_url_raw( wp_login_url( get_permalink() ?: home_url( '/' ) ) ),
				'i18n'         => array(
					'loading'          => __( 'Loading reminders...', 'remindmii' ),
					'notLoggedIn'      => __( 'You need to be logged in to use Remindmii.', 'remindmii' ),
					'loginCta'         => __( 'Log in', 'remindmii' ),
					'empty'            => __( 'No reminders yet.', 'remindmii' ),
					'create'           => __( 'Create reminder', 'remindmii' ),
					'createCategory'   => __( 'Add category', 'remindmii' ),
					'creating'         => __( 'Saving...', 'remindmii' ),
					'creatingCategory' => __( 'Adding...', 'remindmii' ),
					'titleRequired'    => __( 'Title is required.', 'remindmii' ),
					'categoryRequired' => __( 'Category name is required.', 'remindmii' ),
					'genericError'     => __( 'Something went wrong. Please try again.', 'remindmii' ),
					'markComplete'     => __( 'Mark complete', 'remindmii' ),
					'markActive'       => __( 'Mark active', 'remindmii' ),
					'delete'           => __( 'Delete', 'remindmii' ),
					'confirmDelete'    => __( 'Delete this reminder?', 'remindmii' ),
					'confirmDeleteCategory' => __( 'Delete this category?', 'remindmii' ),
					'untitled'         => __( 'Untitled reminder', 'remindmii' ),
					'dueLabel'         => __( 'Due', 'remindmii' ),
					'categoryLabel'    => __( 'Category', 'remindmii' ),
					'noCategory'       => __( 'No category', 'remindmii' ),
					'newCategoryLabel' => __( 'New category', 'remindmii' ),
					'descriptionLabel' => __( 'Description', 'remindmii' ),
				),
			)
		);

		wp_enqueue_style( 'remindmii-frontend' );
		wp_enqueue_script( 'remindmii-frontend' );

		ob_start();
		require REMINDMII_PLUGIN_DIR . 'templates/frontend/app-shell.php';
		return (string) ob_get_clean();
	}
}