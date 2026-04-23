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
				<div class="remindmii-notifications-toolbar">
					<label class="remindmii-field remindmii-field--compact">
						<span><?php echo esc_html__( 'Status', 'remindmii' ); ?></span>
						<select data-remindmii-notifications-filter>
							<option value="all"><?php echo esc_html__( 'All statuses', 'remindmii' ); ?></option>
							<option value="sent"><?php echo esc_html__( 'Sent', 'remindmii' ); ?></option>
							<option value="failed"><?php echo esc_html__( 'Failed', 'remindmii' ); ?></option>
							<option value="preview"><?php echo esc_html__( 'Preview', 'remindmii' ); ?></option>
						</select>
					</label>
					<label class="remindmii-field remindmii-field--compact">
						<span><?php echo esc_html__( 'Date range', 'remindmii' ); ?></span>
						<select data-remindmii-notifications-date-filter>
							<option value="all"><?php echo esc_html__( 'All dates', 'remindmii' ); ?></option>
							<option value="7"><?php echo esc_html__( 'Last 7 days', 'remindmii' ); ?></option>
							<option value="30"><?php echo esc_html__( 'Last 30 days', 'remindmii' ); ?></option>
						</select>
					</label>
					<label class="remindmii-field remindmii-field--compact">
						<span><?php echo esc_html__( 'Search', 'remindmii' ); ?></span>
						<input type="search" data-remindmii-notifications-search placeholder="<?php echo esc_attr__( 'Search notifications...', 'remindmii' ); ?>" />
					</label>
					<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-notifications-refresh>
						<?php echo esc_html__( 'Refresh history', 'remindmii' ); ?>
					</button>
					<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-notifications-export>
						<?php echo esc_html__( 'Export CSV', 'remindmii' ); ?>
					</button>
				</div>
				<ul class="remindmii-notifications" data-remindmii-notifications-list></ul>
				<div class="remindmii-notifications-footer">
					<p class="remindmii-notifications-count" data-remindmii-notifications-count></p>
					<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-notifications-load-more hidden>
						<?php echo esc_html__( 'Load more', 'remindmii' ); ?>
					</button>
				</div>
			</div>

		<div class="remindmii-auth-message" data-remindmii-auth-message hidden>
			<p><?php echo esc_html__( 'You need to be logged in to use Remindmii.', 'remindmii' ); ?></p>
			<a class="remindmii-button remindmii-button--secondary" data-remindmii-login-link href="#">
				<?php echo esc_html__( 'Log in', 'remindmii' ); ?>
			</a>
		</div>

		<form class="remindmii-form" data-remindmii-form hidden>
			<div class="remindmii-form__template-bar">
				<button type="button" class="remindmii-button remindmii-button--secondary remindmii-button--small" data-remindmii-templates-open>
					<?php echo esc_html__( 'Use template', 'remindmii' ); ?>
				</button>
			</div>
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

		<!-- Preferences panel -->
		<div class="remindmii-preferences-panel" data-remindmii-preferences-panel hidden>
			<h3><?php echo esc_html__( 'Preferences', 'remindmii' ); ?></h3>

			<div class="remindmii-pref-section">
				<h4><?php echo esc_html__( 'Appearance', 'remindmii' ); ?></h4>
				<div class="remindmii-theme-grid" data-remindmii-theme-grid>
					<?php
					$themes = array(
						'default'  => array( 'label' => __( 'Default', 'remindmii' ), 'icon' => '✨' ),
						'light'    => array( 'label' => __( 'Light', 'remindmii' ), 'icon' => '☀️' ),
						'dark'     => array( 'label' => __( 'Dark', 'remindmii' ), 'icon' => '🌙' ),
						'romantic' => array( 'label' => __( 'Romantic', 'remindmii' ), 'icon' => '💕' ),
					);
					foreach ( $themes as $theme_id => $theme ) :
					?>
					<button type="button" class="remindmii-theme-btn" data-remindmii-theme="<?php echo esc_attr( $theme_id ); ?>">
						<span class="remindmii-theme-btn__icon"><?php echo esc_html( $theme['icon'] ); ?></span>
						<span><?php echo esc_html( $theme['label'] ); ?></span>
					</button>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="remindmii-pref-section">
				<h4><?php echo esc_html__( 'Features', 'remindmii' ); ?></h4>

				<div class="remindmii-toggle-row">
					<div>
						<strong><?php echo esc_html__( 'Location reminders', 'remindmii' ); ?></strong>
						<p><?php echo esc_html__( 'Get notified when near relevant locations.', 'remindmii' ); ?></p>
					</div>
					<button type="button" class="remindmii-toggle" role="switch" data-remindmii-pref-toggle="enable_location_reminders" aria-checked="false"></button>
				</div>

				<div class="remindmii-toggle-row">
					<div>
						<strong><?php echo esc_html__( 'Gamification', 'remindmii' ); ?></strong>
						<p><?php echo esc_html__( 'Track streaks, earn badges and see your progress.', 'remindmii' ); ?></p>
					</div>
					<button type="button" class="remindmii-toggle" role="switch" data-remindmii-pref-toggle="enable_gamification" aria-checked="false"></button>
				</div>

				<div class="remindmii-toggle-row">
					<div>
						<strong><?php echo esc_html__( 'Focus mode', 'remindmii' ); ?></strong>
						<p><?php echo esc_html__( 'Simplify the interface to reduce distractions.', 'remindmii' ); ?></p>
					</div>
					<button type="button" class="remindmii-toggle" role="switch" data-remindmii-pref-toggle="distracted_mode" aria-checked="false"></button>
				</div>
			</div>

			<div class="remindmii-form__actions">
				<button type="button" class="remindmii-button" data-remindmii-preferences-save>
					<?php echo esc_html__( 'Save preferences', 'remindmii' ); ?>
				</button>
			</div>
			<p class="remindmii-pref-status" data-remindmii-preferences-status hidden></p>
		</div>

		<!-- Shared lists panel -->
		<div class="remindmii-shared-panel" data-remindmii-shared-panel hidden>
			<h3><?php echo esc_html__( 'Shared with me', 'remindmii' ); ?></h3>
			<div class="remindmii-shared-list" data-remindmii-shared-list>
				<p><?php echo esc_html__( 'Loading...', 'remindmii' ); ?></p>
			</div>

			<h3 style="margin-top:1.5rem"><?php echo esc_html__( 'Share a wishlist', 'remindmii' ); ?></h3>
			<p class="remindmii-muted"><?php echo esc_html__( 'Open a wishlist to share it with others.', 'remindmii' ); ?></p>
		</div>

		<!-- Wishlist panel -->
		<div class="remindmii-wishlists-panel" data-remindmii-wishlists-panel hidden>
			<div class="remindmii-wishlists-header">
				<h3><?php echo esc_html__( 'Wishlists', 'remindmii' ); ?></h3>
				<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-wishlist-new>
					<?php echo esc_html__( 'New wishlist', 'remindmii' ); ?>
				</button>
			</div>

			<form class="remindmii-wishlist-form" data-remindmii-wishlist-form hidden>
				<input type="hidden" data-remindmii-wishlist-editing-id value="" />
				<label class="remindmii-field">
					<span><?php echo esc_html__( 'Wishlist title', 'remindmii' ); ?></span>
					<input type="text" name="wishlist_title" maxlength="191" required />
				</label>
				<label class="remindmii-field">
					<span><?php echo esc_html__( 'Description (optional)', 'remindmii' ); ?></span>
					<textarea name="wishlist_description" rows="3"></textarea>
				</label>
				<label class="remindmii-checkbox">
					<input type="checkbox" name="wishlist_is_public" value="1" />
					<span><?php echo esc_html__( 'Make public (share link)', 'remindmii' ); ?></span>
				</label>
				<div class="remindmii-form__actions">
					<button type="submit" class="remindmii-button" data-remindmii-wishlist-submit>
						<?php echo esc_html__( 'Create wishlist', 'remindmii' ); ?>
					</button>
					<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-wishlist-form-cancel>
						<?php echo esc_html__( 'Cancel', 'remindmii' ); ?>
					</button>
				</div>
			</form>

			<p class="remindmii-app-shell__status" data-remindmii-wishlists-status hidden></p>
			<ul class="remindmii-wishlists-list" data-remindmii-wishlists-list></ul>

			<!-- Detail view -->
			<div class="remindmii-wishlist-detail" data-remindmii-wishlist-detail hidden>
				<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-wishlist-back>
					<?php echo esc_html__( '&#8592; Back', 'remindmii' ); ?>
				</button>
				<h4 data-remindmii-wishlist-detail-title></h4>
				<p class="remindmii-wishlist-detail-desc" data-remindmii-wishlist-detail-desc></p>
				<p class="remindmii-wishlist-share" data-remindmii-wishlist-share hidden>
					<a href="#" target="_blank" rel="noopener" data-remindmii-wishlist-share-link></a>
					<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-wishlist-copy-link>
						<?php echo esc_html__( 'Copy link', 'remindmii' ); ?>
					</button>
				</p>

				<div class="remindmii-wishlists-items-header">
					<h4><?php echo esc_html__( 'Items', 'remindmii' ); ?></h4>
					<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-item-new>
						<?php echo esc_html__( 'Add item', 'remindmii' ); ?>
					</button>
				</div>

				<form class="remindmii-item-form" data-remindmii-item-form hidden>
					<input type="hidden" data-remindmii-item-editing-id value="" />
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Item title', 'remindmii' ); ?></span>
						<input type="text" name="item_title" maxlength="191" required />
					</label>
					<label class="remindmii-field">
						<span><?php echo esc_html__( 'Description (optional)', 'remindmii' ); ?></span>
						<textarea name="item_description" rows="2"></textarea>
					</label>
					<div class="remindmii-field-group">
						<label class="remindmii-field">
							<span><?php echo esc_html__( 'URL (optional)', 'remindmii' ); ?></span>
							<input type="url" name="item_url" maxlength="2083" />
						</label>
						<label class="remindmii-field">
							<span><?php echo esc_html__( 'Price (optional)', 'remindmii' ); ?></span>
							<input type="number" name="item_price" min="0" step="0.01" />
						</label>
						<label class="remindmii-field">
							<span><?php echo esc_html__( 'Currency', 'remindmii' ); ?></span>
							<input type="text" name="item_currency" maxlength="10" value="DKK" />
						</label>
					</div>
					<label class="remindmii-checkbox">
						<input type="checkbox" name="item_is_purchased" value="1" />
						<span><?php echo esc_html__( 'Purchased', 'remindmii' ); ?></span>
					</label>
					<div class="remindmii-form__actions">
						<button type="submit" class="remindmii-button" data-remindmii-item-submit>
							<?php echo esc_html__( 'Save item', 'remindmii' ); ?>
						</button>
						<button type="button" class="remindmii-button remindmii-button--secondary" data-remindmii-item-form-cancel>
							<?php echo esc_html__( 'Cancel', 'remindmii' ); ?>
						</button>
					</div>
				</form>

				<p class="remindmii-app-shell__status" data-remindmii-items-status hidden></p>
				<ul class="remindmii-items-list" data-remindmii-items-list></ul>

				<!-- Share section -->
				<div class="remindmii-wishlist-shares" data-remindmii-wishlist-shares hidden>
					<h4><?php echo esc_html__( 'Sharing', 'remindmii' ); ?></h4>
					<ul class="remindmii-shares-list" data-remindmii-shares-list></ul>
					<form class="remindmii-share-form" data-remindmii-share-form>
						<label class="remindmii-field">
							<span><?php echo esc_html__( 'Share with (email)', 'remindmii' ); ?></span>
							<input type="email" name="share_email" maxlength="191" required placeholder="<?php echo esc_attr__( 'friend@example.com', 'remindmii' ); ?>" />
						</label>
						<label class="remindmii-field">
							<span><?php echo esc_html__( 'Permission', 'remindmii' ); ?></span>
							<select name="share_permission">
								<option value="view"><?php echo esc_html__( 'View', 'remindmii' ); ?></option>
								<option value="edit"><?php echo esc_html__( 'Edit', 'remindmii' ); ?></option>
							</select>
						</label>
						<div class="remindmii-form__actions">
							<button type="submit" class="remindmii-button" data-remindmii-share-submit>
								<?php echo esc_html__( 'Share', 'remindmii' ); ?>
							</button>
						</div>
					</form>
					<p class="remindmii-app-shell__status" data-remindmii-shares-status hidden></p>
				</div>
			</div>
		</div>
		<!-- Template picker modal -->
		<div class="remindmii-modal-overlay" data-remindmii-templates-modal hidden>
			<div class="remindmii-modal">
				<div class="remindmii-modal__header">
					<h3><?php echo esc_html__( 'Choose a template', 'remindmii' ); ?></h3>
					<button type="button" class="remindmii-modal__close" data-remindmii-templates-close>&#x2715;</button>
				</div>
				<div class="remindmii-templates-filter" data-remindmii-templates-filter></div>
				<ul class="remindmii-templates-list" data-remindmii-templates-list></ul>
			</div>
		</div>
	</div>
</div>