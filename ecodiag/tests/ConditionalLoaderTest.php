<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for EcoDiag_Conditional_Loader::should_load (private, accessed via Reflection).
 */
final class ConditionalLoaderTest extends TestCase {

    private static \ReflectionMethod $method;
    private EcoDiag_Conditional_Loader $loader;

    public static function setUpBeforeClass(): void {
        self::$method = new \ReflectionMethod( EcoDiag_Conditional_Loader::class, 'should_load' );
    }

    protected function setUp(): void {
        // Reset global test state
        $GLOBALS['_ecodiag_test_is_admin']     = false;
        $GLOBALS['_ecodiag_test_post_type']    = '';
        $GLOBALS['_ecodiag_test_queried_object_id'] = 0;
        $GLOBALS['_ecodiag_test_queried_object'] = null;
        $GLOBALS['_ecodiag_test_is_home']      = false;
        $GLOBALS['_ecodiag_test_is_front_page'] = false;
        $GLOBALS['_ecodiag_test_options']       = array();

        $this->loader = new EcoDiag_Conditional_Loader();
    }

    protected function tearDown(): void {
        unset(
            $GLOBALS['_ecodiag_test_is_admin'],
            $GLOBALS['_ecodiag_test_post_type'],
            $GLOBALS['_ecodiag_test_queried_object_id'],
            $GLOBALS['_ecodiag_test_queried_object'],
            $GLOBALS['_ecodiag_test_is_home'],
            $GLOBALS['_ecodiag_test_is_front_page']
        );
    }

    private function shouldLoad( array $rule ): bool {
        return self::$method->invoke( $this->loader, $rule );
    }

    // ─── mode: everywhere ───────────────────────────

    #[Test]
    public function everywhere_mode_always_returns_true(): void {
        $rule = array( 'plugin' => 'test', 'mode' => 'everywhere' );
        $this->assertTrue( $this->shouldLoad( $rule ) );
    }

    // ─── mode: nowhere ──────────────────────────────

    #[Test]
    public function nowhere_mode_always_returns_false(): void {
        $rule = array( 'plugin' => 'test', 'mode' => 'nowhere' );
        $this->assertFalse( $this->shouldLoad( $rule ) );
    }

    // ─── mode: specific pages ───────────────────────

    #[Test]
    public function specific_mode_returns_true_when_page_matches(): void {
        $GLOBALS['_ecodiag_test_queried_object_id'] = 42;
        $rule = array( 'plugin' => 'test', 'mode' => 'specific', 'pages' => array( '42', '100' ) );
        $this->assertTrue( $this->shouldLoad( $rule ) );
    }

    #[Test]
    public function specific_mode_returns_false_when_page_does_not_match(): void {
        $GLOBALS['_ecodiag_test_queried_object_id'] = 99;
        $rule = array( 'plugin' => 'test', 'mode' => 'specific', 'pages' => array( '42', '100' ) );
        $this->assertFalse( $this->shouldLoad( $rule ) );
    }

    #[Test]
    public function specific_mode_returns_false_when_no_pages_defined(): void {
        // mode=specific but empty pages → falls through to default (true)
        $rule = array( 'plugin' => 'test', 'mode' => 'specific', 'pages' => array() );
        $this->assertTrue( $this->shouldLoad( $rule ) );
    }

    // ─── mode: post_types ───────────────────────────

    #[Test]
    public function post_types_mode_matches_current_post_type(): void {
        $GLOBALS['_ecodiag_test_post_type'] = 'product';
        $rule = array( 'plugin' => 'woocommerce', 'mode' => 'post_types', 'post_types' => array( 'product', 'shop_order' ) );
        $this->assertTrue( $this->shouldLoad( $rule ) );
    }

    #[Test]
    public function post_types_mode_does_not_match_different_type(): void {
        $GLOBALS['_ecodiag_test_post_type'] = 'post';
        $rule = array( 'plugin' => 'woocommerce', 'mode' => 'post_types', 'post_types' => array( 'product', 'shop_order' ) );
        $this->assertFalse( $this->shouldLoad( $rule ) );
    }

    #[Test]
    public function post_types_mode_falls_back_to_queried_object_on_empty_post_type(): void {
        $GLOBALS['_ecodiag_test_post_type'] = '';
        $post = new WP_Post( array( 'ID' => 1, 'post_type' => 'page' ) );
        $GLOBALS['_ecodiag_test_queried_object'] = $post;
        $rule = array( 'plugin' => 'test', 'mode' => 'post_types', 'post_types' => array( 'page' ) );
        $this->assertTrue( $this->shouldLoad( $rule ) );
    }

    #[Test]
    public function post_types_mode_uses_page_type_for_homepage(): void {
        $GLOBALS['_ecodiag_test_post_type'] = '';
        $GLOBALS['_ecodiag_test_queried_object'] = null;
        $GLOBALS['_ecodiag_test_is_home'] = true;
        $rule = array( 'plugin' => 'test', 'mode' => 'post_types', 'post_types' => array( 'page' ) );
        $this->assertTrue( $this->shouldLoad( $rule ) );
    }

    #[Test]
    public function post_types_mode_uses_page_type_for_front_page(): void {
        $GLOBALS['_ecodiag_test_post_type'] = '';
        $GLOBALS['_ecodiag_test_queried_object'] = null;
        $GLOBALS['_ecodiag_test_is_front_page'] = true;
        $rule = array( 'plugin' => 'test', 'mode' => 'post_types', 'post_types' => array( 'page' ) );
        $this->assertTrue( $this->shouldLoad( $rule ) );
    }

    #[Test]
    public function post_types_mode_returns_false_on_homepage_when_page_not_in_list(): void {
        $GLOBALS['_ecodiag_test_post_type'] = '';
        $GLOBALS['_ecodiag_test_queried_object'] = null;
        $GLOBALS['_ecodiag_test_is_home'] = true;
        $rule = array( 'plugin' => 'test', 'mode' => 'post_types', 'post_types' => array( 'product' ) );
        $this->assertFalse( $this->shouldLoad( $rule ) );
    }

    // ─── Unknown mode defaults to true ──────────────

    #[Test]
    public function unknown_mode_returns_true(): void {
        $rule = array( 'plugin' => 'test', 'mode' => 'some_future_mode' );
        $this->assertTrue( $this->shouldLoad( $rule ) );
    }
}
