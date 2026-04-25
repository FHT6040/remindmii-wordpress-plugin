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
	 * @return array<string, int>
	 */
	public function process_notifications( $dry_run = false ) {
		$summary = array(
			'total'                => 0,
			'previewed'            => 0,
			'sent'                 => 0,
			'failed'               => 0,
			'rescheduled'          => 0,
			'marked_notification'  => 0,
		);

		$reminders = $this->reminders_repository->get_due_for_notifications();

		if ( empty( $reminders ) ) {
			return $summary;
		}

		$summary['total'] = count( $reminders );

		foreach ( $reminders as $reminder ) {
			$summary = $this->process_single_reminder( $reminder, (bool) $dry_run, $summary );
		}

		return $summary;
	}

	/**
	 * Send and record a single reminder notification.
	 *
	 * @param array<string, mixed> $reminder Reminder payload.
	 * @param bool                 $dry_run  Whether to preview only.
	 * @param array<string, int>   $summary  Running summary.
	 * @return array<string, int>
	 */
	private function process_single_reminder( $reminder, $dry_run = false, $summary = array() ) {
		if ( $dry_run ) {
			$this->log_notification(
				$reminder,
				'preview',
				__( 'Dry run: reminder would be sent.', 'remindmii' )
			);
			++$summary['previewed'];
			return $summary;
		}

		$sent = $this->send_email_notification( $reminder );

		$this->log_notification(
			$reminder,
			$sent ? 'sent' : 'failed',
			$sent ? __( 'Reminder email sent.', 'remindmii' ) : __( 'Reminder email failed to send.', 'remindmii' )
		);

		if ( ! $sent ) {
			++$summary['failed'];
			return $summary;
		}

		++$summary['sent'];

		if ( ! empty( $reminder['is_recurring'] ) ) {
			if ( $this->reminders_repository->reschedule_recurring( $reminder ) ) {
				++$summary['rescheduled'];
			}
			return $summary;
		}

		if ( $this->reminders_repository->mark_notification_sent( (int) $reminder['id'], (int) $reminder['user_id'] ) ) {
			++$summary['marked_notification'];
		}

		return $summary;
	}

	/**
	 * Send the reminder email (HTML + plain-text multipart).
	 *
	 * @param array<string, mixed> $reminder Reminder payload.
	 * @return bool
	 */
	private function send_email_notification( $reminder ) {
		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$site_url     = home_url();
		$profiles_repo      = new Remindmii_User_Profiles_Repository();
		$unsubscribe_token  = $profiles_repo->get_or_create_unsubscribe_token( (int) $reminder['user_id'] );
		$unsubscribe_url    = $unsubscribe_token
			? rest_url( 'remindmii/v1/unsubscribe?token=' . rawurlencode( $unsubscribe_token ) )
			: '';
		$display_name = ! empty( $reminder['profile_full_name'] ) ? $reminder['profile_full_name'] : __( 'there', 'remindmii' );
		$subject      = sprintf( __( '[%1$s] Reminder: %2$s', 'remindmii' ), $site_name, $reminder['title'] );
		$date_str     = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $reminder['reminder_date'] ) );

		// --- HTML body ---
		$desc_html = '';
		if ( ! empty( $reminder['description'] ) ) {
			$desc_html = '<p style="margin:0 0 12px"><strong>' . esc_html__( 'Details:', 'remindmii' ) . '</strong><br>'
				. nl2br( esc_html( wp_strip_all_tags( (string) $reminder['description'] ) ) ) . '</p>';
		}

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,sans-serif">'
			. '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:32px 0"><tr><td align="center">'
			. '<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:560px">'
			. '<tr><td style="background:#6366f1;padding:24px 32px">'
			.   '<p style="margin:0;color:#fff;font-size:22px;font-weight:700">📅 ' . esc_html( $site_name ) . '</p>'
			. '</td></tr>'
			. '<tr><td style="padding:32px">'
			.   '<p style="margin:0 0 12px;font-size:16px">' . sprintf( esc_html__( 'Hi %s,', 'remindmii' ), esc_html( $display_name ) ) . '</p>'
			.   '<p style="margin:0 0 20px;font-size:15px;color:#374151">'
			.     sprintf( esc_html__( 'This is your reminder for:', 'remindmii' ) )
			.   '</p>'
			.   '<div style="background:#f3f4f6;border-left:4px solid #6366f1;border-radius:4px;padding:16px 20px;margin:0 0 20px">'
			.     '<p style="margin:0 0 6px;font-size:18px;font-weight:700;color:#111827">' . esc_html( $reminder['title'] ) . '</p>'
			.     '<p style="margin:0;font-size:14px;color:#6b7280">🗓 ' . esc_html( $date_str ) . '</p>'
			.   '</div>'
			.   $desc_html
			.   '<p style="margin:0 0 24px;font-size:13px;color:#9ca3af">'
			.     sprintf( esc_html__( 'Sent %d hour(s) before your reminder based on your Remindmii settings.', 'remindmii' ), max( 1, (int) $reminder['notification_hours'] ) )
			.   '</p>'
			.   '<p style="margin:0"><a href="' . esc_url( $site_url ) . '" style="display:inline-block;background:#6366f1;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:600">'
			.     esc_html__( 'Open Remindmii', 'remindmii' )
			.   '</a></p>'
			. '</td></tr>'
			. '<tr><td style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb">'
			.   '<p style="margin:0;font-size:12px;color:#9ca3af;text-align:center">&copy; ' . esc_html( $site_name ) . ' &mdash; <a href="' . esc_url( $site_url ) . '" style="color:#6366f1">' . esc_html( $site_url ) . '</a>'
			.   ( $unsubscribe_url ? ' &mdash; <a href="' . esc_url( $unsubscribe_url ) . '" style="color:#9ca3af">' . esc_html__( 'Unsubscribe', 'remindmii' ) . '</a>' : '' )
			.   '</p>'
			. '</td></tr>'
			. '</table></td></tr></table></body></html>';

		// --- Plain-text fallback ---
		$plain_lines = array(
			sprintf( __( 'Hi %s,', 'remindmii' ), $display_name ),
			'',
			sprintf( __( 'This is your reminder for "%s".', 'remindmii' ), $reminder['title'] ),
			sprintf( __( 'Scheduled for: %s', 'remindmii' ), $date_str ),
		);
		if ( ! empty( $reminder['description'] ) ) {
			$plain_lines[] = '';
			$plain_lines[] = __( 'Details:', 'remindmii' );
			$plain_lines[] = wp_strip_all_tags( (string) $reminder['description'] );
		}
		$plain_lines[] = '';
		$plain_lines[] = sprintf( __( 'Sent %d hour(s) before based on your Remindmii profile settings.', 'remindmii' ), max( 1, (int) $reminder['notification_hours'] ) );
		$plain_lines[] = '';
		$plain_lines[] = $site_url;
		if ( $unsubscribe_url ) {
			$plain_lines[] = '';
			$plain_lines[] = sprintf( __( 'Unsubscribe: %s', 'remindmii' ), $unsubscribe_url );
		}

		// WP multipart: send HTML with Content-Type header; WP auto-adds text/plain when $message is array.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $reminder['profile_email'], $subject, $html, $headers );
	}

	/**
	 * Write an entry to the notifications log.
	 *
	 * @param array<string, mixed> $reminder Reminder payload.
	 * @param string               $status   Delivery status.
	 * @param string               $message  Log message.
	 * @return void
	 */
	/**
	 * Retry a previously failed notification by log ID.
	 *
	 * @param int $log_id Notification log row ID.
	 * @return bool True on successful send.
	 */
	public function retry_from_log( $log_id ) {
		global $wpdb;

		$log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}remindmii_notifications_log WHERE id = %d AND status = 'failed'",
				absint( $log_id )
			),
			ARRAY_A
		);

		if ( ! $log ) {
			return false;
		}

		$context = json_decode( $log['context'], true );
		if ( ! is_array( $context ) ) {
			return false;
		}

		$reminder = array_merge(
			$context,
			array(
				'id'      => (int) $log['reminder_id'],
				'user_id' => (int) $log['user_id'],
			)
		);

		$sent = $this->send_email_notification( $reminder );

		$wpdb->update(
			$wpdb->prefix . 'remindmii_notifications_log',
			array(
				'status'  => $sent ? 'sent' : 'failed',
				'message' => $sent ? __( 'Retry succeeded.', 'remindmii' ) : __( 'Retry failed.', 'remindmii' ),
				'sent_at' => $sent ? current_time( 'mysql' ) : null,
			),
			array( 'id' => absint( $log_id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return $sent;
	}

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
