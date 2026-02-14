<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EcoDiag_Activator {

    public static function activate() {
        self::create_tables();
        self::set_defaults();
        wp_schedule_event( time(), 'daily', 'ecodiag_daily_audit' );
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'ecodiag_daily_audit' );
        wp_clear_scheduled_hook( 'ecodiag_scheduled_audit' );
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'ecodiag_history';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type VARCHAR(20) NOT NULL DEFAULT 'post',
            object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            page_weight BIGINT UNSIGNED NOT NULL DEFAULT 0,
            requests_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            dom_size INT UNSIGNED NOT NULL DEFAULT 0,
            js_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            css_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            img_issues SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            details LONGTEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_object (object_type, object_id),
            KEY idx_created (created_at),
            KEY idx_score (score)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'ecodiag_db_version', ECODIAG_VERSION );
    }

    private static function set_defaults() {
        $defaults = array(
            'ecodiag_frontend_enabled'        => '1',
            'ecodiag_allowed_roles'           => array( 'administrator' ),
            'ecodiag_image_max_weight'        => 200,
            'ecodiag_compression_quality'     => 80,
            'ecodiag_conversion_format'       => 'webp',
            'ecodiag_revisions_keep'          => 5,
            'ecodiag_auto_compress_upload'    => '0',
            'ecodiag_auto_convert_upload'     => '0',
            'ecodiag_upload_max_size'         => 5,
            'ecodiag_audit_frequency'         => 'monthly',
            'ecodiag_alert_threshold'         => 50,
            'ecodiag_alert_email'             => get_option( 'admin_email' ),
            'ecodiag_show_listing_column'     => '1',
            'ecodiag_audited_cpts'            => array(),
            'ecodiag_history_retention'       => 365,
            // Head cleanup toggles (G-HEAD)
            'ecodiag_head_remove_rsd'         => '0',
            'ecodiag_head_remove_wlw'         => '0',
            'ecodiag_head_remove_shortlink'   => '0',
            'ecodiag_head_remove_wp_version'  => '0',
            'ecodiag_head_remove_rest_links'  => '0',
            'ecodiag_head_remove_emoji_dns'   => '0',
            'ecodiag_head_remove_emoji'       => '0',
            'ecodiag_head_remove_embed'       => '0',
            'ecodiag_head_remove_xmlrpc'      => '0',
            'ecodiag_head_remove_gutenberg_css' => '0',
            'ecodiag_head_remove_global_styles' => '0',
            'ecodiag_head_remove_jquery'      => '0',
            // Conditional loading
            'ecodiag_conditional_rules'       => array(),
        );

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                update_option( $key, $value );
            }
        }
    }
}
