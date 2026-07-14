// First-load guard: true once the flight treads have been seeded with derived
// defaults. Before that we distribute the budget evenly; afterwards we preserve
// the user's flight 1 value and only clamp what doesn't fit.
let flightsInitialised = false;

// Hide featured steps on load
jQuery('#left-featured-step').hide();
jQuery('#right-featured-step').hide();

/**
 * Derives flight 2 (after the turn) and clamps flight 1 so neither is ever
 * negative, then writes the corrected values back into the tread inputs.
 * #treadat is the derived (readonly) field for quarter turns.
 */
function allocateQuarterTurnFlights(budget, tits) {
  const w1 = parseInt(tits, 10) || 0;
  const available = budget - w1;
  const invalidMsg =
    'This staircase has too few risers for the selected turn — increase the floor height or reduce the winders.';
  const clampMsg = 'Only ' + Math.max(0, available) + ' treads available after the turn — values adjusted.';

  if (!flightsInitialised) {
    const [f1, f2] = BuilderUtils.distributeFlightTreads(available, 2);
    flightsInitialised = true;
    jQuery('#treadbt').val(f1);
    jQuery('#treadat').val(f2);
    window.bdFlightInvalid = false;
    BuilderUtils.setFlightAllocationWarning('treadat', '');
    return { beforeturn: f1, afterturn1: f2 };
  }
  const res = BuilderUtils.allocateFlightTreads(
    budget, [w1], [jQuery('#treadbt').val()],
    [BuilderUtils.MIN_FLIGHT_FIRST, BuilderUtils.MIN_FLIGHT_LAST]
  );
  if (!res.valid) {
    window.bdFlightInvalid = true;
    jQuery('#treadat').val(0);
    BuilderUtils.setFlightAllocationWarning('treadat', invalidMsg);
    return { beforeturn: parseInt(jQuery('#treadbt').val(), 10) || 0, afterturn1: 0 };
  }
  const [f1, f2] = res.flights;
  jQuery('#treadbt').val(f1);
  jQuery('#treadat').val(f2);
  window.bdFlightInvalid = false;
  BuilderUtils.setFlightAllocationWarning('treadat', res.clamped ? clampMsg : '');
  return { beforeturn: f1, afterturn1: f2 };
}

/**
 * Extracts and returns all variables for quarter-turn staircases.
 */
function grabFormValues() {
  let tits = parseFloat(jQuery("#treadit").val());
  let going = parseFloat(jQuery("#going").val()) || 240;
  let floor_h = jQuery("#floor-height").val();
  let direction = jQuery("#sc-direction").val();
  let nposts = BuilderUtils.getString("newel-posts");
  let spinglass = jQuery('input[name="ballustrades"]:checked').val();
  let height = parseFloat((floor_h || '0').replace(/,/g, ''));
  let adj = height / 0.90040404;
  let width = parseFloat(jQuery("#stair-width").val()) || 800;
  let width2 = parseFloat(jQuery("#stair-width2").val()) || 800;
  let modifier = 0;
  let risers = parseFloat(jQuery("#risers").val()) || 14;
  let treads = risers;

  // Flight tread allocation: derive flight 2 and clamp flight 1 (never negative).
  const alloc = allocateQuarterTurnFlights(treads, tits);
  let beforeturn = alloc.beforeturn;
  let afterturn1 = alloc.afterturn1;

  let riserh = Math.ceil(height / treads);
  let total_run = going * treads;
  let rake = BuilderUtils.calculateRake(height, total_run).toFixed(2);
  let pitch = BuilderUtils.calculateStepPitch(riserh, going); // Doc K per-step pitch

  // Feature tread config
  let fl = 0, fr = 0;
  const featureTreadConfig = jQuery('#feature_tread').find('option:selected').data('config');
  if (featureTreadConfig) {
    [fl, fr] = featureTreadConfig.split(',');
  }

  // Post/feature logic
  let tl = false, tr = false, br = false, bl = false;
  let f1bc = false, boxcorner = false, f2bc = false;
  let spLmod = 0, spRmod = 0;
  let bal_l = false, bal_r = false, bal2_l = false, bal2_r = false;
  let turntop = false, turnside = false;
  let newel_style = (jQuery("#newel_type").val() || '').toUpperCase();
  let handrail_type = (jQuery("#handrail_type").val() || '').toUpperCase();
  let spin_style = (jQuery("#spindle_type").val() || '').toUpperCase();
  let newel_cap = (BuilderUtils.getString("newel_cap") || '').toUpperCase();

  if (nposts === "custom") {
    jQuery('#custom').show();
    if (jQuery("#tl-post").is(":checked")) {
      if (direction === 'left') tl = true; else tr = true;
    }
    if (jQuery("#tr-post").is(":checked")) {
      if (direction === 'left') tr = true; else tl = true;
    }
    if (jQuery("#box-post").is(":checked")) {
      if (direction === 'left') boxcorner = true; else f2bc = true;
    }
    if (jQuery("#bo-post").is(":checked")) {
      if (direction === 'left') f2bc = true; else boxcorner = true;
    }
    bl = jQuery("#bl-post").is(":checked");
    br = jQuery("#br-post").is(":checked");
    f1bc = jQuery("#to-post").is(":checked");

    if (spinglass === "true") {
      switch (direction) {
        case 'left':
          bal_r = f1bc && br; bal_l = bl;
          bal2_r = f2bc && tr; bal2_l = tl;
          turntop = boxcorner && f2bc; turnside = boxcorner && f1bc;
          break;
        case 'right':
          bal_l = br; bal_r = f1bc && bl;
          bal2_r = boxcorner && tr; bal2_l = tl;
          turntop = boxcorner && f2bc; turnside = f2bc && f1bc;
          break;
      }
    }
  } else {
    jQuery('#custom').hide();
  }

  return {
    tits, beforeturn, afterturn1, direction, going, floor_h, nposts, spinglass,
    height, adj, width, width2, modifier, risers, treads, riserh, total_run,
    rake, pitch, tl, tr, br, bl, f1bc, boxcorner, f2bc, spLmod, spRmod,
    fr, fl, bal_l, bal_r, bal2_l, bal2_r, turntop, turnside,
    newel_style, handrail_type, spin_style, newel_cap
  };
}
window.grabFormValues = grabFormValues; // Expose globally

function bonuslogic() {
  const materials = BuilderUtils.grab3dValues();
  const string_material = materials.stringer_material;
  const tread_material = materials.tread_material;
  const riser_material = materials.riser_material;
  let no_oak_price = parseFloat(jQuery("#quarter_landing_no_oak").val()) || 0;
  let oak_tread_price = parseFloat(jQuery("#quarter_landing_oak_tread").val()) || 0;
  let oak_tread_riser_price = parseFloat(jQuery("#quarter_landing_oak_tr").val()) || 0;
  let oak_string_price = parseFloat(jQuery("#quarter_landing_oak_string").val()) || 0;
  let all_oak_price = parseFloat(jQuery("#quarter_landing_all_oak").val()) || 0;

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

function getStaircaseConfig(going, height) {
  return BuilderUtils.getStaircaseConfig(going, height);
}

function onLoad(changedElement = null) {
  // Resolve the riser dropdown FIRST so the tread budget (treads = risers) is
  // settled before grabFormValues runs the flight allocation.
  const going = parseFloat(jQuery("#going").val()) || 240;
  const height = parseFloat((jQuery("#floor-height").val() || '0').replace(/,/g, ''));
  getStaircaseConfig(going, height);

  const variables = grabFormValues();
  // Going input feedback (building-regs warning) is handled centrally in
  // formLogic.js from admin-configured Construction Settings.

  const quarterturnStairsConfig = {
    type: 'quarterturn',
    direction: variables.direction,
    backgroundColor: bd_diagram_colours.canvas_bg || 'transparent',
    font: 'Varela Round',
    treadHeight: variables.going,
    flight1Treads: {
      amount: variables.beforeturn,
      width: variables.width,
      fillColor: bd_diagram_colours.treads_fill,
      strokeColor: bd_diagram_colours.treads_outline,
      textColor: bd_diagram_colours.treads_text
    },
    turnTreadsAmount: variables.tits,
    flight2Treads: {
      // Stairs.js divides by maxAmount when scaling tread height, so a legit
      // 0-tread flight after the turn would divide by zero and NaN the canvas.
      // Floor it at 1 to keep the drawing sane; proper zero-tread final-flight
      // rendering is a separate Stairs.js fix (see debrief).
      maxAmount: Math.max(variables.afterturn1, 1),
      amount: variables.afterturn1,
      width: variables.width2,
      fillColor: bd_diagram_colours.treads_fill,
      strokeColor: bd_diagram_colours.treads_outline,
      textColor: bd_diagram_colours.treads_text
    },
    posts: {
      turnTopLeft: variables.f2bc,
      turnTopRight: variables.boxcorner,
      turnBottom: variables.f1bc,
      flight1BottomLeft: variables.bl,
      flight1BottomRight: variables.br,
      flight2Top: variables.tr,
      flight2Bottom: variables.tl,
      fillColor: bd_diagram_colours.posts_fill,
      strokeColor: bd_diagram_colours.posts_outline,
      textColor: bd_diagram_colours.posts_text
    },
    ballustrades: {
      flight1Outside: variables.bal_r,
      flight1Inside: variables.bal_l,
      flight2Outside: variables.bal2_r,
      flight2Inside: variables.bal2_l,
      turnTop: variables.turntop,
      turnSide: variables.turnside,
      primaryFillColor: bd_diagram_colours.stringer_fill,
      secondaryFillColor: bd_diagram_colours.spindles,
      strokeColor: bd_diagram_colours.stringer_outline
    },
    featureTread: {
      left: variables.fl,
      right: variables.fr
    },
    minHeight: 50,
    maxHeight: 300,
    minWidth: 800,
    maxWidth: 1200
  };
  const canvas = document.getElementById("canvas");
  Stairs.init(canvas, quarterturnStairsConfig);
}

// UI/event handling
jQuery(document).ready(function () {
  jQuery('#floor-height').val(2600);
  jQuery('#going').val(parseFloat(jQuery('#going').val()) || 240);
  jQuery('#stair-width').val(800);
  jQuery('#stair-width2').val(800);
  jQuery('#custom').hide();

  // First draw: grabFormValues (via onLoad) seeds the derived flight defaults.
  onLoad();

  jQuery('#stairbuild').on('change', ':input', function () {
    const changedElement = jQuery(this).attr('id');
    // Main width input acts as a uniform default — propagate to per-flight
    // inputs so all flights stay in sync. Individual flights can still be
    // overridden afterwards by editing #stair-width2 directly.
    if (changedElement === 'stair-width') {
      jQuery('#stair-width2').val(jQuery(this).val());
    }
    onLoad(changedElement);
  });
});