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
	 * @return array{weight_kb: int, co2_g: float, grade: string, grade_color: string, resources: array, diagnostics: array}|WP_Error
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

		$report['resources']   = $resources['items'];
		$report['html_bytes']  = $html_weight;
		$report['diagnostics'] = self::run_diagnostics( $body, $url, $resources );

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

	/**
	 * Run eco-design diagnostics on the page HTML.
	 *
	 * Returns an array of diagnostic items, each with:
	 * - id: unique identifier
	 * - label: human-readable label
	 * - status: 'ok', 'warning', 'error'
	 * - value: current measured value
	 * - detail: explanation string
	 *
	 * @param string $html      The full HTML body.
	 * @param string $page_url  The page URL.
	 * @param array  $resources Parsed resources from parse_resources().
	 * @return array<array{id: string, label: string, status: string, value: mixed, detail: string}>
	 */
	private static function run_diagnostics( string $html, string $page_url, array $resources ): array {
		$diagnostics = [];

		$diagnostics[] = self::diag_dom_complexity( $html );
		$diagnostics[] = self::diag_http_requests( $resources );
		$diagnostics[] = self::diag_third_party_domains( $html, $page_url );
		$diagnostics[] = self::diag_images_no_dimensions( $html );
		$diagnostics[] = self::diag_non_optimized_images( $resources );
		$diagnostics[] = self::diag_inline_css( $html );
		$diagnostics[] = self::diag_inline_js( $html );
		$diagnostics[] = self::diag_web_fonts( $html );
		$diagnostics[] = self::diag_social_iframes( $html );
		$diagnostics[] = self::diag_render_blocking( $html );

		return $diagnostics;
	}

	/**
	 * Diagnostic: DOM complexity (number of HTML elements).
	 *
	 * @param string $html HTML body.
	 * @return array Diagnostic item.
	 */
	private static function diag_dom_complexity( string $html ): array {
		$count = preg_match_all( '/<[a-z][a-z0-9]*[\s>]/i', $html );
		$count = $count ?: 0;

		if ( $count <= 500 ) {
			$status = 'ok';
			$detail = __( 'Complexité DOM correcte.', 'ecopress-auditor' );
		} elseif ( $count <= 1500 ) {
			$status = 'warning';
			$detail = __( 'Complexité DOM élevée. Simplifiez la structure HTML.', 'ecopress-auditor' );
		} else {
			$status = 'error';
			$detail = __( 'DOM très complexe. Réduisez le nombre d\'éléments HTML.', 'ecopress-auditor' );
		}

		return [
			'id'     => 'dom_complexity',
			'label'  => __( 'Complexité DOM', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $count,
			'detail' => $detail,
		];
	}

	/**
	 * Diagnostic: total number of HTTP requests.
	 *
	 * @param array $resources Parsed resources.
	 * @return array Diagnostic item.
	 */
	private static function diag_http_requests( array $resources ): array {
		$count = count( $resources['items'] );

		if ( $count <= 20 ) {
			$status = 'ok';
			$detail = __( 'Nombre de requêtes HTTP correct.', 'ecopress-auditor' );
		} elseif ( $count <= 40 ) {
			$status = 'warning';
			$detail = __( 'Nombre de requêtes HTTP élevé. Combinez ou supprimez des ressources.', 'ecopress-auditor' );
		} else {
			$status = 'error';
			$detail = __( 'Trop de requêtes HTTP. Chaque requête alourdit le bilan carbone.', 'ecopress-auditor' );
		}

		return [
			'id'     => 'http_requests',
			'label'  => __( 'Requêtes HTTP', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $count,
			'detail' => $detail,
		];
	}

	/**
	 * Diagnostic: third-party domains loaded on the page.
	 *
	 * @param string $html     HTML body.
	 * @param string $page_url Page URL for determining first-party domain.
	 * @return array Diagnostic item.
	 */
	private static function diag_third_party_domains( string $html, string $page_url ): array {
		$parsed_base = wp_parse_url( $page_url );
		$base_host   = $parsed_base['host'] ?? '';

		$domains = [];
		// Collect all src and href URLs.
		if ( preg_match_all( '/(?:src|href)=["\']https?:\/\/([^"\'\/]+)/i', $html, $matches ) ) {
			foreach ( $matches[1] as $host ) {
				$host = strtolower( $host );
				if ( $host !== $base_host && ! str_ends_with( $host, '.' . $base_host ) ) {
					$domains[ $host ] = true;
				}
			}
		}

		$count = count( $domains );
		if ( $count <= 2 ) {
			$status = 'ok';
			$detail = __( 'Peu de dépendances externes.', 'ecopress-auditor' );
		} elseif ( $count <= 5 ) {
			$status = 'warning';
			$detail = sprintf(
				/* translators: %s: comma-separated list of domains */
				__( 'Domaines tiers détectés : %s', 'ecopress-auditor' ),
				implode( ', ', array_keys( $domains ) )
			);
		} else {
			$status = 'error';
			$detail = sprintf(
				/* translators: %d: number of third-party domains */
				__( 'Trop de domaines tiers (%d). Chacun ajoute des résolutions DNS et du poids.', 'ecopress-auditor' ),
				$count
			);
		}

		return [
			'id'     => 'third_party',
			'label'  => __( 'Domaines tiers', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $count,
			'detail' => $detail,
		];
	}

	/**
	 * Diagnostic: images without width/height attributes (causes CLS).
	 *
	 * @param string $html HTML body.
	 * @return array Diagnostic item.
	 */
	private static function diag_images_no_dimensions( string $html ): array {
		$total    = preg_match_all( '/<img\b[^>]*>/i', $html, $img_tags );
		$missing  = 0;

		if ( $total ) {
			foreach ( $img_tags[0] as $tag ) {
				$has_width  = preg_match( '/\bwidth\s*=/i', $tag );
				$has_height = preg_match( '/\bheight\s*=/i', $tag );
				if ( ! $has_width || ! $has_height ) {
					++$missing;
				}
			}
		}

		if ( 0 === $missing ) {
			$status = 'ok';
			$detail = __( 'Toutes les images ont des dimensions explicites.', 'ecopress-auditor' );
		} else {
			$status = 'warning';
			$detail = sprintf(
				/* translators: %d: number of images missing dimensions */
				__( '%d image(s) sans attributs width/height. Cela provoque des décalages de mise en page (CLS).', 'ecopress-auditor' ),
				$missing
			);
		}

		return [
			'id'     => 'img_no_dimensions',
			'label'  => __( 'Images sans dimensions', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $missing,
			'detail' => $detail,
		];
	}

	/**
	 * Diagnostic: images in non-optimized formats (JPEG/PNG/GIF instead of WebP/AVIF).
	 *
	 * @param array $resources Parsed resources.
	 * @return array Diagnostic item.
	 */
	private static function diag_non_optimized_images( array $resources ): array {
		$legacy = 0;
		$total  = 0;

		foreach ( $resources['items'] as $item ) {
			if ( 'image' !== $item['type'] ) {
				continue;
			}
			++$total;
			$url_lower = strtolower( $item['url'] );
			if ( preg_match( '/\.(jpe?g|png|gif|bmp|tiff?)(\?|$)/i', $url_lower ) ) {
				++$legacy;
			}
		}

		if ( 0 === $total || 0 === $legacy ) {
			$status = 'ok';
			$detail = __( 'Les images utilisent des formats modernes (WebP/AVIF).', 'ecopress-auditor' );
		} elseif ( $legacy <= 3 ) {
			$status = 'warning';
			$detail = sprintf(
				/* translators: %d: number of non-optimized images */
				__( '%d image(s) en format non optimisé (JPEG/PNG/GIF). Convertissez-les en WebP.', 'ecopress-auditor' ),
				$legacy
			);
		} else {
			$status = 'error';
			$detail = sprintf(
				/* translators: %d: number of non-optimized images */
				__( '%d image(s) en ancien format. La conversion en WebP/AVIF peut réduire le poids de 30-80%%.', 'ecopress-auditor' ),
				$legacy
			);
		}

		return [
			'id'     => 'legacy_formats',
			'label'  => __( 'Formats d\'image obsolètes', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $legacy,
			'detail' => $detail,
		];
	}

	/**
	 * Diagnostic: inline CSS weight.
	 *
	 * @param string $html HTML body.
	 * @return array Diagnostic item.
	 */
	private static function diag_inline_css( string $html ): array {
		$total_bytes = 0;
		if ( preg_match_all( '/<style[^>]*>(.*?)<\/style>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $css ) {
				$total_bytes += strlen( $css );
			}
		}

		$kb = round( $total_bytes / 1024, 1 );

		if ( $kb <= 5 ) {
			$status = 'ok';
			$detail = __( 'CSS inline minimal.', 'ecopress-auditor' );
		} elseif ( $kb <= 20 ) {
			$status = 'warning';
			$detail = sprintf(
				/* translators: %s: inline CSS size in KB */
				__( '%s Ko de CSS inline. Externalisez les styles pour bénéficier du cache navigateur.', 'ecopress-auditor' ),
				number_format_i18n( $kb, 1 )
			);
		} else {
			$status = 'error';
			$detail = sprintf(
				/* translators: %s: inline CSS size in KB */
				__( '%s Ko de CSS inline. Ce contenu n\'est pas mis en cache par le navigateur.', 'ecopress-auditor' ),
				number_format_i18n( $kb, 1 )
			);
		}

		return [
			'id'     => 'inline_css',
			'label'  => __( 'CSS inline', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $kb,
			'detail' => $detail,
		];
	}

	/**
	 * Diagnostic: inline JavaScript weight.
	 *
	 * @param string $html HTML body.
	 * @return array Diagnostic item.
	 */
	private static function diag_inline_js( string $html ): array {
		$total_bytes = 0;
		// Match <script> tags without src attribute (inline scripts).
		if ( preg_match_all( '/<script(?![^>]*\bsrc\s*=)[^>]*>(.*?)<\/script>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $js ) {
				$total_bytes += strlen( trim( $js ) );
			}
		}

		$kb = round( $total_bytes / 1024, 1 );

		if ( $kb <= 10 ) {
			$status = 'ok';
			$detail = __( 'JavaScript inline raisonnable.', 'ecopress-auditor' );
		} elseif ( $kb <= 30 ) {
			$status = 'warning';
			$detail = sprintf(
				/* translators: %s: inline JS size in KB */
				__( '%s Ko de JS inline. Externalisez les scripts pour bénéficier du cache.', 'ecopress-auditor' ),
				number_format_i18n( $kb, 1 )
			);
		} else {
			$status = 'error';
			$detail = sprintf(
				/* translators: %s: inline JS size in KB */
				__( '%s Ko de JS inline. Cela alourdit chaque chargement de page.', 'ecopress-auditor' ),
				number_format_i18n( $kb, 1 )
			);
		}

		return [
			'id'     => 'inline_js',
			'label'  => __( 'JavaScript inline', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $kb,
			'detail' => $detail,
		];
	}

	/**
	 * Diagnostic: web font files loaded.
	 *
	 * @param string $html HTML body.
	 * @return array Diagnostic item.
	 */
	private static function diag_web_fonts( string $html ): array {
		$font_count = 0;

		// Detect linked font CSS (Google Fonts, Adobe Fonts, etc.).
		$font_count += (int) preg_match_all(
			'/fonts\.googleapis\.com|fonts\.gstatic\.com|use\.typekit\.net/i',
			$html
		);

		// Detect @font-face declarations in inline styles.
		$font_count += (int) preg_match_all( '/@font-face/i', $html );

		// Detect direct .woff/.woff2/.ttf/.otf file references.
		$direct_fonts = (int) preg_match_all(
			'/["\'][^"\']*\.(woff2?|ttf|otf|eot)(\?[^"\']*)?["\']/i',
			$html
		);
		$font_count += $direct_fonts;

		if ( $font_count <= 2 ) {
			$status = 'ok';
			$detail = __( 'Chargement de polices maîtrisé.', 'ecopress-auditor' );
		} elseif ( $font_count <= 5 ) {
			$status = 'warning';
			$detail = sprintf(
				/* translators: %d: number of font resources */
				__( '%d ressources de polices détectées. Limitez les variantes de polices chargées.', 'ecopress-auditor' ),
				$font_count
			);
		} else {
			$status = 'error';
			$detail = sprintf(
				/* translators: %d: number of font resources */
				__( '%d ressources de polices détectées. Utilisez les polices système ou limitez à 2 variantes.', 'ecopress-auditor' ),
				$font_count
			);
		}

		return [
			'id'     => 'web_fonts',
			'label'  => __( 'Polices web', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $font_count,
			'detail' => $detail,
		];
	}

	/**
	 * Diagnostic: social/video iframes (YouTube, Vimeo, Twitter, Facebook...).
	 *
	 * @param string $html HTML body.
	 * @return array Diagnostic item.
	 */
	private static function diag_social_iframes( string $html ): array {
		$heavy_iframes = 0;

		if ( preg_match_all( '/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				if ( preg_match( '/youtube|vimeo|dailymotion|facebook|twitter|instagram|tiktok|spotify/i', $src ) ) {
					++$heavy_iframes;
				}
			}
		}

		if ( 0 === $heavy_iframes ) {
			$status = 'ok';
			$detail = __( 'Aucun iframe de média social/vidéo détecté.', 'ecopress-auditor' );
		} elseif ( $heavy_iframes <= 2 ) {
			$status = 'warning';
			$detail = sprintf(
				/* translators: %d: number of social/video iframes */
				__( '%d iframe(s) média lourd(s). Utilisez une façade (image cliquable) pour limiter le chargement.', 'ecopress-auditor' ),
				$heavy_iframes
			);
		} else {
			$status = 'error';
			$detail = sprintf(
				/* translators: %d: number of social/video iframes */
				__( '%d iframes médias lourds. Chaque iframe charge des centaines de Ko de scripts tiers.', 'ecopress-auditor' ),
				$heavy_iframes
			);
		}

		return [
			'id'     => 'social_iframes',
			'label'  => __( 'Iframes médias', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $heavy_iframes,
			'detail' => $detail,
		];
	}

	/**
	 * Diagnostic: render-blocking resources (CSS/JS in <head> without async/defer).
	 *
	 * @param string $html HTML body.
	 * @return array Diagnostic item.
	 */
	private static function diag_render_blocking( string $html ): array {
		$blocking = 0;

		// Extract head content.
		$head_content = '';
		if ( preg_match( '/<head[^>]*>(.*?)<\/head>/is', $html, $head_match ) ) {
			$head_content = $head_match[1];
		}

		if ( ! empty( $head_content ) ) {
			// Count CSS <link> in head (all are render-blocking by default unless media="print" or similar).
			$blocking += (int) preg_match_all(
				'/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i',
				$head_content
			);

			// Count <script> in head without async or defer.
			if ( preg_match_all( '/<script[^>]+src=["\'][^"\']+["\'][^>]*>/i', $head_content, $script_matches ) ) {
				foreach ( $script_matches[0] as $tag ) {
					if ( ! preg_match( '/\b(async|defer)\b/i', $tag ) ) {
						++$blocking;
					}
				}
			}
		}

		if ( $blocking <= 3 ) {
			$status = 'ok';
			$detail = __( 'Peu de ressources bloquant le rendu.', 'ecopress-auditor' );
		} elseif ( $blocking <= 8 ) {
			$status = 'warning';
			$detail = sprintf(
				/* translators: %d: number of render-blocking resources */
				__( '%d ressource(s) bloquant le rendu dans le <head>. Ajoutez defer/async aux scripts.', 'ecopress-auditor' ),
				$blocking
			);
		} else {
			$status = 'error';
			$detail = sprintf(
				/* translators: %d: number of render-blocking resources */
				__( '%d ressource(s) bloquent le rendu. Le First Contentful Paint est fortement impacté.', 'ecopress-auditor' ),
				$blocking
			);
		}

		return [
			'id'     => 'render_blocking',
			'label'  => __( 'Ressources bloquantes', 'ecopress-auditor' ),
			'status' => $status,
			'value'  => $blocking,
			'detail' => $detail,
		];
	}
}
