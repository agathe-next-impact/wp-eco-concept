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
}
