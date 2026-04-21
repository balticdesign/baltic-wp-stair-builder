<?php
//STRAIGHT FLIGHT//
     add_filter( 'gform_field_value_half_pine_newel', 'addhalf_pine_newel' );
 function addhalf_pine_newel( $value ) {
    $pine_half_newel = get_field('pine_half_newel_post', 'option');
     return $pine_half_newel;
 }
 add_filter( 'gform_field_value_half_oak_newel', 'addhalf_oak_newel' );
 function addhalf_oak_newel( $value ) {
    $oak_half_newel = get_field('oak_half_newel_post', 'option');
     return $oak_half_newel;
 }
     
//SINGLE WINDER//

     add_filter( 'gform_field_value_sw_alloak', 'addsw_alloak' );
function addsw_alloak( $value ) {
    $sw_alloak = get_field('single_winder_all_oak', 'option');
    return $sw_alloak;
}

add_filter( 'gform_field_value_sw_oakstring', 'addsw_oakstring' );
function addsw_oakstring( $value ) {
    $sw_oakstring = get_field('single_winder_oak_string', 'option');
    return $sw_oakstring;
}

add_filter( 'gform_field_value_sw_oak_tr', 'addsw_oak_tr' );
function addsw_oak_tr( $value ) {
    $sw_oak_tr = get_field('single_winder_oak_tr', 'option');
    return $sw_oak_tr;
}

add_filter( 'gform_field_value_sw_oak_tread', 'addsw_oak_tread' );
function addsw_oak_tread( $value ) {
    $sw_oak_tread = get_field('single_winder_oak_tread', 'option');
    return $sw_oak_tread;
}

add_filter( 'gform_field_value_sw_nooak', 'addsw_nooak' );
function addsw_nooak( $value ) {
    $sw_nooak = get_field('single_winder_no_oak', 'option');
    return $sw_nooak;
}

//QUARTER LANDING//

add_filter( 'gform_field_value_ql_alloak', 'addql_alloak' );
function addql_alloak( $value ) {
    $ql_alloak = get_field('quarter_landing_all_oak', 'option');
    return $ql_alloak;
}

add_filter( 'gform_field_value_ql_oakstring', 'addql_oakstring' );
function addql_oakstring( $value ) {
    $ql_oakstring = get_field('quarter_landing_oak_string', 'option');
    return $ql_oakstring;
}

add_filter( 'gform_field_value_ql_oak_tr', 'addql_oak_tr' );
function addql_oak_tr( $value ) {
    $ql_oak_tr = get_field('quarter_landing_oak_tr', 'option');
    return $ql_oak_tr;
}

add_filter( 'gform_field_value_ql_oak_tread', 'addql_oak_tread' );
function addql_oak_tread( $value ) {
    $ql_oak_tread = get_field('quarter_landing_oak_tread', 'option');
    return $ql_oak_tread;
}

add_filter( 'gform_field_value_ql_nooak', 'addql_nooak' );
function addql_nooak( $value ) {
    $ql_nooak = get_field('quarter_landing_no_oak', 'option');
    return $ql_nooak;
}

//HALF LANDING//

add_filter( 'gform_field_value_hl_alloak', 'addhl_alloak' );
function addhl_alloak( $value ) {
    $hl_alloak = get_field('half_landing_all_oak', 'option');
    return $hl_alloak;
}

add_filter( 'gform_field_value_hl_oakstring', 'addhl_oakstring' );
function addhl_oakstring( $value ) {
    $hl_oakstring = get_field('half_landing_oak_string', 'option');
    return $hl_oakstring;
}

add_filter( 'gform_field_value_hl_oak_tr', 'addhl_oak_tr' );
function addhl_oak_tr( $value ) {
    $hl_oak_tr = get_field('half_landing_oak_tr', 'option');
    return $hl_oak_tr;
}

add_filter( 'gform_field_value_hl_oak_tread', 'addhl_oak_tread' );
function addhl_oak_tread( $value ) {
    $hl_oak_tread = get_field('half_landing_oak_tread', 'option');
    return $hl_oak_tread;
}

add_filter( 'gform_field_value_hl_nooak', 'addhl_nooak' );
function addhl_nooak( $value ) {
    $hl_nooak = get_field('half_landing_no_oak', 'option');
    return $hl_nooak;
}

//DOUBLE WINDER//

add_filter( 'gform_field_value_dw_alloak', 'adddw_alloak' );
function adddw_alloak( $value ) {
    $dw_alloak = get_field('double_winder_all_oak', 'option');
    return $dw_alloak;
}

add_filter( 'gform_field_value_dw_oakstring', 'adddw_oakstring' );
function adddw_oakstring( $value ) {
    $dw_oakstring = get_field('double_winder_oak_string', 'option');
    return $dw_oakstring;
}

add_filter( 'gform_field_value_dw_oak_tr', 'adddw_oak_tr' );
function adddw_oak_tr( $value ) {
    $dw_oak_tr = get_field('double_winder_oak_tr', 'option');
    return $dw_oak_tr;
}

add_filter( 'gform_field_value_dw_oak_tread', 'adddw_oak_tread' );
function adddw_oak_tread( $value ) {
    $dw_oak_tread = get_field('double_winder_oak_tread', 'option');
    return $dw_oak_tread;
}

add_filter( 'gform_field_value_dw_nooak', 'adddw_nooak' );
function adddw_nooak( $value ) {
    $dw_nooak = get_field('double_winder_no_oak', 'option');
    return $dw_nooak;
}
    

    add_filter( 'gform_pre_render_3', 'populate_posts' ); //New SW Flight - LIVE//
    add_filter( 'gform_pre_process_3', 'populate_posts' ); //New SW Flight - LIVE//
    add_filter( 'gform_pre_render_4', 'populate_posts' ); //New DW Flight - LIVE//
    add_filter( 'gform_pre_process_4', 'populate_posts' ); //New DW Flight - LIVE//
    add_filter( 'gform_pre_render_5', 'populate_posts' ); //New QL Flight  - LIVE//
    add_filter( 'gform_pre_process_5', 'populate_posts' ); //New QL Flight - LIVE//
    add_filter( 'gform_pre_render_6', 'populate_posts' ); //New HL Flight - LIVE//
    add_filter( 'gform_pre_process_6', 'populate_posts' ); //New HL Flight - LIVE//
    add_filter( 'gform_pre_render_7', 'populate_posts' ); //New Straight Flight - LIVE//
    add_filter( 'gform_pre_process_7', 'populate_posts' ); //New Straight Flight - LIVE//

    function populate_posts( $form ) {

        $builder_ids = array(3, 4, 5, 6, 7);

        if (!in_array($form['id'], $builder_ids)) {
            return;
        }
        
        foreach ( $form['fields'] as &$field ) {

 //STRINGS
 $pine_string = get_field('pine_string_price', 'option');
 $oak_string = get_field('oak_string_price', 'option');
 //TREADS
 $mdf_tread = get_field('mdf_tread_price', 'option');
 $pine_tread = get_field('pine_tread_price', 'option');
 $oak_tread = get_field('oak_tread_price', 'option');
 //RISERS
 $mdf_riser = get_field('mdf_riser_price', 'option');
 $pine_riser = get_field('pine_riser_price', 'option');
 $oak_riser = get_field('oak_riser_price', 'option');
 $solid_oak_riser = get_field('solid_oak_riser_price', 'option');

 //NEWEL POSTS - Warkworth Plain Square Complete Newel Post 1500mm
 
 $pine_newel = wc_get_product( '7368' )->get_price();
 
 $oak_newel = wc_get_product( '7370' )->get_price();
 

//NEWEL POSTS - Lindisfarne Stop Chamfer Complete Newel Post 1500mm

 $pine_sc_newel = wc_get_product( '6128' )->get_price();

 $oak_sc_newel = wc_get_product( '6130' )->get_price();

 //HALF NEWEL POSTS 
 //$pine_half_newel = get_field('pine_half_newel_post', 'option');
 $pine_half_newel = wc_get_product( '7373' )->get_price();
 //$oak_half_newel = get_field('oak_half_newel_post', 'option');
 $oak_half_newel = wc_get_product( '7375' )->get_price();

 //NEWEL BASES
 //$pine_base = get_field('pine_newel_base_price', 'option');
 $pine_base = wc_get_product( '152946' )->get_price();
 //$oak_base = get_field('oak_newel_base_price', 'option');
 $oak_base = wc_get_product( '152948' )->get_price();
 
 //NEWEL CAPS 90mm Pyramid

 $pine_pyramid = wc_get_product( '10174' )->get_price();
 $oak_pyramid = wc_get_product( '10171' )->get_price();

 //NEWEL HALF CAPS 90mm Pyramid

 $pine_half_pyramid = wc_get_product( '10187' )->get_price();
 $oak_half_pyramid = wc_get_product( '10184' )->get_price();

 //NEWEL CAPS 90mm Flat

 $pine_flat = wc_get_product( '34779' )->get_price();
 $oak_flat = wc_get_product( '34778' )->get_price();

 //NEWEL HALF CAPS 90mm Flat

 $pine_half_flat = wc_get_product( '34784' )->get_price();
 $oak_half_flat = wc_get_product( '34782' )->get_price();

 //FEATURED STEP PRICING

 //BULLNOSE
 $mdf_bullnose = get_field('mdf_bullnose_price', 'option');
 $pine_bullnose = get_field('pine_bullnose_price', 'option');
 $oak_bullnose = get_field('oak_bullnose_price', 'option');

 //CURTAIL
 $mdf_curtail = get_field('mdf_curtail_price', 'option');
 $pine_curtail = get_field('pine_curtail_price', 'option');
 $oak_curtail = get_field('oak_curtail_price', 'option');

//BN + CT
$mixed_mdf = $mdf_curtail + $mdf_bullnose;
$mixed_pine = $pine_curtail + $pine_bullnose;
$mixed_oak = $oak_curtail + $oak_bullnose;

//DOUBLE CURTAIL
$mdf_dbl_curtail = get_field('mdf_dbl_curtail_price', 'option');
$pine_dbl_curtail = get_field('pine_dbl_curtail_price', 'option');
$oak_dbl_curtail = get_field('oak_dbl_curtail_price', 'option');

//DOUBLE CURTAIL + BULLNOSE
$mdf_dcbull = $mdf_dbl_curtail + $mdf_bullnose;
$pine_dcbull = $pine_dbl_curtail + $pine_bullnose;
$oak_dcbull = $oak_dbl_curtail + $oak_bullnose;

//DOUBLE CURTAIL + CURTAIL
$mdf_dccurt = $mdf_dbl_curtail + $mdf_curtail;
$pine_dccurt = $pine_dbl_curtail + $pine_curtail;
$oak_dccurt = $oak_dbl_curtail + $oak_curtail;

//DCC + C (19,20 -> 169)
$mdf_dccc =  $mdf_dccurt + $mdf_curtail;
$pine_dccc = $pine_dccurt + $pine_curtail;
$oak_dccc = $oak_dccurt + $oak_curtail;

//DCC + B (19,20 -> 171)
$mdf_dccb =  $mdf_dccurt + $mdf_bullnose;
$pine_dccb = $pine_dccurt + $pine_bullnose;
$oak_dccb = $oak_dccurt + $oak_bullnose;

//DCB + C (19,20 -> 172)
$mdf_dcbc =  $mdf_dcbull + $mdf_curtail;
$pine_dcbc = $pine_dcbull + $pine_curtail;
$oak_dcbc = $oak_dcbull + $oak_curtail;

//DCB + B (19,20 -> 170)
$mdf_dcbb =  $mdf_dcbull + $mdf_bullnose;
$pine_dcbb = $pine_dcbull + $pine_bullnose;
$oak_dcbb = $oak_dcbull + $oak_bullnose;

//DCB + DCC (19,20 -> 89)
$mdf_dubdub = $mdf_dcbull + $mdf_dccurt;
$pine_dubdub = $pine_dcbull + $pine_dccurt;
$oak_dubdub = $oak_dcbull + $oak_dccurt;

 //BALLUSTRADE
 $pine_ballustrade = get_field('pine_ballustrade_price', 'option');
 $oak_ballustrade = get_field('oak_ballustrade_price', 'option');
 $glass_rebate_ballustrade = get_field('glass_rebate_ballustrade_price', 'option');
 $glass_bracketed_ballustrade = get_field('glass_bracketed_ballustrade_price', 'option');
 //SPINDLE
 $pine_spindle = get_field('pine_spindle_price', 'option');
 $oak_spindle = get_field('oak_spindle_price', 'option');
 $rebated_glass_price = get_field('rebated_glass_price', 'option');
 $bracketed_glass_price = get_field('bracketed_glass_price', 'option');

 //LINDISFARNE SPINDLE

 $li_pine_spindle_32 = wc_get_product( '61370' )->get_price();
 $li_pine_spindle_41 = wc_get_product( '6095' )->get_price();
 $li_oak_spindle_32 = wc_get_product( '61372' )->get_price();
 $li_oak_spindle_41 = wc_get_product( '6097' )->get_price();

 //WARKWORTH SPINDLE

 $ww_pine_spindle_32 = wc_get_product( '61365' )->get_price();
 $ww_pine_spindle_41 = wc_get_product( '7351' )->get_price();
 $ww_oak_spindle_32 = wc_get_product( '61367' )->get_price();
 $ww_oak_spindle_41 = wc_get_product( '7353' )->get_price();


 $strings = array('Pine (32mm)' => $pine_string, 'Engineered Oak (32mm)' => $oak_string);
 $treads = array('MDF (25mm)' =>  $mdf_tread, 'Pine (25mm)' => $pine_tread, 'Oak (22mm)' => $oak_tread);
 if ( $form['id'] == 22 ) {
 $risers = array('MDF (12mm)' =>  $mdf_riser, 'Pine Faced (13mm)' => $pine_riser, 'Oak Faced (13mm)' => $oak_riser);  //'Solid Oak (18mm)' => $solid_oak_riser
 } else {
    $risers = array('MDF (12mm)' =>  $mdf_riser, 'Pine Faced (13mm)' => $pine_riser, 'Oak Faced (13mm)' => $oak_riser, 'Solid Oak (18mm)' => $solid_oak_riser);   
 }
 $newels = array('Pine' => $pine_newel, 'Oak' => $oak_newel);
 $sc_newels = array('Pine' => $pine_sc_newel, 'Oak' => $oak_sc_newel);
 $bases = array('Pine' => $pine_base, 'Oak' => $oak_base);
 $caps = array('Pyramid Cap - Pine' => $pine_pyramid, 'Pyramid Cap - Oak' => $oak_pyramid, 'Flat Cap - Pine' => $pine_flat, 'Flat Cap - Oak' => $oak_flat );
 $halfcaps = array('Pyramid Half Cap - Pine' => $pine_half_pyramid, 'Pyramid Half Cap - Oak' => $oak_half_pyramid, 'Flat Half Cap - Pine' => $pine_half_flat, 'Flat Half Cap - Oak' => $oak_half_flat);

 //FOR BALLUSTRADE CALCULATOR//
 $pcaps = array('Pyramid Cap - Pine' => $pine_pyramid, 'Pyramid Cap - Oak' => $oak_pyramid );
 $fcaps = array('Flat Cap - Pine' => $pine_flat, 'Flat Cap - Oak' => $oak_flat );

 //ADD FEATURED STEP MATERIALS TO ARRAY

 $bullnose = array('MDF' =>  $mdf_bullnose, 'Pine' => $pine_bullnose, 'Oak' => $oak_bullnose);
 $curtail = array('MDF' =>  $mdf_curtail, 'Pine' => $pine_curtail, 'Oak' => $oak_curtail);
 $mixed = array('MDF' =>  $mixed_mdf, 'Pine' => $mixed_pine, 'Oak' => $mixed_oak);
 $dbl_curtail_bull = array('MDF' =>  $mdf_dcbull, 'Pine' => $pine_dcbull, 'Oak' => $oak_dcbull);
 $dbl_curtail_curt = array('MDF' =>  $mdf_dccurt, 'Pine' => $pine_dccurt, 'Oak' => $oak_dccurt);

 $dbl_cc_curt = array('MDF' =>  $mdf_dccc, 'Pine' => $pine_dccc, 'Oak' => $oak_dccc);
 $dbl_cc_bull = array('MDF' =>  $mdf_dccb, 'Pine' => $pine_dccb, 'Oak' => $oak_dccb);
 $dbl_cb_curt = array('MDF' =>  $mdf_dcbc, 'Pine' => $pine_dcbc, 'Oak' => $oak_dcbc);
 $dbl_cb_bull = array('MDF' =>  $mdf_dcbb, 'Pine' => $pine_dcbb, 'Oak' => $oak_dcbb);
 $dbl_cb_cc = array('MDF' =>  $mdf_dubdub, 'Pine' => $pine_dubdub, 'Oak' => $oak_dubdub);

 $glass_rebate_ballustrade_dd = array('Oak (59x59mm Grooved)' => $glass_rebate_ballustrade);
 $glass_bracket_ballustrade_dd = array('Oak (59x59mm)' => $glass_bracketed_ballustrade);
 $ballustrade = array('Pine (59 x 59mm)' => $pine_ballustrade, 'Oak (59 x 59mm)' => $oak_ballustrade);
 $glass_type = array( '(8mm) Rebated Glass' =>$rebated_glass_price, '(8mm) Bracketed Glass' => $bracketed_glass_price );
 $spindle = array('Pine' => $pine_spindle, 'Oak' => $oak_spindle);
 $lispindle = array('Pine (32mm)' => $li_pine_spindle_32, 'Pine (41mm)' => $li_pine_spindle_41, 'Oak (32mm)' => $li_oak_spindle_32, 'Oak (41mm)' => $li_oak_spindle_41);
 $wwspindle = array('Pine (32mm)' => $ww_pine_spindle_32, 'Pine (41mm)' => $ww_pine_spindle_41, 'Oak (32mm)' => $ww_oak_spindle_32, 'Oak (41mm)' => $ww_oak_spindle_41);
 
          //FORM FIELD MAP// 
//STRAIGHT FLIGHT CALCULATOR//
            if ( $form['id'] == 7 ) {
                $ball = 72;
                $spin = 74;
                $spin2 = 120;
                $fs_mixed = 88;
                $dblBullnose = 97;
                $dblCurtail = 100;
                $dcc_c = 169;
                $dcc_b = 171;
                $dcb_c = 172;
                $dcb_b = 170;
                $dcb_dcc = 89;
                $glass_option = 123;
                $glass = 124;
                $clamp_mod = 127;
                $clamp_price = 128;
                $capfield = 174;
                $half_capfield = 178;
                $rebate = 145;
                $bracket = 146;
                $extranewel = 150;
                $extrahalf = 158;
                $section1 = 152;
                $section2 = 153;
                $section3 = 154;
                $section4 = 155;
                $section5 = 156;

            }
            //QUARTER TURN CALCULATOR//
            if ($form['id'] == 5) {
                $ball = 90;
                $spin = 95;
                $spin2 = 133;
                $fs_mixed = 115;
                $dblBullnose = 117;
                $dblCurtail = 118;
                $dcc_c = 143;
                $dcc_b = 144;
                $dcb_c = 145;
                $dcb_b = 146;
                $dcb_dcc = 116;
                $glass_option = 152;
                $glass = 153;
                $clamp_mod = 157;
                $clamp_price = 156;
                $capfield = 183;
                $half_capfield = 185;
                $rebate = 154;
                $bracket = 155;
                $extranewel = 163;
                $extrahalf = 166;
                $section1 = 169;
                $section2 = 170;
                $section3 = 171;
                $section4 = 172;
                $section5 = 173;

                $alloak = 85;
                $oakstring = 86;
                $oaktr = 131;
                $oaktread = 132;
                $nooak = 87;
            }
            //SINGLE WINDER CALCULATOR//
            if ($form['id'] == 3) {
                $ball = 90;
                $spin = 95;
                $spin2 = 136;
                $fs_mixed = 116;
                $dblBullnose = 118;
                $dblCurtail = 119;
                $dcc_c = 117;
                $dcc_b = 146;
                $dcb_c = 147;
                $dcb_b = 148;
                $dcb_dcc = 149;
                $glass_option = 154;
                $glass = 155;
                $clamp_mod = 161;
                $clamp_price = 160;
                $capfield = 186;
                $half_capfield = 188;
                $rebate = 156;
                $bracket = 157;
                $extranewel = 166;
                $extrahalf = 169;
                $section1 = 171;
                $section2 = 172;
                $section3 = 173;
                $section4 = 174;
                $section5 = 175;

                $alloak = 85;
                $oakstring = 86;
                $oaktr = 133;
                $oaktread = 135;
                $nooak = 87;
            }
//HALF LANDING CALCULATOR//
    if($form['id'] == 6) {
                $ball = 79;
                $spin = 90;
                $spin2 = 124;
                $fs_mixed = 99;
                $dblBullnose = 101;
                $dblCurtail = 102;
                $dcc_c = 135;
                $dcc_b = 136;
                $dcb_c = 137;
                $dcb_b = 138;
                $dcb_dcc = 100;
                $glass_option = 142;
                $glass = 143;
                $clamp_mod = 147;
                $clamp_price = 146;
                $capfield = 174;
                $half_capfield = 176;
                $rebate = 144;
                $bracket = 145;
                $sections = 157;
                $extranewel = 158;
                $extrahalf = 161;
                $section1 = 164;
                $section2 = 165;
                $section3 = 166;
                $section4 = 167;
                $section5 = 168;
                
                $alloak = 113;
                $oakstring = 114;
                $oaktr = 121;
                $oaktread = 122;
                $nooak = 115;
            }
//DOUBLE WINDER CALCULATOR//
if ($form['id'] == 4) {
                $ball = 84;
                $spin = 98;
                $spin2 = 128;
                $fs_mixed = 109;
                $dblBullnose = 110;
                $dblCurtail = 111;
                $dcc_c = 108;
                $dcc_b = 140;
                $dcb_c = 141;
                $dcb_b = 142;
                $dcb_dcc = 143;
                $glass_option = 170;
                $glass = 150;
                $clamp_mod = 153;
                $clamp_price = 151;
                $capfield = 182;
                $half_capfield = 184;
                $rebate = 147;
                $bracket = 148;
                $sections = 157;
                $extranewel = 158;
                $extrahalf = 161;
                $section1 = 164;
                $section2 = 165;
                $section3 = 166;
                $section4 = 167;
                $section5 = 168;
                $alloak = 121;
                $oakstring = 122;
                $oaktr = 126;
                $oaktread = 127;
                $nooak = 123;
            }
            //BALLUSTRADING CALCULATOR//
if ($form['id'] == 27) {
    $ball = 48;
    $spin = 51;
    $spin2 = 52;
    $glass_option = 46;
    $glass = 50;
    $clamp_mod = 63;
    $clamp_price = 62;
    $pyrcap = 59;
    $flatcap = 60;
    $rebate = 47;
    $bracket = 49;
}

/* GLASS LOGIC OPTIONS */ 

if ( $field->id == $clamp_mod ) {
    $field->conditionalLogic =
        array(
            'actionType' => 'show',
            'logicType' => 'all',
            'rules' =>
                array( array( 'fieldId' => $glass_option, 'operator' => 'is', 'value' =>  'Glass' ), array( 'fieldId' => $glass, 'operator' => 'is', 'value' =>  $bracketed_glass_price ) )
        );
} /* SHOW CLAMP MODIFIER IF GLASS IS CHOSEN AND BRACKETED GLASS IS CHOSEN */ 

if ( $field->id == $clamp_price ) {
    $field->conditionalLogic =
        array(
            'actionType' => 'show',
            'logicType' => 'all',
            'rules' =>
                array( array( 'fieldId' => $glass_option, 'operator' => 'is', 'value' =>  'Glass' ), array( 'fieldId' => $glass, 'operator' => 'is', 'value' =>  $bracketed_glass_price ) )
        );
} /* SHOW CLAMP PRICE IF GLASS IS CHOSEN AND BRACKETED GLASS IS CHOSEN */

if ( $field->id == $rebate ) {
    $field->conditionalLogic =
        array(
            'actionType' => 'show',
            'logicType' => 'all',
            'rules' =>
                array( array( 'fieldId' => $glass_option, 'operator' => 'is', 'value' =>  'Glass' ), array( 'fieldId' => $glass, 'operator' => 'is', 'value' =>  $rebated_glass_price ) )
        );
} /* SHOW REBATED GLASS BALUSTRADE PRICING IF GLASS IS CHOSEN AND REBATED GLASS IS CHOSEN  */

if ( $field->id == $bracket ) {
    $field->conditionalLogic =
        array(
            'actionType' => 'show',
            'logicType' => 'all',
            'rules' =>
                array( array( 'fieldId' => $glass_option, 'operator' => 'is', 'value' =>  'Glass' ), array( 'fieldId' => $glass, 'operator' => 'is', 'value' =>  $bracketed_glass_price ) )
        );
} /* SHOW BRACKETED GLASS BALUSTRADE IF GLASS IS CHOSEN AND BRACKETED GLASS IS CHOSEN */

/* OAK PRICE LOGIC MODIFIERS */ 

  /*ALLOAK*/   if ( $field->id == $alloak ) {
    $field->conditionalLogic =
    array(
        'actionType' => 'show',
        'logicType' => 'all',
        'rules' =>
            array( array( 'fieldId' => 1, 'operator' => 'is', 'value' =>  $oak_string ), array( 'fieldId' => 2, 'operator' => 'is', 'value' => $oak_tread ) )
    );
}

/*OAKSTRING*/   if ( $field->id == $oakstring ) {
$field->conditionalLogic =
    array(
        'actionType' => 'show',
        'logicType' => 'all',
        'rules' =>
            array( array( 'fieldId' => 1, 'operator' => 'is', 'value' => $oak_string ), array( 'fieldId' => 2, 'operator' => 'isnot', 'value' => $oak_tread )  )
    );
}

/*OAKTREAD & RISER*/   if ( $field->id == $oaktr ) {
    $field->conditionalLogic =
    array(
        'actionType' => 'show',
        'logicType' => 'all',
        'rules' =>
            array( array( 'fieldId' => 1, 'operator' => 'isnot', 'value' => $oak_string ), array( 'fieldId' => 2, 'operator' => 'is', 'value' => $oak_tread ), array( 'fieldId' => 3, 'operator' => 'is', 'value' => $oak_riser )  )
    );
}
 /*OAK TREAD ONLY*/   if ( $field->id == $oaktread ) {
    $field->conditionalLogic =
    array(
        'actionType' => 'show',
        'logicType' => 'all',
        'rules' =>
            array( array( 'fieldId' => 1, 'operator' => 'isnot', 'value' => $oak_string ), array( 'fieldId' => 2, 'operator' => 'is', 'value' => $oak_tread ), array( 'fieldId' => 3, 'operator' => 'isnot', 'value' => $oak_riser )  )
    );
}


/*NOOAK*/   if ( $field->id == $nooak ) {
$field->conditionalLogic =
array(
'actionType' => 'show',
'logicType' => 'all',
'rules' =>
array( array( 'fieldId' => 1, 'operator' => 'isnot', 'value' => $oak_string ), array( 'fieldId' => 2, 'operator' => 'isnot', 'value' => $oak_tread ) )
);
}

            if ( $field->type != 'select'){
                continue;
            }
            $field_id = $field['id'];
          switch ($field_id) {
            case 1: 
            $field["choices"] = convertArray($strings); 
            break;
            case 2:
            $field["choices"] = convertArray($treads);
            break;
            case 3:
            $field["choices"] = convertArray($risers);
            break;
            case 46:
            $field["choices"] = convertArray($newels);
            break; 
            case 184:
            $field["choices"] = convertArray($sc_newels);
            break;
            case 47:
            $field["choices"] = convertArray($bases);
            break;
            case 51:
            $field["choices"] = convertArray($bullnose);
            break;
            case 52:
            $field["choices"] = convertArray($curtail);
            break;
            case $ball:
            $field["choices"] = convertArray($ballustrade);
            break;
            case $spin:
            $field["choices"] = convertArray($lispindle);
            break;
            case $spin2:
            $field["choices"] = convertArray($wwspindle);
            break; 
            case $fs_mixed:
                $field["choices"] = convertArray($mixed);
            break;
            case $dblBullnose:
            $field["choices"] = convertArray($dbl_curtail_bull);
            break;
            case $dblCurtail:
            $field["choices"] = convertArray($dbl_curtail_curt);
            break;
            case $dcc_c:
                $field["choices"] = convertArray($dbl_cc_curt);
            break;
            case $dcc_b:
                $field["choices"] = convertArray($dbl_cc_bull);
            break;
            case $dcb_c:
                 $field["choices"] = convertArray($dbl_cb_curt);
            break;
            case $dcb_b:
                $field["choices"] = convertArray($dbl_cb_bull);
            break;
            case $dcb_dcc:
                $field["choices"] = convertArray($dbl_cb_cc);
            break;
            case $glass:
                $field["choices"] = convertArray($glass_type);
                break;
            case $capfield;
            $field["choices"] = convertArray($caps);
            break;
            case $pyrcap;
            $field["choices"] = convertArray($pcaps);
            break;
            case $flatcap;
            $field["choices"] = convertArray($fcaps);
            break;
            case $half_capfield;
            $field["choices"] = convertArray($halfcaps);
            break;
            case $rebate:
            $field["choices"] = convertArray($glass_rebate_ballustrade_dd);
            break;
            case $bracket;
            $field["choices"] = convertArray($glass_bracket_ballustrade_dd);
            break;          
        }
           
        }
     
        return $form;
    }

    add_filter( 'gform_calculation_result', function ( $result, $formula, $field, $form, $entry ) {

        $builder_ids = array(3, 4, 5, 6, 7);
        
        if (in_array($form['id'], $builder_ids)) {
    
            if ( $form['id'] == 7 ) { //STRAIGHT FLIGHT
                $minrise = 160;
                $gUnit = 181;
                $ball = 78;
            }
            if ($form['id'] == 5) { //QUARTER TURN
                $minrise = 136;
                $ball = 92;
                $gUnit = 175;
                $spinUnit = 178;
            }
            if  ($form['id'] == 3) { //SINGLE WINDER
                $minrise = 139;
                $ball = 92;
                $gUnit = 177;
                $spinUnit = 179;
            }
            if ($form['id'] == 6) { //HALF LANDING
                $minrise = 128;
                $rake = 32;
                $ball = 85;
                $gUnit = 166;
                $spinUnit = 169;
            }
            if ($form['id'] == 4) { //DOUBLE WINDER
                $minrise = 133;
                $ball = 93;
                $gUnit = 169;
                $spinUnit = 175;
            }
            if ($form['id'] == 27) { //BALUSTRADING
                $ball = 67;
                $gUnit = 169;
                $spinUnit = 69;
            }
            
    $field_id = $field['id'];
        switch ($field_id) {
            case $minrise: // Minimum Risers //
            $result = ceil($result);
            case 31: //Pallets UNUSED //
            $result = ceil($result);
            break;
            case 32: //Rake Calculation//
            $result = round( sqrt($result), 2);
            break;
            case $ball: //Ballustrade Units//
            $result = ceil($result);
            break;
            case $gUnit: //Glass Units//
            $result = ceil($result);
            break;
            case $spinUnit: //Spindle Units//
                $result = ceil($result);
                break;
    }      
        }
            return $result;
        }, 10, 5 );
    
    // Change '1' to your form ID
    
    
    add_filter( 'gform_pre_submission', 'replace_entry_id', 10, 2 );
     
    function replace_entry_id( $entry ) {
     
        $today = date("Ymd");
        $rand = strtoupper(substr(uniqid(sha1(time())),0,4));
        $name = rgpost( 'input_69_6' );
        $unique = $name . $today . $rand;
        
        $data = rgpost( 'input_66' );
    
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);
    
        $tempfile = home_url().'/wp-content/uploads/canvas/straight_flight_'.$unique.'.png';
    
        $file_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/canvas/straight_flight_'.$unique.'.png';
    
        file_put_contents( $file_path, $data);
     
            // Change '25' to the field you want to copy the entry_id to
            $_POST['input_68'] = $tempfile;
     
    }
    
    add_filter( 'gform_submit_button', 'add_onclick', 10, 2 );
    function add_onclick( $button, $form ) {
        $dom = new DOMDocument();
        $dom->loadHTML( $button );
        $input = $dom->getElementsByTagName( 'input' )->item(0);
        $onclick = $input->getAttribute( 'onclick' );
        $onclick .= " grabcanvas();"; // Here's the JS function we're calling on click.
        $input->setAttribute( 'onclick', $onclick );
        return $dom->saveHtml( $input );
    }