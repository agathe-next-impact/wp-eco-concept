<?php
/**
 * EcoPress Admin: settings page for global fixers.
 *
 * @package EcoPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page for EcoPress global configuration.
 */
final class EcoPress_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . ECOPRESS_PLUGIN_BASENAME, [ $this, 'add_settings_link' ] );
	}

	/**
	 * Add the EcoPress settings page under the Settings menu.
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'EcoPress – Réglages', 'ecopress-auditor' ),
			__( 'EcoPress', 'ecopress-auditor' ),
			'manage_options',
			'ecopress-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings and sections.
	 */
	public function register_settings(): void {
		// DOM Cleaner section.
		add_settings_section(
			'ecopress_dom_section',
			__( 'DOM Cleaner – Nettoyage global', 'ecopress-auditor' ),
			static function (): void {
				echo '<p>' . esc_html__(
					'Supprime les ressources WordPress non essentielles pour réduire le poids des pages.',
					'ecopress-auditor'
				) . '</p>';
			},
			'ecopress-settings'
		);

		$this->register_checkbox(
			'ecopress_dom_remove_emoji',
			__( 'Supprimer les Emojis WordPress', 'ecopress-auditor' ),
			__( 'Retire le script et les styles liés aux emojis WordPress.', 'ecopress-auditor' ),
			'ecopress_dom_section'
		);

		$this->register_checkbox(
			'ecopress_dom_remove_embeds',
			__( 'Désactiver les oEmbeds', 'ecopress-auditor' ),
			__( 'Désactive la fonctionnalité d\'intégration automatique (oEmbed).', 'ecopress-auditor' ),
			'ecopress_dom_section'
		);

		$this->register_checkbox(
			'ecopress_dom_remove_version',
			__( 'Supprimer le versioning CSS/JS', 'ecopress-auditor' ),
			__( 'Retire les paramètres ?ver= des URL de ressources.', 'ecopress-auditor' ),
			'ecopress_dom_section'
		);

		$this->register_checkbox(
			'ecopress_dom_remove_jquery_migrate',
			__( 'Supprimer jQuery Migrate', 'ecopress-auditor' ),
			__( 'Retire jQuery Migrate (~10 Ko) du frontend. Couche de compatibilité rarement nécessaire.', 'ecopress-auditor' ),
			'ecopress_dom_section'
		);

		$this->register_checkbox(
			'ecopress_dom_remove_dashicons',
			__( 'Supprimer Dashicons (visiteurs)', 'ecopress-auditor' ),
			__( 'Retire le CSS Dashicons (~46 Ko) pour les visiteurs non connectés.', 'ecopress-auditor' ),
			'ecopress_dom_section'
		);

		$this->register_checkbox(
			'ecopress_dom_disable_heartbeat',
			__( 'Désactiver le Heartbeat frontend', 'ecopress-auditor' ),
			__( 'Stoppe les requêtes AJAX périodiques du Heartbeat API sur le frontend.', 'ecopress-auditor' ),
			'ecopress_dom_section'
		);

		$this->register_checkbox(
			'ecopress_dom_clean_head',
			__( 'Nettoyer le <head>', 'ecopress-auditor' ),
			__( 'Supprime RSD, WLW, shortlink, liens REST API, flux RSS et meta generator du <head>.', 'ecopress-auditor' ),
			'ecopress_dom_section'
		);

		// Lazy-Load section.
		add_settings_section(
			'ecopress_lazyload_section',
			__( 'Lazy-Load Force', 'ecopress-auditor' ),
			static function (): void {
				echo '<p>' . esc_html__(
					'Force l\'attribut loading="lazy" sur les images et iframes.',
					'ecopress-auditor'
				) . '</p>';
			},
			'ecopress-settings'
		);

		$this->register_checkbox(
			'ecopress_lazyload_enabled',
			__( 'Activer le Lazy-Load forcé', 'ecopress-auditor' ),
			__( 'Ajoute loading="lazy" à toutes les images et iframes du contenu.', 'ecopress-auditor' ),
			'ecopress_lazyload_section'
		);

		// Threshold section.
		add_settings_section(
			'ecopress_threshold_section',
			__( 'Seuil d\'alerte', 'ecopress-auditor' ),
			static function (): void {
				echo '<p>' . esc_html__(
					'Définissez le seuil de poids au-delà duquel le HUD affiche une alerte.',
					'ecopress-auditor'
				) . '</p>';
			},
			'ecopress-settings'
		);

		register_setting( 'ecopress_settings', 'ecopress_weight_threshold', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1500,
		] );

		add_settings_field(
			'ecopress_weight_threshold',
			__( 'Poids maximum (Ko)', 'ecopress-auditor' ),
			function (): void {
				$value = (int) get_option( 'ecopress_weight_threshold', 1500 );
				echo '<input type="number" name="ecopress_weight_threshold" value="' . esc_attr( (string) $value ) . '" min="100" max="10000" step="100" class="small-text" />';
				echo '<p class="description">' . esc_html__(
					'En kilo-octets. Le HUD passera en mode alerte au-delà de cette valeur.',
					'ecopress-auditor'
				) . '</p>';
			},
			'ecopress-settings',
			'ecopress_threshold_section'
		);
	}

	/**
	 * Register a checkbox setting.
	 *
	 * @param string $option_name  Option key.
	 * @param string $label        Field label.
	 * @param string $description  Help text.
	 * @param string $section      Section ID.
	 */
	private function register_checkbox(
		string $option_name,
		string $label,
		string $description,
		string $section
	): void {
		register_setting( 'ecopress_settings', $option_name, [
			'type'              => 'integer',
			'sanitize_callback' => static fn( $val ): int => $val ? 1 : 0,
			'default'           => 0,
		] );

		add_settings_field(
			$option_name,
			$label,
			static function () use ( $option_name, $description ): void {
				$checked = (int) get_option( $option_name, 0 );
				echo '<label>';
				echo '<input type="checkbox" name="' . esc_attr( $option_name ) . '" value="1" ' . checked( $checked, 1, false ) . ' />';
				echo ' ' . esc_html( $description );
				echo '</label>';
			},
			'ecopress-settings',
			$section
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'ecopress_settings' );
		do_settings_sections( 'ecopress-settings' );
		submit_button( __( 'Enregistrer les réglages', 'ecopress-auditor' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Add a "Settings" link on the Plugins page.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified links.
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=ecopress-settings' ) ) . '">'
			. esc_html__( 'Réglages', 'ecopress-auditor' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
