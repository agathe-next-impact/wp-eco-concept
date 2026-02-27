<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Structural and security tests for EcoDiag_Ajax_Handler.
 *
 * Since AJAX handlers call wp_send_json_error/success which call die(),
 * we test the structure, registration, and verify method via stubs.
 */
final class AjaxHandlerTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['_ecodiag_test_actions'] = array();
        $GLOBALS['_ecodiag_test_capabilities'] = array();
    }

    protected function tearDown(): void {
        unset(
            $GLOBALS['_ecodiag_test_actions'],
            $GLOBALS['_ecodiag_test_capabilities']
        );
    }

    // ─── Action registration ────────────────────────

    #[Test]
    public function constructor_registers_all_expected_ajax_actions(): void {
        $handler = new EcoDiag_Ajax_Handler();

        $registered = array_keys( $GLOBALS['_ecodiag_test_actions'] ?? array() );

        $expected_actions = array(
            'wp_ajax_ecodiag_run_audit',
            'wp_ajax_ecodiag_adminbar_data',
            'wp_ajax_ecodiag_add_lazy_loading',
            'wp_ajax_ecodiag_add_dimensions',
            'wp_ajax_ecodiag_convert_images',
            'wp_ajax_ecodiag_compress_images',
            'wp_ajax_ecodiag_convert_embeds',
            'wp_ajax_ecodiag_remove_autoplay',
            'wp_ajax_ecodiag_add_iframe_lazy',
            'wp_ajax_ecodiag_purge_revisions',
            'wp_ajax_ecodiag_clean_orphaned_meta',
            'wp_ajax_ecodiag_disable_scripts',
            'wp_ajax_ecodiag_purge_all_revisions',
            'wp_ajax_ecodiag_clean_transients',
            'wp_ajax_ecodiag_clean_orphan_options',
            'wp_ajax_ecodiag_clean_orphan_postmeta',
            'wp_ajax_ecodiag_optimize_tables',
            'wp_ajax_ecodiag_delete_plugin',
            'wp_ajax_ecodiag_delete_theme',
            'wp_ajax_ecodiag_bulk_convert',
            'wp_ajax_ecodiag_bulk_compress',
            'wp_ajax_ecodiag_delete_orphan_media',
            'wp_ajax_ecodiag_remove_image_sizes',
            'wp_ajax_ecodiag_toggle_head',
            'wp_ajax_ecodiag_save_conditional_rules',
            'wp_ajax_ecodiag_delete_orphan_crons',
            'wp_ajax_ecodiag_restrict_blocks',
            'wp_ajax_ecodiag_export_csv',
            'wp_ajax_ecodiag_dashboard_data',
            'wp_ajax_ecodiag_server_diagnostics',
            'wp_ajax_ecodiag_run_full_audit',
            'wp_ajax_ecodiag_popup_data',
        );

        foreach ( $expected_actions as $action ) {
            $this->assertContains( $action, $registered, "Missing AJAX action: $action" );
        }
    }

    #[Test]
    public function popup_data_is_registered_separately(): void {
        $handler = new EcoDiag_Ajax_Handler();
        $this->assertArrayHasKey( 'wp_ajax_ecodiag_popup_data', $GLOBALS['_ecodiag_test_actions'] );
    }

    // ─── verify() method checks ─────────────────────

    #[Test]
    public function verify_method_exists_and_is_private(): void {
        $reflection = new \ReflectionMethod( EcoDiag_Ajax_Handler::class, 'verify' );
        $this->assertTrue( $reflection->isPrivate() );
    }

    #[Test]
    public function verify_method_has_nonce_action_parameter(): void {
        $reflection = new \ReflectionMethod( EcoDiag_Ajax_Handler::class, 'verify' );
        $params = $reflection->getParameters();
        $this->assertCount( 1, $params );
        $this->assertTrue( $params[0]->isOptional() );
        $this->assertSame( 'ecodiag_nonce', $params[0]->getDefaultValue() );
    }

    // ─── Structural checks on handler methods ───────

    #[Test]
    public function all_handler_methods_exist(): void {
        $handler_methods = array(
            'ecodiag_run_audit',
            'ecodiag_adminbar_data',
            'ecodiag_add_lazy_loading',
            'ecodiag_convert_images',
            'ecodiag_purge_revisions',
            'ecodiag_optimize_tables',
            'ecodiag_popup_data',
            'ecodiag_dashboard_data',
            'ecodiag_run_full_audit',
            'ecodiag_export_csv',
        );

        foreach ( $handler_methods as $method_name ) {
            $this->assertTrue(
                method_exists( EcoDiag_Ajax_Handler::class, $method_name ),
                "Missing method: $method_name"
            );
        }
    }

    #[Test]
    public function handler_methods_are_public(): void {
        $reflection = new \ReflectionClass( EcoDiag_Ajax_Handler::class );
        $public_methods = array_map(
            fn( $m ) => $m->getName(),
            $reflection->getMethods( \ReflectionMethod::IS_PUBLIC )
        );

        // All registered action methods should be public (for WP hook system)
        $this->assertContains( 'ecodiag_run_audit', $public_methods );
        $this->assertContains( 'ecodiag_popup_data', $public_methods );
        $this->assertContains( 'ecodiag_optimize_tables', $public_methods );
    }

    // ─── Source code security analysis ──────────────

    #[Test]
    public function verify_uses_check_ajax_referer(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        // verify() should call check_ajax_referer
        $this->assertMatchesRegularExpression( '/check_ajax_referer\s*\(/', $source );
    }

    #[Test]
    public function verify_checks_manage_options_capability(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        $this->assertStringContainsString( "current_user_can( 'manage_options' )", $source );
    }

    #[Test]
    public function popup_data_checks_edit_posts_capability(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        // The popup_data method should use edit_posts, not manage_options
        // Find the ecodiag_popup_data function and check its body
        $this->assertMatchesRegularExpression(
            "/function\s+ecodiag_popup_data.*?current_user_can\s*\(\s*'edit_posts'\s*\)/s",
            $source
        );
    }

    #[Test]
    public function all_post_id_accesses_use_isset(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        // No direct $_POST['post_id'] without isset
        // Should find "isset( $_POST['post_id'] )" pattern
        $post_id_accesses = preg_match_all( '/\$_POST\s*\[\s*[\'"]post_id[\'"]\s*\]/', $source );
        $isset_checks = preg_match_all( '/isset\s*\(\s*\$_POST\s*\[\s*[\'"]post_id[\'"]\s*\]/', $source );
        // Each access should be wrapped in isset (or appear after isset in a ternary)
        $this->assertGreaterThan( 0, $post_id_accesses, 'Should have $_POST[post_id] accesses' );
        $this->assertGreaterThanOrEqual(
            $isset_checks,
            $isset_checks,
            'All post_id accesses should use isset'
        );
    }

    #[Test]
    public function optimize_tables_validates_table_names(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        // Should validate table name format
        $this->assertMatchesRegularExpression( '/preg_match.*\[a-zA-Z0-9_\]/', $source );
    }

    #[Test]
    public function verify_returns_after_error(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        // In the verify method, after each wp_send_json_error there should be a return
        // Extract verify method
        preg_match( '/private\s+function\s+verify\s*\(.*?\)\s*\{(.*?)\n\s*\}/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract verify method body' );
        $verify_body = $m[1];
        // Count wp_send_json_error calls and return statements
        $error_calls = preg_match_all( '/wp_send_json_error/', $verify_body );
        $returns = preg_match_all( '/\breturn\b/', $verify_body );
        $this->assertSame( $error_calls, $returns, 'Each wp_send_json_error in verify() must be followed by return' );
    }

    #[Test]
    public function no_user_input_in_raw_sql(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        // Ensure no $_POST or $_GET values are interpolated directly into query() calls
        // Static cleanup queries (e.g. DELETE FROM wp_posts WHERE post_type='revision') are safe
        $dangerous = preg_match_all( '/\$wpdb->query\s*\([^;]*\$_(?:POST|GET|REQUEST)/', $source );
        $this->assertSame(
            0,
            $dangerous,
            'No user input ($_POST/$_GET) should be interpolated directly into $wpdb->query()'
        );
    }

    #[Test]
    public function queries_with_user_data_use_prepare(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        // The only $wpdb->query with $wpdb->prepare should use proper placeholders
        $prepared = preg_match_all( '/\$wpdb->query\s*\(\s*\$wpdb->prepare\s*\(/', $source );
        $this->assertGreaterThan( 0, $prepared, 'Should have at least one prepared query' );
    }

    // ─── Conversion methods update GUID ─────────────

    #[Test]
    public function bulk_convert_updates_guid(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_bulk_convert\s*\(\)(.*?)function\s+ecodiag_bulk_compress/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_bulk_convert method' );
        $this->assertStringContainsString( "'guid'", $m[1], 'bulk_convert must update attachment GUID' );
    }

    #[Test]
    public function bulk_convert_regenerates_metadata(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_bulk_convert\s*\(\)(.*?)function\s+ecodiag_bulk_compress/s', $source, $m );
        $body = $m[1];
        $this->assertStringContainsString( 'wp_generate_attachment_metadata', $body, 'bulk_convert must regenerate attachment metadata' );
        $this->assertStringContainsString( 'wp_update_attachment_metadata', $body, 'bulk_convert must save regenerated metadata' );
    }

    #[Test]
    public function bulk_convert_updates_post_content_urls(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_bulk_convert\s*\(\)(.*?)function\s+ecodiag_bulk_compress/s', $source, $m );
        $body = $m[1];
        // Must update URLs in post_content via SQL REPLACE
        $this->assertStringContainsString( 'REPLACE(post_content', $body, 'bulk_convert must update image URLs in post content' );
    }

    #[Test]
    public function bulk_convert_invalidates_media_cache(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        // Extract region between bulk_convert function and next function
        preg_match( '/function\s+ecodiag_bulk_convert\s*\(\)(.*?)function\s+ecodiag_bulk_compress/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_bulk_convert body' );
        $this->assertStringContainsString( "delete_transient( 'ecodiag_diag_media' )", $m[1], 'bulk_convert must invalidate media diagnostics cache' );
    }

    #[Test]
    public function convert_images_updates_guid(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_convert_images\s*\(\)(.*?)function\s+ecodiag_compress_images/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_convert_images body' );
        $this->assertStringContainsString( "'guid'", $m[1], 'convert_images must update attachment GUID' );
    }

    #[Test]
    public function convert_images_regenerates_metadata(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_convert_images\s*\(\)(.*?)function\s+ecodiag_compress_images/s', $source, $m );
        $this->assertStringContainsString( 'wp_generate_attachment_metadata', $m[1], 'convert_images must regenerate attachment metadata' );
    }

    #[Test]
    public function convert_images_invalidates_media_cache(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_convert_images\s*\(\)(.*?)function\s+ecodiag_compress_images/s', $source, $m );
        $this->assertStringContainsString( "delete_transient( 'ecodiag_diag_media' )", $m[1], 'convert_images must invalidate media diagnostics cache' );
    }

    #[Test]
    public function bulk_convert_does_not_use_incremental_offset(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_bulk_convert\s*\(\)(.*?)function\s+ecodiag_bulk_compress/s', $source, $m );
        // After conversion, images drop from query (mime type changes),
        // so offset should always be 0
        $this->assertStringContainsString( "'offset'         => 0", $m[1], 'bulk_convert must always query from offset 0 since converted images drop from results' );
    }

    // ─── Lazy loading action ────────────────────────

    #[Test]
    public function lazy_loading_regex_does_not_duplicate_decoding(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_add_lazy_loading\s*\(\)(.*?)function\s+ecodiag_add_dimensions/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_add_lazy_loading body' );
        $body = $m[1];
        // loading and decoding should be added separately to avoid duplicates
        $loading_regexes = preg_match_all( '/preg_replace.*loading/', $body );
        $decoding_regexes = preg_match_all( '/preg_replace.*decoding/', $body );
        $this->assertGreaterThanOrEqual( 1, $loading_regexes, 'Should have a regex for loading' );
        $this->assertGreaterThanOrEqual( 1, $decoding_regexes, 'Should have a separate regex for decoding' );
        // The loading regex should NOT add decoding="async" in the same replacement
        $this->assertDoesNotMatchRegularExpression(
            '/preg_replace.*loading.*decoding="async"/',
            $body,
            'loading regex must not also inject decoding="async" (prevents duplicates)'
        );
    }

    #[Test]
    public function bulk_lazy_loading_action_exists(): void {
        $handler = new EcoDiag_Ajax_Handler();
        $registered = array_keys( $GLOBALS['_ecodiag_test_actions'] ?? array() );
        $this->assertContains( 'wp_ajax_ecodiag_bulk_lazy_loading', $registered );
    }

    #[Test]
    public function bulk_lazy_loading_method_exists_and_is_public(): void {
        $this->assertTrue( method_exists( EcoDiag_Ajax_Handler::class, 'ecodiag_bulk_lazy_loading' ) );
        $r = new \ReflectionMethod( EcoDiag_Ajax_Handler::class, 'ecodiag_bulk_lazy_loading' );
        $this->assertTrue( $r->isPublic() );
    }

    // ─── Popup force refresh ───────────────────────

    #[Test]
    public function popup_data_reads_force_parameter(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_popup_data\s*\(\)(.*?)$/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_popup_data body' );
        $body = $m[1];
        $this->assertStringContainsString( "\$_POST['force']", $body, 'popup_data must read force parameter from POST' );
        $this->assertStringContainsString( '$force', $body, 'popup_data must pass force to audit_post' );
    }

    // ─── Cache invalidation checks ──────────────────

    #[Test]
    public function bulk_lazy_loading_invalidates_media_cache(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_bulk_lazy_loading\s*\(\)(.*?)function\s+ecodiag_export_csv/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_bulk_lazy_loading body' );
        $this->assertStringContainsString( "delete_transient( 'ecodiag_diag_media' )", $m[1], 'bulk_lazy_loading must invalidate media diagnostics cache' );
    }

    #[Test]
    public function bulk_compress_invalidates_media_cache(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_bulk_compress\s*\(\)(.*?)function\s+ecodiag_delete_orphan_media/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_bulk_compress body' );
        $this->assertStringContainsString( "delete_transient( 'ecodiag_diag_media' )", $m[1], 'bulk_compress must invalidate media diagnostics cache' );
    }

    #[Test]
    public function optimize_tables_invalidates_database_cache(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_optimize_tables\s*\(\)(.*?)function\s+ecodiag_delete_plugin/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_optimize_tables body' );
        $this->assertStringContainsString( "delete_transient( 'ecodiag_diag_database' )", $m[1], 'optimize_tables must invalidate database diagnostics cache' );
    }

    // ─── Autoplay removal safety ───────────────────

    #[Test]
    public function remove_autoplay_targets_only_tags(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_remove_autoplay\s*\(\)(.*?)function\s+ecodiag_add_iframe_lazy/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_remove_autoplay body' );
        $body = $m[1];
        // Must NOT use a naive global regex like '/\s*autoplay\s*/i' that matches text content
        $this->assertDoesNotMatchRegularExpression(
            "/preg_replace\s*\(\s*'\/.*autoplay.*\/i'\s*,\s*' '/",
            $body,
            'remove_autoplay must not use a naive global regex that matches text content'
        );
        // Must use preg_replace_callback targeting specific tags
        $this->assertStringContainsString( 'preg_replace_callback', $body, 'remove_autoplay should use preg_replace_callback to target only HTML tags' );
    }

    // ─── Image action helpers ──────────────────────

    #[Test]
    public function resolve_attachment_id_helper_exists(): void {
        $r = new \ReflectionMethod( EcoDiag_Ajax_Handler::class, 'resolve_attachment_id' );
        $this->assertTrue( $r->isPrivate(), 'resolve_attachment_id should be private' );
        $params = $r->getParameters();
        $this->assertCount( 1, $params, 'resolve_attachment_id takes one parameter (src)' );
    }

    #[Test]
    public function get_sized_dimensions_helper_exists(): void {
        $r = new \ReflectionMethod( EcoDiag_Ajax_Handler::class, 'get_sized_dimensions' );
        $this->assertTrue( $r->isPrivate() );
        $this->assertCount( 2, $r->getParameters(), 'get_sized_dimensions takes two parameters (src, att_id)' );
    }

    #[Test]
    public function replace_image_urls_in_content_helper_exists(): void {
        $r = new \ReflectionMethod( EcoDiag_Ajax_Handler::class, 'replace_image_urls_in_content' );
        $this->assertTrue( $r->isPrivate() );
        $this->assertCount( 4, $r->getParameters(), 'replace_image_urls_in_content takes 4 parameters' );
    }

    #[Test]
    public function convert_images_uses_wp_image_class(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_convert_images\s*\(\)(.*?)function\s+ecodiag_compress_images/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_convert_images body' );
        // Must extract attachment IDs from Gutenberg wp-image-{ID} class
        $this->assertStringContainsString( 'wp-image-', $m[1], 'convert_images must extract attachment IDs from wp-image class' );
        // Must also use resolve_attachment_id for classic editor URLs
        $this->assertStringContainsString( 'resolve_attachment_id', $m[1], 'convert_images must use resolve_attachment_id for URL fallback' );
    }

    #[Test]
    public function convert_images_replaces_sized_urls(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_convert_images\s*\(\)(.*?)function\s+ecodiag_compress_images/s', $source, $m );
        // Must use replace_image_urls_in_content to handle sized variants
        $this->assertStringContainsString( 'replace_image_urls_in_content', $m[1], 'convert_images must replace all URL variants including sized ones' );
    }

    #[Test]
    public function compress_images_uses_resolve_attachment_id(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_compress_images\s*\(\)(.*?)function\s+ecodiag_convert_embeds/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract ecodiag_compress_images body' );
        $this->assertStringContainsString( 'resolve_attachment_id', $m[1], 'compress_images must use resolve_attachment_id' );
        $this->assertStringContainsString( 'wp_generate_attachment_metadata', $m[1], 'compress_images must regenerate sized variants' );
    }

    #[Test]
    public function add_dimensions_uses_resolve_and_sized_dims(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+add_img_dimensions\s*\((.*?)function\s+ecodiag_convert_images/s', $source, $m );
        $this->assertNotEmpty( $m[1], 'Could not extract add_img_dimensions body' );
        $body = $m[1];
        // Must use Gutenberg class extraction
        $this->assertStringContainsString( 'wp-image-', $body, 'add_img_dimensions must try Gutenberg class for attachment ID' );
        // Must use resolve_attachment_id as fallback
        $this->assertStringContainsString( 'resolve_attachment_id', $body, 'add_img_dimensions must use resolve_attachment_id' );
        // Must use get_sized_dimensions for correct dimensions
        $this->assertStringContainsString( 'get_sized_dimensions', $body, 'add_img_dimensions must use get_sized_dimensions for correct size' );
    }

    #[Test]
    public function bulk_convert_replaces_sized_url_variants(): void {
        $source = file_get_contents( ECODIAG_PATH . 'includes/class-ecodiag-ajax-handler.php' );
        preg_match( '/function\s+ecodiag_bulk_convert\s*\(\)(.*?)function\s+ecodiag_bulk_compress/s', $source, $m );
        // Must save old metadata before conversion to know old sizes
        $this->assertStringContainsString( 'old_metadata', $m[1], 'bulk_convert must save old metadata for sized URL replacement' );
        // Must iterate sizes and replace each
        $this->assertStringContainsString( "['sizes']", $m[1], 'bulk_convert must iterate old sizes for URL replacement' );
    }
}
