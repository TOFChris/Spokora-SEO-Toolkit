# Quick setup wizard — Free notes for a later Pro port

**Free only for now.** Do not copy this into `spokora-plugin` until Chris opens that tree. Same option keys (`spokora_options`) so a later Pro port is a lift, not a rewrite.

Opened **2026-10-19** while tightening Free on ez.spokora.com.

---

## Locked intent (Chris)

- **Starting fresh** → Business Info is the identity baseline (organization schema + global fallbacks).
- **I already have a setup** → **do not** land on Business Info next. Show **Protect & hijack** first: one-button **Backup**, then **Hijack SEO**, then Continue to Business Info.
- No vault, no API fields, no license. Quick setup must not stall on keys (PRODUCT.md).

The old “Apply baseline” button that silently copied the site title was not enough.

---

## Current Free flow (v0.1.9, live on ez.spokora.com)

`spokora_setup_profile` = `fresh` | `agency`. Sequence is built in `Spokora_Onboarding::sequence()`.

### Fresh

| Step | Label |
|------|--------|
| 1 | Your path |
| 2 | Business Info (required org name) |
| 3 | Home page |
| 4 | Go live |

### Agency (“I already have a setup”)

| Step | Label |
|------|--------|
| 1 | Your path |
| 2 | **Protect & hijack** — Backup download + Hijack SEO (returns here) + Continue |
| 3 | Business Info |
| 4 | Home page |
| 5 | Go live |

Backup: `includes/backup-engine.php` + `modules/class-spokora-backups.php`. Database + `wp-content` zip. **No `wp-config.php`.** Vault/license option rows scrubbed if present. Also under **Configure → Backups**.

Hijack from the wizard POSTs `spokora_run_migration` with `spokora_setup_return=1` so the settings engine redirects back to this step (not Import SEO). Full Import SEO remains available.

Option keys written on Business Info (merge, do not wipe the rest of `spokora_options`):

`org_name`, `global_desc`, `schema_type`, `logo_url`, `public_email`, `schema_phone`, `schema_street`, `schema_city`, `schema_region`, `schema_zip`, `og_image`

Left on the Business Info **screen**, not the wizard: Agency ROI, founder, services, socials, global JSON-LD import, front-end SEO output toggle.

---

## When Pro gets this

**Shipped in Pro 1.5.42 (2026-10-20).** See `agent-notes/spokora.md` → *Quick setup port*. Pro wizard: 7 steps (vault after identity); green sites skip Protect & prepare; license activation lands on step 2; Spokora Free = warnings only.

Original intent (for diff vs Free):

Port the **Business Info form + prefill + org-name gate**. Do **not** put API Vault, GSC OAuth, or DataForSEO in that step. Pro can add a later “keys” step after identity.

Port the **agency switcher step** (backup + hijack before identity).

---

## NOTE FOR A LATER AGENT — Pro must hijack its own stuff

**Do not implement in Free.** Chris: in the **Pro** version we need to hijack **our own** Spokora data, not only Yoast / Rank Math / AIOSEO / SEOPress.

Today Hijack copies competitor post meta into `_spokora_seo_*`. It does **not** treat existing `_spokora_seo_*` (or Pro options) as a source. That breaks “re-hijack after a Pro reinstall,” “Free → Pro on a site that already ran Spokora,” and “copy titles from another Spokora install.”

When someone opens `spokora-plugin`:

1. Add a Hijack source for **Spokora** itself: read `_spokora_seo_*` (and related keys) instead of only competitor prefixes.
2. Do not overwrite good Spokora titles with empty competitor fields.
3. Keep confirm-before-deactivate for **other** SEO plugins; self-hijack is a copy/refresh, not a deactivate.
4. Leave Free competitor Hijack as-is unless Chris says otherwise.

Code to start from (Pro tree, do not edit until asked): `modules/class-spokora-settings.php` `process_seo_migration`, `includes/legacy-seo-plugins.php`.
