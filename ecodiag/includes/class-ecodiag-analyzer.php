<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Page Analyzer — Fetches a page and extracts all metrics.
 */
class EcoDiag_Analyzer {

    /**
     * Run a full audit on a post.
     *
     * @param int  $post_id Post ID.
     * @param bool $force   Bypass cache.
     * @return array|WP_Error Audit results.
     */
    public static function audit_post( $post_id, $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( 'ecodiag_audit_' . $post_id );
            if ( $cached ) {
                return $cached;
            }
        }

        $url = get_permalink( $post_id );
        if ( ! $url ) {
            return new WP_Error( 'no_url', __( 'Impossible de déterminer l\'URL de ce contenu.', 'ecodiag' ) );
        }

        $result = self::analyze_url( $url );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Add content-specific diagnostics
        $post = get_post( $post_id );
        $result['content_diagnostics'] = self::diagnose_content( $post );
        $result['post_id'] = $post_id;

        // Calculate score
        $result['score'] = EcoDiag_Scoring::calculate( array(
            'page_weight' => $result['total_weight'],
            'requests'    => $result['requests_count'],
            'dom_size'    => $result['dom_size'],
            'js_count'    => $result['js_count'],
            'css_count'   => $result['css_count'],
            'img_issues'  => $result['img_issues_count'],
        ) );
        $result['grade'] = EcoDiag_Scoring::grade( $result['score'] );
        $result['color'] = EcoDiag_Scoring::hex_color( $result['score'] );

        // Cache for 24 hours
        set_transient( 'ecodiag_audit_' . $post_id, $result, DAY_IN_SECONDS );

        // Save to history
        EcoDiag_History::save( 'post', $post_id, $result );

        return $result;
    }

    /**
     * Analyze a URL and extract all metrics.
     *
     * @param string $url URL to analyze.
     * @return array|WP_Error Analysis results.
     */
    public static function analyze_url( $url ) {
        // Forward logged-in cookies for internal URLs so the analyzer sees the same page
        $cookies = array();
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $url_host  = wp_parse_url( $url, PHP_URL_HOST );
        if ( $url_host === $site_host && is_user_logged_in() ) {
            foreach ( $_COOKIE as $name => $value ) {
                if ( strpos( $name, 'wordpress_logged_in' ) === 0 ) {
                    $cookies[] = new WP_Http_Cookie( array( 'name' => $name, 'value' => $value ) );
                }
            }
        }

        $response = wp_remote_get( $url, array(
            'timeout'    => 30,
            'sslverify'  => false,
            'user-agent' => 'EcoDiag/' . ECODIAG_VERSION,
            'cookies'    => $cookies,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) {
            return new WP_Error( 'empty_response', __( 'Réponse vide du serveur.', 'ecodiag' ) );
        }

        $html_weight = strlen( $html );

        // Parse resources
        $css_files  = self::extract_css( $html, $url );
        $js_files   = self::extract_js( $html, $url );
        $images     = self::extract_images( $html, $url );
        $iframes    = self::extract_iframes( $html );
        $fonts      = self::extract_fonts( $html, $url );

        // Get resource sizes
        $css_resources  = self::measure_resources( $css_files );
        $js_resources   = self::measure_resources( $js_files );
        $img_resources  = self::measure_resources( wp_list_pluck( $images, 'src' ) );
        $font_resources = self::measure_resources( $fonts );

        $css_weight   = array_sum( wp_list_pluck( $css_resources, 'size' ) );
        $js_weight    = array_sum( wp_list_pluck( $js_resources, 'size' ) );
        $img_weight   = array_sum( wp_list_pluck( $img_resources, 'size' ) );
        $font_weight  = array_sum( wp_list_pluck( $font_resources, 'size' ) );
        $total_weight = $html_weight + $css_weight + $js_weight + $img_weight + $font_weight;

        // DOM analysis
        $dom_size = self::count_dom_nodes( $html );

        // Image issues
        $img_issues = self::analyze_images( $images, $img_resources );
        $img_total_count = count( $images );
        $img_issues_count = count( array_filter( $img_issues, function( $i ) {
            return ! empty( $i['issues'] );
        } ) );

        // Video/embed detection
        $embeds = self::detect_embeds( $iframes, $html );

        // External resources
        $external_domains = self::detect_external_domains( $css_files, $js_files, $images, $fonts, $url );

        // Tracking scripts
        $tracking = self::detect_tracking_scripts( $js_files, $html );

        // Render-blocking detection
        $render_blocking = self::detect_render_blocking( $html );

        $requests_count = count( $css_files ) + count( $js_files ) + count( $images ) + count( $fonts ) + count( $iframes );

        return array(
            'url'             => $url,
            'html_weight'     => $html_weight,
            'css_weight'      => $css_weight,
            'js_weight'       => $js_weight,
            'img_weight'      => $img_weight,
            'font_weight'     => $font_weight,
            'total_weight'    => $total_weight,
            'requests_count'  => $requests_count,
            'dom_size'        => $dom_size,
            'js_count'        => count( $js_files ),
            'css_count'       => count( $css_files ),
            'img_total_count' => $img_total_count,
            'img_issues_count'=> $img_issues_count,
            'css_resources'   => $css_resources,
            'js_resources'    => $js_resources,
            'img_resources'   => $img_resources,
            'font_resources'  => $font_resources,
            'images'          => $img_issues,
            'iframes'         => $iframes,
            'embeds'          => $embeds,
            'external_domains'=> $external_domains,
            'tracking'        => $tracking,
            'render_blocking' => $render_blocking,
            'weight_breakdown'=> array(
                'html'   => $html_weight,
                'css'    => $css_weight,
                'js'     => $js_weight,
                'images' => $img_weight,
                'fonts'  => $font_weight,
            ),
        );
    }

    /**
     * Content-specific diagnostics.
     */
    public static function diagnose_content( $post ) {
        $diagnostics = array();

        // P-CONT-01: Revisions count
        $revisions = wp_get_post_revisions( $post->ID, array( 'fields' => 'ids' ) );
        $revision_count = count( $revisions );
        $diagnostics['revisions'] = array(
            'ref'   => 'P-CONT-01',
            'count' => $revision_count,
            'estimated_weight' => $revision_count * strlen( $post->post_content ),
            'status' => $revision_count > 10 ? 'red' : ( $revision_count > 5 ? 'orange' : 'green' ),
        );

        // P-CONT-02: Orphaned postmeta
        global $wpdb;
        $orphaned_meta = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE '\_%%' AND meta_key NOT IN ('_edit_lock','_edit_last','_wp_page_template','_thumbnail_id','_wp_old_slug')",
            $post->ID
        ) );
        $diagnostics['orphaned_meta'] = array(
            'ref'   => 'P-CONT-02',
            'count' => (int) $orphaned_meta,
            'status' => $orphaned_meta > 20 ? 'red' : ( $orphaned_meta > 10 ? 'orange' : 'green' ),
        );

        // P-CONT-03: Raw HTML weight
        $content_weight = strlen( $post->post_content );
        $diagnostics['content_weight'] = array(
            'ref'    => 'P-CONT-03',
            'weight' => $content_weight,
            'formatted' => EcoDiag_Scoring::format_size( $content_weight ),
            'status' => $content_weight > 102400 ? 'red' : ( $content_weight > 51200 ? 'orange' : 'green' ),
        );

        // P-CONT-04: Gutenberg block count
        $blocks = parse_blocks( $post->post_content );
        $block_count = self::count_blocks( $blocks );
        $diagnostics['block_count'] = array(
            'ref'   => 'P-CONT-04',
            'count' => $block_count,
            'status' => 'info',
        );

        // P-CONT-05: Broken/obsolete shortcodes
        $broken_shortcodes = self::detect_broken_shortcodes( $post->post_content );
        $diagnostics['broken_shortcodes'] = array(
            'ref'   => 'P-CONT-05',
            'items' => $broken_shortcodes,
            'count' => count( $broken_shortcodes ),
            'status' => count( $broken_shortcodes ) > 0 ? 'orange' : 'green',
        );

        return $diagnostics;
    }

    /**
     * Extract CSS file URLs from HTML.
     */
    private static function extract_css( $html, $base_url ) {
        $files = array();
        if ( preg_match_all( '/<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $m ) ) {
            foreach ( $m[1] as $href ) {
                $files[] = self::resolve_url( $href, $base_url );
            }
        }
        if ( preg_match_all( '/<link[^>]+href=["\']([^"\']+)["\'][^>]*rel=["\']stylesheet["\'][^>]*>/i', $html, $m ) ) {
            foreach ( $m[1] as $href ) {
                $resolved = self::resolve_url( $href, $base_url );
                if ( ! in_array( $resolved, $files, true ) ) {
                    $files[] = $resolved;
                }
            }
        }
        return $files;
    }

    /**
     * Extract JS file URLs from HTML.
     */
    private static function extract_js( $html, $base_url ) {
        $files = array();
        if ( preg_match_all( '/<script[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $m ) ) {
            foreach ( $m[1] as $src ) {
                $files[] = self::resolve_url( $src, $base_url );
            }
        }
        return $files;
    }

    /**
     * Extract image data from HTML.
     * Supports standard <img>, <picture>/<source>, data-src lazy loading, and self-closing tags.
     */
    private static function extract_images( $html, $base_url ) {
        $images = array();
        $seen_srcs = array();

        // Match standard <img> tags (with or without space, self-closing)
        if ( preg_match_all( '/<img[\s\/][^>]*\/?>/i', $html, $m ) ) {
            foreach ( $m[0] as $tag ) {
                $img = self::parse_img_tag( $tag, $base_url );
                if ( $img && $img['src'] && ! isset( $seen_srcs[ $img['src'] ] ) ) {
                    $seen_srcs[ $img['src'] ] = true;
                    $images[] = $img;
                }
            }
        }

        // Match <picture> elements — extract the <img> fallback and check for WebP/AVIF <source>
        if ( preg_match_all( '/<picture[^>]*>(.*?)<\/picture>/is', $html, $pm ) ) {
            foreach ( $pm[1] as $picture_content ) {
                if ( preg_match( '/<img[\s\/][^>]*\/?>/i', $picture_content, $im ) ) {
                    $img = self::parse_img_tag( $im[0], $base_url );
                    if ( ! $img || ! $img['src'] ) {
                        continue;
                    }
                    $has_modern = (bool) preg_match( '/type=["\']image\/(webp|avif)["\']/i', $picture_content );
                    if ( isset( $seen_srcs[ $img['src'] ] ) ) {
                        // Update has_modern_source on the already-seen entry
                        if ( $has_modern ) {
                            foreach ( $images as &$existing ) {
                                if ( $existing['src'] === $img['src'] ) {
                                    $existing['has_modern_source'] = true;
                                    break;
                                }
                            }
                            unset( $existing );
                        }
                    } else {
                        $img['has_modern_source'] = $has_modern;
                        $seen_srcs[ $img['src'] ] = true;
                        $images[] = $img;
                    }
                }
            }
        }

        // Detect lazy-loaded images using data-src (common JS lazy-load pattern)
        if ( preg_match_all( '/<img[^>]+data-src=["\']([^"\']+)["\'][^>]*>/i', $html, $dm ) ) {
            foreach ( $dm[0] as $idx => $tag ) {
                $data_src = self::resolve_url( $dm[1][ $idx ], $base_url );
                if ( ! isset( $seen_srcs[ $data_src ] ) ) {
                    $img = self::parse_img_tag( $tag, $base_url );
                    if ( $img ) {
                        $img['src'] = $data_src;
                        $img['has_lazy'] = true;
                        $seen_srcs[ $data_src ] = true;
                        $images[] = $img;
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Parse a single <img> tag into structured data.
     */
    private static function parse_img_tag( $tag, $base_url ) {
        $img = array(
            'tag'        => $tag,
            'src'        => '',
            'has_lazy'   => (bool) preg_match( '/loading\s*=\s*["\']lazy["\']/i', $tag ),
            'has_decoding' => (bool) preg_match( '/decoding\s*=\s*["\']async["\']/i', $tag ),
            'has_width'  => (bool) preg_match( '/\swidth\s*=\s*["\']?\d+/i', $tag ),
            'has_height' => (bool) preg_match( '/\sheight\s*=\s*["\']?\d+/i', $tag ),
            'has_alt'    => (bool) preg_match( '/\salt\s*=\s*["\']/i', $tag ),
            'alt_empty'  => (bool) preg_match( '/\salt\s*=\s*["\']["\']/', $tag ),
            'has_srcset' => (bool) preg_match( '/\ssrcset\s*=\s*["\']/i', $tag ),
            'has_modern_source' => false,
            'width'  => 0,
            'height' => 0,
        );
        // Match src but not data-src
        if ( preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $tag, $s ) ) {
            $img['src'] = self::resolve_url( $s[1], $base_url );
        }
        if ( preg_match( '/\swidth=["\']?(\d+)/i', $tag, $w ) ) {
            $img['width'] = (int) $w[1];
        }
        if ( preg_match( '/\sheight=["\']?(\d+)/i', $tag, $h ) ) {
            $img['height'] = (int) $h[1];
        }
        return $img;
    }

    /**
     * Extract iframe tags from HTML.
     */
    private static function extract_iframes( $html ) {
        $iframes = array();
        if ( preg_match_all( '/<iframe\s[^>]*>/i', $html, $m ) ) {
            foreach ( $m[0] as $tag ) {
                $iframe = array(
                    'tag' => $tag,
                    'src' => '',
                    'has_lazy' => (bool) preg_match( '/loading\s*=\s*["\']lazy["\']/i', $tag ),
                );
                if ( preg_match( '/src=["\']([^"\']+)["\']/i', $tag, $s ) ) {
                    $iframe['src'] = $s[1];
                }
                $iframes[] = $iframe;
            }
        }
        return $iframes;
    }

    /**
     * Extract font file URLs from HTML (link preloads and @font-face in inline styles).
     */
    private static function extract_fonts( $html, $base_url ) {
        $fonts = array();
        // Preloaded fonts
        if ( preg_match_all( '/<link[^>]+as=["\']font["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m ) ) {
            foreach ( $m[1] as $href ) {
                $fonts[] = self::resolve_url( $href, $base_url );
            }
        }
        // @font-face in inline styles
        if ( preg_match_all( '/url\(["\']?([^"\')\s]+\.(?:woff2?|ttf|otf|eot))["\']?\)/i', $html, $m ) ) {
            foreach ( $m[1] as $href ) {
                $resolved = self::resolve_url( $href, $base_url );
                if ( ! in_array( $resolved, $fonts, true ) ) {
                    $fonts[] = $resolved;
                }
            }
        }
        return $fonts;
    }

    /**
     * Measure resource sizes via HEAD requests.
     */
    private static function measure_resources( $urls, $max = 50 ) {
        $resources = array();
        $count = 0;
        foreach ( $urls as $url ) {
            $size = 0;
            if ( $count < $max ) {
                $response = wp_remote_head( $url, array(
                    'timeout'   => 3,
                    'sslverify' => false,
                ) );
                if ( ! is_wp_error( $response ) ) {
                    $cl = wp_remote_retrieve_header( $response, 'content-length' );
                    if ( $cl ) {
                        $size = (int) $cl;
                    }
                }
            }
            $resources[] = array(
                'url'  => $url,
                'size' => $size,
                'name' => basename( wp_parse_url( $url, PHP_URL_PATH ) ?: $url ),
            );
            $count++;
        }
        // Sort by size descending
        usort( $resources, function( $a, $b ) {
            return $b['size'] - $a['size'];
        } );
        return $resources;
    }

    /**
     * Count DOM nodes in HTML.
     */
    private static function count_dom_nodes( $html ) {
        $count = 0;
        // Use regex to count opening tags (fast approximation)
        $count = preg_match_all( '/<[a-z][^>]*>/i', $html, $m );
        return $count ?: 0;
    }

    /**
     * Analyze image issues (P-IMG diagnostics).
     */
    /**
     * Normalize URL for map lookups: strip query string and fragment.
     */
    private static function normalize_url( $url ) {
        $parsed = wp_parse_url( $url );
        $scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : 'https://';
        $host   = isset( $parsed['host'] ) ? $parsed['host'] : '';
        $path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
        return $scheme . $host . $path;
    }

    private static function analyze_images( $images, $img_resources ) {
        $max_weight = (int) get_option( 'ecodiag_image_max_weight', 200 ) * 1024;

        // Build resource map with normalized URLs for reliable lookups
        $resource_map = array();
        foreach ( $img_resources as $r ) {
            $resource_map[ self::normalize_url( $r['url'] ) ] = $r['size'];
            // Also keep the raw URL key as fallback
            $resource_map[ $r['url'] ] = $r['size'];
        }

        $results = array();
        foreach ( $images as $img ) {
            $issues = array();
            $src = $img['src'];
            $ext = strtolower( pathinfo( wp_parse_url( $src, PHP_URL_PATH ) ?: '', PATHINFO_EXTENSION ) );
            $has_modern_source = ! empty( $img['has_modern_source'] );

            // P-IMG-01: Not WebP/AVIF — skip if <picture> provides a modern <source>
            if ( ! in_array( $ext, array( 'webp', 'avif', 'svg' ), true ) && ! $has_modern_source ) {
                $issues[] = array( 'ref' => 'P-IMG-01', 'label' => __( 'Non converti en WebP/AVIF', 'ecodiag' ) );
            }

            // P-IMG-02: No lazy loading
            if ( ! $img['has_lazy'] ) {
                $issues[] = array( 'ref' => 'P-IMG-02', 'label' => __( 'Pas de loading="lazy"', 'ecodiag' ) );
            }

            // P-IMG-03: No decoding async
            if ( ! $img['has_decoding'] ) {
                $issues[] = array( 'ref' => 'P-IMG-03', 'label' => __( 'Pas de decoding="async"', 'ecodiag' ) );
            }

            // P-IMG-05: No width/height
            if ( ! $img['has_width'] || ! $img['has_height'] ) {
                $issues[] = array( 'ref' => 'P-IMG-05', 'label' => __( 'Attributs width/height manquants', 'ecodiag' ) );
            }

            // P-IMG-06: Too heavy — try normalized URL then raw
            $normalized = self::normalize_url( $src );
            $size = isset( $resource_map[ $normalized ] ) ? $resource_map[ $normalized ] : ( isset( $resource_map[ $src ] ) ? $resource_map[ $src ] : 0 );
            if ( $size > $max_weight ) {
                $issues[] = array(
                    'ref'   => 'P-IMG-06',
                    'label' => sprintf( __( 'Image trop lourde : %s', 'ecodiag' ), EcoDiag_Scoring::format_size( $size ) ),
                );
            }

            // P-IMG-08: Decorative image without alt=""
            if ( ! $img['has_alt'] ) {
                $issues[] = array( 'ref' => 'P-IMG-08', 'label' => __( 'Attribut alt manquant', 'ecodiag' ) );
            }

            // P-IMG-09: No srcset
            if ( ! $img['has_srcset'] && ! in_array( $ext, array( 'svg', 'gif' ), true ) ) {
                $issues[] = array( 'ref' => 'P-IMG-09', 'label' => __( 'Attribut srcset manquant', 'ecodiag' ) );
            }

            $results[] = array(
                'src'    => $src,
                'size'   => $size,
                'issues' => $issues,
            );
        }
        return $results;
    }

    /**
     * Detect video embeds (P-VID diagnostics).
     */
    private static function detect_embeds( $iframes, $html ) {
        $embeds = array();
        foreach ( $iframes as $iframe ) {
            $src = $iframe['src'];
            $type = 'unknown';
            if ( strpos( $src, 'youtube.com' ) !== false || strpos( $src, 'youtu.be' ) !== false ) {
                $type = 'youtube';
            } elseif ( strpos( $src, 'vimeo.com' ) !== false ) {
                $type = 'vimeo';
            } elseif ( strpos( $src, 'dailymotion.com' ) !== false ) {
                $type = 'dailymotion';
            }

            $issues = array();
            if ( in_array( $type, array( 'youtube', 'vimeo', 'dailymotion' ), true ) ) {
                $issues[] = array( 'ref' => 'P-VID-01', 'label' => sprintf( __( 'Embed %s détecté (iframe lourde)', 'ecodiag' ), $type ) );
            }
            if ( ! $iframe['has_lazy'] ) {
                $issues[] = array( 'ref' => 'P-VID-03', 'label' => __( 'Iframe sans loading="lazy"', 'ecodiag' ) );
            }

            $embeds[] = array(
                'src'    => $src,
                'type'   => $type,
                'issues' => $issues,
            );
        }

        // Check for autoplay videos
        if ( preg_match_all( '/<video[^>]*autoplay[^>]*>/i', $html, $m ) ) {
            foreach ( $m[0] as $tag ) {
                $embeds[] = array(
                    'src'    => '',
                    'type'   => 'video_autoplay',
                    'issues' => array(
                        array( 'ref' => 'P-VID-02', 'label' => __( 'Vidéo en autoplay détectée', 'ecodiag' ) ),
                    ),
                );
            }
        }

        return $embeds;
    }

    /**
     * Detect external domains.
     */
    private static function detect_external_domains( $css, $js, $images, $fonts, $page_url ) {
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $domains = array();
        $all_urls = array_merge( $css, $js, wp_list_pluck( $images, 'src' ), $fonts );

        foreach ( $all_urls as $url ) {
            $host = wp_parse_url( $url, PHP_URL_HOST );
            if ( $host && $host !== $site_host && ! isset( $domains[ $host ] ) ) {
                $domains[ $host ] = 0;
            }
            if ( $host && $host !== $site_host ) {
                $domains[ $host ]++;
            }
        }

        return $domains;
    }

    /**
     * Detect tracking scripts.
     */
    private static function detect_tracking_scripts( $js_files, $html ) {
        $trackers = array();
        $patterns = array(
            'google_analytics' => array( 'google-analytics.com', 'googletagmanager.com', 'gtag/js' ),
            'facebook_pixel'   => array( 'connect.facebook.net', 'fbevents.js', 'fbq(' ),
            'hotjar'           => array( 'static.hotjar.com', 'hotjar.com' ),
            'plausible'        => array( 'plausible.io' ),
            'matomo'           => array( 'matomo.', 'piwik.' ),
            'hubspot'          => array( 'js.hs-scripts.com', 'hs-analytics' ),
            'linkedin'         => array( 'snap.licdn.com', 'linkedin.com/px' ),
            'twitter'          => array( 'static.ads-twitter.com', 'platform.twitter.com' ),
            'tiktok'           => array( 'analytics.tiktok.com' ),
        );

        $all_content = implode( ' ', $js_files ) . ' ' . $html;

        foreach ( $patterns as $name => $signatures ) {
            foreach ( $signatures as $sig ) {
                if ( stripos( $all_content, $sig ) !== false ) {
                    $trackers[] = $name;
                    break;
                }
            }
        }

        return array_unique( $trackers );
    }

    /**
     * Detect render-blocking resources.
     */
    private static function detect_render_blocking( $html ) {
        $blocking = array( 'css' => array(), 'js' => array() );

        // CSS without media=print or disabled
        if ( preg_match_all( '/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $m ) ) {
            foreach ( $m[0] as $tag ) {
                if ( ! preg_match( '/media=["\']print["\']/i', $tag ) ) {
                    if ( preg_match( '/href=["\']([^"\']+)["\']/i', $tag, $h ) ) {
                        $blocking['css'][] = $h[1];
                    }
                }
            }
        }

        // JS without defer or async
        if ( preg_match_all( '/<script[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $m, PREG_SET_ORDER ) ) {
            foreach ( $m as $match ) {
                $tag = $match[0];
                if ( ! preg_match( '/\b(defer|async)\b/i', $tag ) ) {
                    $blocking['js'][] = $match[1];
                }
            }
        }

        return $blocking;
    }

    /**
     * Count Gutenberg blocks recursively.
     */
    private static function count_blocks( $blocks ) {
        $count = 0;
        foreach ( $blocks as $block ) {
            if ( ! empty( $block['blockName'] ) ) {
                $count++;
            }
            if ( ! empty( $block['innerBlocks'] ) ) {
                $count += self::count_blocks( $block['innerBlocks'] );
            }
        }
        return $count;
    }

    /**
     * Detect broken shortcodes.
     */
    private static function detect_broken_shortcodes( $content ) {
        global $shortcode_tags;
        $broken = array();
        if ( preg_match_all( '/\[([a-zA-Z0-9_-]+)/', $content, $m ) ) {
            foreach ( $m[1] as $tag ) {
                if ( ! isset( $shortcode_tags[ $tag ] ) ) {
                    $broken[] = $tag;
                }
            }
        }
        return array_unique( $broken );
    }

    /**
     * Resolve a relative URL to absolute.
     */
    private static function resolve_url( $url, $base ) {
        if ( strpos( $url, '//' ) === 0 ) {
            return 'https:' . $url;
        }
        if ( strpos( $url, 'http' ) === 0 ) {
            return $url;
        }
        $parsed = wp_parse_url( $base );
        $scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] : 'https';
        $host   = isset( $parsed['host'] ) ? $parsed['host'] : '';
        if ( strpos( $url, '/' ) === 0 ) {
            return $scheme . '://' . $host . $url;
        }
        $path = isset( $parsed['path'] ) ? dirname( $parsed['path'] ) : '';
        return $scheme . '://' . $host . rtrim( $path, '/' ) . '/' . $url;
    }
}
