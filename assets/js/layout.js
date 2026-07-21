/*
 * Baltic Stairbuilder — floating-panel layout controller (v1.4.2)
 *
 * - Three viewport-fixed collapsible panels around a relative canvas-container.
 * - State persisted in localStorage (one key per panel).
 * - Canvas BITMAP dimensions kept in sync with its CSS-rendered box so
 *   Stairs.js draws at native resolution (no stretching).
 *
 * The whole .bd-stairbuilder-layout block is hoisted to <body> on init so
 * page-builder ancestors with transform/filter (Bricks animations etc.)
 * cannot hijack the fixed-positioning containing-block of our panels.
 *
 * Loose dependency: window.onLoad — defined as a function declaration at
 * the top of straightFlight.js / quarterTurn.js / halfTurn.js, hoisted to
 * window. Calling it triggers a redraw at the new bitmap dimensions.
 */
(function () {
  'use strict';

  const STORAGE_KEYS = {
    form:         'stairbuilder_panel_form',
    measurements: 'stairbuilder_panel_measurements'
  };

  // Canvas sizing. 1.7.0+ owns container height in CSS (viewport model);
  // the canvas bitmap matches the rendered container box and width still
  // caps at CANVAS_MAX_WIDTH. CANVAS_RATIO_W/H kept as historical
  // reference for the pre-1.7.0 aspect-ratio layout in case of revert.
  const CANVAS_MAX_WIDTH = 1200;
  // const CANVAS_RATIO_W   = 568;
  // const CANVAS_RATIO_H   = 506;

  const PANEL_TARGETS = {
    form:         '#stairbuild',
    measurements: '.mm_breakout'
  };

  const PANEL_LABELS = {
    form:         'Configure',
    measurements: 'Measurements'
  };

  function readStoredState(panel) {
    try {
      const v = window.localStorage.getItem(STORAGE_KEYS[panel]);
      if (v === 'open' || v === 'collapsed') return v;
    } catch (_) {}
    return 'open';
  }

  function writeStoredState(panel, state) {
    try { window.localStorage.setItem(STORAGE_KEYS[panel], state); } catch (_) {}
  }

  function setPanelState(layout, panel, state) {
    layout.classList.toggle('is-' + panel + '-collapsed', state === 'collapsed');
    writeStoredState(panel, state);
    resizeCanvas();
  }

  function isMobile() {
    return window.matchMedia('(max-width: 767px)').matches;
  }

  function togglePanel(layout, panel) {
    const collapsed = layout.classList.contains('is-' + panel + '-collapsed');
    const willOpen = collapsed;
    setPanelState(layout, panel, willOpen ? 'open' : 'collapsed');
    // Mobile: one sheet at a time. Opening a panel collapses the other so the
    // two bottom bars never both expand at once.
    if (willOpen && isMobile()) {
      const other = panel === 'form' ? 'measurements' : 'form';
      setPanelState(layout, other, 'collapsed');
    }
  }

  /**
   * Hoist the layout wrapper to <body> so no page-builder ancestor with
   * transform/filter/perspective can capture our panels' `position: fixed`
   * containing block. BEFORE hoisting, lift #canvas-container out of the
   * wrapper so it stays in document flow at the shortcode's original
   * location (sitting between the theme header and footer like normal
   * page content). After this, the wrapper contains only the floating
   * panels (form, mm_breakout, breakout, handles, FABs).
   */
  function hoistLayoutToBody(layout) {
    const canvasContainer = layout.querySelector('#canvas-container');
    if (canvasContainer && canvasContainer.parentNode === layout && layout.parentNode) {
      layout.parentNode.insertBefore(canvasContainer, layout);
    }
    if (layout.parentNode !== document.body) {
      document.body.appendChild(layout);
    }
  }

  function injectControls(layout) {
    Object.keys(PANEL_TARGETS).forEach(function (panel) {
      const target = layout.querySelector(PANEL_TARGETS[panel]);
      if (!target) return;

      // Close/collapse control lives in the panel's own header markup
      // (data-bd-toggle) now, so no injected X. Inject the edge pull-tab
      // (desktop) and the FAB (mobile) only.
      const handle = document.createElement('button');
      handle.type = 'button';
      handle.className = 'bd-handle bd-handle--' + panel;
      handle.dataset.bdToggle = panel;
      handle.setAttribute('aria-label', 'Open ' + PANEL_LABELS[panel]);
      handle.textContent = PANEL_LABELS[panel];
      layout.appendChild(handle);

      // Mobile launcher bar. Hidden on desktop; on mobile the two bars stack
      // at the bottom of the screen (Measurements above Configure) and each
      // expands its panel upward as a sheet — see the mobile media query.
      const fab = document.createElement('button');
      fab.type = 'button';
      fab.className = 'bd-fab bd-fab--' + panel;
      fab.dataset.bdToggle = panel;
      fab.setAttribute('aria-label', 'Open ' + PANEL_LABELS[panel]);
      fab.textContent = panel === 'form' ? 'Configure Staircase' : 'Staircase Measurements';
      layout.appendChild(fab);
    });
  }

  function wireToggles(layout) {
    layout.addEventListener('click', function (e) {
      const trigger = e.target.closest('[data-bd-toggle]');
      if (!trigger) return;
      e.preventDefault();
      togglePanel(layout, trigger.dataset.bdToggle);
    });
  }

  /* ============ Accordion sections ============
   * Sections are class-based (.form-tab / .sec-head / .tab-content): clicking
   * a head toggles .is-open on its .form-tab. Independent open/close — click
   * an open head to collapse it — plus a header "Close others" that leaves
   * only the first open section, matching the design mockup.
   */
  function setSectionOpen(tab, open) {
    tab.classList.toggle('is-open', open);
    const head = tab.querySelector('.sec-head');
    if (head) head.setAttribute('aria-expanded', String(open));
  }

  function wireSections(layout) {
    layout.addEventListener('click', function (e) {
      const head = e.target.closest('.sec-head');
      if (!head) return;
      const tab = head.closest('.form-tab');
      if (!tab) return;
      setSectionOpen(tab, !tab.classList.contains('is-open'));
    });

    const closeOthers = layout.querySelector('#bd-close-others');
    if (closeOthers) {
      closeOthers.addEventListener('click', function () {
        const open = layout.querySelectorAll('.form-tab.is-open');
        open.forEach(function (tab, i) { if (i > 0) setSectionOpen(tab, false); });
      });
    }
  }

  /* ============ Completion ticks + dynamic section summaries ============
   * Each section's config points at the fields that make it "done" and builds
   * a short summary shown under the title. Read on load + on every input in
   * the form so ticks and summaries track the live configuration. */
  function txt(id) {
    const el = document.getElementById(id);
    if (!el) return '';
    if (el.tagName === 'SELECT') {
      const opt = el.options[el.selectedIndex];
      return opt ? opt.text.trim() : '';
    }
    return (el.value || '').trim();
  }
  function mm(id) { const v = txt(id); return v ? Number(v).toLocaleString() + ' mm' : ''; }
  function join(parts) { return parts.filter(Boolean).join(' · '); }
  // "Key value" pair, or '' when the value is empty — so each section summary
  // can list every choice it holds with a short label, dropping blank ones.
  function kv(label, value) { return value ? label + ' ' + value : ''; }

  // Keyed by the section's .tab-content id (or the .form-tab id). done() drives
  // the completion tick; summary() builds the (up to 2-line) sub-title listing
  // that section's choices with short labels. Blank fields are omitted.
  const SECTION_CONFIG = {
    msrm: {
      done: function () { return txt('floor-height') && txt('going') && txt('stair-width'); },
      summary: function () {
        return join([
          txt('floor-height') && (Number(txt('floor-height')).toLocaleString() + ' mm rise'),
          kv('Going', mm('going')),
          kv('Width', mm('stair-width')),
          kv('Turn', txt('sc-direction'))
        ]);
      }
    },
    tits: {
      done: function () { return txt('treadbt') !== ''; },
      summary: function () {
        return join([
          kv('Before', txt('treadbt')),
          txt('treadit'),
          kv('After', txt('treadat')),
          txt('treadit2')
        ]);
      }
    },
    cnstr: {
      done: function () { return !!txt('construction_type'); },
      summary: function () {
        var feat = txt('feature_tread');
        return join([
          txt('construction_type'),
          txt('tread-profile'),
          txt('building_regs'),
          (feat && feat !== 'None') ? feat : ''
        ]);
      }
    },
    mat: {
      done: function () { return !!txt('stringer_material'); },
      summary: function () {
        return join([
          kv('Stringer', txt('stringer_material')),
          kv('Treads', txt('tread_material')),
          kv('Risers', txt('riser_material'))
        ]);
      }
    },
    posts: {
      done: function () { var n = txt('newel-posts'); return n && n !== 'None Required'; },
      summary: function () {
        var b = document.querySelector('input[name="ballustrades"]:checked');
        var hasBal = b && b.value === 'true';
        return join([
          kv('Newels', txt('newel-posts')),
          hasBal ? (kv('Spindles', txt('spindle_type')) || 'Balustrades') : 'No balustrades'
        ]);
      }
    },
    deliv: {
      done: function () { return !!document.querySelector('input[name="delivery"]:checked'); },
      summary: function () {
        var d = document.querySelector('input[name="delivery"]:checked');
        var p = document.querySelector('input[name="package"]:checked');
        return join([
          d ? (d.value === 'kerbside' ? 'Kerbside delivery' : 'Collected') : '',
          p ? (p.id === 'asspkg' ? 'Part assembled' : 'Flat packed') : '',
          txt('project_delivery_date')
        ]);
      }
    },
    contact: {
      done: function () { return txt('contact_name') && txt('contact_email'); },
      summary: function () {
        return join([txt('contact_name'), txt('contact_email'), txt('contact_phone')]);
      }
    }
  };

  function sectionKey(tab) {
    const content = tab.querySelector('.tab-content');
    if (content && content.id && SECTION_CONFIG[content.id]) return content.id;
    // Fall back to the section's own id (e.g. #cnstr, #mat, #deliv, #contact
    // carry the key on the .form-tab wrapper, not the content div).
    if (tab.id && SECTION_CONFIG[tab.id]) return tab.id;
    return null;
  }

  function refreshSummaries(layout) {
    layout.querySelectorAll('.form-tab').forEach(function (tab) {
      const key = sectionKey(tab);
      if (!key) return;
      const cfg = SECTION_CONFIG[key];
      let done = false, summary = '';
      try { done = !!cfg.done(); } catch (_) {}
      try { summary = cfg.summary() || ''; } catch (_) {}
      tab.classList.toggle('is-done', done);
      const sub = tab.querySelector('.sec-sub');
      if (sub) sub.textContent = summary;
    });
  }

  function wireSummaries(layout) {
    const form = layout.querySelector('#stairbuild');
    if (!form) return;
    const update = function () { refreshSummaries(layout); };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    // Flight scripts / priceCalc mutate fields programmatically; catch those
    // by also refreshing shortly after load once defaults are populated.
    refreshSummaries(layout);
    setTimeout(update, 400);
  }

  function applyInitialState(layout) {
    Object.keys(STORAGE_KEYS).forEach(function (panel) {
      setPanelState(layout, panel, readStoredState(panel));
    });
  }

  /**
   * Match the canvas BITMAP (HTML width/height attributes) to its CSS
   * RENDERED size. They are independent: CSS scales whatever the bitmap
   * holds. If they don't match, the diagram looks stretched.
   *
   * Stairs.js still overwrites canvas.height during draw with its own
   * computed value (out of scope to change). Our paired CSS rule
   * `height: auto` lets the rendered height follow the bitmap height,
   * preventing vertical stretch even after that overwrite.
   */
  let resizePending = false;
  function sizeCanvasToContainer() {
    if (resizePending) return;
    resizePending = true;
    requestAnimationFrame(function () {
      resizePending = false;
      const c = document.getElementById('canvas');
      const container = document.getElementById('canvas-container');
      if (!c || !container) return;

      // Viewport-driven sizing (1.7.0+): the container's CSS rule owns
      // height (min(70vh, 700px) with a 360px floor); we just match the
      // canvas bitmap to the rendered box. Width still caps at
      // CANVAS_MAX_WIDTH so high-DPI 4K monitors don't get an oversized
      // bitmap.
      const w = Math.min(container.clientWidth || CANVAS_MAX_WIDTH, CANVAS_MAX_WIDTH);
      const h = container.clientHeight;
      if (!w || !h) return;

      if (c.width === w && c.height === h) return;
      c.width = w;
      c.height = h;
      // container.style.height is CSS-controlled — don't set it from JS.

      // Trigger a Stairs.js redraw at the new bitmap size. The flight scripts
      // expose window.onLoad (hoisted) as the canonical draw entry point.
      if (typeof window.onLoad === 'function') {
        try { window.onLoad(); } catch (_) {}
      }
    });
  }
  // Back-compat alias for callers that still use the old name.
  const resizeCanvas = sizeCanvasToContainer;

  function initCanvasResize() {
    // Look up by id rather than via the layout wrapper — the canvas-container
    // is no longer a descendant of .bd-stairbuilder-layout after hoist.
    const canvas = document.getElementById('canvas');
    if (!canvas) return;
    if (typeof ResizeObserver === 'function') {
      new ResizeObserver(resizeCanvas).observe(canvas);
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
  }

  function init() {
    const layout = document.querySelector('.bd-stairbuilder-layout');
    if (!layout) return;

    hoistLayoutToBody(layout);
    injectControls(layout);
    wireToggles(layout);
    wireSections(layout);
    wireSummaries(layout);
    applyInitialState(layout);
    // Mobile: Configure takes priority — start with it expanded and
    // Measurements collapsed to its bar (one sheet open at a time).
    if (isMobile()) {
      setPanelState(layout, 'measurements', 'collapsed');
      setPanelState(layout, 'form', 'open');
    }
    initCanvasResize();

    // Open the first section (Measurements) by default so the panel doesn't
    // load fully collapsed.
    const firstTab = layout.querySelector('.form-tab');
    if (firstTab) setSectionOpen(firstTab, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
