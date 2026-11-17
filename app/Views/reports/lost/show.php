<?php
/**
 * app/Views/reports/lost/show.php
 * Detail Laporan Barang Hilang — dengan pemisahan akses data privat vs publik,
 * kontrol verifikasi Petugas & Admin, AI Possible Match, dan riwayat status (Prompt 33B)
 */

$userRole = $user['role_name'] ?? 'tamu';
$isAdmin  = ($userRole === 'admin');
$isOfficer = ($userRole === 'petugas');
$canManageReport = ($isOfficer || $isAdmin);
$isCivitas = ($userRole === 'civitas');
?>

<div class="container py-4">

    <?php if ($isCivitas): ?>
        <!-- Standalone Transparent Page Header for Civitas (PAGE-01.2A.3) -->
        <?php
        $stBadge = match ($report['status']) {
            'published'     => 'cf-badge cf-badge-lost',
            'investigating' => 'cf-badge cf-badge-warning',
            'found_matched' => 'cf-badge cf-badge-info',
            'closed'        => 'cf-badge cf-badge-neutral',
            'cancelled'     => 'cf-badge cf-badge-neutral',
            default         => 'cf-badge cf-badge-neutral',
        };
        $statusBadge = '<span class="' . $stBadge . ' fs-6 px-3 py-2"><i class="bi bi-search"></i> ' . ucfirst($report['status']) . '</span>';
        $headerConfig = [
            'eyebrow'     => 'Detail Laporan Kehilangan',
            'title'       => $report['title'],
            'icon'        => 'bi bi-search',
            'description' => 'Nomor Laporan: ' . htmlspecialchars($report['report_number']) . ' • Dilaporkan oleh ' . htmlspecialchars($report['reporter_name']) . ' pada ' . date('d F Y, H:i', strtotime($report['created_at'])) . ' WIB',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/dashboard'],
                ['label' => 'Barang Hilang', 'url' => '/reports/lost'],
                ['label' => htmlspecialchars($report['report_number'])],
            ],
            'badge'       => $statusBadge,
        ];
        require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
        ?>
    <?php else: ?>
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="/">Beranda</a></li>
                <?php if ($canManageReport): ?>
                    <li class="breadcrumb-item"><a href="/reports/manage?tab=lost">Manajemen Laporan</a></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="/reports/lost">Barang Hilang</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($report['report_number']) ?></li>
            </ol>
        </nav>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Report Details -->
        <div class="col-lg-8">
            <div class="cf-card p-4 p-md-5 mb-4">

                <!-- Header Info -->
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <span class="cf-badge cf-badge-neutral font-monospace mb-1">
                            <?= htmlspecialchars($report['report_number']) ?>
                        </span>
                        <<?= $isCivitas ? 'h2 class="cf-section-h2' : 'h1 class="h3' ?> fw-bold text-dark mb-1">
                            <?= htmlspecialchars($report['title']) ?>
                        </<?= $isCivitas ? 'h2' : 'h1' ?>>
                        <div class="text-muted small">
                            Dilaporkan oleh <strong><?= htmlspecialchars($report['reporter_name']) ?></strong> &bull; <?= date('d F Y, H:i', strtotime($report['created_at'])) ?> WIB
                        </div>
                    </div>
                    <?php
                    $stBadge = match ($report['status']) {
                        'published'     => 'cf-badge cf-badge-lost',
                        'investigating' => 'cf-badge cf-badge-warning',
                        'matched'       => 'cf-badge cf-badge-claim',
                        'claimed'       => 'cf-badge cf-badge-neutral',
                        'resolved'      => 'cf-badge cf-badge-found',
                        'closed', 'cancelled' => 'cf-badge cf-badge-neutral',
                        default         => 'cf-badge cf-badge-neutral',
                    };
                    ?>
                    <span class="<?= $stBadge ?> fs-6 px-3 py-2">
                        <i class="bi bi-search me-1"></i> <?= \App\Models\LostReportModel::getStatusLabel($report['status']) ?>
                    </span>
                </div>

                <hr class="my-4">

                <!-- Image Preview (jika ada) -->
                <?php if (!empty($report['primary_image'])): ?>
                    <div class="mb-4 text-center p-2 rounded-3 bg-light border">
                        <?php if (\App\Middleware\AuthMiddleware::isLoggedIn()): ?>
                            <img src="<?= htmlspecialchars($report['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="<?= htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                 class="img-fluid rounded-3" 
                                 style="max-height: 400px; object-fit: contain;">
                        <?php else: ?>
                            <div class="py-4 text-muted">
                                <i class="bi bi-shield-lock text-primary fs-1 d-block mb-2"></i>
                                <p class="mb-1 fw-semibold text-dark">Foto barang hanya dapat dilihat oleh civitas akademika terdaftar.</p>
                                <p class="small text-muted mb-3">Silakan masuk ke akun Anda untuk melihat foto barang.</p>
                                <a href="/login" class="btn btn-sm btn-primary px-3 py-2 fw-semibold">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Attribute Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="cf-attribute-box">
                            <span class="cf-attribute-label">Kategori Barang</span>
                            <div class="cf-attribute-value"><i class="bi bi-tag me-1 text-primary"></i><?= htmlspecialchars($report['category_name']) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="cf-attribute-box">
                            <span class="cf-attribute-label">Waktu Terakhir Terlihat</span>
                            <div class="cf-attribute-value"><i class="bi bi-calendar-event me-1 text-primary"></i><?= date('d M Y', strtotime($report['lost_date'])) ?> <?= !empty($report['lost_time']) ? '(' . substr($report['lost_time'], 0, 5) . ' WIB)' : '' ?></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="cf-attribute-box">
                            <span class="cf-attribute-label">Lokasi Terakhir Terlihat</span>
                            <div class="cf-attribute-value"><i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars($report['location_name']) ?></div>
                            <?php if (!empty($report['location_detail'])): ?>
                                <div class="text-muted small mt-1">&rarr; <?= htmlspecialchars($report['location_detail']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Deskripsi Umum (Publik) -->
                <div class="mb-4">
                    <h2 class="h6 fw-bold text-dark mb-2">Deskripsi Barang (Informasi Publik)</h2>
                    <div class="p-3 rounded-3 bg-white border text-dark" style="line-height: 1.7; white-space: pre-line;">
                        <?= htmlspecialchars($report['description']) ?>
                    </div>
                </div>

                <!-- Informasi Identitas Pelapor (Khusus Petugas / Admin) -->
                <?php if ($canAccessPrivate && $isStaff): ?>
                    <div class="mb-4 p-3 rounded-3 bg-primary-subtle border border-primary-subtle">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-person-lines-fill text-primary fs-5"></i>
                            <h3 class="h6 fw-bold text-primary mb-0">Identitas Pelapor (Verifikasi Petugas)</h3>
                        </div>
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <span class="text-muted">Nama:</span> <strong><?= htmlspecialchars($report['reporter_name']) ?></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">NIM / Identitas:</span> <strong><?= htmlspecialchars($report['reporter_identity'] ?? '-') ?></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">Email Kampus:</span> <strong><?= htmlspecialchars($report['reporter_email']) ?></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">Saluran Komunikasi:</span> <strong class="text-primary"><i class="bi bi-chat-dots-fill me-1"></i>Chat Resmi Internal Campus Find</strong>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Informasi Sensitif / Privat -->
                <div class="mb-4">
                    <?php if ($canAccessPrivate): ?>
                        <!-- Unlocked Private Box for Owner, Petugas, and Admin -->
                        <div class="p-4 rounded-3" style="background: rgba(236, 253, 245, 0.9); border: 1px solid rgba(167, 243, 208, 0.9);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-lock-fill text-success fs-5"></i>
                                    <h3 class="h6 fw-bold text-success mb-0">Informasi Sensitif &amp; Ciri Rahasia (Data Privat)</h3>
                                </div>
                                <span class="cf-badge cf-badge-found">
                                    <?= $isOwner ? 'Pemilik Laporan' : 'Petugas / Admin' ?>
                                </span>
                            </div>
                            <p class="text-muted small mb-2">
                                🔒 Informasi ini <strong>hanya dapat dilihat oleh Anda dan Petugas Lost &amp; Found</strong> untuk verifikasi keaslian barang saat serah terima.
                            </p>
                            <div class="bg-white p-3 rounded-3 border text-dark small" style="line-height:1.6; white-space: pre-line;">
                                <?= !empty($report['private_details']) ? htmlspecialchars($report['private_details']) : '<span class="text-muted fst-italic">Tidak ada detail rahasia yang diisi saat pembuatan laporan.</span>' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Locked Box for General Public / Other Users -->
                        <div class="p-3 rounded-3 text-center" style="background: rgba(241, 245, 249, 0.8); border: 1px dashed var(--cf-border);">
                            <i class="bi bi-shield-lock text-muted fs-4 d-block mb-1"></i>
                            <div class="fw-semibold text-dark small">Informasi Verifikasi Rahasia Dilindungi</div>
                            <div class="text-muted small" style="font-size:0.75rem;">
                                Detail nomor serial / ciri rahasia disembunyikan untuk menjaga keamanan verifikasi dan mencegah klaim palsu.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Bar -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top">
                    <div>
                        <?php if ($canManageReport): ?>
                            <a href="/reports/manage?tab=lost" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                <i class="bi bi-arrow-left me-1"></i> Kembali ke Manajemen Laporan
                            </a>
                        <?php else: ?>
                            <a href="/reports/lost" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                <i class="bi bi-arrow-left me-1"></i> Kembali ke Katalog
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <!-- Pintasan Chat Terikat Laporan (Prompt 34D) -->
                        <?php if ($isOwner && ($user['role_name'] ?? '') === 'civitas'): ?>
                            <a href="/chats/report/lost/<?= (int) $report['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                                <i class="bi bi-chat-dots-fill me-1"></i> Tanya Petugas
                            </a>
                        <?php elseif (($user['role_name'] ?? '') === 'petugas'): ?>
                            <a href="/chats/report/lost/<?= (int) $report['id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                                <i class="bi bi-chat-dots-fill me-1"></i> Hubungi Pelapor
                            </a>
                        <?php endif; ?>

                        <!-- Petugas & Admin: Ubah Status Pemeriksaan -->
                        <?php if ($canManageReport): ?>
                            <button type="button" class="btn btn-warning btn-sm text-dark fw-semibold rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-update-status">
                                <i class="bi bi-arrow-repeat me-1"></i> Ubah Status Laporan
                            </button>
                        <?php endif; ?>

                        <!-- Pemilik Laporan: Edit -->
                        <?php if ($isOwner): ?>
                            <a href="/reports/lost/<?= $report['id'] ?>/edit" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                <i class="bi bi-pencil me-1"></i> Edit Laporan
                            </a>
                            <form action="/reports/lost/<?= $report['id'] ?>/delete" method="POST" class="d-inline" id="form-delete-report">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" 
                                        class="btn btn-danger btn-sm rounded-pill px-3" 
                                        data-cf-confirm="Apakah Anda yakin ingin membatalkan/menghapus laporan kehilangan ini?" 
                                        data-cf-title="Konfirmasi Hapus Laporan"
                                        data-cf-variant="danger"
                                        data-cf-confirm-text="Hapus Laporan">
                                    <i class="bi bi-trash me-1"></i> Hapus
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- HANYA Admin: Hapus Laporan Bermasalah / Spam -->
                        <?php if ($isAdmin && !$isOwner): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modal-admin-delete">
                                <i class="bi bi-shield-x me-1"></i> Hapus Laporan (Admin Moderasi)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Riwayat Status Section (Status Histories) -->
            <?php if (!empty($histories)): ?>
                <div class="cf-card mb-4">
                    <div class="card-body p-4">
                        <h3 class="h6 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-clock-history text-primary"></i> Riwayat Transisi Status Laporan
                        </h3>
                        <div class="timeline ps-2 border-start border-2 border-primary-subtle ms-2">
                            <?php foreach ($histories as $h): ?>
                                <div class="mb-3 ps-3 position-relative">
                                    <div class="position-absolute rounded-circle bg-primary" 
                                         style="width: 10px; height: 10px; left: -21px; top: 6px;"></div>
                                    <div class="d-flex justify-content-between align-items-baseline">
                                        <span class="fw-semibold text-dark small">
                                            Status diubah ke: <span class="cf-badge cf-badge-neutral"><?= htmlspecialchars(ucfirst($h['new_status'])) ?></span>
                                        </span>
                                        <span class="text-muted" style="font-size: 0.75rem;">
                                            <?= date('d M Y, H:i', strtotime($h['created_at'])) ?> WIB
                                        </span>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        Oleh: <strong><?= htmlspecialchars($h['changed_by_name'] ?? 'Petugas / Sistem') ?></strong>
                                    </div>
                                    <?php if (!empty($h['notes'])): ?>
                                        <div class="p-2 bg-light rounded mt-1 small text-dark fst-italic">
                                            "<?= htmlspecialchars($h['notes']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar / AI Match & Contact Info -->
        <div class="col-lg-4">
            <!-- Panel Klaim Terkait (Prompt 34F: Detail laporan menampilkan klaim terkait) -->
            <?php if ($canManageReport): ?>
                <div class="cf-card mb-4">
                    <div class="card-body p-4">
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-1">
                            <i class="bi bi-shield-check text-primary"></i> Klaim Terkait (<?= count($relatedClaims ?? []) ?>)
                        </h3>
                        <?php if (!empty($relatedClaims)): ?>
                            <div class="list-group list-group-flush small">
                                <?php foreach ($relatedClaims as $cl): ?>
                                    <a href="/petugas/claims/<?= (int) $cl['id'] ?>" class="list-group-item list-group-item-action px-0 py-2 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-semibold text-primary font-monospace"><?= htmlspecialchars($cl['claim_number']) ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;">Oleh: <?= htmlspecialchars($cl['claimant_name'] ?? 'Civitas') ?></div>
                                        </div>
                                        <span class="cf-badge cf-badge-neutral"><?= \App\Models\ClaimModel::getStatusLabel($cl['status']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-0">Belum ada klaim yang terhubung dengan laporan kehilangan ini.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!empty($relatedClaims)): ?>
                <div class="cf-card mb-4">
                    <div class="card-body p-4">
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-1">
                            <i class="bi bi-shield-check text-primary"></i> Status Klaim Anda
                        </h3>
                        <div class="list-group list-group-flush small">
                            <?php foreach ($relatedClaims as $cl): ?>
                                <a href="/claims/<?= (int) $cl['id'] ?>" class="list-group-item list-group-item-action px-0 py-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold text-primary font-monospace"><?= htmlspecialchars($cl['claim_number']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Diajukan: <?= date('d M Y', strtotime($cl['created_at'])) ?></div>
                                    </div>
                                    <span class="cf-badge cf-badge-neutral"><?= \App\Models\ClaimModel::getStatusLabel($cl['status']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Possible Match AI Card (Khusus Petugas & Admin / Owner) -->
            <?php if ($canManageReport || $isOwner): ?>
                <div class="cf-card cf-card-subtle mb-4 overflow-hidden" style="border-top: 4px solid var(--cf-ai-purple) !important;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h3 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                                <i class="bi bi-cpu-fill text-purple"></i> Possible Match (AI)
                            </h3>
                            <span class="cf-badge cf-badge-ai small">NVIDIA NIM</span>
                        </div>
                        <p class="text-muted small mb-3">
                            Kandidat barang temuan yang dipindai sistem kecerdasan buatan berdasarkan kesamaan kategori, deskripsi, dan lokasi.
                        </p>

                        <!-- DISCLAIMER RESMI WAJIB -->
                        <div class="alert alert-warning border-0 p-2 rounded-3 small mb-3 d-flex align-items-start gap-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-6 flex-shrink-0 mt-1"></i>
                            <div style="font-size: 0.78rem; line-height: 1.4;">
                                <strong>Pemberitahuan Petugas:</strong> Kandidat kecocokan AI adalah indikasi rekomendasi sistem dan <u>BUKAN merupakan bukti sah kepemilikan</u>. Petugas wajib melakukan verifikasi fisik dan bukti rahasia secara mandiri.
                            </div>
                        </div>

                        <?php if (!empty($candidates)): ?>
                            <div class="list-group list-group-flush mb-3">
                                <?php foreach (array_slice($candidates, 0, 3) as $cItem): ?>
                                    <div class="list-group-item px-0 py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="me-2">
                                                <a href="/reports/found/<?= $cItem['found_report_id'] ?>" class="text-decoration-none fw-semibold small text-dark d-block">
                                                    <?= htmlspecialchars($cItem['found_title'] ?? 'Barang Temuan') ?>
                                                </a>
                                                <span class="text-muted font-monospace" style="font-size:0.75rem;">
                                                    <?= htmlspecialchars($cItem['found_report_number'] ?? '') ?>
                                                </span>
                                            </div>
                                            <span class="cf-badge cf-badge-found small">
                                                <?= round((float)($cItem['score'] ?? 0)) ?>% Cocok
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small fst-italic mb-3">
                                Belum ada kandidat barang temuan yang dipindai secara otomatis.
                            </p>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <a href="/ai-match/lost/<?= (int)$report['id'] ?>" class="btn btn-sm text-white rounded-pill shadow-sm" style="background-color: #6f42c1;">
                                <i class="bi bi-columns-gap me-1"></i> <?= !empty($candidates) ? 'Bandingkan Barang (' . count($candidates) . ')' : 'Buka Workspace AI Matching' ?>
                            </a>
                            <form action="/ai-match/lost/<?= (int)$report['id'] ?>/run" method="POST" class="ai-match-scan-form" data-report-id="<?= (int)$report['id'] ?>" data-report-type="lost">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn-outline-purple btn-sm rounded-pill w-100">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Pindai Ulang AI
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Card for other civitas -->
            <div class="cf-card p-4 mb-4">
                <h3 class="h6 fw-bold text-dark mb-2">
                    <i class="bi bi-lightbulb text-warning me-1"></i> Menemukan Barang Ini?
                </h3>
                <p class="text-muted small mb-3">
                    Jika Anda menemukan barang dengan ciri-ciri serupa, silakan serahkan ke Lost &amp; Found Center kampus atau catat laporan temuan Anda.
                </p>
                <div class="d-grid gap-2">
                    <a href="/reports/found/create" class="btn btn-outline-primary btn-sm rounded-pill">
                        <i class="bi bi-plus-circle me-1"></i> Buat Laporan Temuan
                    </a>
                </div>
            </div>

            <!-- Lost & Found Center Location Card -->
            <div class="cf-card p-4">
                <h3 class="h6 fw-bold text-dark mb-2">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> Posko Lost &amp; Found Center
                </h3>
                <p class="small text-muted mb-2">
                    Pusat Pengamanan &amp; Verifikasi Barang Kampus:
                </p>
                <div class="bg-light p-3 rounded-3 border small">
                    <strong>Gedung Rektorat Kampus</strong><br>
                    <span class="text-muted">Lantai 1, Ruang Piket Layanan Terpadu</span><br>
                    <span class="text-muted"><i class="bi bi-clock me-1"></i>Senin – Jumat, 08.00 – 16.00 WIB</span>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal Ubah Status Pemeriksaan (Khusus Petugas & Admin) -->
<?php if ($canManageReport): ?>
    <div class="modal fade" id="modal-update-status" tabindex="-1" aria-labelledby="modalStatusLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <form action="/reports/lost/<?= $report['id'] ?>/status" method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark" id="modalStatusLabel">
                            <i class="bi bi-arrow-repeat text-warning me-1"></i> Ubah Status Laporan Kehilangan
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body py-3">
                        <p class="text-muted small mb-3">
                            Pembaruan status akan dicatat pada riwayat resmi laporan dan memicu notifikasi pembaruan ke pemilik laporan.
                        </p>

                        <div class="mb-3">
                            <label for="select-new-status" class="form-label small fw-semibold text-dark">Status Baru</label>
                            <select name="status" id="select-new-status" class="form-select" required>
                                <option value="published" <?= $report['status'] === 'published' ? 'selected' : '' ?>>
                                    Laporan Baru (Published)
                                </option>
                                <option value="investigating" <?= $report['status'] === 'investigating' ? 'selected' : '' ?>>
                                    Sedang Diperiksa Petugas (Investigating)
                                </option>
                                <option value="matched" <?= $report['status'] === 'matched' ? 'selected' : '' ?>>
                                    Terindikasi Cocok (Matched)
                                </option>
                                <option value="resolved" <?= $report['status'] === 'resolved' ? 'selected' : '' ?>>
                                    Selesai / Ditemukan (Resolved)
                                </option>
                                <option value="closed" <?= $report['status'] === 'closed' ? 'selected' : '' ?>>
                                    Ditutup (Closed)
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="textarea-status-notes" class="form-label small fw-semibold text-dark">Catatan Internal Perubahan Status (Wajib)</label>
                            <textarea name="notes" id="textarea-status-notes" class="form-control" rows="3" 
                                      placeholder="Contoh: Petugas sedang mencocokkan dengan barang temuan di Posko Gudang A..." 
                                      required minlength="5"></textarea>
                            <div class="form-text small">Catatan ini akan tersimpan permanen di riwayat pemeriksaan.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Admin Moderasi: Hapus Laporan Bermasalah / Spam (HANYA Admin) -->
<?php if ($isAdmin && !$isOwner): ?>
    <div class="modal fade" id="modal-admin-delete" tabindex="-1" aria-labelledby="modalAdminDeleteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <form action="/admin/reports/lost/<?= $report['id'] ?>/delete" method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold text-danger" id="modalAdminDeleteLabel">
                            <i class="bi bi-shield-x me-1"></i> Hapus Laporan Bermasalah / Spam
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="alert alert-danger border-0 small rounded-3 mb-3">
                            <strong>Perhatian Administrator:</strong> Tindakan ini akan menghapus data laporan kehilangan <strong><?= htmlspecialchars($report['report_number']) ?></strong> secara permanen. Tindakan ini hanya boleh dilakukan untuk laporan spam, duplikasi ilegal, atau pelanggaran etika.
                        </div>

                        <div class="mb-3">
                            <label for="admin-delete-reason" class="form-label small fw-semibold text-dark">Alasan Penghapusan (Wajib dicatat di Audit Log)</label>
                            <textarea name="deletion_reason" id="admin-delete-reason" class="form-control" rows="3" 
                                      placeholder="Tuliskan alasan spesifik (misal: Laporan spam berulang / foto melanggar etika)..." 
                                      required minlength="5"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger btn-sm rounded-pill px-4 shadow-sm">
                            Hapus Permanen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
