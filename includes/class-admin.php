<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Admin {
	/**
	 * Plugin settings option key.
	 *
	 * @var string
	 */
	private $option_key = 'remindmii_settings';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_remindmii_run_notifications', array( $this, 'handle_run_notifications' ) );
	}

	/**
	 * Register plugin admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Remindmii', 'remindmii' ),
			__( 'Remindmii', 'remindmii' ),
			'manage_options',
			'remindmii',
			array( $this, 'render_page' ),
			'dashicons-calendar-alt',
			56
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'remindmii_settings_group',
			$this->option_key,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'remindmii_general_settings',
			__( 'General Settings', 'remindmii' ),
			array( $this, 'render_general_section' ),
			'remindmii'
		);

		add_settings_field(
			'default_email_notifications',
			__( 'Default email notifications', 'remindmii' ),
			array( $this, 'render_default_email_notifications_field' ),
			'remindmii',
			'remindmii_general_settings'
		);

		add_settings_field(
			'default_notification_hours',
			__( 'Default notification lead time', 'remindmii' ),
			array( $this, 'render_default_notification_hours_field' ),
			'remindmii',
			'remindmii_general_settings'
		);
	}

	/**
	 * Enqueue admin assets on the plugin screen.
	 *
	 * @param string $hook_suffix Current admin page suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_remindmii' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'remindmii-admin',
			REMINDMII_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			REMINDMII_VERSION
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @param array<string, mixed> $input Submitted settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ) {
		$settings = Remindmii_Installer::get_default_settings();

		if ( ! is_array( $input ) ) {
			return $settings;
		}

		$settings['default_email_notifications'] = ! empty( $input['default_email_notifications'] ) ? 1 : 0;

		if ( isset( $input['default_notification_hours'] ) ) {
			$notification_hours = absint( $input['default_notification_hours'] );
			$settings['default_notification_hours'] = max( 1, min( 720, $notification_hours ) );
		}

		return $settings;
	}

	/**
	 * Render general settings section text.
	 *
	 * @return void
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure the default reminder behavior for newly initialized Remindmii users.', 'remindmii' ) . '</p>';
	}

	/**
	 * Render the default email notifications field.
	 *
	 * @return void
	 */
	public function render_default_email_notifications_field() {
		$settings = $this->get_settings();
		?>
		<label for="remindmii_default_email_notifications">
			<input
				id="remindmii_default_email_notifications"
				type="checkbox"
				name="<?php echo esc_attr( $this->option_key ); ?>[default_email_notifications]"
				value="1"
				<?php checked( ! empty( $settings['default_email_notifications'] ) ); ?>
			/>
			<?php echo esc_html__( 'Enable email notifications by default for new plugin user records.', 'remindmii' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the default notification hours field.
	 *
	 * @return void
	 */
	public function render_default_notification_hours_field() {
		$settings = $this->get_settings();
		?>
		<input
			id="remindmii_default_notification_hours"
			type="number"
			min="1"
			max="720"
			class="small-text"
			name="<?php echo esc_attr( $this->option_key ); ?>[default_notification_hours]"
			value="<?php echo esc_attr( (string) $settings['default_notification_hours'] ); ?>"
		/>
		<p class="description">
			<?php echo esc_html__( 'Hours before a reminder when newly initialized users should be notified by default.', 'remindmii' ); ?>
		</p>
		<?php
	}

	/**
	 * Read plugin settings with defaults applied.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings() {
		return wp_parse_args( get_option( $this->option_key, array() ), Remindmii_Installer::get_default_settings() );
	}

	/**
	 * Run notification cron processing manually from the admin page.
	 *
	 * @return void
	 */
	public function handle_run_notifications() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'remindmii' ) );
		}

		check_admin_referer( 'remindmii_run_notifications' );

		do_action( 'remindmii_process_notifications' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'remindmii',
					'remindmii_notice'   => 'notifications_ran',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Fetch latest notification log rows.
	 *
	 * @param int $limit Number of rows to return.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_notification_logs( $limit = 30 ) {
		global $wpdb;

		$limit = max( 1, min( 200, absint( $limit ) ) );
		$table = $wpdb->prefix . 'remindmii_notifications_log';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, reminder_id, notification_type, channel, status, message, sent_at, created_at
				FROM {$table}
				ORDER BY id DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Render placeholder admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = isset( $_GET['remindmii_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['remindmii_notice'] ) ) : '';
		$logs   = $this->get_notification_logs();

		?>
		<div class="wrap remindmii-admin-page">
			<h1><?php echo esc_html__( 'Remindmii', 'remindmii' ); ?></h1>
			<p><?php echo esc_html__( 'Manage the plugin defaults for new and backfilled Remindmii users.', 'remindmii' ); ?></p>

			<?php if ( 'notifications_ran' === $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'Notification processing was run successfully.', 'remindmii' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php" class="remindmii-admin-form">
				<?php
				settings_fields( 'remindmii_settings_group' );
				do_settings_sections( 'remindmii' );
				submit_button( __( 'Save settings', 'remindmii' ) );
				?>
			</form>

			<div class="remindmii-admin-section">
				<h2><?php echo esc_html__( 'Notification Diagnostics', 'remindmii' ); ?></h2>
				<p><?php echo esc_html__( 'Use this to run reminder notifications immediately and inspect the latest delivery logs.', 'remindmii' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="remindmii-admin-inline-form">
					<input type="hidden" name="action" value="remindmii_run_notifications" />
					<?php wp_nonce_field( 'remindmii_run_notifications' ); ?>
					<?php submit_button( __( 'Run Notifications Now', 'remindmii' ), 'secondary', 'submit', false ); ?>
				</form>

				<div class="remindmii-table-wrap">
					<table class="widefat striped remindmii-log-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'ID', 'remindmii' ); ?></th>
								<th><?php echo esc_html__( 'User', 'remindmii' ); ?></th>
								<th><?php echo esc_html__( 'Reminder', 'remindmii' ); ?></th>
								<th><?php echo esc_html__( 'Type', 'remindmii' ); ?></th>
								<th><?php echo esc_html__( 'Channel', 'remindmii' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'remindmii' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'remindmii' ); ?></th>
								<th><?php echo esc_html__( 'Sent At', 'remindmii' ); ?></th>
								<th><?php echo esc_html__( 'Created At', 'remindmii' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $logs ) ) : ?>
								<tr>
									<td colspan="9"><?php echo esc_html__( 'No notification logs yet.', 'remindmii' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $logs as $log ) : ?>
									<tr>
										<td><?php echo esc_html( (string) $log['id'] ); ?></td>
										<td><?php echo esc_html( (string) $log['user_id'] ); ?></td>
										<td><?php echo esc_html( (string) $log['reminder_id'] ); ?></td>
										<td><?php echo esc_html( (string) $log['notification_type'] ); ?></td>
										<td><?php echo esc_html( (string) $log['channel'] ); ?></td>
										<td><?php echo esc_html( (string) $log['status'] ); ?></td>
										<td><?php echo esc_html( (string) $log['message'] ); ?></td>
										<td><?php echo esc_html( ! empty( $log['sent_at'] ) ? (string) $log['sent_at'] : '-' ); ?></td>
										<td><?php echo esc_html( (string) $log['created_at'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}
}