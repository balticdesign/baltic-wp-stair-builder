# Brief: Flight tread allocation — derived defaults, clamping, no negative flights

**Project:** baltic-wp-stair-builder
**Type:** Bug fix (form validity + quote correctness)
**Files:** `assets/js/halfTurn.js`, `assets/js/quarterTurn.js`, BuilderUtils (`assets/js/core/`), `front/form-template.php` (input attrs), `assets/js/formLogic.js` (warning pattern reuse)
**Version:** minor bump from current (behaviour change: form defaults + input constraints)
**Depends on:** the Doc K pitch fix (already pushed — both flight scripts use `calculateStepPitch`).

---

## 1. Problem

Flight tread allocation is unfloored arithmetic over hardcoded defaults, so impossible staircases render, price, and reach the quote PDF.

**Half turn** (`halfTurn.js`, `grabFormValues`):
```js
let treads = risers;
let afterturn2 = parseInt(treads - (tits + tits2) - beforeturn - afterturn1);
```
Default double winder at 2600mm now resolves to 13 risers (post pitch fix), with hardcoded `treadbt=6`, `treadat=3`, two 3-winder turns: 13 − 3 − 3 − 6 − 3 = **−2**. Verified live: canvas draws a broken flight 3, the field displays −2, a price (£660.44 inc VAT observed) is computed against an impossible staircase, and the value serialises into the lead → PDF. Before the pitch fix the same config gave −1; the pitch fix changed the riser default, it never touched allocation.

**Quarter turn** (`quarterTurn.js`):
```js
let afterturn1 = parseInt(risers - beforeturn - tits);
jQuery("#treadat").val(afterturn1); // auto-update hidden field
```
Same unfloored subtraction, one flight earlier. Note `#treadat` is **already de facto derived** here — user edits are overwritten on every `grabFormValues()` call — it just isn't marked as such in the UI.

Root cause in both: defaults are hardcoded constants that were never valid for the default riser count, and nothing constrains user input against the tread budget.

## 2. Model

Define per staircase type:

```
budget    = treads                      // as computed in grabFormValues (currently = risers; do NOT change that convention here — see §7)
winders   = tits (+ tits2 on half turn) // Half Landing special case: treadit === 4 → tits = tits2 = 1, afterturn1 forced 0 (existing behaviour, keep)
available = budget − winders            // treads to distribute across straight flights
```

Straight flights: half turn = [flight1 (`#treadbt`), flight2 (`#treadat`), flight3 (derived `#treadat2`)]; quarter turn = [flight1 (`#treadbt`), flight2 (derived `#treadat`)].

**Flight minimums** — implement as named constants at the top of BuilderUtils with these defaults, each commented as a domain decision:
- `MIN_FLIGHT_FIRST = 1` — assume a staircase can't open directly onto a winder. **Flag for Dan to confirm.**
- `MIN_FLIGHT_MID = 0` — 0 treads between turns is definitely legal for Half Landing (existing `treadit===4` path forces it); assume winder-into-winder (six-winder 180°) is also sellable. **Flag for Dan to confirm.**
- `MIN_FLIGHT_LAST = 0` — **confirmed by Dan**: a staircase can legitimately end on the winder box with 0 treads after the final turn (the last riser lands on the upper floor).

## 3. New shared helper — `BuilderUtils.allocateFlightTreads()`

One pure, unit-testable function used by both flight scripts:

```js
/**
 * @param {number} budget      total tread units (treads)
 * @param {number[]} winders   [tits] or [tits, tits2]
 * @param {number[]} requested user values for the non-derived flights, in order
 *                             (half: [beforeturn, afterturn1]; quarter: [beforeturn])
 * @param {number[]} mins      per-flight minimums incl. the derived last flight
 * @returns {{ flights: number[], clamped: boolean, valid: boolean }}
 */
```

Rules:
1. `available = budget − sum(winders)`. If `available < sum(mins)` the configuration is structurally impossible for this riser count → `valid: false` (see §5 warning).
2. Derived last flight = `available − sum(requested)`.
3. If last flight < its min: reduce the requested flights to fit — take from the **last user flight first** (half: `afterturn1`, then `beforeturn`), never below each flight's min. Set `clamped: true`.
4. Requested values are also floored at their own mins and cast with `parseInt`/NaN-guarded (NaN → that flight's min).
5. Deterministic: same inputs always produce the same allocation.

## 4. Defaults — derive, don't hardcode

On initial load and whenever `risers`, `floor-height`, or `going` changes the riser count:
- Recompute `available` and re-run the allocation with the user's current values; clamp per §3 rules. The user's shape choices survive where possible; only excess is trimmed.
- **First load only** (no user edits yet): distribute `available` as evenly as possible across the straight flights, remainder to flight 1 (e.g. available 7 on a double winder → [3, 2, 2]). Remove the hardcoded `6`/`3` template defaults — write the derived values into the inputs before first draw.
- Write derived fields (`#treadat2` half / `#treadat` quarter) inside `onLoad()` as well as the change handler — currently the half-turn derived field is only written on change, so the initial render can disagree with the field.

## 5. Input constraints + warning

- Clamp on `change` for `#treadbt` (both types) and `#treadat` (half turn): if the entered value forces any flight below its min, apply §3 clamping, write the corrected values back into the inputs, and show an inline warning: *"Only N treads available after the turn(s) — values adjusted."* Reuse the existing admin-configured going/building-regs feedback pattern in `formLogic.js` (same styling/placement, new message key).
- `valid: false` case (riser count can't support the winder configuration at all): show the warning persistently and suppress price display until resolved — do not let a price compute from an invalid allocation.
- Convert the derived fields to honest UI: `readonly` (NOT `disabled` — disabled inputs don't POST; the value must still reach the lead + PDF) with a muted style, on `#treadat2` (half) and `#treadat` (quarter). Half Landing mode (`treadit===4`) keeps its existing hide/force-0 behaviour for `#treadat` — verify no conflict with the v2.5.0 stair_config lock/hidden-input logic before touching those attrs.

## 6. Interactions & guardrails

- **Price:** allocation runs before `priceCalc.js` reads anything; after clamping, trigger the normal recalc path so displayed price and captured lead always describe a legal staircase. No changes to priceCalc itself.
- **Canvas:** `flight3Treads.amount` / `flight2Treads.amount` can now legitimately be **0** — verify Stairs.js draws a zero-tread final flight sensibly (winder box straight onto the floor nosing). If it can't, that's a separate canvas fix — report, don't bodge here.
- **PDF:** "Treads after Turn 2: 0" is a legitimate, sellable configuration and must display as `0`, not be suppressed. (The v2.9.1 PDF brief's negative-row guard stays as defence-in-depth; confirm it doesn't swallow zeros — `$bd_row` shows '0' today, keep it that way.)
- **stair_config shortcode pages:** winder configs pre-select 3 Winders — re-run the default derivation after `resolve_stair_config` has applied its selections, not before.

## 7. Out of scope (do not touch)

- The `treads = risers` convention (vs risers − 1). The allocation is convention-agnostic — it spends whatever `treads` is. The convention question stays parked with the pitch-fix audit follow-up.
- `calculateRake` / stringer-length maths.
- priceCalc.js internals, lead capture, PDF template (beyond the §6 zero check).
- Server-side re-validation of allocation in lead capture — optional hardening, park as a named follow-up decision.

## 8. Test matrix

| # | Scenario | Expect |
|---|----------|--------|
| 1 | Double winder, 2600mm default load (the live failing case) | Derived defaults, no negatives, e.g. 13 treads − 6 winders → [3,2,2]; canvas + price sane |
| 2 | Same, user sets treadbt=6 then treadat=3 | Clamped with warning; flight 3 ≥ 0; inputs show corrected values |
| 3 | User reduces risers after configuring flights | Re-clamp fires; no negative appears even transiently in `#treadat2` |
| 4 | Legit 0-after-turn-2 config | Draws, prices, PDF shows "Treads after Turn 2: 0" |
| 5 | Half Landing (`treadit=4`) | Existing force-0 behaviour intact; allocation respects tits=tits2=1 |
| 6 | Quarter turn, oversized treadbt | `#treadat` (readonly) never negative; warning shown |
| 7 | Riser count too small for winders (`valid:false`) | Persistent warning, price suppressed |
| 8 | `stair_config` winder shortcode pages | Defaults derived after config resolution; locked fields still POST |
