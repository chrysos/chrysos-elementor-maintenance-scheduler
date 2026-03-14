<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'chrysos_ems_settings' );
delete_transient( 'chrysos_ems_sync' );
wp_clear_scheduled_hook( 'chrysos_ems_cleanup_dates' );
