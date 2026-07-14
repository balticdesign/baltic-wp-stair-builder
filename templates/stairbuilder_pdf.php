<?php
/**
 * PDF template for staircase quotes (lead-gen mode).
 *
 * Variables in scope from baltic_stair_generate_pdf():
 *   $title   — heading
 *   $content — assoc array merging form fields + contact info + price totals
 *
 * Layout follows _reference/quote_2_mpdf.html (Claude Design), rebuilt for
 * mPDF (table/float layout, no flexbox). Brand colours, logo and the four
 * header/footer strings come from the Brand Colours → "Quote PDF" settings
 * group; contact detail lives in those four fields, not hard-coded here.
 * Font: the design's Jost falls back to DejaVu Sans (mPDF core) — bundle Jost
 * in mPDF's font config later for the exact typeface.
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

/* ------------------------------------------------------------------ */
/* Branding — Brand Colours → "Quote PDF" group                        */
/* ------------------------------------------------------------------ */
$bd_opt = function ( $key, $default = '' ) {
    $v = function_exists( 'stairbuilder_get_option' ) ? stairbuilder_get_option( $key, '' ) : '';
    return ( $v !== '' && $v !== null ) ? $v : $default;
};
$c_accent = $bd_opt( 'pdf_accent', '#A6914E' ); // brand / rules / headings
$c_dark   = $bd_opt( 'pdf_dark',   '#35332F' ); // header + footer + price box
$c_muted  = $bd_opt( 'pdf_muted',  '#7A756A' ); // secondary text / spec keys
$c_panel  = $bd_opt( 'pdf_panel',  '#EBE8E0' ); // hairlines / light panels

$hdr_l = $bd_opt( 'pdf_header_left', '' );
$hdr_r = $bd_opt( 'pdf_header_right', '' );
$ftr_l = $bd_opt( 'pdf_footer_left', '' );
$ftr_r = $bd_opt( 'pdf_footer_right', '' );

$logo_id  = absint( $bd_opt( 'pdf_logo', 0 ) );
$logo_src = '';
if ( $logo_id ) {
    $lp       = get_attached_file( $logo_id );
    $logo_src = ( $lp && file_exists( $lp ) ) ? $lp : (string) wp_get_attachment_image_url( $logo_id, 'medium' );
}

$company    = get_bloginfo( 'name' );
$ref        = (int) ( $content['lead_id'] ?? 0 );
$quote_date = current_time( 'j F Y' );
$cust_name  = (string) ( $content['name'] ?? '' );

/* ------------------------------------------------------------------ */
/* Derived spec values                                                 */
/* ------------------------------------------------------------------ */
$bd_type_labels    = array( 'straight' => 'Straight Flight', 'quarter' => 'Quarter Turn', 'half' => 'Half Turn' );
$bd_config_labels  = array( 'landing' => 'Landing', 'winder' => 'Winder', 'double_quarter' => 'Double Quarter Landing' );
$bd_type_label     = $bd_type_labels[ $content['stair_type'] ?? '' ] ?? '';
$bd_config_label   = $bd_config_labels[ $content['stair_config'] ?? '' ] ?? '';
$bd_staircase_type = trim( $bd_type_label . ( $bd_config_label ? ' — ' . $bd_config_label : '' ) );

$bd_treadit_labels = array( '1' => 'Quarter Landing', '2' => '2 Winders', '3' => '3 Winders', '4' => 'Half Landing' );
$bd_feat_labels    = array( '0' => 'None', '1' => 'Curtail', '2' => 'Bullnose', '4' => 'Full Curtail & Bullnose' );
$bd_map_label      = function ( $map, $key ) { $key = (string) $key; return $map[ $key ] ?? ''; };
$bd_building_reg   = $bd_code_label( 'building_regs', $content['building_regs'] ?? '', 'building_reg_value', 'building_reg_name' );

$bd_turn1      = $bd_map_label( $bd_treadit_labels, $content['treadit'] ?? '' );
$bd_turn2      = $bd_map_label( $bd_treadit_labels, $content['treadit2'] ?? '' );
$bd_left_step  = $bd_map_label( $bd_feat_labels, $content['left-featured-step'] ?? '' );
$bd_right_step = $bd_map_label( $bd_feat_labels, $content['right-featured-step'] ?? '' );

// Newel count: prefer the exact figure priceCalc.js captured into #newel-count,
// so the quote matches the price by construction. Leads captured before that
// field existed fall back to rebuilding it from the saved fields. Fallback: the
// submit-time colon strip stores `newel-posts` as its label only
// (none/left/right/both/custom), so map presets to their count and, for custom
// flights, sum the saved per-corner checkboxes; then add the mandatory
// box-corner posts every turn structurally includes — boxes = flights - 1
// (straight 0, quarter 1, half 2), matching the canvas drawing.
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

// Spindle count captured from priceCalc.js (#spindle-count): wood / metal
// spindles or glass panels. Zero for a continuous per-metre glass run.
$bd_spindle_count = ( isset( $content['spindle-count'] ) && is_numeric( $content['spindle-count'] ) ) ? (int) $content['spindle-count'] : 0;

// Ballustrading panel only appears when chosen; legacy leads default to shown.
$bd_show_bal = ( ( $content['ballustrades'] ?? 'true' ) !== 'false' );

// Delivery & packaging — only present when that section was enabled on the form.
$bd_deliv_map = array( 'collected' => 'Collected', 'kerbside' => 'Kerb Side Delivery' );
$bd_deliv     = $bd_deliv_map[ $content['delivery'] ?? '' ] ?? '';
$bd_pkg       = $content['package'] ?? '';
$bd_pkg_label = ( $bd_pkg === '' ) ? '' : ( is_numeric( $bd_pkg ) ? 'Part Assembled' : 'Flat Packed' );
$bd_two_man   = isset( $content['duodeliv'] );
$bd_addons    = array();
if ( isset( $content['addon_fixkit'] ) ) { $bd_addons[] = 'Fixing Kit'; }
if ( isset( $content['addon_xtrap'] ) )  { $bd_addons[] = 'Extra Packaging'; }
$bd_has_delivery = ( $bd_deliv !== '' || $bd_pkg_label !== '' || $bd_two_man || ! empty( $bd_addons ) );

$bd_price   = (float) ( $content['price'] ?? 0 );
$bd_vat     = (float) ( $content['vat'] ?? 0 );
$bd_total   = (float) ( $content['total'] ?? 0 );
$bd_vat_pct = ( $bd_price > 0 ) ? round( $bd_vat / $bd_price * 100 ) : 0;

// Emit one spec row (label / value), skipping empty values.
$bd_row = function ( $label, $value ) {
    $value = trim( (string) $value );
    if ( $value === '' ) {
        return;
    }
    echo '<tr><td class="k">' . esc_html( $label ) . '</td><td class="v">' . esc_html( $value ) . '</td></tr>';
};

// Emit a numeric count row: 0 is a legitimate value and shows, but the row is
// suppressed when the value is non-numeric or negative — a negative tread count
// must never reach a customer quote. (The v2.11.0 flight allocator now prevents
// negatives at source; this stays as defence-in-depth for legacy leads already
// saved with a -1, and for hand-crafted POSTs.)
$bd_row_count = function ( $label, $value ) use ( $bd_row ) {
    if ( ! is_numeric( $value ) || (int) $value < 0 ) {
        return;
    }
    $bd_row( $label, (string) (int) $value );
};

// Section heading as a single-row table with the styling on the <td>. mPDF
// honours td padding where it drops/collapses div margins (the same reason the
// v2.9.0 coloured boxes moved onto td-backed tables), so this gives reliable
// spacing above and below every heading.
$bd_sectlabel = function ( $text ) {
    return '<table style="width:100%; border-collapse:collapse;"><tr><td class="sectlabel-td">' . esc_html( $text ) . '</td></tr></table>';
};
?>
<style>
  /* No @page rule: the page size (A4) and zero margins are set in the mPDF
     constructor (see baltic_stair_generate_pdf). An @page rule here is buggy in
     this mPDF build — `size` spawns extra blank pages and `margin:0` triggers a
     divide-by-zero with nested tables. */
  body { margin: 0; font-family: 'Jost', 'DejaVu Sans', sans-serif; color: <?php echo $c_dark; ?>; }

  .band { width: 100%; border-collapse: collapse; }
  .band td { vertical-align: middle; }
  .topstrip td { background: <?php echo $c_accent; ?>; color: #ffffff; padding: 9px 40px; font-size: 12.5px; letter-spacing: 0.4px; }
  .masthead td { background: <?php echo $c_dark; ?>; color: #ffffff; padding: 24px 40px; }
  .status td { background: <?php echo $c_accent; ?>; color: #ffffff; padding: 10px 40px; font-size: 14px; letter-spacing: 1.2px; text-transform: uppercase; text-align: center; font-weight: 500; }
  .footer td { background: <?php echo $c_dark; ?>; color: <?php echo $c_panel; ?>; padding: 14px 40px; font-size: 11.5px; letter-spacing: 0.5px; }

  /* Section heading on a <td> (mPDF drops div margins in cells). The 18px top
     padding is the reliable inter-section spacer — do not depend on .block. */
  .sectlabel-td { font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: <?php echo $c_accent; ?>; border-bottom: 2px solid <?php echo $c_accent; ?>; padding: 18px 0 8px; }
  /* page-break-inside:avoid keeps a section whole if an extreme config spills to
     page 2 (mPDF honours it on tables) instead of splitting mid-table. */
  .spec { width: 100%; border-collapse: collapse; font-size: 13.5px; page-break-inside: avoid; }
  .spec td { padding: 7px 0; border-bottom: 1px solid <?php echo $c_panel; ?>; }
  .spec tr:last-child td { border-bottom: none; }
  .spec .k { color: <?php echo $c_muted; ?>; }
  .spec .v { text-align: right; font-weight: 500; }

  .block { margin-bottom: 6px; }
  /* Coloured boxes below carry their fill/border inline on a wrapper <td> —
     mPDF fills td backgrounds behind nested content, but not div backgrounds. */
  .plan { border: 1px solid <?php echo $c_panel; ?>; background: <?php echo $c_panel; ?>; color: <?php echo $c_muted; ?>; font-size: 13px; }
  .notes .lbl { font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: <?php echo $c_accent; ?>; margin-bottom: 6px; }
  .notes p { margin: 0; font-size: 13px; line-height: 1.55; color: <?php echo $c_muted; ?>; }
</style>

<?php if ( $hdr_l !== '' || $hdr_r !== '' ) : ?>
<table class="band topstrip"><tr>
  <td><?php echo esc_html( $hdr_l ); ?></td>
  <td style="text-align: right;"><?php echo esc_html( $hdr_r ); ?></td>
</tr></table>
<?php endif; ?>

<table class="band masthead"><tr>
  <td>
    <?php if ( $logo_src ) : ?>
      <img src="<?php echo esc_attr( $logo_src ); ?>" style="max-height: 46px;" alt="<?php echo esc_attr( $company ); ?>">
    <?php else : ?>
      <div style="font-size: 28px; font-weight: 600; letter-spacing: 0.5px; line-height: 1;"><?php echo esc_html( $company ); ?></div>
    <?php endif; ?>
  </td>
  <td style="text-align: right;">
    <div style="font-size: 22px; font-weight: 500; letter-spacing: 2.5px; text-transform: uppercase;">Staircase Quote</div>
    <div style="font-size: 13px; color: #C9BC93; margin-top: 5px; letter-spacing: 1px;">Reference <?php echo $ref; ?> &nbsp;&middot;&nbsp; <?php echo esc_html( $quote_date ); ?></div>
  </td>
</tr></table>

<table class="band status"><tr><td>Indicative Quote<?php echo $cust_name !== '' ? ' &mdash; Prepared for ' . esc_html( $cust_name ) : ''; ?></td></tr></table>

<?php
/* Build each spec section to an HTML string (sections that produce no rows drop
   out), then greedy-pack them into two balanced columns for the full-width spec
   band (Table B). Packing keeps reading order roughly canonical while stopping a
   Details-heavy winder from towering over one column and pushing the body to
   page 2. */
$bd_sections = array();

ob_start();
$bd_row( 'Staircase Type', $bd_staircase_type );
$bd_row( 'Building Regs', $bd_building_reg );
$bd_row( 'Direction', $content['sc-direction'] ?? '' );
$bd_row( 'Floor to Floor', ( $content['floor-height'] ?? '' ) !== '' ? $content['floor-height'] . 'mm' : '' );
$bd_row( ! empty( $content['stair-width2'] ) ? 'Staircase Width (Flight 1)' : 'Staircase Width', ( $content['stair-width'] ?? '' ) !== '' ? $content['stair-width'] . 'mm' : '' );
$bd_row( 'Staircase Width (Flight 2)', ! empty( $content['stair-width2'] ) ? $content['stair-width2'] . 'mm' : '' );
$bd_row( 'Staircase Width (Flight 3)', ! empty( $content['stair-width3'] ) ? $content['stair-width3'] . 'mm' : '' );
$bd_row( 'Risers', $content['risers'] ?? '' );
$bd_row( 'Going', ( $content['going'] ?? '' ) !== '' ? $content['going'] . 'mm' : '' );
$bd_sections[] = array( 'Staircase Essentials', ob_get_clean() );

ob_start();
$bd_row( 'Construction Type', $bd_code_label( 'construction_types', $content['construction_type'] ?? '', 'construction_code', 'construction_name' ) );
$bd_row( 'Tread Profile', $bd_code_label( 'tread_profiles', $content['tread-profile'] ?? '', 'tread_profile_code', 'tread_profile_name' ) );
$bd_row( 'String Material', $bd_code_label( 'stringer_types', $content['stringer_material'] ?? '', 'stringer_code', 'stringer_name' ) );
$bd_row( 'Tread Material', $bd_code_label( 'tread_types', $content['tread_material'] ?? '', 'tread_code', 'tread_name' ) );
$bd_row( 'Riser Material', $bd_code_label( 'riser_types', $content['riser_material'] ?? '', 'riser_code', 'riser_name' ) );
$bd_row( 'Turn 1', $bd_turn1 );
$bd_row_count( 'Treads before Turn', $content['treadbt'] ?? '' );
$bd_row_count( 'Treads after Turn', $content['treadat'] ?? '' );
$bd_row( 'Turn 2', $bd_turn2 );
$bd_row_count( 'Treads after Turn 2', $content['treadat2'] ?? '' );
if ( $bd_left_step !== '' && $bd_left_step !== 'None' )  { $bd_row( 'Left Featured Step', $bd_left_step ); }
if ( $bd_right_step !== '' && $bd_right_step !== 'None' ) { $bd_row( 'Right Featured Step', $bd_right_step ); }
$bd_sections[] = array( 'Staircase Details', ob_get_clean() );

ob_start();
$bd_row( 'Type', $bd_code_label( 'newel_types', $content['newel_type'] ?? '' ) );
$bd_row( 'Material', $content['newel_material'] ?? '' );
$bd_row( 'Number', (string) (int) $newel_number );
$bd_row( 'Caps', $bd_code_label( 'cap_types', $content['newel_cap'] ?? '' ) );
if ( ( $content['newel_cap'] ?? '' ) !== 'none' ) { $bd_row( 'Cap Number', (string) (int) $newel_number ); }
$bd_sections[] = array( 'Newel Posts', ob_get_clean() );

if ( $bd_show_bal ) {
    ob_start();
    $bd_row( 'Handrail Type', $bd_code_label( 'handrail_types', $content['handrail_type'] ?? '' ) );
    $bd_row( 'Handrail Material', $content['hdr_material'] ?? '' );
    $bd_row( 'Baserail Material', $content['bsr_material'] ?? '' );
    $bd_row( 'Spindles', $bd_code_label( 'spindle_types', $content['spindle_type'] ?? '' ) );
    $bd_row( 'Spindle Material', $content['bal_material'] ?? '' );
    if ( $bd_spindle_count > 0 ) { $bd_row( 'Spindle Number', (string) $bd_spindle_count ); }
    $bd_sections[] = array( 'Balustrading', ob_get_clean() );
}

if ( $bd_has_delivery ) {
    ob_start();
    $bd_row( 'Delivery Method', $bd_deliv );
    if ( $bd_two_man ) { $bd_row( '2 Man Delivery', 'Yes' ); }
    $bd_row( 'Packaging', $bd_pkg_label );
    if ( ! empty( $bd_addons ) ) { $bd_row( 'Add-Ons', implode( ', ', $bd_addons ) ); }
    $bd_sections[] = array( 'Delivery & Packaging', ob_get_clean() );
}

// Greedy pack: append each section to whichever column has the lower running
// weight (ties -> left). Weight = row count + title overhead. Sections stay
// atomic — never split across columns.
$bd_colA = ''; $bd_colB = ''; $bd_wA = 0; $bd_wB = 0;
foreach ( $bd_sections as $bd_sec ) {
    list( $bd_title, $bd_body ) = $bd_sec;
    if ( strpos( $bd_body, '<td class="k"' ) === false ) {
        continue; // no rows — drop the section so no orphan heading is left
    }
    $bd_html   = $bd_sectlabel( $bd_title ) . '<table class="spec">' . $bd_body . '</table>';
    $bd_weight = substr_count( $bd_html, '<tr' ) + 2;
    if ( $bd_wA <= $bd_wB ) { $bd_colA .= $bd_html; $bd_wA += $bd_weight; }
    else                    { $bd_colB .= $bd_html; $bd_wB += $bd_weight; }
}
?>

<!-- Table A: plan (left) + price / customer sidebar (right) -->
<table style="width: 100%; border-collapse: collapse;">
<tr>
  <!-- LEFT: staircase plan only -->
  <td style="width: 57%; vertical-align: top; padding: 30px 14px 10px 40px;">
    <?php echo $bd_sectlabel( 'Staircase Plan' ); ?>
    <div class="block" style="text-align: center; margin-top: 8px;">
      <?php if ( ! empty( $content['canvas_image_path'] ) && file_exists( $content['canvas_image_path'] ) ) : ?>
        <img src="<?php echo esc_attr( $content['canvas_image_path'] ); ?>" alt="Staircase diagram" style="max-width: 100%; max-height: 280px; border: 1px solid <?php echo $c_panel; ?>;">
      <?php else : ?>
        <table style="width: 100%; border-collapse: collapse;"><tr><td class="plan" style="height: 180px; vertical-align: middle; text-align: center;">Staircase plan drawing not available</td></tr></table>
      <?php endif; ?>
    </div>
  </td>

  <!-- RIGHT: price + customer -->
  <td style="width: 43%; vertical-align: top; padding: 30px 40px 26px 14px;">

    <?php
    // Flat 2-column table (not a nested one) so mPDF renders every cell — deeply
    // nested tables drop cells here. Dark fill is on each <td>, which mPDF paints
    // reliably, so the whole box reads as one charcoal block.
    $pb_cell = 'background: ' . $c_dark . '; padding: 9px 24px; border-bottom: 1px solid #4A4741; font-size: 14px;';
    $pb_tot  = 'background: ' . $c_dark . '; padding: 14px 24px 22px; font-size: 14px; white-space: nowrap;';
    ?>
    <table class="block pricebox" style="width: 100%; border-collapse: collapse;">
      <tr><td colspan="2" style="background: <?php echo $c_dark; ?>; color: #C9BC93; padding: 22px 24px 12px; font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;">Indicative Quote</td></tr>
      <tr>
        <td style="<?php echo $pb_cell; ?> width: 45%; color: <?php echo $c_panel; ?>;">Subtotal</td>
        <td style="<?php echo $pb_cell; ?> width: 55%; color: #ffffff; text-align: right;">&pound;<?php echo esc_html( number_format( $bd_price, 2 ) ); ?></td>
      </tr>
      <tr>
        <td style="<?php echo $pb_cell; ?> width: 45%; color: <?php echo $c_panel; ?>;">VAT<?php echo $bd_vat_pct ? ' (' . (int) $bd_vat_pct . '%)' : ''; ?></td>
        <td style="<?php echo $pb_cell; ?> width: 55%; color: #ffffff; text-align: right;">&pound;<?php echo esc_html( number_format( $bd_vat, 2 ) ); ?></td>
      </tr>
      <tr>
        <td style="<?php echo $pb_tot; ?> width: 45%; color: #ffffff; font-weight: 500;">Total inc VAT</td>
        <td style="<?php echo $pb_tot; ?> width: 55%; color: #D3B96A; font-size: 22px; font-weight: 600; text-align: right;">&pound;<?php echo esc_html( number_format( $bd_total, 2 ) ); ?></td>
      </tr>
    </table>

    <?php if ( ! empty( $content['project_delivery_date'] ) ) : ?>
    <table class="block badge" style="width: 100%; border-collapse: collapse;"><tr>
      <td style="border: 1px solid <?php echo $c_accent; ?>; padding: 12px 16px;">
        <table style="width: 100%; border-collapse: collapse;"><tr>
          <td style="width: 28px; font-size: 20px; color: <?php echo $c_accent; ?>; vertical-align: middle;">&#10003;</td>
          <td style="vertical-align: middle;">
            <div style="font-size: 13px; font-weight: 600; letter-spacing: 0.5px;">Project Delivery</div>
            <div style="font-size: 13px; color: <?php echo $c_muted; ?>;"><?php echo esc_html( $content['project_delivery_date'] ); ?></div>
          </td>
        </tr></table>
      </td>
    </tr></table>
    <?php endif; ?>

    <?php echo $bd_sectlabel( 'Your Details' ); ?>
    <div class="block">
      <table class="spec">
        <?php
        $bd_row( 'Name', $content['name'] ?? '' );
        $bd_row( 'Email', $content['email'] ?? '' );
        $bd_row( 'Phone', $content['phone'] ?? '' );
        $bd_row( 'Postcode', $content['postcode'] ?? '' );
        ?>
      </table>
    </div>

    <table class="block notes" style="width: 100%; border-collapse: collapse;"><tr>
      <td style="background: <?php echo $c_panel; ?>; border-left: 3px solid <?php echo $c_accent; ?>; padding: 14px 16px;">
        <div class="lbl">Notes</div>
        <p>This is an indicative quote based on the configuration submitted. Final pricing is subject to a follow-up consultation.</p>
      </td>
    </tr></table>

  </td>
</tr>
</table>

<!-- Table B: full-width, two balanced columns of spec sections -->
<table style="width: 100%; border-collapse: collapse;">
<tr>
  <td style="width: 50%; vertical-align: top; padding: 12px 14px 26px 40px;"><?php echo $bd_colA; ?></td>
  <td style="width: 50%; vertical-align: top; padding: 12px 40px 26px 14px;"><?php echo $bd_colB; ?></td>
</tr>
</table>

<table class="band footer"><tr>
  <td><?php echo esc_html( $ftr_l !== '' ? $ftr_l : $company ); ?> &mdash; Quote Ref <?php echo $ref; ?></td>
  <td style="text-align: right;"><?php echo esc_html( $ftr_r ); ?></td>
</tr></table>
