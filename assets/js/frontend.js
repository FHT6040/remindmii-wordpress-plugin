document.addEventListener('DOMContentLoaded', function () {
	var root = document.querySelector('[data-remindmii-app]');

	if (!root) {
		return;
	}

	root.setAttribute('data-remindmii-ready', 'true');
});