<?php

namespace Chrysos_EMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    const OPTION_KEY = 'chrysos_ems_settings';
    const PAGE_SLUG  = 'chrysos-ems-schedule';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Add settings page under Settings menu.
     */
    public function add_menu_page(): void {
        add_options_page(
            __( 'Maintenance Schedule', 'chrysos-ems' ),
            __( 'Maintenance Schedule', 'chrysos-ems' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings(): void {
        register_setting( 'chrysos_ems', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );

        // Section: General.
        add_settings_section( 'chrysos_ems_enable', __( 'General', 'chrysos-ems' ), [ $this, 'section_general_description' ], self::PAGE_SLUG );
        add_settings_field( 'enabled', __( 'Scheduling', 'chrysos-ems' ), [ $this, 'field_enabled' ], self::PAGE_SLUG, 'chrysos_ems_enable' );
        add_settings_field( 'mode', __( 'Mode', 'chrysos-ems' ), [ $this, 'field_mode' ], self::PAGE_SLUG, 'chrysos_ems_enable' );

        // Section: Weekly Schedule.
        add_settings_section( 'chrysos_ems_weekly', __( 'Weekly schedule', 'chrysos-ems' ), [ $this, 'section_weekly_description' ], self::PAGE_SLUG );
        add_settings_field( 'weekly_start', __( 'Turns on', 'chrysos-ems' ), [ $this, 'field_weekly_start' ], self::PAGE_SLUG, 'chrysos_ems_weekly' );
        add_settings_field( 'weekly_end', __( 'Turns off', 'chrysos-ems' ), [ $this, 'field_weekly_end' ], self::PAGE_SLUG, 'chrysos_ems_weekly' );

        // Section: Extra Dates.
        add_settings_section( 'chrysos_ems_extra', __( 'Extra dates', 'chrysos-ems' ), [ $this, 'section_extra_dates_description' ], self::PAGE_SLUG );
        add_settings_field( 'extra_dates', __( 'Dates', 'chrysos-ems' ), [ $this, 'field_extra_dates' ], self::PAGE_SLUG, 'chrysos_ems_extra' );
    }

    /**
     * Sanitize and validate settings before saving.
     */
    public function sanitize_settings( $input ): array {
        $clean = [];

        $clean['enabled'] = ! empty( $input['enabled'] );

        // Mode.
        $mode = $input['mode'] ?? Maintenance::MODE_MAINTENANCE;
        $clean['mode'] = in_array( $mode, [ Maintenance::MODE_MAINTENANCE, Maintenance::MODE_COMING_SOON ], true )
            ? $mode
            : Maintenance::MODE_MAINTENANCE;

        // Weekly schedule.
        $clean['weekly_start_day']  = self::sanitize_day( $input['weekly_start_day'] ?? 5 );
        $clean['weekly_start_time'] = self::sanitize_time( $input['weekly_start_time'] ?? '18:00' );
        $clean['weekly_end_day']    = self::sanitize_day( $input['weekly_end_day'] ?? 6 );
        $clean['weekly_end_time']   = self::sanitize_time( $input['weekly_end_time'] ?? '19:00' );

        // Extra dates.
        $clean['extra_dates'] = [];
        if ( ! empty( $input['extra_dates'] ) && is_array( $input['extra_dates'] ) ) {
            $today = current_time( 'Y-m-d' );
            foreach ( $input['extra_dates'] as $entry ) {
                $date = sanitize_text_field( $entry['date'] ?? '' );
                if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                    continue;
                }
                // Remove past dates.
                if ( $date < $today ) {
                    continue;
                }
                $clean['extra_dates'][] = [
                    'date'       => $date,
                    'start_time' => self::sanitize_time( $entry['start_time'] ?? '18:00' ),
                    'end_time'   => self::sanitize_time( $entry['end_time'] ?? '19:00' ),
                ];
            }
        }

        // Reschedule actions after saving.
        Scheduler::reschedule( $clean );

        return $clean;
    }

    /**
     * Render the settings page.
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = get_option( self::OPTION_KEY, [] );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php $this->render_info_panel( $settings ); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'chrysos_ems' );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the informational panel.
     */
    private function render_info_panel( array $settings ): void {
        $is_active = Maintenance::is_active();
        $tz_string = wp_timezone_string();
        $template_id = get_option( 'elementor_maintenance_mode_template_id', 0 );
        ?>
        <div class="card" style="max-width: 600px; margin-bottom: 20px; padding: 12px;">
            <h3 style="margin-top: 0;"><?php esc_html_e( 'Current status', 'chrysos-ems' ); ?></h3>
            <table class="form-table" role="presentation" style="margin: 0;">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Right now', 'chrysos-ems' ); ?></th>
                    <td>
                        <?php if ( $is_active ) :
                            $current_mode = get_option( 'elementor_maintenance_mode_mode', '' );
                            $mode_label   = $current_mode === 'coming_soon'
                                ? __( 'On — Coming Soon mode', 'chrysos-ems' )
                                : __( 'On — Maintenance mode', 'chrysos-ems' );
                        ?>
                            <span style="color: #d63638; font-weight: bold;">&#9679; <?php echo esc_html( $mode_label ); ?></span>
                        <?php else : ?>
                            <span style="color: #00a32a; font-weight: bold;">&#9679; <?php esc_html_e( 'Off — site is public', 'chrysos-ems' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Timezone', 'chrysos-ems' ); ?></th>
                    <td>
                        <code><?php echo esc_html( $tz_string ); ?></code>
                        &mdash; <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"><?php esc_html_e( 'Change', 'chrysos-ems' ); ?></a>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Template', 'chrysos-ems' ); ?></th>
                    <td>
                        <?php if ( $template_id ) : ?>
                            <?php echo esc_html( get_the_title( $template_id ) ); ?>
                        <?php else : ?>
                            <span style="color: #dba617;"><?php esc_html_e( 'None selected', 'chrysos-ems' ); ?></span>
                        <?php endif; ?>
                        &mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=elementor-tools#tab-maintenance_mode' ) ); ?>"><?php esc_html_e( 'Choose in Elementor', 'chrysos-ems' ); ?></a>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    // ---- Field callbacks ----

    public function field_enabled(): void {
        $settings = get_option( self::OPTION_KEY, [] );
        $checked  = ! empty( $settings['enabled'] );
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $checked ); ?>>
            <?php esc_html_e( 'Turn maintenance mode on and off automatically based on the schedule below', 'chrysos-ems' ); ?>
        </label>
        <?php
    }

    public function field_mode(): void {
        $settings = get_option( self::OPTION_KEY, [] );
        $mode     = $settings['mode'] ?? Maintenance::MODE_MAINTENANCE;
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mode]">
            <option value="<?php echo esc_attr( Maintenance::MODE_MAINTENANCE ); ?>" <?php selected( $mode, Maintenance::MODE_MAINTENANCE ); ?>>
                <?php esc_html_e( 'Maintenance', 'chrysos-ems' ); ?>
            </option>
            <option value="<?php echo esc_attr( Maintenance::MODE_COMING_SOON ); ?>" <?php selected( $mode, Maintenance::MODE_COMING_SOON ); ?>>
                <?php esc_html_e( 'Coming Soon', 'chrysos-ems' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'Maintenance returns HTTP 503, which tells search engines the site is temporarily down. Coming Soon returns HTTP 200, better for sites not yet launched. When in doubt, pick Maintenance.', 'chrysos-ems' ); ?>
        </p>
        <?php
    }

    public function field_weekly_start(): void {
        $settings = get_option( self::OPTION_KEY, [] );
        $day  = $settings['weekly_start_day'] ?? 5;
        $time = $settings['weekly_start_time'] ?? '18:00';
        $this->render_day_time_fields( 'weekly_start_day', 'weekly_start_time', $day, $time );
    }

    public function field_weekly_end(): void {
        $settings = get_option( self::OPTION_KEY, [] );
        $day  = $settings['weekly_end_day'] ?? 6;
        $time = $settings['weekly_end_time'] ?? '19:00';
        $this->render_day_time_fields( 'weekly_end_day', 'weekly_end_time', $day, $time );
    }

    public function section_general_description(): void {
        echo '<p>' . esc_html__( 'Enable or disable the automatic schedule below. While disabled, nothing happens, but your settings are kept.', 'chrysos-ems' ) . '</p>';
    }

    public function section_weekly_description(): void {
        echo '<p>' . esc_html__( 'Pick the day and time when maintenance mode turns on and off each week. All times use your site\'s timezone (shown in the Status panel above).', 'chrysos-ems' ) . '</p>';
    }

    public function section_extra_dates_description(): void {
        echo '<p>' . esc_html__( 'Add specific dates when you also want maintenance mode on, like holidays or one-off events. Past dates are removed automatically.', 'chrysos-ems' ) . '</p>';
    }

    public function field_extra_dates(): void {
        $settings    = get_option( self::OPTION_KEY, [] );
        $extra_dates = $settings['extra_dates'] ?? [];
        ?>
        <div id="chrysos-ems-extra-dates">
            <?php foreach ( $extra_dates as $i => $entry ) : ?>
                <div class="chrysos-ems-extra-row" style="margin-bottom: 8px;">
                    <input type="date"
                           name="<?php echo esc_attr( self::OPTION_KEY ); ?>[extra_dates][<?php echo (int) $i; ?>][date]"
                           value="<?php echo esc_attr( $entry['date'] ?? '' ); ?>"
                           required>
                    <input type="time"
                           name="<?php echo esc_attr( self::OPTION_KEY ); ?>[extra_dates][<?php echo (int) $i; ?>][start_time]"
                           value="<?php echo esc_attr( $entry['start_time'] ?? '18:00' ); ?>"
                           required>
                    <span>&rarr;</span>
                    <input type="time"
                           name="<?php echo esc_attr( self::OPTION_KEY ); ?>[extra_dates][<?php echo (int) $i; ?>][end_time]"
                           value="<?php echo esc_attr( $entry['end_time'] ?? '19:00' ); ?>"
                           required>
                    <button type="button" class="button chrysos-ems-remove-row">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="chrysos-ems-add-row">
            <?php esc_html_e( '+ Add Date', 'chrysos-ems' ); ?>
        </button>
        <?php
    }

    // ---- Helper methods ----

    private function render_day_time_fields( string $day_name, string $time_name, $day_value, string $time_value ): void {
        $days = [
            0 => __( 'Sunday', 'chrysos-ems' ),
            1 => __( 'Monday', 'chrysos-ems' ),
            2 => __( 'Tuesday', 'chrysos-ems' ),
            3 => __( 'Wednesday', 'chrysos-ems' ),
            4 => __( 'Thursday', 'chrysos-ems' ),
            5 => __( 'Friday', 'chrysos-ems' ),
            6 => __( 'Saturday', 'chrysos-ems' ),
        ];
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $day_name ); ?>]">
            <?php foreach ( $days as $num => $label ) : ?>
                <option value="<?php echo (int) $num; ?>" <?php selected( (int) $day_value, $num ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="time"
               name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $time_name ); ?>]"
               value="<?php echo esc_attr( $time_value ); ?>">
        <?php
    }

    private static function sanitize_day( $day ): int {
        $day = (int) $day;
        return ( $day >= 0 && $day <= 6 ) ? $day : 5;
    }

    private static function sanitize_time( $time ): string {
        if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time ) ) {
            return $time;
        }
        return '18:00';
    }

    /**
     * Admin notices.
     */
    public function admin_notices(): void {
        // Template not configured — show only on our settings page.
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'settings_page_' . self::PAGE_SLUG ) {
            $template_id = get_option( 'elementor_maintenance_mode_template_id', 0 );
            if ( ! $template_id ) {
                echo '<div class="notice notice-warning"><p>';
                printf(
                    /* translators: %s: link to Elementor maintenance mode settings */
                    esc_html__( 'You need to choose a maintenance page template in Elementor before the schedule can work. %s to pick one.', 'chrysos-ems' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=elementor-tools#tab-maintenance_mode' ) ) . '">' . esc_html__( 'Go to Elementor settings', 'chrysos-ems' ) . '</a>'
                );
                echo '</p></div>';
            }
        }
    }

    /**
     * Enqueue admin scripts on our settings page only.
     */
    public function enqueue_scripts( string $hook ): void {
        if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_add_inline_script( 'jquery', $this->get_inline_js() );
    }

    /**
     * Inline JS for extra dates repeatable fields.
     */
    private function get_inline_js(): string {
        $option_key = self::OPTION_KEY;
        return <<<JS
jQuery(function($) {
    var container = $('#chrysos-ems-extra-dates');
    var index = container.find('.chrysos-ems-extra-row').length;

    $('#chrysos-ems-add-row').on('click', function() {
        var row = $('<div class="chrysos-ems-extra-row" style="margin-bottom: 8px;"></div>');
        row.append('<input type="date" name="{$option_key}[extra_dates][' + index + '][date]" required> ');
        row.append('<input type="time" name="{$option_key}[extra_dates][' + index + '][start_time]" value="18:00" required> ');
        row.append('<span>&rarr;</span> ');
        row.append('<input type="time" name="{$option_key}[extra_dates][' + index + '][end_time]" value="19:00" required> ');
        row.append('<button type="button" class="button chrysos-ems-remove-row">&times;</button>');
        container.append(row);
        index++;
    });

    container.on('click', '.chrysos-ems-remove-row', function() {
        $(this).closest('.chrysos-ems-extra-row').remove();
    });
});
JS;
    }
}
