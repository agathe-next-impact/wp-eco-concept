<?php
/**
 * Script Unloader fixer: selectively dequeue scripts per page.
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allows dequeuing third-party scripts on specific posts/pages.
 */
final class EcoPress_Fixer_Scripts {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_dequeue' ], 9999 );
	}

	/**
	 * Dequeue scripts disabled for the current post.
	 */
	public function maybe_dequeue(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$disabled = get_post_meta( $post_id, '_ecopress_disabled_scripts', true );
		if ( ! is_array( $disabled ) || empty( $disabled ) ) {
			return;
		}

		foreach ( $disabled as $handle ) {
			$handle = sanitize_key( $handle );
			if ( $handle ) {
				wp_dequeue_script( $handle );
				wp_dequeue_style( $handle );
			}
		}
	}

	/**
	 * Get all registered script handles for the current page.
	 * Used in the metabox to populate the script selector.
	 *
	 * @return array<array{handle: string, src: string}>
	 */
	public static function get_registered_scripts(): array {
		$wp_scripts = wp_scripts();
		$scripts    = [];

		foreach ( $wp_scripts->registered as $handle => $script ) {
			if ( empty( $script->src ) ) {
				continue;
			}

			// Skip WordPress core scripts.
			if ( str_starts_with( $script->src, '/wp-includes/' )
				|| str_starts_with( $script->src, '/wp-admin/' )
				|| str_contains( $script->src, 'wp-includes' )
				|| str_contains( $script->src, 'wp-admin' ) ) {
				continue;
			}

			// Skip our own plugin script.
			if ( str_contains( $script->src, 'ecopress' ) ) {
				continue;
			}

			$scripts[] = [
				'handle' => $handle,
				'src'    => $script->src,
			];
		}

		usort( $scripts, static fn( array $a, array $b ): int => strcmp( $a['handle'], $b['handle'] ) );

		return $scripts;
	}

	/**
	 * Save the list of disabled scripts for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $handles Script handles to disable.
	 */
	public static function save_disabled_scripts( int $post_id, array $handles ): void {
		$sanitized = array_map( 'sanitize_key', $handles );
		$sanitized = array_filter( $sanitized );
		$sanitized = array_values( $sanitized );

		if ( empty( $sanitized ) ) {
			delete_post_meta( $post_id, '_ecopress_disabled_scripts' );
		} else {
			update_post_meta( $post_id, '_ecopress_disabled_scripts', $sanitized );
		}

		EcoPress_Analyzer::invalidate_cache( $post_id );
	}
}
