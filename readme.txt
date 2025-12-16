=== Taxonomy Depth Control ===

Contributors: ayoubkhan558
Tags: taxonomy, depth, terms, hierarchical, category, limit, control
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control maximum taxonomy depth for hierarchical taxonomies. Prevent deep nesting and customize taxonomy display with column hiding and custom level labels.

== Description ==

**Taxonomy Depth Control** provides granular control over hierarchical taxonomies in WordPress. Limit how deep users can nest categories, tags, and custom taxonomy terms, while customizing the admin interface with column visibility controls and custom level naming.

Perfect for site administrators who need to:
- Enforce consistent taxonomy structure across the site
- Prevent overly complex category hierarchies
- Simplify taxonomy management interfaces
- Provide custom naming for different taxonomy levels

= Key Features =

* **Depth Limiting**: Set maximum depth for any hierarchical taxonomy (categories, custom taxonomies, etc.)
* **Smart Validation**: Automatically prevents creation of terms exceeding the depth limit
* **Term Assignment Protection**: Filters out deep terms when assigning to posts
* **Column Visibility Control**: Hide Description, Slug, and Count columns per taxonomy
* **Custom Level Labels**: Define custom names for each taxonomy level (e.g., "Category", "Subcategory", "Product Type")
* **Visual Level Indicators**: Display level badges next to term names in taxonomy admin screens
* **Parent Dropdown Filtering**: Automatically removes invalid parent options when editing terms
* **User-Friendly Interface**: Tabbed settings page for easy per-taxonomy configuration
* **REST API Compatible**: Works with Gutenberg block editor and REST API term assignments
* **Translation Ready**: Fully internationalized for multi-language sites

== Installation ==

1. Upload the `taxonomy-depth-control` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Settings → Taxonomy Depth to configure
4. Set maximum depth and customize display options for each taxonomy

== Frequently Asked Questions ==

= What does "depth" mean? =

Depth refers to the number of ancestors a term has. Top-level terms have depth 0, their children have depth 1, grandchildren have depth 2, and so on.

= What happens if I set a depth limit after terms already exist? =

The plugin will prevent new terms from exceeding the limit and will filter out deep terms when assigning to posts. Existing deep terms won't be deleted but won't be assignable.

= Can I use this with custom taxonomies? =

Yes! The plugin works with all hierarchical taxonomies, including custom taxonomies created by themes or other plugins.

= Will this affect the Gutenberg editor? =

Yes, the plugin works seamlessly with both the classic and block editors. Deep terms will be disabled in the category selector in both interfaces.

== Screenshots ==

1. Settings page with tabbed interface for multiple taxonomies
2. Depth control and column visibility options
3. Custom level labels configuration
4. Level badges displayed in taxonomy admin screen

== Changelog ==

= 1.0.0 (2025-12-16) =
**Initial Release**

* Depth limiting for hierarchical taxonomies
* Prevent term creation beyond maximum depth
* Filter deep terms on post assignment
* Column visibility controls (Description, Slug, Count)
* Custom level label naming system
* Visual level badges in taxonomy screens
* Parent dropdown filtering on term edit
* Tabbed settings interface
* Translation-ready with text domain
* REST API and Gutenberg compatibility
* Admin notices for validation feedback
* Custom walker for term checklist
* Dynamic label input fields based on depth setting

== Upgrade Notice ==

= 1.0.0 =
Initial release of Taxonomy Depth Control. Configure depth limits and customize your taxonomy admin interface.

== Usage ==

After activation:

1. Go to **Settings → Taxonomy Depth**
2. Select a taxonomy tab (Categories, Tags, or custom taxonomies)
3. Set the **maximum depth** (0 = unlimited)
4. Choose which columns to hide (Description, Slug, Count)
5. Define **custom level labels** for better organization
6. Enable **level label display** to show badges next to term names
7. Click **Save Changes**

The plugin will immediately:
- Prevent creation of terms deeper than the limit
- Disable selection of deep terms in post editor
- Show validation messages when depth limits are exceeded
- Display custom level indicators in taxonomy screens

== Technical Details ==

The plugin uses WordPress hooks to:
- Hook into `pre_insert_term` to validate depth on creation
- Filter `pre_set_object_terms` to prevent deep term assignment
- Use custom walker for term checklist rendering
- Apply CSS-based column hiding for performance
- Leverage JavaScript for real-time UI updates

== Support ==

For bug reports, feature requests, or contributions, please visit the plugin repository.

== License ==

This plugin is licensed under GPLv2 or later.
