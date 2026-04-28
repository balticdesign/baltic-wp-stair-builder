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

function getPriceAndID($optionsMap, $type){
  $pine_id = $oak_id = null;
  if (array_key_exists($type, $optionsMap)) {
    $option = $optionsMap[$type];
    if (!$option['option']) {
        $pinePrice = stairbuilder_get_option($option['price']['pine']);
        $oakPrice = stairbuilder_get_option($option['price']['oak']);
    } else {
        $pine_id = stairbuilder_get_option($option['id']['pine']);
        $oak_id = stairbuilder_get_option($option['id']['oak']);

        $pine_product = function_exists('wc_get_product') ? wc_get_product($pine_id) : null;
        $pinePrice = $pine_product ? $pine_product->get_price() : stairbuilder_get_option($option['price']['pine']);

        $oak_product = function_exists('wc_get_product') ? wc_get_product($oak_id) : null;
        $oakPrice = $oak_product ? $oak_product->get_price() : stairbuilder_get_option($option['price']['oak']);
    }
  } else {
    $pinePrice = stairbuilder_get_option($optionsMap['default']['price']['pine']);
    $oakPrice = stairbuilder_get_option($optionsMap['default']['price']['oak']);
  }

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

  $newelType = $_POST['newelType'];
  $capType = $_POST['capType'];
  $hrType = $_POST['hrType'];
  $spindleType = $_POST['spindleType'];

  $newel_options = [
    'square' => [
        'option' => stairbuilder_get_option('square_newel_option'),
        'id' => ['pine' => 'pine_square_newel_post_id', 'oak' => 'oak_newel_post_id'],
        'price' => ['pine' => 'pine_newel_post_price', 'oak' => 'oak_newel_post_price']
    ],
    'chamfered' => [
        'option' => stairbuilder_get_option('chamfered_newel_option'),
        'id' => ['pine' => 'pine_chmf_newel_post_id', 'oak' => 'oak_chmf_newel_post_id'],
        'price' => ['pine' => 'pine_chmf_newel_price', 'oak' => 'oak_chmf_newel_price']
    ],
    'turned' => [
        'option' => stairbuilder_get_option('turned_newel_option'),
        'id' => ['pine' => 'pine_trn_newel_id', 'oak' => 'oak_trn_newel_id'],
        'price' => ['pine' => 'pine_trn_newel_price', 'oak' => 'oak_trn_newel_price']
    ],
    'base' => [
        'option' => stairbuilder_get_option('newel_base_option'),
        'id' => ['pine' => 'pine_newel_base_id', 'oak' => 'oak_newel_base_id'],
        'price' => ['pine' => 'pine_newel_base_price', 'oak' => 'oak_newel_base_price']
    ],
    'default' => [
        'option' => null,
        'id' => ['pine' => null, 'oak' => null],
        'price' => ['pine' => 'pine_newel_post_price', 'oak' => 'oak_newel_post_price']
    ]
  ];

  $cap_options = [
    'pyramid' => [
      'option' => stairbuilder_get_option('pyr_cap_option'),
      'id' => ['pine' => 'pine_pyramid_cap_id', 'oak' => 'oak_pyramid_cap_id'],
      'price' => ['pine' => 'pine_pyramid_cap_price', 'oak' => 'oak_pyramid_cap_price']
    ],
    'ball' => [
      'option' => stairbuilder_get_option('ball_cap_option'),
      'id' => ['pine' => 'pine_ball_cap_id', 'oak' => 'oak_ball_cap_id'],
      'price' => ['pine' => 'pine_ball_cap_price', 'oak' => 'oak_ball_cap_price']
    ],
    'flat' => [
      'option' => stairbuilder_get_option('flat_cap_option'),
      'id' => ['pine' => 'pine_flat_cap_id', 'oak' => 'oak_flat_cap_id'],
      'price' => ['pine' => 'pine_flat_cap_price', 'oak' => 'oak_flat_cap_price']
    ],
    'default' => [
      'option' => null,
      'id' => ['pine' => null, 'oak' => null],
      'price' => ['pine' => 'pine_pyramid_cap_price', 'oak' => 'oak_pyramid_cap_price']
    ]
  ];

  $handrail_options = [
    'crown' => [
      'option' => stairbuilder_get_option('crwn_hand_option'),
      'id' => ['pine' => 'pine_crwn_hand_id', 'oak' => 'oak_crwn_hand_id'],
      'price' => ['pine' => 'pine_crwn_hand_price', 'oak' => 'oak_crwn_hand_price']
    ],
    'hdr' => [
      'option' => stairbuilder_get_option('hdr_hand_option'),
      'id' => ['pine' => 'pine_hdr_hand_id', 'oak' => 'oak_hdr_hand_id'],
      'price' => ['pine' => 'pine_hdr_hand_price', 'oak' => 'oak_hdr_hand_price']
    ],
    'default' => [
      'option' => null,
      'id' => ['pine' => null, 'oak' => null],
      'price' => ['pine' => 'pine_crwn_hand_price', 'oak' => 'oak_crwn_hand_price']
    ]
  ];

  $spindleMap = [
    'chamfered' => [
      'option' => stairbuilder_get_option('chmf_spin_option'),
      'id' => ['pine' => 'pine_chmf_spindle_price_id', 'oak' => 'oak_chmf_spindle_id'],
      'price' => ['pine' => 'pine_chmf_spindle_price', 'oak' => 'oak_chmf_spindle_price']
    ],
    'edwardian' => [
      'option' => stairbuilder_get_option('edwa_spin_option'),
      'id' => ['pine' => 'pine_edwa_spindle_id', 'oak' => 'oak_edwa_spindle_id'],
      'price' => ['pine' => 'pine_edwa_spindle_price', 'oak' => 'oak_edwa_spindle_price']
    ],
    'twist' => [
      'option' => stairbuilder_get_option('twist_spin_option'),
      'id' => ['pine' => 'pine_twist_spindle_id', 'oak' => 'oak_twist_spindle_id'],
      'price' => ['pine' => 'pine_twist_spindle_price', 'oak' => 'oak_twist_spindle_price']
    ],
    'flute' => [
      'option' => stairbuilder_get_option('flute_spin_option'),
      'id' => ['pine' => 'pine_flt_spindle_id', 'oak' => 'oak_flt_spindle_id'],
      'price' => ['pine' => 'pine_flt_spindle_price', 'oak' => 'oak_flt_spindle_price']
    ],
    'tulip' => [
      'option' => stairbuilder_get_option('tulip_spin_option'),
      'id' => ['pine' => 'pine_tlp_spindle_id', 'oak' => 'oak_tlp_spindle_id'],
      'price' => ['pine' => 'pine_tlp_spindle_price', 'oak' => 'oak_tlp_spindle_price']
    ],
    'victorian' => [
      'option' => stairbuilder_get_option('vtrn_spin_option'),
      'id' => ['pine' => 'pine_vtrn_spindle_id', 'oak' => 'oak_vtrn_spindle_id'],
      'price' => ['pine' => 'pine_vtrn_spindle_price', 'oak' => 'oak_vtrn_spindle_price']
    ],
    'provincial' => [
      'option' => stairbuilder_get_option('prv_spin_option'),
      'id' => ['pine' => 'pine_prv_spindle_id', 'oak' => 'oak_prv_spindle_id'],
      'price' => ['pine' => 'pine_prv_spindle_price', 'oak' => 'oak_prv_spindle_price']
    ],
    'default' => [
      'option' => null,
      'id' => ['pine' => null, 'oak' => null],
      'price' => ['pine' => 'pine_chmf_spindle_price', 'oak' => 'oak_chmf_spindle_price']
    ]
  ];

  $form_options = array(
    'newel_options' => getPriceAndID($newel_options, $newelType),
    'cap_options' => getPriceAndID($cap_options, $capType),
    'handrail_options' => getPriceAndID($handrail_options, $hrType),
    'spindle_options' => getPriceAndID($spindleMap, $spindleType),
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
