<?php
/**
 * app/Views/dashboard/petugas.php
 * Petugas Dashboard — Operational Command Center & Aura Liquid Glass (PAGE-02.1)
 * High-performance, action-oriented workspace for Lost & Found Center Operators.
 */

$userName = htmlspecialchars($user['name'] ?? 'Petugas L&F', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');

$storedItems = (int)($stats['stored_items'] ?? 0);
$pendingClaims = (int)($stats['pending_claims'] ?? 0);
$unverifiedFound = (int)($stats['unverified_found'] ?? 0);
$storageLocationsCount = (int)($stats['storage_locations'] ?? 0);

// Load read-only operational datasets safely for triage presentation
$storageLocations = [];
$pendingClaimsList = [];
$pendingFoundList = [];
$approvedClaimsList = [];
$recentHistories = [];

try {
    $storageModel = new \App\Models\StorageLocationModel();
    $storageLocations = $storageModel->getAllWithCapacity();
} catch (\Throwable $e) {
    $storageLocations = [];
}

try {
    $claimModel = new \App\Models\ClaimModel();
    $pendingClaimsList = $claimModel->getClaimsForPetugas(['pending', 'under_review', 'need_more_info'], null, 5, 0);
    $approvedClaimsList = $claimModel->getClaimsForPetugas('approved', null, 5, 0);
} catch (\Throwable $e) {
    $pendingClaimsList = [];
    $approvedClaimsList = [];
}

try {
    $foundModel = new \App\Models\FoundReportModel();
    $pendingFoundList = $foundModel->getStaffReports('pending_handover', null, null, null, null, null, null, 5, 0);
} catch (\Throwable $e) {
    $pendingFoundList = [];
}

try {
    $pdo = \Core\Database::getInstance()->getPdo();
    $stmt = $pdo->query("
        SELECT sh.*, u.name AS actor_name 
        FROM status_histories sh 
        LEFT JOIN users u ON sh.changed_by_user_id = u.id 
        ORDER BY sh.id DESC 
        LIMIT 5
    ");
    if ($stmt) {
        $recentHistories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) {
    $recentHistories = [];
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

<div class="cf-petugas-dashboard py-4">
    <div class="container cf-dashboard-container">

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 1: OPERATIONAL HERO & SHIFT STATUS CAPSULE
             Dominant Slate Navy Accent Hero with Live Status & Quick Triage
             ───────────────────────────────────────────────────────────── -->
        <section class="cf-petugas-hero cf-reveal mb-4" aria-labelledby="cf-petugas-hero-heading">
            <div class="row align-items-center g-4">
                <div class="col-12 col-xl-8">
                    <!-- Eyebrow & Live Shift Capsule -->
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                        <span class="cf-petugas-hero-badge">
                            <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i> Panel Operasional Posko
                        </span>
                        <span class="cf-petugas-shift-pill">
                            <span class="cf-pulse-dot" aria-hidden="true"></span> Loket Posko Aktif • Petugas Bertugas
                        </span>
                        <span class="small text-white-50 font-monospace d-none d-md-inline ms-auto">
                            <?= $userEmail ?>
                        </span>
                    </div>

                    <!-- Hero Title & Operational Role -->
                    <h1 class="cf-petugas-hero-title" id="cf-petugas-hero-heading">
                        <?= $timeGreeting ?>, <?= $userName ?>!
                    </h1>
                    <p class="cf-petugas-hero-desc">
                        Pusat Komando Lost &amp; Found Center. Kelola penerimaan fisik barang temuan di meja piket, verifikasi bukti kepemilikan klaim secara cermat, dan pantau ketersediaan rak penyimpanan secara real-time.
                    </p>

                    <!-- Quick Primary Action CTAs -->
                    <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
                        <a href="/reports/manage?tab=found&status=pending_handover" class="cf-hero-action-btn primary" title="Proses penerimaan fisik barang temuan">
                            <i data-lucide="package-plus" style="width: 17px; height: 17px;"></i>
                            <span>+ Penerimaan Fisik (Intake)</span>
                        </a>
                        <a href="/petugas/claims?tab=action" class="cf-hero-action-btn secondary" title="Tinjau antrean klaim yang memerlukan verifikasi">
                            <i data-lucide="shield-alert" style="width: 17px; height: 17px;"></i>
                            <span>Verifikasi Klaim Terdepan</span>
                        </a>
                        <a href="/petugas/inventory" class="cf-hero-action-btn outline" title="Buka katalog barang fisik di posko">
                            <i data-lucide="boxes" style="width: 16px; height: 16px;"></i>
                            <span>Katalog Inventaris</span>
                        </a>
                        <a href="/petugas/storage-locations" class="cf-hero-action-btn outline" title="Buka manajemen kapasitas rak gudang">
                            <i data-lucide="warehouse" style="width: 16px; height: 16px;"></i>
                            <span>Rak Gudang</span>
                        </a>
                    </div>
                </div>

                <!-- Right Micro Operational Glance -->
                <div class="col-12 col-xl-4 d-none d-xl-block">
                    <div class="cf-petugas-hero-glance">
                        <div class="cf-hero-glance-header">
                            <i data-lucide="activity" style="width: 15px; height: 15px; color: #A77B5B;"></i>
                            <span>Status Ringkas Meja Piket</span>
                        </div>
                        <div class="cf-hero-glance-body">
                            <div class="cf-hero-glance-row">
                                <span class="label">Barang Tersimpan:</span>
                                <span class="value"><?= $storedItems ?> Item</span>
                            </div>
                            <div class="cf-hero-glance-row">
                                <span class="label">Antrean Klaim Masuk:</span>
                                <span class="value text-warning"><?= $pendingClaims ?> Menunggu</span>
                            </div>
                            <div class="cf-hero-glance-row">
                                <span class="label">Temuan Belum Intake:</span>
                                <span class="value text-info"><?= $unverifiedFound ?> Pending</span>
                            </div>
                            <div class="cf-hero-glance-row">
                                <span class="label">Total Rak Gudang:</span>
                                <span class="value text-success"><?= $storageLocationsCount ?> Rak Aktif</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 2: 4-KPI OPERATIONAL STAT MESH (LIQUID GLASS)
             4 Balanced Glass Cards with High Contrast & Direct Jump Links
             ───────────────────────────────────────────────────────────── -->
        <div class="row g-3 g-md-4 mb-4">
            <!-- KPI 1: Total Barang di Posko -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/petugas/inventory" class="cf-kpi-card cf-reveal text-decoration-none d-block h-100" style="animation-delay: 50ms;">
                    <div class="cf-kpi-inner">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="cf-kpi-icon emerald">
                                <i data-lucide="boxes" style="width: 22px; height: 22px;"></i>
                            </div>
                            <span class="cf-kpi-jump-badge">
                                Inventaris <i data-lucide="arrow-right" style="width: 12px; height: 12px;"></i>
                            </span>
                        </div>
                        <div class="cf-kpi-value text-dark"><?= $storedItems ?></div>
                        <div class="cf-kpi-label">Total Barang di Posko</div>
                        <div class="cf-kpi-subtext text-muted">
                            <i data-lucide="check-circle" style="width: 13px; height: 13px; color: #16A34A;"></i>
                            Tersimpan dalam Rak Fisik
                        </div>
                    </div>
                </a>
            </div>

            <!-- KPI 2: Klaim Perlu Verifikasi -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/petugas/claims?tab=action" class="cf-kpi-card cf-reveal text-decoration-none d-block h-100" style="animation-delay: 100ms;">
                    <div class="cf-kpi-inner">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="cf-kpi-icon amber">
                                <i data-lucide="shield-alert" style="width: 22px; height: 22px;"></i>
                            </div>
                            <span class="cf-kpi-jump-badge">
                                Tinjau <i data-lucide="arrow-right" style="width: 12px; height: 12px;"></i>
                            </span>
                        </div>
                        <div class="cf-kpi-value text-dark"><?= $pendingClaims ?></div>
                        <div class="cf-kpi-label">Klaim Perlu Verifikasi</div>
                        <div class="cf-kpi-subtext text-muted">
                            <i data-lucide="clock" style="width: 13px; height: 13px; color: #D97706;"></i>
                            Menunggu Tinjauan Bukti
                        </div>
                    </div>
                </a>
            </div>

            <!-- KPI 3: Temuan Menunggu Serah -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/reports/manage?tab=found&status=pending_handover" class="cf-kpi-card cf-reveal text-decoration-none d-block h-100" style="animation-delay: 150ms;">
                    <div class="cf-kpi-inner">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="cf-kpi-icon navy">
                                <i data-lucide="package-check" style="width: 22px; height: 22px;"></i>
                            </div>
                            <span class="cf-kpi-jump-badge">
                                Intake <i data-lucide="arrow-right" style="width: 12px; height: 12px;"></i>
                            </span>
                        </div>
                        <div class="cf-kpi-value text-dark"><?= $unverifiedFound ?></div>
                        <div class="cf-kpi-label">Temuan Menunggu Serah</div>
                        <div class="cf-kpi-subtext text-muted">
                            <i data-lucide="inbox" style="width: 13px; height: 13px; color: #2563EB;"></i>
                            Belum Tiba di Meja Posko
                        </div>
                    </div>
                </a>
            </div>

            <!-- KPI 4: Lokasi Rak Gudang Aktif -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/petugas/storage-locations" class="cf-kpi-card cf-reveal text-decoration-none d-block h-100" style="animation-delay: 200ms;">
                    <div class="cf-kpi-inner">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="cf-kpi-icon slate">
                                <i data-lucide="warehouse" style="width: 22px; height: 22px;"></i>
                            </div>
                            <span class="cf-kpi-jump-badge">
                                Kelola <i data-lucide="arrow-right" style="width: 12px; height: 12px;"></i>
                            </span>
                        </div>
                        <div class="cf-kpi-value text-dark"><?= $storageLocationsCount ?></div>
                        <div class="cf-kpi-label">Lokasi Rak Gudang Aktif</div>
                        <div class="cf-kpi-subtext text-muted">
                            <i data-lucide="layers" style="width: 13px; height: 13px; color: #475569;"></i>
                            Zona Penyimpanan Terdaftar
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 3: PRIORITY OPERATIONAL WORK QUEUES (DUA KOLOM SEJAJAR)
             Left: Antrean Verifikasi Klaim Masuk
             Right: Temuan Menunggu Intake Fisik
             ───────────────────────────────────────────────────────────── -->
        <div class="row g-4 mb-4">
            <!-- Left: Antrean Verifikasi Klaim Masuk -->
            <div class="col-12 col-lg-6">
                <div class="cf-petugas-panel cf-reveal h-100" style="animation-delay: 120ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon warning">
                                <i data-lucide="shield-alert" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Antrean Verifikasi Klaim Masuk</h2>
                                <p class="cf-panel-subtitle">Periksa jawaban rahasia dan bukti kepemilikan pelapor.</p>
                            </div>
                        </div>
                        <a href="/petugas/claims?tab=action" class="cf-panel-action-link" title="Buka seluruh antrean verifikasi klaim">
                            <span>Lihat Semua</span>
                            <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                        </a>
                    </div>

                    <div class="cf-panel-body">
                        <?php if (!empty($pendingClaimsList)): ?>
                            <div class="cf-operational-list">
                                <?php foreach ($pendingClaimsList as $claim): ?>
                                    <div class="cf-op-list-item">
                                        <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                                            <span class="cf-op-code">#<?= htmlspecialchars($claim['claim_number'] ?? 'CLM', ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="cf-status-badge <?= ($claim['status'] === 'under_review') ? 'info' : 'warning' ?>">
                                                <?= ($claim['status'] === 'under_review') ? 'Sedang Ditinjau' : 'Perlu Verifikasi' ?>
                                            </span>
                                        </div>
                                        <div class="cf-op-title mb-1">
                                            <?= htmlspecialchars($claim['item_title'] ?? 'Barang Tanpa Nama', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted small mt-2">
                                            <div class="d-flex align-items-center gap-1">
                                                <i data-lucide="user" style="width: 13px; height: 13px;"></i>
                                                <span><?= htmlspecialchars($claim['claimant_name'] ?? 'Pemohon', ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <a href="/petugas/claims/<?= (int)$claim['id'] ?>" class="btn btn-sm cf-op-btn-action">
                                                <i data-lucide="external-link" style="width: 13px; height: 13px;"></i> Tinjau Bukti
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- High-Aesthetic Zero-State -->
                            <div class="cf-zero-state py-4 text-center">
                                <div class="cf-zero-icon-wrapper emerald mb-3">
                                    <i data-lucide="shield-check" style="width: 28px; height: 28px; color: #16A34A;"></i>
                                </div>
                                <h3 class="cf-zero-title">Tidak Ada Klaim Menunggu Tindakan</h3>
                                <p class="cf-zero-desc">
                                    Seluruh klaim kepemilikan yang masuk saat ini telah selesai diverifikasi atau diproses. Tetap siaga untuk pengajuan klaim baru dari civitas.
                                </p>
                                <a href="/petugas/claims" class="btn btn-sm cf-btn-neutral-glass">
                                    <i data-lucide="archive" style="width: 14px; height: 14px;"></i> Buka Arsip Klaim Posko
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Temuan Menunggu Intake Fisik -->
            <div class="col-12 col-lg-6">
                <div class="cf-petugas-panel cf-reveal h-100" style="animation-delay: 160ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon primary">
                                <i data-lucide="package-plus" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Temuan Menunggu Intake Fisik</h2>
                                <p class="cf-panel-subtitle">Barang temuan dilaporkan civitas dan perlu diserahkan ke posko.</p>
                            </div>
                        </div>
                        <a href="/reports/manage?tab=found&status=pending_handover" class="cf-panel-action-link" title="Buka laporan temuan pending handover">
                            <span>Kelola Laporan</span>
                            <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                        </a>
                    </div>

                    <div class="cf-panel-body">
                        <?php if (!empty($pendingFoundList)): ?>
                            <div class="cf-operational-list">
                                <?php foreach ($pendingFoundList as $found): ?>
                                    <div class="cf-op-list-item">
                                        <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                                            <span class="cf-op-code">#<?= htmlspecialchars($found['report_number'] ?? 'FND', ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="cf-status-badge warning">Perlu Serah Fisik</span>
                                        </div>
                                        <div class="cf-op-title mb-1">
                                            <?= htmlspecialchars($found['title'] ?? 'Barang Temuan', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted small mt-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="d-inline-flex align-items-center gap-1">
                                                    <i data-lucide="user" style="width: 13px; height: 13px;"></i>
                                                    <?= htmlspecialchars($found['reporter_name'] ?? 'Penemu', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <span class="d-inline-flex align-items-center gap-1">
                                                    <i data-lucide="map-pin" style="width: 13px; height: 13px;"></i>
                                                    <?= htmlspecialchars($found['location_name'] ?? 'Area Kampus', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                            <a href="/petugas/intake/<?= (int)$found['id'] ?>" class="btn btn-sm cf-op-btn-action primary">
                                                <i data-lucide="box-arrow-in-down" style="width: 13px; height: 13px;"></i> Terima &amp; Simpan
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- High-Aesthetic Zero-State -->
                            <div class="cf-zero-state py-4 text-center">
                                <div class="cf-zero-icon-wrapper navy mb-3">
                                    <i data-lucide="inbox" style="width: 28px; height: 28px; color: #1E40AF;"></i>
                                </div>
                                <h3 class="cf-zero-title">Seluruh Temuan Telah Di-Intake</h3>
                                <p class="cf-zero-desc">
                                    Tidak ada laporan barang temuan yang tertunda serah terimanya ke meja posko saat ini. Seluruh barang temuan aktif telah tersimpan rapi di rak.
                                </p>
                                <a href="/reports/manage?tab=found" class="btn btn-sm cf-btn-neutral-glass">
                                    <i data-lucide="list-filter" style="width: 14px; height: 14px;"></i> Buka Katalog Semua Temuan
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 4: INTELLIGENCE & RESOLUTION HUB (DUA SEKSI KHUSUS)
             Left: Kandidat AI Match & Rekomendasi Cerdas
             Right: Klaim Disetujui (Siap Serah Terima Fisik ke Pemilik)
             ───────────────────────────────────────────────────────────── -->
        <div class="row g-4 mb-4">
            <!-- Left: Kandidat AI Match Review -->
            <div class="col-12 col-lg-6">
                <div class="cf-petugas-panel cf-reveal h-100" style="animation-delay: 200ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon ai">
                                <i data-lucide="sparkles" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Rekomendasi Kecocokan AI (Gemini)</h2>
                                <p class="cf-panel-subtitle">Kecocokan otomatis antara laporan kehilangan dan barang temuan.</p>
                            </div>
                        </div>
                        <a href="/reports/manage" class="cf-panel-action-link" title="Jelajahi laporan dan kandidat match">
                            <span>Jelajahi</span>
                            <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                        </a>
                    </div>

                    <div class="cf-panel-body">
                        <div class="cf-ai-intelligence-card p-3 mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="cf-ai-badge">
                                    <i data-lucide="sparkles" style="width: 12px; height: 12px;"></i> Gemini AI Multi-Modal Engine
                                </span>
                                <span class="text-muted small ms-auto">Aktif 24/7</span>
                            </div>
                            <p class="small text-muted mb-3">
                                Sistem secara otomatis menganalisis kemiripan visual, kesesuaian kategori, lokasi kejadian, dan teks deskripsi rahasia untuk merekomendasikan pencocokan barang kepada petugas posko.
                            </p>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <a href="/reports/manage?tab=lost" class="btn btn-sm cf-ai-action-btn">
                                    <i data-lucide="search" style="width: 13px; height: 13px;"></i> Laporan Kehilangan
                                </a>
                                <a href="/reports/manage?tab=found" class="btn btn-sm cf-ai-action-btn">
                                    <i data-lucide="package" style="width: 13px; height: 13px;"></i> Laporan Temuan
                                </a>
                            </div>
                        </div>

                        <div class="cf-tip-box d-flex align-items-center gap-2 p-2 px-3 rounded-3">
                            <i data-lucide="info" style="width: 15px; height: 15px; color: #A77B5B; flex-shrink: 0;"></i>
                            <span class="small text-muted">
                                Buka detail laporan mana pun untuk meninjau persentase kemiripan skor AI dan memberikan konfirmasi manual.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Klaim Disetujui (Siap Serah Terima Fisik) -->
            <div class="col-12 col-lg-6">
                <div class="cf-petugas-panel cf-reveal h-100" style="animation-delay: 240ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon success">
                                <i data-lucide="check-circle-2" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Klaim Disetujui (Siap Serah Terima)</h2>
                                <p class="cf-panel-subtitle">Pemilik dapat mengambil barang fisik di meja posko dengan verifikasi KTM/KTP.</p>
                            </div>
                        </div>
                        <a href="/petugas/claims?status=approved" class="cf-panel-action-link" title="Buka daftar klaim approved">
                            <span>Daftar Disetujui</span>
                            <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                        </a>
                    </div>

                    <div class="cf-panel-body">
                        <?php if (!empty($approvedClaimsList)): ?>
                            <div class="cf-operational-list">
                                <?php foreach ($approvedClaimsList as $approved): ?>
                                    <div class="cf-op-list-item">
                                        <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                                            <span class="cf-op-code">#<?= htmlspecialchars($approved['claim_number'] ?? 'CLM', ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="cf-status-badge success">Disetujui • Siap Diambil</span>
                                        </div>
                                        <div class="cf-op-title mb-1">
                                            <?= htmlspecialchars($approved['item_title'] ?? 'Barang', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted small mt-2">
                                            <div class="d-flex align-items-center gap-1">
                                                <i data-lucide="user-check" style="width: 13px; height: 13px;"></i>
                                                <span><?= htmlspecialchars($approved['claimant_name'] ?? 'Pemilik Sah', ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <a href="/petugas/claims/<?= (int)$approved['id'] ?>/handover" class="btn btn-sm cf-op-btn-action success">
                                                <i data-lucide="hand-metal" style="width: 13px; height: 13px;"></i> Serah Terima Fisik
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- High-Aesthetic Zero-State -->
                            <div class="cf-zero-state py-4 text-center">
                                <div class="cf-zero-icon-wrapper emerald mb-3">
                                    <i data-lucide="package-open" style="width: 28px; height: 28px; color: #16A34A;"></i>
                                </div>
                                <h3 class="cf-zero-title">Tidak Ada Barang Menunggu Pengambilan</h3>
                                <p class="cf-zero-desc">
                                    Saat ini tidak ada klaim berstatus disetujui yang menunggu serah terima di posko. Semua barang yang telah disetujui telah diambil oleh pemilik.
                                </p>
                                <a href="/petugas/claims?status=completed" class="btn btn-sm cf-btn-neutral-glass">
                                    <i data-lucide="history" style="width: 14px; height: 14px;"></i> Riwayat Serah Terima Selesai
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 5: MANAJEMEN GUDANG & STANDAR OPERASIONAL (SOP)
             Left: Utilisasi Kapasitas Rak Penyimpanan
             Right: Standar Operasional Prosedur (SOP Posko)
             ───────────────────────────────────────────────────────────── -->
        <div class="row g-4 mb-4">
            <!-- Left: Utilisasi Kapasitas Rak Penyimpanan -->
            <div class="col-12 col-lg-6">
                <div class="cf-petugas-panel cf-reveal h-100" style="animation-delay: 280ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon slate">
                                <i data-lucide="warehouse" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Kapasitas Rak &amp; Lemari Penyimpanan</h2>
                                <p class="cf-panel-subtitle">Monitor keterisian slot rak gudang di posko secara live.</p>
                            </div>
                        </div>
                        <a href="/petugas/storage-locations" class="cf-panel-action-link" title="Buka manajemen seluruh rak gudang">
                            <span>Kelola Rak</span>
                            <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                        </a>
                    </div>

                    <div class="cf-panel-body">
                        <?php if (!empty($storageLocations)): ?>
                            <div class="cf-storage-grid">
                                <?php foreach ($storageLocations as $loc): ?>
                                    <?php
                                    $currCount = (int)($loc['current_items_count'] ?? 0);
                                    $capacity = (int)($loc['capacity'] ?? 1);
                                    $percentage = (float)($loc['usage_percentage'] ?? round(($currCount / max(1, $capacity)) * 100, 1));
                                    $barClass = 'emerald';
                                    if ($percentage >= 85) {
                                        $barClass = 'danger';
                                    } elseif ($percentage >= 60) {
                                        $barClass = 'amber';
                                    }
                                    ?>
                                    <div class="cf-storage-item p-2 px-3 mb-2 rounded-3">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="cf-storage-code badge"><?= htmlspecialchars($loc['code'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="cf-storage-name small fw-semibold text-dark">
                                                    <?= htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                            <span class="cf-storage-count small font-monospace text-muted">
                                                <strong><?= $currCount ?></strong> / <?= $capacity ?>
                                            </span>
                                        </div>
                                        <div class="cf-storage-progress-track">
                                            <div class="cf-storage-progress-bar <?= $barClass ?>" style="width: <?= min(100, max(4, $percentage)) ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small">Tidak ada data rak penyimpanan yang aktif.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Standar Operasional Prosedur (SOP Posko) -->
            <div class="col-12 col-lg-6">
                <div class="cf-petugas-panel cf-reveal h-100" style="animation-delay: 320ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon info">
                                <i data-lucide="clipboard-list" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Standar Operasional Meja Posko</h2>
                                <p class="cf-panel-subtitle">Protokol resmi penanganan barang dan serah terima hak milik.</p>
                            </div>
                        </div>
                        <span class="badge bg-light text-dark border">SOP Resmi</span>
                    </div>

                    <div class="cf-panel-body">
                        <div class="cf-sop-timeline">
                            <div class="cf-sop-step d-flex gap-3 mb-3">
                                <div class="cf-sop-num">1</div>
                                <div>
                                    <div class="fw-semibold text-dark small mb-0.5">Pemeriksaan Fisik &amp; Intake Meja Posko</div>
                                    <div class="text-muted small">Pastikan barang temuan diterima langsung di meja piket, periksa kondisi fisik sesungguhnya, berikan kode item unik, dan tempatkan di rak yang tepat.</div>
                                </div>
                            </div>
                            <div class="cf-sop-step d-flex gap-3 mb-3">
                                <div class="cf-sop-num">2</div>
                                <div>
                                    <div class="fw-semibold text-dark small mb-0.5">Verifikasi Identitas &amp; Jawaban Rahasia</div>
                                    <div class="text-muted small">Wajib memvalidasi kartu identitas (KTM/KTP/SIM) dan mencocokkan ciri khusus atau bukti rahasia yang tidak diumumkan di publik sebelum klaim disetujui.</div>
                                </div>
                            </div>
                            <div class="cf-sop-step d-flex gap-3 mb-3">
                                <div class="cf-sop-num">3</div>
                                <div>
                                    <div class="fw-semibold text-dark small mb-0.5">Penyelesaian Berita Acara Serah Terima</div>
                                    <div class="text-muted small">Gunakan tombol 'Serah Terima Fisik' pada klaim yang telah disetujui untuk mencatat nomor identitas penerima dan melepaskan slot kapasitas rak gudang.</div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2 border-top">
                            <a href="/chats" class="cf-sop-action-btn">
                                <i data-lucide="message-square" style="width: 15px; height: 15px;"></i>
                                <span>Buka Ruang Diskusi Tiket Civitas</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 6: LOG AKTIVITAS OPERASIONAL TERKINI (AUDIT TRAIL FEED)
             Real-time status transitions from status_histories
             ───────────────────────────────────────────────────────────── -->
        <section class="cf-petugas-panel cf-reveal mb-4" style="animation-delay: 360ms;" aria-labelledby="cf-petugas-log-heading">
            <div class="cf-panel-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="cf-panel-icon history">
                        <i data-lucide="history" style="width: 18px; height: 18px;"></i>
                    </div>
                    <div>
                        <h2 class="cf-panel-title" id="cf-petugas-log-heading">Riwayat Aktivitas &amp; Transisi Status Terkini</h2>
                        <p class="cf-panel-subtitle">Pencatatan otomatis seluruh pergantian status barang, klaim, dan laporan di posko.</p>
                    </div>
                </div>
                <span class="badge bg-light text-dark border">Audit Trail</span>
            </div>

            <div class="cf-panel-body">
                <?php if (!empty($recentHistories)): ?>
                    <div class="table-responsive">
                        <table class="table cf-audit-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" style="min-width: 140px;">Waktu Kejadian</th>
                                    <th scope="col" style="min-width: 120px;">Entitas</th>
                                    <th scope="col" style="min-width: 170px;">Perubahan Status</th>
                                    <th scope="col" style="min-width: 140px;">Petugas Eksekutor</th>
                                    <th scope="col">Catatan Operasional</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentHistories as $log): ?>
                                    <tr>
                                        <td class="small text-muted font-monospace">
                                            <?= htmlspecialchars(date('d M Y, H:i', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td>
                                            <span class="cf-entity-pill">
                                                <?= htmlspecialchars(strtoupper($log['entity_type'] ?? 'ITEM'), ENT_QUOTES, 'UTF-8') ?> #<?= (int)($log['entity_id'] ?? 0) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="cf-trans-badge">
                                                <?= htmlspecialchars($log['old_status'] ?? 'awal', ENT_QUOTES, 'UTF-8') ?>
                                                <i data-lucide="arrow-right" style="width: 11px; height: 11px;"></i>
                                                <strong><?= htmlspecialchars($log['new_status'] ?? 'baru', ENT_QUOTES, 'UTF-8') ?></strong>
                                            </span>
                                        </td>
                                        <td class="small fw-semibold text-dark">
                                            <i data-lucide="user-check" style="width: 13px; height: 13px; color: #16A34A;" class="me-1"></i>
                                            <?= htmlspecialchars($log['actor_name'] ?? 'Petugas Posko', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= htmlspecialchars($log['notes'] ?: 'Transisi status sistem otomatis.', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Belum ada riwayat aktivitas operasional yang tercatat.</p>
                <?php endif; ?>
            </div>
        </section>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>
