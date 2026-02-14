/**
 * EcoDiag Dashboard JavaScript
 * Handles dashboard data loading, charts, and global actions.
 */
(function () {
    'use strict';

    var config = window.ecodiagDashboard || {};
    var ajaxUrl = config.ajaxUrl;
    var nonce = config.nonce;
    var i18n = config.i18n || {};

    // Utility functions
    function fmt(bytes) {
        if (!bytes) return '0 o';
        bytes = parseInt(bytes, 10);
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' Mo';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' Ko';
        return bytes + ' o';
    }

    function scoreColor(score) {
        score = parseInt(score, 10);
        if (score >= 75) return '#2ecc71';
        if (score >= 50) return '#f39c12';
        return '#e74c3c';
    }

    function scoreClass(score) {
        score = parseInt(score, 10);
        if (score >= 75) return 'green';
        if (score >= 50) return 'orange';
        return 'red';
    }

    // AJAX helper
    function ajax(action, data, callback) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        if (data) {
            Object.keys(data).forEach(function (k) {
                if (Array.isArray(data[k])) {
                    data[k].forEach(function (v, i) {
                        fd.append(k + '[' + i + ']', v);
                    });
                } else {
                    fd.append(k, data[k]);
                }
            });
        }
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (r) { callback(r); })
            .catch(function (e) { callback({ success: false, data: e.message }); });
    }

    // Notification
    function notify(msg, type) {
        var el = document.getElementById('ecodiag-action-notification');
        if (!el) return;
        el.textContent = msg;
        el.className = 'ecodiag-notification ' + (type || 'success');
        el.style.display = '';
        setTimeout(function () { el.style.display = 'none'; }, 4000);
    }

    // ==================================================================
    // DASHBOARD PAGE
    // ==================================================================
    if (document.getElementById('ecodiag-global-score')) {
        loadDashboard();
    }

    function loadDashboard() {
        ajax('ecodiag_dashboard_data', null, function (r) {
            if (!r.success) return;
            var d = r.data;

            // Global score
            var avg = d.averages;
            if (avg && avg.avg_score !== null) {
                var score = Math.round(avg.avg_score);
                var el = document.getElementById('ecodiag-avg-score');
                el.textContent = score;
                el.parentElement.className = 'ecodiag-big-score ' + scoreClass(score);
                document.getElementById('ecodiag-pages-audited').textContent =
                    (avg.total_pages || 0) + ' page(s) auditée(s)';
            }

            // Counters
            var counters = document.getElementById('ecodiag-counters');
            if (counters && avg) {
                counters.innerHTML =
                    statItem(avg.total_img_issues || 0, 'Images non opt.') +
                    statItem(d.database ? d.database.revisions.count : 0, 'Révisions') +
                    statItem(d.media ? d.media.non_converted.count : 0, 'Non converties') +
                    statItem(d.database ? d.database.db_size.formatted : '—', 'Taille BDD');
            }

            // Heaviest pages table
            renderTable('ecodiag-heaviest-table', d.heaviest || [], function (row) {
                return '<td><a href="post.php?action=edit&post=' + row.object_id + '">' + (row.post_title || '#' + row.object_id) + '</a></td>' +
                    '<td>' + fmt(row.page_weight) + '</td>' +
                    '<td><span class="ecodiag-score-badge" style="background:' + scoreColor(row.score) + '">' + row.score + '</span></td>';
            });

            // Worst scored table
            renderTable('ecodiag-worst-table', d.worst || [], function (row) {
                return '<td><a href="post.php?action=edit&post=' + row.object_id + '">' + (row.post_title || '#' + row.object_id) + '</a></td>' +
                    '<td><span class="ecodiag-score-badge" style="background:' + scoreColor(row.score) + '">' + row.score + '</span></td>' +
                    '<td>' + fmt(row.page_weight) + '</td>';
            });

            // History chart (simple canvas bars)
            renderHistoryChart(d.history || []);
        });
    }

    function statItem(value, label) {
        return '<div class="ecodiag-stat-item"><div class="ecodiag-stat-value">' + value + '</div><div class="ecodiag-stat-label">' + label + '</div></div>';
    }

    function renderTable(id, rows, rowRenderer) {
        var table = document.getElementById(id);
        if (!table) return;
        var tbody = table.querySelector('tbody');
        tbody.innerHTML = '';
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#999">Aucune donnée</td></tr>';
            return;
        }
        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.innerHTML = rowRenderer(row);
            tbody.appendChild(tr);
        });
    }

    function renderHistoryChart(history) {
        var canvas = document.getElementById('ecodiag-history-chart');
        if (!canvas || history.length === 0) return;

        var ctx = canvas.getContext('2d');
        var w = canvas.parentElement.offsetWidth;
        canvas.width = w;
        canvas.height = 200;

        var maxScore = 100;
        var padding = 40;
        var chartW = w - padding * 2;
        var chartH = 160;
        var barW = Math.max(4, Math.min(20, chartW / history.length - 2));

        // Background
        ctx.fillStyle = '#f6f7f7';
        ctx.fillRect(0, 0, w, 200);

        // Grid lines
        ctx.strokeStyle = '#ddd';
        ctx.lineWidth = 1;
        [25, 50, 75, 100].forEach(function (v) {
            var y = padding + chartH - (v / maxScore * chartH);
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(w - padding, y);
            ctx.stroke();
            ctx.fillStyle = '#999';
            ctx.font = '10px sans-serif';
            ctx.fillText(v, 5, y + 3);
        });

        // Bars
        history.forEach(function (entry, i) {
            var score = Math.round(parseFloat(entry.avg_score));
            var x = padding + (i / history.length) * chartW;
            var barH = (score / maxScore) * chartH;
            var y = padding + chartH - barH;

            ctx.fillStyle = scoreColor(score);
            ctx.fillRect(x, y, barW, barH);
        });

        // X-axis labels (first, middle, last)
        ctx.fillStyle = '#666';
        ctx.font = '10px sans-serif';
        if (history.length > 0) {
            ctx.fillText(history[0].date, padding, 195);
            if (history.length > 2) {
                var mid = Math.floor(history.length / 2);
                ctx.fillText(history[mid].date, padding + (mid / history.length) * chartW, 195);
            }
            ctx.fillText(history[history.length - 1].date, w - padding - 60, 195);
        }
    }

    // Export CSV
    var exportBtn = document.getElementById('ecodiag-export-csv');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            ajax('ecodiag_export_csv', null, function (r) {
                if (!r.success) return notify(r.data || 'Erreur', 'error');
                var blob = new Blob([r.data.csv], { type: 'text/csv' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'ecodiag-export-' + new Date().toISOString().split('T')[0] + '.csv';
                a.click();
                URL.revokeObjectURL(url);
            });
        });
    }

    // Full audit button
    var fullAuditBtn = document.getElementById('ecodiag-run-full-audit');
    if (fullAuditBtn) {
        fullAuditBtn.addEventListener('click', function () {
            fullAuditBtn.disabled = true;
            fullAuditBtn.textContent = i18n.processing || 'Traitement…';
            // Trigger a batch audit via cron
            ajax('ecodiag_run_audit', { post_id: 0 }, function () {
                fullAuditBtn.disabled = false;
                fullAuditBtn.textContent = i18n.done || 'Terminé';
                notify('Audit planifié.', 'success');
            });
        });
    }

    // ==================================================================
    // DIAGNOSTICS PAGE
    // ==================================================================
    if (document.getElementById('ecodiag-diagnostics-content')) {
        loadDiagnostics();
    }

    function loadDiagnostics() {
        ajax('ecodiag_dashboard_data', null, function (r) {
            if (!r.success) return;
            var d = r.data;

            document.getElementById('ecodiag-diagnostics-loading').style.display = 'none';
            document.getElementById('ecodiag-diagnostics-content').style.display = '';

            renderBddDiag(d.database);
            renderPlgDiag(d.plugins);
            renderMedDiag(d.media);
            renderHeadDiag(d.head);
            renderTrackingDiag(d.tracking);
            renderEditorialDiag(d.editorial);
            renderCronDiag(d.cron);
            renderServerDiag(d.server);
        });
    }

    function diagCard(ref, title, value, detail, status, actionHtml) {
        return '<div class="ecodiag-diag-card ' + (status || 'info') + '">' +
            '<div class="ecodiag-diag-ref">' + ref + '</div>' +
            '<div class="ecodiag-diag-title">' + title + '</div>' +
            '<div class="ecodiag-diag-value">' + value + '</div>' +
            (detail ? '<div class="ecodiag-diag-detail">' + detail + '</div>' : '') +
            (actionHtml ? '<div class="ecodiag-diag-action">' + actionHtml + '</div>' : '') +
            '</div>';
    }

    function actionBtn(action, label, data) {
        var attrs = '';
        if (data) {
            Object.keys(data).forEach(function (k) {
                attrs += ' data-' + k + '="' + data[k] + '"';
            });
        }
        return '<button type="button" class="button ecodiag-global-action" data-action="' + action + '"' + attrs + '>' + label + '</button>';
    }

    function renderBddDiag(db) {
        if (!db) return;
        var el = document.getElementById('ecodiag-diag-bdd');
        el.innerHTML =
            diagCard('G-BDD-01', 'Révisions', db.revisions.count, db.revisions.formatted_weight, db.revisions.status,
                actionBtn('ecodiag_purge_all_revisions', 'Purger les révisions')) +
            diagCard('G-BDD-02', 'Transients expirés', db.transients.count, '', db.transients.status,
                actionBtn('ecodiag_clean_transients', 'Nettoyer')) +
            diagCard('G-BDD-03', 'Options autoload suspectes', db.autoload_options.count, '', db.autoload_options.status, '') +
            diagCard('G-BDD-04', 'Métadonnées orphelines', db.orphaned_meta.total,
                'posts: ' + db.orphaned_meta.postmeta + ', users: ' + db.orphaned_meta.usermeta + ', comments: ' + db.orphaned_meta.commentmeta,
                db.orphaned_meta.status, actionBtn('ecodiag_clean_orphan_postmeta', 'Purger')) +
            diagCard('G-BDD-05', 'Tables à optimiser', db.tables_to_optimize.count, '', db.tables_to_optimize.status,
                actionBtn('ecodiag_optimize_tables', 'Optimiser les tables')) +
            diagCard('G-BDD-07', 'Taille base de données', db.db_size.formatted, '', 'info', '');
    }

    function renderPlgDiag(plg) {
        if (!plg) return;
        var el = document.getElementById('ecodiag-diag-plg');
        var html = '';

        // Inactive plugins
        html += diagCard('G-PLG-01', 'Plugins inactifs', plg.inactive_plugins.count, '', plg.inactive_plugins.status, '');
        if (plg.inactive_plugins.items.length > 0) {
            html += '<div class="ecodiag-diag-card info"><div class="ecodiag-diag-title">Plugins inactifs à supprimer</div>';
            plg.inactive_plugins.items.forEach(function (p) {
                html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #eee">' +
                    '<span>' + p.name + ' (' + p.formatted_size + ')</span>' +
                    actionBtn('ecodiag_delete_plugin', 'Supprimer', { plugin: p.file }) +
                    '</div>';
            });
            html += '</div>';
        }

        // Inactive themes
        html += diagCard('G-PLG-02', 'Thèmes inactifs', plg.inactive_themes.count, '', plg.inactive_themes.status, '');
        if (plg.inactive_themes.items.length > 0) {
            html += '<div class="ecodiag-diag-card info"><div class="ecodiag-diag-title">Thèmes inactifs à supprimer</div>';
            plg.inactive_themes.items.forEach(function (t) {
                html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #eee">' +
                    '<span>' + t.name + ' (' + t.formatted_size + ')</span>' +
                    actionBtn('ecodiag_delete_theme', 'Supprimer', { theme: t.slug }) +
                    '</div>';
            });
            html += '</div>';
        }

        // Plugin asset weights
        if (plg.plugin_assets && plg.plugin_assets.items.length > 0) {
            html += '<div class="ecodiag-diag-card info"><div class="ecodiag-diag-ref">G-PLG-03</div><div class="ecodiag-diag-title">Poids des assets par plugin</div>';
            html += '<table class="ecodiag-table"><thead><tr><th>Plugin</th><th>JS+CSS</th></tr></thead><tbody>';
            plg.plugin_assets.items.slice(0, 10).forEach(function (p) {
                html += '<tr><td>' + p.name + '</td><td>' + p.formatted + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }

        el.innerHTML = html;
    }

    function renderMedDiag(med) {
        if (!med) return;
        var el = document.getElementById('ecodiag-diag-med');
        el.innerHTML =
            diagCard('G-MED-01', 'Images non converties (WebP/AVIF)', med.non_converted.count, '', med.non_converted.status,
                actionBtn('ecodiag_bulk_convert', 'Conversion bulk')) +
            diagCard('G-MED-03', 'Médias orphelins', med.orphan_media.count, '', med.orphan_media.status,
                actionBtn('ecodiag_delete_orphan_media', 'Supprimer les orphelins')) +
            diagCard('G-MED-04', 'Tailles d\'images supplémentaires', med.extra_sizes.count, '', 'info', '') +
            diagCard('G-MED-05', 'Taille médiathèque', med.total_size.formatted, '', 'info', '') +
            diagCard('G-MED-06', 'Images sans texte alternatif', med.no_alt.count, '', med.no_alt.status, '');
    }

    function renderHeadDiag(head) {
        if (!head || !Array.isArray(head)) return;
        var el = document.getElementById('ecodiag-diag-head');
        var html = '';
        head.forEach(function (item) {
            html += '<div class="ecodiag-toggle-item">' +
                '<span><span class="ecodiag-toggle-status ' + (item.cleaned ? 'cleaned' : 'present') + '"></span>' +
                '<span class="ecodiag-toggle-label">' + item.label + ' <small>(' + item.ref + ')</small></span></span>' +
                '<label class="ecodiag-switch"><input type="checkbox" class="ecodiag-head-toggle" data-option="' + item.option + '" ' + (item.cleaned ? 'checked' : '') + ' />' +
                '<span class="ecodiag-switch-slider"></span></label>' +
                '</div>';
        });
        el.innerHTML = html;

        // Toggle event handlers
        el.querySelectorAll('.ecodiag-head-toggle').forEach(function (input) {
            input.addEventListener('change', function () {
                var option = this.dataset.option;
                var value = this.checked ? '1' : '0';
                ajax('ecodiag_toggle_head', { option: option, value: value }, function (r) {
                    notify(r.success ? 'Réglage mis à jour.' : (r.data || 'Erreur'), r.success ? 'success' : 'error');
                });
            });
        });
    }

    function renderTrackingDiag(tracking) {
        if (!tracking) return;
        var el = document.getElementById('ecodiag-diag-tracking');
        var html = '';
        if (tracking.trackers) {
            html += diagCard('G-TRACK-01', 'Scripts de tracking détectés', tracking.trackers.count,
                tracking.trackers.items.join(', '), tracking.trackers.status, '');
        }
        if (tracking.external_domains) {
            var domains = Object.keys(tracking.external_domains.items || {}).join(', ');
            html += diagCard('G-TRACK-03', 'Domaines externes', tracking.external_domains.count, domains, tracking.external_domains.status, '');
        }
        el.innerHTML = html;
    }

    function renderEditorialDiag(editorial) {
        if (!editorial) return;
        var el = document.getElementById('ecodiag-diag-editorial');
        var html = '';
        if (editorial.stale_content) {
            html += diagCard('G-EDIT-01', 'Contenus obsolètes (>12 mois)', editorial.stale_content.count, '', editorial.stale_content.status, '');
            if (editorial.stale_content.items && editorial.stale_content.items.length > 0) {
                html += '<div class="ecodiag-diag-card info"><div class="ecodiag-diag-title">Contenus non modifiés depuis 12+ mois</div>';
                html += '<table class="ecodiag-table"><thead><tr><th>Titre</th><th>Dernière modification</th></tr></thead><tbody>';
                editorial.stale_content.items.forEach(function (item) {
                    html += '<tr><td><a href="post.php?action=edit&post=' + item.ID + '">' + item.post_title + '</a></td><td>' + item.post_modified + '</td></tr>';
                });
                html += '</tbody></table></div>';
            }
        }
        if (editorial.upload_max) {
            html += diagCard('G-EDIT-04', 'Taille max d\'upload', editorial.upload_max.formatted, '', 'info', '');
        }
        el.innerHTML = html;
    }

    function renderCronDiag(cron) {
        var el = document.getElementById('ecodiag-diag-cron');
        var html = '';
        var orphaned = cron || {};
        var count = Object.keys(orphaned).length;

        html += diagCard('G-CRON-02', 'Tâches cron orphelines', count, '',
            count > 0 ? 'orange' : 'green',
            count > 0 ? actionBtn('ecodiag_delete_orphan_crons', 'Supprimer les tâches orphelines') : '');

        if (count > 0) {
            html += '<div class="ecodiag-diag-card info"><div class="ecodiag-diag-title">Tâches cron orphelines</div><ul>';
            Object.keys(orphaned).forEach(function (hook) {
                html += '<li><code>' + hook + '</code></li>';
            });
            html += '</ul></div>';
        }

        el.innerHTML = html;
    }

    function renderServerDiag(server) {
        if (!server) return;
        var el = document.getElementById('ecodiag-diag-server');
        var html = '';

        if (server.php_version) {
            html += diagCard('G-REC-01', 'Version PHP', server.php_version.value,
                server.php_version.message, server.php_version.status, '');
        }
        if (server.compression) {
            html += diagCard('G-REC-02', 'Compression serveur',
                (server.compression.brotli ? 'Brotli ' : '') + (server.compression.gzip ? 'Gzip' : ''),
                server.compression.message, server.compression.status, '');
        }
        if (server.opcache) {
            html += diagCard('G-REC-04', 'OPcache',
                server.opcache.enabled ? 'Activé' : 'Désactivé',
                server.opcache.message, server.opcache.status, '');
        }
        if (server.object_cache) {
            html += diagCard('G-REC-05', 'Cache objet',
                server.object_cache.enabled ? 'Activé' : 'Désactivé',
                server.object_cache.message, server.object_cache.status, '');
        }
        if (server.page_cache) {
            html += diagCard('G-REC-06', 'Cache de page',
                server.page_cache.enabled ? 'Activé' : 'Désactivé',
                server.page_cache.message, server.page_cache.status, '');
        }
        if (server.green_hosting) {
            html += diagCard('G-REC-08', 'Hébergeur vert', '?',
                server.green_hosting.message, 'info', '');
        }
        if (server.wp_cron) {
            html += diagCard('G-CRON-01', 'WP-Cron',
                server.wp_cron.native ? 'Natif (wp-cron.php)' : 'Cron système',
                server.wp_cron.message, server.wp_cron.status, '');
        }
        if (server.mysql_version) {
            html += diagCard('G-REC-12', 'Version MySQL/MariaDB',
                server.mysql_version.value, '', 'info', '');
        }

        el.innerHTML = html;
    }

    // ==================================================================
    // GLOBAL ACTION HANDLER
    // ==================================================================
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ecodiag-global-action');
        if (!btn) return;
        e.preventDefault();

        var action = btn.dataset.action;
        if (!action) return;

        // Confirmation for destructive actions
        var destructive = ['ecodiag_purge_all_revisions', 'ecodiag_delete_plugin', 'ecodiag_delete_theme',
            'ecodiag_delete_orphan_media', 'ecodiag_clean_orphan_postmeta', 'ecodiag_delete_orphan_crons'];
        if (destructive.indexOf(action) !== -1) {
            if (!confirm(i18n.confirmDelete || 'Cette action est irréversible. Continuer ?')) return;
        }

        btn.disabled = true;
        var origText = btn.textContent;
        btn.textContent = i18n.processing || 'Traitement…';

        var data = {};
        // Collect data attributes
        Array.prototype.forEach.call(btn.attributes, function (attr) {
            if (attr.name.indexOf('data-') === 0 && attr.name !== 'data-action') {
                data[attr.name.replace('data-', '')] = attr.value;
            }
        });

        ajax(action, data, function (r) {
            btn.disabled = false;
            if (r.success) {
                btn.textContent = i18n.done || 'Terminé';
                btn.style.background = '#2ecc71';
                btn.style.color = '#fff';
                btn.style.borderColor = '#27ae60';
                var msg = typeof r.data === 'string' ? r.data : (r.data && r.data.message ? r.data.message : 'OK');
                notify(msg, 'success');

                // Handle bulk operations with progress
                if (r.data && r.data.has_more) {
                    btn.textContent = r.data.message;
                    btn.style.background = '';
                    btn.style.color = '';
                    btn.style.borderColor = '';
                    btn.disabled = false;
                    // Auto-continue
                    data.offset = r.data.offset;
                    setTimeout(function () { btn.click(); }, 500);
                }
            } else {
                btn.textContent = origText;
                notify(r.data || i18n.error || 'Erreur', 'error');
            }
        });
    });

    // ==================================================================
    // ARCHIVES AUDIT
    // ==================================================================
    document.querySelectorAll('.ecodiag-audit-url').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = this.dataset.url;
            this.disabled = true;
            this.textContent = i18n.processing || 'Analyse…';

            ajax('ecodiag_adminbar_data', { url: url }, function (r) {
                btn.disabled = false;
                btn.textContent = 'Diagnostiquer';
                if (!r.success) return notify(r.data || 'Erreur', 'error');

                var d = r.data;
                var results = document.getElementById('ecodiag-archive-results');
                results.style.display = '';
                document.getElementById('ecodiag-archive-title').textContent = 'Résultat pour : ' + url;

                var grid = document.getElementById('ecodiag-archive-indicators');
                grid.innerHTML =
                    '<div class="ecodiag-indicator-card ' + scoreClass(d.score) + '"><div class="ecodiag-ind-label">Score</div><div class="ecodiag-ind-value">' + d.score + '/100</div></div>' +
                    '<div class="ecodiag-indicator-card"><div class="ecodiag-ind-label">Poids</div><div class="ecodiag-ind-value">' + fmt(d.total_weight) + '</div></div>' +
                    '<div class="ecodiag-indicator-card"><div class="ecodiag-ind-label">Requêtes</div><div class="ecodiag-ind-value">' + d.requests_count + '</div></div>' +
                    '<div class="ecodiag-indicator-card"><div class="ecodiag-ind-label">DOM</div><div class="ecodiag-ind-value">' + d.dom_size + '</div></div>' +
                    '<div class="ecodiag-indicator-card"><div class="ecodiag-ind-label">JS</div><div class="ecodiag-ind-value">' + d.js_count + '</div></div>' +
                    '<div class="ecodiag-indicator-card"><div class="ecodiag-ind-label">CSS</div><div class="ecodiag-ind-value">' + d.css_count + '</div></div>';
            });
        });
    });

    // ==================================================================
    // CONDITIONAL LOADING
    // ==================================================================
    var saveConditionalBtn = document.getElementById('ecodiag-save-conditional');
    if (saveConditionalBtn) {
        saveConditionalBtn.addEventListener('click', function () {
            var rules = [];
            document.querySelectorAll('.ecodiag-conditional-table tbody tr').forEach(function (row) {
                var plugin = row.dataset.plugin;
                var checked = row.querySelector('input[type="radio"]:checked');
                if (plugin && checked) {
                    rules.push({ plugin: plugin, mode: checked.value, post_types: [], pages: [] });
                }
            });

            var fd = new FormData();
            fd.append('action', 'ecodiag_save_conditional_rules');
            fd.append('nonce', nonce);
            rules.forEach(function (rule, i) {
                fd.append('rules[' + i + '][plugin]', rule.plugin);
                fd.append('rules[' + i + '][mode]', rule.mode);
            });

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    notify(r.success ? 'Règles enregistrées.' : (r.data || 'Erreur'), r.success ? 'success' : 'error');
                });
        });
    }
})();
