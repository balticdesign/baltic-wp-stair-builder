# Baltic Stairbuilder — dev notes

Lead-gen staircase configurator plugin. Customers configure a staircase,
get an indicative price, and submit their details; the plugin stores the
lead, generates a PDF quote (mPDF) and emails both sides.

This file is internal and never ships: release zips are cut by
`bin/build-release.sh` via `git archive`, and `.gitattributes` excludes all
`.md` files, `docs/`, `bin/`, `composer.*` and dot files.

## Working on it

- Local site: DDEV at `https://bd-pricing-builder.ddev.site` (PHP 8.3).
- Project conventions and current direction: see `CLAUDE.md`.
- Historical briefs and phase plans: `docs/briefs/`.
- `vendor/` is committed (clients have no composer). `composer.json` pins
  mpdf/mpdf 8.1.6 for reference; don't run `composer install` casually.
- `vendor/mpdf/mpdf/ttfonts/` is pruned to DejaVu Sans (+ Condensed) only —
  the PDF template uses DejaVu exclusively. Don't restore the full font set.

## Releasing

1. Bump the version in the plugin header, `BALTIC_STAIRBUILDER_VERSION`
   and the `readme.txt` Stable tag — all three must match.
2. Commit, then run `bin/build-release.sh` (refuses a dirty tree).
3. Upload `dist/baltic-wp-stair-builder-<version>.zip` through wp-admin.
   Never deploy GitHub's "Download ZIP" — it installs under a `-main`
   folder, which breaks the text domain and ships the whole repo.
