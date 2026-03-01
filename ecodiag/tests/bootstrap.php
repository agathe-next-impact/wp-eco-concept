<?php
/**
 * PHPUnit bootstrap for EcoDiag tests.
 *
 * Provides minimal WordPress function stubs so we can unit-test plugin classes
 * without a full WordPress installation.
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'ECODIAG_VERSION', '1.0.0-test' );
define( 'ECODIAG_PATH', dirname( __DIR__ ) . '/' );
define( 'ECODIAG_URL', 'https://example.com/wp-content/plugins/ecodiag/' );
define( 'DAY_IN_SECONDS', 86400 );

// ──────────────────────────────────────────────
// Minimal WP function stubs
// ──────────────────────────────────────────────

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url, $component = -1 ) {
        if ( $component === -1 ) {
            return parse_url( $url );
        }
        return parse_url( $url, $component );
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
    }
}

/**
 * Stubbed get_option — tests can override via EcoDiag_Test_Options.
 */
$GLOBALS['_ecodiag_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $key, $default = false ) {
        if ( isset( $GLOBALS['_ecodiag_test_options'][ $key ] ) ) {
            return $GLOBALS['_ecodiag_test_options'][ $key ];
        }
        return $default;
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) {
        return 'https://example.com' . $path;
    }
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
    function wp_list_pluck( $list, $field ) {
        return array_map( function ( $item ) use ( $field ) {
            return is_object( $item ) ? $item->$field : $item[ $field ];
        }, $list );
    }
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
    function is_user_logged_in() {
        return $GLOBALS['_ecodiag_test_user_logged_in'] ?? false;
    }
}

if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() {
        return $GLOBALS['_ecodiag_test_is_admin'] ?? false;
    }
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
    function wp_get_current_user() {
        return $GLOBALS['_ecodiag_test_current_user'] ?? (object) array( 'roles' => array() );
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        return $GLOBALS['_ecodiag_test_capabilities'][ $capability ] ?? false;
    }
}

if ( ! function_exists( 'is_singular' ) ) {
    function is_singular() {
        return $GLOBALS['_ecodiag_test_is_singular'] ?? false;
    }
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
    function get_queried_object_id() {
        return $GLOBALS['_ecodiag_test_queried_object_id'] ?? 0;
    }
}

if ( ! function_exists( 'get_queried_object' ) ) {
    function get_queried_object() {
        return $GLOBALS['_ecodiag_test_queried_object'] ?? null;
    }
}

if ( ! function_exists( 'get_post_type' ) ) {
    function get_post_type() {
        return $GLOBALS['_ecodiag_test_post_type'] ?? '';
    }
}

if ( ! function_exists( 'is_home' ) ) {
    function is_home() {
        return $GLOBALS['_ecodiag_test_is_home'] ?? false;
    }
}

if ( ! function_exists( 'is_front_page' ) ) {
    function is_front_page() {
        return $GLOBALS['_ecodiag_test_is_front_page'] ?? false;
    }
}

if ( ! function_exists( 'wp_dequeue_script' ) ) {
    function wp_dequeue_script( $handle ) {
        $GLOBALS['_ecodiag_test_dequeued_scripts'][] = $handle;
    }
}

if ( ! function_exists( 'wp_deregister_script' ) ) {
    function wp_deregister_script( $handle ) {}
}

if ( ! function_exists( 'wp_dequeue_style' ) ) {
    function wp_dequeue_style( $handle ) {
        $GLOBALS['_ecodiag_test_dequeued_styles'][] = $handle;
    }
}

if ( ! function_exists( 'wp_deregister_style' ) ) {
    function wp_deregister_style( $handle ) {}
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key, $single = false ) {
        $meta = $GLOBALS['_ecodiag_test_post_meta'][ $post_id ][ $key ] ?? ( $single ? '' : array() );
        return $meta;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        $GLOBALS['_ecodiag_test_actions'][ $hook ][] = array( $callback, $priority );
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        $GLOBALS['_ecodiag_test_filters'][ $hook ][] = array( $callback, $priority );
    }
}

// WP_Post stub
if ( ! class_exists( 'WP_Post' ) ) {
    class WP_Post {
        public $ID;
        public $post_type;
        public $post_title;
        public $post_content;

        public function __construct( $data = array() ) {
            foreach ( $data as $k => $v ) {
                $this->$k = $v;
            }
        }
    }
}

// ──────────────────────────────────────────────
// AJAX / security stubs
// ──────────────────────────────────────────────

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
        return $GLOBALS['_ecodiag_test_nonce_valid'] ?? true;
    }
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null, $status_code = null ) {
        throw new \EcoDiag_Test_JsonException( 'error', $data );
    }
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null ) {
        throw new \EcoDiag_Test_JsonException( 'success', $data );
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
    }
}

if ( ! function_exists( 'url_to_postid' ) ) {
    function url_to_postid( $url ) {
        return $GLOBALS['_ecodiag_test_url_to_postid'] ?? 0;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) {
        return json_encode( $data, $options, $depth );
    }
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) {
        return 'test_nonce_' . $action;
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) {
        return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = 'default' ) {
        echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) {
        echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

/**
 * Custom exception for intercepting wp_send_json_error/success.
 */
class EcoDiag_Test_JsonException extends \RuntimeException {
    public string $type;
    public mixed $payload;

    public function __construct( string $type, mixed $data ) {
        $this->type    = $type;
        $this->payload = $data;
        parent::__construct( "wp_send_json_{$type}" );
    }
}

// ──────────────────────────────────────────────
// Load the plugin classes under test
// ──────────────────────────────────────────────

require_once ECODIAG_PATH . 'includes/class-ecodiag-scoring.php';
require_once ECODIAG_PATH . 'includes/class-ecodiag-analyzer.php';
require_once ECODIAG_PATH . 'includes/class-ecodiag-conditional-loader.php';
require_once ECODIAG_PATH . 'public/class-ecodiag-front-popup.php';
require_once ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php';
