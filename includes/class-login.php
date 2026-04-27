<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remindmii_Login {

	/** Max login/register attempts per IP per minute. */
	const RATE_LIMIT = 5;

	/** Predefined error codes → translated messages. */
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
			'rate_limited'          => __( 'Too many attempts. Please wait a minute and try again.', 'remindmii' ),
			'invalid_key'           => __( 'This password reset link is invalid or has expired. Please request a new one.', 'remindmii' ),
			'passwords_mismatch'    => __( 'Passwords do not match.', 'remindmii' ),
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'handle_form' ), 5 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'login_init', array( $this, 'redirect_from_wp_login' ) );
		add_action( 'template_redirect', array( $this, 'redirect_logged_in_from_login_page' ) );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'logout_url', array( $this, 'filter_logout_url' ), 10, 2 );
		add_filter( 'retrieve_password_message', array( $this, 'filter_reset_email_message' ), 10, 4 );
	}

	/**
	 * Register REST routes for AJAX form handling.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		$actions = array(
			array( 'login',          array( $this, 'rest_login' ) ),
			array( 'register',       array( $this, 'rest_register' ) ),
			array( 'lost-password',  array( $this, 'rest_lost_password' ) ),
			array( 'reset-password', array( $this, 'rest_reset_password' ) ),
		);

		foreach ( $actions as list( $path, $callback ) ) {
			register_rest_route(
				'remindmii/v1/auth',
				$path,
				array(
					'methods'             => 'POST',
					'callback'            => $callback,
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	// ── URL helpers ──────────────────────────────────────────────────────────

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
	 * Point login_url() calls to the custom page.
	 *
	 * @param string $login_url    Default login URL.
	 * @param string $redirect     Redirect-to value.
	 * @param bool   $force_reauth Unused.
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

	// ── Redirects ─────────────────────────────────────────────────────────────

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
		$pass_through = array( 'logout', 'postpass', 'confirm_admin_email' );
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
			return; // Custom page not created yet.
		}

		// For password-reset links (action=rp), preserve key and login params.
		if ( 'rp' === $action || 'resetpass' === $action ) {
			$key      = isset( $_GET['key'] )   ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) )   : '';
			$rp_login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['login'] ) ) : '';
			wp_safe_redirect( add_query_arg( array( 'action' => 'rp', 'key' => $key, 'login' => $rp_login ), $login_url ) );
			exit;
		}

		wp_safe_redirect( $login_url );
		exit;
	}

	/**
	 * Replace the wp-login.php reset link in password-reset emails with the custom page URL.
	 *
	 * @param string   $message    Email message body.
	 * @param string   $key        Reset key.
	 * @param string   $user_login User login name.
	 * @param \WP_User $user_data  User object.
	 * @return string
	 */
	public function filter_reset_email_message( $message, $key, $user_login, $user_data ) {
		$custom_url = $this->get_login_url();
		if ( ! $custom_url ) {
			return $message;
		}

		$custom_reset = add_query_arg(
			array(
				'action' => 'rp',
				'key'    => $key,
				'login'  => rawurlencode( $user_login ),
			),
			$custom_url
		);

		$default_reset = network_site_url(
			'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user_login ),
			'login'
		);

		return str_replace( $default_reset, esc_url( $custom_reset ), $message );
	}

	// ── Rate limiting ─────────────────────────────────────────────────────────

	/**
	 * Check and increment an IP-based rate limit.
	 *
	 * @return bool False when the limit is exceeded.
	 */
	private function check_ip_rate_limit() {
		$ip    = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$key   = 'remindmii_login_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, 60 );
		} else {
			set_transient( $key, $count + 1, 60 );
		}

		return true;
	}

	// ── Server-side form dispatch ─────────────────────────────────────────────

	/**
	 * Dispatch POST form submissions (server-side fallback, no JS required).
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
			case 'reset_password':
				$this->process_reset_password();
				break;
		}
	}

	/** @return void */
	private function process_login() {
		if ( ! $this->check_ip_rate_limit() ) {
			$this->redirect_error( 'rate_limited' );
		}

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
		if ( ! $this->check_ip_rate_limit() ) {
			$this->redirect_error( 'rate_limited', 'register' );
		}

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

		$user_id = $this->create_user( $email, $password, $fullname );
		if ( is_wp_error( $user_id ) ) {
			$this->redirect_error( 'create_failed', 'register' );
		}

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

		retrieve_password( $email ); // Always show success to avoid user enumeration.

		$login_url = $this->get_login_url() ?: home_url( '/' );
		wp_safe_redirect( add_query_arg( array( 'tab' => 'lost_password', 'rmsg' => 'reset_sent' ), $login_url ) );
		exit;
	}

	/** @return void */
	private function process_reset_password() {
		if ( ! isset( $_POST['_remindmii_nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_remindmii_nonce'] ) ), 'remindmii_reset_password' ) ) {
			$this->redirect_error( 'security_failed' );
		}

		$key      = sanitize_text_field( wp_unslash( (string) ( $_POST['rp_key'] ?? '' ) ) );
		$rp_login = sanitize_text_field( wp_unslash( (string) ( $_POST['rp_login'] ?? '' ) ) );
		$pass1    = wp_unslash( (string) ( $_POST['pass1'] ?? '' ) );
		$pass2    = wp_unslash( (string) ( $_POST['pass2'] ?? '' ) );

		$user = check_password_reset_key( $key, $rp_login );
		if ( is_wp_error( $user ) ) {
			$this->redirect_error( 'invalid_key' );
		}
		if ( $pass1 !== $pass2 ) {
			$login_url = $this->get_login_url() ?: home_url( '/' );
			wp_safe_redirect( add_query_arg( array( 'action' => 'rp', 'key' => $key, 'login' => rawurlencode( $rp_login ), 'login_error' => 'passwords_mismatch' ), $login_url ) );
			exit;
		}
		if ( strlen( $pass1 ) < 6 ) {
			$login_url = $this->get_login_url() ?: home_url( '/' );
			wp_safe_redirect( add_query_arg( array( 'action' => 'rp', 'key' => $key, 'login' => rawurlencode( $rp_login ), 'login_error' => 'short_password' ), $login_url ) );
			exit;
		}

		reset_password( $user, $pass1 );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID );
		wp_safe_redirect( home_url( '/remindmii/' ) );
		exit;
	}

	// ── REST callbacks ────────────────────────────────────────────────────────

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_login( $request ) {
		if ( ! $this->check_ip_rate_limit() ) {
			return $this->rest_error( self::get_error_message( 'rate_limited' ), 429 );
		}

		$login    = sanitize_text_field( (string) $request->get_param( 'username' ) );
		$password = (string) $request->get_param( 'password' );

		if ( '' === $login || '' === $password ) {
			return $this->rest_error( self::get_error_message( 'empty_fields' ) );
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => (bool) $request->get_param( 'remember' ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			return $this->rest_error( self::get_error_message( 'invalid_credentials' ) );
		}

		$redirect = $request->get_param( 'redirect_to' );
		$redirect = $redirect ? esc_url_raw( (string) $redirect ) : home_url( '/remindmii/' );

		return new WP_REST_Response( array( 'success' => true, 'redirect' => $redirect ), 200 );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_register( $request ) {
		if ( ! $this->check_ip_rate_limit() ) {
			return $this->rest_error( self::get_error_message( 'rate_limited' ), 429 );
		}

		if ( ! get_option( 'users_can_register' ) ) {
			return $this->rest_error( self::get_error_message( 'registration_disabled' ) );
		}

		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$password = (string) $request->get_param( 'password' );
		$fullname = sanitize_text_field( (string) $request->get_param( 'fullname' ) );

		if ( ! is_email( $email ) ) {
			return $this->rest_error( self::get_error_message( 'invalid_email' ) );
		}
		if ( strlen( $password ) < 6 ) {
			return $this->rest_error( self::get_error_message( 'short_password' ) );
		}
		if ( email_exists( $email ) ) {
			return $this->rest_error( self::get_error_message( 'email_exists' ) );
		}

		$user_id = $this->create_user( $email, $password, $fullname );
		if ( is_wp_error( $user_id ) ) {
			return $this->rest_error( self::get_error_message( 'create_failed' ) );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		return new WP_REST_Response( array( 'success' => true, 'redirect' => home_url( '/remindmii/' ) ), 200 );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_lost_password( $request ) {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		if ( ! $email ) {
			return $this->rest_error( self::get_error_message( 'empty_email' ) );
		}

		retrieve_password( $email ); // Always respond with success.

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'If an account exists with that email, a reset link has been sent. Check your inbox.', 'remindmii' ),
			),
			200
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_reset_password( $request ) {
		$key      = sanitize_text_field( (string) $request->get_param( 'rp_key' ) );
		$rp_login = sanitize_text_field( (string) $request->get_param( 'rp_login' ) );
		$pass1    = (string) $request->get_param( 'pass1' );
		$pass2    = (string) $request->get_param( 'pass2' );

		$user = check_password_reset_key( $key, $rp_login );
		if ( is_wp_error( $user ) ) {
			return $this->rest_error( self::get_error_message( 'invalid_key' ) );
		}
		if ( $pass1 !== $pass2 ) {
			return $this->rest_error( self::get_error_message( 'passwords_mismatch' ) );
		}
		if ( strlen( $pass1 ) < 6 ) {
			return $this->rest_error( self::get_error_message( 'short_password' ) );
		}

		reset_password( $user, $pass1 );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID );

		return new WP_REST_Response( array( 'success' => true, 'redirect' => home_url( '/remindmii/' ) ), 200 );
	}

	// ── Shared helpers ────────────────────────────────────────────────────────

	/**
	 * Create a WP user from email, password and optional display name.
	 *
	 * @param string $email
	 * @param string $password
	 * @param string $fullname
	 * @return int|\WP_Error
	 */
	private function create_user( $email, $password, $fullname ) {
		$base     = sanitize_user( explode( '@', $email )[0], true ) ?: 'user';
		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $suffix++;
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( $fullname ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $fullname ) );
		}

		Remindmii_Installer::ensure_user_records( $user_id );

		return $user_id;
	}

	/**
	 * Build a WP_REST_Response for an error.
	 *
	 * @param string $message
	 * @param int    $status  HTTP status code.
	 * @return \WP_REST_Response
	 */
	private function rest_error( $message, $status = 400 ) {
		return new WP_REST_Response( array( 'success' => false, 'message' => $message ), $status );
	}

	/**
	 * Redirect back to the login page with an error code.
	 *
	 * @param string $code Error code key.
	 * @param string $tab  Which tab to show.
	 * @return never
	 */
	private function redirect_error( $code, $tab = 'login' ) {
		$login_url = $this->get_login_url() ?: home_url( '/' );
		wp_safe_redirect( add_query_arg( array( 'tab' => $tab, 'login_error' => $code ), $login_url ) );
		exit;
	}

	/**
	 * Return a translated error message for a given code (called from templates and REST).
	 *
	 * @param string $code
	 * @return string
	 */
	public static function get_error_message( $code ) {
		$messages = self::error_messages();
		return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
	}
}
