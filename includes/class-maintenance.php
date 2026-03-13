<?php

namespace Chrysos_EMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maintenance {

    const MODE_MAINTENANCE = 'maintenance';
    const MODE_COMING_SOON = 'coming_soon';

    /**
     * Activate Elementor maintenance/coming-soon mode.
     *
     * @param string $mode 'maintenance' or 'coming_soon'. Defaults to saved setting.
     */
    public static function activate( string $mode = '' ): void {
        if ( ! class_exists( '\Elementor\Maintenance_Mode' ) ) {
            return;
        }
        if ( ! $mode ) {
            $settings = get_option( 'chrysos_ems_settings', [] );
            $mode     = $settings['mode'] ?? self::MODE_MAINTENANCE;
        }
        if ( ! in_array( $mode, [ self::MODE_MAINTENANCE, self::MODE_COMING_SOON ], true ) ) {
            $mode = self::MODE_MAINTENANCE;
        }
        update_option( 'elementor_maintenance_mode_mode', $mode );
    }

    /**
     * Deactivate Elementor maintenance mode.
     */
    public static function deactivate(): void {
        if ( ! class_exists( '\Elementor\Maintenance_Mode' ) ) {
            return;
        }
        update_option( 'elementor_maintenance_mode_mode', '' );
    }

    /**
     * Check if maintenance mode is currently active (either mode).
     */
    public static function is_active(): bool {
        return in_array( get_option( 'elementor_maintenance_mode_mode', '' ), [ self::MODE_MAINTENANCE, self::MODE_COMING_SOON ], true );
    }
}
