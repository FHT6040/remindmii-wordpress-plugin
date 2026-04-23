<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="remindmii-app-shell" data-remindmii-app>
	<div class="remindmii-app-shell__header">
		<h2><?php echo esc_html__( 'Remindmii', 'remindmii' ); ?></h2>
		<p class="remindmii-app-shell__intro"><?php echo esc_html__( 'Create and manage your reminders directly from WordPress.', 'remindmii' ); ?></p>
	</div>
	<div class="remindmii-app-shell__body">
			<input type="hidden" data-remindmii-editing-id value="" />
		<div class="remindmii-app-shell__status" data-remindmii-status aria-live="polite">
			<?php echo esc_html__( 'Loading reminders...', 'remindmii' ); ?>
		</div>

			<form class="remindmii-profile-form" data-remindmii-profile-form hidden>
				<h3><?php echo esc_html__( 'Profile settings', 'remindmii' ); ?></h3>
				<div class="remindmii-field-group">
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Full name', 'remindmii' ); ?></span>
						<input type="text" name="full_name" maxlength="191" />
					</label>
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Email', 'remindmii' ); ?></span>
						<input type="email" name="email" maxlength="191" />
					</label>
				</div>
				<div class="remindmii-field-group">
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Phone', 'remindmii' ); ?></span>
						<input type="text" name="phone" maxlength="50" />
					</label>
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Birth date', 'remindmii' ); ?></span>
						<input type="date" name="birth_date" />
					</label>
				</div>
				<div class="remindmii-field-group">
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Gender', 'remindmii' ); ?></span>
						<input type="text" name="gender" maxlength="50" />
					</label>
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Pronouns', 'remindmii' ); ?></span>
						<input type="text" name="pronouns" maxlength="100" />
					</label>
				</div>
				<div class="remindmii-field-group">
					<label class="remindmii-checkbox">
						<input type="checkbox" name="email_notifications" value="1" />
						<span><?php echo esc_html__( 'Enable email notifications', 'remindmii' ); ?></span>
					</label>
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Notify me this many hours before', 'remindmii' ); ?></span>
						<input type="number" min="1" max="720" name="notification_hours" />
					</label>
				</div>
				<div class="remindmii-form__actions">
					<button type="submit" class="remindmii-button" data-remindmii-profile-submit>
						<?php echo esc_html__( 'Save profile', 'remindmii' ); ?>
					</button>
				</div>
			</form>

			<div class="remindmii-notifications-panel" data-remindmii-notifications-panel hidden>
				<h3><?php echo esc_html__( 'Notification history', 'remindmii' ); ?></h3>
				<ul class="remindmii-notifications" data-remindmii-notifications-list></ul>
			</div>

		<div class="remindmii-auth-message" data-remindmii-auth-message hidden>
			<p><?php echo esc_html__( 'You need to be logged in to use Remindmii.', 'remindmii' ); ?></p>
			<a class="remindmii-button remindmii-button--secondary" data-remindmii-login-link href="#">
				<?php echo esc_html__( 'Log in', 'remindmii' ); ?>
			</a>
		</div>

		<form class="remindmii-form" data-remindmii-form hidden>
			<div class="remindmii-field-group">
				<label class="remindmii-field">
					<span><?php echo esc_html__( 'Title', 'remindmii' ); ?></span>
					<input type="text" name="title" maxlength="191" required />
				</label>
				<label class="remindmii-field">
					<span><?php echo esc_html__( 'Category', 'remindmii' ); ?></span>
					<select name="category_id" data-remindmii-category-select>
						<option value=""><?php echo esc_html__( 'No category', 'remindmii' ); ?></option>
					</select>
				</label>
			</div>

			<div class="remindmii-field-group">
				<label class="remindmii-field">
					<span><?php echo esc_html__( 'Reminder date', 'remindmii' ); ?></span>
					<input type="datetime-local" name="reminder_date" required />
				</label>
				<div class="remindmii-field remindmii-field--category-create">
					<span><?php echo esc_html__( 'New category', 'remindmii' ); ?></span>
					<div class="remindmii-inline-create">
						<input type="text" name="new_category_name" maxlength="191" data-remindmii-category-name />
						<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-category-submit>
							<?php echo esc_html__( 'Add category', 'remindmii' ); ?>
						</button>
					</div>
				</div>
			</div>

			<label class="remindmii-field">
				<span><?php echo esc_html__( 'Description', 'remindmii' ); ?></span>
				<textarea name="description" rows="4"></textarea>
			</label>

			<label class="remindmii-checkbox">
				<input type="checkbox" name="is_recurring" value="1" />
				<span><?php echo esc_html__( 'Recurring reminder', 'remindmii' ); ?></span>
			</label>

			<label class="remindmii-field">
				<span><?php echo esc_html__( 'Repeat interval', 'remindmii' ); ?></span>
				<select name="recurrence_interval">
					<option value=""><?php echo esc_html__( 'No repeat', 'remindmii' ); ?></option>
					<option value="daily"><?php echo esc_html__( 'Daily', 'remindmii' ); ?></option>
					<option value="weekly"><?php echo esc_html__( 'Weekly', 'remindmii' ); ?></option>
					<option value="monthly"><?php echo esc_html__( 'Monthly', 'remindmii' ); ?></option>
					<option value="yearly"><?php echo esc_html__( 'Yearly', 'remindmii' ); ?></option>
				</select>
			</label>

			<div class="remindmii-form__actions">
				<button type="submit" class="remindmii-button" data-remindmii-submit>
					<?php echo esc_html__( 'Create reminder', 'remindmii' ); ?>
				</button>
				<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-cancel-edit hidden>
					<?php echo esc_html__( 'Cancel edit', 'remindmii' ); ?>
				</button>
			</div>
		</form>

		<ul class="remindmii-reminders" data-remindmii-list hidden></ul>
			<ul class="remindmii-categories" data-remindmii-categories hidden></ul>
	</div>
</div>