<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Login {

	/** Predefined error codes and their translated messages. */
	private static function error_messages() {
		return array(
			'empty_fields'          => __( 'Please enter your email and password.', 'remindmii' ),
			'invalid_credentials'   => __( 'Invalid email or password.', 'remindmii' ),
			'invalid_email'         => __( 'Please enter a valid email address.', 'remindmii' ),
			'short_password'        => __( 'Password must be at least 6 characters.', 'remindmii' ),
			'email_exists'          => __( 'An account with that email already exists.', 'remindmii' ),
			'registration_disabled' => __( 'User registration is currently disabled.', 'remindmii' ),
			'empty_email'           => __( 'Please enter your email address.', 'remindmii' ),
			'security_failed'       => __( 'Security check failed. Please try again.', 'remindmii' ),
			'create_failed'         => __( 'Could not create account. Please try again.', 'remindmii' ),
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'handle_form' ), 5 );
		add_action( 'login_init', array( $this, 'redirect_from_wp_login' ) );
		add_action( 'template_redirect', array( $this, 'redirect_logged_in_from_login_page' ) );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'logout_url', array( $this, 'filter_logout_url' ), 10, 2 );
	}

	/**
	 * Return the permalink of the custom login page, or empty string if not found.
	 *
	 * @return string
	 */
	public function get_login_url() {
		$page = get_page_by_path( 'remindmii-login' );
		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			return (string) get_permalink( $page );
		}
		return '';
	}

	/**
	 * Redirect already-logged-in users away from the login page.
	 *
	 * @return void
	 */
	public function redirect_logged_in_from_login_page() {
		if ( is_user_logged_in() && is_page( 'remindmii-login' ) ) {
			wp_safe_redirect( home_url( '/remindmii/' ) );
			exit;
		}
	}

	/**
	 * Redirect non-admin requests to wp-login.php to the custom login page.
	 *
	 * @return void
	 */
	public function redirect_from_wp_login() {
		$action = sanitize_key( isset( $_REQUEST['action'] ) ? wp_unslash( (string) $_REQUEST['action'] ) : 'login' );

		// Always let WP handle these natively.
		$pass_through = array( 'logout', 'rp', 'resetpass', 'postpass', 'confirm_admin_email', 'lostpassword' );
		if ( in_array( $action, $pass_through, true ) ) {
			return;
		}

		// Logged-in admins may access wp-login.php directly.
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		// Requests destined for wp-admin are the normal admin login flow — let through.
		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) ) : '';
		if ( $redirect_to && false !== strpos( $redirect_to, '/wp-admin' ) ) {
			return;
		}

		$login_url = $this->get_login_url();
		if ( ! $login_url ) {
			return; // Custom page not created yet; fall through to WP login.
		}

		wp_safe_redirect( $login_url );
		exit;
	}

	/**
	 * Point login_url() calls to the custom page.
	 *
	 * @param string      $login_url    Default login URL.
	 * @param string      $redirect     Redirect-to parameter.
	 * @param bool        $force_reauth Whether to force re-authentication.
	 * @return string
	 */
	public function filter_login_url( $login_url, $redirect, $force_reauth ) {
		$custom = $this->get_login_url();
		if ( ! $custom ) {
			return $login_url;
		}
		if ( $redirect ) {
			$custom = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $custom );
		}
		return $custom;
	}

	/**
	 * After logout, redirect to the custom login page (when no explicit redirect set).
	 *
	 * @param string $logout_url Default logout URL.
	 * @param string $redirect   Caller-specified redirect.
	 * @return string
	 */
	public function filter_logout_url( $logout_url, $redirect ) {
		if ( ! empty( $redirect ) ) {
			return $logout_url;
		}
		$custom = $this->get_login_url();
		if ( $custom ) {
			$logout_url = add_query_arg( 'redirect_to', rawurlencode( $custom ), $logout_url );
		}
		return $logout_url;
	}

	/**
	 * Dispatch POST form submissions.
	 *
	 * @return void
	 */
	public function handle_form() {
		if ( empty( $_POST['remindmii_login_action'] ) ) {
			return;
		}

		switch ( sanitize_key( (string) $_POST['remindmii_login_action'] ) ) {
			case 'login':
				$this->process_login();
				break;
			case 'register':
				$this->process_register();
				break;
			case 'lost_password':
				$this->process_lost_password();
				break;
		}
	}

	/** @return void */
	private function process_login() {
		if ( ! isset( $_POST['_remindmii_nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_remindmii_nonce'] ) ), 'remindmii_login' ) ) {
			$this->redirect_error( 'security_failed' );
		}

		$login    = sanitize_text_field( wp_unslash( (string) ( $_POST['username'] ?? '' ) ) );
		$password = wp_unslash( (string) ( $_POST['password'] ?? '' ) );

		if ( '' === $login || '' === $password ) {
			$this->redirect_error( 'empty_fields' );
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => ! empty( $_POST['remember'] ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			$this->redirect_error( 'invalid_credentials' );
		}

		$redirect_to = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : home_url( '/remindmii/' );
		wp_safe_redirect( $redirect_to );
		exit;
	}

	/** @return void */
	private function process_register() {
		if ( ! isset( $_POST['_remindmii_nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_remindmii_nonce'] ) ), 'remindmii_register' ) ) {
			$this->redirect_error( 'security_failed', 'register' );
		}

		if ( ! get_option( 'users_can_register' ) ) {
			$this->redirect_error( 'registration_disabled', 'register' );
		}

		$email    = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );
		$password = wp_unslash( (string) ( $_POST['password'] ?? '' ) );
		$fullname = sanitize_text_field( wp_unslash( (string) ( $_POST['fullname'] ?? '' ) ) );

		if ( ! is_email( $email ) ) {
			$this->redirect_error( 'invalid_email', 'register' );
		}

		if ( strlen( $password ) < 6 ) {
			$this->redirect_error( 'short_password', 'register' );
		}

		if ( email_exists( $email ) ) {
			$this->redirect_error( 'email_exists', 'register' );
		}

		// Derive unique username from email local part.
		$base     = sanitize_user( explode( '@', $email )[0], true ) ?: 'user';
		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $suffix++;
		}

		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			$this->redirect_error( 'create_failed', 'register' );
		}

		if ( $fullname ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $fullname ) );
		}

		Remindmii_Installer::ensure_user_records( $user_id );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		wp_safe_redirect( home_url( '/remindmii/' ) );
		exit;
	}

	/** @return void */
	private function process_lost_password() {
		if ( ! isset( $_POST['_remindmii_nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_remindmii_nonce'] ) ), 'remindmii_lost_password' ) ) {
			$this->redirect_error( 'security_failed', 'lost_password' );
		}

		$email = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );

		if ( ! $email ) {
			$this->redirect_error( 'empty_email', 'lost_password' );
		}

		// Always show success to avoid user enumeration.
		retrieve_password( $email );

		$login_url = $this->get_login_url() ?: home_url( '/' );
		wp_safe_redirect( add_query_arg( array( 'tab' => 'lost_password', 'rmsg' => 'reset_sent' ), $login_url ) );
		exit;
	}

	/**
	 * Redirect back to the login page with an error code in the query string.
	 *
	 * @param string $code Error code key.
	 * @param string $tab  Which tab to show (login|register|lost_password).
	 * @return never
	 */
	private function redirect_error( $code, $tab = 'login' ) {
		$login_url = $this->get_login_url() ?: home_url( '/' );
		wp_safe_redirect( add_query_arg( array( 'tab' => $tab, 'login_error' => $code ), $login_url ) );
		exit;
	}

	/**
	 * Return a translated error message for a given error code (called from templates).
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	public static function get_error_message( $code ) {
		$messages = self::error_messages();
		return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
	}
}
