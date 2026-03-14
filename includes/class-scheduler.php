<?php

namespace Chrysos_EMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scheduler {

    const CLEANUP_HOOK  = 'chrysos_ems_cleanup_dates';
    const TRANSIENT_KEY = 'chrysos_ems_sync';

    public function __construct() {
        add_action( 'init', [ $this, 'sync_state' ] );
        add_action( self::CLEANUP_HOOK, [ self::class, 'handle_cleanup_dates' ] );
    }

    /**
     * Plugin activation: schedule cleanup and force an immediate sync.
     */
    public static function on_activate(): void {
        if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CLEANUP_HOOK );
        }

        delete_transient( self::TRANSIENT_KEY );
    }

    /**
     * Plugin deactivation: clear cleanup cron and deactivate maintenance mode.
     */
    public static function on_deactivate(): void {
        wp_clear_scheduled_hook( self::CLEANUP_HOOK );
        delete_transient( self::TRANSIENT_KEY );
        Maintenance::deactivate();
    }

    /**
     * Apply settings immediately (called from Admin after saving).
     */
    public static function reschedule( ?array $settings = null ): void {
        if ( null === $settings ) {
            $settings = get_option( 'chrysos_ems_settings', [] );
        }

        delete_transient( self::TRANSIENT_KEY );

        if ( empty( $settings['enabled'] ) ) {
            Maintenance::deactivate();
            return;
        }

        $tz  = wp_timezone();
        $now = new \DateTime( 'now', $tz );

        if ( self::is_in_active_window( $settings, $now, $tz ) ) {
            Maintenance::activate();
        } else {
            Maintenance::deactivate();
        }
    }

    /**
     * Sync maintenance mode state on every page load (cached for 5 minutes).
     */
    public function sync_state(): void {
        if ( false !== get_transient( self::TRANSIENT_KEY ) ) {
            return;
        }

        $settings = get_option( 'chrysos_ems_settings', [] );

        if ( empty( $settings['enabled'] ) ) {
            set_transient( self::TRANSIENT_KEY, 'checked', 5 * MINUTE_IN_SECONDS );
            return;
        }

        $tz        = wp_timezone();
        $now       = new \DateTime( 'now', $tz );
        $should_be = self::is_in_active_window( $settings, $now, $tz );
        $is_active = Maintenance::is_active();

        if ( $should_be && ! $is_active ) {
            Maintenance::activate();
        } elseif ( ! $should_be && $is_active ) {
            Maintenance::deactivate();
        }

        set_transient( self::TRANSIENT_KEY, 'checked', 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Check if we are currently inside any active window (weekly or extra date).
     */
    public static function is_in_active_window( ?array $settings = null, ?\DateTime $now = null, ?\DateTimeZone $tz = null ): bool {
        if ( null === $settings ) {
            $settings = get_option( 'chrysos_ems_settings', [] );
        }
        if ( null === $tz ) {
            $tz = wp_timezone();
        }
        if ( null === $now ) {
            $now = new \DateTime( 'now', $tz );
        }

        // Check weekly window.
        if ( self::get_weekly_window( $settings, $now, $tz ) ) {
            return true;
        }

        // Check extra dates.
        if ( ! empty( $settings['extra_dates'] ) && is_array( $settings['extra_dates'] ) ) {
            foreach ( $settings['extra_dates'] as $entry ) {
                if ( empty( $entry['date'] ) || empty( $entry['start_time'] ) || empty( $entry['end_time'] ) ) {
                    continue;
                }

                $start = \DateTime::createFromFormat( 'Y-m-d H:i', $entry['date'] . ' ' . $entry['start_time'], $tz );
                $end   = \DateTime::createFromFormat( 'Y-m-d H:i', $entry['date'] . ' ' . $entry['end_time'], $tz );

                if ( ! $start || ! $end ) {
                    continue;
                }

                if ( $end <= $start ) {
                    $end->modify( '+1 day' );
                }

                if ( $now >= $start && $now < $end ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the current weekly window if we are inside it, or null.
     *
     * Returns [ 'start' => DateTime, 'end' => DateTime ] or null.
     */
    private static function get_weekly_window( array $settings, \DateTime $now, \DateTimeZone $tz ): ?array {
        if ( ! isset( $settings['weekly_start_day'], $settings['weekly_start_time'], $settings['weekly_end_day'], $settings['weekly_end_time'] ) ) {
            return null;
        }

        $start_day  = (int) $settings['weekly_start_day'];
        $end_day    = (int) $settings['weekly_end_day'];
        $start_time = $settings['weekly_start_time'];
        $end_time   = $settings['weekly_end_time'];

        // Build this week's start datetime.
        $current_dow = (int) $now->format( 'w' );

        // Calculate start relative to now.
        $diff_to_start = $start_day - $current_dow;
        $start_dt      = clone $now;
        $start_dt->modify( sprintf( '%+d days', $diff_to_start ) );
        $start_dt->setTime( ...array_map( 'intval', explode( ':', $start_time ) ) );

        // Calculate end relative to start.
        $diff_start_to_end = $end_day - $start_day;
        if ( $diff_start_to_end < 0 ) {
            $diff_start_to_end += 7;
        }
        if ( $diff_start_to_end === 0 && $end_time <= $start_time ) {
            $diff_start_to_end = 7;
        }

        $end_dt = clone $start_dt;
        $end_dt->modify( sprintf( '+%d days', $diff_start_to_end ) );
        $end_dt->setTime( ...array_map( 'intval', explode( ':', $end_time ) ) );

        // Check if now is inside [start, end).
        if ( $now >= $start_dt && $now < $end_dt ) {
            return [ 'start' => $start_dt, 'end' => $end_dt ];
        }

        // Also check previous week's window (it might still be active).
        $prev_start = clone $start_dt;
        $prev_start->modify( '-7 days' );
        $prev_end = clone $end_dt;
        $prev_end->modify( '-7 days' );

        if ( $now >= $prev_start && $now < $prev_end ) {
            return [ 'start' => $prev_start, 'end' => $prev_end ];
        }

        return null;
    }

    /**
     * Remove past extra dates from settings.
     */
    public static function handle_cleanup_dates(): void {
        $settings = get_option( 'chrysos_ems_settings', [] );

        if ( empty( $settings['extra_dates'] ) || ! is_array( $settings['extra_dates'] ) ) {
            return;
        }

        $today   = current_time( 'Y-m-d' );
        $cleaned = [];

        foreach ( $settings['extra_dates'] as $entry ) {
            if ( ! empty( $entry['date'] ) && $entry['date'] >= $today ) {
                $cleaned[] = $entry;
            }
        }

        if ( count( $cleaned ) < count( $settings['extra_dates'] ) ) {
            $settings['extra_dates'] = $cleaned;
            update_option( 'chrysos_ems_settings', $settings );
        }
    }
}
