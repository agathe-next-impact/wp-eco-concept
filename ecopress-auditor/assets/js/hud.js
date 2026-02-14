/**
 * EcoPress HUD – Vanilla JS front-end overlay.
 * Zero framework dependency. Target: < 5 KB unminified.
 *
 * @package EcoPress
 */
(function () {
	'use strict';

	if (typeof ecopressHud === 'undefined') {
		return;
	}

	var config = ecopressHud;
	var i18n = config.i18n;
	var panel = null;
	var toggle = null;
	var loaded = false;

	/**
	 * Format bytes to human-readable string.
	 *
	 * @param {number} bytes
	 * @return {string}
	 */
	function formatSize(bytes) {
		if (bytes >= 1048576) {
			return (bytes / 1048576).toFixed(1) + ' Mo';
		}
		return (bytes / 1024).toFixed(0) + ' Ko';
	}

	/**
	 * Extract filename from URL.
	 *
	 * @param {string} url
	 * @return {string}
	 */
	function extractFilename(url) {
		try {
			var parts = url.split('/');
			var name = parts[parts.length - 1].split('?')[0];
			return name || url;
		} catch (e) {
			return url;
		}
	}

	/**
	 * Create the HUD DOM elements.
	 */
	function createHUD() {
		// Toggle button.
		toggle = document.createElement('button');
		toggle.id = 'ecopress-hud-toggle';
		toggle.setAttribute('aria-label', i18n.title);
		toggle.setAttribute('title', i18n.title);
		toggle.textContent = 'E';
		toggle.addEventListener('click', togglePanel);

		// Panel.
		panel = document.createElement('div');
		panel.id = 'ecopress-hud-panel';
		panel.setAttribute('role', 'dialog');
		panel.setAttribute('aria-label', i18n.title);
		panel.innerHTML =
			'<div class="ecopress-hud-header">' +
				'<h3>' + i18n.title + '</h3>' +
				'<button class="ecopress-hud-close" aria-label="' + i18n.close + '">&times;</button>' +
			'</div>' +
			'<div class="ecopress-loading">' + i18n.loading + '</div>';

		panel.querySelector('.ecopress-hud-close').addEventListener('click', closePanel);

		document.body.appendChild(toggle);
		document.body.appendChild(panel);
	}

	/**
	 * Toggle the panel visibility.
	 */
	function togglePanel() {
		if (panel.classList.contains('ecopress-open')) {
			closePanel();
		} else {
			openPanel();
		}
	}

	/**
	 * Open the panel and fetch data if needed.
	 */
	function openPanel() {
		panel.classList.add('ecopress-open');
		if (!loaded) {
			fetchData();
		}
	}

	/**
	 * Close the panel.
	 */
	function closePanel() {
		panel.classList.remove('ecopress-open');
	}

	/**
	 * Fetch audit data via AJAX.
	 */
	function fetchData() {
		var url = config.ajaxUrl + '?action=ecopress_hud_data&nonce=' +
			encodeURIComponent(config.nonce) + '&post_id=' + config.postId;

		var xhr = new XMLHttpRequest();
		xhr.open('GET', url);
		xhr.responseType = 'json';
		xhr.onload = function () {
			if (xhr.status === 200 && xhr.response && xhr.response.success) {
				renderData(xhr.response.data);
				loaded = true;
			} else {
				renderError();
			}
		};
		xhr.onerror = function () {
			renderError();
		};
		xhr.send();
	}

	/**
	 * Render the audit data into the panel.
	 *
	 * @param {Object} data
	 */
	function renderData(data) {
		var isAlert = data.weight_kb > config.threshold;
		var html = '';

		// Header stays.
		html += '<div class="ecopress-hud-header">';
		html += '<h3>' + i18n.title + '</h3>';
		html += '<button class="ecopress-hud-close" aria-label="' + i18n.close + '">&times;</button>';
		html += '</div>';

		// Metrics.
		html += '<div class="ecopress-metrics">';
		html += '<div class="ecopress-metric">';
		html += '<div class="ecopress-metric-value">' + data.weight_kb + ' Ko</div>';
		html += '<div class="ecopress-metric-label">' + i18n.weight + '</div>';
		html += '</div>';
		html += '<div class="ecopress-metric">';
		html += '<div class="ecopress-metric-value" style="color:' + data.grade_color + '">' + data.grade + '</div>';
		html += '<div class="ecopress-metric-label">' + i18n.grade + '</div>';
		html += '</div>';
		html += '<div class="ecopress-metric">';
		html += '<div class="ecopress-metric-value">' + data.co2_g + ' g</div>';
		html += '<div class="ecopress-metric-label">' + i18n.co2 + '</div>';
		html += '</div>';
		html += '</div>';

		// Alert.
		html += '<div class="ecopress-alert-bar' + (isAlert ? ' ecopress-visible' : '') + '">';
		html += i18n.alert;
		html += '</div>';

		// Resources.
		if (data.resources && data.resources.length > 0) {
			html += '<div class="ecopress-resources">';
			html += '<h4>' + i18n.resources + '</h4>';
			for (var i = 0; i < data.resources.length; i++) {
				var res = data.resources[i];
				html += '<div class="ecopress-resource-item">';
				html += '<span class="ecopress-resource-type" data-type="' + res.type + '">' + res.type + '</span>';
				html += '<span class="ecopress-resource-name" title="' + res.url + '">' + extractFilename(res.url) + '</span>';
				html += '<span class="ecopress-resource-size">' + formatSize(res.size) + '</span>';
				html += '</div>';
			}
			html += '</div>';
		}

		panel.innerHTML = html;
		panel.querySelector('.ecopress-hud-close').addEventListener('click', closePanel);

		// Update toggle color.
		toggle.style.background = data.grade_color;
		toggle.textContent = data.grade;

		if (isAlert) {
			toggle.classList.add('ecopress-alert');
		}
	}

	/**
	 * Render an error state.
	 */
	function renderError() {
		var loading = panel.querySelector('.ecopress-loading');
		if (loading) {
			loading.textContent = i18n.error;
		}
	}

	// Initialize on DOMContentLoaded.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', createHUD);
	} else {
		createHUD();
	}
})();
