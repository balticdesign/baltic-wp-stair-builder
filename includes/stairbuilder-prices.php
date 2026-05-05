<?php
/**
 * Page-render price helpers. Run at the top of form-template.php to expose
 * pricing values and material-option arrays as local PHP variables. All
 * lookups go through stairbuilder_get_option() — no ACF.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'bd_stairbuilder_normalise_repeater' ) ) {
    function bd_stairbuilder_normalise_repeater( $rows, $name_key, $code_key, $value_key ) {
        $out = array();
        if ( ! is_array( $rows ) ) {
            return $out;
        }
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $out[] = array(
                'name'  => isset( $row[ $name_key ] )  ? $row[ $name_key ]  : '',
                'code'  => isset( $row[ $code_key ] )  ? $row[ $code_key ]  : '',
                'value' => isset( $row[ $value_key ] ) ? $row[ $value_key ] : 0,
            );
        }
        return $out;
    }
}

// STRINGS
$pine_string = stairbuilder_get_option( 'pine_string_price' );
$oak_string  = stairbuilder_get_option( 'oak_string_price' );

$stringer_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'stringer_types', array() ),
    'stringer_name',
    'stringer_code',
    'stringer_value'
);

// TREADS
$tread_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'tread_types', array() ),
    'tread_name',
    'tread_code',
    'tread_value'
);

// RISERS — scalar legacy keys ($*_riser) appear unused in form-template.php; flagged for review/removal.
$mdf_riser       = stairbuilder_get_option( 'mdf_riser_price' );
$pine_riser      = stairbuilder_get_option( 'pine_riser_price' );
$oak_riser       = stairbuilder_get_option( 'oak_riser_price' );
$solid_oak_riser = stairbuilder_get_option( 'solid_oak_riser_price' );

$riser_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'riser_types', array() ),
    'riser_name',
    'riser_code',
    'riser_value'
);

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