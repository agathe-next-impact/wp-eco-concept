<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for EcoDiag_Scoring.
 */
final class ScoringTest extends TestCase {

    // ─── calculate() ────────────────────────────────

    #[Test]
    public function calculate_returns_100_for_perfect_metrics(): void {
        $metrics = array(
            'page_weight' => 0,
            'requests'    => 0,
            'dom_size'    => 0,
            'js_count'    => 0,
            'css_count'   => 0,
            'img_issues'  => 0,
        );
        $this->assertSame( 100, EcoDiag_Scoring::calculate( $metrics ) );
    }

    #[Test]
    public function calculate_returns_100_for_green_threshold_metrics(): void {
        $metrics = array(
            'page_weight' => 512000,
            'requests'    => 25,
            'dom_size'    => 800,
            'js_count'    => 5,
            'css_count'   => 3,
            'img_issues'  => 0,
        );
        $this->assertSame( 100, EcoDiag_Scoring::calculate( $metrics ) );
    }

    #[Test]
    public function calculate_returns_lower_score_for_red_metrics(): void {
        $metrics = array(
            'page_weight' => 1048576,  // orange→red boundary
            'requests'    => 50,
            'dom_size'    => 1500,
            'js_count'    => 10,
            'css_count'   => 6,
            'img_issues'  => 3,
        );
        $score = EcoDiag_Scoring::calculate( $metrics );
        $this->assertSame( 50, $score );
    }

    #[Test]
    public function calculate_returns_0_for_extremely_bad_metrics(): void {
        $metrics = array(
            'page_weight' => 10000000,
            'requests'    => 500,
            'dom_size'    => 50000,
            'js_count'    => 100,
            'css_count'   => 100,
            'img_issues'  => 100,
        );
        $score = EcoDiag_Scoring::calculate( $metrics );
        $this->assertSame( 0, $score );
    }

    #[Test]
    public function calculate_handles_missing_keys_as_zero(): void {
        $metrics = array();
        // All values default to 0 → perfect score
        $this->assertSame( 100, EcoDiag_Scoring::calculate( $metrics ) );
    }

    #[Test]
    public function calculate_handles_partial_keys(): void {
        $metrics = array( 'page_weight' => 2000000 );
        $score = EcoDiag_Scoring::calculate( $metrics );
        // page_weight very bad, everything else 0 → mixed
        $this->assertGreaterThan( 0, $score );
        $this->assertLessThan( 100, $score );
    }

    #[Test]
    public function calculate_returns_integer(): void {
        $metrics = array(
            'page_weight' => 700000,
            'requests'    => 35,
            'dom_size'    => 1000,
            'js_count'    => 7,
            'css_count'   => 4,
            'img_issues'  => 2,
        );
        $score = EcoDiag_Scoring::calculate( $metrics );
        $this->assertIsInt( $score );
    }

    // ─── grade() ────────────────────────────────────

    #[Test]
    #[DataProvider('gradeProvider')]
    public function grade_returns_correct_letter( int $score, string $expected ): void {
        $this->assertSame( $expected, EcoDiag_Scoring::grade( $score ) );
    }

    public static function gradeProvider(): array {
        return [
            'A at 100'    => [ 100, 'A' ],
            'A at 90'     => [ 90, 'A' ],
            'B at 89'     => [ 89, 'B' ],
            'B at 75'     => [ 75, 'B' ],
            'C at 74'     => [ 74, 'C' ],
            'C at 60'     => [ 60, 'C' ],
            'D at 59'     => [ 59, 'D' ],
            'D at 45'     => [ 45, 'D' ],
            'E at 44'     => [ 44, 'E' ],
            'E at 30'     => [ 30, 'E' ],
            'F at 29'     => [ 29, 'F' ],
            'F at 15'     => [ 15, 'F' ],
            'G at 14'     => [ 14, 'G' ],
            'G at 0'      => [ 0, 'G' ],
        ];
    }

    // ─── color() ────────────────────────────────────

    #[Test]
    public function color_returns_green_for_high_scores(): void {
        $this->assertSame( 'green', EcoDiag_Scoring::color( 75 ) );
        $this->assertSame( 'green', EcoDiag_Scoring::color( 100 ) );
    }

    #[Test]
    public function color_returns_orange_for_medium_scores(): void {
        $this->assertSame( 'orange', EcoDiag_Scoring::color( 50 ) );
        $this->assertSame( 'orange', EcoDiag_Scoring::color( 74 ) );
    }

    #[Test]
    public function color_returns_red_for_low_scores(): void {
        $this->assertSame( 'red', EcoDiag_Scoring::color( 0 ) );
        $this->assertSame( 'red', EcoDiag_Scoring::color( 49 ) );
    }

    // ─── hex_color() ────────────────────────────────

    #[Test]
    public function hex_color_returns_correct_values(): void {
        $this->assertSame( '#2ecc71', EcoDiag_Scoring::hex_color( 75 ) );
        $this->assertSame( '#f39c12', EcoDiag_Scoring::hex_color( 50 ) );
        $this->assertSame( '#e74c3c', EcoDiag_Scoring::hex_color( 49 ) );
    }

    // ─── format_size() ──────────────────────────────

    #[Test]
    #[DataProvider('formatSizeProvider')]
    public function format_size_returns_correct_string( int $bytes, string $expected ): void {
        $this->assertSame( $expected, EcoDiag_Scoring::format_size( $bytes ) );
    }

    public static function formatSizeProvider(): array {
        return [
            'bytes'      => [ 500, '500 o' ],
            'zero bytes' => [ 0, '0 o' ],
            'kilobytes'  => [ 1024, '1 Ko' ],
            'kilobytes fractional' => [ 1536, '1.5 Ko' ],
            'megabytes'  => [ 1048576, '1 Mo' ],
            'megabytes fractional' => [ 1572864, '1.5 Mo' ],
            'boundary kb' => [ 1023, '1023 o' ],
        ];
    }

    // ─── indicator_status() ─────────────────────────

    #[Test]
    public function indicator_status_returns_green_for_values_within_threshold(): void {
        $this->assertSame( 'green', EcoDiag_Scoring::indicator_status( 'page_weight', 500000 ) );
        $this->assertSame( 'green', EcoDiag_Scoring::indicator_status( 'requests', 20 ) );
        $this->assertSame( 'green', EcoDiag_Scoring::indicator_status( 'img_issues', 0 ) );
    }

    #[Test]
    public function indicator_status_returns_orange_for_medium_values(): void {
        $this->assertSame( 'orange', EcoDiag_Scoring::indicator_status( 'page_weight', 600000 ) );
        $this->assertSame( 'orange', EcoDiag_Scoring::indicator_status( 'requests', 30 ) );
        $this->assertSame( 'orange', EcoDiag_Scoring::indicator_status( 'img_issues', 2 ) );
    }

    #[Test]
    public function indicator_status_returns_red_for_high_values(): void {
        $this->assertSame( 'red', EcoDiag_Scoring::indicator_status( 'page_weight', 2000000 ) );
        $this->assertSame( 'red', EcoDiag_Scoring::indicator_status( 'requests', 60 ) );
        $this->assertSame( 'red', EcoDiag_Scoring::indicator_status( 'img_issues', 5 ) );
    }

    #[Test]
    public function indicator_status_returns_green_for_unknown_metric(): void {
        $this->assertSame( 'green', EcoDiag_Scoring::indicator_status( 'nonexistent_key', 999999 ) );
    }

    // ─── Constants consistency ──────────────────────

    #[Test]
    public function weights_sum_to_100(): void {
        $this->assertSame( 100, array_sum( EcoDiag_Scoring::WEIGHTS ) );
    }

    #[Test]
    public function thresholds_and_weights_have_same_keys(): void {
        $this->assertSame(
            array_keys( EcoDiag_Scoring::THRESHOLDS ),
            array_keys( EcoDiag_Scoring::WEIGHTS )
        );
    }

    #[Test]
    public function thresholds_green_is_always_less_than_red(): void {
        foreach ( EcoDiag_Scoring::THRESHOLDS as $key => $t ) {
            $this->assertLessThan(
                $t[1],
                $t[0],
                "Threshold $key: green ({$t[0]}) should be < red ({$t[1]})"
            );
        }
    }
}
