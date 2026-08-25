# Implementation Brief — Featured Step: Independent Left / Right Selection

**Target version:** v2.23.0 (v2.22.0 already shipped — verify against the repo, not this brief)

**Filename:** `BRIEF-featured-step-split-2026-08-25.md`. **Disregard any earlier featured-step brief on disk, including `BRIEF-featured-step-left-right.md`.** Delete it rather than reconciling the two.

**Scope:** Featured step form input, pricing, and settings. `Stairs.js` and all rendering are **out of scope** — see §7.

> **Supersedes any earlier draft of this brief.** An earlier version modelled pricing as a lookup table of (left, right) SKU pairs, treating each pair as a distinct bespoke price, and treated the two-sided multiplier as a discount. Both were wrong. Pricing is a per-side sum, with a global **uplift** multiplier applied when both sides carry a feature. Do not implement a pair lookup table and do not treat the multiplier as a discount.

---

## 1. Goal

Replace the single combined `Featured Tread` dropdown with two independent dropdowns — **Left** and **Right** — each selecting the treatment applied to that side of the staircase.

This restores parity with the client's original form, which had exactly this arrangement, and adds asymmetric combinations that the current builder cannot express.

---

## 2. Terminology — read this before anything else

The word **"double"** in the client's price field names means **two steps deep**, not two sides.

- `{mat}_dbl_curtail_price` is the price of a curtail feature spanning steps 1 and 2, **on one side**.
- It is *not* the price of a curtail on the left plus a curtail on the right.

**Every price field in the featured step group is a one-side price.** A feature applied to both sides costs two of them.

The current builder's option labels use "Double" in the opposite sense — "Double Bullnose Step" there means both sides. This collision is the source of the confusion and must be removed from the new labels.

---

## 3. Data model

```
featured_step_left   ∈ { none, bullnose, curtail, dbl_curtail, dbl_curtail_bullnose }
featured_step_right  ∈ { none, bullnose, curtail, dbl_curtail, dbl_curtail_bullnose }
```

Five values per side, fully independent. All 25 combinations are valid — there are no illegal pairs.

Material remains a **single** value for the whole feature step. Do not add per-side material.

---

## 4. Option labels

Since both selects are explicitly headed Left and Right, "double" can no longer be misread as sides — but it is still worth being unambiguous. Proposed:

| Value | Label | Notes |
|---|---|---|
| `none` | None | |
| `bullnose` | Bullnose Step | |
| `curtail` | Curtail Step | |
| `dbl_curtail` | Double Curtail plus Single Curtail | Two steps — see §4.2 |
| `dbl_curtail_bullnose` | Double Curtail plus Bullnose | Two steps — see §4.2 |

Use the client's wording verbatim. Andy and Daniel read these on the works order.

### 4.1 Glossary — value names are shorthand for whole products

This terminology has been misread repeatedly. Pin it down:

| Value | Price field | Full product name | SPD shorthand |
|---|---|---|---|
| `bullnose` | `{mat}_bullnose_price` | Bullnose Step | — |
| `curtail` | `{mat}_curtail_price` | Curtail Step | — |
| `dbl_curtail` | `{mat}_dbl_curtail_price` | Double Curtail plus Single Curtail | DCC |
| `dbl_curtail_bullnose` | `{mat}_dcb_curtail_price` | Double Curtail plus Bullnose | DCB |

`dbl_curtail` is shorthand for the **entire** Double Curtail plus Single Curtail product — the double-going step 1 *and* the single curtail resting on step 2. It is **not** a double-going step on its own, and it is **not** a curtail on both sides.

SPD's own abbreviations appear on their legacy form's material label: "Double / Single Material - DCC/B".

Each value name matches its price field key. Preserve that alignment — it is what makes the mapping verifiable at a glance.

### 4.2 What "double curtail" refers to

**"Double curtail" refers to the going, not to the number of sides.** The step is built to **double the standard going** — a 240mm going produces a 480mm-deep first step. The second element ("plus single curtail" or "plus bullnose") sits on step 2, resting on the deeper step below.

There is no standalone "double curtail" product; it always carries something above it. This is why only two `dbl_curtail*` price fields exist.

**Geometry note — informational only.** The renderer already handles values 3 and 4 and is **out of scope for this release** (§7). Do not adjust drawing dimensions, `total_run`, or pitch in response to this description. Pitch remains `atan(individual_rise / going)` using the standard going, unchanged.

---

## 5. Pricing

### 5.1 The rule

Base pricing is a per-side sum. A configurable **uplift multiplier** applies when both sides carry a feature, reflecting the additional cost of fitting features to both sides of the step.

```
if left == none OR right == none:
    price = price(left) + price(right)              // one term is 0, no uplift
else:
    price = (price(left) + price(right)) × multiplier
```

The multiplier applies to the **combined** price, not to one side. It is order-independent by construction — `(curtail, bullnose)` and `(bullnose, curtail)` return the same figure.

No pair lookup table, no per-combination SKUs, no per-material multipliers. One global value.

Worked example, Oak, multiplier 1.5:

```
(curtail, none)         109.14 + 0        = £109.14      no uplift
(curtail, curtail)     (109.14 + 109.14)  × 1.5 = £327.42
(curtail, bullnose)    (109.14 +  85.60)  × 1.5 = £292.11
```

### 5.2 Per-side price map

| Value | Price field |
|---|---|
| `none` | `0` |
| `bullnose` | `{mat}_bullnose_price` |
| `curtail` | `{mat}_curtail_price` |
| `dbl_curtail` | `{mat}_dbl_curtail_price` |
| `dbl_curtail_bullnose` | `{mat}_dcb_curtail_price` |

`{mat}` ∈ `mdf`, `ply`, `pine`, `oak`. All 16 fields already exist and are populated — no new price fields are required.

Note that `{mat}_dbl_curtail_price` is currently **orphaned** — the treatment it prices was dropped when the builder was simplified. Reinstating it costs nothing.

### 5.3 The multiplier setting

```
featured_step_both_sides_multiplier    default 1.5
```

- Single global setting, not per material and not per combination.
- Store and present as a **multiplier**, not as an amount to add. `1.5` means a 50% uplift. Do not label it "0.5" or "50%" in the stored value — the ambiguity about what the figure is added to is exactly what needs removing.
- Validate `0.1 ≤ x ≤ 5`. Reject anything outside; do not silently clamp.
- Add `'adjustable' => true` so the planned bulk price tool picks it up without a second pass.
- Place it on the Featured Step tab, above the price fields, with help text: *"Applied to the combined price when a feature is fitted to both sides of the step. 1 = no uplift. 1.5 = 50% uplift."*
- Show a live worked example beneath it using current Oak prices, so the effect is visible before saving.

### 5.4 Rounding

Apply the multiplier at full precision and round **once**, at the point the featured step subtotal enters the quote. Match whatever rounding convention the rest of the plugin already uses for component subtotals — locate it and follow it rather than introducing a new one. Do not round the intermediate sum before multiplying.

### 5.5 Correctness rule

A price mapping defect exists in v2.22.x: `get_stepCost()` resolves `1 → bullnose, 2 → curtail`, while the renderer and the form's own option labels use `1 → curtail, 2 → bullnose`. Values 3 and 4 are correctly aligned. **`get_stepCost()` is the outlier and must be corrected.**

Consequence today: curtail selections are charged at bullnose prices (undercharging SPD by £23–25 per side) and bullnose selections at curtail prices (overcharging the customer by the same). The drawing and the works order are correct; only the price is wrong.

**Correct `get_stepCost()` to `1 → curtail, 2 → bullnose`.** Do not preserve the existing behaviour.

Scope of the fix: **positions 1 and 2 only.** Verified empirically in MDF —

- Left Curtail currently charges £44.10 (`mdf_bullnose_price`); should be £67.20
- Left Bullnose currently charges £67.20 (`mdf_curtail_price`); should be £44.10
- Left Double Curtail + Bullnose charges £120.00 (`mdf_dcb_curtail_price`) — **already correct, leave alone**
- Position 3 (`dbl_curtail`) is unexposed and therefore untestable, but is unaffected by a 1↔2 transposition. Once exposed, Left Double Curtail must return **£89.25 ex VAT** in MDF. Add this to the test pass.

This supersedes any earlier instruction to keep v2.2x prices identical. The rule is now:

> With the multiplier at 1.0, every combination must equal the sum of the per-side price fields as labelled by SPD — verified against the table below, not against current output.

| Option (multiplier 1.0) | MDF | Ply | Pine | Oak |
|---|---|---|---|---|
| Single Bullnose | 44.10 | 30.00 | 57.85 | 85.60 |
| Single Curtail | 67.20 | 55.00 | 81.90 | 109.14 |
| Single Double Curtail | 89.25 | 68.00 | 141.75 | 210.79 |
| Single Double Curtail + Bullnose | 120.00 | 102.00 | 165.00 | 250.00 |
| Both Bullnose | 88.20 | 60.00 | 115.70 | 171.20 |
| Both Curtail | 134.40 | 110.00 | 163.80 | 218.28 |
| Both Double Curtail + Bullnose | 240.00 | 204.00 | 330.00 | 500.00 |

At the shipped multiplier of 1.5, every two-sided figure above rises by 50%.

**Produce a before/after diff** of all 9 current options × 4 materials, showing old price, new price at multiplier 1.0, and new price at 1.5. This goes to the client — it is the record of what changed and why.

Commit the `get_stepCost()` correction **separately** from the left/right feature work so it can be reverted independently.

### 5.6 Missing prices → price on application

If a required price field is empty or zero, do **not** quote a partial figure. Route the configuration into the **existing price-on-application path** built for mini open riser and cut string. Reuse that code — do not write a parallel implementation.

Behaviour must be identical: "Submit for Quote" across configurator, PDF, customer email and online quote page, with the internal calculated figure still recorded and passed to the office.

---

## 6. Backward compatibility

Existing stored entries hold the old single enum. Decompose on read — do **not** migrate data destructively:

| Legacy value | → (left, right) |
|---|---|
| None | `(none, none)` |
| Left Bullnose Step | `(bullnose, none)` |
| Right Bullnose Step | `(none, bullnose)` |
| Double Bullnose Step | `(bullnose, bullnose)` |
| Left Curtail Step | `(curtail, none)` |
| Right Curtail Step | `(none, curtail)` |
| Double Curtail Step | `(curtail, curtail)` |
| Left Curtail and Bullnose Step | `(dbl_curtail_bullnose, none)` |
| Right Curtail and Bullnose Step | `(none, dbl_curtail_bullnose)` |
| Double Curtail and Bullnose Step | `(dbl_curtail_bullnose, dbl_curtail_bullnose)` |

Note the last three: the current builder's "Curtail and Bullnose Step" is the legacy `dcb` product, which is a **two-step** feature on one side. It maps to `dbl_curtail_bullnose` — not to a composition of a curtail and a bullnose.

---

## 7. Renderer — DO NOT MODIFY

`Stairs.js` is out of scope. The client has been satisfied with its output for years and has raised no complaint about measurements or rendering. **Make no changes to it.**

### 7.1 Why no changes are needed

Inspection confirms the per-side model is already complete:

- `Stairs.options.featureTread.left` / `.right` parsed at lines 336–340.
- Mapping documented in-file at every config construction site: `0: none 1: curtail 2: bullnose 3: double going curtail plus single curtail 4: double going curtail plus bullnose`.
- Geometry for values 3 and 4 present and reached. `Stairs.doubleFeatureTreadEnabled` (lines 342–348) fires when either side ≥ 3, driving `drawDoubleFeatureTread` at lines 883–890, 934–940, 1013–1019, 1148–1154, 1212–1218.
- `printMMHeight` derives from `treads.height × (amount − 1)` plus the top lip only. `FEATURE_TREAD_HEIGHT` affects canvas sizing, never the printed dimension.

Exposing the two selects feeds the renderer values it already understands. Nothing further is required.

### 7.2 Explicitly out of scope

Noted here so they are not "helpfully" fixed in passing:

- **`StairConstants.FEATURE_TREAD_HEIGHT = 6/5`.** Feature treads draw at 1.2 × going, not 2 ×. This is an established drawing convention applied consistently to all feature treads including bullnose and curtail, which have been live for years without complaint. **Leave it.**
- **`calculateStartY` singles branch** (lines 3208–3213) subtracts `height` where `dealWithYPositions` uses `featureHeight`. Existing behaviour. **Leave it.**
- **Variable names** `isLeftDoubleBullnose` / `isLeftDoubleCurtail` describe DCB and DCC respectively. Misleading but harmless, and the in-file comments are accurate. **Leave them.**

### 7.3 Verification only — observe, do not fix

Confirm by looking at the output that exposing value 3 produces a sensible drawing:

- `dbl_curtail` (value 3) in each stair type — straight, quarter turn, half turn, double turn.
- Both sides at value ≥ 3 simultaneously.
- An asymmetric pair, e.g. `dbl_curtail` left with `bullnose` right.

If any of these draws incorrectly, **report it and stop**. Do not fix it inside this release — it becomes a separate decision for the client.

---

## 8. Material control

One material for the whole feature step. Give it a **static** label — "Feature Step Material".

Do **not** replicate the legacy form's dynamic label ("Double Step Material with Bullnose" / "…with Curtail"). It existed to disambiguate a badly-modelled option list and is unnecessary once treatments are named clearly.

If the two sides have different treatments, the single material applies to both.

---

## 9. Output formatting

One shared helper converts the pair into a display string. Use it everywhere — configurator summary, PDF quote, customer email, works order, form entry. Do not duplicate per output target.

```
(none, none)                     → "None"
(curtail, none)                  → "Left: Curtail Step"
(curtail, curtail)               → "Both sides: Curtail Step"
(curtail, bullnose)              → "Left: Curtail Step / Right: Bullnose Step"
(dbl_curtail_bullnose, none)     → "Left: Double Curtail + Bullnose (2 steps)"
```

Collapse to "Both sides:" only when the two values are identical.

---

## 10. Do not change

**Scope boundary: this release touches pricing and form configuration only.**

- **`Stairs.js` — no changes of any kind.** See §7. The client has been satisfied with its rendering and measurements for years. Not a line.
- **`total_run` must not absorb any featured step adjustment.** It feeds the pitch calculation; this bug has occurred before with landing nosing depth.
- **Pitch remains `atan(individual_rise / going)`** using the standard going — a per-step property. Do not recompute from totals.
- Do not alter material selection logic. It was recently fixed after silently reverting to Pine and repricing quotes.
- Do not rename or add featured step **price** option keys. All 16 required fields exist. (The multiplier setting in §5.3 is a new key and is expected.)
- Do not add per-side material selection.
- Do not implement a pair lookup table, per-combination SKUs, or per-material multipliers. One global value, applied per §5.1.
- Do not apply the multiplier to single-sided configurations.
- Do not exempt the existing "Double" options from the multiplier. They are two-sided configurations and must be treated as such.
- Do not refactor, tidy, or "improve" adjacent code. If something looks wrong and is outside pricing or form configuration, report it and move on.

---

## 11. Open questions — flag, do not guess

1. Does the client want `dbl_curtail` ("Double curtail plus single curtail") reinstated? It exists in their legacy form and its price field is populated, but it was dropped from this builder. Assumed yes. Confirm before shipping.
2. Are all four materials offered on all five treatments? Prices exist for all 16 combinations, so assumed yes.

---

## 12. Acceptance criteria

- [ ] Two independent selects render, each with the five values in §3.
- [ ] Pricing is a per-side sum with a single global uplift multiplier per §5.1. No pair table, no per-material values, no special-casing anywhere in the path.
- [ ] `get_stepCost()` resolves `1 → curtail, 2 → bullnose`, matching the renderer and form labels. Committed separately.
- [ ] **With the multiplier forced to 1.0**, all combinations match the table in §5.5 exactly.
- [ ] A before/after diff of 9 options × 4 materials has been produced, showing old, new at 1.0, and new at 1.5.
- [ ] At the default 1.5, every two-sided figure in §5.5 is 50% higher.
- [ ] The multiplier does **not** apply to single-sided configurations — `(curtail, none)` prices at `{mat}_curtail_price` unchanged at any multiplier value.
- [ ] `(curtail, bullnose)` and `(bullnose, curtail)` return the same figure.
- [ ] `dbl_curtail` is selectable and prices from `{mat}_dbl_curtail_price`.
- [ ] The multiplier setting saves, reloads, rejects out-of-range values, and its worked example updates live.
- [ ] An empty or zero price field triggers price on application across all four customer-facing surfaces, with the internal figure still reaching the office.
- [ ] Legacy stored entries decompose per §6 and reprice correctly. Note these will reprice *upward* at the default multiplier where the legacy value was two-sided — expected, not a fault.
- [ ] **`Stairs.js` is unmodified.** Confirm with a diff.
- [ ] `dbl_curtail` (value 3) renders sensibly in every stair type — straight, quarter turn, half turn, double turn. Observation only.
- [ ] Both sides at value ≥ 3 simultaneously, and an asymmetric pair, render sensibly. Observation only.
- [ ] Pitch and `total_run` are unchanged for every configuration carrying a featured step.
- [ ] Version bumped to 2.23.0 in the plugin header and changelog.
