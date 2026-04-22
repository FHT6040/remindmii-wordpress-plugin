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
	var form = root.querySelector('[data-remindmii-form]');
	var list = root.querySelector('[data-remindmii-list]');
	var submitButton = root.querySelector('[data-remindmii-submit]');

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

	if (list) {
		list.hidden = false;
	}

	loadReminders();

	async function loadReminders() {
		setStatus(config.i18n.loading, false);

		try {
			var response = await apiRequest(config.restUrl, { method: 'GET' });
			var reminders = await response.json();

			renderReminders(Array.isArray(reminders) ? reminders : []);
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
		var payload = {
			title: title,
			description: String(formData.get('description') || '').trim(),
			reminder_date: reminderDate.toISOString(),
			is_recurring: isRecurring,
			recurrence_interval: isRecurring ? String(formData.get('recurrence_interval') || '') : '',
			is_completed: false
		};

		submitButton.disabled = true;
		submitButton.textContent = config.i18n.creating;
		setStatus('', false);

		try {
			await apiRequest(config.restUrl, {
				method: 'POST',
				body: JSON.stringify(payload)
			});

			form.reset();
			await loadReminders();
		} catch (error) {
			setStatus(getErrorMessage(error), true);
		} finally {
			submitButton.disabled = false;
			submitButton.textContent = config.i18n.create;
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
			var description = reminder.description ? '<p class="remindmii-reminder__description">' + escapeHtml(reminder.description) + '</p>' : '';

			item.innerHTML = '' +
				'<div class="remindmii-reminder__content">' +
					'<h3>' + escapeHtml(reminder.title || config.i18n.untitled) + '</h3>' +
					'<p class="remindmii-reminder__meta"><strong>' + escapeHtml(config.i18n.dueLabel) + ':</strong> ' + escapeHtml(dueDate) + '</p>' +
					description +
				'</div>' +
				'<div class="remindmii-reminder__actions">' +
					'<button type="button" class="remindmii-button remindmii-button--secondary" data-action="toggle">' + escapeHtml(reminder.is_completed ? config.i18n.markActive : config.i18n.markComplete) + '</button>' +
					'<button type="button" class="remindmii-button remindmii-button--danger" data-action="delete">' + escapeHtml(config.i18n.delete) + '</button>' +
				'</div>';

			item.querySelector('[data-action="toggle"]').addEventListener('click', function () {
				handleToggleReminder(reminder);
			});

			item.querySelector('[data-action="delete"]').addEventListener('click', function () {
				handleDeleteReminder(reminder.id);
			});

			list.appendChild(item);
		});
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

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}
});