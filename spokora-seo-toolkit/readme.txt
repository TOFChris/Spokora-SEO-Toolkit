=== Spokora SEO Toolkit ===
Contributors: chrismcmanus
Donate link: https://spokora.com/#licenses
Tags: seo, schema, sitemap, redirects, xml-sitemap
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.28
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO toolkit for WordPress: titles, schema, sitemap, and redirects. No account and no license key.

== Description ==

Spokora SEO Toolkit is a GPL SEO plugin for WordPress websites: titles and meta, JSON-LD schema, an XML sitemap, exact 301 redirects, import from Yoast / Rank Math / AIOSEO / SEOPress, a one-button backup zip, search SEO scoring in the editor, `/llms.txt` and markdown URLs, optional IndexNow pings, an optional cookie banner with optional GA4 after Accept, and light performance helpers.

No Spokora account. No license key. SEO features in this plugin are not locked behind payment.

[Spokora Pro](https://spokora.com) is a **separate** commercial plugin (agency week, API vault, Search Console, rank tracking). Do not run this plugin and Spokora Pro at the same time; Pro already includes this SEO surface.

= Features =

* Titles, meta descriptions, canonical URLs, robots, editor metabox
* JSON-LD schema with site defaults and per-page overrides
* XML sitemap
* Exact 301 redirect rules
* Import titles, descriptions, keyphrases, and exact 301s from major SEO plugins (you confirm before deactivating the old plugin)
* Quick setup wizard (backup first when migrating)
* One-click database + `wp-content` zip (does not include `wp-config.php`)
* Optional cookie banner (off until you turn it on)
* Optional GA4 Measurement ID — loads only after the visitor accepts non-essential cookies
* `/llms.txt`, `/ai-docs/{slug}.md`, `/md/{slug}.md`
* Optional IndexNow pings (off until you turn them on)
* In-editor search SEO score (model drafting stays in Pro)

Product site: [spokora.com](https://spokora.com)
Source: [github.com/TOFChris/Spokora-SEO-Toolkit](https://github.com/TOFChris/Spokora-SEO-Toolkit)

= External services =

**IndexNow** (optional, off until you enable Spokora → Search ping). When a public page is published, updated, or unpublished, the plugin sends that page URL, this site’s host, and an IndexNow key to `https://api.indexnow.org/indexnow`. Participating search engines (not Google) use that to recrawl. Nothing is sent while Search ping is off.

Service: [IndexNow](https://www.indexnow.org/). Documentation and protocol terms: [indexnow.org/documentation](https://www.indexnow.org/documentation). The shared endpoint is operated with Microsoft Bing: [Microsoft Privacy Statement](https://privacy.microsoft.com/privacystatement).

**Google Analytics 4** (optional). If you paste a Measurement ID under Cookies & privacy and a visitor clicks Accept on the cookie banner, the browser loads `https://www.googletagmanager.com/gtag/js` and sends page-view data to your Google Analytics property. The tag does not load until Accept. Spokora does not receive that analytics data.

Google Analytics terms: [google.com/analytics/terms](https://www.google.com/analytics/terms/). Google Tag Manager use policy: [tag manager use policy](https://marketingplatform.google.com/about/analytics/tag-manager/use-policy/). Google privacy policy: [policies.google.com/privacy](https://policies.google.com/privacy).

Support on WordPress.org: [wordpress.org/support/plugin/spokora-seo-toolkit/](https://wordpress.org/support/plugin/spokora-seo-toolkit/)
Optional extra forum: [forum.spokora.com](https://forum.spokora.com/)

== Installation ==

1. Install from Plugins → Add New, or upload the `spokora-seo-toolkit` folder to `/wp-content/plugins/`.
2. Activate **Spokora SEO Toolkit**. On **multisite**, use **Network Activate** so every site in the network gets SEO, sitemap, redirects, and `/llms.txt`.
3. Open **Spokora → Quick setup** on each site that needs its own Business Info (shop NAP is per site).
4. Optional: **Cookies & privacy** to show a banner; **Search ping** to enable IndexNow.

== Frequently Asked Questions ==

= Does this plugin phone home to spokora.com? =

No. Directory builds update through WordPress.org. Optional emails on spokora.com/updates are a form you fill in yourself.

= Do I need an account or license? =

No for Spokora SEO Toolkit.

= Can I run this next to Spokora Pro? =

No. Activate Pro and leave this plugin off. Pro includes this SEO layer.

= Does it deactivate Yoast or Rank Math by itself? =

No. Import copies data into Spokora. Deactivating the old plugin is a separate, confirmed step.

= Is the cookie banner required? =

No. It is off on new installs until you enable it under Cookies & privacy.

= Does it work on WordPress multisite / subdomains? =

Yes. Network Activate so each site in the network runs the plugin. Titles, schema, sitemap, redirects, and `/llms.txt` are per site — a shop subdomain keeps its own Business Info. If you use ThreeWP Broadcast, SEO box fields copy with the post. Do not run this plugin next to Spokora Pro.

== Changelog ==

= 0.1.28 =
* Admin menu sits after Comments and before Appearance, same place as Spokora Pro. A tall side list shifts up and scrolls instead of running off the screen.

= 0.1.27 =
* Directory review: display name Spokora SEO Toolkit, text domain spokora-seo-toolkit.
* Setup screen styles are enqueued. JSON-LD is encoded inside the script tag. Business Info saves only known fields.
* Admin menu sits after Settings. IndexNow and Google Analytics terms are documented above. Both stay off until you opt in.
* Legacy HW Core option and post-meta keys are copied once to Spokora keys.

= 0.1.26 =
* Multisite: Network Activate seeds every site; Broadcast copies hidden SEO/LLMS post meta.

= 0.1.25 =
* Remove “Tested up to” from the plugin PHP header (WordPress.org scan: that field belongs only in readme.txt).

= 0.1.24 =
* WordPress.org Plugin Check: PHPCS fixes (i18n, escaping, redirects, backup export, GA4 enqueue).
* Admin menu icon scaled to match other dashboard icons.

= 0.1.23 =
* Broader Business type list on Quick setup and Business Info (software, trades, retail, and more schema.org types).

= 0.1.22 =
* Encode < and & in JSON-LD so custom schema cannot break out of the script tag.

= 0.1.21 =
* WordPress.org Contributors username: chrismcmanus.

= 0.1.20 =
* WordPress.org-ready: removed custom spokora.com updater and Update URI.
* Text domain matched the directory slug at that time.
* Cookie banner and IndexNow are opt-in.
* Public UI says Import SEO (not Hijack).
* Added readme.txt and license.txt.

= 0.1.19 =
* Stay out of the way when activating Spokora Pro so both plugins are not loaded in the same request.

= 0.1.18 =
* Honest copy about version checks (removed in 0.1.20 for the directory).

== Upgrade Notice ==

= 0.1.28 =
Menu sits with Spokora Pro, after Comments. The side list stays on screen.

= 0.1.27 =
WordPress.org review build. Business Info from older installs is kept and renamed automatically.

= 0.1.26 =
Multisite-ready: Network Activate; SEO fields travel with ThreeWP Broadcast.

= 0.1.25 =
WordPress.org: Tested up to lives only in the readme.

= 0.1.24 =
Plugin Check PHPCS fixes for WordPress.org review.

= 0.1.23 =
More business types in Quick setup and Business Info.

= 0.1.22 =
Submit this build.

= 0.1.21 =
WordPress.org account chrismcmanus.

= 0.1.20 =
Updates now come from WordPress.org. Custom checks against spokora.com are removed. Turn on Search ping or the cookie banner if you still want those.
