<?php
/**
 * app/Views/dashboard/civitas.php
 * Civitas Dashboard â€” Clean Editorial Ã— Subtle Ambient Liquid Glass (PAGE-01)
 * Authoritative Reference Dashboard for CampusFind
 */

$userName = htmlspecialchars($user['name'] ?? 'Civitas', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');

$heroActiveReports = (int)($hero['active_reports'] ?? 0);
$heroClaimsInProcess = (int)($hero['claims_in_process'] ?? 0);
$heroReturnedItems = (int)($hero['returned_items'] ?? 0);
$recoveryRate = $hero['recovery_rate'] ?? null;

$kpiActiveLost = (int)($kpis['active_lost'] ?? 0);
$kpiMyFound = (int)($kpis['my_found'] ?? 0);
$kpiClaimsInProcess = (int)($kpis['claims_in_process'] ?? 0);
$kpiReturnedItems = (int)($kpis['returned_items'] ?? 0);

$recentActivities = $activities ?? [];
$poskoInfo = $posko ?? [
    'name'     => 'Pusat Layanan Lost & Found Center',
    'location' => 'Gedung Rektorat Lt. 1 (Ruang Piket Layanan Terpadu)',
    'hours'    => 'Senin â€“ Jumat: 08.00 â€“ 16.00 WIB',
    'notes'    => 'Penyerahan barang temuan fisik & verifikasi pengambilan barang.',
];

// Helper to resolve activity badge
function getCivitasActivityBadge(string $type, string $status): array {
    return match ($type) {
        'lost' => match ($status) {
            'published'     => ['class' => 'cf-badge-warning', 'label' => 'Mencari'],
            'investigating' => ['class' => 'cf-badge-info', 'label' => 'Pemeriksaan'],
            'matched'       => ['class' => 'cf-badge-ai', 'label' => 'Potensi Cocok'],
            'claimed'       => ['class' => 'cf-badge-info', 'label' => 'Diklaim'],
            'resolved'      => ['class' => 'cf-badge-success', 'label' => 'Selesai / Ditemukan'],
            'cancelled'     => ['class' => 'cf-badge-neutral', 'label' => 'Dibatalkan'],
            'expired'       => ['class' => 'cf-badge-neutral', 'label' => 'Kedaluwarsa'],
            default         => ['class' => 'cf-badge-neutral', 'label' => ucfirst($status)],
        },
        'found' => match ($status) {
            'published'             => ['class' => 'cf-badge-found', 'label' => 'Dipublikasikan'],
            'pending_handover'      => ['class' => 'cf-badge-warning', 'label' => 'Perlu Serah Terima'],
            'handed_over_to_center' => ['class' => 'cf-badge-info', 'label' => 'Di Posko'],
            'verified'              => ['class' => 'cf-badge-info', 'label' => 'Terverifikasi'],
            'claimed'               => ['class' => 'cf-badge-claim', 'label' => 'Dalam Klaim'],
            'completed'             => ['class' => 'cf-badge-success', 'label' => 'Selesai Diserahkan'],
            'cancelled'             => ['class' => 'cf-badge-neutral', 'label' => 'Dibatalkan'],
            default                 => ['class' => 'cf-badge-neutral', 'label' => ucfirst($status)],
        },
        'claim' => match ($status) {
            'pending'               => ['class' => 'cf-badge-warning', 'label' => 'Menunggu Review'],
            'under_review'          => ['class' => 'cf-badge-info', 'label' => 'Sedang Ditinjau'],
            'need_more_info'        => ['class' => 'cf-badge-warning', 'label' => 'Perlu Info'],
            'approved'              => ['class' => 'cf-badge-success', 'label' => 'Klaim Disetujui'],
            'rejected'              => ['class' => 'cf-badge-danger', 'label' => 'Klaim Ditolak'],
            'completed'             => ['class' => 'cf-badge-success', 'label' => 'Barang Diambil'],
            'cancelled'             => ['class' => 'cf-badge-neutral', 'label' => 'Dibatalkan'],
            default                 => ['class' => 'cf-badge-neutral', 'label' => ucfirst($status)],
        },
        default => ['class' => 'cf-badge-neutral', 'label' => ucfirst($status)],
    };
}
// Time-based greeting helper
$currentHour = (int)date('H');
if ($currentHour >= 4 && $currentHour < 11) {
    $timeGreeting = 'Selamat pagi';
} elseif ($currentHour >= 11 && $currentHour < 15) {
    $timeGreeting = 'Selamat siang';
} elseif ($currentHour >= 15 && $currentHour < 18) {
    $timeGreeting = 'Selamat sore';
} else {
    $timeGreeting = 'Selamat malam';
}
?>

<div class="cf-civitas-dashboard py-4">
    <div class="container cf-dashboard-container cf-workspace-wide">

        <!-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
             ROW 1: HERO & GLASS CALENDAR
             â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <div class="row g-4 mb-4">
            <!-- Left: Dominant Deep Navy Hero Card -->
            <div class="col-12 col-lg-8">
                <section class="cf-hero-card cf-reveal" aria-labelledby="cf-hero-heading">
                    <div>
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-1">
                            <span class="cf-hero-badge">
                                <i data-lucide="graduation-cap" style="width: 14px; height: 14px;"></i> Civitas Akademika
                            </span>
                            <span class="small text-white-50 font-monospace">
                                <?= $userEmail ?>
                            </span>
                        </div>

                        <h1 class="cf-hero-title" id="cf-hero-heading" style="color: #FFFFFF !important;">
                            <?= $timeGreeting ?>, <?= $userName ?>!
                        </h1>
                        <p class="cf-hero-desc">
                            Pantau status barang hilang, laporkan temuan baru di area kampus, atau cek verifikasi klaim kepemilikan Anda secara transparan dan aman.
                        </p>

                        <!-- Three Real Micro-Metrics + Progress Ring -->
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 my-3">
                            <div class="cf-hero-metrics mb-0">
                                <div class="cf-hero-metric-item">
                                    <div class="cf-hero-metric-val" data-count-target="<?= $heroActiveReports ?>">
                                        <?= $heroActiveReports ?>
                                    </div>
                                    <div class="cf-hero-metric-lbl">Laporan Aktif</div>
                                </div>
                                <div class="cf-hero-metric-item">
                                    <div class="cf-hero-metric-val" data-count-target="<?= $heroClaimsInProcess ?>">
                                        <?= $heroClaimsInProcess ?>
                                    </div>
                                    <div class="cf-hero-metric-lbl">Klaim Diproses</div>
                                </div>
                                <div class="cf-hero-metric-item">
                                    <div class="cf-hero-metric-val" data-count-target="<?= $heroReturnedItems ?>">
                                        <?= $heroReturnedItems ?>
                                    </div>
                                    <div class="cf-hero-metric-lbl">Barang Kembali</div>
                                </div>
                            </div>

                            <!-- Circular Progress Ring (Tingkat Resolusi) -->
                            <div class="cf-hero-ring-wrapper">
                                <svg class="cf-hero-ring-svg" viewBox="0 0 116 116" role="img" aria-label="Indikator Tingkat Resolusi">
                                    <circle class="cf-hero-ring-bg" cx="58" cy="58" r="48" />
                                    <circle class="cf-hero-ring-fg" id="cf-hero-progress-ring" cx="58" cy="58" r="48"
                                            data-progress-rate="<?= $recoveryRate !== null ? $recoveryRate : 'none' ?>" />
                                </svg>
                                <div class="cf-hero-ring-center">
                                    <div class="cf-hero-ring-val">
                                        <?= $recoveryRate !== null ? $recoveryRate . '%' : 'â€”' ?>
                                    </div>
                                    <div class="cf-hero-ring-lbl">
                                        <?= $recoveryRate !== null ? 'Resolusi' : 'Belum Ada Data' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Primary Action Button -->
                    <div class="d-flex flex-wrap align-items-center gap-3 pt-2">
                        <a href="/reports/lost/create" class="cf-hero-cta-btn">
                            <i data-lucide="plus-circle" style="width: 17px; height: 17px;"></i>
                            <span>Lapor Barang Hilang</span>
                        </a>
                        <a href="/reports/found/create" class="btn btn-outline-light btn-sm rounded-pill px-3 py-2" style="border-color: rgba(255,255,255,0.25); min-height: 44px; display: inline-flex; align-items: center;">
                            <i data-lucide="package-plus" class="me-1.5" style="width: 17px; height: 17px;"></i> Lapor Barang Temuan
                        </a>
                    </div>
                </section>
            </div>

            <!-- Right: Interactive Liquid Glass Calendar -->
            <div class="col-12 col-lg-4">
                <section class="cf-glass-calendar cf-reveal" id="cf-glass-calendar-container" aria-label="Kalender Aktivitas dan Jadwal Posko">
                    <div>
                        <!-- Header with Month Title and Navigation -->
                        <div class="cf-glass-calendar-header">
                            <h2 class="cf-glass-calendar-title" id="cf-calendar-month-year">
                                <?= date('F Y') ?>
                            </h2>
                            <div class="cf-glass-calendar-nav">
                                <button type="button" class="cf-glass-calendar-btn" id="cf-calendar-prev" aria-label="Bulan Sebelumnya">
                                    <i data-lucide="chevron-left" style="width: 16px; height: 16px;"></i>
                                </button>
                                <button type="button" class="cf-glass-calendar-btn" id="cf-calendar-next" aria-label="Bulan Berikutnya">
                                    <i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Weekday Labels -->
                        <div class="cf-glass-calendar-weekdays" aria-hidden="true">
                            <span>Min</span>
                            <span>Sen</span>
                            <span>Sel</span>
                            <span>Rab</span>
                            <span>Kam</span>
                            <span>Jum</span>
                            <span>Sab</span>
                        </div>

                        <!-- Dynamic Days Grid -->
                        <div class="cf-glass-calendar-grid" id="cf-calendar-days-grid" role="grid">
                            <!-- Populated dynamically by civitas-dashboard.js -->
                        </div>

                        <!-- Schedule Feedback Box -->
                        <div id="cf-calendar-selected-feedback"></div>
                    </div>

                    <!-- Calendar Footer -->
                    <div class="cf-glass-calendar-footer">
                        <span><i class="bi bi-dot text-primary fs-5 align-middle"></i> Titik biru: Jadwal Posko</span>
                        <a href="/civitas/my-claims" class="text-decoration-none text-dark fw-semibold small">
                            Klaim Saya &rarr;
                        </a>
                    </div>
                </section>
            </div>
        </div>

        <!-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
             ROW 2: FOUR NEUTRAL KPI CARDS
             â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <div class="row g-3 mb-4">
            <!-- KPI 1: Active Lost Reports -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/civitas/my-reports" class="text-decoration-none text-reset d-block h-100">
                    <div class="cf-kpi-card cf-reveal">
                        <div>
                            <div class="cf-kpi-top">
                                <span class="cf-badge cf-badge-warning">Sedang Mencari</span>
                                <div class="cf-kpi-icon-wrap lost">
                                    <i data-lucide="search" style="width: 20px; height: 20px;"></i>
                                </div>
                            </div>
                            <div class="cf-kpi-val" data-count-target="<?= $kpiActiveLost ?>">
                                <?= $kpiActiveLost ?>
                            </div>
                            <div class="cf-kpi-lbl">Laporan Hilang Aktif</div>
                        </div>
                        <div class="cf-kpi-sub d-flex align-items-center justify-content-between pt-2 border-top">
                            <span>Status dalam pencarian</span>
                            <i data-lucide="arrow-right" class="text-muted" style="width: 15px; height: 15px;"></i>
                        </div>
                    </div>
                </a>
            </div>

            <!-- KPI 2: Reported Found Items -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/civitas/my-reports" class="text-decoration-none text-reset d-block h-100">
                    <div class="cf-kpi-card cf-reveal">
                        <div>
                            <div class="cf-kpi-top">
                                <span class="cf-badge cf-badge-found">Temuan Anda</span>
                                <div class="cf-kpi-icon-wrap found">
                                    <i data-lucide="package" style="width: 20px; height: 20px;"></i>
                                </div>
                            </div>
                            <div class="cf-kpi-val" data-count-target="<?= $kpiMyFound ?>">
                                <?= $kpiMyFound ?>
                            </div>
                            <div class="cf-kpi-lbl">Barang Temuan Dilaporkan</div>
                        </div>
                        <div class="cf-kpi-sub d-flex align-items-center justify-content-between pt-2 border-top">
                            <span>Bantuan Anda bagi sesama</span>
                            <i data-lucide="arrow-right" class="text-muted" style="width: 15px; height: 15px;"></i>
                        </div>
                    </div>
                </a>
            </div>

            <!-- KPI 3: Claims in Process -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/civitas/my-claims" class="text-decoration-none text-reset d-block h-100">
                    <div class="cf-kpi-card cf-reveal">
                        <div>
                            <div class="cf-kpi-top">
                                <span class="cf-badge cf-badge-info">Verifikasi</span>
                                <div class="cf-kpi-icon-wrap claim">
                                    <i data-lucide="shield-check" style="width: 20px; height: 20px;"></i>
                                </div>
                            </div>
                            <div class="cf-kpi-val" data-count-target="<?= $kpiClaimsInProcess ?>">
                                <?= $kpiClaimsInProcess ?>
                            </div>
                            <div class="cf-kpi-lbl">Klaim Dalam Proses</div>
                        </div>
                        <div class="cf-kpi-sub d-flex align-items-center justify-content-between pt-2 border-top">
                            <span>Pemeriksaan oleh petugas</span>
                            <i data-lucide="arrow-right" class="text-muted" style="width: 15px; height: 15px;"></i>
                        </div>
                    </div>
                </a>
            </div>

            <!-- KPI 4: Successfully Returned Items -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/civitas/my-claims" class="text-decoration-none text-reset d-block h-100">
                    <div class="cf-kpi-card cf-reveal">
                        <div>
                            <div class="cf-kpi-top">
                                <span class="cf-badge cf-badge-success">Berhasil Pulih</span>
                                <div class="cf-kpi-icon-wrap returned">
                                    <i data-lucide="check-circle-2" style="width: 20px; height: 20px;"></i>
                                </div>
                            </div>
                            <div class="cf-kpi-val" data-count-target="<?= $kpiReturnedItems ?>">
                                <?= $kpiReturnedItems ?>
                            </div>
                            <div class="cf-kpi-lbl">Barang Berhasil Kembali</div>
                        </div>
                        <div class="cf-kpi-sub d-flex align-items-center justify-content-between pt-2 border-top">
                            <span>Telah diterima kembali</span>
                            <i data-lucide="arrow-right" class="text-muted" style="width: 15px; height: 15px;"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
             ROW 3: QUICK ACTIONS PILL BAR
             â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <div class="mb-4 cf-reveal">
            <nav class="cf-quick-actions-bar" aria-label="Aksi Cepat Civitas">
                <a href="/search" class="cf-quick-action-pill">
                    <i data-lucide="search" style="width: 17px; height: 17px;"></i>
                    <span>Cari Barang Temuan</span>
                </a>
                <a href="/reports/lost/create" class="cf-quick-action-pill">
                    <i data-lucide="alert-circle" style="width: 17px; height: 17px;"></i>
                    <span>Lapor Barang Hilang</span>
                </a>
                <a href="/reports/found/create" class="cf-quick-action-pill">
                    <i data-lucide="package-plus" style="width: 17px; height: 17px;"></i>
                    <span>Lapor Barang Temuan</span>
                </a>
                <a href="/civitas/my-claims" class="cf-quick-action-pill">
                    <i data-lucide="shield-check" style="width: 17px; height: 17px;"></i>
                    <span>Daftar Klaim Saya</span>
                </a>
            </nav>
        </div>

        <!-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
             ROW 4: ANALYTICS (MAIN ACTIVITY TREND & CATEGORY DONUT)
             â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <div class="row g-4 mb-4">
            <!-- Left: Main Activity Trend (6 Months) -->
            <div class="col-12 col-lg-8">
                <div class="cf-analytics-card cf-reveal">
                    <div class="cf-analytics-header">
                        <div>
                            <h2 class="cf-analytics-title">Tren Aktivitas Laporan</h2>
                            <div class="cf-analytics-sub">Riwayat pelaporan kehilangan dan temuan Anda (6 Bulan Terakhir)</div>
                        </div>
                        <div class="cf-chart-legend">
                            <span class="d-flex align-items-center">
                                <span class="cf-chart-legend-dot" style="background: #B45309;"></span> Kehilangan
                            </span>
                            <span class="d-flex align-items-center">
                                <span class="cf-chart-legend-dot" style="background: #2D7D46;"></span> Penemuan
                            </span>
                        </div>
                    </div>

                    <!-- SVG Trend Chart Container -->
                    <div id="cf-trend-chart-container" class="cf-svg-chart-wrap">
                        <!-- Rendered by civitas-dashboard.js -->
                    </div>
                </div>
            </div>

            <!-- Right: Category Distribution Donut Chart -->
            <div class="col-12 col-lg-4">
                <div class="cf-analytics-card cf-reveal">
                    <div class="cf-analytics-header">
                        <div>
                            <h2 class="cf-analytics-title">Distribusi Kategori</h2>
                            <div class="cf-analytics-sub">Proporsi barang yang Anda laporkan</div>
                        </div>
                    </div>

                    <!-- SVG Donut Chart Container -->
                    <div id="cf-donut-chart-container" class="cf-donut-wrapper">
                        <!-- Rendered by civitas-dashboard.js -->
                    </div>
                </div>
            </div>
        </div>

        <!-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
             ROW 5: RECENT ACTIVITY STREAM & POSKO OPERATIONAL CENTER
             â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <div class="row g-4">
            <!-- Left: Recent Activity Stream -->
            <div class="col-12 col-lg-8">
                <div class="cf-activity-card cf-reveal">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div>
                            <h2 class="cf-analytics-title">Aktivitas Terkini</h2>
                            <div class="cf-analytics-sub">5 pembaruan status laporan dan klaim terbaru Anda</div>
                        </div>
                        <a href="/civitas/my-reports" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            Lihat Semua Laporan
                        </a>
                    </div>

                    <?php if (!empty($recentActivities)): ?>
                        <ul class="cf-activity-list" role="list">
                            <?php foreach ($recentActivities as $act): 
                                $b = getCivitasActivityBadge($act['type'], $act['status']);
                                $iconClass = match ($act['type']) {
                                    'lost'  => 'lost bi-search',
                                    'found' => 'found bi-box-seam',
                                    'claim' => 'claim bi-shield-check',
                                    default => 'bi-file-text',
                                };
                                $typeLabel = match ($act['type']) {
                                    'lost'  => 'Laporan Kehilangan',
                                    'found' => 'Laporan Temuan',
                                    'claim' => 'Pengajuan Klaim',
                                    default => 'Aktivitas',
                                };
                                $createdTime = date('d M Y, H:i', strtotime($act['created_at']));
                            ?>
                                <li>
                                    <a href="<?= htmlspecialchars($act['url'], ENT_QUOTES, 'UTF-8') ?>" class="cf-activity-item">
                                        <div class="cf-activity-left">
                                            <div class="cf-activity-icon <?= $iconClass ?>">
                                                <i class="bi <?= explode(' ', $iconClass)[1] ?? 'bi-file-text' ?>"></i>
                                            </div>
                                            <div class="cf-activity-details">
                                                <div class="cf-activity-title text-truncate">
                                                    <?= htmlspecialchars($act['title'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <div class="cf-activity-meta">
                                                    <span class="fw-semibold text-secondary"><?= $typeLabel ?></span>
                                                    <span>&bull;</span>
                                                    <span class="font-monospace text-muted"><?= htmlspecialchars($act['ref_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span>&bull;</span>
                                                    <span><?= $createdTime ?> WIB</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="cf-badge <?= $b['class'] ?>">
                                                <?= $b['label'] ?>
                                            </span>
                                            <i class="bi bi-chevron-right text-muted small"></i>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <?php
                            $icon = 'bi-inbox';
                            $title = 'Belum Ada Aktivitas Terkini';
                            $message = 'Anda belum memiliki laporan kehilangan, temuan, atau klaim aktif. Silakan buat laporan baru jika kehilangan atau menemukan barang.';
                            $actionUrl = '/reports/lost/create';
                            $actionText = 'Lapor Barang Hilang';
                            $actionIcon = 'bi-plus-lg';
                            $actionClass = 'btn-primary btn-sm';
                            require BASE_PATH . '/app/Views/partials/empty_state.php';
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Posko Lost & Found Center Service Card -->
            <div class="col-12 col-lg-4">
                <aside class="cf-posko-card cf-reveal" aria-label="Informasi Posko Fisik Lost and Found">
                    <span class="cf-posko-badge">
                        <i class="bi bi-building"></i> Posko Layanan Fisik
                    </span>
                    <h2 class="h5 fw-bold text-dark mb-2">
                        <?= htmlspecialchars($poskoInfo['name'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <p class="text-muted small mb-3">
                        <?= htmlspecialchars($poskoInfo['notes'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <div class="cf-posko-info-block">
                        <div class="d-flex align-items-start gap-2.5 mb-2.5">
                            <i class="bi bi-geo-alt-fill text-danger fs-5 mt-0.5"></i>
                            <div>
                                <div class="fw-bold text-dark small">Lokasi Posko</div>
                                <div class="text-secondary small"><?= htmlspecialchars($poskoInfo['location'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-2.5">
                            <i class="bi bi-clock-fill text-primary fs-5 mt-0.5"></i>
                            <div>
                                <div class="fw-bold text-dark small">Jam Operasional</div>
                                <div class="text-secondary small"><?= htmlspecialchars($poskoInfo['hours'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="extra-small text-muted" style="font-size: 0.72rem;">Sabtu, Minggu &amp; Libur Nasional: Tutup</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-top">
                        <a href="/search?type=found" class="btn btn-outline-primary btn-sm w-100 rounded-pill py-2" style="min-height: 44px; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                            <i class="bi bi-boxes"></i>
                            <span>Telusuri Inventaris Posko</span>
                        </a>
                    </div>
                </aside>
            </div>
        </div>

    </div>
</div>

<!-- Structured Client-Side Data for Native SVG Charts & Calendar -->
<script id="cf-civitas-data" type="application/json">
<?= json_encode([
    'calendar'   => $calendar ?? [],
    'trend'      => $trend ?? [],
    'categories' => $categories ?? [],
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>
</script>

<!-- Civitas Dashboard Script -->
<script src="/js/civitas-dashboard.js?v=<?= file_exists(BASE_PATH . '/public/js/civitas-dashboard.js') ? filemtime(BASE_PATH . '/public/js/civitas-dashboard.js') : time() ?>"></script>

