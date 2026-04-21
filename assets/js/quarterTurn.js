const toggleButton = document.getElementById("goto3d");
let is3DLoaded = false;
let is3DView = false;
let simulator;

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
  jQuery('#going').css({ color: (going < 220 || going > 250) ? 'red' : 'inherit' });

  const quarterturnStairsConfig = {
    type: 'quarterturn',
    direction: variables.direction,
    backgroundColor: 'transparent',
    font: 'Varela Round',
    treadHeight: variables.going,
    flight1Treads: {
      amount: variables.beforeturn,
      width: variables.width,
      fillColor: acf_colours.treads_fill,
      strokeColor: acf_colours.treads_outline,
      textColor: acf_colours.treads_text
    },
    turnTreadsAmount: variables.tits,
    flight2Treads: {
      maxAmount: variables.afterturn1,
      amount: variables.afterturn1,
      width: variables.width2,
      fillColor: acf_colours.treads_fill,
      strokeColor: acf_colours.treads_outline,
      textColor: acf_colours.treads_text
    },
    posts: {
      turnTopLeft: variables.f2bc,
      turnTopRight: variables.boxcorner,
      turnBottom: variables.f1bc,
      flight1BottomLeft: variables.bl,
      flight1BottomRight: variables.br,
      flight2Top: variables.tr,
      flight2Bottom: variables.tl,
      fillColor: acf_colours.posts_fill,
      strokeColor: acf_colours.posts_outline,
      textColor: acf_colours.posts_text
    },
    ballustrades: {
      flight1Outside: variables.bal_r,
      flight1Inside: variables.bal_l,
      flight2Outside: variables.bal2_r,
      flight2Inside: variables.bal2_l,
      turnTop: variables.turntop,
      turnSide: variables.turnside,
      primaryFillColor: acf_colours.stringer_fill,
      secondaryFillColor: acf_colours.spindles,
      strokeColor: acf_colours.stringer_outline
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

function drawQuarterTurn() {
  const variables = grabFormValues();
  const variables3d = BuilderUtils.grab3dValues();
  const going = variables.going;
  const directiona = (variables.direction || '').toUpperCase();
  const height = variables.height;
  const { selectedRiserHeight, numberOfStairs } = getStaircaseConfig(going, height);

  simulator.drawQuarterTurn({
    stair_width: variables.width,
    floor_height: variables.height,
    stair_going: going,
    stair_riser: selectedRiserHeight,
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
        direction: { left: variables.bal2_l, right: variables.bal2_r }
      }
    },
    box: {
      tnum: variables.tits,
      post: {
        direction: {
          bottom: variables.f1bc,
          top: variables.f2bc,
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
    construct: variables3d.construction_type,
    feature: variables3d.featured_step
  });
}

// UI/event handling
jQuery(document).ready(function () {
  jQuery('#floor-height').val(2600);
  jQuery('#going').val(grabFormValues().going);
  jQuery('#stair-width').val(800);
  jQuery('#stair-width2').val(800);
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
        simulator.on('started', () => drawQuarterTurn());
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
      drawQuarterTurn();
    }
  });
});