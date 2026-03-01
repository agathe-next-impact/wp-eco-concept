<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add EcoDiag columns to admin post listings.
 */
class EcoDiag_Admin_Columns {

    public function __construct() {
        if ( get_option( 'ecodiag_show_listing_column', '1' ) !== '1' ) {
            return;
        }

        add_action( 'admin_init', array( $this, 'register_columns' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function register_columns() {
        $post_types = EcoDiag_Core::get_audited_post_types();
        foreach ( $post_types as $pt ) {
            add_filter( "manage_{$pt}_posts_columns", array( $this, 'add_columns' ) );
            add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
            add_filter( "manage_edit-{$pt}_sortable_columns", array( $this, 'sortable_columns' ) );
        }
    }

    public function add_columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['ecodiag_score']  = __( 'Score EcoDiag', 'ecodiag' );
                $new['ecodiag_weight'] = __( 'Poids estimé', 'ecodiag' );
                $new['ecodiag_imgs']   = __( 'Img. non opt.', 'ecodiag' );
            }
        }
        return $new;
    }

    public function render_column( $column, $post_id ) {
        $cached = get_transient( 'ecodiag_audit_' . $post_id );

        if ( $column === 'ecodiag_score' ) {
            if ( $cached && isset( $cached['score'] ) ) {
                $score = (int) $cached['score'];
                $color = EcoDiag_Scoring::hex_color( $score );
                printf(
                    '<span class="ecodiag-col-badge" style="background:%s;color:#fff;padding:2px 8px;border-radius:10px;font-weight:700;font-size:12px">%d</span>',
                    esc_attr( $color ),
                    $score
                );
            } else {
                echo '<span class="ecodiag-col-na" style="color:#999">—</span>';
            }
        }

        if ( $column === 'ecodiag_weight' ) {
            if ( $cached && isset( $cached['total_weight'] ) ) {
                echo esc_html( EcoDiag_Scoring::format_size( $cached['total_weight'] ) );
            } else {
                echo '—';
            }
        }

        if ( $column === 'ecodiag_imgs' ) {
            if ( $cached && isset( $cached['img_issues_count'] ) ) {
                $count = (int) $cached['img_issues_count'];
                $color = $count === 0 ? '#2ecc71' : ( $count <= 3 ? '#f39c12' : '#e74c3c' );
                printf(
                    '<span style="color:%s;font-weight:700">%d</span>',
                    esc_attr( $color ),
                    $count
                );
            } else {
                echo '—';
            }
        }
    }

    public function sortable_columns( $columns ) {
        $columns['ecodiag_score']  = 'ecodiag_score';
        $columns['ecodiag_weight'] = 'ecodiag_weight';
        return $columns;
    }

    public function enqueue( $hook ) {
        if ( strpos( $hook, 'edit.php' ) === false ) return;
        wp_enqueue_style( 'ecodiag-columns', ECODIAG_URL . 'assets/css/columns.css', array(), ECODIAG_VERSION );
    }
}
