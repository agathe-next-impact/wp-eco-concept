<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EcoDiag Scoring Engine
 *
 * Calculates a score from 0-100 inspired by EcoIndex methodology.
 * Takes into account: page weight, DOM size, HTTP requests, JS/CSS count,
 * image optimization issues, and diagnostic results.
 */
class EcoDiag_Scoring {

    /**
     * Thresholds: [green_max, orange_max]
     * Green: <= green_max, Orange: <= orange_max, Red: > orange_max
     */
    const THRESHOLDS = array(
        'page_weight'   => array( 512000, 1048576 ),   // 500 Ko, 1 Mo
        'requests'      => array( 25, 50 ),
        'dom_size'      => array( 800, 1500 ),
        'js_count'      => array( 5, 10 ),
        'css_count'     => array( 3, 6 ),
        'img_issues'    => array( 0, 3 ),
    );

    /**
     * Weights for each metric in the score calculation.
     */
    const WEIGHTS = array(
        'page_weight'   => 30,
        'requests'      => 15,
        'dom_size'      => 15,
        'js_count'      => 10,
        'css_count'     => 10,
        'img_issues'    => 20,
    );

    /**
     * Calculate the EcoDiag score for given metrics.
     *
     * @param array $metrics Associative array with keys: page_weight, requests, dom_size, js_count, css_count, img_issues
     * @return int Score from 0 to 100.
     */
    public static function calculate( array $metrics ) {
        $total_weight = array_sum( self::WEIGHTS );
        $score = 0;

        foreach ( self::WEIGHTS as $key => $weight ) {
            $value = isset( $metrics[ $key ] ) ? (int) $metrics[ $key ] : 0;
            $thresholds = self::THRESHOLDS[ $key ];
            $metric_score = self::metric_score( $value, $thresholds[0], $thresholds[1] );
            $score += ( $metric_score / 100 ) * $weight;
        }

        return (int) round( ( $score / $total_weight ) * 100 );
    }

    /**
     * Score a single metric on 0-100 scale.
     *
     * @param int|float $value The measured value.
     * @param int|float $green Threshold for perfect score (100).
     * @param int|float $red Threshold for minimum score (0).
     * @return int Score 0-100.
     */
    private static function metric_score( $value, $green, $red ) {
        if ( $value <= 0 ) {
            return 100;
        }
        if ( $value <= $green ) {
            return 100;
        }
        if ( $value >= $red * 2 ) {
            return 0;
        }
        if ( $value <= $red ) {
            // Linear interpolation between green (100) and red (50)
            $ratio = ( $value - $green ) / max( 1, $red - $green );
            return (int) round( 100 - ( $ratio * 50 ) );
        }
        // Beyond red threshold, scale down to 0
        $ratio = ( $value - $red ) / max( 1, $red );
        return max( 0, (int) round( 50 - ( $ratio * 50 ) ) );
    }

    /**
     * Get color for a given score.
     *
     * @param int $score 0-100.
     * @return string 'green', 'orange', or 'red'.
     */
    public static function color( $score ) {
        if ( $score >= 75 ) return 'green';
        if ( $score >= 50 ) return 'orange';
        return 'red';
    }

    /**
     * Get hex color for a given score.
     *
     * @param int $score 0-100.
     * @return string Hex color code.
     */
    public static function hex_color( $score ) {
        if ( $score >= 75 ) return '#2ecc71';
        if ( $score >= 50 ) return '#f39c12';
        return '#e74c3c';
    }

    /**
     * Get EcoIndex grade letter.
     *
     * @param int $score 0-100.
     * @return string A-G.
     */
    public static function grade( $score ) {
        if ( $score >= 90 ) return 'A';
        if ( $score >= 75 ) return 'B';
        if ( $score >= 60 ) return 'C';
        if ( $score >= 45 ) return 'D';
        if ( $score >= 30 ) return 'E';
        if ( $score >= 15 ) return 'F';
        return 'G';
    }

    /**
     * Format bytes to human readable string.
     *
     * @param int $bytes Size in bytes.
     * @return string Formatted size.
     */
    public static function format_size( $bytes ) {
        if ( $bytes >= 1048576 ) {
            return round( $bytes / 1048576, 2 ) . ' Mo';
        }
        if ( $bytes >= 1024 ) {
            return round( $bytes / 1024, 1 ) . ' Ko';
        }
        return $bytes . ' o';
    }

    /**
     * Get indicator status based on thresholds.
     *
     * @param string $metric Metric key.
     * @param int|float $value The measured value.
     * @return string 'green', 'orange', or 'red'.
     */
    public static function indicator_status( $metric, $value ) {
        if ( ! isset( self::THRESHOLDS[ $metric ] ) ) {
            return 'green';
        }
        $t = self::THRESHOLDS[ $metric ];
        if ( $value <= $t[0] ) return 'green';
        if ( $value <= $t[1] ) return 'orange';
        return 'red';
    }
}
