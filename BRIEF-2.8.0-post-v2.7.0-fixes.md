# Brief: Post-2.7.0 Fixes — v2.8.0

---

## 1. Admin Menu Restructure

Move Pricing + Bulk Price Update out of Settings into a **top-level "Stairbuilder" menu** with both as submenu children. Update hook guards and redirect URLs to match the new page slugs.

## 2. Stringer / Tread / Riser Code → Dropdown

Convert `stringer_code`, `tread_code`, `riser_code` from free text to `<select>`: `mdf` / `pine` / `oak`. No data-loss risk. Use a plain array so `plywood` can be added later.

Once structured, Bulk Pricing material quick-selects should sweep these rows by material too.

## 3. Bulk Pricing — CSS/UX

- `.widefat .check-column` — `padding-left: 10px`.
- `.sb-bulk-num` / `.sb-bulk-new` — center-align or `padding-right: 15px` on `.sb-bulk-new`.
- **2-column grid** for item rows — too much wasted horizontal space.
- **Toggle repaint bug** — material quick-select buttons don't visually update until clicking elsewhere. State/re-render timing issue.
- **Checked-row highlighting** — tinted background or bottom border on toggled rows (distinct from Preview's red-highlight on changed values).

## 4. Settings Panel Layout Regression

Newel Posts, Newel Caps, Handrails & Baserails render with stacked wide fields. Spindles renders correctly with compact inline fields.

**Root cause:** `$variant_col()` inside `render_card_row()` is called without `$flat = true` for these panels. Spindles passes it. Pass it for these too.

## 5. Quote PDF

### 5a. Newel/cap count shows 0

**Root cause — two bugs:**
1. The per-corner checkboxes (`tl-post`, `tr-post`, `to-post`, `bo-post`, `box-post`, `bl-post`, `br-post`) are **only present on turned staircases**. Straight flights use the `newel-posts` select instead. The template only reads the checkboxes, so straight flights always sum to 0.
2. `box-post` is counted **twice** — once in `$box = !empty($content['box-post']) ? 1 : 0` and again in the `intval()` sum. Double-count bug on turned stairs.

**Fix:** handle both mechanisms — if per-corner checkboxes are present (turned stair), sum them (fixing the `box-post` double-count); otherwise fall back to `newel-posts`. Check if `newel-posts` value needs colon-strip treatment (note the `custom:0` option).

### 5b. No spindle count

Never captured as form data — only calculated client-side for pricing. Add a hidden input that `priceCalc.js` populates with the computed count. Confirm approach with Dan before building.

### 5c. Layout

- **Staircase Type / Building Regs** → move to Staircase Essentials. Show value only if it reads cleanly.
- **2 columns** instead of 3: Details + Newel Posts paired, Ballustrading below.

### 5d. PDF styling

Dan providing via Claude Design → HTML. Out of scope.

---

## Build Sequence

1. `$flat` fix (§4)
2. Menu restructure (§1)
3. Code dropdowns (§2)
4. Bulk Pricing CSS (§3)
5. PDF newel/cap count (§5a)
6. PDF layout (§5c)
7. PDF spindle count (§5b) — confirm approach first
