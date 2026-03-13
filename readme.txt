=== Chrysos – Elementor Maintenance Mode Scheduling ===
Contributors: chrysos
Tags: maintenance, elementor, schedule, coming soon, cron
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule Elementor's maintenance or coming soon mode to turn on and off automatically every week and on specific dates you choose.

== Description ==

Pick a weekly window (e.g. Friday 18:00 to Saturday 19:00) and the plugin activates Elementor's maintenance mode at the start and deactivates it at the end. You can also add one-off dates for holidays or special events.

The plugin uses [Action Scheduler](https://actionscheduler.org/) for reliable background processing, so times are respected even on busy sites.

**How it works:**

1. You configure a weekly on/off window in Settings > Maintenance Schedule.
2. The plugin schedules activation and deactivation actions automatically.
3. When the window starts, maintenance mode turns on. When it ends, it turns off.
4. If you're inside the window when you save, maintenance mode activates immediately.

**Features:**

* Weekly recurring schedule with configurable day and time
* Extra one-off dates for holidays or special events
* Supports both Maintenance (HTTP 503) and Coming Soon (HTTP 200) modes
* Detects your site timezone and uses it for all scheduling
* WP-Cron help panel with setup instructions for server cron
* WordPress Abilities API support (WP 6.9+) for external tools and AI agents
* Translations included: Portuguese (Brazil), Spanish, Hebrew, French

**Requirements:**

* Elementor (free or Pro) must be installed and active
* A maintenance page template must be configured in Elementor > Tools > Maintenance Mode

== Installation ==

1. Upload the `chrysos-elementor-maintenance-scheduler` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Go to Settings > Maintenance Schedule to configure your weekly window and any extra dates.
4. Make sure you have a maintenance page template selected in Elementor > Tools > Maintenance Mode.

== Frequently Asked Questions ==

= Do I need Elementor Pro? =

No. The free version of Elementor includes maintenance mode. This plugin works with both free and Pro.

= What happens if my site has low traffic? =

WordPress runs scheduled tasks only when someone visits the site. On low-traffic sites, activation or deactivation may be delayed. The plugin includes a help panel in the settings page explaining how to set up a real server cron job for precise timing.

= Can the schedule span midnight? =

Yes. If the end time is earlier than the start time on the same day, the plugin treats it as ending the next day. For example, Friday 22:00 to Saturday 08:00 works as expected.

= What happens when I deactivate the plugin? =

All scheduled actions are cleared and maintenance mode is turned off. Your settings are kept in the database in case you reactivate later.

= What happens when I delete the plugin? =

The plugin removes its settings from the database via uninstall.php.

= What is the Abilities API support? =

On WordPress 6.9+, the plugin registers five abilities discoverable via REST API at `/wp-abilities/v1/`. External tools and AI agents can check maintenance status, activate/deactivate maintenance mode, read the schedule, and trigger a reschedule. All abilities require the `manage_options` capability.

== Screenshots ==

1. Settings page with status panel, weekly schedule, and extra dates.

== Changelog ==

= 1.0.0 =
* Initial release.
* Weekly recurring maintenance mode schedule.
* Extra one-off dates for holidays and events.
* Maintenance and Coming Soon mode support.
* WP-Cron help panel.
* WordPress Abilities API integration (WP 6.9+).
* Translations: pt_BR, es_ES, he_IL, fr_FR.
