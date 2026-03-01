<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Module 2 — Content metabox with diagnostic & actions.
 */
class EcoDiag_Admin_Metabox {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function register() {
        $post_types = EcoDiag_Core::get_audited_post_types();
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'ecodiag-metabox',
                __( 'EcoDiag — Diagnostic écoconception', 'ecodiag' ),
                array( $this, 'render' ),
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function enqueue( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }
        wp_enqueue_style( 'ecodiag-metabox', ECODIAG_URL . 'assets/css/metabox.css', array(), ECODIAG_VERSION );
        wp_enqueue_script( 'ecodiag-metabox', ECODIAG_URL . 'assets/js/metabox.js', array(), ECODIAG_VERSION, true );
        wp_localize_script( 'ecodiag-metabox', 'ecodiagMetabox', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ecodiag_nonce' ),
            'postId'  => get_the_ID(),
            'i18n'    => array(
                'running'        => __( 'Analyse en cours…', 'ecodiag' ),
                'error'          => __( 'Erreur lors de l\'analyse.', 'ecodiag' ),
                'score'          => __( 'Score EcoDiag', 'ecodiag' ),
                'weight'         => __( 'Poids total', 'ecodiag' ),
                'requests'       => __( 'Requêtes HTTP', 'ecodiag' ),
                'dom'            => __( 'Nœuds DOM', 'ecodiag' ),
                'js'             => __( 'Scripts JS', 'ecodiag' ),
                'css'            => __( 'Feuilles CSS', 'ecodiag' ),
                'images'         => __( 'Images', 'ecodiag' ),
                'noIssues'       => __( 'Aucun problème détecté.', 'ecodiag' ),
                'revisions'      => __( 'Révisions', 'ecodiag' ),
                'orphanedMeta'   => __( 'Métadonnées orphelines', 'ecodiag' ),
                'contentWeight'  => __( 'Poids HTML brut', 'ecodiag' ),
                'blocks'         => __( 'Blocs Gutenberg', 'ecodiag' ),
                'shortcodes'     => __( 'Shortcodes cassés', 'ecodiag' ),
                'processing'     => __( 'Traitement…', 'ecodiag' ),
                'done'           => __( 'Terminé', 'ecodiag' ),
                'tabDiag'        => __( 'Diagnostic', 'ecodiag' ),
                'tabActions'     => __( 'Actions', 'ecodiag' ),
                'tabReco'        => __( 'Recommandations', 'ecodiag' ),
            ),
        ) );
    }

    public function render( $post ) {
        ?>
        <div id="ecodiag-metabox-wrap">
            <div class="ecodiag-tabs">
                <button class="ecodiag-tab active" data-tab="diagnostic"><?php esc_html_e( 'Diagnostic', 'ecodiag' ); ?></button>
                <button class="ecodiag-tab" data-tab="actions"><?php esc_html_e( 'Actions', 'ecodiag' ); ?></button>
                <button class="ecodiag-tab" data-tab="recommendations"><?php esc_html_e( 'Recommandations', 'ecodiag' ); ?></button>
            </div>

            <!-- DIAGNOSTIC TAB -->
            <div class="ecodiag-tab-content active" id="ecodiag-tab-diagnostic">
                <div class="ecodiag-score-header">
                    <div class="ecodiag-score-circle" id="ecodiag-score-circle">
                        <span class="ecodiag-score-value">—</span>
                        <span class="ecodiag-score-label">/100</span>
                    </div>
                    <div class="ecodiag-score-actions">
                        <button type="button" class="button button-primary" id="ecodiag-run-audit">
                            <?php esc_html_e( 'Lancer le diagnostic', 'ecodiag' ); ?>
                        </button>
                    </div>
                </div>

                <div id="ecodiag-indicators" class="ecodiag-grid" style="display:none">
                    <!-- Populated by JS -->
                </div>

                <div id="ecodiag-details" style="display:none">
                    <h4><?php esc_html_e( 'Images du contenu', 'ecodiag' ); ?></h4>
                    <div id="ecodiag-images-list"></div>

                    <h4><?php esc_html_e( 'Vidéos & Embeds', 'ecodiag' ); ?></h4>
                    <div id="ecodiag-embeds-list"></div>

                    <h4><?php esc_html_e( 'Contenu éditorial', 'ecodiag' ); ?></h4>
                    <div id="ecodiag-content-diag"></div>

                    <h4><?php esc_html_e( 'Ressources les plus lourdes', 'ecodiag' ); ?></h4>
                    <div id="ecodiag-resources-list"></div>
                </div>
            </div>

            <!-- ACTIONS TAB -->
            <div class="ecodiag-tab-content" id="ecodiag-tab-actions">
                <p class="ecodiag-help"><?php esc_html_e( 'Lancez d\'abord un diagnostic pour voir les actions disponibles.', 'ecodiag' ); ?></p>

                <div id="ecodiag-actions-list" style="display:none">
                    <h4><?php esc_html_e( 'Images', 'ecodiag' ); ?></h4>
                    <div class="ecodiag-action-group" id="ecodiag-actions-images">
                        <button type="button" class="button ecodiag-action-btn" data-action="ecodiag_convert_images" data-ref="P-IMG-01">
                            <?php esc_html_e( 'Convertir en WebP', 'ecodiag' ); ?>
                        </button>
                        <button type="button" class="button ecodiag-action-btn" data-action="ecodiag_add_lazy_loading" data-ref="P-IMG-02">
                            <?php esc_html_e( 'Ajouter lazy loading', 'ecodiag' ); ?>
                        </button>
                        <button type="button" class="button ecodiag-action-btn" data-action="ecodiag_add_dimensions" data-ref="P-IMG-05">
                            <?php esc_html_e( 'Ajouter dimensions', 'ecodiag' ); ?>
                        </button>
                        <button type="button" class="button ecodiag-action-btn" data-action="ecodiag_compress_images" data-ref="P-IMG-06">
                            <?php esc_html_e( 'Compresser les images', 'ecodiag' ); ?>
                        </button>
                    </div>

                    <h4><?php esc_html_e( 'Vidéos & Embeds', 'ecodiag' ); ?></h4>
                    <div class="ecodiag-action-group" id="ecodiag-actions-videos">
                        <button type="button" class="button ecodiag-action-btn" data-action="ecodiag_convert_embeds" data-ref="P-VID-01">
                            <?php esc_html_e( 'Convertir en façade légère', 'ecodiag' ); ?>
                        </button>
                        <button type="button" class="button ecodiag-action-btn" data-action="ecodiag_remove_autoplay" data-ref="P-VID-02">
                            <?php esc_html_e( 'Supprimer autoplay', 'ecodiag' ); ?>
                        </button>
                        <button type="button" class="button ecodiag-action-btn" data-action="ecodiag_add_iframe_lazy" data-ref="P-VID-03">
                            <?php esc_html_e( 'Lazy loading iframes', 'ecodiag' ); ?>
                        </button>
                    </div>

                    <h4><?php esc_html_e( 'Contenu', 'ecodiag' ); ?></h4>
                    <div class="ecodiag-action-group" id="ecodiag-actions-content">
                        <button type="button" class="button ecodiag-action-btn" data-action="ecodiag_purge_revisions" data-ref="P-CONT-01">
                            <?php esc_html_e( 'Purger les révisions', 'ecodiag' ); ?>
                        </button>
                        <button type="button" class="button ecodiag-action-btn" data-action="ecodiag_clean_orphaned_meta" data-ref="P-CONT-02">
                            <?php esc_html_e( 'Nettoyer métadonnées orphelines', 'ecodiag' ); ?>
                        </button>
                    </div>

                    <h4><?php esc_html_e( 'Assets de la page', 'ecodiag' ); ?></h4>
                    <div class="ecodiag-action-group" id="ecodiag-actions-assets">
                        <div id="ecodiag-scripts-manager"></div>
                    </div>
                </div>

                <div id="ecodiag-action-result" class="ecodiag-notice" style="display:none"></div>
            </div>

            <!-- RECOMMENDATIONS TAB -->
            <div class="ecodiag-tab-content" id="ecodiag-tab-recommendations">
                <p class="ecodiag-help"><?php esc_html_e( 'Lancez d\'abord un diagnostic pour voir les recommandations.', 'ecodiag' ); ?></p>
                <div id="ecodiag-recommendations-list" style="display:none"></div>
            </div>
        </div>
        <?php
    }
}
