<?php
/**
 * Media Optimizer fixer: convert images to WebP/AVIF.
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles one-click image conversion to modern formats.
 */
final class EcoPress_Fixer_Media {

	/**
	 * Supported source formats for conversion.
	 *
	 * @var string[]
	 */
	private const CONVERTIBLE_MIMES = [
		'image/jpeg',
		'image/png',
		'image/gif',
	];

	/**
	 * Register AJAX handlers for media optimization.
	 */
	public static function register_ajax(): void {
		add_action( 'wp_ajax_ecopress_convert_images', [ self::class, 'ajax_convert_images' ] );
	}

	/**
	 * AJAX handler: convert post images to WebP.
	 */
	public static function ajax_convert_images(): void {
		check_ajax_referer( 'ecopress_fixer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissions insuffisantes.', 'ecopress-auditor' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( __( 'ID d\'article invalide.', 'ecopress-auditor' ), 400 );
		}

		$format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'webp';
		if ( ! in_array( $format, [ 'webp', 'avif' ], true ) ) {
			$format = 'webp';
		}

		$attachments = get_attached_media( '', $post_id );
		$converted   = 0;
		$errors      = [];

		foreach ( $attachments as $attachment ) {
			if ( ! in_array( $attachment->post_mime_type, self::CONVERTIBLE_MIMES, true ) ) {
				continue;
			}

			$result = self::convert_attachment( $attachment->ID, $format );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				++$converted;
			}
		}

		EcoPress_Analyzer::invalidate_cache( $post_id );

		wp_send_json_success( [
			'converted' => $converted,
			'errors'    => $errors,
			/* translators: %d: number of converted images */
			'message'   => sprintf( __( '%d image(s) convertie(s).', 'ecopress-auditor' ), $converted ),
		] );
	}

	/**
	 * Convert a single attachment to the specified format.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $format        Target format: 'webp' or 'avif'.
	 * @return true|\WP_Error True on success.
	 */
	private static function convert_attachment( int $attachment_id, string $format ): true|\WP_Error {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error(
				'ecopress_file_missing',
				__( 'Fichier source introuvable.', 'ecopress-auditor' )
			);
		}

		$editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		// Determine target mime type.
		$mime = 'webp' === $format ? 'image/webp' : 'image/avif';

		// Check if GD/Imagick supports the format.
		if ( ! self::supports_format( $format ) ) {
			return new \WP_Error(
				'ecopress_unsupported_format',
				/* translators: %s: image format name */
				sprintf( __( 'Le serveur ne supporte pas le format %s.', 'ecopress-auditor' ), strtoupper( $format ) )
			);
		}

		$path_info = pathinfo( $file_path );
		$new_path  = $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $format;

		$saved = $editor->save( $new_path, $mime );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		// Store original path in meta for potential undo.
		update_post_meta( $attachment_id, '_ecopress_original_file', $file_path );

		// Update attachment metadata.
		update_attached_file( $attachment_id, $saved['path'] );

		$metadata = wp_generate_attachment_metadata( $attachment_id, $saved['path'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Update mime type.
		wp_update_post( [
			'ID'             => $attachment_id,
			'post_mime_type' => $mime,
		] );

		return true;
	}

	/**
	 * Revert a converted attachment to its original file.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return true|\WP_Error True on success.
	 */
	public static function revert_attachment( int $attachment_id ): true|\WP_Error {
		$original = get_post_meta( $attachment_id, '_ecopress_original_file', true );
		if ( ! $original || ! file_exists( $original ) ) {
			return new \WP_Error(
				'ecopress_no_original',
				__( 'Fichier original introuvable.', 'ecopress-auditor' )
			);
		}

		$mime = wp_check_filetype( $original )['type'] ?? 'image/jpeg';

		update_attached_file( $attachment_id, $original );

		$metadata = wp_generate_attachment_metadata( $attachment_id, $original );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_update_post( [
			'ID'             => $attachment_id,
			'post_mime_type' => $mime,
		] );

		delete_post_meta( $attachment_id, '_ecopress_original_file' );

		return true;
	}

	/**
	 * Check if the server supports a given image format.
	 *
	 * @param string $format 'webp' or 'avif'.
	 * @return bool
	 */
	private static function supports_format( string $format ): bool {
		if ( 'webp' === $format ) {
			return function_exists( 'imagewebp' )
				|| ( extension_loaded( 'imagick' ) && \Imagick::queryFormats( 'WEBP' ) );
		}

		if ( 'avif' === $format ) {
			return function_exists( 'imageavif' )
				|| ( extension_loaded( 'imagick' ) && \Imagick::queryFormats( 'AVIF' ) );
		}

		return false;
	}
}

// Register AJAX hooks at load time.
EcoPress_Fixer_Media::register_ajax();
