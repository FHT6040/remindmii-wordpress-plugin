<?php
/**
 * Template: Custom login / register / lost-password page.
 * Rendered by the [remindmii_login] shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allowed_tabs = array( 'login', 'register', 'lost_password' );
$tab          = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'login';
$tab          = in_array( $tab, $allowed_tabs, true ) ? $tab : 'login';

$error_code = isset( $_GET['login_error'] ) ? sanitize_key( (string) $_GET['login_error'] ) : '';
$error_msg  = $error_code ? Remindmii_Login::get_error_message( $error_code ) : '';
$success    = isset( $_GET['rmsg'] ) ? sanitize_key( (string) $_GET['rmsg'] ) : '';

$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_GET['redirect_to'] ) ) : '';

$tab_url = function ( $t ) {
	return esc_url( add_query_arg( 'tab', $t, remove_query_arg( array( 'login_error', 'rmsg' ) ) ) );
};
?>
<div class="remindmii-login-wrap">

	<div class="remindmii-login-logo">
		<?php
		$logo = get_theme_mod( 'custom_logo' );
		if ( $logo ) {
			echo wp_get_attachment_image( $logo, 'medium' );
		} else {
			echo '<span class="remindmii-login-site-name">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		}
		?>
	</div>

	<?php if ( 'lost_password' !== $tab ) : ?>
	<nav class="remindmii-login-tabs" aria-label="<?php esc_attr_e( 'Authentication tabs', 'remindmii' ); ?>">
		<a href="<?php echo $tab_url( 'login' ); ?>"
		   class="remindmii-login-tab <?php echo 'login' === $tab ? 'is-active' : ''; ?>">
			<?php esc_html_e( 'Log in', 'remindmii' ); ?>
		</a>
		<a href="<?php echo $tab_url( 'register' ); ?>"
		   class="remindmii-login-tab <?php echo 'register' === $tab ? 'is-active' : ''; ?>">
			<?php esc_html_e( 'Create account', 'remindmii' ); ?>
		</a>
	</nav>
	<?php endif; ?>

	<div class="remindmii-login-box">

		<?php if ( $error_msg ) : ?>
		<p class="remindmii-login-notice remindmii-login-notice--error" role="alert">
			<?php echo esc_html( $error_msg ); ?>
		</p>
		<?php endif; ?>

		<?php if ( 'reset_sent' === $success ) : ?>
		<p class="remindmii-login-notice remindmii-login-notice--success" role="status">
			<?php esc_html_e( 'If an account exists with that email, a reset link has been sent. Check your inbox.', 'remindmii' ); ?>
		</p>
		<?php endif; ?>

		<?php if ( 'login' === $tab ) : ?>
		<!-- LOGIN -->
		<form method="post" class="remindmii-login-form" novalidate>
			<?php wp_nonce_field( 'remindmii_login', '_remindmii_nonce' ); ?>
			<input type="hidden" name="remindmii_login_action" value="login" />
			<?php if ( $redirect_to ) : ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
			<?php endif; ?>

			<div class="remindmii-login-field">
				<label class="remindmii-login-label" for="rml-username">
					<?php esc_html_e( 'Email or username', 'remindmii' ); ?>
				</label>
				<input
					id="rml-username"
					class="remindmii-login-input"
					type="text"
					name="username"
					autocomplete="username"
					required
					autofocus
				/>
			</div>

			<div class="remindmii-login-field">
				<label class="remindmii-login-label" for="rml-password">
					<?php esc_html_e( 'Password', 'remindmii' ); ?>
				</label>
				<input
					id="rml-password"
					class="remindmii-login-input"
					type="password"
					name="password"
					autocomplete="current-password"
					required
				/>
			</div>

			<label class="remindmii-login-remember">
				<input type="checkbox" name="remember" value="1" />
				<?php esc_html_e( 'Remember me', 'remindmii' ); ?>
			</label>

			<button type="submit" class="remindmii-login-btn">
				<?php esc_html_e( 'Log in', 'remindmii' ); ?>
			</button>

			<a href="<?php echo $tab_url( 'lost_password' ); ?>" class="remindmii-login-link">
				<?php esc_html_e( 'Forgot your password?', 'remindmii' ); ?>
			</a>
		</form>

		<?php elseif ( 'register' === $tab ) : ?>
		<!-- REGISTER -->
		<form method="post" class="remindmii-login-form" novalidate>
			<?php wp_nonce_field( 'remindmii_register', '_remindmii_nonce' ); ?>
			<input type="hidden" name="remindmii_login_action" value="register" />

			<div class="remindmii-login-field">
				<label class="remindmii-login-label" for="rml-fullname">
					<?php esc_html_e( 'Full name', 'remindmii' ); ?>
				</label>
				<input
					id="rml-fullname"
					class="remindmii-login-input"
					type="text"
					name="fullname"
					autocomplete="name"
					autofocus
				/>
			</div>

			<div class="remindmii-login-field">
				<label class="remindmii-login-label" for="rml-reg-email">
					<?php esc_html_e( 'Email address', 'remindmii' ); ?>
				</label>
				<input
					id="rml-reg-email"
					class="remindmii-login-input"
					type="email"
					name="email"
					autocomplete="email"
					required
				/>
			</div>

			<div class="remindmii-login-field">
				<label class="remindmii-login-label" for="rml-reg-password">
					<?php esc_html_e( 'Password', 'remindmii' ); ?>
				</label>
				<input
					id="rml-reg-password"
					class="remindmii-login-input"
					type="password"
					name="password"
					autocomplete="new-password"
					required
					minlength="6"
				/>
			</div>

			<button type="submit" class="remindmii-login-btn">
				<?php esc_html_e( 'Create account', 'remindmii' ); ?>
			</button>
		</form>

		<?php elseif ( 'lost_password' === $tab ) : ?>
		<!-- LOST PASSWORD -->
		<h2 class="remindmii-login-heading"><?php esc_html_e( 'Reset your password', 'remindmii' ); ?></h2>
		<p class="remindmii-login-desc">
			<?php esc_html_e( 'Enter your email address and we will send you a reset link.', 'remindmii' ); ?>
		</p>

		<form method="post" class="remindmii-login-form" novalidate>
			<?php wp_nonce_field( 'remindmii_lost_password', '_remindmii_nonce' ); ?>
			<input type="hidden" name="remindmii_login_action" value="lost_password" />

			<div class="remindmii-login-field">
				<label class="remindmii-login-label" for="rml-lp-email">
					<?php esc_html_e( 'Email address', 'remindmii' ); ?>
				</label>
				<input
					id="rml-lp-email"
					class="remindmii-login-input"
					type="email"
					name="email"
					autocomplete="email"
					required
					autofocus
				/>
			</div>

			<button type="submit" class="remindmii-login-btn">
				<?php esc_html_e( 'Send reset link', 'remindmii' ); ?>
			</button>

			<a href="<?php echo $tab_url( 'login' ); ?>" class="remindmii-login-link">
				<?php esc_html_e( '&#8592; Back to login', 'remindmii' ); ?>
			</a>
		</form>

		<?php endif; ?>
	</div><!-- .remindmii-login-box -->

</div><!-- .remindmii-login-wrap -->
