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
    measurements: 'stairbuilder_panel_measurements',
    quote:        'stairbuilder_panel_quote'
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
    measurements: '.mm_breakout',
    quote:        '.breakout'
  };

  const PANEL_LABELS = {
    form:         'Configure',
    measurements: 'Measurements',
    quote:        'Quote'
  };

  function readStoredState(panel) {
    // 1.7.0: quote always defaults to 'open' on page load — it's the
    // lead-gen entry point so a stale localStorage 'collapsed' from
    // earlier sessions shouldn't hide it. Users can still collapse it
    // via the tab in-session; that just doesn't persist for quote.
    if (panel === 'quote') return 'open';
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

  function togglePanel(layout, panel) {
    const collapsed = layout.classList.contains('is-' + panel + '-collapsed');
    setPanelState(layout, panel, collapsed ? 'open' : 'collapsed');
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

      if (!target.querySelector('.bd-panel-close')) {
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'bd-panel-close';
        close.dataset.bdToggle = panel;
        close.setAttribute('aria-label', 'Close ' + PANEL_LABELS[panel]);
        close.textContent = '×';
        target.appendChild(close);
      }

      const handle = document.createElement('button');
      handle.type = 'button';
      handle.className = 'bd-handle bd-handle--' + panel;
      handle.dataset.bdToggle = panel;
      handle.setAttribute('aria-label', 'Open ' + PANEL_LABELS[panel]);
      handle.textContent = PANEL_LABELS[panel];
      layout.appendChild(handle);

      const fab = document.createElement('button');
      fab.type = 'button';
      fab.className = 'bd-fab bd-fab--' + panel;
      fab.dataset.bdToggle = panel;
      fab.setAttribute('aria-label', PANEL_LABELS[panel]);
      fab.textContent = panel === 'form' ? '☰' : panel === 'measurements' ? '📐' : '£';
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

      // Trigger Stairs.js redraw via the existing change-handler in the
      // flight scripts (which is bound to inputs inside #stairbuild).
      const checked = document.querySelector('#stairbuild input[name="rd"]:checked');
      if (checked) {
        checked.dispatchEvent(new Event('change', { bubbles: true }));
      } else if (typeof window.onLoad === 'function') {
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
    applyInitialState(layout);
    initCanvasResize();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
