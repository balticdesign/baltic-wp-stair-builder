const toggleButton = document.getElementById("goto3d");
let is3DLoaded = false;
let is3DView = false;
let simulator;
let variables;

function getString (formElementId) {
  // get the value of the form element
  var value = jQuery("#" + formElementId).val();

  // split the value using the colon separator
  var valueParts = value.split(":");

  // get the second part of the resulting array (index 1)
  var theValueString = valueParts[0];

  // return the second part of the value
  return theValueString;
}

function showSpinner() {
  const canvasContainer = document.getElementById("canvas-container");
  const spinner = document.createElement("img");
  spinner.src = pluginDirUrl + "assets/images/spinner.svg";
  spinner.className = "spinner";
  canvasContainer.appendChild(spinner);
}

function hideSpinner() {
  const spinner = document.querySelector(".spinner");
  if (spinner) {
    spinner.parentNode.removeChild(spinner);
  }
}

function getString(formElementId) {
  // get the value of the form element
  var value = jQuery("#" + formElementId).val();

  // split the value using the colon separator
  var valueParts = value.split(":");

  // get the second part of the resulting array (index 1)
  var String = valueParts[0];

  // return the second part of the value
  return String;
}

jQuery('#left-featured-step').hide();
jQuery('#right-featured-step').hide();


function grabFormValues(){

let tits = parseFloat(jQuery("#treadit").val());
let beforeturn = '';
let afterturn1 = '';
let afterturn2 = '';
let afterturn2a = '';
let going = parseFloat(jQuery("#going").val()) || 240;
let floor_h = jQuery("#floor-height").val();
let direction = jQuery("#sc-direction").val();
let nposts = getString("newel-posts");
let spinglass = jQuery('input[name="ballustrades"]:checked').val();
let height = parseFloat(floor_h.replace(/,/g, ''));
let adj = parseFloat(height / 0.90040404);
let widthadj = parseFloat(jQuery("#stair-width").val());
let width = (widthadj + 30);
let topriser = (width + 12);
let modifier =  0; 
let risers = Math.ceil(adj / going);
let regcheck = Math.ceil(height / risers);
if (regcheck > 220) {modifier = 1; }
let treads = parseFloat(risers + modifier);
beforeturn = parseFloat(jQuery("#treadbt").val());
afterturn1 = parseFloat(jQuery("#treadat").val());
afterturn2 = risers - (tits * 2) - beforeturn - afterturn1;
afterturn2a = parseFloat((risers - (tits * 2) - beforeturn - afterturn1) - 1);
let riserh = Math.ceil(height / treads);
let total_run =  (going * treads);
let rake = Math.sqrt((height * height) + (total_run * total_run)).toFixed(2);
let pitch = Math.atan(height / total_run) * (180/Math.PI);
let tl = false;
let tr = false;
let br = false;
let bl = false;
let f1bc = false;
let boxcorner = false;
let f2bc = false;
let f1bc2 = false;
let boxcorner2 = false;
let f3bc = false;
let spLmod = 0;
let spRmod = 0;
let featureTreadConfig = jQuery('#feature_tread').find('option:selected').data('config');
//let fr = jQuery("#right-featured-step").val();
//let fl = jQuery("#left-featured-step").val();
const [fl, fr] = featureTreadConfig.split(',');
let bal_l = false;
let bal_r = false;
let bal2_l = false;
let bal2_r = false;
let bal3_l = false;
let bal3_r = false;
let turntop = false;
let turnside = false;
let turn2top = false;
let turn2side = false;
let newel_style = jQuery("#newel_type").val().toUpperCase();
let spin_style = jQuery("#spindle_type").val().toUpperCase();
let newel_cap = getString("newel_cap").toUpperCase();

if (nposts === "left") {
  tl = true; bl = true; }   
  if (nposts === "right") {
  tr = true; br = true; }   
  if (nposts === "both") {
  tl = true; bl = true; tr = true; br = true; }   
  if (nposts === "custom") {
      jQuery('#custom').show();
      if (jQuery("#tl-post").is(":checked")) {tl = true; }   
      if (jQuery("#tr-post").is(":checked")) {tr = true; }
      if (jQuery("#bl-post").is(":checked")) {bl = true; }
      if (jQuery("#br-post").is(":checked")) {br = true; }  
  } else { jQuery('#custom').hide(); }  
  if (spinglass === "true") {
    switch (direction) {
        case 'left':
            bal_r = f1bc && br;
            bal_l = bl;
            bal2_r = f2bc && tr;
            bal2_l = tl;
            turntop = boxcorner && f2bc;
            turnside = boxcorner && f1bc;
            break;
        case 'right':
            bal_l = br;
            bal_r = f1bc && bl;
            bal2_r = boxcorner && tr;
            bal2_l = tl;
            turntop = boxcorner && f2bc;
            turnside = f2bc && f1bc;
            break;
    }
}

const variables = {
  tits,
  beforeturn,
  afterturn1,
  afterturn2,
  afterturn2a,
  direction,
  going,
  floor_h,
  nposts,
  spinglass,
  height,
  adj,
  widthadj,
  width,
  topriser,
  modifier,
  risers,
  treads,
  regcheck,
  riserh,
  total_run,
  rake,
  tl,
  tr,
  br,
  bl,
  f1bc,
  boxcorner,
  f2bc,
  f1bc2,
  boxcorner2,
  f3bc,
  spLmod,
  spRmod,
  fr,
  fl,
  bal_l,
  bal_r,
  bal2_l,
  bal2_r,
  bal3_l,
  bal3_r,
  turntop,
  turnside,
  turn2top,
  turn2side,
  newel_style,
  spin_style,
  newel_cap
};

return variables;

};

function grab3dValues(){
  let post_material = getString('newel_material').toUpperCase();
  let spin_material = getString('bal_material').toUpperCase();
  let cap_material = getString('cap_material').toUpperCase();
  let stringer_material = getString('stringer_material').toUpperCase();
  let tread_material = getString('tread_material').toUpperCase();
  let riser_material = getString('riser_material').toUpperCase();
  let hdr_material = getString('hdr_material').toUpperCase();
  let bsr_material = getString('bsr_material').toUpperCase();
  let construction_type = jQuery("#construction_type").val();
  let fr = jQuery("#right-featured-step").val();
  let fl = jQuery("#left-featured-step").val();
  let featured_step = jQuery("#feature_tread").val(); /** to select from the right option here  */

  const variables3d = {
    post_material,
    spin_material,
    cap_material,
    stringer_material,
    tread_material,
    riser_material,
    hdr_material,
    bsr_material,
    construction_type,
    featured_step
  };
  return variables3d;
}

function bonuslogic(){
  materials = grab3dValues();
  string_material = materials.stringer_material;
  tread_material = materials.tread_material;
  riser_material = materials.riser_material;
let no_oak_price = jQuery("#half_landing_no_oak").val();
let oak_tread_price = jQuery("#half_landing_oak_tread").val();
let oak_tread_riser_price = jQuery("#half_landing_oak_tr").val();
let oak_string_price = jQuery("#half_landing_oak_string").val();
let all_oak_price = jQuery("#half_landing_all_oak").val();

let price_addon = 0; 

if (string_material === 'OAK' && tread_material === 'OAK' && riser_material === 'OAK') {
  price_addon = all_oak_price;
} else if (tread_material === 'OAK' && riser_material === 'OAK') {
  price_addon = oak_tread_riser_price;
} else if (tread_material === 'OAK') {
  price_addon = oak_tread_price;
} else if (string_material === 'OAK') {
  price_addon = oak_string_price;
} else {
  price_addon = no_oak_price;
}

return parseFloat(price_addon);

}

function onLoad(){
// console.log('2D Staircase Running');
const variables = grabFormValues();
var going = variables.going;
console.log(variables.width);
jQuery("#risers").val(variables.treads);


if ((going < 220) || (going > 250)) { jQuery('#going').css({ 'color': 'red' }); } else { jQuery('#going').css({ 'color': 'inherit' }); } 

var halfturnStairsConfig = {
    type: 'halfturn',
    direction: variables.direction,
    backgroundColor : 'transparent',
    font: 'Varela Round',
    treadHeight: going, // in millimeters
    flight1Treads : {
        amount : variables.beforeturn,
        width : variables.width, // in millimeters
        fillColor : '#e4b177', //optional
        strokeColor : '#402100', //optional
        textColor : '#402100' //optional
    },
    turn1TreadsAmount : variables.tits,
    flight2Treads : {
        maxAmount : 6,
        amount : variables.afterturn1,
        width : variables.topriser, // in millimeters
        fillColor : '#d3cece', //optional
        strokeColor : '#2f2f2f', //optional
        textColor : '#2f2f2f' //optional
    },
    turn2TreadsAmount : variables.tits,
    flight3Treads : {
        amount : variables.afterturn2a,
        width : variables.width, // in millimeters
       fillColor : '#d3cece', //optional
        strokeColor : '#2f2f2f', //optional
        textColor : '#2f2f2f' //optional
    },
    posts : {
        flight1BottomLeft : variables.bl, //optional: default false
        flight1BottomRight : variables.br, //optional: default false
        turn1TopLeft : variables.f2bc, //optional: default false
        turn1TopRight :  variables.boxcorner, //optional: default false
        turn1Bottom : variables.f1bc, // for outside post. inside bottom post is not optional
        turn2TopLeft : variables.f3bc, //optional: default false
        turn2TopRight : variables.boxcorner2, //optional: default false
        turn2Bottom : variables.f1bc2, // for outside post. inside bottom post is not optional
        flight3Left : variables.tl, //optional: default false
        flight3Right : variables.tr, //optional: default false
        fillColor : '#b88242', //optional
        strokeColor : '#683602', //optional
        textColor : '#683602' //optional
    },
    ballustrades : {
        flight1Outside: variables.bal_r, //optional: default false
        flight1Inside: variables.bal_l, //optional: default false
        flight2Outside: variables.bal2_r, //optional: default false
        flight2Inside: variables.bal2_l, //optional: default false
        flight3Outside: variables.bal3_r, //optional: default false
        flight3Inside: variables.bal3_l, //optional: default false
        turn1Top: variables.turntop, //optional: default false
        turn1Side: variables.turnside, //optional: default false
        turn2Top: variables.turn2top, //optional: default false
        turn2Side: variables.turn2side, //optional: default false
         primaryFillColor : '#f9ef9f', //optional
        secondaryFillColor : '#b88242', //optional
        strokeColor : '#b88242', //optional
    },
    featureTread : {
        left : variables.fl, //0: none 1: curtail 2: bullnose 3: double going curtail plus single curtail 4: double going curtail plus bullnose
        right : variables.fr, //0: none 1: curtail 2: bullnose 3: double going curtail plus single curtail 4: double going curtail plus bullnose
    },
    minHeight : 220,  // in millimeters
    maxHeight : 250,  // in millimeters
    minWidth : 800, // in millimeters
    maxWidth : 1200  // in millimeters
}
                var canvas = document.getElementById("canvas");
                Stairs.init(canvas,halfturnStairsConfig);
               
            }

            function drawHalfTurn() {
              const variables = grabFormValues();
              const variables3d = grab3dValues();
              jQuery("#risers").val(variables.treads);
              var going = variables.going;
              var directiona = variables.direction.toUpperCase();

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
                    direction: {
                      left: 0,
                      right: 0,
                    },
                  },
                  post: {
                    type: variables.newel_style,
                    material: variables3d.post_material,
                    direction: {
                      left: variables.bl,
                      right: variables.br,
                    },
                  },
                  handrails: {
                    type: variables.spin_style,
                    material: variables3d.spin_material,
                    baseMaterial: variables3d.stringer_material,
                    direction: {
                      left: variables.bal_l,
                      right: variables.bal_r,
                    },
                  },
                },
                section2: {
                  num: variables.afterturn1,
                  width: variables.width,
                  caps: {
                    type: variables.newel_cap,
                    material: variables3d.cap_material,
                    direction: {
                      left: 0,
                      right: 0,
                    },
                  },
                  post: {
                    type: variables.newel_style,
                    material: variables3d.post_material,
                    direction: {
                      left: variables.tl,
                      right: variables.tr,
                    },
                  },
                  handrails: {
                    type: variables.spin_style,
                    material: variables3d.spin_material,
                    baseMaterial: variables3d.stringer_material,
                    direction: {
                      left: variables.bal2_l,
                      right: variables.bal2_r,
                    },
                  },
                },
                section3: {
                  num: 5,
                  width: variables.width,
                  caps: {
                    type: 'BALL',
                    material: 'PINE',
                    direction: {
                      left: true,
                      right: true,
                    },
                  },
                  post: {
                    type: 'SQUARE',
                    material: 'PINE',
                    direction: {
                      left: true,
                      right: true,
                    },
                  },
                  handrails: {
                    type: 'CHAMFERED',
                    material: 'PINE',
                    baseMaterial: 'PINE',
                    direction: {
                      left: true,
                      right: true,
                    },
                  },
                },
                box1: {
                  tnum: 2,
                  post: {
                    direction: {
                      bottom: true,
                      top: true,
                      corner: true,
                    },
                  },
                  handrails: {
                    direction: {
                      left: true,
                      right: true,
                    },
                  },
                },
                box2: {
                  tnum: 2,
                  post: {
                    direction: {
                      bottom: true,
                      top: true,
                      corner: true,
                    },
                  },
                  handrails: {
                    direction: {
                      left: true,
                      right: true,
                    },
                  },
                },
                construct: variables3d.construction_type,
                feature: variables3d.featured_step
              });
            }
            
jQuery(document).ready(function() {

  // Add safety check for toggleButton existence
  if (toggleButton) {
    toggleButton.addEventListener("click", (event) => {
      event.preventDefault();
      // Load 3D script if not loaded yet
    if (!is3DLoaded) {
     
      const canvasContainer = document.getElementById("canvas-container");
      
      // Safety check for canvas container
      if (!canvasContainer) {
        console.warn("Canvas container not found - 3D functionality disabled");
        return;
      }

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
    
    // Safety check for canvas container
    if (!canvasContainer) {
      console.warn("Canvas container not found - cannot toggle 3D view");
      return;
    }
    
    if (is3DView) {
      toggleButton.textContent = "Switch to 3D";
      const builderWrap = document.getElementById("builder-wrap");
      const innerWrap = builderWrap.querySelector(".ct-section-inner-wrap");
      let canvas3D = document.getElementById("canvas3D");

        // if canvas3D exists, remove it
        if (canvas3D) {
            canvas3D.parentNode.removeChild(canvas3D);
        }
      let canvas = document.getElementById("canvas");
      if (!canvas) {
        canvas = document.createElement("canvas");
        canvas.id = "canvas";
        canvas.width = 558; // Set the desired width value in pixels
        canvas.height = 556;
        builderWrap.insertBefore(canvas, builderWrap.firstChild);
        canvasContainer.appendChild(canvas);
      }
     
    canvas.style.display = "block";
    innerWrap.style.padding = "75px auto !important";
      onLoad(); // Call the 2D drawing function
      is3DView = false;
    } else {
      toggleButton.textContent = "Switch to 2D";
      let canvas = document.getElementById("canvas");
      if (canvas) {
          canvas.parentNode.removeChild(canvas);
      }
     // Create new canvas element
     let canvas3D = document.createElement("canvas");
     canvas3D.id = "canvas3D";
     canvasContainer.appendChild(canvas3D);
     
     //showSpinner();
     
    simulator = new STAIR.StairSimulator(canvas3D);
       simulator.on('started', () => {
        drawHalfTurn();
      });
      simulator.setSize(window.innerWidth, window.innerHeight);
      const builderWrap = document.getElementById("builder-wrap");
     const innerWrap = builderWrap.querySelector(".ct-section-inner-wrap");
      innerWrap.style.padding = "0";
      //hideSpinner();
      is3DView = true;
    }
  });
  } // End of toggleButton safety check

              // Set the default value of the input
              jQuery('#floor-height').val(2600);
              jQuery('#going').val(grabFormValues().going);
              jQuery('#stair-width').val(800);
              jQuery('#custom').hide();
              console.log(grabFormValues().height);
              if(!is3DView) { onLoad(); }
       
              jQuery('#stairbuild').on('change', ':input', function() {
                if (!is3DLoaded) {
                  onLoad();
                } else { 
                  drawHalfTurn();
                }
              });
             
});