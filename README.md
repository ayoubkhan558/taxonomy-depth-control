=== Taxonomy Depth Control ===

Contributors: copilot
Tags: taxonomy, depth, terms
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control maximum taxonomy depth per hierarchical taxonomy.

Installation
- Copy the `taxonomy-depth-control` folder into `wp-content/plugins`.
- Activate the plugin in the WordPress admin.

Usage
- Go to Settings → Taxonomy Depth to set a maximum depth (0 = unlimited) per hierarchical taxonomy.
- The plugin will prevent creating terms that would exceed the depth and will disable selection of deeper terms in the post editor.

Notes
- When terms deeper than the configured max are assigned (programmatically or via REST), they will be filtered out on save and an admin notice will display.
