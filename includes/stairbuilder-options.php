<?php
/**
 * Pricing-related AJAX endpoints + helpers used by the configurator.
 *
 * This file used to host a WooCommerce add-to-cart pipeline. That has been
 * replaced by the lead-capture flow in stairbuilder-lead-capture.php; only
 * the price-lookup helpers and option-driven endpoints survive here.
 *
 * VAT shortcode `[vat_rate]` lives in stairbuilder-lead-capture.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Find a repeater row by its `code`, falling back to the first row when the
 * code is missing or not found. Returns null only for an empty/invalid set.
 *
 * @param array  $rows Repeater rows (each with at least a `code`).
 * @param string $code Selected code from the form.
 * @return array|null
 */
function bd_sb_find_row($rows, $code) {
  if (!is_array($rows) || empty($rows)) {
    return null;
  }
  foreach ($rows as $row) {
    if (is_array($row) && isset($row['code']) && (string) $row['code'] === (string) $code) {
      return $row;
    }
  }
  // Sensible default = first row. For caps a "none"/unknown code is harmless:
  // the qty multiplier (0) zeroes the cap cost regardless of which row's
  // price is returned, preserving the calc contract.
  return is_array($rows[0]) ? $rows[0] : null;
}

/**
 * Build the pine/oak <option> pair for a material select from a repeater row.
 *
 * Output contract is unchanged from the legacy flat-key version — the price is
 * encoded as `value="pine:PRICE"` with the WC id in data-product-id — so
 * priceCalc.js needs no changes. When the row's per-row Use-Product-ID switch
 * is on, the live WooCommerce product price overrides the direct price.
 *
 * @param array|null $row Resolved repeater row.
 * @return string[] [pine <option>, oak <option>]
 */
function getPriceAndID($row){
  $pine_id = $oak_id = null;

  if (!is_array($row)) {
    return [
      '<option data-product-id="" data-material-mode="wood" value="pine:0">Pine</option>',
      '<option data-product-id="" data-material-mode="wood" value="oak:0">Oak</option>'
    ];
  }

  // Spindle rows can be Metal or Glass single-material modes (newel/cap/handrail
  // rows have no material_mode and fall through to the Pine/Oak default). For the
  // single-material modes the front-end material select collapses to one option;
  // priceCalc.js reads the mode + glass basis from the option's data-attributes.
  $mode = isset($row['material_mode']) ? $row['material_mode'] : 'wood_pine_oak';

  $resolve_price = function($price, $id) {
    if (!empty($id) && function_exists('wc_get_product')) {
      $product = wc_get_product($id);
      if ($product) { return $product->get_price(); }
    }
    return ($price === '' || $price === null) ? 0 : $price;
  };

  if ($mode === 'metal' || $mode === 'glass') {
    $use_pid = !empty($row['use_product_id']);
    if ($mode === 'metal') {
      $pid   = $use_pid ? (isset($row['metal_id']) ? $row['metal_id'] : null) : null;
      $price = $resolve_price(isset($row['metal_price']) ? $row['metal_price'] : 0, $pid);
      return [
        '<option data-product-id="'.esc_attr($pid).'" data-material-mode="metal" value="metal:'.esc_attr($price).'">Metal</option>'
      ];
    }
    // glass
    $pid   = $use_pid ? (isset($row['glass_id']) ? $row['glass_id'] : null) : null;
    $price = $resolve_price(isset($row['glass_price']) ? $row['glass_price'] : 0, $pid);
    $unit  = (isset($row['pricing_unit']) && $row['pricing_unit'] === 'per_panel') ? 'per_panel' : 'per_metre';
    $pw    = isset($row['panel_width_mm']) && $row['panel_width_mm'] !== '' ? $row['panel_width_mm'] : '';
    $gap   = isset($row['panel_gap_mm']) && $row['panel_gap_mm'] !== '' ? $row['panel_gap_mm'] : '';
    return [
      '<option data-product-id="'.esc_attr($pid).'" data-material-mode="glass"'
        .' data-pricing-unit="'.esc_attr($unit).'" data-panel-width="'.esc_attr($pw).'" data-panel-gap="'.esc_attr($gap).'"'
        .' value="glass:'.esc_attr($price).'">Glass</option>'
    ];
  }

  // Wood (Pine / Oak) — unchanged behaviour.
  $pinePrice = isset($row['pine_price']) ? $row['pine_price'] : 0;
  $oakPrice  = isset($row['oak_price'])  ? $row['oak_price']  : 0;

  if (!empty($row['use_product_id'])) {
    $pine_id = isset($row['pine_id']) ? $row['pine_id'] : null;
    $oak_id  = isset($row['oak_id'])  ? $row['oak_id']  : null;

    $pine_product = function_exists('wc_get_product') ? wc_get_product($pine_id) : null;
    if ($pine_product) { $pinePrice = $pine_product->get_price(); }

    $oak_product = function_exists('wc_get_product') ? wc_get_product($oak_id) : null;
    if ($oak_product) { $oakPrice = $oak_product->get_price(); }
  }

  if ($pinePrice === '' || $pinePrice === null) { $pinePrice = 0; }
  if ($oakPrice === '' || $oakPrice === null) { $oakPrice = 0; }

  return [
    '<option data-product-id="'.$pine_id.'" data-material-mode="wood" value="pine:' . $pinePrice . '">' . 'Pine' . '</option>',
    '<option data-product-id="'.$oak_id.'" data-material-mode="wood" value="oak:' . $oakPrice . '">' . 'Oak' . '</option>'
  ];
}

function fetch_sp_prices() {
  if ( ! baltic_stair_prepare_raw_response( 'wp_ajax_fetch_sp_prices' ) ) {
    wp_die( '', '', array( 'response' => 500 ) );
  }
  if (!isset($_POST['security'])) {
    wp_send_json_error('Nonce not received');
  }
  if (!wp_verify_nonce($_POST['security'], 'sb-ajax-nonce')) {
    wp_send_json_error('Nonce verification failed');
  }

  $newelType   = isset($_POST['newelType'])   ? sanitize_text_field(wp_unslash($_POST['newelType']))   : '';
  $capType     = isset($_POST['capType'])      ? sanitize_text_field(wp_unslash($_POST['capType']))     : '';
  $hrType      = isset($_POST['hrType'])       ? sanitize_text_field(wp_unslash($_POST['hrType']))      : '';
  $spindleType = isset($_POST['spindleType'])  ? sanitize_text_field(wp_unslash($_POST['spindleType'])) : '';

  // Component pricing is now driven by admin-managed repeater rows. Each row
  // carries its own name/code, per-row Use-Product-ID switch, and pine/oak
  // price + product ID — resolved by code (default = first row).
  $newel_rows    = stairbuilder_get_option('newel_types', array());
  $cap_rows      = stairbuilder_get_option('cap_types', array());
  $handrail_rows = stairbuilder_get_option('handrail_types', array());
  $spindle_rows  = stairbuilder_get_option('spindle_types', array());

  $form_options = array(
    'newel_options'    => getPriceAndID(bd_sb_find_row($newel_rows, $newelType)),
    'cap_options'      => getPriceAndID(bd_sb_find_row($cap_rows, $capType)),
    'handrail_options' => getPriceAndID(bd_sb_find_row($handrail_rows, $hrType)),
    'spindle_options'  => getPriceAndID(bd_sb_find_row($spindle_rows, $spindleType)),
  );

  echo json_encode($form_options);
  wp_die();
}
add_action('wp_ajax_fetch_sp_prices', 'fetch_sp_prices');
add_action('wp_ajax_nopriv_fetch_sp_prices', 'fetch_sp_prices');

/**
 * Front-end spindle catalogue, localised to JS (stairBuilderVars.spindles).
 *
 * Unlike newel/cap/handrail (Style → Material via AJAX), spindle balustrading is
 * Material-first: the customer picks Pine/Oak/Metal/Glass, then the Style list
 * filters to that material's rows. formLogic.js needs every row's mode + resolved
 * price client-side to do that instantly, so we resolve here (honouring the per-row
 * Use-Product-ID switch, exactly like getPriceAndID) and hand JS a flat array.
 *
 * @return array[] [{code,name,mode, pine?,oak?|metal?|glass?,pricing_unit?,panel_width?,panel_gap?}]
 */
function bd_stairbuilder_spindle_frontend_rows() {
  $rows = stairbuilder_get_option('spindle_types', array());
  $out  = array();
  if (!is_array($rows)) {
    return $out;
  }
  foreach ($rows as $r) {
    if (!is_array($r) || empty($r['code'])) {
      continue;
    }
    $mode_raw = isset($r['material_mode']) ? $r['material_mode'] : 'wood_pine_oak';
    $mode     = ($mode_raw === 'metal') ? 'metal' : (($mode_raw === 'glass') ? 'glass' : 'wood');
    $use_pid  = !empty($r['use_product_id']);
    $resolve  = function ($price, $id) use ($use_pid) {
      if ($use_pid && !empty($id) && function_exists('wc_get_product')) {
        $product = wc_get_product($id);
        if ($product) { return (float) $product->get_price(); }
      }
      return ($price === '' || $price === null) ? 0.0 : (float) $price;
    };
    $get = function ($k) use ($r) { return isset($r[$k]) ? $r[$k] : null; };

    $row = array(
      'code' => (string) $r['code'],
      'name' => isset($r['name']) ? (string) $r['name'] : (string) $r['code'],
      'mode' => $mode,
    );
    if ($mode === 'wood') {
      $row['pine'] = $resolve($get('pine_price'), $get('pine_id'));
      $row['oak']  = $resolve($get('oak_price'), $get('oak_id'));
    } elseif ($mode === 'metal') {
      $row['metal'] = $resolve($get('metal_price'), $get('metal_id'));
    } else { // glass
      $row['glass']        = $resolve($get('glass_price'), $get('glass_id'));
      $row['pricing_unit'] = ($get('pricing_unit') === 'per_panel') ? 'per_panel' : 'per_metre';
      $row['panel_width']  = ($get('panel_width_mm') === null || $get('panel_width_mm') === '') ? 0.0 : (float) $get('panel_width_mm');
      $row['panel_gap']    = ($get('panel_gap_mm') === null || $get('panel_gap_mm') === '') ? 0.0 : (float) $get('panel_gap_mm');
    }
    $out[] = $row;
  }
  return $out;
}

function get_stepCost($featNumber, $material) {
  if ($featNumber == 0) {
    return 0;
  }
  // Row order IS the feature value order: index 0 = value 1, index 1 = value 2, etc.
  //   1 = curtail   2 = bullnose   3 = double curtail plus single curtail (DCC)
  //   4 = double curtail plus bullnose (DCB)
  // matching Stairs.js (isLeftCurtail = left == 1, isLeftBullnose = left == 2) and
  // the option labels on #left-featured-step / #right-featured-step.
  //
  // Rows 1 and 2 were transposed until v2.23.0, so a curtail was charged at the
  // bullnose price and a bullnose at the curtail price — under-charging SPD by
  // £23-25 a side on curtails and over-charging the customer by the same on
  // bullnoses. The drawing and the works order were always correct; only this
  // lookup was wrong. Rows 3 and 4 were never affected.
  $stepMaterialPrices = [
    ['mdf_curtail_price', 'ply_curtail_price', 'pine_curtail_price', 'oak_curtail_price'],
    ['mdf_bullnose_price', 'ply_bullnose_price', 'pine_bullnose_price', 'oak_bullnose_price'],
    ['mdf_dbl_curtail_price', 'ply_dbl_curtail_price', 'pine_dbl_curtail_price', 'oak_dbl_curtail_price'],
    ['mdf_dcb_curtail_price', 'ply_dcb_curtail_price', 'pine_dcb_curtail_price', 'oak_dcb_curtail_price']
  ];

  $materialMap = ['mdf' => 0, 'ply' => 1, 'pine' => 2, 'oak' => 3];
  $material    = strtolower( (string) $material );
  // An unknown material used to emit an undefined-key warning, which printed
  // ahead of the JSON body and broke the response the caller tried to parse.
  if ( ! isset( $materialMap[ $material ] ) || ! isset( $stepMaterialPrices[ $featNumber - 1 ] ) ) {
    return 0;
  }
  $materialIndex = $materialMap[ $material ];

  $price = stairbuilder_get_option($stepMaterialPrices[$featNumber - 1][$materialIndex]);

  return $price;
}

/**
 * Clears the way for a response that is not HTML — a binary stream, a CSV, a
 * JSON payload. Call as the FIRST statement of any such handler, before any
 * header() call.
 *
 * Why this exists as a shared helper rather than a fix per handler: anything
 * already written to the output buffer ends up in front of the payload, and
 * the polluting output does not have to be ours. On a licensee's site it can
 * come from any plugin or theme installed, which we do not control. Chasing
 * each new cause is unbounded work; defending the responder is bounded, and is
 * the right layer. Three separate handlers were corrupted this way before this
 * function existed (v2.23.0 AJAX, v2.24.1 PDF stream, and the v2.24.0 CSV
 * export which was exposed but never observed failing).
 *
 * It hides because it depends on display_errors — on under WP_DEBUG locally,
 * off on most production hosts. Latent everywhere, visible almost nowhere.
 *
 * Discarding buffers opened by other plugins is correct and expected for a
 * responder of this kind; every file-download implementation in WordPress does
 * the same. No attempt is made to preserve foreign buffers.
 *
 * @param  string $hook Name of the calling hook, for the debug log.
 * @return bool   False when headers have already gone out and the caller must
 *                abort. True when the buffer is clean and it is safe to emit.
 */
function baltic_stair_prepare_raw_response( $hook = '' ) {
  // Checked FIRST: once headers are on the wire the corruption has already
  // shipped and no amount of buffer clearing undoes it. The caller has to fail
  // cleanly instead. A user who sees "download unavailable" can try again; a
  // user handed a silently truncated PDF cannot tell anything went wrong.
  $file = '';
  $line = 0;
  if ( headers_sent( $file, $line ) ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
      error_log( sprintf(
        '[baltic-stairbuilder] %s: headers already sent at %s:%d — aborting rather than emitting a corrupt response.',
        $hook !== '' ? $hook : 'raw response',
        $file,
        $line
      ) );
    }
    return false;
  }

  // Buffers nest innermost-first, and an inner buffer's content has not yet
  // reached the outer one — so each chunk discarded was emitted BEFORE the
  // chunk discarded previously. Prepend to rebuild the original order.
  $discarded = '';
  while ( ob_get_level() > 0 ) {
    $chunk = ob_get_clean();
    if ( false !== $chunk ) {
      $discarded = $chunk . $discarded;
    }
  }

  // Bake in the diagnosis. Working out what had polluted the PDF stream in
  // v2.24.1 took a byte-level look at the response; the next occurrence on a
  // licensee's site should be one line in the log instead.
  if ( '' !== $discarded && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( sprintf(
      '[baltic-stairbuilder] %s: discarded %d bytes of buffered output before responding: %s',
      $hook !== '' ? $hook : 'raw response',
      strlen( $discarded ),
      // Enough to identify the culprit without filling the log with a page.
      substr( preg_replace( '/\s+/', ' ', $discarded ), 0, 500 )
    ) );
  }

  return true;
}

/**
 * Map a stored component code back to its admin-defined human label for
 * display. Leads store codes in form_data; if a code is later renamed/removed
 * the raw code is shown (same fragility as the legacy plugin — out of scope).
 *
 * $code_key / $name_key default to the plain 'code'/'name' sub-fields used by
 * the newel/cap/handrail/spindle repeaters. The stringer/tread/riser/
 * construction/profile repeaters use prefixed sub-keys (e.g. stringer_code /
 * stringer_name), so those callers pass the matching keys explicitly.
 *
 * Lived as a closure inside templates/stairbuilder_pdf.php until v2.24.0. The
 * Enquiries admin list resolves the same codes, and a template-local closure
 * cannot be reached from there. Home is this file rather than
 * stairbuilder-prices.php: that one is required from front/form-template.php
 * only, so a helper there would not load in admin.
 */
function bd_code_label( $option_key, $code, $code_key = 'code', $name_key = 'name' ) {
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
}

/**
 * Staircase type as one display string, from a lead's form_data.
 *
 * The same derivation the PDF template performs inline. Added here for the
 * Enquiries admin list; the PDF is explicitly out of bounds this release, so
 * it still carries its own copy and should adopt this helper next time it is
 * legitimately open — one place owning the wording is the v2.23.0 §9 rule.
 *
 * Leads captured before the stair_type / stair_config hidden inputs existed
 * carry neither and return '' rather than a guess.
 */
function bd_staircase_type_label( $form_data ) {
  if ( ! is_array( $form_data ) ) {
    return '';
  }
  $types   = array( 'straight' => 'Straight Flight', 'quarter' => 'Quarter Turn', 'half' => 'Half Turn' );
  $configs = array( 'landing' => 'Landing', 'winder' => 'Winder', 'double_quarter' => 'Double Quarter Landing' );

  $type   = isset( $types[ $form_data['stair_type'] ?? '' ] ) ? $types[ $form_data['stair_type'] ] : '';
  $config = isset( $configs[ $form_data['stair_config'] ?? '' ] ) ? $configs[ $form_data['stair_config'] ] : '';

  return trim( $type . ( ( $type && $config ) ? ' — ' . $config : '' ) );
}

/**
 * Is this lead a half-turn HALF LANDING, from its form_data?
 *
 * The one config where internal flight 2 is collapsed to zero treads and is the
 * landing itself, so the customer-facing surfaces number the flights 1 and 2 and
 * name the middle section "Landing" rather than "Flight 2" (v2.26.0 on the form,
 * v2.28.0 on the quote PDF). Shared so the form, the PDF and anything added later
 * cannot disagree about which config that is.
 *
 * Leads captured before the stair_type / stair_config hidden inputs existed carry
 * neither, so they return false and keep the original flight numbering — their
 * quotes were produced under it and must still read the way they were sent.
 */
function bd_is_half_landing( $form_data ) {
  if ( ! is_array( $form_data ) ) {
    return false;
  }
  return 'half' === ( $form_data['stair_type'] ?? '' )
    && 'landing' === ( $form_data['stair_config'] ?? '' );
}

/**
 * Featured step treatment labels, keyed by the renderer's own value vocabulary.
 * SPD's wording — Andy and Daniel read these on the works order.
 */
function bd_featured_step_labels() {
  return array(
    '0' => 'None',
    '1' => 'Curtail Step',
    '2' => 'Bullnose Step',
    '3' => 'Double Curtail plus Single Curtail',
    '4' => 'Double Curtail plus Bullnose',
  );
}

/**
 * §9 — the one place a (left, right) pair becomes a display string. Used by the
 * PDF, the customer email, the works order and the form entry; the configurator
 * summary uses the JS twin in builderUtils.js. Do not re-derive per surface.
 *
 * "Both sides:" only when the two values are identical.
 */
function bd_featured_step_label( $left, $right ) {
  $labels = bd_featured_step_labels();
  $l_key  = (string) $left;
  $r_key  = (string) $right;
  $l      = isset( $labels[ $l_key ] ) ? $labels[ $l_key ] : 'None';
  $r      = isset( $labels[ $r_key ] ) ? $labels[ $r_key ] : 'None';

  if ( 'None' === $l && 'None' === $r ) {
    return 'None';
  }
  if ( $l_key === $r_key ) {
    return 'Both sides: ' . $l;
  }
  if ( 'None' === $r ) {
    return 'Left: ' . $l;
  }
  if ( 'None' === $l ) {
    return 'Right: ' . $r;
  }
  return 'Left: ' . $l . ' / Right: ' . $r;
}

/**
 * T&G landing selection labels — SPD's wording, half:landing only.
 *
 * Keyed by the value the select submits. The landing is a priced line item
 * rather than a stated inclusion: "Landing not included" means the customer is
 * supplying their own boards, so it costs nothing extra; "T&G landing included"
 * adds tandg_landing_boards_price. NEITHER changes the drawing — the landing is
 * structurally present either way — so nothing here may gate rendering.
 */
function bd_tandg_landing_labels() {
  return array(
    'not_included' => 'Landing not included',
    'included'     => 'T&G landing included',
  );
}

/**
 * The one place a stored tandg_landing value becomes a display string. Used by
 * the form, the quote PDF and the Enquiries detail view — do not re-derive it
 * per surface (the same rule that collapsed bd_code_label() in v2.24.0 and
 * bd_staircase_type_label() in v2.25.0).
 *
 * Returns '' for an absent or unrecognised value rather than guessing, so every
 * lead that predates this field — and every config that never had the select —
 * simply drops the row instead of asserting something about a landing.
 */
function bd_tandg_landing_label( $value ) {
  $labels = bd_tandg_landing_labels();
  $key    = (string) $value;
  return isset( $labels[ $key ] ) ? $labels[ $key ] : '';
}

/**
 * §6 — decompose a legacy single-enum featured step into (left, right).
 *
 * Leads captured before v2.23.0 stored the combined label chosen from the old
 * #feature_tread dropdown. Read-time only: nothing rewrites stored entries.
 * Returns the pair as renderer values. "Curtail and Bullnose Step" is the
 * legacy DCB product — a two-step feature on ONE side — not a curtail composed
 * with a bullnose.
 */
function bd_featured_step_from_legacy( $legacy ) {
  $map = array(
    'None'                             => array( '0', '0' ),
    'Left Bullnose Step'               => array( '2', '0' ),
    'Right Bullnose Step'              => array( '0', '2' ),
    'Double Bullnose Step'             => array( '2', '2' ),
    'Left Curtail Step'                => array( '1', '0' ),
    'Left D Step'                      => array( '1', '0' ),
    'Right Curtail Step'               => array( '0', '1' ),
    'Right D Step'                     => array( '0', '1' ),
    'Double Curtail Step'              => array( '1', '1' ),
    'Double D Step'                    => array( '1', '1' ),
    'Left Curtail and Bullnose Step'   => array( '4', '0' ),
    'Right Curtail and Bullnose Step'  => array( '0', '4' ),
    'Double Curtail and Bullnose Step' => array( '4', '4' ),
  );
  $key = trim( (string) $legacy );
  return isset( $map[ $key ] ) ? $map[ $key ] : array( '0', '0' );
}

/**
 * Both-sides uplift multiplier (§5.3). One global value — never per material
 * and never per combination. Out-of-range stored values fall back to 1 (no
 * uplift) rather than silently scaling by something nonsensical; the admin
 * screen rejects them on save, so this only catches a hand-edited option row.
 */
function bd_featured_step_multiplier() {
  $raw = stairbuilder_get_option( 'featured_step_both_sides_multiplier', null );
  if ( $raw === null || $raw === '' ) {
    return 1.5;                      // shipped default
  }
  $m = (float) $raw;
  return ( $m >= 0.1 && $m <= 5 ) ? $m : 1.0;
}

/**
 * Featured step subtotal (§5.1). Per-side sum, with the uplift applied to the
 * COMBINED price only when both sides carry a feature.
 *
 * Full precision — no rounding here. The plugin formats component subtotals at
 * display time (priceCalc.js toFixed(2)) and rounds nothing in between, so the
 * multiplier lands on the unrounded sum exactly as §5.4 requires.
 */
function bd_featured_step_total( $leftCost, $rightCost, $multiplier ) {
  $sum = (float) $leftCost + (float) $rightCost;
  if ( $leftCost <= 0 || $rightCost <= 0 ) {
    return $sum;                     // one side bare → no uplift
  }
  return $sum * (float) $multiplier;
}

function get_featured_step() {
  if ( ! baltic_stair_prepare_raw_response( 'wp_ajax_get_featured_step' ) ) {
    wp_die( '', '', array( 'response' => 500 ) );
  }
  $treadMaterial = isset( $_POST['tread_material'] ) ? sanitize_text_field( wp_unslash( $_POST['tread_material'] ) ) : '';
  $leftStep      = isset( $_POST['leftFeat'] ) ? (int) $_POST['leftFeat'] : 0;
  $rightStep     = isset( $_POST['rightFeat'] ) ? (int) $_POST['rightFeat'] : 0;

  $leftCost  = get_stepCost($leftStep, $treadMaterial);
  $rightCost = get_stepCost($rightStep, $treadMaterial);

  // §5.6 — a selected treatment whose price field is empty or zero can't be
  // quoted. Rather than sending out a partial figure, the configuration takes
  // the same price-on-application route as the POA construction types.
  $poa = ( $leftStep > 0 && ! ( (float) $leftCost > 0 ) )
      || ( $rightStep > 0 && ! ( (float) $rightCost > 0 ) );

  $multiplier = bd_featured_step_multiplier();

  $feat_options = array(
    'leftCost'   => $leftCost,
    'rightCost'  => $rightCost,
    'multiplier' => $multiplier,
    // Uplift applied when both sides carry a feature (§5.1).
    'total'      => bd_featured_step_total( $leftCost, $rightCost, $multiplier ),
    'poa'        => $poa ? 1 : 0,
  );
  echo json_encode($feat_options);
  wp_die();
}
add_action('wp_ajax_get_featured_step', 'get_featured_step');
add_action('wp_ajax_nopriv_get_featured_step', 'get_featured_step');

add_action( 'wp_ajax_nopriv_get_delivery_price', 'get_delivery_price' );
add_action( 'wp_ajax_get_delivery_price', 'get_delivery_price' );

function get_delivery_price() {
  if ( ! baltic_stair_prepare_raw_response( 'wp_ajax_get_delivery_price' ) ) {
    wp_die( '', '', array( 'response' => 500 ) );
  }
  if (!isset($_POST['security'])) {
    wp_send_json_error('Nonce not received');
  }
  if (!wp_verify_nonce($_POST['security'], 'sb-ajax-nonce')) {
    wp_send_json_error('Nonce verification failed');
  }

  $postcode = strtoupper($_POST['postcode']);
  $greaterLondonPostcodesString = stairbuilder_get_option('greater_london_pcodes');
  $mainlandUKPostcodesString = stairbuilder_get_option('mainland_uk_pcodes');
  $greaterLondonDeliveryPrice = stairbuilder_get_option('greater_london_delivery_price');
  $mainlandUKDeliveryPrice = stairbuilder_get_option('mainland_uk_delivery_price');

  $deliveryPrice = null;

  $greaterLondonPostcodes = array_map('trim', explode(",", $greaterLondonPostcodesString));
  $mainlandUKPostcodes = array_map('trim', explode(",", $mainlandUKPostcodesString));

  $postcodePrefix = substr($postcode, 0, 2);

  if (in_array($postcodePrefix, $greaterLondonPostcodes)) {
    $deliveryPrice = $greaterLondonDeliveryPrice;
  } else if (in_array($postcodePrefix, $mainlandUKPostcodes)) {
    $deliveryPrice = $mainlandUKDeliveryPrice;
  } else {
    $deliveryPrice = 'Invalid Postcode';
  }

  wp_send_json_success($deliveryPrice);
}
