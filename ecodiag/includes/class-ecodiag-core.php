<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EcoDiag_Core {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_modules();

        // Invalidate audit cache when a post is saved
        add_action( 'save_post', array( $this, 'invalidate_audit_cache' ), 10, 1 );
    }

    /**
     * Clear audit transient when a post is updated.
     */
    public function invalidate_audit_cache( $post_id ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        delete_transient( 'ecodiag_audit_' . $post_id );
    }

    private function load_modules() {
        // Always load
        new EcoDiag_Head_Cleanup();
        new EcoDiag_Upload_Handler();
        new EcoDiag_Conditional_Loader();
        new EcoDiag_Cron();

        // Admin
        if ( is_admin() ) {
            new EcoDiag_Admin_Dashboard();
            new EcoDiag_Admin_Settings();
            new EcoDiag_Admin_Metabox();
            new EcoDiag_Admin_Columns();
            new EcoDiag_Ajax_Handler();
        }

        // Front-end admin bar
        if ( get_option( 'ecodiag_frontend_enabled', '1' ) === '1' ) {
            new EcoDiag_Adminbar();
        }

        // Front-end diagnostic popup
        if ( get_option( 'ecodiag_popup_enabled', '1' ) === '1' ) {
            new EcoDiag_Front_Popup();
        }
    }

    /**
     * Get all public post types to audit.
     */
    public static function get_audited_post_types() {
        $configured = get_option( 'ecodiag_audited_cpts', array() );
        if ( ! empty( $configured ) ) {
            return $configured;
        }
        $types = get_post_types( array( 'public' => true ), 'names' );
        unset( $types['attachment'] );
        return array_values( $types );
    }
}
