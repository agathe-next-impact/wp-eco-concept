<?php
/**
 * Eco-HUD: lightweight front-end diagnostic popup for editors.
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects the Eco-HUD overlay on the front-end for authorized users.
 */
final class EcoPress_HUD {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_ecopress_hud_data', [ $this, 'ajax_hud_data' ] );
	}

	/**
	 * Enqueue HUD assets only for logged-in editors on singular pages.
	 */
	public function enqueue_assets(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		wp_enqueue_style(
			'ecopress-hud',
			ECOPRESS_PLUGIN_URL . 'assets/css/hud.css',
			[],
			ECOPRESS_VERSION
		);

		wp_enqueue_script(
			'ecopress-hud',
			ECOPRESS_PLUGIN_URL . 'assets/js/hud.js',
			[],
			ECOPRESS_VERSION,
			true
		);

		wp_localize_script( 'ecopress-hud', 'ecopressHud', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'ecopress_hud_nonce' ),
			'postId'    => get_queried_object_id(),
			'threshold' => (int) get_option( 'ecopress_weight_threshold', 1500 ),
			'i18n'      => [
				'title'       => __( 'EcoPress', 'ecopress-auditor' ),
				'weight'      => __( 'Poids', 'ecopress-auditor' ),
				'grade'       => __( 'Score', 'ecopress-auditor' ),
				'co2'         => __( 'CO2', 'ecopress-auditor' ),
				'alert'       => __( 'Cette page dépasse le seuil recommandé !', 'ecopress-auditor' ),
				'loading'     => __( 'Analyse en cours...', 'ecopress-auditor' ),
				'error'       => __( 'Erreur d\'analyse.', 'ecopress-auditor' ),
				'close'       => __( 'Fermer', 'ecopress-auditor' ),
				'resources'   => __( 'Ressources les plus lourdes', 'ecopress-auditor' ),
				'diagnostics' => __( 'Diagnostic éco-conception', 'ecopress-auditor' ),
			],
		] );
	}

	/**
	 * AJAX handler: return HUD data for the current post.
	 */
	public function ajax_hud_data(): void {
		check_ajax_referer( 'ecopress_hud_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Non autorisé.', 'ecopress-auditor' ), 403 );
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( __( 'ID invalide.', 'ecopress-auditor' ), 400 );
		}

		$report = EcoPress_Analyzer::audit( $post_id );

		if ( is_wp_error( $report ) ) {
			wp_send_json_error( $report->get_error_message() );
		}

		// Return only the top 5 resources for the HUD.
		$report['resources'] = array_slice( $report['resources'], 0, 5 );

		wp_send_json_success( $report );
	}
}
