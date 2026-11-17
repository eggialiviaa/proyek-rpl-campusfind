/**
 * CampusFind — public/js/sidebar-dock.js
 * PAGE-00B.3.1: Elastic Liquid Glass Dock Interaction Controller
 * 
 * Fitur Utama:
 * 1. Elastic Capsule Stretch: Hanya .dock-glass yang meregang secara elastis.
 * 2. Spring-based Icon Magnification & Neighbor Spatial Reaction.
 * 3. Cursor Radial Specular Light (--mx, --my).
 * 4. Single Sliding Glass Bubble with Automatic SSR Active-Route Docking.
 * 5. Floating Dock Tooltip with 100ms Debounce & Badge Info.
 * 6. Desktop Collapse/Expand Sync (Ctrl+B) & Quick Search (Ctrl+K).
 * 7. Mobile Off-Canvas Drawer (<992px) with Backdrop & Escape Dismissal.
 */

'use strict';

// ──────────────────────────────────────────────
// TUNABLE CONSTANTS (PAGE-00B.3.1)
// ──────────────────────────────────────────────
const STRETCH_MAX = 10;    // Maximum outward edge extension, px
const SQUEEZE_MAX = 5;     // Maximum opposite-edge contraction, px
const CENTER_STRETCH = 2;  // Symmetric stretch at center, px

const SPRING_STIFFNESS = 260;
const SPRING_DAMPING = 22;
const SPRING_MASS = 1;

/**
 * Inisialisasi Pengendali Interaksi Dock Kaca Elastis
 */
function initCampusFindDock() {
    const shellWrapper = document.getElementById('cf-shell-wrapper');
    const dock = document.getElementById('cf-sidebar');
    const dockGlass = document.getElementById('cf-dock-glass');
    const dockContent = document.getElementById('cf-dock-content');
    const nav = document.getElementById('cf-sidebar-nav');
    const slidingBubble = document.getElementById('cf-sidebar-sliding-bubble') || document.getElementById('cf-sidebar-sliding-indicator');
    const searchInput = document.getElementById('cf-sidebar-search-input');
    const toggleBtn = document.getElementById('cf-sidebar-toggle');
    const toggleIcon = document.getElementById('cf-sidebar-toggle-icon');
    const mobileToggle = document.getElementById('cf-mobile-toggle');
    const mobileClose = document.getElementById('cf-sidebar-close');
    const backdrop = document.getElementById('cf-sidebar-backdrop');
    const docEl = document.documentElement;
    const STORAGE_KEY = 'cf_sidebar_collapsed';

    if (!shellWrapper || !dock || !dockGlass) {
        return; // Guard: Hanya aktif di halaman dengan authenticated shell dan dock layer
    }

    if (dock.dataset.dockInitialized === 'true') {
        return; // Mencegah duplikasi inisialisasi
    }
    dock.dataset.dockInitialized = 'true';

    // Helper aman escape HTML untuk tooltip
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Status preferensi lingkungan
    function isFinePointer() {
        return !window.matchMedia('(hover: none)').matches;
    }

    function isReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function isCollapsedDesktop() {
        return shellWrapper.classList.contains('cf-sidebar-collapsed') && window.innerWidth >= 992;
    }

    // ──────────────────────────────────────────────
    // 1. STATE & CACHED GEOMETRY
    // ──────────────────────────────────────────────
    let hasGenuinePointerMoved = false;
    window.addEventListener('pointermove', function onFirstPointerMove() {
        hasGenuinePointerMoved = true;
        window.removeEventListener('pointermove', onFirstPointerMove);
    }, { once: true, passive: true });

    let isPointerInside = false;
    let pointerClientY = 0;

    let dockRect = { top: 0, height: 0, left: 0 };
    let dockCenterY = 0;
    let dockHeight = 0;

    // Glass spring state (hanya meregangkan .dock-glass)
    let glassTranslateY = 0;
    let glassTranslateYVelocity = 0;
    let targetGlassTranslateY = 0;

    let glassScaleY = 1.0;
    let glassScaleYVelocity = 0;
    let targetGlassScaleY = 1.0;

    // Icon items cache & spring state
    const iconItems = [];
    if (nav) {
        const links = nav.querySelectorAll('.cf-sidebar-link');
        links.forEach(function (link) {
            const wrapper = link.querySelector('.cf-sidebar-icon-wrapper');
            if (wrapper) {
                iconItems.push({
                    link: link,
                    wrapper: wrapper,
                    centerY: 0,
                    scale: 1.0,
                    scaleVelocity: 0,
                    targetScale: 1.0,
                    translateY: 0,
                    translateYVelocity: 0,
                    targetTranslateY: 0,
                });
            }
        });
    }

    // Perbarui cache geometri tanpa membebani setiap frame animasi
    function updateCachedGeometry() {
        if (!dock) return;
        const rect = dock.getBoundingClientRect();
        dockRect = rect;
        dockHeight = rect.height || 1;
        dockCenterY = rect.top + dockHeight / 2;

        iconItems.forEach(function (item) {
            const wRect = item.wrapper.getBoundingClientRect();
            item.centerY = wRect.top + wRect.height / 2;
        });
    }

    updateCachedGeometry();

    if (typeof ResizeObserver !== 'undefined') {
        const ro = new ResizeObserver(function () {
            updateCachedGeometry();
        });
        ro.observe(dock);
    }

    window.addEventListener('resize', function () {
        updateCachedGeometry();
        hideDockTooltip();
        updateSlidingBubble(null);
    });

    // ──────────────────────────────────────────────
    // 2. TARGET CALCULATIONS
    // ──────────────────────────────────────────────
    function computeTargets(pointerY) {
        if (!isCollapsedDesktop() || !isFinePointer() || isReducedMotion()) {
            resetTargets();
            return;
        }

        // A. Elastic Capsule Stretch Targets (Section 2)
        const p = Math.max(-1, Math.min(1, (pointerY - dockCenterY) / (dockHeight / 2)));
        const centerStretch = CENTER_STRETCH * (1 - Math.abs(p));
        const topExpand = STRETCH_MAX * Math.max(-p, 0) - SQUEEZE_MAX * Math.max(p, 0) + centerStretch;
        const bottomExpand = STRETCH_MAX * Math.max(p, 0) - SQUEEZE_MAX * Math.max(-p, 0) + centerStretch;

        targetGlassTranslateY = (bottomExpand - topExpand) / 2;
        targetGlassScaleY = (dockHeight + topExpand + bottomExpand) / dockHeight;

        // B. Icon Magnification & Neighbor Motion Targets (Section 3)
        const sigma = 56;
        const sigmaSpatial = 78;

        iconItems.forEach(function (item) {
            const dy = item.centerY - pointerY;
            const distance = Math.abs(dy);

            // Gaussian Magnification Formula
            const scale = 1 + 0.55 * Math.exp(-(distance * distance) / (2 * sigma * sigma));
            item.targetScale = Math.min(1.58, Math.max(1.0, scale));

            // Spatial Reaction (dorong tetangga vertikal agar tidak bergesekan)
            if (distance < 3) {
                item.targetTranslateY = 0;
            } else {
                const dir = Math.sign(dy);
                item.targetTranslateY = dir * 12 * Math.exp(-(distance * distance) / (2 * sigmaSpatial * sigmaSpatial));
            }
        });
    }

    function resetTargets() {
        targetGlassTranslateY = 0;
        targetGlassScaleY = 1.0;
        iconItems.forEach(function (item) {
            item.targetScale = 1.0;
            item.targetTranslateY = 0;
        });
    }

    // ──────────────────────────────────────────────
    // 3. MASTER SPRING ANIMATION LOOP
    // ──────────────────────────────────────────────
    let rafId = null;
    let lastTime = 0;

    function masterSpringLoop(currentTime) {
        if (!lastTime) lastTime = currentTime;
        const dt = Math.min(0.032, (currentTime - lastTime) / 1000) || 0.016;
        lastTime = currentTime;

        let isStillMoving = false;

        // 1. Integrator Pegas untuk Layer Kaca (.dock-glass)
        const accGlassY = (SPRING_STIFFNESS * (targetGlassTranslateY - glassTranslateY) - SPRING_DAMPING * glassTranslateYVelocity) / SPRING_MASS;
        glassTranslateYVelocity += accGlassY * dt;
        glassTranslateY += glassTranslateYVelocity * dt;

        const accGlassScale = (SPRING_STIFFNESS * (targetGlassScaleY - glassScaleY) - SPRING_DAMPING * glassScaleYVelocity) / SPRING_MASS;
        glassScaleYVelocity += accGlassScale * dt;
        glassScaleY += glassScaleYVelocity * dt;

        const isGlassYResting = Math.abs(targetGlassTranslateY - glassTranslateY) < 0.04 && Math.abs(glassTranslateYVelocity) < 0.06;
        const isGlassScaleResting = Math.abs(targetGlassScaleY - glassScaleY) < 0.001 && Math.abs(glassScaleYVelocity) < 0.002;

        if (!isGlassYResting || !isGlassScaleResting) {
            isStillMoving = true;
            dockGlass.style.transform = `translateY(${glassTranslateY.toFixed(2)}px) scaleY(${glassScaleY.toFixed(4)})`;
        } else if (!isPointerInside && targetGlassTranslateY === 0 && targetGlassScaleY === 1.0) {
            glassTranslateY = 0;
            glassTranslateYVelocity = 0;
            glassScaleY = 1.0;
            glassScaleYVelocity = 0;
            dockGlass.style.transform = '';
        }

        // 2. Integrator Pegas untuk Wrapper Ikon
        iconItems.forEach(function (item) {
            const accScale = (SPRING_STIFFNESS * (item.targetScale - item.scale) - SPRING_DAMPING * item.scaleVelocity) / SPRING_MASS;
            item.scaleVelocity += accScale * dt;
            item.scale += item.scaleVelocity * dt;

            const accY = (SPRING_STIFFNESS * (item.targetTranslateY - item.translateY) - SPRING_DAMPING * item.translateYVelocity) / SPRING_MASS;
            item.translateYVelocity += accY * dt;
            item.translateY += item.translateYVelocity * dt;

            const isScaleResting = Math.abs(item.targetScale - item.scale) < 0.002 && Math.abs(item.scaleVelocity) < 0.005;
            const isYResting = Math.abs(item.targetTranslateY - item.translateY) < 0.04 && Math.abs(item.translateYVelocity) < 0.06;

            if (!isScaleResting || !isYResting) {
                isStillMoving = true;
                item.wrapper.style.transform = `translateY(${item.translateY.toFixed(2)}px) scale(${item.scale.toFixed(3)})`;
            } else if (!isPointerInside && item.targetScale === 1.0 && item.targetTranslateY === 0) {
                item.scale = 1.0;
                item.scaleVelocity = 0;
                item.translateY = 0;
                item.translateYVelocity = 0;
                item.wrapper.style.transform = '';
            }
        });

        // Loop berlanjut bila pointer di dalam atau pegas belum sepenuhnya diam
        if (isPointerInside || isStillMoving) {
            rafId = requestAnimationFrame(masterSpringLoop);
        } else {
            rafId = null;
            lastTime = 0;
        }
    }

    function startSpringLoop() {
        if (!rafId) {
            lastTime = 0;
            rafId = requestAnimationFrame(masterSpringLoop);
        }
    }

    // ──────────────────────────────────────────────
    // 4. POINTER & SPECULAR LIGHT LISTENERS
    // ──────────────────────────────────────────────
    dock.addEventListener('pointerenter', function (e) {
        if (e.pointerType === 'touch') return;
        if (!hasGenuinePointerMoved) return;
        isPointerInside = true;
        dock.classList.add('is-pointer-inside');
        updateCachedGeometry();
        pointerClientY = e.clientY;
        computeTargets(pointerClientY);
        startSpringLoop();
    });

    dock.addEventListener('pointermove', function (e) {
        if (e.pointerType === 'touch') return;
        if (!hasGenuinePointerMoved) return;
        isPointerInside = true;
        dock.classList.add('is-pointer-inside');
        pointerClientY = e.clientY;

        // Koordinat Specular Light dinamis (--mx, --my) relatif terhadap dock
        const mx = (e.clientX - dockRect.left).toFixed(1) + 'px';
        const my = (e.clientY - dockRect.top).toFixed(1) + 'px';
        dock.style.setProperty('--mx', mx);
        dock.style.setProperty('--my', my);

        computeTargets(pointerClientY);
        startSpringLoop();
    });

    dock.addEventListener('pointerleave', function () {
        isPointerInside = false;
        dock.classList.remove('is-pointer-inside');
        resetTargets();
        startSpringLoop(); // Mengembalikan pegas ke posisi semula secara natural
        updateSlidingBubble(null); // Kembalikan bubble ke item rute aktif
    });

    window.addEventListener('pagehide', function () {
        isPointerInside = false;
        if (dock) dock.classList.remove('is-pointer-inside');
        resetTargets();
    });

    // ──────────────────────────────────────────────
    // 5. FLOATING DOCK TOOLTIP (COLLAPSED MODE)
    // ──────────────────────────────────────────────
    let dockTooltip = document.getElementById('cf-dock-tooltip');
    if (!dockTooltip) {
        dockTooltip = document.createElement('div');
        dockTooltip.id = 'cf-dock-tooltip';
        dockTooltip.className = 'cf-dock-tooltip';
        dockTooltip.setAttribute('role', 'tooltip');
        dockTooltip.setAttribute('aria-hidden', 'true');
        document.body.appendChild(dockTooltip);
    }

    let tooltipTimer = null;

    function showDockTooltip(targetEl) {
        if (!dockTooltip) return;
        if (!hasGenuinePointerMoved) return;
        if (!isCollapsedDesktop()) {
            hideDockTooltip();
            return;
        }
        clearTimeout(tooltipTimer);
        tooltipTimer = setTimeout(function () {
            const label = targetEl.getAttribute('data-label') || targetEl.getAttribute('title') || '';
            if (!label) {
                hideDockTooltip();
                return;
            }
            const badgeEl = targetEl.querySelector('.cf-sidebar-badge');
            const hasBadge = badgeEl && !badgeEl.classList.contains('d-none') && badgeEl.textContent.trim();
            const badgeCount = hasBadge ? badgeEl.textContent.trim() : null;

            dockTooltip.innerHTML = badgeCount
                ? `${escapeHtml(label)} <span class="cf-dock-tooltip-badge">${escapeHtml(badgeCount)}</span>`
                : escapeHtml(label);

            const rect = targetEl.getBoundingClientRect();
            const tipRect = dockTooltip.getBoundingClientRect();
            const top = Math.round(rect.top + (rect.height - (tipRect.height || 28)) / 2);
            const left = Math.round(rect.right + 14);

            dockTooltip.style.top = top + 'px';
            dockTooltip.style.left = left + 'px';
            dockTooltip.classList.add('show');
            dockTooltip.setAttribute('aria-hidden', 'false');
        }, 100); // 100ms debounce per spesifikasi
    }

    function hideDockTooltip() {
        clearTimeout(tooltipTimer);
        if (dockTooltip) {
            dockTooltip.classList.remove('show');
            dockTooltip.setAttribute('aria-hidden', 'true');
        }
    }

    const tooltipTargets = dock.querySelectorAll('.cf-sidebar-link, .cf-sidebar-user-link, .cf-sidebar-logout-btn');
    tooltipTargets.forEach(function (target) {
        target.addEventListener('mouseenter', function () { showDockTooltip(target); });
        target.addEventListener('mouseleave', hideDockTooltip);
        target.addEventListener('click', hideDockTooltip);
    });

    // ──────────────────────────────────────────────
    // 6. SINGLE SLIDING GLASS BUBBLE
    // ──────────────────────────────────────────────
    function updateSlidingBubble(targetLink) {
        if (!slidingBubble || !nav) return;

        // Jika null / keluar, kembali ke item menu yang aktif dari SSR
        if (!targetLink) {
            const activeLink = nav.querySelector('.cf-sidebar-link.active');
            if (activeLink && activeLink.offsetParent !== null) {
                updateSlidingBubble(activeLink);
            } else {
                slidingBubble.classList.remove('active');
            }
            return;
        }

        const navRect = nav.getBoundingClientRect();
        const linkRect = targetLink.getBoundingClientRect();

        const top = (linkRect.top - navRect.top) + nav.scrollTop;
        const left = (linkRect.left - navRect.left);
        const width = linkRect.width;
        const height = linkRect.height;

        slidingBubble.style.top = top + 'px';
        slidingBubble.style.left = left + 'px';
        slidingBubble.style.width = width + 'px';
        slidingBubble.style.height = height + 'px';
        slidingBubble.classList.add('active');
    }

    // ──────────────────────────────────────────────
    // 6. SINGLE SLIDING GLASS BUBBLE & NAV LISTENERS
    // ──────────────────────────────────────────────
    function restoreSidebarScroll() {
        if (!nav) return;
        try {
            const raw = sessionStorage.getItem('cf_sidebar_scroll');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (parsed && parsed.role === shellWrapper.dataset.role && (Date.now() - parsed.ts < 30000)) {
                    if (typeof parsed.scrollTop === 'number') {
                        nav.scrollTop = parsed.scrollTop;
                    }
                }
            }
        } catch (e) {}
    }

    if (nav) {
        // Simpan posisi scroll pada debounced scroll
        let scrollTimer = null;
        nav.addEventListener('scroll', function () {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function () {
                try {
                    sessionStorage.setItem('cf_sidebar_scroll', JSON.stringify({
                        scrollTop: nav.scrollTop,
                        role: shellWrapper.dataset.role,
                        ts: Date.now()
                    }));
                } catch (e) {}
            }, 80);
        }, { passive: true });

        // Hover & keyboard focus bubble listeners
        const navLinks = nav.querySelectorAll('.cf-sidebar-link');
        navLinks.forEach(function (link) {
            link.addEventListener('mouseenter', function () {
                if (!hasGenuinePointerMoved) return;
                updateSlidingBubble(link);
            });
            link.addEventListener('focus', function () {
                updateSlidingBubble(link);
            });
        });

        nav.addEventListener('mouseleave', function () {
            updateSlidingBubble(null);
        });

        // Click Handler: Presisi Simpan Snapshot Navigasi FLIP (Bug 3) & Scroll (Bug 2)
        nav.addEventListener('click', function (e) {
            const link = e.target.closest('.cf-sidebar-link');
            if (!link) return;

            // Simpan scroll position terkini
            try {
                sessionStorage.setItem('cf_sidebar_scroll', JSON.stringify({
                    scrollTop: nav.scrollTop,
                    role: shellWrapper.dataset.role,
                    ts: Date.now()
                }));
            } catch (err) {}

            // Abaikan navigasi modified (Ctrl/Cmd/Shift/Alt/middle click) atau non-internal
            if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;

            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

            // Respons pressed feedback instan (~80ms, transform scale 0.98 murni visual)
            link.style.transform = 'scale(0.98)';
            setTimeout(function () {
                link.style.transform = '';
            }, 80);

            // Ambil geometri BUBBLE RUTE AKTIF SAAT INI (Bukan bubble hover)
            const currentActive = nav.querySelector('.cf-sidebar-link.active');
            if (currentActive && currentActive.offsetParent !== null && slidingBubble) {
                const navRect = nav.getBoundingClientRect();
                const activeRect = currentActive.getBoundingClientRect();
                const geom = {
                    top: (activeRect.top - navRect.top) + nav.scrollTop,
                    left: activeRect.left - navRect.left,
                    width: activeRect.width,
                    height: activeRect.height
                };

                const currentId = currentActive.id || currentActive.getAttribute('data-menu-id') || '';
                const targetId = link.id || link.getAttribute('data-menu-id') || '';

                try {
                    sessionStorage.setItem('cf_nav_flip', JSON.stringify({
                        sourceId: currentId,
                        targetId: targetId,
                        targetUrl: href,
                        geometry: geom,
                        role: shellWrapper.dataset.role,
                        ts: Date.now()
                    }));
                } catch (err) {}
            }
        });
    }

    // Pembersihan snapshot saat logout
    const logoutForm = document.querySelector('.cf-sidebar-logout-form');
    if (logoutForm) {
        logoutForm.addEventListener('submit', function () {
            try {
                sessionStorage.removeItem('cf_nav_flip');
                sessionStorage.removeItem('cf_sidebar_scroll');
            } catch (e) {}
        });
    }

    // ──────────────────────────────────────────────
    // 7. DESKTOP EXPAND / COLLAPSE SYNCHRONIZATION
    // ──────────────────────────────────────────────
    function applyDesktopCollapsedState(isCollapsed, persist) {
        if (window.innerWidth < 992) return;

        hideDockTooltip();
        resetTargets();

        if (isCollapsed) {
            shellWrapper.classList.add('cf-sidebar-collapsed');
            docEl.classList.add('cf-sidebar-collapsed');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', 'false');
                toggleBtn.setAttribute('aria-label', 'Perbesar Navigasi');
                toggleBtn.setAttribute('title', 'Perbesar Navigasi (Ctrl+B)');
            }
            if (toggleIcon) {
                toggleIcon.className = 'bi bi-chevron-right';
            }
        } else {
            shellWrapper.classList.remove('cf-sidebar-collapsed');
            docEl.classList.remove('cf-sidebar-collapsed');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', 'true');
                toggleBtn.setAttribute('aria-label', 'Perkecil Navigasi');
                toggleBtn.setAttribute('title', 'Perkecil Navigasi (Ctrl+B)');
            }
            if (toggleIcon) {
                toggleIcon.className = 'bi bi-chevron-left';
            }
        }

        if (persist) {
            try {
                localStorage.setItem(STORAGE_KEY, isCollapsed ? 'true' : 'false');
            } catch (e) {}

            setTimeout(function () {
                updateCachedGeometry();
                updateSlidingBubble(null);
            }, 360);
        }
    }

    // ──────────────────────────────────────────────
    // INISIALISASI STARTUP (BUGS 2 & 3: PREPAINT, SCROLL, FLIP & DOUBLE-RAF)
    // ──────────────────────────────────────────────
    // 1. Sinkronisasi state collapsed dari localStorage
    try {
        const savedCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
        applyDesktopCollapsedState(savedCollapsed, false);
    } catch (e) {}

    // 2. Pulihkan scroll container sidebar terlebih dahulu
    restoreSidebarScroll();

    // 3. Perbarui cache geometri setelah scroll dipulihkan
    updateCachedGeometry();

    // 4. Validasi dan jalankan transisi FLIP jika ada snapshot yang valid
    let flipSnapshot = null;
    try {
        const raw = sessionStorage.getItem('cf_nav_flip');
        if (raw) {
            const parsed = JSON.parse(raw);
            sessionStorage.removeItem('cf_nav_flip'); // Sekali pakai
            if (parsed && (Date.now() - parsed.ts < 5000) && parsed.role === shellWrapper.dataset.role) {
                flipSnapshot = parsed;
            }
        }
    } catch (e) {}

    const initialActiveLink = nav ? nav.querySelector('.cf-sidebar-link.active') : null;

    if (flipSnapshot && initialActiveLink && initialActiveLink.offsetParent !== null && slidingBubble && !isReducedMotion()) {
        const navRect = nav.getBoundingClientRect();
        const targetRect = initialActiveLink.getBoundingClientRect();
        const targetTop = (targetRect.top - navRect.top) + nav.scrollTop;
        const targetLeft = targetRect.left - navRect.left;
        const targetWidth = targetRect.width;
        const targetHeight = targetRect.height;

        const isDifferent = Math.abs(targetTop - flipSnapshot.geometry.top) > 2;

        if (isDifferent) {
            // Pasang bubble di posisi awal (snapshot lama) secara instan tanpa transisi
            slidingBubble.style.transition = 'none';
            slidingBubble.style.top = flipSnapshot.geometry.top + 'px';
            slidingBubble.style.left = flipSnapshot.geometry.left + 'px';
            slidingBubble.style.width = flipSnapshot.geometry.width + 'px';
            slidingBubble.style.height = flipSnapshot.geometry.height + 'px';
            slidingBubble.classList.add('active');

            // Force reflow agar geometri awal terkunci di layout engine
            void slidingBubble.offsetHeight;

            // Lepaskan .no-transition via double-requestAnimationFrame lalu animasikan ke tujuan
            requestAnimationFrame(function () {
                docEl.classList.remove('no-transition');
                requestAnimationFrame(function () {
                    slidingBubble.style.transition = 'top 300ms cubic-bezier(0.16, 1, 0.3, 1), left 300ms cubic-bezier(0.16, 1, 0.3, 1), width 300ms cubic-bezier(0.16, 1, 0.3, 1), height 300ms cubic-bezier(0.16, 1, 0.3, 1), opacity 180ms ease';
                    slidingBubble.style.top = targetTop + 'px';
                    slidingBubble.style.left = targetLeft + 'px';
                    slidingBubble.style.width = targetWidth + 'px';
                    slidingBubble.style.height = targetHeight + 'px';
                });
            });
        } else {
            updateSlidingBubble(initialActiveLink);
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    docEl.classList.remove('no-transition');
                });
            });
        }
    } else {
        if (initialActiveLink) {
            updateSlidingBubble(initialActiveLink);
        }
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                docEl.classList.remove('no-transition');
            });
        });
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            hideDockTooltip();
            const currentlyCollapsed = shellWrapper.classList.contains('cf-sidebar-collapsed');
            applyDesktopCollapsedState(!currentlyCollapsed, true);
        });
    }

    // Shortcut Keyboard Desktop Ctrl+B untuk toggle collapse
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
            if (window.innerWidth >= 992 && toggleBtn) {
                e.preventDefault();
                toggleBtn.click();
            }
        }
    });

    // ──────────────────────────────────────────────
    // 8. QUICK SEARCH FILTER (EXPANDED MODE)
    // ──────────────────────────────────────────────
    if (searchInput && nav) {
        // Prevent form submit & handle Enter to open first matched menu
        const searchForm = searchInput.closest('form');
        if (searchForm) {
            searchForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const firstVisible = nav.querySelector('.cf-sidebar-link:not([style*="display: none"])');
                if (firstVisible) {
                    firstVisible.click();
                }
            });
        }

        // Escape to clear search or blur; Enter to navigate
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (this.value) {
                    this.value = '';
                    this.dispatchEvent(new Event('input', { bubbles: true }));
                    e.stopPropagation();
                } else if (window.innerWidth >= 992) {
                    this.blur();
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const firstVisible = nav.querySelector('.cf-sidebar-link:not([style*="display: none"])');
                if (firstVisible) {
                    firstVisible.click();
                }
            }
        });

        searchInput.addEventListener('input', function () {
            const query = (this.value || '').trim().toLowerCase();
            const groups = nav.querySelectorAll('.cf-sidebar-group');
            const dividers = nav.querySelectorAll('.cf-sidebar-group-divider');
            let totalVisible = 0;

            groups.forEach(function (group) {
                const links = group.querySelectorAll('.cf-sidebar-link');
                const heading = group.querySelector('.cf-sidebar-heading');
                const headingText = (heading ? heading.textContent : '').toLowerCase();
                const groupMatches = Boolean(query && headingText.includes(query));
                let hasVisible = false;

                links.forEach(function (link) {
                    const labelEl = link.querySelector('.cf-sidebar-label');
                    const label = (labelEl ? labelEl.textContent : '').toLowerCase();

                    if (!query || label.includes(query) || groupMatches) {
                        link.style.display = '';
                        hasVisible = true;
                        totalVisible++;
                    } else {
                        link.style.display = 'none';
                    }
                });

                if (heading) {
                    heading.style.display = hasVisible ? '' : 'none';
                }
                group.style.display = hasVisible ? '' : 'none';
            });

            // Sembunyikan pembatas group saat mode pencarian aktif
            dividers.forEach(function (divider) {
                divider.style.display = query ? 'none' : '';
            });

            // Empty state display bila tidak ada menu yang cocok
            let emptyEl = nav.querySelector('.cf-sidebar-search-empty');
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.className = 'cf-sidebar-search-empty text-center py-4 px-3 text-muted small';
                emptyEl.innerHTML = '<i class="bi bi-search d-block fs-4 mb-2 opacity-50"></i><span>Tidak ada menu yang sesuai</span>';
                nav.appendChild(emptyEl);
            }

            if (query && totalVisible === 0) {
                emptyEl.style.display = '';
            } else {
                emptyEl.style.display = 'none';
            }

            // Sinkronkan sliding bubble dengan item aktif jika masih terlihat
            const activeLink = nav.querySelector('.cf-sidebar-link.active');
            if (activeLink && activeLink.offsetParent !== null) {
                updateSlidingBubble(null);
            } else if (slidingBubble) {
                slidingBubble.style.opacity = '0';
            }
        });
    }

    // Shortcut Keyboard Desktop Ctrl+K: Fokus pencarian (otomatis expand bila collapsed)
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            if (window.innerWidth >= 992 && searchInput) {
                e.preventDefault();
                if (shellWrapper.classList.contains('cf-sidebar-collapsed')) {
                    applyDesktopCollapsedState(false, true);
                }
                setTimeout(function () {
                    searchInput.focus();
                    searchInput.select();
                }, 200);
            }
        }
    });

    // ──────────────────────────────────────────────
    // 9. MOBILE OFF-CANVAS DRAWER (<992px)
    // ──────────────────────────────────────────────
    let lastActiveElement = null;

    function openMobileDrawer() {
        lastActiveElement = document.activeElement;
        shellWrapper.classList.add('cf-mobile-open');
        document.body.style.overflow = 'hidden';
        if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'true');
        if (mobileClose) mobileClose.focus();
    }

    function closeMobileDrawer() {
        shellWrapper.classList.remove('cf-mobile-open');
        document.body.style.overflow = '';
        if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'false');
        if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
            lastActiveElement.focus();
        }
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (shellWrapper.classList.contains('cf-mobile-open')) {
                closeMobileDrawer();
            } else {
                openMobileDrawer();
            }
        });
    }

    if (mobileClose) {
        mobileClose.addEventListener('click', function (e) {
            e.preventDefault();
            closeMobileDrawer();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeMobileDrawer);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            if (shellWrapper.classList.contains('cf-mobile-open')) {
                e.preventDefault();
                closeMobileDrawer();
            }
        }
    });
}

// Ekspos global helper
window.initCampusFindDock = initCampusFindDock;

// Inisialisasi saat DOM siap
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCampusFindDock);
} else {
    initCampusFindDock();
}
