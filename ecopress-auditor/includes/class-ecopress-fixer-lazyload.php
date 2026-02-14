<?php
/**
 * Lazy-Load Force fixer.
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies loading="lazy" to all images and iframes.
 */
final class EcoPress_Fixer_LazyLoad {

	public function __construct() {
		if ( (int) get_option( 'ecopress_lazyload_enabled', 0 ) === 1 ) {
			add_filter( 'the_content', [ $this, 'add_lazy_loading' ], 999 );
			add_filter( 'post_thumbnail_html', [ $this, 'add_lazy_loading' ], 999 );
			add_filter( 'widget_text', [ $this, 'add_lazy_loading' ], 999 );
			add_filter( 'get_avatar', [ $this, 'add_lazy_loading' ], 999 );
		}
	}

	/**
	 * Add loading="lazy" to img and iframe tags that lack it.
	 *
	 * @param string $content HTML content.
	 * @return string Modified HTML content.
	 */
	public function add_lazy_loading( string $content ): string {
		if ( empty( $content ) ) {
			return $content;
		}

		// Add lazy loading to images without a loading attribute.
		$content = (string) preg_replace(
			'/<img(?![^>]*loading\s*=)([^>]*)\/?>/i',
			'<img loading="lazy"$1 />',
			$content
		);

		// Add lazy loading to iframes without a loading attribute.
		$content = (string) preg_replace(
			'/<iframe(?![^>]*loading\s*=)([^>]*)>/i',
			'<iframe loading="lazy"$1>',
			$content
		);

		return $content;
	}
}
