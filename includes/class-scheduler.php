<?php

namespace Chrysos_EMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scheduler {

    const ACTION_ACTIVATE   = 'chrysos_ems_activate';
    const ACTION_DEACTIVATE = 'chrysos_ems_deactivate';
    const ACTION_CLEANUP    = 'chrysos_ems_cleanup_dates';
    const AS_GROUP          = 'chrysos-ems';

    public function __construct() {
        add_action( self::ACTION_ACTIVATE, [ $this, 'handle_activate' ] );
        add_action( self::ACTION_DEACTIVATE, [ $this, 'handle_deactivate' ] );
        add_action( self::ACTION_CLEANUP, [ self::class, 'handle_cleanup_dates' ] );
    }

    /**
     * Plugin activation: schedule if settings exist.
     */
    public static function on_activate(): void {
        self::schedule_cleanup();
        $settings = get_option( 'chrysos_ems_settings', [] );
        if ( ! empty( $settings['enabled'] ) ) {
            self::reschedule( $settings );
        }
    }

    /**
     * Plugin deactivation: clear all scheduled actions and deactivate maintenance mode.
     */
    public static function on_deactivate(): void {
        as_unschedule_all_actions( self::ACTION_ACTIVATE );
        as_unschedule_all_actions( self::ACTION_DEACTIVATE );
        as_unschedule_all_actions( self::ACTION_CLEANUP );
        Maintenance::deactivate();
    }

    /**
     * Reschedule all actions based on current settings.
     */
    public static function reschedule( ?array $settings = null ): void {
        if ( null === $settings ) {
            $settings = get_option( 'chrysos_ems_settings', [] );
        }

        // Clear existing actions.
        as_unschedule_all_actions( self::ACTION_ACTIVATE );
        as_unschedule_all_actions( self::ACTION_DEACTIVATE );

        if ( empty( $settings['enabled'] ) ) {
            Maintenance::deactivate();
            return;
        }

        $tz  = wp_timezone();
        $now = new \DateTime( 'now', $tz );

        // Schedule weekly actions.
        self::schedule_weekly( $settings, $now, $tz );

        // Schedule extra date actions.
        self::schedule_extra_dates( $settings, $now, $tz );

        // Immediate state: activate or deactivate based on current window.
        if ( self::is_in_active_window( $settings, $now, $tz ) ) {
            Maintenance::activate();
        } else {
            Maintenance::deactivate();
        }
    }

    /**
     * Schedule weekly activate/deactivate actions.
     */
    private static function schedule_weekly( array $settings, \DateTime $now, \DateTimeZone $tz ): void {
        if ( ! isset( $settings['weekly_start_day'], $settings['weekly_start_time'], $settings['weekly_end_day'], $settings['weekly_end_time'] ) ) {
            return;
        }

        $start = self::next_weekday_time( (int) $settings['weekly_start_day'], $settings['weekly_start_time'], $now, $tz );
        $end   = self::next_weekday_time( (int) $settings['weekly_end_day'], $settings['weekly_end_time'], $now, $tz );

        // If we are currently inside the weekly window, only schedule deactivation and next activation.
        $current_window = self::get_weekly_window( $settings, $now, $tz );
        if ( $current_window ) {
            as_schedule_single_action( $current_window['end']->getTimestamp(), self::ACTION_DEACTIVATE, [], self::AS_GROUP );
            as_schedule_single_action( $start->getTimestamp(), self::ACTION_ACTIVATE, [], self::AS_GROUP );
        } else {
            as_schedule_single_action( $start->getTimestamp(), self::ACTION_ACTIVATE, [], self::AS_GROUP );
            as_schedule_single_action( $end->getTimestamp(), self::ACTION_DEACTIVATE, [], self::AS_GROUP );
        }
    }

    /**
     * Schedule extra date actions.
     */
    private static function schedule_extra_dates( array $settings, \DateTime $now, \DateTimeZone $tz ): void {
        if ( empty( $settings['extra_dates'] ) || ! is_array( $settings['extra_dates'] ) ) {
            return;
        }

        foreach ( $settings['extra_dates'] as $entry ) {
            if ( empty( $entry['date'] ) || empty( $entry['start_time'] ) || empty( $entry['end_time'] ) ) {
                continue;
            }

            $start = \DateTime::createFromFormat( 'Y-m-d H:i', $entry['date'] . ' ' . $entry['start_time'], $tz );
            $end   = \DateTime::createFromFormat( 'Y-m-d H:i', $entry['date'] . ' ' . $entry['end_time'], $tz );

            if ( ! $start || ! $end ) {
                continue;
            }

            // If end is before start, it means end is next day.
            if ( $end <= $start ) {
                $end->modify( '+1 day' );
            }

            if ( $start > $now ) {
                as_schedule_single_action( $start->getTimestamp(), self::ACTION_ACTIVATE, [ 'extra_date' => $entry['date'] ], self::AS_GROUP );
            }

            if ( $end > $now ) {
                as_schedule_single_action( $end->getTimestamp(), self::ACTION_DEACTIVATE, [ 'extra_date' => $entry['date'] ], self::AS_GROUP );
            }
        }
    }

    /**
     * Handle activation action.
     */
    public function handle_activate(): void {
        Maintenance::activate();

        $settings = get_option( 'chrysos_ems_settings', [] );
        $tz       = wp_timezone();
        $now      = new \DateTime( 'now', $tz );

        // Schedule corresponding deactivation.
        $window = self::get_weekly_window( $settings, $now, $tz );
        if ( $window && $window['end'] > $now ) {
            as_schedule_single_action( $window['end']->getTimestamp(), self::ACTION_DEACTIVATE, [], self::AS_GROUP );
        }

        // Schedule next week's activation.
        if ( isset( $settings['weekly_start_day'], $settings['weekly_start_time'] ) ) {
            $next_start = self::next_weekday_time( (int) $settings['weekly_start_day'], $settings['weekly_start_time'], $now, $tz );
            as_schedule_single_action( $next_start->getTimestamp(), self::ACTION_ACTIVATE, [], self::AS_GROUP );
        }
    }

    /**
     * Handle deactivation action.
     */
    public function handle_deactivate(): void {
        $settings = get_option( 'chrysos_ems_settings', [] );
        $tz       = wp_timezone();
        $now      = new \DateTime( 'now', $tz );

        // Guard: don't deactivate if we're still inside another active window.
        if ( self::is_in_active_window( $settings, $now, $tz ) ) {
            return;
        }

        Maintenance::deactivate();
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
     * Schedule the weekly cleanup of past extra dates.
     */
    private static function schedule_cleanup(): void {
        if ( false === as_next_scheduled_action( self::ACTION_CLEANUP, [], self::AS_GROUP ) ) {
            as_schedule_recurring_action( time(), WEEK_IN_SECONDS, self::ACTION_CLEANUP, [], self::AS_GROUP );
        }
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

    /**
     * Calculate the next occurrence of a given weekday + time from now.
     */
    private static function next_weekday_time( int $target_day, string $time, \DateTime $now, \DateTimeZone $tz ): \DateTime {
        $current_dow = (int) $now->format( 'w' );
        $diff        = $target_day - $current_dow;

        $dt = clone $now;
        $dt->modify( sprintf( '%+d days', $diff ) );
        $dt->setTime( ...array_map( 'intval', explode( ':', $time ) ) );

        // If this time has already passed, advance to next week.
        if ( $dt <= $now ) {
            $dt->modify( '+7 days' );
        }

        return $dt;
    }
}
