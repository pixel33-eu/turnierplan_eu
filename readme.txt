=== Turnierplan.eu – Turniere einbetten ===
Contributors: pixel33
Tags: tournament, sports, schedule, standings, iframe
Requires at least: 6.5
Tested up to: 6.5
Stable tag: 0.1.0
Requires PHP: 8.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed public tournament schedules, standings, and results from Turnierplan.eu in WordPress.

== Description ==

Turnierplan.eu will provide a Gutenberg block, a shortcode, and reusable presets for public tournaments managed on Turnierplan.eu.

Version 0.1.0 is a development version. Its shortcode renders public tournament standings and schedules after an administrator enables the external service. The Gutenberg block and reusable presets are still being developed, so this version is not intended for production websites.

The plugin is developed independently for Turnierplan.eu and follows the WordPress Coding Standards. It does not create sample content or contact an external service during activation.

== Installation ==

1. Upload the `turnierplan-eu` directory to `/wp-content/plugins/`.
2. Activate “Turnierplan.eu – Turniere einbetten” in the WordPress plugin screen.
3. Open Settings → Turnierplan.eu and explicitly enable the external service.
4. Add `[turnierplan tournament="12345" view="standings"]` to a page.
5. Continue with the development roadmap before using the plugin on a public website.

== Frequently Asked Questions ==

= Does this version display tournaments? =

Yes, through the `[turnierplan]` shortcode after the external service is enabled. The Gutenberg editor interface is not included yet.

= Does activation send data to Turnierplan.eu? =

No. Activation only validates the runtime requirements and stores the installed plugin version in WordPress.

== Changelog ==

= 0.1.0 =

* Add the activatable plugin foundation, runtime checks, development environment, and quality tooling.
