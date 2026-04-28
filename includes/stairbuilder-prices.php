<?php 

//STRINGS
 $pine_string = stairbuilder_get_option('pine_string_price');
 $oak_string = stairbuilder_get_option('oak_string_price');

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
 $mdf_riser = stairbuilder_get_option('mdf_riser_price');
 $pine_riser = stairbuilder_get_option('pine_riser_price');
 $oak_riser = stairbuilder_get_option('oak_riser_price');
 $solid_oak_riser = stairbuilder_get_option('solid_oak_riser_price');

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

$width_mp = stairbuilder_get_option('width_mp');

$setup_fee = stairbuilder_get_option('setup_fee');

$mdf_bullnose_price = stairbuilder_get_option('mdf_bullnose_price');

$ply_bullnose_price = stairbuilder_get_option('ply_bullnose_price');

$pine_bullnose_price = stairbuilder_get_option('pine_bullnose_price');

$oak_bullnose_price = stairbuilder_get_option('oak_bullnose_price');

$mdf_curtail_price = stairbuilder_get_option('mdf_curtail_price');

$ply_curtail_price = stairbuilder_get_option('ply_curtail_price');

$pine_curtail_price = stairbuilder_get_option('pine_curtail_price');

$oak_curtail_price = stairbuilder_get_option('oak_curtail_price');

$mdf_dbl_curtail_price = stairbuilder_get_option('mdf_dbl_curtail_price');

$ply_dbl_curtail_price = stairbuilder_get_option('ply_dbl_curtail_price');

$pine_dbl_curtail_price = stairbuilder_get_option('pine_dbl_curtail_price');

$oak_dcb_curtail_price = stairbuilder_get_option('oak_dcb_curtail_price');

$mdf_dcb_curtail_price = stairbuilder_get_option('mdf_dcb_curtail_price');

$ply_dcb_curtail_price = stairbuilder_get_option('ply_dcb_curtail_price');

$pine_dcb_curtail_price = stairbuilder_get_option('pine_dcb_curtail_price');

$oak_dcb_curtail_price = stairbuilder_get_option('oak_dcb_curtail_price');

$two_man_delivery_price = stairbuilder_get_option('two_man_delivery_price');

$part_assembled_price = stairbuilder_get_option('part_assembled_price');

$fixing_kit_price = stairbuilder_get_option('fixing_kit_price');

$extra_packaging_price = stairbuilder_get_option('extra_packaging_price');

$cut_string_price = stairbuilder_get_option('cut_string_price');

//NEWEL CAPS
// $pine_pyramid = stairbuilder_get_option('pine_pyramid_cap_price');
// $oak_pyramid = stairbuilder_get_option('oak_pyramid_cap_price');
// $pine_ball = stairbuilder_get_option('pine_ball_cap_price');
// $oak_ball = stairbuilder_get_option('oak_ball_cap_price');

//SPINDLES
// $pine_spindle = stairbuilder_get_option('pine_spindle_price');
// $oak_spindle = stairbuilder_get_option('oak_spindle_price');

//HANDRAILS
// $pine_crwn_hand_price = stairbuilder_get_option('pine_crwn_hand_price');
// $oak_crwn_hand_price = stairbuilder_get_option('oak_crwn_hand_price');
// $pine_hdr_hand_price = stairbuilder_get_option('pine_hdr_hand_price');
// $oak_hdr_hand_price = stairbuilder_get_option('oak_hdr_hand_price');
//$pine_handrail = stairbuilder_get_option('pine_ballustrade_price');
//$oak_handrail = stairbuilder_get_option('oak_ballustrade_price');

//HANDRAILS
$pine_baserail = stairbuilder_get_option('pine_baserail_price');
$oak_baserail = stairbuilder_get_option('oak_baserail_price');



 ?>