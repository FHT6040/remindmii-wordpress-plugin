document.addEventListener('DOMContentLoaded', function () {
	var root = document.querySelector('[data-remindmii-app]');
	var config = window.remindmiiFrontend || null;

	if (!root || !config) {
		return;
	}

	root.setAttribute('data-remindmii-ready', 'true');

	var status = root.querySelector('[data-remindmii-status]');
	var authMessage = root.querySelector('[data-remindmii-auth-message]');
	var loginLink = root.querySelector('[data-remindmii-login-link]');
	var profileForm = root.querySelector('[data-remindmii-profile-form]');
	var profileSubmitButton = root.querySelector('[data-remindmii-profile-submit]');
	var notificationsPanel = root.querySelector('[data-remindmii-notifications-panel]');
	var notificationsList = root.querySelector('[data-remindmii-notifications-list]');
	var notificationsCount = root.querySelector('[data-remindmii-notifications-count]');
	var notificationsFilter = root.querySelector('[data-remindmii-notifications-filter]');
	var notificationsDateFilter = root.querySelector('[data-remindmii-notifications-date-filter]');
	var notificationsSearchInput = root.querySelector('[data-remindmii-notifications-search]');
	var notificationsRefreshButton = root.querySelector('[data-remindmii-notifications-refresh]');
	var notificationsLoadMoreButton = root.querySelector('[data-remindmii-notifications-load-more]');
	var form = root.querySelector('[data-remindmii-form]');
	var list = root.querySelector('[data-remindmii-list]');
	var editingInput = root.querySelector('[data-remindmii-editing-id]');
	var categoriesList = root.querySelector('[data-remindmii-categories]');
	var categorySelect = root.querySelector('[data-remindmii-category-select]');
	var categoryNameInput = root.querySelector('[data-remindmii-category-name]');
	var categorySubmitButton = root.querySelector('[data-remindmii-category-submit]');
	var cancelEditButton = root.querySelector('[data-remindmii-cancel-edit]');
	var submitButton = root.querySelector('[data-remindmii-submit]');
	var categories = [];
	var reminders = [];
	var notificationItems = [];
	var notificationOffset = 0;
	var notificationsLimit = 10;
	var notificationsHasMore = false;
	var notificationsSearchTimer = null;

	if (!config.isLoggedIn) {
		if (status) {
			status.textContent = config.i18n.notLoggedIn;
		}

		if (authMessage) {
			authMessage.hidden = false;
		}

		if (loginLink) {
			loginLink.setAttribute('href', config.loginUrl);
		}

		return;
	}

	if (form) {
		form.hidden = false;
		form.addEventListener('submit', handleCreateReminder);
	}

	if (profileForm) {
		profileForm.hidden = false;
		profileForm.addEventListener('submit', handleSaveProfile);
	}

	if (notificationsPanel) {
		notificationsPanel.hidden = false;
	}

	if (notificationsFilter) {
		notificationsFilter.addEventListener('change', function () {
			renderNotifications(notificationItems);
		});
	}

	if (notificationsDateFilter) {
		notificationsDateFilter.addEventListener('change', function () {
			loadNotificationHistory({ append: false });
		});
	}

	if (notificationsSearchInput) {
		notificationsSearchInput.addEventListener('input', function () {
			if (notificationsSearchTimer) {
				window.clearTimeout(notificationsSearchTimer);
			}

			notificationsSearchTimer = window.setTimeout(function () {
				loadNotificationHistory({ append: false });
			}, 300);
		});
	}

	if (notificationsRefreshButton) {
		notificationsRefreshButton.addEventListener('click', function () {
			loadNotificationHistory({ manualRefresh: true, append: false });
		});
	}

	if (notificationsLoadMoreButton) {
		notificationsLoadMoreButton.addEventListener('click', function () {
			loadNotificationHistory({ append: true });
		});
	}

	if (list) {
		list.hidden = false;
	}

	if (categoriesList) {
		categoriesList.hidden = false;
	}

	if (categorySubmitButton) {
		categorySubmitButton.addEventListener('click', handleCreateCategory);
	}

	if (cancelEditButton) {
		cancelEditButton.addEventListener('click', resetFormState);
	}

	loadProfile();
	loadNotificationHistory({ append: false });
	loadCategories().then(loadReminders);

	async function loadNotificationHistory(options) {
		options = options || {};
		var append = Boolean(options.append);
		var isManualRefresh = Boolean(options.manualRefresh);
		var requestOffset = append ? notificationOffset : 0;
		var sinceDays = notificationsDateFilter ? parseInt(notificationsDateFilter.value || '0', 10) : 0;
		var searchQuery = notificationsSearchInput ? String(notificationsSearchInput.value || '').trim() : '';
		var notificationsUrl = config.notificationsUrl + '?limit=' + notificationsLimit + '&offset=' + requestOffset;

		if (sinceDays > 0) {
			notificationsUrl += '&since_days=' + sinceDays;
		}

		if (searchQuery) {
			notificationsUrl += '&q=' + encodeURIComponent(searchQuery);
		}

		if (!notificationsList) {
			return;
		}

		if (notificationsRefreshButton) {
			notificationsRefreshButton.disabled = true;
			notificationsRefreshButton.textContent = isManualRefresh ? config.i18n.refreshingHistory : config.i18n.refreshHistory;
		}

		if (notificationsLoadMoreButton) {
			notificationsLoadMoreButton.disabled = true;
			notificationsLoadMoreButton.textContent = config.i18n.loadingMoreHistory;
		}

		if (!append) {
			notificationsList.innerHTML = '<li class="remindmii-notification remindmii-notification--empty"><p>' + escapeHtml(config.i18n.loadingNotifications) + '</p></li>';
		}

		try {
			var response = await apiRequest(notificationsUrl, { method: 'GET' });
			var payload = await response.json();
			var items = payload && Array.isArray(payload.items) ? payload.items : [];

			notificationItems = append ? notificationItems.concat(items) : items;
			notificationOffset = payload && typeof payload.next_offset === 'number' ? payload.next_offset : notificationItems.length;
			notificationsHasMore = Boolean(payload && payload.has_more);
			renderNotifications(notificationItems);
			updateLoadMoreVisibility();
		} catch (error) {
			if (!append) {
				notificationItems = [];
				notificationOffset = 0;
				notificationsHasMore = false;
			}
			renderNotifications(notificationItems);
			updateLoadMoreVisibility();
			setStatus(getErrorMessage(error), true);
		} finally {
			if (notificationsRefreshButton) {
				notificationsRefreshButton.disabled = false;
				notificationsRefreshButton.textContent = config.i18n.refreshHistory;
			}

			if (notificationsLoadMoreButton) {
				notificationsLoadMoreButton.disabled = false;
				notificationsLoadMoreButton.textContent = config.i18n.loadMoreHistory;
			}
		}
	}

	async function loadProfile() {
		try {
			var response = await apiRequest(config.profileUrl, { method: 'GET' });
			var profile = await response.json();
			fillProfileForm(profile || {});
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		}
	}

	async function loadCategories() {
		try {
			var response = await apiRequest(config.categoriesUrl, { method: 'GET' });
			categories = await response.json();
			renderCategories(Array.isArray(categories) ? categories : []);
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		}
	}

	async function loadReminders() {
		setStatus(config.i18n.loading, false);

		try {
			var response = await apiRequest(config.restUrl, { method: 'GET' });
			var responseData = await response.json();

			reminders = Array.isArray(responseData) ? responseData : [];
			renderReminders(reminders);
			setStatus('', false);
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		}
	}

	async function handleCreateReminder(event) {
		event.preventDefault();

		if (!form || !submitButton) {
			return;
		}

		var formData = new FormData(form);
		var title = String(formData.get('title') || '').trim();

		if (!title) {
			setStatus(config.i18n.titleRequired, true);
			return;
		}

		var reminderDateValue = String(formData.get('reminder_date') || '').trim();
		var reminderDate = reminderDateValue ? new Date(reminderDateValue) : null;

		if (!reminderDate || Number.isNaN(reminderDate.getTime())) {
			setStatus(config.i18n.genericError, true);
			return;
		}

		var isRecurring = formData.get('is_recurring') === '1';
		var editingId = getEditingReminderId();
		var payload = {
			category_id: String(formData.get('category_id') || '').trim(),
			title: title,
			description: String(formData.get('description') || '').trim(),
			reminder_date: reminderDate.toISOString(),
			is_recurring: isRecurring,
			recurrence_interval: isRecurring ? String(formData.get('recurrence_interval') || '') : '',
			is_completed: editingId ? getEditingReminderCompletedState(editingId) : false
		};

		submitButton.disabled = true;
		submitButton.textContent = editingId ? config.i18n.updating : config.i18n.creating;
		setStatus('', false);

		try {
			await apiRequest(editingId ? config.restUrl + '/' + editingId : config.restUrl, {
				method: editingId ? 'PUT' : 'POST',
				body: JSON.stringify(payload)
			});

			resetFormState();
			await loadReminders();
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		} finally {
			submitButton.disabled = false;
			submitButton.textContent = getEditingReminderId() ? config.i18n.update : config.i18n.create;
		}
	}

	async function handleCreateCategory() {
		if (!categoryNameInput || !categorySubmitButton) {
			return;
		}

		var name = String(categoryNameInput.value || '').trim();

		if (!name) {
			setStatus(config.i18n.categoryRequired, true);
			return;
		}

		categorySubmitButton.disabled = true;
		categorySubmitButton.textContent = config.i18n.creatingCategory;

		try {
			var response = await apiRequest(config.categoriesUrl, {
				method: 'POST',
				body: JSON.stringify({ name: name })
			});

			var category = await response.json();
			categories.push(category);
			categories.sort(function (left, right) {
				return String(left.name || '').localeCompare(String(right.name || ''));
			});
			renderCategories(categories);
			if (categorySelect) {
				categorySelect.value = String(category.id);
			}
			categoryNameInput.value = '';
			setStatus('', false);
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		} finally {
			categorySubmitButton.disabled = false;
			categorySubmitButton.textContent = config.i18n.createCategory;
		}
	}

	async function handleSaveProfile(event) {
		event.preventDefault();

		if (!profileForm || !profileSubmitButton) {
			return;
		}

		var formData = new FormData(profileForm);
		var payload = {
			full_name: String(formData.get('full_name') || '').trim(),
			email: String(formData.get('email') || '').trim(),
			phone: String(formData.get('phone') || '').trim(),
			birth_date: String(formData.get('birth_date') || '').trim(),
			gender: String(formData.get('gender') || '').trim(),
			pronouns: String(formData.get('pronouns') || '').trim(),
			email_notifications: formData.get('email_notifications') === '1',
			notification_hours: String(formData.get('notification_hours') || '24').trim()
		};

		profileSubmitButton.disabled = true;
		profileSubmitButton.textContent = config.i18n.savingProfile;

		try {
			var response = await apiRequest(config.profileUrl, {
				method: 'PUT',
				body: JSON.stringify(payload)
			});
			var profile = await response.json();
			fillProfileForm(profile || {});
			setStatus(config.i18n.profileSaved, false);
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		} finally {
			profileSubmitButton.disabled = false;
			profileSubmitButton.textContent = config.i18n.saveProfile;
		}
	}

	async function handleToggleReminder(reminder) {
		try {
			setStatus('', false);
			await apiRequest(config.restUrl + '/' + reminder.id, {
				method: 'PUT',
				body: JSON.stringify({
					title: reminder.title,
					description: reminder.description || '',
					reminder_date: reminder.reminder_date,
					is_recurring: reminder.is_recurring,
					recurrence_interval: reminder.recurrence_interval || '',
					is_completed: !reminder.is_completed,
					category_id: reminder.category_id || ''
				})
			});

			await loadReminders();
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		}
	}

	async function handleDeleteReminder(reminderId) {
		if (!window.confirm(config.i18n.confirmDelete)) {
			return;
		}

		try {
			setStatus('', false);
			await apiRequest(config.restUrl + '/' + reminderId, { method: 'DELETE' });
			if (String(getEditingReminderId()) === String(reminderId)) {
				resetFormState();
			}
			await loadReminders();
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		}
	}

	function renderReminders(reminders) {
		if (!list) {
			return;
		}

		list.innerHTML = '';

		if (!reminders.length) {
			list.innerHTML = '<li class="remindmii-reminder remindmii-reminder--empty"><p>' + escapeHtml(config.i18n.empty) + '</p></li>';
			return;
		}

		reminders.forEach(function (reminder) {
			var item = document.createElement('li');
			item.className = 'remindmii-reminder' + (reminder.is_completed ? ' remindmii-reminder--completed' : '');

			var dueDate = formatDate(reminder.reminder_date);
			var categoryName = getCategoryName(reminder.category_id);
			var description = reminder.description ? '<p class="remindmii-reminder__description">' + escapeHtml(reminder.description) + '</p>' : '';

			item.innerHTML = '' +
				'<div class="remindmii-reminder__content">' +
					'<h3>' + escapeHtml(reminder.title || config.i18n.untitled) + '</h3>' +
					'<p class="remindmii-reminder__meta"><strong>' + escapeHtml(config.i18n.categoryLabel) + ':</strong> ' + escapeHtml(categoryName) + '</p>' +
					'<p class="remindmii-reminder__meta"><strong>' + escapeHtml(config.i18n.dueLabel) + ':</strong> ' + escapeHtml(dueDate) + '</p>' +
					description +
				'</div>' +
				'<div class="remindmii-reminder__actions">' +
					'<button type="button" class="remindmii-button remindmii-button--secondary" data-action="edit">' + escapeHtml(config.i18n.edit) + '</button>' +
					'<button type="button" class="remindmii-button remindmii-button--secondary" data-action="toggle">' + escapeHtml(reminder.is_completed ? config.i18n.markActive : config.i18n.markComplete) + '</button>' +
					'<button type="button" class="remindmii-button remindmii-button--danger" data-action="delete">' + escapeHtml(config.i18n.delete) + '</button>' +
				'</div>';

			item.querySelector('[data-action="edit"]').addEventListener('click', function () {
				beginEditReminder(reminder);
			});

			item.querySelector('[data-action="toggle"]').addEventListener('click', function () {
				handleToggleReminder(reminder);
			});

			item.querySelector('[data-action="delete"]').addEventListener('click', function () {
				handleDeleteReminder(reminder.id);
			});

			list.appendChild(item);
		});
	}

	function beginEditReminder(reminder) {
		if (!form || !submitButton || !editingInput) {
			return;
		}

		editingInput.value = String(reminder.id);
		form.elements.title.value = reminder.title || '';
		form.elements.description.value = reminder.description || '';
		form.elements.category_id.value = reminder.category_id ? String(reminder.category_id) : '';
		form.elements.is_recurring.checked = Boolean(reminder.is_recurring);
		form.elements.recurrence_interval.value = reminder.recurrence_interval || '';
		form.elements.reminder_date.value = toDatetimeLocalValue(reminder.reminder_date);
		submitButton.textContent = config.i18n.update;

		if (cancelEditButton) {
			cancelEditButton.hidden = false;
		}

		setStatus(config.i18n.editingStatus, false);
		form.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	function fillProfileForm(profile) {
		if (!profileForm) {
			return;
		}

		profileForm.elements.full_name.value = profile.full_name || '';
		profileForm.elements.email.value = profile.email || '';
		profileForm.elements.phone.value = profile.phone || '';
		profileForm.elements.birth_date.value = profile.birth_date || '';
		profileForm.elements.gender.value = profile.gender || '';
		profileForm.elements.pronouns.value = profile.pronouns || '';
		profileForm.elements.email_notifications.checked = Boolean(profile.email_notifications);
		profileForm.elements.notification_hours.value = profile.notification_hours || 24;
	}

	function renderNotifications(items) {
		if (!notificationsList) {
			return;
		}

		var activeFilter = notificationsFilter ? String(notificationsFilter.value || 'all') : 'all';
		var visibleItems = items.filter(function (item) {
			if (activeFilter === 'all') {
				return true;
			}

			return String(item.status || '').toLowerCase() === activeFilter;
		});

		notificationsList.innerHTML = '';

		if (!visibleItems.length) {
			notificationsList.innerHTML = '<li class="remindmii-notification remindmii-notification--empty"><p>' + escapeHtml(config.i18n.noNotifications) + '</p></li>';
			updateNotificationsCount(0, items.length);
			return;
		}

		visibleItems.forEach(function (item) {
			var row = document.createElement('li');
			var statusLabel = getNotificationStatusLabel(item.status);
			var timestampLabel = item.sent_at ? config.i18n.sentAtLabel : config.i18n.createdAtLabel;
			var timestampValue = formatDate(item.sent_at || item.created_at);
			var title = item.title || item.message || config.i18n.notificationHistory;
			var reminderDate = item.reminder_date ? '<p class="remindmii-notification__meta"><strong>' + escapeHtml(config.i18n.dueLabel) + ':</strong> ' + escapeHtml(formatDate(item.reminder_date)) + '</p>' : '';
			var openReminderAction = item.reminder_id ? '<button type="button" class="remindmii-button remindmii-button--secondary remindmii-button--small" data-action="open-reminder">' + escapeHtml(config.i18n.openReminder) + '</button>' : '';

			row.className = 'remindmii-notification remindmii-notification--' + escapeHtml(String(item.status || 'sent'));
			row.innerHTML = '' +
				'<div class="remindmii-notification__content">' +
					'<h4>' + escapeHtml(title) + '</h4>' +
					'<p class="remindmii-notification__meta"><strong>' + escapeHtml(config.i18n.statusLabel || 'Status') + ':</strong> ' + escapeHtml(statusLabel) + '</p>' +
					'<p class="remindmii-notification__meta"><strong>' + escapeHtml(timestampLabel) + ':</strong> ' + escapeHtml(timestampValue) + '</p>' +
					reminderDate +
					'<p class="remindmii-notification__message">' + escapeHtml(item.message || '') + '</p>' +
					(openReminderAction ? '<div class="remindmii-notification__actions">' + openReminderAction + '</div>' : '') +
				'</div>';

			if (item.reminder_id) {
				row.querySelector('[data-action="open-reminder"]').addEventListener('click', function () {
					focusReminder(item.reminder_id);
				});
			}

			notificationsList.appendChild(row);
		});

		updateNotificationsCount(visibleItems.length, items.length);
	}

	function updateNotificationsCount(visibleCount, loadedCount) {
		if (!notificationsCount) {
			return;
		}

		if (loadedCount <= 0) {
			notificationsCount.textContent = '';
			return;
		}

		if (visibleCount === loadedCount) {
			notificationsCount.textContent = config.i18n.historyCountAll.replace('%1$d', String(loadedCount));
			return;
		}

		notificationsCount.textContent = config.i18n.historyCount
			.replace('%1$d', String(visibleCount))
			.replace('%2$d', String(loadedCount));
	}

	function updateLoadMoreVisibility() {
		if (!notificationsLoadMoreButton) {
			return;
		}

		notificationsLoadMoreButton.hidden = !notificationsHasMore;
	}

	function focusReminder(reminderId) {
		var reminder = reminders.find(function (item) {
			return String(item.id) === String(reminderId);
		});

		if (!reminder) {
			setStatus(config.i18n.reminderUnavailable, true);
			return;
		}

		beginEditReminder(reminder);
	}

	function getNotificationStatusLabel(status) {
		switch (String(status || '').toLowerCase()) {
			case 'preview':
				return config.i18n.statusPreview;
			case 'failed':
				return config.i18n.statusFailed;
			case 'sent':
			default:
				return config.i18n.statusSent;
		}
	}

	function resetFormState() {
		if (form) {
			form.reset();
		}

		if (editingInput) {
			editingInput.value = '';
		}

		if (submitButton) {
			submitButton.textContent = config.i18n.create;
			submitButton.disabled = false;
		}

		if (cancelEditButton) {
			cancelEditButton.hidden = true;
		}

		if (categorySelect) {
			categorySelect.value = '';
		}

		setStatus('', false);
	}

	function renderCategories(categoryItems) {
		renderCategorySelect(categoryItems);

		if (!categoriesList) {
			return;
		}

		categoriesList.innerHTML = '';

		if (!categoryItems.length) {
			return;
		}

		categoryItems.forEach(function (category) {
			var item = document.createElement('li');
			item.className = 'remindmii-category';
			item.innerHTML = '' +
				'<span class="remindmii-category__swatch" style="background:' + escapeHtml(category.color || '#3B82F6') + ';"></span>' +
				'<span class="remindmii-category__name">' + escapeHtml(category.name || config.i18n.noCategory) + '</span>' +
				'<button type="button" class="remindmii-button remindmii-button--secondary remindmii-button--small" data-action="delete-category">' + escapeHtml(config.i18n.delete) + '</button>';

			item.querySelector('[data-action="delete-category"]').addEventListener('click', function () {
				handleDeleteCategory(category.id);
			});

			categoriesList.appendChild(item);
		});
	}

	function renderCategorySelect(categoryItems) {
		if (!categorySelect) {
			return;
		}

		var selectedValue = categorySelect.value;
		categorySelect.innerHTML = '<option value="">' + escapeHtml(config.i18n.noCategory) + '</option>';

		categoryItems.forEach(function (category) {
			var option = document.createElement('option');
			option.value = String(category.id);
			option.textContent = category.name || config.i18n.noCategory;
			categorySelect.appendChild(option);
		});

		if (selectedValue) {
			categorySelect.value = selectedValue;
		}
	}

	function getEditingReminderId() {
		return editingInput && editingInput.value ? String(editingInput.value) : '';
	}

	function getEditingReminderCompletedState(reminderId) {
		var match = reminders.find(function (reminder) {
			return String(reminder.id) === String(reminderId);
		});

		return match ? Boolean(match.is_completed) : false;
	}

	async function handleDeleteCategory(categoryId) {
		if (!window.confirm(config.i18n.confirmDeleteCategory)) {
			return;
		}

		try {
			await apiRequest(config.categoriesUrl + '/' + categoryId, { method: 'DELETE' });
			categories = categories.filter(function (category) {
				return String(category.id) !== String(categoryId);
			});
			renderCategories(categories);
			await loadReminders();
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		}
	}

	async function apiRequest(url, options) {
		var requestOptions = Object.assign(
			{
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.restNonce
				}
			},
			options || {}
		);

		requestOptions.headers = Object.assign(
			{
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.restNonce
			},
			requestOptions.headers || {}
		);

		var response = await window.fetch(url, requestOptions);

		if (!response.ok) {
			var errorMessage = config.i18n.genericError;

			try {
				var errorData = await response.json();
				if (errorData && typeof errorData.message === 'string' && errorData.message) {
					errorMessage = errorData.message;
				}
			} catch (jsonError) {
				void jsonError;
			}

			throw new Error(errorMessage);
		}

		return response;
	}

	function setStatus(message, isError) {
		if (!status) {
			return;
		}

		status.textContent = message;
		status.classList.toggle('is-error', Boolean(message) && Boolean(isError));
		status.classList.toggle('is-success', Boolean(message) && !isError);
	}

	function getErrorMessage(error) {
		if (error && typeof error.message === 'string' && error.message) {
			return error.message;
		}

		return config.i18n.genericError;
	}

	function formatDate(value) {
		var date = new Date(value);

		if (Number.isNaN(date.getTime())) {
			return value;
		}

		return date.toLocaleString();
	}

	function toDatetimeLocalValue(value) {
		var date = new Date(value);

		if (Number.isNaN(date.getTime())) {
			return '';
		}

		var offset = date.getTimezoneOffset();
		var localDate = new Date(date.getTime() - offset * 60000);

		return localDate.toISOString().slice(0, 16);
	}

	function getCategoryName(categoryId) {
		if (!categoryId) {
			return config.i18n.noCategory;
		}

		var match = categories.find(function (category) {
			return String(category.id) === String(categoryId);
		});

		return match && match.name ? match.name : config.i18n.noCategory;
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

});
