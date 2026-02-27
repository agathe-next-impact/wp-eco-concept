<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Global diagnostics for G-BDD, G-PLG, G-MED, G-EDIT, G-TRACK, G-REC.
 */
class EcoDiag_Diagnostics {

    /**
     * G-BDD: Database diagnostics.
     */
    public static function database( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( 'ecodiag_diag_database' );
            if ( $cached ) return $cached;
        }
        global $wpdb;

        // G-BDD-01: Total revisions
        $revisions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );
        $revision_weight = $wpdb->get_var(
            "SELECT SUM(LENGTH(post_content)) FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );

        // G-BDD-02: Expired transients
        $transients = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE '\\_transient\\_timeout\\_%'
             AND option_value < UNIX_TIMESTAMP()"
        );

        // G-BDD-03: Autoloaded suspicious options
        $autoload_options = $wpdb->get_results(
            "SELECT option_name, LENGTH(option_value) as size
             FROM {$wpdb->options}
             WHERE autoload = 'yes'
             AND LENGTH(option_value) > 10000
             ORDER BY size DESC
             LIMIT 20"
        );

        // G-BDD-04: Orphaned postmeta
        $orphaned_postmeta = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.ID IS NULL"
        );

        $orphaned_usermeta = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} um
             LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
             WHERE u.ID IS NULL"
        );

        $orphaned_commentmeta = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm
             LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
             WHERE c.comment_ID IS NULL"
        );

        // G-BDD-05: Tables to optimize
        $tables = $wpdb->get_results( "SHOW TABLE STATUS" );
        $tables_to_optimize = array();
        foreach ( $tables as $t ) {
            if ( isset( $t->Data_free ) && $t->Data_free > 0 ) {
                $tables_to_optimize[] = array(
                    'name'      => $t->Name,
                    'data_free' => (int) $t->Data_free,
                    'data_size' => (int) $t->Data_length,
                    'rows'      => (int) $t->Rows,
                );
            }
        }

        // G-BDD-06: Options from deleted plugins
        $active_plugins = get_option( 'active_plugins', array() );
        $active_slugs = array_map( function( $p ) { return dirname( $p ); }, $active_plugins );
        $suspect_options = $wpdb->get_results(
            "SELECT option_name, LENGTH(option_value) as size
             FROM {$wpdb->options}
             WHERE option_name NOT LIKE '\\_transient%'
             AND option_name NOT LIKE '\\_site\\_transient%'
             AND option_name NOT LIKE 'widget\\_%'
             AND option_name NOT LIKE 'ecodiag\\_%'
             ORDER BY size DESC
             LIMIT 50"
        );

        // G-BDD-07: Total DB size
        $db_size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = %s",
                DB_NAME
            )
        );

        $result = array(
            'revisions' => array(
                'ref'    => 'G-BDD-01',
                'count'  => (int) $revisions,
                'weight' => (int) $revision_weight,
                'formatted_weight' => EcoDiag_Scoring::format_size( (int) $revision_weight ),
                'status' => $revisions > 100 ? 'red' : ( $revisions > 20 ? 'orange' : 'green' ),
            ),
            'transients' => array(
                'ref'    => 'G-BDD-02',
                'count'  => (int) $transients,
                'status' => $transients > 50 ? 'red' : ( $transients > 10 ? 'orange' : 'green' ),
            ),
            'autoload_options' => array(
                'ref'   => 'G-BDD-03',
                'items' => $autoload_options,
                'count' => count( $autoload_options ),
                'status' => count( $autoload_options ) > 5 ? 'red' : ( count( $autoload_options ) > 0 ? 'orange' : 'green' ),
            ),
            'orphaned_meta' => array(
                'ref'   => 'G-BDD-04',
                'postmeta'    => (int) $orphaned_postmeta,
                'usermeta'    => (int) $orphaned_usermeta,
                'commentmeta' => (int) $orphaned_commentmeta,
                'total' => (int) $orphaned_postmeta + (int) $orphaned_usermeta + (int) $orphaned_commentmeta,
                'status' => ( $orphaned_postmeta + $orphaned_usermeta + $orphaned_commentmeta ) > 100 ? 'red' : ( ( $orphaned_postmeta + $orphaned_usermeta + $orphaned_commentmeta ) > 10 ? 'orange' : 'green' ),
            ),
            'tables_to_optimize' => array(
                'ref'   => 'G-BDD-05',
                'items' => $tables_to_optimize,
                'count' => count( $tables_to_optimize ),
                'status' => count( $tables_to_optimize ) > 5 ? 'orange' : 'green',
            ),
            'suspect_options' => array(
                'ref'   => 'G-BDD-06',
                'items' => $suspect_options,
                'count' => count( $suspect_options ),
            ),
            'db_size' => array(
                'ref'       => 'G-BDD-07',
                'size'      => (int) $db_size,
                'formatted' => EcoDiag_Scoring::format_size( (int) $db_size ),
            ),
        );
        set_transient( 'ecodiag_diag_database', $result, 5 * MINUTE_IN_SECONDS );
        return $result;
    }

    /**
     * G-PLG: Plugins diagnostics.
     */
    public static function plugins( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( 'ecodiag_diag_plugins' );
            if ( $cached ) return $cached;
        }
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );

        // G-PLG-01: Inactive plugins
        $inactive = array();
        foreach ( $all_plugins as $file => $data ) {
            if ( ! in_array( $file, $active_plugins, true ) ) {
                $slug = dirname( $file );
                $path = WP_PLUGIN_DIR . '/' . $slug;
                $size = 0;
                if ( is_dir( $path ) ) {
                    $size = self::dir_size( $path );
                }
                $inactive[] = array(
                    'file' => $file,
                    'name' => $data['Name'],
                    'size' => $size,
                    'formatted_size' => EcoDiag_Scoring::format_size( $size ),
                );
            }
        }

        // G-PLG-02: Inactive themes
        $all_themes    = wp_get_themes();
        $active_theme  = get_stylesheet();
        $parent_theme  = get_template();
        $default_theme = WP_DEFAULT_THEME;
        $inactive_themes = array();

        foreach ( $all_themes as $slug => $theme ) {
            if ( $slug === $active_theme || $slug === $parent_theme || $slug === $default_theme ) {
                continue;
            }
            $path = $theme->get_stylesheet_directory();
            $size = is_dir( $path ) ? self::dir_size( $path ) : 0;
            $inactive_themes[] = array(
                'slug' => $slug,
                'name' => $theme->get( 'Name' ),
                'size' => $size,
                'formatted_size' => EcoDiag_Scoring::format_size( $size ),
            );
        }

        // G-PLG-03: Active plugin asset weights
        $plugin_assets = array();
        foreach ( $active_plugins as $file ) {
            $slug = dirname( $file );
            if ( $slug === '.' ) $slug = basename( $file, '.php' );
            if ( $slug === 'ecodiag' ) continue;

            $data = $all_plugins[ $file ] ?? array();
            $path = WP_PLUGIN_DIR . '/' . $slug;
            $js_size = 0;
            $css_size = 0;

            if ( is_dir( $path ) ) {
                $js_size  = self::dir_size( $path, array( 'js' ) );
                $css_size = self::dir_size( $path, array( 'css' ) );
            }

            $plugin_assets[] = array(
                'slug'       => $slug,
                'name'       => $data['Name'] ?? $slug,
                'js_size'    => $js_size,
                'css_size'   => $css_size,
                'total_size' => $js_size + $css_size,
                'formatted'  => EcoDiag_Scoring::format_size( $js_size + $css_size ),
            );
        }

        usort( $plugin_assets, function( $a, $b ) {
            return $b['total_size'] - $a['total_size'];
        } );

        $result = array(
            'inactive_plugins' => array(
                'ref'   => 'G-PLG-01',
                'items' => $inactive,
                'count' => count( $inactive ),
                'status' => count( $inactive ) > 0 ? 'orange' : 'green',
            ),
            'inactive_themes' => array(
                'ref'   => 'G-PLG-02',
                'items' => $inactive_themes,
                'count' => count( $inactive_themes ),
                'status' => count( $inactive_themes ) > 0 ? 'orange' : 'green',
            ),
            'plugin_assets' => array(
                'ref'   => 'G-PLG-03',
                'items' => $plugin_assets,
            ),
        );
        set_transient( 'ecodiag_diag_plugins', $result, 10 * MINUTE_IN_SECONDS );
        return $result;
    }

    /**
     * G-MED: Media library diagnostics.
     */
    public static function media( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( 'ecodiag_diag_media' );
            if ( $cached ) return $cached;
        }
        global $wpdb;

        // G-MED-01: Non-converted images
        $non_webp = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type IN ('image/jpeg','image/png','image/gif')
             AND post_status = 'inherit'"
        );

        // G-MED-03: Orphan media (not attached)
        $orphan_media = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             WHERE p.post_type = 'attachment'
             AND p.post_parent = 0
             AND p.ID NOT IN (
                 SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = '_thumbnail_id' AND meta_value IS NOT NULL
             )"
        );

        // G-MED-04: Registered image sizes
        $registered_sizes = wp_get_registered_image_subsizes();
        $default_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large' );
        $extra_sizes = array();
        foreach ( $registered_sizes as $name => $size ) {
            if ( ! in_array( $name, $default_sizes, true ) ) {
                $extra_sizes[ $name ] = $size;
            }
        }

        // G-MED-05: Total media library size
        $upload_dir = wp_upload_dir();
        $total_size = is_dir( $upload_dir['basedir'] ) ? self::dir_size( $upload_dir['basedir'] ) : 0;

        // G-MED-06: Images without alt text
        $no_alt = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment'
             AND p.post_mime_type LIKE 'image/%'
             AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );

        // G-LAZY-01: Posts with images missing loading="lazy"
        $post_types = EcoDiag_Core::get_audited_post_types();
        $no_lazy = 0;
        if ( ! empty( $post_types ) ) {
            $type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
            $no_lazy = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type IN ({$type_placeholders})
                     AND post_status = 'publish'
                     AND post_content REGEXP '<img[^>]*>'
                     AND post_content NOT REGEXP '<img[^>]*loading\\\\s*='",
                    ...$post_types
                )
            );
        }

        $result = array(
            'non_converted' => array(
                'ref'    => 'G-MED-01',
                'count'  => (int) $non_webp,
                'status' => $non_webp > 50 ? 'red' : ( $non_webp > 10 ? 'orange' : 'green' ),
            ),
            'no_lazy' => array(
                'ref'    => 'G-LAZY-01',
                'count'  => (int) $no_lazy,
                'status' => $no_lazy > 20 ? 'red' : ( $no_lazy > 5 ? 'orange' : 'green' ),
            ),
            'orphan_media' => array(
                'ref'    => 'G-MED-03',
                'count'  => (int) $orphan_media,
                'status' => $orphan_media > 20 ? 'red' : ( $orphan_media > 5 ? 'orange' : 'green' ),
            ),
            'extra_sizes' => array(
                'ref'   => 'G-MED-04',
                'items' => $extra_sizes,
                'count' => count( $extra_sizes ),
            ),
            'total_size' => array(
                'ref'       => 'G-MED-05',
                'size'      => $total_size,
                'formatted' => EcoDiag_Scoring::format_size( $total_size ),
            ),
            'no_alt' => array(
                'ref'    => 'G-MED-06',
                'count'  => (int) $no_alt,
                'status' => $no_alt > 20 ? 'red' : ( $no_alt > 5 ? 'orange' : 'green' ),
            ),
        );
        set_transient( 'ecodiag_diag_media', $result, 10 * MINUTE_IN_SECONDS );
        return $result;
    }

    /**
     * G-REC: Server/hosting diagnostics.
     */
    public static function server() {
        $diagnostics = array();

        // G-REC-01: PHP version
        $php_version = phpversion();
        $diagnostics['php_version'] = array(
            'ref'     => 'G-REC-01',
            'value'   => $php_version,
            'status'  => version_compare( $php_version, '8.2', '>=' ) ? 'green' : ( version_compare( $php_version, '8.0', '>=' ) ? 'orange' : 'red' ),
            'message' => version_compare( $php_version, '8.2', '>=' ) ? '' : __( 'Mettre à jour vers PHP 8.2+ (gains de performance 20-40%)', 'ecodiag' ),
        );

        // G-REC-02: Compression (Brotli/Gzip)
        $has_brotli = function_exists( 'brotli_compress' ) || ( isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) && strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'br' ) !== false );
        $has_gzip = function_exists( 'gzencode' );
        $diagnostics['compression'] = array(
            'ref'     => 'G-REC-02',
            'brotli'  => $has_brotli,
            'gzip'    => $has_gzip,
            'status'  => $has_brotli ? 'green' : ( $has_gzip ? 'orange' : 'red' ),
            'message' => $has_brotli ? '' : ( $has_gzip ? __( 'Activer Brotli pour une meilleure compression', 'ecodiag' ) : __( 'Activer Brotli ou Gzip côté serveur', 'ecodiag' ) ),
        );

        // G-REC-04: OPcache
        $opcache = function_exists( 'opcache_get_status' ) && ! empty( @opcache_get_status() );
        $diagnostics['opcache'] = array(
            'ref'     => 'G-REC-04',
            'enabled' => $opcache,
            'status'  => $opcache ? 'green' : 'red',
            'message' => $opcache ? '' : __( 'Activer et configurer OPcache', 'ecodiag' ),
        );

        // G-REC-05: Object cache
        $object_cache = wp_using_ext_object_cache();
        $diagnostics['object_cache'] = array(
            'ref'     => 'G-REC-05',
            'enabled' => $object_cache,
            'status'  => $object_cache ? 'green' : 'orange',
            'message' => $object_cache ? '' : __( 'Activer un cache objet persistant (Redis recommandé)', 'ecodiag' ),
        );

        // G-REC-06: Page cache
        $page_cache = defined( 'WP_CACHE' ) && WP_CACHE;
        $diagnostics['page_cache'] = array(
            'ref'     => 'G-REC-06',
            'enabled' => $page_cache,
            'status'  => $page_cache ? 'green' : 'orange',
            'message' => $page_cache ? '' : __( 'Activer un cache de page', 'ecodiag' ),
        );

        // G-REC-08: Green hosting check
        $diagnostics['green_hosting'] = array(
            'ref'     => 'G-REC-08',
            'status'  => 'info',
            'message' => __( 'Vérifiez votre hébergeur sur thegreenwebfoundation.org', 'ecodiag' ),
        );

        // G-REC-09: WP-Cron mode
        $wp_cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
        $diagnostics['wp_cron'] = array(
            'ref'     => 'G-CRON-01',
            'native'  => ! $wp_cron_disabled,
            'status'  => $wp_cron_disabled ? 'green' : 'orange',
            'message' => $wp_cron_disabled ? '' : __( 'Passer en cron système pour de meilleures performances', 'ecodiag' ),
        );

        // G-REC-12: MySQL version
        global $wpdb;
        $mysql_version = $wpdb->get_var( "SELECT VERSION()" );
        $diagnostics['mysql_version'] = array(
            'ref'     => 'G-REC-12',
            'value'   => $mysql_version,
            'status'  => 'info',
        );

        return $diagnostics;
    }

    /**
     * G-EDIT: Editorial diagnostics.
     */
    public static function editorial() {
        global $wpdb;

        // G-EDIT-01: Stale content (not modified in 12+ months)
        $stale_content = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_modified
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ('post','page')
             AND post_modified < DATE_SUB(NOW(), INTERVAL 12 MONTH)
             ORDER BY post_modified ASC
             LIMIT 20"
        );

        // G-EDIT-04: Upload max size
        $max_upload = wp_max_upload_size();

        return array(
            'stale_content' => array(
                'ref'   => 'G-EDIT-01',
                'items' => $stale_content,
                'count' => count( $stale_content ),
                'status' => count( $stale_content ) > 10 ? 'orange' : 'green',
            ),
            'upload_max' => array(
                'ref'       => 'G-EDIT-04',
                'size'      => $max_upload,
                'formatted' => EcoDiag_Scoring::format_size( $max_upload ),
            ),
        );
    }

    /**
     * G-TRACK: Tracking scripts diagnostics (checks front page).
     */
    public static function tracking() {
        $home_url = home_url( '/' );
        $response = wp_remote_get( $home_url, array( 'timeout' => 15, 'sslverify' => false ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => $response->get_error_message() );
        }

        $html = wp_remote_retrieve_body( $response );
        $trackers = array();
        $tracker_patterns = array(
            'Google Analytics'  => array( 'google-analytics.com', 'gtag(' ),
            'Google Tag Manager'=> array( 'googletagmanager.com', 'gtm.js' ),
            'Facebook Pixel'    => array( 'connect.facebook.net', 'fbq(' ),
            'Hotjar'            => array( 'static.hotjar.com' ),
            'LinkedIn Insight'  => array( 'snap.licdn.com' ),
            'Twitter Pixel'     => array( 'static.ads-twitter.com' ),
            'TikTok Pixel'      => array( 'analytics.tiktok.com' ),
            'HubSpot'           => array( 'js.hs-scripts.com' ),
        );

        foreach ( $tracker_patterns as $name => $sigs ) {
            foreach ( $sigs as $sig ) {
                if ( stripos( $html, $sig ) !== false ) {
                    $trackers[] = $name;
                    break;
                }
            }
        }

        // External domains
        $external = array();
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( preg_match_all( '/(?:src|href)=["\'](?:https?:)?\/\/([^"\'\/]+)/i', $html, $m ) ) {
            foreach ( $m[1] as $host ) {
                if ( $host !== $site_host ) {
                    $external[ $host ] = isset( $external[ $host ] ) ? $external[ $host ] + 1 : 1;
                }
            }
        }

        return array(
            'trackers' => array(
                'ref'   => 'G-TRACK-01',
                'items' => $trackers,
                'count' => count( $trackers ),
                'status' => count( $trackers ) > 3 ? 'red' : ( count( $trackers ) > 1 ? 'orange' : 'green' ),
            ),
            'external_domains' => array(
                'ref'   => 'G-TRACK-03',
                'items' => $external,
                'count' => count( $external ),
                'status' => count( $external ) > 10 ? 'red' : ( count( $external ) > 5 ? 'orange' : 'green' ),
            ),
        );
    }

    /**
     * Calculate directory size, optionally filtered by extension.
     */
    public static function dir_size( $path, $extensions = array() ) {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                if ( ! empty( $extensions ) ) {
                    $ext = strtolower( $file->getExtension() );
                    if ( ! in_array( $ext, $extensions, true ) ) {
                        continue;
                    }
                }
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
