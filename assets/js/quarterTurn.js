// Hide featured steps on load
jQuery('#left-featured-step').hide();
jQuery('#right-featured-step').hide();

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
  let beforeturn = parseFloat(jQuery("#treadbt").val());
  let afterturn1 = parseInt(risers - beforeturn - tits);
  jQuery("#treadat").val(afterturn1); // auto-update hidden field
  let treads = risers;
  let riserh = Math.ceil(height / treads);
  let total_run = going * treads;
  let rake = BuilderUtils.calculateRake(height, total_run).toFixed(2);
  let pitch = BuilderUtils.calculatePitch(height, total_run);

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
  const variables = grabFormValues();
  const { going, height } = variables;
  const { selectedRiserHeight, numberOfStairs, lowestStairNumber } = getStaircaseConfig(going, height);
  let RiserNo = numberOfStairs;
  if (changedElement) {
    if (changedElement === 'floor-height' || changedElement === 'going') {
      RiserNo = lowestStairNumber;
    } else if (changedElement === 'risers') {
      RiserNo = numberOfStairs;
    }
  }
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
      maxAmount: variables.afterturn1,
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
  jQuery('#going').val(grabFormValues().going);
  jQuery('#stair-width').val(800);
  jQuery('#stair-width2').val(800);
  jQuery('#custom').hide();

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