/**
 * Pan/zoom input handler for the stairbuilder canvas (1.7.0).
 *
 * Mutates Stairs.viewport.{zoom,panX,panY} in response to wheel, pointer
 * (drag), and two-touch (pinch) input, then calls Stairs.draw to repaint.
 * Staircase-local geometry is untouched — the transform from local space
 * to canvas pixels lives in Stairs.applyViewportTransform.
 *
 * Pointer Events API unifies mouse/touch/stylus. Pinch is handled
 * manually since Pointer Events don't emit a native pinch.
 */
(function () {
    'use strict';

    var canvas;
    var dragStart = null;          // { x, y, panX, panY } at pointerdown
    var pinchStart = null;         // { dist, zoom, midX, midY } at two-touch start
    var activeTouches = new Map(); // pointerId -> { x, y }

    function redraw() {
        if (window.Stairs && Stairs.canvas) Stairs.draw(Stairs.canvas);
    }

    function clampZoom(z) {
        return Math.max(Stairs.ZOOM_MIN, Math.min(Stairs.ZOOM_MAX, z));
    }

    // Anchored zoom: the world point at (anchorX, anchorY) on the canvas
    // stays under that screen point after the zoom step. Anchor passed in
    // absolute canvas coords (0..canvas.width, 0..canvas.height).
    //
    // Derivation: applyViewportTransform maps local (lx,ly) to screen via
    //   sx = (lx - stairCentre) * zoom + canvasCentre + pan
    // so pan is an offset _from canvas centre_, not from origin. To pin
    // the world point under the anchor through a zoom-by-k step:
    //   newPan = (anchor - canvasCentre) * (1 - k) + pan * k
    // For anchor == canvasCentre this collapses to newPan = pan * k —
    // pan scales with zoom and the staircase stays at its current offset
    // from centre. That's what we want for wheel zoom.
    function zoomAt(anchorX, anchorY, factor) {
        var v = Stairs.viewport;
        var oldZoom = v.zoom;
        var newZoom = clampZoom(oldZoom * factor);
        if (newZoom === oldZoom) return;

        var k = newZoom / oldZoom;
        var dx = anchorX - canvas.width / 2;
        var dy = anchorY - canvas.height / 2;
        v.panX = dx * (1 - k) + v.panX * k;
        v.panY = dy * (1 - k) + v.panY * k;
        v.zoom = newZoom;
        redraw();
    }

    function onWheel(e) {
        e.preventDefault();
        // Anchor wheel zoom to canvas centre, not cursor. Cursor-anchored
        // zoom drifts the staircase toward whichever corner the cursor is
        // in — fine for map UIs, wrong for a single-subject diagram. Pinch
        // zoom (touch) still anchors at the finger midpoint because that's
        // the intuitive pinch behaviour.
        var factor = e.deltaY < 0 ? 1.1 : 1 / 1.1;
        zoomAt(canvas.width / 2, canvas.height / 2, factor);
    }

    function onPointerDown(e) {
        canvas.setPointerCapture(e.pointerId);
        activeTouches.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (activeTouches.size === 2) {
            var pts = Array.from(activeTouches.values());
            var dx = pts[1].x - pts[0].x;
            var dy = pts[1].y - pts[0].y;
            var rect = canvas.getBoundingClientRect();
            pinchStart = {
                dist: Math.hypot(dx, dy),
                zoom: Stairs.viewport.zoom,
                midX: (pts[0].x + pts[1].x) / 2 - rect.left,
                midY: (pts[0].y + pts[1].y) / 2 - rect.top
            };
            dragStart = null;
            Stairs.viewport.isDragging = false;
            canvas.style.cursor = 'grab';
        } else if (activeTouches.size === 1) {
            dragStart = {
                x: e.clientX,
                y: e.clientY,
                panX: Stairs.viewport.panX,
                panY: Stairs.viewport.panY
            };
            Stairs.viewport.isDragging = true;
            canvas.style.cursor = 'grabbing';
        }
    }

    function onPointerMove(e) {
        if (!activeTouches.has(e.pointerId)) return;
        activeTouches.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (pinchStart && activeTouches.size === 2) {
            var pts = Array.from(activeTouches.values());
            var dx = pts[1].x - pts[0].x;
            var dy = pts[1].y - pts[0].y;
            var newDist = Math.hypot(dx, dy);
            if (!newDist) return;
            var targetZoom = clampZoom(pinchStart.zoom * (newDist / pinchStart.dist));
            // Same corrected anchored-zoom formula as zoomAt(), inlined
            // because pinch goes via a different state path (uses
            // pinchStart.zoom as the baseline, not v.zoom).
            var v = Stairs.viewport;
            var k = targetZoom / v.zoom;
            var ax = pinchStart.midX - canvas.width / 2;
            var ay = pinchStart.midY - canvas.height / 2;
            v.panX = ax * (1 - k) + v.panX * k;
            v.panY = ay * (1 - k) + v.panY * k;
            v.zoom = targetZoom;
            redraw();
        } else if (dragStart && activeTouches.size === 1) {
            Stairs.viewport.panX = dragStart.panX + (e.clientX - dragStart.x);
            Stairs.viewport.panY = dragStart.panY + (e.clientY - dragStart.y);
            redraw();
        }
    }

    function onPointerUp(e) {
        activeTouches.delete(e.pointerId);
        if (activeTouches.size < 2) pinchStart = null;
        if (activeTouches.size === 0) {
            dragStart = null;
            Stairs.viewport.isDragging = false;
            canvas.style.cursor = 'grab';
        }
    }

    function resetView() {
        Stairs.viewport.zoom = Stairs.viewport.fitZoom;
        Stairs.viewport.panX = 0;
        Stairs.viewport.panY = 0;
        redraw();
    }

    function init() {
        canvas = document.getElementById('canvas');
        if (!canvas || !window.Stairs) return;

        canvas.style.cursor = 'grab';
        canvas.style.touchAction = 'none'; // prevent browser pinch/scroll on canvas

        canvas.addEventListener('wheel',         onWheel, { passive: false });
        canvas.addEventListener('pointerdown',   onPointerDown);
        canvas.addEventListener('pointermove',   onPointerMove);
        canvas.addEventListener('pointerup',     onPointerUp);
        canvas.addEventListener('pointercancel', onPointerUp);
        canvas.addEventListener('dblclick',      resetView);

        var resetBtn = document.getElementById('bd-viewport-reset');
        if (resetBtn) resetBtn.addEventListener('click', resetView);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
