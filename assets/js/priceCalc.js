// Metal/glass balustrade pricing. Wood spindles never call this — their per-tread
// count is left exactly as it was. Counts/lengths come pre-computed from the caller.
//   metal: count = ceil(stairRun / 141) + ceil(landingRun / 112), min 1, × unit price.
//          stairRun uses the 240mm-going / 42°-rake assumption so the configurator
//          agrees with the live spindle-calculator product page (divisors 141/112).
//   glass: per linear metre  -> (run / 1000) × price
//          per panel         -> ceil(run / panelWidth) × price
function altBalustradePrice(mode, g) {
  if (mode === 'metal') {
    let n = Math.ceil(g.runStairs / 141);
    if (g.runLanding > 0) n += Math.ceil(g.runLanding / 112);
    return Math.max(1, n) * g.unitCost;
  }
  // glass
  if (g.glassUnit === 'per_panel') {
    // Panels don't butt together — the effective pitch is panel width + gap.
    const pitch = g.panelWidth + (g.panelGap || 0);
    const panels = pitch > 0 ? Math.ceil(g.glassRun / pitch) : 0;
    return panels * g.unitCost;
  }
  return (g.glassRun / 1000) * g.unitCost;
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
  const $adj = parseFloat($height / 0.90040404);
  const $going = parseFloat(jQuery("#going").val());
  const $risers = Math.ceil($adj / $going);
  const $str_price = ($stringer_price + $tread_price + $tread_profile_price + $riser_price) * $risers;

  // === Delivery & Optional Extras ===
  // Falls back to 0 when the Packaging & Delivery section is disabled (no .deliv_btn present).
  const $delivery = parseFloat(jQuery('.deliv_btn').attr('data-price')) || 0;
  if (jQuery('#duodeliv').is(':checked')) $duodeliv = parseFloat(jQuery('#duodeliv').val());
  if (jQuery('#fixkit').is(':checked')) $fixkit = parseFloat(jQuery('#fixkit').val());
  if (jQuery('#asspkg').is(':checked')) $asspkg = parseFloat(jQuery('#asspkg').val());
  if (jQuery('#xtrap').is(':checked')) $xtrap = parseFloat(jQuery('#xtrap').val());

  let $width_price = 0;
  if ($width > 1000) $width_price = parseFloat($str_price) * $wmp;

  if ($risers < 7) $setup_fee = parseFloat(jQuery('#setupfee').val());

  // === Newels/Caps/Spindles/Handrails/Baserails ===
  const $newel_amt = BuilderUtils.getNumber('newel-posts');
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
  const $leftFeatStep = parseFloat(jQuery('#leftFeatStep').val());
  const $rightFeatStep = parseFloat(jQuery('#rightFeatStep').val());

  const $newels_price = $newel_cost * $newel_amt;
  const $caps_price = $cap_cost * $newel_amt;

  // === Spindle/Handrail/Baserail Logic ===
  let $spindle_price = 0,
    $hdr_price = 0,
    $bsr_price = 0,
    $ball_price = 0;

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
      section3Length = $htAfter2 * rakeDivided * (rightBal2 + leftBal2);
    }

    const boxSpindles = $boxSpindleNo * (boxBal1 + boxBal2 + boxBal3 + boxBal4);
    totalSpindles = parseInt(flight1Spindles + flight2Spindles + flight3Spindles + boxSpindles);
    $spindle_price = totalSpindles * $spindle_cost;

    // Handrail/baserail per "length"
    const section1Length = $qtBefore * rakeDivided * (rightBal + leftBal);
    const section2Length = $qtAfter * rakeDivided * (rightBal2 + leftBal2);

    let activeBoxWidth = 0;
    if (boxBal1) activeBoxWidth += $width;
    if (boxBal2) activeBoxWidth += $width;

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
      $spindle_price = altBalustradePrice(spMode, {
        unitCost: $spindle_cost,
        glassUnit: spGlassUnit,
        panelWidth: spPanelW,
        panelGap: spPanelGap,
        runStairs: rakePerStep * stepsSides,
        runLanding: activeBoxWidth,
        glassRun: totalLength,
      });
    }
  } else {
    // Simple/straight logic
    const $spindles_needed = ($risers * 2) * ($spLmod + $spRmod);
    $spindle_price = $spindles_needed * $spindle_cost;

    const $hdrUnits = Math.ceil($rake / 1000);
    $hdr_price = $hdr_cost * $hdrUnits;
    $bsr_price = $bsr_cost * $hdrUnits;

    $ball_price = ($hdr_price + $bsr_price) * ($spLmod + $spRmod);

    // Metal/glass override the wood spindle count: rake length ÷141 (240/42°
    // assumption) per balustraded side; glass prices the run per-metre/per-panel.
    if (spMode === 'metal' || spMode === 'glass') {
      const sides = $spLmod + $spRmod;
      const rakePerStep = 240 / Math.cos(42 * Math.PI / 180);
      $spindle_price = altBalustradePrice(spMode, {
        unitCost: $spindle_cost,
        glassUnit: spGlassUnit,
        panelWidth: spPanelW,
        panelGap: spPanelGap,
        runStairs: rakePerStep * treads * sides,
        runLanding: 0,
        glassRun: parseFloat($rake) * sides,
      });
    }
  }

  // === Total Calculation ===
  let $total =
    $setup_fee +
    $str_price +
    $ctype +
    $width_price +
    $newels_price +
    $caps_price +
    $spindle_price +
    $ball_price +
    $duodeliv +
    $fixkit +
    $xtrap +
    $asspkg +
    $delivery +
    $leftFeatStep +
    $rightFeatStep +
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

  jQuery("#priceCalc").text('£' + price.toFixed(2));
  jQuery("#vat").text('£' + vatAmount.toFixed(2));
  jQuery("#total").text('£' + priceWithVat.toFixed(2));
}

// Auto-recalculate on form input change
jQuery('#stairbuild :input').change(function () {
  calculateTotalPrice();
});