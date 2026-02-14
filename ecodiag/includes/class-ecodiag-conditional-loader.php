<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Conditional asset loading — G-COND-01 to G-COND-03
 * Unloads plugin assets based on rules defined in settings.
 */
class EcoDiag_Conditional_Loader {

    public function __construct() {
        if ( ! is_admin() ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'apply_rules' ), 9999 );
        }

        // Per-content script disabling (P-ASS-03)
        add_action( 'wp_enqueue_scripts', array( $this, 'apply_per_content_rules' ), 9999 );
    }

    /**
     * Apply global conditional loading rules.
     */
    public function apply_rules() {
        $rules = get_option( 'ecodiag_conditional_rules', array() );
        if ( empty( $rules ) || ! is_array( $rules ) ) {
            return;
        }

        foreach ( $rules as $rule ) {
            if ( empty( $rule['plugin'] ) || empty( $rule['mode'] ) ) {
                continue;
            }

            $should_load = $this->should_load( $rule );
            if ( ! $should_load ) {
                $this->unload_plugin_assets( $rule['plugin'] );
            }
        }
    }

    /**
     * Apply per-content script/style disabling.
     */
    public function apply_per_content_rules() {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            return;
        }

        $disabled_scripts = get_post_meta( $post_id, '_ecodiag_disabled_scripts', true );
        if ( is_array( $disabled_scripts ) ) {
            foreach ( $disabled_scripts as $handle ) {
                wp_dequeue_script( sanitize_key( $handle ) );
                wp_deregister_script( sanitize_key( $handle ) );
            }
        }

        $disabled_styles = get_post_meta( $post_id, '_ecodiag_disabled_styles', true );
        if ( is_array( $disabled_styles ) ) {
            foreach ( $disabled_styles as $handle ) {
                wp_dequeue_style( sanitize_key( $handle ) );
                wp_deregister_style( sanitize_key( $handle ) );
            }
        }
    }

    /**
     * Determine if a plugin's assets should be loaded on the current page.
     */
    private function should_load( $rule ) {
        $mode = $rule['mode'];

        if ( $mode === 'everywhere' ) {
            return true;
        }

        if ( $mode === 'nowhere' ) {
            return false;
        }

        if ( $mode === 'specific' && ! empty( $rule['pages'] ) ) {
            $current_id = get_queried_object_id();
            $pages = array_map( 'intval', (array) $rule['pages'] );
            return in_array( $current_id, $pages, true );
        }

        if ( $mode === 'post_types' && ! empty( $rule['post_types'] ) ) {
            $current_type = get_post_type();
            // On homepage/archive, get_post_type() can return empty
            if ( ! $current_type ) {
                $queried = get_queried_object();
                if ( $queried instanceof WP_Post ) {
                    $current_type = $queried->post_type;
                } elseif ( is_home() || is_front_page() ) {
                    $current_type = 'page';
                }
            }
            return $current_type && in_array( $current_type, (array) $rule['post_types'], true );
        }

        return true;
    }

    /**
     * Unload all scripts and styles from a specific plugin.
     */
    private function unload_plugin_assets( $plugin_slug ) {
        global $wp_scripts, $wp_styles;

        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_slug . '/';

        if ( isset( $wp_scripts->registered ) ) {
            foreach ( $wp_scripts->registered as $handle => $script ) {
                if ( isset( $script->src ) && strpos( $script->src, $plugin_slug ) !== false ) {
                    wp_dequeue_script( $handle );
                }
            }
        }

        if ( isset( $wp_styles->registered ) ) {
            foreach ( $wp_styles->registered as $handle => $style ) {
                if ( isset( $style->src ) && strpos( $style->src, $plugin_slug ) !== false ) {
                    wp_dequeue_style( $handle );
                }
            }
        }
    }

    /**
     * Get all active plugins and their detected assets.
     */
    public static function get_plugin_assets() {
        $active_plugins = get_option( 'active_plugins', array() );
        $result = array();

        foreach ( $active_plugins as $plugin_file ) {
            $slug = dirname( $plugin_file );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_file, '.php' );
            }

            // Skip EcoDiag itself
            if ( $slug === 'ecodiag' ) {
                continue;
            }

            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
            $result[] = array(
                'slug' => $slug,
                'name' => $plugin_data['Name'] ?? $slug,
                'file' => $plugin_file,
            );
        }

        return $result;
    }

    /**
     * Detect well-known plugin asset patterns for smart defaults.
     */
    public static function get_smart_suggestions() {
        $suggestions = array();
        $active_plugins = get_option( 'active_plugins', array() );
        $slugs = array_map( function( $p ) { return dirname( $p ); }, $active_plugins );

        // G-COND-01: Contact Form 7
        if ( in_array( 'contact-form-7', $slugs, true ) ) {
            $suggestions[] = array(
                'ref'    => 'G-COND-01',
                'plugin' => 'contact-form-7',
                'label'  => __( 'Contact Form 7 : assets chargés sur toutes les pages', 'ecodiag' ),
                'suggestion' => __( 'Charger uniquement sur les pages contenant un formulaire', 'ecodiag' ),
            );
        }

        // G-COND-02: WooCommerce
        if ( in_array( 'woocommerce', $slugs, true ) ) {
            $suggestions[] = array(
                'ref'    => 'G-COND-02',
                'plugin' => 'woocommerce',
                'label'  => __( 'WooCommerce : assets chargés hors boutique', 'ecodiag' ),
                'suggestion' => __( 'Charger uniquement sur les pages boutique', 'ecodiag' ),
            );
        }

        return $suggestions;
    }
}
