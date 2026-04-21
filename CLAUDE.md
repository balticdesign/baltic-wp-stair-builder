# baltic-wp-stair-builder

Lead-gen-first staircase configurator plugin for WordPress. Commercial product sold via baltic.digital, installed on client sites (SPD staging as initial trial/demo) under licence.

## What this plugin is

- **Today**: A staircase configurator with 3D .obj model visualisation, WooCommerce integration, and a "buy staircase" checkout flow. Hand-coded from scratch by Dan.
- **Target**: A lead-gen-first extension — form-driven configurator that captures leads via a generated PDF quote. One thing, done well. The 3D and WC layers are being stripped during the current modification pass.

## Current direction (actively being worked on)

The plugin is pivoting from "full productisation → buy staircase" to "configure → capture lead + PDF quote". Three modification pillars:

1. **Strip the 3D**. Remove `/assets/js/static/model/*.obj` (~40MB of OBJ models), the 1.3MB `stair_min.js` Three.js bundle, and any per-stair-type 3D rendering logic. The plugin becomes form-first, not 3D-first. Open question for discussion: does any visual element remain (simple SVG diagram?) or fully form-only with PDF output?
2. **Cut the buy flow**. Remove the `/woocommerce/` directory overrides and any WC-dependent code paths. Final step of user flow = generated PDF + captured lead, never cart/checkout.
3. **Make it a proper BD commercial product**. Storage migration from ACF to `wp_options`. BD branding on plugin name, author URL, admin screens. Licence key system matching the Oakworld pattern.

## Architecture conventions

- **Storage**: `wp_options` (single serialised/JSON blob per logical settings group), NOT ACF. ACF dependency is being removed — it's only still installed in dev to migrate existing settings across.
- **Module layout** (target state, moving toward this during refactor):
  - `/admin/` — settings screens, admin UI
  - `/ajax/` — AJAX endpoint handlers
  - `/licence/` — licence key validation, domain checks
  - `/builder/` — core configurator logic
  - `/storage/` — wp_options read/write wrappers
- **AJAX**: endpoints registered in `/ajax/` via `wp_ajax_*` / `wp_ajax_nopriv_*` hooks. Nonce verification on every handler.
- **Naming**: prefix everything with `bd_` or `baltic_wp_stair_builder_` to avoid collisions. Class names `BD_Stair_Builder_*`.
- **Versioning**: iterate version in main plugin header on every substantive change — patch (1.0.1), minor (1.1.0), or major (2.0.0) depending on scope. Per user preference.

## Housekeeping targets (do before refactor work starts)

Low-risk cleanup pass first, to tidy the starting point:
- `sdfsdfchtml` at plugin root — typo-named junk, delete
- `includes/stairbuilder-options_old.php` — legacy options, delete
- `templates/stairbuilder_pdf_older.php` — legacy PDF template, delete
- `assets/js/halfTurn.js.js` — typo in filename (double .js), rename or delete depending on whether it's used

## Dev environment

- **Local**: Ubuntu 24.04, DDEV v1.25 WordPress site at `https://bd-pricing-builder.ddev.site`
- **Plugin path**: `~/dev/bd-pricing-builder-dev/public/wp-content/plugins/baltic-wp-stair-builder`
- **Git**: fresh repo, `main` branch. Old GitHub repo (`balticdesign/StairBuilder`) is 13 months stale and archived at `~/archive/stairbuilder-repo-2024-snapshot` — not a live source.
- **PHP**: 8.3 in DDEV, matching typical client production.

## For deeper context

Architectural decisions, the distribution pattern, the branding+licence pattern, the storage migration reasoning, the lead-gen pivot rationale — all captured in Baltic Mind under project `baltic-wp-stair-builder` and `bd-pricing-builder` (the latter is the earlier placeholder slug, still valid for search). When starting a new session, "pull all baltic-wp-stair-builder decisions from Baltic Mind" will surface the full context.
