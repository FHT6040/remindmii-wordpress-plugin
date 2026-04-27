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
		add_action( 'admin_post_remindmii_repair_pages',      array( $this, 'handle_repair_pages' ) );
		add_action( 'admin_post_remindmii_merchant_create',      array( $this, 'handle_merchant_create' ) );
		add_action( 'admin_post_remindmii_merchant_toggle',      array( $this, 'handle_merchant_toggle' ) );
		add_action( 'admin_post_remindmii_merchant_assign_user', array( $this, 'handle_merchant_assign_user' ) );
		add_action( 'admin_post_remindmii_merchant_remove_user', array( $this, 'handle_merchant_remove_user' ) );
		add_action( 'admin_post_remindmii_merchant_update',      array( $this, 'handle_merchant_update' ) );
		add_action( 'admin_post_remindmii_export_logs',           array( $this, 'handle_export_logs' ) );
		add_action( 'admin_post_remindmii_retry_notification',    array( $this, 'handle_retry_notification' ) );
		add_action( 'admin_post_remindmii_generate_test_data',   array( $this, 'handle_generate_test_data' ) );
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

		if ( 'dry-run' === $mode ) {
			$due = ( new Remindmii_Reminders_Repository() )->get_due_for_notifications( 200 );
			$preview_rows = array();
			foreach ( $due as $r ) {
				$preview_rows[] = array(
					'title'          => $r['title'],
					'reminder_date'  => $r['reminder_date'],
					'profile_email'  => $r['profile_email'],
					'is_recurring'   => ! empty( $r['is_recurring'] ),
				);
			}
			set_transient( 'remindmii_dryrun_' . get_current_user_id(), $preview_rows, 120 );
		}

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
	private function get_notification_logs( $limit = 30, $filters = array(), $page = 1 ) {
		global $wpdb;

		$limit        = max( 1, min( 200, absint( $limit ) ) );
		$page         = max( 1, absint( $page ) );
		$offset       = ( $page - 1 ) * $limit;
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
		$query_params[] = $offset;
		$sql            = "SELECT id, user_id, reminder_id, notification_type, channel, status, message, sent_at, created_at
			FROM {$table}
			WHERE {$where_sql}
			ORDER BY id DESC
			LIMIT %d OFFSET %d";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	private function get_notification_logs_count( $filters = array() ) {
		global $wpdb;

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

		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count = empty( $query_params ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, $query_params ) );

		return (int) $count;
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
				<span style="font-size:13px;font-weight:normal;color:#646970;margin-left:8px">v<?php echo esc_html( REMINDMII_VERSION ); ?></span>
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
					$paged   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
					$filters = $this->get_notification_log_filters();
					$total   = $this->get_notification_logs_count( $filters );
					$logs    = $this->get_notification_logs( 50, $filters, $paged );
					$this->render_tab_logs( $filters, $logs, $paged, $total, $notice );
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
$stats   = $this->get_plugin_stats();
$weekly  = $this->get_last_7_days_stats();
$next_run = wp_next_scheduled( 'remindmii_process_notifications' );
?>
<div class="remindmii-admin-page">
<?php if ( 'notifications_ran' === $notice ) : ?>
<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( __( 'Notifications sent. Evaluated %1$d, sent %2$d, failed %3$d, rescheduled %4$d.', 'remindmii' ), $total, $sent, $failed, $rescheduled ) ); ?></p></div>
<?php endif; ?>
<?php if ( 'notifications_dry_run' === $notice ) : ?>
<div class="notice notice-info is-dismissible"><p><?php echo esc_html( sprintf( __( 'Dry run complete. Evaluated %1$d reminder(s); %2$d would be sent.', 'remindmii' ), $total, $previewed ) ); ?></p></div>
<?php
$dry_details = get_transient( 'remindmii_dryrun_' . get_current_user_id() );
delete_transient( 'remindmii_dryrun_' . get_current_user_id() );
if ( ! empty( $dry_details ) ) : ?>
<div class="remindmii-admin-card" style="margin-bottom:16px">
<h3 style="margin-top:0"><?php esc_html_e( 'Would-be recipients', 'remindmii' ); ?></h3>
<table class="widefat striped" style="font-size:13px">
	<thead><tr>
		<th><?php esc_html_e( 'Reminder', 'remindmii' ); ?></th>
		<th><?php esc_html_e( 'Date', 'remindmii' ); ?></th>
		<th><?php esc_html_e( 'Email', 'remindmii' ); ?></th>
		<th><?php esc_html_e( 'Recurring', 'remindmii' ); ?></th>
	</tr></thead>
	<tbody>
	<?php foreach ( $dry_details as $dr ) : ?>
	<tr>
		<td><?php echo esc_html( $dr['title'] ); ?></td>
		<td><?php echo esc_html( $dr['reminder_date'] ); ?></td>
		<td><?php echo esc_html( $dr['profile_email'] ); ?></td>
		<td><?php echo $dr['is_recurring'] ? '&#10003;' : '&ndash;'; ?></td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>
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

<?php
// Page health check.
$pages_health  = Remindmii_Installer::get_pages_health();
$pages_ok      = array_filter( $pages_health, fn( $p ) => 'ok' === $p['status'] );
$pages_broken  = count( $pages_health ) - count( $pages_ok );
$repair_notice = isset( $_GET['remindmii_notice'] ) && 'pages_repaired' === $notice;
?>
<div class="remindmii-admin-section">
<h2><?php esc_html_e( 'Page Health', 'remindmii' ); ?></h2>
<?php if ( $repair_notice ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pages repaired successfully.', 'remindmii' ); ?></p></div>
<?php endif; ?>
<?php if ( $pages_broken > 0 ) : ?>
<div class="notice notice-warning inline"><p>
	<?php echo esc_html( sprintf( _n( '%d page has a problem.', '%d pages have problems.', $pages_broken, 'remindmii' ), $pages_broken ) ); ?>
	<?php esc_html_e( 'Click "Repair Pages" to fix slugs, shortcodes, and create any missing pages.', 'remindmii' ); ?>
</p></div>
<?php endif; ?>
<table class="widefat striped" style="margin-bottom:12px">
<thead><tr>
	<th><?php esc_html_e( 'Page', 'remindmii' ); ?></th>
	<th><?php esc_html_e( 'Expected slug', 'remindmii' ); ?></th>
	<th><?php esc_html_e( 'Shortcode', 'remindmii' ); ?></th>
	<th><?php esc_html_e( 'Status', 'remindmii' ); ?></th>
	<th><?php esc_html_e( 'URL', 'remindmii' ); ?></th>
</tr></thead>
<tbody>
<?php foreach ( $pages_health as $ph ) :
	$status_map = array(
		'ok'            => array( 'label' => __( 'OK', 'remindmii' ), 'color' => '#10b981' ),
		'content_wrong' => array( 'label' => __( 'Wrong shortcode', 'remindmii' ), 'color' => '#f59e0b' ),
		'wrong_slug'    => array( 'label' => __( 'Wrong slug', 'remindmii' ), 'color' => '#f59e0b' ),
		'missing'       => array( 'label' => __( 'Missing', 'remindmii' ), 'color' => '#ef4444' ),
	);
	$s = $status_map[ $ph['status'] ] ?? array( 'label' => $ph['status'], 'color' => '#6b7280' );
?>
<tr>
	<td><strong><?php echo esc_html( $ph['title'] ); ?></strong></td>
	<td><code><?php echo esc_html( $ph['slug'] ); ?></code></td>
	<td><?php echo $ph['shortcode'] ? '<code>' . esc_html( $ph['shortcode'] ) . '</code>' : '<em>—</em>'; ?></td>
	<td><span style="color:<?php echo esc_attr( $s['color'] ); ?>;font-weight:600"><?php echo esc_html( $s['label'] ); ?></span></td>
	<td><?php if ( $ph['url'] ) : ?><a href="<?php echo esc_url( $ph['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $ph['url'] ); ?></a><?php else : ?>—<?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="remindmii_repair_pages" />
	<?php wp_nonce_field( 'remindmii_repair_pages' ); ?>
	<?php submit_button( __( 'Repair Pages', 'remindmii' ), 'secondary', 'submit', false ); ?>
</form>
</div>

<?php
$max_bar = 1;
foreach ( $weekly as $wd ) { $max_bar = max( $max_bar, $wd['sent'] + $wd['failed'] ); }
?>
<div class="remindmii-admin-section">
<h2><?php esc_html_e( 'Notifications — Last 7 Days', 'remindmii' ); ?></h2>
<div style="display:flex;align-items:flex-end;gap:6px;height:80px;margin-bottom:4px">
<?php foreach ( $weekly as $wday => $wd ) :
	$day_total = $wd['sent'] + $wd['failed'];
	$sent_h    = $max_bar > 0 ? round( ( $wd['sent']   / $max_bar ) * 64 ) : 0;
	$fail_h    = $max_bar > 0 ? round( ( $wd['failed'] / $max_bar ) * 64 ) : 0;
?>
<div style="flex:1;display:flex;flex-direction:column;align-items:center">
<span style="font-size:10px;color:#6b7280;margin-bottom:2px"><?php echo esc_html( $day_total > 0 ? (string) $day_total : '' ); ?></span>
<div style="width:100%;display:flex;flex-direction:column-reverse;height:64px;gap:1px">
<?php if ( $wd['failed'] > 0 ) : ?><div title="<?php echo esc_attr( $wd['failed'] . ' failed' ); ?>" style="width:100%;height:<?php echo esc_attr( (string) $fail_h ); ?>px;background:#ef4444;border-radius:2px;min-height:2px"></div><?php endif; ?>
<?php if ( $wd['sent'] > 0 )   : ?><div title="<?php echo esc_attr( $wd['sent']   . ' sent' );   ?>" style="width:100%;height:<?php echo esc_attr( (string) $sent_h ); ?>px;background:#10b981;border-radius:2px;min-height:2px"></div><?php endif; ?>
<?php if ( 0 === $day_total )  : ?><div style="width:100%;height:2px;background:#e5e7eb;border-radius:2px;margin-top:62px"></div><?php endif; ?>
</div>
<span style="font-size:10px;color:#9ca3af;margin-top:4px"><?php echo esc_html( wp_date( 'D', strtotime( $wday ) ) ); ?></span>
</div>
<?php endforeach; ?>
</div>
<p style="font-size:11px;color:#6b7280;margin:4px 0 0">
<span style="display:inline-block;width:10px;height:10px;background:#10b981;border-radius:2px;vertical-align:middle;margin-right:4px"></span><?php esc_html_e( 'Sent', 'remindmii' ); ?>&nbsp;&nbsp;
<span style="display:inline-block;width:10px;height:10px;background:#ef4444;border-radius:2px;vertical-align:middle;margin-right:4px"></span><?php esc_html_e( 'Failed', 'remindmii' ); ?>
</p>
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
$leaders = $this->get_leaderboard( 10 );
if ( ! empty( $leaders ) ) :
?>
<div class="remindmii-admin-section">
<h2><?php esc_html_e( 'Top 10 — Points Leaderboard', 'remindmii' ); ?></h2>
<table class="widefat striped" style="max-width:480px">
<thead><tr>
<th style="width:32px">#</th>
<th><?php esc_html_e( 'User', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Points', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Streak', 'remindmii' ); ?></th>
<th><?php esc_html_e( 'Completed', 'remindmii' ); ?></th>
</tr></thead>
<tbody>
<?php foreach ( $leaders as $rank => $l ) : ?>
<tr>
<td><?php echo esc_html( (string) ( $rank + 1 ) ); ?></td>
<td><?php echo esc_html( $l['display_name'] . ' (' . $l['user_login'] . ')' ); ?></td>
<td><strong><?php echo esc_html( number_format_i18n( (int) $l['total_points'] ) ); ?></strong></td>
<td><?php echo esc_html( (string) $l['current_streak'] ); ?></td>
<td><?php echo esc_html( (string) $l['total_completed'] ); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
<?php
}

/**
 * Render the Users tab.
 */
private function render_tab_users( $notice ) {
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : 'id';
$order    = ( isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( (string) $_GET['order'] ) ) ) ) ? 'DESC' : 'ASC';
$users    = $this->get_users_list( $search, $orderby, $order );
$base_url = add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'users' ), admin_url( 'admin.php' ) );
$col      = function ( $label, $key, $default_dir = 'asc' ) use ( $orderby, $order, $search, $base_url ) {
	$flip = 'ASC' === $order ? 'desc' : 'asc';
	$url  = add_query_arg( array( 'orderby' => $key, 'order' => $key === $orderby ? $flip : $default_dir, 's' => $search ), $base_url );
	$ind  = $key === $orderby ? ( 'ASC' === $order ? ' ▲' : ' ▼' ) : '';
	return '<th><a href="' . esc_url( $url ) . '">' . esc_html( $label . $ind ) . '</a></th>';
};
?>
<div class="remindmii-admin-page">
<?php if ( 'user_data_deleted' === $notice ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'User data deleted successfully.', 'remindmii' ); ?></p></div>
<?php endif; ?>

<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:12px">
<input type="hidden" name="page" value="remindmii" />
<input type="hidden" name="remindmii_tab" value="users" />
<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search login, name or email…', 'remindmii' ); ?>" class="regular-text" />
<?php submit_button( __( 'Search', 'remindmii' ), 'secondary', '', false ); ?>
<?php if ( '' !== $search ) : ?>
<a class="button button-link" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Reset', 'remindmii' ); ?></a>
<?php endif; ?>
</form>

<h2 style="margin-top:0"><?php echo esc_html( sprintf( __( 'All Users (%d)', 'remindmii' ), count( $users ) ) ); ?></h2>

<div class="remindmii-table-wrap">
<table class="widefat striped remindmii-users-table">
<thead>
<tr>
<?php echo $col( __( 'ID', 'remindmii' ), 'id' ); ?>
<?php echo $col( __( 'Login', 'remindmii' ), 'login' ); ?>
<?php echo $col( __( 'Display Name', 'remindmii' ), 'display_name' ); ?>
<?php echo $col( __( 'Email', 'remindmii' ), 'email' ); ?>
<?php echo $col( __( 'Registered', 'remindmii' ), 'registered' ); ?>
<?php echo $col( __( 'Reminders', 'remindmii' ), 'reminders', 'desc' ); ?>
<?php echo $col( __( 'Wishlists', 'remindmii' ), 'wishlists', 'desc' ); ?>
<?php echo $col( __( 'Points', 'remindmii' ), 'points', 'desc' ); ?>
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
private function render_tab_logs( $filters, $logs, $paged = 1, $total = 0, $notice = '' ) {
?>
<div class="remindmii-admin-page">
<?php if ( 'retry_success' === $notice ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Notification resent successfully.', 'remindmii' ); ?></p></div>
<?php elseif ( 'retry_failed' === $notice ) : ?>
<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Retry failed — could not send notification.', 'remindmii' ); ?></p></div>
<?php endif; ?>
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
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px">
<?php wp_nonce_field( 'remindmii_export_logs', 'remindmii_export_nonce' ); ?>
<input type="hidden" name="action" value="remindmii_export_logs" />
<input type="hidden" name="log_status" value="<?php echo esc_attr( $filters['status'] ); ?>" />
<input type="hidden" name="log_channel" value="<?php echo esc_attr( $filters['channel'] ); ?>" />
<?php submit_button( __( 'Export CSV', 'remindmii' ), 'secondary', 'submit', false ); ?>
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
<?php if ( 'failed' === $log['status'] ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:4px">
<?php wp_nonce_field( 'remindmii_retry_notification_' . $log['id'] ); ?>
<input type="hidden" name="action" value="remindmii_retry_notification" />
<input type="hidden" name="log_id" value="<?php echo absint( $log['id'] ); ?>" />
<button type="submit" class="button button-small"><?php esc_html_e( 'Retry', 'remindmii' ); ?></button>
</form>
<?php endif; ?>
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
<?php
$per_page  = 50;
$num_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
if ( $num_pages > 1 ) :
	$page_links = paginate_links( array(
		'base'      => add_query_arg( 'paged', '%#%', add_query_arg( array(
			'page'          => 'remindmii',
			'remindmii_tab' => 'logs',
			'log_status'    => $filters['status'],
			'log_channel'   => $filters['channel'],
		), admin_url( 'admin.php' ) ) ),
		'format'    => '',
		'current'   => $paged,
		'total'     => $num_pages,
		'prev_text' => '&laquo;',
		'next_text' => '&raquo;',
	) );
?>
<div class="tablenav bottom" style="margin-top:8px">
<div class="tablenav-pages">
<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', $total, 'remindmii' ), number_format_i18n( $total ) ) ); ?></span>
<?php echo $page_links; // phpcs:ignore WordPress.Security.EscapeOutput -- paginate_links() returns safe HTML ?>
</div>
</div>
<?php endif; ?>
</div>
<?php
}

/**
 * Render the Settings tab.
 */
private function render_tab_settings() {
	// Check if required tables exist.
	global $wpdb;
	$critical_tables = array(
		$wpdb->prefix . 'remindmii_reminders',
		$wpdb->prefix . 'remindmii_merchants',
	);
	$missing = array();
	foreach ( $critical_tables as $table ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$missing[] = $table;
		}
	}
	?>
	<?php if ( ! empty( $missing ) ) : ?>
	<div class="notice notice-error is-dismissible"><p>
		<?php esc_html_e( 'Database schema issue: the following tables are missing. Deactivate and reactivate the plugin to recreate them.', 'remindmii' ); ?>
		<br /><code><?php echo esc_html( implode( ', ', $missing ) ); ?></code>
	</p></div>
	<?php endif; ?>
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
 * Return ad performance stats for all merchants.
 *
 * @return array<int, array<string, mixed>>
 */
private function get_merchant_ad_stats() {
	global $wpdb;

	$rows = $wpdb->get_results(
		"SELECT a.id, a.title, a.impressions, a.clicks, a.is_active, m.name AS merchant_name
		 FROM {$wpdb->prefix}remindmii_merchant_ads a
		 LEFT JOIN {$wpdb->prefix}remindmii_merchants m ON m.id = a.merchant_id
		 ORDER BY m.name ASC, a.impressions DESC",
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : array();
}

/**
 * Return ads with visual properties for admin preview cards.
 *
 * @return array<int, array<string, mixed>>
 */
private function get_merchant_ads_for_preview() {
	global $wpdb;

	$rows = $wpdb->get_results(
		"SELECT a.id, a.title, a.description, a.image_url, a.background_color, a.text_color,
		        a.cta_text, a.cta_url, a.is_active, m.name AS merchant_name
		 FROM {$wpdb->prefix}remindmii_merchant_ads a
		 LEFT JOIN {$wpdb->prefix}remindmii_merchants m ON m.id = a.merchant_id
		 ORDER BY m.name ASC, a.id ASC",
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : array();
}

/**
 * Return top N users by total_points.
 *
 * @param int $limit Number of users to return.
 * @return array<int, array<string, mixed>>
 */
private function get_leaderboard( $limit = 10 ) {
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.user_id, s.total_points, s.current_streak, s.total_completed,
			        u.display_name, u.user_login
			 FROM {$wpdb->prefix}remindmii_user_stats s
			 LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id
			 WHERE s.total_points > 0
			 ORDER BY s.total_points DESC
			 LIMIT %d",
			absint( $limit )
		),
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : array();
}

/**
 * Return sent/failed notification counts per day for the last 7 days.
 *
 * @return array<string, array<string, int>> Keyed by Y-m-d date.
 */
private function get_last_7_days_stats() {
	global $wpdb;

	$since = gmdate( 'Y-m-d', strtotime( '-6 days' ) );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE(sent_at) AS day, status, COUNT(*) AS cnt
			 FROM {$wpdb->prefix}remindmii_notifications_log
			 WHERE sent_at >= %s AND status IN ('sent','failed')
			 GROUP BY DATE(sent_at), status
			 ORDER BY day ASC",
			$since
		),
		ARRAY_A
	);

	$days = array();
	for ( $i = 6; $i >= 0; $i-- ) {
		$days[ gmdate( 'Y-m-d', strtotime( "-{$i} days" ) ) ] = array( 'sent' => 0, 'failed' => 0 );
	}
	foreach ( (array) $rows as $row ) {
		if ( isset( $days[ $row['day'] ] ) ) {
			$days[ $row['day'] ][ $row['status'] ] = (int) $row['cnt'];
		}
	}

	return $days;
}

/**
 * Return all WP users annotated with Remindmii data counts.
 *
 * @return array<int, array<string, mixed>>
 */
private function get_users_list( $search = '', $orderby = 'id', $order = 'ASC' ) {
global $wpdb;

$allowed_orderby = array( 'id', 'login', 'display_name', 'email', 'registered', 'reminders', 'wishlists', 'points' );
$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'id';
$order           = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';

$args = array( 'fields' => array( 'ID', 'user_login', 'display_name', 'user_email', 'user_registered' ) );
if ( '' !== $search ) {
	$args['search']         = '*' . $search . '*';
	$args['search_columns'] = array( 'user_login', 'display_name', 'user_email' );
}
$wp_users = get_users( $args );
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

usort( $result, function ( $a, $b ) use ( $orderby, $order ) {
	$va  = $a[ $orderby ];
	$vb  = $b[ $orderby ];
	$cmp = is_numeric( $va ) && is_numeric( $vb ) ? ( $va <=> $vb ) : strcasecmp( (string) $va, (string) $vb );
	return 'DESC' === $order ? -$cmp : $cmp;
} );

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

	<!-- Generate test data button -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:24px">
		<?php wp_nonce_field( 'remindmii_generate_test_data', 'remindmii_nonce' ); ?>
		<input type="hidden" name="action" value="remindmii_generate_test_data" />
		<button type="submit" class="button button-secondary" onclick="return confirm('Create test merchant and ads?');"><?php esc_html_e( 'Generate Test Data', 'remindmii' ); ?></button>
	</form>

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

	<?php
	$edit_id       = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
	$editing_merch = null;
	foreach ( (array) $merchants as $m ) {
		if ( (int) $m->id === $edit_id ) {
			$editing_merch = $m;
			break;
		}
	}
	if ( $editing_merch ) : ?>
	<div class="remindmii-admin-card" style="max-width:600px;margin-bottom:24px;border-left:4px solid #2271b1;padding-left:16px">
		<h3><?php esc_html_e( 'Edit Merchant', 'remindmii' ); ?> #<?php echo absint( $editing_merch->id ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'remindmii_merchant_update_' . $editing_merch->id, 'remindmii_merchant_nonce' ); ?>
			<input type="hidden" name="action" value="remindmii_merchant_update" />
			<input type="hidden" name="merchant_id" value="<?php echo absint( $editing_merch->id ); ?>" />
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Name', 'remindmii' ); ?></th><td><input type="text" name="merchant_name" class="regular-text" value="<?php echo esc_attr( $editing_merch->name ); ?>" required /></td></tr>
				<tr><th><?php esc_html_e( 'Category', 'remindmii' ); ?></th><td><input type="text" name="merchant_category" class="regular-text" value="<?php echo esc_attr( $editing_merch->category ?? '' ); ?>" /></td></tr>
				<tr><th><?php esc_html_e( 'Logo URL', 'remindmii' ); ?></th><td><input type="url" name="merchant_logo_url" class="regular-text" value="<?php echo esc_attr( $editing_merch->logo_url ?? '' ); ?>" /></td></tr>
				<tr><th><?php esc_html_e( 'Website', 'remindmii' ); ?></th><td><input type="url" name="merchant_website_url" class="regular-text" value="<?php echo esc_attr( $editing_merch->website_url ?? '' ); ?>" /></td></tr>
			</table>
			<?php submit_button( __( 'Save Changes', 'remindmii' ), 'primary', 'submit', false ); ?>
			<a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Cancel', 'remindmii' ); ?></a>
		</form>
	</div>
	<?php endif; ?>

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
					<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', absint( $m->id ), $base_url ) ); ?>"><?php esc_html_e( 'Edit', 'remindmii' ); ?></a>
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

	<!-- Ad performance report -->
	<?php $ad_stats = $this->get_merchant_ad_stats(); ?>
	<?php if ( ! empty( $ad_stats ) ) : ?>
	<h2 style="margin-top:32px"><?php esc_html_e( 'Ad Performance', 'remindmii' ); ?></h2>
	<table class="widefat striped">
		<thead><tr>
			<th><?php esc_html_e( 'Merchant', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Ad Title', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Status', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Impressions', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'Clicks', 'remindmii' ); ?></th>
			<th><?php esc_html_e( 'CTR', 'remindmii' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $ad_stats as $ad ) :
			$ctr = $ad['impressions'] > 0 ? round( ( $ad['clicks'] / $ad['impressions'] ) * 100, 2 ) : 0;
		?>
			<tr>
				<td><?php echo esc_html( $ad['merchant_name'] ); ?></td>
				<td><?php echo esc_html( $ad['title'] ); ?></td>
				<td><?php echo $ad['is_active'] ? '<span style="color:green">&#10003; Active</span>' : '<span style="color:#999">Inactive</span>'; ?></td>
				<td><?php echo esc_html( number_format_i18n( (int) $ad['impressions'] ) ); ?></td>
				<td><?php echo esc_html( number_format_i18n( (int) $ad['clicks'] ) ); ?></td>
				<td><?php echo esc_html( $ctr . '%' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<!-- Ad preview cards -->
	<?php $preview_ads = $this->get_merchant_ads_for_preview(); ?>
	<?php if ( ! empty( $preview_ads ) ) : ?>
	<h2 style="margin-top:32px"><?php esc_html_e( 'Ad Previews', 'remindmii' ); ?></h2>
	<div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:24px">
	<?php foreach ( $preview_ads as $ad ) :
		$bg    = esc_attr( $ad['background_color'] ?: '#3B82F6' );
		$fg    = esc_attr( $ad['text_color'] ?: '#FFFFFF' );
		$alpha = $ad['is_active'] ? '1' : '0.5';
	?>
		<div style="opacity:<?php echo esc_attr( $alpha ); ?>;width:260px;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.15);font-family:Arial,sans-serif;font-size:13px">
			<?php if ( ! empty( $ad['image_url'] ) ) : ?>
				<img src="<?php echo esc_url( $ad['image_url'] ); ?>" alt="" style="width:100%;height:120px;object-fit:cover;display:block">
			<?php endif; ?>
			<div style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;padding:14px 16px">
				<div style="font-size:11px;opacity:.75;margin-bottom:4px"><?php echo esc_html( $ad['merchant_name'] ?? '' ); ?></div>
				<div style="font-size:15px;font-weight:700;margin-bottom:6px"><?php echo esc_html( $ad['title'] ); ?></div>
				<?php if ( ! empty( $ad['description'] ) ) : ?>
					<div style="font-size:12px;opacity:.85;margin-bottom:10px;line-height:1.4"><?php echo esc_html( wp_trim_words( $ad['description'], 12 ) ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $ad['cta_text'] ) ) : ?>
					<span style="display:inline-block;background:rgba(0,0,0,.2);border-radius:4px;padding:5px 12px;font-size:12px;font-weight:600"><?php echo esc_html( $ad['cta_text'] ); ?></span>
				<?php endif; ?>
				<?php if ( ! $ad['is_active'] ) : ?>
					<div style="margin-top:8px;font-size:11px;opacity:.7"><?php esc_html_e( 'Inactive', 'remindmii' ); ?></div>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
	</div>
	<?php endif; ?>

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
	wp_safe_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants', 'remindmii_notice' => 'Merchant+created.' ), admin_url( 'admin.php' ) ) );
	exit;
}

/** Handle update merchant POST. */
public function handle_merchant_update() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$id = absint( $_POST['merchant_id'] ?? 0 );
	if ( $id <= 0 ) { wp_die( esc_html__( 'Invalid merchant ID.', 'remindmii' ) ); }
	check_admin_referer( 'remindmii_merchant_update_' . $id, 'remindmii_merchant_nonce' );
	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'remindmii_merchants',
		array(
			'name'        => sanitize_text_field( wp_unslash( (string) ( $_POST['merchant_name'] ?? '' ) ) ),
			'category'    => sanitize_text_field( wp_unslash( (string) ( $_POST['merchant_category'] ?? '' ) ) ),
			'logo_url'    => esc_url_raw( wp_unslash( (string) ( $_POST['merchant_logo_url'] ?? '' ) ) ),
			'website_url' => esc_url_raw( wp_unslash( (string) ( $_POST['merchant_website_url'] ?? '' ) ) ),
		),
		array( 'id' => $id ),
		array( '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);
	wp_safe_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants', 'remindmii_notice' => 'Merchant+updated.' ), admin_url( 'admin.php' ) ) );
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
	wp_safe_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants' ), admin_url( 'admin.php' ) ) );
	exit;
}

/** Generate test data for development. */
public function handle_generate_test_data() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'remindmii_generate_test_data', 'remindmii_nonce' );

	global $wpdb;
	$admin_users = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( empty( $admin_users ) ) { wp_die( 'No admin user found.' ); }
	$admin_id = $admin_users[0]->ID;

	$merchant_data = array(
		'name'        => 'Test Merchant ' . gmdate( 'Y-m-d H:i:s' ),
		'category'    => 'Testing',
		'logo_url'    => 'https://via.placeholder.com/64?text=Test',
		'website_url' => 'https://example.com',
		'is_active'   => 1,
	);
	$wpdb->insert( $wpdb->prefix . 'remindmii_merchants', $merchant_data, array( '%s', '%s', '%s', '%s', '%d' ) );
	$merchant_id = (int) $wpdb->insert_id;

	$wpdb->replace(
		$wpdb->prefix . 'remindmii_merchant_users',
		array( 'merchant_id' => $merchant_id, 'user_id' => $admin_id, 'role' => 'admin' ),
		array( '%d', '%d', '%s' )
	);

	$now = current_time( 'mysql' );
	for ( $i = 1; $i <= 3; $i++ ) {
		$wpdb->insert(
			$wpdb->prefix . 'remindmii_merchant_ads',
			array(
				'merchant_id'       => $merchant_id,
				'title'             => "Test Ad #{$i}",
				'description'       => "This is test ad number {$i}.",
				'image_url'         => "https://via.placeholder.com/400x200?text=Ad+{$i}",
				'background_color'  => array( '#3B82F6', '#10B981', '#F59E0B' )[ $i - 1 ] ?? '#3B82F6',
				'text_color'        => '#FFFFFF',
				'target_gender'     => wp_json_encode( array( 'all' ) ),
				'target_age_min'    => 18,
				'target_age_max'    => 65,
				'target_categories' => wp_json_encode( array( 'all' ) ),
				'cta_text'          => 'Learn More',
				'cta_url'           => 'https://example.com',
				'is_active'         => 1,
				'impressions'       => wp_rand( 0, 100 ),
				'clicks'            => wp_rand( 0, 20 ),
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants', 'remindmii_notice' => 'Test+data+created.' ), admin_url( 'admin.php' ) ) );
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
	wp_safe_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants', 'remindmii_notice' => 'User+assigned.' ), admin_url( 'admin.php' ) ) );
	exit;
}

/** Handle remove user from merchant. */
public function handle_merchant_remove_user() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$id = absint( $_POST['merchant_user_id'] ?? 0 );
	check_admin_referer( 'remindmii_merchant_remove_user_' . $id, 'remindmii_merchant_nonce' );
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'remindmii_merchant_users', array( 'id' => $id ), array( '%d' ) );
	wp_safe_redirect( add_query_arg( array( 'page' => 'remindmii', 'remindmii_tab' => 'merchants' ), admin_url( 'admin.php' ) ) );
	exit;
}

/** Handle retry of a failed notification. */
public function handle_retry_notification() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$log_id = absint( $_POST['log_id'] ?? 0 );
	if ( $log_id <= 0 ) { wp_die( esc_html__( 'Invalid log ID.', 'remindmii' ) ); }
	check_admin_referer( 'remindmii_retry_notification_' . $log_id );
	$cron   = new Remindmii_Cron();
	$result = $cron->retry_from_log( $log_id );
	wp_safe_redirect( add_query_arg( array(
		'page'             => 'remindmii',
		'remindmii_tab'    => 'logs',
		'remindmii_notice' => $result ? 'retry_success' : 'retry_failed',
	), admin_url( 'admin.php' ) ) );
	exit;
}

/** Handle repair pages POST. */
public function handle_repair_pages() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to perform this action.', 'remindmii' ) );
	}
	check_admin_referer( 'remindmii_repair_pages' );
	Remindmii_Installer::repair_pages();
	wp_safe_redirect( add_query_arg( array(
		'page'             => 'remindmii',
		'remindmii_tab'    => 'dashboard',
		'remindmii_notice' => 'pages_repaired',
	), admin_url( 'admin.php' ) ) );
	exit;
}

/** Handle CSV export of notification logs. */
public function handle_export_logs() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'remindmii_export_logs', 'remindmii_export_nonce' );

	$allowed_statuses = array( '', 'sent', 'failed', 'preview' );
	$allowed_channels = array( '', 'email' );
	$status  = isset( $_POST['log_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['log_status'] ) ) : '';
	$channel = isset( $_POST['log_channel'] ) ? sanitize_key( wp_unslash( (string) $_POST['log_channel'] ) ) : '';
	$filters = array(
		'status'  => in_array( $status, $allowed_statuses, true ) ? $status : '',
		'channel' => in_array( $channel, $allowed_channels, true ) ? $channel : '',
	);

	$logs = $this->get_notification_logs( 5000, $filters, 1 );

	$filename = 'remindmii-logs-' . gmdate( 'Y-m-d' ) . '.csv';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'ID', 'User ID', 'Reminder ID', 'Type', 'Channel', 'Status', 'Message', 'Sent At', 'Created At' ) );
	foreach ( $logs as $log ) {
		fputcsv( $out, array(
			$log['id'],
			$log['user_id'],
			$log['reminder_id'],
			$log['notification_type'],
			$log['channel'],
			$log['status'],
			$log['message'],
			$log['sent_at'] ?? '',
			$log['created_at'],
		) );
	}
	fclose( $out );
	exit;
}
}
