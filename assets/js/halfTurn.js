const toggleButton = document.getElementById("goto3d");
let is3DLoaded = false;
let is3DView = false;
let simulator;
let wasFour = false;

// Hide featured steps on load
jQuery('#left-featured-step').hide();
jQuery('#right-featured-step').hide();

/**
 * Extracts and returns all variables for half-turn staircases.
 */
function grabFormValues() {
  let tits = parseFloat(jQuery("#treadit").val());
  let tits2 = parseFloat(jQuery("#treadit2").val());
  let beforeturn = parseFloat(jQuery("#treadbt").val());
  let afterturn1 = parseFloat(jQuery("#treadat").val());
  if (parseFloat(jQuery("#treadit").val()) === 4) {
    tits = 1;
    tits2 = 1;
    afterturn1 = 0;
    wasFour = true;
  }
  let going = parseFloat(jQuery("#going").val()) || 240;
  let floor_h = jQuery("#floor-height").val();
  let direction = jQuery("#sc-direction").val();
  let nposts = BuilderUtils.getString("newel-posts");
  let spinglass = jQuery('input[name="ballustrades"]:checked').val();
  let height = parseFloat((floor_h || '0').replace(/,/g, ''));
  let adj = height / 0.90040404;
  let width = parseFloat(jQuery("#stair-width").val()) || 800;
  let width2 = parseFloat(jQuery("#stair-width2").val()) || 800;
  let width3 = parseFloat(jQuery("#stair-width3").val()) || 800;
  let modifier = 0;
  let risers = parseFloat(jQuery("#risers").val()) || 14;
  let treads = risers;
  let afterturn2 = parseInt(treads - (tits + tits2) - beforeturn - afterturn1);
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

  // Newel post/feature logic (refactored for clarity)
  let tl = false, tr = false, br = false, bl = false;
  let boxcorner = false, f2bo = false, f1to = false, f2to = false, boxcorner2 = false, f3bo = false;
  let spLmod = 0, spRmod = 0;
  let bal_l = false, bal_r = false, bal2_l = false, bal2_r = false, bal3_l = false, bal3_r = false;
  let turntop = false, turnside = false, turn2top = false, turn2side = false;
  let newel_style = (jQuery("#newel_type").val() || '').toUpperCase();
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
    }
  } else {
    jQuery('#custom').hide();
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

// Use the shared 3D value extractor
BuilderUtils.grab3dValues();

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
  const variables = grabFormValues();
  const { going, height } = variables;
  const { selectedRiserHeight, numberOfStairs, lowestStairNumber } = BuilderUtils.getStaircaseConfig(going, height);
  let RiserNo = numberOfStairs;
  if (changedElement) {
    if (changedElement === 'floor-height' || changedElement === 'going') {
      RiserNo = lowestStairNumber;
    } else if (changedElement === 'risers') {
      RiserNo = numberOfStairs;
    }
  }
  jQuery('#going').css({ color: (going < 220 || going > 250) ? 'red' : 'inherit' });
  const halfturnStairsConfig = {
    type: 'halfturn',
    direction: variables.direction,
    backgroundColor: 'transparent',
    font: 'Varela Round',
    treadHeight: going,
    flight1Treads: {
      amount: variables.beforeturn,
      width: variables.width,
      fillColor: acf_colours.treads_fill,
      strokeColor: acf_colours.treads_outline,
      textColor: acf_colours.treads_text
    },
    turn1TreadsAmount: variables.tits,
    flight2Treads: {
      maxAmount: 6,
      amount: variables.afterturn1,
      width: variables.width2,
      fillColor: acf_colours.treads_fill,
      strokeColor: acf_colours.treads_outline,
      textColor: acf_colours.treads_text
    },
    turn2TreadsAmount: variables.tits2,
    flight3Treads: {
      amount: variables.afterturn2,
      width: variables.width3,
      fillColor: acf_colours.treads_fill,
      strokeColor: acf_colours.treads_outline,
      textColor: acf_colours.treads_text
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
      fillColor: acf_colours.posts_fill,
      strokeColor: acf_colours.posts_outline,
      textColor: acf_colours.posts_text
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
  Stairs.init(canvas, halfturnStairsConfig);
}

// 3D DRAW FUNCTION
function drawHalfTurn() {
  const variables = grabFormValues();
  const variables3d = grab3dValues();
  jQuery("#risers").val(variables.treads);
  const going = variables.going;
  const directiona = variables.direction ? variables.direction.toUpperCase() : '';
  simulator.drawHalfTurn({
    stair_width: variables.width,
    floor_height: variables.height,
    stair_going: going,
    stair_riser: variables.riserh,
    direction: directiona,
    section1: {
      num: variables.beforeturn,
      width: variables.width,
      caps: {
        type: variables.newel_cap,
        material: variables3d.cap_material,
        direction: { left: variables.bl, right: variables.br }
      },
      post: {
        type: variables.newel_style,
        material: variables3d.post_material,
        direction: { left: variables.bl, right: variables.br }
      },
      handrails: {
        type: variables.spin_style,
        material: variables3d.spin_material,
        baseMaterial: variables3d.stringer_material,
        direction: { left: variables.bal_l, right: variables.bal_r }
      }
    },
    section2: {
      num: variables.afterturn1,
      width: variables.width2,
      caps: {
        type: variables.newel_cap,
        material: variables3d.cap_material,
        direction: { left: variables.f2bo, right: variables.f2to }
      },
      post: {
        type: variables.newel_style,
        material: variables3d.post_material,
        direction: { left: true, right: variables.f2to }
      },
      handrails: {
        type: variables.spin_style,
        material: variables3d.spin_material,
        baseMaterial: variables3d.stringer_material,
        direction: { left: variables.bal2_l, right: variables.bal2_r }
      }
    },
    section3: {
      num: variables.afterturn2,
      width: variables.width3,
      caps: {
        type: variables.newel_cap,
        material: variables3d.cap_material,
        direction: { left: variables.tl, right: variables.tr }
      },
      post: {
        type: variables.newel_style,
        material: variables3d.post_material,
        direction: { left: variables.tl, right: variables.tr }
      },
      handrails: {
        type: variables.spin_style,
        material: variables3d.spin_material,
        baseMaterial: variables3d.stringer_material,
        direction: { left: variables.bal3_l, right: variables.bal3_r }
      }
    },
    box1: {
      tnum: variables.tits,
      post: {
        direction: {
          bottom: variables.f1to,
          top: variables.f2bo,
          corner: variables.boxcorner
        }
      },
      handrails: {
        type: variables.spin_style,
        material: variables3d.spin_material,
        baseMaterial: variables3d.stringer_material,
        direction: { left: variables.turntop, right: variables.turnside }
      }
    },
    box2: {
      tnum: variables.tits2,
      post: {
        direction: {
          bottom: variables.f2to,
          top: variables.f3bo,
          corner: variables.boxcorner2
        }
      },
      handrails: {
        direction: { left: variables.turn2top, right: variables.turn2side }
      }
    },
    construct: variables3d.construction_type,
    feature: variables3d.featured_step
  });
}

// UI and event handling
jQuery(document).ready(function () {
  jQuery('#floor-height').val(2600);
  jQuery('#going').val(grabFormValues().going);
  jQuery('#stair-width').val(800);
  jQuery('#stair-width2').val(800);
  jQuery('#stair-width3').val(800);
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

        simulator = new STAIR.StairSimulator(canvas3D);
        simulator.on('started', () => drawHalfTurn());
        simulator.setSize(window.innerWidth, window.innerHeight);

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
    jQuery("#treadat2").val(grabFormValues().afterturn2);
    if (!is3DLoaded) {
      onLoad(changedElement);
    } else {
      drawHalfTurn();
    }
  });
});