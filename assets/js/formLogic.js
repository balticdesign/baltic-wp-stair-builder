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
      jQuery('#bal_material').html(response.spindle_options.join(''));
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

function addSBtoCart() {
  const price = $total;
  const formDataArray = jQuery('#stairbuild').serializeArray();
  const newelIds = getNewelIds();
  const capIds = getCapIds();

  // Remove any :price suffix from values before submitting
  formDataArray.forEach(field => {
    const colonIndex = field.value.indexOf(':');
    if (colonIndex !== -1) {
      field.value = field.value.substring(0, colonIndex);
    }
  });
  const formData = jQuery.param(formDataArray);

  const canvas = document.getElementById("canvas");
  const dataUrl = canvas ? canvas.toDataURL("image/png") : '';

  jQuery.ajax({
    url: stairBuilderVars.ajax_url,
    method: 'POST',
    data: {
      action: 'custom_add_to_cart',
      product_id: 7024,
      custom_price: price,
      custom_meta: formData,
      newel_product_ids: newelIds,
      cap_product_ids: capIds,
      canvas_image: dataUrl,
      security: stairBuilderVars.nonce
    },
    success(response) {
      if (response.success) {
        jQuery(document.body).trigger('wc_update_cart');
        jQuery(document.body).trigger('wc_fragment_refresh');
        window.location.href = stairBuilderVars.cart_url;
      } else {
        console.log("Failed to add product to cart");
      }
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
  jQuery('#mat select').each(function () {
    jQuery(this).find(`option:contains(${material})`).prop('selected', true);
  });
}

// ==============================
// DOM READY & EVENT HOOKS
// ==============================
jQuery(document).ready(function () {
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

  // Cart add
  jQuery('#sbbuybtn').click(function (e) {
    e.preventDefault();
    addSBtoCart();
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

jQuery(document.body).on('wc_fragments_loaded', function () {
  console.log("Cart fragments loaded");
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