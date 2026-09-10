// Metal/glass balustrade pricing. Wood spindles never call this — their per-tread
// count is left exactly as it was. Counts/lengths come pre-computed from the caller.
//   metal: count = ceil(stairRun / 141) + ceil(landingRun / 112), min 1, × unit price.
//          stairRun uses the 240mm-going / 42°-rake assumption so the configurator
//          agrees with the live spindle-calculator product page (divisors 141/112).
//   glass: per linear metre  -> (run / 1000) × price
//          per panel         -> ceil(run / panelWidth) × price
// Returns { price, count }. `count` is the discrete unit count captured for the
// quote PDF: metal = spindles, glass per-panel = panels, glass per-metre = 0
// (a continuous run has no discrete count).
function altBalustradePrice(mode, g) {
  if (mode === 'metal') {
    let n = Math.ceil(g.runStairs / 141);
    if (g.runLanding > 0) n += Math.ceil(g.runLanding / 112);
    n = Math.max(1, n);
    return { price: n * g.unitCost, count: n };
  }
  // glass
  if (g.glassUnit === 'per_panel') {
    // Panels don't butt together — the effective pitch is panel width + gap.
    const pitch = g.panelWidth + (g.panelGap || 0);
    const panels = pitch > 0 ? Math.ceil(g.glassRun / pitch) : 0;
    return { price: panels * g.unitCost, count: panels };
  }
  return { price: (g.glassRun / 1000) * g.unitCost, count: 0 };
}

// Price on application, set per construction type in the admin (data-poa on the
// option). These types are quoted by hand, so the configurator runs and the
// lead still submits — only the figures are withheld.
function bdIsPoaSelected() {
  if (jQuery('#construction_type option:selected').attr('data-poa') === '1') return true;
  // §5.6 — a selected featured step whose price field is empty or zero can't be
  // quoted either. Same route, same wording, set by the pricing endpoint.
  if (jQuery('#featStepPoa').val() === '1') return true;
  // BRIEF-02 (v2.34.0): configured construction limits (minimum flight width,
  // floor-to-floor range) also route into POA. Recomputed fresh here rather
  // than read from state, so the POA verdict can never trail the inputs by an
  // event regardless of handler binding order. Owned by formLogic.js, which
  // also renders the customer-facing messages off the same function.
  if (typeof window.bdComputePoaReasons === 'function' && window.bdComputePoaReasons().length) return true;
  return false;
}

// Wide-flight surcharge (BRIEF-02 amend #3): a fixed amount added ONCE when
// any genuine flight width strictly exceeds the configured threshold. Inert
// (returns 0) until both threshold and amount are set — nothing SPD-specific
// is baked in. Kept as one named function so the per-staircase / landing-depth
// exclusion logic is readable in one place; the width set itself comes from
// BuilderUtils.bdGenuineFlightWidths, shared with the min-width POA check and
// mirrored server-side.
function bdWideFlightSurcharge() {
  const cfg = (window.stairBuilderVars && stairBuilderVars.construction) || {};
  const th = parseFloat(cfg.wide_flight_surcharge_threshold_mm);
  const amt = parseFloat(cfg.wide_flight_surcharge_amount);
  if (!isFinite(th) || th <= 0 || !isFinite(amt) || amt <= 0) return 0;
  const anyWide = BuilderUtils.bdGenuineFlightWidths(jQuery).some(function (w) { return w > th; });
  return anyWide ? amt : 0;
}

// T&G landing board price for the current selections (BRIEF-03, v2.35.0).
// Returns 0 unless the #tandg_landing select exists (half:landing only) AND
// the customer chose "T&G landing included". Two set prices from settings,
// keyed on the tread material code — see the call site comment.
function bdTandgLandingPrice() {
  if (jQuery('#tandg_landing').val() !== 'included') return 0;
  const tg = (window.stairBuilderVars && stairBuilderVars.landing_tg) || {};
  const treadCode = String(BuilderUtils.getString('tread_material') || '').toLowerCase();
  const oakCodes = (tg.oak_codes || ['oak']).map(function (c) { return String(c).toLowerCase(); });
  const price = parseFloat(oakCodes.indexOf(treadCode) !== -1 ? tg.oak : tg.standard);
  return (isFinite(price) && price > 0) ? price : 0;
}

// `is-poa` on the footer hides the cost + VAT rows and the total's label, so
// "Submit for Quote" stands on its own where the total normally sits.
function bdApplyPoaDisplay() {
  jQuery('.bd-panel-foot').addClass('is-poa');
  jQuery('#priceCalc').text('—');
  jQuery('#vat').text('—');
  jQuery('#total').text('Submit for Quote');
}

function calculateTotalPrice() {
  // Use stair-specific grabFormValues if available, otherwise fallback
  const formValues =
    typeof window.grabFormValues === "function"
      ? window.grabFormValues()
      : BuilderUtils?.grabFormValues
      ? BuilderUtils.grabFormValues()
      : (console.error("No grabFormValues function available"), null);

  if (!formValues) return;

  // Flight allocation (run inside grabFormValues for turned staircases) may flag
  // the configuration as structurally impossible for the current riser count.
  // Never price an invalid staircase — blank the outputs and bail until the user
  // resolves it (increases risers / reduces the turns).
  // A POA type is POA whatever else is going on, so resolve it before the
  // invalid-configuration bail-out and clear the state when it's switched off.
  const bdPoa = bdIsPoaSelected();
  if (!bdPoa) {
    jQuery('.bd-panel-foot').removeClass('is-poa');
  }

  if (window.bdFlightInvalid) {
    // Nothing was computed, so there's no internal figure to carry either.
    window.bdComputedPrice = null;
    if (bdPoa) {
      bdApplyPoaDisplay();
      return;
    }
    jQuery("#priceCalc").text('—');
    jQuery("#vat").text('—');
    jQuery("#total").text('—');
    return;
  }

  // === Core Inputs ===
  const $height = formValues.height;
  const treads = formValues.treads;
  const $qtBefore = formValues.beforeturn;
  const $qtAfter = formValues.afterturn1;
  const $htAfter2 = formValues.afterturn2;
  const $riserh = formValues.riserh;
  const $pitch = formValues.pitch;
  const $width = parseFloat(formValues.width);
  const $rake = formValues.rake;
  const $spLmod = parseFloat(formValues.spLmod);
  const $spRmod = parseFloat(formValues.spRmod);
  const rightBal = +formValues.bal_r;
  const leftBal = +formValues.bal_l;
  const rightBal2 = +formValues.bal2_r;
  const leftBal2 = +formValues.bal2_l;
  const leftBal3 = +formValues.bal3_l;
  const rightBal3 = +formValues.bal3_r;
  const boxBal1 = +formValues.turntop || 0;
  const boxBal2 = +formValues.turnside || 0;
  const boxBal3 = +formValues.turn2top || 0;
  const boxBal4 = +formValues.turn2side || 0;

  // === DOM Inputs ===
  const $wmp = parseFloat(jQuery('#widthmulti').val());
  const $vatRate = parseFloat(jQuery('#vatRate').val());

  // === Extras/Addons ===
  let $setup_fee = 0,
    $addprice = 0,
    $duodeliv = 0,
    $fixkit = 0,
    $asspkg = 0,
    $xtrap = 0;

  // === Material Prices ===
  const $ctype = parseFloat(jQuery('#construction_type option:selected').attr('data-price'));
  const $boxSpindleNo = parseFloat(Math.ceil($width / 112));
  if (typeof bonuslogic === "function") $addprice = bonuslogic();

  const $stringer_price = BuilderUtils.getNumber('stringer_material');
  const $tread_price = BuilderUtils.getNumber('tread_material');
  const $riser_price = BuilderUtils.getNumber('riser_material');
  const $tread_profile_price = parseFloat(jQuery('#tread-profile option:selected').attr('data-price')) || 0;

  // === Derived Values ===
  const $going = parseFloat(jQuery("#going").val());

  // Price the staircase the customer actually selected.
  //
  // #risers is a dropdown of the riser counts valid for this height and going
  // under the active building-regs regime (BuilderUtils.getStaircaseConfig). The
  // drawing, the measurements, the angle and the quote PDF have all followed it
  // for releases -- this file did not. It derived its own count from height and
  // going and never read the selection, so a staircase could gain five risers,
  // five treads and 1200mm of stringer without the quote moving by a penny, and
  // the PDF would print "Risers: 18" beside a price computed for 13.
  //
  // The flight scripts were brought onto the dropdown in an earlier release --
  // see the comment at the top of straightFlight.js, which already states that
  // the angle, PRICE and canvas should describe the same staircase. This is the
  // call site that was missed then.
  //
  // formValues.risers and NOT formValues.treads: straightFlight.js adds a tread
  // when the regs cap on riser height is exceeded (treads = risers + modifier),
  // so on a straight flight the two counts differ by one. Stringer and riser
  // material scale with risers, which is what this figure is for.
  const $selectedRisers = parseFloat(formValues.risers);
  // Fallback only for an empty or unset dropdown -- never the primary path. Same
  // legacy estimate straightFlight.js keeps for the same reason; 0.90040404 is
  // an undocumented adjustment factor shared by all the flight scripts.
  const $risers = ( Number.isFinite($selectedRisers) && $selectedRisers > 0 )
    ? $selectedRisers
    : Math.ceil( parseFloat($height / 0.90040404) / $going );

  const $str_price = ($stringer_price + $tread_price + $tread_profile_price + $riser_price) * $risers;

  // === Delivery & Optional Extras ===
  // Falls back to 0 when the Packaging & Delivery section is disabled (no .deliv_btn present).
  const $delivery = parseFloat(jQuery('.deliv_btn').attr('data-price')) || 0;
  if (jQuery('#duodeliv').is(':checked')) $duodeliv = parseFloat(jQuery('#duodeliv').val());
  if (jQuery('#fixkit').is(':checked')) $fixkit = parseFloat(jQuery('#fixkit').val());
  if (jQuery('#asspkg').is(':checked')) $asspkg = parseFloat(jQuery('#asspkg').val());
  if (jQuery('#xtrap').is(':checked')) $xtrap = parseFloat(jQuery('#xtrap').val());

  // === Width price jumps (both admin-configured; they STACK — they price
  // different things). Strictly-greater-than on both, deliberately the same
  // comparison. ===
  const $bdCons = (window.stairBuilderVars && stairBuilderVars.construction) || {};
  // Jump 1: the long-standing Extra Wide Multiplier on the per-riser material
  // cost. Threshold configurable since v2.34.0; falls back to the historic
  // hardcoded 1000. NOTE (long-standing behaviour, deliberately unchanged
  // here): this tests FLIGHT 1's width only.
  const $wmThreshold = parseFloat($bdCons.extra_wide_multiplier_threshold_mm) || 1000;
  let $width_price = 0;
  if ($width > $wmThreshold) $width_price = parseFloat($str_price) * $wmp;
  // Jump 2: fixed surcharge when ANY genuine flight is wider than its own
  // threshold — once per staircase however many flights qualify (a tread that
  // wide is joined from two boards). The landing depth on a half turn with no
  // middle flight is excluded — bdGenuineFlightWidths owns that rule.
  const $wide_surcharge = bdWideFlightSurcharge();

  if ($risers < 7) $setup_fee = parseFloat(jQuery('#setupfee').val());

  // === Newels/Caps/Spindles/Handrails/Baserails ===
  // The newel-posts value carries only the OPTIONAL posts the customer selected.
  // Every turn also structurally includes one mandatory box-corner post per box
  // that joins two flights (straight 0, quarter 1, half 2) — drawn by default on
  // the canvas. Add those so the price matches the drawing and the PDF quote
  // (see the same +mandatory logic in templates/stairbuilder_pdf.php).
  const $stairType = jQuery('input[name="stair_type"]').val() || 'straight';
  const $mandatoryPosts = $stairType === 'half' ? 2 : ($stairType === 'quarter' ? 1 : 0);
  const $newel_amt = BuilderUtils.getNumber('newel-posts') + $mandatoryPosts;
  const $newel_cost = BuilderUtils.getNumber('newel_material');
  const $caps = BuilderUtils.getNumber('newel_cap');
  const $cap_cost = $caps * BuilderUtils.getNumber('cap_material');
  const $spindle_cost = BuilderUtils.getNumber('bal_material');
  // Balustrade material mode + glass basis ride on the selected #bal_material
  // option's data-attrs (set server-side by getPriceAndID). Wood (or no attr)
  // leaves the existing per-tread spindle count untouched.
  const $balOpt = jQuery('#bal_material option:selected');
  const spMode = ($balOpt.attr('data-material-mode') || 'wood').toLowerCase();
  const spGlassUnit = $balOpt.attr('data-pricing-unit') || 'per_metre';
  const spPanelW = parseFloat($balOpt.attr('data-panel-width')) || 0;
  const spPanelGap = parseFloat($balOpt.attr('data-panel-gap')) || 0;
  const $hdr_cost = BuilderUtils.getNumber('hdr_material');
  const $bsr_cost = BuilderUtils.getNumber('bsr_material');
  // Featured step: one combined subtotal, computed server-side so the
  // both-sides uplift (§5.1) is applied in exactly one place. The per-side
  // figures stay available for the works order and diagnostics.
  const $featStepTotal = parseFloat(jQuery('#featStepTotal').val()) || 0;
  // T&G landing boards — half:landing only, additive. Since BRIEF-03
  // (v2.35.0) the charge is TWO set prices keyed on the selected tread
  // material CODE: an oak_codes match takes the Oak price, EVERYTHING ELSE
  // takes the MDF/Pine price — so a material added later gets the standard
  // price rather than silently nothing. Gated on the customer's "T&G landing
  // included" selection; every other config renders no #tandg_landing select,
  // so this contributes nothing there. Additive means the landing is still
  // inside the base price: "not included" quotes the SAME total as before the
  // select existed, and "included" quotes that plus the charge. Whether SPD
  // reduce the base to compensate is their commercial decision.
  const $tandgLanding = bdTandgLandingPrice();

  const $newels_price = $newel_cost * $newel_amt;
  const $caps_price = $cap_cost * $newel_amt;

  // === Spindle/Handrail/Baserail Logic ===
  let $spindle_price = 0,
    $hdr_price = 0,
    $bsr_price = 0,
    $ball_price = 0,
    $spindleCount = 0; // captured into #spindle-count for the quote PDF

  if ($qtBefore) {
    // Multi-flight (quarter/half-turn) logic
    let totalSpindles = 0;
    const flight1Spindles = $qtBefore * 2 * (rightBal + leftBal);
    const flight2Spindles = $qtAfter * 2 * (rightBal2 + leftBal2);
    let flight3Spindles = 0,
      section3Length = 0;

    const rakeDivided = $rake / (treads - 1);

    if ($htAfter2) {
      flight3Spindles = $htAfter2 * 2 * (rightBal3 + leftBal3);
      // Flight 3's rail length takes FLIGHT 3's balustrade sides. It read bal2
      // until v2.30.0 -- the single break in this file's otherwise exact 1/2/3
      // parallel, and the only use of rightBal2/leftBal2 that was not about
      // flight 2. SPD confirmed the sides are a per-flight property (per side,
      // in fact: a wall at any edge removes the need for balustrading there), so
      // borrowing flight 2's count was never defensible.
      //
      // It does NOT correct in one direction. Where flight 3 is railed on more
      // sides than flight 2 the quote rises; where flight 3 has no balustrade of
      // its own but the landing does, it falls.
      section3Length = $htAfter2 * rakeDivided * (rightBal3 + leftBal3);
    }

    // Landing rails vs turn rails.
    //
    // With NO middle flight the landing is three separately priced runs (SPD,
    // 8 September 2026), and they are measured off the landing rather than off
    // flight 1's width:
    //
    //   outer run   = flight 1 width + flight 2 width. The 58mm spacer is NOT
    //                 added: it cancels against the newel post at each end of
    //                 the run. Counted ONCE -- turntop and turn2top are both set
    //                 to the same landingOuter flag in halfTurn.js, so adding
    //                 both would bill the run twice.
    //   each side   = the landing depth, #stair-width2, undeducted.
    //
    // Before this, boxBal3/boxBal4 added spindles but NO length at all, so half
    // the landing rail was drawn and spindled and never measured; and every
    // landing figure was taken as one flight-1 width regardless of the landing's
    // actual size.
    //
    // With a real middle flight nothing changes: the turn rails keep their
    // existing flight-1-width treatment.
    const $landingRails = ( $stairType === 'half' && $qtAfter === 0 );
    let activeBoxWidth = 0;
    let boxSpindles = 0;
    if ($landingRails) {
      const $w3 = parseFloat(jQuery('#stair-width3').val()) || $width;
      const $depth = parseFloat(jQuery('#stair-width2').val()) || $width;
      const $outer = $width + $w3;
      if (boxBal1) { activeBoxWidth += $outer; boxSpindles += Math.ceil($outer / 112); }
      if (boxBal2) { activeBoxWidth += $depth; boxSpindles += Math.ceil($depth / 112); }
      if (boxBal4) { activeBoxWidth += $depth; boxSpindles += Math.ceil($depth / 112); }
    } else {
      boxSpindles = $boxSpindleNo * (boxBal1 + boxBal2 + boxBal3 + boxBal4);
    }
    totalSpindles = parseInt(flight1Spindles + flight2Spindles + flight3Spindles + boxSpindles);
    $spindle_price = totalSpindles * $spindle_cost;
    $spindleCount = totalSpindles; // wood; overridden below for metal/glass

    // Handrail/baserail per "length"
    const section1Length = $qtBefore * rakeDivided * (rightBal + leftBal);
    const section2Length = $qtAfter * rakeDivided * (rightBal2 + leftBal2);

    if (!$landingRails) {
      if (boxBal1) activeBoxWidth += $width;
      if (boxBal2) activeBoxWidth += $width;
    }

    const totalLength = section1Length + section2Length + section3Length + activeBoxWidth;
    const totalUnits = Math.ceil(totalLength / 1000);
    $hdr_price = $hdr_cost * totalUnits;
    $bsr_price = $bsr_cost * totalUnits;
    $ball_price = $hdr_price + $bsr_price;

    // Metal/glass override the wood spindle count for the rake sections (÷141) and
    // box/landing run (÷112); glass prices the full run per-metre/per-panel.
    if (spMode === 'metal' || spMode === 'glass') {
      const rakePerStep = 240 / Math.cos(42 * Math.PI / 180);
      const stepsSides =
        ($qtBefore * (rightBal + leftBal)) +
        ($qtAfter * (rightBal2 + leftBal2)) +
        ($htAfter2 ? $htAfter2 * (rightBal3 + leftBal3) : 0);
      const altT = altBalustradePrice(spMode, {
        unitCost: $spindle_cost,
        glassUnit: spGlassUnit,
        panelWidth: spPanelW,
        panelGap: spPanelGap,
        runStairs: rakePerStep * stepsSides,
        runLanding: activeBoxWidth,
        glassRun: totalLength,
      });
      $spindle_price = altT.price;
      $spindleCount = altT.count;
    }
  } else {
    // Simple/straight logic
    const $spindles_needed = ($risers * 2) * ($spLmod + $spRmod);
    $spindle_price = $spindles_needed * $spindle_cost;
    $spindleCount = $spindles_needed; // wood; overridden below for metal/glass

    const $hdrUnits = Math.ceil($rake / 1000);
    $hdr_price = $hdr_cost * $hdrUnits;
    $bsr_price = $bsr_cost * $hdrUnits;

    $ball_price = ($hdr_price + $bsr_price) * ($spLmod + $spRmod);

    // Metal/glass override the wood spindle count: rake length ÷141 (240/42°
    // assumption) per balustraded side; glass prices the run per-metre/per-panel.
    if (spMode === 'metal' || spMode === 'glass') {
      const sides = $spLmod + $spRmod;
      const rakePerStep = 240 / Math.cos(42 * Math.PI / 180);
      const altS = altBalustradePrice(spMode, {
        unitCost: $spindle_cost,
        glassUnit: spGlassUnit,
        panelWidth: spPanelW,
        panelGap: spPanelGap,
        runStairs: rakePerStep * treads * sides,
        runLanding: 0,
        glassRun: parseFloat($rake) * sides,
      });
      $spindle_price = altS.price;
      $spindleCount = altS.count;
    }
  }

  // === Total Calculation ===
  let $total =
    $setup_fee +
    $str_price +
    $ctype +
    $width_price +
    $wide_surcharge +
    $newels_price +
    $caps_price +
    $spindle_price +
    $ball_price +
    $duodeliv +
    $fixkit +
    $xtrap +
    $asspkg +
    $delivery +
    $featStepTotal +
    $tandgLanding +
    $addprice;

  const price = parseFloat($total); // before VAT
  const vatAmount = parseFloat(price * ($vatRate / 100));
  const priceWithVat = parseFloat(price + vatAmount);

  // === UI Update ===
  const { selectedRiserHeight, numberOfStairs, lowestStairNumber } = getStaircaseConfig($going, $height);

  jQuery("#floor").text($height + ' mm');
  jQuery("#tread").text($going + ' mm');
  jQuery("#rise").text(selectedRiserHeight + ' mm');
  jQuery("#scwidth").text($width + ' mm');
  jQuery("#angl").html($pitch.toFixed(2) + ' &deg;');

  // Keep the computed figures regardless of what's displayed. Under POA the
  // panel shows no numbers, so this is the only place the submit can read them
  // from — the lead still records an internal baseline for whoever prices it.
  window.bdComputedPrice = { price: price, vat: vatAmount, total: priceWithVat };

  if (bdPoa) {
    bdApplyPoaDisplay();
  } else {
    jQuery("#priceCalc").text('£' + price.toFixed(2));
    jQuery("#vat").text('£' + vatAmount.toFixed(2));
    jQuery("#total").text('£' + priceWithVat.toFixed(2));
  }

  // Capture the computed counts so the quote PDF shows exactly what was priced
  // (both are plain integers, so the submit-time colon strip leaves them intact).
  jQuery('#newel-count').val($newel_amt);
  jQuery('#spindle-count').val($spindleCount);
  // Applied wide-flight surcharge (0 when it didn't fire), POSTed with the
  // lead so admin can see why a quote jumped. Like the multiplier, it is
  // folded into the total on every customer surface, not itemised.
  jQuery('#wide-flight-surcharge').val($wide_surcharge);
  // Applied T&G landing charge, same idea (0 off half:landing or when the
  // customer chose "Landing not included"). The PDF states WHAT is included
  // via the tandg_landing enum; this records what it cost.
  jQuery('#tandg-landing-price').val($tandgLanding);
}

// Auto-recalculate on form input change
jQuery('#stairbuild :input').change(function () {
  calculateTotalPrice();
});