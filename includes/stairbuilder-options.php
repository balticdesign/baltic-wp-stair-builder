<?php
/**
 * Pricing-related AJAX endpoints + helpers used by the configurator.
 *
 * This file used to host a WooCommerce add-to-cart pipeline. That has been
 * replaced by the lead-capture flow in stairbuilder-lead-capture.php; only
 * the price-lookup helpers and option-driven endpoints survive here.
 *
 * VAT shortcode `[vat_rate]` lives in stairbuilder-lead-capture.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Find a repeater row by its `code`, falling back to the first row when the
 * code is missing or not found. Returns null only for an empty/invalid set.
 *
 * @param array  $rows Repeater rows (each with at least a `code`).
 * @param string $code Selected code from the form.
 * @return array|null
 */
function bd_sb_find_row($rows, $code) {
  if (!is_array($rows) || empty($rows)) {
    return null;
  }
  foreach ($rows as $row) {
    if (is_array($row) && isset($row['code']) && (string) $row['code'] === (string) $code) {
      return $row;
    }
  }
  // Sensible default = first row. For caps a "none"/unknown code is harmless:
  // the qty multiplier (0) zeroes the cap cost regardless of which row's
  // price is returned, preserving the calc contract.
  return is_array($rows[0]) ? $rows[0] : null;
}

/**
 * Build the pine/oak <option> pair for a material select from a repeater row.
 *
 * Output contract is unchanged from the legacy flat-key version — the price is
 * encoded as `value="pine:PRICE"` with the WC id in data-product-id — so
 * priceCalc.js needs no changes. When the row's per-row Use-Product-ID switch
 * is on, the live WooCommerce product price overrides the direct price.
 *
 * @param array|null $row Resolved repeater row.
 * @return string[] [pine <option>, oak <option>]
 */
function getPriceAndID($row){
  $pine_id = $oak_id = null;

  if (!is_array($row)) {
    return [
      '<option data-product-id="" value="pine:0">Pine</option>',
      '<option data-product-id="" value="oak:0">Oak</option>'
    ];
  }

  $pinePrice = isset($row['pine_price']) ? $row['pine_price'] : 0;
  $oakPrice  = isset($row['oak_price'])  ? $row['oak_price']  : 0;

  if (!empty($row['use_product_id'])) {
    $pine_id = isset($row['pine_id']) ? $row['pine_id'] : null;
    $oak_id  = isset($row['oak_id'])  ? $row['oak_id']  : null;

    $pine_product = function_exists('wc_get_product') ? wc_get_product($pine_id) : null;
    if ($pine_product) { $pinePrice = $pine_product->get_price(); }

    $oak_product = function_exists('wc_get_product') ? wc_get_product($oak_id) : null;
    if ($oak_product) { $oakPrice = $oak_product->get_price(); }
  }

  if ($pinePrice === '' || $pinePrice === null) { $pinePrice = 0; }
  if ($oakPrice === '' || $oakPrice === null) { $oakPrice = 0; }

  return [
    '<option data-product-id="'.$pine_id.'" value="pine:' . $pinePrice . '">' . 'Pine' . '</option>',
    '<option data-product-id="'.$oak_id.'" value="oak:' . $oakPrice . '">' . 'Oak' . '</option>'
  ];
}

function fetch_sp_prices() {
  if (!isset($_POST['security'])) {
    wp_send_json_error('Nonce not received');
  }
  if (!wp_verify_nonce($_POST['security'], 'sb-ajax-nonce')) {
    wp_send_json_error('Nonce verification failed');
  }

  $newelType   = isset($_POST['newelType'])   ? sanitize_text_field(wp_unslash($_POST['newelType']))   : '';
  $capType     = isset($_POST['capType'])      ? sanitize_text_field(wp_unslash($_POST['capType']))     : '';
  $hrType      = isset($_POST['hrType'])       ? sanitize_text_field(wp_unslash($_POST['hrType']))      : '';
  $spindleType = isset($_POST['spindleType'])  ? sanitize_text_field(wp_unslash($_POST['spindleType'])) : '';

  // Component pricing is now driven by admin-managed repeater rows. Each row
  // carries its own name/code, per-row Use-Product-ID switch, and pine/oak
  // price + product ID — resolved by code (default = first row).
  $newel_rows    = stairbuilder_get_option('newel_types', array());
  $cap_rows      = stairbuilder_get_option('cap_types', array());
  $handrail_rows = stairbuilder_get_option('handrail_types', array());
  $spindle_rows  = stairbuilder_get_option('spindle_types', array());

  $form_options = array(
    'newel_options'    => getPriceAndID(bd_sb_find_row($newel_rows, $newelType)),
    'cap_options'      => getPriceAndID(bd_sb_find_row($cap_rows, $capType)),
    'handrail_options' => getPriceAndID(bd_sb_find_row($handrail_rows, $hrType)),
    'spindle_options'  => getPriceAndID(bd_sb_find_row($spindle_rows, $spindleType)),
  );

  echo json_encode($form_options);
  wp_die();
}
add_action('wp_ajax_fetch_sp_prices', 'fetch_sp_prices');
add_action('wp_ajax_nopriv_fetch_sp_prices', 'fetch_sp_prices');

function get_stepCost($featNumber, $material) {
  if ($featNumber == 0) {
    return 0;
  }
  $stepMaterialPrices = [
    ['mdf_bullnose_price', 'ply_bullnose_price', 'pine_bullnose_price', 'oak_bullnose_price'],
    ['mdf_curtail_price', 'ply_curtail_price', 'pine_curtail_price', 'oak_curtail_price'],
    ['mdf_dbl_curtail_price', 'ply_dbl_curtail_price', 'pine_dbl_curtail_price', 'oak_dbl_curtail_price'],
    ['mdf_dcb_curtail_price', 'ply_dcb_curtail_price', 'pine_dcb_curtail_price', 'oak_dcb_curtail_price']
  ];

  $materialIndex = ['mdf' => 0, 'ply' => 1, 'pine' => 2, 'oak' => 3][$material];

  $price = stairbuilder_get_option($stepMaterialPrices[$featNumber - 1][$materialIndex]);

  return $price;
}

function get_featured_step() {
  $treadMaterial = $_POST['tread_material'];
  $leftStep = $_POST['leftFeat'];
  $rightStep = $_POST['rightFeat'];

  $leftCost = get_stepCost($leftStep, $treadMaterial);
  $rightCost = get_stepCost($rightStep, $treadMaterial);

  $feat_options = array(
    'leftCost' => $leftCost,
    'rightCost' => $rightCost
  );
  echo json_encode($feat_options);
  wp_die();
}
add_action('wp_ajax_get_featured_step', 'get_featured_step');
add_action('wp_ajax_nopriv_get_featured_step', 'get_featured_step');

add_action( 'wp_ajax_nopriv_get_delivery_price', 'get_delivery_price' );
add_action( 'wp_ajax_get_delivery_price', 'get_delivery_price' );

function get_delivery_price() {
  if (!isset($_POST['security'])) {
    wp_send_json_error('Nonce not received');
  }
  if (!wp_verify_nonce($_POST['security'], 'sb-ajax-nonce')) {
    wp_send_json_error('Nonce verification failed');
  }

  $postcode = strtoupper($_POST['postcode']);
  $greaterLondonPostcodesString = stairbuilder_get_option('greater_london_pcodes');
  $mainlandUKPostcodesString = stairbuilder_get_option('mainland_uk_pcodes');
  $greaterLondonDeliveryPrice = stairbuilder_get_option('greater_london_delivery_price');
  $mainlandUKDeliveryPrice = stairbuilder_get_option('mainland_uk_delivery_price');

  $deliveryPrice = null;

  $greaterLondonPostcodes = array_map('trim', explode(",", $greaterLondonPostcodesString));
  $mainlandUKPostcodes = array_map('trim', explode(",", $mainlandUKPostcodesString));

  $postcodePrefix = substr($postcode, 0, 2);

  if (in_array($postcodePrefix, $greaterLondonPostcodes)) {
    $deliveryPrice = $greaterLondonDeliveryPrice;
  } else if (in_array($postcodePrefix, $mainlandUKPostcodes)) {
    $deliveryPrice = $mainlandUKDeliveryPrice;
  } else {
    $deliveryPrice = 'Invalid Postcode';
  }

  wp_send_json_success($deliveryPrice);
}
