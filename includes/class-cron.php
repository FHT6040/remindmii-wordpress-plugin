<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Cron {
	/**
	 * Reminder data access.
	 *
	 * @var Remindmii_Reminders_Repository
	 */
	private $reminders_repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->reminders_repository = new Remindmii_Reminders_Repository();
	}

	/**
	 * Register cron hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'remindmii_process_notifications', array( $this, 'process_notifications' ), 10, 1 );
	}

	/**
	 * Process due reminder notifications.
	 *
	 * @param bool $dry_run Whether to preview notifications without sending them.
	 * @return void
	 */
	public function process_notifications( $dry_run = false ) {
		$reminders = $this->reminders_repository->get_due_for_notifications();

		if ( empty( $reminders ) ) {
			return;
		}

		foreach ( $reminders as $reminder ) {
			$this->process_single_reminder( $reminder, (bool) $dry_run );
		}
	}

	/**
	 * Send and record a single reminder notification.
	 *
	 * @param array<string, mixed> $reminder Reminder payload.
	 * @param bool                 $dry_run  Whether to preview only.
	 * @return void
	 */
	private function process_single_reminder( $reminder, $dry_run = false ) {
		if ( $dry_run ) {
			$this->log_notification(
				$reminder,
				'preview',
				__( 'Dry run: reminder would be sent.', 'remindmii' )
			);
			return;
		}

		$sent = $this->send_email_notification( $reminder );

		$this->log_notification(
			$reminder,
			$sent ? 'sent' : 'failed',
			$sent ? __( 'Reminder email sent.', 'remindmii' ) : __( 'Reminder email failed to send.', 'remindmii' )
		);

		if ( ! $sent ) {
			return;
		}

		if ( ! empty( $reminder['is_recurring'] ) ) {
			$this->reminders_repository->reschedule_recurring( $reminder );
			return;
		}

		$this->reminders_repository->mark_notification_sent( (int) $reminder['id'], (int) $reminder['user_id'] );
	}

	/**
	 * Send the reminder email.
	 *
	 * @param array<string, mixed> $reminder Reminder payload.
	 * @return bool
	 */
	private function send_email_notification( $reminder ) {
		$site_name     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$display_name  = ! empty( $reminder['profile_full_name'] ) ? $reminder['profile_full_name'] : __( 'there', 'remindmii' );
		$subject       = sprintf( __( '[%1$s] Reminder: %2$s', 'remindmii' ), $site_name, $reminder['title'] );
		$body_lines    = array(
			sprintf( __( 'Hi %s,', 'remindmii' ), $display_name ),
			'',
			sprintf( __( 'This is your reminder for "%s".', 'remindmii' ), $reminder['title'] ),
			sprintf( __( 'Scheduled for: %s', 'remindmii' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $reminder['reminder_date'] ) ) ),
		);

		if ( ! empty( $reminder['description'] ) ) {
			$body_lines[] = '';
			$body_lines[] = __( 'Details:', 'remindmii' );
			$body_lines[] = wp_strip_all_tags( (string) $reminder['description'] );
		}

		$body_lines[] = '';
		$body_lines[] = sprintf( __( 'Sent %d hour(s) before based on your Remindmii profile settings.', 'remindmii' ), max( 1, (int) $reminder['notification_hours'] ) );

		return wp_mail( $reminder['profile_email'], $subject, implode( "\n", $body_lines ) );
	}

	/**
	 * Write an entry to the notifications log.
	 *
	 * @param array<string, mixed> $reminder Reminder payload.
	 * @param string               $status   Delivery status.
	 * @param string               $message  Log message.
	 * @return void
	 */
	private function log_notification( $reminder, $status, $message ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'remindmii_notifications_log',
			array(
				'user_id'           => (int) $reminder['user_id'],
				'reminder_id'       => (int) $reminder['id'],
				'notification_type' => ! empty( $reminder['is_recurring'] ) ? 'recurring_reminder' : 'reminder',
				'channel'           => 'email',
				'status'            => sanitize_key( $status ),
				'message'           => $message,
				'context'           => wp_json_encode(
					array(
						'title'              => $reminder['title'],
						'reminder_date'      => $reminder['reminder_date'],
						'profile_email'      => $reminder['profile_email'],
						'notification_hours' => (int) $reminder['notification_hours'],
					)
				),
				'sent_at'           => 'sent' === $status ? current_time( 'mysql' ) : null,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
