<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for EcoDiag_Analyzer::extract_images (private, accessed via Reflection).
 */
final class ExtractImagesTest extends TestCase {

    private static \ReflectionMethod $method;

    public static function setUpBeforeClass(): void {
        self::$method = new \ReflectionMethod( EcoDiag_Analyzer::class, 'extract_images' );
    }

    private function extract( string $html, string $base = 'https://example.com/' ): array {
        return self::$method->invoke( null, $html, $base );
    }

    // ─── Standard <img> tags ────────────────────────

    #[Test]
    public function extracts_standard_img_tag(): void {
        $html = '<html><body><img src="photo.jpg" alt="Photo"></body></html>';
        $result = $this->extract( $html );
        $this->assertCount( 1, $result );
        $this->assertSame( 'https://example.com/photo.jpg', $result[0]['src'] );
    }

    #[Test]
    public function extracts_self_closing_img_tag(): void {
        $html = '<img src="test.png" />';
        $result = $this->extract( $html );
        $this->assertCount( 1, $result );
        $this->assertSame( 'https://example.com/test.png', $result[0]['src'] );
    }

    #[Test]
    public function extracts_img_without_self_closing_slash(): void {
        $html = '<img src="test.png">';
        $result = $this->extract( $html );
        $this->assertCount( 1, $result );
    }

    #[Test]
    public function extracts_multiple_images(): void {
        $html = '<img src="a.jpg"><img src="b.png"><img src="c.webp">';
        $result = $this->extract( $html );
        $this->assertCount( 3, $result );
    }

    #[Test]
    public function resolves_absolute_urls(): void {
        $html = '<img src="https://cdn.example.com/img.jpg">';
        $result = $this->extract( $html );
        $this->assertSame( 'https://cdn.example.com/img.jpg', $result[0]['src'] );
    }

    #[Test]
    public function resolves_protocol_relative_urls(): void {
        $html = '<img src="//cdn.example.com/img.jpg">';
        $result = $this->extract( $html );
        $this->assertSame( 'https://cdn.example.com/img.jpg', $result[0]['src'] );
    }

    #[Test]
    public function resolves_root_relative_urls(): void {
        $html = '<img src="/wp-content/uploads/img.jpg">';
        $result = $this->extract( $html, 'https://example.com/blog/' );
        $this->assertSame( 'https://example.com/wp-content/uploads/img.jpg', $result[0]['src'] );
    }

    // ─── Deduplication ──────────────────────────────

    #[Test]
    public function deduplicates_identical_src(): void {
        $html = '<img src="photo.jpg"><img src="photo.jpg">';
        $result = $this->extract( $html );
        $this->assertCount( 1, $result );
    }

    // ─── <picture> elements ─────────────────────────

    #[Test]
    public function extracts_img_inside_picture(): void {
        $html = '<picture><source srcset="photo.webp" type="image/webp"><img src="photo.jpg" alt="Test"></picture>';
        $result = $this->extract( $html );
        $this->assertCount( 1, $result );
        $this->assertSame( 'https://example.com/photo.jpg', $result[0]['src'] );
    }

    #[Test]
    public function detects_modern_source_in_picture(): void {
        $html = '<picture><source srcset="photo.webp" type="image/webp"><img src="photo.jpg"></picture>';
        $result = $this->extract( $html );
        $this->assertCount( 1, $result );
        $this->assertTrue( $result[0]['has_modern_source'] );
    }

    #[Test]
    public function detects_avif_source_in_picture(): void {
        $html = '<picture><source srcset="photo.avif" type="image/avif"><img src="photo.jpg"></picture>';
        $result = $this->extract( $html );
        $this->assertTrue( $result[0]['has_modern_source'] );
    }

    #[Test]
    public function picture_without_modern_source(): void {
        $html = '<picture><source srcset="photo-large.jpg" media="(min-width: 800px)"><img src="photo.jpg"></picture>';
        $result = $this->extract( $html );
        $this->assertFalse( $result[0]['has_modern_source'] );
    }

    #[Test]
    public function picture_does_not_duplicate_already_seen_img(): void {
        // img with same src already found outside <picture>
        $html = '<img src="photo.jpg"><picture><source type="image/webp" srcset="photo.webp"><img src="photo.jpg"></picture>';
        $result = $this->extract( $html );
        // The first img is matched in pass 1, the <picture>'s img is deduplicated
        $this->assertCount( 1, $result );
    }

    // ─── data-src lazy loading ──────────────────────

    #[Test]
    public function extracts_data_src_lazy_images(): void {
        $html = '<img data-src="lazy.jpg" src="placeholder.gif" class="lazyload">';
        $result = $this->extract( $html );
        // The placeholder is matched first, then data-src is a different URL
        $this->assertCount( 2, $result );
        $srcs = array_column( $result, 'src' );
        $this->assertContains( 'https://example.com/lazy.jpg', $srcs );
        $this->assertContains( 'https://example.com/placeholder.gif', $srcs );
    }

    #[Test]
    public function data_src_deduplication_with_regular_src(): void {
        // data-src same as another img's src → deduplicated
        $html = '<img src="photo.jpg"><img data-src="photo.jpg" src="placeholder.gif">';
        $result = $this->extract( $html );
        // photo.jpg matched first, placeholder.gif matched second, data-src photo.jpg deduplicated
        $this->assertCount( 2, $result );
    }

    // ─── Empty / edge cases ─────────────────────────

    #[Test]
    public function returns_empty_for_no_images(): void {
        $html = '<html><body><p>No images here</p></body></html>';
        $result = $this->extract( $html );
        $this->assertCount( 0, $result );
    }

    #[Test]
    public function returns_empty_for_empty_html(): void {
        $result = $this->extract( '' );
        $this->assertCount( 0, $result );
    }

    #[Test]
    public function ignores_img_without_src(): void {
        $html = '<img alt="broken">';
        $result = $this->extract( $html );
        // parse_img_tag returns src='' which is filtered by the empty check
        $this->assertCount( 0, $result );
    }

    // ─── Case insensitivity ─────────────────────────

    #[Test]
    public function handles_uppercase_img_tags(): void {
        $html = '<IMG SRC="photo.jpg" ALT="Test">';
        $result = $this->extract( $html );
        $this->assertCount( 1, $result );
    }

    #[Test]
    public function handles_mixed_case_attributes(): void {
        $html = '<img SRC="photo.jpg" Loading="lazy" Decoding="async">';
        $result = $this->extract( $html );
        $this->assertCount( 1, $result );
        $this->assertTrue( $result[0]['has_lazy'] );
        $this->assertTrue( $result[0]['has_decoding'] );
    }
}
