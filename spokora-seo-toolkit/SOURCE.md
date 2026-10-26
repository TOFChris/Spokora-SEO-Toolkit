# Source lineage (Spokora SEO Toolkit only)

This folder is the **entire** Spokora SEO Toolkit plugin. Nothing in this public repository ships Spokora Pro code paths (no `spokora.php`, license server, or API vault module).

SEO engines here were forked from the commercial Spokora Pro codebase at **1.5.41**, then trimmed to the Free product cut documented in [docs/PRODUCT.md](../docs/PRODUCT.md). Pro development continues privately at [spokora.com](https://spokora.com).

| In this plugin | Notes |
|----------------|--------|
| `modules/class-spokora-*.php` (SEO, sitemap, redirects, consent, etc.) | Free-trimmed modules |
| Most `includes/` engines | Free-trimmed |
| `spokora-free.php`, onboarding, help, modules UI | Free-specific |

Same post meta (`_spokora_seo_*`) and `spokora_options` as Pro so upgrading to Pro later keeps titles. Uninstall does not wipe those by default.
