<?php

namespace Chrysos_EMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities {

	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	public function register_categories(): void {
		wp_register_ability_category( 'maintenance', [
			'label'       => __( 'Maintenance Mode', 'chrysos-elementor-maintenance-scheduler' ),
			'description' => __( 'Turn Elementor maintenance and coming soon modes on or off, check status, and manage the recurring schedule.', 'chrysos-elementor-maintenance-scheduler' ),
		] );
	}

	public function register_abilities(): void {
		$permission = [ self::class, 'permission_check' ];

		wp_register_ability( 'chrysos-ems/get-status', [
			'label'               => __( 'Get Maintenance Status', 'chrysos-elementor-maintenance-scheduler' ),
			'description'         => __( 'Check whether maintenance mode is on right now, which mode is active, and if the current time falls inside a scheduled window.', 'chrysos-elementor-maintenance-scheduler' ),
			'category'            => 'maintenance',
			'input_schema'        => null,
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'is_active'          => [ 'type' => 'boolean', 'description' => 'Whether maintenance mode is currently active.' ],
					'mode'               => [ 'type' => 'string', 'enum' => [ '', 'maintenance', 'coming_soon' ], 'description' => 'The current Elementor maintenance mode.' ],
					'in_active_window'   => [ 'type' => 'boolean', 'description' => 'Whether the current time is inside a scheduled active window.' ],
					'scheduling_enabled' => [ 'type' => 'boolean', 'description' => 'Whether automatic scheduling is enabled.' ],
				],
				'required'   => [ 'is_active', 'mode', 'in_active_window', 'scheduling_enabled' ],
			],
			'execute_callback'    => [ self::class, 'execute_get_status' ],
			'permission_callback' => $permission,
			'annotations'         => [ 'readonly' => true ],
			'show_in_rest'        => true,
		] );

		wp_register_ability( 'chrysos-ems/activate', [
			'label'               => __( 'Activate Maintenance Mode', 'chrysos-elementor-maintenance-scheduler' ),
			'description'         => __( 'Turn maintenance mode on. You can pass "maintenance" or "coming_soon" as the mode; if omitted, the plugin\'s saved setting is used.', 'chrysos-elementor-maintenance-scheduler' ),
			'category'            => 'maintenance',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'mode' => [
						'type'        => 'string',
						'enum'        => [ 'maintenance', 'coming_soon' ],
						'description' => 'The maintenance mode to activate. Defaults to the saved plugin setting.',
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'mode'    => [ 'type' => 'string', 'enum' => [ 'maintenance', 'coming_soon' ] ],
				],
				'required'   => [ 'success', 'mode' ],
			],
			'execute_callback'    => [ self::class, 'execute_activate' ],
			'permission_callback' => $permission,
			'annotations'         => [ 'destructive' => true, 'idempotent' => true ],
			'show_in_rest'        => true,
		] );

		wp_register_ability( 'chrysos-ems/deactivate', [
			'label'               => __( 'Deactivate Maintenance Mode', 'chrysos-elementor-maintenance-scheduler' ),
			'description'         => __( 'Turn maintenance mode off so the site is publicly accessible again.', 'chrysos-elementor-maintenance-scheduler' ),
			'category'            => 'maintenance',
			'input_schema'        => null,
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
				],
				'required'   => [ 'success' ],
			],
			'execute_callback'    => [ self::class, 'execute_deactivate' ],
			'permission_callback' => $permission,
			'annotations'         => [ 'destructive' => true, 'idempotent' => true ],
			'show_in_rest'        => true,
		] );

		wp_register_ability( 'chrysos-ems/get-schedule', [
			'label'               => __( 'Get Maintenance Schedule', 'chrysos-elementor-maintenance-scheduler' ),
			'description'         => __( 'Read the current schedule: weekly on/off times, any extra dates, which mode is configured, and the site timezone.', 'chrysos-elementor-maintenance-scheduler' ),
			'category'            => 'maintenance',
			'input_schema'        => null,
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'enabled'           => [ 'type' => 'boolean' ],
					'mode'              => [ 'type' => 'string' ],
					'weekly_start_day'  => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 6 ],
					'weekly_start_time' => [ 'type' => 'string' ],
					'weekly_end_day'    => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 6 ],
					'weekly_end_time'   => [ 'type' => 'string' ],
					'extra_dates'       => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'date'       => [ 'type' => 'string', 'format' => 'date' ],
								'start_time' => [ 'type' => 'string' ],
								'end_time'   => [ 'type' => 'string' ],
							],
						],
					],
					'timezone'          => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ self::class, 'execute_get_schedule' ],
			'permission_callback' => $permission,
			'annotations'         => [ 'readonly' => true ],
			'show_in_rest'        => true,
		] );

		wp_register_ability( 'chrysos-ems/reschedule', [
			'label'               => __( 'Rebuild Schedule', 'chrysos-elementor-maintenance-scheduler' ),
			'description'         => __( 'Clear and recreate all scheduled on/off actions from the saved settings. Safe to call repeatedly; it rebuilds from scratch each time.', 'chrysos-elementor-maintenance-scheduler' ),
			'category'            => 'maintenance',
			'input_schema'        => null,
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success'       => [ 'type' => 'boolean' ],
					'is_active_now' => [ 'type' => 'boolean', 'description' => 'Whether maintenance mode is active after rescheduling.' ],
				],
				'required'   => [ 'success', 'is_active_now' ],
			],
			'execute_callback'    => [ self::class, 'execute_reschedule' ],
			'permission_callback' => $permission,
			'annotations'         => [ 'idempotent' => true ],
			'show_in_rest'        => true,
		] );
	}

	public static function permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function execute_get_status(): array {
		$settings = get_option( 'chrysos_ems_settings', [] );

		return [
			'is_active'          => Maintenance::is_active(),
			'mode'               => get_option( 'elementor_maintenance_mode_mode', '' ),
			'in_active_window'   => Scheduler::is_in_active_window(),
			'scheduling_enabled' => ! empty( $settings['enabled'] ),
		];
	}

	/**
	 * @param array|null $input Optional input with 'mode' key.
	 * @return array|\WP_Error
	 */
	public static function execute_activate( $input ) {
		if ( ! class_exists( '\Elementor\Maintenance_Mode' ) ) {
			return new \WP_Error( 'elementor_missing', 'Elementor must be active for this to work.', [ 'status' => 500 ] );
		}

		$mode = '';
		if ( is_array( $input ) && isset( $input['mode'] ) ) {
			$mode = $input['mode'];
		}

		Maintenance::activate( $mode );

		return [
			'success' => true,
			'mode'    => get_option( 'elementor_maintenance_mode_mode', '' ),
		];
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function execute_deactivate() {
		if ( ! class_exists( '\Elementor\Maintenance_Mode' ) ) {
			return new \WP_Error( 'elementor_missing', 'Elementor must be active for this to work.', [ 'status' => 500 ] );
		}

		Maintenance::deactivate();

		return [ 'success' => true ];
	}

	public static function execute_get_schedule(): array {
		$settings = get_option( 'chrysos_ems_settings', [] );

		return [
			'enabled'           => ! empty( $settings['enabled'] ),
			'mode'              => $settings['mode'] ?? 'maintenance',
			'weekly_start_day'  => (int) ( $settings['weekly_start_day'] ?? 5 ),
			'weekly_start_time' => $settings['weekly_start_time'] ?? '18:00',
			'weekly_end_day'    => (int) ( $settings['weekly_end_day'] ?? 6 ),
			'weekly_end_time'   => $settings['weekly_end_time'] ?? '19:00',
			'extra_dates'       => $settings['extra_dates'] ?? [],
			'timezone'          => wp_timezone_string(),
		];
	}

	public static function execute_reschedule(): array {
		Scheduler::reschedule();

		return [
			'success'       => true,
			'is_active_now' => Maintenance::is_active(),
		];
	}
}
