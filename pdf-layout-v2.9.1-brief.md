# Brief: Quote PDF layout & spacing fixes — v2.9.1

**File:** `templates/stairbuilder_pdf.php` (possibly minor touch in `baltic_stair_generate_pdf()` / lead-capture.php — see §6)
**Version:** main plugin 2.9.0 → **2.9.1** (layout/style fix pass, no data or schema changes)
**Reference:** `_reference/quote_2_mpdf.html` (Claude Design) remains the visual target. Font bundling (Jost) stays out of scope for this pass.

---

## 1. Problems observed in real renders (vs design)

1. **Complex staircases overflow to page 2.** On a Half Turn — Winder (~30+ spec rows), the entire body — plan image and all spec sections — is pushed to page 2, leaving the header bands orphaned. Root cause: the whole body is a single 2-column `<table>` row; the left `<td>` (57%) stacks the plan image **plus all five/six spec sections**. mPDF treats that row as a near-atomic block, and when the left cell exceeds the remaining page height it moves the whole row.
2. **Section titles are crushed** against the tables above/below them. `.sectlabel` and `.block` spacing relies on `div` `margin-top`/`margin-bottom`, which this mPDF build drops or collapses inside table cells (same class of problem that forced the coloured boxes onto `<td>`-backed tables in v2.9.0 — see inline comments).
3. **Minor:** "Total inc VAT" wraps to two lines in the price box at real render metrics.
4. **Data bug:** "Treads after Turn 2" can render a negative value (`-1` observed on a double-winder lead). Negative tread counts must never appear on a customer-facing quote.

## 2. Target layout restructure

Replace the single body table with **two stacked tables**:

### Table A — top row (plan beside sidebar)
- LEFT `<td>` 57%: **Staircase Plan section only** (title + canvas image / fallback panel).
- RIGHT `<td>` 43%: unchanged sidebar — price box, Project Delivery badge, Your Details, Notes.
- Existing outer padding pattern kept (`40px` outer gutters, `14px` inner gutter).

### Table B — full-width two-column spec band (below Table A)
- One row, two `<td>`s, `vertical-align: top`, each ~46% width with a shared centre gutter (suggest `padding: 0 14px 0 40px` / `0 40px 0 14px` to mirror the existing gutters). Table starts with modest top padding (~10–16px) so it doesn't hug Table A.
- All spec sections live here: **Staircase Essentials, Staircase Details, Newel Posts, Balustrading (conditional), Delivery & Packaging (conditional)**.
- **Do not use CSS columns or floats** — mPDF-unsafe. Two `<td>`s only.

### Column packing (PHP-side balancing)
Sections have variable row counts (empty rows are skipped by `$bd_row`), so balance in PHP:

1. Render each section to a string with `ob_start()`/`ob_get_clean()` in canonical order: Essentials, Details, Newels, Balustrading, Delivery.
2. Weight each section = `substr_count($html, '<tr')` + 2 (title overhead).
3. Greedy pack: iterate in canonical order, append each section to whichever column currently has the lower total weight (ties → left). Sections are **atomic** — never split one across columns.
4. Echo column A into the left `<td>`, column B into the right.

This keeps reading order roughly canonical while preventing the Details-heavy winder case from towering over one column.

## 3. Section-title spacing — make it mPDF-reliable

Convert `.sectlabel` from a bare `div` to a **single-row table with the styling on the `<td>`** (the proven v2.9.0 pattern — mPDF honours `td` padding where it drops `div` margins):

```html
<table style="width:100%; border-collapse:collapse;"><tr>
  <td class="sectlabel-td">Staircase Essentials</td>
</tr></table>
```

```css
.sectlabel-td {
  font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
  color: {accent}; border-bottom: 2px solid {accent};
  padding: 18px 0 8px;   /* top breathing room + gap above the rule */
}
```

- The 18px top padding replaces the unreliable `.block` bottom margin as the inter-section spacer. Keep a small residual `.block { margin-bottom: 6px }` but do **not** depend on it.
- First section in each column: fine for the top padding to remain (it doubles as the gap below Table A) — no `:first-child` handling needed (mPDF's support is poor anyway).
- Give the spec table a small buffer below the rule: change `.spec td { padding: 8px 0 }` to `padding: 7px 0` and add `.spec { margin-top: 2px }` **only if** mPDF honours it in test — otherwise fold the buffer into the sectlabel-td `padding-bottom`.
- Apply the same td-backed pattern to the sidebar's "Your Details" title and the plan title so all headings match.

## 4. Plan image sizing

Currently `width: 100%` — a tall straight-flight canvas can render enormous. Change to:

```html
style="max-width: 100%; max-height: 280px; border: 1px solid {panel};"
```

mPDF supports `max-width`/`max-height` on `img` and preserves aspect. Centre it: wrap in a `<td style="text-align:center">` or keep the existing block and add `text-align:center` on the container.

## 5. Small fixes

- **Price box label wrap:** on the "Total inc VAT" cell add `white-space: nowrap;` (mPDF supports it on `td`) and/or shift widths to 50/50 — verify at real metrics that the £ total never clips.
- **Negative tread counts:** in the Details section, guard `treadbt`, `treadat`, `treadat2` — skip the row when the value is not numeric **or is < 0**. Simplest: wrap in a tiny helper `$bd_row_count($label, $v)` that casts and suppresses negatives. (The underlying capture bug — how `-1` got saved — is a separate front-end issue; note it in the commit but don't chase it in this pass.)
- **Page-break resilience:** add `page-break-inside: avoid;` to `.spec` tables (mPDF honours it on tables) so if an extreme config still spills to page 2, a section moves whole instead of splitting mid-table. Do **not** add any `@page` rules — documented mPDF blank-page/div-zero bugs (see file header comment).

## 6. Pagination fallback (verify, likely no code)

With the body split into Table A + Table B, an extreme config (double winder + featured steps + full balustrading + delivery) may legitimately run to page 2. That is acceptable for v2.9.1 **provided** page 2 doesn't start mid-section (covered by §5 page-break-inside) — a repeating page-2 header via `SetHTMLHeader` is deferred (zero-margin constructor makes header margins fiddly; park as a follow-up decision if testing shows page 2 occurs on realistic configs).

## 7. Test matrix (render all, eyeball against design)

| # | Scenario | Checks |
|---|----------|--------|
| 1 | Straight flight, minimal (no balustrading, no delivery) | Fits page 1; columns roughly balanced; titles have air above/below; plan image ≤ 280px tall |
| 2 | Straight flight, full options | Fits page 1; "Total inc VAT" on one line |
| 3 | Quarter turn — landing | Turn rows present, no negative rows |
| 4 | Half turn — winder (the failing case) | **Fits page 1**; header bands + plan + sidebar all on page 1 |
| 5 | Half turn — double winder, everything on (balustrading, delivery, featured steps) | If page 2 occurs, no section splits mid-table; footer renders |
| 6 | Lead with `treadat2 = -1` (or synthesise) | Row suppressed |

Re-run whatever render-test harness was used for the 24/24 pass in v2.7.0 if still available.

## 8. Out of scope (do not touch)

- Jost font bundling (acknowledged pending — separate pass).
- The `-1` capture bug on the front end (note it, don't fix here).
- Header/footer band markup, branding settings, `bd_code_label`, newel/spindle count derivation — all data logic stays byte-identical.
- mPDF constructor settings in lead-capture.php (zero margins stay).
