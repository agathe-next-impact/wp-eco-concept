<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for EcoDiag_Analyzer::parse_img_tag (private, accessed via Reflection).
 */
final class ParseImgTagTest extends TestCase {

    private static \ReflectionMethod $method;

    public static function setUpBeforeClass(): void {
        self::$method = new \ReflectionMethod( EcoDiag_Analyzer::class, 'parse_img_tag' );
    }

    private function parse( string $tag, string $base = 'https://example.com/' ): array {
        return self::$method->invoke( null, $tag, $base );
    }

    // ─── src extraction ─────────────────────────────

    #[Test]
    public function extracts_src_with_double_quotes(): void {
        $result = $this->parse( '<img src="photo.jpg">' );
        $this->assertSame( 'https://example.com/photo.jpg', $result['src'] );
    }

    #[Test]
    public function extracts_src_with_single_quotes(): void {
        $result = $this->parse( "<img src='photo.jpg'>" );
        $this->assertSame( 'https://example.com/photo.jpg', $result['src'] );
    }

    #[Test]
    public function returns_empty_src_when_no_src_attribute(): void {
        $result = $this->parse( '<img alt="test">' );
        $this->assertSame( '', $result['src'] );
    }

    // ─── lazy loading detection ─────────────────────

    #[Test]
    public function detects_loading_lazy(): void {
        $result = $this->parse( '<img src="a.jpg" loading="lazy">' );
        $this->assertTrue( $result['has_lazy'] );
    }

    #[Test]
    public function detects_loading_lazy_case_insensitive(): void {
        $result = $this->parse( '<img src="a.jpg" Loading="Lazy">' );
        $this->assertTrue( $result['has_lazy'] );
    }

    #[Test]
    public function no_lazy_when_missing(): void {
        $result = $this->parse( '<img src="a.jpg">' );
        $this->assertFalse( $result['has_lazy'] );
    }

    #[Test]
    public function no_lazy_when_loading_eager(): void {
        $result = $this->parse( '<img src="a.jpg" loading="eager">' );
        $this->assertFalse( $result['has_lazy'] );
    }

    // ─── decoding detection ─────────────────────────

    #[Test]
    public function detects_decoding_async(): void {
        $result = $this->parse( '<img src="a.jpg" decoding="async">' );
        $this->assertTrue( $result['has_decoding'] );
    }

    #[Test]
    public function no_decoding_when_sync(): void {
        $result = $this->parse( '<img src="a.jpg" decoding="sync">' );
        $this->assertFalse( $result['has_decoding'] );
    }

    // ─── width/height detection ─────────────────────

    #[Test]
    public function detects_width_and_height(): void {
        $result = $this->parse( '<img src="a.jpg" width="300" height="200">' );
        $this->assertTrue( $result['has_width'] );
        $this->assertTrue( $result['has_height'] );
        $this->assertSame( 300, $result['width'] );
        $this->assertSame( 200, $result['height'] );
    }

    #[Test]
    public function detects_width_without_quotes(): void {
        $result = $this->parse( '<img src="a.jpg" width=300 height=200>' );
        $this->assertTrue( $result['has_width'] );
        $this->assertTrue( $result['has_height'] );
        $this->assertSame( 300, $result['width'] );
        $this->assertSame( 200, $result['height'] );
    }

    #[Test]
    public function missing_width_height(): void {
        $result = $this->parse( '<img src="a.jpg">' );
        $this->assertFalse( $result['has_width'] );
        $this->assertFalse( $result['has_height'] );
        $this->assertSame( 0, $result['width'] );
        $this->assertSame( 0, $result['height'] );
    }

    // ─── alt attribute ──────────────────────────────

    #[Test]
    public function detects_alt_attribute(): void {
        $result = $this->parse( '<img src="a.jpg" alt="A cat photo">' );
        $this->assertTrue( $result['has_alt'] );
        $this->assertFalse( $result['alt_empty'] );
    }

    #[Test]
    public function detects_empty_alt(): void {
        $result = $this->parse( '<img src="a.jpg" alt="">' );
        $this->assertTrue( $result['has_alt'] );
        $this->assertTrue( $result['alt_empty'] );
    }

    #[Test]
    public function detects_missing_alt(): void {
        $result = $this->parse( '<img src="a.jpg">' );
        $this->assertFalse( $result['has_alt'] );
    }

    // ─── srcset detection ───────────────────────────

    #[Test]
    public function detects_srcset(): void {
        $result = $this->parse( '<img src="a.jpg" srcset="a-300.jpg 300w, a-600.jpg 600w">' );
        $this->assertTrue( $result['has_srcset'] );
    }

    #[Test]
    public function no_srcset_when_absent(): void {
        $result = $this->parse( '<img src="a.jpg">' );
        $this->assertFalse( $result['has_srcset'] );
    }

    // ─── has_modern_source default ──────────────────

    #[Test]
    public function has_modern_source_defaults_to_false(): void {
        $result = $this->parse( '<img src="a.jpg">' );
        $this->assertFalse( $result['has_modern_source'] );
    }

    // ─── Full attributes tag ────────────────────────

    #[Test]
    public function parses_fully_attributed_tag(): void {
        $tag = '<img src="photo.webp" loading="lazy" decoding="async" width="800" height="600" alt="Test photo" srcset="photo-400.webp 400w, photo-800.webp 800w">';
        $result = $this->parse( $tag );
        $this->assertSame( 'https://example.com/photo.webp', $result['src'] );
        $this->assertTrue( $result['has_lazy'] );
        $this->assertTrue( $result['has_decoding'] );
        $this->assertTrue( $result['has_width'] );
        $this->assertTrue( $result['has_height'] );
        $this->assertSame( 800, $result['width'] );
        $this->assertSame( 600, $result['height'] );
        $this->assertTrue( $result['has_alt'] );
        $this->assertFalse( $result['alt_empty'] );
        $this->assertTrue( $result['has_srcset'] );
        $this->assertFalse( $result['has_modern_source'] );
    }
}
