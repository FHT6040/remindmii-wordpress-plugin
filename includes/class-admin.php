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
		add_action( 'admin_post_remindmii_delete_user_data',  array( $this, 'handle_delete_user_data' ) );
		add_action( 'admin_post_remindmii_merchant_create',      array( $this, 'handle_merchant_create' ) );
		add_action( 'admin_post_remindmii_merchant_toggle',      array( $this, 'handle_merchant_toggle' ) );
		add_action( 'admin_post_remindmii_merchant_assign_user', array( $this, 'handle_merchant_assign_user' ) );
		add_action( 'admin_post_remindmii_merchant_remove_user', array( $this, 'handle_merchant_remove_user' ) );
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
	 * Render the main admin page (tab dispatcher).
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$allowed_tabs = array( 'dashboard', 'users', 'logs', 'settings', 'merchants' );
		$current_tab  = isset( $_GET['remindmii_tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['remindmii_tab'] ) ) : 'dashboard';
		$current_tab  = in_array( $current_tab, $allowed_tabs, true ) ? $current_tab : 'dashboard';

		$notice      = isset( $_GET['remindmii_notice'] )      ? sanitize_key( wp_unslash( (string) $_GET['remindmii_notice'] ) ) : '';
		$total       = isset( $_GET['remindmii_total'] )       ? absint( wp_unslash( (string) $_GET['remindmii_total'] ) ) : 0;
		$previewed   = isset( $_GET['remindmii_previewed'] )   ? absint( wp_unslash( (string) $_GET['remindmii_previewed'] ) ) : 0;
		$sent        = isset( $_GET['remindmii_sent'] )        ? absint( wp_unslash( (string) $_GET['remindmii_sent'] ) ) : 0;
		$failed      = isset( $_GET['remindmii_failed'] )      ? absint( wp_unslash( (string) $_GET['remindmii_failed'] ) ) : 0;
		$rescheduled = isset( $_GET['remindmii_rescheduled'] ) ? absint( wp_unslash( (string) $_GET['remindmii_rescheduled'] ) ) : 0;

		$base_url = admin_url( 'admin.php' );
		?>
		<div class="wrap">
			<h1 class="remindmii-admin-title">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php echo esc_html__( 'Remindmii', 'remindmii' ); ?>
			</h1>

			<nav class="nav-tab-wrapper remindmii-admin-tabs">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'dashboard' ), $base_url ) ); ?>"
				   class="nav-tab <?php echo 'dashboard' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-dashboard"></span>
					<?php esc_html_e( 'Dashboard', 'remindmii' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'users' ), $base_url ) ); ?>"
				   class="nav-tab <?php echo 'users' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-users"></span>
					<?php esc_html_e( 'Users', 'remindmii' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'logs' ), $base_url ) ); ?>"
				   class="nav-tab <?php echo 'logs' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Notification Logs', 'remindmii' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'settings' ), $base_url ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Settings', 'remindmii' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants' ), $base_url ) ); ?>"
				   class="nav-tab <?php echo 'merchants' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-store"></span>
					<?php esc_html_e( 'Merchants', 'remindmii' ); ?>
				</a>
			</nav>

			<div class="remindmii-admin-tab-content">
				<?php
				if ( 'dashboard' === $current_tab ) {
					$this->render_tab_dashboard( $notice, $sent, $failed, $rescheduled, $total, $previewed );
				} elseif ( 'users' === $current_tab ) {
					$this->render_tab_users( $notice );
				} elseif ( 'logs' === $current_tab ) {
					$filters = $this->get_notification_log_filters();
					$logs    = $this->get_notification_logs( 50, $filters );
					$this->render_tab_logs( $filters, $logs );
				} elseif ( 'settings' === $current_tab ) {
					$this->render_tab_settings();
				} elseif ( 'merchants' === $current_tab ) {
					$this->render_tab_merchants();
				}
				?>
			</div>
		</div>
		<?php
	}


/**
 * Render the Dashboard tab.
 */
private function render_tab_dashboard( $notice, $sent, $failed, $rescheduled, $total, $previewed ) {
$stats    = $this->get_plugin_stats();
$next_run = wp_next_scheduled( 'remindmii_process_notifications' );
?>
<div class="remindmii-admin-page">
<?php if ( 'notifications_ran' === $notice ) : ?>
<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( __( 'Notifications sent. Evaluated %1$d, sent %2$d, failed %3$d, rescheduled %4$d.', 'remindmii' ), $total, $sent, $failed, $rescheduled ) ); ?></p></div>
<?php endif; ?>
<?php if ( 'notifications_dry_run' === $notice ) : ?>
<div class="notice notice-info is-dismissible"><p><?php echo esc_html( sprintf( __( 'Dry run complete. Evaluated %1$d reminder(s); %2$d preview entries added without sending emails.', 'remindmii' ), $total, $previewed ) ); ?></p></div>
<?php endif; ?>
<?php if ( 'user_data_deleted' === $notice ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'User data deleted successfully.', 'remindmii' ); ?></p></div>
<?php endif; ?>

<div class="remindmii-stats-grid">
<div class="remindmii-stat-card">
<span class="remindmii-stat-icon dashicons dashicons-admin-users"></span>
<span class="remindmii-stat-value"><?php echo esc_html( (string) $stats['total_users'] ); ?></span>
<span class="remindmii-stat-label"><?php esc_html_e( 'Users', 'remindmii' ); ?></span>
</div>
<div class="remindmii-stat-card">
<span class="remindmii-stat-icon dashicons dashicons-calendar-alt"></span>
<span class="remindmii-stat-value"><?php echo esc_html( (string) $stats['total_reminders'] ); ?></span>
<span class="remindmii-stat-label"><?php esc_html_e( 'Reminders', 'remindmii' ); ?></span>
</div>
<div class="remindmii-stat-card">
<span class="remindmii-stat-icon dashicons dashicons-star-filled"></span>
<span class="remindmii-stat-value"><?php echo esc_html( (string) $stats['total_wishlists'] ); ?></span>
<span class="remindmii-stat-label"><?php esc_html_e( 'Wishlists', 'remindmii' ); ?></span>
</div>
<div class="remindmii-stat-card">
<span class="remindmii-stat-icon dashicons dashicons-awards"></span>
<span class="remindmii-stat-value"><?php echo esc_html( (string) $stats['total_achievements'] ); ?></span>
<span class="remindmii-stat-label"><?php esc_html_e( 'Achievements Earned', 'remindmii' ); ?></span>
</div>
<div class="remindmii-stat-card">
<span class="remindmii-stat-icon dashicons dashicons-email-alt"></span>
<span class="remindmii-stat-value"><?php echo esc_html( (string) $stats['emails_sent_today'] ); ?></span>
<span class="remindmii-stat-label"><?php esc_html_e( 'Emails Sent Today', 'remindmii' ); ?></span>
</div>
<div class="remindmii-stat-card">
<span class="remindmii-stat-icon dashicons dashicons-clock"></span>
<span class="remindmii-stat-value"><?php echo $next_run ? esc_html( human_time_diff( $next_run ) ) : esc_html__( 'N/A', 'remindmii' ); ?></span>
<span class="remindmii-stat-label"><?php esc_html_e( 'Next Cron Run', 'remindmii' ); ?></span>
</div>
</div>

<div class="remindmii-admin-section">
<h2><?php esc_html_e( 'Notification Diagnostics', 'remindmii' ); ?></h2>
<p><?php esc_html_e( 'Manually trigger the notification cron or run a dry run to preview which reminders are due.', 'remindmii' ); ?></p>
<div class="remindmii-diag-buttons">
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
<input type="hidden" name="action" value="remindmii_run_notifications" />
<input type="hidden" name="mode" value="live" />
<?php wp_nonce_field( 'remindmii_run_notifications' ); ?>
<?php submit_button( __( 'Run Notifications Now', 'remindmii' ), 'secondary', 'submit', false ); ?>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:8px">
<input type="hidden" name="action" value="remindmii_run_notifications" />
<input type="hidden" name="mode" value="dry-run" />
<?php wp_nonce_field( 'remindmii_run_notifications' ); ?>
<?php submit_button( __( 'Dry Run', 'remindmii' ), 'button', 'submit', false ); ?>
</form>
</div>
</div>
</div>
<?php
}

/**
 * Render the Users tab.
 */
private function render_tab_users( $notice ) {
$users    = $this->get_users_list();
$base_url = admin_url( 'admin.php' );
?>
<div class="remindmii-admin-page">
<?php if ( 'user_data_deleted' === $notice ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'User data deleted successfully.', 'remindmii' ); ?></p></div>
<?php endif; ?>

<h2 style="margin-top:0"><?php echo esc_html( sprintf( __( 'All Users (%d)', 'remindmii' ), count( $users ) ) ); ?></h2>

<div class="remindmii-table-wrap">
<table class="widefat striped remindmii-users-table">
<thead>
<tr>
<th><?php esc_html_e( 'ID', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Login', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Display Name', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Email', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Registered', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Reminders', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Wishlists', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Points', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Actions', 'remindmii' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( empty( $users ) ) : ?>
<tr><td colspan="9"><?php esc_html_e( 'No users found.', 'remindmii' ); ?></td></tr>
<?php else : ?>
<?php foreach ( $users as $u ) : ?>
<tr>
<td><?php echo esc_html( (string) $u['id'] ); ?></td>
<td><?php echo esc_html( $u['login'] ); ?></td>
<td><?php echo esc_html( $u['display_name'] ); ?></td>
<td><?php echo esc_html( $u['email'] ); ?></td>
<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $u['registered'] ) ) ); ?></td>
<td><?php echo esc_html( (string) $u['reminders'] ); ?></td>
<td><?php echo esc_html( (string) $u['wishlists'] ); ?></td>
<td><?php echo esc_html( (string) $u['points'] ); ?></td>
<td>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
  onsubmit="return confirm('<?php echo esc_js( __( 'Delete all Remindmii data for this user? This cannot be undone.', 'remindmii' ) ); ?>')">
<input type="hidden" name="action" value="remindmii_delete_user_data" />
<input type="hidden" name="user_id" value="<?php echo absint( $u['id'] ); ?>" />
<?php wp_nonce_field( 'remindmii_delete_user_data_' . $u['id'] ); ?>
<button type="submit" class="button button-link-delete">
<?php esc_html_e( 'Delete Data', 'remindmii' ); ?>
</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<?php
}

/**
 * Render the Notification Logs tab.
 */
private function render_tab_logs( $filters, $logs ) {
?>
<div class="remindmii-admin-page">
<h2 style="margin-top:0"><?php esc_html_e( 'Notification Logs', 'remindmii' ); ?></h2>

<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="remindmii-admin-filters-form">
<input type="hidden" name="page" value="remindmii" />
<input type="hidden" name="remindmii_tab" value="logs" />
<label for="remindmii_log_status">
<span><?php esc_html_e( 'Status', 'remindmii' ); ?></span>
<select id="remindmii_log_status" name="log_status">
<option value=""><?php esc_html_e( 'All statuses', 'remindmii' ); ?></option>
<option value="sent" <?php selected( $filters['status'], 'sent' ); ?>><?php esc_html_e( 'Sent', 'remindmii' ); ?></option>
<option value="failed" <?php selected( $filters['status'], 'failed' ); ?>><?php esc_html_e( 'Failed', 'remindmii' ); ?></option>
<option value="preview" <?php selected( $filters['status'], 'preview' ); ?>><?php esc_html_e( 'Preview', 'remindmii' ); ?></option>
</select>
</label>
<label for="remindmii_log_channel">
<span><?php esc_html_e( 'Channel', 'remindmii' ); ?></span>
<select id="remindmii_log_channel" name="log_channel">
<option value=""><?php esc_html_e( 'All channels', 'remindmii' ); ?></option>
<option value="email" <?php selected( $filters['channel'], 'email' ); ?>><?php esc_html_e( 'Email', 'remindmii' ); ?></option>
</select>
</label>
<?php submit_button( __( 'Filter', 'remindmii' ), 'secondary', '', false ); ?>
<a class="button button-link" href="<?php echo esc_url( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'logs' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Reset', 'remindmii' ); ?></a>
</form>

<div class="remindmii-table-wrap">
<table class="widefat striped remindmii-log-table">
<thead>
<tr>
<th><?php esc_html_e( 'ID', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'User', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Reminder', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Type', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Channel', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Status', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Message', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Sent At', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Created At', 'remindmii' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( empty( $logs ) ) : ?>
<tr><td colspan="9"><?php esc_html_e( 'No notification logs yet.', 'remindmii' ); ?></td></tr>
<?php else : ?>
<?php foreach ( $logs as $log ) : ?>
<tr>
<td><?php echo esc_html( (string) $log['id'] ); ?></td>
<td><?php echo esc_html( (string) $log['user_id'] ); ?></td>
<td><?php echo esc_html( (string) $log['reminder_id'] ); ?></td>
<td><?php echo esc_html( (string) $log['notification_type'] ); ?></td>
<td><?php echo esc_html( (string) $log['channel'] ); ?></td>
<td>
<span class="remindmii-log-status remindmii-log-status--<?php echo esc_attr( (string) $log['status'] ); ?>">
<?php echo esc_html( (string) $log['status'] ); ?>
</span>
</td>
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
<?php
}

/**
 * Render the Settings tab.
 */
private function render_tab_settings() {
?>
<div class="remindmii-admin-page">
<h2 style="margin-top:0"><?php esc_html_e( 'Settings', 'remindmii' ); ?></h2>
<form method="post" action="options.php" class="remindmii-admin-form">
<?php
settings_fields( 'remindmii_settings_group' );
do_settings_sections( 'remindmii' );
submit_button( __( 'Save Settings', 'remindmii' ) );
?>
</form>
</div>
<?php
}

/**
 * Handle deletion of all plugin data for a single user.
 */
public function handle_delete_user_data() {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'You are not allowed to perform this action.', 'remindmii' ) );
}

$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
if ( $user_id <= 0 ) {
wp_die( esc_html__( 'Invalid user ID.', 'remindmii' ) );
}

check_admin_referer( 'remindmii_delete_user_data_' . $user_id );

global $wpdb;
$tables = array(
'remindmii_reminders',
'remindmii_wishlist_items',
'remindmii_wishlists',
'remindmii_wishlist_shares',
'remindmii_user_preferences',
'remindmii_user_achievements',
'remindmii_user_stats',
'remindmii_user_profiles',
'remindmii_notifications_log',
);

foreach ( $tables as $table ) {
$wpdb->delete( $wpdb->prefix . $table, array( 'user_id' => $user_id ), array( '%d' ) );
}

wp_safe_redirect(
add_query_arg(
array(
'page'             => 'remindmii',
'remindmii_tab'   => 'users',
'remindmii_notice' => 'user_data_deleted',
),
admin_url( 'admin.php' )
)
);
exit;
}

/**
 * Aggregate plugin-wide statistics.
 *
 * @return array<string, int>
 */
private function get_plugin_stats() {
global $wpdb;
$today = current_time( 'Y-m-d' );

return array(
'total_users'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}remindmii_user_profiles" ),
'total_reminders'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}remindmii_reminders" ),
'total_wishlists'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}remindmii_wishlists" ),
'total_achievements'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}remindmii_user_achievements" ),
'emails_sent_today'   => (int) $wpdb->get_var(
$wpdb->prepare(
"SELECT COUNT(*) FROM {$wpdb->prefix}remindmii_notifications_log WHERE status = 'sent' AND DATE(sent_at) = %s",
$today
)
),
);
}

/**
 * Return all WP users annotated with Remindmii data counts.
 *
 * @return array<int, array<string, mixed>>
 */
private function get_users_list() {
global $wpdb;

$wp_users = get_users( array( 'fields' => array( 'ID', 'user_login', 'display_name', 'user_email', 'user_registered' ) ) );
if ( empty( $wp_users ) ) {
return array();
}

$ids_placeholder = implode( ',', array_fill( 0, count( $wp_users ), '%d' ) );
$ids             = array_column( (array) $wp_users, 'ID' );

$reminder_counts = $wpdb->get_results(
$wpdb->prepare(
"SELECT user_id, COUNT(*) AS cnt FROM {$wpdb->prefix}remindmii_reminders WHERE user_id IN ($ids_placeholder) GROUP BY user_id",
$ids
),
OBJECT_K
);

$wishlist_counts = $wpdb->get_results(
$wpdb->prepare(
"SELECT user_id, COUNT(*) AS cnt FROM {$wpdb->prefix}remindmii_wishlists WHERE user_id IN ($ids_placeholder) GROUP BY user_id",
$ids
),
OBJECT_K
);

$points_rows = $wpdb->get_results(
$wpdb->prepare(
"SELECT user_id, total_points FROM {$wpdb->prefix}remindmii_user_stats WHERE user_id IN ($ids_placeholder)",
$ids
),
OBJECT_K
);

$result = array();
foreach ( $wp_users as $u ) {
$result[] = array(
'id'           => $u->ID,
'login'        => $u->user_login,
'display_name' => $u->display_name,
'email'        => $u->user_email,
'registered'   => $u->user_registered,
'reminders'    => isset( $reminder_counts[ $u->ID ] ) ? (int) $reminder_counts[ $u->ID ]->cnt : 0,
'wishlists'    => isset( $wishlist_counts[ $u->ID ] ) ? (int) $wishlist_counts[ $u->ID ]->cnt : 0,
'points'       => isset( $points_rows[ $u->ID ] ) ? (int) $points_rows[ $u->ID ]->total_points : 0,
);
}

return $result;
}

/**
 * Render the Merchants tab.
 */
private function render_tab_merchants() {
	global $wpdb;
	$merchants = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}remindmii_merchants ORDER BY id DESC" );
	$m_users   = $wpdb->get_results(
		"SELECT mu.*, m.name AS merchant_name, u.user_login, u.user_email
		 FROM {$wpdb->prefix}remindmii_merchant_users mu
		 LEFT JOIN {$wpdb->prefix}remindmii_merchants m ON m.id = mu.merchant_id
		 LEFT JOIN {$wpdb->users} u ON u.ID = mu.user_id
		 ORDER BY mu.id DESC"
	);
	$notice = isset( $_GET['remindmii_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['remindmii_notice'] ) ) : '';
	$base_url = add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants' ), admin_url( 'admin.php' ) );
	?>
	<div class="remindmii-admin-page">
	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Merchants', 'remindmii' ); ?></h2>

	<!-- Create merchant form -->
	<div class="remindmii-admin-card" style="max-width:600px;margin-bottom:24px">
		<h3><?php esc_html_e( 'Add Merchant', 'remindmii' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'remindmii_merchant_create', 'remindmii_merchant_nonce' ); ?>
			<input type="hidden" name="action" value="remindmii_merchant_create" />
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Name', 'remindmii' ); ?></th><td><input type="text" name="merchant_name" class="regular-text" required /></td></tr>
				<tr><th><?php esc_html_e( 'Category', 'remindmii' ); ?></th><td><input type="text" name="merchant_category" class="regular-text" /></td></tr>
				<tr><th><?php esc_html_e( 'Logo URL', 'remindmii' ); ?></th><td><input type="url" name="merchant_logo_url" class="regular-text" /></td></tr>
				<tr><th><?php esc_html_e( 'Website', 'remindmii' ); ?></th><td><input type="url" name="merchant_website_url" class="regular-text" /></td></tr>
			</table>
			<?php submit_button( __( 'Create Merchant', 'remindmii' ) ); ?>
		</form>
	</div>

	<!-- Merchants list -->
	<table class="wp-list-table widefat fixed striped">
		<thead><tr>
			<th><?php esc_html_e( 'ID', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Name', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Category', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Active', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'remindmii' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( (array) $merchants as $m ) : ?>
			<tr>
				<td><?php echo esc_html( (string) $m->id ); ?></td>
				<td><?php echo esc_html( $m->name ); ?></td>
				<td><?php echo esc_html( $m->category ?? '' ); ?></td>
				<td><?php echo $m->is_active ? '<span style="color:green">&#10003;</span>' : '<span style="color:red">&#10007;</span>'; ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'remindmii_merchant_toggle_' . $m->id, 'remindmii_merchant_nonce' ); ?>
						<input type="hidden" name="action" value="remindmii_merchant_toggle" />
						<input type="hidden" name="merchant_id" value="<?php echo absint( $m->id ); ?>" />
						<button type="submit" class="button button-small"><?php echo $m->is_active ? esc_html__( 'Deactivate', 'remindmii' ) : esc_html__( 'Activate', 'remindmii' ); ?></button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Assign WP user to merchant -->
	<h2 style="margin-top:32px"><?php esc_html_e( 'Assign User to Merchant', 'remindmii' ); ?></h2>
	<div class="remindmii-admin-card" style="max-width:600px;margin-bottom:24px">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'remindmii_merchant_assign_user', 'remindmii_merchant_nonce' ); ?>
			<input type="hidden" name="action" value="remindmii_merchant_assign_user" />
			<table class="form-table">
				<tr><th><?php esc_html_e( 'WP User ID', 'remindmii' ); ?></th><td><input type="number" name="wp_user_id" class="small-text" min="1" required /></td></tr>
				<tr><th><?php esc_html_e( 'Merchant', 'remindmii' ); ?></th><td>
					<select name="assign_merchant_id">
					<?php foreach ( (array) $merchants as $m ) : ?>
						<option value="<?php echo absint( $m->id ); ?>"><?php echo esc_html( $m->name ); ?></option>
					<?php endforeach; ?>
					</select>
				</td></tr>
			</table>
			<?php submit_button( __( 'Assign User', 'remindmii' ) ); ?>
		</form>
	</div>

	<!-- Merchant users list -->
	<table class="wp-list-table widefat fixed striped">
		<thead><tr>
			<th><?php esc_html_e( 'User', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Merchant', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'remindmii' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( (array) $m_users as $mu ) : ?>
			<tr>
				<td><?php echo esc_html( $mu->user_login . ' (' . $mu->user_email . ')' ); ?></td>
				<td><?php echo esc_html( $mu->merchant_name ?? '' ); ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'remindmii_merchant_remove_user_' . $mu->id, 'remindmii_merchant_nonce' ); ?>
						<input type="hidden" name="action" value="remindmii_merchant_remove_user" />
						<input type="hidden" name="merchant_user_id" value="<?php echo absint( $mu->id ); ?>" />
						<button type="submit" class="button button-small button-link-delete" onclick="return confirm('Remove?')"><?php esc_html_e( 'Remove', 'remindmii' ); ?></button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php
}

/** Handle create merchant POST. */
public function handle_merchant_create() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'remindmii_merchant_create', 'remindmii_merchant_nonce' );
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'remindmii_merchants',
		array(
			'name'        => sanitize_text_field( wp_unslash( (string) ( $_POST['merchant_name'] ?? '' ) ) ),
			'category'    => sanitize_text_field( wp_unslash( (string) ( $_POST['merchant_category'] ?? '' ) ) ),
			'logo_url'    => esc_url_raw( wp_unslash( (string) ( $_POST['merchant_logo_url'] ?? '' ) ) ),
			'website_url' => esc_url_raw( wp_unslash( (string) ( $_POST['merchant_website_url'] ?? '' ) ) ),
			'is_active'   => 1,
		),
		array( '%s', '%s', '%s', '%s', '%d' )
	);
	wp_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants', 'remindmii_notice' => 'Merchant+created.' ), admin_url( 'admin.php' ) ) );
	exit;
}

/** Handle toggle merchant active. */
public function handle_merchant_toggle() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$id = absint( $_POST['merchant_id'] ?? 0 );
	check_admin_referer( 'remindmii_merchant_toggle_' . $id, 'remindmii_merchant_nonce' );
	global $wpdb;
	$current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$wpdb->prefix}remindmii_merchants WHERE id=%d", $id ) );
	$wpdb->update( $wpdb->prefix . 'remindmii_merchants', array( 'is_active' => $current ? 0 : 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
	wp_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants' ), admin_url( 'admin.php' ) ) );
	exit;
}

/** Handle assign WP user to merchant. */
public function handle_merchant_assign_user() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'remindmii_merchant_assign_user', 'remindmii_merchant_nonce' );
	global $wpdb;
	$wpdb->replace(
		$wpdb->prefix . 'remindmii_merchant_users',
		array(
			'merchant_id' => absint( $_POST['assign_merchant_id'] ?? 0 ),
			'user_id'     => absint( $_POST['wp_user_id'] ?? 0 ),
			'role'        => 'admin',
		),
		array( '%d', '%d', '%s' )
	);
	wp_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants', 'remindmii_notice' => 'User+assigned.' ), admin_url( 'admin.php' ) ) );
	exit;
}

/** Handle remove user from merchant. */
public function handle_merchant_remove_user() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$id = absint( $_POST['merchant_user_id'] ?? 0 );
	check_admin_referer( 'remindmii_merchant_remove_user_' . $id, 'remindmii_merchant_nonce' );
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'remindmii_merchant_users', array( 'id' => $id ), array( '%d' ) );
	wp_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants' ), admin_url( 'admin.php' ) ) );
	exit;
}
}
