/* =============================================================
   Campus Find — Landing Page Interactive Controller
   Scoped exclusively to body.cf-landing
   Phase 1: Hero Cursor Parallax + Smooth CTA Anchor Navigation
   ============================================================= */

(function () {
    'use strict';

    if (!document.body || !document.body.classList.contains('cf-landing')) {
        return;
    }

    const reducedMotionQuery = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;

    const coarsePointerQuery = window.matchMedia
        ? window.matchMedia('(hover: none), (pointer: coarse)')
        : null;

    function prefersReducedMotion() {
        return Boolean(reducedMotionQuery && reducedMotionQuery.matches);
    }

    function isTouchDevice() {
        if (coarsePointerQuery && coarsePointerQuery.matches) {
            return true;
        }
        return ('ontouchstart' in window) && (navigator.maxTouchPoints > 0);
    }

    function detectSvgBackdropSupport() {
        var supportsBackdropUrl = false;
        if (window.CSS && typeof window.CSS.supports === 'function') {
            supportsBackdropUrl =
                window.CSS.supports('backdrop-filter', 'url(#cf-lg-nav-filter)') ||
                window.CSS.supports('-webkit-backdrop-filter', 'url(#cf-lg-nav-filter)');
        }
        var ua = navigator.userAgent || '';
        var isChromium = /Chrome|Chromium|Edg\//.test(ua);
        var isFirefox = /Firefox\//.test(ua);
        var isSafariOnly = /Safari\//.test(ua) && !isChromium;
        return Boolean(supportsBackdropUrl && isChromium && !isFirefox && !isSafariOnly);
    }

    function buildCapsuleDisplacementMap(widthPx, heightPx, bezelRatio) {
        var w = Math.max(120, Math.round(widthPx));
        var h = Math.max(44, Math.round(heightPx));
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
        var radius = Math.min(halfW, halfH, 52);
        var coreHalfX = Math.max(0, halfW - radius);
        var coreHalfY = Math.max(0, halfH - radius);
        var bezel = Math.max(12, radius * (bezelRatio || 0.62));

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

    if (typeof window.togglePasswordVisibility !== 'function') {
        window.togglePasswordVisibility = function (inputId, btn) {
            var input = document.getElementById(inputId);
            if (!input || !btn) {
                return;
            }
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) {
                    icon.className = 'bi bi-eye-slash';
                }
                btn.setAttribute('aria-label', 'Sembunyikan kata sandi');
            } else {
                input.type = 'password';
                if (icon) {
                    icon.className = 'bi bi-eye';
                }
                btn.setAttribute('aria-label', 'Tampilkan kata sandi');
            }
        };
    }

    function initHeroParallax() {
        const hero = document.getElementById('hero');
        const stage = document.getElementById('cf-hero-stage');
        const ambientEnv = document.getElementById('cf-hero-ambient-env');
        if (!hero || !stage) {
            return;
        }

        const slots = Array.from(stage.querySelectorAll('[data-parallax-depth]'));
        if (slots.length === 0) {
            return;
        }

        // Disable pointer parallax on touch devices or reduced-motion preference
        if (prefersReducedMotion() || isTouchDevice()) {
            slots.forEach(function (slot) {
                slot.style.transform = 'translate3d(0px, 0px, 0px)';
            });
            if (ambientEnv) {
                ambientEnv.style.removeProperty('--cf-hero-glow-x');
                ambientEnv.style.removeProperty('--cf-hero-glow-y');
            }
            return;
        }

        let targetX = 0;
        let targetY = 0;
        let currentX = 0;
        let currentY = 0;
        let rafId = null;

        function renderFrame() {
            currentX += (targetX - currentX) * 0.085;
            currentY += (targetY - currentY) * 0.085;

            // Shift ambient diffused light fields with largest subtle displacement (±22px to 32px)
            if (ambientEnv) {
                const glowX = (currentX * 26).toFixed(2);
                const glowY = (currentY * 26).toFixed(2);
                ambientEnv.style.setProperty('--cf-hero-glow-x', glowX + 'px');
                ambientEnv.style.setProperty('--cf-hero-glow-y', glowY + 'px');
            }

            // Directional bias per slot creates stereo spatial depth
            slots.forEach(function (slot) {
                const depth = parseFloat(slot.getAttribute('data-parallax-depth') || '20');
                const biasDx = parseFloat(slot.getAttribute('data-parallax-dx') || '1');
                const biasDy = parseFloat(slot.getAttribute('data-parallax-dy') || '1');
                const tx = (currentX * depth * biasDx).toFixed(2);
                const ty = (currentY * depth * biasDy).toFixed(2);
                slot.style.transform = 'translate3d(' + tx + 'px, ' + ty + 'px, 0px)';
            });

            const delta = Math.abs(targetX - currentX) + Math.abs(targetY - currentY);
            if (delta > 0.002) {
                rafId = window.requestAnimationFrame(renderFrame);
            } else {
                rafId = null;
            }
        }

        function scheduleFrame() {
            if (rafId === null) {
                rafId = window.requestAnimationFrame(renderFrame);
            }
        }

        hero.addEventListener('pointermove', function (event) {
            if (event.pointerType === 'touch' || prefersReducedMotion()) {
                return;
            }
            var nar = window.CampusFindLanding && window.CampusFindLanding.narrative;
            if (nar && nar.isActive() && (nar.isTransitioning() || nar.getState().activeScene !== 'hero')) {
                return;
            }
            const rect = hero.getBoundingClientRect();
            if (!rect.width || !rect.height) {
                return;
            }
            const normX = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
            const normY = ((event.clientY - rect.top) / rect.height - 0.5) * 2;
            targetX = Math.max(-1, Math.min(1, normX));
            targetY = Math.max(-1, Math.min(1, normY));
            scheduleFrame();
        }, { passive: true });

        hero.addEventListener('pointerleave', function () {
            targetX = 0;
            targetY = 0;
            scheduleFrame();
        }, { passive: true });

        if (reducedMotionQuery && reducedMotionQuery.addEventListener) {
            reducedMotionQuery.addEventListener('change', function () {
                if (prefersReducedMotion()) {
                    targetX = 0;
                    targetY = 0;
                    currentX = 0;
                    currentY = 0;
                    if (rafId !== null) {
                        window.cancelAnimationFrame(rafId);
                        rafId = null;
                    }
                    slots.forEach(function (slot) {
                        slot.style.transform = 'translate3d(0px, 0px, 0px)';
                    });
                    if (ambientEnv) {
                        ambientEnv.style.removeProperty('--cf-hero-glow-x');
                        ambientEnv.style.removeProperty('--cf-hero-glow-y');
                    }
                }
            });
        }

        return {
            resetSoftly: function () {
                targetX = 0;
                targetY = 0;
                scheduleFrame();
            },
        };
    }

    function initHeroCtas() {
        const exploreBtn = document.getElementById('hero-cta-explore');
        const howBtn = document.getElementById('hero-cta-how-it-works');

        if (exploreBtn) {
            exploreBtn.addEventListener('click', function (event) {
                const targetFilter = exploreBtn.getAttribute('data-landing-filter');
                if (!targetFilter) {
                    return; // Guests with PUBLIC_BROWSE_ENABLED=false navigate normally to /login
                }

                const section = document.getElementById('recently-reported');
                if (section) {
                    event.preventDefault();
                    const nar = window.CampusFindLanding && window.CampusFindLanding.narrative;
                    const recentApi = window.CampusFindLanding && window.CampusFindLanding.recentCarousel;
                    if (nar && nar.isActive()) {
                        if (recentApi && typeof recentApi.setFilter === 'function') {
                            recentApi.setFilter(targetFilter, true);
                        }
                        nar.goToBeat('recently-reported');
                        return;
                    }

                    section.scrollIntoView({
                        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                        block: 'start',
                    });

                    // Phase 2 hook: activate matching filter button if present
                    const filterTrigger = section.querySelector('[data-filter-value="' + targetFilter + '"]');
                    if (filterTrigger) {
                        filterTrigger.click();
                    }
                }
            });
        }

        if (howBtn) {
            howBtn.addEventListener('click', function (event) {
                const href = howBtn.getAttribute('href') || '';
                if (!href.startsWith('#')) {
                    return;
                }
                const target = document.getElementById(href.slice(1));
                if (target) {
                    event.preventDefault();
                    if (window.CampusFindLanding && window.CampusFindLanding.narrative && window.CampusFindLanding.narrative.isActive()) {
                        window.CampusFindLanding.narrative.goToBeat('hiw-intro');
                    } else {
                        target.scrollIntoView({
                            behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                            block: 'start',
                        });
                    }
                }
            });
        }
    }

    function initRecentlyReported() {
        var section = document.getElementById('recently-reported');
        var dataEl = document.getElementById('cf-recent-data');
        var carouselEl = document.getElementById('cf-recent-carousel');
        var viewportEl = document.getElementById('cf-recent-viewport');
        var trackEl = document.getElementById('cf-recent-track');
        if (!section || !dataEl || !carouselEl || !viewportEl || !trackEl) {
            return null;
        }

        var datasets = { all: [], lost: [], found: [] };
        try {
            var parsed = JSON.parse(dataEl.textContent || '{}');
            if (parsed && typeof parsed === 'object') {
                datasets.all = Array.isArray(parsed.all) ? parsed.all : [];
                datasets.lost = Array.isArray(parsed.lost) ? parsed.lost : [];
                datasets.found = Array.isArray(parsed.found) ? parsed.found : [];
            }
        } catch (err) {
            return null;
        }

        var filterBtns = Array.prototype.slice.call(section.querySelectorAll('.cf-recent-filter-btn[data-filter-value]'));
        var prevBtn = document.getElementById('cf-recent-prev');
        var nextBtn = document.getElementById('cf-recent-next');
        var dotsContainer = document.getElementById('cf-recent-dots');

        // Modal elements (portal out of scene container so transforms never trap fixed dialog)
        var modalEl = document.getElementById('cf-recent-modal');
        if (modalEl && modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }
        var modalCloseBtn = document.getElementById('cf-recent-modal-close');
        var modalDismissBtn = document.getElementById('cf-recent-modal-dismiss');
        var modalMediaEl = document.getElementById('cf-modal-media');
        var modalTypePill = document.getElementById('cf-modal-type-pill');
        var modalStatusEl = document.getElementById('cf-modal-status');
        var modalTitleEl = document.getElementById('cf-modal-title');
        var modalCatEl = document.getElementById('cf-modal-category');
        var modalLocEl = document.getElementById('cf-modal-location');
        var modalDateEl = document.getElementById('cf-modal-date');
        var modalDescEl = document.getElementById('cf-modal-desc');
        var modalDetailLink = document.getElementById('cf-modal-detail-link');

        var state = {
            filter: 'all',
            items: datasets.all.slice(),
            index: 0,
            perView: 3,
            maxIndex: 0,
            isHovered: false,
            isFocused: false,
            isModalOpen: false,
            isDragging: false,
            dragStartX: 0,
            dragDeltaX: 0,
            dragStartOffset: 0,
            dragMoved: false,
            suppressClickUntil: 0,
            lastTriggerEl: null,
            autoplayTimer: null,
            filterTimer: null,
        };

        var itemLookup = Object.create(null);
        ['all', 'lost', 'found'].forEach(function (k) {
            datasets[k].forEach(function (item) {
                if (item && item.key) {
                    itemLookup[item.key] = item;
                }
            });
        });

        function computePerView() {
            var w = window.innerWidth || document.documentElement.clientWidth || 1200;
            if (w >= 992) {
                return 3;
            }
            if (w >= 768) {
                return 2;
            }
            return 1;
        }

        function createEl(tag, className, text) {
            var node = document.createElement(tag);
            if (className) {
                node.className = className;
            }
            if (typeof text === 'string') {
                node.textContent = text;
            }
            return node;
        }

        function buildPlaceholderNode(item) {
            var isProtected = Boolean(item.has_protected_image);
            var wrap = createEl(
                'div',
                'cf-recent-placeholder ' + (isProtected ? 'cf-recent-placeholder--protected' : 'cf-recent-placeholder--category')
            );

            var badge = createEl('span', 'cf-recent-placeholder-badge');
            var iconClass = isProtected ? 'bi-shield-lock' : (item.category_icon || 'bi-box-seam');
            var icon = createEl('i', 'bi ' + iconClass);
            icon.setAttribute('aria-hidden', 'true');
            badge.appendChild(icon);

            var titleText = isProtected
                ? (item.is_logged_in ? 'Photo Pending Verification' : 'Sign In to View Photo')
                : (item.category_name || 'Umum');
            var subText = isProtected ? (item.category_name || 'Campus Item') : 'No photo provided';

            wrap.appendChild(badge);
            wrap.appendChild(createEl('span', 'cf-recent-placeholder-title', titleText));
            wrap.appendChild(createEl('span', 'cf-recent-placeholder-sub', subText));
            return wrap;
        }

        function buildMediaInner(item, isModal) {
            if (item.can_view_image && item.image_url) {
                var img = createEl('img', isModal ? 'cf-recent-modal-img' : 'cf-recent-media-img');
                img.src = item.image_url;
                img.alt = item.title || 'Reported item';
                if (!isModal) {
                    img.loading = 'lazy';
                    img.decoding = 'async';
                }
                return img;
            }
            return buildPlaceholderNode(item);
        }

        function buildSlideCard(item, idx) {
            var slide = createEl('article', 'cf-recent-slide');
            slide.setAttribute('data-item-index', String(idx));
            slide.setAttribute('data-item-key', item.key);

            var btn = createEl('button', 'cf-recent-card');
            btn.type = 'button';
            btn.setAttribute('data-preview-key', item.key);
            btn.setAttribute('aria-label', 'Preview ' + (item.title || 'item'));

            var media = createEl('div', 'cf-recent-media');
            media.appendChild(buildMediaInner(item, false));

            var typePill = createEl(
                'span',
                'cf-recent-type-pill ' + (item.type === 'lost' ? 'is-lost' : 'is-found'),
                item.type_label || (item.type === 'lost' ? 'Lost' : 'Found')
            );
            media.appendChild(typePill);

            var body = createEl('div', 'cf-recent-card-body');
            body.appendChild(createEl('div', 'cf-recent-card-status', item.status_label || 'Active'));
            body.appendChild(createEl('h3', 'cf-recent-card-title', item.title || 'Untitled Report'));

            var meta = createEl('div', 'cf-recent-card-meta');
            var locSpan = createEl('span', 'cf-recent-meta-loc');
            var locIcon = createEl('i', 'bi bi-geo-alt');
            locIcon.setAttribute('aria-hidden', 'true');
            locSpan.appendChild(locIcon);
            locSpan.appendChild(document.createTextNode(' ' + (item.location_name || 'Campus Area')));

            var dateSpan = createEl('span', 'cf-recent-meta-date', item.date_formatted || '-');
            meta.appendChild(locSpan);
            meta.appendChild(dateSpan);

            body.appendChild(meta);
            btn.appendChild(media);
            btn.appendChild(body);
            slide.appendChild(btn);
            return slide;
        }

        function renderEmptyState() {
            var empty = createEl('div', 'cf-recent-empty-card');
            var iconWrap = createEl('div', 'cf-recent-empty-icon');
            iconWrap.setAttribute('aria-hidden', 'true');
            var icon = createEl('i', 'bi bi-inbox');
            iconWrap.appendChild(icon);

            var heading = createEl('h3', 'cf-recent-empty-title', 'No reports in this category yet');
            var desc = createEl(
                'p',
                'cf-recent-empty-text',
                'Switch filters or check back shortly for newly verified campus reports.'
            );

            empty.appendChild(iconWrap);
            empty.appendChild(heading);
            empty.appendChild(desc);
            trackEl.appendChild(empty);
        }

        function getSlideStepWidth() {
            var firstSlide = trackEl.querySelector('.cf-recent-slide');
            if (!firstSlide) {
                return 0;
            }
            var rect = firstSlide.getBoundingClientRect();
            var trackStyles = window.getComputedStyle(trackEl);
            var gap = parseFloat(trackStyles.columnGap || trackStyles.gap || '24') || 24;
            return rect.width + gap;
        }

        function getOffsetForIndex(idx) {
            var step = getSlideStepWidth();
            if (step <= 0) {
                return 0;
            }
            return idx * step;
        }

        function updateDots() {
            if (!dotsContainer) {
                return;
            }
            while (dotsContainer.firstChild) {
                dotsContainer.removeChild(dotsContainer.firstChild);
            }

            var totalStops = state.maxIndex + 1;
            if (state.items.length === 0 || totalStops <= 1) {
                return;
            }

            for (var i = 0; i < totalStops; i += 1) {
                (function (targetIdx) {
                    var dot = createEl('button', 'cf-recent-dot' + (targetIdx === state.index ? ' is-active' : ''));
                    dot.type = 'button';
                    dot.setAttribute('role', 'tab');
                    dot.setAttribute('aria-selected', targetIdx === state.index ? 'true' : 'false');
                    dot.setAttribute('aria-label', 'Show slide group ' + (targetIdx + 1));
                    dot.addEventListener('click', function () {
                        goToIndex(targetIdx);
                        restartAutoplay();
                    });
                    dotsContainer.appendChild(dot);
                })(i);
            }
        }

        function updateControlsAndTransform(animate) {
            state.perView = computePerView();
            state.maxIndex = Math.max(0, state.items.length - state.perView);
            if (state.index > state.maxIndex) {
                state.index = state.maxIndex;
            }
            if (state.index < 0) {
                state.index = 0;
            }

            var offset = getOffsetForIndex(state.index);
            if (!animate || prefersReducedMotion()) {
                trackEl.style.transition = 'none';
                trackEl.style.transform = 'translate3d(' + (-offset) + 'px, 0, 0)';
                void trackEl.offsetWidth;
                trackEl.style.transition = '';
            } else {
                trackEl.style.transform = 'translate3d(' + (-offset) + 'px, 0, 0)';
            }

            if (prevBtn) {
                prevBtn.disabled = state.items.length === 0 || state.index <= 0;
            }
            if (nextBtn) {
                nextBtn.disabled = state.items.length === 0 || state.index >= state.maxIndex;
            }
            updateDots();
        }

        function goToIndex(nextIdx) {
            if (state.items.length === 0) {
                state.index = 0;
                updateControlsAndTransform(false);
                return;
            }
            if (nextIdx > state.maxIndex) {
                state.index = 0;
            } else if (nextIdx < 0) {
                state.index = state.maxIndex;
            } else {
                state.index = nextIdx;
            }
            updateControlsAndTransform(true);
        }

        function nextSlide(wrapAround) {
            if (state.maxIndex <= 0) {
                return;
            }
            if (state.index >= state.maxIndex) {
                if (wrapAround) {
                    goToIndex(0);
                }
                return;
            }
            goToIndex(state.index + 1);
        }

        function prevSlide() {
            if (state.maxIndex <= 0 || state.index <= 0) {
                return;
            }
            goToIndex(state.index - 1);
        }

        function renderTrackItems(items) {
            while (trackEl.firstChild) {
                trackEl.removeChild(trackEl.firstChild);
            }
            if (!items || items.length === 0) {
                renderEmptyState();
            } else {
                items.forEach(function (item, idx) {
                    trackEl.appendChild(buildSlideCard(item, idx));
                });
            }
            updateControlsAndTransform(false);
        }

        function setFilter(nextFilter, immediate) {
            var normalized = (nextFilter === 'lost' || nextFilter === 'found') ? nextFilter : 'all';
            filterBtns.forEach(function (btn) {
                var isMatch = btn.getAttribute('data-filter-value') === normalized;
                btn.classList.toggle('is-active', isMatch);
                btn.setAttribute('aria-selected', isMatch ? 'true' : 'false');
            });

            if (state.filterTimer) {
                window.clearTimeout(state.filterTimer);
                state.filterTimer = null;
            }

            state.filter = normalized;
            state.items = (datasets[normalized] || []).slice();
            state.index = 0;

            if (immediate || prefersReducedMotion()) {
                trackEl.classList.remove('is-switching');
                renderTrackItems(state.items);
                restartAutoplay();
                return;
            }

            trackEl.classList.add('is-switching');
            state.filterTimer = window.setTimeout(function () {
                renderTrackItems(state.items);
                trackEl.classList.remove('is-switching');
                restartAutoplay();
            }, 130);
        }

        function shouldPauseAutoplay() {
            var nar = window.CampusFindLanding && window.CampusFindLanding.narrative;
            var blockedByNarrative = Boolean(nar && nar.isActive() && (nar.isTransitioning() || nar.getState().activeScene !== 'recent'));
            return (
                prefersReducedMotion() ||
                document.hidden ||
                blockedByNarrative ||
                state.isHovered ||
                state.isFocused ||
                state.isModalOpen ||
                state.isDragging ||
                state.maxIndex <= 0
            );
        }

        function stopAutoplay() {
            if (state.autoplayTimer !== null) {
                window.clearInterval(state.autoplayTimer);
                state.autoplayTimer = null;
            }
        }

        function startAutoplay() {
            stopAutoplay();
            if (shouldPauseAutoplay()) {
                return;
            }
            state.autoplayTimer = window.setInterval(function () {
                if (shouldPauseAutoplay()) {
                    return;
                }
                nextSlide(true);
            }, 5500);
        }

        function restartAutoplay() {
            stopAutoplay();
            startAutoplay();
        }

        function openModal(itemKey, triggerEl) {
            var item = itemLookup[itemKey];
            if (!item || !modalEl) {
                return;
            }

            state.lastTriggerEl = triggerEl || document.activeElement;
            state.isModalOpen = true;
            stopAutoplay();

            if (modalMediaEl) {
                while (modalMediaEl.firstChild) {
                    modalMediaEl.removeChild(modalMediaEl.firstChild);
                }
                modalMediaEl.appendChild(buildMediaInner(item, true));
            }

            if (modalTypePill) {
                modalTypePill.textContent = item.type_label || (item.type === 'lost' ? 'Lost' : 'Found');
                modalTypePill.className = 'cf-recent-type-pill ' + (item.type === 'lost' ? 'is-lost' : 'is-found');
            }
            if (modalStatusEl) {
                modalStatusEl.textContent = item.status_label || 'Active';
            }
            if (modalTitleEl) {
                modalTitleEl.textContent = item.title || 'Reported Item';
            }
            if (modalCatEl) {
                modalCatEl.textContent = item.category_name || 'Umum';
            }
            if (modalLocEl) {
                modalLocEl.textContent = item.location_name || 'Campus Area';
            }
            if (modalDateEl) {
                modalDateEl.textContent = item.date_formatted || '-';
            }
            if (modalDescEl) {
                modalDescEl.textContent = item.description || 'Details visible to verified campus members.';
            }
            if (modalDetailLink) {
                modalDetailLink.setAttribute('href', item.detail_url || '/login');
            }

            modalEl.hidden = false;
            document.body.classList.add('cf-recent-modal-open');
            if (modalCloseBtn) {
                modalCloseBtn.focus();
            }
        }

        function closeModal() {
            if (!modalEl || modalEl.hidden) {
                return;
            }
            modalEl.hidden = true;
            state.isModalOpen = false;
            document.body.classList.remove('cf-recent-modal-open');
            if (state.lastTriggerEl && typeof state.lastTriggerEl.focus === 'function') {
                state.lastTriggerEl.focus();
            }
            state.lastTriggerEl = null;
            startAutoplay();
        }

        // Filter button bindings
        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setFilter(btn.getAttribute('data-filter-value') || 'all');
            });
        });

        // Prev / Next arrow bindings
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                prevSlide();
                restartAutoplay();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                nextSlide(false);
                restartAutoplay();
            });
        }

        // Keyboard arrow navigation
        carouselEl.addEventListener('keydown', function (event) {
            if (state.isModalOpen) {
                return;
            }
            if (event.key === 'ArrowRight') {
                event.preventDefault();
                nextSlide(false);
                restartAutoplay();
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                prevSlide();
                restartAutoplay();
            }
        });

        // Pause triggers: hover, focus, document visibility
        carouselEl.addEventListener('mouseenter', function () {
            state.isHovered = true;
            stopAutoplay();
        });
        carouselEl.addEventListener('mouseleave', function () {
            state.isHovered = false;
            startAutoplay();
        });
        carouselEl.addEventListener('focusin', function () {
            state.isFocused = true;
            stopAutoplay();
        });
        carouselEl.addEventListener('focusout', function (event) {
            if (!carouselEl.contains(event.relatedTarget)) {
                state.isFocused = false;
                startAutoplay();
            }
        });
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopAutoplay();
            } else {
                startAutoplay();
            }
        });

        // Pointer drag & touch swipe
        viewportEl.addEventListener('pointerdown', function (event) {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }
            if (state.maxIndex <= 0) {
                return;
            }
            state.isDragging = true;
            state.dragStartX = event.clientX;
            state.dragDeltaX = 0;
            state.dragMoved = false;
            state.dragStartOffset = getOffsetForIndex(state.index);
            trackEl.classList.add('is-dragging');
            stopAutoplay();
        });

        window.addEventListener('pointermove', function (event) {
            if (!state.isDragging) {
                return;
            }
            state.dragDeltaX = event.clientX - state.dragStartX;
            if (Math.abs(state.dragDeltaX) > 8) {
                state.dragMoved = true;
            }
            var maxOffset = getOffsetForIndex(state.maxIndex);
            var nextOffset = state.dragStartOffset - state.dragDeltaX;
            if (nextOffset < 0) {
                nextOffset = nextOffset * 0.28;
            } else if (nextOffset > maxOffset) {
                nextOffset = maxOffset + (nextOffset - maxOffset) * 0.28;
            }
            trackEl.style.transform = 'translate3d(' + (-nextOffset) + 'px, 0, 0)';
        });

        function finishDrag() {
            if (!state.isDragging) {
                return;
            }
            var delta = state.dragDeltaX;
            var moved = state.dragMoved;
            state.isDragging = false;
            state.dragDeltaX = 0;
            state.dragMoved = false;
            trackEl.classList.remove('is-dragging');

            if (moved) {
                state.suppressClickUntil = Date.now() + 260;
            }

            if (delta <= -48 && state.index < state.maxIndex) {
                goToIndex(state.index + 1);
            } else if (delta >= 48 && state.index > 0) {
                goToIndex(state.index - 1);
            } else {
                updateControlsAndTransform(true);
            }
            startAutoplay();
        }

        window.addEventListener('pointerup', finishDrag);
        window.addEventListener('pointercancel', finishDrag);

        // Card preview modal click delegation
        trackEl.addEventListener('click', function (event) {
            if (Date.now() < state.suppressClickUntil) {
                event.preventDefault();
                return;
            }
            var card = event.target.closest('[data-preview-key]');
            if (!card) {
                return;
            }
            var key = card.getAttribute('data-preview-key');
            if (key) {
                openModal(key, card);
            }
        });

        if (modalCloseBtn) {
            modalCloseBtn.addEventListener('click', closeModal);
        }
        if (modalDismissBtn) {
            modalDismissBtn.addEventListener('click', closeModal);
        }
        if (modalEl) {
            modalEl.addEventListener('click', function (event) {
                if (event.target === modalEl) {
                    closeModal();
                }
            });
        }
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && state.isModalOpen) {
                event.preventDefault();
                closeModal();
            }
        });

        var resizeRaf = null;
        window.addEventListener('resize', function () {
            if (resizeRaf !== null) {
                window.cancelAnimationFrame(resizeRaf);
            }
            resizeRaf = window.requestAnimationFrame(function () {
                resizeRaf = null;
                updateControlsAndTransform(false);
            });
        });

        if (reducedMotionQuery && reducedMotionQuery.addEventListener) {
            reducedMotionQuery.addEventListener('change', function () {
                if (prefersReducedMotion()) {
                    stopAutoplay();
                } else {
                    startAutoplay();
                }
                updateControlsAndTransform(false);
            });
        }

        // Initial layout computation on SSR track & start autoplay
        updateControlsAndTransform(false);
        startAutoplay();

        return {
            setFilter: setFilter,
            next: function () { nextSlide(false); },
            prev: function () { prevSlide(); },
            openModal: openModal,
            closeModal: closeModal,
            pause: stopAutoplay,
            resume: startAutoplay,
            getState: function () {
                return {
                    filter: state.filter,
                    index: state.index,
                    perView: state.perView,
                    maxIndex: state.maxIndex,
                    total: state.items.length,
                    isModalOpen: state.isModalOpen,
                    autoplayActive: state.autoplayTimer !== null,
                };
            },
        };
    }

    function initHowItWorks() {
        var section = document.getElementById('how-it-works');
        var dataEl = document.getElementById('cf-hiw-data');
        var journeyTablist = document.getElementById('cf-hiw-journey-tablist');
        var tabIndicator = document.getElementById('cf-hiw-tab-indicator');
        var stagesListEl = document.getElementById('cf-hiw-stages');
        var progressFillEl = document.getElementById('cf-hiw-progress-fill');
        var panelEl = document.getElementById('cf-hiw-panel');
        var panelInnerEl = document.getElementById('cf-hiw-panel-inner');
        if (!section || !dataEl || !journeyTablist || !stagesListEl || !panelEl || !panelInnerEl) {
            return null;
        }

        var journeys = null;
        try {
            journeys = JSON.parse(dataEl.textContent || '{}');
        } catch (err) {
            return null;
        }
        if (!journeys || !journeys.lost || !journeys.found) {
            return null;
        }

        var journeyBtns = Array.prototype.slice.call(journeyTablist.querySelectorAll('[data-journey]'));
        var panelStepEl = document.getElementById('cf-hiw-panel-step');
        var panelTitleEl = document.getElementById('cf-hiw-panel-title');
        var panelDescEl = document.getElementById('cf-hiw-panel-desc');
        var panelPointsEl = document.getElementById('cf-hiw-panel-points');
        var cardIconEl = document.getElementById('cf-hiw-card-icon');
        var cardNumEl = document.getElementById('cf-hiw-card-num');
        var cardBadgeEl = document.getElementById('cf-hiw-card-badge');
        var cardActorEl = document.getElementById('cf-hiw-card-actor');
        var prevStageBtn = document.getElementById('cf-hiw-prev-stage');
        var nextStageBtn = document.getElementById('cf-hiw-next-stage');

        // Phase 4C Repair 4.2: 3D Floating Coverflow Cards
        var cards = Array.prototype.slice.call(section.querySelectorAll('.cf-hiw-card-3d'));
        function getCards() {
            if (!cards || cards.length === 0) {
                cards = Array.prototype.slice.call(section.querySelectorAll('.cf-hiw-card-3d'));
            }
            return cards;
        }

        // Independent state per journey (manual only, no automatic stage progression)
        var state = {
            journey: 'lost',
            stageByJourney: {
                lost: 0,
                found: 0,
            },
            swapTimer: null,
        };

        function getActiveStageIndex() {
            return state.stageByJourney[state.journey] || 0;
        }

        function populateCards(journeyKey) {
            var cardList = getCards();
            if (!cardList || cardList.length === 0) {
                return;
            }
            var journeyData = journeys[journeyKey] || journeys.lost;
            cardList.forEach(function (card, idx) {
                var stage = journeyData.stages[idx];
                if (!stage) {
                    return;
                }
                card.setAttribute('aria-label', 'Stage ' + (idx + 1) + ' of ' + journeyData.stages.length + ': ' + (stage.title || ''));

                var iconEl = card.querySelector('.cf-hiw-card-icon');
                if (iconEl) {
                    iconEl.className = 'bi ' + (stage.icon || 'bi-check2-circle') + ' cf-hiw-card-icon';
                }
                var numEl = card.querySelector('.cf-hiw-card-num');
                if (numEl) {
                    numEl.textContent = stage.code || ('0' + (idx + 1));
                }
                var badgeEl = card.querySelector('.cf-hiw-card-badge');
                if (badgeEl) {
                    badgeEl.textContent = stage.badge || '';
                }
                var metaEl = card.querySelector('.cf-hiw-card-step-meta');
                if (metaEl) {
                    metaEl.textContent = 'Stage ' + (idx + 1) + ' of ' + journeyData.stages.length + ' \u00b7 ' + journeyData.subtitle;
                }
                var titleEl = card.querySelector('.cf-hiw-card-title');
                if (titleEl) {
                    titleEl.textContent = stage.title || '';
                }
                var descEl = card.querySelector('.cf-hiw-card-desc');
                if (descEl) {
                    descEl.textContent = stage.desc || '';
                }
                var pointsEl = card.querySelector('.cf-hiw-card-points');
                if (pointsEl) {
                    while (pointsEl.firstChild) {
                        pointsEl.removeChild(pointsEl.firstChild);
                    }
                    (stage.points || []).forEach(function (pt) {
                        var li = document.createElement('li');
                        li.className = 'cf-hiw-card-point';
                        var marker = document.createElement('span');
                        marker.className = 'cf-hiw-point-marker';
                        marker.setAttribute('aria-hidden', 'true');
                        var textSpan = document.createElement('span');
                        textSpan.textContent = pt;
                        li.appendChild(marker);
                        li.appendChild(textSpan);
                        pointsEl.appendChild(li);
                    });
                }
                var actorEl = card.querySelector('.cf-hiw-card-actor');
                if (actorEl) {
                    actorEl.textContent = stage.actor || '';
                }
                var prevBtn = card.querySelector('.cf-hiw-card-prev');
                if (prevBtn) {
                    prevBtn.disabled = idx <= 0;
                }
                var nextBtn = card.querySelector('.cf-hiw-card-next');
                if (nextBtn) {
                    nextBtn.disabled = idx >= journeyData.stages.length - 1;
                }
            });
        }

        function updateCoverflow(virtualPosition, isDirect) {
            var cardList = getCards();
            if (!cardList || cardList.length === 0) {
                return;
            }
            var v = typeof virtualPosition === 'number' ? virtualPosition : getActiveStageIndex();
            var width = window.innerWidth || (document.documentElement && document.documentElement.clientWidth) || 1200;
            var isMobile = width < 768;
            var isTablet = !isMobile && width < 1024;
            var isReduced = prefersReducedMotion();

            // Symmetrical 3D Elliptical Orbital Geometry
            var Rx = isMobile ? 260 : (isTablet ? 430 : 600);
            var Rz = isMobile ? 160 : (isTablet ? 250 : 340);
            var Kz = isMobile ? 40 : (isTablet ? 70 : 100);
            var Ky = isMobile ? 8 : (isTablet ? 12 : 16);
            var thetaStep = 0.4886; // ~28 deg per stage along orbital ellipse

            // Peripheral interface & living environment: shift active focal light with virtual stage
            var ambientEnv = document.getElementById('cf-hiw-ambient-env');
            if (ambientEnv) {
                var focalShiftX = (v - 2) * (isMobile ? 16 : 28);
                ambientEnv.style.setProperty('--hiw-stage-focal-x', focalShiftX.toFixed(2) + 'px');
            }

            cardList.forEach(function (card, idx) {
                var delta = idx - v;
                var u = Math.abs(delta);
                var sgn = delta < 0 ? -1 : (delta > 0 ? 1 : 0);

                var isActive = u < 0.5;
                card.classList.toggle('is-active', isActive);
                card.classList.toggle('is-prev', delta >= -1.5 && delta < -0.5);
                card.classList.toggle('is-next', delta > 0.5 && delta <= 1.5);
                card.classList.toggle('is-far', u > 1.5);
                card.setAttribute('aria-current', isActive ? 'step' : 'false');
                card.setAttribute('tabindex', isActive ? '0' : '-1');

                if (isReduced) {
                    card.style.transform = 'translate3d(-50%, -50%, 0)';
                    card.style.opacity = isActive ? '1' : '0';
                    card.style.filter = 'none';
                    card.style.zIndex = isActive ? '100' : '1';
                    card.style.pointerEvents = isActive ? 'auto' : 'none';
                    return;
                }

                var theta = delta * thetaStep;
                var sinT = Math.sin(theta);
                var cosT = Math.cos(theta);

                // Continuous 3D Elliptical Orbital Trajectory
                // X: elliptical arc
                var dx = Rx * sinT;
                // Z: continuous background recession
                var dz = -Rz * (1 - cosT) - Kz * Math.pow(u, 1.15);
                // Y: oblique elevation tilt (recedes upwards/obliquely into depth)
                var dy = -Ky * (1 - cosT) - 8 * u - sgn * 6 * Math.sin(u * thetaStep);

                // Rotations: Y-axis turns inward, X-axis pitches slightly into depth, Z-axis orbital banking
                var rotY = -sgn * Math.min(44, 25.0 * Math.pow(u, 0.85));
                var rotX = Math.min(4.0, 2.8 * (1 - cosT) + 1.2 * u);
                var rotZ = -sgn * Math.min(2.5, 1.6 * Math.pow(u, 0.9));

                // Scale: smooth depth reduction along orbital path
                var scale = Math.max(0.60, 1.0 / (1.0 + 0.18 * u + 0.02 * u * u));

                // Opacity: side/neighboring cards NEVER fade to zero and remain visibly present throughout transitions
                var opacity = Math.max(0.12, 1.0 - 0.44 * Math.pow(u, 0.85));

                // Blur: used sparingly (<= 1.4px upper limit)
                var blur = Math.min(1.4, 0.75 * u);

                // Z-index: active card dominant on top (100), receding stages lower
                var zIndex = Math.max(1, Math.round(100 - 25 * u));

                if (isDirect) {
                    card.style.transition = '';
                } else {
                    card.style.transition = 'none';
                }

                card.style.transform = 'translate3d(calc(-50% + ' + dx.toFixed(2) + 'px), calc(-50% + ' + dy.toFixed(2) + 'px), ' + dz.toFixed(2) + 'px) ' +
                    'rotateX(' + rotX.toFixed(2) + 'deg) rotateY(' + rotY.toFixed(2) + 'deg) rotateZ(' + rotZ.toFixed(2) + 'deg) ' +
                    'scale(' + scale.toFixed(4) + ')';
                card.style.opacity = opacity.toFixed(4);
                card.style.filter = blur > 0.05 ? ('blur(' + blur.toFixed(2) + 'px)') : 'none';
                card.style.zIndex = String(zIndex);
                card.style.pointerEvents = opacity > 0.08 ? 'auto' : 'none';
            });
        }

        function bindCardEvents() {
            var cardList = getCards();
            if (!cardList || cardList.length === 0) {
                return;
            }
            cardList.forEach(function (card) {
                card.addEventListener('click', function (e) {
                    var activeIdx = getActiveStageIndex();
                    var cardIdx = parseInt(card.getAttribute('data-stage-index'), 10);
                    if (!isNaN(cardIdx) && cardIdx !== activeIdx) {
                        e.preventDefault();
                        selectStage(cardIdx, false);
                    }
                });

                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        var activeIdx = getActiveStageIndex();
                        var cardIdx = parseInt(card.getAttribute('data-stage-index'), 10);
                        if (!isNaN(cardIdx) && cardIdx !== activeIdx) {
                            e.preventDefault();
                            selectStage(cardIdx, false);
                        }
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        var total = (journeys[state.journey] && journeys[state.journey].stages) ? journeys[state.journey].stages.length : 5;
                        selectStage((getActiveStageIndex() + 1) % total, false);
                    } else if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        var totalSt = (journeys[state.journey] && journeys[state.journey].stages) ? journeys[state.journey].stages.length : 5;
                        selectStage((getActiveStageIndex() - 1 + totalSt) % totalSt, false);
                    }
                });

                var cardPrev = card.querySelector('.cf-hiw-card-prev');
                if (cardPrev) {
                    cardPrev.addEventListener('click', function (e) {
                        e.stopPropagation();
                        var activeIdx = getActiveStageIndex();
                        if (activeIdx > 0) {
                            selectStage(activeIdx - 1, false);
                        }
                    });
                }

                var cardNext = card.querySelector('.cf-hiw-card-next');
                if (cardNext) {
                    cardNext.addEventListener('click', function (e) {
                        e.stopPropagation();
                        var activeIdx = getActiveStageIndex();
                        var total = (journeys[state.journey] && journeys[state.journey].stages) ? journeys[state.journey].stages.length : 5;
                        if (activeIdx < total - 1) {
                            selectStage(activeIdx + 1, false);
                        }
                    });
                }
            });
        }

        function updateTabIndicator(animate) {
            if (!tabIndicator) {
                return;
            }
            var activeBtn = journeyTablist.querySelector('.cf-hiw-journey-tab.is-active') || journeyBtns[0];
            if (!activeBtn) {
                return;
            }
            var left = activeBtn.offsetLeft;
            var width = activeBtn.offsetWidth;
            if (!animate || prefersReducedMotion()) {
                tabIndicator.style.transition = 'none';
                tabIndicator.style.width = width + 'px';
                tabIndicator.style.transform = 'translate3d(' + left + 'px, 0, 0)';
                void tabIndicator.offsetWidth;
                tabIndicator.style.transition = '';
            } else {
                tabIndicator.style.width = width + 'px';
                tabIndicator.style.transform = 'translate3d(' + left + 'px, 0, 0)';
            }
        }

        function updateProgressRail(stageIdx, totalStages) {
            if (!progressFillEl) {
                return;
            }
            var maxIdx = Math.max(1, totalStages - 1);
            var isMobile = (window.innerWidth || document.documentElement.clientWidth || 1200) < 768;
            var pct = isMobile
                ? ((stageIdx + 1) / totalStages) * 100
                : (stageIdx / maxIdx) * 100;
            progressFillEl.style.width = Math.max(0, Math.min(100, pct)) + '%';
        }

        function renderStageButtons() {
            var journeyData = journeys[state.journey];
            var activeIdx = getActiveStageIndex();

            while (stagesListEl.firstChild) {
                stagesListEl.removeChild(stagesListEl.firstChild);
            }

            journeyData.stages.forEach(function (stage, idx) {
                var isActive = idx === activeIdx;
                var isPassed = idx < activeIdx;

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'cf-hiw-stage-btn-' + idx;
                btn.className = 'cf-hiw-stage-btn' + (isActive ? ' is-active' : '') + (isPassed ? ' is-passed' : '');
                btn.setAttribute('role', 'tab');
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                btn.setAttribute('aria-controls', 'cf-hiw-panel');
                btn.setAttribute('data-stage-index', String(idx));
                btn.setAttribute('tabindex', isActive ? '0' : '-1');

                var node = document.createElement('span');
                node.className = 'cf-hiw-stage-node';
                node.textContent = stage.code || ('0' + (idx + 1));

                var label = document.createElement('span');
                label.className = 'cf-hiw-stage-label';
                label.textContent = stage.label || '';

                btn.appendChild(node);
                btn.appendChild(label);
                btn.addEventListener('click', function () {
                    selectStage(idx, false);
                });

                stagesListEl.appendChild(btn);
            });

            updateProgressRail(activeIdx, journeyData.stages.length);
        }

        function applyPanelContent(journeyData, stageIdx) {
            var stage = journeyData.stages[stageIdx] || journeyData.stages[0];
            var activeJourneyBtnId = state.journey === 'found' ? 'cf-hiw-journey-found' : 'cf-hiw-journey-lost';

            panelEl.setAttribute('aria-labelledby', activeJourneyBtnId + ' cf-hiw-stage-btn-' + stageIdx);

            if (panelStepEl) {
                panelStepEl.textContent = 'Stage ' + (stageIdx + 1) + ' of ' + journeyData.stages.length + ' \u00b7 ' + journeyData.subtitle;
            }
            if (panelTitleEl) {
                panelTitleEl.textContent = stage.title || '';
            }
            if (panelDescEl) {
                panelDescEl.textContent = stage.desc || '';
            }
            if (panelPointsEl) {
                while (panelPointsEl.firstChild) {
                    panelPointsEl.removeChild(panelPointsEl.firstChild);
                }
                (stage.points || []).forEach(function (pt) {
                    var li = document.createElement('li');
                    li.className = 'cf-hiw-panel-point';

                    var marker = document.createElement('span');
                    marker.className = 'cf-hiw-point-marker';
                    marker.setAttribute('aria-hidden', 'true');

                    var textSpan = document.createElement('span');
                    textSpan.textContent = pt;

                    li.appendChild(marker);
                    li.appendChild(textSpan);
                    panelPointsEl.appendChild(li);
                });
            }
            if (cardIconEl) {
                cardIconEl.className = 'bi ' + (stage.icon || 'bi-check2-circle');
            }
            if (cardNumEl) {
                cardNumEl.textContent = stage.code || '01';
            }
            if (cardBadgeEl) {
                cardBadgeEl.textContent = stage.badge || '';
            }
            if (cardActorEl) {
                cardActorEl.textContent = stage.actor || '';
            }
            if (prevStageBtn) {
                prevStageBtn.disabled = stageIdx <= 0;
            }
            if (nextStageBtn) {
                nextStageBtn.disabled = stageIdx >= journeyData.stages.length - 1;
            }
        }

        function transitionPanel(journeyData, stageIdx, directionSign) {
            if (state.swapTimer !== null) {
                window.clearTimeout(state.swapTimer);
                state.swapTimer = null;
            }

            if (prefersReducedMotion()) {
                panelInnerEl.classList.remove('is-leaving', 'is-entering');
                applyPanelContent(journeyData, stageIdx);
                return;
            }

            panelInnerEl.style.setProperty('--cf-hiw-dir', (directionSign >= 0 ? 14 : -14) + 'px');
            panelInnerEl.classList.add('is-leaving');

            state.swapTimer = window.setTimeout(function () {
                state.swapTimer = null;
                applyPanelContent(journeyData, stageIdx);
                panelInnerEl.classList.remove('is-leaving');
                panelInnerEl.classList.add('is-entering');
                void panelInnerEl.offsetWidth;
                panelInnerEl.classList.remove('is-entering');
            }, 135);
        }

        function syncJourneyButtons() {
            journeyBtns.forEach(function (btn) {
                var isMatch = btn.getAttribute('data-journey') === state.journey;
                btn.classList.toggle('is-active', isMatch);
                btn.setAttribute('aria-selected', isMatch ? 'true' : 'false');
                btn.setAttribute('tabindex', isMatch ? '0' : '-1');
            });
            updateTabIndicator(true);
        }

        function syncCompletionCta(journeyKey) {
            var ctaSection = document.getElementById('final-cta');
            if (!ctaSection) {
                return;
            }
            var normalized = journeyKey === 'found' ? 'found' : 'lost';
            ctaSection.setAttribute('data-active-journey', normalized);

            var eyebrowEl = document.getElementById('cf-final-cta-eyebrow-text');
            var titleEl = document.getElementById('final-cta-heading');
            var descEl = document.getElementById('cf-final-cta-desc');
            var primaryBtn = document.getElementById('cf-final-cta-primary');
            var primaryLabel = document.getElementById('cf-final-cta-primary-label');
            var secondaryBtn = document.getElementById('cf-final-cta-secondary');
            var secondaryLabel = document.getElementById('cf-final-cta-secondary-label');

            if (eyebrowEl) {
                eyebrowEl.textContent = eyebrowEl.getAttribute(normalized === 'found' ? 'data-found-eyebrow' : 'data-lost-eyebrow') || eyebrowEl.textContent;
            }
            if (titleEl) {
                titleEl.textContent = titleEl.getAttribute(normalized === 'found' ? 'data-found-title' : 'data-lost-title') || titleEl.textContent;
            }
            if (descEl) {
                descEl.textContent = descEl.getAttribute(normalized === 'found' ? 'data-found-desc' : 'data-lost-desc') || descEl.textContent;
            }
            if (primaryBtn) {
                var targetHref = primaryBtn.getAttribute(normalized === 'found' ? 'data-found-href' : 'data-lost-href');
                if (targetHref) {
                    primaryBtn.setAttribute('href', targetHref);
                }
            }
            if (primaryLabel && primaryBtn) {
                primaryLabel.textContent = primaryBtn.getAttribute(normalized === 'found' ? 'data-found-label' : 'data-lost-label') || primaryLabel.textContent;
            }
            if (secondaryBtn) {
                secondaryBtn.setAttribute('data-target-journey', normalized === 'found' ? 'lost' : 'found');
            }
            if (secondaryLabel && secondaryBtn) {
                secondaryLabel.textContent = secondaryBtn.getAttribute(normalized === 'found' ? 'data-found-label' : 'data-lost-label') || secondaryLabel.textContent;
            }
        }

        var hiwListeners = [];

        function selectJourney(nextJourneyKey, focusTab) {
            var normalized = nextJourneyKey === 'found' ? 'found' : 'lost';
            var dirSign = normalized === 'found' ? 1 : -1;
            if (normalized === state.journey) {
                syncCompletionCta(normalized);
                return;
            }

            state.journey = normalized;
            syncJourneyButtons();
            populateCards(normalized);
            renderStageButtons();
            var narSync = window.CampusFindLanding && window.CampusFindLanding.narrative;
            if (narSync && narSync.isActive() && narSync.isSyncingFromBeat()) {
                if (panelInnerEl) {
                    panelInnerEl.classList.remove('is-leaving', 'is-entering');
                }
                applyPanelContent(journeys[state.journey], getActiveStageIndex());
            } else {
                transitionPanel(journeys[state.journey], getActiveStageIndex(), dirSign);
            }
            updateCoverflow(getActiveStageIndex(), true);
            syncCompletionCta(normalized);

            if (focusTab) {
                var targetBtn = journeyTablist.querySelector('[data-journey="' + normalized + '"]');
                if (targetBtn) {
                    targetBtn.focus();
                }
            }

            hiwListeners.forEach(function (fn) {
                fn('journey', normalized, getActiveStageIndex());
            });
        }

        function setStageInternal(nextIdx, focusStageBtn, fillOverridePct) {
            var journeyData = journeys[state.journey];
            var maxIdx = journeyData.stages.length - 1;
            var clamped = Math.max(0, Math.min(maxIdx, Number(nextIdx) || 0));
            var prevIdx = getActiveStageIndex();
            var dirSign = clamped >= prevIdx ? 1 : -1;

            state.stageByJourney[state.journey] = clamped;
            renderStageButtons();
            if (typeof fillOverridePct === 'number' && progressFillEl) {
                progressFillEl.style.width = Math.max(0, Math.min(100, fillOverridePct)).toFixed(1) + '%';
            } else {
                updateProgressRail(clamped, journeyData.stages.length);
            }

            var narSync = window.CampusFindLanding && window.CampusFindLanding.narrative;
            if (narSync && narSync.isActive() && narSync.isSyncingFromBeat()) {
                if (panelInnerEl) {
                    panelInnerEl.classList.remove('is-leaving', 'is-entering');
                }
                applyPanelContent(journeyData, clamped);
            } else if (clamped !== prevIdx) {
                transitionPanel(journeyData, clamped, dirSign);
            } else {
                applyPanelContent(journeyData, clamped);
            }
            updateCoverflow(clamped, true);

            if (focusStageBtn) {
                var targetStageBtn = document.getElementById('cf-hiw-stage-btn-' + clamped);
                if (targetStageBtn) {
                    targetStageBtn.focus();
                }
            }
        }

        function selectStage(nextIdx, focusStageBtn) {
            var journeyData = journeys[state.journey];
            var maxIdx = journeyData.stages.length - 1;
            var clamped = Math.max(0, Math.min(maxIdx, Number(nextIdx) || 0));
            var nar = window.CampusFindLanding && window.CampusFindLanding.narrative;

            if (nar && nar.isActive() && !nar.isSyncingFromBeat()) {
                var started = nar.goToBeat('hiw-' + state.journey + '-' + (clamped + 1));
                if (started && focusStageBtn) {
                    var targetStageBtn = document.getElementById('cf-hiw-stage-btn-' + clamped);
                    if (targetStageBtn) {
                        targetStageBtn.focus({ preventScroll: true });
                    }
                }
                return;
            }

            setStageInternal(clamped, focusStageBtn, null);
        }

        // Bind journey tabs (click + WAI-ARIA ArrowLeft/ArrowRight/Home/End)
        journeyBtns.forEach(function (btn, idx) {
            btn.addEventListener('click', function () {
                var chosen = btn.getAttribute('data-journey') === 'found' ? 'found' : 'lost';
                var nar = window.CampusFindLanding && window.CampusFindLanding.narrative;
                if (nar && nar.isActive()) {
                    if (nar.getHiwPhase() === 'stages') {
                        var equivStageNum = (getActiveStageIndex() || 0) + 1;
                        nar.goToBeat('hiw-' + chosen + '-' + equivStageNum);
                    } else {
                        nar.setJourney(chosen);
                    }
                    return;
                }
                selectJourney(chosen, false);
            });

            btn.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowRight' || event.key === 'ArrowLeft' || event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    var nextIdx = idx;
                    if (event.key === 'ArrowRight') {
                        nextIdx = (idx + 1) % journeyBtns.length;
                    } else if (event.key === 'ArrowLeft') {
                        nextIdx = (idx - 1 + journeyBtns.length) % journeyBtns.length;
                    } else if (event.key === 'Home') {
                        nextIdx = 0;
                    } else if (event.key === 'End') {
                        nextIdx = journeyBtns.length - 1;
                    }
                    var nextBtn = journeyBtns[nextIdx];
                    if (nextBtn) {
                        var chosenKey = nextBtn.getAttribute('data-journey') === 'found' ? 'found' : 'lost';
                        var narKey = window.CampusFindLanding && window.CampusFindLanding.narrative;
                        if (narKey && narKey.isActive()) {
                            if (narKey.getHiwPhase() === 'stages') {
                                var equivStageNumKey = (getActiveStageIndex() || 0) + 1;
                                if (narKey.goToBeat('hiw-' + chosenKey + '-' + equivStageNumKey)) {
                                    nextBtn.focus({ preventScroll: true });
                                }
                            } else {
                                narKey.setJourney(chosenKey);
                                nextBtn.focus({ preventScroll: true });
                            }
                        } else {
                            selectJourney(chosenKey, true);
                        }
                    }
                }
            });
        });

        var switchCtaBtn = document.getElementById('cf-final-cta-secondary');
        if (switchCtaBtn) {
            switchCtaBtn.addEventListener('click', function () {
                var targetJourney = switchCtaBtn.getAttribute('data-target-journey') || (state.journey === 'found' ? 'lost' : 'found');
                var normalizedTarget = targetJourney === 'found' ? 'found' : 'lost';
                var nar = window.CampusFindLanding && window.CampusFindLanding.narrative;
                if (nar && nar.isActive()) {
                    nar.goToBeat('hiw-' + normalizedTarget + '-1');
                } else {
                    selectJourney(normalizedTarget, false);
                    section.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
                }
            });
        }

        // Keyboard navigation across 5-stage tablist
        stagesListEl.addEventListener('keydown', function (event) {
            var activeIdx = getActiveStageIndex();
            var total = journeys[state.journey].stages.length;
            if (event.key === 'ArrowRight') {
                event.preventDefault();
                selectStage((activeIdx + 1) % total, true);
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                selectStage((activeIdx - 1 + total) % total, true);
            } else if (event.key === 'Home') {
                event.preventDefault();
                selectStage(0, true);
            } else if (event.key === 'End') {
                event.preventDefault();
                selectStage(total - 1, true);
            }
        });

        if (prevStageBtn) {
            prevStageBtn.addEventListener('click', function () {
                selectStage(getActiveStageIndex() - 1, false);
            });
        }
        if (nextStageBtn) {
            nextStageBtn.addEventListener('click', function () {
                selectStage(getActiveStageIndex() + 1, false);
            });
        }

        // Keep sliding indicator & progress rail responsive on resize and font ready
        var hiwResizeRaf = null;
        window.addEventListener('resize', function () {
            if (hiwResizeRaf !== null) {
                window.cancelAnimationFrame(hiwResizeRaf);
            }
            hiwResizeRaf = window.requestAnimationFrame(function () {
                hiwResizeRaf = null;
                updateTabIndicator(false);
                updateProgressRail(getActiveStageIndex(), journeys[state.journey].stages.length);
                updateCoverflow(getActiveStageIndex(), false);
            });
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                document.body.classList.add('is-page-hidden');
            } else {
                document.body.classList.remove('is-page-hidden');
            }
        });

        // Batch 1: Living Spatial Environment (Pointer-reactive subtle ambient lighting shift on desktop)
        var ambientEnv = document.getElementById('cf-hiw-ambient-env');
        var targetPtrX = 0;
        var targetPtrY = 0;
        var currentPtrX = 0;
        var currentPtrY = 0;
        var ptrRafId = null;

        function updatePointerAtmosphere() {
            ptrRafId = null;
            if (prefersReducedMotion() || document.hidden || !ambientEnv) {
                return;
            }
            currentPtrX += (targetPtrX - currentPtrX) * 0.05;
            currentPtrY += (targetPtrY - currentPtrY) * 0.05;

            ambientEnv.style.setProperty('--hiw-ptr-x', (currentPtrX * 24).toFixed(2) + 'px');
            ambientEnv.style.setProperty('--hiw-ptr-y', (currentPtrY * 16).toFixed(2) + 'px');

            var dist = Math.abs(targetPtrX - currentPtrX) + Math.abs(targetPtrY - currentPtrY);
            if (dist > 0.001) {
                ptrRafId = window.requestAnimationFrame(updatePointerAtmosphere);
            }
        }

        function onHiwPointerMove(e) {
            if (window.innerWidth < 1024 || prefersReducedMotion()) {
                return;
            }
            var rect = section ? section.getBoundingClientRect() : null;
            if (!rect || rect.width <= 0 || rect.height <= 0) {
                return;
            }
            var x = (e.clientX - rect.left) / rect.width;
            var y = (e.clientY - rect.top) / rect.height;
            targetPtrX = Math.max(-1, Math.min(1, (x - 0.5) * 2));
            targetPtrY = Math.max(-1, Math.min(1, (y - 0.5) * 2));

            if (ptrRafId === null) {
                ptrRafId = window.requestAnimationFrame(updatePointerAtmosphere);
            }
        }

        function onHiwPointerLeave() {
            targetPtrX = 0;
            targetPtrY = 0;
            if (ptrRafId === null) {
                ptrRafId = window.requestAnimationFrame(updatePointerAtmosphere);
            }
        }

        if (section) {
            section.addEventListener('pointermove', onHiwPointerMove, { passive: true });
            section.addEventListener('pointerleave', onHiwPointerLeave, { passive: true });
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                updateTabIndicator(false);
            });
        }

        // Bind existing click triggers cleanly
        populateCards(state.journey);
        bindCardEvents();
        renderStageButtons();
        applyPanelContent(journeys[state.journey], getActiveStageIndex());
        updateCoverflow(getActiveStageIndex(), false);
        updateTabIndicator(false);
        syncCompletionCta(state.journey);

        return {
            selectJourney: function (k) { selectJourney(k, false); },
            selectStage: function (i) { selectStage(i, false); },
            syncScrollStage: function (i, fillOverridePct) {
                setStageInternal(i, false, fillOverridePct);
            },
            setCoverflowProgress: function (virtualIdx) {
                updateCoverflow(virtualIdx, false);
            },
            refreshIndicator: function () { updateTabIndicator(false); },
            refreshCoverflow: function () { updateCoverflow(getActiveStageIndex(), false); },
            onChange: function (fn) {
                if (typeof fn === 'function') {
                    hiwListeners.push(fn);
                }
            },
            getState: function () {
                var activeIdx = getActiveStageIndex();
                var stagesLen = journeys[state.journey].stages.length;
                var indicatorRect = tabIndicator ? tabIndicator.getBoundingClientRect() : null;
                var activeJourneyBtn = journeyTablist.querySelector('.cf-hiw-journey-tab.is-active');
                var activeBtnRect = activeJourneyBtn ? activeJourneyBtn.getBoundingClientRect() : null;
                return {
                    journey: state.journey,
                    stageIndex: activeIdx,
                    stageLabel: journeys[state.journey].stages[activeIdx].label,
                    stageByJourney: {
                        lost: state.stageByJourney.lost,
                        found: state.stageByJourney.found,
                    },
                    progressWidth: progressFillEl ? progressFillEl.style.width : null,
                    indicatorMatchesActiveTab: Boolean(
                        indicatorRect && activeBtnRect &&
                        Math.abs(indicatorRect.left - activeBtnRect.left) <= 2 &&
                        Math.abs(indicatorRect.width - activeBtnRect.width) <= 2
                    ),
                    stagesCount: stagesLen,
                };
            },
        };
    }

    function initFinalCtaReveal() {
        var container = document.getElementById('cf-final-cta-inner');
        if (!container) {
            return;
        }

        if (prefersReducedMotion() || document.getElementById('cf-stage-viewport') || typeof window.IntersectionObserver !== 'function') {
            container.classList.add('cf-reveal-ready');
            return;
        }

        var rect = container.getBoundingClientRect();
        if (rect.top <= window.innerHeight * 0.92) {
            container.classList.add('cf-reveal-ready');
            return;
        }

        container.classList.add('cf-reveal-pending');

        var observer = new window.IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    container.classList.remove('cf-reveal-pending');
                    container.classList.add('cf-reveal-ready');
                    observer.disconnect();
                }
            });
        }, { threshold: 0.16 });

        observer.observe(container);
    }

    function initGuestLiquidGlassNav() {
        var dock = document.getElementById('cf-guest-nav');
        var capsule = document.getElementById('cf-lg-capsule');
        if (!dock || !capsule) {
            return null;
        }

        document.body.classList.add('cf-has-glass-nav');

        var navList = document.getElementById('cf-lg-nav-list');
        var indicator = document.getElementById('cf-lg-active-indicator');
        var helpItem = document.getElementById('cf-lg-help-item');
        var helpBtn = document.getElementById('cf-lg-help-btn');
        var helpMenu = document.getElementById('cf-lg-help-menu');
        var burger = document.getElementById('cf-guest-burger');
        var mobDrawer = document.getElementById('cf-guest-mob');
        var navMapEl = document.getElementById('cf-lg-nav-map');
        var howItWorksEl = document.getElementById('how-it-works');
        var navSections = Array.from(document.querySelectorAll('#cf-lg-nav-list [data-nav-section]'));
        var helpItems = helpMenu ? Array.from(helpMenu.querySelectorAll('[role="menuitem"]')) : [];

        var activeSection = 'hero';
        var helpOpen = false;
        var mobileOpen = false;
        var focusInsideNav = false;
        var pointerNearTop = false;
        var navHidden = false;
        var programmaticScrollUntil = 0;
        var helpHoverInTimer = null;
        var helpHoverOutTimer = null;

        var opticalSupported = detectSvgBackdropSupport();
        document.body.classList.toggle('is-optical-active', opticalSupported);
        document.body.classList.toggle('is-fallback-forced', !opticalSupported);

        var lastMapWidth = 0;
        function applyStaticDisplacementMap() {
            if (!navMapEl || !opticalSupported) {
                return;
            }
            var rect = capsule.getBoundingClientRect();
            if (!rect.width || !rect.height) {
                return;
            }
            if (Math.abs(rect.width - lastMapWidth) <= 14 && lastMapWidth > 0) {
                return;
            }
            lastMapWidth = rect.width;
            var dataUrl = buildCapsuleDisplacementMap(rect.width, rect.height, 0.62);
            if (dataUrl) {
                navMapEl.setAttribute('href', dataUrl);
            }
        }
        applyStaticDisplacementMap();

        // Pointer specular sheen (updates CSS custom props via rAF only)
        if (!prefersReducedMotion() && !isTouchDevice()) {
            var rootStyle = document.documentElement.style;
            var specRaf = null;
            var ptrX = 50;
            var ptrY = 28;
            var ptrAngle = 132;

            capsule.addEventListener('pointermove', function (event) {
                if (prefersReducedMotion()) {
                    return;
                }
                var rect = capsule.getBoundingClientRect();
                if (!rect.width || !rect.height) {
                    return;
                }
                ptrX = Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100));
                ptrY = Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100));
                var dx = event.clientX - (rect.left + rect.width * 0.5);
                var dy = event.clientY - (rect.top + rect.height * 0.5);
                ptrAngle = (Math.atan2(dy, dx) * 180) / Math.PI + 90;

                if (specRaf === null) {
                    specRaf = window.requestAnimationFrame(function () {
                        specRaf = null;
                        rootStyle.setProperty('--lg-ptr-x', ptrX.toFixed(1) + '%');
                        rootStyle.setProperty('--lg-ptr-y', ptrY.toFixed(1) + '%');
                        rootStyle.setProperty('--lg-angle', ptrAngle.toFixed(1) + 'deg');
                    });
                }
            }, { passive: true });
        }

        function setHiddenState(shouldHide) {
            if (helpOpen || mobileOpen || focusInsideNav || pointerNearTop) {
                shouldHide = false;
            }
            navHidden = Boolean(shouldHide);
            dock.classList.toggle('is-nav-hidden', navHidden);
        }

        function updateActiveIndicator(animate) {
            if (!navList || !indicator) {
                return;
            }
            var activeEl = navList.querySelector('[data-nav-section="' + activeSection + '"]');
            if (!activeEl) {
                activeEl = navList.querySelector('[data-nav-section="hero"]');
            }
            if (!activeEl || activeEl.offsetWidth === 0) {
                return;
            }

            if (animate === false || prefersReducedMotion()) {
                indicator.style.transition = 'none';
            } else {
                indicator.style.transition = '';
            }

            var innerRect = indicator.parentElement.getBoundingClientRect();
            var itemRect = activeEl.getBoundingClientRect();
            var offsetLeft = itemRect.left - innerRect.left;

            indicator.style.width = itemRect.width + 'px';
            indicator.style.transform = 'translate3d(' + offsetLeft + 'px, 0, 0)';
            indicator.classList.add('is-ready');

            if (animate === false) {
                void indicator.offsetWidth;
                indicator.style.transition = '';
            }
        }

        function setActiveSection(sectionId) {
            if (!sectionId || activeSection === sectionId) {
                return;
            }
            activeSection = sectionId;
            navSections.forEach(function (el) {
                var isActive = el.getAttribute('data-nav-section') === sectionId;
                el.classList.toggle('is-active', isActive);
                if (el.tagName === 'A') {
                    if (isActive) {
                        el.setAttribute('aria-current', 'location');
                    } else {
                        el.removeAttribute('aria-current');
                    }
                }
            });
            updateActiveIndicator(true);
        }

        function openHelp(focusFirstItem) {
            window.clearTimeout(helpHoverOutTimer);
            if (!helpBtn || !helpMenu) {
                return;
            }
            helpOpen = true;
            setHiddenState(false);
            dock.classList.add('is-open-menu');
            helpBtn.setAttribute('aria-expanded', 'true');
            helpMenu.hidden = false;
            helpMenu.setAttribute('aria-hidden', 'false');
            window.requestAnimationFrame(function () {
                if (helpOpen) {
                    helpMenu.classList.add('is-open');
                }
            });
            if (focusFirstItem && helpItems.length > 0) {
                helpItems[0].focus();
            }
        }

        function closeHelp(restoreFocus) {
            window.clearTimeout(helpHoverInTimer);
            window.clearTimeout(helpHoverOutTimer);
            if (!helpBtn || !helpMenu) {
                return;
            }
            helpOpen = false;
            helpBtn.setAttribute('aria-expanded', 'false');
            helpMenu.classList.remove('is-open');
            helpMenu.setAttribute('aria-hidden', 'true');
            helpMenu.hidden = true;
            if (!mobileOpen) {
                dock.classList.remove('is-open-menu');
            }
            if (restoreFocus) {
                helpBtn.focus();
            }
        }

        function setMobile(open) {
            if (!burger || !mobDrawer) {
                return;
            }
            mobileOpen = Boolean(open);
            if (mobileOpen) {
                closeHelp(false);
                setHiddenState(false);
                dock.classList.add('is-open-menu');
                mobDrawer.hidden = false;
                mobDrawer.setAttribute('aria-hidden', 'false');
                burger.setAttribute('aria-expanded', 'true');
                burger.setAttribute('aria-label', 'Close menu');
                document.body.style.overflow = 'hidden';
                window.requestAnimationFrame(function () {
                    if (mobileOpen) {
                        mobDrawer.classList.add('is-open');
                    }
                });
            } else {
                mobDrawer.classList.remove('is-open');
                mobDrawer.setAttribute('aria-hidden', 'true');
                mobDrawer.hidden = true;
                burger.setAttribute('aria-expanded', 'false');
                burger.setAttribute('aria-label', 'Open menu');
                document.body.style.overflow = '';
                if (!helpOpen) {
                    dock.classList.remove('is-open-menu');
                }
            }
        }

        function smoothScrollToTarget(selector) {
            if (!selector || selector.charAt(0) !== '#') {
                return;
            }
            closeHelp(false);
            setMobile(false);

            var sectionName = selector.slice(1);
            if (sectionName === 'hero' || sectionName === 'recently-reported' || sectionName === 'how-it-works') {
                setActiveSection(sectionName);
            }

            programmaticScrollUntil = prefersReducedMotion() ? 0 : (Date.now() + 950);

            var nar = window.CampusFindLanding && window.CampusFindLanding.narrative;
            if (nar && nar.isActive()) {
                nar.jumpToLandmark(sectionName);
                return;
            }

            if (sectionName === 'hero') {
                window.scrollTo({
                    top: 0,
                    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                });
                return;
            }

            var targetEl = document.querySelector(selector);
            if (!targetEl) {
                return;
            }
            var targetTop = window.scrollY + targetEl.getBoundingClientRect().top - 72;
            window.scrollTo({
                top: Math.max(0, targetTop),
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
            });
        }

        // Smooth anchor navigation + Cinematic Direct Login Shortcut across dock + mobile drawer
        dock.addEventListener('click', function (event) {
            var loginTrigger = event.target.closest('#cf-guest-login-btn');
            if (loginTrigger) {
                var nar = window.CampusFindLanding && window.CampusFindLanding.narrative;
                if (nar && nar.isActive() && !prefersReducedMotion()) {
                    event.preventDefault();
                    closeHelp(false);
                    setMobile(false);
                    nar.openLoginShortcut();
                    return;
                }
            }

            var anchor = event.target.closest('[data-nav-target]');
            if (anchor) {
                event.preventDefault();
                smoothScrollToTarget(anchor.getAttribute('data-nav-target'));
                return;
            }

            var cfindTrigger = event.target.closest('[data-open-cfind]');
            if (cfindTrigger) {
                event.preventDefault();
                closeHelp(false);
                setMobile(false);
                if (typeof window.openCFind === 'function') {
                    window.openCFind(true);
                } else {
                    var launcher = document.getElementById('cfind-launcher');
                    if (launcher) {
                        launcher.click();
                    }
                }
            }
        });

        if (mobDrawer) {
            mobDrawer.addEventListener('click', function (event) {
                var mobLoginTrigger = event.target.closest('#cf-guest-mob-login');
                if (mobLoginTrigger) {
                    var narMob = window.CampusFindLanding && window.CampusFindLanding.narrative;
                    if (narMob && narMob.isActive() && !prefersReducedMotion()) {
                        event.preventDefault();
                        setMobile(false);
                        narMob.openLoginShortcut();
                        return;
                    }
                }

                var anchor = event.target.closest('[data-nav-target]');
                if (anchor) {
                    event.preventDefault();
                    smoothScrollToTarget(anchor.getAttribute('data-nav-target'));
                    return;
                }

                var cfindBtn = event.target.closest('[data-open-cfind]');
                if (cfindBtn) {
                    event.preventDefault();
                    setMobile(false);
                    if (typeof window.openCFind === 'function') {
                        window.openCFind(true);
                    } else {
                        var launcher = document.getElementById('cfind-launcher');
                        if (launcher) {
                            launcher.click();
                        }
                    }
                }
            });
        }

        // Help dropdown click, hover & keyboard interactions
        if (helpBtn && helpItem && helpMenu) {
            helpBtn.addEventListener('click', function (event) {
                event.preventDefault();
                if (helpOpen) {
                    closeHelp(false);
                } else {
                    openHelp(false);
                }
            });

            helpItem.addEventListener('pointerenter', function (event) {
                if (event.pointerType !== 'mouse') {
                    return;
                }
                window.clearTimeout(helpHoverOutTimer);
                if (!helpOpen) {
                    helpHoverInTimer = window.setTimeout(function () {
                        openHelp(false);
                    }, 90);
                }
            });

            helpItem.addEventListener('pointerleave', function (event) {
                if (event.pointerType !== 'mouse') {
                    return;
                }
                window.clearTimeout(helpHoverInTimer);
                helpHoverOutTimer = window.setTimeout(function () {
                    closeHelp(false);
                }, 160);
            });

            helpBtn.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openHelp(true);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    openHelp(false);
                    if (helpItems.length > 0) {
                        helpItems[helpItems.length - 1].focus();
                    }
                }
            });

            helpMenu.addEventListener('keydown', function (event) {
                if (helpItems.length === 0) {
                    return;
                }
                var curIdx = helpItems.indexOf(document.activeElement);
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    helpItems[(curIdx + 1) % helpItems.length].focus();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    helpItems[(curIdx - 1 + helpItems.length) % helpItems.length].focus();
                } else if (event.key === 'Home') {
                    event.preventDefault();
                    helpItems[0].focus();
                } else if (event.key === 'End') {
                    event.preventDefault();
                    helpItems[helpItems.length - 1].focus();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeHelp(true);
                } else if (event.key === 'Tab') {
                    window.requestAnimationFrame(function () {
                        if (!helpItem.contains(document.activeElement)) {
                            closeHelp(false);
                        }
                    });
                }
            });
        }

        if (burger) {
            burger.addEventListener('click', function () {
                setMobile(!mobileOpen);
            });
        }

        // Global Escape & Outside click handling
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            if (helpOpen) {
                closeHelp(true);
            }
            if (mobileOpen) {
                setMobile(false);
                if (burger) {
                    burger.focus();
                }
            }
        });

        document.addEventListener('click', function (event) {
            if (helpOpen && helpItem && !helpItem.contains(event.target)) {
                closeHelp(false);
            }
        });

        // Keyboard focus inside dock keeps smart navbar visible
        dock.addEventListener('focusin', function () {
            focusInsideNav = true;
            setHiddenState(false);
        });

        dock.addEventListener('focusout', function () {
            window.requestAnimationFrame(function () {
                focusInsideNav = dock.contains(document.activeElement);
                if (!helpItem || !helpItem.contains(document.activeElement)) {
                    if (helpOpen) {
                        closeHelp(false);
                    }
                }
            });
        });

        // Desktop top-edge cursor reveal (clientY <= 38)
        document.addEventListener('pointermove', function (event) {
            if ((event.pointerType && event.pointerType !== 'mouse') || isTouchDevice()) {
                return;
            }
            var inDock = dock.contains(event.target);
            if (event.clientY <= 38 || inDock) {
                pointerNearTop = true;
                if (navHidden) {
                    setHiddenState(false);
                }
            } else if (event.clientY > 140) {
                pointerNearTop = false;
            }
        }, { passive: true });

        // Smart hide-on-scroll-down / reveal-on-scroll-up + active section tracking + dark contrast guard
        var trackedSections = [
            { id: 'hero', el: document.getElementById('hero') },
            { id: 'recently-reported', el: document.getElementById('recently-reported') },
            { id: 'how-it-works', el: howItWorksEl },
        ].filter(function (s) { return Boolean(s.el); });

        var lastScrollY = window.scrollY || 0;
        var scrollRaf = null;

        function handleScrollFrame() {
            scrollRaf = null;

            // Narrative beat coordinator owns activeSection, overDark & dock visibility in active mode
            var narApi = window.CampusFindLanding && window.CampusFindLanding.narrative;
            if (narApi && narApi.isActive()) {
                setHiddenState(false);
                return;
            }

            var currentY = window.scrollY || 0;
            var delta = currentY - lastScrollY;

            // Hide when scrolling down cleanly past top threshold; reveal when scrolling up or near top
            if (currentY <= 80 || delta < -6) {
                setHiddenState(false);
            } else if (Date.now() > programmaticScrollUntil && currentY > 120 && delta > 8) {
                setHiddenState(true);
            }
            lastScrollY = currentY;

            var currentActive = 'hero';
            for (var i = 0; i < trackedSections.length; i += 1) {
                var secRect = trackedSections[i].el.getBoundingClientRect();
                if (secRect.top <= 170) {
                    currentActive = trackedSections[i].id;
                }
            }
            setActiveSection(currentActive);

            if (howItWorksEl) {
                var hiwRect = howItWorksEl.getBoundingClientRect();
                dock.classList.toggle('is-over-dark', hiwRect.top <= 76 && hiwRect.bottom >= 16);
            }
        }

        window.addEventListener('scroll', function () {
            if (scrollRaf === null) {
                scrollRaf = window.requestAnimationFrame(handleScrollFrame);
            }
        }, { passive: true });

        // Debounced resize for displacement map & responsive indicator / drawer cleanup
        var resizeMapTimer = null;
        window.addEventListener('resize', function () {
            updateActiveIndicator(false);
            if (window.innerWidth >= 900 && mobileOpen) {
                setMobile(false);
            }
            window.clearTimeout(resizeMapTimer);
            resizeMapTimer = window.setTimeout(applyStaticDisplacementMap, 160);
        });

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                updateActiveIndicator(false);
            });
        }

        // Initial indicator sync
        updateActiveIndicator(false);
        handleScrollFrame();

        return {
            getState: function () {
                return {
                    opticalActive: document.body.classList.contains('is-optical-active'),
                    fallbackForced: document.body.classList.contains('is-fallback-forced'),
                    activeSection: activeSection,
                    navHidden: navHidden,
                    sceneHidden: dock.classList.contains('is-scene-hidden'),
                    helpOpen: helpOpen,
                    mobileOpen: mobileOpen,
                    overDark: dock.classList.contains('is-over-dark'),
                    hasDisplacementHref: Boolean(navMapEl && navMapEl.getAttribute('href')),
                };
            },
            setActiveSection: setActiveSection,
            setOverDark: function (flag) {
                dock.classList.toggle('is-over-dark', Boolean(flag));
            },
            setSceneHidden: function (flag) {
                dock.classList.toggle('is-scene-hidden', Boolean(flag));
            },
            forceReveal: function () {
                setHiddenState(false);
            },
            openHelp: openHelp,
            closeHelp: closeHelp,
        };
    }

    function initSceneNarrative(hiwApi, navApi, recentApi, heroParallaxApi) {
        var root = document.getElementById('cf-narrative-root');
        var stageViewport = document.getElementById('cf-stage-viewport');
        var sceneLayers = {
            hero: document.getElementById('cf-scene-hero'),
            recent: document.getElementById('cf-scene-recent'),
            hiw: document.getElementById('cf-scene-hiw'),
            cta: document.getElementById('cf-scene-cta'),
            closing: document.getElementById('cf-scene-closing'),
        };
        if (!root || !stageViewport || !sceneLayers.hero || !sceneLayers.recent || !sceneLayers.hiw || !sceneLayers.cta || !sceneLayers.closing) {
            return null;
        }

        var heroEyebrowWrap = document.getElementById('cf-hero-scrub-eyebrow');
        var heroTitleWrap = document.getElementById('cf-hero-scrub-title');
        var heroLine1Wrap = document.getElementById('cf-hero-scrub-line-1');
        var heroLine2Wrap = document.getElementById('cf-hero-scrub-line-2');
        var heroSubWrap = document.getElementById('cf-hero-scrub-sub');
        var heroActionsWrap = document.getElementById('cf-hero-scrub-actions');
        var heroBtns = heroActionsWrap ? Array.prototype.slice.call(heroActionsWrap.querySelectorAll('.cf-hero-btn')) : [];
        var heroScrubObjects = Array.prototype.slice.call(sceneLayers.hero.querySelectorAll('.cf-hero-scrub-obj'));
        var recentHeadingWrap = sceneLayers.recent.querySelector('.cf-recent-heading-wrap');
        var recentControlsBar = sceneLayers.recent.querySelector('.cf-recent-controls-bar');
        var recentGatedWrap = sceneLayers.recent.querySelector('.cf-recent-gated');

        var hiwShell = document.getElementById('cf-hiw-shell');
        var hiwHeadingWrap = document.getElementById('cf-hiw-heading-wrap');
        var hiwChoiceWrap = document.getElementById('cf-hiw-choice-wrap');
        var hiwLostTab = document.getElementById('cf-hiw-journey-lost');
        var hiwFoundTab = document.getElementById('cf-hiw-journey-found');
        var hiwShowcase = document.getElementById('cf-hiw-showcase');
        var hiwPanelInner = document.getElementById('cf-hiw-panel-inner');
        var hiwProgressFill = document.getElementById('cf-hiw-progress-fill');
        var ctaInner = document.getElementById('cf-final-cta-inner');
        var ctaEyebrow = document.getElementById('cf-final-cta-eyebrow');
        var ctaTitle = document.getElementById('final-cta-heading');
        var ctaDesc = document.getElementById('cf-final-cta-desc');
        var ctaActions = sceneLayers.cta ? sceneLayers.cta.querySelector('.cf-final-cta-actions') : null;
        var ctaPrimary = document.getElementById('cf-final-cta-primary');
        var ctaSecondary = document.getElementById('cf-final-cta-secondary');

        var closingCopy = sceneLayers.closing ? sceneLayers.closing.querySelector('.cf-closing-copy') : null;
        var closingEyebrow = sceneLayers.closing ? sceneLayers.closing.querySelector('.cf-closing-eyebrow') : null;
        var closingTitle = document.getElementById('cf-closing-heading');
        var closingDesc = sceneLayers.closing ? sceneLayers.closing.querySelector('.cf-closing-desc') : null;
        var closingActions = sceneLayers.closing ? sceneLayers.closing.querySelector('.cf-closing-actions') : null;
        var closingObjs = sceneLayers.closing ? Array.prototype.slice.call(sceneLayers.closing.querySelectorAll('.cf-closing-obj')) : [];
        var closingFooterWrap = sceneLayers.closing ? sceneLayers.closing.querySelector('.cf-closing-footer-wrap') : null;

        var loginPortal = document.getElementById('cf-scene-login-portal');
        var loginBackdrop = document.getElementById('cf-scene-login-backdrop');
        var loginBrand = document.getElementById('cf-scene-login-brand');
        var loginCardWrap = document.getElementById('cf-scene-login-card-wrap');
        var loginBackBtn = document.getElementById('cf-login-scene-back');
        var loginBackLabel = document.getElementById('cf-login-scene-back-label');
        var loginGlassPanel = document.getElementById('cf-lg-login-panel');
        var loginHeader = loginGlassPanel ? loginGlassPanel.querySelector('.cf-lg-login-header') : null;
        var loginIdentityInput = loginGlassPanel ? loginGlassPanel.querySelector('#input-identity') : null;
        var loginIdentityGroup = loginIdentityInput ? loginIdentityInput.closest('.mb-3') : null;
        var loginPasswordInput = loginGlassPanel ? loginGlassPanel.querySelector('#input-password') : null;
        var loginPasswordGroup = loginPasswordInput ? loginPasswordInput.closest('.mb-3') : null;
        var loginSubmitBtn = document.getElementById('btn-submit-login');
        var loginHelperText = loginGlassPanel ? loginGlassPanel.querySelector('.border-top') : null;
        var loginGlassMap = document.getElementById('cf-lg-login-map');
        var opticalSupported = detectSvgBackdropSupport();
        var lastLoginMapW = 0;

        var TRANSITION_DURATION_MS = 620;
        var WHEEL_THRESHOLD = 52;
        var WHEEL_IDLE_DEBOUNCE_MS = 260;
        var TOUCH_SWIPE_THRESHOLD = 46;

        function applyLoginGlassMap() {
            if (!loginGlassPanel || !loginGlassMap || !opticalSupported) {
                return;
            }
            var rect = loginGlassPanel.getBoundingClientRect();
            if (!rect.width || !rect.height) {
                return;
            }
            if (Math.abs(rect.width - lastLoginMapW) <= 18 && lastLoginMapW > 0) {
                return;
            }
            lastLoginMapW = rect.width;
            var href = buildCapsuleDisplacementMap(Math.min(360, rect.width * 0.55), Math.min(420, rect.height * 0.55), 0.34);
            if (href) {
                loginGlassMap.setAttribute('href', href);
            }
        }

        if (loginGlassPanel && !isTouchDevice()) {
            loginGlassPanel.addEventListener('pointermove', function (event) {
                if (event.pointerType === 'touch' || prefersReducedMotion()) {
                    return;
                }
                var rect = loginGlassPanel.getBoundingClientRect();
                if (!rect.width || !rect.height) {
                    return;
                }
                var xPct = Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100));
                var yPct = Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100));
                loginGlassPanel.style.setProperty('--cf-login-mx', xPct.toFixed(1) + '%');
                loginGlassPanel.style.setProperty('--cf-login-my', yPct.toFixed(1) + '%');
            }, { passive: true });
        }

        var BEAT_ORDER = [
            'hero',
            'recently-reported',
            'hiw-intro',
            'hiw-journey-selection',
            'hiw-lost-1',
            'hiw-lost-2',
            'hiw-lost-3',
            'hiw-lost-4',
            'hiw-lost-5',
            'hiw-found-1',
            'hiw-found-2',
            'hiw-found-3',
            'hiw-found-4',
            'hiw-found-5',
            'cta-lost',
            'cta-found',
            'closing',
            'login',
        ];

        var BEATS = {
            'hero': { id: 'hero', scene: 'hero', rank: 0, navSection: 'hero', overDark: false, hideNav: false },
            'recently-reported': { id: 'recently-reported', scene: 'recent', rank: 1, navSection: 'recently-reported', overDark: false, hideNav: false },
            'hiw-intro': { id: 'hiw-intro', scene: 'hiw', hiwPhase: 'intro', rank: 2, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-journey-selection': { id: 'hiw-journey-selection', scene: 'hiw', hiwPhase: 'choice', rank: 3, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-lost-1': { id: 'hiw-lost-1', scene: 'hiw', hiwPhase: 'stages', journey: 'lost', stageIndex: 0, rank: 4, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-lost-2': { id: 'hiw-lost-2', scene: 'hiw', hiwPhase: 'stages', journey: 'lost', stageIndex: 1, rank: 5, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-lost-3': { id: 'hiw-lost-3', scene: 'hiw', hiwPhase: 'stages', journey: 'lost', stageIndex: 2, rank: 6, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-lost-4': { id: 'hiw-lost-4', scene: 'hiw', hiwPhase: 'stages', journey: 'lost', stageIndex: 3, rank: 7, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-lost-5': { id: 'hiw-lost-5', scene: 'hiw', hiwPhase: 'stages', journey: 'lost', stageIndex: 4, rank: 8, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-found-1': { id: 'hiw-found-1', scene: 'hiw', hiwPhase: 'stages', journey: 'found', stageIndex: 0, rank: 4, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-found-2': { id: 'hiw-found-2', scene: 'hiw', hiwPhase: 'stages', journey: 'found', stageIndex: 1, rank: 5, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-found-3': { id: 'hiw-found-3', scene: 'hiw', hiwPhase: 'stages', journey: 'found', stageIndex: 2, rank: 6, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-found-4': { id: 'hiw-found-4', scene: 'hiw', hiwPhase: 'stages', journey: 'found', stageIndex: 3, rank: 7, navSection: 'how-it-works', overDark: true, hideNav: false },
            'hiw-found-5': { id: 'hiw-found-5', scene: 'hiw', hiwPhase: 'stages', journey: 'found', stageIndex: 4, rank: 8, navSection: 'how-it-works', overDark: true, hideNav: false },
            'cta-lost': { id: 'cta-lost', scene: 'cta', journey: 'lost', rank: 9, navSection: 'how-it-works', overDark: false, hideNav: false },
            'cta-found': { id: 'cta-found', scene: 'cta', journey: 'found', rank: 9, navSection: 'how-it-works', overDark: false, hideNav: false },
            'closing': { id: 'closing', scene: 'closing', rank: 10, navSection: 'how-it-works', overDark: false, hideNav: false },
            'login': { id: 'login', scene: 'login', rank: 11, navSection: 'how-it-works', overDark: true, hideNav: true },
        };

        var state = {
            active: !prefersReducedMotion(),
            syncingFromBeat: false,
            currentBeat: 'hero',
            activeBeat: 'hero',
            targetBeat: null,
            activeScene: 'hero',
            hiwPhase: 'intro',
            currentJourney: 'lost',
            currentJourneyStage: 1,
            isTransitioning: false,
            loginEntryMode: 'natural',
            loginOriginBeat: 'closing',
            returnBeat: 'closing',
            gestureInProgress: false,
            lastGestureTime: 0,
            wheelAccum: 0,
            wheelAccumDir: 0,
            wheelIdleTimer: null,
            rafId: null,
            transitionTimer: null,
            transitionToken: 0,
        };

        function easeOutCubic(t) {
            var inv = 1 - Math.max(0, Math.min(1, t));
            return 1 - inv * inv * inv;
        }

        function easeInOutCubic(t) {
            var clamped = Math.max(0, Math.min(1, t));
            return clamped < 0.5
                ? 4 * clamped * clamped * clamped
                : 1 - Math.pow(-2 * clamped + 2, 3) / 2;
        }

        function getActiveJourney() {
            if (hiwApi && typeof hiwApi.getState === 'function') {
                return hiwApi.getState().journey === 'found' ? 'found' : 'lost';
            }
            return state.currentJourney === 'found' ? 'found' : 'lost';
        }

        function resolveBeatId(rawId) {
            var key = String(rawId || '').trim().toLowerCase().replace(/^\/?#/, '');
            if (!key) {
                return null;
            }
            if (Object.prototype.hasOwnProperty.call(BEATS, key)) {
                return key;
            }
            if (key === 'cta' || key === 'final-cta') {
                return 'cta-' + getActiveJourney();
            }
            if (key === 'how-it-works' || key === 'fitur' || key === 'how-it-works-intro') {
                return 'hiw-intro';
            }
            if (key === 'cf-closing') {
                return 'closing';
            }
            if (key === 'login-scene' || key === 'cf-scene-login-portal') {
                return 'login';
            }
            return null;
        }

        function getNextNaturalBeat(fromId) {
            var from = fromId || state.currentBeat;
            var activeJourney = getActiveJourney();
            if (from === 'hero') {
                return 'recently-reported';
            }
            if (from === 'recently-reported') {
                return 'hiw-intro';
            }
            if (from === 'hiw-intro') {
                return 'hiw-journey-selection';
            }
            if (from === 'hiw-journey-selection') {
                return 'hiw-' + activeJourney + '-1';
            }
            var beat = BEATS[from];
            if (beat && beat.hiwPhase === 'stages' && typeof beat.stageIndex === 'number') {
                var stageNum = beat.stageIndex + 1;
                if (stageNum < 5) {
                    return 'hiw-' + beat.journey + '-' + (stageNum + 1);
                }
                return 'cta-' + beat.journey;
            }
            if (from === 'cta-lost' || from === 'cta-found') {
                return 'closing';
            }
            if (from === 'closing') {
                return 'login';
            }
            return null;
        }

        function getPrevNaturalBeat(fromId) {
            var from = fromId || state.currentBeat;
            var activeJourney = getActiveJourney();
            if (from === 'hero') {
                return null;
            }
            if (from === 'recently-reported') {
                return 'hero';
            }
            if (from === 'hiw-intro') {
                return 'recently-reported';
            }
            if (from === 'hiw-journey-selection') {
                return 'hiw-intro';
            }
            var beat = BEATS[from];
            if (beat && beat.hiwPhase === 'stages' && typeof beat.stageIndex === 'number') {
                var stageNum = beat.stageIndex + 1;
                if (stageNum > 1) {
                    return 'hiw-' + beat.journey + '-' + (stageNum - 1);
                }
                return 'hiw-journey-selection';
            }
            if (from === 'cta-lost') {
                return 'hiw-lost-5';
            }
            if (from === 'cta-found') {
                return 'hiw-found-5';
            }
            if (from === 'closing') {
                return 'cta-' + activeJourney;
            }
            if (from === 'login') {
                if (state.loginEntryMode === 'shortcut' && state.loginOriginBeat && state.loginOriginBeat !== 'login') {
                    return state.loginOriginBeat;
                }
                return 'closing';
            }
            return null;
        }

        function computeTransitionDirection(fromBeat, toBeat) {
            if (!fromBeat || !toBeat) {
                return 1;
            }
            if (fromBeat.id === 'login') {
                return -1;
            }
            if (toBeat.id === 'login') {
                return 1;
            }
            if (toBeat.rank !== fromBeat.rank) {
                return toBeat.rank > fromBeat.rank ? 1 : -1;
            }
            if (toBeat.journey && fromBeat.journey && toBeat.journey !== fromBeat.journey) {
                return toBeat.journey === 'found' ? 1 : -1;
            }
            return 1;
        }

        function clampDocumentScroll() {
            if (!state.active) {
                return;
            }
            if ((window.scrollY || 0) !== 0 || (window.scrollX || 0) !== 0) {
                window.scrollTo(0, 0);
            }
            if (document.documentElement && document.documentElement.scrollTop !== 0) {
                document.documentElement.scrollTop = 0;
            }
            if (document.body && document.body.scrollTop !== 0) {
                document.body.scrollTop = 0;
            }
        }

        function clearTransitionInlineStyles() {
            Object.keys(sceneLayers).forEach(function (sceneKey) {
                var layerEl = sceneLayers[sceneKey];
                layerEl.classList.remove(
                    'is-scene-entering', 'is-scene-leaving', 'is-scene-underlay',
                    'is-scene-cover', 'is-scene-curtain-fg', 'is-scene-curtain-bg',
                    'is-scene-curtain-navy', 'is-scene-receding-bg',
                    'is-scene-curtain-white', 'is-scene-withdrawing-hiw',
                    'is-closing-converging'
                );
                layerEl.style.opacity = '';
                layerEl.style.transform = '';
            });
            if (loginPortal) {
                loginPortal.classList.remove('is-portal-transitioning', 'is-login-entering');
                loginPortal.style.opacity = '';
                loginPortal.style.transform = '';
            }
            if (loginBackdrop) {
                loginBackdrop.style.clipPath = '';
                loginBackdrop.style.webkitClipPath = '';
                loginBackdrop.style.opacity = '';
            }
            [loginBrand, loginCardWrap, loginHeader, loginIdentityGroup, loginPasswordGroup, loginSubmitBtn, loginHelperText].forEach(function (el) {
                if (el) {
                    el.style.opacity = '';
                    el.style.transform = '';
                }
            });
            if (hiwShell) {
                hiwShell.classList.remove('is-hiw-subtransitioning');
            }
            [hiwHeadingWrap, hiwLostTab, hiwFoundTab, hiwChoiceWrap, hiwShowcase, hiwPanelInner].forEach(function (el) {
                if (el) {
                    el.classList.remove('is-hiw-raf-animating');
                    el.style.opacity = '';
                    el.style.transform = '';
                    el.style.transformOrigin = '';
                }
            });
            [heroEyebrowWrap, heroTitleWrap, heroLine1Wrap, heroLine2Wrap, heroSubWrap, heroActionsWrap].forEach(function (el) {
                if (el) {
                    el.style.opacity = '';
                    el.style.transform = '';
                }
            });
            heroBtns.forEach(function (btn) {
                btn.classList.remove('is-hero-btn-animating');
            });
            heroScrubObjects.forEach(function (obj) {
                obj.style.opacity = '';
                obj.style.transform = '';
            });
            if (recentHeadingWrap) {
                recentHeadingWrap.style.opacity = '';
                recentHeadingWrap.style.transform = '';
            }
            if (recentControlsBar) {
                recentControlsBar.style.opacity = '';
                recentControlsBar.style.transform = '';
            }
            if (recentGatedWrap) {
                recentGatedWrap.style.opacity = '';
                recentGatedWrap.style.transform = '';
            }
            var allRecentSlides = Array.prototype.slice.call(sceneLayers.recent.querySelectorAll('.cf-recent-slide'));
            allRecentSlides.forEach(function (slide) {
                slide.style.opacity = '';
                slide.style.transform = '';
            });
            [ctaEyebrow, ctaTitle, ctaDesc, ctaPrimary, ctaSecondary, ctaInner, ctaActions].forEach(function (el) {
                if (el) {
                    el.style.opacity = '';
                    el.style.transform = '';
                }
            });
            [closingEyebrow, closingTitle, closingDesc, closingActions, closingFooterWrap, closingCopy].forEach(function (el) {
                if (el) {
                    el.style.opacity = '';
                    el.style.transform = '';
                }
            });
            closingObjs.forEach(function (obj) {
                obj.style.opacity = '';
                obj.style.transform = '';
            });
        }

        function setLayerA11yAndVisibility(activeSceneKey, underlyingSceneKey) {
            Object.keys(sceneLayers).forEach(function (sceneKey) {
                var layerEl = sceneLayers[sceneKey];
                var isMatch = sceneKey === activeSceneKey;
                var wasMatch = layerEl.classList.contains('is-scene-active');
                layerEl.classList.toggle('is-scene-active', isMatch);
                layerEl.setAttribute('aria-hidden', sceneKey === activeSceneKey ? 'false' : 'true');
                if (isMatch && !wasMatch && activeSceneKey !== 'login') {
                    layerEl.scrollTop = 0;
                }
            });

            if (loginPortal) {
                var isLogin = activeSceneKey === 'login';
                loginPortal.classList.toggle('is-portal-visible', isLogin);
                loginPortal.classList.toggle('is-portal-interactive', isLogin);
                loginPortal.setAttribute('aria-hidden', isLogin ? 'false' : 'true');
                if (isLogin) {
                    loginPortal.scrollTop = 0;
                    if (loginBackLabel) {
                        loginBackLabel.textContent = (state.loginEntryMode === 'natural' || state.loginOriginBeat === 'closing')
                            ? 'Return to Experience'
                            : 'Return to Section';
                    }
                    if (opticalSupported) {
                        applyLoginGlassMap();
                    }
                }
            }
        }

        function applyBeatInternalState(beat) {
            if (!beat) {
                return;
            }
            state.currentBeat = beat.id;
            state.activeBeat = beat.id;
            state.activeScene = beat.scene;
            root.setAttribute('data-active-beat', beat.id);

            if (beat.scene === 'hiw' && hiwShell) {
                state.hiwPhase = beat.hiwPhase || 'intro';
                hiwShell.setAttribute('data-stage-phase', state.hiwPhase);
            } else {
                state.hiwPhase = 'intro';
            }

            if (beat.journey) {
                state.currentJourney = beat.journey;
            }
            if (typeof beat.stageIndex === 'number') {
                state.currentJourneyStage = beat.stageIndex + 1;
            } else if (beat.hiwPhase === 'choice' || beat.hiwPhase === 'intro') {
                state.currentJourneyStage = 1;
            } else if (beat.scene === 'cta') {
                state.currentJourneyStage = 5;
            }

            if (hiwApi && (beat.journey || typeof beat.stageIndex === 'number')) {
                state.syncingFromBeat = true;
                if (beat.journey) {
                    hiwApi.selectJourney(beat.journey);
                }
                if (typeof beat.stageIndex === 'number') {
                    hiwApi.syncScrollStage(beat.stageIndex, null);
                }
                state.syncingFromBeat = false;
            }

            if (navApi) {
                navApi.setActiveSection(beat.navSection);
                navApi.setOverDark(Boolean(beat.overDark));
                navApi.setSceneHidden(Boolean(beat.hideNav));
            }
        }

        function clearActiveModeOverrides() {
            cancelActiveTransition();
            clearTransitionInlineStyles();
            root.removeAttribute('data-active-beat');
            Object.keys(sceneLayers).forEach(function (sceneKey) {
                var layerEl = sceneLayers[sceneKey];
                layerEl.classList.remove('is-scene-active');
                layerEl.removeAttribute('aria-hidden');
                layerEl.scrollTop = 0;
            });
            if (hiwShell) {
                hiwShell.setAttribute('data-stage-phase', 'stages');
            }
            if (loginPortal) {
                loginPortal.classList.remove('is-portal-visible', 'is-portal-interactive', 'is-portal-transitioning');
                loginPortal.removeAttribute('aria-hidden');
            }
            if (navApi) {
                navApi.setSceneHidden(false);
                navApi.setOverDark(false);
            }
        }

        function cancelActiveTransition() {
            if (state.rafId !== null) {
                window.cancelAnimationFrame(state.rafId);
                state.rafId = null;
            }
            if (state.transitionTimer !== null) {
                window.clearTimeout(state.transitionTimer);
                state.transitionTimer = null;
            }
            state.transitionToken += 1;
            state.isTransitioning = false;
            state.targetBeat = null;
        }

        function renderBeat(id) {
            var resolved = resolveBeatId(id);
            if (!resolved) {
                return false;
            }
            var beat = BEATS[resolved];
            if (!state.active) {
                state.currentBeat = beat.id;
                state.activeBeat = beat.id;
                state.activeScene = beat.scene;
                root.setAttribute('data-active-beat', beat.id);
                return true;
            }

            cancelActiveTransition();
            clearTransitionInlineStyles();

            var underlyingKey = 'closing';
            if (beat.id === 'login' && state.loginEntryMode === 'shortcut' && state.loginOriginBeat && BEATS[state.loginOriginBeat]) {
                var originScene = BEATS[state.loginOriginBeat].scene;
                if (originScene && originScene !== 'login') {
                    underlyingKey = originScene;
                }
            }

            applyBeatInternalState(beat);
            setLayerA11yAndVisibility(beat.scene, underlyingKey);
            clampDocumentScroll();

            if (beat.scene === 'recent') {
                var carouselOnRender = recentApi || (window.CampusFindLanding && window.CampusFindLanding.recentCarousel);
                if (carouselOnRender && typeof carouselOnRender.resume === 'function') {
                    carouselOnRender.resume();
                }
            } else if (beat.scene === 'hero' || beat.scene === 'hiw') {
                var carouselOnRenderHero = recentApi || (window.CampusFindLanding && window.CampusFindLanding.recentCarousel);
                if (carouselOnRenderHero && typeof carouselOnRenderHero.pause === 'function') {
                    carouselOnRenderHero.pause();
                }
                if (beat.scene === 'hero') {
                    var heroParallaxOnRender = heroParallaxApi || (window.CampusFindLanding && window.CampusFindLanding.heroParallax);
                    if (heroParallaxOnRender && typeof heroParallaxOnRender.resetSoftly === 'function') {
                        heroParallaxOnRender.resetSoftly();
                    }
                }
            }

            if (hiwApi && beat.scene === 'hiw' && typeof hiwApi.refreshIndicator === 'function') {
                window.requestAnimationFrame(function () {
                    hiwApi.refreshIndicator();
                });
            }
            if (hiwApi && beat.scene === 'hiw' && typeof beat.stageIndex === 'number' && typeof hiwApi.setCoverflowProgress === 'function') {
                hiwApi.setCoverflowProgress(beat.stageIndex);
            }

            return true;
        }

        function finishLoginFocusIfNeeded(beatId) {
            if (beatId === 'login') {
                window.requestAnimationFrame(function () {
                    var idField = document.getElementById('input-identity');
                    if (idField) {
                        idField.focus({ preventScroll: true });
                    } else if (loginBackBtn) {
                        loginBackBtn.focus({ preventScroll: true });
                    }
                });
            }
        }

        function safeRect(el) {
            if (!el || typeof el.getBoundingClientRect !== 'function') {
                return null;
            }
            var r = el.getBoundingClientRect();
            if (!r || !isFinite(r.width) || !isFinite(r.height) || r.width <= 0 || r.height <= 0) {
                return null;
            }
            return { left: r.left, top: r.top, width: r.width, height: r.height };
        }

        function captureHiwRects() {
            return {
                heading: safeRect(hiwHeadingWrap),
                lost: safeRect(hiwLostTab),
                found: safeRect(hiwFoundTab),
            };
        }

        function applyFlipFrame(el, firstRect, lastRect, e) {
            if (!el || !firstRect || !lastRect) {
                return;
            }
            var dx = (firstRect.left + firstRect.width / 2) - (lastRect.left + lastRect.width / 2);
            var dy = (firstRect.top + firstRect.height / 2) - (lastRect.top + lastRect.height / 2);
            var rawSx = firstRect.width / lastRect.width;
            var rawSy = firstRect.height / lastRect.height;
            if (!isFinite(rawSx) || !isFinite(rawSy)) {
                return;
            }
            if (Math.abs(dx) + Math.abs(dy) + Math.abs(1 - rawSx) + Math.abs(1 - rawSy) < 0.01) {
                return;
            }
            var inv = 1 - Math.max(0, Math.min(1, e));
            var curDx = dx * inv;
            var curDy = dy * inv;
            var uniformScale = Math.max(0.68, Math.min(1.65, Math.sqrt(Math.max(0.1, rawSx * rawSy))));
            var curScale = 1 + (uniformScale - 1) * inv;
            el.style.transformOrigin = 'center center';
            el.style.transform = 'translate3d(' + curDx.toFixed(2) + 'px, ' + curDy.toFixed(2) + 'px, 0) scale(' + curScale.toFixed(4) + ')';
        }

        function startTransition(resolvedTarget, options) {
            var opts = options || {};
            var toBeat = BEATS[resolvedTarget];
            var fromBeat = BEATS[state.currentBeat] || BEATS.hero;
            if (!toBeat) {
                return false;
            }

            if (toBeat.id === 'login' && fromBeat.id !== 'login') {
                state.loginEntryMode = opts.entryMode === 'natural' ? 'natural' : 'shortcut';
                state.loginOriginBeat = fromBeat.id;
                state.returnBeat = fromBeat.id;
                if (loginBackLabel) {
                    loginBackLabel.textContent = (state.loginEntryMode === 'natural' || fromBeat.id === 'closing')
                        ? 'Return to Experience'
                        : 'Return to Section';
                }
            }

            if (opts.immediate || prefersReducedMotion()) {
                var rendered = renderBeat(toBeat.id);
                if (rendered && toBeat.id !== 'login') {
                    state.loginEntryMode = 'natural';
                }
                if (rendered) {
                    finishLoginFocusIfNeeded(toBeat.id);
                }
                return rendered;
            }

            cancelActiveTransition();
            clearTransitionInlineStyles();

            var dir = typeof opts.direction === 'number'
                ? (opts.direction >= 0 ? 1 : -1)
                : computeTransitionDirection(fromBeat, toBeat);

            state.isTransitioning = true;
            state.targetBeat = toBeat.id;
            var token = state.transitionToken;
            var duration = TRANSITION_DURATION_MS;
            var startTime = null;
            var swappedMidpoint = false;

            var fromSceneKey = fromBeat.scene;
            var toSceneKey = toBeat.scene;
            var isFromLogin = fromSceneKey === 'login';
            var isToLogin = toSceneKey === 'login';
            var isSameHiwScene = fromSceneKey === 'hiw' && toSceneKey === 'hiw';
            var isSameHiwStages = isSameHiwScene && fromBeat.hiwPhase === 'stages' && toBeat.hiwPhase === 'stages';
            var fromStageIdx = (fromBeat && typeof fromBeat.stageIndex === 'number') ? fromBeat.stageIndex : 0;
            var toStageIdx = (toBeat && typeof toBeat.stageIndex === 'number') ? toBeat.stageIndex : 0;
            var isHeroRecentPair = (fromSceneKey === 'hero' && toSceneKey === 'recent') ||
                                   (fromSceneKey === 'recent' && toSceneKey === 'hero');
            var isRecentHiwPair = (fromSceneKey === 'recent' && toSceneKey === 'hiw') ||
                                  (fromSceneKey === 'hiw' && toSceneKey === 'recent');
            var isHiwCtaPair = (fromSceneKey === 'hiw' && toSceneKey === 'cta') ||
                               (fromSceneKey === 'cta' && toSceneKey === 'hiw');
            var isCtaClosingPair = (fromSceneKey === 'cta' && toSceneKey === 'closing') ||
                                   (fromSceneKey === 'closing' && toSceneKey === 'cta');
            var isClosingLoginPair = (fromSceneKey === 'closing' && toSceneKey === 'login') ||
                                     (fromSceneKey === 'login' && toSceneKey === 'closing');
            var isShortcutLoginPair = (toSceneKey === 'login' && fromSceneKey !== 'closing') ||
                                      (fromSceneKey === 'login' && toSceneKey !== 'closing');
            if (isHeroRecentPair) {
                duration = 1050;
            } else if (isRecentHiwPair) {
                duration = 1050; // Batch 3: 1050ms layered curtain takeover & receding depth
            } else if (isHiwCtaPair) {
                duration = 1080; // Batch 4: 950-1200ms target duration for Stage 5 <-> CTA
            } else if (isCtaClosingPair) {
                duration = 1020; // Batch 4: 900-1150ms target duration for CTA <-> Closing
            } else if (isClosingLoginPair) {
                duration = 1120; // Batch 5: 1050-1350ms Closing convergence -> Login
            } else if (isShortcutLoginPair) {
                duration = 1020; // Batch 5: 1020ms shortcut transition to Login
            } else if (isSameHiwStages) {
                duration = 1050; // Batch 1: 950-1200ms target duration for 3D elliptical orbital carousel
            }
            var fromHiwPhase = fromBeat.hiwPhase || 'intro';
            var toHiwPhase = toBeat.hiwPhase || 'intro';
            var hiwFirstRects = null;
            var hiwLastRects = null;

            var carousel = recentApi || (window.CampusFindLanding && window.CampusFindLanding.recentCarousel);
            var heroParallax = heroParallaxApi || (window.CampusFindLanding && window.CampusFindLanding.heroParallax);

            if ((isHeroRecentPair || isRecentHiwPair) && carousel && typeof carousel.pause === 'function') {
                carousel.pause();
            }

            var underlyingLayerKey = (BEATS[state.loginOriginBeat] && BEATS[state.loginOriginBeat].scene !== 'login')
                ? BEATS[state.loginOriginBeat].scene
                : 'closing';
            var fromLayer = isFromLogin ? null : sceneLayers[fromSceneKey];
            var toLayer = isToLogin ? null : sceneLayers[toSceneKey];

            if (isClosingLoginPair) {
                applyBeatInternalState(toBeat);
                sceneLayers.closing.classList.add('is-scene-active', 'is-scene-underlay', 'is-closing-converging');
                if (loginPortal) {
                    loginPortal.classList.add('is-portal-visible', 'is-portal-transitioning', 'is-login-entering');
                    loginPortal.classList.remove('is-portal-interactive');
                    loginPortal.setAttribute('aria-hidden', 'false');
                    loginPortal.scrollTop = 0;
                }
                sceneLayers.closing.scrollTop = 0;
                if (opticalSupported) {
                    applyLoginGlassMap();
                }
                if (navApi) {
                    navApi.setActiveSection(toBeat.navSection);
                    navApi.setOverDark(Boolean(toBeat.overDark));
                    navApi.setSceneHidden(Boolean(toBeat.hideNav));
                }
            } else if (isShortcutLoginPair) {
                applyBeatInternalState(toBeat);
                var activeUnderlyingKey = isToLogin ? fromSceneKey : toSceneKey;
                if (sceneLayers[activeUnderlyingKey]) {
                    sceneLayers[activeUnderlyingKey].classList.add('is-scene-active', 'is-scene-underlay');
                    sceneLayers[activeUnderlyingKey].scrollTop = 0;
                }
                if (loginPortal) {
                    loginPortal.classList.add('is-portal-visible', 'is-portal-transitioning', 'is-login-entering');
                    loginPortal.classList.remove('is-portal-interactive');
                    loginPortal.setAttribute('aria-hidden', 'false');
                    loginPortal.scrollTop = 0;
                }
                if (opticalSupported) {
                    applyLoginGlassMap();
                }
                if (navApi) {
                    navApi.setActiveSection(toBeat.navSection);
                    navApi.setOverDark(Boolean(toBeat.overDark));
                    navApi.setSceneHidden(Boolean(toBeat.hideNav));
                }
            } else if (isSameHiwScene) {
                if (isSameHiwStages) {
                    if (hiwPanelInner) {
                        hiwPanelInner.classList.add('is-hiw-raf-animating');
                    }
                    if (hiwApi && typeof hiwApi.setCoverflowProgress === 'function') {
                        hiwApi.setCoverflowProgress(fromStageIdx);
                    }
                } else {
                    if (hiwShell) {
                        hiwShell.classList.add('is-hiw-subtransitioning');
                    }
                    if (fromHiwPhase === 'intro' && toHiwPhase === 'choice') {
                        hiwFirstRects = captureHiwRects();
                        applyBeatInternalState(toBeat);
                        hiwLastRects = captureHiwRects();
                        swappedMidpoint = true;
                        if (hiwLostTab) {
                            hiwLostTab.style.opacity = '0';
                        }
                        if (hiwFoundTab) {
                            hiwFoundTab.style.opacity = '0';
                        }
                    } else if (toHiwPhase === 'stages') {
                        hiwFirstRects = captureHiwRects();
                        applyBeatInternalState(toBeat);
                        hiwLastRects = captureHiwRects();
                        swappedMidpoint = true;
                        if (hiwShowcase) {
                            hiwShowcase.style.opacity = '0';
                        }
                    }
                }
                if (navApi) {
                    navApi.setActiveSection(toBeat.navSection);
                    navApi.setOverDark(Boolean(toBeat.overDark));
                    navApi.setSceneHidden(Boolean(toBeat.hideNav));
                }
            } else if (isHeroRecentPair) {
                applyBeatInternalState(toBeat);
                sceneLayers.hero.classList.add('is-scene-active', 'is-scene-curtain-fg', 'is-scene-underlay');
                sceneLayers.recent.classList.add('is-scene-active', 'is-scene-curtain-bg', 'is-scene-cover');
                sceneLayers.hero.scrollTop = 0;
                sceneLayers.recent.scrollTop = 0;
                heroBtns.forEach(function (btn) {
                    btn.classList.add('is-hero-btn-animating');
                });
                if (navApi) {
                    navApi.setActiveSection(toBeat.navSection);
                    navApi.setOverDark(Boolean(toBeat.overDark));
                    navApi.setSceneHidden(Boolean(toBeat.hideNav));
                }
            } else if (isRecentHiwPair) {
                applyBeatInternalState(toBeat);
                sceneLayers.recent.classList.add('is-scene-active', 'is-scene-receding-bg');
                sceneLayers.hiw.classList.add('is-scene-active', 'is-scene-curtain-navy');
                sceneLayers.recent.scrollTop = 0;
                sceneLayers.hiw.scrollTop = 0;
                if (navApi) {
                    navApi.setActiveSection(toBeat.navSection);
                    navApi.setOverDark(Boolean(toBeat.overDark));
                    navApi.setSceneHidden(Boolean(toBeat.hideNav));
                }
            } else if (isHiwCtaPair) {
                applyBeatInternalState(toBeat);
                sceneLayers.hiw.classList.add('is-scene-active', 'is-scene-withdrawing-hiw');
                sceneLayers.cta.classList.add('is-scene-active', 'is-scene-curtain-white');
                sceneLayers.hiw.scrollTop = 0;
                sceneLayers.cta.scrollTop = 0;
                if (navApi) {
                    navApi.setActiveSection(toBeat.navSection);
                    navApi.setOverDark(Boolean(toBeat.overDark));
                    navApi.setSceneHidden(Boolean(toBeat.hideNav));
                }
            } else if (isCtaClosingPair) {
                applyBeatInternalState(toBeat);
                sceneLayers.cta.classList.add('is-scene-active', 'is-scene-underlay');
                sceneLayers.closing.classList.add('is-scene-active', 'is-scene-cover');
                sceneLayers.cta.scrollTop = 0;
                sceneLayers.closing.scrollTop = 0;
                if (navApi) {
                    navApi.setActiveSection(toBeat.navSection);
                    navApi.setOverDark(Boolean(toBeat.overDark));
                    navApi.setSceneHidden(Boolean(toBeat.hideNav));
                }
            } else if (fromLayer && toLayer && fromLayer !== toLayer) {
                applyBeatInternalState(toBeat);
                fromLayer.classList.add('is-scene-active', 'is-scene-leaving');
                toLayer.classList.add('is-scene-active', 'is-scene-entering');
                toLayer.scrollTop = 0;
                if (hiwApi && toSceneKey === 'hiw' && typeof hiwApi.refreshIndicator === 'function') {
                    hiwApi.refreshIndicator();
                }
            }

            function commitFrame(rawProgress) {
                var p = Math.max(0, Math.min(1, rawProgress));
                var e = easeOutCubic(p);

                if (isClosingLoginPair) {
                    var progress = (toSceneKey === 'login') ? p : (1 - p);
                    var eScene = easeInOutCubic(progress);

                    // =========================================================
                    // 1. CLOSING SCENE CONVERGENCE CHOREOGRAPHY (p in [0.00, 0.65])
                    // All elements converge toward single focal point (52%, 48%)
                    // =========================================================

                    // 1. Closing Headline: moves toward convergence point immediately
                    var pCloseHead = easeInOutCubic(Math.min(1, progress / 0.55));
                    if (closingTitle) {
                        var hConvX = (180 * pCloseHead).toFixed(2);
                        var hConvY = (50 * pCloseHead).toFixed(2);
                        var hConvScale = (1 - 0.45 * pCloseHead).toFixed(4);
                        var hConvOp = (1 - pCloseHead).toFixed(4);
                        closingTitle.style.transform = 'translate3d(' + hConvX + 'px, ' + hConvY + 'px, 0) scale(' + hConvScale + ')';
                        closingTitle.style.opacity = hConvOp;
                    }

                    // 2. Closing Eyebrow: follows headline
                    var pCloseEye = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.04) / 0.48)));
                    if (closingEyebrow) {
                        var eyeConvX = (160 * pCloseEye).toFixed(2);
                        var eyeConvY = (70 * pCloseEye).toFixed(2);
                        var eyeConvScale = (1 - 0.45 * pCloseEye).toFixed(4);
                        var eyeConvOp = (1 - pCloseEye).toFixed(4);
                        closingEyebrow.style.transform = 'translate3d(' + eyeConvX + 'px, ' + eyeConvY + 'px, 0) scale(' + eyeConvScale + ')';
                        closingEyebrow.style.opacity = eyeConvOp;
                    }

                    // 3. Supporting Copy: follows shortly after
                    var pCloseDesc = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.06) / 0.52)));
                    if (closingDesc) {
                        var descConvX = (170 * pCloseDesc).toFixed(2);
                        var descConvY = (40 * pCloseDesc).toFixed(2);
                        var descConvScale = (1 - 0.45 * pCloseDesc).toFixed(4);
                        var descConvOp = (1 - pCloseDesc).toFixed(4);
                        closingDesc.style.transform = 'translate3d(' + descConvX + 'px, ' + descConvY + 'px, 0) scale(' + descConvScale + ')';
                        closingDesc.style.opacity = descConvOp;
                    }

                    // 4. CTA Actions: exits in restrained stagger
                    var pCloseAct = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.10) / 0.46)));
                    if (closingActions) {
                        var actConvX = (140 * pCloseAct).toFixed(2);
                        var actConvY = (15 * pCloseAct).toFixed(2);
                        var actConvScale = (1 - 0.45 * pCloseAct).toFixed(4);
                        var actConvOp = (1 - pCloseAct).toFixed(4);
                        closingActions.style.transform = 'translate3d(' + actConvX + 'px, ' + actConvY + 'px, 0) scale(' + actConvScale + ')';
                        closingActions.style.opacity = actConvOp;
                    }

                    // 5. 3D Floating Visual Objects: distinct diagonal/curved convergence trajectories toward center
                    var objConfigsConv = [
                        { dx: -190, dy: 45,  scale: 0.42, start: 0.08, span: 0.52 }, // notebook
                        { dx: -170, dy: 65,  scale: 0.42, start: 0.12, span: 0.50 }, // wallet
                        { dx: -150, dy: 25,  scale: 0.40, start: 0.15, span: 0.48 }, // keys
                        { dx: -210, dy: -15, scale: 0.40, start: 0.10, span: 0.50 }, // tumbler
                        { dx: -180, dy: 85,  scale: 0.40, start: 0.14, span: 0.48 }, // earbuds
                    ];
                    closingObjs.forEach(function (objEl, oIdx) {
                        var cfg = objConfigsConv[oIdx] || { dx: -160, dy: 30, scale: 0.40, start: 0.10, span: 0.50 };
                        var pObj = easeInOutCubic(Math.min(1, Math.max(0, (progress - cfg.start) / cfg.span)));
                        var curDx = (cfg.dx * pObj).toFixed(2);
                        var curDy = (cfg.dy * pObj).toFixed(2);
                        var curScale = (1 - (1 - cfg.scale) * pObj).toFixed(4);
                        var curOp = (1 - pObj).toFixed(4);
                        objEl.style.transform = 'translate3d(' + curDx + 'px, ' + curDy + 'px, 0) scale(' + curScale + ')';
                        objEl.style.opacity = curOp;
                    });

                    // 6. Footer: Brand signature and tagline follow upward toward center
                    var pCloseFoot = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.14) / 0.50)));
                    if (closingFooterWrap) {
                        var footConvY = (-75 * pCloseFoot).toFixed(2);
                        var footConvScale = (1 - 0.18 * pCloseFoot).toFixed(4);
                        var footConvOp = (1 - pCloseFoot).toFixed(4);
                        closingFooterWrap.style.transform = 'translate3d(0, ' + footConvY + 'px, 0) scale(' + footConvScale + ')';
                        closingFooterWrap.style.opacity = footConvOp;
                    }

                    // Closing layer container fade
                    var pCloseLayerFade = Math.min(1, Math.max(0, (progress - 0.45) / 0.23));
                    sceneLayers.closing.style.opacity = (1 - pCloseLayerFade).toFixed(4);
                    sceneLayers.closing.style.transform = 'scale(' + (1 - 0.04 * eScene).toFixed(4) + ')';

                    // =========================================================
                    // 2. NAVY ENVIRONMENT TAKEOVER & RADIAL EXPANSION (p in [0.26, 0.78])
                    // =========================================================
                    var pNavy = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.26) / 0.48)));
                    var navyRadius = (pNavy * 150).toFixed(2);
                    if (loginBackdrop) {
                        loginBackdrop.style.clipPath = 'circle(' + navyRadius + '% at 52% 48%)';
                        loginBackdrop.style.webkitClipPath = 'circle(' + navyRadius + '% at 52% 48%)';
                        loginBackdrop.style.opacity = Math.min(1, (progress / 0.38)).toFixed(4);
                    }
                    if (loginPortal) {
                        loginPortal.style.opacity = Math.min(1, (progress / 0.35)).toFixed(4);
                    }

                    // =========================================================
                    // 3. LOGIN COMPOSITION STAGGERED ENTRANCE (p in [0.44, 1.00])
                    // =========================================================

                    // Left Column: Brand Identity enters from left (-50px -> 0px)
                    var pBrand = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.44) / 0.52)));
                    if (loginBrand) {
                        var brandX = ((1 - pBrand) * -50).toFixed(2);
                        var brandScale = (0.96 + 0.04 * pBrand).toFixed(4);
                        loginBrand.style.transform = 'translate3d(' + brandX + 'px, 0, 0) scale(' + brandScale + ')';
                        loginBrand.style.opacity = pBrand.toFixed(4);
                    }

                    // Right Column: Liquid Glass Panel enters from right (+65px -> 0px, scale 0.95 -> 1.0)
                    var pCard = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.48) / 0.50)));
                    if (loginCardWrap) {
                        var cardX = ((1 - pCard) * 65).toFixed(2);
                        var cardScale = (0.95 + 0.05 * pCard).toFixed(4);
                        loginCardWrap.style.transform = 'translate3d(' + cardX + 'px, 0, 0) scale(' + cardScale + ')';
                        loginCardWrap.style.opacity = pCard.toFixed(4);
                    }

                    // Form Header: Stagger reveal (Title & Subtitle)
                    var pHeader = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.58) / 0.40)));
                    if (loginHeader) {
                        loginHeader.style.transform = 'translate3d(0, ' + ((1 - pHeader) * 14).toFixed(2) + 'px, 0)';
                        loginHeader.style.opacity = pHeader.toFixed(4);
                    }

                    // Identity input group reveal
                    var pId = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.65) / 0.34)));
                    if (loginIdentityGroup) {
                        loginIdentityGroup.style.transform = 'translate3d(0, ' + ((1 - pId) * 14).toFixed(2) + 'px, 0)';
                        loginIdentityGroup.style.opacity = pId.toFixed(4);
                    }

                    // Password input group reveal
                    var pPwd = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.72) / 0.28)));
                    if (loginPasswordGroup) {
                        loginPasswordGroup.style.transform = 'translate3d(0, ' + ((1 - pPwd) * 14).toFixed(2) + 'px, 0)';
                        loginPasswordGroup.style.opacity = pPwd.toFixed(4);
                    }

                    // Submit Button reveal with prominent scale settle
                    var pSubmit = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.78) / 0.22)));
                    if (loginSubmitBtn) {
                        var subScale = (0.96 + 0.04 * pSubmit).toFixed(4);
                        loginSubmitBtn.style.transform = 'translate3d(0, ' + ((1 - pSubmit) * 14).toFixed(2) + 'px, 0) scale(' + subScale + ')';
                        loginSubmitBtn.style.opacity = pSubmit.toFixed(4);
                    }

                    // Helper / Policy text reveal
                    var pHelper = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.82) / 0.18)));
                    if (loginHelperText) {
                        loginHelperText.style.opacity = pHelper.toFixed(4);
                    }
                } else if (isShortcutLoginPair) {
                    var progress = (toSceneKey === 'login') ? p : (1 - p);
                    var eScene = easeInOutCubic(progress);

                    // Originating scene layer recedes into depth
                    var originLayer = (toSceneKey === 'login') ? fromLayer : toLayer;
                    if (originLayer) {
                        var origScale = (1 - 0.05 * eScene).toFixed(4);
                        var origOp = (1 - easeInOutCubic(Math.min(1, progress / 0.60))).toFixed(4);
                        originLayer.style.transform = 'scale(' + origScale + ')';
                        originLayer.style.opacity = origOp;
                    }

                    // Login portal fades & expands in
                    if (loginPortal) {
                        loginPortal.style.opacity = easeInOutCubic(Math.min(1, progress / 0.45)).toFixed(4);
                    }
                    if (loginBackdrop) {
                        loginBackdrop.style.opacity = easeInOutCubic(Math.min(1, progress / 0.40)).toFixed(4);
                    }

                    // Brand Identity enters from left (-45px -> 0px)
                    var pBrand = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.38) / 0.58)));
                    if (loginBrand) {
                        var brandX = ((1 - pBrand) * -45).toFixed(2);
                        var brandScale = (0.96 + 0.04 * pBrand).toFixed(4);
                        loginBrand.style.transform = 'translate3d(' + brandX + 'px, 0, 0) scale(' + brandScale + ')';
                        loginBrand.style.opacity = pBrand.toFixed(4);
                    }

                    // Liquid Glass Card enters from right (+55px -> 0px)
                    var pCard = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.44) / 0.54)));
                    if (loginCardWrap) {
                        var cardX = ((1 - pCard) * 55).toFixed(2);
                        var cardScale = (0.95 + 0.05 * pCard).toFixed(4);
                        loginCardWrap.style.transform = 'translate3d(' + cardX + 'px, 0, 0) scale(' + cardScale + ')';
                        loginCardWrap.style.opacity = pCard.toFixed(4);
                    }

                    // Form Header reveal
                    var pHeader = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.52) / 0.44)));
                    if (loginHeader) {
                        loginHeader.style.transform = 'translate3d(0, ' + ((1 - pHeader) * 12).toFixed(2) + 'px, 0)';
                        loginHeader.style.opacity = pHeader.toFixed(4);
                    }

                    // Form Fields
                    var pId = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.58) / 0.38)));
                    if (loginIdentityGroup) {
                        loginIdentityGroup.style.transform = 'translate3d(0, ' + ((1 - pId) * 12).toFixed(2) + 'px, 0)';
                        loginIdentityGroup.style.opacity = pId.toFixed(4);
                    }
                    var pPwd = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.65) / 0.32)));
                    if (loginPasswordGroup) {
                        loginPasswordGroup.style.transform = 'translate3d(0, ' + ((1 - pPwd) * 12).toFixed(2) + 'px, 0)';
                        loginPasswordGroup.style.opacity = pPwd.toFixed(4);
                    }
                    var pSubmit = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.72) / 0.26)));
                    if (loginSubmitBtn) {
                        loginSubmitBtn.style.transform = 'translate3d(0, ' + ((1 - pSubmit) * 12).toFixed(2) + 'px, 0)';
                        loginSubmitBtn.style.opacity = pSubmit.toFixed(4);
                    }
                    var pHelper = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.78) / 0.20)));
                    if (loginHelperText) {
                        loginHelperText.style.opacity = pHelper.toFixed(4);
                    }
                } else if (isSameHiwStages) {
                    var pStage = Math.max(0, Math.min(1, rawProgress));
                    var eStage = easeInOutCubic(pStage);
                    var vStage = fromStageIdx + (toStageIdx - fromStageIdx) * eStage;
                    if (hiwApi && typeof hiwApi.setCoverflowProgress === 'function') {
                        hiwApi.setCoverflowProgress(vStage);
                    }
                    if (hiwProgressFill) {
                        var isMobileVp = (window.innerWidth || (document.documentElement && document.documentElement.clientWidth) || 1200) < 768;
                        var maxHiwIdx = 4;
                        var pctFill = isMobileVp ? ((vStage + 1) / 5) * 100 : (vStage / maxHiwIdx) * 100;
                        hiwProgressFill.style.width = Math.max(0, Math.min(100, pctFill)).toFixed(1) + '%';
                    }
                    if (!swappedMidpoint && pStage >= 0.48) {
                        swappedMidpoint = true;
                        applyBeatInternalState(toBeat);
                        if (hiwApi && typeof hiwApi.refreshIndicator === 'function') {
                            hiwApi.refreshIndicator();
                        }
                    }
                } else if (isSameHiwScene) {
                    var isMobileViewport = (window.innerWidth || 1200) < 768;
                    if (fromHiwPhase === 'intro' && toHiwPhase === 'choice') {
                        if (hiwFirstRects && hiwLastRects) {
                            applyFlipFrame(hiwHeadingWrap, hiwFirstRects.heading, hiwLastRects.heading, easeInOutCubic(p));
                        }
                        // "I Lost Something" enters individually on distinct left-biased trajectory
                        if (hiwLostTab) {
                            var pLost = Math.max(0, Math.min(1, (p - 0.06) / 0.68));
                            var eLost = easeOutCubic(pLost);
                            var lostX = (-(1 - eLost) * 20).toFixed(2);
                            var lostY = ((1 - eLost) * 26).toFixed(2);
                            var lostScale = (0.95 + 0.05 * eLost).toFixed(4);
                            hiwLostTab.style.opacity = eLost.toFixed(4);
                            hiwLostTab.style.transform = 'translate3d(' + lostX + 'px, ' + lostY + 'px, 0) scale(' + lostScale + ')';
                        }
                        // "I Found Something" follows with short stagger on distinct right-biased trajectory
                        if (hiwFoundTab) {
                            var pFound = Math.max(0, Math.min(1, (p - 0.22) / 0.68));
                            var eFound = easeOutCubic(pFound);
                            var foundX = ((1 - eFound) * 20).toFixed(2);
                            var foundY = ((1 - eFound) * 26).toFixed(2);
                            var foundScale = (0.95 + 0.05 * eFound).toFixed(4);
                            hiwFoundTab.style.opacity = eFound.toFixed(4);
                            hiwFoundTab.style.transform = 'translate3d(' + foundX + 'px, ' + foundY + 'px, 0) scale(' + foundScale + ')';
                        }
                    } else if (fromHiwPhase === 'choice' && toHiwPhase === 'intro') {
                        if (p < 0.44) {
                            // Reverse: "I Found Something" exits first, "I Lost Something" follows
                            var pOutF = Math.max(0, Math.min(1, p / 0.32));
                            var pOutL = Math.max(0, Math.min(1, (p - 0.10) / 0.32));
                            if (hiwFoundTab) {
                                var eOutF = easeOutCubic(pOutF);
                                hiwFoundTab.style.opacity = (1 - eOutF).toFixed(4);
                                hiwFoundTab.style.transform = 'translate3d(' + (16 * eOutF).toFixed(2) + 'px, ' + (18 * eOutF).toFixed(2) + 'px, 0)';
                            }
                            if (hiwLostTab) {
                                var eOutL = easeOutCubic(pOutL);
                                hiwLostTab.style.opacity = (1 - eOutL).toFixed(4);
                                hiwLostTab.style.transform = 'translate3d(' + (-16 * eOutL).toFixed(2) + 'px, ' + (18 * eOutL).toFixed(2) + 'px, 0)';
                            }
                        } else {
                            if (!swappedMidpoint) {
                                swappedMidpoint = true;
                                if (hiwLostTab) { hiwLostTab.style.opacity = ''; hiwLostTab.style.transform = ''; }
                                if (hiwFoundTab) { hiwFoundTab.style.opacity = ''; hiwFoundTab.style.transform = ''; }
                                hiwFirstRects = captureHiwRects();
                                applyBeatInternalState(toBeat);
                                hiwLastRects = captureHiwRects();
                            }
                            var eBackIntro = easeOutCubic((p - 0.44) / 0.56);
                            if (hiwFirstRects && hiwLastRects) {
                                applyFlipFrame(hiwHeadingWrap, hiwFirstRects.heading, hiwLastRects.heading, eBackIntro);
                            }
                        }
                    } else if (toHiwPhase === 'stages') {
                        var eRail = easeInOutCubic(Math.min(1, p / 0.82));
                        if (hiwFirstRects && hiwLastRects) {
                            applyFlipFrame(hiwHeadingWrap, hiwFirstRects.heading, hiwLastRects.heading, eRail);
                            if (fromHiwPhase === 'choice') {
                                applyFlipFrame(hiwLostTab, hiwFirstRects.lost, hiwLastRects.lost, eRail);
                                applyFlipFrame(hiwFoundTab, hiwFirstRects.found, hiwLastRects.found, eRail);
                            } else if (hiwChoiceWrap) {
                                hiwChoiceWrap.style.opacity = easeOutCubic(Math.min(1, p / 0.55)).toFixed(4);
                            }
                        }
                        // 3D Orbital Carousel arrives along the elliptical perspective path from depth
                        if (hiwShowcase) {
                            var pGallery = Math.max(0, Math.min(1, (p - 0.16) / 0.84));
                            var eGallery = easeOutCubic(pGallery);
                            var galleryScale = (0.84 + 0.16 * eGallery).toFixed(4);
                            var galleryZ = (-(1 - eGallery) * 200).toFixed(2);
                            var galleryY = (isMobileViewport ? (1 - eGallery) * 16 : (1 - eGallery) * 22).toFixed(2);
                            hiwShowcase.style.opacity = eGallery.toFixed(4);
                            hiwShowcase.style.transform = 'translate3d(0, ' + galleryY + 'px, ' + galleryZ + 'px) scale(' + galleryScale + ')';
                        }
                    } else if (fromHiwPhase === 'stages') {
                        if (p < 0.32) {
                            // Reverse: Orbital gallery recedes deeper into perspective
                            var eHide = easeOutCubic(p / 0.32);
                            if (hiwShowcase) {
                                var hideScale = (1 - 0.16 * eHide).toFixed(4);
                                var hideZ = (-200 * eHide).toFixed(2);
                                var hideY = (isMobileViewport ? 16 * eHide : 22 * eHide).toFixed(2);
                                hiwShowcase.style.opacity = (1 - eHide).toFixed(4);
                                hiwShowcase.style.transform = 'translate3d(0, ' + hideY + 'px, ' + hideZ + 'px) scale(' + hideScale + ')';
                            }
                        } else {
                            if (!swappedMidpoint) {
                                swappedMidpoint = true;
                                if (hiwShowcase) {
                                    hiwShowcase.style.opacity = '';
                                    hiwShowcase.style.transform = '';
                                }
                                hiwFirstRects = captureHiwRects();
                                applyBeatInternalState(toBeat);
                                hiwLastRects = captureHiwRects();
                            }
                            var eExpand = easeInOutCubic((p - 0.32) / 0.68);
                            if (hiwFirstRects && hiwLastRects) {
                                applyFlipFrame(hiwHeadingWrap, hiwFirstRects.heading, hiwLastRects.heading, eExpand);
                                if (toHiwPhase === 'choice') {
                                    applyFlipFrame(hiwLostTab, hiwFirstRects.lost, hiwLastRects.lost, eExpand);
                                    applyFlipFrame(hiwFoundTab, hiwFirstRects.found, hiwLastRects.found, eExpand);
                                }
                            }
                        }
                    }
                } else if (isHeroRecentPair) {
                    var progress = (toSceneKey === 'recent') ? p : (1 - p);
                    var eCurtain = easeInOutCubic(progress);

                    // 1. Hero Foreground Curtain Panel Glides Horizontally to the Left (0% -> -100%)
                    var heroX = (-100 * eCurtain).toFixed(2);
                    sceneLayers.hero.style.transform = 'translate3d(' + heroX + '%, 0, 0)';

                    // Layered depth: Hero elements exit with differential speeds & subtle horizontal stagger
                    // Eyebrow exits first (-110px)
                    if (heroEyebrowWrap) {
                        var pEye = easeInOutCubic(Math.min(1, progress * 1.20));
                        heroEyebrowWrap.style.transform = 'translate3d(' + (-110 * pEye).toFixed(2) + 'px, 0, 0)';
                    }

                    // Line 1 leads headline movement (-95px)
                    if (heroLine1Wrap) {
                        var pLine1 = easeInOutCubic(Math.min(1, progress * 1.15));
                        heroLine1Wrap.style.transform = 'translate3d(' + (-95 * pLine1).toFixed(2) + 'px, 0, 0)';
                    }

                    // Line 2 follows with slight offset (-85px)
                    if (heroLine2Wrap) {
                        var pLine2 = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.02) * 1.15)));
                        heroLine2Wrap.style.transform = 'translate3d(' + (-85 * pLine2).toFixed(2) + 'px, 0, 0)';
                    } else if (heroTitleWrap) {
                        var pHead = easeInOutCubic(Math.min(1, progress * 1.15));
                        heroTitleWrap.style.transform = 'translate3d(' + (-90 * pHead).toFixed(2) + 'px, 0, 0)';
                    }

                    // Subtitle follows with subtle lag (-65px)
                    var pSub = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.04) * 1.15)));
                    if (heroSubWrap) {
                        heroSubWrap.style.transform = 'translate3d(' + (-65 * pSub).toFixed(2) + 'px, 0, 0)';
                    }

                    // CTA buttons (-45px)
                    var pAct = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.06) * 1.15)));
                    if (heroActionsWrap) {
                        heroActionsWrap.style.transform = 'translate3d(' + (-45 * pAct).toFixed(2) + 'px, 0, 0)';
                    }

                    // 3D Floating Objects: differential horizontal drift vectors for 5 objects
                    var objConfigs = [
                        { dx: -75, dy: -8,  scale: 0.96, start: 0.00, span: 0.85 }, // notebook
                        { dx: -95, dy: 10,  scale: 0.96, start: 0.02, span: 0.85 }, // wallet
                        { dx: 50,  dy: 8,   scale: 0.95, start: 0.04, span: 0.85 }, // keys
                        { dx: -65, dy: -12, scale: 0.95, start: 0.03, span: 0.85 }, // earbuds
                        { dx: 45,  dy: -10, scale: 0.95, start: 0.05, span: 0.85 }, // tumbler
                    ];

                    for (var oIdx = 0; oIdx < heroScrubObjects.length; oIdx++) {
                        var objEl = heroScrubObjects[oIdx];
                        var cfg = objConfigs[oIdx] || { dx: -60, dy: 0, scale: 0.96, start: 0.02, span: 0.85 };
                        var pObj = easeInOutCubic(Math.min(1, Math.max(0, (progress - cfg.start) / cfg.span)));
                        var curDx = (cfg.dx * pObj).toFixed(2);
                        var curDy = (cfg.dy * pObj).toFixed(2);
                        var curScale = (1 - (1 - cfg.scale) * pObj).toFixed(4);
                        objEl.style.transform = 'translate3d(' + curDx + 'px, ' + curDy + 'px, 0) scale(' + curScale + ')';
                    }

                    // 2. Underlying Recently Reported Scene Layer (Progressively Revealed from Right Edge)
                    // Subtle overall parallax shift on the underlying canvas (+35px -> 0px)
                    var recentShiftX = (35 * (1 - eCurtain)).toFixed(2);
                    sceneLayers.recent.style.transform = 'translate3d(' + recentShiftX + 'px, 0, 0)';

                    // Heading revealed with progressive horizontal glide (+90px -> 0px)
                    var pHeadR = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.10) / 0.90)));
                    if (recentHeadingWrap) {
                        recentHeadingWrap.style.transform = 'translate3d(' + ((1 - pHeadR) * 90).toFixed(2) + 'px, 0, 0)';
                    }

                    // Controls / Filters revealed with progressive horizontal glide (+120px -> 0px)
                    var pCtrlR = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.16) / 0.84)));
                    if (recentControlsBar) {
                        recentControlsBar.style.transform = 'translate3d(' + ((1 - pCtrlR) * 120).toFixed(2) + 'px, 0, 0)';
                    }
                    if (recentGatedWrap) {
                        recentGatedWrap.style.transform = 'translate3d(' + ((1 - pCtrlR) * 120).toFixed(2) + 'px, 0, 0)';
                    }

                    // Carousel Slides / Cards: progressive staggered horizontal entrance
                    var currentRecentSlides = Array.prototype.slice.call(sceneLayers.recent.querySelectorAll('.cf-recent-slide'));
                    for (var sIdx = 0; sIdx < currentRecentSlides.length; sIdx++) {
                        var cardStart = 0.18 + Math.min(0.28, sIdx * 0.05);
                        var pCard = easeInOutCubic(Math.min(1, Math.max(0, (progress - cardStart) / (1 - cardStart))));
                        var cardDx = ((1 - pCard) * (130 + sIdx * 25)).toFixed(2);
                        currentRecentSlides[sIdx].style.transform = 'translate3d(' + cardDx + 'px, 0, 0)';
                    }
                } else if (isRecentHiwPair) {
                    var progress = (toSceneKey === 'hiw') ? p : (1 - p);
                    var eCurtain = easeInOutCubic(progress);

                    // 1. Incoming Deep Navy (#14283C) Visual Curtain Panel Glides from Right (100% -> 0%)
                    var hiwX = ((1 - eCurtain) * 100).toFixed(2);
                    sceneLayers.hiw.style.transform = 'translate3d(' + hiwX + '%, 0, 0)';

                    // How It Works Intro Title emerges from depth into center position as curtain sweeps (p >= 0.20)
                    var pTitle = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.20) / 0.80)));
                    if (hiwHeadingWrap) {
                        var titleDx = ((1 - pTitle) * 60).toFixed(2);
                        var titleScale = (0.90 + 0.10 * pTitle).toFixed(4);
                        hiwHeadingWrap.style.opacity = pTitle.toFixed(4);
                        hiwHeadingWrap.style.transform = 'translate3d(' + titleDx + 'px, 0, 0) scale(' + titleScale + ')';
                    }

                    // 2. Underlying Recently Reported Scene Layer: Recedes into Depth
                    var recentScale = (1 - 0.05 * eCurtain).toFixed(4);
                    var recentShiftX = (-28 * eCurtain).toFixed(2);
                    sceneLayers.recent.style.transform = 'translate3d(' + recentShiftX + 'px, 0, 0) scale(' + recentScale + ')';

                    // Heading recedes backward and away (scale 1 -> 0.92, translate3d -45px, -14px, opacity 1 -> 0)
                    var pHeadExit = easeInOutCubic(Math.min(1, progress * 1.25));
                    if (recentHeadingWrap) {
                        var hDx = (-45 * pHeadExit).toFixed(2);
                        var hDy = (-14 * pHeadExit).toFixed(2);
                        var hSc = (1 - 0.08 * pHeadExit).toFixed(4);
                        var hOp = (1 - pHeadExit).toFixed(4);
                        recentHeadingWrap.style.opacity = hOp;
                        recentHeadingWrap.style.transform = 'translate3d(' + hDx + 'px, ' + hDy + 'px, 0) scale(' + hSc + ')';
                    }

                    // Filter controls follow with slight delay (scale 1 -> 0.93, translate3d -35px, -12px, opacity 1 -> 0)
                    var pCtrlExit = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.06) * 1.25)));
                    var cDx = (-35 * pCtrlExit).toFixed(2);
                    var cDy = (-12 * pCtrlExit).toFixed(2);
                    var cSc = (1 - 0.07 * pCtrlExit).toFixed(4);
                    var cOp = (1 - pCtrlExit).toFixed(4);
                    if (recentControlsBar) {
                        recentControlsBar.style.opacity = cOp;
                        recentControlsBar.style.transform = 'translate3d(' + cDx + 'px, ' + cDy + 'px, 0) scale(' + cSc + ')';
                    }
                    if (recentGatedWrap) {
                        recentGatedWrap.style.opacity = cOp;
                        recentGatedWrap.style.transform = 'translate3d(' + cDx + 'px, ' + cDy + 'px, 0) scale(' + cSc + ')';
                    }

                    // Carousel cards recede as coordinated group with subtle differential depths
                    var currentRecentSlides = Array.prototype.slice.call(sceneLayers.recent.querySelectorAll('.cf-recent-slide'));
                    for (var sIdx = 0; sIdx < currentRecentSlides.length; sIdx++) {
                        var cardExitStart = Math.min(0.18, sIdx * 0.04);
                        var pCardExit = easeInOutCubic(Math.min(1, Math.max(0, (progress - cardExitStart) / (1 - cardExitStart))));
                        var cardSc = (1 - (0.12 + sIdx * 0.015) * pCardExit).toFixed(4);
                        var cardDx = (-25 * pCardExit).toFixed(2);
                        var cardDy = (24 * pCardExit).toFixed(2);
                        var cardOp = (1 - pCardExit).toFixed(4);
                        currentRecentSlides[sIdx].style.opacity = cardOp;
                        currentRecentSlides[sIdx].style.transform = 'translate3d(' + cardDx + 'px, ' + cardDy + 'px, 0) scale(' + cardSc + ')';
                    }
                } else if (isHiwCtaPair) {
                    var progress = (toSceneKey === 'cta') ? p : (1 - p);
                    var eCurtain = easeInOutCubic(progress);

                    // 1. Incoming Soft White (#F7F8FA) Curtain Glides from Right (100% -> 0%)
                    var ctaX = ((1 - eCurtain) * 100).toFixed(2);
                    sceneLayers.cta.style.transform = 'translate3d(' + ctaX + '%, 0, 0)';

                    // 2. Outgoing How It Works Scene: 3D Orbital Withdrawal & Receding Depth
                    var hiwShiftX = (-30 * eCurtain).toFixed(2);
                    var hiwScale = (1 - 0.05 * eCurtain).toFixed(4);
                    sceneLayers.hiw.style.transform = 'translate3d(' + hiwShiftX + 'px, 0, 0) scale(' + hiwScale + ')';

                    // Active stage card moves along 3D orbital trajectory (v = 4.0 -> 4.75)
                    if (hiwApi && typeof hiwApi.setCoverflowProgress === 'function') {
                        var vWithdraw = 4 + 0.75 * eCurtain;
                        hiwApi.setCoverflowProgress(vWithdraw);
                    }

                    // Orbital gallery recedes deeper into perspective
                    if (hiwShowcase) {
                        var galleryZ = (-120 * eCurtain).toFixed(2);
                        var galleryScale = (1 - 0.08 * eCurtain).toFixed(4);
                        var galleryOpacity = (1 - 0.70 * eCurtain).toFixed(4);
                        hiwShowcase.style.transform = 'translate3d(0, 0, ' + galleryZ + 'px) scale(' + galleryScale + ')';
                        hiwShowcase.style.opacity = galleryOpacity;
                    }

                    // Navigation rail exits in coordination
                    var pRailExit = easeInOutCubic(Math.min(1, progress * 1.30));
                    var railY = (-24 * pRailExit).toFixed(2);
                    var railOp = (1 - pRailExit).toFixed(4);
                    if (hiwHeadingWrap) {
                        hiwHeadingWrap.style.transform = 'translate3d(0, ' + railY + 'px, 0)';
                        hiwHeadingWrap.style.opacity = railOp;
                    }
                    if (hiwChoiceWrap) {
                        hiwChoiceWrap.style.transform = 'translate3d(0, ' + railY + 'px, 0)';
                        hiwChoiceWrap.style.opacity = railOp;
                    }

                    // 3. Incoming CTA Content Staggered Reveal Sequence
                    // Eyebrow enters first
                    var pEye = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.16) / 0.62)));
                    if (ctaEyebrow) {
                        var eyeDx = ((1 - pEye) * 45).toFixed(2);
                        ctaEyebrow.style.opacity = pEye.toFixed(4);
                        ctaEyebrow.style.transform = 'translate3d(' + eyeDx + 'px, 0, 0)';
                    }

                    // Headline follows
                    var pTitle = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.24) / 0.64)));
                    if (ctaTitle) {
                        var titleDx = ((1 - pTitle) * 55).toFixed(2);
                        var titleScale = (0.94 + 0.06 * pTitle).toFixed(4);
                        ctaTitle.style.opacity = pTitle.toFixed(4);
                        ctaTitle.style.transform = 'translate3d(' + titleDx + 'px, 0, 0) scale(' + titleScale + ')';
                    }

                    // Supporting copy follows
                    var pDesc = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.32) / 0.60)));
                    if (ctaDesc) {
                        var descDx = ((1 - pDesc) * 35).toFixed(2);
                        ctaDesc.style.opacity = pDesc.toFixed(4);
                        ctaDesc.style.transform = 'translate3d(' + descDx + 'px, 0, 0)';
                    }

                    // Primary report action
                    var pPri = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.40) / 0.55)));
                    if (ctaPrimary) {
                        var priDy = ((1 - pPri) * 18).toFixed(2);
                        var priScale = (0.95 + 0.05 * pPri).toFixed(4);
                        ctaPrimary.style.opacity = pPri.toFixed(4);
                        ctaPrimary.style.transform = 'translate3d(0, ' + priDy + 'px, 0) scale(' + priScale + ')';
                    }

                    // Secondary journey action
                    var pSec = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.46) / 0.50)));
                    if (ctaSecondary) {
                        var secDy = ((1 - pSec) * 18).toFixed(2);
                        var secScale = (0.95 + 0.05 * pSec).toFixed(4);
                        ctaSecondary.style.opacity = pSec.toFixed(4);
                        ctaSecondary.style.transform = 'translate3d(0, ' + secDy + 'px, 0) scale(' + secScale + ')';
                    }
                } else if (isCtaClosingPair) {
                    var progress = (toSceneKey === 'closing') ? p : (1 - p);
                    var eScene = easeInOutCubic(progress);

                    // 1. Outgoing CTA Scene: Actions exit with brief stagger, headline follows
                    var pActExit = easeInOutCubic(Math.min(1, progress / 0.45));
                    var actDy = (18 * pActExit).toFixed(2);
                    var actOp = (1 - pActExit).toFixed(4);
                    if (ctaPrimary) {
                        ctaPrimary.style.transform = 'translate3d(0, ' + actDy + 'px, 0)';
                        ctaPrimary.style.opacity = actOp;
                    }
                    var pSecExit = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.04) / 0.44)));
                    if (ctaSecondary) {
                        ctaSecondary.style.transform = 'translate3d(0, ' + (18 * pSecExit).toFixed(2) + 'px, 0)';
                        ctaSecondary.style.opacity = (1 - pSecExit).toFixed(4);
                    }

                    var pHeadExit = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.08) / 0.50)));
                    if (ctaTitle) {
                        ctaTitle.style.transform = 'translate3d(0, ' + (-20 * pHeadExit).toFixed(2) + 'px, 0) scale(' + (1 - 0.03 * pHeadExit).toFixed(4) + ')';
                        ctaTitle.style.opacity = (1 - pHeadExit).toFixed(4);
                    }
                    if (ctaDesc) {
                        ctaDesc.style.transform = 'translate3d(0, ' + (-16 * pHeadExit).toFixed(2) + 'px, 0)';
                        ctaDesc.style.opacity = (1 - pHeadExit).toFixed(4);
                    }
                    if (ctaEyebrow) {
                        ctaEyebrow.style.opacity = (1 - pHeadExit).toFixed(4);
                    }

                    sceneLayers.cta.style.opacity = (1 - easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.20) / 0.75)))).toFixed(4);

                    // 2. Incoming Closing Scene
                    sceneLayers.closing.style.opacity = easeInOutCubic(Math.min(1, progress / 0.70)).toFixed(4);
                    var closeShiftY = ((1 - eScene) * 26).toFixed(2);
                    sceneLayers.closing.style.transform = 'translate3d(0, ' + closeShiftY + 'px, 0)';

                    // Closing Eyebrow
                    var pCloseEye = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.16) / 0.65)));
                    if (closingEyebrow) {
                        closingEyebrow.style.opacity = pCloseEye.toFixed(4);
                        closingEyebrow.style.transform = 'translate3d(0, ' + ((1 - pCloseEye) * 16).toFixed(2) + 'px, 0)';
                    }

                    // Closing Headline: arrives as primary focal element
                    var pCloseHead = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.22) / 0.66)));
                    if (closingTitle) {
                        var cHeadY = ((1 - pCloseHead) * 24).toFixed(2);
                        var cHeadSc = (0.96 + 0.04 * pCloseHead).toFixed(4);
                        closingTitle.style.opacity = pCloseHead.toFixed(4);
                        closingTitle.style.transform = 'translate3d(0, ' + cHeadY + 'px, 0) scale(' + cHeadSc + ')';
                    }

                    // Supporting copy: enters after headline
                    var pCloseDesc = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.30) / 0.62)));
                    if (closingDesc) {
                        closingDesc.style.opacity = pCloseDesc.toFixed(4);
                        closingDesc.style.transform = 'translate3d(0, ' + ((1 - pCloseDesc) * 18).toFixed(2) + 'px, 0)';
                    }

                    // Actions
                    var pCloseAct = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.38) / 0.58)));
                    if (closingActions) {
                        closingActions.style.opacity = pCloseAct.toFixed(4);
                        closingActions.style.transform = 'translate3d(0, ' + ((1 - pCloseAct) * 16).toFixed(2) + 'px, 0)';
                    }

                    // 3D Floating visual objects in Closing
                    var objConfigsClose = [
                        { dx: 18, dy: -12, scale: 0.94, start: 0.18, span: 0.70 }, // notebook
                        { dx: -14, dy: 15, scale: 0.94, start: 0.24, span: 0.68 }, // wallet
                        { dx: 12, dy: 16, scale: 0.94, start: 0.30, span: 0.65 },  // keys
                    ];
                    closingObjs.forEach(function (objEl, oIdx) {
                        var cfg = objConfigsClose[oIdx] || { dx: 10, dy: 10, scale: 0.95, start: 0.20, span: 0.65 };
                        var pObj = easeInOutCubic(Math.min(1, Math.max(0, (progress - cfg.start) / cfg.span)));
                        var curDx = ((1 - pObj) * cfg.dx).toFixed(2);
                        var curDy = ((1 - pObj) * cfg.dy).toFixed(2);
                        var curScale = (cfg.scale + (1 - cfg.scale) * pObj).toFixed(4);
                        objEl.style.opacity = pObj.toFixed(4);
                        objEl.style.transform = 'translate3d(' + curDx + 'px, ' + curDy + 'px, 0) scale(' + curScale + ')';
                    });

                    // Footer
                    var pFooter = easeInOutCubic(Math.min(1, Math.max(0, (progress - 0.28) / 0.70)));
                    if (closingFooterWrap) {
                        closingFooterWrap.style.opacity = pFooter.toFixed(4);
                        closingFooterWrap.style.transform = 'translate3d(0, ' + ((1 - pFooter) * 20).toFixed(2) + 'px, 0)';
                    }
                } else if (fromLayer && toLayer) {
                    var eScene = easeInOutCubic(p);
                    var outScale = 1 - 0.025 * eScene;
                    var outY = dir > 0 ? (-18 * eScene) : (22 * eScene);
                    var inY = dir > 0 ? (28 * (1 - eScene)) : (-26 * (1 - eScene));
                    var inScale = 0.985 + 0.015 * eScene;
                    fromLayer.style.opacity = (1 - eScene).toFixed(4);
                    fromLayer.style.transform = 'translate3d(0, ' + outY.toFixed(2) + 'px, 0) scale(' + outScale.toFixed(4) + ')';
                    toLayer.style.opacity = eScene.toFixed(4);
                    toLayer.style.transform = 'translate3d(0, ' + inY.toFixed(2) + 'px, 0) scale(' + inScale.toFixed(4) + ')';
                }
            }

            function finalizeTransition() {
                if (state.transitionToken !== token) {
                    return;
                }
                if (state.rafId !== null) {
                    window.cancelAnimationFrame(state.rafId);
                    state.rafId = null;
                }
                if (state.transitionTimer !== null) {
                    window.clearTimeout(state.transitionTimer);
                    state.transitionTimer = null;
                }

                clearTransitionInlineStyles();
                applyBeatInternalState(toBeat);
                if (toBeat.id !== 'login') {
                    state.loginEntryMode = 'natural';
                }
                setLayerA11yAndVisibility(toBeat.scene, underlyingLayerKey);
                clampDocumentScroll();

                if (isHeroRecentPair || isRecentHiwPair) {
                    if (toBeat.scene === 'recent') {
                        if (carousel && typeof carousel.resume === 'function') {
                            carousel.resume();
                        }
                    } else if (toBeat.scene === 'hero') {
                        if (carousel && typeof carousel.pause === 'function') {
                            carousel.pause();
                        }
                        if (heroParallax && typeof heroParallax.resetSoftly === 'function') {
                            heroParallax.resetSoftly();
                        }
                    } else if (toBeat.scene === 'hiw') {
                        if (carousel && typeof carousel.pause === 'function') {
                            carousel.pause();
                        }
                    }
                }

                if (hiwApi && toBeat.scene === 'hiw' && typeof hiwApi.refreshIndicator === 'function') {
                    hiwApi.refreshIndicator();
                }
                if ((isSameHiwStages || (toBeat.scene === 'hiw' && typeof toBeat.stageIndex === 'number')) && hiwApi && typeof hiwApi.setCoverflowProgress === 'function') {
                    hiwApi.setCoverflowProgress(toBeat.stageIndex);
                }

                state.isTransitioning = false;
                state.targetBeat = null;
                finishLoginFocusIfNeeded(toBeat.id);
            }

            function stepFrame(timestamp) {
                if (state.transitionToken !== token) {
                    return;
                }
                var now = typeof timestamp === 'number' && timestamp > 0 ? timestamp : Date.now();
                if (startTime === null) {
                    startTime = now;
                }
                var progress = duration > 0 ? (now - startTime) / duration : 1;
                if (progress >= 1) {
                    commitFrame(1);
                    finalizeTransition();
                    return;
                }
                commitFrame(progress);
                state.rafId = window.requestAnimationFrame(stepFrame);
            }

            commitFrame(0);
            state.rafId = window.requestAnimationFrame(stepFrame);
            state.transitionTimer = window.setTimeout(function () {
                if (state.isTransitioning && state.transitionToken === token) {
                    commitFrame(1);
                    finalizeTransition();
                }
            }, duration + 180);

            return true;
        }

        function goToBeat(id, options) {
            var resolved = resolveBeatId(id);
            if (!resolved) {
                return false;
            }
            var opts = options || {};

            if (!state.active) {
                var targetIdMap = {
                    'hero': 'hero',
                    'recently-reported': 'recently-reported',
                    'hiw-intro': 'how-it-works',
                    'hiw-journey-selection': 'how-it-works',
                    'cta-lost': 'final-cta',
                    'cta-found': 'final-cta',
                    'closing': 'cf-closing',
                    'login': 'login-scene',
                };
                var beatMeta = BEATS[resolved];
                if (hiwApi && beatMeta.journey) {
                    hiwApi.selectJourney(beatMeta.journey);
                }
                if (hiwApi && typeof beatMeta.stageIndex === 'number') {
                    hiwApi.syncScrollStage(beatMeta.stageIndex, null);
                }
                var domId = targetIdMap[resolved] || (resolved.indexOf('hiw-') === 0 ? 'how-it-works' : 'hero');
                var el = document.getElementById(domId);
                if (el) {
                    el.scrollIntoView({ behavior: 'auto', block: 'start' });
                }
                return true;
            }

            if (state.isTransitioning && !opts.immediate) {
                return false;
            }

            if (resolved === state.currentBeat && !opts.force) {
                return false;
            }

            return startTransition(resolved, opts);
        }

        function stepBeat(delta) {
            if (!state.active || state.isTransitioning) {
                return false;
            }
            var nextId = delta > 0 ? getNextNaturalBeat(state.currentBeat) : getPrevNaturalBeat(state.currentBeat);
            if (!nextId) {
                return false;
            }
            return goToBeat(nextId, {
                entryMode: nextId === 'login' ? 'natural' : undefined,
                direction: delta > 0 ? 1 : -1,
            });
        }

        function setJourney(journeyKey) {
            var normalized = journeyKey === 'found' ? 'found' : 'lost';
            state.currentJourney = normalized;
            if (hiwApi) {
                state.syncingFromBeat = true;
                hiwApi.selectJourney(normalized);
                state.syncingFromBeat = false;
                if (state.activeScene === 'hiw' && typeof hiwApi.refreshIndicator === 'function') {
                    window.requestAnimationFrame(function () {
                        hiwApi.refreshIndicator();
                    });
                }
            }
            return normalized;
        }

        function jumpToLandmark(name) {
            return goToBeat(name);
        }

        function scrollToHiwStage(stageIdx, journeyKey) {
            var journey = journeyKey === 'found' || journeyKey === 'lost' ? journeyKey : getActiveJourney();
            var clamped = Math.max(1, Math.min(5, (Number(stageIdx) || 0) + 1));
            return goToBeat('hiw-' + journey + '-' + clamped);
        }

        function openLoginShortcut() {
            if (!state.active) {
                window.location.href = '/login';
                return false;
            }
            return goToBeat('login', { entryMode: 'shortcut' });
        }

        // Input Isolation & Internal Scroll Protection
        function isOverlayActive() {
            if (document.body.classList.contains('cf-recent-modal-open')) {
                return true;
            }
            var recentModal = document.getElementById('cf-recent-modal');
            if (recentModal && recentModal.classList.contains('is-open')) {
                return true;
            }
            var confirmModal = document.getElementById('cf-confirm-modal');
            if (confirmModal && confirmModal.classList.contains('show')) {
                return true;
            }
            var scannerModal = document.getElementById('cf-ai-scanner-modal');
            if (scannerModal && scannerModal.classList.contains('show')) {
                return true;
            }
            var mobDrawer = document.getElementById('cf-guest-mob');
            if (mobDrawer && mobDrawer.classList.contains('is-open')) {
                return true;
            }
            return false;
        }

        function isEventInsideIsolatedOverlay(target) {
            if (isOverlayActive()) {
                return true;
            }
            if (!target || typeof target.closest !== 'function') {
                return false;
            }
            if (target.closest('#cf-recent-modal, .cf-recent-modal-dialog, #cf-confirm-modal, #cf-ai-scanner-modal, .modal.show')) {
                return true;
            }
            if (target.closest('#cfind-panel, #cfind-messages, #cfind-form, #cfind-input, #cfind-launcher, #cfind-teaser, .cfind-panel, .cfind-teaser')) {
                return true;
            }
            if (target.closest('#cf-guest-mob')) {
                return true;
            }
            return false;
        }

        function canLayerScrollInternally(target, stepDir) {
            if (!target || typeof target.closest !== 'function') {
                return false;
            }
            var host = target.closest('.cf-scene-layer.is-scene-active, .cf-scene-login-portal.is-portal-visible');
            if (!host) {
                return false;
            }
            var scrollHeight = host.scrollHeight || 0;
            var clientHeight = host.clientHeight || 0;
            if (scrollHeight <= clientHeight + 2) {
                return false;
            }
            var scrollTop = host.scrollTop || 0;
            if (stepDir > 0) {
                return scrollTop + clientHeight < scrollHeight - 2;
            }
            if (stepDir < 0) {
                return scrollTop > 2;
            }
            return false;
        }

        // Wheel Controller (discrete trackpad + mouse wheel normalization with momentum debounce)
        function endWheelGesture() {
            state.wheelIdleTimer = null;
            state.gestureInProgress = false;
            state.wheelAccum = 0;
            state.wheelAccumDir = 0;
        }

        function normalizeWheelDeltaY(event) {
            var dy = Number(event.deltaY) || 0;
            if (event.deltaMode === 1) {
                dy *= 16;
            } else if (event.deltaMode === 2) {
                dy *= (window.innerHeight || 900);
            }
            return dy;
        }

        function onNarrativeWheel(event) {
            if (!state.active || prefersReducedMotion()) {
                return;
            }
            if (isEventInsideIsolatedOverlay(event.target)) {
                return;
            }
            if (event.target && typeof event.target.closest === 'function' && event.target.closest('textarea, select')) {
                return;
            }

            var dy = normalizeWheelDeltaY(event);
            var dx = Number(event.deltaX) || 0;
            if (Math.abs(dy) < 0.5 || (Math.abs(dx) > Math.abs(dy) * 1.25 && Math.abs(dy) < 12)) {
                return;
            }

            var stepDir = dy > 0 ? 1 : -1;
            if (canLayerScrollInternally(event.target, stepDir)) {
                state.wheelAccum = 0;
                state.wheelAccumDir = 0;
                return;
            }

            if (event.cancelable) {
                event.preventDefault();
            }

            if (state.wheelIdleTimer !== null) {
                window.clearTimeout(state.wheelIdleTimer);
            }
            state.wheelIdleTimer = window.setTimeout(endWheelGesture, WHEEL_IDLE_DEBOUNCE_MS);

            if (state.isTransitioning || state.gestureInProgress) {
                state.gestureInProgress = true;
                return;
            }

            if (state.wheelAccumDir !== 0 && state.wheelAccumDir !== stepDir) {
                state.wheelAccum = 0;
            }
            state.wheelAccumDir = stepDir;
            state.wheelAccum += dy;

            if (Math.abs(state.wheelAccum) >= WHEEL_THRESHOLD) {
                var dir = state.wheelAccum > 0 ? 1 : -1;
                state.wheelAccum = 0;
                state.wheelAccumDir = 0;
                state.gestureInProgress = true;
                state.lastGestureTime = Date.now();
                stepBeat(dir);
            }
        }

        // Touch Controller (one vertical swipe = max one beat; preserves horizontal carousel & overlays)
        var touchSession = {
            active: false,
            handled: false,
            ignored: false,
            startX: 0,
            startY: 0,
            startTarget: null,
        };

        function onNarrativeTouchStart(event) {
            if (!state.active || prefersReducedMotion()) {
                return;
            }
            if (!event.touches || event.touches.length !== 1) {
                touchSession.active = false;
                touchSession.ignored = true;
                return;
            }
            if (isEventInsideIsolatedOverlay(event.target)) {
                touchSession.active = false;
                touchSession.ignored = true;
                return;
            }
            if (event.target && typeof event.target.closest === 'function' && event.target.closest('input, textarea, select, [contenteditable]')) {
                touchSession.active = false;
                touchSession.ignored = true;
                return;
            }

            var touch = event.touches[0];
            touchSession.active = true;
            touchSession.ignored = false;
            touchSession.handled = Boolean(state.isTransitioning || state.gestureInProgress);
            touchSession.startX = touch.clientX;
            touchSession.startY = touch.clientY;
            touchSession.startTarget = event.target;
        }

        function onNarrativeTouchMove(event) {
            if (!state.active || !touchSession.active || touchSession.ignored) {
                return;
            }
            if (!event.touches || event.touches.length !== 1) {
                touchSession.active = false;
                touchSession.ignored = true;
                return;
            }
            if (touchSession.handled) {
                if (event.cancelable) {
                    event.preventDefault();
                }
                return;
            }

            var touch = event.touches[0];
            var dx = touch.clientX - touchSession.startX;
            var dy = touch.clientY - touchSession.startY;
            var absDx = Math.abs(dx);
            var absDy = Math.abs(dy);

            if (absDx < 8 && absDy < 8) {
                return;
            }
            if (absDx >= absDy) {
                touchSession.ignored = true;
                touchSession.active = false;
                return;
            }

            // Upward swipe (dy < 0) advances +1 beat; downward swipe (dy > 0) reverses -1 beat
            var stepDir = dy < 0 ? 1 : -1;
            if (canLayerScrollInternally(touchSession.startTarget, stepDir)) {
                touchSession.ignored = true;
                touchSession.active = false;
                return;
            }

            if (event.cancelable) {
                event.preventDefault();
            }

            if (absDy >= TOUCH_SWIPE_THRESHOLD) {
                touchSession.handled = true;
                if (!state.isTransitioning && !state.gestureInProgress) {
                    state.gestureInProgress = true;
                    state.lastGestureTime = Date.now();
                    stepBeat(stepDir);
                }
            }
        }

        function onNarrativeTouchEnd(event) {
            if (!event.touches || event.touches.length === 0) {
                touchSession.active = false;
                touchSession.handled = false;
                touchSession.ignored = false;
                if (state.wheelIdleTimer === null) {
                    state.gestureInProgress = false;
                }
            }
        }

        // Keyboard Controller (ArrowDown/PageDown/Space -> +1, ArrowUp/PageUp -> -1)
        function isInteractiveKeyboardTarget(target) {
            if (!target || typeof target.closest !== 'function') {
                return false;
            }
            return Boolean(target.closest(
                'input, textarea, select, summary, [contenteditable="true"], [contenteditable=""], ' +
                'button, a[href], [role="tab"], [role="tablist"], [role="button"], [role="menuitem"], [role="slider"]'
            ));
        }

        function onNarrativeKeyDown(event) {
            if (!state.active || prefersReducedMotion()) {
                return;
            }
            if (event.defaultPrevented || event.repeat || event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            var stepDir = 0;
            if (event.key === 'ArrowDown' || event.key === 'PageDown' || event.key === ' ' || event.key === 'Spacebar') {
                stepDir = 1;
            } else if (event.key === 'ArrowUp' || event.key === 'PageUp') {
                stepDir = -1;
            } else {
                return;
            }

            if (isEventInsideIsolatedOverlay(event.target) || isInteractiveKeyboardTarget(event.target)) {
                return;
            }

            if (canLayerScrollInternally(event.target, stepDir)) {
                return;
            }

            event.preventDefault();
            if (state.isTransitioning) {
                return;
            }
            state.lastGestureTime = Date.now();
            stepBeat(stepDir);
        }

        function syncReducedMotionMode() {
            var shouldActivate = !prefersReducedMotion();
            state.active = shouldActivate;
            document.documentElement.classList.toggle('cf-narrative-lock', shouldActivate);
            document.body.classList.toggle('cf-narrative-active', shouldActivate);

            if (!shouldActivate) {
                clearActiveModeOverrides();
                if (navApi) {
                    navApi.forceReveal();
                }
            } else {
                clampDocumentScroll();
                renderBeat(state.currentBeat || 'hero');
                applyLoginGlassMap();
            }
        }

        if (reducedMotionQuery && reducedMotionQuery.addEventListener) {
            reducedMotionQuery.addEventListener('change', syncReducedMotionMode);
        }

        window.addEventListener('scroll', clampDocumentScroll, { passive: true });
        window.addEventListener('wheel', onNarrativeWheel, { passive: false });
        window.addEventListener('touchstart', onNarrativeTouchStart, { passive: true });
        window.addEventListener('touchmove', onNarrativeTouchMove, { passive: false });
        window.addEventListener('touchend', onNarrativeTouchEnd, { passive: true });
        window.addEventListener('touchcancel', onNarrativeTouchEnd, { passive: true });
        window.addEventListener('keydown', onNarrativeKeyDown);

        var resizeTimer = null;
        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                if (state.active) {
                    clampDocumentScroll();
                    if (hiwApi && state.activeScene === 'hiw' && typeof hiwApi.refreshIndicator === 'function') {
                        hiwApi.refreshIndicator();
                    }
                    applyLoginGlassMap();
                }
            }, 90);
        });

        window.addEventListener('hashchange', function () {
            if (!state.active) {
                return;
            }
            var nextBeat = resolveBeatId(window.location.hash);
            if (nextBeat) {
                clampDocumentScroll();
                goToBeat(nextBeat);
            }
        });

        // Bind CTA, Closing, Login & Landing Footer internal triggers to beat coordinator
        if (loginBackBtn) {
            loginBackBtn.addEventListener('click', function () {
                if (!state.active) {
                    window.scrollTo({ top: 0, behavior: 'auto' });
                    return;
                }
                if (state.isTransitioning) {
                    return;
                }
                var targetBackBeat = getPrevNaturalBeat('login') || 'hero';
                goToBeat(targetBackBeat, { direction: -1 });
            });
        }

        var closingTopBtn = document.getElementById('cf-closing-top-btn');
        if (closingTopBtn) {
            closingTopBtn.addEventListener('click', function (event) {
                if (state.active) {
                    event.preventDefault();
                    goToBeat('hero');
                }
            });
        }

        var closingSignInBtn = document.getElementById('cf-closing-signin-btn');
        if (closingSignInBtn && closingSignInBtn.getAttribute('href') === '/login') {
            closingSignInBtn.addEventListener('click', function (event) {
                if (state.active) {
                    event.preventDefault();
                    goToBeat('login', { entryMode: 'natural' });
                }
            });
        }

        var footerNavLinks = Array.prototype.slice.call(document.querySelectorAll('.cf-landing-footer-nav a[href*="#"], .cf-landing-footer-wordmark'));
        footerNavLinks.forEach(function (linkEl) {
            linkEl.addEventListener('click', function (event) {
                if (!state.active) {
                    return;
                }
                var href = linkEl.getAttribute('href') || '';
                if (href === '/') {
                    event.preventDefault();
                    goToBeat('hero');
                    return;
                }
                var hashIdx = href.indexOf('#');
                if (hashIdx !== -1) {
                    var targetBeat = resolveBeatId(href.slice(hashIdx));
                    if (targetBeat) {
                        event.preventDefault();
                        goToBeat(targetBeat);
                    }
                }
            });
        });

        // Initial activation
        document.documentElement.classList.toggle('cf-narrative-lock', state.active);
        document.body.classList.toggle('cf-narrative-active', state.active);
        if (state.active) {
            var initialBeat = resolveBeatId(window.location.hash) || 'hero';
            clampDocumentScroll();
            renderBeat(initialBeat);
        } else {
            clearActiveModeOverrides();
        }

        return {
            isActive: function () { return state.active; },
            isSyncingFromBeat: function () { return state.syncingFromBeat; },
            isSyncingFromScroll: function () { return state.syncingFromBeat; },
            isTransitioning: function () { return state.isTransitioning; },
            getHiwPhase: function () { return state.hiwPhase; },
            getBeatOrder: function () { return BEAT_ORDER.slice(); },
            getBeat: function (id) {
                var resolved = resolveBeatId(id);
                return resolved ? Object.assign({}, BEATS[resolved]) : null;
            },
            getNextNaturalBeat: getNextNaturalBeat,
            getPrevNaturalBeat: getPrevNaturalBeat,
            goToBeat: goToBeat,
            renderBeat: renderBeat,
            stepBeat: stepBeat,
            setJourney: setJourney,
            jumpToLandmark: jumpToLandmark,
            scrollToHiwStage: scrollToHiwStage,
            openLoginShortcut: openLoginShortcut,
            getState: function () {
                var hiwState = hiwApi && typeof hiwApi.getState === 'function' ? hiwApi.getState() : null;
                return {
                    active: state.active,
                    reducedMotion: prefersReducedMotion(),
                    scrollY: window.scrollY || 0,
                    currentBeat: state.currentBeat,
                    activeBeat: state.currentBeat,
                    targetBeat: state.targetBeat,
                    isTransitioning: state.isTransitioning,
                    activeScene: state.activeScene,
                    hiwPhase: state.hiwPhase,
                    currentJourney: hiwState ? hiwState.journey : state.currentJourney,
                    journey: hiwState ? hiwState.journey : state.currentJourney,
                    currentJourneyStage: state.currentJourneyStage,
                    stageIndex: hiwState ? hiwState.stageIndex : 0,
                    loginEntryMode: state.loginEntryMode,
                    loginOriginBeat: state.loginOriginBeat,
                    returnBeat: state.loginOriginBeat,
                    gestureInProgress: state.gestureInProgress,
                    lastGestureTime: state.lastGestureTime,
                    beatsCount: BEAT_ORDER.length,
                    portalInteractive: Boolean(loginPortal && loginPortal.classList.contains('is-portal-interactive')),
                };
            },
        };
    }

    window.CampusFindLanding = {
        version: '0.8.8-phase4c-batch5',
        prefersReducedMotion: prefersReducedMotion,
        isTouchDevice: isTouchDevice,
        navbar: null,
        heroParallax: null,
        recentCarousel: null,
        howItWorks: null,
        narrative: null,
        initialized: false,
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.CampusFindLanding.navbar = initGuestLiquidGlassNav();
        window.CampusFindLanding.heroParallax = initHeroParallax();
        initHeroCtas();
        window.CampusFindLanding.recentCarousel = initRecentlyReported();
        window.CampusFindLanding.howItWorks = initHowItWorks();
        initFinalCtaReveal();
        window.CampusFindLanding.narrative = initSceneNarrative(
            window.CampusFindLanding.howItWorks,
            window.CampusFindLanding.navbar,
            window.CampusFindLanding.recentCarousel,
            window.CampusFindLanding.heroParallax
        );
        window.CampusFindLanding.initialized = true;
    });
})();
