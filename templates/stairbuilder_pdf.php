<?php
/**
 * PDF template for staircase quotes (lead-gen mode).
 *
 * Variables in scope from baltic_stair_generate_pdf():
 *   $title   — heading
 *   $content — assoc array merging form fields + contact info + price totals
 */

// Map a stored component code back to its admin-defined human label for
// display. Leads store codes in form_data; if a code is later renamed/removed
// the raw code is shown (same fragility as the legacy plugin — out of scope).
//
// $code_key / $name_key default to the plain 'code'/'name' sub-fields used by
// the newel/cap/handrail/spindle repeaters. The stringer/tread/riser/
// construction/profile repeaters use prefixed sub-keys (e.g. stringer_code /
// stringer_name), so those callers pass the matching keys explicitly.
$bd_code_label = function ( $option_key, $code, $code_key = 'code', $name_key = 'name' ) {
    $code = (string) $code;
    if ( $code === '' ) {
        return '';
    }
    $rows = function_exists( 'stairbuilder_get_option' ) ? stairbuilder_get_option( $option_key, array() ) : array();
    if ( is_array( $rows ) ) {
        foreach ( $rows as $row ) {
            if ( is_array( $row ) && isset( $row[ $code_key ] ) && (string) $row[ $code_key ] === $code && ! empty( $row[ $name_key ] ) ) {
                return $row[ $name_key ];
            }
        }
    }
    return $code;
};
?>
<style>
    @page { margin: 20px; font-family: Arial, Helvetica, sans-serif; width: 100%; }
    h1, h2, h3, h4, h5, h6 { font-family: Arial, Helvetica, sans-serif; }
    h1 { font-size: 1em; }
    h3 { font-size: 1em; margin: 0; padding: 0; }
    .wrapper { overflow: hidden; }
    .leftcol { width: 59%; float: left; }
    .rightcol { width: 39%; float: right; }
    .col { width: 33%; float: left; }
    .panel { font-size: 1.2em; vertical-align: top; }
    .panel table { padding: 10px; width: 100%; }
    .table-wrapper { height: 220px; margin-right: 10px; }
    .lbl { font-weight: bold; width: 60%; }
    .vl { padding-left: 10px; text-transform: capitalize; }
    .vl.lc { text-transform: lowercase; }
    .clear { clear: both; width: 100%; display: block; }
</style>
<table style="margin-bottom:20px;">
    <tr>
        <td><strong style="font-size:18px;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong></td>
        <td style="text-align: right;"><h1><?php echo esc_html( $title ); ?></h1></td>
    </tr>
</table>

<div class="wrapper">
    <div class="leftcol">
        <div class="panel">
            <h3>Staircase Plan</h3>
            <div style="background-color:#e1e6f7; text-align:left;">
                <?php if ( ! empty( $content['canvas_image_path'] ) && file_exists( $content['canvas_image_path'] ) ) : ?>
                    <img src="<?php echo esc_attr( $content['canvas_image_path'] ); ?>" alt="Staircase Diagram" width="100%">
                <?php else : ?>
                    <p style="padding:20px;">Diagram not available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="rightcol">
        <div class="panel">
            <h3>Staircase Essentials</h3>
            <table style="background-color:#e1e6f7;">
                <?php if ( ! empty( $content['sc-direction'] ) ) : ?>
                    <tr><td class="lbl">Direction:</td><td class="vl"><?php echo esc_html( $content['sc-direction'] ); ?></td></tr>
                <?php endif; ?>
                <tr><td class="lbl">Floor to Floor:</td><td class="vl lc"><?php echo esc_html( $content['floor-height'] ?? '' ); ?>mm</td></tr>
                <tr><td class="lbl">Staircase Width:</td><td class="vl lc"><?php echo esc_html( $content['stair-width'] ?? '' ); ?>mm</td></tr>
                <tr><td class="lbl">Risers:</td><td class="vl lc"><?php echo esc_html( $content['risers'] ?? '' ); ?></td></tr>
                <tr><td class="lbl">Going:</td><td class="vl lc"><?php echo esc_html( $content['going'] ?? '' ); ?>mm</td></tr>
            </table>
        </div>
        <div class="panel">
            <h3 style="margin-top:20px;">Indicative Quote</h3>
            <table style="background-color:#e1e6f7;">
                <tr><td class="lbl">Subtotal:</td><td>£<?php echo esc_html( number_format( (float) ( $content['price'] ?? 0 ), 2 ) ); ?></td></tr>
                <tr><td class="lbl">VAT:</td><td>£<?php echo esc_html( number_format( (float) ( $content['vat'] ?? 0 ), 2 ) ); ?></td></tr>
                <tr><td colspan="2" style="border-top: 1px solid #154782;"></td></tr>
                <tr><td class="lbl">Total (inc VAT):</td><td><strong>£<?php echo esc_html( number_format( (float) ( $content['total'] ?? 0 ), 2 ) ); ?></strong></td></tr>
            </table>
        </div>
    </div>
</div>

<div class="wrapper">
    <div class="col">
        <div class="panel">
            <h3 style="margin-top:20px;">Staircase Details</h3>
            <div class="table-wrapper" style="background-color:#e1e6f7;">
                <table>
                    <?php
                    // Friendly staircase type/config label from the captured shortcode attrs.
                    $bd_type_labels   = array( 'straight' => 'Straight Flight', 'quarter' => 'Quarter Turn', 'half' => 'Half Turn' );
                    $bd_config_labels = array( 'landing' => 'Landing', 'winder' => 'Winder', 'double_quarter' => 'Double Quarter Landing' );
                    $bd_type_label    = $bd_type_labels[ $content['stair_type'] ?? '' ] ?? '';
                    $bd_config_label  = $bd_config_labels[ $content['stair_config'] ?? '' ] ?? '';
                    $bd_staircase_type = trim( $bd_type_label . ( $bd_config_label ? ' — ' . $bd_config_label : '' ) );
                    ?>
                    <?php if ( $bd_staircase_type ) : ?>
                    <tr><td class="lbl">Staircase Type:</td><td class="vl"><?php echo esc_html( $bd_staircase_type ); ?></td></tr>
                    <?php endif; ?>
                    <tr><td class="lbl">Construction Type:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'construction_types', $content['construction_type'] ?? '', 'construction_code', 'construction_name' ) ); ?></td></tr>
                    <tr><td class="lbl">Tread Profile:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'tread_profiles', $content['tread-profile'] ?? '', 'tread_profile_code', 'tread_profile_name' ) ); ?></td></tr>
                    <tr><td class="lbl">String Material:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'stringer_types', $content['stringer_material'] ?? '', 'stringer_code', 'stringer_name' ) ); ?></td></tr>
                    <tr><td class="lbl">Tread Material:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'tread_types', $content['tread_material'] ?? '', 'tread_code', 'tread_name' ) ); ?></td></tr>
                    <tr><td class="lbl">Riser Material:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'riser_types', $content['riser_material'] ?? '', 'riser_code', 'riser_name' ) ); ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="panel">
            <h3 style="margin-top:20px;">Newel Posts</h3>
            <div class="table-wrapper" style="background-color:#e1e6f7;">
                <table>
                    <tr><td class="lbl">Type:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'newel_types', $content['newel_type'] ?? '' ) ); ?></td></tr>
                    <tr><td class="lbl">Material:</td><td class="vl"><?php echo esc_html( $content['newel_material'] ?? '' ); ?></td></tr>
                    <?php
                    $box   = ! empty( $content['box-post'] ) ? 1 : 0;
                    $newel_number = $box
                        + intval( $content['tl-post'] ?? 0 )
                        + intval( $content['tr-post'] ?? 0 )
                        + intval( $content['to-post'] ?? 0 )
                        + intval( $content['bo-post'] ?? 0 )
                        + intval( $content['box-post'] ?? 0 )
                        + intval( $content['bl-post'] ?? 0 )
                        + intval( $content['br-post'] ?? 0 );
                    ?>
                    <tr><td class="lbl">Number:</td><td class="vl"><?php echo (int) $newel_number; ?></td></tr>
                    <tr><td class="lbl">Caps:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'cap_types', $content['newel_cap'] ?? '' ) ); ?></td></tr>
                    <?php if ( ( $content['newel_cap'] ?? '' ) !== 'none' ) : ?>
                        <tr><td class="lbl">Cap Number:</td><td class="vl"><?php echo (int) $newel_number; ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="panel">
            <h3 style="margin-top:20px;">Ballustrading</h3>
            <div class="table-wrapper" style="background-color:#e1e6f7; margin-right: 0px;">
                <table>
                    <tr><td class="lbl">Handrail Type:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'handrail_types', $content['handrail_type'] ?? '' ) ); ?></td></tr>
                    <tr><td class="lbl">Handrail Material:</td><td class="vl"><?php echo esc_html( $content['hdr_material'] ?? '' ); ?></td></tr>
                    <tr><td class="lbl">Baserail Material:</td><td class="vl"><?php echo esc_html( $content['bsr_material'] ?? '' ); ?></td></tr>
                    <tr><td class="lbl">Spindles:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'spindle_types', $content['spindle_type'] ?? '' ) ); ?></td></tr>
                    <tr><td class="lbl">Spindle Material:</td><td class="vl"><?php echo esc_html( $content['bal_material'] ?? '' ); ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="wrapper" style="margin-top:20px;">
    <div class="leftcol">
        <div class="panel">
            <h3>Your Details</h3>
            <div style="background-color:#e1e6f7; padding:10px; font-size:14px;">
                <strong><?php echo esc_html( $content['name'] ?? '' ); ?></strong><br>
                <?php echo esc_html( $content['email'] ?? '' ); ?><br>
                <?php if ( ! empty( $content['phone'] ) ) : ?>
                    <?php echo esc_html( $content['phone'] ); ?><br>
                <?php endif; ?>
                <?php if ( ! empty( $content['postcode'] ) ) : ?>
                    Postcode: <?php echo esc_html( $content['postcode'] ); ?><br>
                <?php endif; ?>
                <?php if ( ! empty( $content['project_delivery_date'] ) ) : ?>
                    Project Delivery Date: <strong><?php echo esc_html( $content['project_delivery_date'] ); ?></strong>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="rightcol">
        <div class="panel">
            <h3>Notes</h3>
            <p style="font-size:11px;">This is an indicative quote based on the configuration submitted. Final pricing is subject to a follow-up consultation.</p>
        </div>
    </div>
</div>

<div style="margin-top:10px; text-align:center; padding-top:15px; border-top:1px solid #154782;">
    <?php echo esc_html( get_bloginfo( 'name' ) ); ?> &mdash; Quote Ref <?php echo (int) ( $content['lead_id'] ?? 0 ); ?>
</div>
