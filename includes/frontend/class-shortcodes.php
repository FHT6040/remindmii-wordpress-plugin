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
				'notificationsUrl' => esc_url_raw( rest_url( 'remindmii/v1/notifications' ) ),
				'profileUrl'   => esc_url_raw( rest_url( 'remindmii/v1/profile' ) ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'isLoggedIn'   => is_user_logged_in(),
				'loginUrl'     => esc_url_raw( wp_login_url( get_permalink() ?: home_url( '/' ) ) ),
				'i18n'         => array(
					'loading'          => __( 'Loading reminders...', 'remindmii' ),
					'loadingProfile'   => __( 'Loading profile...', 'remindmii' ),
					'notLoggedIn'      => __( 'You need to be logged in to use Remindmii.', 'remindmii' ),
					'loginCta'         => __( 'Log in', 'remindmii' ),
					'empty'            => __( 'No reminders yet.', 'remindmii' ),
					'create'           => __( 'Create reminder', 'remindmii' ),
					'update'           => __( 'Update reminder', 'remindmii' ),
					'createCategory'   => __( 'Add category', 'remindmii' ),
					'creating'         => __( 'Saving...', 'remindmii' ),
					'updating'         => __( 'Updating...', 'remindmii' ),
					'creatingCategory' => __( 'Adding...', 'remindmii' ),
					'edit'             => __( 'Edit', 'remindmii' ),
					'cancelEdit'       => __( 'Cancel edit', 'remindmii' ),
					'editingStatus'    => __( 'Editing reminder.', 'remindmii' ),
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
					'profileHeading'   => __( 'Profile settings', 'remindmii' ),
					'fullName'         => __( 'Full name', 'remindmii' ),
					'email'            => __( 'Email', 'remindmii' ),
					'phone'            => __( 'Phone', 'remindmii' ),
					'birthDate'        => __( 'Birth date', 'remindmii' ),
					'gender'           => __( 'Gender', 'remindmii' ),
					'pronouns'         => __( 'Pronouns', 'remindmii' ),
					'emailNotifications' => __( 'Enable email notifications', 'remindmii' ),
					'notificationHours'  => __( 'Notify me this many hours before', 'remindmii' ),
					'saveProfile'      => __( 'Save profile', 'remindmii' ),
					'savingProfile'    => __( 'Saving profile...', 'remindmii' ),
					'profileSaved'     => __( 'Profile saved.', 'remindmii' ),
					'notificationHistory' => __( 'Notification history', 'remindmii' ),
					'loadingNotifications' => __( 'Loading notifications...', 'remindmii' ),
					'noNotifications'  => __( 'No notifications yet.', 'remindmii' ),
					'allStatuses'      => __( 'All statuses', 'remindmii' ),
					'refreshHistory'   => __( 'Refresh history', 'remindmii' ),
					'refreshingHistory' => __( 'Refreshing history...', 'remindmii' ),
					'loadMoreHistory'  => __( 'Load more', 'remindmii' ),
					'loadingMoreHistory' => __( 'Loading more...', 'remindmii' ),
					'statusLabel'      => __( 'Status', 'remindmii' ),
					'openReminder'     => __( 'Open reminder', 'remindmii' ),
					'reminderUnavailable' => __( 'The linked reminder is no longer available.', 'remindmii' ),
					'sentAtLabel'      => __( 'Sent', 'remindmii' ),
					'createdAtLabel'   => __( 'Logged', 'remindmii' ),
					'statusPreview'    => __( 'Preview', 'remindmii' ),
					'statusSent'       => __( 'Sent', 'remindmii' ),
					'statusFailed'     => __( 'Failed', 'remindmii' ),
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