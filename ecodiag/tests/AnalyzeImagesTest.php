<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for EcoDiag_Analyzer::analyze_images (private, accessed via Reflection).
 */
final class AnalyzeImagesTest extends TestCase {

    private static \ReflectionMethod $method;

    public static function setUpBeforeClass(): void {
        self::$method = new \ReflectionMethod( EcoDiag_Analyzer::class, 'analyze_images' );
    }

    protected function setUp(): void {
        // Default max weight = 200 Ko
        $GLOBALS['_ecodiag_test_options']['ecodiag_image_max_weight'] = 200;
    }

    protected function tearDown(): void {
        unset( $GLOBALS['_ecodiag_test_options']['ecodiag_image_max_weight'] );
    }

    private function analyze( array $images, array $resources = array() ): array {
        return self::$method->invoke( null, $images, $resources );
    }

    private function makeImage( array $overrides = array() ): array {
        return array_merge( array(
            'tag'               => '<img src="photo.jpg">',
            'src'               => 'https://example.com/photo.jpg',
            'has_lazy'          => false,
            'has_decoding'      => false,
            'has_width'         => false,
            'has_height'        => false,
            'has_alt'           => false,
            'alt_empty'         => false,
            'has_srcset'        => false,
            'has_modern_source' => false,
            'width'             => 0,
            'height'            => 0,
        ), $overrides );
    }

    // ─── P-IMG-01: WebP/AVIF format ────────────────

    #[Test]
    public function flags_non_webp_avif_image(): void {
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/photo.jpg' ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertContains( 'P-IMG-01', $refs );
    }

    #[Test]
    public function does_not_flag_webp_image(): void {
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/photo.webp' ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-01', $refs );
    }

    #[Test]
    public function does_not_flag_avif_image(): void {
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/photo.avif' ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-01', $refs );
    }

    #[Test]
    public function does_not_flag_svg_image(): void {
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/icon.svg' ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-01', $refs );
    }

    #[Test]
    public function does_not_flag_jpg_with_modern_source(): void {
        $images = array( $this->makeImage( array(
            'src'               => 'https://example.com/photo.jpg',
            'has_modern_source' => true,
        ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-01', $refs );
    }

    // ─── P-IMG-02: Lazy loading ────────────────────

    #[Test]
    public function flags_missing_lazy_loading(): void {
        $images = array( $this->makeImage( array( 'has_lazy' => false ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertContains( 'P-IMG-02', $refs );
    }

    #[Test]
    public function does_not_flag_with_lazy_loading(): void {
        $images = array( $this->makeImage( array( 'has_lazy' => true ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-02', $refs );
    }

    // ─── P-IMG-03: Decoding async ──────────────────

    #[Test]
    public function flags_missing_decoding_async(): void {
        $images = array( $this->makeImage( array( 'has_decoding' => false ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertContains( 'P-IMG-03', $refs );
    }

    #[Test]
    public function does_not_flag_with_decoding_async(): void {
        $images = array( $this->makeImage( array( 'has_decoding' => true ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-03', $refs );
    }

    // ─── P-IMG-05: Width/Height ────────────────────

    #[Test]
    public function flags_missing_width_or_height(): void {
        $images = array( $this->makeImage( array( 'has_width' => true, 'has_height' => false ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertContains( 'P-IMG-05', $refs );
    }

    #[Test]
    public function does_not_flag_with_both_dimensions(): void {
        $images = array( $this->makeImage( array( 'has_width' => true, 'has_height' => true ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-05', $refs );
    }

    // ─── P-IMG-06: Image too heavy ─────────────────

    #[Test]
    public function flags_heavy_image(): void {
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/big.jpg' ) ) );
        $resources = array( array( 'url' => 'https://example.com/big.jpg', 'size' => 300 * 1024 ) );
        $result = $this->analyze( $images, $resources );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertContains( 'P-IMG-06', $refs );
    }

    #[Test]
    public function does_not_flag_lightweight_image(): void {
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/small.jpg' ) ) );
        $resources = array( array( 'url' => 'https://example.com/small.jpg', 'size' => 50 * 1024 ) );
        $result = $this->analyze( $images, $resources );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-06', $refs );
    }

    #[Test]
    public function matches_resource_by_normalized_url(): void {
        // Image src has no query string but resource URL has one
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/photo.jpg' ) ) );
        $resources = array( array( 'url' => 'https://example.com/photo.jpg?v=1', 'size' => 300 * 1024 ) );
        $result = $this->analyze( $images, $resources );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertContains( 'P-IMG-06', $refs );
    }

    // ─── P-IMG-08: Missing alt ─────────────────────

    #[Test]
    public function flags_missing_alt(): void {
        $images = array( $this->makeImage( array( 'has_alt' => false ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertContains( 'P-IMG-08', $refs );
    }

    #[Test]
    public function does_not_flag_present_alt(): void {
        $images = array( $this->makeImage( array( 'has_alt' => true ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-08', $refs );
    }

    // ─── P-IMG-09: Missing srcset ──────────────────

    #[Test]
    public function flags_missing_srcset_for_jpg(): void {
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/photo.jpg', 'has_srcset' => false ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertContains( 'P-IMG-09', $refs );
    }

    #[Test]
    public function does_not_flag_missing_srcset_for_svg(): void {
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/icon.svg', 'has_srcset' => false ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-09', $refs );
    }

    #[Test]
    public function does_not_flag_missing_srcset_for_gif(): void {
        $images = array( $this->makeImage( array( 'src' => 'https://example.com/anim.gif', 'has_srcset' => false ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-09', $refs );
    }

    #[Test]
    public function does_not_flag_present_srcset(): void {
        $images = array( $this->makeImage( array( 'has_srcset' => true ) ) );
        $result = $this->analyze( $images );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertNotContains( 'P-IMG-09', $refs );
    }

    // ─── Perfect image has no issues ────────────────

    #[Test]
    public function perfect_image_has_no_issues(): void {
        $images = array( $this->makeImage( array(
            'src'               => 'https://example.com/photo.webp',
            'has_lazy'          => true,
            'has_decoding'      => true,
            'has_width'         => true,
            'has_height'        => true,
            'has_alt'           => true,
            'has_srcset'        => true,
            'has_modern_source' => false,
        ) ) );
        $resources = array( array( 'url' => 'https://example.com/photo.webp', 'size' => 50 * 1024 ) );
        $result = $this->analyze( $images, $resources );
        $this->assertEmpty( $result[0]['issues'] );
    }

    // ─── Multiple images ────────────────────────────

    #[Test]
    public function analyzes_multiple_images_independently(): void {
        $images = array(
            $this->makeImage( array( 'src' => 'https://example.com/good.webp', 'has_lazy' => true, 'has_decoding' => true, 'has_width' => true, 'has_height' => true, 'has_alt' => true, 'has_srcset' => true ) ),
            $this->makeImage( array( 'src' => 'https://example.com/bad.jpg' ) ),
        );
        $result = $this->analyze( $images );
        $this->assertEmpty( $result[0]['issues'] );
        $this->assertNotEmpty( $result[1]['issues'] );
    }
}
