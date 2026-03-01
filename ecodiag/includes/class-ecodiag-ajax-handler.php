<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Central AJAX handler for all EcoDiag actions.
 */
class EcoDiag_Ajax_Handler {

    public function __construct() {
        $actions = array(
            // Audit
            'ecodiag_run_audit',
            'ecodiag_adminbar_data',
            // Content actions (P-IMG, P-VID, P-CONT)
            'ecodiag_add_lazy_loading',
            'ecodiag_add_dimensions',
            'ecodiag_convert_images',
            'ecodiag_compress_images',
            'ecodiag_convert_embeds',
            'ecodiag_remove_autoplay',
            'ecodiag_add_iframe_lazy',
            'ecodiag_purge_revisions',
            'ecodiag_clean_orphaned_meta',
            'ecodiag_disable_scripts',
            // Global actions (G-BDD)
            'ecodiag_purge_all_revisions',
            'ecodiag_clean_transients',
            'ecodiag_clean_orphan_options',
            'ecodiag_clean_orphan_postmeta',
            'ecodiag_optimize_tables',
            // Global actions (G-PLG)
            'ecodiag_delete_plugin',
            'ecodiag_delete_theme',
            // Global actions (G-MED)
            'ecodiag_bulk_convert',
            'ecodiag_bulk_compress',
            'ecodiag_delete_orphan_media',
            'ecodiag_remove_image_sizes',
            // Global actions (G-HEAD)
            'ecodiag_toggle_head',
            // Global actions (G-COND)
            'ecodiag_save_conditional_rules',
            // Global actions (G-CRON)
            'ecodiag_delete_orphan_crons',
            // Global actions (G-EDIT)
            'ecodiag_restrict_blocks',
            // Global actions (G-LAZY)
            'ecodiag_bulk_lazy_loading',
            // Export
            'ecodiag_export_csv',
            // Dashboard data
            'ecodiag_dashboard_data',
            // Server diagnostics
            'ecodiag_server_diagnostics',
            // Full site audit
            'ecodiag_run_full_audit',
        );

        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, array( $this, $action ) );
        }

        // Popup data endpoint — lower capability requirement (edit_posts)
        add_action( 'wp_ajax_ecodiag_popup_data', array( $this, 'ecodiag_popup_data' ) );
    }

    /**
     * Verify nonce and capability.
     */
    private function verify( $nonce_action = 'ecodiag_nonce' ) {
        if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
            wp_send_json_error( __( 'Nonce invalide.', 'ecodiag' ) );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permissions insuffisantes.', 'ecodiag' ) );
            return;
        }
    }

    /**
     * Resolve attachment ID from a URL, handling sized image URLs (-300x200).
     */
    private function resolve_attachment_id( $src ) {
        $att_id = attachment_url_to_postid( $src );
        if ( $att_id ) return $att_id;

        // Strip size suffix: image-300x200.jpg → image.jpg
        $path = wp_parse_url( $src, PHP_URL_PATH );
        if ( $path && preg_match( '/^(.+)-\d+x\d+(\.[a-zA-Z]{3,4})$/', $path, $m ) ) {
            $original_url = str_replace( $path, $m[1] . $m[2], $src );
            $att_id = attachment_url_to_postid( $original_url );
            if ( $att_id ) return $att_id;
        }

        return 0;
    }

    /**
     * Get the dimensions for a specific image size from attachment metadata.
     * If the URL references a sized version, returns those dimensions;
     * otherwise returns the original dimensions.
     */
    private function get_sized_dimensions( $src, $att_id ) {
        $meta = wp_get_attachment_metadata( $att_id );
        if ( ! $meta || ! isset( $meta['width'], $meta['height'] ) ) {
            return null;
        }

        // Check if the URL references a sized variant
        $path = wp_parse_url( $src, PHP_URL_PATH );
        if ( $path && preg_match( '/-(\d+)x(\d+)\.[a-zA-Z]{3,4}$/', $path, $m ) ) {
            return array( 'width' => (int) $m[1], 'height' => (int) $m[2] );
        }

        // Return original dimensions
        return array( 'width' => (int) $meta['width'], 'height' => (int) $meta['height'] );
    }

    /**
     * Replace all size variants of an old image URL with the new extension in content.
     * E.g. photo.jpg, photo-300x200.jpg, photo-150x150.jpg → .webp equivalents.
     */
    private function replace_image_urls_in_content( $content, $old_file, $new_ext, $upload_dir ) {
        $old_info = pathinfo( $old_file );
        $old_base = $old_info['filename']; // e.g., "photo"
        $old_ext  = $old_info['extension']; // e.g., "jpg"

        $old_rel = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $old_file );
        $new_rel = str_replace( '.' . $old_ext, '.' . $new_ext, $old_rel );

        // Replace full-size URL
        $content = str_replace( $old_rel, $new_rel, $content );

        // Replace sized variants: photo-{W}x{H}.jpg → photo-{W}x{H}.webp
        $content = preg_replace(
            '/' . preg_quote( $old_base, '/' ) . '-(\d+x\d+)\.' . preg_quote( $old_ext, '/' ) . '/i',
            $old_base . '-$1.' . $new_ext,
            $content
        );

        return $content;
    }

    // ========================================================================
    // AUDIT
    // ========================================================================

    public function ecodiag_run_audit() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id ) {
            wp_send_json_error( __( 'ID de contenu invalide.', 'ecodiag' ) );
        }
        $result = EcoDiag_Analyzer::audit_post( $post_id, true );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        wp_send_json_success( $result );
    }

    public function ecodiag_adminbar_data() {
        $this->verify();
        $url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
        if ( ! $url ) {
            wp_send_json_error( __( 'URL invalide.', 'ecodiag' ) );
        }

        // Try to find the post ID from the URL
        $post_id = url_to_postid( $url );
        if ( $post_id ) {
            $result = EcoDiag_Analyzer::audit_post( $post_id );
        } else {
            $result = EcoDiag_Analyzer::analyze_url( $url );
            if ( ! is_wp_error( $result ) ) {
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
            }
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        wp_send_json_success( $result );
    }

    // ========================================================================
    // CONTENT ACTIONS (P-IMG, P-VID, P-CONT)
    // ========================================================================

    public function ecodiag_add_lazy_loading() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $post = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( __( 'Contenu introuvable.', 'ecodiag' ) );

        $content = $post->post_content;
        // Add loading="lazy" to images that don't have it
        $content = preg_replace( '/<img(?![^>]*loading\s*=)([^>]*)>/i', '<img loading="lazy"$1>', $content );
        // Add decoding="async" to images that don't have it
        $content = preg_replace( '/<img(?![^>]*decoding\s*=)([^>]*)>/i', '<img decoding="async"$1>', $content );
        // Add loading="lazy" to iframes that don't have it
        $content = preg_replace( '/<iframe(?![^>]*loading\s*=)([^>]*)>/i', '<iframe loading="lazy"$1>', $content );

        wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
        delete_transient( 'ecodiag_audit_' . $post_id );
        wp_send_json_success( __( 'Lazy loading ajouté.', 'ecodiag' ) );
    }

    public function ecodiag_add_dimensions() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $post = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( __( 'Contenu introuvable.', 'ecodiag' ) );

        $content = $post->post_content;
        $content = preg_replace_callback( '/<img\s[^>]*>/i', array( $this, 'add_img_dimensions' ), $content );

        wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
        delete_transient( 'ecodiag_audit_' . $post_id );
        wp_send_json_success( __( 'Dimensions ajoutées.', 'ecodiag' ) );
    }

    private function add_img_dimensions( $matches ) {
        $tag = $matches[0];
        if ( preg_match( '/\swidth=/i', $tag ) && preg_match( '/\sheight=/i', $tag ) ) {
            return $tag;
        }
        if ( preg_match( '/src=["\']([^"\']+)["\']/i', $tag, $m ) ) {
            $src = $m[1];

            // Try Gutenberg class first: wp-image-{ID}
            $attachment_id = 0;
            if ( preg_match( '/class="[^"]*wp-image-(\d+)/', $tag, $ci ) ) {
                $attachment_id = (int) $ci[1];
            }
            if ( ! $attachment_id ) {
                $attachment_id = $this->resolve_attachment_id( $src );
            }

            if ( $attachment_id ) {
                // Get dimensions for the actual size used (not necessarily the original)
                $dims = $this->get_sized_dimensions( $src, $attachment_id );
                if ( $dims ) {
                    if ( ! preg_match( '/\swidth=/i', $tag ) ) {
                        $tag = str_replace( '<img', '<img width="' . $dims['width'] . '"', $tag );
                    }
                    if ( ! preg_match( '/\sheight=/i', $tag ) ) {
                        $tag = str_replace( '<img', '<img height="' . $dims['height'] . '"', $tag );
                    }
                }
            }
        }
        return $tag;
    }

    public function ecodiag_convert_images() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $format  = get_option( 'ecodiag_conversion_format', 'webp' );
        $post    = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( __( 'Contenu introuvable.', 'ecodiag' ) );

        $content    = $post->post_content;
        $converted  = 0;
        $upload_dir = wp_upload_dir();
        $ext        = $format === 'avif' ? 'avif' : 'webp';
        $mime       = $format === 'avif' ? 'image/avif' : 'image/webp';
        $quality    = (int) get_option( 'ecodiag_compression_quality', 80 );
        $done_ids   = array();

        // Collect unique attachment IDs from content
        // Method 1: Gutenberg class wp-image-{ID}
        if ( preg_match_all( '/class="[^"]*wp-image-(\d+)[^"]*"/', $content, $cm ) ) {
            foreach ( $cm[1] as $id ) {
                $done_ids[ (int) $id ] = true;
            }
        }
        // Method 2: src URL resolution (handles classic editor)
        if ( preg_match_all( '/src=["\']([^"\']+\.(?:png|jpg|jpeg|gif))(?:\?[^"\']*)?["\']/', $content, $sm ) ) {
            foreach ( $sm[1] as $src ) {
                $att_id = $this->resolve_attachment_id( $src );
                if ( $att_id ) {
                    $done_ids[ $att_id ] = true;
                }
            }
        }

        foreach ( array_keys( $done_ids ) as $att_id ) {
            $file = get_attached_file( $att_id );
            if ( ! $file || ! file_exists( $file ) ) continue;

            // Skip if already converted
            $file_ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
            if ( in_array( $file_ext, array( 'webp', 'avif' ), true ) ) continue;

            $editor = wp_get_image_editor( $file );
            if ( is_wp_error( $editor ) ) continue;

            $info     = pathinfo( $file );
            $new_file = $info['dirname'] . '/' . $info['filename'] . '.' . $ext;

            $editor->set_quality( $quality );
            $result = $editor->save( $new_file, $mime );
            if ( is_wp_error( $result ) ) continue;

            $new_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $result['path'] );

            update_post_meta( $att_id, '_ecodiag_original_file', $file );
            update_attached_file( $att_id, $result['path'] );
            wp_update_post( array(
                'ID'             => $att_id,
                'post_mime_type' => $mime,
                'guid'           => $new_url,
            ) );

            // Regenerate sized variants for the new format
            $metadata = wp_generate_attachment_metadata( $att_id, $result['path'] );
            wp_update_attachment_metadata( $att_id, $metadata );

            // Replace all URL variants (full-size + sized) in content
            $content = $this->replace_image_urls_in_content( $content, $file, $ext, $upload_dir );
            $converted++;
        }

        if ( $converted > 0 ) {
            wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
        }
        delete_transient( 'ecodiag_audit_' . $post_id );
        delete_transient( 'ecodiag_diag_media' );

        wp_send_json_success( sprintf(
            /* translators: %d: number of converted images */
            __( '%d image(s) convertie(s) en %s.', 'ecodiag' ),
            $converted,
            strtoupper( $format )
        ) );
    }

    public function ecodiag_compress_images() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $post    = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( __( 'Contenu introuvable.', 'ecodiag' ) );

        $quality    = (int) get_option( 'ecodiag_compression_quality', 80 );
        $max_weight = (int) get_option( 'ecodiag_image_max_weight', 200 ) * 1024;
        $compressed = 0;
        $done_ids   = array();

        // Collect unique attachment IDs from content (via class or src URL)
        if ( preg_match_all( '/class="[^"]*wp-image-(\d+)[^"]*"/', $post->post_content, $cm ) ) {
            foreach ( $cm[1] as $id ) {
                $done_ids[ (int) $id ] = true;
            }
        }
        if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $sm ) ) {
            foreach ( $sm[1] as $src ) {
                $att_id = $this->resolve_attachment_id( $src );
                if ( $att_id ) {
                    $done_ids[ $att_id ] = true;
                }
            }
        }

        foreach ( array_keys( $done_ids ) as $att_id ) {
            $file = get_attached_file( $att_id );
            if ( ! $file || ! file_exists( $file ) ) continue;
            if ( filesize( $file ) <= $max_weight ) continue;

            $editor = wp_get_image_editor( $file );
            if ( is_wp_error( $editor ) ) continue;

            $editor->set_quality( $quality );
            $result = $editor->save( $file );
            if ( ! is_wp_error( $result ) ) {
                // Regenerate sized variants with new quality
                $metadata = wp_generate_attachment_metadata( $att_id, $file );
                wp_update_attachment_metadata( $att_id, $metadata );
                $compressed++;
            }
        }

        delete_transient( 'ecodiag_audit_' . $post_id );
        wp_send_json_success( sprintf( __( '%d image(s) compressée(s).', 'ecodiag' ), $compressed ) );
    }

    public function ecodiag_convert_embeds() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $post    = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( __( 'Contenu introuvable.', 'ecodiag' ) );

        $content   = $post->post_content;
        $converted = 0;

        // Replace YouTube iframes with lite-youtube facade
        $content = preg_replace_callback(
            '/<iframe[^>]*src=["\'](?:https?:)?\/\/(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]+)[^"\']*["\'][^>]*><\/iframe>/i',
            function( $matches ) use ( &$converted ) {
                $converted++;
                $video_id = $matches[1];
                return '<div class="ecodiag-lite-embed" data-provider="youtube" data-id="' . esc_attr( $video_id ) . '">'
                    . '<a href="https://www.youtube.com/watch?v=' . esc_attr( $video_id ) . '" target="_blank" rel="noopener">'
                    . '<img src="https://i.ytimg.com/vi/' . esc_attr( $video_id ) . '/hqdefault.jpg" alt="Vidéo YouTube" loading="lazy" />'
                    . '<span class="ecodiag-play-btn">&#9654;</span>'
                    . '</a></div>';
            },
            $content
        );

        // Replace Vimeo iframes
        $content = preg_replace_callback(
            '/<iframe[^>]*src=["\'](?:https?:)?\/\/player\.vimeo\.com\/video\/(\d+)[^"\']*["\'][^>]*><\/iframe>/i',
            function( $matches ) use ( &$converted ) {
                $converted++;
                $video_id = $matches[1];
                return '<div class="ecodiag-lite-embed" data-provider="vimeo" data-id="' . esc_attr( $video_id ) . '">'
                    . '<a href="https://vimeo.com/' . esc_attr( $video_id ) . '" target="_blank" rel="noopener">'
                    . '<span class="ecodiag-play-btn">&#9654;</span>'
                    . '</a></div>';
            },
            $content
        );

        if ( $converted > 0 ) {
            wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
        }
        delete_transient( 'ecodiag_audit_' . $post_id );
        wp_send_json_success( sprintf( __( '%d embed(s) converti(s) en façade légère.', 'ecodiag' ), $converted ) );
    }

    public function ecodiag_remove_autoplay() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $post    = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( __( 'Contenu introuvable.', 'ecodiag' ) );

        $content = preg_replace_callback(
            '/<(video|iframe)([^>]*)>/i',
            function ( $m ) {
                $tag  = $m[1];
                $attrs = preg_replace( '/\s+autoplay(?:\s*=\s*["\'][^"\']*["\'])?/i', '', $m[2] );
                return '<' . $tag . $attrs . '>';
            },
            $post->post_content
        );
        wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
        delete_transient( 'ecodiag_audit_' . $post_id );
        wp_send_json_success( __( 'Autoplay supprimé.', 'ecodiag' ) );
    }

    public function ecodiag_add_iframe_lazy() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $post    = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( __( 'Contenu introuvable.', 'ecodiag' ) );

        $content = preg_replace( '/<iframe(?![^>]*loading=)([^>]*)>/i', '<iframe loading="lazy"$1>', $post->post_content );
        wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
        delete_transient( 'ecodiag_audit_' . $post_id );
        wp_send_json_success( __( 'Lazy loading ajouté aux iframes.', 'ecodiag' ) );
    }

    public function ecodiag_purge_revisions() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $keep    = (int) get_option( 'ecodiag_revisions_keep', 5 );

        $revisions = wp_get_post_revisions( $post_id, array( 'order' => 'DESC' ) );
        $count = 0;
        $i = 0;
        foreach ( $revisions as $rev ) {
            $i++;
            if ( $i > $keep ) {
                wp_delete_post_revision( $rev->ID );
                $count++;
            }
        }
        delete_transient( 'ecodiag_audit_' . $post_id );
        wp_send_json_success( sprintf( __( '%d révision(s) purgée(s).', 'ecodiag' ), $count ) );
    }

    public function ecodiag_clean_orphaned_meta() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        global $wpdb;

        $known_keys = array( '_edit_lock', '_edit_last', '_wp_page_template', '_thumbnail_id', '_wp_old_slug', '_ecodiag_disabled_scripts', '_ecodiag_disabled_styles' );
        $placeholders = implode( ',', array_fill( 0, count( $known_keys ), '%s' ) );

        $count = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE '\\_%%' AND meta_key NOT IN ({$placeholders})",
            array_merge( array( $post_id ), $known_keys )
        ) );

        delete_transient( 'ecodiag_audit_' . $post_id );
        wp_send_json_success( sprintf( __( '%d métadonnée(s) orpheline(s) nettoyée(s).', 'ecodiag' ), $count ) );
    }

    public function ecodiag_disable_scripts() {
        $this->verify();
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $scripts = isset( $_POST['scripts'] ) ? array_map( 'sanitize_key', (array) $_POST['scripts'] ) : array();
        $styles  = isset( $_POST['styles'] ) ? array_map( 'sanitize_key', (array) $_POST['styles'] ) : array();

        update_post_meta( $post_id, '_ecodiag_disabled_scripts', $scripts );
        update_post_meta( $post_id, '_ecodiag_disabled_styles', $styles );
        delete_transient( 'ecodiag_audit_' . $post_id );
        wp_send_json_success( __( 'Règles de désactivation enregistrées.', 'ecodiag' ) );
    }

    // ========================================================================
    // GLOBAL ACTIONS (G-BDD)
    // ========================================================================

    public function ecodiag_purge_all_revisions() {
        $this->verify();
        global $wpdb;
        $keep = (int) get_option( 'ecodiag_revisions_keep', 5 );

        if ( $keep <= 0 ) {
            $count = $wpdb->query(
                "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'"
            );
        } else {
            // Get all posts that have revisions, then delete beyond keep limit
            $parent_ids = $wpdb->get_col(
                "SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision'"
            );
            $count = 0;
            foreach ( $parent_ids as $parent_id ) {
                $to_delete = $wpdb->get_col( $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_type = 'revision' AND post_parent = %d
                     ORDER BY post_date DESC
                     LIMIT 99999 OFFSET %d",
                    $parent_id, $keep
                ) );
                if ( ! empty( $to_delete ) ) {
                    $ids = implode( ',', array_map( 'intval', $to_delete ) );
                    $count += (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$ids})" );
                }
            }
        }

        // Clean orphaned postmeta
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.ID IS NULL"
        );

        delete_transient( 'ecodiag_diag_database' );
        wp_send_json_success( sprintf( __( '%d révision(s) purgée(s).', 'ecodiag' ), $count ) );
    }

    public function ecodiag_clean_transients() {
        $this->verify();
        global $wpdb;

        $count = $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a
             INNER JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_timeout_', '_')
             WHERE a.option_name LIKE '\\_transient\\_timeout\\_%'
             AND a.option_value < UNIX_TIMESTAMP()"
        );

        // Also delete orphaned transients without timeout
        $count2 = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '\\_transient\\_timeout\\_%'
             AND option_value < UNIX_TIMESTAMP()"
        );

        delete_transient( 'ecodiag_diag_database' );
        wp_send_json_success( sprintf( __( '%d transient(s) expiré(s) nettoyé(s).', 'ecodiag' ), $count + $count2 ) );
    }

    public function ecodiag_clean_orphan_options() {
        $this->verify();
        $options = isset( $_POST['options'] ) ? array_map( 'sanitize_text_field', (array) $_POST['options'] ) : array();
        $count = 0;
        foreach ( $options as $option ) {
            if ( delete_option( $option ) ) {
                $count++;
            }
        }
        wp_send_json_success( sprintf( __( '%d option(s) supprimée(s).', 'ecodiag' ), $count ) );
    }

    public function ecodiag_clean_orphan_postmeta() {
        $this->verify();
        global $wpdb;

        $count = $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.ID IS NULL"
        );

        // Also clean orphaned usermeta
        $count2 = $wpdb->query(
            "DELETE um FROM {$wpdb->usermeta} um
             LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
             WHERE u.ID IS NULL"
        );

        // Clean orphaned commentmeta
        $count3 = $wpdb->query(
            "DELETE cm FROM {$wpdb->commentmeta} cm
             LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
             WHERE c.comment_ID IS NULL"
        );

        delete_transient( 'ecodiag_diag_database' );
        wp_send_json_success( sprintf(
            __( '%d métadonnée(s) orpheline(s) nettoyée(s) (posts: %d, users: %d, comments: %d).', 'ecodiag' ),
            $count + $count2 + $count3, $count, $count2, $count3
        ) );
    }

    public function ecodiag_optimize_tables() {
        $this->verify();
        global $wpdb;

        $tables = $wpdb->get_col( "SHOW TABLES" );
        $optimized = 0;
        foreach ( $tables as $table ) {
            // Only optimize tables belonging to this WordPress install
            if ( strpos( $table, $wpdb->prefix ) !== 0 ) {
                continue;
            }
            // Sanitize table name: only allow alphanumeric and underscores
            if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) ) {
                continue;
            }
            $wpdb->query( "OPTIMIZE TABLE `{$table}`" );
            $optimized++;
        }

        delete_transient( 'ecodiag_diag_database' );
        wp_send_json_success( sprintf( __( '%d table(s) optimisée(s).', 'ecodiag' ), $optimized ) );
    }

    // ========================================================================
    // GLOBAL ACTIONS (G-PLG)
    // ========================================================================

    public function ecodiag_delete_plugin() {
        $this->verify();
        if ( ! current_user_can( 'delete_plugins' ) ) {
            wp_send_json_error( __( 'Permissions insuffisantes.', 'ecodiag' ) );
        }
        $plugin = isset( $_POST['plugin'] ) ? sanitize_text_field( $_POST['plugin'] ) : '';
        if ( ! $plugin ) wp_send_json_error( __( 'Plugin non spécifié.', 'ecodiag' ) );

        $result = delete_plugins( array( $plugin ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        delete_transient( 'ecodiag_diag_plugins' );
        wp_send_json_success( __( 'Plugin supprimé.', 'ecodiag' ) );
    }

    public function ecodiag_delete_theme() {
        $this->verify();
        if ( ! current_user_can( 'delete_themes' ) ) {
            wp_send_json_error( __( 'Permissions insuffisantes.', 'ecodiag' ) );
        }
        $theme = isset( $_POST['theme'] ) ? sanitize_text_field( $_POST['theme'] ) : '';
        if ( ! $theme ) wp_send_json_error( __( 'Thème non spécifié.', 'ecodiag' ) );

        $result = delete_theme( $theme );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        delete_transient( 'ecodiag_diag_plugins' );
        wp_send_json_success( __( 'Thème supprimé.', 'ecodiag' ) );
    }

    // ========================================================================
    // GLOBAL ACTIONS (G-MED)
    // ========================================================================

    public function ecodiag_bulk_convert() {
        $this->verify();
        $format  = get_option( 'ecodiag_conversion_format', 'webp' );
        $quality = (int) get_option( 'ecodiag_compression_quality', 80 );
        $batch   = 5;

        // Count total remaining BEFORE processing (not using offset since converted
        // images change mime type and drop out of the query automatically)
        global $wpdb;
        $total_remaining = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type IN ('image/jpeg','image/png','image/gif')
             AND post_status = 'inherit'"
        );

        // Always fetch from offset 0: already-converted images are no longer
        // returned by this query (their mime type changed), so the next batch
        // is always the first N remaining images.
        $images = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
            'posts_per_page' => $batch,
            'offset'         => 0,
            'post_status'    => 'inherit',
            'fields'         => 'ids',
        ) );

        $converted  = 0;
        $processed  = isset( $_POST['processed'] ) ? (int) $_POST['processed'] : 0;
        $total_init = isset( $_POST['total_init'] ) ? (int) $_POST['total_init'] : $total_remaining;
        $upload_dir = wp_upload_dir();

        foreach ( $images as $att_id ) {
            $file = get_attached_file( $att_id );
            if ( ! $file || ! file_exists( $file ) ) continue;

            $editor = wp_get_image_editor( $file );
            if ( is_wp_error( $editor ) ) continue;

            $info     = pathinfo( $file );
            $ext      = $format === 'avif' ? 'avif' : 'webp';
            $mime     = $format === 'avif' ? 'image/avif' : 'image/webp';
            $new_file = $info['dirname'] . '/' . $info['filename'] . '.' . $ext;

            $editor->set_quality( $quality );
            $result = $editor->save( $new_file, $mime );
            if ( is_wp_error( $result ) ) continue;

            $new_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $result['path'] );

            // Save old metadata before conversion (to get old sized file URLs)
            $old_metadata = wp_get_attachment_metadata( $att_id );

            update_post_meta( $att_id, '_ecodiag_original_file', $file );
            update_attached_file( $att_id, $result['path'] );
            wp_update_post( array(
                'ID'             => $att_id,
                'post_mime_type' => $mime,
                'guid'           => $new_url,
            ) );

            // Regenerate attachment metadata (sizes, dimensions) for the new format
            $metadata = wp_generate_attachment_metadata( $att_id, $result['path'] );
            wp_update_attachment_metadata( $att_id, $metadata );

            // Update image URLs in all posts referencing this attachment
            $old_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file );

            // Collect all old URLs to replace (full-size + each sized variant)
            $old_dir_url = dirname( $old_url );
            $new_dir_url = dirname( $new_url );
            $url_replacements = array( $old_url => $new_url );

            if ( ! empty( $old_metadata['sizes'] ) ) {
                foreach ( $old_metadata['sizes'] as $old_size ) {
                    $old_size_url = $old_dir_url . '/' . $old_size['file'];
                    $new_size_file = preg_replace( '/\.[a-zA-Z]{3,4}$/', '.' . $ext, $old_size['file'] );
                    $new_size_url = $new_dir_url . '/' . $new_size_file;
                    $url_replacements[ $old_size_url ] = $new_size_url;
                }
            }

            foreach ( $url_replacements as $old => $new ) {
                if ( $old !== $new ) {
                    $wpdb->query( $wpdb->prepare(
                        "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
                        $old,
                        $new,
                        '%' . $wpdb->esc_like( $old ) . '%'
                    ) );
                }
            }

            $converted++;
        }

        $processed += $converted;
        $has_more = ( $total_remaining - $converted ) > 0 && $converted > 0;

        // Invalidate media diagnostics cache
        delete_transient( 'ecodiag_diag_media' );

        wp_send_json_success( array(
            'converted'  => $converted,
            'processed'  => $processed,
            'total_init' => $total_init,
            'has_more'   => $has_more,
            'total'      => $total_remaining - $converted,
            'message'    => sprintf( __( '%d/%d traité(s)...', 'ecodiag' ), $processed, $total_init ),
        ) );
    }

    public function ecodiag_bulk_compress() {
        $this->verify();
        $quality    = (int) get_option( 'ecodiag_compression_quality', 80 );
        $max_weight = (int) get_option( 'ecodiag_image_max_weight', 200 ) * 1024;
        $offset     = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
        $batch      = 10;

        $images = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
            'posts_per_page' => $batch,
            'offset'         => $offset,
            'post_status'    => 'inherit',
            'fields'         => 'ids',
        ) );

        $compressed = 0;
        foreach ( $images as $att_id ) {
            $file = get_attached_file( $att_id );
            if ( ! $file || ! file_exists( $file ) ) continue;
            if ( filesize( $file ) <= $max_weight ) continue;

            $editor = wp_get_image_editor( $file );
            if ( is_wp_error( $editor ) ) continue;

            $editor->set_quality( $quality );
            $result = $editor->save( $file );
            if ( ! is_wp_error( $result ) ) {
                $compressed++;
            }
        }

        global $wpdb;
        $total_images = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type IN ('image/jpeg','image/png','image/webp')
             AND post_status = 'inherit'"
        );
        $has_more   = count( $images ) === $batch;
        $new_offset = $offset + $batch;

        // Invalidate media diagnostics cache
        delete_transient( 'ecodiag_diag_media' );

        wp_send_json_success( array(
            'compressed' => $compressed,
            'offset'     => $new_offset,
            'has_more'   => $has_more,
            'total'      => $total_images,
            'message'    => sprintf( __( '%d/%d traité(s), %d compressée(s).', 'ecodiag' ), min( $new_offset, $total_images ), $total_images, $compressed ),
        ) );
    }

    public function ecodiag_delete_orphan_media() {
        $this->verify();
        global $wpdb;

        $orphans = $wpdb->get_col(
            "SELECT p.ID FROM {$wpdb->posts} p
             WHERE p.post_type = 'attachment'
             AND p.post_parent = 0
             AND p.ID NOT IN (
                 SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = '_thumbnail_id'
             )"
        );

        $deleted = 0;
        foreach ( $orphans as $att_id ) {
            if ( wp_delete_attachment( $att_id, true ) ) {
                $deleted++;
            }
        }

        delete_transient( 'ecodiag_diag_media' );
        wp_send_json_success( sprintf( __( '%d média(s) orphelin(s) supprimé(s).', 'ecodiag' ), $deleted ) );
    }

    public function ecodiag_remove_image_sizes() {
        $this->verify();
        $sizes = isset( $_POST['sizes'] ) ? array_map( 'sanitize_text_field', (array) $_POST['sizes'] ) : array();
        if ( empty( $sizes ) ) wp_send_json_error( __( 'Aucune taille sélectionnée.', 'ecodiag' ) );

        // Store sizes to remove
        update_option( 'ecodiag_removed_image_sizes', $sizes );

        // Hook to remove the sizes
        foreach ( $sizes as $size ) {
            remove_image_size( $size );
        }

        wp_send_json_success( sprintf( __( '%d taille(s) d\'image désactivée(s).', 'ecodiag' ), count( $sizes ) ) );
    }

    // ========================================================================
    // GLOBAL ACTIONS (G-HEAD)
    // ========================================================================

    public function ecodiag_toggle_head() {
        $this->verify();
        $option = isset( $_POST['option'] ) ? sanitize_key( $_POST['option'] ) : '';
        $value  = isset( $_POST['value'] ) ? sanitize_text_field( $_POST['value'] ) : '0';

        // Only allow known EcoDiag head options
        if ( strpos( $option, 'ecodiag_head_' ) !== 0 ) {
            wp_send_json_error( __( 'Option non autorisée.', 'ecodiag' ) );
        }

        update_option( $option, $value === '1' ? '1' : '0' );
        wp_send_json_success( __( 'Réglage mis à jour.', 'ecodiag' ) );
    }

    // ========================================================================
    // GLOBAL ACTIONS (G-COND)
    // ========================================================================

    public function ecodiag_save_conditional_rules() {
        $this->verify();
        $rules = isset( $_POST['rules'] ) ? $_POST['rules'] : array();
        $sanitized = array();

        if ( is_array( $rules ) ) {
            foreach ( $rules as $rule ) {
                $sanitized[] = array(
                    'plugin'     => sanitize_key( $rule['plugin'] ?? '' ),
                    'mode'       => sanitize_key( $rule['mode'] ?? 'everywhere' ),
                    'post_types' => isset( $rule['post_types'] ) ? array_map( 'sanitize_key', (array) $rule['post_types'] ) : array(),
                    'pages'      => isset( $rule['pages'] ) ? array_map( 'intval', (array) $rule['pages'] ) : array(),
                );
            }
        }

        update_option( 'ecodiag_conditional_rules', $sanitized );
        wp_send_json_success( __( 'Règles de chargement conditionnel enregistrées.', 'ecodiag' ) );
    }

    // ========================================================================
    // GLOBAL ACTIONS (G-CRON)
    // ========================================================================

    public function ecodiag_delete_orphan_crons() {
        $this->verify();
        $orphaned = EcoDiag_Cron::get_orphaned_cron_events();
        $count = 0;
        foreach ( array_keys( $orphaned ) as $hook ) {
            wp_clear_scheduled_hook( $hook );
            $count++;
        }
        wp_send_json_success( sprintf( __( '%d tâche(s) cron orpheline(s) supprimée(s).', 'ecodiag' ), $count ) );
    }

    // ========================================================================
    // GLOBAL ACTIONS (G-EDIT)
    // ========================================================================

    public function ecodiag_restrict_blocks() {
        $this->verify();
        $allowed = isset( $_POST['blocks'] ) ? array_map( 'sanitize_text_field', (array) $_POST['blocks'] ) : array();
        update_option( 'ecodiag_allowed_blocks', $allowed );
        wp_send_json_success( __( 'Blocs autorisés mis à jour.', 'ecodiag' ) );
    }

    // ========================================================================
    // GLOBAL ACTIONS (G-LAZY) — Bulk lazy loading
    // ========================================================================

    public function ecodiag_bulk_lazy_loading() {
        $this->verify();
        $batch = 10;

        $post_types = EcoDiag_Core::get_audited_post_types();
        $processed  = isset( $_POST['processed'] ) ? (int) $_POST['processed'] : 0;
        $total_init = isset( $_POST['total_init'] ) ? (int) $_POST['total_init'] : 0;

        // Find posts whose content contains <img or <iframe WITHOUT loading=
        global $wpdb;

        $type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        // Count total remaining (posts with images/iframes that lack loading=)
        $total_remaining = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type IN ({$type_placeholders})
                 AND post_status = 'publish'
                 AND (
                     post_content REGEXP '<img[^>]*>' AND post_content NOT REGEXP '<img[^>]*loading\\\\s*='
                     OR post_content REGEXP '<iframe[^>]*>' AND post_content NOT REGEXP '<iframe[^>]*loading\\\\s*='
                 )",
                ...$post_types
            )
        );

        if ( $total_init <= 0 ) {
            $total_init = $total_remaining;
        }

        // Get the next batch of posts needing lazy loading
        $posts = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type IN ({$type_placeholders})
                 AND post_status = 'publish'
                 AND (
                     post_content REGEXP '<img[^>]*>' AND post_content NOT REGEXP '<img[^>]*loading\\\\s*='
                     OR post_content REGEXP '<iframe[^>]*>' AND post_content NOT REGEXP '<iframe[^>]*loading\\\\s*='
                 )
                 LIMIT %d",
                ...array_merge( $post_types, array( $batch ) )
            )
        );

        $modified = 0;
        foreach ( $posts as $pid ) {
            $post = get_post( $pid );
            if ( ! $post ) continue;

            $content = $post->post_content;
            $original = $content;

            // Add loading="lazy" to images
            $content = preg_replace( '/<img(?![^>]*loading\s*=)([^>]*)>/i', '<img loading="lazy"$1>', $content );
            // Add decoding="async" to images
            $content = preg_replace( '/<img(?![^>]*decoding\s*=)([^>]*)>/i', '<img decoding="async"$1>', $content );
            // Add loading="lazy" to iframes
            $content = preg_replace( '/<iframe(?![^>]*loading\s*=)([^>]*)>/i', '<iframe loading="lazy"$1>', $content );

            if ( $content !== $original ) {
                wp_update_post( array( 'ID' => $pid, 'post_content' => $content ) );
                delete_transient( 'ecodiag_audit_' . $pid );
                $modified++;
            }
        }

        $processed += $modified;
        $has_more = ( $total_remaining - $modified ) > 0 && $modified > 0;

        // Invalidate media diagnostics cache (G-LAZY-01 counter)
        delete_transient( 'ecodiag_diag_media' );

        wp_send_json_success( array(
            'converted'  => $modified,
            'processed'  => $processed,
            'total_init' => $total_init,
            'has_more'   => $has_more,
            'total'      => $total_remaining - $modified,
            'message'    => sprintf( __( '%d/%d traité(s)...', 'ecodiag' ), $processed, $total_init ),
        ) );
    }

    // ========================================================================
    // EXPORT
    // ========================================================================

    public function ecodiag_export_csv() {
        $this->verify();

        $history = EcoDiag_History::get_global( 365 );
        $heaviest = EcoDiag_History::get_heaviest_pages( 50 );

        $csv = "Date,Score moyen,Poids moyen,Pages auditées\n";
        foreach ( $history as $row ) {
            $csv .= sprintf( "%s,%s,%s,%s\n",
                $row['date'],
                round( $row['avg_score'], 1 ),
                EcoDiag_Scoring::format_size( (int) $row['avg_weight'] ),
                $row['pages_audited']
            );
        }

        $csv .= "\n\nPages les plus lourdes\n";
        $csv .= "ID,Titre,Score,Poids,Date\n";
        foreach ( $heaviest as $row ) {
            $csv .= sprintf( "%s,%s,%s,%s,%s\n",
                $row['object_id'],
                '"' . str_replace( '"', '""', $row['post_title'] ?? '' ) . '"',
                $row['score'],
                EcoDiag_Scoring::format_size( (int) $row['page_weight'] ),
                $row['created_at']
            );
        }

        wp_send_json_success( array( 'csv' => $csv ) );
    }

    // ========================================================================
    // DASHBOARD DATA
    // ========================================================================

    public function ecodiag_dashboard_data() {
        $this->verify();

        $data = array(
            'averages'  => EcoDiag_History::get_global_averages(),
            'history'   => EcoDiag_History::get_global( 90 ),
            'heaviest'  => EcoDiag_History::get_heaviest_pages( 10 ),
            'worst'     => EcoDiag_History::get_worst_scored( 10 ),
            'database'  => EcoDiag_Diagnostics::database(),
            'plugins'   => EcoDiag_Diagnostics::plugins(),
            'media'     => EcoDiag_Diagnostics::media(),
            'head'      => EcoDiag_Head_Cleanup::diagnose(),
            'cron'      => EcoDiag_Cron::get_orphaned_cron_events(),
            'server'    => EcoDiag_Diagnostics::server(),
            'editorial' => EcoDiag_Diagnostics::editorial(),
            'tracking'  => EcoDiag_Diagnostics::tracking(),
        );

        wp_send_json_success( $data );
    }

    // ========================================================================
    // SERVER DIAGNOSTICS
    // ========================================================================

    public function ecodiag_server_diagnostics() {
        $this->verify();
        wp_send_json_success( EcoDiag_Diagnostics::server() );
    }

    // ========================================================================
    // FULL SITE AUDIT
    // ========================================================================

    public function ecodiag_run_full_audit() {
        $this->verify();

        $post_types = EcoDiag_Core::get_audited_post_types();
        $offset     = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
        $batch      = 10;

        $posts = get_posts( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $batch,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );

        $total_query = new WP_Query( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );
        $total = $total_query->found_posts;

        $audited = 0;
        foreach ( $posts as $post_id ) {
            EcoDiag_Analyzer::audit_post( $post_id, true );
            $audited++;
        }

        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        $has_more   = count( $posts ) === $batch;
        $new_offset = $offset + $batch;

        wp_send_json_success( array(
            'audited'  => $audited,
            'offset'   => $new_offset,
            'has_more' => $has_more,
            'total'    => $total,
            'message'  => sprintf( __( '%d/%d page(s) auditée(s)…', 'ecodiag' ), min( $new_offset, $total ), $total ),
        ) );
    }

    // ========================================================================
    // FRONT-END POPUP DATA (lower capability: edit_posts)
    // ========================================================================

    public function ecodiag_popup_data() {
        if ( ! check_ajax_referer( 'ecodiag_nonce', 'nonce', false ) ) {
            wp_send_json_error( __( 'Nonce invalide.', 'ecodiag' ) );
            return;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permissions insuffisantes.', 'ecodiag' ) );
            return;
        }

        $url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
        if ( ! $url ) {
            wp_send_json_error( __( 'URL invalide.', 'ecodiag' ) );
            return;
        }

        $force   = isset( $_POST['force'] ) && $_POST['force'] === '1';
        $post_id = url_to_postid( $url );
        if ( $post_id ) {
            $result = EcoDiag_Analyzer::audit_post( $post_id, $force );
        } else {
            $result = EcoDiag_Analyzer::analyze_url( $url );
            if ( ! is_wp_error( $result ) ) {
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
            }
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
            return;
        }
        wp_send_json_success( $result );
    }
}
