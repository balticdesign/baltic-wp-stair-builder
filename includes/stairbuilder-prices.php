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
                // v2.16.0 Phase 2: availability tags travel with the option so the
                // front-end filter can address the row without a row identifier
                // (codes are non-unique on tread/riser). Empty for untagged rows.
                'available_for' => ( isset( $row['available_for'] ) && is_array( $row['available_for'] ) ) ? array_map( 'strval', $row['available_for'] ) : array(),
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

$tread_profile_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'tread_profiles', array() ),
    'tread_profile_name',
    'tread_profile_code',
    'tread_profile_value'
);

// CONSTRUCTION TYPES
$construction_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'construction_types', array() ),
    'construction_name',
    'construction_code',
    'construction_value'
);

// BUILDING REGS — 2-field repeater; we map building_reg_value into the
// `code` slot so it lands in the HTML <option value=""> attr.
$building_regs_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'building_regs', array() ),
    'building_reg_name',
    'building_reg_value',
    'building_reg_value'
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
$part_assembled_price   = stairbuilder_get_option('part_assembled_price');
$fixing_kit_price       = stairbuilder_get_option('fixing_kit_price');
$extra_packaging_price  = stairbuilder_get_option('extra_packaging_price');

// Per-option enable flags. Treat null/missing as enabled so that adding new
// delivery options to schema doesn't silently disable existing front-end rows.
if ( ! function_exists( 'bd_stairbuilder_is_enabled' ) ) {
    function bd_stairbuilder_is_enabled( $key ) {
        $v = stairbuilder_get_option( $key, null );
        if ( $v === null || $v === '' ) {
            return true;
        }
        return ! empty( $v );
    }
}
// Master toggle for the whole "Packaging & Delivery" front-end tab. When off,
// the tab is hidden and its delivery/package/add-on charges are not applied;
// Project Delivery Date + Postcode move into the "Your Details" tab.
$delivery_section_enabled = bd_stairbuilder_is_enabled( 'delivery_options_enabled' );
$two_man_delivery_enabled = bd_stairbuilder_is_enabled( 'two_man_delivery_enabled' );
$part_assembled_enabled   = bd_stairbuilder_is_enabled( 'part_assembled_enabled' );
$fixing_kit_enabled       = bd_stairbuilder_is_enabled( 'fixing_kit_enabled' );
$extra_packaging_enabled  = bd_stairbuilder_is_enabled( 'extra_packaging_enabled' );

// PROJECT DELIVERY DATES — single-field repeater; name is used as both label and value.
$project_delivery_date_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'project_delivery_dates', array() ),
    'project_delivery_date_name',
    'project_delivery_date_name',
    'project_delivery_date_name'
);

$cut_string_price = stairbuilder_get_option('cut_string_price');

// NEWEL POSTS / CAPS / HANDRAILS / SPINDLES — admin-managed repeater rows.
// Type selects only need name + code; price resolution stays server-side via
// the fetch_sp_prices AJAX. Caps additionally need caps_per_newel, mapped into
// the `value` slot so the front-end can build `{code}:{caps_per_newel}` options.
$newel_type_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'newel_types', array() ),
    'name',
    'code',
    'code'
);

$cap_type_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'cap_types', array() ),
    'name',
    'code',
    'caps_per_newel'
);

$handrail_type_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'handrail_types', array() ),
    'name',
    'code',
    'code'
);

$spindle_type_options = bd_stairbuilder_normalise_repeater(
    stairbuilder_get_option( 'spindle_types', array() ),
    'name',
    'code',
    'code'
);

//HANDRAILS
$pine_baserail = stairbuilder_get_option('pine_baserail_price');
$oak_baserail = stairbuilder_get_option('oak_baserail_price');

// Front-end "Set all to Pine / Oak" quick buttons. Default OFF — only shown
// when an admin explicitly turns them on (Construction Settings tab).
$material_quick_set_enabled = ! empty( stairbuilder_get_option( 'material_quick_set_enabled' ) );

if ( ! function_exists( 'bd_stairbuilder_render_type_field' ) ) {
    /**
     * Render an admin-driven "type/style" select — or, when only a single
     * choice exists, a hidden input carrying that one value.
     *
     * Collapsing to a hidden field keeps the form simple for the customer
     * while preserving pricing exactly: the JS reads these fields by id via
     * .val() (splitting on ":"), so a hidden <input> is a drop-in for the
     * <select> and the captured value — and therefore the price — is identical.
     *
     * @param string $id       Field id/name (must match the JS hooks).
     * @param string $label    Visible label (only shown when rendered as a select).
     * @param array  $choices  List of ['value' => string, 'label' => string].
     */
    function bd_stairbuilder_render_type_field( $id, $label, array $choices ) {
        // No admin rows — keep the existing disabled placeholder behaviour.
        if ( empty( $choices ) ) {
            printf(
                '<div class="form-row"><label for="%1$s">%2$s</label><select id="%1$s" name="%1$s"><option value="" disabled>No options available</option></select></div>',
                esc_attr( $id ),
                esc_html( $label )
            );
            return;
        }
        // Single choice — invisible field, no UI clutter, same submitted value.
        if ( count( $choices ) === 1 ) {
            printf(
                '<input type="hidden" id="%1$s" name="%1$s" value="%2$s">',
                esc_attr( $id ),
                esc_attr( $choices[0]['value'] )
            );
            return;
        }
        printf(
            '<div class="form-row"><label for="%1$s">%2$s</label><select id="%1$s" name="%1$s">',
            esc_attr( $id ),
            esc_html( $label )
        );
        foreach ( $choices as $choice ) {
            printf(
                '<option value="%1$s">%2$s</option>',
                esc_attr( $choice['value'] ),
                esc_html( $choice['label'] )
            );
        }
        echo '</select></div>';
    }
}

 ?>