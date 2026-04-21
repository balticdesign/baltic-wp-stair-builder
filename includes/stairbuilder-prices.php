<?php 

//STRINGS
 $pine_string = get_field('pine_string_price', 'option');
 $oak_string = get_field('oak_string_price', 'option');

 $stringer_options = []; // Initialize an empty array to hold tread options

// Check if the repeater field has rows of data
if (have_rows('stringer_types', 'option')):

  // Loop through the rows of data
  while (have_rows('stringer_types', 'option')) : the_row();

    // Retrieve sub field values
    $stringer_name = get_sub_field('stringer_name');
    $stringer_code = get_sub_field('stringer_code');
    $stringer_value = get_sub_field('stringer_value');

    // Append the name and value to the tread options array
    $stringer_options[] = [
      'name' => $stringer_name,
      'code' => $stringer_code,
      'value' => $stringer_value
  ];
  endwhile;
endif;

 //TREADS
 $tread_options = []; // Initialize an empty array to hold tread options

// Check if the repeater field has rows of data
if (have_rows('tread_types', 'option')):

  // Loop through the rows of data
  while (have_rows('tread_types', 'option')) : the_row();

    // Retrieve sub field values
    $tread_name = get_sub_field('tread_name');
    $tread_code = get_sub_field('tread_code');
    $tread_value = get_sub_field('tread_value');

    // Append the name and value to the tread options array
    $tread_options[] = [
      'name' => $tread_name,
      'code' => $tread_code,
      'value' => $tread_value
  ];

  endwhile;
endif;


 //RISERS
 $mdf_riser = get_field('mdf_riser_price', 'option');
 $pine_riser = get_field('pine_riser_price', 'option');
 $oak_riser = get_field('oak_riser_price', 'option');
 $solid_oak_riser = get_field('solid_oak_riser_price', 'option');

 $riser_options = []; // Initialize an empty array to hold tread options

// Check if the repeater field has rows of data
if (have_rows('riser_types', 'option')):

  // Loop through the rows of data
  while (have_rows('riser_types', 'option')) : the_row();

    // Retrieve sub field values
    $riser_name = get_sub_field('riser_name');
    $riser_code = get_sub_field('riser_code');
    $riser_value = get_sub_field('riser_value');

    // Append the name and value to the tread options array
    $riser_options[] = [
      'name' => $riser_name,
      'code' => $riser_code,
      'value' => $riser_value
  ];
  endwhile;
endif;

$width_mp = get_field('width_mp', 'option');

$setup_fee = get_field('setup_fee', 'option');

$mdf_bullnose_price = get_field('mdf_bullnose_price', 'option');

$ply_bullnose_price = get_field('ply_bullnose_price', 'option');

$pine_bullnose_price = get_field('pine_bullnose_price', 'option');

$oak_bullnose_price = get_field('oak_bullnose_price', 'option');

$mdf_curtail_price = get_field('mdf_curtail_price', 'option');

$ply_curtail_price = get_field('ply_curtail_price', 'option');

$pine_curtail_price = get_field('pine_curtail_price', 'option');

$oak_curtail_price = get_field('oak_curtail_price', 'option');

$mdf_dbl_curtail_price = get_field('mdf_dbl_curtail_price', 'option');

$ply_dbl_curtail_price = get_field('ply_dbl_curtail_price', 'option');

$pine_dbl_curtail_price = get_field('pine_dbl_curtail_price', 'option');

$oak_dcb_curtail_price = get_field('oak_dcb_curtail_price', 'option');

$mdf_dcb_curtail_price = get_field('mdf_dcb_curtail_price', 'option');

$ply_dcb_curtail_price = get_field('ply_dcb_curtail_price', 'option');

$pine_dcb_curtail_price = get_field('pine_dcb_curtail_price', 'option');

$oak_dcb_curtail_price = get_field('oak_dcb_curtail_price', 'option');

$two_man_delivery_price = get_field('two_man_delivery_price', 'option');

$part_assembled_price = get_field('part_assembled_price', 'option');

$fixing_kit_price = get_field('fixing_kit_price', 'option');

$extra_packaging_price = get_field('extra_packaging_price', 'option');

$cut_string_price = get_field('cut_string_price', 'option');

//NEWEL CAPS
// $pine_pyramid = get_field('pine_pyramid_cap_price', 'option');
// $oak_pyramid = get_field('oak_pyramid_cap_price', 'option');
// $pine_ball = get_field('pine_ball_cap_price', 'option');
// $oak_ball = get_field('oak_ball_cap_price', 'option');

//SPINDLES
// $pine_spindle = get_field('pine_spindle_price', 'option');
// $oak_spindle = get_field('oak_spindle_price', 'option');

//HANDRAILS
// $pine_crwn_hand_price = get_field('pine_crwn_hand_price', 'option');
// $oak_crwn_hand_price = get_field('oak_crwn_hand_price', 'option');
// $pine_hdr_hand_price = get_field('pine_hdr_hand_price', 'option');
// $oak_hdr_hand_price = get_field('oak_hdr_hand_price', 'option');
//$pine_handrail = get_field('pine_ballustrade_price', 'option');
//$oak_handrail = get_field('oak_ballustrade_price', 'option');

//HANDRAILS
$pine_baserail = get_field('pine_baserail_price', 'option');
$oak_baserail = get_field('oak_baserail_price', 'option');



 ?>