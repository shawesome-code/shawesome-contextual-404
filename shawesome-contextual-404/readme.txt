=== shawesome Contextual 404 – Different 404 Pages by Post Type and URL ===
Contributors: shawesome
Tags: 404, error page, post type, not found, multilingual
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shows a different 404 page depending on the requested URL: by post type, by taxonomy or by your own path rules.

== Description ==

A missing product should lead to the shop, a missing blog post to the blog. shawesome Contextual 404 shows the 404 page that fits the requested URL.

**404 pages**

Create 404 pages under *shawesome → Contextual 404* in the tab *404 Pages*, with the block editor or with Elementor. The column *Used for* shows where each page applies. They use the page template of your theme, so the title is shown as a heading. They have no public URL and are not part of the sitemaps of WordPress.

**Assign them**

In the tab *Settings* you assign a 404 page to every public post type, and a default for everything else. For a request, the plugin checks in this order:

1. **Path rules** – a prefix or a regular expression, in the order you entered them.
2. **Query vars** – what the rewrite rules of WordPress recognized: a post type, a taxonomy of a post type, a date.
3. **URL prefixes** – the rewrite slug or the archive of a post type, e.g. `/shop/`.
4. **Parent pages** – URLs below an existing page belong to the post type Pages.
5. **Default** – the page selected as the default, otherwise the 404 page of your theme.

With the permalink structure `/%postname%/`, posts and pages both live in the root. A URL such as `/xyz` does not reveal what was requested, so the default applies.

The field *Test URL* below the path rules shows which 404 page a URL would get, with the values not yet saved.

**Search engines**

The response keeps the status 404 and carries an `X-Robots-Tag: noindex` header. While the `<head>` is printed, WordPress reports a 404, so that SEO plugins handle the request as an error page.

**Languages**

404 pages can be translated with Polylang. Select a 404 page in any language, the plugin shows its translation in the current language. Language prefixes are not part of the paths in your rules. The language switcher leads to the home page of a language.

**Statistics**

The plugin counts 404 errors per month, by the path rule or post type that applied, otherwise as "Default". A dashboard widget shows the current month as a bar by category, the tab *404 Log* shows the last 12 months, together or one by one, with the most frequent URLs. Older months are deleted.

Only the path of a URL is stored, percent-encoded and without its query string, together with a counter. IP addresses, user agents and referrers are not stored. A month holds up to 1,000 rows; beyond that, new URLs are only counted by category. Requests answered by a page cache do not reach WordPress and are not counted.

Counting can be switched off in the tab *Settings*. The statistics can be deleted in the tab *404 Log*.

**For developers**

* Filter `shawesome_c404_page_id` – change the 404 page of a request.
* Filter `shawesome_c404_log_limit` – change the number of rows a month holds.
* Template `single-shawesome_404.php` in a theme – replaces the page template for 404 pages.
* All callbacks are named functions and can be removed with `remove_action()` / `remove_filter()`.

== Installation ==

1. Upload the folder `shawesome-contextual-404` to `/wp-content/plugins/` (or upload the ZIP via *Plugins → Add New*).
2. Activate the plugin.
3. Create your 404 pages under *shawesome → Contextual 404* in the tab *404 Pages*.
4. Assign them in the tab *Settings*.

== Frequently Asked Questions ==

= Posts and pages show the same 404 page. =

With the permalink structure `/%postname%/` they cannot be told apart, see the description. Use a prefix for posts, e.g. `/blog/%postname%/`.

= A 404 page is not shown. =

Only published 404 pages are shown. If an existing redirect applies to the URL, it takes precedence.

= Does it work in a multisite network? =

Yes. Each site has its own 404 pages, rules and statistics. On uninstall, the data of every site is removed.

= What happens on uninstall? =

The settings, all 404 pages and the statistics are deleted.

== Screenshots ==

1. Settings: a 404 page for every public post type and a default, path rules by prefix or regular expression, and the URL test showing which 404 page a URL gets.
2. 404 Log: errors by category and the most frequent URLs, with a shortcut to a new path rule.
3. The list of 404 pages with the column "Used for".
4. Dashboard widget with the 404 errors of the current month.
5. Front end: the 404 page of a path rule for a shop URL.
6. Front end: the 404 page for posts.
7. Front end: the 404 page for pages.

== Changelog ==

= 1.0.0 =
* Initial release.
