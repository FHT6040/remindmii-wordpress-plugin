<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_CLI_Commands {
	/**
	 * Register WP-CLI commands.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'remindmii test-notifications', array( __CLASS__, 'test_notifications' ) );
		WP_CLI::add_command( 'remindmii create-test-merchant', array( __CLASS__, 'create_test_merchant' ) );
		WP_CLI::add_command( 'remindmii create-test-ad', array( __CLASS__, 'create_test_ad' ) );
		WP_CLI::add_command( 'remindmii db-check', array( __CLASS__, 'db_check' ) );
	}

	/**
	 * Test notification processing.
	 *
	 * @param array $args WP-CLI args.
	 * @return void
	 */
	public static function test_notifications( $args ) {
		WP_CLI::log( 'Running notification processing...' );

		do_action( 'remindmii_process_notifications' );

		WP_CLI::success( 'Notification processing completed.' );
	}

	/**
	 * Create test merchant and user assignment.
	 *
	 * @param array $args WP-CLI args.
	 * @return void
	 */
	public static function create_test_merchant( $args ) {
		global $wpdb;

		// Create or use first admin.
		$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		if ( empty( $admin ) ) {
			WP_CLI::error( 'No admin user found. Create one first.' );
		}
		$admin_id = $admin[0]->ID;

		// Create merchant.
		$merchant_name = ! empty( $args[0] ) ? sanitize_text_field( $args[0] ) : 'Test Merchant ' . gmdate( 'His' );

		$result = $wpdb->insert(
			$wpdb->prefix . 'remindmii_merchants',
			array(
				'name'        => $merchant_name,
				'category'    => 'Test',
				'logo_url'    => 'https://via.placeholder.com/64',
				'website_url' => 'https://example.com',
				'is_active'   => 1,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		if ( ! $result ) {
			WP_CLI::error( 'Failed to create merchant.' );
		}

		$merchant_id = (int) $wpdb->insert_id;

		// Assign to user.
		$wpdb->replace(
			$wpdb->prefix . 'remindmii_merchant_users',
			array(
				'merchant_id' => $merchant_id,
				'user_id'     => $admin_id,
				'role'        => 'admin',
			),
			array( '%d', '%d', '%s' )
		);

		WP_CLI::success( "Created merchant #{$merchant_id} '{$merchant_name}' and assigned to user #{$admin_id}." );
	}

	/**
	 * Create test ad for a merchant.
	 *
	 * @param array $args WP-CLI args (merchant_id optional).
	 * @return void
	 */
	public static function create_test_ad( $args ) {
		global $wpdb;

		$merchant_id = ! empty( $args[0] ) ? absint( $args[0] ) : 1;

		// Verify merchant exists.
		$merchant = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}remindmii_merchants WHERE id = %d", $merchant_id )
		);
		if ( ! $merchant ) {
			WP_CLI::error( "Merchant #{$merchant_id} not found." );
		}

		$now = current_time( 'mysql' );

		$wpdb->insert(
			$wpdb->prefix . 'remindmii_merchant_ads',
			array(
				'merchant_id'      => $merchant_id,
				'title'            => 'Test Ad ' . gmdate( 'His' ),
				'description'      => 'This is a test ad.',
				'image_url'        => 'https://via.placeholder.com/400x200',
				'background_color' => '#3B82F6',
				'text_color'       => '#FFFFFF',
				'target_gender'    => wp_json_encode( array( 'all' ) ),
				'target_age_min'   => 18,
				'target_age_max'   => 65,
				'target_categories'=> wp_json_encode( array( 'all' ) ),
				'cta_text'         => 'Learn More',
				'cta_url'          => 'https://example.com',
				'is_active'        => 1,
				'impressions'      => 0,
				'clicks'           => 0,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		$ad_id = (int) $wpdb->insert_id;
		WP_CLI::success( "Created test ad #{$ad_id} for merchant #{$merchant_id}." );
	}

	/**
	 * Check database schema integrity.
	 *
	 * @param array $args WP-CLI args.
	 * @return void
	 */
	public static function db_check( $args ) {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'remindmii_reminders',
			$wpdb->prefix . 'remindmii_categories',
			$wpdb->prefix . 'remindmii_wishlists',
			$wpdb->prefix . 'remindmii_user_profiles',
			$wpdb->prefix . 'remindmii_user_preferences',
			$wpdb->prefix . 'remindmii_user_notifications',
			$wpdb->prefix . 'remindmii_shared_lists',
			$wpdb->prefix . 'remindmii_achievements',
			$wpdb->prefix . 'remindmii_notification_logs',
			$wpdb->prefix . 'remindmii_merchants',
			$wpdb->prefix . 'remindmii_merchant_users',
			$wpdb->prefix . 'remindmii_merchant_ads',
		);

		WP_CLI::log( 'Checking database schema...' );

		foreach ( $tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists ) {
				WP_CLI::log( "✓ {$table}" );
			} else {
				WP_CLI::warning( "✗ {$table} NOT FOUND" );
			}
		}

		WP_CLI::success( 'Schema check complete.' );
	}
}
