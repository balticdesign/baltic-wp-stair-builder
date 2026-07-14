# Brief: Pitch Angle Calculation Fix — Per-Step Pitch (Doc K Compliant)

**Project:** baltic-wp-stair-builder
**Type:** Bug fix (calculation correctness + regulatory validation)
**Version:** bump minor from current (calculation behaviour change, not just cosmetic)

---

## Problem

Pitch is currently calculated as `atan(total_height / total_run)`, where:

- `total_height` = floor-to-floor rise = **risers** × riser_height (e.g. 13 × 200 = 2600mm)
- `total_run` = **treads** × going = (risers − 1) × going (e.g. 12 × 250 = 3000mm)

This mixes 13 risers' worth of height with 12 goings' worth of run, producing an
inflated angle: **40.91° instead of the correct 38.66°** for the 2600/13/250 example.

The pitch line (Approved Doc K) joins the nosings. From the first nosing to the
floor nosing there are (risers − 1) rises and (risers − 1) goings, so:

```
correct pitch = atan(riser_height / going)
```

This is definitionally correct, has no off-by-one risk, and is what UK Building
Regs mean by "pitch".

## Impact — two severities

### 1. FUNCTIONAL BUG (priority): `getStaircaseConfig()` in utils.js

```js
let newTotalRun = going * (possibleRisers - 1);
let newPitch = calculatePitch(height, newTotalRun);   // inflated
if (going >= 220 && going <= 300 && newPitch <= 42) { ... }
```

The inflated pitch means **valid riser configurations near the 42° limit are
wrongly excluded** from the "No. Of Risers" dropdown. Customers are being denied
legal configurations.

**Fix:**
```js
let newPitch = Math.atan(possibleRiserHeight / going) * (180 / Math.PI);
```
(`newTotalRun` can stay if used elsewhere, but pitch must not derive from it.)

### 2. DISPLAY BUG: quote panel "Angle"

The measurements/quote panel (and PDF, if it echoes the same value) shows the
inflated angle. Wherever the displayed pitch is computed (flight scripts /
grabFormValues implementations), switch to `atan(riserh / going)`.

## Required changes

1. **utils.js — add a correct helper, don't silently change the old one:**
   ```js
   /**
    * Doc K pitch: angle of the line joining nosings.
    * @param {number} riserHeight - individual rise (mm)
    * @param {number} going - individual going (mm)
    */
   function calculateStepPitch(riserHeight, going) {
     if (going === 0) return 0;
     return Math.atan(riserHeight / going) * (180 / Math.PI);
   }
   ```
   Then migrate call sites. Once no callers remain that rely on the old
   height/run semantics, deprecate or remove `calculatePitch`.

2. **Audit ALL call sites of `calculatePitch`** — utils.js fallback
   `grabFormValues`, and every flight script (straight, landing, winder,
   double_quarter each have their own `grabFormValues`). Each computes
   `pitch = Math.atan(height / total_run) * (180 / Math.PI)` inline or via the
   helper. All must move to per-step pitch.

3. **`getStaircaseConfig()` validation loop** — fix as above. After fixing,
   sanity-check that the dropdown now offers configs it previously excluded
   (test near the 42° boundary, e.g. going=220 with riser heights around
   195–200mm: atan(198/220) = 42.0°).

4. **Audit, don't blind-fix, these two:**
   - `calculateRiserHeight(totalHeight, numberOfTreads)` — divides by treads,
     but riser height = height ÷ **risers**. Check what each caller actually
     passes. If callers pass riser count, rename the param. If callers pass
     tread count, it's a live bug — fix and note it.
   - `calculateRake(height, totalRun)` — full-height hypotenuse. If rake is
     used for stringer/material length this may be intentionally generous
     (material runs past the pitch line). **Do not change without checking
     usage; flag findings in the debrief instead.**

5. **PDF quote** — verify whether the pitch on the v2.9.0 branded PDF is
   recomputed or passed through from the front end. Either way it must show
   the corrected value.

## Do NOT change

- `total_run = going × (risers − 1)` — this is correct and drives the plan
  drawing footprint. Leave it.
- Riser count semantics: risers is the canonical input; treads = risers − 1.
  The dropdown label format ("13 @ 200.0mm") stays.
- `regcheck`/`modifier` legacy logic in the fallback `grabFormValues` — out of
  scope unless it directly blocks the pitch fix; if it smells wrong, flag it,
  don't refactor it in this pass.

## Verification

- 2600mm / 13 risers / 250 going → panel shows **38.66°** (was 40.91°)
- 2600mm / 13 risers / 220 going → **42.27°** (should now be excluded/flagged —
  confirm the validator excludes it for the RIGHT reason)
- Riser dropdown at boundary cases offers configs previously missing
- Doc K sanity: 2×rise + going within 550–700 for offered configs (if this
  check exists; if it doesn't, note it as a future enhancement, don't add it now)
- Regression: quote PDF, lead capture payload, and drawing all consistent

## Debrief expectations

Report back: which call sites were changed, findings on `calculateRiserHeight`
and `calculateRake` usage, and any configs the dropdown now offers that it
previously excluded.
