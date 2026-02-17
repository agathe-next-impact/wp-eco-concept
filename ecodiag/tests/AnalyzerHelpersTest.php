<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for additional EcoDiag_Analyzer helper methods:
 * resolve_url, extract_css, extract_js, count_dom_nodes, detect_render_blocking, extract_fonts
 */
final class AnalyzerHelpersTest extends TestCase {

    private static \ReflectionMethod $resolveUrl;
    private static \ReflectionMethod $extractCss;
    private static \ReflectionMethod $extractJs;
    private static \ReflectionMethod $countDom;
    private static \ReflectionMethod $detectBlocking;
    private static \ReflectionMethod $extractFonts;
    private static \ReflectionMethod $extractIframes;
    private static \ReflectionMethod $detectEmbeds;
    private static \ReflectionMethod $detectTracking;

    public static function setUpBeforeClass(): void {
        $class = EcoDiag_Analyzer::class;
        self::$resolveUrl     = new \ReflectionMethod( $class, 'resolve_url' );
        self::$extractCss     = new \ReflectionMethod( $class, 'extract_css' );
        self::$extractJs      = new \ReflectionMethod( $class, 'extract_js' );
        self::$countDom       = new \ReflectionMethod( $class, 'count_dom_nodes' );
        self::$detectBlocking = new \ReflectionMethod( $class, 'detect_render_blocking' );
        self::$extractFonts   = new \ReflectionMethod( $class, 'extract_fonts' );
        self::$extractIframes = new \ReflectionMethod( $class, 'extract_iframes' );
        self::$detectEmbeds   = new \ReflectionMethod( $class, 'detect_embeds' );
        self::$detectTracking = new \ReflectionMethod( $class, 'detect_tracking_scripts' );
    }

    // ═══════════════════════════════════════════════
    // resolve_url
    // ═══════════════════════════════════════════════

    #[Test]
    #[DataProvider('resolveUrlProvider')]
    public function resolve_url_works( string $url, string $base, string $expected ): void {
        $result = self::$resolveUrl->invoke( null, $url, $base );
        $this->assertSame( $expected, $result );
    }

    public static function resolveUrlProvider(): array {
        return [
            'absolute https'       => [ 'https://cdn.com/a.js', 'https://example.com/', 'https://cdn.com/a.js' ],
            'absolute http'        => [ 'http://cdn.com/a.js', 'https://example.com/', 'http://cdn.com/a.js' ],
            'protocol-relative'    => [ '//cdn.com/a.js', 'https://example.com/', 'https://cdn.com/a.js' ],
            'root-relative'        => [ '/assets/a.js', 'https://example.com/blog/', 'https://example.com/assets/a.js' ],
            'relative from root'   => [ 'a.js', 'https://example.com/', 'https://example.com/a.js' ],
            'relative from subdir' => [ 'img.jpg', 'https://example.com/blog/page/', 'https://example.com/blog/img.jpg' ],
            'relative from file'   => [ 'img.jpg', 'https://example.com/blog/index.html', 'https://example.com/blog/img.jpg' ],
        ];
    }

    // ═══════════════════════════════════════════════
    // extract_css
    // ═══════════════════════════════════════════════

    #[Test]
    public function extract_css_finds_stylesheet_links(): void {
        $html = '<head><link rel="stylesheet" href="/css/style.css"><link rel="stylesheet" href="/css/print.css" media="print"></head>';
        $result = self::$extractCss->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 2, $result );
        $this->assertContains( 'https://example.com/css/style.css', $result );
        $this->assertContains( 'https://example.com/css/print.css', $result );
    }

    #[Test]
    public function extract_css_handles_href_before_rel(): void {
        $html = '<link href="/css/other.css" rel="stylesheet">';
        $result = self::$extractCss->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 1, $result );
    }

    #[Test]
    public function extract_css_deduplicates(): void {
        $html = '<link rel="stylesheet" href="/css/a.css"><link href="/css/a.css" rel="stylesheet">';
        $result = self::$extractCss->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 1, $result );
    }

    #[Test]
    public function extract_css_returns_empty_for_no_stylesheets(): void {
        $html = '<head><link rel="icon" href="/favicon.ico"></head>';
        $result = self::$extractCss->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 0, $result );
    }

    // ═══════════════════════════════════════════════
    // extract_js
    // ═══════════════════════════════════════════════

    #[Test]
    public function extract_js_finds_script_srcs(): void {
        $html = '<script src="/js/app.js"></script><script src="https://cdn.com/lib.js"></script>';
        $result = self::$extractJs->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 2, $result );
        $this->assertContains( 'https://example.com/js/app.js', $result );
        $this->assertContains( 'https://cdn.com/lib.js', $result );
    }

    #[Test]
    public function extract_js_ignores_inline_scripts(): void {
        $html = '<script>var x = 1;</script><script src="/app.js"></script>';
        $result = self::$extractJs->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 1, $result );
    }

    #[Test]
    public function extract_js_returns_empty_for_no_scripts(): void {
        $html = '<p>No scripts</p>';
        $result = self::$extractJs->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 0, $result );
    }

    // ═══════════════════════════════════════════════
    // count_dom_nodes
    // ═══════════════════════════════════════════════

    #[Test]
    public function count_dom_nodes_counts_opening_tags(): void {
        $html = '<html><head><title>T</title></head><body><div><p>Hello</p></div></body></html>';
        $count = self::$countDom->invoke( null, $html );
        // <html>, <head>, <title>, <body>, <div>, <p> = 6
        $this->assertSame( 6, $count );
    }

    #[Test]
    public function count_dom_nodes_returns_zero_for_empty(): void {
        $this->assertSame( 0, self::$countDom->invoke( null, '' ) );
    }

    #[Test]
    public function count_dom_nodes_handles_self_closing(): void {
        $html = '<div><img src="a.jpg" /><br/><hr></div>';
        $count = self::$countDom->invoke( null, $html );
        // <div>, <img ...>, <br/>, <hr> = 4
        $this->assertSame( 4, $count );
    }

    // ═══════════════════════════════════════════════
    // detect_render_blocking
    // ═══════════════════════════════════════════════

    #[Test]
    public function detect_render_blocking_css(): void {
        $html = '<link rel="stylesheet" href="/a.css"><link rel="stylesheet" href="/print.css" media="print">';
        $result = self::$detectBlocking->invoke( null, $html );
        $this->assertCount( 1, $result['css'] );
        $this->assertContains( '/a.css', $result['css'] );
        // print stylesheet should NOT be blocking
    }

    #[Test]
    public function detect_render_blocking_js(): void {
        $html = '<script src="/blocking.js"></script><script src="/async.js" async></script><script src="/defer.js" defer></script>';
        $result = self::$detectBlocking->invoke( null, $html );
        $this->assertCount( 1, $result['js'] );
        $this->assertContains( '/blocking.js', $result['js'] );
    }

    #[Test]
    public function detect_render_blocking_empty_for_optimized_page(): void {
        $html = '<link rel="stylesheet" href="/a.css" media="print"><script src="/a.js" defer></script>';
        $result = self::$detectBlocking->invoke( null, $html );
        $this->assertCount( 0, $result['css'] );
        $this->assertCount( 0, $result['js'] );
    }

    // ═══════════════════════════════════════════════
    // extract_fonts
    // ═══════════════════════════════════════════════

    #[Test]
    public function extract_fonts_finds_preloaded(): void {
        $html = '<link rel="preload" as="font" href="/fonts/roboto.woff2" type="font/woff2" crossorigin>';
        $result = self::$extractFonts->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 1, $result );
        $this->assertContains( 'https://example.com/fonts/roboto.woff2', $result );
    }

    #[Test]
    public function extract_fonts_finds_inline_font_face(): void {
        $html = '<style>@font-face { font-family: "Custom"; src: url("/fonts/custom.woff2"); }</style>';
        $result = self::$extractFonts->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 1, $result );
    }

    #[Test]
    public function extract_fonts_deduplicates(): void {
        $html = '<link rel="preload" as="font" href="/fonts/a.woff2"><style>@font-face { src: url("/fonts/a.woff2"); }</style>';
        $result = self::$extractFonts->invoke( null, $html, 'https://example.com/' );
        $this->assertCount( 1, $result );
    }

    // ═══════════════════════════════════════════════
    // extract_iframes
    // ═══════════════════════════════════════════════

    #[Test]
    public function extract_iframes_finds_iframes(): void {
        $html = '<iframe src="https://youtube.com/embed/xyz" loading="lazy"></iframe>';
        $result = self::$extractIframes->invoke( null, $html );
        $this->assertCount( 1, $result );
        $this->assertSame( 'https://youtube.com/embed/xyz', $result[0]['src'] );
        $this->assertTrue( $result[0]['has_lazy'] );
    }

    #[Test]
    public function extract_iframes_detects_missing_lazy(): void {
        $html = '<iframe src="https://youtube.com/embed/xyz"></iframe>';
        $result = self::$extractIframes->invoke( null, $html );
        $this->assertFalse( $result[0]['has_lazy'] );
    }

    // ═══════════════════════════════════════════════
    // detect_embeds
    // ═══════════════════════════════════════════════

    #[Test]
    public function detect_embeds_identifies_youtube(): void {
        $iframes = array( array( 'tag' => '<iframe src="https://youtube.com/embed/xyz">', 'src' => 'https://youtube.com/embed/xyz', 'has_lazy' => false ) );
        $result = self::$detectEmbeds->invoke( null, $iframes, '' );
        $this->assertSame( 'youtube', $result[0]['type'] );
    }

    #[Test]
    public function detect_embeds_identifies_vimeo(): void {
        $iframes = array( array( 'tag' => '<iframe src="https://player.vimeo.com/video/123">', 'src' => 'https://player.vimeo.com/video/123', 'has_lazy' => true ) );
        $result = self::$detectEmbeds->invoke( null, $iframes, '' );
        $this->assertSame( 'vimeo', $result[0]['type'] );
    }

    #[Test]
    public function detect_embeds_finds_autoplay_videos(): void {
        $html = '<video autoplay muted loop><source src="bg.mp4"></video>';
        $result = self::$detectEmbeds->invoke( null, array(), $html );
        $this->assertCount( 1, $result );
        $this->assertSame( 'video_autoplay', $result[0]['type'] );
        $refs = array_column( $result[0]['issues'], 'ref' );
        $this->assertContains( 'P-VID-02', $refs );
    }

    // ═══════════════════════════════════════════════
    // detect_tracking_scripts
    // ═══════════════════════════════════════════════

    #[Test]
    public function detect_tracking_finds_google_analytics(): void {
        $js = array( 'https://www.googletagmanager.com/gtag/js?id=G-XXXX' );
        $result = self::$detectTracking->invoke( null, $js, '' );
        $this->assertContains( 'google_analytics', $result );
    }

    #[Test]
    public function detect_tracking_finds_facebook_pixel(): void {
        $html = '<script>!function(f,b,e,v,n,t,s){fbq("init","XXXX")}()</script>';
        $result = self::$detectTracking->invoke( null, array(), $html );
        $this->assertContains( 'facebook_pixel', $result );
    }

    #[Test]
    public function detect_tracking_returns_empty_for_clean_page(): void {
        $result = self::$detectTracking->invoke( null, array( '/js/app.js' ), '<html><body></body></html>' );
        $this->assertEmpty( $result );
    }
}
