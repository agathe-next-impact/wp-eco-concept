<?php
/**
 * Plugin Name:       EcoPress Auditor & Fixer
 * Plugin URI:        https://github.com/agathe-next-impact/wp-eco-concept
 * Description:       Mesure l'empreinte carbone de vos pages et propose des correctifs d'éco-conception en un clic.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            EcoPress Community
 * Author URI:        https://github.com/agathe-next-impact
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ecopress-auditor
 * Domain Path:       /languages
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ECOPRESS_VERSION', '1.0.0' );
define( 'ECOPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECOPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ECOPRESS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload modules.
require_once ECOPRESS_PLUGIN_DIR . 'includes/class-ecopress-calculator.php';
require_once ECOPRESS_PLUGIN_DIR . 'includes/class-ecopress-analyzer.php';
require_once ECOPRESS_PLUGIN_DIR . 'includes/class-ecopress-fixer-dom.php';
require_once ECOPRESS_PLUGIN_DIR . 'includes/class-ecopress-fixer-lazyload.php';
require_once ECOPRESS_PLUGIN_DIR . 'includes/class-ecopress-fixer-scripts.php';
require_once ECOPRESS_PLUGIN_DIR . 'includes/class-ecopress-fixer-media.php';
require_once ECOPRESS_PLUGIN_DIR . 'admin/class-ecopress-admin.php';
require_once ECOPRESS_PLUGIN_DIR . 'admin/class-ecopress-metabox.php';
require_once ECOPRESS_PLUGIN_DIR . 'public/class-ecopress-hud.php';

/**
 * Main plugin bootstrap class.
 */
final class EcoPress_Auditor {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		$this->init_modules();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'ecopress-auditor',
			false,
			dirname( ECOPRESS_PLUGIN_BASENAME ) . '/languages'
		);
	}

	private function init_modules(): void {
		// Admin modules.
		if ( is_admin() ) {
			new EcoPress_Admin();
			new EcoPress_Metabox();
		}

		// Front-end HUD (only for logged-in editors).
		new EcoPress_HUD();

		// Fixers – always active, controlled by options.
		new EcoPress_Fixer_DOM();
		new EcoPress_Fixer_LazyLoad();
		new EcoPress_Fixer_Scripts();
	}
}

/**
 * Activation hook: set default options.
 */
function ecopress_activate(): void {
	$defaults = [
		'ecopress_dom_remove_emoji'          => 0,
		'ecopress_dom_remove_embeds'         => 0,
		'ecopress_dom_remove_version'        => 0,
		'ecopress_dom_remove_jquery_migrate' => 0,
		'ecopress_dom_remove_dashicons'      => 0,
		'ecopress_dom_disable_heartbeat'     => 0,
		'ecopress_dom_clean_head'            => 0,
		'ecopress_lazyload_enabled'          => 0,
		'ecopress_weight_threshold'          => 1500, // Ko.
		'ecopress_disabled_scripts'          => [],
	];

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( $key ) ) {
			add_option( $key, $value );
		}
	}
}
register_activation_hook( __FILE__, 'ecopress_activate' );

/**
 * Uninstall: clean up options.
 */
function ecopress_uninstall(): void {
	$options = [
		'ecopress_dom_remove_emoji',
		'ecopress_dom_remove_embeds',
		'ecopress_dom_remove_version',
		'ecopress_dom_remove_jquery_migrate',
		'ecopress_dom_remove_dashicons',
		'ecopress_dom_disable_heartbeat',
		'ecopress_dom_clean_head',
		'ecopress_lazyload_enabled',
		'ecopress_weight_threshold',
		'ecopress_disabled_scripts',
	];
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clean transients.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_ecopress_%'
		    OR option_name LIKE '_transient_timeout_ecopress_%'"
	);
}
register_uninstall_hook( __FILE__, 'ecopress_uninstall' );

// Boot.
EcoPress_Auditor::get_instance();
