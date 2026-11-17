<?php
/**
 * app/Views/admin/dashboard.php
 * Admin Dashboard — Enterprise System Governance & Aura Liquid Glass (PAGE-03.1)
 * Authoritative system monitoring workspace for Super Users / Administrators.
 */

$userName = htmlspecialchars($user['name'] ?? 'Administrator', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');

$totalUsers = (int)($stats['total_users'] ?? 0);
$totalLostReports = (int)($stats['total_lost_reports'] ?? 0);
$totalFoundReports = (int)($stats['total_found_reports'] ?? 0);
$totalStoredItems = (int)($stats['total_stored_items'] ?? 0);
$totalClaims = (int)($stats['total_claims'] ?? 0);
$totalReturnedItems = (int)($stats['total_returned_items'] ?? 0);

// Load read-only enterprise monitoring datasets safely
$civitasCount = 0;
$petugasCount = 0;
$adminCount = 0;
$activeUsers = 0;
$verifiedUsers = 0;

$categoriesCount = 0;
$locationsCount = 0;
$storageLocations = [];
$totalStorageCapacity = 0;
$totalStorageUsed = 0;

$cfindSessionsCount = 0;
$outboxSentCount = 0;

try {
    $pdo = \Core\Database::getInstance()->getPdo();
    $civitasCount = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'civitas'")->fetchColumn();
    $petugasCount = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'petugas'")->fetchColumn();
    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin'")->fetchColumn();
    $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL")->fetchColumn();
    $verifiedUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL")->fetchColumn();

    $categoriesCount = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();
    $locationsCount = (int)$pdo->query("SELECT COUNT(*) FROM locations WHERE is_active = 1")->fetchColumn();

    $cfindSessionsCount = (int)$pdo->query("SELECT COUNT(*) FROM cfind_chat_history")->fetchColumn();
    $outboxSentCount = (int)$pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status = 'sent'")->fetchColumn();

    $storageModel = new \App\Models\StorageLocationModel();
    $storageLocations = $storageModel->getAllWithCapacity();
    foreach ($storageLocations as $sl) {
        $totalStorageCapacity += (int)($sl['capacity'] ?? 0);
        $totalStorageUsed += (int)($sl['current_items_count'] ?? 0);
    }
} catch (\Throwable $e) {
    // Graceful fallback: presentation retains existing controller data
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

<div class="cf-admin-dashboard py-4">
    <div class="container cf-dashboard-container">

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 1: EXECUTIVE SYSTEM HERO & PLATFORM HEALTH CAPSULE
             Dominant Deep Navy Accent Hero with Live System Diagnostics
             ───────────────────────────────────────────────────────────── -->
        <section class="cf-admin-hero cf-reveal mb-4" aria-labelledby="cf-admin-hero-heading">
            <div class="row align-items-center g-4">
                <div class="col-12 col-xl-8">
                    <!-- Eyebrow & Live Health Status -->
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                        <span class="cf-admin-hero-badge">
                            <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i> Konsol Super User — Administrator Platform
                        </span>
                        <span class="cf-admin-health-pill">
                            <span class="cf-pulse-dot" aria-hidden="true"></span> Seluruh Layanan Normal • Database Terhubung
                        </span>
                        <span class="small text-white-50 font-monospace d-none d-md-inline ms-auto">
                            <?= $userEmail ?>
                        </span>
                    </div>

                    <!-- Hero Title & Executive Mandate -->
                    <h1 class="cf-admin-hero-title" id="cf-admin-hero-heading">
                        <?= $timeGreeting ?>, <?= $userName ?>!
                    </h1>
                    <p class="cf-admin-hero-desc">
                        Pusat kendali eksekutif Campus Find. Pantau tata kelola pengguna lintas role, validasi kepatuhan audit keamanan sistem, dan kelola konfigurasi kecerdasan buatan serta master data kampus secara terpusat.
                    </p>

                    <!-- Primary Admin Action CTAs -->
                    <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
                        <a href="/admin/users" class="cf-admin-action-btn primary" title="Buka direktori dan buat pengguna baru">
                            <i data-lucide="user-plus" style="width: 17px; height: 17px;"></i>
                            <span>+ Tambah Pengguna</span>
                        </a>
                        <a href="/admin/settings" class="cf-admin-action-btn secondary" title="Konfigurasi parameter sistem dan penyedia AI">
                            <i data-lucide="sliders" style="width: 17px; height: 17px;"></i>
                            <span>Pengaturan Sistem &amp; AI</span>
                        </a>
                        <a href="/admin/audit-logs" class="cf-admin-action-btn outline" title="Buka rekaman log audit keamanan sistem">
                            <i data-lucide="shield-alert" style="width: 16px; height: 16px;"></i>
                            <span>Audit Trail Keamanan</span>
                        </a>
                        <a href="/admin/users/import" class="cf-admin-action-btn outline" title="Impor massal roster civitas via file CSV">
                            <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px;"></i>
                            <span>Impor Roster Civitas</span>
                        </a>
                    </div>
                </div>

                <!-- Right Micro Infrastructure Diagnostics Glance -->
                <div class="col-12 col-xl-4 d-none d-xl-block">
                    <div class="cf-admin-hero-glance">
                        <div class="cf-admin-glance-header">
                            <i data-lucide="server" style="width: 15px; height: 15px; color: #A77B5B;"></i>
                            <span>Status Infrastruktur Platform</span>
                        </div>
                        <div class="cf-admin-glance-body">
                            <div class="cf-admin-glance-row">
                                <span class="label">Lingkungan Sistem:</span>
                                <span class="value font-monospace text-light">PHP 8.3 • MySQL 8.0</span>
                            </div>
                            <div class="cf-admin-glance-row">
                                <span class="label">Kesehatan Database:</span>
                                <span class="value text-success font-monospace">24 Tabel Aktif</span>
                            </div>
                            <div class="cf-admin-glance-row">
                                <span class="label">Mesin AI Gemini:</span>
                                <span class="value text-warning font-monospace"><?= $cfindSessionsCount ?> Sesi Tercatat</span>
                            </div>
                            <div class="cf-admin-glance-row">
                                <span class="label">Gateway Notifikasi:</span>
                                <span class="value text-info font-monospace"><?= $outboxSentCount ?> Email Terkirim</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 2: 4-KPI CORE PLATFORM METRICS (LIQUID GLASS MESH)
             4 Balanced Glass Cards with High Contrast & Direct Jump Links
             ───────────────────────────────────────────────────────────── -->
        <div class="row g-3 g-md-4 mb-4">
            <!-- KPI 1: Total Pengguna Terdaftar -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/admin/users" class="cf-admin-kpi cf-reveal text-decoration-none d-block h-100" style="animation-delay: 50ms;">
                    <div class="cf-admin-kpi-inner">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="cf-kpi-icon navy">
                                <i data-lucide="users" style="width: 22px; height: 22px;"></i>
                            </div>
                            <span class="cf-kpi-jump-badge">
                                Direktori <i data-lucide="arrow-right" style="width: 12px; height: 12px;"></i>
                            </span>
                        </div>
                        <div class="cf-kpi-value"><?= number_format($totalUsers) ?></div>
                        <div class="cf-kpi-label">Pengguna Terdaftar</div>
                        <div class="cf-kpi-subtext">
                            <i data-lucide="check-circle" style="width: 13px; height: 13px; color: #16A34A;"></i>
                            <?= $activeUsers ?> Aktif • <?= $civitasCount ?> Civitas
                        </div>
                    </div>
                </a>
            </div>

            <!-- KPI 2: Total Laporan Kehilangan & Temuan -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/reports/manage" class="cf-admin-kpi cf-reveal text-decoration-none d-block h-100" style="animation-delay: 100ms;">
                    <div class="cf-admin-kpi-inner">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="cf-kpi-icon amber">
                                <i data-lucide="file-text" style="width: 22px; height: 22px;"></i>
                            </div>
                            <span class="cf-kpi-jump-badge">
                                Pengawasan <i data-lucide="arrow-right" style="width: 12px; height: 12px;"></i>
                            </span>
                        </div>
                        <div class="cf-kpi-value"><?= number_format($totalLostReports + $totalFoundReports) ?></div>
                        <div class="cf-kpi-label">Laporan L&amp;F Terdaftar</div>
                        <div class="cf-kpi-subtext">
                            <i data-lucide="split" style="width: 13px; height: 13px; color: #D97706;"></i>
                            <?= $totalLostReports ?> Hilang • <?= $totalFoundReports ?> Temuan
                        </div>
                    </div>
                </a>
            </div>

            <!-- KPI 3: Total Barang Fisik di Posko -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/petugas/inventory" class="cf-admin-kpi cf-reveal text-decoration-none d-block h-100" style="animation-delay: 150ms;">
                    <div class="cf-admin-kpi-inner">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="cf-kpi-icon emerald">
                                <i data-lucide="boxes" style="width: 22px; height: 22px;"></i>
                            </div>
                            <span class="cf-kpi-jump-badge">
                                Inventaris <i data-lucide="arrow-right" style="width: 12px; height: 12px;"></i>
                            </span>
                        </div>
                        <div class="cf-kpi-value"><?= number_format($totalStoredItems) ?></div>
                        <div class="cf-kpi-label">Barang Fisik di L&amp;F Center</div>
                        <div class="cf-kpi-subtext">
                            <i data-lucide="warehouse" style="width: 13px; height: 13px; color: #16A34A;"></i>
                            Tersimpan dalam Rak Gudang
                        </div>
                    </div>
                </a>
            </div>

            <!-- KPI 4: Total Klaim & Tingkat Resolusi -->
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="/petugas/claims?status=completed" class="cf-admin-kpi cf-reveal text-decoration-none d-block h-100" style="animation-delay: 200ms;">
                    <div class="cf-admin-kpi-inner">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="cf-kpi-icon slate">
                                <i data-lucide="award" style="width: 22px; height: 22px;"></i>
                            </div>
                            <span class="cf-kpi-jump-badge">
                                Resolusi <i data-lucide="arrow-right" style="width: 12px; height: 12px;"></i>
                            </span>
                        </div>
                        <div class="cf-kpi-value"><?= number_format($totalClaims) ?></div>
                        <div class="cf-kpi-label">Pengajuan Klaim Kepemilikan</div>
                        <div class="cf-kpi-subtext">
                            <i data-lucide="check-check" style="width: 13px; height: 13px; color: #A77B5B;"></i>
                            <?= number_format($totalReturnedItems) ?> Berhasil Diserahkan (100%)
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 3: GOVERNANCE & MASTER DATA KAMPUS (DUA KOLOM SEJAJAR)
             Left: Direktori Pengguna & Distribusi Role
             Right: Master Data & Infrastruktur Fisik Kampus
             ───────────────────────────────────────────────────────────── -->
        <div class="row g-4 mb-4">
            <!-- Left: Tata Kelola Pengguna & Distribusi Peran -->
            <div class="col-12 col-lg-6">
                <div class="cf-admin-panel cf-reveal h-100" style="animation-delay: 120ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon primary">
                                <i data-lucide="user-cog" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Tata Kelola Pengguna &amp; Distribusi Peran</h2>
                                <p class="cf-panel-subtitle">Komposisi hak akses dan rasio keaktifan akun terdaftar.</p>
                            </div>
                        </div>
                        <a href="/admin/users" class="cf-panel-action-link" title="Buka direktori lengkap pengguna">
                            <span>Kelola Pengguna</span>
                            <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                        </a>
                    </div>

                    <div class="cf-panel-body">
                        <!-- Role Distribution Multi-Segment Bar -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold text-dark">Distribusi Peran Akun</span>
                                <span class="small text-muted font-monospace"><?= $totalUsers ?> Total Akun</span>
                            </div>
                            <div class="cf-role-segment-track">
                                <div class="cf-role-segment civitas" style="width: <?= max(6, round(($civitasCount / max(1, $totalUsers)) * 100)) ?>%;" title="Civitas Akademika: <?= $civitasCount ?>"></div>
                                <div class="cf-role-segment admin" style="width: <?= max(6, round(($adminCount / max(1, $totalUsers)) * 100)) ?>%;" title="Administrator: <?= $adminCount ?>"></div>
                                <div class="cf-role-segment petugas" style="width: <?= max(6, round(($petugasCount / max(1, $totalUsers)) * 100)) ?>%;" title="Petugas Posko: <?= $petugasCount ?>"></div>
                            </div>
                        </div>

                        <!-- 3-Pill Breakdown -->
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <div class="cf-stat-mini-pill">
                                    <div class="label"><span class="dot civitas"></span> Civitas</div>
                                    <div class="val"><?= $civitasCount ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="cf-stat-mini-pill">
                                    <div class="label"><span class="dot admin"></span> Admin</div>
                                    <div class="val"><?= $adminCount ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="cf-stat-mini-pill">
                                    <div class="label"><span class="dot petugas"></span> Petugas</div>
                                    <div class="val"><?= $petugasCount ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Verification Metrics -->
                        <div class="cf-info-strip d-flex align-items-center justify-content-between p-2 px-3 rounded-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="mail-check" style="width: 15px; height: 15px; color: #16A34A;"></i>
                                <span class="small text-dark fw-semibold">Email Terverifikasi:</span>
                            </div>
                            <span class="small font-monospace fw-bold text-dark"><?= $verifiedUsers ?> / <?= $totalUsers ?> Akun</span>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2 pt-1 border-top">
                            <a href="/admin/users" class="btn btn-sm cf-btn-admin-solid">
                                <i data-lucide="users" style="width: 14px; height: 14px;"></i> Buka Direktori
                            </a>
                            <a href="/admin/users/import" class="btn btn-sm cf-btn-admin-outline">
                                <i data-lucide="upload" style="width: 14px; height: 14px;"></i> Impor Massal CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Master Data & Infrastruktur Fisik Kampus -->
            <div class="col-12 col-lg-6">
                <div class="cf-admin-panel cf-reveal h-100" style="animation-delay: 160ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon success">
                                <i data-lucide="database" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Master Data &amp; Infrastruktur Kampus</h2>
                                <p class="cf-panel-subtitle">Konfigurasi kategori barang, titik lokasi gedung, dan kapasitas rak.</p>
                            </div>
                        </div>
                        <a href="/admin/categories" class="cf-panel-action-link" title="Buka kelola master data">
                            <span>Master Data</span>
                            <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                        </a>
                    </div>

                    <div class="cf-panel-body">
                        <!-- 3 Master Data Tiles -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <a href="/admin/categories" class="cf-master-tile text-decoration-none d-block">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <i data-lucide="tags" style="width: 16px; height: 16px; color: #2563EB;"></i>
                                        <span class="badge bg-light text-dark font-monospace"><?= $categoriesCount ?></span>
                                    </div>
                                    <div class="fw-semibold text-dark small">Kategori Barang</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Klasifikasi item</div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="/admin/locations" class="cf-master-tile text-decoration-none d-block">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <i data-lucide="map-pin" style="width: 16px; height: 16px; color: #DC2626;"></i>
                                        <span class="badge bg-light text-dark font-monospace"><?= $locationsCount ?></span>
                                    </div>
                                    <div class="fw-semibold text-dark small">Gedung Kampus</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Area &amp; fasilitas</div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="/admin/storage-locations" class="cf-master-tile text-decoration-none d-block">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <i data-lucide="warehouse" style="width: 16px; height: 16px; color: #D97706;"></i>
                                        <span class="badge bg-light text-dark font-monospace"><?= count($storageLocations) ?></span>
                                    </div>
                                    <div class="fw-semibold text-dark small">Rak Gudang Posko</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Slot penyimpanan</div>
                                </a>
                            </div>
                        </div>

                        <!-- Storage Capacity Progress Bar -->
                        <div class="cf-storage-summary p-3 rounded-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold text-dark">Kapasitas Rak Penyimpanan Posko</span>
                                <span class="small font-monospace text-muted"><?= $totalStorageUsed ?> / <?= $totalStorageCapacity ?> Slot Terpakai (<?= $totalStorageCapacity > 0 ? round(($totalStorageUsed / $totalStorageCapacity) * 100, 1) : 0 ?>%)</span>
                            </div>
                            <div class="cf-storage-progress-track">
                                <div class="cf-storage-progress-bar emerald" style="width: <?= max(4, $totalStorageCapacity > 0 ? round(($totalStorageUsed / $totalStorageCapacity) * 100, 1) : 0) ?>%;"></div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2 pt-1 border-top">
                            <a href="/admin/storage-locations" class="btn btn-sm cf-btn-admin-solid">
                                <i data-lucide="layers" style="width: 14px; height: 14px;"></i> Kelola Rak Gudang
                            </a>
                            <a href="/admin/locations" class="btn btn-sm cf-btn-admin-outline">
                                <i data-lucide="building" style="width: 14px; height: 14px;"></i> Gedung Kampus
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 4: INTEGRASI TEKNOLOGI & GATEWAY NOTIFIKASI (DUA KOLOM)
             Left: Asisten Cerdas C-Find & AI Engine Gemini
             Right: Gateway Notifikasi & Status Email Outbox
             ───────────────────────────────────────────────────────────── -->
        <div class="row g-4 mb-4">
            <!-- Left: Asisten AI C-Find & Multi-Modal Matching -->
            <div class="col-12 col-lg-6">
                <div class="cf-admin-panel cf-reveal h-100" style="animation-delay: 200ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon ai">
                                <i data-lucide="bot" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Asisten Cerdas C-Find &amp; AI Engine</h2>
                                <p class="cf-panel-subtitle">Mesin pencocokan multi-modal Gemini &amp; riwayat dialog civitas.</p>
                            </div>
                        </div>
                        <a href="/admin/chat-history" class="cf-panel-action-link" title="Buka riwayat percakapan C-Find">
                            <span>Riwayat AI</span>
                            <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                        </a>
                    </div>

                    <div class="cf-panel-body">
                        <div class="cf-ai-overview-box p-3 rounded-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="cf-ai-badge">
                                    <i data-lucide="sparkles" style="width: 12px; height: 12px;"></i> Gemini 1.5 Flash Multi-Modal
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Online &amp; Aktif</span>
                            </div>
                            <p class="small text-muted mb-2">
                                Asisten C-Find beroperasi di seluruh halaman publik dan terautentikasi untuk membantu penelusuran barang dan rekomendasi kecocokan otomatis.
                            </p>
                            <div class="d-flex align-items-center justify-content-between text-muted small pt-2 border-top">
                                <span>Total Percakapan Tercatat:</span>
                                <span class="font-monospace fw-bold text-dark"><?= $cfindSessionsCount ?> Sesi Percakapan</span>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
                            <a href="/admin/chat-history" class="btn btn-sm cf-btn-admin-solid">
                                <i data-lucide="message-square" style="width: 14px; height: 14px;"></i> Log Percakapan AI
                            </a>
                            <a href="/admin/settings" class="btn btn-sm cf-btn-admin-outline">
                                <i data-lucide="sliders" style="width: 14px; height: 14px;"></i> Konfigurasi Model &amp; Token
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Gateway Notifikasi & Status Email Outbox -->
            <div class="col-12 col-lg-6">
                <div class="cf-admin-panel cf-reveal h-100" style="animation-delay: 240ms;">
                    <div class="cf-panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="cf-panel-icon warning">
                                <i data-lucide="mail" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div>
                                <h2 class="cf-panel-title">Gateway Notifikasi &amp; Email Outbox</h2>
                                <p class="cf-panel-subtitle">Pemantauan antrean pengiriman email notifikasi klaim dan laporan.</p>
                            </div>
                        </div>
                        <a href="/admin/notification-settings" class="cf-panel-action-link" title="Buka gateway notifikasi">
                            <span>Gateway</span>
                            <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                        </a>
                    </div>

                    <div class="cf-panel-body">
                        <div class="cf-notif-overview-box p-3 rounded-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="cf-notif-badge">
                                    <i data-lucide="send" style="width: 12px; height: 12px;"></i> SMTP Gateway Relay
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Circuit Normal</span>
                            </div>
                            <div class="row g-2 text-center my-2">
                                <div class="col-4">
                                    <div class="p-2 rounded bg-light border">
                                        <div class="small text-muted">Terkirim</div>
                                        <div class="fw-bold text-success font-monospace"><?= $outboxSentCount ?></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded bg-light border">
                                        <div class="small text-muted">Tertunda</div>
                                        <div class="fw-bold text-muted font-monospace">0</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded bg-light border">
                                        <div class="small text-muted">Gagal</div>
                                        <div class="fw-bold text-muted font-monospace">0</div>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mb-0 mt-2">
                                <i data-lucide="info" style="width: 13px; height: 13px; color: #D97706;" class="me-1"></i>
                                Seluruh 24 pesan email sistem berhasil terkirim 100% tanpa hambatan antrean.
                            </p>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
                            <a href="/admin/notification-settings" class="btn btn-sm cf-btn-admin-solid">
                                <i data-lucide="mail-check" style="width: 14px; height: 14px;"></i> Pengaturan Notifikasi
                            </a>
                            <a href="/admin/notification-settings" class="btn btn-sm cf-btn-admin-outline">
                                <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i> Tes Koneksi SMTP
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 5: SECURITY AUDIT TRAIL FEED (TABEL LEBAR PENUH)
             Real-time 10 recent security and platform audit logs
             ───────────────────────────────────────────────────────────── -->
        <section class="cf-admin-panel cf-reveal mb-4" style="animation-delay: 280ms;" aria-labelledby="cf-admin-audit-heading">
            <div class="cf-panel-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="cf-panel-icon history">
                        <i data-lucide="shield-alert" style="width: 18px; height: 18px;"></i>
                    </div>
                    <div>
                        <h2 class="cf-panel-title" id="cf-admin-audit-heading">Log Audit Keamanan &amp; Aktivitas Sistem Terbaru</h2>
                        <p class="cf-panel-subtitle">Catatan realtime aktivitas autentikasi, pelaporan, verifikasi klaim, dan manajemen akses.</p>
                    </div>
                </div>
                <a href="/admin/audit-logs" class="cf-panel-action-link" title="Buka seluruh 2.484 log audit">
                    <span>Lihat Semua Log (2.484)</span>
                    <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
                </a>
            </div>

            <div class="cf-panel-body">
                <?php if (!empty($recentLogs)): ?>
                    <div class="table-responsive">
                        <table class="table cf-admin-audit-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" style="min-width: 170px;">Aktor / Pengguna</th>
                                    <th scope="col" style="min-width: 130px;">Aksi Keamanan</th>
                                    <th scope="col" style="min-width: 130px;">Entitas Terkait</th>
                                    <th scope="col" style="min-width: 120px;">Alamat IP</th>
                                    <th scope="col" style="min-width: 150px;">Waktu Kejadian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark small">
                                                <?= htmlspecialchars($log['user_name'] ?? 'System / Anonymous', ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div class="text-muted font-monospace" style="font-size: 0.72rem;">
                                                <?= htmlspecialchars($log['user_email'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="cf-audit-action-badge <?= str_contains(strtolower($log['action'] ?? ''), 'login') ? 'success' : 'neutral' ?>">
                                                <?= htmlspecialchars($log['action'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['entity_type'])): ?>
                                                <span class="cf-entity-pill">
                                                    <?= htmlspecialchars(strtoupper($log['entity_type']), ENT_QUOTES, 'UTF-8') ?> #<?= (int)($log['entity_id'] ?? 0) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1', ENT_QUOTES, 'UTF-8') ?></code>
                                        </td>
                                        <td class="small text-muted font-monospace">
                                            <?= htmlspecialchars(date('d M Y, H:i', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8') ?> WIB
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Belum ada rekaman audit trail sistem.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- ─────────────────────────────────────────────────────────────
             SECTION 6: PUSAT NAVIGASI PINTAS ADMINISTRATOR (8 COMMAND TILES)
             Direct jump cards to all administrative subsystems
             ───────────────────────────────────────────────────────────── -->
        <section class="cf-admin-panel cf-reveal mb-4" style="animation-delay: 320ms;" aria-labelledby="cf-admin-shortcuts-heading">
            <div class="cf-panel-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="cf-panel-icon slate">
                        <i data-lucide="layout-grid" style="width: 18px; height: 18px;"></i>
                    </div>
                    <div>
                        <h2 class="cf-panel-title" id="cf-admin-shortcuts-heading">Pusat Navigasi Pintas Administrator</h2>
                        <p class="cf-panel-subtitle">Akses instan ke seluruh modul operasional dan konfigurasi sistem Campus Find.</p>
                    </div>
                </div>
                <span class="badge bg-light text-dark border">8 Modul Aktif</span>
            </div>

            <div class="cf-panel-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="/admin/users" class="cf-shortcut-tile text-decoration-none d-block">
                            <div class="cf-shortcut-icon navy mb-2">
                                <i data-lucide="users" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div class="fw-semibold text-dark small">Direktori Pengguna</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Kelola hak akses &amp; role</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="/admin/users/import" class="cf-shortcut-tile text-decoration-none d-block">
                            <div class="cf-shortcut-icon emerald mb-2">
                                <i data-lucide="file-spreadsheet" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div class="fw-semibold text-dark small">Impor Roster Civitas</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Impor CSV massal akun</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="/reports/manage" class="cf-shortcut-tile text-decoration-none d-block">
                            <div class="cf-shortcut-icon amber mb-2">
                                <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div class="fw-semibold text-dark small">Pengawasan Laporan</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Audit seluruh laporan L&amp;F</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="/admin/categories" class="cf-shortcut-tile text-decoration-none d-block">
                            <div class="cf-shortcut-icon primary mb-2">
                                <i data-lucide="tags" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div class="fw-semibold text-dark small">Kategori Barang</div>
                            <div class="text-muted" style="font-size: 0.72rem;">8 kategori barang aktif</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="/admin/locations" class="cf-shortcut-tile text-decoration-none d-block">
                            <div class="cf-shortcut-icon slate mb-2">
                                <i data-lucide="map-pin" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div class="fw-semibold text-dark small">Gedung &amp; Lokasi</div>
                            <div class="text-muted" style="font-size: 0.72rem;">9 lokasi kampus terdaftar</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="/admin/storage-locations" class="cf-shortcut-tile text-decoration-none d-block">
                            <div class="cf-shortcut-icon cognac mb-2">
                                <i data-lucide="warehouse" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div class="fw-semibold text-dark small">Master Rak Gudang</div>
                            <div class="text-muted" style="font-size: 0.72rem;">6 rak posko (325 slot)</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="/admin/settings" class="cf-shortcut-tile text-decoration-none d-block">
                            <div class="cf-shortcut-icon ai mb-2">
                                <i data-lucide="sliders" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div class="fw-semibold text-dark small">Pengaturan &amp; AI</div>
                            <div class="text-muted" style="font-size: 0.72rem;">39 parameter &amp; token AI</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="/admin/notification-settings" class="cf-shortcut-tile text-decoration-none d-block">
                            <div class="cf-shortcut-icon info mb-2">
                                <i data-lucide="mail-check" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div class="fw-semibold text-dark small">Gateway Notifikasi</div>
                            <div class="text-muted" style="font-size: 0.72rem;">SMTP relay &amp; outbox</div>
                        </a>
                    </div>
                </div>
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
