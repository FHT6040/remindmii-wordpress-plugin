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
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : 'live';
		$mode = in_array( $mode, array( 'live', 'dry-run' ), true ) ? $mode : 'live';
		$cron = new Remindmii_Cron();
		$summary = $cron->process_notifications( 'dry-run' === $mode );

		$summary = is_array( $summary ) ? $summary : array();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'remindmii',
					'remindmii_notice'   => 'dry-run' === $mode ? 'notifications_dry_run' : 'notifications_ran',
					'remindmii_total'    => isset( $summary['total'] ) ? absint( $summary['total'] ) : 0,
					'remindmii_previewed'=> isset( $summary['previewed'] ) ? absint( $summary['previewed'] ) : 0,
					'remindmii_sent'     => isset( $summary['sent'] ) ? absint( $summary['sent'] ) : 0,
					'remindmii_failed'   => isset( $summary['failed'] ) ? absint( $summary['failed'] ) : 0,
					'remindmii_rescheduled' => isset( $summary['rescheduled'] ) ? absint( $summary['rescheduled'] ) : 0,
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
	private function get_notification_logs( $limit = 30, $filters = array() ) {
		global $wpdb;

		$limit        = max( 1, min( 200, absint( $limit ) ) );
		$table        = $wpdb->prefix . 'remindmii_notifications_log';
		$where_sql    = '1=1';
		$query_params = array();

		if ( ! empty( $filters['status'] ) ) {
			$where_sql      .= ' AND status = %s';
			$query_params[] = $filters['status'];
		}

		if ( ! empty( $filters['channel'] ) ) {
			$where_sql      .= ' AND channel = %s';
			$query_params[] = $filters['channel'];
		}

		$query_params[] = $limit;
		$sql            = "SELECT id, user_id, reminder_id, notification_type, channel, status, message, sent_at, created_at
			FROM {$table}
			WHERE {$where_sql}
			ORDER BY id DESC
			LIMIT %d";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Read notification log filters from the current request.
	 *
	 * @return array<string, string>
	 */
	private function get_notification_log_filters() {
		$status  = isset( $_GET['log_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['log_status'] ) ) : '';
		$channel = isset( $_GET['log_channel'] ) ? sanitize_key( wp_unslash( (string) $_GET['log_channel'] ) ) : '';

		$allowed_statuses = array( '', 'sent', 'failed', 'preview' );
		$allowed_channels = array( '', 'email' );

		return array(
			'status'  => in_array( $status, $allowed_statuses, true ) ? $status : '',
			'channel' => in_array( $channel, $allowed_channels, true ) ? $channel : '',
		);
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

		$notice  = isset( $_GET['remindmii_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['remindmii_notice'] ) ) : '';
		$filters = $this->get_notification_log_filters();
		$logs    = $this->get_notification_logs( 30, $filters );
		$total   = isset( $_GET['remindmii_total'] ) ? absint( wp_unslash( (string) $_GET['remindmii_total'] ) ) : 0;
		$previewed = isset( $_GET['remindmii_previewed'] ) ? absint( wp_unslash( (string) $_GET['remindmii_previewed'] ) ) : 0;
		$sent    = isset( $_GET['remindmii_sent'] ) ? absint( wp_unslash( (string) $_GET['remindmii_sent'] ) ) : 0;
		$failed  = isset( $_GET['remindmii_failed'] ) ? absint( wp_unslash( (string) $_GET['remindmii_failed'] ) ) : 0;
		$rescheduled = isset( $_GET['remindmii_rescheduled'] ) ? absint( wp_unslash( (string) $_GET['remindmii_rescheduled'] ) ) : 0;

		?>
		<div class="wrap remindmii-admin-page">
			<h1><?php echo esc_html__( 'Remindmii', 'remindmii' ); ?></h1>
			<p><?php echo esc_html__( 'Manage the plugin defaults for new and backfilled Remindmii users.', 'remindmii' ); ?></p>

			<?php if ( 'notifications_ran' === $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: total reminders, 2: sent count, 3: failed count, 4: rescheduled count */
								__( 'Notification processing completed. Evaluated %1$d reminder(s), sent %2$d, failed %3$d, rescheduled %4$d.', 'remindmii' ),
								$total,
								$sent,
								$failed,
								$rescheduled
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( 'notifications_dry_run' === $notice ) : ?>
				<div class="notice notice-info is-dismissible">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: total reminders, 2: preview count */
								__( 'Notification dry run completed. Evaluated %1$d reminder(s); %2$d preview log entries were added without sending emails.', 'remindmii' ),
								$total,
								$previewed
							)
						);
						?>
					</p>
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
					<input type="hidden" name="mode" value="live" />
					<?php wp_nonce_field( 'remindmii_run_notifications' ); ?>
					<?php submit_button( __( 'Run Notifications Now', 'remindmii' ), 'secondary', 'submit', false ); ?>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="remindmii-admin-inline-form">
					<input type="hidden" name="action" value="remindmii_run_notifications" />
					<input type="hidden" name="mode" value="dry-run" />
					<?php wp_nonce_field( 'remindmii_run_notifications' ); ?>
					<?php submit_button( __( 'Dry Run Notifications', 'remindmii' ), 'button', 'submit', false ); ?>
				</form>

				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="remindmii-admin-filters-form">
					<input type="hidden" name="page" value="remindmii" />
					<label for="remindmii_log_status">
						<span><?php echo esc_html__( 'Status', 'remindmii' ); ?></span>
						<select id="remindmii_log_status" name="log_status">
							<option value=""><?php echo esc_html__( 'All statuses', 'remindmii' ); ?></option>
							<option value="sent" <?php selected( $filters['status'], 'sent' ); ?>><?php echo esc_html__( 'Sent', 'remindmii' ); ?></option>
							<option value="failed" <?php selected( $filters['status'], 'failed' ); ?>><?php echo esc_html__( 'Failed', 'remindmii' ); ?></option>
							<option value="preview" <?php selected( $filters['status'], 'preview' ); ?>><?php echo esc_html__( 'Preview', 'remindmii' ); ?></option>
						</select>
					</label>
					<label for="remindmii_log_channel">
						<span><?php echo esc_html__( 'Channel', 'remindmii' ); ?></span>
						<select id="remindmii_log_channel" name="log_channel">
							<option value=""><?php echo esc_html__( 'All channels', 'remindmii' ); ?></option>
							<option value="email" <?php selected( $filters['channel'], 'email' ); ?>><?php echo esc_html__( 'Email', 'remindmii' ); ?></option>
						</select>
					</label>
					<?php submit_button( __( 'Filter Logs', 'remindmii' ), 'secondary', '', false ); ?>
					<a class="button button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=remindmii' ) ); ?>"><?php echo esc_html__( 'Reset filters', 'remindmii' ); ?></a>
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