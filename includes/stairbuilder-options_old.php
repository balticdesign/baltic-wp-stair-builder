<?php

//NEWEL POSTS - Plain Square
 
//$pine_newel = wc_get_product( '7368' )->get_price();

// The function to add a product to the cart with a custom price
function add_product_with_custom_price($product_id, $custom_price, $custom_meta) {

  error_log('add_product_with_custom_price called');
  error_log("product_id: $product_id, custom_price: $custom_price, custom_meta: $custom_meta");
  // Store the custom price and meta in the session
  WC()->session->set('custom_price_' . $product_id, $custom_price);
  WC()->session->set('custom_meta', $custom_meta);
  WC()->session->set('custom_product_id', $product_id);
  
  // Add the product to the cart
  WC()->cart->add_to_cart($product_id);
}

// The filter to adjust the product price
function adjust_product_price($cart_object) {
  foreach ($cart_object->cart_contents as $key => $value) {
      // Apply only if the custom_price session variable exists and this product is the custom product
      if (WC()->session->get('custom_price_' . $value['product_id']) !== null && $value['product_id'] == WC()->session->get('custom_product_id')){
        $value['data']->set_price(WC()->session->get('custom_price_' . $value['product_id']));
    }
  }
   
}
add_action('woocommerce_before_calculate_totals', 'adjust_product_price', 1, 1);

function adjust_product_meta($cart_item_data, $product_id) {
  // Apply only if the custom_meta session variable exists and this product is the custom product
  if (WC()->session->get('custom_meta') !== null && $product_id == WC()->session->get('custom_product_id')) {
      $cart_item_data['custom_meta'] = WC()->session->get('custom_meta');
  }
  return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'adjust_product_meta', 10, 2);

// Define AJAX action for logged in users
add_action('wp_ajax_custom_add_to_cart', 'custom_add_to_cart');
// Define the same AJAX action for non-logged in users
add_action('wp_ajax_nopriv_custom_add_to_cart', 'custom_add_to_cart');

function custom_add_to_cart() {


  error_log('custom_add_to_cart called');
  error_log('POST data: ' . print_r($_POST, true));
    // Validate the nonce - if not valid, die
     // Check if our nonce is set.
  if (!isset($_POST['security'])) {
    wp_send_json_error('Nonce not received');
}

// Verify that the nonce is valid.
if (!wp_verify_nonce($_POST['security'], 'sb-ajax-nonce')) {
    wp_send_json_error('Nonce verification failed');
}

    $product_id = $_POST['product_id'];
    $custom_price = $_POST['custom_price'];
    $custom_meta = $_POST['custom_meta'];
    $newel_products = isset($_POST['newel_product_ids']) ? $_POST['newel_product_ids'] : null;
    $cap_products = isset($_POST['cap_product_ids']) ? $_POST['cap_product_ids'] : null;

  // Adjust the custom_price by subtracting the price of the extra products
  if (isset($newel_products) && is_array($newel_products)) {
    foreach ($newel_products as $newel_product) {
      $extra_product_price = wc_get_product($newel_product['id'])->get_price();
      $custom_price -= ($extra_product_price * $newel_product['qty']);
    }
  }

   // Adjust the custom_price by subtracting the price of the extra products
   if (isset($cap_products) && is_array($cap_products)) {
    foreach ($cap_products as $cap_product) {
      $extra_product_price = wc_get_product($cap_product['id'])->get_price();
      $custom_price -= ($extra_product_price * $cap_product['qty']);
    }
  }

    // Call your function
    add_product_with_custom_price($product_id, $custom_price, $custom_meta);

 // Now, add the other products
 if (isset($newel_products) && is_array($newel_products)) {
  foreach ($newel_products as $newel_product) {
    for ($i = 0; $i < $newel_product['qty']; $i++) {
      WC()->cart->add_to_cart($newel_product['id']);
    }
  }
}

if (isset($cap_products) && is_array($cap_products)) {
  foreach ($cap_products as $cap_product) {
    for ($i = 0; $i < $cap_product['qty']; $i++) {
      WC()->cart->add_to_cart($cap_product['id']);
    }
  }
}

    wp_send_json_success();
    wp_die(); // Always die in functions echoing AJAX response
}

function get_newel_posts() {

  // Check if our nonce is set.
  if (!isset($_POST['security'])) {
    wp_send_json_error('Nonce not received');
}

// Verify that the nonce is valid.
if (!wp_verify_nonce($_POST['security'], 'sb-ajax-nonce')) {
    wp_send_json_error('Nonce verification failed');
}

$newelType = $_POST['newelType'];
$capType = $_POST['capType'];
$hrType = $_POST['hrType'];
$spindleType = $_POST['spindleType'];

// $square_newel_option = get_field('square_newel_option', 'option');
// $chmf_newel_option = get_field('chamfered_newel_option', 'option');
// $trn_newel_option = get_field('turned_newel_option', 'option');
// $base_newel_option = get_field('newel_base_option', 'option');

$crwn_hand_option = get_field('crwn_hand_option', 'option');
$hdr_hand_option = get_field('hdr_hand_option', 'option');
$pyr_cap_option = get_field('pyr_cap_option', 'option');
$ball_cap_option = get_field('ball_cap_option', 'option');

$newel_options = [
  'square' => [
      'option' => get_field('square_newel_option', 'option'),
      'id' => ['pine' => 'pine_square_newel_post_id', 'oak' => 'oak_newel_post_id'],
      'price' => ['pine' => 'pine_newel_post_price', 'oak' => 'oak_newel_post_price']
  ],
  'chamfered' => [
      'option' => get_field('chamfered_newel_option', 'option'),
      'id' => ['pine' => 'pine_chmf_newel_post_id', 'oak' => 'oak_chmf_newel_post_id'],
      'price' => ['pine' => 'pine_chmf_newel_price', 'oak' => 'oak_chmf_newel_price']
  ],
  'turned' => [
      'option' => get_field('turned_newel_option', 'option'),
      'id' => ['pine' => 'pine_trn_newel_id', 'oak' => 'oak_trn_newel_id'],
      'price' => ['pine' => 'pine_trn_newel_price', 'oak' => 'oak_trn_newel_price']
  ],
  'base' => [
      'option' => get_field('newel_base_option', 'option'),
      'id' => ['pine' => 'pine_newel_base_id', 'oak' => 'oak_newel_base_id'],
      'price' => ['pine' => 'pine_newel_base_price', 'oak' => 'oak_newel_base_price']
  ],
  'default' => [
      'option' => null,
      'id' => ['pine' => null, 'oak' => null],
      'price' => ['pine' => 'pine_newel_post_price', 'oak' => 'oak_newel_post_price']
  ]
];

if (array_key_exists($newelType, $newel_options)) {
  $newel_option = $newel_options[$newelType];
  $pine_id = $oak_id = null;

  if (!$newel_option['option']) {
      $pinePrice = get_field($newel_option['price']['pine'], 'option');
      $oakPrice = get_field($newel_option['price']['oak'], 'option');
  } else {
      $pine_id = get_field($newel_option['id']['pine'], 'option');
      $oak_id = get_field($newel_option['id']['oak'], 'option');

      $pine_product = wc_get_product($pine_id);
      $pinePrice = $pine_product ? $pine_product->get_price() : get_field($newel_option['price']['pine'], 'option');

      $oak_product = wc_get_product($oak_id);
      $oakPrice = $oak_product ? $oak_product->get_price() : get_field($newel_option['price']['oak'], 'option');
  }
} else {
  $pinePrice = get_field($newel_options['default']['price']['pine'], 'option');
  $oakPrice = get_field($newel_options['default']['price']['oak'], 'option');
}

// switch ($newelType) {
//     case 'square':
//     if (!$square_newel_option) {
//       $pinePrice = get_field('pine_newel_post_price', 'option');
//       $oakPrice = get_field('oak_newel_post_price', 'option');
//       $pine_id = $oak_id = null;
//     } else {
//     $pine_id = get_field('pine_square_newel_post_id', 'option');
//     $oak_id = get_field('oak_newel_post_id', 'option');
//     $pine_product = wc_get_product($pine_id);
//     $pinePrice = $pine_product ? $pine_product->get_price() : get_field('pine_newel_post_price', 'option');
//     $oak_product = wc_get_product($oak_id);
//     $oakPrice = $oak_product ? $oak_product->get_price() : get_field('oak_newel_post_price', 'option');
//     }
//     break;
//     case 'chamfered':
//       if (!$chmf_newel_option) {
//           $pinePrice = get_field('pine_chmf_newel_price', 'option');
//           $oakPrice = get_field('oak_chmf_newel_price', 'option');
//           $pine_id = $oak_id = null;
//       } else {
//           $pine_id = get_field('pine_chmf_newel_post_id', 'option');
//           $oak_id = get_field('oak_chmf_newel_post_id', 'option');
//           $pine_product = wc_get_product($pine_id);
//           $pinePrice = $pine_product ? $pine_product->get_price() : get_field('pine_chmf_newel_price', 'option');
//           $oak_product = wc_get_product($oak_id);
//           $oakPrice = $oak_product ? $oak_product->get_price() : get_field('oak_chmf_newel_price', 'option');
//       }
//       break;

//   case 'turned':
//       if (!$trn_newel_option) {
//           $pinePrice = get_field('pine_trn_newel_price', 'option');
//           $oakPrice = get_field('oak_trn_newel_price', 'option');
//           $pine_id = $oak_id = null;
//       } else {
//           $pine_id = get_field('pine_trn_newel_id', 'option');
//           $oak_id = get_field('oak_trn_newel_id', 'option');
//           $pine_product = wc_get_product($pine_id);
//           $pinePrice = $pine_product ? $pine_product->get_price() : get_field('pine_trn_newel_price', 'option');
//           $oak_product = wc_get_product($oak_id);
//           $oakPrice = $oak_product ? $oak_product->get_price() : get_field('oak_trn_newel_price', 'option');
//       }
//       break;

//   case 'base':
//       if (!$base_newel_option) {
//           $pinePrice = get_field('pine_newel_base_price', 'option');
//           $oakPrice = get_field('oak_newel_base_price', 'option');
//           $pine_id = $oak_id = null;
//       } else {
//           $pine_id = get_field('pine_newel_base_id', 'option');
//           $oak_id = get_field('oak_newel_base_id', 'option');
//           $pine_product = wc_get_product($pine_id);
//           $pinePrice = $pine_product ? $pine_product->get_price() : get_field('pine_newel_base_price', 'option');
//           $oak_product = wc_get_product($oak_id);
//           $oakPrice = $oak_product ? $oak_product->get_price() : get_field('oak_newel_base_price', 'option');
//       }
//       break;
//     default:
//     $pinePrice = get_field('pine_newel_post_price', 'option');
//     $oakPrice = get_field('oak_newel_post_price', 'option');
//     $pine_id = $oak_id = null;
//   }

  switch ($capType) {
    case 'pyramid':
      if (!$pyr_cap_option) {
        $pineCapPrice = get_field('pine_pyramid_cap_price', 'option');
        $oakCapPrice = get_field('oak_pyramid_cap_price', 'option');
        $pineCapId = $oakCapId = null;
      } else {
      $pineCapId =  get_field('pine_pyramid_cap_id', 'option');
      $pineCapProduct = wc_get_product($pineCapId);
      $pineCapPrice = $pineCapProduct ? $pineCapProduct->get_price() : get_field('pine_pyramid_cap_price', 'option');
      $oakCapId = get_field('oak_pyramid_cap_id', 'option');
      $oakCapProduct = wc_get_product($oakCapId);
      $oakCapPrice = $oakCapProduct ? $oakCapProduct->get_price() : get_field('oak_pyramid_cap_price', 'option');
      }
      break;
    case 'ball':
      if (!$ball_cap_option) { 
      $pineCapPrice = get_field('pine_ball_cap_price', 'option');
      $oakCapPrice = get_field('oak_ball_cap_price', 'option');
      $pineCapId = $oakCapId = null;
    } else {
      $pineCapId =  get_field('pine_ball_cap_id', 'option');
      $pineCapProduct = wc_get_product($pineCapId);
      $pineCapPrice = $pineCapProduct ? $pineCapProduct->get_price() : get_field('pine_ball_cap_price', 'option');
      $oakCapId =  get_field('oak_ball_cap_id', 'option');
      $oakCapProduct = wc_get_product($oakCapId);
      $oakCapPrice = $oakCapProduct ? $oakCapProduct->get_price() : get_field('oak_ball_cap_price', 'option');
    }
      break;
      default:
      $pineCapId = $oakCapId = null;
      $pineCapPrice = get_field('pine_pyramid_cap_price', 'option');
      $oakCapPrice = get_field('oak_pyramid_cap_price', 'option');
    }

    switch ($hrType) {
      case 'crown':
        if (!$crwn_hand_option) {
          $pineHdrPrice = get_field('pine_crwn_hand_price', 'option');
          $oakHdrPrice = get_field('oak_crwn_hand_price', 'option');
          $pineHdrId = $oakHdrId = null;
        } else {
          $pineHdrId = get_field('pine_crwn_hand_id', 'option');
          $pineHdrProduct = wc_get_product($pineHdrId);
          $pineHdrPrice = $pineHdrProduct ? $pineHdrProduct->get_price() : get_field('pine_crwn_hand_price', 'option');
          $oakHdrId = get_field('oak_crwn_hand_id', 'option');
          $oakHdrProduct = wc_get_product($oakHdrId);
          $oakHdrPrice = $oakHdrProduct ? $oakHdrProduct->get_price() : get_field('oak_crwn_hand_price', 'option');
        }
        break;
      case 'hdr':
        if (!$hdr_hand_option) {
          $pineHdrPrice = get_field('pine_hdr_hand_price', 'option');
          $oakHdrPrice = get_field('oak_hdr_hand_price', 'option');
          $pineHdrId = $oakHdrId = null;
        } else {
          $pineHdrId = get_field('pine_hdr_hand_id', 'option');
          $pineHdrProduct = wc_get_product($pineHdrId);
          $pineHdrPrice = $pineHdrProduct ? $pineHdrProduct->get_price() : get_field('pine_hdr_hand_price', 'option');
          $oakHdrId = get_field('oak_hdr_hand_id', 'option');
          $oakHdrProduct = wc_get_product($oakHdrId);
          $oakHdrPrice = $oakHdrProduct ? $oakHdrProduct->get_price() : get_field('oak_hdr_hand_price', 'option');
        }
        break;
      default:
        $pineHdrPrice = get_field('pine_crwn_hand_price', 'option');
        $oakHdrPrice = get_field('oak_crwn_hand_price', 'option');
        $pineHdrId = $oakHdrId = null;
      }

      
      

    switch ($spindleType) {
      case 'chamfered':
        $pineSpinPrice = get_field('pine_chmf_spindle_price', 'option');
        $oakSpinPrice = get_field('oak_chmf_spindle_price', 'option');
        break;
      case 'edwardian':
        $pineSpinPrice = get_field('pine_edwa_spindle_price', 'option');
        $oakSpinPrice = get_field('oak_edwa_spindle_price', 'option');
        break;
        case 'twist':
          $pineSpinPrice = get_field('pine_twist_spindle_price', 'option');
          $oakSpinPrice = get_field('oak_twist_spindle_price', 'option');
          break;
      case 'flute':
            $pineSpinPrice = get_field('pine_flt_spindle_price', 'option');
            $oakSpinPrice = get_field('oak_flt_spindle_price', 'option');
            break;
      case 'tulip':
              $pineSpinPrice = get_field('pine_tlp_spindle_price', 'option');
              $oakSpinPrice = get_field('oak_tlp_spindle_price', 'option');
              break;
      case 'victorian':
                $pineSpinPrice = get_field('pine_vtrn_spindle_price', 'option');
                $oakSpinPrice = get_field('oak_vtrn_spindle_price', 'option');
                break;
      case 'provincial':
                  $pineSpinPrice = get_field('pine_prv_spindle_price', 'option');
                  $oakSpinPrice = get_field('oak_prv_spindle_price', 'option');
                  break;
        default:
        $pineSpinPrice = get_field('pine_chmf_spindle_price', 'option');
        $oakSpinPrice = get_field('oak_chmf_spindle_price', 'option');
      }
  
  $form_options = array(
    'newel_options' => array(
    '<option data-product-id="'.$pine_id.'" value="pine:' . $pinePrice . '">' . 'Pine' . '</option>',
    '<option data-product-id="'.$oak_id.'" value="oak:' . $oakPrice . '">' . 'Oak' . '</option>' ),
    'cap_options' => array(
      '<option data-product-id="'.$pineCapId.'" value="pine:' . $pineCapPrice . '">' . 'Pine' . '</option>',
      '<option data-product-id="'.$oakCapId.'" value="oak:' . $oakCapPrice . '">' . 'Oak' . '</option>' ),
      'handrail_options' => array(
        '<option data-product-id="'.$pineHdrId.'" value="pine:' . $pineHdrPrice . '">' . 'Pine' . '</option>',
        '<option data-product-id="'.$oakHdrId.'" value="oak:' . $oakHdrPrice . '">' . 'Oak' . '</option>'
      ),
      'spindle_options' => array(
        '<option value="pine:' . $pineSpinPrice . '">' . 'Pine' . '</option>',
        '<option value="oak:' . $oakSpinPrice . '">' . 'Oak' . '</option>' ),
  );

echo json_encode($form_options);

wp_die();
}
add_action('wp_ajax_get_newel_posts', 'get_newel_posts');
add_action('wp_ajax_nopriv_get_newel_posts', 'get_newel_posts');

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

  // Convert material string to index number
  $materialIndex = ['mdf' => 0, 'ply' => 1, 'pine' => 2, 'oak' => 3][$material];

   // Get the price field from the DB
   $price = get_field($stepMaterialPrices[$featNumber - 1][$materialIndex], 'option');

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

wp_die(); ///Need to Write Ajax part!! 
}
add_action('wp_ajax_get_featured_step', 'get_featured_step');
add_action('wp_ajax_nopriv_get_featured_step', 'get_featured_step');


  add_action( 'wp_ajax_nopriv_get_delivery_price', 'get_delivery_price' );
  add_action( 'wp_ajax_get_delivery_price', 'get_delivery_price' );
  
  function get_delivery_price() {

    // Check if our nonce is set.
    if (!isset($_POST['security'])) {
      wp_send_json_error('Nonce not received');
  }

  // Verify that the nonce is valid.
  if (!wp_verify_nonce($_POST['security'], 'sb-ajax-nonce')) {
      wp_send_json_error('Nonce verification failed');
  }

      $postcode = strtoupper($_POST['postcode']);
      $greaterLondonPostcodesString = get_field('greater_london_pcodes', 'option');
    $mainlandUKPostcodesString = get_field('mainland_uk_pcodes', 'option');
      $greaterLondonDeliveryPrice = get_field('greater_london_delivery_price', 'option'); // replace 'option' with your actual ACF options page
      $mainlandUKDeliveryPrice = get_field('mainland_uk_delivery_price', 'option'); // replace 'option' with your actual ACF options page
  
      $deliveryPrice = null;

      $greaterLondonPostcodes = array_map('trim', explode(",", $greaterLondonPostcodesString));
    $mainlandUKPostcodes = array_map('trim', explode(",", $mainlandUKPostcodesString));

    $postcodePrefix = substr($postcode, 0, 2);
  
    if (in_array($postcodePrefix, $greaterLondonPostcodes)) {
      $deliveryPrice = $greaterLondonDeliveryPrice;
  }
  // If the postcode didn't match a Greater London postcode, check if it matches a Mainland UK postcode
  else if (in_array($postcodePrefix, $mainlandUKPostcodes)) {
      $deliveryPrice = $mainlandUKDeliveryPrice;
  } else { $deliveryPrice = 'Invalid Postcode'; }
  
      // Return the delivery price as a JSON response
      wp_send_json_success($deliveryPrice);
  }