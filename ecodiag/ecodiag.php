<?php
/**
 * Plugin Name: EcoDiag
 * Plugin URI: https://github.com/agathe-next-impact/wp-eco-concept
 * Description: Plugin WordPress d'audit et d'optimisation écoconception. Diagnostic front-end, par contenu et global avec actions d'optimisation automatisées.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Agathe Next Impact
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ecodiag
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ECODIAG_VERSION', '1.0.0' );
define( 'ECODIAG_FILE', __FILE__ );
define( 'ECODIAG_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECODIAG_URL', plugin_dir_url( __FILE__ ) );
define( 'ECODIAG_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
spl_autoload_register( function ( $class ) {
    $prefix = 'EcoDiag_';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $relative = strtolower( str_replace( '_', '-', $relative ) );
    $filename = 'class-ecodiag-' . $relative . '.php';

    $dirs = array(
        ECODIAG_DIR . 'includes/',
        ECODIAG_DIR . 'includes/diagnostics/',
        ECODIAG_DIR . 'includes/actions/',
        ECODIAG_DIR . 'admin/',
        ECODIAG_DIR . 'public/',
    );

    foreach ( $dirs as $dir ) {
        $filepath = $dir . $filename;
        if ( file_exists( $filepath ) ) {
            require_once $filepath;
            return;
        }
    }
} );

// Activation
register_activation_hook( __FILE__, array( 'EcoDiag_Activator', 'activate' ) );

// Deactivation
register_deactivation_hook( __FILE__, array( 'EcoDiag_Activator', 'deactivate' ) );

// Boot
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'ecodiag', false, dirname( ECODIAG_BASENAME ) . '/languages' );
    EcoDiag_Core::instance();
} );
