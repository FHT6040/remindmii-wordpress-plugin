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
		add_shortcode( 'remindmii_public_wishlist', array( $this, 'render_public_wishlist' ) );
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
				'notificationsExportUrl' => esc_url_raw( rest_url( 'remindmii/v1/notifications/export' ) ),
				'profileUrl'   => esc_url_raw( rest_url( 'remindmii/v1/profile' ) ),
				'wishlistsUrl' => esc_url_raw( rest_url( 'remindmii/v1/wishlists' ) ),
				'templatesUrl'    => esc_url_raw( rest_url( 'remindmii/v1/templates' ) ),
				'preferencesUrl'  => esc_url_raw( rest_url( 'remindmii/v1/preferences' ) ),
				'sharedWithMeUrl' => esc_url_raw( rest_url( 'remindmii/v1/shared-with-me' ) ),
				'gamificationUrl' => esc_url_raw( rest_url( 'remindmii/v1/gamification' ) ),
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
					'dateRangeLabel'   => __( 'Date range', 'remindmii' ),
					'allDates'         => __( 'All dates', 'remindmii' ),
					'last7Days'        => __( 'Last 7 days', 'remindmii' ),
					'last30Days'       => __( 'Last 30 days', 'remindmii' ),
					'refreshHistory'   => __( 'Refresh history', 'remindmii' ),
					'refreshingHistory' => __( 'Refreshing history...', 'remindmii' ),
					'exportHistory'    => __( 'Export CSV', 'remindmii' ),
					'exportingHistory' => __( 'Exporting...', 'remindmii' ),
					'noNotificationsToExport' => __( 'No notifications to export.', 'remindmii' ),
					'loadMoreHistory'  => __( 'Load more', 'remindmii' ),
					'loadingMoreHistory' => __( 'Loading more...', 'remindmii' ),
					'historyCount'      => __( 'Showing %1$d of %2$d', 'remindmii' ),
					'historyCountAll'   => __( 'Showing all %1$d', 'remindmii' ),
					'statusLabel'      => __( 'Status', 'remindmii' ),
					'openReminder'     => __( 'Open reminder', 'remindmii' ),
					'reminderUnavailable' => __( 'The linked reminder is no longer available.', 'remindmii' ),
					'sentAtLabel'      => __( 'Sent', 'remindmii' ),
					'createdAtLabel'   => __( 'Logged', 'remindmii' ),
					'statusPreview'    => __( 'Preview', 'remindmii' ),
					'statusSent'       => __( 'Sent', 'remindmii' ),
					'statusFailed'     => __( 'Failed', 'remindmii' ),
					// Wishlists.
					'wishlistsHeading'       => __( 'Wishlists', 'remindmii' ),
					'newWishlist'            => __( 'New wishlist', 'remindmii' ),
					'wishlistTitle'          => __( 'Wishlist title', 'remindmii' ),
					'wishlistDescription'    => __( 'Description (optional)', 'remindmii' ),
					'wishlistPublic'         => __( 'Make public (share link)', 'remindmii' ),
					'createWishlist'         => __( 'Create wishlist', 'remindmii' ),
					'creatingWishlist'       => __( 'Creating...', 'remindmii' ),
					'updateWishlist'         => __( 'Save wishlist', 'remindmii' ),
					'updatingWishlist'       => __( 'Saving...', 'remindmii' ),
					'confirmDeleteWishlist'  => __( 'Delete this wishlist?', 'remindmii' ),
					'noWishlists'            => __( 'No wishlists yet.', 'remindmii' ),
					'loadingWishlists'       => __( 'Loading wishlists...', 'remindmii' ),
					'copyLink'               => __( 'Copy link', 'remindmii' ),
					'linkCopied'             => __( 'Link copied!', 'remindmii' ),
					'back'                   => __( '&#8592; Back', 'remindmii' ),
					'itemsHeading'           => __( 'Items', 'remindmii' ),
					'addItem'                => __( 'Add item', 'remindmii' ),
					'itemTitle'              => __( 'Item title', 'remindmii' ),
					'itemDescription'        => __( 'Description (optional)', 'remindmii' ),
					'itemUrl'                => __( 'URL (optional)', 'remindmii' ),
					'itemPrice'              => __( 'Price (optional)', 'remindmii' ),
					'itemCurrency'           => __( 'Currency', 'remindmii' ),
					'itemPurchased'          => __( 'Purchased', 'remindmii' ),
					'saveItem'               => __( 'Save item', 'remindmii' ),
					'savingItem'             => __( 'Saving...', 'remindmii' ),
					'confirmDeleteItem'      => __( 'Delete this item?', 'remindmii' ),
					'noItems'                => __( 'No items yet.', 'remindmii' ),
					'loadingItems'           => __( 'Loading items...', 'remindmii' ),
					'togglePurchased'        => __( 'Toggle purchased', 'remindmii' ),
					'publicBadge'            => __( 'Public', 'remindmii' ),
					'privateBadge'           => __( 'Private', 'remindmii' ),
					// Templates.
					'useTemplate'            => __( 'Use template', 'remindmii' ),
					'templatesHeading'       => __( 'Choose a template', 'remindmii' ),
					'templatesAll'           => __( 'All', 'remindmii' ),
					'templateCategories'     => array(
						'birthday'     => __( 'Birthdays', 'remindmii' ),
						'anniversary'  => __( 'Anniversaries', 'remindmii' ),
						'subscription' => __( 'Subscriptions', 'remindmii' ),
						'gift_card'    => __( 'Gift cards', 'remindmii' ),
						'voucher'      => __( 'Vouchers', 'remindmii' ),
						'finance'      => __( 'Finance', 'remindmii' ),
						'event'        => __( 'Events', 'remindmii' ),
						'health'       => __( 'Health', 'remindmii' ),
					),
					'loadingTemplates'       => __( 'Loading templates...', 'remindmii' ),
					'closeTemplates'         => __( 'Close', 'remindmii' ),
					// Preferences.
					'preferencesHeading'        => __( 'Preferences', 'remindmii' ),
					'appearanceHeading'         => __( 'Appearance', 'remindmii' ),
					'themeLabel'                => __( 'Theme', 'remindmii' ),
					'themeDefault'              => __( 'Default', 'remindmii' ),
					'themeLight'                => __( 'Light', 'remindmii' ),
					'themeDark'                 => __( 'Dark', 'remindmii' ),
					'themeRomantic'             => __( 'Romantic', 'remindmii' ),
					'featuresHeading'           => __( 'Features', 'remindmii' ),
					'locationReminders'         => __( 'Location reminders', 'remindmii' ),
					'locationRemindersDesc'     => __( 'Get notified when near relevant locations.', 'remindmii' ),
					'gamification'              => __( 'Gamification', 'remindmii' ),
					'gamificationDesc'          => __( 'Track streaks, earn badges and see your progress.', 'remindmii' ),
					'distractedMode'            => __( 'Focus / distracted mode', 'remindmii' ),
					'distractedModeDesc'        => __( 'Simplify the interface to reduce distractions.', 'remindmii' ),
					'savePreferences'           => __( 'Save preferences', 'remindmii' ),
					'savingPreferences'         => __( 'Saving...', 'remindmii' ),
					'preferencesSaved'          => __( 'Preferences saved.', 'remindmii' ),
					'loadingPreferences'        => __( 'Loading preferences...', 'remindmii' ),
					// Shared lists.
					'sharedListsHeading'        => __( 'Shared with me', 'remindmii' ),
					'sharingHeading'            => __( 'Share this wishlist', 'remindmii' ),
					'shareEmailPlaceholder'     => __( 'Email address', 'remindmii' ),
					'sharePermissionView'       => __( 'View', 'remindmii' ),
					'sharePermissionEdit'       => __( 'Edit', 'remindmii' ),
					'shareBtn'                  => __( 'Share', 'remindmii' ),
					'sharingBtn'                => __( 'Sharing...', 'remindmii' ),
					'revokeShare'               => __( 'Revoke', 'remindmii' ),
					'noSharedLists'             => __( 'No lists shared with you yet.', 'remindmii' ),
					'loadingSharedLists'        => __( 'Loading...', 'remindmii' ),
					// Gamification.
					'gamificationHeading'       => __( 'Your Progress', 'remindmii' ),
					'points'                    => __( 'Points', 'remindmii' ),
					'dayStreak'                 => __( 'Day streak', 'remindmii' ),
					'loadingGamification'       => __( 'Loading...', 'remindmii' ),
					// Location reminders.
					'nearLocation'              => __( 'You are near', 'remindmii' ),
					'geolocationUnsupported'    => __( 'Geolocation is not supported by this browser.', 'remindmii' ),
					'geolocationError'          => __( 'Could not determine your location.', 'remindmii' ),
					// Voice input.
					'voiceStart'                => __( 'Start', 'remindmii' ),
					'voiceStop'                 => __( 'Stop', 'remindmii' ),
					'voiceError'                => __( 'Voice recognition error. Please try again.', 'remindmii' ),
					'voicePermissionDenied'     => __( 'Microphone access denied. Please allow microphone access and try again.', 'remindmii' ),
				),
			)
		);

		wp_enqueue_style( 'remindmii-frontend' );
		wp_enqueue_script( 'remindmii-frontend' );

		ob_start();
		require REMINDMII_PLUGIN_DIR . 'templates/frontend/app-shell.php';
		return (string) ob_get_clean();
	}

	/**
	 * Render public wishlist view.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_public_wishlist( $atts ) {
		$atts  = shortcode_atts( array( 'token' => '' ), $atts, 'remindmii_public_wishlist' );
		$token = sanitize_text_field( $atts['token'] );

		// Fall back to query string (?remindmii_wishlist=...).
		if ( '' === $token && isset( $_GET['remindmii_wishlist'] ) ) {
			$token = sanitize_text_field( (string) wp_unslash( $_GET['remindmii_wishlist'] ) );
		}

		wp_localize_script(
			'remindmii-frontend',
			'remindmiiPublicWishlist',
			array(
				'token'      => $token,
				'apiUrl'     => esc_url_raw( rest_url( 'remindmii/v1/public/wishlists/' . $token ) ),
				'i18n'       => array(
					'loading'        => __( 'Loading wishlist...', 'remindmii' ),
					'notFound'       => __( 'Wishlist not found or not public.', 'remindmii' ),
					'noItems'        => __( 'No items on this wishlist yet.', 'remindmii' ),
					'purchased'      => __( 'Purchased', 'remindmii' ),
					'priceLabel'     => __( 'Price', 'remindmii' ),
					'viewLink'       => __( 'View', 'remindmii' ),
				),
			)
		);

		wp_enqueue_style( 'remindmii-frontend' );
		wp_enqueue_script( 'remindmii-frontend' );

		ob_start();
		require REMINDMII_PLUGIN_DIR . 'templates/frontend/public-wishlist.php';
		return (string) ob_get_clean();
	}
}