<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Front-end diagnostic summary popup.
 * Displays a floating popup with page diagnostic results for logged-in users (excluding subscribers).
 */
class EcoDiag_Front_Popup {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_popup' ), 50 );
    }

    /**
     * Check if current user should see the popup.
     * Visible to logged-in users except subscribers, and only on front-end.
     */
    private function can_view() {
        if ( ! is_user_logged_in() || is_admin() ) {
            return false;
        }
        $user = wp_get_current_user();
        // Exclude subscriber role
        if ( in_array( 'subscriber', (array) $user->roles, true ) ) {
            return false;
        }
        // Must have at least edit_posts capability
        if ( ! current_user_can( 'edit_posts' ) ) {
            return false;
        }
        return true;
    }

    /**
     * Render the popup HTML, CSS, and JS in the footer.
     */
    public function render_popup() {
        if ( ! $this->can_view() ) {
            return;
        }

        $nonce    = wp_create_nonce( 'ecodiag_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );
        $dashboard_url = admin_url( 'admin.php?page=ecodiag' );
        $current_post_id = 0;
        if ( is_singular() ) {
            $current_post_id = get_queried_object_id();
        }
        $edit_url = $current_post_id ? admin_url( 'post.php?action=edit&post=' . $current_post_id . '#ecodiag-metabox' ) : '';
        ?>
        <style id="ecodiag-popup-css">
        #ecodiag-popup-toggle{
            position:fixed;bottom:20px;right:20px;z-index:99998;
            width:48px;height:48px;border-radius:50%;border:none;
            background:#23282d;color:#fff;cursor:pointer;
            font-size:20px;line-height:48px;text-align:center;
            box-shadow:0 2px 8px rgba(0,0,0,.25);transition:transform .2s,background .3s;
        }
        #ecodiag-popup-toggle:hover{transform:scale(1.1)}
        #ecodiag-popup-toggle .ecodiag-popup-dot{
            position:absolute;top:4px;right:4px;width:12px;height:12px;
            border-radius:50%;border:2px solid #23282d;
        }
        #ecodiag-popup{
            position:fixed;bottom:78px;right:20px;z-index:99999;
            width:340px;max-height:80vh;overflow-y:auto;
            background:#fff;border-radius:8px;
            box-shadow:0 4px 24px rgba(0,0,0,.18);
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,sans-serif;
            font-size:13px;color:#23282d;
            display:none;opacity:0;transform:translateY(12px);
            transition:opacity .25s ease,transform .25s ease;
        }
        #ecodiag-popup.ecodiag-popup-visible{display:block;opacity:1;transform:translateY(0)}
        #ecodiag-popup-header{
            display:flex;align-items:center;justify-content:space-between;
            padding:14px 16px;border-bottom:1px solid #e2e4e7;
        }
        #ecodiag-popup-header h3{margin:0;font-size:14px;font-weight:600}
        #ecodiag-popup-close{background:none;border:none;cursor:pointer;font-size:18px;color:#999;padding:0 2px}
        #ecodiag-popup-close:hover{color:#23282d}
        #ecodiag-popup-score{
            display:flex;align-items:center;justify-content:center;
            gap:16px;padding:16px;border-bottom:1px solid #f0f0f0;
        }
        .ecodiag-popup-grade{
            width:56px;height:56px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;
            font-size:22px;font-weight:800;color:#fff;
        }
        .ecodiag-popup-score-details{text-align:left}
        .ecodiag-popup-score-val{font-size:24px;font-weight:700;line-height:1}
        .ecodiag-popup-score-label{font-size:11px;color:#888;margin-top:2px}
        #ecodiag-popup-metrics{padding:10px 16px}
        .ecodiag-popup-metric{
            display:flex;align-items:center;justify-content:space-between;
            padding:6px 0;border-bottom:1px solid #f6f7f7;
        }
        .ecodiag-popup-metric:last-child{border-bottom:none}
        .ecodiag-popup-metric-label{display:flex;align-items:center;gap:6px}
        .ecodiag-popup-metric-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
        .ecodiag-popup-metric-val{font-weight:600;white-space:nowrap}
        #ecodiag-popup-issues{padding:0 16px 10px}
        .ecodiag-popup-issue-title{font-weight:600;font-size:12px;color:#e74c3c;margin-bottom:4px}
        .ecodiag-popup-issue-list{list-style:none;margin:0;padding:0}
        .ecodiag-popup-issue-list li{font-size:11px;color:#666;padding:2px 0;padding-left:14px;position:relative}
        .ecodiag-popup-issue-list li::before{content:"";position:absolute;left:0;top:8px;width:6px;height:6px;border-radius:50%;background:#e74c3c}
        #ecodiag-popup-footer{
            display:flex;gap:8px;padding:12px 16px;border-top:1px solid #e2e4e7;
        }
        .ecodiag-popup-btn{
            flex:1;padding:7px 10px;font-size:12px;font-weight:500;
            border:1px solid #ddd;border-radius:4px;background:#f7f7f7;
            color:#23282d;cursor:pointer;text-align:center;text-decoration:none;
            transition:background .15s;display:block;
        }
        .ecodiag-popup-btn:hover{background:#e8e8e8}
        .ecodiag-popup-btn-primary{background:#0073aa;color:#fff;border-color:#0073aa}
        .ecodiag-popup-btn-primary:hover{background:#005a87;color:#fff}
        #ecodiag-popup-loading{padding:30px;text-align:center;color:#888}
        .ecodiag-popup-spinner{display:inline-block;width:20px;height:20px;border:2px solid #ddd;border-top-color:#0073aa;border-radius:50%;animation:ecodiag-spin .7s linear infinite}
        @keyframes ecodiag-spin{to{transform:rotate(360deg)}}
        @media(max-width:420px){
            #ecodiag-popup{width:calc(100vw - 30px);right:15px;bottom:74px}
            #ecodiag-popup-toggle{right:15px;bottom:15px}
        }
        </style>

        <button type="button" id="ecodiag-popup-toggle" title="<?php esc_attr_e( 'Diagnostic EcoDiag', 'ecodiag' ); ?>">
            <span aria-hidden="true">&#9878;</span>
            <span class="ecodiag-popup-dot" id="ecodiag-popup-status-dot" style="background:#888"></span>
        </button>

        <div id="ecodiag-popup" role="dialog" aria-label="<?php esc_attr_e( 'Résumé du diagnostic EcoDiag', 'ecodiag' ); ?>">
            <div id="ecodiag-popup-header">
                <h3><?php esc_html_e( 'EcoDiag — Diagnostic', 'ecodiag' ); ?></h3>
                <button type="button" id="ecodiag-popup-close" aria-label="<?php esc_attr_e( 'Fermer', 'ecodiag' ); ?>">&times;</button>
            </div>
            <div id="ecodiag-popup-body">
                <div id="ecodiag-popup-loading">
                    <span class="ecodiag-popup-spinner"></span>
                    <p><?php esc_html_e( 'Analyse en cours…', 'ecodiag' ); ?></p>
                </div>
            </div>
        </div>

        <script id="ecodiag-popup-js">
        (function(){
            'use strict';
            var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
            var nonce = <?php echo wp_json_encode( $nonce ); ?>;
            var dashUrl = <?php echo wp_json_encode( $dashboard_url ); ?>;
            var editUrl = <?php echo wp_json_encode( $edit_url ); ?>;
            var cached = null;

            var toggle = document.getElementById('ecodiag-popup-toggle');
            var popup = document.getElementById('ecodiag-popup');
            var closeBtn = document.getElementById('ecodiag-popup-close');
            var statusDot = document.getElementById('ecodiag-popup-status-dot');
            var body = document.getElementById('ecodiag-popup-body');

            function esc(str) {
                if (!str) return '';
                var d = document.createElement('div');
                d.appendChild(document.createTextNode(String(str)));
                return d.innerHTML;
            }

            function fmt(bytes) {
                bytes = parseInt(bytes, 10) || 0;
                if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' Mo';
                if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' Ko';
                return bytes + ' o';
            }

            function statusColor(key, val) {
                var t = { weight:[512000,1048576], requests:[25,50], dom:[800,1500], js:[5,10], css:[3,6], img:[0,3] };
                var th = t[key];
                if (!th) return '#888';
                if (val <= th[0]) return '#2ecc71';
                if (val <= th[1]) return '#f39c12';
                return '#e74c3c';
            }

            function scoreColor(s) {
                if (s >= 75) return '#2ecc71';
                if (s >= 50) return '#f39c12';
                return '#e74c3c';
            }

            function grade(s) {
                if (s >= 90) return 'A';
                if (s >= 75) return 'B';
                if (s >= 60) return 'C';
                if (s >= 45) return 'D';
                if (s >= 30) return 'E';
                if (s >= 15) return 'F';
                return 'G';
            }

            function renderData(d) {
                var sc = parseInt(d.score, 10) || 0;
                var gr = grade(sc);
                var col = scoreColor(sc);

                statusDot.style.background = col;

                var html = '';
                // Score section
                html += '<div id="ecodiag-popup-score">';
                html += '<div class="ecodiag-popup-grade" style="background:' + col + '">' + esc(gr) + '</div>';
                html += '<div class="ecodiag-popup-score-details">';
                html += '<div class="ecodiag-popup-score-val">' + sc + '<span style="font-size:14px;font-weight:400;color:#888">/100</span></div>';
                html += '<div class="ecodiag-popup-score-label">' + esc(<?php echo wp_json_encode( __( 'Score écoconception', 'ecodiag' ) ); ?>) + '</div>';
                html += '</div></div>';

                // Metrics
                var metrics = [
                    { key: 'weight', label: <?php echo wp_json_encode( __( 'Poids total', 'ecodiag' ) ); ?>, val: fmt(d.total_weight), raw: d.total_weight },
                    { key: 'requests', label: <?php echo wp_json_encode( __( 'Requêtes HTTP', 'ecodiag' ) ); ?>, val: d.requests_count, raw: d.requests_count },
                    { key: 'dom', label: <?php echo wp_json_encode( __( 'Taille DOM', 'ecodiag' ) ); ?>, val: d.dom_size + ' noeuds', raw: d.dom_size },
                    { key: 'js', label: <?php echo wp_json_encode( __( 'Fichiers JS', 'ecodiag' ) ); ?>, val: d.js_count, raw: d.js_count },
                    { key: 'css', label: <?php echo wp_json_encode( __( 'Fichiers CSS', 'ecodiag' ) ); ?>, val: d.css_count, raw: d.css_count },
                    { key: 'img', label: <?php echo wp_json_encode( __( 'Images non optimisées', 'ecodiag' ) ); ?>, val: d.img_issues_count, raw: d.img_issues_count }
                ];

                html += '<div id="ecodiag-popup-metrics">';
                metrics.forEach(function(m) {
                    var c = statusColor(m.key, m.raw);
                    html += '<div class="ecodiag-popup-metric">';
                    html += '<span class="ecodiag-popup-metric-label"><span class="ecodiag-popup-metric-dot" style="background:' + c + '"></span>' + esc(m.label) + '</span>';
                    html += '<span class="ecodiag-popup-metric-val">' + esc(String(m.val)) + '</span>';
                    html += '</div>';
                });
                html += '</div>';

                // Weight breakdown
                if (d.weight_breakdown) {
                    var wb = d.weight_breakdown;
                    html += '<div id="ecodiag-popup-metrics" style="padding-top:0">';
                    html += '<div class="ecodiag-popup-metric"><span class="ecodiag-popup-metric-label" style="font-size:12px;color:#888">' + esc(<?php echo wp_json_encode( __( 'Détail du poids', 'ecodiag' ) ); ?>) + '</span><span></span></div>';
                    var parts = [
                        { label: 'HTML', val: wb.html },
                        { label: 'CSS', val: wb.css },
                        { label: 'JS', val: wb.js },
                        { label: 'Images', val: wb.images },
                        { label: 'Fonts', val: wb.fonts }
                    ];
                    parts.forEach(function(p) {
                        html += '<div class="ecodiag-popup-metric">';
                        html += '<span class="ecodiag-popup-metric-label" style="padding-left:14px">' + esc(p.label) + '</span>';
                        html += '<span class="ecodiag-popup-metric-val" style="font-weight:400">' + fmt(p.val) + '</span>';
                        html += '</div>';
                    });
                    html += '</div>';
                }

                // Top issues (max 5)
                if (d.images && d.images.length > 0) {
                    var issues = [];
                    d.images.forEach(function(img) {
                        if (img.issues && img.issues.length > 0) {
                            img.issues.forEach(function(issue) {
                                if (issues.length < 5) {
                                    issues.push(issue.label);
                                }
                            });
                        }
                    });
                    if (issues.length > 0) {
                        html += '<div id="ecodiag-popup-issues">';
                        html += '<div class="ecodiag-popup-issue-title">' + esc(<?php echo wp_json_encode( __( 'Problèmes détectés', 'ecodiag' ) ); ?>) + ' (' + d.img_issues_count + ')</div>';
                        html += '<ul class="ecodiag-popup-issue-list">';
                        issues.forEach(function(i) { html += '<li>' + esc(i) + '</li>'; });
                        html += '</ul></div>';
                    }
                }

                // Footer buttons
                html += '<div id="ecodiag-popup-footer">';
                html += '<button type="button" class="ecodiag-popup-btn" id="ecodiag-popup-refresh">' + esc(<?php echo wp_json_encode( __( 'Relancer', 'ecodiag' ) ); ?>) + '</button>';
                if (editUrl) {
                    html += '<a href="' + esc(editUrl) + '" class="ecodiag-popup-btn">' + esc(<?php echo wp_json_encode( __( 'Modifier', 'ecodiag' ) ); ?>) + '</a>';
                }
                html += '<a href="' + esc(dashUrl) + '" class="ecodiag-popup-btn ecodiag-popup-btn-primary">' + esc(<?php echo wp_json_encode( __( 'Dashboard', 'ecodiag' ) ); ?>) + '</a>';
                html += '</div>';

                body.innerHTML = html;

                // Refresh button handler
                var refreshBtn = document.getElementById('ecodiag-popup-refresh');
                if (refreshBtn) {
                    refreshBtn.addEventListener('click', function() {
                        cached = null;
                        loadData(true);
                    });
                }
            }

            function showLoading() {
                body.innerHTML = '<div id="ecodiag-popup-loading"><span class="ecodiag-popup-spinner"></span><p>' +
                    esc(<?php echo wp_json_encode( __( 'Analyse en cours…', 'ecodiag' ) ); ?>) + '</p></div>';
            }

            function showError(msg) {
                body.innerHTML = '<div id="ecodiag-popup-loading" style="color:#e74c3c"><p>' + esc(msg || <?php echo wp_json_encode( __( 'Erreur lors de l\'analyse.', 'ecodiag' ) ); ?>) + '</p></div>';
                statusDot.style.background = '#e74c3c';
            }

            function loadData(force) {
                if (cached && !force) {
                    renderData(cached);
                    return;
                }
                showLoading();
                var fd = new FormData();
                fd.append('action', 'ecodiag_popup_data');
                fd.append('nonce', nonce);
                fd.append('url', window.location.href);
                if (force) fd.append('force', '1');

                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(r) {
                        if (!r.success) {
                            showError(typeof r.data === 'string' ? r.data : null);
                            return;
                        }
                        cached = r.data;
                        // Update status dot even when popup is closed
                        var sc = parseInt(r.data.score, 10) || 0;
                        statusDot.style.background = scoreColor(sc);
                        if (popup.classList.contains('ecodiag-popup-visible')) {
                            renderData(r.data);
                        }
                    })
                    .catch(function() {
                        showError();
                    });
            }

            // Toggle popup
            toggle.addEventListener('click', function() {
                if (popup.classList.contains('ecodiag-popup-visible')) {
                    popup.classList.remove('ecodiag-popup-visible');
                } else {
                    popup.classList.add('ecodiag-popup-visible');
                    if (cached) {
                        renderData(cached);
                    } else {
                        loadData(false);
                    }
                }
            });

            // Close button
            closeBtn.addEventListener('click', function() {
                popup.classList.remove('ecodiag-popup-visible');
            });

            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!popup.contains(e.target) && !toggle.contains(e.target) && popup.classList.contains('ecodiag-popup-visible')) {
                    popup.classList.remove('ecodiag-popup-visible');
                }
            });

            // Close on Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && popup.classList.contains('ecodiag-popup-visible')) {
                    popup.classList.remove('ecodiag-popup-visible');
                }
            });

            // Pre-fetch data on page load to show score dot color
            document.addEventListener('DOMContentLoaded', function() {
                loadData(false);
            });
        })();
        </script>
        <?php
    }
}
