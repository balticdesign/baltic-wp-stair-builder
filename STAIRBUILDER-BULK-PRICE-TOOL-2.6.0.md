# Stair Builder — Bulk Price-Increase Tool (v2.6.0)

**Deliverable brief for Claude Code.**
Main plugin `BALTIC_STAIRBUILDER_VERSION` 2.5.3 → **2.6.0** (new feature, minor bump).
Pricing settings file (`includes/stairbuilder-pricing-settings.php`) header `@version` 2.0.0 → **2.1.0** (schema gains `adjustable` flags + a bulk-update method; no structural change to stored data).

---

## 1. What this is

A one-shot admin tool that applies a **single percentage change to a selected set of component/material prices at once** — the classic "oak supplier put prices up 8%, bump every oak price" job. Native `wp_options` port of the old ACF-options-page bulk updater.

The UX, in one screen:

1. A **table of every adjustable price currently stored**, grouped by section (Strings, Treads, Risers, Newels, Caps, Handrails, Baserail, Spindles, Featured Step). Each row: an **include checkbox**, label (with repeater row name), the row's **material** (Oak / Pine / MDF / Ply / Metal / Glass / —), and current value.
2. **Material quick-selects** — buttons that tick/untick rows by material: **All Oak**, **All Pine**, **All MDF**, **All Ply**, **All Metal**, **All Glass**, plus **Select all** / **Select none**. This is the "toggle all oak / toggle all pine-mdf" behaviour from the old tool — you pick which column of prices the rise applies to.
3. A single **percentage input** (e.g. `8`, or `-2.5` to reduce).
4. **Preview** — computes the new value for every *checked* row and shows old vs new, **changed rows highlighted red**. Nothing is written.
5. **Apply & Save** — writes the new values for checked rows back to the `stairbuilder_options` blob in **one `update_option()`**, then reloads to the new baseline.

No new option keys, no new storage shape — it reads and rewrites values already in `stairbuilder_options`.

> **Not to be confused with the existing front-end feature.** `material_quick_set_enabled` renders customer-facing "Set all to Pine / Set all to Oak" buttons on the *front form* (the old `#all_oak`/`#all_pine` handlers in `formLogic.js`, which just flip the material dropdowns). That is a different thing. This brief is an **admin price-editing tool**; the material quick-selects here tick rows in the bulk table, they don't touch the front-end form.

---

## 2. The one architectural rule: schema-driven, `adjustable`-flagged

Load-bearing decision from the design phase — do not compromise it:

> **The tool walks the schema and sweeps every field / repeater sub-field carrying `'adjustable' => true`. It does NOT use a hardcoded key list, and it does NOT infer "is this a price" from field type.**

Why it can't infer from type:

- Prices live under **two** field types. Some are `type => 'price'` (`pine_price`, `cut_string_price`, baserail prices…). **Most are `type => 'number'`** (every featured-step price, every landing figure, the repeater `*_value` fields, all delivery charges). A "bump all `type=price`" tool would miss the large majority of real prices.
- Not every number is a price. `width_mp` (`:2073`) is a **~1.x multiplier**, not currency — %-bumping it corrupts pricing. Same category: `caps_per_newel`, `panel_width_mm`, `panel_gap_mm`, the Going/width warning bounds, `going_max`.
- `type => 'product_id'` fields are auto-excluded for free (never `adjustable`), satisfying "never touch WooCommerce IDs" with no special-casing.

The flag is the single source of truth for "the bulk tool may touch this". Field type decides how a field renders/sanitises; `adjustable` decides whether it's swept. Because the walk is schema-driven, **build order stops mattering** — a flat price becoming a repeater later just moves between buckets the walker already handles.

---

## 3. The flagging pass — "components and materials only" (DECISION 1)

Per Dan: the sweep covers **component and material prices only** — *not* delivery, packaging, or fixed base fees. Add `'adjustable' => true` to exactly the fields below. This is the gating prerequisite; it changes no behaviour. Line refs are current.

### 3a. Repeater sub-field prices → flag (all rows inherit)

| Repeater | Sub-field(s) to flag | Ref | Material axis |
|---|---|---|---|
| `newel_types` | `variant_subfields`: `pine_price`, `oak_price` | `:1695–1696` | Pine / Oak |
| `handrail_types` | *(same shared `variant_subfields`)* | `:1695–1696` | Pine / Oak |
| `cap_types` | `cap_subfields`: `pine_price`, `oak_price` | `:1709–1710` | Pine / Oak |
| `spindle_types` | `pine_price`, `oak_price`, `metal_price`, `glass_price` | `:1728–1738` | **already flagged** |
| `stringer_types` | `stringer_value` | `:1755` | — (single value) |
| `tread_types` | `tread_value` | `:1786` | — |
| `riser_types` | `riser_value` | `:1805` | — |
| `construction_types` | `construction_value` | `:1774` | — |
| `tread_profiles` | `tread_profile_value` (per-tread surcharge) | `:1793` | — |

> Note: `variant_subfields` is shared by **newels and handrails**, so flagging it once (`:1695–1696`) flags both. Good — one edit, both consumers covered.

### 3b. Flat prices → flag

| Field(s) | Ref | Material axis |
|---|---|---|
| Featured Step — 16 fields: `{mdf,ply,pine,oak}_{bullnose,curtail,dbl_curtail,dcb_curtail}_price` | `:1840–1930` | MDF / Ply / Pine / Oak |
| `pine_baserail_price`, `oak_baserail_price` | `:1955–1971` | Pine / Oak |
| `cut_string_price` | `:2079` | — |

### 3c. Do NOT flag (out of scope per Decision 1, or not a price)

- **Base fees / multipliers:** `setup_fee` (`:2067`), `width_mp` (`:2073`, multiplier anyway).
- **Delivery / packaging:** `two_man_delivery_price`, `part_assembled_price`, `fixing_kit_price`, `extra_packaging_price` (`:2176–2182`), `greater_london_delivery_price`, `mainland_uk_delivery_price` (`:2154–2164`).
- **Non-price numbers:** `caps_per_newel`, `panel_width_mm`, `panel_gap_mm`, `going_regs_warning_min/max`, `going_max`.
- **Every `product_id`.**

### 3d. Landing figures → flag (DECIDED: include)

Quarter Landing (`:2085–2113`) and Half Landing (`:2115–2143`) hold ten `number` figures — flag all ten as `'adjustable' => true`:

- `quarter_landing_all_oak`, `quarter_landing_oak_string`, `quarter_landing_oak_tr`, `quarter_landing_oak_tread`, `quarter_landing_no_oak`
- `half_landing_all_oak`, `half_landing_oak_string`, `half_landing_oak_tr`, `half_landing_oak_tread`, `half_landing_no_oak`

They're oak-price-driven, so a supplier oak rise should move them too. **Material tagging:** tag the oak-keyed figures (`*_all_oak`, `*_oak_string`, `*_oak_tr`, `*_oak_tread`) as **Oak** so "All Oak" catches them; the `*_no_oak` figures carry no material tag (toggled individually / via Select all).

---

## 4. Deriving each row's material (for the quick-selects)

The material axis is inconsistent across fields, so the tool derives a row's material tag like this:

- **Repeater sub-field prices** — from the sub-field id: `pine_price` → **Pine**, `oak_price` → **Oak**, `metal_price` → **Metal**, `glass_price` → **Glass**.
- **Flat featured-step / baserail prices** — from the id prefix: `mdf_*` → **MDF**, `ply_*` → **Ply**, `pine_*` → **Pine**, `oak_*` → **Oak**.
- **Single-value rows** (`stringer_value`, `tread_value`, `riser_value`, `construction_value`, `tread_profile_value`, `cut_string_price`) — material **—** (none). These are never caught by a material quick-select; they're toggled individually or via **Select all**.

Optional: declare the tag explicitly in the schema (`'material' => 'oak'`) rather than string-sniffing ids, if cleaner. Either is acceptable; the id-derivation table above is the fallback contract.

---

## 5. Screen placement (DECISION 2)

Default: a **new tab inside the existing Stair Builder Pricing page** (`PAGE_SLUG = 'stairbuilder-pricing'`), reusing existing admin CSS.

Dan's aside: because this screen carries an interactive selectable list (checkboxes + material quick-selects), it may read better as its **own submenu page** (`add_submenu_page`). Non-blocking — build as a tab; promote to a submenu only if the tab feels cramped alongside the settings tabs. Either way the save path is identical.

---

## 6. Mechanics & contracts to preserve

- **Storage**: single blob under `Stairbuilder_Pricing_Settings::OPTION_KEY` (`stairbuilder_options`). Flat keys map `id => value`; repeaters map `id => [ rowIndex => [ subfield_id => value ] ]`. Verified against `sanitize()` (`:567`) and `sanitize_repeater()` (`:626`).
- **Walker**: a method returning every adjustable price as `['path', 'section', 'label', 'material', 'value']`, covering **both** flat (`$options[$id]`) and repeater sub-rows (`$options[$repeater_id][$i][$subfield_id]`). Repeater rows include the row `name`/`code` in the label so "Oak Price (Georgian Newel)" is distinguishable.
- **Write path**: route the bulk save **through the existing `sanitize()`** — read blob → apply computed deltas to checked adjustable paths → hand merged array to `sanitize()` → `update_option()`. This reuses the existing coercion (`price`/`number` → `(float)`, blank → `''`), the `_schema_version` stamp, and repeater sanitisation, guaranteeing the bulk write is byte-identical to a normal Settings-API save.
- **Rounding (DECISION 3)**: **2 dp**, always. `round($old * (1 + $pct/100), 2)`. (No whole-£ option in v1.)
- **Empty prices**: a blank price (`''`) stays blank — never coerce empty to `0.00`, never bump it. Only checked rows with a numeric current value change.
- **Percentage input**: accept negatives (cuts) and decimals; reject non-numeric; `0` or blank % is a no-op ("no changes").
- **Safety**: Preview is pure (no writes). Apply is nonce-verified and `manage_options`-gated. Guard against double-submit re-applying the %.

---

## 7. Acceptance criteria

1. Every field in §3a/§3b carries `'adjustable' => true` (+ landing figures per Dan's §3d answer); nothing in §3c is flagged; no `product_id` is flagged.
2. The walker lists **all** adjustable prices — flat and repeater sub-rows — each with a human label (incl. repeater row name) and a derived material tag.
3. Material quick-selects correctly tick/untick rows by material; Select all / none work; single-value rows carry no material and are unaffected by material buttons.
4. Preview shows old vs new for checked rows only, highlights changed rows red, writes nothing.
5. Apply writes every changed value in one `update_option()` via `sanitize()`; resulting blob is shape-identical to a normal settings save; unchecked rows are untouched.
6. Empty prices stay empty; negative % reduces; non-numeric % is rejected with a clear message; all results are 2 dp.
7. Front-end quote maths unchanged — a staircase priced before Apply, with 0% applied, yields an identical quote after.
8. Versions bumped: main plugin → 2.6.0; pricing-settings `@version` → 2.1.0.

---

## 8. Build sequence

1. **Flagging pass** (§3, incl. §3d landing figures) — mechanical, no behaviour change, lands first and independently.
2. **Walker method** — schema → flat list of adjustable paths + values + material tags. Unit-testable in isolation.
3. **Screen + selection UI** — render the grouped table, include-checkboxes, material quick-selects, % input.
4. **Preview** — compute checked rows old→new at 2 dp, red-highlight changes, no write.
5. **Apply & Save** — nonce/cap-gated write through `sanitize()`, success notice, reload.
6. **Verify** on the DDEV site with a real save-and-quote against the §7 criteria.

All scope decisions are resolved — no open questions.
