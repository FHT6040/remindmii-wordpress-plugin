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
	 * Render placeholder admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap remindmii-admin-page">
			<h1><?php echo esc_html__( 'Remindmii', 'remindmii' ); ?></h1>
			<p><?php echo esc_html__( 'Manage the plugin defaults for new and backfilled Remindmii users.', 'remindmii' ); ?></p>

			<form method="post" action="options.php" class="remindmii-admin-form">
				<?php
				settings_fields( 'remindmii_settings_group' );
				do_settings_sections( 'remindmii' );
				submit_button( __( 'Save settings', 'remindmii' ) );
				?>
			</form>
		</div>
		<?php
	}
}