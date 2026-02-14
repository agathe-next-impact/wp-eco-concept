<?php
/**
 * Page weight analyzer using wp_remote_get.
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches a page and analyzes its weight and resource breakdown.
 */
final class EcoPress_Analyzer {

	/** Transient cache duration: 24 hours. */
	private const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Run the full audit for a given post ID.
	 * Results are cached for 24 h via the Transients API.
	 *
	 * @param int  $post_id  The post to audit.
	 * @param bool $force    Bypass cache when true.
	 * @return array{weight_kb: int, co2_g: float, grade: string, grade_color: string, resources: array}|WP_Error
	 */
	public static function audit( int $post_id, bool $force = false ): array|\WP_Error {
		$transient_key = 'ecopress_audit_' . $post_id;

		if ( ! $force ) {
			$cached = get_transient( $transient_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return new \WP_Error( 'ecopress_no_url', __( 'Impossible de déterminer l\'URL de cet article.', 'ecopress-auditor' ) );
		}

		$response = wp_remote_get( $url, [
			'timeout'    => 30,
			'sslverify'  => false,
			'user-agent' => 'EcoPress-Auditor/' . ECOPRESS_VERSION,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new \WP_Error( 'ecopress_empty', __( 'La page est vide.', 'ecopress-auditor' ) );
		}

		$html_weight = strlen( $body );
		$resources   = self::parse_resources( $body, $url );
		$total_kb    = (int) round( ( $html_weight + $resources['total_bytes'] ) / 1024 );
		$report      = EcoPress_Calculator::build_report( $total_kb );

		$report['resources']  = $resources['items'];
		$report['html_bytes'] = $html_weight;

		set_transient( $transient_key, $report, self::CACHE_TTL );

		return $report;
	}

	/**
	 * Extract and estimate sizes of external resources referenced in the HTML.
	 *
	 * @param string $html     The full HTML body.
	 * @param string $page_url The page URL for resolving relative paths.
	 * @return array{total_bytes: int, items: array}
	 */
	private static function parse_resources( string $html, string $page_url ): array {
		$items       = [];
		$total_bytes = 0;

		// CSS files.
		if ( preg_match_all( '/<link[^>]+href=["\']([^"\']+\.css[^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				$size = self::estimate_resource_size( $src, $page_url );
				$items[] = [
					'url'  => $src,
					'type' => 'css',
					'size' => $size,
				];
				$total_bytes += $size;
			}
		}

		// JS files.
		if ( preg_match_all( '/<script[^>]+src=["\']([^"\']+\.js[^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				$size = self::estimate_resource_size( $src, $page_url );
				$items[] = [
					'url'  => $src,
					'type' => 'js',
					'size' => $size,
				];
				$total_bytes += $size;
			}
		}

		// Images.
		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				$size = self::estimate_resource_size( $src, $page_url );
				$items[] = [
					'url'  => $src,
					'type' => 'image',
					'size' => $size,
				];
				$total_bytes += $size;
			}
		}

		// Sort by size descending.
		usort( $items, static fn( array $a, array $b ): int => $b['size'] <=> $a['size'] );

		return [
			'total_bytes' => $total_bytes,
			'items'       => $items,
		];
	}

	/**
	 * Estimate the size of a remote resource via a HEAD request.
	 *
	 * @param string $src      Resource URL (absolute or relative).
	 * @param string $page_url Base page URL for resolving relative paths.
	 * @return int Estimated size in bytes.
	 */
	private static function estimate_resource_size( string $src, string $page_url ): int {
		$absolute = self::resolve_url( $src, $page_url );

		$response = wp_remote_head( $absolute, [
			'timeout'   => 5,
			'sslverify' => false,
		] );

		if ( is_wp_error( $response ) ) {
			return 0;
		}

		$length = wp_remote_retrieve_header( $response, 'content-length' );
		return $length ? (int) $length : 0;
	}

	/**
	 * Resolve a potentially relative URL against a base URL.
	 *
	 * @param string $src  The URL to resolve.
	 * @param string $base The base URL.
	 * @return string Absolute URL.
	 */
	private static function resolve_url( string $src, string $base ): string {
		if ( str_starts_with( $src, 'http' ) ) {
			return $src;
		}

		$parsed = wp_parse_url( $base );
		$scheme = $parsed['scheme'] ?? 'https';
		$host   = $parsed['host'] ?? '';

		if ( str_starts_with( $src, '//' ) ) {
			return $scheme . ':' . $src;
		}

		if ( str_starts_with( $src, '/' ) ) {
			return $scheme . '://' . $host . $src;
		}

		$path = dirname( $parsed['path'] ?? '/' );
		return $scheme . '://' . $host . $path . '/' . $src;
	}

	/**
	 * Invalidate cached audit for a post.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function invalidate_cache( int $post_id ): void {
		delete_transient( 'ecopress_audit_' . $post_id );
	}
}
