<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="remindmii-merchant-portal" data-remindmii-merchant-portal>

	<?php if ( ! is_user_logged_in() ) : ?>
	<div class="remindmii-auth-message">
		<p><?php echo esc_html__( 'You need to be logged in to access the Merchant Portal.', 'remindmii' ); ?></p>
		<a class="remindmii-button remindmii-button--secondary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
			<?php echo esc_html__( 'Log in', 'remindmii' ); ?>
		</a>
	</div>
	<?php else : ?>

	<!-- Loading state -->
	<div class="remindmii-merchant-loading" data-merchant-loading>
		<span class="remindmii-spinner"></span>
		<?php echo esc_html__( 'Loading merchant portal…', 'remindmii' ); ?>
	</div>

	<!-- No merchant assigned state -->
	<div class="remindmii-merchant-no-access" data-merchant-no-access hidden>
		<div class="remindmii-merchant-no-access__inner">
			<span class="remindmii-merchant-no-access__icon">&#127978;</span>
			<h3><?php echo esc_html__( 'No merchant account', 'remindmii' ); ?></h3>
			<p><?php echo esc_html__( 'Your account is not linked to a merchant. Please contact an administrator.', 'remindmii' ); ?></p>
		</div>
	</div>

	<!-- Main portal (shown once merchant data loads) -->
	<div class="remindmii-merchant-main" data-merchant-main hidden>

		<!-- Header -->
		<div class="remindmii-merchant-header" data-merchant-header>
			<div class="remindmii-merchant-header__logo" data-merchant-logo></div>
			<div class="remindmii-merchant-header__info">
				<h2 class="remindmii-merchant-header__name" data-merchant-name></h2>
				<p class="remindmii-merchant-header__sub"><?php echo esc_html__( 'Merchant Portal', 'remindmii' ); ?></p>
			</div>
		</div>

		<!-- Stats grid -->
		<div class="remindmii-merchant-stats" data-merchant-stats>
			<div class="remindmii-merchant-stat">
				<span class="remindmii-merchant-stat__value" data-merchant-stat-total>—</span>
				<span class="remindmii-merchant-stat__label"><?php echo esc_html__( 'Total ads', 'remindmii' ); ?></span>
			</div>
			<div class="remindmii-merchant-stat">
				<span class="remindmii-merchant-stat__value" data-merchant-stat-active>—</span>
				<span class="remindmii-merchant-stat__label"><?php echo esc_html__( 'Active', 'remindmii' ); ?></span>
			</div>
			<div class="remindmii-merchant-stat">
				<span class="remindmii-merchant-stat__value" data-merchant-stat-impressions>—</span>
				<span class="remindmii-merchant-stat__label"><?php echo esc_html__( 'Impressions', 'remindmii' ); ?></span>
			</div>
			<div class="remindmii-merchant-stat">
				<span class="remindmii-merchant-stat__value" data-merchant-stat-clicks>—</span>
				<span class="remindmii-merchant-stat__label"><?php echo esc_html__( 'Clicks', 'remindmii' ); ?></span>
			</div>
			<div class="remindmii-merchant-stat">
				<span class="remindmii-merchant-stat__value" data-merchant-stat-ctr>—</span>
				<span class="remindmii-merchant-stat__label"><?php echo esc_html__( 'CTR', 'remindmii' ); ?></span>
			</div>
		</div>

		<!-- Ads panel -->
		<div class="remindmii-merchant-ads-panel">
			<div class="remindmii-merchant-ads-panel__header">
				<h3><?php echo esc_html__( 'My Ads', 'remindmii' ); ?></h3>
				<button type="button" class="remindmii-button" data-merchant-new-ad>
					+ <?php echo esc_html__( 'New Ad', 'remindmii' ); ?>
				</button>
			</div>
			<div class="remindmii-merchant-ads-list" data-merchant-ads-list>
				<p class="remindmii-muted"><?php echo esc_html__( 'Loading ads…', 'remindmii' ); ?></p>
			</div>
		</div>

	</div><!-- /data-merchant-main -->

	<!-- Ad form modal -->
	<div class="remindmii-modal-overlay remindmii-merchant-ad-modal" data-merchant-ad-modal hidden>
		<div class="remindmii-modal remindmii-merchant-ad-modal__inner">
			<div class="remindmii-modal__header">
				<h3 data-merchant-modal-title><?php echo esc_html__( 'New Ad', 'remindmii' ); ?></h3>
				<button type="button" class="remindmii-modal__close" data-merchant-modal-close>&#x2715;</button>
			</div>
			<form class="remindmii-merchant-ad-form" data-merchant-ad-form>
				<input type="hidden" data-merchant-editing-id value="" />

				<label class="remindmii-field">
					<span><?php echo esc_html__( 'Title *', 'remindmii' ); ?></span>
					<input type="text" name="title" maxlength="191" required />
				</label>

				<label class="remindmii-field">
					<span><?php echo esc_html__( 'Description', 'remindmii' ); ?></span>
					<textarea name="description" rows="3"></textarea>
				</label>

				<label class="remindmii-field">
					<span><?php echo esc_html__( 'Image URL', 'remindmii' ); ?></span>
					<input type="url" name="image_url" maxlength="2083" />
				</label>

				<div class="remindmii-field-group">
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Background colour', 'remindmii' ); ?></span>
						<input type="color" name="background_color" value="#3B82F6" />
					</label>
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Text colour', 'remindmii' ); ?></span>
						<input type="color" name="text_color" value="#FFFFFF" />
					</label>
				</div>

				<div class="remindmii-field-group">
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Min. age', 'remindmii' ); ?></span>
						<input type="number" name="target_age_min" min="0" max="120" value="0" />
					</label>
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Max. age', 'remindmii' ); ?></span>
						<input type="number" name="target_age_max" min="0" max="120" value="120" />
					</label>
				</div>

				<div class="remindmii-field-group">
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Start date', 'remindmii' ); ?></span>
						<input type="date" name="start_date" />
					</label>
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'End date', 'remindmii' ); ?></span>
						<input type="date" name="end_date" />
					</label>
				</div>

				<label class="remindmii-field">
					<span><?php echo esc_html__( 'CTA text', 'remindmii' ); ?></span>
					<input type="text" name="cta_text" maxlength="100" value="Se tilbud" />
				</label>

				<label class="remindmii-field">
					<span><?php echo esc_html__( 'CTA URL', 'remindmii' ); ?></span>
					<input type="url" name="cta_url" maxlength="2083" />
				</label>

				<label class="remindmii-checkbox">
					<input type="checkbox" name="is_active" value="1" checked />
					<span><?php echo esc_html__( 'Active', 'remindmii' ); ?></span>
				</label>

				<div class="remindmii-form__actions">
					<button type="submit" class="remindmii-button" data-merchant-ad-submit>
						<?php echo esc_html__( 'Save ad', 'remindmii' ); ?>
					</button>
					<button type="button" class="remindmii-button remindmii-button--secondary" data-merchant-modal-close>
						<?php echo esc_html__( 'Cancel', 'remindmii' ); ?>
					</button>
				</div>
				<p class="remindmii-merchant-form-status" data-merchant-form-status hidden></p>
			</form>
		</div>
	</div>

	<?php endif; ?>
</div>
