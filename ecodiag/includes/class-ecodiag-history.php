<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * History tracking for EcoDiag scores.
 */
class EcoDiag_History {

    /**
     * Save a score entry.
     */
    public static function save( $type, $object_id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ecodiag_history';

        $wpdb->insert( $table, array(
            'object_type'    => sanitize_key( $type ),
            'object_id'      => (int) $object_id,
            'score'          => isset( $data['score'] ) ? (int) $data['score'] : 0,
            'page_weight'    => isset( $data['total_weight'] ) ? (int) $data['total_weight'] : 0,
            'requests_count' => isset( $data['requests_count'] ) ? (int) $data['requests_count'] : 0,
            'dom_size'       => isset( $data['dom_size'] ) ? (int) $data['dom_size'] : 0,
            'js_count'       => isset( $data['js_count'] ) ? (int) $data['js_count'] : 0,
            'css_count'      => isset( $data['css_count'] ) ? (int) $data['css_count'] : 0,
            'img_issues'     => isset( $data['img_issues_count'] ) ? (int) $data['img_issues_count'] : 0,
            'details'        => wp_json_encode( $data ),
            'created_at'     => current_time( 'mysql' ),
        ), array( '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ) );
    }

    /**
     * Get history for an object.
     */
    public static function get( $type, $object_id, $limit = 50 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ecodiag_history';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT score, page_weight, requests_count, dom_size, js_count, css_count, img_issues, created_at
             FROM {$table} WHERE object_type = %s AND object_id = %d ORDER BY created_at DESC LIMIT %d",
            $type, $object_id, $limit
        ), ARRAY_A );
    }

    /**
     * Get global history (site-wide score averages grouped by date).
     */
    public static function get_global( $days = 90 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ecodiag_history';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as date, AVG(score) as avg_score, AVG(page_weight) as avg_weight,
                    COUNT(DISTINCT object_id) as pages_audited
             FROM {$table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $days
        ), ARRAY_A );
    }

    /**
     * Get top N heaviest pages.
     */
    public static function get_heaviest_pages( $limit = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ecodiag_history';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT h.object_id, h.score, h.page_weight, h.created_at, p.post_title
             FROM {$table} h
             INNER JOIN (
                 SELECT object_id, MAX(created_at) as max_date
                 FROM {$table}
                 WHERE object_type = 'post'
                 GROUP BY object_id
             ) latest ON h.object_id = latest.object_id AND h.created_at = latest.max_date
             LEFT JOIN {$wpdb->posts} p ON h.object_id = p.ID
             WHERE h.object_type = 'post'
             ORDER BY h.page_weight DESC
             LIMIT %d",
            $limit
        ), ARRAY_A );
    }

    /**
     * Get top N worst scored pages.
     */
    public static function get_worst_scored( $limit = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ecodiag_history';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT h.object_id, h.score, h.page_weight, h.created_at, p.post_title
             FROM {$table} h
             INNER JOIN (
                 SELECT object_id, MAX(created_at) as max_date
                 FROM {$table}
                 WHERE object_type = 'post'
                 GROUP BY object_id
             ) latest ON h.object_id = latest.object_id AND h.created_at = latest.max_date
             LEFT JOIN {$wpdb->posts} p ON h.object_id = p.ID
             WHERE h.object_type = 'post'
             ORDER BY h.score ASC
             LIMIT %d",
            $limit
        ), ARRAY_A );
    }

    /**
     * Get global averages (latest audit per post).
     */
    public static function get_global_averages() {
        global $wpdb;
        $table = $wpdb->prefix . 'ecodiag_history';
        return $wpdb->get_row(
            "SELECT AVG(h.score) as avg_score, AVG(h.page_weight) as avg_weight,
                    SUM(h.img_issues) as total_img_issues, COUNT(DISTINCT h.object_id) as total_pages
             FROM {$table} h
             INNER JOIN (
                 SELECT object_id, MAX(created_at) as max_date
                 FROM {$table}
                 WHERE object_type = 'post'
                 GROUP BY object_id
             ) latest ON h.object_id = latest.object_id AND h.created_at = latest.max_date
             WHERE h.object_type = 'post'",
            ARRAY_A
        );
    }

    /**
     * Cleanup old history entries.
     */
    public static function cleanup() {
        global $wpdb;
        $table = $wpdb->prefix . 'ecodiag_history';
        $days  = (int) get_option( 'ecodiag_history_retention', 365 );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }
}
