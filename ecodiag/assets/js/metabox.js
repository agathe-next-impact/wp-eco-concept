/**
 * EcoDiag Metabox JavaScript
 * Handles audit, actions, and recommendations in the post editor.
 */
(function () {
    'use strict';

    var config = window.ecodiagMetabox || {};
    var ajaxUrl = config.ajaxUrl;
    var nonce = config.nonce;
    var postId = config.postId;
    var i18n = config.i18n || {};
    var auditData = null;

    // Tab switching
    document.querySelectorAll('.ecodiag-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.ecodiag-tab').forEach(function (t) { t.classList.remove('active'); });
            document.querySelectorAll('.ecodiag-tab-content').forEach(function (c) { c.classList.remove('active'); });
            tab.classList.add('active');
            var target = document.getElementById('ecodiag-tab-' + tab.dataset.tab);
            if (target) target.classList.add('active');
        });
    });

    // Format bytes
    function fmt(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' Mo';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' Ko';
        return bytes + ' o';
    }

    // Escape HTML to prevent XSS
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    // Score color
    function scoreColor(score) {
        if (score >= 75) return 'green';
        if (score >= 50) return 'orange';
        return 'red';
    }

    // Indicator status
    function indStatus(key, val) {
        var thresholds = {
            page_weight: [512000, 1048576],
            requests: [25, 50],
            dom_size: [800, 1500],
            js_count: [5, 10],
            css_count: [3, 6],
            img_issues: [0, 3]
        };
        var t = thresholds[key];
        if (!t) return 'green';
        if (val <= t[0]) return 'green';
        if (val <= t[1]) return 'orange';
        return 'red';
    }

    // Run audit
    var auditBtn = document.getElementById('ecodiag-run-audit');
    if (auditBtn) {
        auditBtn.addEventListener('click', function () {
            auditBtn.disabled = true;
            auditBtn.textContent = i18n.running || 'Analyse en cours…';

            var fd = new FormData();
            fd.append('action', 'ecodiag_run_audit');
            fd.append('nonce', nonce);
            fd.append('post_id', postId);

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    auditBtn.disabled = false;
                    auditBtn.textContent = i18n.tabDiag || 'Lancer le diagnostic';
                    if (r.success) {
                        auditData = r.data;
                        renderDiagnostic(r.data);
                        renderActions(r.data);
                        renderRecommendations(r.data);
                    } else {
                        showNotice(r.data || i18n.error, 'error');
                    }
                })
                .catch(function () {
                    auditBtn.disabled = false;
                    auditBtn.textContent = i18n.tabDiag || 'Lancer le diagnostic';
                    showNotice(i18n.error, 'error');
                });
        });
    }

    // Render diagnostic results
    function renderDiagnostic(data) {
        // Score
        var circle = document.getElementById('ecodiag-score-circle');
        var scoreVal = circle.querySelector('.ecodiag-score-value');
        scoreVal.textContent = data.score;
        circle.className = 'ecodiag-score-circle ' + scoreColor(data.score);

        // Indicators grid
        var grid = document.getElementById('ecodiag-indicators');
        grid.style.display = '';
        grid.innerHTML = '';

        var indicators = [
            { key: 'page_weight', label: i18n.weight || 'Poids total', value: fmt(data.total_weight), raw: data.total_weight },
            { key: 'requests', label: i18n.requests || 'Requêtes HTTP', value: data.requests_count, raw: data.requests_count },
            { key: 'dom_size', label: i18n.dom || 'Nœuds DOM', value: data.dom_size, raw: data.dom_size },
            { key: 'js_count', label: i18n.js || 'Scripts JS', value: data.js_count, raw: data.js_count },
            { key: 'css_count', label: i18n.css || 'Feuilles CSS', value: data.css_count, raw: data.css_count },
            { key: 'img_issues', label: i18n.images || 'Images non optimisées', value: data.img_issues_count, raw: data.img_issues_count }
        ];

        indicators.forEach(function (ind) {
            var card = document.createElement('div');
            card.className = 'ecodiag-indicator-card ' + indStatus(ind.key, ind.raw);
            card.innerHTML = '<div class="ecodiag-ind-label">' + ind.label + '</div>' +
                '<div class="ecodiag-ind-value">' + ind.value + '</div>';
            grid.appendChild(card);
        });

        // Details section
        var details = document.getElementById('ecodiag-details');
        details.style.display = '';

        // Images
        var imgList = document.getElementById('ecodiag-images-list');
        imgList.innerHTML = '';
        if (data.images && data.images.length > 0) {
            data.images.forEach(function (img) {
                if (!img.issues || img.issues.length === 0) return;
                var item = document.createElement('div');
                item.className = 'ecodiag-issue-item';
                var tags = img.issues.map(function (issue) {
                    return '<span class="ecodiag-issue-tag">' + escHtml(issue.ref) + ': ' + escHtml(issue.label) + '</span>';
                }).join('');
                item.innerHTML = '<span class="ecodiag-issue-src">' + escHtml(basename(img.src)) + (img.size ? ' (' + fmt(img.size) + ')' : '') + '</span>' +
                    '<span class="ecodiag-issue-tags">' + tags + '</span>';
                imgList.appendChild(item);
            });
        } else {
            imgList.innerHTML = '<p class="ecodiag-help">' + (i18n.noIssues || 'Aucun problème détecté.') + '</p>';
        }

        // Embeds
        var embedList = document.getElementById('ecodiag-embeds-list');
        embedList.innerHTML = '';
        if (data.embeds && data.embeds.length > 0) {
            data.embeds.forEach(function (embed) {
                if (!embed.issues || embed.issues.length === 0) return;
                var item = document.createElement('div');
                item.className = 'ecodiag-issue-item';
                var tags = embed.issues.map(function (issue) {
                    return '<span class="ecodiag-issue-tag">' + escHtml(issue.label) + '</span>';
                }).join('');
                item.innerHTML = '<span class="ecodiag-issue-src">' + escHtml(embed.src ? basename(embed.src) : embed.type) + '</span>' +
                    '<span class="ecodiag-issue-tags">' + tags + '</span>';
                embedList.appendChild(item);
            });
        } else {
            embedList.innerHTML = '<p class="ecodiag-help">' + (i18n.noIssues || 'Aucun problème.') + '</p>';
        }

        // Content diagnostics
        var contentDiag = document.getElementById('ecodiag-content-diag');
        contentDiag.innerHTML = '';
        if (data.content_diagnostics) {
            var cd = data.content_diagnostics;
            var items = [
                { label: i18n.revisions || 'Révisions', value: cd.revisions ? cd.revisions.count + ' (' + fmt(cd.revisions.estimated_weight) + ')' : '—', status: cd.revisions ? cd.revisions.status : 'green' },
                { label: i18n.orphanedMeta || 'Métadonnées orphelines', value: cd.orphaned_meta ? cd.orphaned_meta.count : '—', status: cd.orphaned_meta ? cd.orphaned_meta.status : 'green' },
                { label: i18n.contentWeight || 'Poids HTML brut', value: cd.content_weight ? cd.content_weight.formatted : '—', status: cd.content_weight ? cd.content_weight.status : 'green' },
                { label: i18n.blocks || 'Blocs Gutenberg', value: cd.block_count ? cd.block_count.count : '—', status: 'info' },
                { label: i18n.shortcodes || 'Shortcodes cassés', value: cd.broken_shortcodes ? cd.broken_shortcodes.count : '0', status: cd.broken_shortcodes ? cd.broken_shortcodes.status : 'green' }
            ];

            items.forEach(function (it) {
                var div = document.createElement('div');
                div.className = 'ecodiag-indicator-card ' + it.status;
                div.innerHTML = '<div class="ecodiag-ind-label">' + it.label + '</div>' +
                    '<div class="ecodiag-ind-value">' + it.value + '</div>';
                contentDiag.appendChild(div);
            });
            contentDiag.className = 'ecodiag-grid';
        }

        // Resources (top 10)
        var resList = document.getElementById('ecodiag-resources-list');
        resList.innerHTML = '';
        var allResources = [].concat(data.css_resources || [], data.js_resources || [], data.img_resources || []);
        allResources.sort(function (a, b) { return b.size - a.size; });
        allResources = allResources.slice(0, 10);

        if (allResources.length > 0) {
            var table = '<table class="ecodiag-resources-table"><thead><tr><th>Ressource</th><th>Poids</th></tr></thead><tbody>';
            allResources.forEach(function (r) {
                table += '<tr><td>' + escHtml(r.name) + '</td><td>' + fmt(r.size) + '</td></tr>';
            });
            table += '</tbody></table>';
            resList.innerHTML = table;
        }
    }

    // Render actions
    function renderActions(data) {
        var list = document.getElementById('ecodiag-actions-list');
        list.style.display = '';
        document.querySelector('#ecodiag-tab-actions .ecodiag-help').style.display = 'none';

        // Show/hide action buttons based on issues found
        var hasImgIssues = data.img_issues_count > 0;
        var hasEmbedIssues = data.embeds && data.embeds.some(function (e) { return e.issues && e.issues.length > 0; });
        var hasRevisions = data.content_diagnostics && data.content_diagnostics.revisions && data.content_diagnostics.revisions.count > 0;

        // All buttons are always shown but grayed out if no issues
        document.querySelectorAll('.ecodiag-action-btn').forEach(function (btn) {
            var ref = btn.dataset.ref;
            var relevant = true;
            if (ref && ref.startsWith('P-IMG') && !hasImgIssues) relevant = false;
            if (ref && ref.startsWith('P-VID') && !hasEmbedIssues) relevant = false;
            if (ref === 'P-CONT-01' && !hasRevisions) relevant = false;
            btn.style.opacity = relevant ? '1' : '0.5';
        });
    }

    // Render recommendations
    function renderRecommendations(data) {
        var list = document.getElementById('ecodiag-recommendations-list');
        list.style.display = '';
        list.innerHTML = '';
        document.querySelector('#ecodiag-tab-recommendations .ecodiag-help').style.display = 'none';

        var recos = [];

        // P-REC-01: DOM size
        if (data.dom_size > 800) {
            recos.push({ ref: 'P-REC-01', label: 'Taille du DOM élevée (' + data.dom_size + ' nœuds)', advice: 'Simplifier le template : réduire la profondeur HTML, supprimer les wrappers inutiles.' });
        }

        // P-REC-03: Fonts
        if (data.font_resources && data.font_resources.length > 2) {
            recos.push({ ref: 'P-REC-03', label: data.font_resources.length + ' polices web chargées (' + fmt(data.font_weight) + ')', advice: 'Passer en polices système ou réduire à 1 famille / 2 variantes WOFF2.' });
        }

        // P-REC-06: Scripts without defer/async
        if (data.render_blocking && data.render_blocking.js && data.render_blocking.js.length > 0) {
            recos.push({ ref: 'P-REC-06', label: data.render_blocking.js.length + ' script(s) sans defer ni async', advice: 'Ajouter defer ou async sur les scripts non critiques.' });
        }

        // P-REC-05: Render-blocking CSS
        if (data.render_blocking && data.render_blocking.css && data.render_blocking.css.length > 3) {
            recos.push({ ref: 'P-REC-05', label: data.render_blocking.css.length + ' feuilles CSS render-blocking', advice: 'Inliner le CSS critique et différer le reste.' });
        }

        // P-REC-11: External resources
        if (data.external_domains && Object.keys(data.external_domains).length > 3) {
            var domains = Object.keys(data.external_domains).join(', ');
            recos.push({ ref: 'P-REC-11', label: Object.keys(data.external_domains).length + ' domaines externes', advice: 'Héberger en local ou supprimer les dépendances externes non essentielles. Domaines : ' + domains });
        }

        // P-REC-12: Tracking scripts
        if (data.tracking && data.tracking.length > 0) {
            recos.push({ ref: 'P-REC-12', label: data.tracking.length + ' script(s) de tracking détecté(s)', advice: 'Évaluer la nécessité, remplacer par une alternative légère (Plausible, Umami).' });
        }

        if (recos.length === 0) {
            list.innerHTML = '<p class="ecodiag-help">Aucune recommandation supplémentaire.</p>';
            return;
        }

        recos.forEach(function (reco) {
            var div = document.createElement('div');
            div.className = 'ecodiag-reco-item';
            div.innerHTML = '<div class="ecodiag-reco-ref">' + reco.ref + '</div>' +
                '<div class="ecodiag-reco-label">' + reco.label + '</div>' +
                '<div class="ecodiag-reco-advice">' + reco.advice + '</div>';
            list.appendChild(div);
        });
    }

    // Action buttons
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ecodiag-action-btn');
        if (!btn) return;
        e.preventDefault();

        var action = btn.dataset.action;
        if (!action) return;

        btn.classList.add('loading');
        var origText = btn.textContent;
        btn.textContent = i18n.processing || 'Traitement…';

        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        fd.append('post_id', postId);

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                btn.classList.remove('loading');
                if (r.success) {
                    btn.classList.add('done');
                    btn.textContent = '✓ ' + (r.data || i18n.done);
                    showNotice(r.data || i18n.done, 'success');
                    // Re-run audit after action to reflect changes
                    if (auditBtn) {
                        setTimeout(function () { auditBtn.click(); }, 1500);
                    }
                } else {
                    btn.textContent = origText;
                    showNotice(r.data || i18n.error, 'error');
                }
            })
            .catch(function () {
                btn.classList.remove('loading');
                btn.textContent = origText;
                showNotice(i18n.error, 'error');
            });
    });

    function showNotice(msg, type) {
        var el = document.getElementById('ecodiag-action-result');
        if (!el) return;
        el.style.display = '';
        el.className = 'ecodiag-notice ' + type;
        el.textContent = msg;
        setTimeout(function () { el.style.display = 'none'; }, 5000);
    }

    function basename(url) {
        try {
            var parts = url.split('/');
            return parts[parts.length - 1].split('?')[0];
        } catch (e) {
            return url;
        }
    }
})();
