<?php
/**
 * Plugin Name: Chrysos – Elementor Maintenance Mode Scheduling
 * Plugin URI:  https://github.com/chrysos/chrysos-elementor-maintenance-scheduler
 * Description: Schedule Elementor's maintenance or coming soon mode to turn on and off automatically every week (e.g. Shabbat, off-peak hours) and on specific dates you choose.
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
require_once CHRYSOS_EMS_PATH . 'includes/class-abilities.php';

// Load translations.
add_action( 'init', function () {
    load_plugin_textdomain( 'chrysos-ems', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/**
 * Bootstrap after Elementor has loaded.
 */
add_action( 'plugins_loaded', function () {
    if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__( 'Maintenance Schedule requires the Elementor plugin. Please install and activate Elementor first.', 'chrysos-ems' );
            echo '</p></div>';
        } );
        return;
    }

    new Chrysos_EMS\Admin();
    new Chrysos_EMS\Scheduler();

    if ( function_exists( 'wp_register_ability' ) ) {
        new Chrysos_EMS\Abilities();
    }
}, 20 );

// Settings link on the Plugins page.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $url = admin_url( 'options-general.php?page=chrysos-ems-schedule' );
    array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Settings', 'chrysos-ems' ) . '</a>' );
    return $links;
} );

// Activation.
register_activation_hook( __FILE__, [ 'Chrysos_EMS\\Scheduler', 'on_activate' ] );

// Deactivation.
register_deactivation_hook( __FILE__, [ 'Chrysos_EMS\\Scheduler', 'on_deactivate' ] );

// Uninstall is handled via uninstall.php.
