// Hide featured steps on load
jQuery('#left-featured-step').hide();
jQuery('#right-featured-step').hide();

/**
 * Parses the form and returns all variables needed for this stair type.
 */
function grabFormValues() {
  // Extract main form values
  const going = parseFloat(jQuery("#going").val()) || 240;
  const floor_h = jQuery("#floor-height").val();
  const nposts = BuilderUtils.getString("newel-posts");
  const spinglass = jQuery('input[name="ballustrades"]:checked').val();
  const height = parseFloat((floor_h || '2600').replace(/,/g, ''));
  const adj = height / 0.90040404;
  const width = parseFloat(jQuery("#stair-width").val()) || 800;
  let modifier = 0;

  // Risers, treads, etc. The riser count comes from the #risers dropdown
  // (populated by getStaircaseConfig in onLoad) so the angle, price and canvas
  // all describe the SAME staircase — matching the half/quarter turn scripts.
  // The legacy going-based estimate (adj) is only a fallback for an empty
  // dropdown; on its own it ignored the user's selection and produced an angle
  // for the wrong riser count (e.g. 40.96° instead of 38.66° at 2600mm/250mm).
  const risers = parseFloat(jQuery("#risers").val()) || Math.ceil(adj / going);
  const regcheck = Math.ceil(height / risers);
  if (regcheck > 220) modifier = 1;
  const treads = risers + modifier;
  const riserh = Math.ceil(height / treads);
  const total_run = going * treads;
  const rake = BuilderUtils.calculateRake(height, total_run).toFixed(2);
  const pitch = BuilderUtils.calculateStepPitch(riserh, going); // Doc K per-step pitch

  // Newel post positions
  let tl = false, tr = false, bl = false, br = false;
  if (nposts === "left")      { tl = true; bl = true; }
  if (nposts === "right")     { tr = true; br = true; }
  if (nposts === "both")      { tl = true; bl = true; tr = true; br = true; }
  if (nposts === "custom") {
    jQuery('#custom').show();
    if (jQuery("#tl-post").is(":checked")) tl = true;
    if (jQuery("#tr-post").is(":checked")) tr = true;
    if (jQuery("#bl-post").is(":checked")) bl = true;
    if (jQuery("#br-post").is(":checked")) br = true;
  } else {
    jQuery('#custom').hide();
  }

  // Feature tread config
  let fl = 0, fr = 0;
  const featureTreadConfig = jQuery('#feature_tread').find('option:selected').data('config');
  if (featureTreadConfig) {
    [fl, fr] = featureTreadConfig.split(',');
  }

  // Balustrade modifiers
  let spLmod = 0, spRmod = 0, bal_l = false, bal_r = false;
  if (spinglass === "true") {
    if (tr && br) { bal_r = true; spRmod = 1; }
    if (tl && bl) { bal_l = true; spLmod = 1; }
  }

  // Styles
  const newel_style = (jQuery("#newel_type").val() || '').toUpperCase();
  const spin_style = (jQuery("#spindle_type").val() || '').toUpperCase();
  const newel_cap = (BuilderUtils.getString("newel_cap") || '').toUpperCase();

  // Return all relevant variables
  return {
    going, floor_h, nposts, spinglass, height, adj, width,
    topriser: width, // Unused? Keep if needed
    modifier, risers, treads, regcheck, riserh, total_run, rake, pitch,
    tl, tr, br, bl, spLmod, spRmod, fl, fr, bal_l, bal_r,
    newel_style, spin_style, newel_cap
  };
}

// Expose for other scripts if needed
window.grabFormValues = grabFormValues;

// MAIN 2D RENDER FUNCTION
function onLoad(changedElement = null) {
  // Resolve the #risers dropdown FIRST (keeps a still-valid selection, else
  // falls back to the lowest valid config) so it is the single source of truth
  // for the riser count used by grabFormValues (angle/price) and the canvas.
  const going = parseFloat(jQuery("#going").val()) || 240;
  const height = parseFloat((jQuery("#floor-height").val() || '2600').replace(/,/g, ''));
  BuilderUtils.getStaircaseConfig(going, height);

  const variables = grabFormValues();
  const { width } = variables;

  // Going input feedback (building-regs warning) is handled centrally in
  // formLogic.js from admin-configured Construction Settings.

  // Prepare config for renderer
  const regularStairsConfig = {
    type: 'regular',
    backgroundColor: bd_diagram_colours.canvas_bg || 'transparent',
    font: 'Varela Round',
    treads: {
      amount: variables.risers,
      width: width,
      height: going,
      fillColor: bd_diagram_colours.treads_fill,
      strokeColor: bd_diagram_colours.treads_outline,
      textColor: bd_diagram_colours.treads_text
    },
    posts: {
      topLeft: variables.tl,
      topRight: variables.tr,
      bottomLeft: variables.bl,
      bottomRight: variables.br,
      fillColor: bd_diagram_colours.posts_fill,
      strokeColor: bd_diagram_colours.posts_outline,
      textColor: bd_diagram_colours.posts_text
    },
    ballustrades: {
      left: variables.bal_l,
      right: variables.bal_r,
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
  Stairs.init(canvas, regularStairsConfig);
}

// INITIALISATION
jQuery(document).ready(function () {
  jQuery('#floor-height').val(2600);
  jQuery('#going').val(parseFloat(jQuery('#going').val()) || 240);
  jQuery('#stair-width').val(800);
  jQuery('#custom').hide();

  onLoad();

  jQuery('#stairbuild').on('change', ':input', function () {
    const changedElement = jQuery(this).attr('id');
    onLoad(changedElement);
  });
});