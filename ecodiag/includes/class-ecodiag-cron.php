<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Scheduled audits and cron management.
 */
class EcoDiag_Cron {

    public function __construct() {
        add_action( 'ecodiag_daily_audit', array( $this, 'daily_tasks' ) );
        add_action( 'ecodiag_scheduled_audit', array( $this, 'run_full_audit' ) );
        add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );

        // Schedule the periodic audit if not scheduled
        $this->maybe_schedule_audit();
    }

    public function add_schedules( $schedules ) {
        $schedules['ecodiag_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Hebdomadaire (EcoDiag)', 'ecodiag' ),
        );
        $schedules['ecodiag_monthly'] = array(
            'interval' => MONTH_IN_SECONDS,
            'display'  => __( 'Mensuel (EcoDiag)', 'ecodiag' ),
        );
        return $schedules;
    }

    private function maybe_schedule_audit() {
        $freq = get_option( 'ecodiag_audit_frequency', 'monthly' );
        if ( $freq === 'never' ) {
            wp_clear_scheduled_hook( 'ecodiag_scheduled_audit' );
            return;
        }

        if ( ! wp_next_scheduled( 'ecodiag_scheduled_audit' ) ) {
            $recurrence = $freq === 'weekly' ? 'ecodiag_weekly' : 'ecodiag_monthly';
            wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, 'ecodiag_scheduled_audit' );
        }
    }

    /**
     * Daily tasks: cleanup history, check alerts.
     */
    public function daily_tasks() {
        EcoDiag_History::cleanup();
        $this->check_score_alert();
    }

    /**
     * Run a full site audit (batch processing).
     */
    public function run_full_audit() {
        $post_types = EcoDiag_Core::get_audited_post_types();
        $posts = get_posts( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );

        foreach ( $posts as $post_id ) {
            EcoDiag_Analyzer::audit_post( $post_id, true );
            // Prevent server overload
            if ( function_exists( 'wp_cache_flush' ) ) {
                wp_cache_flush();
            }
        }

        $this->check_score_alert();
    }

    /**
     * Check if global score is below threshold and send alert.
     */
    private function check_score_alert() {
        $threshold = (int) get_option( 'ecodiag_alert_threshold', 50 );
        $email     = get_option( 'ecodiag_alert_email', get_option( 'admin_email' ) );
        $averages  = EcoDiag_History::get_global_averages();

        if ( $averages && isset( $averages['avg_score'] ) && (int) $averages['avg_score'] < $threshold ) {
            $score = (int) $averages['avg_score'];
            $subject = sprintf(
                /* translators: %d: the current score */
                __( '[EcoDiag] Alerte : score global descendu à %d/100', 'ecodiag' ),
                $score
            );
            $message = sprintf(
                __( "Le score EcoDiag global de votre site est descendu à %d/100 (seuil d'alerte : %d).\n\nConsultez le tableau de bord EcoDiag pour identifier les pages à optimiser.\n\n%s", 'ecodiag' ),
                $score,
                $threshold,
                admin_url( 'admin.php?page=ecodiag' )
            );
            wp_mail( $email, $subject, $message );
        }
    }

    /**
     * Get orphaned cron events (from deleted plugins).
     */
    public static function get_orphaned_cron_events() {
        $crons = _get_cron_array();
        if ( ! $crons ) return array();

        $wp_core_hooks = array(
            'wp_privacy_delete_old_export_files', 'wp_cron_lock_timeout',
            'wp_version_check', 'wp_update_plugins', 'wp_update_themes',
            'wp_scheduled_delete', 'wp_scheduled_auto_draft_delete',
            'delete_expired_transients', 'wp_site_health_scheduled_check',
            'recovery_mode_clean_expired_keys', 'wp_https_detection',
        );

        $orphaned = array();
        foreach ( $crons as $timestamp => $cron_hooks ) {
            foreach ( $cron_hooks as $hook => $events ) {
                if ( in_array( $hook, $wp_core_hooks, true ) ) continue;
                if ( strpos( $hook, 'ecodiag_' ) === 0 ) continue;
                if ( ! has_action( $hook ) ) {
                    $orphaned[ $hook ] = array(
                        'next_run' => $timestamp,
                        'count'    => count( $events ),
                    );
                }
            }
        }
        return $orphaned;
    }
}
