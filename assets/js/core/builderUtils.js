/**
 * Baltic Stairbuilder - Core Utility Functions
 * 
 * This file contains pure helper functions used across different stair types.
 * All functions are designed to be pure (no side effects) and not rely on globals.
 * 
 * @version 1.0.0
 */

// =============================================================================
// PARAMETER PARSING FUNCTIONS
// =============================================================================

/**
 * Extracts the string value from a form element formatted as "value:price"
 * @param {string} formElementId - The ID of the form element
 * @param {Object} $ - jQuery instance (injected dependency)
 * @returns {string} The part before the colon, or empty string if not found
 */
function getString(formElementId, $ = window.jQuery) {
  try {
    const value = $("#" + formElementId).val();
    if (!value) return '';
    
    const valueParts = value.split(":");
    return valueParts[0] || '';
  } catch (error) {
    console.warn('getString error for element:', formElementId, error);
    return '';
  }
}

/**
 * Extracts the numeric price from a form element formatted as "value:price"
 * @param {string} formElementId - The ID of the form element
 * @param {Object} $ - jQuery instance (injected dependency)
 * @returns {number} The parsed float price after the colon, or 0 if not found
 */
function getNumber(formElementId, $ = window.jQuery) {
  try {
    const value = $("#" + formElementId).val();
    if (!value) return 0;
    
    const valueParts = value.split(":");
    const price = valueParts[1];
    return parseFloat(price) || 0;
  } catch (error) {
    console.warn('getNumber error for element:', formElementId, error);
    return 0;
  }
}

// =============================================================================
// MATHEMATICAL CALCULATION FUNCTIONS
// =============================================================================

/**
 * Calculates the pitch angle of a staircase.
 *
 * @deprecated Mixes full floor-to-floor height with total run, which double-counts
 * the off-by-one between risers and goings (see calculateStepPitch). Retained only
 * as a safety shim for any external caller; all internal call sites now use
 * calculateStepPitch. Do not use for new code.
 *
 * @param {number} height - Total height of the staircase
 * @param {number} totalRun - Total horizontal run of the staircase
 * @returns {number} Pitch angle in degrees
 */
function calculatePitch(height, totalRun) {
  if (totalRun === 0) return 0;
  return Math.atan(height / totalRun) * (180 / Math.PI);
}

/**
 * Calculates the Doc K pitch: the angle of the line joining the tread nosings.
 *
 * From the first nosing to the floor nosing there are (risers - 1) rises and
 * (risers - 1) goings, so the pitch reduces to the per-step angle
 * atan(riserHeight / going). This is definitionally correct, has no off-by-one
 * risk, and is what UK Building Regs (Approved Document K) mean by "pitch".
 *
 * @param {number} riserHeight - Individual rise in millimetres
 * @param {number} going - Individual going in millimetres
 * @returns {number} Pitch angle in degrees
 */
function calculateStepPitch(riserHeight, going) {
  if (going === 0) return 0;
  return Math.atan(riserHeight / going) * (180 / Math.PI);
}

/**
 * Calculates the rake (diagonal length) of a staircase
 * @param {number} height - Total height of the staircase
 * @param {number} totalRun - Total horizontal run of the staircase
 * @returns {number} Rake length in millimeters
 */
function calculateRake(height, totalRun) {
  return Math.sqrt((height * height) + (totalRun * totalRun));
}

/**
 * Calculates individual riser height
 * @param {number} totalHeight - Total height of the staircase
 * @param {number} numberOfTreads - Number of treads
 * @returns {number} Individual riser height in millimeters
 */
function calculateRiserHeight(totalHeight, numberOfTreads) {
  if (numberOfTreads === 0) return 0;
  return Math.ceil(totalHeight / numberOfTreads);
}

/**
 * Gets the lowest stair number from valid configurations
 * @param {Array} validConfigs - Array of valid stair configurations
 * @returns {number} The lowest number of risers
 */
function getLowestStairNumber(validConfigs) {
  if (!validConfigs || validConfigs.length === 0) return 0;
  return validConfigs[0].risers;
}

/**
 * Calculates valid staircase configurations based on going and height
 * @param {number} going - Going distance in millimeters
 * @param {number} height - Total height in millimeters
 * @param {Object} $ - jQuery instance (injected dependency)
 * @returns {Object} Configuration object with selectedRiserHeight, numberOfStairs, lowestStairNumber
 */
function getStaircaseConfig(going, height, $ = window.jQuery) {
  // Capture the currently selected value from the dropdown
  let currentSelected = $("#risers").val();
  
  if (currentSelected === '' || currentSelected === null) {
    currentSelected = 14;
  }
  
  let validConfigs = [];
  let uniqueRiserCounts = new Set();
  
  // Generate valid configurations
  for (let possibleRiserHeight = 150; possibleRiserHeight <= 220; possibleRiserHeight++) {
    let possibleRisers = Math.ceil(height / possibleRiserHeight);
    
    if (uniqueRiserCounts.has(possibleRisers)) {
      continue;
    }
    
    uniqueRiserCounts.add(possibleRisers);

    // Doc K per-step pitch: atan(rise / going). The previous formula divided the
    // full height by (risers - 1) goings, inflating the angle and wrongly excluding
    // valid riser configurations near the 42 degree limit.
    let newPitch = calculateStepPitch(possibleRiserHeight, going);

    if (going >= 220 && going <= 300 && newPitch <= 42) {
      validConfigs.push({
        risers: possibleRisers,
        height: possibleRiserHeight.toFixed(1)
      });
    }
  }
  
  // Sort configs by the number of risers
  validConfigs.sort((a, b) => a.risers - b.risers);
  
  // Update the <select> box
  let selectBox = $("#risers");
  selectBox.empty();
  validConfigs.forEach(function(config) {
    selectBox.append($('<option>', {
      value: config.risers,
      text: `${config.risers} @ ${config.height}mm`
    }));
  });
  
  // Try to restore the captured value, if it exists. Otherwise, default to the first option.
  if (selectBox.find(`option[value="${currentSelected}"]`).length > 0) {
    selectBox.val(currentSelected);
  } else {
    selectBox.val(selectBox.find("option:first").val());
  }
  
  // Get the lowest stair number
  const lowestStairNumber = getLowestStairNumber(validConfigs);
  
  const selectedConfig = validConfigs.find(config => config.risers === parseInt(currentSelected));
  
  if (selectedConfig) {
    return {
      selectedRiserHeight: selectedConfig.height,
      numberOfStairs: selectedConfig.risers,
      lowestStairNumber: lowestStairNumber
    };
  } else {
    return {
      selectedRiserHeight: null,
      numberOfStairs: null,
      lowestStairNumber: lowestStairNumber
    };
  }
}

// =============================================================================
// UI HELPER FUNCTIONS
// =============================================================================

/**
 * Shows a loading spinner in the canvas container
 * @param {string} pluginDirUrl - URL to the plugin directory
 * @param {string} containerId - ID of the container element (default: 'canvas-container')
 */
function showSpinner(pluginDirUrl, containerId = 'canvas-container') {
  const canvasContainer = document.getElementById(containerId);
  if (!canvasContainer) {
    console.warn('Canvas container not found:', containerId);
    return;
  }
  
  const spinner = document.createElement("img");
  spinner.src = pluginDirUrl + "assets/images/spinner.svg";
  spinner.className = "spinner";
  canvasContainer.appendChild(spinner);
}

/**
 * Hides the loading spinner
 */
function hideSpinner() {
  const spinner = document.querySelector(".spinner");
  if (spinner) {
    spinner.parentNode.removeChild(spinner);
  }
}

// =============================================================================
// FORM VALUE EXTRACTION FUNCTIONS
// =============================================================================

/**
 * Extracts 3D material values from form elements
 * @param {Object} $ - jQuery instance (injected dependency)
 * @returns {Object} Object containing 3D material variables
 */
function grab3dValues($ = window.jQuery) {
  return {
    post_material: getString('newel_material', $).toUpperCase(),
    spin_material: getString('bal_material', $).toUpperCase(),
    cap_material: getString('cap_material', $).toUpperCase(),
    stringer_material: getString('stringer_material', $).toUpperCase(),
    tread_material: getString('tread_material', $).toUpperCase(),
    riser_material: getString('riser_material', $).toUpperCase(),
    hdr_material: getString('hdr_material', $).toUpperCase(),
    bsr_material: getString('bsr_material', $).toUpperCase(),
    construction_type: $("#construction_type").val(),
    featured_step: $("#feature_tread").val()
  };
}

/**
 * Generic form values extraction function
 * This serves as a fallback when stair-specific grabFormValues is not available
 * @param {Object} $ - jQuery instance (injected dependency)
 * @returns {Object} Object containing basic form values
 */
function grabFormValues($ = window.jQuery) {
  // This is the fallback implementation - don't check for other versions
  
  // Fallback implementation with basic form values
  let going = parseFloat($("#going").val()) || 240;
  let floor_h = $("#floor-height").val();
  let nposts = getString("newel-posts", $);
  let spinglass = $('input[name="ballustrades"]:checked').val();
  let height = parseFloat(floor_h.replace(/,/g, ''));
  let adj = parseFloat(height / 0.90040404);
  let widthadj = parseFloat($("#stair-width").val());
  let width = (widthadj);
  let modifier = 0;
  let risers = Math.ceil(adj / going);
  let regcheck = Math.ceil(height / risers);
  if (regcheck > 220) { modifier = 1; }
  let treads = parseFloat(risers + modifier);
  let riserh = Math.ceil(height / treads);
  let total_run = (going * treads);
  let rake = Math.sqrt((height * height) + (total_run * total_run)).toFixed(2);
  let pitch = calculateStepPitch(riserh, going); // Doc K per-step pitch: atan(rise / going)
  let tl = false;
  let tr = false;
  let br = false;
  let bl = false;
  let spLmod = 0;
  let spRmod = 0;
  let featureTreadConfig = $('#feature_tread').find('option:selected').data('config');
  const [fl, fr] = featureTreadConfig ? featureTreadConfig.split(',') : ['0', '0'];
  let bal_l = false;
  let bal_r = false;
  let bal2_l = false;
  let bal2_r = false;
  let newel_style = $("#newel_type").val() ? $("#newel_type").val().toUpperCase() : '';
  let spin_style = $("#spindle_type").val() ? $("#spindle_type").val().toUpperCase() : '';
  let newel_cap = getString("newel_cap", $).toUpperCase();

  if (nposts === "left") {
    tl = true; bl = true;
  }
  if (nposts === "right") {
    tr = true; br = true;
  }
  if (nposts === "both") {
    tl = true; bl = true; tr = true; br = true;
  }
  if (nposts === "custom") {
    if ($("#tl-post").is(":checked")) { tl = true; }
    if ($("#tr-post").is(":checked")) { tr = true; }
    if ($("#bl-post").is(":checked")) { bl = true; }
    if ($("#br-post").is(":checked")) { br = true; }
  }
  if (spinglass === "true") {
    if ((tr == true) && (br == true)) {
      bal_r = true; spRmod = 1;
    }
    if ((tl == true) && (bl == true)) {
      bal_l = true; spLmod = 1;
    }
  }

  return {
    tits: '',
    beforeturn: '',
    afterturn1: '',
    afterturn2: '',
    afterturn2a: '',
    going,
    floor_h,
    nposts,
    spinglass,
    height,
    adj,
    widthadj,
    width,
    topriser: width,
    modifier,
    risers,
    treads,
    regcheck,
    riserh,
    total_run,
    rake,
    pitch,
    tl,
    tr,
    br,
    bl,
    spLmod,
    spRmod,
    fr,
    fl,
    bal_l,
    bal_r,
    bal2_l,
    bal2_r,
    newel_style,
    spin_style,
    newel_cap
  };
}

/**
 * Extracts newel post product IDs and quantities
 * @param {Object} $ - jQuery instance (injected dependency)
 * @returns {Array} Array of objects with id and qty properties
 */
function getNewelIds($ = window.jQuery) {
  const newelIds = [];
  
  // Check each newel post checkbox
  const positions = ['tl', 'tr', 'bl', 'br'];
  positions.forEach(pos => {
    if ($(`#${pos}-post`).is(":checked")) {
      const productId = getNumber(`newel_${pos}`, $);
      if (productId) {
        newelIds.push({ id: productId, qty: 1 });
      }
    }
  });
  
  return newelIds;
}

/**
 * Extracts cap product IDs and quantities
 * @param {Object} $ - jQuery instance (injected dependency)
 * @returns {Array} Array of objects with id and qty properties
 */
function getCapIds($ = window.jQuery) {
  const capIds = [];
  
  // Check each cap checkbox
  const positions = ['tl', 'tr', 'bl', 'br'];
  positions.forEach(pos => {
    if ($(`#${pos}-post`).is(":checked")) {
      const productId = getNumber(`cap_${pos}`, $);
      if (productId) {
        capIds.push({ id: productId, qty: 1 });
      }
    }
  });
  
  return capIds;
}

// =============================================================================
// PRICING CALCULATION FUNCTIONS
// =============================================================================

/**
 * Calculates material-based pricing adjustments for oak materials
 * @param {Object} materials - Object containing material selections
 * @returns {number} Price adjustment amount
 */
function calculateOakBonus(materials) {
  let bonus = 0;
  
  // Oak material pricing logic
  const oakMaterials = ['OAK', 'AMERICAN_OAK', 'EUROPEAN_OAK'];
  
  Object.values(materials).forEach(material => {
    if (typeof material === 'string' && oakMaterials.includes(material.toUpperCase())) {
      bonus += 50; // Base oak premium
    }
  });
  
  return bonus;
}

/**
 * Validates form input values
 * @param {number} going - Going distance
 * @param {number} height - Total height
 * @param {number} width - Stair width
 * @returns {Object} Validation result with isValid flag and errors array
 */
function validateStairInputs(going, height, width) {
  const errors = [];
  
  if (going < 220 || going > 300) {
    errors.push('Going must be between 220mm and 300mm');
  }
  
  if (height < 2000 || height > 3500) {
    errors.push('Height must be between 2000mm and 3500mm');
  }
  
  if (width < 600 || width > 1200) {
    errors.push('Width must be between 600mm and 1200mm');
  }
  
  return {
    isValid: errors.length === 0,
    errors: errors
  };
}

// =============================================================================
// EXPORT FUNCTIONS (for module systems)
// =============================================================================

// For environments that support modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    getString,
    getNumber,
    calculatePitch,
    calculateStepPitch,
    calculateRake,
    calculateRiserHeight,
    getLowestStairNumber,
    getStaircaseConfig,
    showSpinner,
    hideSpinner,
    grab3dValues,
    grabFormValues,
    getNewelIds,
    getCapIds,
    calculateOakBonus,
    validateStairInputs
  };
}

// For browser environments, attach to window
if (typeof window !== 'undefined') {
  window.BuilderUtils = {
    getString,
    getNumber,
    calculatePitch,
    calculateStepPitch,
    calculateRake,
    calculateRiserHeight,
    getLowestStairNumber,
    getStaircaseConfig,
    showSpinner,
    hideSpinner,
    grab3dValues,
    grabFormValues,
    getNewelIds,
    getCapIds,
    calculateOakBonus,
    validateStairInputs
  };
}