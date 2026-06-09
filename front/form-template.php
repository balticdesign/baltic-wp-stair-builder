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
?>

<div class="bd-stairbuilder-layout">
  <div id="canvas-container" class="sb-canvas-container">
    <canvas id="canvas" width="558" height="556"></canvas>
  </div>
  <form id="stairbuild" method="post">
    <div class="form-tabs">
  <div class="form-tab"><!--- Measurments -->
  <input type="radio" class="form-chk" id="chck1" name="rd">
  <label class="tab-label" for="chck1">Measurements</label>
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
            <label for="floor-height">Floor Height:</label>
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
            <label for="going">Going:</label>
            <input type="number" id="going" name="going" value="">
        </div>
        <div class="form-row">
            <label for="stair-width">Width:</label>
            <input type="number" id="stair-width" name="stair-width" value="">
            <?php if ($flight2) {?>
            <label for="stair-width2">Flight 2 Width:</label>
            <input type="number" id="stair-width2" name="stair-width2" value="">
            <?php } ?>
            <?php if ($flight3) {?>
            <label for="stair-width3">Flight 3 Width:</label>
            <input type="number" id="stair-width3" name="stair-width3" value="">
            <?php } ?>
            <input type="hidden" id="widthmulti" value="<?php echo $width_mp; ?>">
            <input type="hidden" id="setupfee" value="<?php echo $setup_fee; ?>">
        </div>
        </div>
        </div>
        <?php if ($flight2) {?>
          <div class="form-tab"><!--- Flights -->
  <input type="radio" class="form-chk" id="flights" name="rd">
  <label class="tab-label" for="flights">Sections</label>
    <div id="tits" class="tab-content">
          <div class="form-row">
            <h4>Flight 1</h4>
        <label for="treadit">Treads before Turn:</label>
        <input type="number" id="treadbt" name="treadbt" value="6">
        </div>
        <div class="form-row">
        <label for="treadit">Treads in Turn:</label>
        <select id="treadit" name="treadit">
        <option value="1">Quarter Landing</option>
        <option value="2">2 Winders</option>
        <option value="3">3 Winders</option>
        <?php if ($flight3) {?>
        <option value="4">Half Landing</option>
        <?php } ?>
        </select>
        </div>
        <h4>Flight 2</h4>
        <div class="form-row">
        <label for="treadat">Treads after Turn:</label>
        <input type="number" id="treadat" name="treadat" value="3">
        </div>
        <?php if ($flight3) {?> 
         <div class="form-row">
        <label for="treadit2">Treads in Turn2:</label>
        <select id="treadit2" name="treadit2">
        <option value="1">Quarter Landing</option>
        <option value="2">2 Winders</option>
        <option value="3">3 Winders</option>
        </select>
        </div>
        <h4>Flight 3</h4>
        <div class="form-row">
        <label for="treadat2">Treads after Turn2:</label>
        <input type="number" id="treadat2" name="treadat2" value="" readonly>
        </div>
        <?php } ?>
        </div>
        </div>
        <?php } ?>
    
    <div class="form-tab"><!--- Posts & Balustrades -->
  <input type="radio" class="form-chk" id="psts1" name="rd">
  <label class="tab-label" for="psts1">Posts & Balustrades</label>
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
    <h3>Top (Flt.3)</h3>
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
      <h3>Box 2</h3>
      <div class="form-row">
        <div class="form-col">
        <label for="to-post2">Flt.2 Top Outside</label>
            <input id="to-post2" type="checkbox" name="to-post2" value="1">
            </div><div class="form-col">
        <label for="bo-post2">Flt.3 Bottom Outside</label>
            <input id="bo-post2" type="checkbox" name="bo-post2" value="1">
            </div><div class="form-col">
            <label for="box-post2">Flt.2 Box Corner</label>
            <input id="box-post2" type="checkbox" name="box-post2" value="1">
        </div>
    </div>
    <?php } ?>
    <?php if ($flight2) {?> 
      <h3>Box</h3>
      <div class="form-row">
        <div class="form-col">
        <label for="to-post">Flt.1 Top Outside</label>
            <input id="to-post" type="checkbox" name="to-post" value="1">
            </div><div class="form-col">
        <label for="bo-post">Flt.2 Bottom Outside</label>
            <input id="bo-post" type="checkbox" name="bo-post" value="1">
            </div><div class="form-col">
            <label for="box-post">Flt.1 Box Corner</label>
            <input id="box-post" type="checkbox" name="box-post" value="1">
        </div>
    </div>
    <?php } ?>
    <h3>Bottom (Flt.1)</h3>
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
    <div id="posts">
    <div class="form-row">
    <label for="newel_type">Newel Style</label>
      <select id="newel_type" name="newel_type">
    <?php if (!empty($newel_type_options)): ?>
      <?php foreach ($newel_type_options as $nt_option): ?>
        <option value="<?php echo esc_attr($nt_option['code']); ?>"><?php echo esc_html($nt_option['name']); ?></option>
      <?php endforeach; ?>
    <?php else: ?>
      <option value="" disabled>No options available</option>
    <?php endif; ?>
      </select>
    </div>
    <div class="form-row">
    <label for="newel_cap">Newel Caps</label>
      <select id="newel_cap" name="newel_cap">
        <option value="none:0">None</option>
    <?php foreach ($cap_type_options as $cap_option):
      $cap_qty = ($cap_option['value'] === '' || $cap_option['value'] === null) ? 1 : $cap_option['value']; ?>
        <option value="<?php echo esc_attr($cap_option['code'] . ':' . $cap_qty); ?>"><?php echo esc_html($cap_option['name']); ?></option>
    <?php endforeach; ?>
      </select>
    </div>
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
    <label for="handrail_type">Handrail Style</label>
      <select id="handrail_type" name="handrail_type">
    <?php if (!empty($handrail_type_options)): ?>
      <?php foreach ($handrail_type_options as $hr_option): ?>
        <option value="<?php echo esc_attr($hr_option['code']); ?>"><?php echo esc_html($hr_option['name']); ?></option>
      <?php endforeach; ?>
    <?php else: ?>
      <option value="" disabled>No options available</option>
    <?php endif; ?>
      </select>
    </div>
<div class="form-row">
    <label for="spindle_type">Spindle Style</label>
      <select id="spindle_type" name="spindle_type">
    <?php if (!empty($spindle_type_options)): ?>
      <?php foreach ($spindle_type_options as $sp_option): ?>
        <option value="<?php echo esc_attr($sp_option['code']); ?>"><?php echo esc_html($sp_option['name']); ?></option>
      <?php endforeach; ?>
    <?php else: ?>
      <option value="" disabled>No options available</option>
    <?php endif; ?>
      </select>
    </div>
</div>
    </div>
 </div>
    <div id="cnstr" class="form-tab"><!--- Construction -->
    <input type="radio" class="form-chk" id="chck2" name="rd">
    <label class="tab-label" for="chck2">Construction</label>
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
    </div>
    <div class="form-row">
    <label for="construction_type">Construction Type:</label>
      <select id="construction_type" name="construction_type">
    <?php if (!empty($construction_options)): ?>
      <?php foreach ($construction_options as $c_option): ?>
        <option data-price="<?php echo esc_attr($c_option['value']); ?>" value="<?php echo esc_attr($c_option['code']); ?>">
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
        <option data-price="<?php echo esc_attr($tp_option['value']); ?>" value="<?php echo esc_attr($tp_option['code']); ?>">
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

        <label for="feature_tread">Featured Tread:</label>
      <select id="feature_tread" class="form-select">
        <option data-config="0,0" value="None" selected="selected">None</option>
        <option data-config="2,0" value="Left Bullnose Step">Left Bullnose Step</option>
        <option data-config="0,2" value="Right Bullnose Step">Right Bullnose Step</option>
        <option data-config="2,2" value="Double Bullnose Step">Double Bullnose Step</option>
        <option data-config="1,0" value="Left D Step">Left Curtail Step</option>
        <option data-config="0,1" value="Right D Step">Right Curtail Step</option>
        <option data-config="1,1" value="Double D Step">Double Curtail Step</option>
        <!--<option value="Left Double Curtail Step">Left Curtail Step</option> -->
        <!--<option value="Right Double Curtail Step">Right Curtail Step</option> -->
        <!--<option value="Double Curtail Step">Double Curtail Step</option> -->
        <option data-config="4,0"  value="Left Curtail and Bullnose Step">Left Curtail and Bullnose Step</option>
        <option data-config="0,4" value="Right Curtail and Bullnose Step">Right Curtail and Bullnose Step</option>
        <option data-config="4,4" value="Double Curtail and Bullnose Step">Double Curtail and Bullnose Step</option>
      </select>
  
      <!--<label for="left-featured-step">Left Hand Side:</label>-->
      <select id="left-featured-step" name="left-featured-step">
        <option value="0">None</option>
        <option value="1">Curtail</option>
        <option value="2">Bullnose</option>
        <!--<option value="3">Curtail & Double Curtail</option>-->
        <option value="4">Full Curtail & Bullnose</option>
      </select>

      <!--<label for="right-featured-step">Right Hand Side:</label>-->
      <select id="right-featured-step" name="right-featured-step">
        <option value="0">None</option>
        <option value="1">Curtail</option>
        <option value="2">Bullnose</option>
        <!--<option value="3">Curtail & Double Curtail</option>-->
        <option value="4">Full Curtail & Bullnose</option>
      </select>
      <input type="hidden" id="leftFeatStep" value="0">
      <input type="hidden" id="rightFeatStep" value="0">
   
    </div>
</div>
</div>
<div id="mat" class="form-tab"><!--- Material -->
<input type="radio" class="form-chk" id="chck3" name="rd">
    <label class="tab-label" for="chck3">Material</label>
    <div class="tab-content">
    <button type="button" id="all_pine">Set all to Pine</button>
    <button type="button" id="all_oak">Set all to Oak</button>
    <div class="form-row">
    <label for="stringer_material">Stringer:</label>
    <select id="stringer_material" name="stringer_material">
    <?php if (!empty($stringer_options)): ?>
      <?php foreach ($stringer_options as $str_option): ?>
        <option value="<?php echo esc_attr($str_option['code']) . ':' . esc_attr($str_option['value']); ?>">
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
  <select id="tread_material" name="tread_material">
    <?php if (!empty($tread_options)): ?>
      <?php foreach ($tread_options as $tr_option): ?>
        <option value="<?php echo esc_attr($tr_option['code']) . ':' . esc_attr($tr_option['value']); ?>">
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
      <select id="riser_material" name="riser_material">
      <?php if (!empty($riser_options)) { ?>
      <?php foreach ($riser_options as $r_option) { ?>
        <option value="<?php echo esc_attr($r_option['code']) . ':' . esc_attr($r_option['value']); ?>">
          <?php echo esc_html($r_option['name']); ?>
        </option>
      <?php } } else { ?>
      <option value="">No options available</option>
    <?php } ?>
      </select>
    </div>
    <div class="form-row">
    <label for="newel_material">Newel Posts:</label>
      <select id="newel_material" name="newel_material">
        <option value="">Pine</option>
        <option value="">Oak</option>
      </select>
    </div>
    <div class="form-row">
  <label for="cap_material">Newel Caps:</label>
  <select id="cap_material" name="cap_material">
    <option value="">Pine</option>
    <option value="">Oak</option>
  </select>
</div>
<div class="form-row">
  <label for="bal_material">Spindles(Baluster):</label>
  <select id="bal_material" name="bal_material">
    <option value="">Pine</option>
    <option value="">Oak</option>
  </select>
</div>
<div class="form-row">
  <label for="hdr_material">Handrails:</label>
  <select id="hdr_material" name="hdr_material">
    <option value="">Pine</option>
    <option value="">Oak</option>
  </select>
</div>
<div class="form-row">
  <label for="bsr_material">Baserails:</label>
  <select id="bsr_material" name="bsr_material">
    <option value="pine:<?php echo $pine_baserail; ?>">Pine</option>
    <option value="oak:<?php echo $oak_baserail; ?>">Oak</option>
  </select>
</div>
<?php echo $bonuslogic; ?>
</div>
</div>
<div id="deliv" class="form-tab"><!--- Packaging / Delivery -->
<input type="radio" class="form-chk" id="chck4" name="rd">
    <label class="tab-label" for="chck4">Packaging & Delivery</label>
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
          <input id="collected" type="radio" name="delivery" class="p radio__radio" checked>
          <label for="collected" class="d radio__label vertical-icon"><img src="<?php echo plugin_dir_url( __FILE__ ) . '../assets/images/collected.svg' ?>" alt="icon"> Collected </label>
          <input id="delivery" type="radio" name="delivery" class="p radio__radio">
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
          <input id="fixkit" type="checkbox" name="addon" class="a chkbx" checked value="<?php echo $fixing_kit_price; ?>">
          <label for="fixkit" class="p chkbx__label">Fixing Kit </label>
          </div>
        <?php } ?>
        <?php if ($extra_packaging_enabled) { ?>
          <div class="chkboxbttn">
          <input id="xtrap" type="checkbox" name="addon" class="a chkbx" value="<?php echo $extra_packaging_price; ?>">
          <label for="xtrap" class="p chkbx__label">Extra Packaging </label>
          </div>
        <?php } ?>
          <input type="hidden" id="vatRate" value="<?php echo do_shortcode('[vat_rate]'); ?>">
      </div>
  </div>
</div>
<div id="contact" class="form-tab"><!--- Contact / Lead capture -->
    <input type="radio" class="form-chk" id="chck5" name="rd">
    <label class="tab-label" for="chck5">Your Details</label>
    <div class="tab-content">
      <div class="form-row">
        <label for="contact_name">Name *</label>
        <input type="text" id="contact_name" name="contact_name" required>
      </div>
      <div class="form-row">
        <label for="contact_email">Email *</label>
        <input type="email" id="contact_email" name="contact_email" required>
      </div>
      <div class="form-row">
        <label for="contact_phone">Phone</label>
        <input type="tel" id="contact_phone" name="contact_phone">
      </div>
      <p class="contact-note"><small>We'll email your PDF quote to the address above and follow up to discuss your project.</small></p>
    </div>
</div>
<div class="form-tab">
        <input type="radio" class="form-chk" id="rd5" name="rd">
        <label for="rd5" class="tab-close">Close others &times;</label>
      </div>
</div><!--- form-tabs -->
  </form>
  <div class="breakout">
    <div id="pricetotal">
      <h4>Price</h4>
      <span id="total" class="price">£0.00</span>
      <span id="vatnote">inc. VAT</span>
      <!-- Subtotal + VAT kept hidden: still populated by priceCalc.js and read
           by formLogic.js at submit for the captured lead + quote PDF. -->
      <span id="priceCalc" class="price" style="display:none;">£0.00</span>
      <span id="vat" class="price" style="display:none;">£0.00</span>
    </div>
    <button id="sbbuybtn" class="sb-buynow" type="button">Get Free Quote</button>
    <p id="sb-submit-error" class="sb-submit-error" style="display:none;"></p>
    <button type="button" class="bd-panel-close" data-bd-toggle="quote" aria-label="Close Quote">&times;</button>
  </div>
  <div class="mm_breakout">
    <h4>Floor to floor</h4>
    <span id="floor" class="msmnt">00</span>
    <h4>Tread length</h4>
    <span id="tread" class="msmnt">00</span>
    <h4>Rise per tread</h4>
    <span id="rise" class="msmnt">00</span>
    <h4>Width</h4>
    <span id="scwidth" class="msmnt">00</span>
    <h4>Angle</h4>
    <span id="angl" class="msmnt">00 &deg;</span>
  </div>
</div><!-- /.bd-stairbuilder-layout -->