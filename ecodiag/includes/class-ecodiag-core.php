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
