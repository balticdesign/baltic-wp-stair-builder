/**
 * Baltic Stairbuilder — front-end diagnostics
 *
 * Activates only when ?sb_debug=1 is in the URL OR window.SB_DEBUG === true.
 * Prints a structured snapshot at DOM-ready and on every form change so you
 * can spot exactly where NaN enters the pipeline.
 */
(function () {
  'use strict';

  var qs = new URLSearchParams(window.location.search);
  var ENABLED = qs.get('sb_debug') === '1' || window.SB_DEBUG === true;
  if (!ENABLED) return;

  var GROUP = '[Stairbuilder DEBUG]';

  function safe(fn, fallback) {
    try { return fn(); } catch (e) { return fallback; }
  }

  function snapshot(tag) {
    var $ = window.jQuery;
    if (!$) { console.log(GROUP, tag, 'jQuery not loaded yet'); return; }

    var report = {
      tag: tag,
      time: new Date().toISOString(),

      env: {
        jQueryVersion: $.fn && $.fn.jquery,
        hasBuilderUtils: typeof window.BuilderUtils === 'object',
        hasStairs: typeof window.Stairs !== 'undefined',
        hasOnLoad: typeof window.onLoad === 'function',
        hasGrabFormValues: typeof window.grabFormValues === 'function',
        hasCalculateTotalPrice: typeof window.calculateTotalPrice === 'function',
        hasBdDiagramColours: typeof window.bd_diagram_colours !== 'undefined',
        bdDiagramColours: window.bd_diagram_colours,
        stairBuilderVars: window.stairBuilderVars
      },

      formInputs: {
        'floor-height': safe(function () { return $('#floor-height').val(); }),
        'going':        safe(function () { return $('#going').val(); }),
        'stair-width':  safe(function () { return $('#stair-width').val(); }),
        'risers_count_options': safe(function () { return $('#risers option').length; }),
        'risers_value':         safe(function () { return $('#risers').val(); }),
        'widthmulti':   safe(function () { return $('#widthmulti').val(); }),
        'setupfee':     safe(function () { return $('#setupfee').val(); }),
        'vatRate':      safe(function () { return $('#vatRate').val(); }),
        'tread_material_options':    safe(function () { return $('#tread_material option').length; }),
        'stringer_material_options': safe(function () { return $('#stringer_material option').length; }),
        'riser_material_options':    safe(function () { return $('#riser_material option').length; }),
        'newel_material_options':    safe(function () { return $('#newel_material option').length; })
      },

      grabFormValues: safe(function () {
        return typeof window.grabFormValues === 'function' ? window.grabFormValues() : '<<undefined>>';
      }, '<<error in grabFormValues>>'),

      builderUtilsResult: safe(function () {
        if (!window.BuilderUtils || !window.BuilderUtils.getStaircaseConfig) return '<<no BuilderUtils>>';
        var fh = parseFloat($('#floor-height').val()) || 0;
        var g  = parseFloat($('#going').val()) || 0;
        return window.BuilderUtils.getStaircaseConfig(g, fh);
      }, '<<error in getStaircaseConfig>>'),

      domReadout: {
        floor:   safe(function () { return $('#floor').text(); }),
        tread:   safe(function () { return $('#tread').text(); }),
        rise:    safe(function () { return $('#rise').text(); }),
        scwidth: safe(function () { return $('#scwidth').text(); }),
        angl:    safe(function () { return $('#angl').text(); }),
        priceCalc: safe(function () { return $('#priceCalc').text(); }),
        vat:       safe(function () { return $('#vat').text(); }),
        total:     safe(function () { return $('#total').text(); })
      }
    };

    console.groupCollapsed(GROUP, tag);
    console.log(report);
    console.groupEnd();
    return report;
  }

  window.SB_DEBUG_SNAPSHOT = snapshot;

  if (window.jQuery) {
    jQuery(document).ready(function () {
      snapshot('DOM ready (immediate)');
      setTimeout(function () { snapshot('DOM ready + 50ms'); }, 50);
      setTimeout(function () { snapshot('DOM ready + 500ms'); }, 500);

      jQuery('#stairbuild').on('change', ':input', function () {
        var id = jQuery(this).attr('id');
        snapshot('change: ' + id);
      });
    });
  }

  console.log(GROUP, 'diagnostics module loaded — watch for snapshots');
})();
