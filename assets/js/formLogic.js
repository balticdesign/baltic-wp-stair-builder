// ==============================
// Baltic Stairbuilder - Form Logic
// ==============================

/**
 * Helpers to extract product IDs for newels and caps based on selected options.
 */
function getNumber(formElementId) {
  return BuilderUtils.getNumber(formElementId);
}
function getNewelIds() {
  const newel_amt = BuilderUtils.getNumber('newel-posts');
  return jQuery('#newel_material option:selected[data-product-id]')
    .map(function () {
      const productId = jQuery(this).data('product-id');
      return productId ? { id: productId, qty: newel_amt } : null;
    })
    .get();
}
function getCapIds() {
  const newel_amt = BuilderUtils.getNumber('newel-posts');
  return jQuery('#cap_material option:selected[data-product-id]')
    .map(function () {
      const productId = jQuery(this).data('product-id');
      return productId ? { id: productId, qty: newel_amt } : null;
    })
    .get();
}

// ==============================
// UI Update: AJAX/Cart/Materials
// ==============================
function updateNewelPosts(newelType, capType, hrType, spindleType) {
  jQuery.ajax({
    url: stairBuilderVars.ajax_url,
    type: 'POST',
    data: {
      action: 'fetch_sp_prices',
      newelType, capType, hrType, spindleType,
      security: stairBuilderVars.nonce
    },
    dataType: 'json',
    success(response) {
      jQuery('#newel_material').html(response.newel_options.join(''));
      jQuery('#cap_material').html(response.cap_options.join(''));
      jQuery('#hdr_material').html(response.handrail_options.join(''));
      // Spindle material/style is now Material-first and resolved client-side
      // (see the bdSpindle* helpers) — no longer injected from this AJAX.
      calculateTotalPrice();
    },
    error(jqXHR, textStatus, errorThrown) {
      console.log('AJAX error:', textStatus, errorThrown);
    }
  });
}

function getDeliveryPrice() {
  const postcode = jQuery('#postcode').val();
  jQuery.ajax({
    url: stairBuilderVars.ajax_url,
    method: 'POST',
    data: {
      action: 'get_delivery_price',
      postcode,
      security: stairBuilderVars.nonce
    },
    success(response) {
      if (response.success) {
        const price = response.data;
        if (jQuery.isNumeric(price)) {
          jQuery('.deliv_btn').text(`Update Delivery (£${price})`).attr('data-price', price);
        } else {
          jQuery('.deliv_btn').text(`Update Delivery (${price})`).attr('data-price', '0');
        }
        calculateTotalPrice();
      }
    }
  });
}

function readPriceFromDOM(selector) {
  const txt = (jQuery(selector).text() || '').replace(/[£,\s]/g, '');
  const n = parseFloat(txt);
  return isNaN(n) ? 0 : n;
}

function submitStairLead() {
  const name = (jQuery('#contact_name').val() || '').trim();
  const email = (jQuery('#contact_email').val() || '').trim();
  const phone = (jQuery('#contact_phone').val() || '').trim();
  const $err = jQuery('#sb-submit-error');
  $err.hide().text('');

  if (!name || !email) {
    $err.text('Please enter your name and email to receive your quote.').show();
    return;
  }

  const formDataArray = jQuery('#stairbuild').serializeArray()
    .filter(f => f.name !== 'contact_name' && f.name !== 'contact_email' && f.name !== 'contact_phone');

  // Un-stripped copy of the fields server-side availability revalidation needs
  // (v2.16.0 Phase 2). Captured BEFORE the colon-strip below, so material values
  // keep their code:price form — code+price identifies the exact row even where
  // the code alone is non-unique (two pine treads at different thicknesses).
  // Storage/PDF/bd_code_label continue to use the stripped codes in custom_meta.
  const REVALIDATE_FIELDS = ['construction_type', 'stringer_material', 'tread_material', 'riser_material', 'tread-profile'];
  const revalidateMeta = jQuery.param(formDataArray.filter(f => REVALIDATE_FIELDS.indexOf(f.name) !== -1));

  formDataArray.forEach(field => {
    const colonIndex = field.value.indexOf(':');
    if (colonIndex !== -1) {
      field.value = field.value.substring(0, colonIndex);
    }
  });
  const formData = jQuery.param(formDataArray);

  const canvas = document.getElementById('canvas');
  const dataUrl = canvas ? canvas.toDataURL('image/png') : '';

  const price = readPriceFromDOM('#priceCalc');
  const vat   = readPriceFromDOM('#vat');
  const total = readPriceFromDOM('#total');

  const $btn = jQuery('#sbbuybtn');
  const originalLabel = $btn.text();
  $btn.prop('disabled', true).text('Generating quote…');

  jQuery.ajax({
    url: stairBuilderVars.ajax_url,
    method: 'POST',
    data: {
      action: 'baltic_stair_submit_lead',
      contact_name: name,
      contact_email: email,
      contact_phone: phone,
      custom_meta: formData,
      revalidate_meta: revalidateMeta,
      canvas_image: dataUrl,
      price, vat, total,
      security: stairBuilderVars.nonce
    },
    success(response) {
      if (response && response.success && response.data && response.data.redirect_url) {
        window.location.href = response.data.redirect_url;
        return;
      }
      const msg = (response && response.data && response.data.message) || 'Sorry, something went wrong submitting your quote. Please try again.';
      $err.text(msg).show();
      $btn.prop('disabled', false).text(originalLabel);
    },
    error() {
      $err.text('Network error — please try again.').show();
      $btn.prop('disabled', false).text(originalLabel);
    }
  });
}

function getFeaturedStepCosts() {
  const variables = grabFormValues();
  const treadMaterial = BuilderUtils.getString('tread_material');
  jQuery.ajax({
    url: stairBuilderVars.ajax_url,
    method: 'POST',
    data: {
      action: 'get_featured_step',
      leftFeat: variables.fl,
      rightFeat: variables.fr,
      tread_material: treadMaterial,
      security: stairBuilderVars.nonce
    },
    success(response) {
      let responseObj = response;
      // Some backends double-encode: fallback to JSON.parse
      if (typeof response === "string") {
        responseObj = JSON.parse(response);
      }
      jQuery("#leftFeatStep").val(responseObj.leftCost);
      jQuery("#rightFeatStep").val(responseObj.rightCost);
      calculateTotalPrice();
    }
  });
}

function resetInputs() {
  jQuery(".ksd input[type='text'], .pcode input[type='text']").val('');
  jQuery(".ksd input[type='checkbox'], .pcode input[type='checkbox']").prop('checked', false);
}

function setMaterial(material) {
  // Material selects now live across the Material and Posts & Balustrades tabs,
  // so target them by shared class rather than by container.
  jQuery('.bd-mat-select').each(function () {
    jQuery(this).find(`option:contains(${material})`).prop('selected', true);
  });
  // The spindle Material select drives Style + price resolution, which only runs
  // on change — so nudge it after a quick-set so the spindle price stays correct.
  jQuery('#bal_material').trigger('change');
}

// ==============================
// SPINDLE BALUSTRADING (Material-first)
// ==============================
// Customer picks Spindle Material (Pine/Oak/Metal/Glass), which filters the
// Spindle Style list to that material's rows (collapsing to a hidden field when
// only one style exists). Price + mode + glass basis are resolved client-side
// from the localised catalogue and baked into the selected #bal_material option
// so priceCalc.js reads them exactly as before (value "key:price" + data-attrs).

function bdSpindleRows() {
  return (window.stairBuilderVars && Array.isArray(stairBuilderVars.spindles)) ? stairBuilderVars.spindles : [];
}

// Materials available given the defined rows. Pine + Oak both come from wood rows.
function bdAvailableMaterials() {
  const rows = bdSpindleRows();
  const has = (m) => rows.some((r) => r.mode === m);
  const mats = [];
  if (has('wood')) {
    mats.push({ key: 'pine', label: 'Pine', mode: 'wood', field: 'pine' });
    mats.push({ key: 'oak', label: 'Oak', mode: 'wood', field: 'oak' });
  }
  if (has('metal')) mats.push({ key: 'metal', label: 'Metal', mode: 'metal', field: 'metal' });
  if (has('glass')) mats.push({ key: 'glass', label: 'Glass', mode: 'glass', field: 'glass' });
  return mats;
}

function bdMaterialByKey(key) {
  return bdAvailableMaterials().find((m) => m.key === key) || null;
}

function bdSelectedMaterialKey() {
  const v = jQuery('#bal_material').val();
  return v ? String(v).split(':')[0] : '';
}

// Fill the Material select (preserving the current choice when still valid).
function bdPopulateMaterials() {
  const mats = bdAvailableMaterials();
  const $sel = jQuery('#bal_material');
  const prev = bdSelectedMaterialKey();
  $sel.empty();
  mats.forEach((m) => $sel.append('<option value="' + m.key + '">' + m.label + '</option>'));
  if (prev && mats.some((m) => m.key === prev)) $sel.val(prev);
}

// Fill the Style select with the chosen material's rows; collapse to a hidden
// single value when there is one (or zero) style for that material.
function bdPopulateStyles() {
  const mat = bdMaterialByKey(bdSelectedMaterialKey());
  const $row = jQuery('#spindle_style_row');
  const $sel = jQuery('#spindle_type');
  if (!mat) { $sel.empty(); $row.hide(); return; }
  const styles = bdSpindleRows().filter((r) => r.mode === mat.mode);
  const prev = $sel.val();
  $sel.empty();
  styles.forEach((r) => $sel.append('<option value="' + r.code + '">' + r.name + '</option>'));
  if (prev && styles.some((r) => r.code === prev)) $sel.val(prev);
  if (styles.length <= 1) $row.hide(); else $row.show();
}

// Resolve price + mode (+ glass basis) for the current (material, style) and bake
// it into the selected #bal_material option that priceCalc.js reads.
function bdResolveSpindle() {
  const mat = bdMaterialByKey(bdSelectedMaterialKey());
  const $opt = jQuery('#bal_material option:selected');
  if (!mat || !$opt.length) return;
  const code = jQuery('#spindle_type').val();
  const rows = bdSpindleRows().filter((r) => r.mode === mat.mode);
  const row = rows.find((r) => r.code === code) || rows[0] || null;
  const price = row ? (parseFloat(row[mat.field]) || 0) : 0;
  $opt.attr('value', mat.key + ':' + price);
  $opt.attr('data-material-mode', mat.mode);
  if (mat.mode === 'glass' && row) {
    $opt.attr('data-pricing-unit', row.pricing_unit || 'per_metre');
    $opt.attr('data-panel-width', row.panel_width || 0);
    $opt.attr('data-panel-gap', row.panel_gap || 0);
  } else {
    $opt.removeAttr('data-pricing-unit').removeAttr('data-panel-width').removeAttr('data-panel-gap');
  }
}

function bdInitSpindle() {
  bdPopulateMaterials();
  bdPopulateStyles();
  bdResolveSpindle();
  jQuery('#bal_material').on('change', function () {
    bdPopulateStyles();
    bdResolveSpindle();
    if (typeof calculateTotalPrice === 'function') calculateTotalPrice();
  });
  jQuery('#spindle_type').on('change', function () {
    bdResolveSpindle();
    if (typeof calculateTotalPrice === 'function') calculateTotalPrice();
  });
}

// ==============================
// DOM READY & EVENT HOOKS
// ==============================
jQuery(document).ready(function () {
  bdInitSpindle();
  jQuery('#ball').hide();
  jQuery('#ball :input').prop('disabled', true);

  // Initial population
  let newelType = jQuery('#newel_type').val();
  let spindleType = jQuery('#spindle_type').val();
  let hrType = jQuery('#handrail_type').val();
  let capType = BuilderUtils.getString('newel_cap');
  updateNewelPosts(newelType, capType, hrType, spindleType);

  if (jQuery("#collected").is(":checked")) {
    jQuery(".ksd").hide();
    jQuery(".pcode").hide();
  }

  jQuery('#newel-posts option[value="custom:0"]').val("custom:0");
  getNewelIds();

  // Delivery update click
  jQuery('.deliv_btn').click(function (e) {
    e.preventDefault();
    getDeliveryPrice();
  });

  // Lead submission (replaces legacy WC add-to-cart)
  jQuery('#sbbuybtn').click(function (e) {
    e.preventDefault();
    submitStairLead();
  });

  // All oak/pine bulk set
  jQuery('#all_oak').click(function (e) {
    e.preventDefault();
    setMaterial("Oak");
    calculateTotalPrice();
  });
  jQuery('#all_pine').click(function (e) {
    e.preventDefault();
    setMaterial("Pine");
    calculateTotalPrice();
  });
});

jQuery('#feature_tread').on('change', getFeaturedStepCosts);

// Newel posts, ballustrade, custom UI/logic
jQuery('#posts :input').change(function () {
  let newelValue = BuilderUtils.getString('newel-posts');
  if (newelValue === 'custom') {
    jQuery('#custom').show();
    jQuery('#custom :input').prop('disabled', false);
  } else {
    jQuery('#custom').hide();
    jQuery('#custom :input').prop('disabled', true);
  }
  // Recalculate number of checked custom posts and update
  let numChecked = jQuery('#custom :checkbox:checked').length;
  let customValue = 'custom:' + Math.min(numChecked, 7);
  jQuery('#newel-posts').find('option').filter(function () {
    return this.value.startsWith('custom:');
  }).val(customValue);
  getNewelIds();
  let newelType = jQuery('#newel_type').val();
  let spindleType = jQuery('#spindle_type').val();
  let hrType = jQuery('#handrail_type').val();
  let capType = BuilderUtils.getString('newel_cap');

  // Ballustrade show/hide
  if (jQuery("#ballustrades-yes").is(":checked")) {
    jQuery('#ball').show();
    jQuery('#ball :input').prop('disabled', false);
  } else {
    jQuery('#ball').hide();
    jQuery('#ball :input').prop('disabled', true);
  }

  updateNewelPosts(newelType, capType, hrType, spindleType);
});

// Delivery options toggle
jQuery("input[name='delivery']").change(function () {
  if (jQuery("#collected").is(":checked")) {
    jQuery(".ksd").hide();
    jQuery(".pcode").hide();
    resetInputs();
    getDeliveryPrice();
  } else if (jQuery("#delivery").is(":checked")) {
    jQuery(".ksd").show();
    jQuery(".pcode").show();
  }
});

// ==============================
// Construction limits: building-regs warning (Going turns red) + hard
// maximums for Going and Width. All admin-configured via the "Construction
// Settings" tab and passed through stairBuilderVars.construction.
// ==============================
(function () {
  const cfg = (window.stairBuilderVars && stairBuilderVars.construction) || {};

  const warnEnabled =
    cfg.going_warning_enabled === true ||
    cfg.going_warning_enabled === 1 ||
    cfg.going_warning_enabled === '1';
  const warnMin  = parseFloat(cfg.going_warning_min);
  const warnMax  = parseFloat(cfg.going_warning_max);
  const goingMax = parseFloat(cfg.going_max); // NaN => no hard limit
  const widthMax = parseFloat(cfg.width_max); // NaN => no hard limit

  const WIDTH_IDS = ['stair-width', 'stair-width2', 'stair-width3'];

  function buildMsg(custom, fallback, maxVal) {
    const text = (custom && String(custom).trim()) || fallback;
    return text.replace(/\{max\}/g, isNaN(maxVal) ? '' : maxVal);
  }
  const goingMsg = buildMsg(cfg.going_max_message, 'Maximum going is {max}mm.', goingMax);
  const widthMsg = buildMsg(cfg.width_max_message, 'Maximum width is {max}mm.', widthMax);

  // Create a hidden red message element immediately after the given input.
  function makeMsgEl(afterInput) {
    const el = document.createElement('p');
    el.className = 'sb-limit-msg';
    el.style.cssText = 'display:none;color:#d63638;margin:4px 0 0;font-size:13px;font-weight:600;';
    afterInput.insertAdjacentElement('afterend', el);
    return jQuery(el);
  }

  jQuery(function () {
    const goingInput = document.getElementById('going');
    if (!goingInput) return;

    const $going = jQuery(goingInput);
    const $goingMsg = makeMsgEl(goingInput);

    // One shared width message, placed after the last width field present.
    let lastWidthEl = null;
    WIDTH_IDS.forEach(function (id) {
      const el = document.getElementById(id);
      if (el) lastWidthEl = el;
    });
    const $widthMsg = lastWidthEl ? makeMsgEl(lastWidthEl) : jQuery();

    // v2.16.0 Phase 1: resolve the EFFECTIVE limits from the active regime,
    // falling back to the global Construction Settings. Recomputed each call so
    // changing #building_regs re-applies immediately.
    //   - Soft warn-min for going = regime.min_going (else global warn-min).
    //   - Hard maxima (going/width) are RAISED so a regime minimum can't deadlock
    //     them (§2): if regime.min_going 280 > global going hard-max 250, the
    //     effective going max becomes 280 so the user can actually reach it.
    function num(v) { const n = parseFloat(v); return isNaN(n) ? null : n; }
    function regimeLimits() {
      const regime = (window.BuilderUtils && BuilderUtils.bdActiveRegime) ? BuilderUtils.bdActiveRegime() : null;
      const rMinGoing = regime ? num(regime.min_going) : null;
      const rMinWidth = regime ? num(regime.min_width) : null;
      let effGoingMax = isNaN(goingMax) ? null : goingMax;
      if (effGoingMax !== null && rMinGoing !== null && rMinGoing > effGoingMax) effGoingMax = rMinGoing;
      let effWidthMax = isNaN(widthMax) ? null : widthMax;
      if (effWidthMax !== null && rMinWidth !== null && rMinWidth > effWidthMax) effWidthMax = rMinWidth;
      return {
        warnMin: (rMinGoing !== null) ? rMinGoing : (isNaN(warnMin) ? null : warnMin),
        warnMax: isNaN(warnMax) ? null : warnMax,
        goingMax: effGoingMax,
        minWidth: rMinWidth,
        widthMax: effWidthMax,
      };
    }

    function applyGoing() {
      const lim = regimeLimits();
      let v = parseFloat($going.val());
      // Hard maximum — clamp and show message.
      if (lim.goingMax !== null && !isNaN(v) && v > lim.goingMax) {
        v = lim.goingMax;
        $going.val(lim.goingMax);
        $goingMsg.text(buildMsg(cfg.going_max_message, 'Maximum going is {max}mm.', lim.goingMax)).show();
      } else {
        $goingMsg.hide();
      }
      // Soft building-regs warning — colour the field red, value still allowed.
      const outOfRange =
        warnEnabled && !isNaN(v) &&
        ((lim.warnMin !== null && v < lim.warnMin) || (lim.warnMax !== null && v > lim.warnMax));
      $going.css('color', outOfRange ? 'red' : 'inherit');
    }

    function applyWidth(input) {
      const lim = regimeLimits();
      const $w = jQuery(input);
      const v = parseFloat($w.val());
      if (lim.widthMax !== null && !isNaN(v) && v > lim.widthMax) {
        $w.val(lim.widthMax);
        $widthMsg.text(buildMsg(cfg.width_max_message, 'Maximum width is {max}mm.', lim.widthMax)).show();
      } else {
        $widthMsg.hide();
      }
      // Soft regime min-width warning — value still allowed.
      const belowMin = lim.minWidth !== null && !isNaN(v) && v < lim.minWidth;
      $w.css('color', belowMin ? 'red' : 'inherit');
    }

    $going.on('input change', applyGoing);
    WIDTH_IDS.forEach(function (id) {
      const el = document.getElementById(id);
      if (el) jQuery(el).on('input change', function () { applyWidth(this); });
    });
    // Regime description shown under the #building_regs select.
    function applyRegimeDesc() {
      const $d = jQuery('#building_regs_desc');
      if (!$d.length) return;
      const regime = (window.BuilderUtils && BuilderUtils.bdActiveRegime) ? BuilderUtils.bdActiveRegime() : null;
      $d.text(regime && regime.description ? regime.description : '');
    }

    // Re-apply when the building-regs regime changes.
    jQuery('#building_regs').on('change', function () {
      applyGoing();
      WIDTH_IDS.forEach(function (id) {
        const el = document.getElementById(id);
        if (el) applyWidth(el);
      });
      applyRegimeDesc();
    });

    // Initial pass (after the flight script has seeded default values).
    applyGoing();
    applyRegimeDesc();
  });
})();

// ============================================================
// CONSTRUCTION AVAILABILITY FILTER (v2.16.0 Phase 2)
// Filters material/profile <option>s by the selected construction type per the
// strict_for / available_for resolution rule (§3.4). FULLY GENERIC — no
// construction code is special-cased; a licensee with zero tags gets pre-v2.16
// behaviour exactly (untagged rows stay available, no strict repeaters).
// available_for travels on each option as data-available-for, so no row identity
// is needed (tread/riser codes are non-unique). Snapshot-at-init is safe: the
// filter is the only code path that rebuilds these selects (setMaterial only sets
// `selected`; the flight scripts only read them).
// ============================================================
(function () {
  const av = (window.stairBuilderVars && stairBuilderVars.availability) || null;
  if (!av || !av.repeater_select) return;
  const strictMap = av.strict_for || {};
  const selectMap = av.repeater_select; // repeaterId -> '#selectId'

  jQuery(function () {
    const $ct = jQuery('#construction_type');
    if (!$ct.length) return;

    const snapshots = {}; // repeaterId -> [{value,text,tags[],dataPrice}]
    const msgEls    = {}; // repeaterId -> jQuery .sb-limit-msg

    function ctName(code) {
      const $o = $ct.find('option[value="' + code + '"]');
      return $o.length ? $o.text().trim() : '';
    }
    function optionAllowed(opt, ctCode, strict) {
      // strict: only rows tagged for this construction. permissive: untagged OR tagged.
      if (strict) return opt.tags.indexOf(ctCode) !== -1;
      return opt.tags.length === 0 || opt.tags.indexOf(ctCode) !== -1;
    }

    // Snapshot each managed select's full option set + wrap it so a stated line
    // can sit beside it, and give it a hidden limit-message element.
    Object.keys(selectMap).forEach(function (repId) {
      const $sel = jQuery(selectMap[repId]);
      if (!$sel.length) return;
      snapshots[repId] = $sel.find('option').map(function () {
        const $o  = jQuery(this);
        const raw = ($o.attr('data-available-for') || '').trim();
        return {
          value: $o.attr('value'),
          text: $o.text().replace(/\s+/g, ' ').trim(),
          tags: raw ? raw.split(',').map(s => s.trim()).filter(Boolean) : [],
          dataPrice: $o.attr('data-price'),
        };
      }).get();
      if (!$sel.parent().hasClass('bd-avail-wrap')) {
        $sel.wrap('<span class="bd-avail-wrap"></span>');
        $sel.after('<span class="bd-avail-stated" aria-live="polite"></span>');
      }
      const $m = jQuery('<p class="sb-limit-msg" style="display:none;color:#d63638;margin:4px 0 0;font-size:13px;font-weight:600;"></p>');
      $sel.parent().after($m);
      msgEls[repId] = $m;
    });

    function rebuild(repId, ctCode) {
      const $sel = jQuery(selectMap[repId]);
      const snap = snapshots[repId];
      if (!$sel.length || !snap) return;
      const strict  = (strictMap[ctCode] || []).indexOf(repId) !== -1;
      const prev    = $sel.val();
      const allowed = snap.filter(o => optionAllowed(o, ctCode, strict));
      const $wrap   = $sel.parent();
      const $stated = $wrap.find('.bd-avail-stated');

      // Filter individual options by REBUILDING (removed, not disabled/CSS-hidden,
      // so nothing invalid can POST).
      $sel.empty();
      allowed.forEach(function (o) {
        const $o = jQuery('<option>').attr('value', o.value).text(o.text);
        if (o.dataPrice !== undefined) $o.attr('data-price', o.dataPrice);
        $sel.append($o);
      });

      // Preserve the selection if still valid, else fall back to the first option
      // and flag a forced change (§6.2).
      let forced = false;
      if (allowed.some(o => o.value === prev)) {
        $sel.val(prev);
      } else {
        $sel.val(allowed.length ? allowed[0].value : '');
        if (prev && prev !== '' && allowed.length) forced = true;
      }

      // Exactly one option → a stated line, not an interactive one-option select.
      // The select stays in the DOM (hidden via class) so it still POSTs and
      // priceCalc reads it unchanged; the stated line is the visible treatment.
      // Distinguish "one because strictness filtered the rest" (show the reason)
      // from "one because the client configured a single row" (no reason).
      if (allowed.length === 1) {
        const filtered = strict && snap.length > 1;
        $sel.addClass('bd-avail-collapsed');
        const label  = $wrap.closest('.form-row').find('label').first().text().replace(':', '').trim();
        const reason = filtered ? ' · required for ' + (ctName(ctCode) || 'this construction') : '';
        $stated.attr('data-bd-reason', filtered ? 'filtered' : 'catalogue')
               .text((label ? label + ' — ' : '') + allowed[0].text + reason)
               .addClass('is-shown');
      } else {
        $sel.removeClass('bd-avail-collapsed');
        $stated.removeClass('is-shown').removeAttr('data-bd-reason').text('');
      }

      const $m = msgEls[repId];
      if ($m) {
        if (forced) { $m.text('Your selection isn’t available for this construction — updated to the closest option.').show(); }
        else { $m.hide(); }
      }
      if (forced) $sel.trigger('change'); // priceCalc reads prices off the markup; recalc
      return allowed.length;
    }

    function applyAll() {
      const ctCode = $ct.val();
      Object.keys(selectMap).forEach(function (repId) { rebuild(repId, ctCode); });
    }

    // A construction type whose STRICT repeater has zero rows tagged for it is
    // unbuildable, so it is not offered (§4.1/§6.1). The paired admin-facing
    // warning lives server-side (settings screen), so the reason is never silent.
    $ct.find('option').each(function () {
      const $o   = jQuery(this);
      const code = $o.attr('value');
      const empties = (strictMap[code] || []).some(function (repId) {
        const snap = snapshots[repId];
        return snap && !snap.some(o => o.tags.indexOf(code) !== -1);
      });
      if (empties) $o.remove();
    });

    $ct.on('change', applyAll);
    applyAll();
  });
})();