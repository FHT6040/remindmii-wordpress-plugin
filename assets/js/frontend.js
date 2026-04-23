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
	var notificationsExportButton = root.querySelector('[data-remindmii-notifications-export]');
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
	var notificationTotalCount = 0;
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
			loadNotificationHistory({ append: false });
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

	if (notificationsExportButton) {
		notificationsExportButton.addEventListener('click', function () {
			exportNotificationsCsv();
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
	loadPreferences();
	loadSharedWithMe();
	loadGamification();
	loadNotificationHistory({ append: false });
	loadCategories().then(loadReminders);

	async function loadNotificationHistory(options) {
		options = options || {};
		var append = Boolean(options.append);
		var isManualRefresh = Boolean(options.manualRefresh);
		var requestOffset = append ? notificationOffset : 0;
		var sinceDays = notificationsDateFilter ? parseInt(notificationsDateFilter.value || '0', 10) : 0;
		var statusFilter = notificationsFilter ? String(notificationsFilter.value || 'all').trim().toLowerCase() : 'all';
		var searchQuery = notificationsSearchInput ? String(notificationsSearchInput.value || '').trim() : '';
		var notificationsUrl = config.notificationsUrl + '?limit=' + notificationsLimit + '&offset=' + requestOffset;

		if (sinceDays > 0) {
			notificationsUrl += '&since_days=' + sinceDays;
		}

		if (statusFilter !== 'all') {
			notificationsUrl += '&status=' + encodeURIComponent(statusFilter);
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

		if (notificationsExportButton) {
			notificationsExportButton.disabled = true;
			notificationsExportButton.textContent = config.i18n.exportingHistory || config.i18n.exportHistory;
		}

		if (!append) {
			notificationsList.innerHTML = '<li class="remindmii-notification remindmii-notification--empty"><p>' + escapeHtml(config.i18n.loadingNotifications) + '</p></li>';
		}

		try {
			var response = await apiRequest(notificationsUrl, { method: 'GET' });
			var payload = await response.json();
			var items = payload && Array.isArray(payload.items) ? payload.items : [];

			notificationItems = append ? notificationItems.concat(items) : items;
			notificationTotalCount = payload && typeof payload.total_count === 'number' ? payload.total_count : notificationItems.length;
			notificationOffset = payload && typeof payload.next_offset === 'number' ? payload.next_offset : notificationItems.length;
			notificationsHasMore = Boolean(payload && payload.has_more);
			renderNotifications(notificationItems);
			updateLoadMoreVisibility();
		} catch (error) {
			if (!append) {
				notificationItems = [];
				notificationOffset = 0;
				notificationTotalCount = 0;
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

			if (notificationsExportButton) {
				notificationsExportButton.disabled = false;
				notificationsExportButton.textContent = config.i18n.exportHistory;
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

		var visibleItems = getVisibleNotificationItems(items);

		notificationsList.innerHTML = '';

		if (!visibleItems.length) {
			notificationsList.innerHTML = '<li class="remindmii-notification remindmii-notification--empty"><p>' + escapeHtml(config.i18n.noNotifications) + '</p></li>';
			updateNotificationsCount(0, notificationTotalCount);
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

		updateNotificationsCount(visibleItems.length, notificationTotalCount);
	}

	function getVisibleNotificationItems(items) {
		return Array.isArray(items) ? items : [];
	}

	async function exportNotificationsCsv() {
		if (!notificationsExportButton) {
			return;
		}

		var sinceDays = notificationsDateFilter ? parseInt(notificationsDateFilter.value || '0', 10) : 0;
		var statusFilter = notificationsFilter ? String(notificationsFilter.value || 'all').trim().toLowerCase() : 'all';
		var searchQuery = notificationsSearchInput ? String(notificationsSearchInput.value || '').trim() : '';
		var exportUrl = config.notificationsExportUrl;
		var query = [];

		if (sinceDays > 0) {
			query.push('since_days=' + encodeURIComponent(String(sinceDays)));
		}

		if (statusFilter !== 'all') {
			query.push('status=' + encodeURIComponent(statusFilter));
		}

		if (searchQuery) {
			query.push('q=' + encodeURIComponent(searchQuery));
		}

		if (query.length) {
			exportUrl += '?' + query.join('&');
		}

		notificationsExportButton.disabled = true;
		notificationsExportButton.textContent = config.i18n.exportingHistory || config.i18n.exportHistory;

		try {
			var response = await window.fetch(exportUrl, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': config.restNonce
				}
			});

			if (!response.ok) {
				throw new Error(config.i18n.genericError);
			}

			var contentDisposition = response.headers.get('Content-Disposition') || '';
			var filenameMatch = /filename="?([^";]+)"?/i.exec(contentDisposition);
			var filename = filenameMatch && filenameMatch[1] ? filenameMatch[1] : 'remindmii-notifications.csv';
			var blob = await response.blob();
			var downloadUrl = window.URL.createObjectURL(blob);
			var link = document.createElement('a');

			link.href = downloadUrl;
			link.download = filename;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			window.URL.revokeObjectURL(downloadUrl);
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		} finally {
			notificationsExportButton.disabled = false;
			notificationsExportButton.textContent = config.i18n.exportHistory;
		}
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

	// =========================================================================
	// Wishlists
	// =========================================================================

	var wishlistsPanel     = root.querySelector('[data-remindmii-wishlists-panel]');
	var wishlistsListEl    = root.querySelector('[data-remindmii-wishlists-list]');
	var wishlistForm       = root.querySelector('[data-remindmii-wishlist-form]');
	var wishlistSubmitBtn  = root.querySelector('[data-remindmii-wishlist-submit]');
	var wishlistNewBtn     = root.querySelector('[data-remindmii-wishlist-new]');
	var wishlistCancelBtn  = root.querySelector('[data-remindmii-wishlist-form-cancel]');
	var wishlistEditingId  = root.querySelector('[data-remindmii-wishlist-editing-id]');
	var wishlistsStatus    = root.querySelector('[data-remindmii-wishlists-status]');

	var wishlistDetailEl     = root.querySelector('[data-remindmii-wishlist-detail]');
	var wishlistDetailTitle  = root.querySelector('[data-remindmii-wishlist-detail-title]');
	var wishlistDetailDesc   = root.querySelector('[data-remindmii-wishlist-detail-desc]');
	var wishlistShareEl      = root.querySelector('[data-remindmii-wishlist-share]');
	var wishlistShareLink    = root.querySelector('[data-remindmii-wishlist-share-link]');
	var wishlistCopyLinkBtn  = root.querySelector('[data-remindmii-wishlist-copy-link]');
	var wishlistBackBtn      = root.querySelector('[data-remindmii-wishlist-back]');

	var itemsListEl     = root.querySelector('[data-remindmii-items-list]');
	var itemForm        = root.querySelector('[data-remindmii-item-form]');
	var itemSubmitBtn   = root.querySelector('[data-remindmii-item-submit]');
	var itemNewBtn      = root.querySelector('[data-remindmii-item-new]');
	var itemCancelBtn   = root.querySelector('[data-remindmii-item-form-cancel]');
	var itemEditingId   = root.querySelector('[data-remindmii-item-editing-id]');
	var itemsStatus     = root.querySelector('[data-remindmii-items-status]');

	var wishlists         = [];
	var activeWishlistId  = null;
	var wishlistItems     = [];

	function setWishlistStatus(msg, show) {
		if (!wishlistsStatus) { return; }
		wishlistsStatus.textContent = msg;
		wishlistsStatus.hidden = !show;
	}

	function setItemsStatus(msg, show) {
		if (!itemsStatus) { return; }
		itemsStatus.textContent = msg;
		itemsStatus.hidden = !show;
	}

	function loadWishlists() {
		if (!config.wishlistsUrl) { return; }
		setWishlistStatus(config.i18n.loadingWishlists || 'Loading...', true);

		fetch(config.wishlistsUrl, {
			headers: { 'X-WP-Nonce': config.restNonce }
		})
		.then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
		.then(function (data) {
			wishlists = data.wishlists || [];
			renderWishlists(wishlists);
			setWishlistStatus('', false);
		})
		.catch(function () {
			setWishlistStatus(config.i18n.genericError || 'Error', true);
		});
	}

	function renderWishlists(list) {
		if (!wishlistsListEl) { return; }
		wishlistsListEl.innerHTML = '';

		if (!list.length) {
			wishlistsListEl.innerHTML = '<li class="remindmii-empty">' + escapeHtml(config.i18n.noWishlists || 'No wishlists yet.') + '</li>';
			return;
		}

		list.forEach(function (wl) {
			var li = document.createElement('li');
			li.className = 'remindmii-wishlist-row';
			var badge = wl.is_public
				? '<span class="remindmii-badge remindmii-badge--public">' + escapeHtml(config.i18n.publicBadge || 'Public') + '</span>'
				: '<span class="remindmii-badge remindmii-badge--private">' + escapeHtml(config.i18n.privateBadge || 'Private') + '</span>';

			li.innerHTML =
				'<button type="button" class="remindmii-wishlist-row__title remindmii-link" data-action="open-wishlist">' + escapeHtml(wl.title) + '</button>' +
				badge +
				'<div class="remindmii-wishlist-row__actions">' +
				'<button type="button" class="remindmii-button remindmii-button--secondary remindmii-button--small" data-action="edit-wishlist">' + escapeHtml(config.i18n.edit || 'Edit') + '</button>' +
				'<button type="button" class="remindmii-button remindmii-button--danger remindmii-button--small" data-action="delete-wishlist">' + escapeHtml(config.i18n.delete || 'Delete') + '</button>' +
				'</div>';

			li.querySelector('[data-action="open-wishlist"]').addEventListener('click', function () {
				openWishlist(wl);
			});
			li.querySelector('[data-action="edit-wishlist"]').addEventListener('click', function () {
				startEditWishlist(wl);
			});
			li.querySelector('[data-action="delete-wishlist"]').addEventListener('click', function () {
				handleDeleteWishlist(wl.id);
			});

			wishlistsListEl.appendChild(li);
		});
	}

	function startEditWishlist(wl) {
		if (!wishlistForm) { return; }
		wishlistEditingId.value = wl.id;
		wishlistForm.querySelector('[name="wishlist_title"]').value = wl.title || '';
		wishlistForm.querySelector('[name="wishlist_description"]').value = wl.description || '';
		wishlistForm.querySelector('[name="wishlist_is_public"]').checked = !!wl.is_public;
		if (wishlistSubmitBtn) {
			wishlistSubmitBtn.textContent = config.i18n.updateWishlist || 'Save wishlist';
		}
		wishlistForm.hidden = false;
	}

	function handleDeleteWishlist(wlId) {
		if (!window.confirm(config.i18n.confirmDeleteWishlist || 'Delete this wishlist?')) { return; }
		fetch(config.wishlistsUrl + '/' + wlId, {
			method: 'DELETE',
			headers: { 'X-WP-Nonce': config.restNonce }
		})
		.then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
		.then(function () {
			loadWishlists();
		})
		.catch(function () {
			setWishlistStatus(config.i18n.genericError || 'Error', true);
		});
	}

	function openWishlist(wl) {
		activeWishlistId = wl.id;
		if (wishlistDetailTitle) { wishlistDetailTitle.textContent = wl.title; }
		if (wishlistDetailDesc) { wishlistDetailDesc.textContent = wl.description || ''; }

		if (wishlistShareEl) {
			if (wl.is_public && wl.public_token) {
				var shareUrl = window.location.origin + '/?remindmii_wishlist=' + wl.public_token;
				wishlistShareEl.hidden = false;
				if (wishlistShareLink) {
					wishlistShareLink.href = shareUrl;
					wishlistShareLink.textContent = shareUrl;
				}
			} else {
				wishlistShareEl.hidden = true;
			}
		}

		if (wishlistsListEl) { wishlistsListEl.hidden = true; }
		if (wishlistForm) { wishlistForm.hidden = true; }
		if (wishlistDetailEl) { wishlistDetailEl.hidden = false; }

		loadWishlistItems(wl.id);

		// Load sharing section.
		currentWishlistIdForShares = wl.id;
		if ( sharesSection ) { sharesSection.hidden = false; }
		loadWishlistShares(wl.id);
	}

	function loadWishlistItems(wlId) {
		if (!config.wishlistsUrl) { return; }
		setItemsStatus(config.i18n.loadingItems || 'Loading items...', true);

		fetch(config.wishlistsUrl + '/' + wlId + '/items', {
			headers: { 'X-WP-Nonce': config.restNonce }
		})
		.then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
		.then(function (data) {
			wishlistItems = data.items || [];
			renderWishlistItems(wishlistItems);
			setItemsStatus('', false);
		})
		.catch(function () {
			setItemsStatus(config.i18n.genericError || 'Error', true);
		});
	}

	function renderWishlistItems(items) {
		if (!itemsListEl) { return; }
		itemsListEl.innerHTML = '';

		if (!items.length) {
			itemsListEl.innerHTML = '<li class="remindmii-empty">' + escapeHtml(config.i18n.noItems || 'No items yet.') + '</li>';
			return;
		}

		items.forEach(function (item) {
			var li = document.createElement('li');
			li.className = 'remindmii-item-row' + (item.is_purchased ? ' remindmii-item-row--purchased' : '');
			var priceStr = item.price !== null ? ' – ' + item.price + ' ' + escapeHtml(item.currency) : '';
			var urlStr = item.url ? ' <a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' + escapeHtml(item.url) + '</a>' : '';

			li.innerHTML =
				'<span class="remindmii-item-row__title">' + escapeHtml(item.title) + priceStr + '</span>' +
				urlStr +
				'<div class="remindmii-item-row__actions">' +
				'<button type="button" class="remindmii-button remindmii-button--secondary remindmii-button--small" data-action="toggle-item" title="' + escapeHtml(config.i18n.togglePurchased || 'Toggle purchased') + '">' + (item.is_purchased ? '✓' : '○') + '</button>' +
				'<button type="button" class="remindmii-button remindmii-button--secondary remindmii-button--small" data-action="edit-item">' + escapeHtml(config.i18n.edit || 'Edit') + '</button>' +
				'<button type="button" class="remindmii-button remindmii-button--danger remindmii-button--small" data-action="delete-item">' + escapeHtml(config.i18n.delete || 'Delete') + '</button>' +
				'</div>';

			li.querySelector('[data-action="toggle-item"]').addEventListener('click', function () {
				handleToggleItem(item.id);
			});
			li.querySelector('[data-action="edit-item"]').addEventListener('click', function () {
				startEditItem(item);
			});
			li.querySelector('[data-action="delete-item"]').addEventListener('click', function () {
				handleDeleteItem(item.id);
			});

			itemsListEl.appendChild(li);
		});
	}

	function startEditItem(item) {
		if (!itemForm) { return; }
		itemEditingId.value = item.id;
		itemForm.querySelector('[name="item_title"]').value = item.title || '';
		itemForm.querySelector('[name="item_description"]').value = item.description || '';
		itemForm.querySelector('[name="item_url"]').value = item.url || '';
		itemForm.querySelector('[name="item_price"]').value = item.price !== null ? item.price : '';
		itemForm.querySelector('[name="item_currency"]').value = item.currency || 'DKK';
		itemForm.querySelector('[name="item_is_purchased"]').checked = !!item.is_purchased;
		if (itemSubmitBtn) { itemSubmitBtn.textContent = config.i18n.saveItem || 'Save item'; }
		itemForm.hidden = false;
	}

	function handleToggleItem(itemId) {
		fetch(config.wishlistsUrl + '/' + activeWishlistId + '/items/' + itemId + '/toggle', {
			method: 'PUT',
			headers: { 'X-WP-Nonce': config.restNonce }
		})
		.then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
		.then(function () {
			loadWishlistItems(activeWishlistId);
		})
		.catch(function () {
			setItemsStatus(config.i18n.genericError || 'Error', true);
		});
	}

	function handleDeleteItem(itemId) {
		if (!window.confirm(config.i18n.confirmDeleteItem || 'Delete this item?')) { return; }
		fetch(config.wishlistsUrl + '/' + activeWishlistId + '/items/' + itemId, {
			method: 'DELETE',
			headers: { 'X-WP-Nonce': config.restNonce }
		})
		.then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
		.then(function () {
			loadWishlistItems(activeWishlistId);
		})
		.catch(function () {
			setItemsStatus(config.i18n.genericError || 'Error', true);
		});
	}

	// --- Wishlist form handlers ---

	if (wishlistNewBtn) {
		wishlistNewBtn.addEventListener('click', function () {
			if (!wishlistForm) { return; }
			wishlistEditingId.value = '';
			wishlistForm.reset();
			if (wishlistSubmitBtn) { wishlistSubmitBtn.textContent = config.i18n.createWishlist || 'Create wishlist'; }
			wishlistForm.hidden = !wishlistForm.hidden;
		});
	}

	if (wishlistCancelBtn) {
		wishlistCancelBtn.addEventListener('click', function () {
			if (wishlistForm) { wishlistForm.hidden = true; }
		});
	}

	if (wishlistForm) {
		wishlistForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var editId = wishlistEditingId ? wishlistEditingId.value : '';
			var titleField = wishlistForm.querySelector('[name="wishlist_title"]');
			var titleVal = titleField ? titleField.value.trim() : '';

			if (!titleVal) {
				setWishlistStatus(config.i18n.titleRequired || 'Title is required.', true);
				return;
			}

			var payload = {
				title: titleVal,
				description: (wishlistForm.querySelector('[name="wishlist_description"]') || {}).value || '',
				is_public: !!(wishlistForm.querySelector('[name="wishlist_is_public"]') || {}).checked
			};

			var method = editId ? 'PUT' : 'POST';
			var url = editId ? config.wishlistsUrl + '/' + editId : config.wishlistsUrl;
			if (wishlistSubmitBtn) { wishlistSubmitBtn.textContent = config.i18n.creatingWishlist || 'Creating...'; }

			fetch(url, {
				method: method,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.restNonce
				},
				body: JSON.stringify(payload)
			})
			.then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
			.then(function () {
				wishlistForm.hidden = true;
				wishlistForm.reset();
				wishlistEditingId.value = '';
				loadWishlists();
			})
			.catch(function () {
				setWishlistStatus(config.i18n.genericError || 'Error', true);
				if (wishlistSubmitBtn) { wishlistSubmitBtn.textContent = config.i18n.createWishlist || 'Create wishlist'; }
			});
		});
	}

	// --- Item form handlers ---

	if (itemNewBtn) {
		itemNewBtn.addEventListener('click', function () {
			if (!itemForm) { return; }
			itemEditingId.value = '';
			itemForm.reset();
			itemForm.querySelector('[name="item_currency"]').value = 'DKK';
			if (itemSubmitBtn) { itemSubmitBtn.textContent = config.i18n.saveItem || 'Save item'; }
			itemForm.hidden = !itemForm.hidden;
		});
	}

	if (itemCancelBtn) {
		itemCancelBtn.addEventListener('click', function () {
			if (itemForm) { itemForm.hidden = true; }
		});
	}

	if (wishlistBackBtn) {
		wishlistBackBtn.addEventListener('click', function () {
			activeWishlistId = null;
			if (wishlistDetailEl) { wishlistDetailEl.hidden = true; }
			if (wishlistsListEl) { wishlistsListEl.hidden = false; }
		});
	}

	if (wishlistCopyLinkBtn) {
		wishlistCopyLinkBtn.addEventListener('click', function () {
			var link = wishlistShareLink ? wishlistShareLink.href : '';
			if (!link) { return; }
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(link).then(function () {
					wishlistCopyLinkBtn.textContent = config.i18n.linkCopied || 'Link copied!';
					setTimeout(function () {
						wishlistCopyLinkBtn.textContent = config.i18n.copyLink || 'Copy link';
					}, 2000);
				});
			}
		});
	}

	if (itemForm) {
		itemForm.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!activeWishlistId) { return; }
			var editId = itemEditingId ? itemEditingId.value : '';
			var titleField = itemForm.querySelector('[name="item_title"]');
			var titleVal = titleField ? titleField.value.trim() : '';

			if (!titleVal) {
				setItemsStatus(config.i18n.titleRequired || 'Title is required.', true);
				return;
			}

			var priceField = itemForm.querySelector('[name="item_price"]');
			var priceVal = priceField && priceField.value !== '' ? parseFloat(priceField.value) : null;

			var payload = {
				title: titleVal,
				description: (itemForm.querySelector('[name="item_description"]') || {}).value || '',
				url: (itemForm.querySelector('[name="item_url"]') || {}).value || '',
				price: priceVal,
				currency: (itemForm.querySelector('[name="item_currency"]') || {}).value || 'DKK',
				is_purchased: !!(itemForm.querySelector('[name="item_is_purchased"]') || {}).checked
			};

			var method = editId ? 'PUT' : 'POST';
			var url = editId
				? config.wishlistsUrl + '/' + activeWishlistId + '/items/' + editId
				: config.wishlistsUrl + '/' + activeWishlistId + '/items';

			if (itemSubmitBtn) { itemSubmitBtn.textContent = config.i18n.savingItem || 'Saving...'; }

			fetch(url, {
				method: method,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.restNonce
				},
				body: JSON.stringify(payload)
			})
			.then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
			.then(function () {
				itemForm.hidden = true;
				itemForm.reset();
				itemForm.querySelector('[name="item_currency"]').value = 'DKK';
				itemEditingId.value = '';
				loadWishlistItems(activeWishlistId);
			})
			.catch(function () {
				setItemsStatus(config.i18n.genericError || 'Error', true);
				if (itemSubmitBtn) { itemSubmitBtn.textContent = config.i18n.saveItem || 'Save item'; }
			});
		});
	}

	// Expose wishlists panel initialisation for nav (mirrors existing patterns).
	// loadWishlists() is called when the user navigates to the wishlists view.

	// =========================================================================
	// Reminder Templates
	// =========================================================================

	var templatesModal      = root.querySelector('[data-remindmii-templates-modal]');
	var templatesOpenBtn    = root.querySelector('[data-remindmii-templates-open]');
	var templatesCloseBtn   = root.querySelector('[data-remindmii-templates-close]');
	var templatesListEl     = root.querySelector('[data-remindmii-templates-list]');
	var templatesFilterEl   = root.querySelector('[data-remindmii-templates-filter]');

	var allTemplates          = [];
	var activeTemplateCategory = 'all';

	function loadTemplates() {
		if ( ! config.templatesUrl || ! templatesListEl ) { return; }
		if ( allTemplates.length ) {
			renderTemplates();
			return;
		}
		if ( templatesListEl ) {
			templatesListEl.innerHTML = '<li class="remindmii-empty">' + escapeHtml( config.i18n.loadingTemplates || 'Loading...' ) + '</li>';
		}
		fetch( config.templatesUrl )
			.then( function (r) { return r.ok ? r.json() : Promise.reject(r); } )
			.then( function (data) {
				allTemplates = data.templates || [];
				buildTemplateCategoryFilter();
				renderTemplates();
			} )
			.catch( function () {
				if ( templatesListEl ) { templatesListEl.innerHTML = ''; }
			} );
	}

	function buildTemplateCategoryFilter() {
		if ( ! templatesFilterEl ) { return; }
		var cats = ['all'];
		allTemplates.forEach( function (t) {
			if ( cats.indexOf(t.category) === -1 ) { cats.push(t.category); }
		} );

		templatesFilterEl.innerHTML = '';
		cats.forEach( function (cat) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'remindmii-button remindmii-button--secondary remindmii-button--small remindmii-template-cat-btn' + (cat === activeTemplateCategory ? ' is-active' : '');
			var catLabels = (config.i18n.templateCategories || {});
			btn.textContent = cat === 'all' ? (config.i18n.templatesAll || 'All') : (catLabels[cat] || cat);
			btn.addEventListener('click', function () {
				activeTemplateCategory = cat;
				templatesFilterEl.querySelectorAll('.remindmii-template-cat-btn').forEach( function (b) { b.classList.remove('is-active'); } );
				btn.classList.add('is-active');
				renderTemplates();
			});
			templatesFilterEl.appendChild(btn);
		} );
	}

	function renderTemplates() {
		if ( ! templatesListEl ) { return; }
		templatesListEl.innerHTML = '';
		var filtered = activeTemplateCategory === 'all'
			? allTemplates
			: allTemplates.filter( function (t) { return t.category === activeTemplateCategory; } );

		filtered.forEach( function (tmpl) {
			var li = document.createElement('li');
			li.className = 'remindmii-template-row';
			li.innerHTML =
				'<span class="remindmii-template-row__icon">' + escapeHtml(tmpl.icon || '') + '</span>' +
				'<div class="remindmii-template-row__body">' +
				'<strong>' + escapeHtml(tmpl.name) + '</strong>' +
				( tmpl.description ? '<p>' + escapeHtml(tmpl.description) + '</p>' : '' ) +
				'</div>' +
				'<button type="button" class="remindmii-button remindmii-button--small" data-action="apply-template">' + escapeHtml(config.i18n.useTemplate || 'Use') + '</button>';

			li.querySelector('[data-action="apply-template"]').addEventListener('click', function () {
				applyTemplate(tmpl);
			});
			templatesListEl.appendChild(li);
		} );
	}

	function applyTemplate(tmpl) {
		var df = tmpl.default_fields || {};
		if ( form ) {
			var titleInput = form.querySelector('[name="title"]');
			var descInput  = form.querySelector('[name="description"]');
			var recurCheck = form.querySelector('[name="is_recurring"]');
			var recurSel   = form.querySelector('[name="recurrence_interval"]');

			if ( titleInput && df.title ) { titleInput.value = df.title; }
			if ( descInput  && df.description !== undefined ) { descInput.value = df.description; }
			if ( recurCheck ) { recurCheck.checked = !!df.is_recurring; }
			if ( recurSel   && df.recurrence_interval !== undefined ) { recurSel.value = df.recurrence_interval; }
		}
		if ( templatesModal ) { templatesModal.hidden = true; }
	}

	if ( templatesOpenBtn ) {
		templatesOpenBtn.addEventListener('click', function () {
			if ( ! templatesModal ) { return; }
			templatesModal.hidden = false;
			loadTemplates();
		});
	}

	if ( templatesCloseBtn ) {
		templatesCloseBtn.addEventListener('click', function () {
			if ( templatesModal ) { templatesModal.hidden = true; }
		});
	}

	if ( templatesModal ) {
		templatesModal.addEventListener('click', function (e) {
			if ( e.target === templatesModal ) { templatesModal.hidden = true; }
		});
	}

	// =========================================================================
	// Preferences
	// =========================================================================

	var prefsPanel      = root.querySelector('[data-remindmii-preferences-panel]');
	var prefsSaveBtn    = root.querySelector('[data-remindmii-preferences-save]');
	var prefsStatusEl   = root.querySelector('[data-remindmii-preferences-status]');
	var themeGrid       = root.querySelector('[data-remindmii-theme-grid]');

	var currentPrefs = { theme: 'default', enable_location_reminders: false, enable_gamification: true, distracted_mode: false };

	function loadPreferences() {
		if ( ! config.preferencesUrl ) { return; }
		fetch( config.preferencesUrl, {
			headers: { 'X-WP-Nonce': config.restNonce }
		} )
		.then( function (r) { return r.ok ? r.json() : Promise.reject(r); } )
		.then( function (data) {
			currentPrefs = data;
			renderPreferences(data);
		} )
		.catch( function () {} );
	}

	function renderPreferences(prefs) {
		// Theme buttons.
		if ( themeGrid ) {
			themeGrid.querySelectorAll('[data-remindmii-theme]').forEach( function (btn) {
				btn.classList.toggle('is-active', btn.getAttribute('data-remindmii-theme') === prefs.theme);
			} );
		}
		// Toggle buttons.
		if ( prefsPanel ) {
			['enable_location_reminders', 'enable_gamification', 'distracted_mode'].forEach( function (key) {
				var btn = prefsPanel.querySelector('[data-remindmii-pref-toggle="' + key + '"]');
				if ( btn ) {
					var val = !!prefs[key];
					btn.setAttribute('aria-checked', val ? 'true' : 'false');
					btn.classList.toggle('is-on', val);
				}
			} );
		}
	}

	if ( themeGrid ) {
		themeGrid.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-remindmii-theme]');
			if ( ! btn ) { return; }
			currentPrefs.theme = btn.getAttribute('data-remindmii-theme');
			renderPreferences(currentPrefs);
		} );
	}

	if ( prefsPanel ) {
		prefsPanel.querySelectorAll('[data-remindmii-pref-toggle]').forEach( function (btn) {
			btn.addEventListener('click', function () {
				var key = btn.getAttribute('data-remindmii-pref-toggle');
				currentPrefs[key] = ! currentPrefs[key];
				btn.setAttribute('aria-checked', currentPrefs[key] ? 'true' : 'false');
				btn.classList.toggle('is-on', currentPrefs[key]);
			} );
		} );
	}

	if ( prefsSaveBtn ) {
		prefsSaveBtn.addEventListener('click', function () {
			if ( ! config.preferencesUrl ) { return; }
			prefsSaveBtn.textContent = config.i18n.savingPreferences || 'Saving...';
			fetch( config.preferencesUrl, {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.restNonce
				},
				body: JSON.stringify(currentPrefs)
			} )
			.then( function (r) { return r.ok ? r.json() : Promise.reject(r); } )
			.then( function (data) {
				currentPrefs = data;
				renderPreferences(data);
				applyThemeClass(data.theme);
				prefsSaveBtn.textContent = config.i18n.savePreferences || 'Save preferences';
				if ( prefsStatusEl ) {
					prefsStatusEl.textContent = config.i18n.preferencesSaved || 'Preferences saved.';
					prefsStatusEl.hidden = false;
					setTimeout( function () { prefsStatusEl.hidden = true; }, 3000 );
				}
			} )
			.catch( function () {
				prefsSaveBtn.textContent = config.i18n.savePreferences || 'Save preferences';
				if ( prefsStatusEl ) {
					prefsStatusEl.textContent = config.i18n.genericError || 'Error';
					prefsStatusEl.hidden = false;
				}
			} );
		} );
	}

	function applyThemeClass(theme) {
		var appEl = document.querySelector('[data-remindmii-app]');
		if ( ! appEl ) { return; }
		appEl.setAttribute('data-remindmii-theme', theme || 'default');
	}

	// =========================================================================
	// Shared lists (shared-with-me + share per wishlist)
	// =========================================================================

	var sharedPanel    = root.querySelector('[data-remindmii-shared-panel]');
	var sharedListEl   = root.querySelector('[data-remindmii-shared-list]');
	var sharesListEl   = root.querySelector('[data-remindmii-shares-list]');
	var sharesSection  = root.querySelector('[data-remindmii-wishlist-shares]');
	var shareForm      = root.querySelector('[data-remindmii-share-form]');
	var sharesStatus   = root.querySelector('[data-remindmii-shares-status]');
	var currentWishlistIdForShares = null;

	function loadSharedWithMe() {
		if ( ! config.sharedWithMeUrl || ! sharedListEl ) { return; }
		sharedListEl.innerHTML = '<p>' + ( config.i18n.loadingSharedLists || 'Loading...' ) + '</p>';
		fetch( config.sharedWithMeUrl, {
			headers: { 'X-WP-Nonce': config.restNonce }
		} )
		.then( function (r) { return r.ok ? r.json() : Promise.reject(r); } )
		.then( function (data) {
			var shares = data.shares || [];
			if ( shares.length === 0 ) {
				sharedListEl.innerHTML = '<p>' + ( config.i18n.noSharedLists || 'No lists shared with you yet.' ) + '</p>';
				return;
			}
			var html = '<ul class="remindmii-shared-items">';
			shares.forEach( function (s) {
				html += '<li><strong>' + escapeHtml( s.wishlist_title || '' ) + '</strong>';
				if ( s.wishlist_description ) { html += '<br><small>' + escapeHtml( s.wishlist_description ) + '</small>'; }
				html += '<br><small>' + escapeHtml( s.permission ) + ' — ' + escapeHtml( s.shared_with_email ) + '</small></li>';
			} );
			html += '</ul>';
			sharedListEl.innerHTML = html;
		} )
		.catch( function () {
			sharedListEl.innerHTML = '<p>' + ( config.i18n.genericError || 'Error' ) + '</p>';
		} );
	}

	function loadWishlistShares( wishlistId ) {
		if ( ! sharesListEl ) { return; }
		var url = config.wishlistsUrl + '/' + wishlistId + '/shares';
		fetch( url, { headers: { 'X-WP-Nonce': config.restNonce } } )
		.then( function (r) { return r.ok ? r.json() : Promise.reject(r); } )
		.then( function (data) {
			renderSharesList( data.shares || [] );
		} )
		.catch( function () {} );
	}

	function renderSharesList( shares ) {
		if ( ! sharesListEl ) { return; }
		if ( shares.length === 0 ) {
			sharesListEl.innerHTML = '';
			return;
		}
		var html = '';
		shares.forEach( function (s) {
			html += '<li class="remindmii-share-item">' +
				escapeHtml( s.shared_with_email ) +
				' <span class="remindmii-badge">' + escapeHtml( s.permission ) + '</span>' +
				' <button type="button" class="remindmii-button remindmii-button--danger remindmii-button--sm" data-revoke-share="' + s.id + '">' +
				( config.i18n.revokeShare || 'Revoke' ) +
				'</button></li>';
		} );
		sharesListEl.innerHTML = html;
	}

	if ( sharesListEl ) {
		sharesListEl.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-revoke-share]');
			if ( ! btn || ! currentWishlistIdForShares ) { return; }
			var shareId = btn.getAttribute('data-revoke-share');
			var url = config.wishlistsUrl + '/' + currentWishlistIdForShares + '/shares/' + shareId;
			fetch( url, { method: 'DELETE', headers: { 'X-WP-Nonce': config.restNonce } } )
			.then( function (r) { return r.ok ? r.json() : Promise.reject(r); } )
			.then( function () { loadWishlistShares( currentWishlistIdForShares ); } )
			.catch( function () {} );
		} );
	}

	if ( shareForm ) {
		shareForm.addEventListener('submit', function (e) {
			e.preventDefault();
			if ( ! currentWishlistIdForShares ) { return; }
			var email      = shareForm.querySelector('[name="share_email"]').value.trim();
			var permission = shareForm.querySelector('[name="share_permission"]').value;
			var submitBtn  = shareForm.querySelector('[data-remindmii-share-submit]');
			if ( submitBtn ) { submitBtn.disabled = true; }

			var url = config.wishlistsUrl + '/' + currentWishlistIdForShares + '/shares';
			fetch( url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
				body: JSON.stringify( { shared_with_email: email, permission: permission } )
			} )
			.then( function (r) { return r.ok ? r.json() : r.json().then( function (e) { return Promise.reject(e); } ); } )
			.then( function () {
				shareForm.reset();
				loadWishlistShares( currentWishlistIdForShares );
				if ( sharesStatus ) { sharesStatus.textContent = ''; sharesStatus.hidden = true; }
			} )
			.catch( function (err) {
				if ( sharesStatus ) {
					sharesStatus.textContent = ( err && err.message ) || ( config.i18n.genericError || 'Error' );
					sharesStatus.hidden = false;
				}
			} )
			.finally( function () {
				if ( submitBtn ) { submitBtn.disabled = false; }
			} );
		} );
	}

	// =========================================================================
	// Gamification
	// =========================================================================

	var gamificationPanel  = root.querySelector('[data-remindmii-gamification-panel]');
	var statPoints         = root.querySelector('[data-remindmii-stat-points]');
	var statStreak         = root.querySelector('[data-remindmii-stat-streak]');
	var statCompleted      = root.querySelector('[data-remindmii-stat-completed]');
	var statCreated        = root.querySelector('[data-remindmii-stat-created]');
	var badgesGrid         = root.querySelector('[data-remindmii-badges-grid]');

	function loadGamification() {
		if ( ! config.gamificationUrl ) { return; }
		fetch( config.gamificationUrl, {
			headers: { 'X-WP-Nonce': config.restNonce }
		} )
		.then( function (r) { return r.ok ? r.json() : Promise.reject(r); } )
		.then( function (data) {
			var s = data.stats || {};
			if ( statPoints )    { statPoints.textContent    = s.total_points    || 0; }
			if ( statStreak )    { statStreak.textContent    = s.current_streak  || 0; }
			if ( statCompleted ) { statCompleted.textContent = s.total_completed || 0; }
			if ( statCreated )   { statCreated.textContent   = s.total_reminders_created || 0; }

			if ( badgesGrid ) {
				var badges = data.all_badges || [];
				var html   = '';
				badges.forEach( function (b) {
					html += '<div class="remindmii-badge-card' + ( b.earned ? ' is-earned' : ' is-locked' ) + '">' +
						'<span class="remindmii-badge-card__icon">' + escapeHtml( b.icon || '🏅' ) + '</span>' +
						'<strong>' + escapeHtml( b.name ) + '</strong>' +
						'<small>' + escapeHtml( b.description ) + '</small>' +
						( b.earned ? '<span class="remindmii-badge-earned-tag">✓ Earned</span>' : '<span class="remindmii-badge-locked-tag">🔒 Locked</span>' ) +
						'</div>';
				} );
				badgesGrid.innerHTML = html;
			}
		} )
		.catch( function () {} );
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
