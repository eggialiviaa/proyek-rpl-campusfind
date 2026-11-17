/**
 * public/js/login-scene.js
 * Standalone Login Scene Animation & Optical Interaction Controller (FLOW-01.1 Phase C)
 *
 * Reproduces the exact entrance animation language of the public landing login scene:
 * 1. Ambient backdrop reveal
 * 2. Left brand statement enters from left
 * 3. Right liquid glass panel enters from right
 * 4. Header & description text reveal
 * 5. Credential inputs reveal in sequence
 * 6. Submit button & supporting policy reveal
 *
 * Features:
 * - Graceful static fallback if JS is disabled or fails
 * - Full prefers-reduced-motion support
 * - Dynamic SVG displacement capsule map generation for glass panel refraction
 * - Interactive pointer tracking specular highlight (--cf-login-mx, --cf-login-my)
 * - Auto-dismisses animation classes on input focus to prevent any blocking of autofill/interaction
 */

(function () {
    'use strict';

    function isReducedMotion() {
        try {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (_) {
            return false;
        }
    }

    function buildCapsuleDisplacementMap(widthPx, heightPx, bezelRatio) {
        var w = Math.max(120, Math.round(widthPx));
        var h = Math.max(44, Math.round(heightPx));
        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        if (!ctx) return '';

        var imgData = ctx.createImageData(w, h);
        var data = imgData.data;
        var b = Math.min(w, h) * (bezelRatio || 0.34);

        for (var y = 0; y < h; y++) {
            for (var x = 0; x < w; x++) {
                var idx = (y * w + x) * 4;
                var dx = 0;
                var dy = 0;

                if (x < b) dx = (x - b) / b;
                else if (x > w - b) dx = (x - (w - b)) / b;

                if (y < b) dy = (y - b) / b;
                else if (y > h - b) dy = (y - (h - b)) / b;

                data[idx]     = Math.round(128 + dx * 127);
                data[idx + 1] = Math.round(128 + dy * 127);
                data[idx + 2] = 128;
                data[idx + 3] = 255;
            }
        }
        ctx.putImageData(imgData, 0, 0);
        return canvas.toDataURL('image/png');
    }

    function initLoginScene() {
        var portal = document.getElementById('cf-scene-login-portal');
        if (!portal) return;

        // Skip if inside landing page scroll narrative (landing.js owns it)
        if (document.body.classList.contains('cf-narrative-active')) return;
        if (window.CampusFindLanding && window.CampusFindLanding.narrative) return;

        var panel = document.getElementById('cf-lg-login-panel');
        var glassMap = document.getElementById('cf-lg-login-map');

        // 1. Generate & Attach Optical Displacement Map
        if (panel && glassMap) {
            try {
                var rect = panel.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) {
                    var mapW = Math.min(360, rect.width * 0.55);
                    var mapH = Math.min(420, rect.height * 0.55);
                    var dataUrl = buildCapsuleDisplacementMap(mapW, mapH, 0.34);
                    if (dataUrl) {
                        glassMap.setAttribute('href', dataUrl);
                    }
                }
            } catch (err) {
                console.warn('[CampusFind Login] Displacement map generation failed:', err);
            }
        }

        // 2. Interactive Specular Reflection Pointer Tracking
        if (panel && !window.matchMedia('(pointer: coarse)').matches) {
            panel.addEventListener('pointermove', function (e) {
                if (isReducedMotion()) return;
                var rect = panel.getBoundingClientRect();
                if (!rect.width || !rect.height) return;
                var xPct = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
                var yPct = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));
                panel.style.setProperty('--cf-login-mx', xPct.toFixed(1) + '%');
                panel.style.setProperty('--cf-login-my', yPct.toFixed(1) + '%');
            }, { passive: true });
        }

        // 3. Animation State Management
        if (isReducedMotion()) {
            portal.classList.add('is-animation-complete');
            window.CampusFindLoginScene = { animated: false, completed: true, reducedMotion: true };
            return;
        }

        // Trigger entrance animation sequence
        portal.classList.add('is-standalone-animating');
        window.CampusFindLoginScene = { animated: true, completed: false, reducedMotion: false };

        var finishTimer = setTimeout(function () {
            portal.classList.remove('is-standalone-animating');
            portal.classList.add('is-animation-complete');
            window.CampusFindLoginScene.completed = true;
        }, 1000);

        // Immediate completion if user clicks or focuses any field before sequence ends
        var formControls = portal.querySelectorAll('input, button, a');
        formControls.forEach(function (el) {
            el.addEventListener('focus', function () {
                clearTimeout(finishTimer);
                portal.classList.remove('is-standalone-animating');
                portal.classList.add('is-animation-complete');
                window.CampusFindLoginScene.completed = true;
            }, { once: true });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoginScene);
    } else {
        initLoginScene();
    }
})();

