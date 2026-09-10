let wasFour = false;

// First-load guard: true once the flight treads have been seeded with derived
// defaults. Before that we distribute the budget evenly; afterwards we preserve
// the user's values and only clamp what doesn't fit.
let flightsInitialised = false;

/**
 * Derives / clamps the three straight flights of a half-turn staircase and
 * writes the corrected values back into the tread inputs. Half Landing
 * (treadit === 4) pins the middle flight to 0 and distributes only across
 * flights 1 and 3. Returns the tread counts actually used.
 */
function allocateHalfTurnFlights(budget, tits, tits2, isHalfLanding) {
  const w1 = parseInt(tits, 10) || 0;
  const w2 = parseInt(tits2, 10) || 0;
  // A landing IS a tread structurally -- a landing board rather than a tread
  // board, but it occupies a tread's place and needs its riser -- so each turn
  // rightly takes its treads out of the budget.
  //
  // A HALF LANDING is the exception: it is two landings at the SAME height
  // joined into one, so it occupies ONE tread, not two. A double quarter
  // landing is two landings one riser apart and correctly consumes both. Both
  // arrive here as tits = 1, tits2 = 1 after the remap in grabFormValues(),
  // which is precisely why they cannot be told apart from those values alone
  // and this flag has to say which is which.
  //
  // The remap itself is left as it is: tits/tits2 are also handed to the
  // renderer as turn1TreadsAmount / turn2TreadsAmount, so changing them there
  // would redraw the turns. Only the budget arithmetic is corrected.
  //
  // ONE figure for both paths below. allocateFlightTreads() recomputes its own
  // available from the winders array it is given, so the first-load
  // distribution and the re-allocation will silently disagree unless they are
  // handed the same number -- which is exactly what went wrong first time.
  const turnTreads = isHalfLanding ? Math.max(0, w1 + w2 - 1) : (w1 + w2);
  const available = budget - turnTreads;

  const invalidMsg =
    'This staircase has too few risers for the selected turns — increase the floor height or reduce the turns.';
  const clampMsg = 'Only ' + Math.max(0, available) + ' treads available across the flights — values adjusted.';

  if (isHalfLanding) {
    if (!flightsInitialised) {
      const [f1, f3] = BuilderUtils.distributeFlightTreads(available, 2);
      flightsInitialised = true;
      writeHalfInputs(f1, 0, f3);
      window.bdFlightInvalid = false;
      BuilderUtils.setFlightAllocationWarning('treadat2', '');
      return { beforeturn: f1, afterturn1: 0, afterturn2: f3 };
    }
    const res = BuilderUtils.allocateFlightTreads(
      budget, [turnTreads], [jQuery('#treadbt').val()],
      [BuilderUtils.MIN_FLIGHT_FIRST, BuilderUtils.MIN_FLIGHT_LAST]
    );
    if (!res.valid) {
      window.bdFlightInvalid = true;
      jQuery('#treadat').val(0);
      jQuery('#treadat2').val(0);
      BuilderUtils.setFlightAllocationWarning('treadat2', invalidMsg);
      return { beforeturn: parseInt(jQuery('#treadbt').val(), 10) || 0, afterturn1: 0, afterturn2: 0 };
    }
    const [f1, f3] = res.flights;
    writeHalfInputs(f1, 0, f3);
    window.bdFlightInvalid = false;
    BuilderUtils.setFlightAllocationWarning('treadat2', res.clamped ? clampMsg : '');
    return { beforeturn: f1, afterturn1: 0, afterturn2: f3 };
  }

  // Normal winders: three straight flights.
  if (!flightsInitialised) {
    const [f1, f2, f3] = BuilderUtils.distributeFlightTreads(available, 3);
    flightsInitialised = true;
    writeHalfInputs(f1, f2, f3);
    window.bdFlightInvalid = false;
    BuilderUtils.setFlightAllocationWarning('treadat2', '');
    return { beforeturn: f1, afterturn1: f2, afterturn2: f3 };
  }
  const res = BuilderUtils.allocateFlightTreads(
    budget, [w1, w2], [jQuery('#treadbt').val(), jQuery('#treadat').val()],
    [BuilderUtils.MIN_FLIGHT_FIRST, BuilderUtils.MIN_FLIGHT_MID, BuilderUtils.MIN_FLIGHT_LAST]
  );
  if (!res.valid) {
    window.bdFlightInvalid = true;
    jQuery('#treadat2').val(0);
    BuilderUtils.setFlightAllocationWarning('treadat2', invalidMsg);
    return {
      beforeturn: parseInt(jQuery('#treadbt').val(), 10) || 0,
      afterturn1: parseInt(jQuery('#treadat').val(), 10) || 0,
      afterturn2: 0
    };
  }
  const [f1, f2, f3] = res.flights;
  writeHalfInputs(f1, f2, f3);
  window.bdFlightInvalid = false;
  BuilderUtils.setFlightAllocationWarning('treadat2', res.clamped ? clampMsg : '');
  return { beforeturn: f1, afterturn1: f2, afterturn2: f3 };
}

function writeHalfInputs(f1, f2, f3) {
  jQuery('#treadbt').val(f1);
  jQuery('#treadat').val(f2);
  jQuery('#treadat2').val(f3);
}

/**
 * Extracts and returns all variables for half-turn staircases.
 */
function grabFormValues() {
  const titsRaw = parseFloat(jQuery("#treadit").val());
  let tits = titsRaw;
  let tits2 = parseFloat(jQuery("#treadit2").val());
  const isHalfLanding = (titsRaw === 4);
  if (isHalfLanding) {
    tits = 1;
    tits2 = 1;
    wasFour = true;
  }
  let going = parseFloat(jQuery("#going").val()) || 240;
  let floor_h = jQuery("#floor-height").val();
  let direction = jQuery("#sc-direction").val();
  let nposts = BuilderUtils.getString("newel-posts");
  let spinglass = jQuery('input[name="ballustrades"]:checked').val();
  let height = parseFloat((floor_h || '0').replace(/,/g, ''));
  // 0.90040404 is an undocumented adjustment factor, the same in all four flight
  // scripts and in builderUtils. It converts floor height into a going-based
  // estimate of the riser count. NOTHING IN THIS FILE USES adj -- the riser count
  // comes from the #risers dropdown below -- it is only passed out in the returned
  // object. Kept for callers; do not reintroduce it as a source of truth.
  let adj = height / 0.90040404;
  let width = parseFloat(jQuery("#stair-width").val()) || 800;
  let width2 = parseFloat(jQuery("#stair-width2").val()) || 800;
  let width3 = parseFloat(jQuery("#stair-width3").val()) || 800;
  let modifier = 0;
  let risers = parseFloat(jQuery("#risers").val()) || 14;
  let treads = risers;

  // Flight tread allocation: derive/clamp the straight flights so none is ever
  // negative and the derived flight 3 always fits the tread budget.
  const alloc = allocateHalfTurnFlights(treads, tits, tits2, isHalfLanding);
  let beforeturn = alloc.beforeturn;
  let afterturn1 = alloc.afterturn1;
  let afterturn2 = alloc.afterturn2;

  let riserh = Math.ceil(height / treads);
  let total_run = going * treads;
  let rake = BuilderUtils.calculateRake(height, total_run).toFixed(2);
  let pitch = BuilderUtils.calculateStepPitch(riserh, going); // Doc K per-step pitch

  // Feature tread config — read per side (v2.23.0). Values unchanged:
  // 0 none, 1 curtail, 2 bullnose, 3 DCC, 4 DCB.
  const { fl, fr } = BuilderUtils.bdFeaturedStepPair(jQuery);

  // Newel post/feature logic (refactored for clarity)
  let tl = false, tr = false, br = false, bl = false;
  let boxcorner = false, f2bo = false, f1to = false, f2to = false, boxcorner2 = false, f3bo = false;
  let spLmod = 0, spRmod = 0;
  let bal_l = false, bal_r = false, bal2_l = false, bal2_r = false, bal3_l = false, bal3_r = false;
  let turntop = false, turnside = false, turn2top = false, turn2side = false;
  let newel_style = (jQuery("#newel_type").val() || '').toUpperCase();
  let spin_style = (jQuery("#spindle_type").val() || '').toUpperCase();
  let newel_cap = (BuilderUtils.getString("newel_cap") || '').toUpperCase();

  // A staircase with no middle flight: half landing (forced), or a double winder
  // or double quarter landing where the customer set Treads After Turn to 0.
  // Read from the live input, not the shortcode config -- it changes as they type.
  const middleFlightEmpty = (parseInt(jQuery('#treadat').val(), 10) || 0) === 0;

  // #custom visibility is owned by formLogic.js (bdUpdatePostsBalUI) — the
  // shared P&B implementation. This function only READS the boxes.
  if (nposts === "custom") {
    if (jQuery("#tl-post").is(":checked")) {
      if (direction === 'left') tl = true; else tr = true;
    }
    if (jQuery("#tr-post").is(":checked")) {
      if (direction === 'left') tr = true; else tl = true;
    }
    bl = jQuery("#bl-post").is(":checked");
    br = jQuery("#br-post").is(":checked");
    f1to = jQuery("#to-post").is(":checked");
    // Turn/box logic (left/right direction split)
    if (jQuery("#box-post").is(":checked")) {
      if (direction === 'left') boxcorner = true; else f2bo = true;
    }
    if (jQuery("#bo-post").is(":checked")) {
      if (direction === 'left') f2bo = true; else boxcorner = true;
    }
    if (jQuery("#box-post2").is(":checked")) {
      if (direction === 'left') boxcorner2 = true; else f2to = true;
    }
    if (jQuery("#to-post2").is(":checked")) {
      if (direction === 'left') f2to = true; else boxcorner2 = true;
    }
    f3bo = jQuery("#bo-post2").is(":checked");
    // Balustrades
    if (spinglass === "true") {
      switch (direction) {
        case 'left':
          bal_r = f1to && br; bal_l = bl;
          bal2_r = f2bo && f2to; bal2_l = true;
          bal3_r = f3bo && tl; bal3_l = tr;
          turntop = boxcorner && f2bo; turnside = boxcorner && f1to;
          turn2top = boxcorner2 && f2to; turn2side = boxcorner2 && f3bo;
          break;
        case 'right':
          bal_l = br; bal_r = f1to && bl;
          bal2_l = true; bal2_r = boxcorner && boxcorner2;
          bal3_r = tr; bal3_l = f3bo && tl;
          turntop = boxcorner && f2bo; turnside = f1to && f2bo;
          turn2top = boxcorner2 && f2to; turn2side = f2to && f3bo;
          break;
      }

      // ---- Landing rails, when the middle flight is empty -------------------
      //
      // With no middle flight there are no posts at its ends, so #bo-post and
      // #to-post2 are not rendered (formLogic.js binds them to #treadat) and the
      // direction-mapped flags they fed are dead. The landing's three rails are
      // therefore gated on the surviving checkboxes DIRECTLY, not on those flags:
      // reading boxcorner/boxcorner2 here would work on a left-hand stair and
      // silently fail on a right-hand one, because which variable a box feeds
      // depends on the direction.
      //
      // SPD's model: every edge either has a rail or has none, so the landing is
      // three separately switchable, separately priced runs.
      //
      //   outer run  -- both box corners        (one span, not two halves)
      //   side, flight 1 end                    (#to-post)
      //   side, flight 2 end                    (#bo-post2)
      //
      // The outer run is one continuous rail across the landing. It is expressed
      // as both halves being true together, which is what the renderer draws as a
      // full-width run; there is no middle post to break it at, and none is drawn
      // now that the mid-flight boxes are gone.
      if (middleFlightEmpty) {
        const landingOuter = jQuery("#box-post").is(":checked") && jQuery("#box-post2").is(":checked");
        turntop  = landingOuter;
        turn2top = landingOuter;
        turnside  = jQuery("#to-post").is(":checked");
        turn2side = jQuery("#bo-post2").is(":checked");
      }
    }
  }

  return {
    tits, tits2, beforeturn, afterturn1, afterturn2,
    direction, going, floor_h, nposts, spinglass,
    height, adj, width, width2, width3,
    modifier, risers, treads, riserh, total_run, rake, pitch,
    tl, tr, br, bl, f1to, boxcorner, f2bo, f2to, boxcorner2, f3bo,
    spLmod, spRmod, fr, fl, bal_l, bal_r, bal2_l, bal2_r, bal3_l, bal3_r,
    turntop, turnside, turn2top, turn2side,
    newel_style, spin_style, newel_cap
  };
}

window.grabFormValues = grabFormValues; // Expose globally if needed

function bonuslogic() {
  const materials = grab3dValues();
  const string_material = materials.stringer_material;
  const tread_material = materials.tread_material;
  const riser_material = materials.riser_material;
  let no_oak_price = parseFloat(jQuery("#half_landing_no_oak").val()) || 0;
  let oak_tread_price = parseFloat(jQuery("#half_landing_oak_tread").val()) || 0;
  let oak_tread_riser_price = parseFloat(jQuery("#half_landing_oak_tr").val()) || 0;
  let oak_string_price = parseFloat(jQuery("#half_landing_oak_string").val()) || 0;
  let all_oak_price = parseFloat(jQuery("#half_landing_all_oak").val()) || 0;
  if (string_material === 'OAK' && tread_material === 'OAK' && riser_material === 'OAK') {
    return all_oak_price;
  } else if (tread_material === 'OAK' && riser_material === 'OAK') {
    return oak_tread_riser_price;
  } else if (tread_material === 'OAK') {
    return oak_tread_price;
  } else if (string_material === 'OAK') {
    return oak_string_price;
  } else {
    return no_oak_price;
  }
}

// MAIN 2D RENDER FUNCTION
function onLoad(changedElement = null) {
  // Resolve the riser dropdown FIRST so the tread budget (treads = risers) is
  // settled before grabFormValues runs the flight allocation.
  const going = parseFloat(jQuery("#going").val()) || 240;
  const height = parseFloat((jQuery("#floor-height").val() || '0').replace(/,/g, ''));
  BuilderUtils.getStaircaseConfig(going, height);

  const variables = grabFormValues();
  // Going input feedback (building-regs warning) is handled centrally in
  // formLogic.js from admin-configured Construction Settings.
  const halfturnStairsConfig = {
    type: 'halfturn',
    direction: variables.direction,
    backgroundColor: bd_diagram_colours.canvas_bg || 'transparent',
    font: 'Varela Round',
    treadHeight: going,
    flight1Treads: {
      amount: variables.beforeturn,
      width: variables.width,
      fillColor: bd_diagram_colours.treads_fill,
      strokeColor: bd_diagram_colours.treads_outline,
      textColor: bd_diagram_colours.treads_text
    },
    // Gap between the two flights when the middle flight is empty, in MILLIMETRES.
    // Two newel overhangs: each flight's stringer meets the middle of its own
    // post, so the flights are pushed apart by one overhang each. Drawing only --
    // it is never priced, and consumes no extra stringer or handrail.
    // Zero when a middle flight exists; its treads already separate the flights.
    landingSpacerMm: (function () {
      var vars = window.stairBuilderVars || {};
      var geo  = vars.geometry || {};
      var map  = geo.newel_overhang_mm || {};
      var code = (jQuery('#newel_type').val() || '').split(':')[0];
      var per  = parseFloat(map[code]);
      if (!isFinite(per)) per = parseFloat(geo.newel_overhang_default);
      if (!isFinite(per)) per = 27;
      return per * 2;
    })(),
    // Two quarter landings meeting with no flight between them -- separate
    // platforms a riser apart, so the drawing needs a boundary. titsRaw is read
    // BEFORE the half-landing remap, which rewrites both to 1 and would otherwise
    // make a half landing indistinguishable from this.
    isDoubleQuarterLanding: (parseFloat(jQuery('#treadit').val()) === 1
                             && parseFloat(jQuery('#treadit2').val()) === 1
                             && (parseInt(jQuery('#treadat').val(), 10) || 0) === 0),
    turn1TreadsAmount: variables.tits,
    flight2Treads: {
      maxAmount: 6,
      amount: variables.afterturn1,
      width: variables.width2,
      fillColor: bd_diagram_colours.treads_fill,
      strokeColor: bd_diagram_colours.treads_outline,
      textColor: bd_diagram_colours.treads_text
    },
    turn2TreadsAmount: variables.tits2,
    flight3Treads: {
      amount: variables.afterturn2,
      width: variables.width3,
      fillColor: bd_diagram_colours.treads_fill,
      strokeColor: bd_diagram_colours.treads_outline,
      textColor: bd_diagram_colours.treads_text
    },
    posts: {
      flight1BottomLeft: variables.bl,
      flight1BottomRight: variables.br,
      turn1TopLeft: variables.f2bo,
      turn1TopRight: variables.boxcorner,
      turn1Bottom: variables.f1to,
      turn2TopLeft: variables.boxcorner2,
      turn2TopRight: variables.f2to,
      turn2Bottom: variables.f3bo,
      flight3Left: variables.tl,
      flight3Right: variables.tr,
      fillColor: bd_diagram_colours.posts_fill,
      strokeColor: bd_diagram_colours.posts_outline,
      textColor: bd_diagram_colours.posts_text
    },
    ballustrades: {
      flight1Outside: variables.bal_r,
      flight1Inside: variables.bal_l,
      flight2Outside: variables.bal2_r,
      flight2Inside: variables.bal2_l,
      flight3Outside: variables.bal3_r,
      flight3Inside: variables.bal3_l,
      turn1Top: variables.turn2top,
      turn1Side: variables.turn2side,
      turn2Top: variables.turntop,
      turn2Side: variables.turnside,
      primaryFillColor: bd_diagram_colours.stringer_fill,
      secondaryFillColor: bd_diagram_colours.spindles,
      strokeColor: bd_diagram_colours.stringer_outline
    },
    featureTread: {
      left: variables.fl,
      right: variables.fr
    },
    minHeight: 220,
    maxHeight: 250,
    minWidth: 800,
    maxWidth: 1200
  };
  const canvas = document.getElementById("canvas");
  Stairs.init(canvas, halfturnStairsConfig);
}

// UI and event handling
jQuery(document).ready(function () {
  jQuery('#floor-height').val(2600);
  jQuery('#going').val(parseFloat(jQuery('#going').val()) || 240);
  jQuery('#stair-width').val(800);
  jQuery('#stair-width2').val(800);
  jQuery('#stair-width3').val(800);

  // First draw: grabFormValues (via onLoad) seeds the derived flight defaults.
  onLoad();

  jQuery('#stairbuild').on('change', ':input', function () {
    const changedElement = jQuery(this).attr('id');
    // Main width input acts as a uniform default — propagate to per-flight
    // inputs so all flights stay in sync. Individual flights can still be
    // overridden afterwards by editing #stair-width2 / #stair-width3 directly.
    if (changedElement === 'stair-width') {
      const v = jQuery(this).val();
      jQuery('#stair-width2').val(v);
      jQuery('#stair-width3').val(v);
    }
    // Flight allocation (incl. writing the derived #treadat2) now runs inside
    // grabFormValues, which onLoad calls.
    onLoad(changedElement);
  });
});