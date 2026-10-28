# Changelog

All notable changes to **Spokora Free** in this repository are documented here.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.1.30] - 2026-10-28

### Added

- Canonical URLs and robots in the SEO module (`modules/class-spokora-seo.php`): `output_canonical()` on `wp_head`, `filter_robots()` on `wp_robots`, and `send_robots_header()` on `send_headers`. Editor and Quick Edit can set a canonical URL and index/follow. Noindex URLs are left out of `sitemap.xml`.

### Fixed

- PHP 7.4 activation and runtime: `str_contains()`, `str_starts_with()`, and `str_ends_with()` polyfills in `spokora-seo-toolkit.php` before the includes that call them (`includes/article-markdown.php`, `includes/content-images.php`, `includes/legacy-seo-plugins.php`).

### Changed

- Home welcome note is the first block under the title.
- JSON-LD and redirect editors use a light code field.
- AI index (`/llms.txt`) and Markdown copies sit under Configure. Search Console teaser copy describes search performance.

## [0.1.29] - 2026-10-26

### Fixed

- AI readiness scores update when you enable **Public AI document** and save in the block editor (stored scores and sidebar gauges match saved LLMS meta).

### Changed

- Schema copy notes that model-generated JSON-LD is available in **Spokora Pro** (Free keeps presets and paste).

## [0.1.28] - 2026-10-26

### Changed

- Admin menu sits after Comments and before Appearance, matching Spokora Pro. A tall flyout shifts up and scrolls if it would leave the window.

## [0.1.27] - 2026-10-26

### Changed

- WordPress.org review: display name **Spokora SEO Toolkit**, text domain and requested slug **spokora-seo-toolkit**. Plugin folder is `spokora-seo-toolkit/`.
- Setup CSS is enqueued. JSON-LD is printed with `wp_print_inline_script_tag` and hex encoding. `spokora_options` saves a whitelist of scalar fields.
- Admin menu position is 81 (after Settings). Readme documents IndexNow and Google Analytics terms, privacy, what is sent, and that both stay off until opted in.
- Legacy `hwc_*` option keys and `_hw_seo_*` post meta are copied once to Spokora keys. Runtime reads Spokora keys only.

## [0.1.26] - 2026-10-23

### Added

- WordPress **multisite**: `Network: true`. Network Activate seeds every blog (rewrites for `/llms.txt`, `/ai-docs/`, `/md/`). New sites in the network get the same seed. Each site keeps its own Business Info, sitemap, and redirects. ThreeWP Broadcast copies hidden `_spokora_*` (and `_hw_seo_*`) SEO fields with the post.

---

## [0.1.25] - 2026-10-23

### Fixed

- WordPress.org plugin scan: removed `Tested up to` from the main PHP plugin header. It stays in `readme.txt` only.

---

## [0.1.24] - 2026-10-23

### Fixed

- WordPress.org **Plugin Check** / PHPCS: i18n translators comments, ordered placeholders, escaping and redirect hygiene, backup export annotations, GA4 local enqueue, schema-type text domain literals.
- Admin sidebar Spokora icon sized to 20×20 (Free menu slug `spokora-free` was missing from the CSS).

---

## [0.1.23] - 2026-10-23

### Changed

- Quick setup (step 3) and Business Info share a larger grouped **Business type** list: SaaS/apps/games, trades, retail, food, auto, education, government, and entertainment schema.org types.

---

## [0.1.22] - 2026-10-23

### Security

- JSON-LD output uses `JSON_HEX_TAG | JSON_HEX_AMP` so a `</script>` in custom schema cannot break the tag.

---

## [0.1.21] - 2026-10-23

### Fixed

- `readme.txt` Contributors is **chrismcmanus** ([WordPress.org profile](https://profiles.wordpress.org/chrismcmanus/)).

---

## [0.1.20] - 2026-10-23

### Changed

- **WordPress.org directory:** removed the spokora.com update checker, `Update URI`, and Plugins-row update panel. Updates go through WordPress.org after listing.
- Text domain is **`spokora-free`** (matches the plugin slug).
- Cookie banner and IndexNow are **opt-in** (existing cookie settings keep the banner; sites that already pinged IndexNow stay on).
- Public UI uses **Import SEO** instead of Hijack.
- Added `readme.txt` and `license.txt` in the plugin folder.

---

## [0.1.19] - 2026-10-22

### Fixed

- **Activate Spokora Pro** while Free is still installed: Free steps aside on that request so WordPress can activate Pro without a duplicate-function error.

---

## [0.1.18] - 2026-10-22

### Changed

- Home note and copy: Spokora Free **does** check spokora.com for a newer zip (version check only — no account, no license key). Optional update emails stay extra.

---

## [0.1.17] - 2026-10-22

### Added

- Public source repository ([Spokora-FreeWPPlugin](https://github.com/TOFChris/Spokora-FreeWPPlugin)) — **Free plugin only**; Pro remains commercial.

### Changed

- Product naming: **Spokora Free** only (retired internal “Lite” workspace naming).

---

## [0.1.16] and earlier

Shipped via spokora.com theme downloads and internal builds before this repo went public. See git history of `spokora-free/` for file-level changes from 0.1.17 backward.
