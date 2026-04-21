console.log('ToonKrub');
const toggleButton = document.getElementById("goto3d");
let is3DLoaded = false;
let is3DView = false;
let simulator;
let variables;

function getString (formElementId) {
  return BuilderUtils.getString(formElementId);
}

function showSpinner() {
  BuilderUtils.showSpinner(pluginDirUrl);
}

function hideSpinner() {
  BuilderUtils.hideSpinner();
}


jQuery('#left-featured-step').hide();
jQuery('#right-featured-step').hide();

let wasFour = false;
function grabFormValues(){

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
let afterturn2 = '';
let going = parseFloat(jQuery("#going").val()) || 240;
let floor_h = jQuery("#floor-height").val();
let direction = jQuery("#sc-direction").val();
let nposts = BuilderUtils.getString("newel-posts");
let spinglass = jQuery('input[name="ballustrades"]:checked').val();
let height = parseFloat(floor_h.replace(/,/g, ''));
let adj = parseFloat(height / 0.90040404);
let widthadj = parseFloat(jQuery("#stair-width").val());
let width = (widthadj); // (widthadj + 30);
let width2 = parseFloat(jQuery("#stair-width2").val());
let width3 = parseFloat(jQuery("#stair-width3").val());
let topriser = (width); // (width + 12);
let modifier =  0; 
let risers = parseFloat(jQuery("#risers").val()) || 14;
//let regcheck = Math.ceil(height / risers);
//if (regcheck > 220) {modifier = 1; }
let treads = risers;
afterturn2 = parseInt(treads - (tits + tits2) - beforeturn - afterturn1); 
let riserh = Math.ceil(height / treads);
let total_run =  (going * treads);
let rake = Math.sqrt((height * height) + (total_run * total_run)).toFixed(2);
let pitch = Math.atan(height / total_run) * (180/Math.PI);
let tl = false;
let tr = false;
let boxcorner = false;
let f2bo = false;
let f1to = false;
let f2to = false;
let boxcorner2 = false;
let f3bo = false;
let br = false;
let bl = false;
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
let newel_cap = BuilderUtils.getString("newel_cap").toUpperCase();

if (nposts === "custom") {
  jQuery('#custom').show();
  
  if (jQuery("#tl-post").is(":checked")) {
    switch(direction) {
        case 'left':
        tl = true;
        break;
        case 'right':
        tr = true;
        break; }  }    
  if (jQuery("#tr-post").is(":checked")) {
    switch(direction) {
        case 'left':
        tr = true;
        break;
        case 'right':
        tl = true;
        break; } } 

        bl = jQuery("#bl-post").is(":checked");
        br = jQuery("#br-post").is(":checked");

  f1to = jQuery("#to-post").is(":checked"); // Flight 1 Top Outside

  if (jQuery("#box-post").is(":checked")) {
     switch(direction) {
            case 'left':
            boxcorner = true;
            break;
            case 'right':
            f2bo = true;
            break; }     
          }  
    if (jQuery("#bo-post").is(":checked")) {
        switch(direction) {
            case 'left':
            f2bo = true;
            break;
            case 'right':
            boxcorner = true;
            break; }    
        } 

        if (jQuery("#box-post2").is(":checked")) {
          switch(direction) {
                 case 'left':
                  boxcorner2 = true;
                 break;
                 case 'right':
                  f2to = true;
                 break; }     
               }  
         if (jQuery("#to-post2").is(":checked")) {
             switch(direction) {
                 case 'left':
                  f2to = true;
                 break;
                 case 'right':
                  boxcorner2 = true;
                 break; }    
             } 

  f3bo = jQuery("#bo-post2").is(":checked"); // Flight 2 Top Outside
 

    if (spinglass === "true") {
    switch (direction) {
        case 'left':
            bal_r = f1to && br;
            bal_l = bl;
            bal2_r = f2bo && f2to;
            bal2_l = true;
            bal3_r = f3bo && tl;
            bal3_l = tr;
            turntop = boxcorner && f2bo;
            turnside = boxcorner && f1to;
            turn2top = boxcorner2 && f2to;
            turn2side = boxcorner2 && f3bo;
            break;
        case 'right':
            bal_l = br;
            bal_r = f1to && bl;
            bal2_l = true;
            bal2_r = boxcorner && boxcorner2;
            bal3_r = tr;
            bal3_l = f3bo && tl;
            turntop = boxcorner && f2bo;
            turnside = f1to && f2bo;
            turn2top = boxcorner2 && f2to;
            turn2side = f2to && f3bo;
            break;
    }
}
  
} else { jQuery('#custom').hide(); }  

const variables = {
  tits,
  tits2,
  beforeturn,
  afterturn1,
  afterturn2,
  direction,
  going,
  floor_h,
  nposts,
  spinglass,
  height,
  adj,
  widthadj,
  width,
  width2,
  width3,
  topriser,
  modifier,
  risers,
  treads,
  riserh,
  total_run,
  rake,
  pitch,
  tl,
  tr,
  br,
  bl,
  f1to,
  boxcorner,
  f2bo,
  f2to,
  boxcorner2,
  f3bo,
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

window.grabFormValues = grabFormValues;

};

function grab3dValues(){
  return BuilderUtils.grab3dValues();
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


function getStaircaseConfig(going, height) {
  return BuilderUtils.getStaircaseConfig(going, height);
}

function onLoad(changedElement= null){
//console.log('2D Staircase Running');
const variables = grabFormValues();
var going = variables.going;
let height = variables.height;
let RiserNo;

const { selectedRiserHeight, numberOfStairs, lowestStairNumber } = getStaircaseConfig(going, height);

if (changedElement) {
  switch (changedElement) {
    case 'floor-height':
      RiserNo = lowestStairNumber;
      break;
      case 'going':
      RiserNo = lowestStairNumber;
      break;
      case 'risers':
        RiserNo = numberOfStairs;
        break;
        default:
        RiserNo = numberOfStairs;
  }
} else { RiserNo = numberOfStairs; }

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
        fillColor : acf_colours.treads_fill, //optional
        strokeColor : acf_colours.treads_outline, //optional
        textColor : acf_colours.treads_text //optional
    },
    turn1TreadsAmount : variables.tits,
    flight2Treads : {
        maxAmount : 6,
        amount : variables.afterturn1,
        width : variables.width2, // in millimeters
        fillColor : acf_colours.treads_fill, //optional
        strokeColor : acf_colours.treads_outline, //optional
        textColor : acf_colours.treads_text //optional
    },
    turn2TreadsAmount : variables.tits2,
    flight3Treads : {
        amount : variables.afterturn2,
        width : variables.width3, // in millimeters
       fillColor : acf_colours.treads_fill, //optional
        strokeColor : acf_colours.treads_outline, //optional
        textColor : acf_colours.treads_text //optional
    },
    posts : {
        flight1BottomLeft : variables.bl, //optional: default false
        flight1BottomRight : variables.br, //optional: default false
        turn1TopLeft : variables.f2bo, //optional: default false
        turn1TopRight :  variables.boxcorner, //optional: default false
        turn1Bottom : variables.f1to, // for outside post. inside bottom post is not optional
        turn2TopLeft : variables.boxcorner2, //optional: default false
        turn2TopRight : variables.f2to, //optional: default false
        turn2Bottom : variables.f3bo, // for outside post. inside bottom post is not optional
        flight3Left : variables.tl, //optional: default false
        flight3Right : variables.tr, //optional: default false
        fillColor : acf_colours.posts_fill, //optional
        strokeColor : acf_colours.posts_outline, //optional
        textColor : acf_colours.posts_text //optional
    },
    ballustrades : {
        flight1Outside: variables.bal_r, //optional: default false
        flight1Inside: variables.bal_l, //optional: default false
        flight2Outside: variables.bal2_r, //optional: default false
        flight2Inside: variables.bal2_l, //optional: default false
        flight3Outside: variables.bal3_r, //optional: default false
        flight3Inside: variables.bal3_l, //optional: default false
        turn1Top: variables.turn2top, //optional: default false
        turn1Side: variables.turn2side, //optional: default false
        turn2Top: variables.turntop, //optional: default false
        turn2Side: variables.turnside, //optional: default false
         primaryFillColor : acf_colours.stringer_fill, //optional
        secondaryFillColor : acf_colours.spindles, //optional
        strokeColor : acf_colours.stringer_outline, //optional
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
                      left: variables.bl,
                      right: variables.br,
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
                  width: variables.width2,
                  caps: {
                    type: variables.newel_cap,
                    material: variables3d.cap_material,
                    direction: {
                      left: variables.f2bo,
                      right: variables.f2to,
                    },
                  },
                  post: {
                    type: variables.newel_style,
                    material: variables3d.post_material,
                    direction: {
                      left: true, //Compulsory
                      right: variables.f2to,
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
                  num: variables.afterturn2,
                  width: variables.width3,
                  caps: {
                    type: variables.newel_cap,
                    material: variables3d.cap_material,
                    direction: {
                      left: variables.tl,
                      right: variables.tr,
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
                    left: variables.bal3_l,
                    right: variables.bal3_r,
                    },
                  },
                },
                box1: {
                  tnum: variables.tits,
                  post: {
                    direction: {
                      bottom: variables.f1to,
                      top: variables.f2bo,
                      corner: variables.boxcorner,
                      },
                  },
                  handrails: {
                    type: variables.spin_style,
                    material: variables3d.spin_material,
                    baseMaterial: variables3d.stringer_material,
                    direction: {
                      left: variables.turntop,
                      right: variables.turnside,
                    },
                  },
                },
                box2: {
                  tnum: variables.tits2,
                  post: {
                    direction: {
                      bottom: variables.f2to,
                      top: variables.f3bo,
                      corner: variables.boxcorner2,
                    },
                  },
                  handrails: {
                    direction: {
                      left: variables.turn2top,
                      right: variables.turn2side,
                    },
                  },
                },
                construct:  variables3d.construction_type,
                feature: variables3d.featured_step
              });
            }

jQuery(document).ready(function() {

  jQuery('#floor-height').val(2600);
              jQuery('#going').val(grabFormValues().going);
              jQuery('#stair-width').val(800);
              jQuery('#stair-width2').val(800);
              jQuery('#stair-width3').val(800);
              jQuery('#custom').hide();

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
             
              if(!is3DView) { onLoad(); }
       
              jQuery('#stairbuild').on('change', ':input', function() {
                const changedElement = jQuery(this).attr('id');
                jQuery("#treadat2").val(grabFormValues().afterturn2);
                if (!is3DLoaded) {
                  onLoad(changedElement);
                } else { 
                  drawHalfTurn();
                }
              });
             
});