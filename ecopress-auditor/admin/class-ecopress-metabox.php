<?php
/**
 * EcoPress Metabox: audit results under the post editor.
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds an Eco-Audit metabox to the post editing screen.
 */
final class EcoPress_Metabox {

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
		add_action( 'save_post', [ $this, 'save_metabox' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_ecopress_run_audit', [ $this, 'ajax_run_audit' ] );
	}

	/**
	 * Register the metabox for public post types.
	 */
	public function register_metabox(): void {
		$post_types = get_post_types( [ 'public' => true ] );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'ecopress_audit',
				__( 'EcoPress – Audit Éco-conception', 'ecopress-auditor' ),
				[ $this, 'render_metabox' ],
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Enqueue admin assets for the metabox.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		wp_enqueue_style(
			'ecopress-admin',
			ECOPRESS_PLUGIN_URL . 'assets/css/admin.css',
			[],
			ECOPRESS_VERSION
		);

		wp_enqueue_script(
			'ecopress-admin',
			ECOPRESS_PLUGIN_URL . 'assets/js/admin.js',
			[],
			ECOPRESS_VERSION,
			true
		);

		$post_id = get_the_ID();
		wp_localize_script( 'ecopress-admin', 'ecopressAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ecopress_fixer_nonce' ),
			'postId'  => $post_id ?: 0,
			'i18n'    => [
				'runAudit'    => __( 'Lancer l\'audit', 'ecopress-auditor' ),
				'running'     => __( 'Analyse en cours...', 'ecopress-auditor' ),
				'weight'      => __( 'Poids total', 'ecopress-auditor' ),
				'grade'       => __( 'Score Eco-Index', 'ecopress-auditor' ),
				'co2'         => __( 'Empreinte CO2', 'ecopress-auditor' ),
				'topRes'      => __( 'Top 5 ressources les plus lourdes', 'ecopress-auditor' ),
				'resource'    => __( 'Ressource', 'ecopress-auditor' ),
				'type'        => __( 'Type', 'ecopress-auditor' ),
				'size'        => __( 'Taille', 'ecopress-auditor' ),
				'action'      => __( 'Action', 'ecopress-auditor' ),
				'converting'  => __( 'Conversion en cours...', 'ecopress-auditor' ),
				'convertWebp' => __( 'Convertir les images en WebP', 'ecopress-auditor' ),
				'noData'      => __( 'Publiez l\'article puis lancez un audit.', 'ecopress-auditor' ),
				'error'       => __( 'Erreur lors de l\'audit.', 'ecopress-auditor' ),
			],
		] );
	}

	/**
	 * Render the metabox content.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'ecopress_metabox_nonce', 'ecopress_metabox_nonce_field' );

		$disabled_scripts = get_post_meta( $post->ID, '_ecopress_disabled_scripts', true );
		if ( ! is_array( $disabled_scripts ) ) {
			$disabled_scripts = [];
		}

		echo '<div id="ecopress-metabox-root">';
		echo '<div id="ecopress-audit-results">';

		if ( 'publish' === $post->post_status ) {
			echo '<p><button type="button" class="button button-primary" id="ecopress-run-audit">';
			echo esc_html__( 'Lancer l\'audit', 'ecopress-auditor' );
			echo '</button></p>';
			echo '<div id="ecopress-audit-output"></div>';
		} else {
			echo '<p>' . esc_html__( 'Publiez l\'article puis lancez un audit.', 'ecopress-auditor' ) . '</p>';
		}

		echo '</div>';

		// Script Unloader section.
		echo '<div id="ecopress-script-unloader" style="margin-top:16px;">';
		echo '<h4>' . esc_html__( 'Script Unloader', 'ecopress-auditor' ) . '</h4>';
		echo '<p class="description">';
		echo esc_html__( 'Entrez les handles de scripts à désactiver pour cette page (un par ligne).', 'ecopress-auditor' );
		echo '</p>';
		echo '<textarea name="ecopress_disabled_scripts" rows="4" style="width:100%;">';
		echo esc_textarea( implode( "\n", $disabled_scripts ) );
		echo '</textarea>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Save metabox data.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_metabox( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['ecopress_metabox_nonce_field'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ecopress_metabox_nonce_field'] ) ), 'ecopress_metabox_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save disabled scripts.
		if ( isset( $_POST['ecopress_disabled_scripts'] ) ) {
			$raw     = sanitize_textarea_field( wp_unslash( $_POST['ecopress_disabled_scripts'] ) );
			$handles = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
			EcoPress_Fixer_Scripts::save_disabled_scripts( $post_id, $handles );
		}
	}

	/**
	 * AJAX handler: run audit for a post.
	 */
	public function ajax_run_audit(): void {
		check_ajax_referer( 'ecopress_fixer_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Non autorisé.', 'ecopress-auditor' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( __( 'ID invalide.', 'ecopress-auditor' ), 400 );
		}

		$report = EcoPress_Analyzer::audit( $post_id, true );

		if ( is_wp_error( $report ) ) {
			wp_send_json_error( $report->get_error_message() );
		}

		// Keep top 5.
		$report['resources'] = array_slice( $report['resources'], 0, 5 );

		wp_send_json_success( $report );
	}
}
