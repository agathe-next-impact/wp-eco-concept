<?php
/**
 * DOM Cleaner fixer: remove emojis, embeds, CSS versioning.
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes unnecessary WordPress default features that add weight.
 */
final class EcoPress_Fixer_DOM {

	public function __construct() {
		add_action( 'init', [ $this, 'apply_fixes' ] );
	}

	/**
	 * Apply enabled DOM cleaning fixes.
	 */
	public function apply_fixes(): void {
		if ( (int) get_option( 'ecopress_dom_remove_emoji', 0 ) === 1 ) {
			$this->remove_emojis();
		}

		if ( (int) get_option( 'ecopress_dom_remove_embeds', 0 ) === 1 ) {
			$this->remove_embeds();
		}

		if ( (int) get_option( 'ecopress_dom_remove_version', 0 ) === 1 ) {
			add_filter( 'style_loader_src', [ $this, 'strip_version_param' ], 9999 );
			add_filter( 'script_loader_src', [ $this, 'strip_version_param' ], 9999 );
		}

		if ( (int) get_option( 'ecopress_dom_remove_jquery_migrate', 0 ) === 1 ) {
			add_action( 'wp_default_scripts', [ $this, 'remove_jquery_migrate' ] );
		}

		if ( (int) get_option( 'ecopress_dom_remove_dashicons', 0 ) === 1 ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'remove_dashicons' ] );
		}

		if ( (int) get_option( 'ecopress_dom_disable_heartbeat', 0 ) === 1 ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'disable_heartbeat_frontend' ], 99 );
		}

		if ( (int) get_option( 'ecopress_dom_clean_head', 0 ) === 1 ) {
			$this->clean_wp_head();
		}
	}

	/**
	 * Remove WordPress emoji scripts and styles.
	 */
	private function remove_emojis(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter( 'tiny_mce_plugins', static function ( array $plugins ): array {
			return array_diff( $plugins, [ 'wpemoji' ] );
		} );

		add_filter( 'wp_resource_hints', static function ( array $urls, string $relation_type ): array {
			if ( 'dns-prefetch' === $relation_type ) {
				$urls = array_filter( $urls, static function ( $url ): bool {
					if ( is_array( $url ) ) {
						$url = $url['href'] ?? '';
					}
					return ! str_contains( (string) $url, 'svgxuse' )
						&& ! str_contains( (string) $url, 's.w.org/images/core/emoji' );
				} );
			}
			return $urls;
		}, 10, 2 );
	}

	/**
	 * Disable WordPress default oEmbed functionality.
	 */
	private function remove_embeds(): void {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );

		add_filter( 'embed_oembed_discover', '__return_false' );

		add_action( 'wp_footer', static function (): void {
			wp_deregister_script( 'wp-embed' );
		} );
	}

	/**
	 * Strip ?ver= query string from asset URLs.
	 *
	 * @param string|null $src The source URL.
	 * @return string|null Cleaned URL.
	 */
	public function strip_version_param( ?string $src ): ?string {
		if ( $src && str_contains( $src, 'ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Remove jQuery Migrate from the frontend.
	 * jQuery Migrate is a legacy compatibility layer rarely needed on modern sites.
	 *
	 * @param \WP_Scripts $scripts WordPress scripts registry.
	 */
	public function remove_jquery_migrate( \WP_Scripts $scripts ): void {
		if ( is_admin() ) {
			return;
		}

		if ( isset( $scripts->registered['jquery'] ) ) {
			$scripts->registered['jquery']->deps = array_diff(
				$scripts->registered['jquery']->deps,
				[ 'jquery-migrate' ]
			);
		}
	}

	/**
	 * Remove Dashicons CSS on the frontend for non-logged-in users.
	 * Dashicons is ~46 KB and only needed by the admin bar.
	 */
	public function remove_dashicons(): void {
		if ( ! is_user_logged_in() ) {
			wp_deregister_style( 'dashicons' );
		}
	}

	/**
	 * Disable the WordPress Heartbeat API on the frontend.
	 * Heartbeat sends AJAX requests every 15-60 seconds, wasting bandwidth.
	 */
	public function disable_heartbeat_frontend(): void {
		wp_deregister_script( 'heartbeat' );
	}

	/**
	 * Remove unnecessary tags from wp_head.
	 * Cleans: RSD link, WLW manifest, shortlink, REST API link, wp-json, feed links, generator meta.
	 */
	private function clean_wp_head(): void {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}
}
