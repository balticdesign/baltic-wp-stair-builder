/**
 * Baltic Stairbuilder - Core Utility Functions
 * 
 * This file contains pure helper functions used across different stair types.
 * All functions are designed to be pure (no side effects) and not rely on globals.
 *
 * @version 2.11.0
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

/* ---- Building-regs regime (v2.16.0 Phase 1) ----------------------------
 * The active regime is the current #building_regs selection, resolved against
 * the localised stairBuilderVars.regs table. Empty numeric columns mean
 * "no constraint" (parseFloat('') === NaN → treated as absent). */
function bdRegimeNum(v) {
  if (v === '' || v === null || v === undefined) return null;
  var n = parseFloat(v);
  return isNaN(n) ? null : n;
}
function bdActiveRegime($ = window.jQuery) {
  try {
    var code = $('#building_regs').val();
    var regs = (window.stairBuilderVars && window.stairBuilderVars.regs) || {};
    return (code && regs[code]) ? regs[code] : null;
  } catch (e) { return null; }
}
// "Unregulated" = every dimensional column empty (the No Building Regs row).
// Distinguishes it from a regime that merely omits max_pitch (which falls back
// to Doc K 42) — see getStaircaseConfig / bdRegimePitchLimit.
function bdRegimeUnregulated(regime) {
  if (!regime) return true;
  var keys = ['min_going', 'max_rise', 'min_rise', 'min_width', 'max_pitch', 'two_r_g_min', 'two_r_g_max', 'max_open_gap', 'max_risers_run'];
  for (var i = 0; i < keys.length; i++) {
    if (bdRegimeNum(regime[keys[i]]) !== null) return false;
  }
  return true;
}
function bdRegimePitchLimit(regime) {
  if (!regime) return Infinity;                 // no regime selected → no pitch gate
  var mp = bdRegimeNum(regime.max_pitch);
  if (mp !== null) return mp;
  // max_pitch empty: no gate for the unregulated row, else Doc K 42 fallback (§3.3).
  return bdRegimeUnregulated(regime) ? Infinity : 42;
}

/* ---- Derived open-riser board height (v2.16.0 Phase 3, §4.2) -------------
 * Display spec only — the riser price stays flat and does NOT track this.
 * height = individual rise − gap, where gap = the active regime's max_open_gap
 * (e.g. Domestic/Commercial = 100) falling back to the construction type's
 * default_open_gap (No Building Regs path). Returns { height, gap } or NULL when
 * this isn't a derived-riser-height construction, OR when BOTH gap sources are
 * empty, OR when the geometry is nonsensical (gap ≥ rise). NULL means "suppress
 * the line" — the brief requires height never resolve to null/zero on screen, so
 * the caller (Phase 5 panel) omits the row rather than showing a wrong figure.
 * Phase 3 exposes this; Phase 5 renders + POSTs it. */
function bdRiserBoard($ = window.jQuery) {
  try {
    var code = $('#construction_type').val();
    var meta = (window.stairBuilderVars && window.stairBuilderVars.availability
      && window.stairBuilderVars.availability.construction_meta) || {};
    var cm = code && meta[code];
    if (!cm || !cm.derives_riser_height) return null;   // not an open-riser construction
    var regime = bdActiveRegime($);
    var gap = regime ? bdRegimeNum(regime.max_open_gap) : null;
    if (gap === null) gap = bdRegimeNum(cm.default_open_gap);
    if (gap === null) return null;                       // both-empty → suppress
    var rise = parseFloat(String($('#rise').text() || '').replace(/[^\d.]/g, ''));
    if (isNaN(rise) || rise <= 0) return null;
    var height = rise - gap;
    if (!(height > 0)) return null;                      // gap ≥ rise → no sensible board
    return { height: height, gap: gap };
  } catch (e) { return null; }
}

/* ---- Top lip / display total run (v2.16.0 Phase 4, §5) ------------------
 * The lip is a fixed length at the head of the flight where it meets the upper
 * floor — DISPLAY + DRAWING only, NEVER priced (§5.2). It touches display_total_run
 * and the canvas, and MUST NOT feed total_run / calculateRake / the metal count.
 * bdDisplayTotalRun takes an already-computed base run (mm) and an EXPLICIT
 * includeLip flag — the lip is added exactly once, by the caller that owns the
 * "final flight only" rule (turned staircases), so it can't double-count. */
function bdTopLipMm() {
  var g = (window.stairBuilderVars && window.stairBuilderVars.geometry) || {};
  var n = bdRegimeNum(g.top_lip_mm);
  return (n !== null && n > 0) ? n : 0;   // default 0 → no lip → pre-v2.16 geometry
}
function bdDisplayTotalRun(baseRunMm, includeLip) {
  var base = parseFloat(baseRunMm);
  if (isNaN(base)) return null;
  return base + (includeLip ? bdTopLipMm() : 0);
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

  // v2.16.0 Phase 1: riser-height search bounds + pitch gate now derive from the
  // active building-regs regime, replacing the hardcoded 150–220 / 42° values.
  //   - riser-height range: regime min_rise..max_rise. When the regime supplies
  //     no value (e.g. No Building Regs) it falls back to the admin
  //     riser_search_min/max (Geometry / Defaults tab, default 150/220 = ADK
  //     non-domestic min / domestic max). These are building-regs figures, so
  //     they are configuration, not a hardcoded constant — a licensee building
  //     loft ladders can widen them without touching code.
  //   - pitch gate: regime max_pitch (Doc K 42 fallback; no gate when unregulated).
  // The old `going >= 220 && going <= 300` band is GONE: bounding the going INPUT
  // is not this function's job — going is a soft warning (min_going) + hard max
  // (going_max) in formLogic (§2/§3.3). getStaircaseConfig filters riser counts
  // by pitch only.
  const regime   = bdActiveRegime($);
  const maxPitch = bdRegimePitchLimit(regime);
  const geo      = (window.stairBuilderVars && window.stairBuilderVars.geometry) || {};
  const fbMin    = (bdRegimeNum(geo.riser_search_min) !== null) ? bdRegimeNum(geo.riser_search_min) : 150;
  const fbMax    = (bdRegimeNum(geo.riser_search_max) !== null) ? bdRegimeNum(geo.riser_search_max) : 220;
  const riserMin = (regime && bdRegimeNum(regime.min_rise) !== null) ? bdRegimeNum(regime.min_rise) : fbMin;
  const riserMax = (regime && bdRegimeNum(regime.max_rise) !== null) ? bdRegimeNum(regime.max_rise) : fbMax;

  for (let possibleRiserHeight = riserMin; possibleRiserHeight <= riserMax; possibleRiserHeight++) {
    let possibleRisers = Math.ceil(height / possibleRiserHeight);

    if (uniqueRiserCounts.has(possibleRisers)) {
      continue;
    }

    uniqueRiserCounts.add(possibleRisers);

    // Doc K per-step pitch: atan(rise / going). The previous formula divided the
    // full height by (risers - 1) goings, inflating the angle and wrongly excluding
    // valid riser configurations near the limit.
    let newPitch = calculateStepPitch(possibleRiserHeight, going);

    if (newPitch <= maxPitch) {
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
// FLIGHT TREAD ALLOCATION (turned / multi-flight staircases)
// =============================================================================

// Per-flight minimum straight-tread counts. These are domain decisions,
// confirmed by Dan (2026-07-14): a staircase MAY open directly onto a winder,
// MAY run winder-into-winder (a six-winder 180 turn), and MAY end on the winder
// box (the last riser lands on the upper floor). So every minimum is 0. They are
// kept as named constants so the rules can be tightened later without touching
// the allocation maths.
const MIN_FLIGHT_FIRST = 0; // treads before the first turn
const MIN_FLIGHT_MID   = 0; // treads between two turns (half turn only)
const MIN_FLIGHT_LAST  = 0; // treads after the final turn

/**
 * Distributes a tread budget as evenly as possible across N straight flights,
 * giving any remainder to the earliest flights. Used for first-load defaults.
 * e.g. distributeFlightTreads(7, 3) -> [3, 2, 2]
 * @param {number} available - treads to distribute
 * @param {number} nFlights  - number of straight flights
 * @returns {number[]} tread count per flight
 */
function distributeFlightTreads(available, nFlights) {
  const total = Math.max(0, parseInt(available, 10) || 0);
  const base = Math.floor(total / nFlights);
  const remainder = total - base * nFlights;
  const out = [];
  for (let i = 0; i < nFlights; i++) {
    out.push(base + (i < remainder ? 1 : 0));
  }
  return out;
}

/**
 * Allocates the tread budget across the straight flights of a turned staircase,
 * honouring per-flight minimums and never producing a negative flight. Pure and
 * deterministic: identical inputs always yield the same allocation.
 *
 * @param {number} budget      total tread units (as computed in grabFormValues)
 * @param {number[]} winders   winder treads per turn, e.g. [tits] or [tits, tits2]
 * @param {number[]} requested user values for the non-derived flights, in order
 *                             (half: [beforeturn, afterturn1]; quarter: [beforeturn])
 * @param {number[]} mins      per-flight minimums INCLUDING the derived last flight
 * @returns {{ flights: number[], clamped: boolean, valid: boolean }}
 *          flights = [...requested, derivedLast]; empty when valid === false.
 */
function allocateFlightTreads(budget, winders, requested, mins) {
  const toInt = (n, fallback) => {
    const v = parseInt(n, 10);
    return isNaN(v) ? fallback : v;
  };

  const winderSum = winders.reduce((sum, w) => sum + toInt(w, 0), 0);
  const available = toInt(budget, 0) - winderSum;
  const minsSum = mins.reduce((sum, m) => sum + m, 0);

  // Structurally impossible: the winders (plus flight minimums) don't fit the
  // tread budget for this riser count.
  if (available < minsSum) {
    return { flights: [], clamped: false, valid: false };
  }

  // Floor each requested flight at its own minimum (NaN -> that flight's min).
  const req = requested.map((n, i) => Math.max(toInt(n, mins[i]), mins[i]));
  const lastMin = mins[mins.length - 1];
  let clamped = false;

  // Derived last flight is whatever budget remains after the user flights.
  let last = available - req.reduce((sum, n) => sum + n, 0);

  // If the last flight would fall below its minimum, reclaim treads from the
  // user flights, taking from the LAST user flight first, never below each min.
  if (last < lastMin) {
    let deficit = lastMin - last;
    for (let i = req.length - 1; i >= 0 && deficit > 0; i--) {
      const take = Math.min(req[i] - mins[i], deficit);
      if (take > 0) { req[i] -= take; deficit -= take; clamped = true; }
    }
    last = available - req.reduce((sum, n) => sum + n, 0);
  }

  return { flights: [...req, last], clamped, valid: true };
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

/**
 * Shows / updates an inline flight-allocation warning placed after a reference
 * input, reusing the red .sb-limit-msg styling that formLogic.js uses for the
 * going/width limits. The message element is created once and then toggled.
 * @param {string} afterId - id of the input to insert the message after
 * @param {string} message - text to show; pass '' to hide
 */
function setFlightAllocationWarning(afterId, message) {
  let el = document.getElementById('sb-flight-msg');
  if (!el) {
    const ref = document.getElementById(afterId);
    if (!ref) return;
    el = document.createElement('p');
    el.id = 'sb-flight-msg';
    el.className = 'sb-limit-msg';
    el.style.cssText = 'display:none;color:#d63638;margin:4px 0 0;font-size:13px;font-weight:600;';
    ref.insertAdjacentElement('afterend', el);
  }
  if (message) {
    el.textContent = message;
    el.style.display = '';
  } else {
    el.style.display = 'none';
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
    bdActiveRegime,
    bdRegimeNum,
    bdRegimeUnregulated,
    bdRegimePitchLimit,
    bdRiserBoard,
    bdTopLipMm,
    bdDisplayTotalRun,
    MIN_FLIGHT_FIRST,
    MIN_FLIGHT_MID,
    MIN_FLIGHT_LAST,
    distributeFlightTreads,
    allocateFlightTreads,
    showSpinner,
    hideSpinner,
    setFlightAllocationWarning,
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
    bdActiveRegime,
    bdRegimeNum,
    bdRegimeUnregulated,
    bdRegimePitchLimit,
    bdRiserBoard,
    bdTopLipMm,
    bdDisplayTotalRun,
    MIN_FLIGHT_FIRST,
    MIN_FLIGHT_MID,
    MIN_FLIGHT_LAST,
    distributeFlightTreads,
    allocateFlightTreads,
    showSpinner,
    hideSpinner,
    setFlightAllocationWarning,
    grab3dValues,
    grabFormValues,
    getNewelIds,
    getCapIds,
    calculateOakBonus,
    validateStairInputs
  };
}