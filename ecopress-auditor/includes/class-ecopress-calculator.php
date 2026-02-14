<?php
/**
 * CO2 and Eco-Index calculation engine.
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all carbon footprint and Eco-Index computations.
 *
 * Formula: CO2 (g) = Data (GB) * Energy_per_GB (kWh/GB) * Carbon_Intensity (g/kWh)
 * - Energy per GB: 0.81 kWh/GB (2026 average)
 * - Carbon intensity: 490 g CO2/kWh (world grid average)
 */
final class EcoPress_Calculator {

	/** Energy consumption per GB transferred (kWh/GB). */
	private const ENERGY_PER_GB = 0.81;

	/** Average carbon intensity of the global electricity grid (gCO2/kWh). */
	private const CARBON_INTENSITY = 490;

	/** Eco-Index thresholds in kilobytes. */
	private const GRADE_THRESHOLDS = [
		'A' => 200,
		'B' => 500,
		'C' => 1000,
		'D' => 1500,
		'E' => 2500,
		'F' => 4000,
	];

	/**
	 * Calculate CO2 emissions for a given page weight.
	 *
	 * @param int $weight_kb Page weight in kilobytes.
	 * @return float CO2 in grams, rounded to 2 decimals.
	 */
	public static function compute_co2( int $weight_kb ): float {
		$weight_gb = $weight_kb / ( 1024 * 1024 );
		return round( $weight_gb * self::ENERGY_PER_GB * self::CARBON_INTENSITY, 2 );
	}

	/**
	 * Determine the Eco-Index grade (A–G) for a page weight.
	 *
	 * @param int $weight_kb Page weight in kilobytes.
	 * @return string Single letter grade from A (best) to G (worst).
	 */
	public static function compute_grade( int $weight_kb ): string {
		foreach ( self::GRADE_THRESHOLDS as $grade => $max_kb ) {
			if ( $weight_kb <= $max_kb ) {
				return $grade;
			}
		}
		return 'G';
	}

	/**
	 * Return a human-readable color for a given grade.
	 *
	 * @param string $grade A–G letter grade.
	 * @return string Hex color code.
	 */
	public static function grade_color( string $grade ): string {
		$colors = [
			'A' => '#2d9a2d',
			'B' => '#5fb832',
			'C' => '#a8c836',
			'D' => '#f0c800',
			'E' => '#f09600',
			'F' => '#e64b00',
			'G' => '#c8190a',
		];
		return $colors[ $grade ] ?? '#c8190a';
	}

	/**
	 * Build a full report array from a page weight.
	 *
	 * @param int $weight_kb Page weight in kilobytes.
	 * @return array{weight_kb: int, co2_g: float, grade: string, grade_color: string}
	 */
	public static function build_report( int $weight_kb ): array {
		$grade = self::compute_grade( $weight_kb );
		return [
			'weight_kb'   => $weight_kb,
			'co2_g'       => self::compute_co2( $weight_kb ),
			'grade'       => $grade,
			'grade_color' => self::grade_color( $grade ),
		];
	}
}
