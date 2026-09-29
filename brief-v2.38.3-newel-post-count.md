# Brief — v2.38.3: Newel post count cap and box-corner double count

**Repo:** baltic-wp-stair-builder (GitHub is ground truth — verify everything below against current `main` before changing anything)
**Current version:** 2.38.2 → bump to **2.38.3** (patch: pricing bug fix, no schema or settings change)
**Reported by:** SPD (Daniel), on the double winder: "it will only cost in up to 9 posts, if I add in 10–12 it won't increase the price"

---

## Background — what we already know (verified 24 Sept 2026 at 2.38.2)

1. **Hard cap of 7 on custom posts.** `assets/js/formLogic.js`, in the `#posts :input` change handler:

   ```js
   let customValue = 'custom:' + Math.min(numChecked, 7);
   ```

   `numChecked` counts every ticked checkbox in `#custom`. The result is written into the `custom:N` option value, which `priceCalc.js` reads via `BuilderUtils.getNumber('newel-posts')`.

2. **Mandatory posts are added on top.** `assets/js/priceCalc.js`:

   ```js
   const $mandatoryPosts = $stairType === 'half' ? 2 : ($stairType === 'quarter' ? 1 : 0);
   const $newel_amt = BuilderUtils.getNumber('newel-posts') + $mandatoryPosts;
   ```

   So on a half turn the ceiling is 7 + 2 = **9**, which matches Daniel's report exactly.

3. **Checkbox counts per stair type** (`front/form-template.php`, `#custom`):
   - Straight: tl, tr, bl, br = 4
   - Quarter: + to-post, bo-post, box-post = 7
   - Half: + to-post2, bo-post2, box-post2 = 10

   The cap of 7 appears to be a leftover sized for the quarter turn. It truncates the half turn.

4. **Suspected double count on box corners.** The mandatory posts added in `priceCalc.js` are described in comments as "one mandatory box-corner post per box that joins two flights", drawn by default on the canvas. The `#box-post` / `#box-post2` checkboxes ("Flt.1 Box Corner" / "Flt.2 Box Corner") appear to be **those same posts**. If so, ticking them adds a second charge for a post that is already priced.

5. **Same pattern in the PDF fallback.** `templates/stairbuilder_pdf.php` rebuilds the count for older leads with no `newel-count` field: it sums all ten checkboxes including `box-post` / `box-post2`, then adds `flights - 1` mandatory posts. There's no cap there, but it has the same possible double count. New leads use `newel-count` from `priceCalc.js`, so they inherit whatever the JS does.

**Expected physical maximum on a half turn with a middle flight:** 4 (top/bottom L/R) + 3 per turn × 2 = 10 posts, of which 2 are the mandatory box corners. The correct priced maximum is therefore **8 optional + 2 mandatory = 10**.

---

## Part 1 — Recon (report only, no code changes)

Confirm or correct each point below. Report back before implementing if any of them turns out to be wrong.

1. Confirm the `Math.min(numChecked, 7)` cap and find any other cap or clamp on newel count (formLogic, priceCalc, BuilderUtils, halfTurn/quarterTurn, enquiries class, PDF).
2. **Key question:** are `#box-post` / `#box-post2` the same physical posts as the mandatory box-corner posts? Check how the canvas (Stairs.js / halfTurn.js / quarterTurn.js) draws box corners:
   - Is the box corner drawn whether or not the checkbox is ticked?
   - Does the checkbox change anything other than the count (drawing, balustrade logic — note `boxcorner` / `boxcorner2` feed the `turntop` / `turnside` balustrade flags in halfTurn.js)?
3. Check `#custom :checkbox:checked` includes only the post checkboxes, not anything else living inside `#custom`.
4. Confirm the mid-flight posts (`.bd-midflight-post`, `#bo-post` / `#to-post2`) are unchecked when hidden (the comments say `bdUpdateMidFlightPosts` does this), so they can't inflate the count when the middle flight is empty.
5. For each stair type, list: checkboxes available, which are box corners, mandatory count, and resulting min/max priced newel count today vs. what it should be.

**Decision flag for Dan:** if recon shows the box-corner checkbox is genuinely optional (i.e. a staircase can be built and drawn **without** that post), then the mandatory +1/+2 is what's wrong, not the checkbox. Do not guess. Report and stop.

---

## Part 2 — Implementation (only if recon confirms the assumptions above)

1. **Remove the fixed cap of 7.** The count should be the number of ticked optional post checkboxes, with no arbitrary ceiling. If a ceiling is kept as a safety net, derive it from the number of optional checkboxes actually rendered for the stair type. Do not hardcode it.
2. **Stop box-corner ticks being counted twice.** Preferred approach: exclude `#box-post` and `#box-post2` from the custom tally, since those posts are always priced as mandatory. Leave the checkboxes' drawing and balustrade behaviour unchanged unless recon shows they need to change. Recommend (don't implement without asking) whether they should instead render as ticked and disabled for clarity. That's a UI decision for Dan.
3. **Apply the same rule in the PDF fallback** in `templates/stairbuilder_pdf.php`: remove `box-post` / `box-post2` from the `$corner_posts` tally, so old leads rebuild to the same number the JS now produces.
4. **Quarter turn:** the same box-corner rule applies (1 mandatory, `#box-post`). Make sure the fix covers it.
5. Update the code comments in all touched places to state the rule clearly. Box corners are mandatory and always priced; the custom tally counts optional posts only; there is no fixed cap. That way nobody reintroduces the 7.

**Out of scope:** newel pricing values, newel spec visibility rules (`bdUpdateNewelVisibility`, `bdSyncHiddenNewelSpec`), the preset left/right/both counts on straight flights, and base/turn pricing. Don't touch these.

---

## Testing checklist

For each: straight, quarter landing, single winder, half landing, double winder, double quarter landing. On half turns, test with the middle flight both populated and empty (Treads After Turn = 0).

- [ ] Tick every available optional post → priced newel count equals physical post count (half turn with middle flight = 10).
- [ ] Tick posts one at a time → price increases by one newel each time, with no plateau.
- [ ] Tick/untick box-corner boxes → newel count and price do not change.
- [ ] Middle flight emptied → hidden mid-flight posts drop out of the count.
- [ ] `#newel-count` hidden field, the submitted lead and the PDF quote all show the same number as the price.
- [ ] A pre-existing lead without `newel-count` rebuilds in the PDF to the corrected number.
- [ ] Presets (None/Left/Right/Both) on straight flights unchanged.

## Release

- Bump `Version:` header and `BALTIC_STAIRBUILDER_VERSION` to 2.38.3.
- Changelog entry: newel post count no longer capped; box-corner posts no longer double counted.
- Debrief: recon findings (especially the box-corner answer), files changed, before/after max counts per stair type.
