/**
 * EcoPress Admin Metabox – Vanilla JS.
 *
 * @package EcoPress
 */
(function () {
	'use strict';

	if (typeof ecopressAdmin === 'undefined') {
		return;
	}

	var config = ecopressAdmin;
	var i18n = config.i18n;

	/**
	 * Format bytes to human-readable.
	 *
	 * @param {number} bytes
	 * @return {string}
	 */
	function formatSize(bytes) {
		if (bytes >= 1048576) {
			return (bytes / 1048576).toFixed(1) + ' Mo';
		}
		if (bytes >= 1024) {
			return (bytes / 1024).toFixed(0) + ' Ko';
		}
		return bytes + ' o';
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
			return name.length > 40 ? name.substring(0, 37) + '...' : name;
		} catch (e) {
			return url;
		}
	}

	/**
	 * Run audit via AJAX.
	 */
	function runAudit() {
		var btn = document.getElementById('ecopress-run-audit');
		var output = document.getElementById('ecopress-audit-output');

		if (!btn || !output) {
			return;
		}

		btn.disabled = true;
		btn.innerHTML = '<span class="ecopress-spinner"></span>' + i18n.running;
		output.innerHTML = '';

		var formData = new FormData();
		formData.append('action', 'ecopress_run_audit');
		formData.append('nonce', config.nonce);
		formData.append('post_id', config.postId);

		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl);
		xhr.responseType = 'json';
		xhr.onload = function () {
			btn.disabled = false;
			btn.textContent = i18n.runAudit;

			if (xhr.status === 200 && xhr.response && xhr.response.success) {
				renderAuditResults(output, xhr.response.data);
			} else {
				output.innerHTML = '<p class="ecopress-error">' + i18n.error + '</p>';
			}
		};
		xhr.onerror = function () {
			btn.disabled = false;
			btn.textContent = i18n.runAudit;
			output.innerHTML = '<p class="ecopress-error">' + i18n.error + '</p>';
		};
		xhr.send(formData);
	}

	/**
	 * Render audit results.
	 *
	 * @param {HTMLElement} container
	 * @param {Object} data
	 */
	function renderAuditResults(container, data) {
		var html = '';

		// Summary cards.
		html += '<div class="ecopress-audit-summary">';
		html += '<div class="ecopress-audit-card">';
		html += '<div class="ecopress-audit-card-value">' + data.weight_kb + ' Ko</div>';
		html += '<div class="ecopress-audit-card-label">' + i18n.weight + '</div>';
		html += '</div>';
		html += '<div class="ecopress-audit-card">';
		html += '<div class="ecopress-audit-card-value" style="color:' + data.grade_color + '">' + data.grade + '</div>';
		html += '<div class="ecopress-audit-card-label">' + i18n.grade + '</div>';
		html += '</div>';
		html += '<div class="ecopress-audit-card">';
		html += '<div class="ecopress-audit-card-value">' + data.co2_g + ' g</div>';
		html += '<div class="ecopress-audit-card-label">' + i18n.co2 + '</div>';
		html += '</div>';
		html += '</div>';

		// Resources table.
		if (data.resources && data.resources.length > 0) {
			html += '<h4>' + i18n.topRes + '</h4>';
			html += '<table class="ecopress-resources-table">';
			html += '<thead><tr>';
			html += '<th>' + i18n.resource + '</th>';
			html += '<th>' + i18n.type + '</th>';
			html += '<th>' + i18n.size + '</th>';
			html += '</tr></thead>';
			html += '<tbody>';

			for (var i = 0; i < data.resources.length; i++) {
				var res = data.resources[i];
				var typeClass = 'ecopress-res-type--' + res.type;
				html += '<tr>';
				html += '<td class="ecopress-res-url" title="' + res.url + '">' + extractFilename(res.url) + '</td>';
				html += '<td><span class="ecopress-res-type ' + typeClass + '">' + res.type + '</span></td>';
				html += '<td class="ecopress-res-size">' + formatSize(res.size) + '</td>';
				html += '</tr>';
			}

			html += '</tbody></table>';
		}

		// Convert button.
		html += '<button type="button" class="button" id="ecopress-convert-btn">';
		html += i18n.convertWebp;
		html += '</button>';

		container.innerHTML = html;

		// Bind convert button.
		var convertBtn = document.getElementById('ecopress-convert-btn');
		if (convertBtn) {
			convertBtn.addEventListener('click', convertImages);
		}
	}

	/**
	 * Convert images to WebP via AJAX.
	 */
	function convertImages() {
		var btn = document.getElementById('ecopress-convert-btn');
		if (!btn) {
			return;
		}

		btn.disabled = true;
		btn.innerHTML = '<span class="ecopress-spinner"></span>' + i18n.converting;

		var formData = new FormData();
		formData.append('action', 'ecopress_convert_images');
		formData.append('nonce', config.nonce);
		formData.append('post_id', config.postId);
		formData.append('format', 'webp');

		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl);
		xhr.responseType = 'json';
		xhr.onload = function () {
			btn.disabled = false;
			if (xhr.status === 200 && xhr.response && xhr.response.success) {
				btn.textContent = xhr.response.data.message;
			} else {
				btn.textContent = i18n.error;
			}
		};
		xhr.onerror = function () {
			btn.disabled = false;
			btn.textContent = i18n.error;
		};
		xhr.send(formData);
	}

	// Initialize.
	document.addEventListener('DOMContentLoaded', function () {
		var btn = document.getElementById('ecopress-run-audit');
		if (btn) {
			btn.addEventListener('click', runAudit);
		}
	});
})();
