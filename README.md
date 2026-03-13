# Chrysos – Elementor Maintenance Mode Scheduling

Schedule Elementor's maintenance or coming soon mode to turn on and off automatically every week and on specific dates you choose.

## What it does

You pick a weekly window (e.g. Friday 18:00 to Saturday 19:00) and the plugin activates Elementor's maintenance mode at the start and deactivates it at the end. You can also add one-off dates for holidays or special events.

The plugin uses [Action Scheduler](https://actionscheduler.org/) under the hood, so the timing is reliable even on busy sites.

## Requirements

- WordPress 6.0+
- Elementor (free or Pro)
- PHP 7.4+

## Installation

1. Upload the `chrysos-elementor-maintenance-scheduler` folder to `wp-content/plugins/`.
2. Activate the plugin in **Plugins**.
3. Go to **Settings > Maintenance Schedule** to configure your weekly window and any extra dates.

Before the schedule does anything useful, you also need to pick a maintenance page template in Elementor. Go to **Elementor > Tools > Maintenance Mode** and select a template there.

## Settings

### General

- **Scheduling** — checkbox to enable or disable the automatic schedule. Turning it off keeps your settings but stops all automatic switching.
- **Mode** — Maintenance (HTTP 503, tells search engines the site is temporarily down) or Coming Soon (HTTP 200, better for sites not yet launched).

### Weekly schedule

- **Turns on** — day and time when maintenance mode activates each week.
- **Turns off** — day and time when it deactivates.

All times use your site's timezone (Settings > General in WordPress).

### Extra dates

Add specific dates when you also want maintenance mode on, like holidays or one-off maintenance windows. Each entry has a date, start time, and end time. Past dates are cleaned up automatically.

## WordPress Abilities API

On WordPress 6.9+ the plugin registers five abilities discoverable via the REST API at `/wp-abilities/v1/`:

| Ability | What it does |
|---------|-------------|
| `chrysos-ems/get-status` | Check if maintenance mode is on, which mode, and if you're inside a scheduled window |
| `chrysos-ems/activate` | Turn maintenance mode on (optionally pass `maintenance` or `coming_soon`) |
| `chrysos-ems/deactivate` | Turn maintenance mode off |
| `chrysos-ems/get-schedule` | Read the full schedule configuration |
| `chrysos-ems/reschedule` | Clear and rebuild all scheduled actions from saved settings |

All abilities require the `manage_options` capability. On older WordPress versions without the Abilities API, the plugin works normally without these endpoints.

## How the schedule works

1. When you save settings, the plugin calculates the next activation and deactivation times and registers them with Action Scheduler.
2. At each scheduled time, Action Scheduler fires the corresponding hook and the plugin toggles Elementor's maintenance mode option.
3. After each activation, the plugin schedules the next deactivation (and vice versa), so the weekly cycle continues without manual intervention.
4. If the site is inside an active window when you save, maintenance mode turns on immediately. If outside, it turns off.

## Uninstall

Deactivating the plugin clears all scheduled actions and turns off maintenance mode. Deleting the plugin also removes the `chrysos_ems_settings` option from the database.

## License

GPL-2.0-or-later
