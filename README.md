# Spokora Free — WordPress SEO plugin

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)](https://www.php.net/)

**Spokora Free** is a complete, keyless SEO plugin for WordPress websites: titles and meta, JSON-LD schema, XML sitemaps, exact 301 redirects, migration from Yoast / Rank Math / AIOSEO / SEOPress, on-site backups, search SEO scoring, `/llms.txt` and markdown exports, optional IndexNow, optional cookie banner with optional GA4 after accept, and light performance tweaks.

No Spokora account. No license key. No API vault required to run SEO.

> **This repository is only Spokora Free.** [Spokora Pro](https://spokora.com) (agency week, API vault, GSC, rank tracking, and the rest) is a separate commercial product and is **not** published here.

- **Product site:** [spokora.com](https://spokora.com)
- **Support forum:** [forum.spokora.com](https://forum.spokora.com/)
- **Update emails:** [spokora.com/updates](https://spokora.com/updates/)
- **Author:** [That One Firm](https://thatonefirm.com)

---

## Why Spokora Free exists

Switching SEO plugins should not mean losing titles, schema, or redirects. Spokora Free is built so you can **turn off** another SEO plugin on a clean site and keep a full public SEO surface—without connecting the site to our cloud or pasting a license.

If you later need the full client-week toolkit (vault, dashboards, media lab, and more), [Spokora Pro](https://spokora.com) is the upgrade path. **Do not install Free alongside Pro**; Pro already includes this SEO layer.

---

## Features (Free)

| Area | What you get |
|------|----------------|
| **On-page SEO** | Titles, meta descriptions, canonical URLs, robots, editor metabox, SERP-style preview |
| **Schema** | JSON-LD with sensible defaults and per-page overrides |
| **Sitemap** | XML sitemap so you can drop a standalone sitemap plugin |
| **Redirects** | Exact 301 redirect rules |
| **Import SEO** | Import titles, descriptions, and redirects from major SEO plugins (with confirmation before deactivating the old plugin) |
| **Quick setup** | Guided setup: backup + import when migrating, then business info |
| **Backups** | One-click database + `wp-content` zip (optional secret scrubbing); does not include `wp-config.php` |
| **List Quick Edit** | SEO fields on Posts and Pages list screens |
| **Cookies & privacy** | Banner with Reject / Accept; optional GA4 only after Accept |
| **AI-ready URLs** | `/llms.txt`, `/ai-docs/{slug}.md`, `/md/{slug}.md` |
| **IndexNow** | Ping search engines when content changes |
| **Performance** | Small front-end optimizations (not a full cache plugin) |
| **Search SEO score** | In-editor scoring gauge (generation stays in Pro) |

See [docs/PRODUCT.md](docs/PRODUCT.md) and [docs/MODULE-CUT.md](docs/MODULE-CUT.md) for the product boundary vs Pro.

---

## Requirements

- WordPress **6.0+** (single site or **multisite** — Network Activate)
- PHP **7.4+**
- Not compatible with **Spokora Pro** active at the same time (`spokora/spokora.php`)

---

## Installation

### From a release zip (recommended)

1. Download the latest `spokora-free-x.y.z.zip` from [Releases](https://github.com/TOFChris/Spokora-SEO-Toolkit/releases) or [spokora.com](https://spokora.com).
2. In wp-admin: **Plugins → Add New → Upload Plugin**, choose the zip, install, activate.
3. Open **Spokora → Quick setup** and follow the wizard. On multisite, Network Activate, then run Quick setup on each site that needs its own Business Info.

The zip layout is `spokora-free/spokora-free.php` (Linux-safe paths).

### From this repository

1. Clone this repo.
2. Copy the `spokora-free/` folder into `wp-content/plugins/`.
3. Activate **Spokora Free** in **Plugins**. On a network, **Network Activate**.

Or build a zip (Windows or CI):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-spokora-free-zip.ps1
```

Output: `dist/spokora-free-{version}.zip`.

---

## Updates

After WordPress.org listing, use **Plugins → Automatic updates** like any other directory plugin. Optional release emails: [spokora.com/updates](https://spokora.com/updates/). GitHub [Releases](https://github.com/TOFChris/Spokora-SEO-Toolkit/releases) stay in sync with the directory zip.

---

## Development

| Path | Purpose |
|------|---------|
| `spokora-free/` | Installable plugin (only code that ships) |
| `docs/` | Product and agent copy specs |
| `scripts/build-spokora-free-zip.ps1` | POSIX-path zip for cPanel/Linux |

Contributing: [CONTRIBUTING.md](CONTRIBUTING.md) · Security: [SECURITY.md](SECURITY.md) · Changelog: [CHANGELOG.md](CHANGELOG.md)

---

## License

Spokora Free is free software under the [GNU General Public License v2.0 or later](LICENSE). WordPress plugins that ship to end users must remain GPL-compatible; see [LICENSE](LICENSE).

Spokora Pro and the Spokora name on spokora.com are commercial offerings separate from this repository.

---

## Star / share

If Spokora Free saves you a migration headache, a star on GitHub and an honest review after we land on WordPress.org help more site owners find a keyless SEO option. Pro licenses fund the coffee pot that keeps Free maintained.
