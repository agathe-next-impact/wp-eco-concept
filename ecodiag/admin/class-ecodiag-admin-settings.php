<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Settings page for EcoDiag plugin.
 */
class EcoDiag_Admin_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function add_menu() {
        add_submenu_page(
            'ecodiag',
            __( 'Réglages', 'ecodiag' ),
            __( 'Réglages', 'ecodiag' ),
            'manage_options',
            'ecodiag-settings',
            array( $this, 'render' )
        );

        add_submenu_page(
            'ecodiag',
            __( 'Chargement conditionnel', 'ecodiag' ),
            __( 'Chargement conditionnel', 'ecodiag' ),
            'manage_options',
            'ecodiag-conditional',
            array( $this, 'render_conditional' )
        );
    }

    public function enqueue( $hook ) {
        if ( strpos( $hook, 'ecodiag' ) === false ) return;
        wp_enqueue_style( 'ecodiag-settings', ECODIAG_URL . 'assets/css/settings.css', array(), ECODIAG_VERSION );
        wp_enqueue_script( 'ecodiag-settings', ECODIAG_URL . 'assets/js/settings.js', array(), ECODIAG_VERSION, true );
        wp_localize_script( 'ecodiag-settings', 'ecodiagSettings', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ecodiag_nonce' ),
        ) );
    }

    public function register_settings() {
        // General settings section
        add_settings_section( 'ecodiag_general', __( 'Réglages généraux', 'ecodiag' ), null, 'ecodiag-settings' );

        $this->add_field( 'ecodiag_frontend_enabled', __( 'Activer le bandeau front-end', 'ecodiag' ), 'toggle', 'ecodiag_general' );
        $this->add_field( 'ecodiag_show_listing_column', __( 'Colonne score dans les listings', 'ecodiag' ), 'toggle', 'ecodiag_general' );
        $this->add_field( 'ecodiag_image_max_weight', __( 'Seuil poids image max (Ko)', 'ecodiag' ), 'number', 'ecodiag_general' );
        $this->add_field( 'ecodiag_compression_quality', __( 'Qualité de compression (%)', 'ecodiag' ), 'range', 'ecodiag_general' );
        $this->add_field( 'ecodiag_conversion_format', __( 'Format de conversion', 'ecodiag' ), 'select', 'ecodiag_general', array( 'webp' => 'WebP', 'avif' => 'AVIF' ) );
        $this->add_field( 'ecodiag_revisions_keep', __( 'Révisions à conserver', 'ecodiag' ), 'number', 'ecodiag_general' );

        // Upload settings section
        add_settings_section( 'ecodiag_upload', __( 'Upload & média', 'ecodiag' ), null, 'ecodiag-settings' );

        $this->add_field( 'ecodiag_auto_compress_upload', __( 'Compression auto à l\'upload', 'ecodiag' ), 'toggle', 'ecodiag_upload' );
        $this->add_field( 'ecodiag_auto_convert_upload', __( 'Conversion auto à l\'upload', 'ecodiag' ), 'toggle', 'ecodiag_upload' );
        $this->add_field( 'ecodiag_upload_max_size', __( 'Poids max d\'upload (Mo)', 'ecodiag' ), 'number', 'ecodiag_upload' );

        // Monitoring settings section
        add_settings_section( 'ecodiag_monitoring', __( 'Monitoring & alertes', 'ecodiag' ), null, 'ecodiag-settings' );

        $this->add_field( 'ecodiag_audit_frequency', __( 'Fréquence d\'audit automatique', 'ecodiag' ), 'select', 'ecodiag_monitoring', array( 'never' => __( 'Jamais', 'ecodiag' ), 'weekly' => __( 'Hebdomadaire', 'ecodiag' ), 'monthly' => __( 'Mensuel', 'ecodiag' ) ) );
        $this->add_field( 'ecodiag_alert_threshold', __( 'Seuil d\'alerte score (0-100)', 'ecodiag' ), 'number', 'ecodiag_monitoring' );
        $this->add_field( 'ecodiag_alert_email', __( 'Email de notification', 'ecodiag' ), 'email', 'ecodiag_monitoring' );
        $this->add_field( 'ecodiag_history_retention', __( 'Conserver l\'historique (jours)', 'ecodiag' ), 'number', 'ecodiag_monitoring' );

        // Head cleanup section
        add_settings_section( 'ecodiag_head', __( 'Nettoyage du &lt;head&gt;', 'ecodiag' ), array( $this, 'head_section_description' ), 'ecodiag-settings' );

        $head_toggles = array(
            'ecodiag_head_remove_rsd'          => __( 'Supprimer lien RSD', 'ecodiag' ),
            'ecodiag_head_remove_wlw'          => __( 'Supprimer wlwmanifest', 'ecodiag' ),
            'ecodiag_head_remove_shortlink'    => __( 'Supprimer shortlink', 'ecodiag' ),
            'ecodiag_head_remove_wp_version'   => __( 'Supprimer version WP', 'ecodiag' ),
            'ecodiag_head_remove_rest_links'   => __( 'Supprimer liens REST API', 'ecodiag' ),
            'ecodiag_head_remove_emoji_dns'    => __( 'Supprimer prefetch emojis', 'ecodiag' ),
            'ecodiag_head_remove_emoji'        => __( 'Supprimer script emojis', 'ecodiag' ),
            'ecodiag_head_remove_embed'        => __( 'Supprimer wp-embed.js', 'ecodiag' ),
            'ecodiag_head_remove_xmlrpc'       => __( 'Désactiver XML-RPC', 'ecodiag' ),
            'ecodiag_head_remove_gutenberg_css' => __( 'Supprimer CSS Gutenberg', 'ecodiag' ),
            'ecodiag_head_remove_global_styles' => __( 'Supprimer global-styles', 'ecodiag' ),
            'ecodiag_head_remove_jquery'       => __( 'Supprimer jQuery (front)', 'ecodiag' ),
        );

        foreach ( $head_toggles as $key => $label ) {
            $this->add_field( $key, $label, 'toggle', 'ecodiag_head' );
        }
    }

    private function add_field( $id, $label, $type, $section, $options = array() ) {
        register_setting( 'ecodiag-settings', $id, array(
            'sanitize_callback' => array( $this, 'sanitize_' . $type ),
        ) );

        add_settings_field( $id, $label, function() use ( $id, $type, $options ) {
            $value = get_option( $id );
            switch ( $type ) {
                case 'toggle':
                    printf(
                        '<label class="ecodiag-toggle"><input type="checkbox" name="%s" value="1" %s /><span class="ecodiag-toggle-slider"></span></label>',
                        esc_attr( $id ),
                        checked( $value, '1', false )
                    );
                    break;
                case 'number':
                    printf(
                        '<input type="number" name="%s" value="%s" class="small-text" min="0" />',
                        esc_attr( $id ),
                        esc_attr( $value )
                    );
                    break;
                case 'range':
                    printf(
                        '<input type="range" name="%s" value="%s" min="10" max="100" step="5" class="ecodiag-range" /><span class="ecodiag-range-val">%s%%</span>',
                        esc_attr( $id ),
                        esc_attr( $value ),
                        esc_html( $value )
                    );
                    break;
                case 'select':
                    printf( '<select name="%s">', esc_attr( $id ) );
                    foreach ( $options as $k => $v ) {
                        printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $v ) );
                    }
                    echo '</select>';
                    break;
                case 'email':
                    printf(
                        '<input type="email" name="%s" value="%s" class="regular-text" />',
                        esc_attr( $id ),
                        esc_attr( $value )
                    );
                    break;
            }
        }, 'ecodiag-settings', $section );
    }

    public function head_section_description() {
        echo '<p>' . esc_html__( 'Chaque toggle supprime un élément inutile du &lt;head&gt; WordPress pour alléger les pages.', 'ecodiag' ) . '</p>';
    }

    // Sanitizers
    public function sanitize_toggle( $value ) { return $value ? '1' : '0'; }
    public function sanitize_number( $value ) { return absint( $value ); }
    public function sanitize_range( $value ) { return max( 10, min( 100, absint( $value ) ) ); }
    public function sanitize_select( $value ) { return sanitize_key( $value ); }
    public function sanitize_email( $value ) { return sanitize_email( $value ); }

    /**
     * Render the settings page.
     */
    public function render() {
        ?>
        <div class="wrap ecodiag-wrap">
            <h1 class="ecodiag-title"><?php esc_html_e( 'EcoDiag — Réglages', 'ecodiag' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'ecodiag-settings' );
                do_settings_sections( 'ecodiag-settings' );
                submit_button( __( 'Enregistrer les réglages', 'ecodiag' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the conditional loading page.
     */
    public function render_conditional() {
        $plugins = EcoDiag_Conditional_Loader::get_plugin_assets();
        $rules   = get_option( 'ecodiag_conditional_rules', array() );
        $rules_map = array();
        foreach ( $rules as $rule ) {
            $rules_map[ $rule['plugin'] ] = $rule;
        }
        $suggestions = EcoDiag_Conditional_Loader::get_smart_suggestions();
        ?>
        <div class="wrap ecodiag-wrap">
            <h1 class="ecodiag-title"><?php esc_html_e( 'EcoDiag — Chargement conditionnel', 'ecodiag' ); ?></h1>

            <?php if ( ! empty( $suggestions ) ) : ?>
            <div class="ecodiag-card ecodiag-suggestions">
                <h2><?php esc_html_e( 'Suggestions', 'ecodiag' ); ?></h2>
                <ul>
                    <?php foreach ( $suggestions as $s ) : ?>
                    <li>
                        <strong><?php echo esc_html( $s['label'] ); ?></strong><br>
                        <em><?php echo esc_html( $s['suggestion'] ); ?></em>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="ecodiag-card">
                <h2><?php esc_html_e( 'Règles de chargement', 'ecodiag' ); ?></h2>
                <p><?php esc_html_e( 'Pour chaque plugin, définissez où ses assets (JS/CSS) doivent être chargés.', 'ecodiag' ); ?></p>

                <form id="ecodiag-conditional-form">
                    <table class="ecodiag-table ecodiag-conditional-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Plugin', 'ecodiag' ); ?></th>
                                <th><?php esc_html_e( 'Partout', 'ecodiag' ); ?></th>
                                <th><?php esc_html_e( 'Par type', 'ecodiag' ); ?></th>
                                <th><?php esc_html_e( 'Pages spécifiques', 'ecodiag' ); ?></th>
                                <th><?php esc_html_e( 'Nulle part', 'ecodiag' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $plugins as $p ) :
                                $rule = isset( $rules_map[ $p['slug'] ] ) ? $rules_map[ $p['slug'] ] : array( 'mode' => 'everywhere' );
                                $mode = $rule['mode'] ?? 'everywhere';
                            ?>
                            <tr data-plugin="<?php echo esc_attr( $p['slug'] ); ?>">
                                <td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
                                <td><input type="radio" name="rule_<?php echo esc_attr( $p['slug'] ); ?>" value="everywhere" <?php checked( $mode, 'everywhere' ); ?> /></td>
                                <td><input type="radio" name="rule_<?php echo esc_attr( $p['slug'] ); ?>" value="post_types" <?php checked( $mode, 'post_types' ); ?> /></td>
                                <td><input type="radio" name="rule_<?php echo esc_attr( $p['slug'] ); ?>" value="specific" <?php checked( $mode, 'specific' ); ?> /></td>
                                <td><input type="radio" name="rule_<?php echo esc_attr( $p['slug'] ); ?>" value="nowhere" <?php checked( $mode, 'nowhere' ); ?> /></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <button type="button" class="button button-primary" id="ecodiag-save-conditional">
                        <?php esc_html_e( 'Enregistrer les règles', 'ecodiag' ); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
}
