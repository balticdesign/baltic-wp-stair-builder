<?php require_once  (plugin_dir_path( __FILE__ ) . '../includes/stairbuilder-prices.php');
global $post;

// Resolved by Stairbuilder_Plugin::generate_shortcode() before this template is included.
$stair_type = Stairbuilder_Plugin::$current_stair_type;
if ( ! $stair_type ) {
  // Defensive fallback if template is ever included outside the shortcode flow.
  $stair_type = Stairbuilder_Plugin::DEFAULT_TYPE;
}
$fields = []; // bonus-logic option keys, populated per stair type below
$direction = false;
$flight2 = false;
$flight3 = false;
if ($stair_type === 'half') {
  $flight3 = true;
  $direction = true;
  $flight2 = true;
  $fields = [
    'half_landing_all_oak',
    'half_landing_oak_string',
    'half_landing_oak_tr',
    'half_landing_oak_tread',
    'half_landing_no_oak'
  ];
} else if ($stair_type === 'quarter'){
  $direction = true;
  $flight2 = true;
  $fields = [
    'quarter_landing_all_oak',
    'quarter_landing_oak_string',
    'quarter_landing_oak_tr',
    'quarter_landing_oak_tread',
    'quarter_landing_no_oak'
  ];
}
$bonuslogic = "";
if($fields) {
foreach ($fields as $field) {
  $value = stairbuilder_get_option($field);
  $bonuslogic .= "<input type=\"hidden\" id=\"$field\" value=\"$value\">";
}
}

// stair_config locking — pre-select + disable #treadit / #treadit2 (with a hidden
// input so the value still POSTs and reaches priceCalc.js + the PDF). Half Landing
// (treadit = 4) has no second turn, so its now-N/A treadit2 row is hidden.
$sb_stair_config  = Stairbuilder_Plugin::$current_stair_config;
$sb_treadit       = Stairbuilder_Plugin::$current_treadit;
$sb_treadit2      = Stairbuilder_Plugin::$current_treadit2;
$sb_lock_treadit  = ( '' !== $sb_treadit );
$sb_lock_treadit2 = ( '' !== $sb_treadit2 );
$sb_hide_treadit2 = ( '4' === $sb_treadit );
$sb_treadit_hide  = Stairbuilder_Plugin::$current_treadit_hide;
$sb_treadit2_hide = Stairbuilder_Plugin::$current_treadit2_hide;
// Selected value: the locked value when locked, else the (editable) config default.
$sb_treadit_sel   = $sb_lock_treadit  ? $sb_treadit  : Stairbuilder_Plugin::$current_treadit_default;
$sb_treadit2_sel  = $sb_lock_treadit2 ? $sb_treadit2 : Stairbuilder_Plugin::$current_treadit2_default;

// Landing vs winder. On the three landing configs the customer has no
// treads-in-turn choice to make (the value is locked by the shortcode), so the
// control is hidden and a static inclusion note shown in its place. The winder
// configs keep their control exactly as it is. Keyed on the resolved config so
// this stays independent of the locking mechanism that happens to imply it.
$sb_config_key      = $stair_type . ':' . $sb_stair_config;
$sb_is_landing      = in_array( $sb_config_key, array( 'quarter:landing', 'half:landing', 'half:double_quarter' ), true );

// Half landing is built internally as flight 1, an empty flight 2 (the two
// joined landing sections, treads pinned to 0 by halfTurn.js) and flight 3.
// The customer is shown flight 1 and flight 2: the internal flight-2 row is
// hidden and internal flight 3 is labelled "Flight 2". DISPLAY ONLY — field
// names, form keys, form_data and the flight allocators are untouched.
$sb_is_half_landing = ( 'half:landing' === $sb_config_key );

// Emits the hide markers for a control the customer should not see. Hidden rows
// carry .bd-hidden-row so the section summaries in layout.js can skip the fields
// they hold — a summary must not advertise a control that isn't on screen. A
// class rather than a computed-style check, because the summary also renders
// while the section is collapsed, when everything inside it is hidden anyway.
// Helper so the class and the inline style can never drift apart.
// Landing inclusion note, shown in place of the hidden treads-in-turn control on
// quarter:landing and half:double_quarter. Same null-vs-empty contract as the
// measurements note: option unset => shipped default; option set to '' => the
// admin cleared it deliberately, so render nothing at all rather than falling
// back to SPD's wording. One note per config even where two controls are hidden.
//
// half:landing does NOT take a note. v2.26.0 shipped one there reading "T&G
// landing included", on the understanding that the landing was always included
// and there was no choice to offer. That reading was wrong: SPD want the landing
// priced as a distinct item, so half:landing takes a priced select instead (see
// the Half landing row below) and landing_note_tandg has been removed.
$sb_landing_note = '';
if ( $sb_is_landing && ! $sb_is_half_landing ) {
	$sb_landing_note = stairbuilder_get_option( 'landing_note_boards', null );
	if ( null === $sb_landing_note ) {
		$sb_landing_note = bd_landing_note_boards_default();
	}
	$sb_landing_note = trim( (string) $sb_landing_note );
}

// T&G landing boards charge, half:landing only. Blank/unset reads as 0, which is
// a legitimate value and not a missing price -- the charge is ADDITIVE, so 0
// means no extra charge and the quote still completes. Never routed through the
// price-on-application path, which is for prices whose absence breaks a quote.
$sb_tandg_price = $sb_is_half_landing
	? (float) stairbuilder_get_option( 'tandg_landing_boards_price', 0 )
	: 0.0;

$sb_hide = function ( $on, $extra_class = '' ) {
	$cls = trim( $extra_class . ( $on ? ' bd-hidden-row' : '' ) );
	return ( '' !== $cls ? ' class="' . esc_attr( $cls ) . '"' : '' )
		. ( $on ? ' style="display:none"' : '' );
};
?>

<div class="bd-stairbuilder-layout">
  <div id="canvas-container" class="sb-canvas-container">
    <canvas id="canvas" width="558" height="556"></canvas>
  </div>
  <form id="stairbuild" method="post">
    <?php // Captured into the lead + PDF via the form serialise. ?>
    <input type="hidden" name="stair_type" value="<?php echo esc_attr( $stair_type ); ?>">
    <input type="hidden" name="stair_config" value="<?php echo esc_attr( $sb_stair_config ); ?>">
    <?php // Newel + spindle counts, populated by priceCalc.js so the PDF quote
    // shows exactly the priced figures (see assets/js/priceCalc.js). ?>
    <input type="hidden" id="newel-count" name="newel-count" value="">
    <input type="hidden" id="spindle-count" name="spindle-count" value="">
    <?php // Applied wide-flight surcharge (0 when it didn't fire) and any POA
          // limit reasons — informational copies for the lead record; the
          // server re-resolves both independently at capture. ?>
    <input type="hidden" id="wide-flight-surcharge" name="wide-flight-surcharge" value="0">
    <input type="hidden" id="poa_reasons" name="poa_reasons" value="">

    <header class="bd-panel-head">
      <h2 class="bd-panel-title">Configure</h2>
      <span class="bd-panel-head-spacer"></span>
      <?php // "Close others" removed with v2.21: sections are exclusive now, so
            // there are never any others open for it to close. ?>
      <button type="button" class="bd-panel-collapse" data-bd-toggle="form" aria-label="Collapse configure panel">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M8.5 2.5L4 7l4.5 4.5" stroke="currentColor" stroke-width="1.5"/></svg>
      </button>
    </header>

    <?php
    // Optional support line, set in Stairbuilder Pricing → General. Sits between
    // the header and the scroll region so it stays put as the sections scroll.
    $bd_help_line = trim( (string) stairbuilder_get_option( 'configure_help_text', '' ) );
    if ( $bd_help_line !== '' ) : ?>
    <p class="bd-panel-help"><?php echo bd_stairbuilder_help_line_html( $bd_help_line ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
    <?php endif; ?>

    <div class="bd-scrollwrap">
      <div class="bd-scroll">
    <div class="form-tabs">
  <div class="form-tab"><!--- Measurments -->
  <button type="button" class="sec-head" aria-expanded="false">
    <span class="sec-status" aria-hidden="true"><svg width="9" height="7" viewBox="0 0 9 7" fill="none"><path d="M1 3.5L3.2 5.7L8 1" stroke="currentColor" stroke-width="1.6"/></svg></span>
    <span class="sec-title">Measurements<span class="sec-sub"></span></span>
    <svg class="sec-chev" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5"/></svg>
  </button>
    <div id="msrm" class="tab-content">
 <?php if ($direction) {?>
    <div class="form-row">
            <label for="sc-direction">Direction:</label>
            <select id="sc-direction" name="sc-direction">
            <option value="left">Left</option>
            <option value="right">Right</option>
            </select>
        </div>
        <?php } ?>
        <div class="form-row">
            <label for="floor-height">Floor Height <span class="form-unit">(mm)</span> <span class="form-sub">(Floor to Floor)</span></label>
            <input type="number" id="floor-height" name="floor-height" value="">
        </div>
        <div class="form-row">
            <label for="risers">No. of Risers</label>
            <select id="risers" name="risers">
 </select>
          <!-- <input type="number" id="risers" name="risers" value=""> -->
            <!-- <select id="risers" name="risers">
            <option value="13">13</option> -->
            </select>
        </div>
        <div class="form-row">
            <label for="going">Tread Depth <span class="form-unit">(mm)</span> <span class="form-sub">(Going)</span></label>
            <input type="number" id="going" name="going" value="">
        </div>
        <div class="form-row">
            <label for="stair-width">Width <span class="form-unit">(mm)</span> <span class="form-sub">(Outside to Outside String)</span></label>
            <input type="number" id="stair-width" name="stair-width" value="">
            <?php // Half landing: #stair-width2 sets the landing's DEPTH — the dimension
            // running away from flight 1, not across it. The landing's width is not an
            // input at all; the renderer derives it from the two flights. On a winder the
            // same field really is flight 2's width, hence the split. Internal flight 3
            // becomes "Flight 2".
            //
            // v2.26.0 labelled this "Landing Width" and v2.28.1 carried that onto the
            // quote. Both were wrong: it asked for a depth under the word width. ?>
            <?php // On a staircase with no middle flight this is the landing's depth and
            // the customer has nothing to decide: it follows the widest flight. Hidden by
            // default and derived, with a checkbox to reveal it for commercial jobs or a
            // change of mind. Editing the revealed field overrides the derivation; leaving
            // it alone keeps it running. The value still POSTs while hidden -- display:none
            // does not stop a field submitting -- so it still reaches form_data and the PDF.
            //
            // formLogic.js owns the show/hide and the label, because the condition is the
            // live #treadat value, not the shortcode config: the customer can empty the
            // middle flight on any half turn. ?>
            <?php if ($flight2) {?>
            <div class="bd-depth-field">
            <label for="stair-width2" id="stair-width2-label"><?php echo $sb_is_half_landing ? 'Landing Depth' : 'Flight 2 Width'; ?> <span class="form-unit">(mm)</span></label>
            <input type="number" id="stair-width2" name="stair-width2" value="">
            </div>
            <?php if ($flight3) { ?>
            <div class="bd-depth-toggle" style="display:none">
              <label for="bd-show-depth"><input type="checkbox" id="bd-show-depth"> Set the landing depth myself</label>
            </div>
            <?php } ?>
            <?php } ?>
            <?php if ($flight3) {?>
            <label for="stair-width3"><?php echo $sb_is_half_landing ? 'Flight 2 Width' : 'Flight 3 Width'; ?> <span class="form-unit">(mm)</span></label>
            <input type="number" id="stair-width3" name="stair-width3" value="">
            <?php } ?>
            <input type="hidden" id="widthmulti" value="<?php echo $width_mp; ?>">
            <input type="hidden" id="setupfee" value="<?php echo $setup_fee; ?>">
        </div>
        </div>
        </div>
        <?php if ($flight2) {?>
          <div class="form-tab"><!--- Flights -->
  <button type="button" class="sec-head" aria-expanded="false">
    <span class="sec-status" aria-hidden="true"><svg width="9" height="7" viewBox="0 0 9 7" fill="none"><path d="M1 3.5L3.2 5.7L8 1" stroke="currentColor" stroke-width="1.6"/></svg></span>
    <span class="sec-title">Sections<span class="sec-sub"></span></span>
    <svg class="sec-chev" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5"/></svg>
  </button>
    <div id="tits" class="tab-content">
          <div class="form-row">
            <h4>Flight 1</h4>
        <label for="treadbt">Treads before Turn:</label>
        <?php // Default is derived on load by the flight script (even distribution), not hardcoded. ?>
        <input type="number" id="treadbt" name="treadbt" value="" min="0">
        </div>
        <?php // Landing configs: the value is locked by the shortcode and the customer
        // has no choice to make, so the heading and select are hidden and a static
        // inclusion note shown in their place. The value is unchanged and still
        // submitted -- the hidden input below carries it, because a disabled select
        // does not POST. treadit never reaches pricing; it feeds only the flight
        // allocators, so hiding the control moves no number. Winders keep theirs. ?>
        <div<?php echo $sb_hide( $sb_is_landing, 'form-row' ); ?>>
        <label for="treadit">Treads in Turn:</label>
        <select id="treadit" name="treadit"<?php disabled( $sb_lock_treadit ); ?>>
        <?php
        $treadit_opts = array( '1' => 'Quarter Landing', '2' => '2 Winders', '3' => '3 Winders' );
        if ( $flight3 ) { $treadit_opts['4'] = 'Half Landing'; }
        foreach ( $treadit_opts as $tv => $tl ) :
          $tv = (string) $tv; // numeric array keys arrive as ints; compare as strings
          if ( in_array( $tv, $sb_treadit_hide, true ) ) { continue; } ?>
          <option value="<?php echo esc_attr( $tv ); ?>" <?php selected( $tv, $sb_treadit_sel ); ?>><?php echo esc_html( $tl ); ?></option>
        <?php endforeach; ?>
        </select>
        <?php if ( $sb_lock_treadit ) : ?><input type="hidden" name="treadit" value="<?php echo esc_attr( $sb_treadit ); ?>"><?php endif; ?>
        </div>
        <?php // Disclosure, not a new inclusion: these have always been included, they
        // were simply never stated. Admin-editable, escaped on output. ?>
        <?php if ( '' !== $sb_landing_note ) : ?>
        <p class="bd-landing-note"><?php echo esc_html( $sb_landing_note ); ?></p>
        <?php endif; ?>
        <?php // half:landing prices its landing as a distinct item instead of stating it
        // as included. Default is "T&G landing included" because every quote to date has
        // included it, so out-of-the-box behaviour matches what is on staging now.
        //
        // The charge rides on the option's data-price, the same pattern #construction_type
        // uses, and "Landing not included" carries 0 -- so the additive sum needs no
        // special case and a config without this select contributes nothing.
        //
        // Values are plain enums with NO COLON: formLogic.js truncates every submitted
        // value at its first colon (materials arrive as `code:price`), so a colon here
        // would be silently eaten before the value reached form_data.
        //
        // Labels come from bd_tandg_landing_labels() so the form, the PDF and the
        // Enquiries view cannot drift apart. ?>
        <?php if ( $sb_is_half_landing ) : ?>
        <div class="form-row">
        <label for="tandg_landing">Half landing</label>
        <select id="tandg_landing" name="tandg_landing">
        <?php foreach ( bd_tandg_landing_labels() as $tl_value => $tl_label ) : ?>
          <option value="<?php echo esc_attr( $tl_value ); ?>"
            data-price="<?php echo esc_attr( 'included' === $tl_value ? $sb_tandg_price : 0 ); ?>"
            <?php selected( 'included', $tl_value ); ?>><?php echo esc_html( $tl_label ); ?></option>
        <?php endforeach; ?>
        </select>
        </div>
        <?php endif; ?>
        <?php // Half landing: internal flight 2 IS the landing — halfTurn.js pins its
        // treads to 0 on every recalculation, so this is a phantom control. Heading and
        // row are hidden as a unit; hiding only the label would strand an editable field
        // with no heading above it. display:none does not stop a field POSTing, so the
        // value still reaches form_data and the PDF exactly as before. ?>
        <h4<?php echo $sb_hide( $sb_is_half_landing ); ?>>Flight 2</h4>
        <div<?php echo $sb_hide( $sb_is_half_landing, 'form-row' ); ?>>
        <label for="treadat">Treads after Turn:</label>
        <?php // Quarter turn: #treadat is the DERIVED flight (auto-filled, readonly) — it must
        // stay readonly (not disabled) so its value still POSTs into the lead + PDF.
        // Half turn: #treadat is a real user input (treads between the two turns). ?>
        <input type="number" id="treadat" name="treadat" value="" min="0"<?php echo ( $flight2 && ! $flight3 ) ? ' readonly style="background:#f3f3f3;color:#555;"' : ''; ?>>
        </div>
        <?php if ($flight3) {?>
        <?php // Already hidden on half:landing (treadit = 4 leaves no second turn);
        // half:double_quarter locks it too and now hides it on the same grounds. ?>
         <div<?php echo $sb_hide( $sb_hide_treadit2 || $sb_is_landing, 'form-row' ); ?>>
        <label for="treadit2">Treads in Turn2:</label>
        <select id="treadit2" name="treadit2"<?php disabled( $sb_lock_treadit2 ); ?>>
        <?php
        $treadit2_opts = array( '1' => 'Quarter Landing', '2' => '2 Winders', '3' => '3 Winders' );
        foreach ( $treadit2_opts as $tv => $tl ) :
          $tv = (string) $tv; // numeric array keys arrive as ints; compare as strings
          if ( in_array( $tv, $sb_treadit2_hide, true ) ) { continue; } ?>
          <option value="<?php echo esc_attr( $tv ); ?>" <?php selected( $tv, $sb_treadit2_sel ); ?>><?php echo esc_html( $tl ); ?></option>
        <?php endforeach; ?>
        </select>
        <?php if ( $sb_lock_treadit2 ) : ?><input type="hidden" name="treadit2" value="<?php echo esc_attr( $sb_treadit2 ); ?>"><?php endif; ?>
        </div>
        <h4><?php echo $sb_is_half_landing ? 'Flight 2' : 'Flight 3'; ?></h4>
        <div class="form-row">
        <label for="treadat2">Treads after Turn2:</label>
        <?php // Derived flight 3 (auto-filled). readonly (not disabled) so it still POSTs. ?>
        <input type="number" id="treadat2" name="treadat2" value="" min="0" readonly style="background:#f3f3f3;color:#555;">
        </div>
        <?php } ?>
        </div>
        </div>
        <?php } ?>
    <div id="cnstr" class="form-tab"><!--- Construction -->
    <button type="button" class="sec-head" aria-expanded="false">
    <span class="sec-status" aria-hidden="true"><svg width="9" height="7" viewBox="0 0 9 7" fill="none"><path d="M1 3.5L3.2 5.7L8 1" stroke="currentColor" stroke-width="1.6"/></svg></span>
    <span class="sec-title">Construction<span class="sec-sub"></span></span>
    <svg class="sec-chev" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5"/></svg>
  </button>
    <div class="tab-content">
    <div class="form-row">
    <label for="building_regs">Applicable Building Regs:</label>
      <select id="building_regs" name="building_regs">
    <?php if (!empty($building_regs_options)): ?>
      <?php foreach ($building_regs_options as $br_option): ?>
        <option value="<?php echo esc_attr($br_option['code']); ?>">
          <?php echo esc_html($br_option['name']); ?>
        </option>
      <?php endforeach; ?>
    <?php else: ?>
      <option value="">No options available</option>
    <?php endif; ?>
      </select>
      <?php // Regime description (Phase 1) — populated from stairBuilderVars.regs
            // by formLogic.js on load + change; empty until a regime is selected. ?>
      <p id="building_regs_desc" class="form-sub bd-regs-desc"></p>
    </div>
    <div class="form-row">
    <label for="construction_type">Construction Type:</label>
      <select id="construction_type" name="construction_type">
    <?php if (!empty($construction_options)): ?>
      <?php foreach ($construction_options as $c_option): ?>
        <option data-price="<?php echo esc_attr($c_option['value']); ?>" data-poa="<?php echo esc_attr($c_option['poa']); ?>" value="<?php echo esc_attr($c_option['code']); ?>">
          <?php echo esc_html($c_option['name']); ?>
        </option>
      <?php endforeach; ?>
    <?php else: ?>
      <option value="">No options available</option>
    <?php endif; ?>
      </select>
    </div>
    <div class="form-row">
    <label for="tread-profile">Tread Profile:</label>
      <select id="tread-profile" name="tread-profile">
    <?php if (!empty($tread_profile_options)): ?>
      <?php foreach ($tread_profile_options as $tp_option): ?>
        <option data-price="<?php echo esc_attr($tp_option['value']); ?>" data-available-for="<?php echo esc_attr(implode(',', $tp_option['available_for'])); ?>" value="<?php echo esc_attr($tp_option['code']); ?>">
          <?php echo esc_html($tp_option['name']); ?>
        </option>
      <?php endforeach; ?>
    <?php else: ?>
      <option value="">No options available</option>
    <?php endif; ?>
      </select>
    </div>
    <div class="form-row">
        <h4>Do you require a feature tread?</h4>

      <?php
      // v2.23.0: the two sides are chosen independently. The combined
      // #feature_tread dropdown these replace could only express symmetric or
      // one-sided pairs, and its data-config attribute fed these same two
      // selects behind the scenes — they were present but hidden. Values are
      // the renderer's own vocabulary (Stairs.js): 0 none, 1 curtail,
      // 2 bullnose, 3 double curtail plus single curtail, 4 double curtail
      // plus bullnose. Option order follows the brief; value order does not.
      $bd_feat_step_options = array(
          '0' => 'None',
          '2' => 'Bullnose Step',
          '1' => 'Curtail Step',
          '3' => 'Double Curtail plus Single Curtail',
          '4' => 'Double Curtail plus Bullnose',
      );
      ?>
      <?php // The two sides sit side by side — they're one decision made twice,
            // and stacking them read as two unrelated questions. Falls back to
            // stacked on a narrow panel. ?>
      <div class="bd-field-pair">
        <div class="bd-field">
          <label for="left-featured-step">Left Hand Side:</label>
          <select id="left-featured-step" name="left-featured-step" class="form-select">
            <?php foreach ( $bd_feat_step_options as $bd_fs_val => $bd_fs_label ) : ?>
            <option value="<?php echo esc_attr( $bd_fs_val ); ?>"<?php selected( $bd_fs_val, '0' ); ?>><?php echo esc_html( $bd_fs_label ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="bd-field">
          <label for="right-featured-step">Right Hand Side:</label>
          <select id="right-featured-step" name="right-featured-step" class="form-select">
            <?php foreach ( $bd_feat_step_options as $bd_fs_val => $bd_fs_label ) : ?>
            <option value="<?php echo esc_attr( $bd_fs_val ); ?>"<?php selected( $bd_fs_val, '0' ); ?>><?php echo esc_html( $bd_fs_label ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php // Per-side costs and the combined subtotal (uplift already applied
            // server-side), kept as hidden fields so priceCalc can read them. ?>
      <input type="hidden" id="leftFeatStep" value="0">
      <input type="hidden" id="rightFeatStep" value="0">
      <input type="hidden" id="featStepTotal" value="0">
      <input type="hidden" id="featStepPoa" value="0">

    </div>
</div>
</div>
<div id="mat" class="form-tab"><!--- Material -->
<button type="button" class="sec-head" aria-expanded="false">
    <span class="sec-status" aria-hidden="true"><svg width="9" height="7" viewBox="0 0 9 7" fill="none"><path d="M1 3.5L3.2 5.7L8 1" stroke="currentColor" stroke-width="1.6"/></svg></span>
    <span class="sec-title">Material<span class="sec-sub"></span></span>
    <svg class="sec-chev" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5"/></svg>
  </button>
    <div class="tab-content">
    <?php if ($material_quick_set_enabled) : ?>
    <button type="button" id="all_pine">Set all to Pine</button>
    <button type="button" id="all_oak">Set all to Oak</button>
    <?php endif; ?>
    <div class="form-row">
    <label for="stringer_material">Stringer:</label>
    <select id="stringer_material" name="stringer_material" class="bd-mat-select">
    <?php if (!empty($stringer_options)): ?>
      <?php foreach ($stringer_options as $str_option): ?>
        <option data-available-for="<?php echo esc_attr(implode(',', $str_option['available_for'])); ?>" value="<?php echo esc_attr($str_option['code']) . ':' . esc_attr($str_option['value']); ?>">
          <?php echo esc_html($str_option['name']); ?>
        </option>
      <?php endforeach; ?>
    <?php else: ?>
      <option value="">No options available</option>
    <?php endif; ?>
      </select>
    </div>
    <div class="form-row">
  <label for="tread_material">Treads:</label>
  <select id="tread_material" name="tread_material" class="bd-mat-select">
    <?php if (!empty($tread_options)): ?>
      <?php foreach ($tread_options as $tr_option): ?>
        <option data-available-for="<?php echo esc_attr(implode(',', $tr_option['available_for'])); ?>" value="<?php echo esc_attr($tr_option['code']) . ':' . esc_attr($tr_option['value']); ?>">
          <?php echo esc_html($tr_option['name']); ?>
        </option>
      <?php endforeach; ?>
    <?php else: ?>
      <option value="">No options available</option>
    <?php endif; ?>
  </select>
</div>
    <div class="form-row">
      <label for="riser_material">Risers:</label>
      <select id="riser_material" name="riser_material" class="bd-mat-select">
      <?php if (!empty($riser_options)) { ?>
      <?php foreach ($riser_options as $r_option) { ?>
        <option data-available-for="<?php echo esc_attr(implode(',', $r_option['available_for'])); ?>" value="<?php echo esc_attr($r_option['code']) . ':' . esc_attr($r_option['value']); ?>">
          <?php echo esc_html($r_option['name']); ?>
        </option>
      <?php } } else { ?>
      <option value="">No options available</option>
    <?php } ?>
      </select>
    </div>
<?php echo $bonuslogic; ?>
</div>
</div>
    <?php // data-bd-tick-on-close: this section's completion tick means
    // "opened and then closed", not "has a selection" — "no posts / no
    // balustrading" is a valid deliberate choice (SPD decision, Sept 2026,
    // settled — do not re-open). Handled in layout.js; opt-in so any future
    // section can reuse the rule without being special-cased by id. ?>
    <div class="form-tab" data-bd-tick-on-close="1"><!--- Posts & Balustrades -->
  <button type="button" class="sec-head" aria-expanded="false">
    <span class="sec-status" aria-hidden="true"><svg width="9" height="7" viewBox="0 0 9 7" fill="none"><path d="M1 3.5L3.2 5.7L8 1" stroke="currentColor" stroke-width="1.6"/></svg></span>
    <span class="sec-title">Posts &amp; Balustrades<span class="sec-sub"></span></span>
    <svg class="sec-chev" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5"/></svg>
  </button>
    <div id="posts" class="tab-content">
    <div class="form-row">
    <label for="newel-posts">Add Newel Posts?</label>
      <select id="newel-posts" name="newel-posts">
        <option value="none:0">None Required</option>
        <?php if (!$flight2) {?>
        <option value="left:2">Left</option>
        <option value="right:2">Right</option>
        <option value="both:4">Both Sides</option>
        <option value="custom:0">Custom</option>
        <?php } else { ?>
          <option value="custom:0">Yes</option>
        <?php } ?>
      </select>
    </div>
    <div id="custom">
    <h3>Top</h3>
    <div class="form-row">
        <div class="form-col">
        <label for="tl-post">Left</label>
            <input id="tl-post" type="checkbox" name="tl-post" value="1">
            </div><div class="form-col">
        <label for="tr-post">Right</label>
            <input id="tr-post" type="checkbox" name="tr-post" value="1">
        </div>
    </div>
    <?php if ($flight3) {?>
      <?php // The two MID-FLIGHT posts (#to-post2 here, #bo-post below) sit at the ends
      // of the middle flight. They carry .bd-midflight-post and formLogic.js shows or
      // hides the pair as #treadat crosses zero: with no middle flight there are no
      // posts at its ends, and offering both was the double-charge behind SPD amend 10.
      // Bound to the flight rather than the shortcode config, because the customer can
      // empty the middle flight on any half turn -- which is how the v2.28.0 fix missed
      // the double winder. v2.28.0's single "Landing Middle" box is superseded.
      //
      // Post labels carry flight numbers, so they follow the half-landing relabel
      // above or they contradict it. Internal flight 3 becomes "Flt.2"; the box corners
      // already read correctly under the new numbering, each naming the landing corner
      // nearest its flight.
      //
      // #to-post2 is NOT RENDERED on half:landing. Internal flight 2 is collapsed to
      // zero treads there — it IS the landing — so its "top" (turn 2) and its "bottom"
      // (turn 1) are the same physical post. Two boxes meant a customer could tick both
      // and pay for two newels and two caps where one post exists (SPD amend 10). The
      // surviving box is #bo-post in the Turn 1 block below, labelled "Landing Middle",
      // and halfTurn.js sets BOTH of this post's flags from it so the balustrade
      // outcome is unchanged. Every other config keeps both boxes: flight 2 is real
      // there and the two posts genuinely sit at opposite ends of it.
      //
      // v2.26.0 relabelled these to "Landing Middle (lower)/(upper)", presenting them
      // as two deliberately distinct posts. That was wrong; this supersedes it. ?>
      <h3>Turn 2</h3>
      <div class="form-row">
        <div class="form-col bd-midflight-post">
        <label for="to-post2">Flt.2 Top Outside</label>
            <input id="to-post2" type="checkbox" name="to-post2" value="1">
            </div>
        <div class="form-col">
        <label for="bo-post2"><?php echo $sb_is_half_landing ? 'Flt.2 Bottom Outside' : 'Flt.3 Bottom Outside'; ?></label>
            <input id="bo-post2" type="checkbox" name="bo-post2" value="1">
            </div><div class="form-col">
            <label for="box-post2">Flt.2 Box Corner</label>
            <input id="box-post2" type="checkbox" name="box-post2" value="1">
        </div>
    </div>
    <?php } ?>
    <?php if ($flight2) {?>
      <h3>Turn 1</h3>
      <div class="form-row">
        <div class="form-col">
        <label for="to-post">Flt.1 Top Outside</label>
            <input id="to-post" type="checkbox" name="to-post" value="1">
            </div><div class="form-col bd-midflight-post">
        <label for="bo-post">Flt.2 Bottom Outside</label>
            <input id="bo-post" type="checkbox" name="bo-post" value="1">
            </div><div class="form-col">
            <label for="box-post">Flt.1 Box Corner</label>
            <input id="box-post" type="checkbox" name="box-post" value="1">
        </div>
    </div>
    <?php } ?>
    <h3>Bottom</h3>
    <div class="form-row">
        <div class="form-col">
        <label for="bl-post">Left</label>
            <input id="bl-post" type="checkbox" name="bl-post" value="1">
            </div><div class="form-col">
        <label for="br-post">Right</label>
            <input id="br-post" type="checkbox" name="br-post" value="1">
        </div>
    </div>
    </div>
    <?php // Grouping wrapper only. Was a second id="posts" (duplicate of the
    // tab-content above), which is invalid HTML and made `#posts :input` in
    // formLogic.js reach further than it reads. Nothing targets it. ?>
    <div class="bd-post-materials">
    <?php // Newel spec — hidden by formLogic.js when no newel post is actually
          // priced (see bdUpdateNewelVisibility). Wrapped so the four rows hide
          // as one block and the balustrade question closes up behind them. ?>
    <div class="bd-newel-fields">
    <div class="form-row">
    <label for="newel_material">Newel Material</label>
      <select id="newel_material" name="newel_material" class="bd-mat-select">
        <option value="">Pine</option>
        <option value="">Oak</option>
      </select>
    </div>
    <?php
    // Newel Style — collapses to a hidden field when only one type is defined.
    $newel_type_choices = array();
    foreach ($newel_type_options as $nt_option) {
      $newel_type_choices[] = array('value' => $nt_option['code'], 'label' => $nt_option['name']);
    }
    bd_stairbuilder_render_type_field('newel_type', 'Newel Style', $newel_type_choices);
    ?>
    <?php
    // Newel Caps — "None" is always offered, so this only collapses if no cap rows exist.
    $newel_cap_choices = array(array('value' => 'none:0', 'label' => 'None'));
    foreach ($cap_type_options as $cap_option) {
      $cap_qty = ($cap_option['value'] === '' || $cap_option['value'] === null) ? 1 : $cap_option['value'];
      $newel_cap_choices[] = array('value' => $cap_option['code'] . ':' . $cap_qty, 'label' => $cap_option['name']);
    }
    bd_stairbuilder_render_type_field('newel_cap', 'Newel Caps', $newel_cap_choices);
    ?>
    <div class="form-row">
    <label for="cap_material">Newel Cap Material</label>
      <select id="cap_material" name="cap_material" class="bd-mat-select">
        <option value="">Pine</option>
        <option value="">Oak</option>
      </select>
    </div>
    </div><!-- /.bd-newel-fields -->
    <div class="form-row">
    <h4>Do you require Ballustrades?</h4>
    <div class="form-col">
    <label for="ballustrades-yes">Yes</label>
     <input id="ballustrades-yes" type="radio" name="ballustrades" value="true">
     <label for="ballustrades-no">No</label>
     <input id="ballustrades-no" type="radio" name="ballustrades" value="false" checked>
</div>
    </div>
</div>
<div id="ball">
<div class="form-row">
    <label for="hdr_material">Handrail Material</label>
      <select id="hdr_material" name="hdr_material" class="bd-mat-select">
        <option value="">Pine</option>
        <option value="">Oak</option>
      </select>
    </div>
    <?php
    // Handrail Style — collapses to a hidden field when only one type is defined.
    $handrail_type_choices = array();
    foreach ($handrail_type_options as $hr_option) {
      $handrail_type_choices[] = array('value' => $hr_option['code'], 'label' => $hr_option['name']);
    }
    bd_stairbuilder_render_type_field('handrail_type', 'Handrail Style', $handrail_type_choices);
    ?>
<div class="form-row">
    <label for="bsr_material">Baserail Material</label>
      <select id="bsr_material" name="bsr_material" class="bd-mat-select">
        <option value="pine:<?php echo $pine_baserail; ?>">Pine</option>
        <option value="oak:<?php echo $oak_baserail; ?>">Oak</option>
      </select>
    </div>
<div class="form-row">
    <label for="bal_material">Spindle Material</label>
      <?php // Material-first balustrading: formLogic.js fills the Material options
            // (Pine/Oak/Metal/Glass, only those defined) from the localised spindle
            // catalogue, then filters the Style list below to the chosen material. ?>
      <select id="bal_material" name="bal_material" class="bd-mat-select"></select>
    </div>
    <div class="form-row" id="spindle_style_row">
    <label for="spindle_type">Spindle Style</label>
      <?php // Populated + filtered by formLogic.js; the row hides (style becomes a
            // single hidden value) when the chosen material has only one style. ?>
      <select id="spindle_type" name="spindle_type"></select>
    </div>
</div>
    </div>
 </div>
<?php if ($delivery_section_enabled) : ?>
<div id="deliv" class="form-tab"><!--- Packaging / Delivery -->
<button type="button" class="sec-head" aria-expanded="false">
    <span class="sec-status" aria-hidden="true"><svg width="9" height="7" viewBox="0 0 9 7" fill="none"><path d="M1 3.5L3.2 5.7L8 1" stroke="currentColor" stroke-width="1.6"/></svg></span>
    <span class="sec-title">Packaging &amp; Delivery<span class="sec-sub"></span></span>
    <svg class="sec-chev" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5"/></svg>
  </button>
    <div class="tab-content">
      <?php if (!empty($project_delivery_date_options)) { ?>
      <div class="form-row">
        <label for="project_delivery_date">Project Delivery Date:</label>
        <select id="project_delivery_date" name="project_delivery_date">
          <option value="" disabled selected>Choose a delivery timeframe</option>
          <?php foreach ($project_delivery_date_options as $pdd_option): ?>
            <option value="<?php echo esc_attr($pdd_option['code']); ?>">
              <?php echo esc_html($pdd_option['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php } ?>
      <h5>Delivery Options</h5>
      <div class="delivery form-row rdbuttons">
          <input id="collected" type="radio" name="delivery" class="p radio__radio" value="collected" checked>
          <label for="collected" class="d radio__label vertical-icon"><img src="<?php echo plugin_dir_url( __FILE__ ) . '../assets/images/collected.svg' ?>" alt="icon"> Collected </label>
          <input id="delivery" type="radio" name="delivery" class="p radio__radio" value="kerbside">
          <label for="delivery" class="d radio__label vertical-icon"><img src="<?php echo plugin_dir_url( __FILE__ ) . '../assets/images/delivery.svg' ?>" alt="icon"> Kerb Side Delivery </label>
      </div>
      <?php if ($two_man_delivery_enabled) { ?>
      <div class="ksd form-row">
      <h5>Extra Options:</h5>
      <div class="chkboxbttn">
          <input id="duodeliv" type="checkbox" name="duodeliv" class="a chkbx" value="<?php echo $two_man_delivery_price; ?>">
          <label for="duodeliv" class="p chkbx__label">2 man delivery @ £<?php echo $two_man_delivery_price; ?> extra</label>
      </div></div>
      <?php } ?>
      <div class="pcode rdbuttons form-row">
        <input id="postcode" name="postcode" type="text" placeholder="Your postcode" class="input__form ng-pristine ng-valid ng-touched" style="margin-top: 0;">
        <button class="input__btn deliv_btn input__btn--active" data-price="0"> Update Delivery (£0) </button>
</div>
      <h5>I want my Stairs</h5>
      <div class="packg form-row rdbuttons">
          <input id="flatpkg" type="radio" name="package" class="d radio__radio" checked>
          <label for="flatpkg" class="p radio__label">Flat Packed </label>
          <?php if ($part_assembled_enabled) { ?>
          <input id="asspkg" type="radio" name="package" class="d radio__radio" value="<?php echo $part_assembled_price; ?>">
          <label for="asspkg" class="p radio__label">Part Assembled</label>
          <?php } ?>
      </div>
      <?php if ($fixing_kit_enabled || $extra_packaging_enabled) { ?>
      <h5>Add Ons</h5>
      <?php } ?>
      <div class="addon form-row rdbuttons">
        <?php if ($fixing_kit_enabled) { ?>
        <div class="chkboxbttn">
          <input id="fixkit" type="checkbox" name="addon_fixkit" class="a chkbx" checked value="<?php echo $fixing_kit_price; ?>">
          <label for="fixkit" class="p chkbx__label">Fixing Kit </label>
          </div>
        <?php } ?>
        <?php if ($extra_packaging_enabled) { ?>
          <div class="chkboxbttn">
          <input id="xtrap" type="checkbox" name="addon_xtrap" class="a chkbx" value="<?php echo $extra_packaging_price; ?>">
          <label for="xtrap" class="p chkbx__label">Extra Packaging </label>
          </div>
        <?php } ?>
      </div>
  </div>
</div>
<?php endif; ?>
<div id="contact" class="form-tab"><!--- Contact / Lead capture -->
    <button type="button" class="sec-head" aria-expanded="false">
    <span class="sec-status" aria-hidden="true"><svg width="9" height="7" viewBox="0 0 9 7" fill="none"><path d="M1 3.5L3.2 5.7L8 1" stroke="currentColor" stroke-width="1.6"/></svg></span>
    <span class="sec-title">Your Details<span class="sec-sub"></span></span>
    <svg class="sec-chev" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5"/></svg>
  </button>
    <div class="tab-content">
      <?php if ( ! $delivery_section_enabled ) : ?>
        <?php if (!empty($project_delivery_date_options)) { ?>
        <div class="form-row">
          <label for="project_delivery_date">Project Delivery Date *</label>
          <select id="project_delivery_date" name="project_delivery_date" required>
            <option value="" disabled selected>Choose a delivery timeframe</option>
            <?php foreach ($project_delivery_date_options as $pdd_option): ?>
              <option value="<?php echo esc_attr($pdd_option['code']); ?>">
                <?php echo esc_html($pdd_option['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php } ?>
        <div class="form-row">
          <label for="postcode">Postcode *</label>
          <input type="text" id="postcode" name="postcode" placeholder="Your postcode" required>
        </div>
      <?php endif; ?>
      <div class="form-row">
        <label for="contact_name">Name *</label>
        <input type="text" id="contact_name" name="contact_name" required>
      </div>
      <div class="form-row">
        <label for="contact_email">Email *</label>
        <input type="email" id="contact_email" name="contact_email" required>
      </div>
      <div class="form-row">
        <label for="contact_phone">Phone *</label>
        <input type="tel" id="contact_phone" name="contact_phone" required>
      </div>
      <div class="form-row">
        <label for="additional_notes">Additional notes:</label>
        <?php
        // Optional. maxlength is the convenience, not the enforcement — the
        // server truncates independently in baltic_stair_submit_lead().
        $bd_notes_max = defined( 'BD_STAIR_NOTES_MAX' ) ? BD_STAIR_NOTES_MAX : 1000;
        ?>
        <textarea id="additional_notes" name="additional_notes" rows="4"
          maxlength="<?php echo (int) $bd_notes_max; ?>"
          placeholder="Anything else we should know — for example your landing requirements."></textarea>
        <p class="bd-notes-count"><span id="additional_notes_count">0</span> / <?php echo (int) $bd_notes_max; ?></p>
      </div>
      <p class="contact-note"><small>We'll email your PDF quote to the address above and follow up to discuss your project.</small></p>
      <input type="hidden" id="vatRate" value="<?php echo do_shortcode('[vat_rate]'); ?>">
    </div>
</div>
</div><!--- form-tabs -->
      </div><!-- /.bd-scroll -->
    </div><!-- /.bd-scrollwrap -->

    <footer class="bd-panel-foot">
      <ul class="price-breakdown">
        <li><span class="price-label">Staircase Cost <span class="price-sub">(excl. VAT)</span></span><span id="priceCalc" class="price">£0.00</span></li>
        <li><span class="price-label">VAT</span><span id="vat" class="price">£0.00</span></li>
        <li class="price-total-row"><span class="price-label">Total <span class="price-sub">inc. VAT</span></span><span id="total" class="price">£0.00</span></li>
      </ul>
      <button id="sbbuybtn" class="sb-buynow" type="button">Get Free Quote</button>
      <p id="sb-submit-error" class="sb-submit-error" style="display:none;"></p>
      <?php
      // Reassurance line, set in Stairbuilder Pricing → General. Never saved =
      // the shipped default (so existing installs keep the line without touching
      // settings); saved-but-empty = deliberately hidden.
      $bd_footnote = stairbuilder_get_option( 'quote_footnote', null );
      if ( $bd_footnote === null ) {
        $bd_footnote = 'No payment taken now — every quote is checked by our Design Team.';
      }
      $bd_footnote = trim( (string) $bd_footnote );
      if ( $bd_footnote !== '' ) : ?>
      <p class="bd-foot-note"><?php echo esc_html( $bd_footnote ); ?></p>
      <?php endif; ?>
    </footer>
  </form>

  <section class="mm_breakout" aria-label="Live measurements">
    <header class="bd-panel-head bd-panel-head--dark">
      <h2 class="bd-panel-title">Measurements</h2>
      <span class="bd-panel-head-spacer"></span>
      <button type="button" class="bd-panel-collapse" data-bd-toggle="measurements" aria-label="Collapse measurements panel">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5.5 2.5L10 7l-4.5 4.5" stroke="currentColor" stroke-width="1.5"/></svg>
      </button>
    </header>
    <div class="bd-figs">
      <div class="bd-fig"><div class="bd-fig-lab">Floor to Floor <span class="mm-sub">(Total Rise)</span></div><div class="bd-fig-val"><span id="floor" class="msmnt">—</span></div></div>
      <div class="bd-fig"><div class="bd-fig-lab">Riser Height <span class="mm-sub">(Individual Rise)</span></div><div class="bd-fig-val"><span id="rise" class="msmnt">—</span></div></div>
      <div class="bd-fig"><div class="bd-fig-lab">Going <span class="mm-sub">(Tread Depth)</span></div><div class="bd-fig-val"><span id="tread" class="msmnt">—</span></div></div>
      <div class="bd-fig"><div class="bd-fig-lab">Width <span class="mm-sub">(Outside to Outside String)</span></div><div class="bd-fig-val"><span id="scwidth" class="msmnt">—</span></div></div>
<?php if ( $measurements_angle_enabled ) : ?>
      <div class="bd-fig"><div class="bd-fig-lab">Angle <span class="mm-sub">(Pitch)</span></div><div class="bd-fig-val"><span id="angl" class="msmnt">—</span></div></div>
<?php endif; ?>
    </div>
    <?php
    // Standing note, admin-editable. Same null-vs-empty contract as the
    // reassurance footnote above: option unset => shipped default; option set
    // to '' => the admin cleared it deliberately, so render nothing at all.
    $bd_meas_note = stairbuilder_get_option( 'measurements_note', null );
    if ( $bd_meas_note === null ) {
      $bd_meas_note = bd_measurements_note_default();
    }
    $bd_meas_note = trim( (string) $bd_meas_note );
    if ( $bd_meas_note !== '' ) : ?>
    <div class="bd-meas-note">
      <?php
      // Stored as plain text; the line breaks are the only formatting. Escape
      // first, then convert breaks — never the other way round, and never raw.
      echo nl2br( esc_html( $bd_meas_note ) );
      ?>
    </div>
    <?php endif; ?>
  </section>
</div><!-- /.bd-stairbuilder-layout -->
