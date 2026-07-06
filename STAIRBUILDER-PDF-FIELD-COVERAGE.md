# Stair Builder — PDF Quote Field-Coverage Fixes

**Deliverable brief for Claude Code.**
Two independent workstreams:
- **A. Label-mapping fixes** (bug — quote shows machine codes) → patch bump (indicatively **2.5.4**).
- **B. Missing-field additions** (enhancement — configuration choices absent from the quote) → minor bump (indicatively **2.6.0**).

Final version numbers assigned at build time depending on merge order relative to the bulk-price tool (which also targets 2.6.0). A and B can ship separately; A is the higher-value, lower-risk win and should land first.

---

## 1. Goal

The generated PDF quote (`templates/stairbuilder_pdf.php`) should reflect **every configuration choice the customer made on the front-end form**, in a human-readable form, in an appropriate section. Today it (a) prints raw machine codes for five fields, and (b) omits nine groups of fields entirely.

---

## 2. How the PDF gets its data (mechanism — read before touching anything)

- `baltic_stair_generate_pdf()` (`includes/stairbuilder-lead-capture.php:133`) builds `$content` = the raw serialised **form array** (`$lead_data['form']`) merged with contact/price meta, then `include`s the template (`:141–152`).
- The template reads values **directly by key** — e.g. `$content['construction_type']`. If a key isn't in the serialised form (or isn't rendered), it never appears.
- **Front-end value normalisation:** `formLogic.js:99–105` serialises the form and strips everything after the first `:` on **every** field (`field.value.substring(0, colonIndex)`). So a material select whose option value is `code:price` is stored as just `code`. This is why material/price suffixes never reach the PDF.
- **Label lookup:** `$bd_code_label($option_key, $code)` (`templates/stairbuilder_pdf.php:13–27`) maps a stored code back to its admin-defined name by scanning the repeater's rows for `$row['code'] === $code` and returning `$row['name']`.

> **⚠️ Critical limitation of `bd_code_label` — the crux of Fix A.** It matches on the **plain** keys `code` and `name`. That only works for repeaters built from `variant_subfields` / `cap_subfields` / `spindle_subfields`, which use plain `code`/`name` — i.e. **newel_types, cap_types, handrail_types, spindle_types** (already correctly mapped in the template at `:122,136,149,152`).
>
> The remaining component repeaters use **prefixed** sub-field keys: `stringer_types` → `stringer_code`/`stringer_name`, `tread_types` → `tread_code`/`tread_name`, `riser_types` → `riser_code`/`riser_name`, `construction_types` → `construction_code`/`construction_name`, `tread_profiles` → `tread_profile_code`/`tread_profile_name`. Calling `bd_code_label('stringer_types', …)` on these returns the **raw code unchanged**, because `$row['code']`/`$row['name']` don't exist on those rows. So Fix A is **not** a one-line wrap — the lookup must be made key-aware first.

---

## 3. Fix A — Label-mapping (raw machine codes on the quote)

### 3a. The five broken fields

All in the **Staircase Details** panel, all currently printing the raw code:

| Template line | Field | Prints now | Source repeater | code/name sub-keys |
|---|---|---|---|---|
| `:108` | `construction_type` | `construction_code` value | `construction_types` | `construction_code` / `construction_name` |
| `:109` | `tread-profile` | `tread_profile_code` value | `tread_profiles` | `tread_profile_code` / `tread_profile_name` |
| `:110` | `stringer_material` | `stringer_code` value | `stringer_types` | `stringer_code` / `stringer_name` |
| `:111` | `tread_material` | `tread_code` value | `tread_types` | `tread_code` / `tread_name` |
| `:112` | `riser_material` | `riser_code` value | `riser_types` | `riser_code` / `riser_name` |

The `.vl { text-transform: capitalize }` CSS (`:42`) only upper-cases the first letter, so a code like `closed` or `spnpine` still reads as a machine token, not a proper label.

### 3b. The fix

Generalise the lookup to accept the code/name sub-field keys, then apply it to the five fields. Two acceptable shapes — implementer's choice:

- **Option 1 (preferred): make `bd_code_label` key-aware.** Add optional `$code_key = 'code'`, `$name_key = 'name'` params; match on those. Existing four calls keep working unchanged (defaults). Add five new calls with the prefixed keys, e.g.
  `$bd_code_label('stringer_types', $content['stringer_material'] ?? '', 'stringer_code', 'stringer_name')`.
- **Option 2: a small `$option_key → [code_key, name_key]` map** inside the template, with a single resolver. Same result.

Either way, the four existing plain-key calls (newel/cap/handrail/spindle) must remain byte-for-byte behaviour-identical.

### 3c. Explicitly NOT part of Fix A (already fine — do not "fix")

- **Spindle Material** (`bal_material`, `:153`) — the historical `pine:12.50` wart is **resolved**: the colon-strip (`formLogic.js:105`) stores `pine`/`oak`/`metal`/`glass`, and `capitalize` renders "Pine"/"Metal"/etc. Leave it.
- **Newel / Handrail / Baserail Material** (`newel_material` `:123`, `hdr_material` `:150`, `bsr_material` `:151`) — hardcoded/colon-stripped material words, render fine via `capitalize`. Leave them.
- **Staircase Type** (`:99–106`) — already mapped via hardcoded arrays; correct since the value set is fixed.

---

## 4. Fix B — Fields missing from the PDF entirely

Nine groups are captured on the form but never rendered. **Dan's decision: add all customer-facing groups**; the only omission is the two internal cost-value inputs. Add every row marked **Add** below.

| Field(s) | Form section | PDF home | Action | Notes |
|---|---|---|---|---|
| `building_regs` | Construction | Staircase Details | **Add** | Map via `building_regs` repeater (`building_reg_name`/`_value`). Reg standard the quote was built to. |
| `treadbt`, `treadit`, `treadat`, `treadit2`, `treadat2` | Sections/Flights | New "Turns & Winders" rows in Staircase Details | **Add** (turn stairs) | Treads before/after turn + winder config. `treadit` is a select (Quarter Landing / 2–3 Winders / Half Landing) — map its option label, not the raw `1`–`4`. Only render for `stair_type` quarter/half. |
| `stair-width2`, `stair-width3` | Measurements | Staircase Essentials (next to `stair-width`) | **Add** (multi-flight) | Per-flight widths; only render when present. |
| `feature_tread`, `left-featured-step`, `right-featured-step` | Construction | Staircase Details | **Add** | Featured/bullnose step choice. Map to labels (Left/Right Bullnose etc.), not raw `0`–`4`. |
| `leftFeatStep`, `rightFeatStep` | Construction (hidden) | — | **Omit** | Internal cost values injected by AJAX, not customer-facing choices. |
| `ballustrades` (true/false) | Posts & Balustrades | — (gate, not a row) | **Gate** | Render the Ballustrading panel **only when `ballustrades` is truthy**; hide the whole panel when the customer opted out. (Currently it always shows.) |
| `delivery` (collected/kerbside), `duodeliv`, `package` (flat/part-assembled), `addon` (fixing kit / extra packaging) | Packaging & Delivery | New "Delivery & Packaging" panel | **Add** (when delivery section enabled) | Only when `delivery_options_enabled`. `addon` is multi-value (checkboxes) — render as a list. Map radio/checkbox values to labels. |

### 4b. Value-mapping cautions for Fix B

- **Selects with numeric values** (`treadit`, `treadit2`, `left/right-featured-step`, `feature_tread`) must render the **option label**, not the stored number/code. Pull labels from the same source the form uses (the relevant options/repeater), mirroring the `bd_code_label` pattern.
- **Checkbox groups** (`addon`) serialise as multiple values — render each as a friendly label ("Fixing Kit", "Extra Packaging"); omit the row if none selected.
- **Conditional presence** — flight-2/3 widths, turn fields, and the delivery panel should only render when relevant (multi-flight stair / turn type / delivery section enabled), so a simple straight-flight quote doesn't show empty rows.
- **Serialisation check** — before rendering, confirm each field actually arrives in `$content` (i.e. it's inside `#stairbuild` and serialised). If any hidden/AJAX-populated field isn't in the POST, adding it to the template alone won't help — trace it in `formLogic.js` first.

---

## 5. Decisions (resolved)

1. **Missing groups** — **add all** customer-facing groups (building regs, turn/winder config, multi-flight widths, featured step, delivery & packaging panel). Omit only the internal `leftFeatStep`/`rightFeatStep` cost values.
2. **Balustrading** — **gate the panel**: render only when `ballustrades` is truthy; hide entirely when the customer opted out.
3. **Layout** — the PDF is a fixed 3-column grid with `220px` panels; adding a Delivery & Packaging panel and turn/winder rows will need a second row of panels and/or relaxed panel heights. The layout may grow beyond one screen — that's accepted. Keep the grid tidy (panels aligned, no orphaned empty panels when conditional sections are hidden).

---

## 6. Acceptance criteria

**Fix A:** all five fields render their admin-defined human names; the four existing plain-key mappings are unchanged; a renamed/removed code still falls back to the raw code (existing behaviour); no change to any non-Details field.

**Fix B (for each group Dan approves):** the choice appears in the correct panel with a human label (never a raw number/code); conditional fields render only when relevant; empty/none states omit the row rather than printing blank; the delivery panel respects `delivery_options_enabled`.

**Both:** a full straight-flight quote and a multi-flight turn quote both render cleanly (no empty rows, no raw codes); versions bumped per §top.

---

## 7. Build sequence

1. **Fix A** — key-aware `bd_code_label` + five call sites. Small, high-value, low-risk. Ship first (patch).
2. Get Dan's §5 answers.
3. **Fix B** — add approved groups, with label-mapping and conditional rendering, plus the balustrading gate. Verify both quote shapes on DDEV (minor).
