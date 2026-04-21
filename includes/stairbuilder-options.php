<?php

//NEWEL POSTS - Plain Square
 
//$pine_newel = wc_get_product( '7368' )->get_price();

function get_vat_rate() {
  $rates = WC_Tax::get_rates();
  $firstRate = reset($rates);  // get the first rate

  return isset($firstRate['rate']) ? $firstRate['rate'] : 0;
}
add_shortcode('vat_rate', 'get_vat_rate');

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
    $dataUrl = isset($_POST['canvas_image']) ? $_POST['canvas_image'] : null;

    // Remove the part that we don't need from the provided image and decode it
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl));

    // Define where you want to save the image
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/stairbuilder_PDFs/img/';

    // Ensure the directory exists
    if (!file_exists($file_path)) {
        wp_mkdir_p($file_path);
    }

    // Define the filename
    $file_path .= time() . 'canvas_image.png';

    // Write the contents to the file
    file_put_contents($file_path, $data);

    $custom_meta .= '&canvas_image_path=' . urlencode($file_path);
    

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
  $custom_meta .= '&final_price=' . urlencode($custom_price);
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

function getPriceAndID($optionsMap, $type){
  $pine_id = $oak_id = null;
  if (array_key_exists($type, $optionsMap)) {
    $option = $optionsMap[$type];
    if (!$option['option']) {
        $pinePrice = get_field($option['price']['pine'], 'option');
        $oakPrice = get_field($option['price']['oak'], 'option');
    } else {
        $pine_id = get_field($option['id']['pine'], 'option');
        $oak_id = get_field($option['id']['oak'], 'option');

        $pine_product = wc_get_product($pine_id);
        $pinePrice = $pine_product ? $pine_product->get_price() : get_field($option['price']['pine'], 'option');

        $oak_product = wc_get_product($oak_id);
        $oakPrice = $oak_product ? $oak_product->get_price() : get_field($option['price']['oak'], 'option');
    }
  } else {
    $pinePrice = get_field($optionsMap['default']['price']['pine'], 'option');
    $oakPrice = get_field($optionsMap['default']['price']['oak'], 'option');
  }

  return [
    '<option data-product-id="'.$pine_id.'" value="pine:' . $pinePrice . '">' . 'Pine' . '</option>',
    '<option data-product-id="'.$oak_id.'" value="oak:' . $oakPrice . '">' . 'Oak' . '</option>'
  ];
}

function save_custom_meta_in_order( $order_id ) {
  $custom_meta = WC()->session->get('custom_meta');
  if( ! empty($custom_meta) ) {
      update_post_meta( $order_id, '_custom_meta', $custom_meta );
  }
}

function regenerate_pdf_callback() {
  // Get order ID from GET parameters
  $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;

  if (!$order_id) {
      die('Invalid order ID');
  }

  // Regenerate the PDF
  generate_and_save_pdf($order_id);

  // Get the path of the saved PDF
  $pdf_path = get_post_meta($order_id, '_order_pdf_path', true);

  // Check the file exists
  if (!file_exists($pdf_path)) {
      die('Could not find PDF');
  }

  // Set headers for PDF download
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="order_' . $order_id . '.pdf"');
  header('Content-Length: ' . filesize($pdf_path));

  // Read the file content and send it to the user
  readfile($pdf_path);

  // Important: prevent WordPress from continuing execution after file download
  exit;
}

function regenerate_pdf() {
  $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : null;

  // Here, regenerate the PDF and save it to the server.
  generate_and_save_pdf($order_id, false);

  // After the generation, redirect the admin user back to the order edit screen.
  $redirect_url = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
  wp_redirect( $redirect_url );
  exit;
}
add_action( 'admin_post_regenerate_pdf', 'regenerate_pdf' );

function download_pdf() {
  $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : null;

  // Get the PDF path from the order meta
  $pdf_path = get_post_meta($order_id, '_order_pdf_path', true);

  if (file_exists($pdf_path)) {
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="'.basename($pdf_path).'"');
      header('Content-Length: ' . filesize($pdf_path));
      readfile($pdf_path);
      exit;
  } else {
      // Handle the case when the PDF file doesn't exist
      wp_die('The requested file does not exist.');
  }
}
add_action( 'admin_post_download_pdf', 'download_pdf' );

function add_generate_pdf_button( $order ) {
  $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
  $regenerate_url = admin_url( 'admin-post.php?action=regenerate_pdf&order_id=' . $order_id );
  $download_url = admin_url( 'admin-post.php?action=download_pdf&order_id=' . $order_id );

  echo '<p class="form-field form-field-wide"><a class="button" href="' . $regenerate_url . '">Regenerate PDF</a></p>';
  echo '<p class="form-field form-field-wide"><a class="button" href="' . $download_url . '">Download PDF</a></p>';
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'add_generate_pdf_button' );

function generate_and_save_pdf($order_id, $download = false) {

  $order = wc_get_order($order_id); // get the order object
  $billing_address = $order->get_formatted_billing_address(); // get billing address
  $shipping_address = $order->get_formatted_shipping_address(); // get shipping address
  $order_items = $order->get_items(); // get order items
  $order_total = $order->get_total(); // get order total
  $total_tax = $order->get_total_tax(); // total VAT
  $total_without_vat = $order->get_subtotal() - $total_tax; // total without VAT

  $mpdf = new Mpdf\Mpdf();
  $custom_meta = get_post_meta($order_id, '_custom_meta', true);
  parse_str($custom_meta, $meta_array);
  // Variables to use in your template
  $title = 'Staircase Test 1 - Order:'.$order_id;
  $content = $meta_array;

  $content['billing_address'] = $billing_address;
  $content['shipping_address'] = $shipping_address;
  $content['order_items'] = $order_items;
  $content['order_total'] = $order_total;
  $content['total_tax'] = $total_tax;
  $content['total_without_vat'] = $total_without_vat;

  ob_start();

  // Include the template file
  include plugin_dir_path( __FILE__ ) . '../templates/stairbuilder_pdf.php';

  // Get the content as a string
  $html = ob_get_clean();

  $mpdf->WriteHTML($html);

  $upload_dir = wp_upload_dir();
  $plugin_dir_path = $upload_dir['basedir'] . '/stairbuilder_PDFs/';
  $order_dir_path = $plugin_dir_path . $order_id . '/';

  if (!file_exists($order_dir_path)) {
      wp_mkdir_p($order_dir_path);
  }

  $pdf_path = $order_dir_path . 'order_' . $order_id . '.pdf';

  if ($download) {
    $mpdf->Output('order_' . $order_id . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
  } else {
    // Otherwise, save it as a file
    $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
  }

  // Store the PDF path in the order meta for future reference
  update_post_meta($order_id, '_order_pdf_path', $pdf_path);
}
add_action('woocommerce_checkout_update_order_meta', 'save_custom_meta_in_order');
add_action('woocommerce_checkout_update_order_meta', 'generate_and_save_pdf');

function display_custom_meta_in_admin_order( $order ) {
  $custom_meta = get_post_meta( $order->get_id(), '_custom_meta', true );
  if( ! empty($custom_meta) ) {
      echo '<p><strong>Custom Meta:</strong> ' . $custom_meta . '</p>';
  }
}
add_action('woocommerce_admin_order_data_after_billing_address', 'display_custom_meta_in_admin_order');

function fetch_sp_prices() {

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

$cap_options = [
  'pyramid' => [
    'option' => get_field('pyr_cap_option', 'option'),
    'id' => ['pine' => 'pine_pyramid_cap_id', 'oak' => 'oak_pyramid_cap_id'],
    'price' => ['pine' => 'pine_pyramid_cap_price', 'oak' => 'oak_pyramid_cap_price']
  ],
  'ball' => [
    'option' => get_field('ball_cap_option', 'option'),
    'id' => ['pine' => 'pine_ball_cap_id', 'oak' => 'oak_ball_cap_id'],
    'price' => ['pine' => 'pine_ball_cap_price', 'oak' => 'oak_ball_cap_price']
  ],
  'flat' => [
    'option' => get_field('flat_cap_option', 'option'),
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
    'option' => get_field('crwn_hand_option', 'option'),
    'id' => ['pine' => 'pine_crwn_hand_id', 'oak' => 'oak_crwn_hand_id'],
    'price' => ['pine' => 'pine_crwn_hand_price', 'oak' => 'oak_crwn_hand_price']
  ],
  'hdr' => [
    'option' => get_field('hdr_hand_option', 'option'),
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
    'option' => get_field('chmf_spin_option', 'option'),
    'id' => ['pine' => 'pine_chmf_spindle_price_id', 'oak' => 'oak_chmf_spindle_id'],
    'price' => ['pine' => 'pine_chmf_spindle_price', 'oak' => 'oak_chmf_spindle_price']
  ],
  'edwardian' => [
    'option' => get_field('edwa_spin_option', 'option'),
    'id' => ['pine' => 'pine_edwa_spindle_id', 'oak' => 'oak_edwa_spindle_id'],
    'price' => ['pine' => 'pine_edwa_spindle_price', 'oak' => 'oak_edwa_spindle_price']
  ],
  'twist' => [
    'option' => get_field('twist_spin_option', 'option'),
    'id' => ['pine' => 'pine_twist_spindle_id', 'oak' => 'oak_twist_spindle_id'],
    'price' => ['pine' => 'pine_twist_spindle_price', 'oak' => 'oak_twist_spindle_price']
  ],
  'flute' => [
    'option' => get_field('flute_spin_option', 'option'),
    'id' => ['pine' => 'pine_flt_spindle_id', 'oak' => 'oak_flt_spindle_id'],
    'price' => ['pine' => 'pine_flt_spindle_price', 'oak' => 'oak_flt_spindle_price']
  ],
  'tulip' => [
    'option' => get_field('tulip_spin_option', 'option'),
    'id' => ['pine' => 'pine_tlp_spindle_id', 'oak' => 'oak_tlp_spindle_id'],
    'price' => ['pine' => 'pine_tlp_spindle_price', 'oak' => 'oak_tlp_spindle_price']
  ],
  'victorian' => [
    'option' => get_field('vtrn_spin_option', 'option'),
    'id' => ['pine' => 'pine_vtrn_spindle_id', 'oak' => 'oak_vtrn_spindle_id'],
    'price' => ['pine' => 'pine_vtrn_spindle_price', 'oak' => 'oak_vtrn_spindle_price']
  ],
  'provincial' => [
    'option' => get_field('prv_spin_option', 'option'),
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