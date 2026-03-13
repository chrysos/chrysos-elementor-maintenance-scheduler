<?php
/**
 * Plugin Name: Chrysos – Elementor Maintenance Mode Scheduling
 * Plugin URI:  https://github.com/chrysos/chrysos-elementor-maintenance-scheduler
 * Description: Automatically activates/deactivates Elementor maintenance mode on a recurring weekly schedule (e.g. Shabbat) with support for extra manual dates.
 * Version:     1.0.0
 * Author:      chrysos
 * Author URI:  https://github.com/chrysos
 * License:     GPL-2.0-or-later
 * Requires Plugins: elementor
 * Text Domain: chrysos-ems
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CHRYSOS_EMS_VERSION', '1.0.0' );
define( 'CHRYSOS_EMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHRYSOS_EMS_FILE', __FILE__ );

// Load dependencies (needed early for activation/deactivation hooks).
require_once CHRYSOS_EMS_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
require_once CHRYSOS_EMS_PATH . 'includes/class-maintenance.php';
require_once CHRYSOS_EMS_PATH . 'includes/class-scheduler.php';
require_once CHRYSOS_EMS_PATH . 'includes/class-admin.php';

/**
 * Bootstrap after Elementor has loaded.
 */
add_action( 'plugins_loaded', function () {
    if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__( 'Chrysos EMS requires Elementor to be installed and active.', 'chrysos-ems' );
            echo '</p></div>';
        } );
        return;
    }

    new Chrysos_EMS\Admin();
    new Chrysos_EMS\Scheduler();
}, 20 );

// Activation.
register_activation_hook( __FILE__, [ 'Chrysos_EMS\\Scheduler', 'on_activate' ] );

// Deactivation.
register_deactivation_hook( __FILE__, [ 'Chrysos_EMS\\Scheduler', 'on_deactivate' ] );

// Uninstall is handled via uninstall.php.
