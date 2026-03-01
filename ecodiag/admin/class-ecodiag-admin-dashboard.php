<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Module 3 — Global admin dashboard page.
 */
class EcoDiag_Admin_Dashboard {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function add_menus() {
        // Main menu
        add_menu_page(
            __( 'EcoDiag', 'ecodiag' ),
            __( 'EcoDiag', 'ecodiag' ),
            'manage_options',
            'ecodiag',
            array( $this, 'render_dashboard' ),
            'dashicons-performance',
            80
        );

        // Dashboard submenu
        add_submenu_page(
            'ecodiag',
            __( 'Tableau de bord', 'ecodiag' ),
            __( 'Tableau de bord', 'ecodiag' ),
            'manage_options',
            'ecodiag',
            array( $this, 'render_dashboard' )
        );

        // Diagnostics submenu
        add_submenu_page(
            'ecodiag',
            __( 'Diagnostic global', 'ecodiag' ),
            __( 'Diagnostic global', 'ecodiag' ),
            'manage_options',
            'ecodiag-diagnostics',
            array( $this, 'render_diagnostics' )
        );

        // Archives submenu
        add_submenu_page(
            'ecodiag',
            __( 'Archives', 'ecodiag' ),
            __( 'Archives', 'ecodiag' ),
            'manage_options',
            'ecodiag-archives',
            array( $this, 'render_archives' )
        );

        // Settings submenu (handled by EcoDiag_Admin_Settings)
    }

    public function enqueue( $hook ) {
        if ( strpos( $hook, 'ecodiag' ) === false ) return;

        wp_enqueue_style( 'ecodiag-dashboard', ECODIAG_URL . 'assets/css/dashboard.css', array(), ECODIAG_VERSION );
        wp_enqueue_script( 'ecodiag-dashboard', ECODIAG_URL . 'assets/js/dashboard.js', array(), ECODIAG_VERSION, true );
        wp_localize_script( 'ecodiag-dashboard', 'ecodiagDashboard', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ecodiag_nonce' ),
            'i18n'    => array(
                'loading'       => __( 'Chargement…', 'ecodiag' ),
                'error'         => __( 'Erreur', 'ecodiag' ),
                'confirm'       => __( 'Êtes-vous sûr ?', 'ecodiag' ),
                'confirmDelete' => __( 'Cette action est irréversible. Continuer ?', 'ecodiag' ),
                'processing'    => __( 'Traitement…', 'ecodiag' ),
                'done'          => __( 'Terminé !', 'ecodiag' ),
                'score'         => __( 'Score', 'ecodiag' ),
                'weight'        => __( 'Poids', 'ecodiag' ),
                'export'        => __( 'Exporter CSV', 'ecodiag' ),
            ),
        ) );
    }

    /**
     * Render the main dashboard page.
     */
    public function render_dashboard() {
        ?>
        <div class="wrap ecodiag-wrap">
            <h1 class="ecodiag-title"><?php esc_html_e( 'EcoDiag — Tableau de bord', 'ecodiag' ); ?></h1>

            <!-- Score overview -->
            <div class="ecodiag-dashboard-grid">
                <div class="ecodiag-card ecodiag-card-score" id="ecodiag-global-score">
                    <h2><?php esc_html_e( 'Score EcoDiag global', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-big-score">
                        <span class="ecodiag-big-number" id="ecodiag-avg-score">—</span>
                        <span class="ecodiag-big-label">/100</span>
                    </div>
                    <p id="ecodiag-pages-audited"></p>
                </div>

                <div class="ecodiag-card" id="ecodiag-global-stats">
                    <h2><?php esc_html_e( 'Compteurs globaux', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-stats-grid" id="ecodiag-counters">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <div class="ecodiag-card ecodiag-card-actions">
                    <h2><?php esc_html_e( 'Actions rapides', 'ecodiag' ); ?></h2>
                    <button type="button" class="button" id="ecodiag-export-csv"><?php esc_html_e( 'Exporter CSV', 'ecodiag' ); ?></button>
                    <button type="button" class="button" id="ecodiag-run-full-audit"><?php esc_html_e( 'Lancer un audit complet', 'ecodiag' ); ?></button>
                </div>
            </div>

            <!-- Evolution chart -->
            <div class="ecodiag-card ecodiag-card-wide" id="ecodiag-history-card">
                <h2><?php esc_html_e( 'Évolution du score', 'ecodiag' ); ?></h2>
                <div class="ecodiag-chart-container">
                    <canvas id="ecodiag-history-chart" height="200"></canvas>
                </div>
            </div>

            <!-- Top pages -->
            <div class="ecodiag-dashboard-grid">
                <div class="ecodiag-card">
                    <h2><?php esc_html_e( 'Top 10 — Pages les plus lourdes', 'ecodiag' ); ?></h2>
                    <table class="ecodiag-table" id="ecodiag-heaviest-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Page', 'ecodiag' ); ?></th>
                                <th><?php esc_html_e( 'Poids', 'ecodiag' ); ?></th>
                                <th><?php esc_html_e( 'Score', 'ecodiag' ); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="ecodiag-card">
                    <h2><?php esc_html_e( 'Top 10 — Pages les moins bien notées', 'ecodiag' ); ?></h2>
                    <table class="ecodiag-table" id="ecodiag-worst-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Page', 'ecodiag' ); ?></th>
                                <th><?php esc_html_e( 'Score', 'ecodiag' ); ?></th>
                                <th><?php esc_html_e( 'Poids', 'ecodiag' ); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Weight breakdown -->
            <div class="ecodiag-card ecodiag-card-wide">
                <h2><?php esc_html_e( 'Répartition du poids moyen', 'ecodiag' ); ?></h2>
                <div class="ecodiag-chart-container">
                    <canvas id="ecodiag-weight-chart" height="200"></canvas>
                </div>
            </div>

            <div id="ecodiag-action-notification" class="ecodiag-notification" style="display:none"></div>
        </div>
        <?php
    }

    /**
     * Render the global diagnostics page.
     */
    public function render_diagnostics() {
        ?>
        <div class="wrap ecodiag-wrap">
            <h1 class="ecodiag-title"><?php esc_html_e( 'EcoDiag — Diagnostic global', 'ecodiag' ); ?></h1>

            <div id="ecodiag-diagnostics-loading" class="ecodiag-loading-state">
                <?php esc_html_e( 'Chargement des diagnostics…', 'ecodiag' ); ?>
            </div>

            <div id="ecodiag-diagnostics-content" style="display:none">
                <!-- DATABASE (G-BDD) -->
                <div class="ecodiag-section">
                    <h2><?php esc_html_e( 'Base de données', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-diag-grid" id="ecodiag-diag-bdd"></div>
                </div>

                <!-- PLUGINS & THEMES (G-PLG) -->
                <div class="ecodiag-section">
                    <h2><?php esc_html_e( 'Plugins & Thèmes', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-diag-grid" id="ecodiag-diag-plg"></div>
                </div>

                <!-- MEDIA (G-MED) -->
                <div class="ecodiag-section">
                    <h2><?php esc_html_e( 'Médiathèque', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-diag-grid" id="ecodiag-diag-med"></div>
                </div>

                <!-- HEAD CLEANUP (G-HEAD) -->
                <div class="ecodiag-section">
                    <h2><?php esc_html_e( 'Nettoyage du &lt;head&gt; et assets globaux', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-toggles-grid" id="ecodiag-diag-head"></div>
                </div>

                <!-- TRACKING (G-TRACK) -->
                <div class="ecodiag-section">
                    <h2><?php esc_html_e( 'Scripts tiers & tracking', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-diag-grid" id="ecodiag-diag-tracking"></div>
                </div>

                <!-- EDITORIAL (G-EDIT) -->
                <div class="ecodiag-section">
                    <h2><?php esc_html_e( 'Contenu éditorial', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-diag-grid" id="ecodiag-diag-editorial"></div>
                </div>

                <!-- CRON (G-CRON) -->
                <div class="ecodiag-section">
                    <h2><?php esc_html_e( 'WP-Cron', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-diag-grid" id="ecodiag-diag-cron"></div>
                </div>

                <!-- SERVER (G-REC) -->
                <div class="ecodiag-section">
                    <h2><?php esc_html_e( 'Hébergement & serveur', 'ecodiag' ); ?></h2>
                    <div class="ecodiag-diag-grid" id="ecodiag-diag-server"></div>
                </div>
            </div>

            <div id="ecodiag-action-notification" class="ecodiag-notification" style="display:none"></div>
        </div>
        <?php
    }

    /**
     * Render the archives diagnostic page.
     */
    public function render_archives() {
        ?>
        <div class="wrap ecodiag-wrap">
            <h1 class="ecodiag-title"><?php esc_html_e( 'EcoDiag — Diagnostic des archives', 'ecodiag' ); ?></h1>

            <p><?php esc_html_e( 'Les archives n\'ont pas d\'écran d\'édition natif. Utilisez cette page pour diagnostiquer les pages d\'archives.', 'ecodiag' ); ?></p>

            <div class="ecodiag-card">
                <h2><?php esc_html_e( 'URLs d\'archives à diagnostiquer', 'ecodiag' ); ?></h2>
                <div class="ecodiag-archive-urls">
                    <?php $this->list_archive_urls(); ?>
                </div>
            </div>

            <div id="ecodiag-archive-results" style="display:none">
                <div class="ecodiag-card">
                    <h2 id="ecodiag-archive-title"></h2>
                    <div id="ecodiag-archive-indicators" class="ecodiag-grid"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * List archive URLs for diagnostics.
     */
    private function list_archive_urls() {
        $urls = array();

        // Home page
        $urls[] = array(
            'label' => __( 'Page d\'accueil', 'ecodiag' ),
            'url'   => home_url( '/' ),
        );

        // Category archives
        $categories = get_categories( array( 'number' => 20, 'hide_empty' => true ) );
        foreach ( $categories as $cat ) {
            $urls[] = array(
                'label' => sprintf( __( 'Catégorie : %s', 'ecodiag' ), $cat->name ),
                'url'   => get_category_link( $cat->term_id ),
            );
        }

        // CPT archives
        $post_types = get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects' );
        foreach ( $post_types as $pt ) {
            $archive_url = get_post_type_archive_link( $pt->name );
            if ( $archive_url ) {
                $urls[] = array(
                    'label' => sprintf( __( 'Archive CPT : %s', 'ecodiag' ), $pt->label ),
                    'url'   => $archive_url,
                );
            }
        }

        echo '<table class="ecodiag-table"><thead><tr>';
        echo '<th>' . esc_html__( 'Archive', 'ecodiag' ) . '</th>';
        echo '<th>' . esc_html__( 'URL', 'ecodiag' ) . '</th>';
        echo '<th>' . esc_html__( 'Action', 'ecodiag' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $urls as $item ) {
            echo '<tr>';
            echo '<td>' . esc_html( $item['label'] ) . '</td>';
            echo '<td><code>' . esc_html( $item['url'] ) . '</code></td>';
            echo '<td><button type="button" class="button ecodiag-audit-url" data-url="' . esc_attr( $item['url'] ) . '">' . esc_html__( 'Diagnostiquer', 'ecodiag' ) . '</button></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
