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
    // Dismissing the Configure panel while a section is open counts as
    // closing that section for the tick-on-close rule — the customer has
    // seen it (BRIEF-01 §3.2, recommended option, both desktop pull-tab and
    // mobile sheet). The section keeps its is-open state for when the panel
    // reopens; only the tick bookkeeping records the close.
    if (!willOpen && panel === 'form') {
      layout.querySelectorAll('.form-tab.is-open').forEach(function (t) {
        const key = sectionKey(t);
        if (key) closedAfterOpen.add(key);
      });
      refreshSummaries(layout);
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
   * a head toggles .is-open on its .form-tab. Exclusive: opening a section
   * closes whichever was open, so the panel only ever shows one at a time and
   * the price footer stays in view. Clicking the open head still collapses it,
   * leaving none open. The form loads with every section closed.
   */

  /* Sections the user has actually opened this page load, by section key.
   * A section can't earn its completion tick until it's in here — several
   * sections are "done" from the moment the form renders, purely because their
   * fields carry defaults, and a wall of pre-ticked sections reads as "nothing
   * left to look at" and gets skipped. Opening is the whole test: any change
   * requires the section to be open first, and a customer who opens a section,
   * likes the defaults and closes it again has still made a decision. Not
   * persisted — a fresh page load starts unticked again. */
  const visitedSections = new Set();

  /* Sections that have been opened AND subsequently closed, by section key.
   * Feeds the opt-in data-bd-tick-on-close="1" completion rule (SPD decision,
   * confirmed Sept 2026 — do not re-open): Posts & Balustrades must not tick
   * on visit like the other sections, because "no posts / no balustrading" is
   * a deliberate choice and a visit-tick would imply a selection was made when
   * the customer may only have glanced in. It ticks once they have opened and
   * then CLOSED the section, whatever they selected inside — including
   * nothing. Closing means the section collapsing: via its own head, via
   * another section opening (the accordion is exclusive), or via the whole
   * Configure panel being dismissed while it is open — in every case they
   * have seen the section. Like visitedSections, not persisted. */
  const closedAfterOpen = new Set();

  function markVisited(tab) {
    const key = sectionKey(tab);
    if (key) visitedSections.add(key);
  }

  function setSectionOpen(tab, open) {
    // An actual open -> closed transition records the close for the
    // tick-on-close rule; is-open implies the section was visited.
    if (!open && tab.classList.contains('is-open')) {
      const key = sectionKey(tab);
      if (key) closedAfterOpen.add(key);
    }
    tab.classList.toggle('is-open', open);
    const head = tab.querySelector('.sec-head');
    if (head) head.setAttribute('aria-expanded', String(open));
    if (open) markVisited(tab);
  }

  /* Close every open section except `keep` (pass null to close all). Exported
   * on the layout element's owner window so formLogic.js can reuse it when it
   * opens Your Details to reveal a failed required field. */
  function closeOtherSections(layout, keep) {
    layout.querySelectorAll('.form-tab.is-open').forEach(function (t) {
      if (t !== keep) setSectionOpen(t, false);
    });
  }

  function wireSections(layout) {
    layout.addEventListener('click', function (e) {
      const head = e.target.closest('.sec-head');
      if (!head) return;
      const tab = head.closest('.form-tab');
      if (!tab) return;
      const willOpen = !tab.classList.contains('is-open');
      closeOtherSections(layout, tab);
      setSectionOpen(tab, willOpen);
      // The tick can only appear once a section is visited, so re-evaluate on
      // open as well as on field input.
      refreshSummaries(layout);
    });

    // Reveal-on-validation-failure hook for formLogic.js: open one section and
    // close the rest, so a red field can't be surfaced behind another section.
    window.bdOpenOnlySection = function (tab) {
      if (!tab) return;
      closeOtherSections(layout, tab);
      setSectionOpen(tab, true);
      refreshSummaries(layout);
    };
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
  // As txt(), but blank for a control the template has hidden (.bd-hidden-row):
  // on the landing configs the treads-in-turn selects and the half landing's
  // phantom middle flight still hold and still POST their locked values, but a
  // section summary must not list a choice the customer was never shown. Keyed
  // on the class, not computed style — the summary also renders while the
  // section is collapsed, when everything inside it is hidden regardless.
  function txtVisible(id) {
    const el = document.getElementById(id);
    if (!el || (el.closest && el.closest('.bd-hidden-row'))) return '';
    return txt(id);
  }
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
          kv('Floor Height', mm('floor-height')),
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
          txtVisible('treadit'),
          kv('After', txtVisible('treadat')),
          txtVisible('treadit2')
        ]);
      }
    },
    cnstr: {
      done: function () { return !!txt('construction_type'); },
      summary: function () {
        // Featured step is two independent selects (v2.23.0); the shared
        // helper owns the wording so the summary can't drift from the quote.
        var feat = (window.BuilderUtils && window.BuilderUtils.bdFeaturedStepLabel)
          ? window.BuilderUtils.bdFeaturedStepLabel(txt('left-featured-step'), txt('right-featured-step'))
          : '';
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
      // Visited gate first: an untouched section stays unticked even when its
      // defaults already satisfy done(). A data-bd-tick-on-close="1" section
      // ignores done() entirely — its tick means "opened and closed again",
      // regardless of what was selected inside (see closedAfterOpen above).
      try {
        done = (tab.dataset && tab.dataset.bdTickOnClose === '1')
          ? closedAfterOpen.has(key)
          : (visitedSections.has(key) && !!cfg.done());
      } catch (_) {}
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

    // Every section loads closed and unticked. Measurements used to open on
    // load, which pre-visited it and pre-ticked it; the customer now opens the
    // first section themselves, and the ticks track what they've been through.
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
