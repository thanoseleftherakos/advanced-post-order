=== Advanced Post Order ===
Contributors: bracket
Tags: post order, custom order, drag and drop, taxonomy order, reorder
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop ordering for posts, pages, custom post types, and taxonomy terms — with per-category post ordering that other plugins don't offer.

== Description ==

**Advanced Post Order** gives you complete control over how your content is sorted — directly from the native WordPress admin screens you already use. No new interfaces to learn, no separate reorder pages. Just drag and drop.

What sets this plugin apart is **per-term post ordering**: the ability to define a different post order for each individual category, tag, or custom taxonomy term. Show your products in one order on "Summer Collection" and a completely different order on "Best Sellers" — each term maintains its own independent sort.

= Three Types of Ordering =

**1. Global Post Ordering**
Drag-and-drop to reorder posts, pages, and custom post types on the standard admin list table. The new order is saved to `menu_order` and automatically applied on the front end.

**2. Per-Term Post Ordering**
Filter your admin list by a category or taxonomy term, and the interface switches to per-term mode. Drag posts into the order you want for *that specific term*. Assign the same post to multiple categories — each one keeps its own sort. New posts added to a term automatically appear at the end of the custom order.

**3. Taxonomy Term Ordering**
Reorder categories, tags, and custom taxonomy terms themselves via drag-and-drop on the native `edit-tags.php` screen. The new term order is applied to `get_terms()` queries and navigation menus on the front end.

= How It Works =

1. Go to **Settings > Advanced Post Order**
2. Toggle on the post types you want to reorder
3. Toggle on taxonomies for per-term post ordering
4. Toggle on taxonomies for term reordering
5. Visit your admin list pages and start dragging

Changes save automatically via AJAX — no page refresh needed.

= Built for WordPress, Not Against It =

* Works directly inside native admin list tables (`edit.php` and `edit-tags.php`)
* Uses standard `menu_order` for global ordering — compatible with any theme
* Uses `term_order` for taxonomy terms — the same column WordPress defines
* Per-term order stored as term meta — clean, portable, conflict-free
* Front-end queries are modified transparently via `pre_get_posts` and `posts_clauses`
* Explicit `orderby` parameters (date, title, etc.) are never overridden

= Works With =

* Any public custom post type (portfolios, team members, testimonials, events, FAQs, services)
* WooCommerce products and product categories
* Any registered taxonomy with a UI
* Page builders that use standard `WP_Query` (Elementor, Divi, Beaver Builder)
* Themes that follow WordPress template hierarchy

= For Developers =

Advanced Post Order provides hooks so you can extend or control its behavior:

`// Filter: skip per-term ordering for a specific query
add_filter( 'apo_apply_term_post_order', function( $apply, $term_id, $query ) {
    // Return false to skip
    return $apply;
}, 10, 3 );`

`// Filter: modify the retrieved term post order
add_filter( 'apo_get_term_post_order', function( $ordered_ids, $term_id ) {
    return $ordered_ids;
}, 10, 2 );`

`// Actions: fired after order is saved via drag-and-drop
do_action( 'apo_global_order_updated', $post_ids );
do_action( 'apo_term_post_order_updated', $term_id, $post_ids );
do_action( 'apo_term_order_updated', $term_ids );`

To apply per-term order in custom queries, set `orderby` to `menu_order` and include a `tax_query` with a single term:

`$query = new WP_Query( [
    'post_type' => 'product',
    'orderby'   => 'menu_order',
    'order'     => 'ASC',
    'tax_query' => [ [
        'taxonomy' => 'product-category',
        'field'    => 'term_id',
        'terms'    => 42,
    ] ],
] );`

The plugin will automatically apply the saved per-term order via `FIELD()` SQL — posts not in the saved order appear at the end.

== Installation ==

= Automatic Installation =

1. In your WordPress admin, go to **Plugins > Add New**
2. Search for "Advanced Post Order"
3. Click **Install Now**, then **Activate**
4. Go to **Settings > Advanced Post Order** to configure

= Manual Installation =

1. Download the plugin ZIP file
2. Upload the `advanced-post-order` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu
4. Go to **Settings > Advanced Post Order** to configure

= Configuration =

1. Navigate to **Settings > Advanced Post Order**
2. In the **Post Types** card, toggle on each post type you want to reorder
3. In the **Per-Term Post Ordering** card, toggle on taxonomies (taxonomies appear automatically based on your enabled post types)
4. In the **Term Ordering** card, toggle on taxonomies whose terms you want to reorder
5. Click **Save Changes**

That's it. Visit any enabled post type list and start dragging rows.

== Frequently Asked Questions ==

= Does this work with custom post types? =

Yes. Any post type with `show_ui => true` appears in the settings. This includes WooCommerce products, portfolio items, team members, testimonials, events, and any custom post type registered by your theme or plugins.

= How does per-term ordering work? =

When you filter the admin post list by a taxonomy term (e.g., selecting a specific category from the dropdown), the plugin switches to per-term mode. An info notice appears confirming the mode. Drag posts into the order you want — that order is saved exclusively for that term.

The same post can belong to multiple terms, and each term maintains its own independent order.

= Does the custom order appear on the front end? =

Yes. The plugin hooks into `pre_get_posts` and `posts_clauses` to automatically apply the saved order to front-end queries. For global ordering, it sets `orderby => menu_order`. For per-term ordering, it modifies the SQL `ORDER BY` clause using `FIELD()` so your saved order is respected while keeping pagination and other query parameters intact.

= What if I set an explicit orderby in my query? =

The plugin never overrides explicit `orderby` parameters other than `menu_order`. If your query uses `orderby => date`, `title`, `meta_value`, or anything else, the plugin leaves it alone. This is by design — your code always takes priority.

= Does it work with WooCommerce? =

Yes. Enable the "Products" post type and relevant product taxonomies in the settings. The plugin works with WooCommerce product categories and tags for both global and per-term ordering.

= Can I reorder taxonomy terms (categories, tags)? =

Yes. Enable term ordering for any taxonomy in the settings. Then go to that taxonomy's admin page (e.g., Posts > Categories) and drag terms into your preferred order. The new order is applied to `get_terms()` queries on the front end.

= Does it affect search results? =

No. The plugin only applies its ordering to queries for enabled post types that don't have an explicit `orderby` set. WordPress search queries use relevance-based sorting, which the plugin does not interfere with.

= Is it compatible with page builders? =

Yes. Elementor, Divi, Beaver Builder, and other page builders that use standard `WP_Query` will respect the custom order. If the builder lets you set `orderby` to `menu_order`, the per-term ordering also works automatically.

= What happens when I add a new post to a category that has a custom order? =

New posts that aren't part of the saved per-term order automatically appear at the end. You can then go to the admin, filter by that term, and drag the new post into position.

= Is it compatible with Simple Custom Post Order (SCPO)? =

The plugin detects SCPO and displays an admin notice recommending deactivation. If both are active, Advanced Post Order dequeues SCPO's scripts on pages where APO is active to prevent conflicts. For best results, deactivate SCPO before using this plugin.

= Does it support multisite? =

Yes. The plugin works on individual sites within a multisite network. Each site maintains its own settings and post order.

= How do I reset the order? =

For global order: simply drag posts back to your desired positions, or sort by date/title using column headers.

For per-term order: the order is stored as term meta. Removing the term or deleting the plugin (via uninstall) cleans up all saved per-term order data.

= Does clicking a column header disable drag-and-drop? =

Yes. When you click a column header to sort by title, date, etc., the plugin detects the `orderby` URL parameter and disables drag-and-drop. Removing the sort (clicking the post type menu link again) re-enables it.

== Screenshots ==

1. Settings page — modern card-based UI with toggle switches for post types, taxonomies, and term ordering
2. Global ordering — drag-and-drop rows on the standard post list table
3. Per-term ordering — filter by a category, then drag posts into a term-specific order
4. Term ordering — drag-and-drop taxonomy terms on the edit-tags screen

== Changelog ==

= 1.0.2 =
* Fixed table layout during drag-and-drop on some environments
* Improved first-time post ordering initialization
* Auto-detection and repair of menu_order gaps

= 1.0.1 =
* Improved WordPress coding standards compliance
* Settings page UX improvements
* Minor code quality and compatibility fixes

= 1.0.0 =
* Initial release
* Global post ordering via `menu_order` with drag-and-drop on admin list tables
* Per-taxonomy-term post ordering — independent sort order for each category/tag/term
* Taxonomy term ordering via drag-and-drop on `edit-tags.php`
* Modern settings page with card layout and toggle switches
* Dynamic taxonomy visibility — toggling a post type instantly shows its taxonomies
* Transparent `WP_Query` integration via `pre_get_posts` and `posts_clauses`
* Per-term ordering uses SQL `FIELD()` for native pagination support
* Automatic conflict detection and script dequeuing for Simple Custom Post Order
* Developer hooks: `apo_apply_term_post_order`, `apo_get_term_post_order`, `apo_global_order_updated`, `apo_term_post_order_updated`, `apo_term_order_updated`
* Clean uninstall — removes options and term meta on plugin deletion
* Full internationalization support with `.pot` template

== Upgrade Notice ==

= 1.0.0 =
Initial release. If migrating from Simple Custom Post Order, your existing `menu_order` values are preserved — just enable the same post types in the APO settings.
