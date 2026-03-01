<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for EcoDiag_Analyzer::normalize_url (private, accessed via Reflection).
 */
final class NormalizeUrlTest extends TestCase {

    private static \ReflectionMethod $method;

    public static function setUpBeforeClass(): void {
        self::$method = new \ReflectionMethod( EcoDiag_Analyzer::class, 'normalize_url' );
    }

    private function normalize( string $url ): string {
        return self::$method->invoke( null, $url );
    }

    #[Test]
    public function strips_query_string(): void {
        $this->assertSame(
            'https://example.com/img.jpg',
            $this->normalize( 'https://example.com/img.jpg?v=1.2.3' )
        );
    }

    #[Test]
    public function strips_fragment(): void {
        $this->assertSame(
            'https://example.com/img.jpg',
            $this->normalize( 'https://example.com/img.jpg#section' )
        );
    }

    #[Test]
    public function strips_both_query_and_fragment(): void {
        $this->assertSame(
            'https://example.com/img.jpg',
            $this->normalize( 'https://example.com/img.jpg?v=1#top' )
        );
    }

    #[Test]
    public function preserves_clean_url(): void {
        $this->assertSame(
            'https://example.com/photo.webp',
            $this->normalize( 'https://example.com/photo.webp' )
        );
    }

    #[Test]
    public function preserves_path_with_directories(): void {
        $this->assertSame(
            'https://example.com/wp-content/uploads/2024/photo.jpg',
            $this->normalize( 'https://example.com/wp-content/uploads/2024/photo.jpg?w=300' )
        );
    }

    #[Test]
    public function handles_http_scheme(): void {
        $this->assertSame(
            'http://example.com/img.png',
            $this->normalize( 'http://example.com/img.png?size=large' )
        );
    }

    #[Test]
    public function handles_url_with_no_path(): void {
        $this->assertSame(
            'https://example.com/',
            $this->normalize( 'https://example.com' )
        );
    }

    #[Test]
    public function handles_trailing_slash(): void {
        $this->assertSame(
            'https://example.com/',
            $this->normalize( 'https://example.com/' )
        );
    }

    #[Test]
    public function two_urls_differing_only_by_query_normalize_to_same(): void {
        $url1 = $this->normalize( 'https://cdn.example.com/style.css?ver=5.9' );
        $url2 = $this->normalize( 'https://cdn.example.com/style.css?ver=6.0' );
        $this->assertSame( $url1, $url2 );
    }
}
