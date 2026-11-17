/**
 * CampusFind Phase 4A — Isolated Optical Liquid Glass Prototype
 *
 * Precomputes rounded stadium bezel normal displacement maps into static
 * Canvas PNG data URIs on init/resize only (never recalculated on scroll or mousemove).
 * Pointer movement updates CSS custom properties only.
 */
(function () {
    'use strict';

    var body = document.body;
    var rootStyle = document.documentElement.style;
    var reducedMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    function prefersReducedMotion() {
        return Boolean(reducedMotionQuery && reducedMotionQuery.matches);
    }

    /**
     * Detect reliable support for backdrop-filter: url(#svgFilter).
     * Chromium supports SVG filter references in backdrop-filter;
     * Safari and Firefox fall back cleanly to frosted CSS glass.
     */
    function detectSvgBackdropSupport() {
        var supportsBackdropUrl = false;
        if (window.CSS && typeof window.CSS.supports === 'function') {
            supportsBackdropUrl =
                window.CSS.supports('backdrop-filter', 'url(#cf-lg-nav-filter)') ||
                window.CSS.supports('-webkit-backdrop-filter', 'url(#cf-lg-nav-filter)');
        }
        var ua = navigator.userAgent || '';
        var isChromium = /Chrome|Chromium|Edg\//.test(ua) && !/OPR\//.test(ua) ? true : /Chrome|Chromium|Edg\//.test(ua);
        var isFirefox = /Firefox\//.test(ua);
        var isSafariOnly = /Safari\//.test(ua) && !/Chrome|Chromium|Edg\//.test(ua);

        return Boolean(supportsBackdropUrl && isChromium && !isFirefox && !isSafariOnly);
    }

    /**
     * Precompute a static 2D normal-vector displacement map for a rounded stadium capsule.
     * Center plateau remains neutral (R=128, G=128) so nav text & icons never distort,
     * while the curved outer meniscus generates smooth optical ray refraction.
     */
    function buildCapsuleDisplacementMap(widthPx, heightPx, bezelRatio) {
        var w = Math.max(120, Math.round(widthPx));
        var h = Math.max(48, Math.round(heightPx));
        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return '';
        }

        var imgData = ctx.createImageData(w, h);
        var data = imgData.data;

        var halfW = w * 0.5;
        var halfH = h * 0.5;
        var radius = Math.min(halfW, halfH);
        var coreHalfX = Math.max(0, halfW - radius);
        var coreHalfY = Math.max(0, halfH - radius);
        var bezel = Math.max(12, radius * (bezelRatio || 0.58));

        for (var y = 0; y < h; y += 1) {
            var py = y + 0.5 - halfH;
            var qy = Math.abs(py) - coreHalfY;
            var cy = Math.max(qy, 0);

            for (var x = 0; x < w; x += 1) {
                var px = x + 0.5 - halfW;
                var qx = Math.abs(px) - coreHalfX;
                var cx = Math.max(qx, 0);

                var distOut = Math.hypot(cx, cy) + Math.min(Math.max(qx, qy), 0) - radius;
                var distIn = Math.max(0, -distOut);

                var dx = 0;
                var dy = 0;

                if (distIn < bezel) {
                    var t = 1 - distIn / bezel;
                    // Convex dome surface derivative concentrated at curved meniscus rim
                    var mag = Math.pow(t, 1.45);

                    var nx = 0;
                    var ny = 0;
                    if (cx > 0 || cy > 0) {
                        var len = Math.hypot(cx, cy) || 1;
                        nx = (px >= 0 ? 1 : -1) * (cx / len);
                        ny = (py >= 0 ? 1 : -1) * (cy / len);
                    } else if (Math.abs(px) - coreHalfX > Math.abs(py) - coreHalfY) {
                        nx = px >= 0 ? 1 : -1;
                    } else {
                        ny = py >= 0 ? 1 : -1;
                    }

                    // Refract inward along surface normal (lens magnification around capsule edge)
                    dx = -nx * mag;
                    dy = -ny * mag;
                }

                var idx = (y * w + x) * 4;
                data[idx] = Math.max(0, Math.min(255, Math.round(128 + dx * 127)));
                data[idx + 1] = Math.max(0, Math.min(255, Math.round(128 + dy * 127)));
                data[idx + 2] = 128;
                data[idx + 3] = 255;
            }
        }

        ctx.putImageData(imgData, 0, 0);
        return canvas.toDataURL('image/png');
    }

    function applyStaticMaps() {
        var capsule = document.getElementById('cf-lg-capsule');
        var navMapEl = document.getElementById('cf-lg-nav-map');
        var compareOptical = document.getElementById('cf-lg-compare-optical');
        var tileMapEl = document.getElementById('cf-lg-tile-map');
        var readoutEl = document.getElementById('cf-lg-metric-readout');

        var generationStart = performance.now();

        if (capsule && navMapEl) {
            var navRect = capsule.getBoundingClientRect();
            var navDataUrl = buildCapsuleDisplacementMap(navRect.width || 900, navRect.height || 60, 0.62);
            if (navDataUrl) {
                navMapEl.setAttribute('href', navDataUrl);
            }
        }

        if (compareOptical && tileMapEl) {
            var tileRect = compareOptical.getBoundingClientRect();
            var tileDataUrl = buildCapsuleDisplacementMap(tileRect.width || 380, tileRect.height || 152, 0.56);
            if (tileDataUrl) {
                tileMapEl.setAttribute('href', tileDataUrl);
            }
        }

        var elapsedMs = (performance.now() - generationStart).toFixed(1);
        if (readoutEl) {
            readoutEl.textContent = 'Static map computed once in ' + elapsedMs + 'ms (0ms scroll cost)';
        }
        return elapsedMs;
    }

    function initPointerSpecularTracking() {
        var targets = [
            document.getElementById('cf-lg-capsule'),
            document.getElementById('cf-lg-compare-optical'),
        ].filter(Boolean);

        var pendingFrame = null;
        var nextX = 50;
        var nextY = 28;
        var nextAngle = 132;

        function commitVars() {
            pendingFrame = null;
            rootStyle.setProperty('--lg-ptr-x', nextX.toFixed(1) + '%');
            rootStyle.setProperty('--lg-ptr-y', nextY.toFixed(1) + '%');
            rootStyle.setProperty('--lg-angle', nextAngle.toFixed(1) + 'deg');
        }

        function handlePointer(event) {
            if (prefersReducedMotion()) {
                return;
            }
            var target = event.currentTarget;
            var rect = target.getBoundingClientRect();
            if (!rect.width || !rect.height) {
                return;
            }
            var rx = ((event.clientX - rect.left) / rect.width) * 100;
            var ry = ((event.clientY - rect.top) / rect.height) * 100;
            nextX = Math.max(0, Math.min(100, rx));
            nextY = Math.max(0, Math.min(100, ry));

            var dx = event.clientX - (rect.left + rect.width * 0.5);
            var dy = event.clientY - (rect.top + rect.height * 0.5);
            nextAngle = (Math.atan2(dy, dx) * 180) / Math.PI + 90;

            if (pendingFrame === null) {
                pendingFrame = window.requestAnimationFrame(commitVars);
            }
        }

        targets.forEach(function (el) {
            el.addEventListener('pointermove', handlePointer, { passive: true });
        });
    }

    function initControls(opticalSupported) {
        var capBadge = document.getElementById('cf-lg-capability-badge');
        var btnOptical = document.getElementById('cf-lg-mode-optical');
        var btnFallback = document.getElementById('cf-lg-mode-fallback');
        var btnDark = document.getElementById('cf-lg-contrast-toggle');
        var mobileToggle = document.getElementById('cf-lg-mobile-toggle');
        var mobileDrawer = document.getElementById('cf-lg-mobile-drawer');

        function setMode(mode) {
            var useOptical = mode === 'optical' && opticalSupported;
            body.classList.toggle('is-optical-active', useOptical);
            body.classList.toggle('is-fallback-forced', !useOptical);

            if (btnOptical) {
                btnOptical.classList.toggle('is-selected', useOptical);
            }
            if (btnFallback) {
                btnFallback.classList.toggle('is-selected', !useOptical);
            }
            if (capBadge) {
                capBadge.textContent = useOptical
                    ? 'Optical SVG Refraction Active (feDisplacementMap)'
                    : opticalSupported
                        ? 'Simulated CSS Blur Fallback Active'
                        : 'Graceful Frosted Fallback (SVG backdrop-filter unsupported)';
            }
        }

        if (btnOptical) {
            btnOptical.addEventListener('click', function () {
                setMode('optical');
            });
        }
        if (btnFallback) {
            btnFallback.addEventListener('click', function () {
                setMode('fallback');
            });
        }
        if (btnDark) {
            btnDark.addEventListener('click', function () {
                var isDark = body.classList.toggle('is-dark-capsule');
                btnDark.classList.toggle('is-selected', isDark);
                btnDark.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            });
        }

        if (mobileToggle && mobileDrawer) {
            mobileToggle.addEventListener('click', function () {
                var isOpen = mobileDrawer.hidden;
                mobileDrawer.hidden = !isOpen;
                mobileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }

        setMode(opticalSupported ? 'optical' : 'fallback');

        return { setMode: setMode };
    }

    document.addEventListener('DOMContentLoaded', function () {
        var opticalSupported = detectSvgBackdropSupport();
        var mapMs = applyStaticMaps();
        var controls = initControls(opticalSupported);
        initPointerSpecularTracking();

        var resizeTimer = null;
        var lastW = window.innerWidth;
        window.addEventListener('resize', function () {
            if (Math.abs(window.innerWidth - lastW) < 12) {
                return;
            }
            lastW = window.innerWidth;
            if (resizeTimer) {
                window.clearTimeout(resizeTimer);
            }
            resizeTimer = window.setTimeout(applyStaticMaps, 160);
        });

        window.CampusFindLiquidGlassProto = {
            opticalSupported: opticalSupported,
            initialMapBuildMs: Number(mapMs),
            setMode: controls.setMode,
        };
    });
})();

