<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Head cleanup — G-HEAD-01 to G-HEAD-12
 * Applies toggles set in the plugin settings.
 */
class EcoDiag_Head_Cleanup {

    public function __construct() {
        add_action( 'init', array( $this, 'apply_cleanups' ), 1 );
        add_action( 'wp_enqueue_scripts', array( $this, 'cleanup_scripts' ), 999 );
    }

    public function apply_cleanups() {
        // G-HEAD-01: Remove RSD
        if ( get_option( 'ecodiag_head_remove_rsd', '0' ) === '1' ) {
            remove_action( 'wp_head', 'rsd_link' );
        }

        // G-HEAD-02: Remove wlwmanifest
        if ( get_option( 'ecodiag_head_remove_wlw', '0' ) === '1' ) {
            remove_action( 'wp_head', 'wlwmanifest_link' );
        }

        // G-HEAD-03: Remove shortlink
        if ( get_option( 'ecodiag_head_remove_shortlink', '0' ) === '1' ) {
            remove_action( 'wp_head', 'wp_shortlink_wp_head' );
            remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
        }

        // G-HEAD-04: Remove WP version
        if ( get_option( 'ecodiag_head_remove_wp_version', '0' ) === '1' ) {
            remove_action( 'wp_head', 'wp_generator' );
            add_filter( 'the_generator', '__return_empty_string' );
            add_filter( 'style_loader_src', array( $this, 'remove_version_query' ), 999 );
            add_filter( 'script_loader_src', array( $this, 'remove_version_query' ), 999 );
        }

        // G-HEAD-05: Remove REST API links
        if ( get_option( 'ecodiag_head_remove_rest_links', '0' ) === '1' ) {
            remove_action( 'wp_head', 'rest_output_link_wp_head' );
            remove_action( 'template_redirect', 'rest_output_link_header', 11 );
            remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
        }

        // G-HEAD-06 & 07: Remove emoji DNS prefetch and script
        if ( get_option( 'ecodiag_head_remove_emoji', '0' ) === '1' ) {
            remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
            remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
            remove_action( 'wp_print_styles', 'print_emoji_styles' );
            remove_action( 'admin_print_styles', 'print_emoji_styles' );
            remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
            remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
            remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
            add_filter( 'emoji_svg_url', '__return_false' );
        }

        if ( get_option( 'ecodiag_head_remove_emoji_dns', '0' ) === '1' ) {
            add_filter( 'wp_resource_hints', array( $this, 'remove_emoji_dns_prefetch' ), 10, 2 );
        }

        // G-HEAD-08: Remove wp-embed.js
        if ( get_option( 'ecodiag_head_remove_embed', '0' ) === '1' ) {
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
            remove_action( 'wp_head', 'wp_oembed_add_host_js' );
        }

        // G-HEAD-09: Disable XML-RPC
        if ( get_option( 'ecodiag_head_remove_xmlrpc', '0' ) === '1' ) {
            add_filter( 'xmlrpc_enabled', '__return_false' );
            remove_action( 'wp_head', 'rsd_link' );
        }
    }

    public function cleanup_scripts() {
        // G-HEAD-08: Dequeue wp-embed
        if ( get_option( 'ecodiag_head_remove_embed', '0' ) === '1' ) {
            wp_deregister_script( 'wp-embed' );
        }

        // G-HEAD-10: Remove Gutenberg CSS
        if ( get_option( 'ecodiag_head_remove_gutenberg_css', '0' ) === '1' ) {
            wp_dequeue_style( 'wp-block-library' );
            wp_dequeue_style( 'wp-block-library-theme' );
        }

        // G-HEAD-11: Remove global-styles
        if ( get_option( 'ecodiag_head_remove_global_styles', '0' ) === '1' ) {
            wp_dequeue_style( 'global-styles' );
        }

        // G-HEAD-12: Remove jQuery (with dependency check)
        if ( get_option( 'ecodiag_head_remove_jquery', '0' ) === '1' ) {
            if ( ! is_admin() ) {
                wp_deregister_script( 'jquery' );
                wp_register_script( 'jquery', false, array(), false, true );
            }
        }
    }

    /**
     * Remove ?ver= from CSS/JS URLs.
     */
    public function remove_version_query( $src ) {
        if ( strpos( $src, '?ver=' ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    /**
     * Remove emoji DNS prefetch.
     */
    public function remove_emoji_dns_prefetch( $urls, $relation_type ) {
        if ( $relation_type === 'dns-prefetch' ) {
            $urls = array_filter( $urls, function( $url ) {
                return strpos( $url, 's.w.org' ) === false;
            } );
        }
        return $urls;
    }

    /**
     * Diagnostic: detect which head elements are present.
     * Used by the dashboard to show current state.
     */
    public static function diagnose() {
        $diagnostics = array();

        $items = array(
            'G-HEAD-01' => array( 'option' => 'ecodiag_head_remove_rsd', 'label' => __( 'Lien RSD', 'ecodiag' ) ),
            'G-HEAD-02' => array( 'option' => 'ecodiag_head_remove_wlw', 'label' => __( 'Lien wlwmanifest', 'ecodiag' ) ),
            'G-HEAD-03' => array( 'option' => 'ecodiag_head_remove_shortlink', 'label' => __( 'Shortlink', 'ecodiag' ) ),
            'G-HEAD-04' => array( 'option' => 'ecodiag_head_remove_wp_version', 'label' => __( 'Version WordPress', 'ecodiag' ) ),
            'G-HEAD-05' => array( 'option' => 'ecodiag_head_remove_rest_links', 'label' => __( 'Liens REST API', 'ecodiag' ) ),
            'G-HEAD-06' => array( 'option' => 'ecodiag_head_remove_emoji_dns', 'label' => __( 'DNS prefetch emojis', 'ecodiag' ) ),
            'G-HEAD-07' => array( 'option' => 'ecodiag_head_remove_emoji', 'label' => __( 'Script emojis', 'ecodiag' ) ),
            'G-HEAD-08' => array( 'option' => 'ecodiag_head_remove_embed', 'label' => __( 'Script wp-embed', 'ecodiag' ) ),
            'G-HEAD-09' => array( 'option' => 'ecodiag_head_remove_xmlrpc', 'label' => __( 'XML-RPC', 'ecodiag' ) ),
            'G-HEAD-10' => array( 'option' => 'ecodiag_head_remove_gutenberg_css', 'label' => __( 'CSS Gutenberg', 'ecodiag' ) ),
            'G-HEAD-11' => array( 'option' => 'ecodiag_head_remove_global_styles', 'label' => __( 'CSS global-styles', 'ecodiag' ) ),
            'G-HEAD-12' => array( 'option' => 'ecodiag_head_remove_jquery', 'label' => __( 'jQuery en front', 'ecodiag' ) ),
        );

        foreach ( $items as $ref => $item ) {
            $is_cleaned = get_option( $item['option'], '0' ) === '1';
            $diagnostics[] = array(
                'ref'      => $ref,
                'label'    => $item['label'],
                'option'   => $item['option'],
                'cleaned'  => $is_cleaned,
                'status'   => $is_cleaned ? 'green' : 'orange',
            );
        }

        return $diagnostics;
    }
}
