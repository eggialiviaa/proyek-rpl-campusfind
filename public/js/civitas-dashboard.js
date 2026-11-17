/**
 * CampusFind — Civitas Dashboard Controller (PAGE-01)
 * Native Vanilla JavaScript: Staggered Reveal, Pure SVG Charts, Interactive Glass Calendar, KPI Count-Up
 */

document.addEventListener('DOMContentLoaded', () => {
    const isReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // 1. Parse Real Server Data
    let dashboardData = {};
    const dataEl = document.getElementById('cf-civitas-data');
    if (dataEl) {
        try {
            dashboardData = JSON.parse(dataEl.textContent || '{}');
        } catch (e) {
            console.warn('[CampusFind] Failed to parse dashboard JSON data:', e);
        }
    }

    // 2. IntersectionObserver for Staggered Card Reveal
    const revealElements = document.querySelectorAll('.cf-civitas-dashboard .cf-reveal');
    if (!isReducedMotion && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach((entry, idx) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('cf-revealed');
                    }, idx * 60);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        revealElements.forEach(el => observer.observe(el));
    } else {
        revealElements.forEach(el => el.classList.add('cf-revealed'));
    }

    // 3. Genuine KPI & Hero Scramble Animation (Randomized Digit Scramble → Real DB Value)
    const counterElements = document.querySelectorAll('[data-count-target]');
    counterElements.forEach(el => {
        const target = parseInt(el.getAttribute('data-count-target'), 10);
        const actualValue = isNaN(target) ? 0 : target;
        const formattedActual = actualValue.toLocaleString('id-ID');

        // Always store accessible label immediately for screen readers
        el.setAttribute('aria-label', formattedActual);

        if (isReducedMotion) {
            el.textContent = formattedActual;
            return;
        }

        const duration = 550; // crisp, elegant duration
        const startTime = performance.now();
        const chars = '0123456789';

        const targetStr = String(actualValue);
        const scrambleLen = actualValue === 0 ? 1 : Math.max(targetStr.length, 2);

        let lastScrambleTime = 0;
        const frameInterval = 40; // scramble update interval

        function scrambleStep(now) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);

            if (now - lastScrambleTime >= frameInterval) {
                lastScrambleTime = now;

                if (progress >= 0.8) {
                    // In final 20% of animation, snap to the exact database value
                    el.textContent = formattedActual;
                } else {
                    let randomStr = '';
                    for (let i = 0; i < scrambleLen; i++) {
                        randomStr += chars[Math.floor(Math.random() * chars.length)];
                    }
                    el.textContent = randomStr;
                }
            }

            if (progress < 1) {
                requestAnimationFrame(scrambleStep);
            } else {
                // Guaranteed 100% real database value
                el.textContent = formattedActual;
            }
        }

        requestAnimationFrame(scrambleStep);
    });

    // 4. Hero Circular Progress Ring
    const progressRing = document.getElementById('cf-hero-progress-ring');
    if (progressRing) {
        const radius = 48;
        const circumference = 2 * Math.PI * radius; // ~301.59
        progressRing.style.strokeDasharray = `${circumference}`;
        progressRing.style.strokeDashoffset = `${circumference}`;

        const rate = progressRing.getAttribute('data-progress-rate');
        if (rate !== null && rate !== '' && rate !== 'none') {
            const percentage = Math.min(Math.max(parseInt(rate, 10) || 0, 0), 100);
            const offset = circumference - (percentage / 100) * circumference;

            if (isReducedMotion) {
                progressRing.style.strokeDashoffset = `${offset}`;
            } else {
                setTimeout(() => {
                    progressRing.style.strokeDashoffset = `${offset}`;
                }, 200);
            }
        }
    }

    // 5. Interactive Liquid Glass Calendar Component
    initGlassCalendar(dashboardData.calendar || {});

    // 6. Native SVG Trend Line Chart (Activity over 6 months)
    initTrendChart(dashboardData.trend || []);

    // 7. Native SVG Category Distribution Donut Chart
    initDonutChart(dashboardData.categories || []);
});

/**
 * 5. Interactive Liquid Glass Calendar
 */
function initGlassCalendar(calendarData) {
    const container = document.getElementById('cf-glass-calendar-container');
    if (!container) return;

    const scheduledHandovers = calendarData.handovers || [];

    // Parse scheduled dates map: 'YYYY-MM-DD' -> array of items
    const eventMap = {};
    scheduledHandovers.forEach(h => {
        if (h.handover_scheduled_at) {
            const d = h.handover_scheduled_at.split(' ')[0];
            if (!eventMap[d]) eventMap[d] = [];
            eventMap[d].push(h);
        }
    });

    const now = new Date();
    let currentYear = now.getFullYear();
    let currentMonth = now.getMonth(); // 0-indexed

    const monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    function renderCalendar(year, month) {
        const titleEl = document.getElementById('cf-calendar-month-year');
        if (titleEl) {
            titleEl.textContent = `${monthNames[month]} ${year}`;
        }

        const gridEl = document.getElementById('cf-calendar-days-grid');
        if (!gridEl) return;
        gridEl.innerHTML = '';

        const firstDayIndex = new Date(year, month, 1).getDay(); // 0 = Sun
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const prevDaysInMonth = new Date(year, month, 0).getDate();

        const today = new Date();
        const isCurrentMonth = (today.getFullYear() === year && today.getMonth() === month);
        const todayDate = today.getDate();

        // Previous month padding days
        for (let i = firstDayIndex - 1; i >= 0; i--) {
            const cell = document.createElement('div');
            cell.className = 'cf-glass-calendar-cell other-month';
            cell.textContent = prevDaysInMonth - i;
            gridEl.appendChild(cell);
        }

        // Current month days
        for (let day = 1; day <= daysInMonth; day++) {
            const cell = document.createElement('div');
            cell.className = 'cf-glass-calendar-cell';
            cell.textContent = day;

            const padMonth = String(month + 1).padStart(2, '0');
            const padDay = String(day).padStart(2, '0');
            const dateStr = `${year}-${padMonth}-${padDay}`;

            if (isCurrentMonth && day === todayDate) {
                cell.classList.add('is-today');
                cell.title = 'Hari Ini';
            }

            if (eventMap[dateStr] && eventMap[dateStr].length > 0) {
                cell.classList.add('has-event');
                cell.title = `${eventMap[dateStr].length} Jadwal Serah Terima Posko`;
            }

            // Click listener to show event popup/toast
            cell.addEventListener('click', () => {
                showCalendarScheduleInfo(dateStr, eventMap[dateStr]);
            });

            gridEl.appendChild(cell);
        }

        // Next month padding days to complete 35 or 42 grid cells
        const totalRendered = firstDayIndex + daysInMonth;
        const remaining = (totalRendered <= 35 ? 35 : 42) - totalRendered;
        for (let j = 1; j <= remaining; j++) {
            const cell = document.createElement('div');
            cell.className = 'cf-glass-calendar-cell other-month';
            cell.textContent = j;
            gridEl.appendChild(cell);
        }
    }

    function showCalendarScheduleInfo(dateStr, events) {
        const feedbackEl = document.getElementById('cf-calendar-selected-feedback');
        if (!feedbackEl) return;

        const dateObj = new Date(dateStr + 'T00:00:00');
        const formattedDate = dateObj.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });

        if (events && events.length > 0) {
            let html = `<div class="p-2.5 rounded-3 bg-light border border-info-subtle mt-2 small">
                <div class="fw-bold text-dark d-flex align-items-center gap-1">
                    <i class="bi bi-calendar-check text-primary"></i> ${formattedDate}
                </div>
                <div class="text-muted mt-1">Jadwal serah terima di Posko:</div>
                <ul class="mb-0 ps-3 mt-1">`;
            events.forEach(ev => {
                html += `<li><strong>${escapeHtml(ev.claim_number)}</strong> — ${escapeHtml(ev.item_title)}</li>`;
            });
            html += `</ul></div>`;
            feedbackEl.innerHTML = html;
        } else {
            feedbackEl.innerHTML = `<div class="p-2 rounded-3 bg-light border mt-2 small text-muted">
                <i class="bi bi-info-circle me-1"></i> Tidak ada jadwal serah terima khusus pada <strong>${formattedDate}</strong>. Posko buka reguler 08.00–16.00 WIB.
            </div>`;
        }
    }

    const prevBtn = document.getElementById('cf-calendar-prev');
    const nextBtn = document.getElementById('cf-calendar-next');

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar(currentYear, currentMonth);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar(currentYear, currentMonth);
        });
    }

    renderCalendar(currentYear, currentMonth);
}

/**
 * 6. Native SVG Trend Line Chart
 */
function initTrendChart(trendData) {
    const container = document.getElementById('cf-trend-chart-container');
    if (!container) return;

    if (!trendData || trendData.length === 0) {
        container.innerHTML = `<div class="p-4 text-center text-muted small">Belum ada riwayat aktivitas laporan.</div>`;
        return;
    }

    const width = 600;
    const height = 180;
    const padL = 40;
    const padR = 25;
    const padT = 25;
    const padB = 30;

    const chartW = width - padL - padR;
    const chartH = height - padT - padB;

    let maxVal = 0;
    trendData.forEach(d => {
        if (d.lost > maxVal) maxVal = d.lost;
        if (d.found > maxVal) maxVal = d.found;
    });

    // Provide comfortable head-room
    maxVal = Math.max(maxVal + 1, 4);

    const stepX = chartW / Math.max(trendData.length - 1, 1);

    // Calculate Coordinates
    const lostPoints = [];
    const foundPoints = [];

    trendData.forEach((d, i) => {
        const x = padL + i * stepX;
        const yLost = padT + chartH - (d.lost / maxVal) * chartH;
        const yFound = padT + chartH - (d.found / maxVal) * chartH;
        lostPoints.push({ x, y: yLost, val: d.lost, label: d.label });
        foundPoints.push({ x, y: yFound, val: d.found, label: d.label });
    });

    let svg = `<svg class="cf-svg-trend" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" role="img" aria-label="Grafik Tren Laporan Kehilangan dan Penemuan 6 Bulan Terakhir">`;

    // Gradients
    svg += `<defs>
        <linearGradient id="cf-grad-lost" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#B45309" stop-opacity="0.18" />
            <stop offset="100%" stop-color="#B45309" stop-opacity="0.0" />
        </linearGradient>
        <linearGradient id="cf-grad-found" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#2D7D46" stop-opacity="0.18" />
            <stop offset="100%" stop-color="#2D7D46" stop-opacity="0.0" />
        </linearGradient>
    </defs>`;

    // Horizontal Grid Lines & Y-Labels
    const gridRows = 4;
    for (let r = 0; r <= gridRows; r++) {
        const y = padT + (chartH / gridRows) * r;
        const gridVal = Math.round(maxVal - (maxVal / gridRows) * r);
        svg += `<line x1="${padL}" y1="${y}" x2="${width - padR}" y2="${y}" stroke="rgba(20, 40, 60, 0.07)" stroke-dasharray="3,3" />`;
        svg += `<text x="${padL - 10}" y="${y + 4}" font-size="10" fill="#94A3B8" text-anchor="end">${gridVal}</text>`;
    }

    // Area Paths
    const lostArea = `M ${lostPoints[0].x} ${padT + chartH} ` +
        lostPoints.map(p => `L ${p.x} ${p.y}`).join(' ') +
        ` L ${lostPoints[lostPoints.length - 1].x} ${padT + chartH} Z`;

    const foundArea = `M ${foundPoints[0].x} ${padT + chartH} ` +
        foundPoints.map(p => `L ${p.x} ${p.y}`).join(' ') +
        ` L ${foundPoints[foundPoints.length - 1].x} ${padT + chartH} Z`;

    svg += `<path d="${lostArea}" fill="url(#cf-grad-lost)" />`;
    svg += `<path d="${foundArea}" fill="url(#cf-grad-found)" />`;

    // Line Strokes
    const lostLine = lostPoints.map((p, i) => (i === 0 ? `M ${p.x} ${p.y}` : `L ${p.x} ${p.y}`)).join(' ');
    const foundLine = foundPoints.map((p, i) => (i === 0 ? `M ${p.x} ${p.y}` : `L ${p.x} ${p.y}`)).join(' ');

    svg += `<path d="${lostLine}" fill="none" stroke="#B45309" stroke-width="2.5" stroke-linecap="round" />`;
    svg += `<path d="${foundLine}" fill="none" stroke="#2D7D46" stroke-width="2.5" stroke-linecap="round" />`;

    // X-Axis Labels & Interactive Dots
    lostPoints.forEach((p, idx) => {
        svg += `<text x="${p.x}" y="${height - 8}" font-size="11" font-weight="500" fill="#64748B" text-anchor="middle">${p.label}</text>`;

        // Lost Dot
        svg += `<circle cx="${p.x}" cy="${p.y}" r="4" fill="#FFFFFF" stroke="#B45309" stroke-width="2.5">
            <title>${p.label}: ${p.val} Kehilangan</title>
        </circle>`;

        // Found Dot
        const fp = foundPoints[idx];
        svg += `<circle cx="${fp.x}" cy="${fp.y}" r="4" fill="#FFFFFF" stroke="#2D7D46" stroke-width="2.5">
            <title>${fp.label}: ${fp.val} Penemuan</title>
        </circle>`;
    });

    svg += `</svg>`;
    container.innerHTML = svg;
}

/**
 * 7. Native SVG Category Distribution Donut Chart
 */
function initDonutChart(categories) {
    const container = document.getElementById('cf-donut-chart-container');
    if (!container) return;

    if (!categories || categories.length === 0) {
        container.innerHTML = `
            <div class="text-center p-3 text-muted small w-100">
                <i class="bi bi-pie-chart text-secondary fs-3 d-block mb-1"></i>
                Belum ada data kategori barang yang dilaporkan.
            </div>`;
        return;
    }

    const totalCount = categories.reduce((sum, c) => sum + parseInt(c.count, 10), 0);
    const radius = 56;
    const circumference = 2 * Math.PI * radius; // ~351.85

    const colorPalette = [
        '#14283C', // Deep Navy
        '#A77B5B', // Warm Cognac
        '#2D7D46', // Success Green
        '#0369A1', // Info Blue
        '#B45309', // Warning Amber
        '#5E4B8B', // Purple
    ];

    let svg = `<svg class="cf-donut-svg" viewBox="0 0 160 160" role="img" aria-label="Grafik Donat Distribusi Kategori Barang">`;
    svg += `<circle cx="80" cy="80" r="${radius}" fill="none" stroke="rgba(20, 40, 60, 0.08)" stroke-width="18" />`;

    let accumulatedOffset = 0;
    categories.forEach((cat, index) => {
        const count = parseInt(cat.count, 10);
        const ratio = count / totalCount;
        const dashLength = ratio * circumference;
        const color = colorPalette[index % colorPalette.length];

        svg += `<circle cx="80" cy="80" r="${radius}" fill="none"
            stroke="${color}" stroke-width="18"
            stroke-dasharray="${dashLength} ${circumference - dashLength}"
            stroke-dashoffset="-${accumulatedOffset}">
            <title>${escapeHtml(cat.name)}: ${count} barang (${Math.round(ratio * 100)}%)</title>
        </circle>`;

        accumulatedOffset += dashLength;
    });

    svg += `</svg>`;

    // Center Overlay
    const centerHtml = `
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
            <div style="font-size: 1.5rem; font-weight: 700; color: #0C1926; line-height: 1;">${totalCount}</div>
            <div style="font-size: 0.68rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Barang</div>
        </div>`;

    // Legend List
    let legendHtml = `<ul class="cf-donut-legend-list flex-grow-1">`;
    categories.forEach((cat, idx) => {
        const count = parseInt(cat.count, 10);
        const percent = Math.round((count / totalCount) * 100);
        const color = colorPalette[idx % colorPalette.length];

        legendHtml += `
            <li class="cf-donut-legend-item">
                <span class="d-flex align-items-center gap-1.5 text-truncate" title="${escapeHtml(cat.name)}">
                    <span class="cf-chart-legend-dot" style="background: ${color};"></span>
                    <span class="text-truncate">${escapeHtml(cat.name)}</span>
                </span>
                <span class="fw-bold text-dark font-monospace">${count} <span class="text-muted fw-normal">(${percent}%)</span></span>
            </li>`;
    });
    legendHtml += `</ul>`;

    container.innerHTML = `
        <div class="position-relative d-inline-flex align-items-center justify-content-center">
            ${svg}
            ${centerHtml}
        </div>
        ${legendHtml}`;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

