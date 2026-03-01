<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Upload handler for automatic compression and conversion.
 * G-EDIT-04, G-EDIT-05
 */
class EcoDiag_Upload_Handler {

    public function __construct() {
        // G-EDIT-04: Max upload size
        add_filter( 'upload_size_limit', array( $this, 'limit_upload_size' ) );

        // G-EDIT-05: Auto-compress on upload
        add_filter( 'wp_handle_upload', array( $this, 'handle_upload' ) );
    }

    /**
     * Limit the upload max size.
     */
    public function limit_upload_size( $size ) {
        $max_mo = (float) get_option( 'ecodiag_upload_max_size', 5 );
        $max_bytes = $max_mo * 1048576;
        return min( $size, $max_bytes );
    }

    /**
     * Auto-compress and/or convert uploaded images.
     */
    public function handle_upload( $upload ) {
        if ( ! isset( $upload['file'] ) || ! file_exists( $upload['file'] ) ) {
            return $upload;
        }

        $mime = isset( $upload['type'] ) ? $upload['type'] : '';
        if ( strpos( $mime, 'image/' ) !== 0 ) {
            return $upload;
        }

        // Skip SVGs and already-optimized formats
        if ( in_array( $mime, array( 'image/svg+xml', 'image/webp', 'image/avif' ), true ) ) {
            return $upload;
        }

        // G-EDIT-05: Auto compress
        if ( get_option( 'ecodiag_auto_compress_upload', '0' ) === '1' ) {
            $quality = (int) get_option( 'ecodiag_compression_quality', 80 );
            $this->compress_image( $upload['file'], $quality );
        }

        // Auto convert to WebP/AVIF
        if ( get_option( 'ecodiag_auto_convert_upload', '0' ) === '1' ) {
            $format = get_option( 'ecodiag_conversion_format', 'webp' );
            $converted = $this->convert_image( $upload['file'], $format );
            if ( $converted ) {
                $upload['file'] = $converted['file'];
                $upload['url']  = $converted['url'];
                $upload['type'] = $converted['type'];
            }
        }

        return $upload;
    }

    /**
     * Compress an image file.
     */
    private function compress_image( $file, $quality ) {
        $editor = wp_get_image_editor( $file );
        if ( is_wp_error( $editor ) ) {
            return false;
        }
        $editor->set_quality( $quality );
        $result = $editor->save( $file );
        return ! is_wp_error( $result );
    }

    /**
     * Convert an image to WebP or AVIF.
     */
    private function convert_image( $file, $format ) {
        $editor = wp_get_image_editor( $file );
        if ( is_wp_error( $editor ) ) {
            return false;
        }

        $mime = $format === 'avif' ? 'image/avif' : 'image/webp';
        $ext  = $format === 'avif' ? 'avif' : 'webp';

        $info     = pathinfo( $file );
        $new_file = $info['dirname'] . '/' . $info['filename'] . '.' . $ext;

        $quality = (int) get_option( 'ecodiag_compression_quality', 80 );
        $editor->set_quality( $quality );
        $result = $editor->save( $new_file, $mime );

        if ( is_wp_error( $result ) ) {
            return false;
        }

        // Remove original
        if ( file_exists( $file ) && $new_file !== $file ) {
            wp_delete_file( $file );
        }

        $upload_dir = wp_upload_dir();
        $new_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $new_file );

        return array(
            'file' => $new_file,
            'url'  => $new_url,
            'type' => $mime,
        );
    }
}
