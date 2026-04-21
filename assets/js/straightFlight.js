const toggleButton = document.getElementById("goto3d");
let is3DLoaded = false;
let is3DView = false;
let simulator;

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
  const height = parseFloat((floor_h || '0').replace(/,/g, ''));
  const adj = height / 0.90040404;
  const width = parseFloat(jQuery("#stair-width").val()) || 800;
  let modifier = 0;

  // Risers, treads, etc
  const risers = Math.ceil(adj / going);
  const regcheck = Math.ceil(height / risers);
  if (regcheck > 220) modifier = 1;
  const treads = risers + modifier;
  const riserh = Math.ceil(height / treads);
  const total_run = going * treads;
  const rake = BuilderUtils.calculateRake(height, total_run).toFixed(2);
  const pitch = BuilderUtils.calculatePitch(height, total_run);

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

// Use shared 3D value extractor
BuilderUtils.grab3dValues();

// MAIN 2D RENDER FUNCTION
function onLoad(changedElement = null) {
  const variables = grabFormValues();
  const { going, height, width } = variables;

  const { selectedRiserHeight, numberOfStairs, lowestStairNumber } =
    BuilderUtils.getStaircaseConfig(going, height);

  let RiserNo = numberOfStairs;
  if (changedElement) {
    if (changedElement === 'floor-height' || changedElement === 'going') {
      RiserNo = lowestStairNumber;
    } else if (changedElement === 'risers') {
      RiserNo = numberOfStairs;
    }
  }

  // Going input feedback
  jQuery('#going').css({ color: (going < 220 || going > 250) ? 'red' : 'inherit' });

  // Prepare config for renderer
  const regularStairsConfig = {
    type: 'regular',
    backgroundColor: 'transparent',
    font: 'Varela Round',
    treads: {
      amount: RiserNo,
      width: width,
      height: going,
      fillColor: acf_colours.treads_fill,
      strokeColor: acf_colours.treads_outline,
      textColor: acf_colours.treads_text
    },
    posts: {
      topLeft: variables.tl,
      topRight: variables.tr,
      bottomLeft: variables.bl,
      bottomRight: variables.br,
      fillColor: acf_colours.posts_fill,
      strokeColor: acf_colours.posts_outline,
      textColor: acf_colours.posts_text
    },
    ballustrades: {
      left: variables.bal_l,
      right: variables.bal_r,
      primaryFillColor: acf_colours.stringer_fill,
      secondaryFillColor: acf_colours.spindles,
      strokeColor: acf_colours.stringer_outline
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

// 3D DRAW FUNCTION
function drawStraightFlight() {
  const variables = grabFormValues();
  const variables3d = grab3dValues();

  const { going, height, width } = variables;
  const { selectedRiserHeight, numberOfStairs } =
    BuilderUtils.getStaircaseConfig(going, height);

  simulator.drawStraightFlight({
    stair_width: width,
    floor_height: height,
    stair_going: going,
    stair_riser: selectedRiserHeight,
    post: {
      direction: {
        leftTop: variables.tl,
        leftBottom: variables.bl,
        rightTop: variables.tr,
        rightBottom: variables.br,
      },
      type: variables.newel_style,
      material: variables3d.post_material,
    },
    caps: {
      direction: {
        leftTop: true,
        leftBottom: true,
        rightTop: true,
        rightBottom: true,
      },
      type: variables.newel_cap,
      material: variables3d.cap_material,
    },
    handrails: {
      direction: {
        left: variables.bal_l,
        right: variables.bal_r,
      },
      type: variables.spin_style,
      material: variables3d.spin_material,
      baseMaterial: variables3d.stringer_material,
    },
    construct: variables3d.construction_type,
    feature: variables3d.featured_step
  });
}

// VIEW TOGGLING AND INITIALISATION
jQuery(document).ready(function () {
  jQuery('#floor-height').val(2600);
  jQuery('#going').val(grabFormValues().going);
  jQuery('#stair-width').val(800);
  jQuery('#custom').hide();

  if (toggleButton) {
    toggleButton.addEventListener("click", (event) => {
      event.preventDefault();
      const canvasContainer = document.getElementById("canvas-container");
      if (!canvasContainer) {
        console.warn("Canvas container not found - 3D functionality disabled");
        return;
      }

      if (!is3DLoaded) {
        const script = document.createElement("script");
        script.src = pluginDirUrl + "assets/js/stair_min.js?v=" + Date.now();
        script.onload = () => {
          is3DLoaded = true;
          toggleView();
        };
        document.body.appendChild(script);
      } else {
        toggleView();
        is3DLoaded = false;
      }
    });

    function toggleView() {
      const canvasContainer = document.getElementById("canvas-container");
      if (!canvasContainer) {
        console.warn("Canvas container not found - cannot toggle 3D view");
        return;
      }

      if (is3DView) {
        toggleButton.textContent = "Switch to 3D";
        const builderWrap = document.getElementById("builder-wrap");
        const innerWrap = builderWrap.querySelector(".ct-section-inner-wrap");
        let canvas3D = document.getElementById("canvas3D");
        if (canvas3D) canvas3D.parentNode.removeChild(canvas3D);

        let canvas = document.getElementById("canvas");
        if (!canvas) {
          canvas = document.createElement("canvas");
          canvas.id = "canvas";
          canvas.width = 558;
          canvas.height = 556;
          builderWrap.insertBefore(canvas, builderWrap.firstChild);
          canvasContainer.appendChild(canvas);
        }
        canvas.style.display = "block";
        innerWrap.style.padding = "75px auto !important";
        onLoad();
        is3DView = false;
      } else {
        toggleButton.textContent = "Switch to 2D";
        let canvas = document.getElementById("canvas");
        if (canvas) canvas.parentNode.removeChild(canvas);

        let canvas3D = document.createElement("canvas");
        canvas3D.id = "canvas3D";
        canvasContainer.appendChild(canvas3D);

        const vpWidth = 0.98 * document.documentElement.clientWidth;
        const vpHeight = 0.98 * document.documentElement.clientHeight;
        simulator = new STAIR.StairSimulator(canvas3D);
        simulator.on('started', () => drawStraightFlight());
        simulator.setSize(vpWidth, vpHeight);

        const builderWrap = document.getElementById("builder-wrap");
        const innerWrap = builderWrap.querySelector(".ct-section-inner-wrap");
        innerWrap.style.padding = "0";
        is3DView = true;
      }
    }
  }

  if (!is3DView) onLoad();

  jQuery('#stairbuild').on('change', ':input', function () {
    const changedElement = jQuery(this).attr('id');
    if (!is3DLoaded) {
      onLoad(changedElement);
    } else {
      drawStraightFlight();
    }
  });
});