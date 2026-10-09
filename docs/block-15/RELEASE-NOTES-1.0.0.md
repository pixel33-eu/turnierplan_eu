# Turnierplan.eu – Embed tournaments 1.0.0

The first public release embeds public Turnierplan.eu tournament standings, schedules, and results in WordPress.

## Included

- Gutenberg block with standings and schedule variations
- `[turnierplan]` shortcode for the Classic Editor and page builders
- reusable, protected embed presets
- group, participant, match-number, and date filters
- responsive light, dark, and automatic display modes
- German and English WordPress interface text
- multisite-aware activation, upgrade, and uninstall handling

The external Turnierplan.eu service is disabled by default. An administrator must review the service information and enable it before WordPress makes metadata requests or renders public frames.

## Requirements

- WordPress 6.5 or later
- PHP 8.3 or later
- HTTPS

Download `turnierplan-eu-1.0.0.zip` and install it through **Plugins → Add New Plugin → Upload Plugin**. The accompanying `.sha256` file verifies the archive; the manifest records the exact source commit and packaged files.
