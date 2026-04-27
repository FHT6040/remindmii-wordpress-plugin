<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Installer {
	/**
	 * Return default plugin settings.
	 *
	 * @return array<string, int>
	 */
	public static function get_default_settings() {
		return array(
			'default_email_notifications' => 1,
			'default_notification_hours'  => 24,
		);
	}

	/**
	 * Install or update plugin schema.
	 *
	 * @return void
	 */
	public static function install() {
		Remindmii_DB_Schema::create_tables();
		self::ensure_default_settings();
		self::backfill_existing_users();
		self::ensure_pages();
		update_option( 'remindmii_db_version', REMINDMII_VERSION );
	}

	/**
	 * Return the required page definitions.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function get_required_pages() {
		return array(
			array(
				'title'   => __( 'Remindmii', 'remindmii' ),
				'slug'    => 'remindmii',
				'content' => '[remindmii_app]',
			),
			array(
				'title'   => __( 'Login', 'remindmii' ),
				'slug'    => 'remindmii-login',
				'content' => '[remindmii_login]',
			),
			array(
				'title'   => __( 'Wishlist', 'remindmii' ),
				'slug'    => 'wishlist',
				'content' => '[remindmii_public_wishlist]',
			),
			array(
				'title'   => __( 'Merchant Portal', 'remindmii' ),
				'slug'    => 'merchant-portal',
				'content' => '[remindmii_merchant]',
			),
			array(
				'title'   => __( 'Privacy Policy', 'remindmii' ),
				'slug'    => 'privacy',
				'content' => '',
			),
			array(
				'title'   => __( 'Terms &amp; Conditions', 'remindmii' ),
				'slug'    => 'terms',
				'content' => '',
			),
		);
	}

	/**
	 * Create required frontend pages if they do not already exist.
	 *
	 * @return void
	 */
	private static function ensure_pages() {
		$author_id = get_current_user_id();
		if ( $author_id <= 0 ) {
			$author_id = 1;
		}

		foreach ( self::get_required_pages() as $page_data ) {
			$existing = get_page_by_path( $page_data['slug'], OBJECT, 'page' );
			if ( $existing instanceof WP_Post ) {
				continue;
			}

			wp_insert_post(
				array(
					'post_title'   => wp_strip_all_tags( $page_data['title'] ),
					'post_name'    => $page_data['slug'],
					'post_content' => $page_data['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_author'  => $author_id,
				)
			);
		}
	}

	/**
	 * Check page health and optionally repair: fix wrong slugs, missing shortcodes, and create missing pages.
	 *
	 * @return array<int, array<string, mixed>> Status of each required page after repair.
	 */
	public static function repair_pages() {
		$author_id = get_current_user_id();
		if ( $author_id <= 0 ) {
			$author_id = 1;
		}

		$report = array();

		foreach ( self::get_required_pages() as $page_data ) {
			$slug    = $page_data['slug'];
			$title   = wp_strip_all_tags( $page_data['title'] );
			$content = $page_data['content'];

			$existing = get_page_by_path( $slug, OBJECT, 'page' );

			if ( $existing instanceof WP_Post ) {
				$needs_content_fix = $content !== '' && false === strpos( $existing->post_content, $content );

				if ( $needs_content_fix ) {
					wp_update_post( array( 'ID' => $existing->ID, 'post_content' => $content ) );
					$report[] = array( 'slug' => $slug, 'action' => 'content_fixed', 'id' => $existing->ID );
				} else {
					$report[] = array( 'slug' => $slug, 'action' => 'ok', 'id' => $existing->ID );
				}
				continue;
			}

			// Page not found by slug — search by title as a fallback.
			$by_title = get_posts( array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'draft' ),
				'title'                  => $title,
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			if ( ! empty( $by_title ) ) {
				$post = $by_title[0];
				$update = array( 'ID' => $post->ID, 'post_name' => $slug, 'post_status' => 'publish' );
				if ( $content !== '' && false === strpos( $post->post_content, $content ) ) {
					$update['post_content'] = $content;
				}
				wp_update_post( $update );
				$report[] = array( 'slug' => $slug, 'action' => 'slug_fixed', 'id' => $post->ID );
				continue;
			}

			// Page does not exist at all — create it.
			$new_id = wp_insert_post( array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => $author_id,
			) );
			$report[] = array( 'slug' => $slug, 'action' => 'created', 'id' => is_wp_error( $new_id ) ? 0 : $new_id );
		}

		return $report;
	}

	/**
	 * Get the health status of each required page without making changes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_pages_health() {
		$health = array();

		foreach ( self::get_required_pages() as $page_data ) {
			$slug     = $page_data['slug'];
			$title    = wp_strip_all_tags( $page_data['title'] );
			$content  = $page_data['content'];
			$existing = get_page_by_path( $slug, OBJECT, 'page' );

			if ( $existing instanceof WP_Post ) {
				$content_ok = $content === '' || false !== strpos( $existing->post_content, $content );
				$health[]   = array(
					'slug'       => $slug,
					'title'      => $title,
					'status'     => $content_ok ? 'ok' : 'content_wrong',
					'id'         => $existing->ID,
					'url'        => get_permalink( $existing->ID ),
					'shortcode'  => $content,
				);
			} else {
				// Check if a page exists with matching title but wrong slug.
				$by_title = get_posts( array(
					'post_type'              => 'page',
					'post_status'            => array( 'publish', 'draft' ),
					'title'                  => $title,
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				) );

				if ( ! empty( $by_title ) ) {
					$health[] = array(
						'slug'      => $slug,
						'title'     => $title,
						'status'    => 'wrong_slug',
						'id'        => $by_title[0]->ID,
						'url'       => get_permalink( $by_title[0]->ID ),
						'shortcode' => $content,
					);
				} else {
					$health[] = array(
						'slug'      => $slug,
						'title'     => $title,
						'status'    => 'missing',
						'id'        => 0,
						'url'       => '',
						'shortcode' => $content,
					);
				}
			}
		}

		return $health;
	}

	/**
	 * Ensure plugin settings exist.
	 *
	 * @return void
	 */
	private static function ensure_default_settings() {
		$settings = get_option( 'remindmii_settings', array() );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::get_default_settings() );

		update_option( 'remindmii_settings', $settings );
	}

	/**
	 * Ensure existing WordPress users have plugin profile records.
	 *
	 * @return void
	 */
	private static function backfill_existing_users() {
		$user_ids = get_users(
			array(
				'fields' => 'ID',
			)
		);

		if ( ! is_array( $user_ids ) || empty( $user_ids ) ) {
			return;
		}

		foreach ( $user_ids as $user_id ) {
			self::ensure_user_records( $user_id );
		}
	}

	/**
	 * Ensure the plugin profile and preferences records exist for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	public static function ensure_user_records( $user_id ) {
		global $wpdb;

		$settings = wp_parse_args( get_option( 'remindmii_settings', array() ), self::get_default_settings() );

		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		$profiles_table    = $wpdb->prefix . 'remindmii_user_profiles';
		$preferences_table = $wpdb->prefix . 'remindmii_user_preferences';
		$timestamp         = current_time( 'mysql' );

		$profile_exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$profiles_table} WHERE user_id = %d LIMIT 1",
				$user_id
			)
		);

		if ( 0 === $profile_exists ) {
			$wpdb->insert(
				$profiles_table,
				array(
					'user_id'             => $user_id,
					'full_name'           => $user->display_name,
					'email'               => $user->user_email,
					'email_notifications' => ! empty( $settings['default_email_notifications'] ) ? 1 : 0,
					'notification_hours'  => max( 1, absint( $settings['default_notification_hours'] ) ),
					'created_at'          => $timestamp,
					'updated_at'          => $timestamp,
				),
				array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
		}

		$preferences_exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$preferences_table} WHERE user_id = %d LIMIT 1",
				$user_id
			)
		);

		if ( 0 === $preferences_exists ) {
			$wpdb->insert(
				$preferences_table,
				array(
					'user_id'                   => $user_id,
					'theme'                     => 'system',
					'color_scheme'              => wp_json_encode( array() ),
					'enable_location_reminders' => 0,
					'enable_gamification'       => 1,
					'distracted_mode'           => 0,
					'created_at'                => $timestamp,
					'updated_at'                => $timestamp,
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
			);
		}
	}
}