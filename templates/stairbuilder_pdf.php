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
    .table-wrapper { min-height: 220px; margin-right: 10px; }
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
                <tr><td class="lbl">Staircase Width<?php echo ( ! empty( $content['stair-width2'] ) ) ? ' (Flight 1)' : ''; ?>:</td><td class="vl lc"><?php echo esc_html( $content['stair-width'] ?? '' ); ?>mm</td></tr>
                <?php if ( ! empty( $content['stair-width2'] ) ) : ?>
                    <tr><td class="lbl">Staircase Width (Flight 2):</td><td class="vl lc"><?php echo esc_html( $content['stair-width2'] ); ?>mm</td></tr>
                <?php endif; ?>
                <?php if ( ! empty( $content['stair-width3'] ) ) : ?>
                    <tr><td class="lbl">Staircase Width (Flight 3):</td><td class="vl lc"><?php echo esc_html( $content['stair-width3'] ); ?>mm</td></tr>
                <?php endif; ?>
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

                    // Turn / featured-step value → label maps (fixed value sets).
                    $bd_treadit_labels = array( '1' => 'Quarter Landing', '2' => '2 Winders', '3' => '3 Winders', '4' => 'Half Landing' );
                    $bd_feat_labels    = array( '0' => 'None', '1' => 'Curtail', '2' => 'Bullnose', '4' => 'Full Curtail &amp; Bullnose' );
                    $bd_map_label      = function ( $map, $key ) { $key = (string) $key; return $map[ $key ] ?? ''; };
                    $bd_building_reg   = $bd_code_label( 'building_regs', $content['building_regs'] ?? '', 'building_reg_value', 'building_reg_name' );
                    ?>
                    <?php if ( $bd_staircase_type ) : ?>
                    <tr><td class="lbl">Staircase Type:</td><td class="vl"><?php echo esc_html( $bd_staircase_type ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $bd_building_reg !== '' ) : ?>
                    <tr><td class="lbl">Building Regs:</td><td class="vl"><?php echo esc_html( $bd_building_reg ); ?></td></tr>
                    <?php endif; ?>
                    <tr><td class="lbl">Construction Type:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'construction_types', $content['construction_type'] ?? '', 'construction_code', 'construction_name' ) ); ?></td></tr>
                    <tr><td class="lbl">Tread Profile:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'tread_profiles', $content['tread-profile'] ?? '', 'tread_profile_code', 'tread_profile_name' ) ); ?></td></tr>
                    <tr><td class="lbl">String Material:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'stringer_types', $content['stringer_material'] ?? '', 'stringer_code', 'stringer_name' ) ); ?></td></tr>
                    <tr><td class="lbl">Tread Material:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'tread_types', $content['tread_material'] ?? '', 'tread_code', 'tread_name' ) ); ?></td></tr>
                    <tr><td class="lbl">Riser Material:</td><td class="vl"><?php echo esc_html( $bd_code_label( 'riser_types', $content['riser_material'] ?? '', 'riser_code', 'riser_name' ) ); ?></td></tr>

                    <?php
                    // Turns & Winders — only present for turned staircases.
                    $bd_turn1 = $bd_map_label( $bd_treadit_labels, $content['treadit'] ?? '' );
                    $bd_turn2 = $bd_map_label( $bd_treadit_labels, $content['treadit2'] ?? '' );
                    ?>
                    <?php if ( $bd_turn1 !== '' ) : ?>
                    <tr><td class="lbl">Turn 1:</td><td class="vl"><?php echo esc_html( $bd_turn1 ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( isset( $content['treadbt'] ) && $content['treadbt'] !== '' ) : ?>
                    <tr><td class="lbl">Treads before Turn:</td><td class="vl lc"><?php echo esc_html( $content['treadbt'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( isset( $content['treadat'] ) && $content['treadat'] !== '' ) : ?>
                    <tr><td class="lbl">Treads after Turn:</td><td class="vl lc"><?php echo esc_html( $content['treadat'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $bd_turn2 !== '' ) : ?>
                    <tr><td class="lbl">Turn 2:</td><td class="vl"><?php echo esc_html( $bd_turn2 ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( isset( $content['treadat2'] ) && $content['treadat2'] !== '' ) : ?>
                    <tr><td class="lbl">Treads after Turn 2:</td><td class="vl lc"><?php echo esc_html( $content['treadat2'] ); ?></td></tr>
                    <?php endif; ?>

                    <?php
                    // Featured step — the customer's left/right step choice (0/1/2/4).
                    $bd_left_step  = $bd_map_label( $bd_feat_labels, $content['left-featured-step'] ?? '' );
                    $bd_right_step = $bd_map_label( $bd_feat_labels, $content['right-featured-step'] ?? '' );
                    ?>
                    <?php if ( $bd_left_step !== '' && $bd_left_step !== 'None' ) : ?>
                    <tr><td class="lbl">Left Featured Step:</td><td class="vl"><?php echo esc_html( $bd_left_step ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $bd_right_step !== '' && $bd_right_step !== 'None' ) : ?>
                    <tr><td class="lbl">Right Featured Step:</td><td class="vl"><?php echo esc_html( $bd_right_step ); ?></td></tr>
                    <?php endif; ?>
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
                    // Newel count: prefer the exact figure priceCalc.js captured into
                    // #newel-count, so the quote matches the price by construction.
                    // Leads captured before that field existed fall back to rebuilding
                    // it from the saved fields.
                    //
                    // Fallback: the submit-time colon strip stores `newel-posts` as its
                    // label only (none/left/right/both/custom), so map presets to their
                    // count and, for custom flights, sum the saved per-corner checkboxes
                    // (each saved as "1"). Then add the mandatory box-corner posts every
                    // turn structurally includes — boxes = flights - 1 (straight 0,
                    // quarter 1, half 2), matching the canvas drawing, which shows those
                    // posts with no checkboxes ticked.
                    if ( isset( $content['newel-count'] ) && is_numeric( $content['newel-count'] ) ) {
                        $newel_number = (int) $content['newel-count'];
                    } else {
                        $flights_by_type = array( 'straight' => 1, 'quarter' => 2, 'half' => 3 );
                        $flights         = $flights_by_type[ $content['stair_type'] ?? 'straight' ] ?? 1;
                        $mandatory_posts = max( 0, $flights - 1 );

                        $np = (string) ( $content['newel-posts'] ?? '' );
                        if ( 'custom' === $np ) {
                            $corner_posts   = array( 'tl-post', 'tr-post', 'to-post', 'bo-post', 'box-post', 'bl-post', 'br-post', 'to-post2', 'bo-post2', 'box-post2' );
                            $optional_posts = 0;
                            foreach ( $corner_posts as $cp ) {
                                $optional_posts += ! empty( $content[ $cp ] ) ? 1 : 0;
                            }
                        } else {
                            $preset_counts  = array( 'none' => 0, 'left' => 2, 'right' => 2, 'both' => 4 );
                            $optional_posts = $preset_counts[ $np ] ?? 0;
                        }

                        $newel_number = $optional_posts + $mandatory_posts;
                    }
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
    <?php
    // Ballustrading is optional — the panel only appears when the customer chose
    // it. Legacy leads with no `ballustrades` value default to shown.
    $bd_show_bal = ( ( $content['ballustrades'] ?? 'true' ) !== 'false' );
    ?>
    <?php if ( $bd_show_bal ) : ?>
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
                    <?php
                    // Spindle count captured from priceCalc.js (#spindle-count): wood
                    // spindles / metal spindles / glass panels. Zero for a continuous
                    // per-metre glass run, so only shown when there is a discrete count.
                    $bd_spindle_count = ( isset( $content['spindle-count'] ) && is_numeric( $content['spindle-count'] ) ) ? (int) $content['spindle-count'] : 0;
                    ?>
                    <?php if ( $bd_spindle_count > 0 ) : ?>
                    <tr><td class="lbl">Spindle Number:</td><td class="vl"><?php echo (int) $bd_spindle_count; ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Delivery & Packaging — only rendered when the Packaging & Delivery section
// was enabled on the form (so these fields were captured).
$bd_deliv_map  = array( 'collected' => 'Collected', 'kerbside' => 'Kerb Side Delivery' );
$bd_deliv      = $bd_deliv_map[ $content['delivery'] ?? '' ] ?? '';
$bd_pkg        = $content['package'] ?? '';
$bd_pkg_label  = ( $bd_pkg === '' ) ? '' : ( is_numeric( $bd_pkg ) ? 'Part Assembled' : 'Flat Packed' );
$bd_two_man    = isset( $content['duodeliv'] );
$bd_addons     = array();
if ( isset( $content['addon_fixkit'] ) ) { $bd_addons[] = 'Fixing Kit'; }
if ( isset( $content['addon_xtrap'] ) )  { $bd_addons[] = 'Extra Packaging'; }
$bd_has_delivery = ( $bd_deliv !== '' || $bd_pkg_label !== '' || $bd_two_man || ! empty( $bd_addons ) );
?>
<?php if ( $bd_has_delivery ) : ?>
<div class="wrapper" style="margin-top:20px;">
    <div class="panel">
        <h3>Delivery &amp; Packaging</h3>
        <div class="table-wrapper" style="min-height:0; background-color:#e1e6f7;">
            <table>
                <?php if ( $bd_deliv !== '' ) : ?>
                <tr><td class="lbl">Delivery Method:</td><td class="vl"><?php echo esc_html( $bd_deliv ); ?></td></tr>
                <?php endif; ?>
                <?php if ( $bd_two_man ) : ?>
                <tr><td class="lbl">2 Man Delivery:</td><td class="vl">Yes</td></tr>
                <?php endif; ?>
                <?php if ( $bd_pkg_label !== '' ) : ?>
                <tr><td class="lbl">Packaging:</td><td class="vl"><?php echo esc_html( $bd_pkg_label ); ?></td></tr>
                <?php endif; ?>
                <?php if ( ! empty( $bd_addons ) ) : ?>
                <tr><td class="lbl">Add-Ons:</td><td class="vl"><?php echo esc_html( implode( ', ', $bd_addons ) ); ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

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
