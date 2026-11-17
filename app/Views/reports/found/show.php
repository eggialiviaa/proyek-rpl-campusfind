<?php
/**
 * app/Views/reports/found/show.php
 * Detail Laporan Barang Ditemukan (Liquid Glass)
 */
?>

<?php
$authUser = \App\Middleware\AuthMiddleware::user();
$userRole = $authUser ? strtolower(trim((string)($authUser['role_name'] ?? $authUser['role'] ?? ''))) : null;
$isCivitas = \App\Middleware\AuthMiddleware::isLoggedIn() && ($userRole === 'civitas');
?>

<div class="container py-4">

    <?php if ($isCivitas): ?>
        <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
        <?php
        $statusBadge = '<span class="cf-badge cf-badge-found fs-6 px-3 py-2"><i class="bi bi-hand-thumbs-up"></i> ' . \App\Models\FoundReportModel::getStatusLabel($report['status']) . '</span>';
        $headerConfig = [
            'eyebrow'     => 'Detail Laporan Temuan',
            'title'       => $report['title'],
            'icon'        => 'bi bi-box-seam',
            'description' => 'Nomor Laporan: ' . htmlspecialchars($report['report_number']) . ' • Dilaporkan oleh ' . htmlspecialchars($report['reporter_name']) . ' pada ' . date('d F Y, H:i', strtotime($report['created_at'])) . ' WIB',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/dashboard'],
                ['label' => 'Barang Temuan', 'url' => '/reports/found'],
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
                <li class="breadcrumb-item"><a href="/reports/found">Barang Temuan</a></li>
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
                    <span class="cf-badge cf-badge-found fs-6 px-3 py-2">
                        <i class="bi bi-hand-thumbs-up"></i> <?= \App\Models\FoundReportModel::getStatusLabel($report['status']) ?>
                    </span>
                </div>

                <hr class="my-4">

                <!-- Image Preview (jika ada) -->
                <?php if (!empty($report['primary_image'])): ?>
                    <div class="mb-4 text-center p-2 rounded-3" style="background: rgba(241, 245, 249, 0.8); border: 1px solid var(--cf-border);">
                        <?php if (\App\Middleware\AuthMiddleware::isLoggedIn()): ?>
                            <img src="<?= htmlspecialchars($report['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="<?= htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                 class="img-fluid rounded-3" 
                                 style="max-height: 400px; object-fit: contain;">
                        <?php else: ?>
                            <div class="py-4 text-muted">
                                <i class="bi bi-shield-lock text-primary fs-1 d-block mb-2"></i>
                                <p class="mb-1 fw-semibold text-dark">Foto barang hanya dapat dilihat oleh civitas akademika terdaftar.</p>
                                <p class="small text-muted mb-3">Silakan masuk ke akun Anda untuk melihat foto barang dan mengajukan klaim.</p>
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
                            <span class="cf-attribute-label">Kondisi Fisik Saat Ditemukan</span>
                            <div class="cf-attribute-value"><i class="bi bi-check2-circle me-1 text-success"></i><?= \App\Models\FoundReportModel::getConditionLabel($report['item_condition']) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="cf-attribute-box">
                            <span class="cf-attribute-label">Waktu Ditemukan</span>
                            <div class="cf-attribute-value"><i class="bi bi-calendar-event me-1 text-primary"></i><?= date('d M Y', strtotime($report['found_date'])) ?> <?= !empty($report['found_time']) ? '(' . substr($report['found_time'], 0, 5) . ' WIB)' : '' ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="cf-attribute-box">
                            <span class="cf-attribute-label">Status Penyerahan ke Center</span>
                            <div class="cf-attribute-value">
                                <?php if ($report['is_handed_over_to_center']): ?>
                                    <span class="text-success"><i class="bi bi-building-check me-1"></i>Tersimpan di L&F Center</span>
                                <?php else: ?>
                                    <span class="text-warning"><i class="bi bi-clock me-1"></i>Belum Diserahkan</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="cf-attribute-box">
                            <span class="cf-attribute-label">Lokasi Penemuan</span>
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
                    <div class="p-3 rounded bg-white border text-dark" style="line-height: 1.7; white-space: pre-line;">
                        <?= htmlspecialchars($report['description']) ?>
                    </div>
                </div>

                <!-- Informasi Sensitif / Privat -->
                <div class="mb-4">
                    <?php if ($canAccessPrivate): ?>
                        <div class="p-4 rounded-3" style="background: rgba(236, 253, 245, 0.9); border: 1px solid rgba(167, 243, 208, 0.9);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-lock-fill text-success fs-5"></i>
                                    <h3 class="h6 fw-bold text-success mb-0">Informasi Internal &amp; Ciri Rahasia (Data Privat)</h3>
                                </div>
                                <span class="cf-badge cf-badge-found">
                                    <?= $isOwner ? 'Pelapor / Penemu' : 'Petugas / Admin' ?>
                                </span>
                            </div>
                            <p class="text-muted small mb-2">
                                🔒 Informasi ini <strong>hanya dapat dilihat oleh Anda dan Petugas Lost &amp; Found</strong> untuk verifikasi saat pemilik asli mengajukan klaim.
                            </p>
                            <div class="bg-white p-3 rounded border text-dark small" style="line-height:1.6; white-space: pre-line;">
                                <?= !empty($report['private_details']) ? htmlspecialchars($report['private_details']) : '<span class="text-muted fst-italic">Tidak ada detail rahasia yang diisi oleh penemu.</span>' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-3 rounded-3 text-center" style="background: rgba(241, 245, 249, 0.8); border: 1px dashed var(--cf-border);">
                            <i class="bi bi-shield-lock text-muted fs-4 d-block mb-1"></i>
                            <div class="fw-semibold text-dark small">Informasi Verifikasi Rahasia Dilindungi</div>
                            <div class="text-muted small" style="font-size:0.75rem;">
                                Detail khusus dan catatan verifikasi disembunyikan dari publik demi keamanan proses verifikasi klaim kepemilikan.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Verified Physical Inventory Item Card (jika sudah diverifikasi petugas) -->
                <?php if (!empty($report['item_id']) && !empty($report['item_code'])): ?>
                    <div class="p-4 rounded-3 mb-4" style="background: rgba(238, 242, 255, 0.9); border: 1px solid rgba(199, 210, 254, 0.9);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-box-seam-fill text-primary fs-5"></i>
                            <h3 class="h6 fw-bold text-primary mb-0">Inventaris Resmi Lost &amp; Found Center</h3>
                        </div>
                        <div class="row g-2 small text-dark mt-1">
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Kode Inventaris Barang:</span>
                                <code><strong><?= htmlspecialchars($report['item_code']) ?></strong></code>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Lokasi Penyimpanan Gudang:</span>
                                <strong><?= htmlspecialchars($report['storage_name'] ?? 'Gudang Center') ?></strong>
                                <?php if (!empty($report['storage_code'])): ?>
                                    <span class="cf-badge cf-badge-neutral ms-1"><?= htmlspecialchars($report['storage_code']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($report['verifier_name'])): ?>
                                <div class="col-12 mt-2 pt-2 border-top">
                                    <span class="text-muted">Diverifikasi &amp; dimasukkan ke gudang oleh:</span>
                                    <strong><?= htmlspecialchars($report['verifier_name']) ?></strong> (Petugas L&amp;F)
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Status Timeline / Histories -->
                <?php if (!empty($histories)): ?>
                    <div class="mb-4">
                        <h3 class="h6 fw-bold text-dark mb-3">
                            <i class="bi bi-clock-history me-1 text-secondary"></i> Jejak Transisi Status Laporan
                        </h3>
                        <div class="border-start ps-3 ms-2">
                            <?php foreach ($histories as $h): ?>
                                <div class="position-relative mb-3">
                                    <div style="position: absolute; left: -21px; top: 2px; width: 10px; height: 10px; border-radius: 50%; background: var(--cf-primary);"></div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="cf-badge cf-badge-neutral">
                                            <?= \App\Models\FoundReportModel::getStatusLabel($h['new_status']) ?>
                                        </span>
                                        <span class="text-muted" style="font-size:0.75rem;">
                                            <?= date('d M Y, H:i', strtotime($h['created_at'])) ?> WIB
                                        </span>
                                    </div>
                                    <?php if (!empty($h['notes'])): ?>
                                        <div class="small text-muted mt-1"><?= htmlspecialchars($h['notes']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($h['changed_by_name'])): ?>
                                        <div class="text-muted" style="font-size:0.7rem;">Oleh: <?= htmlspecialchars($h['changed_by_name']) ?> (<?= htmlspecialchars($h['changed_by_role'] ?? 'User') ?>)</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Actions for Pelapor & Petugas -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top">
                    <div>
                        <a href="/reports/found" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Katalog
                        </a>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <!-- Pintasan Chat Terikat Laporan (Prompt 34D) -->
                        <?php if ($isOwner && ($user['role_name'] ?? '') === 'civitas'): ?>
                            <a href="/chats/report/found/<?= (int) $report['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                                <i class="bi bi-chat-dots-fill me-1"></i> Tanya Petugas
                            </a>
                        <?php elseif (($user['role_name'] ?? '') === 'petugas'): ?>
                            <a href="/chats/report/found/<?= (int) $report['id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                                <i class="bi bi-chat-dots-fill me-1"></i> Hubungi Pelapor
                            </a>
                        <?php endif; ?>

                        <!-- Petugas Actions: Verify & Store -->
                        <?php if ($isStaff && in_array($report['status'], ['published', 'pending_handover', 'handed_over_to_center'], true) && empty($report['item_id'])): ?>
                            <a href="/petugas/found-reports/<?= $report['id'] ?>/verify" class="btn btn-primary btn-sm">
                                <i class="bi bi-shield-check me-1"></i> Verifikasi &amp; Simpan ke Gudang
                            </a>
                        <?php endif; ?>

                        <!-- Pelapor Actions: Handover to Center -->
                        <?php if ($isOwner && $report['status'] === 'published'): ?>
                            <form action="/reports/found/<?= $report['id'] ?>/handover" method="POST" class="d-inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" 
                                        class="btn btn-outline-success btn-sm"
                                        data-cf-confirm="Apakah Anda siap menyerahkan barang temuan ini ke Lost & Found Center?"
                                        data-cf-title="Konfirmasi Penyerahan Barang"
                                        data-cf-variant="success"
                                        data-cf-confirm-text="Serahkan Barang">
                                    <i class="bi bi-building-up me-1"></i> Siap Serahkan ke Center
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Pelapor / Admin Edit & Delete Actions (Only if published) -->
                        <?php if (($isOwner || ($user['role_name'] ?? '') === 'admin') && in_array($report['status'], ['draft', 'published'], true)): ?>
                            <a href="/reports/found/<?= $report['id'] ?>/edit" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </a>

                            <form action="/reports/found/<?= $report['id'] ?>/delete" method="POST" class="d-inline" id="form-delete-found">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" 
                                        class="btn btn-danger btn-sm" 
                                        data-cf-confirm="Apakah Anda yakin ingin menghapus laporan barang temuan ini?" 
                                        data-cf-title="Konfirmasi Hapus Laporan"
                                        data-cf-variant="danger"
                                        data-cf-confirm-text="Hapus Laporan">
                                    <i class="bi bi-trash me-1"></i> Hapus
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Panel Klaim Terkait (Prompt 34F: Detail laporan menampilkan klaim terkait) -->
            <?php if ($isStaff): ?>
                <div class="cf-card p-4 mb-4">
                    <h3 class="h6 fw-bold text-dark mb-2">
                        <i class="bi bi-shield-check text-primary me-1"></i> Klaim Terkait (<?= count($relatedClaims ?? []) ?>)
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
                        <p class="text-muted small mb-0">Belum ada klaim yang diajukan untuk barang temuan ini.</p>
                    <?php endif; ?>
                </div>
            <?php elseif (!empty($relatedClaims)): ?>
                <div class="cf-card p-4 mb-4">
                    <h3 class="h6 fw-bold text-dark mb-2">
                        <i class="bi bi-shield-check text-primary me-1"></i> Pengajuan Klaim Anda
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
            <?php endif; ?>

            <!-- AI Matching Banner Card (Petugas & Admin) -->
            <?php if (in_array($user['role_name'] ?? '', ['petugas', 'admin'], true)): ?>
                <div class="cf-card cf-card-subtle p-4 mb-4" style="border-left: 4px solid var(--cf-ai-purple);">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h3 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                            <i class="bi bi-cpu-fill text-purple"></i> AI Match Scan (Petugas)
                        </h3>
                        <span class="cf-badge cf-badge-ai small">NVIDIA NIM</span>
                    </div>
                    <p class="text-muted small mb-3">
                        Pindai kecocokan barang temuan ini terhadap laporan kehilangan aktif menggunakan <strong>NVIDIA NIM</strong>.
                    </p>

                    <?php if (!empty($candidates)): ?>
                        <div class="list-group list-group-flush mb-3">
                            <?php foreach (array_slice($candidates, 0, 3) as $cItem): ?>
                                <div class="list-group-item px-0 py-2 border-bottom bg-transparent">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="me-2">
                                            <span class="fw-semibold small text-dark d-block">
                                                <?= htmlspecialchars($cItem['lost_title'] ?? 'Laporan Kehilangan') ?>
                                            </span>
                                            <span class="text-muted font-monospace" style="font-size:0.75rem;">
                                                <?= htmlspecialchars($cItem['lost_report_number'] ?? '') ?>
                                            </span>
                                        </div>
                                        <span class="cf-badge cf-badge-found small">
                                            <?= round((float)($cItem['relevance_score'] ?? 0)) ?>% Cocok
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-grid gap-2 mb-2">
                            <a href="/ai-match/found/<?= (int)$report['id'] ?>" class="btn btn-sm text-white rounded-pill shadow-sm" style="background-color: #6f42c1;">
                                <i class="bi bi-columns-gap me-1"></i> Bandingkan Barang (<?= count($candidates) ?>)
                            </a>
                        </div>
                    <?php endif; ?>

                    <form action="/ai-match/found/<?= (int)$report['id'] ?>/run" method="POST" class="ai-match-scan-form" data-report-id="<?= (int)$report['id'] ?>" data-report-type="found">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-outline-purple btn-sm rounded-pill w-100">
                            <i class="bi bi-arrow-clockwise me-1"></i> <?= !empty($candidates) ? 'Pindai Ulang AI' : 'Pindai AI Match Laporan' ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Information Card for Owner of Lost Item (Prompt 35A: Hanya untuk Civitas yang memenuhi syarat) -->
            <?php if (!empty($canSubmitClaim)): ?>
                <div class="cf-card p-4 mb-4">
                    <h3 class="h6 fw-bold text-dark mb-2">
                        <i class="bi bi-question-circle text-primary me-1"></i> Apakah Ini Barang Anda?
                    </h3>
                    <p class="text-muted small mb-3">
                        Jika Anda merasa barang ini adalah milik Anda yang hilang, ajukan verifikasi kepemilikan dengan bukti privat Anda.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="/claims/create?found_id=<?= $report['id'] ?><?= !empty($report['item_id']) ? '&item_id=' . $report['item_id'] : '' ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-shield-check me-1"></i> Ajukan Klaim Kepemilikan
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Lost & Found Center Location Card -->
            <div class="cf-card p-4">
                <h3 class="h6 fw-bold text-dark mb-2">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> Posko Serah Terima
                </h3>
                <p class="small text-muted mb-2">
                    Lokasi penyerahan barang temuan &amp; pengambilan:
                </p>
                <div class="bg-light p-3 rounded border small">
                    <strong>Gedung Rektorat Kampus</strong><br>
                    <span class="text-muted">Lantai 1, Ruang Piket Layanan Terpadu</span><br>
                    <span class="text-muted"><i class="bi bi-clock me-1"></i>Senin – Jumat, 08.00 – 16.00 WIB</span>
                </div>
            </div>

            <!-- Admin Moderasi Hapus Spam Card -->
            <?php if (($user['role_name'] ?? '') === 'admin' && !$isOwner): ?>
                <div class="card border-0 shadow-sm rounded-4 p-3 mt-4 bg-danger-subtle border-danger-subtle">
                    <h6 class="fw-bold text-danger mb-1"><i class="bi bi-shield-x me-1"></i> Moderasi Administrator</h6>
                    <p class="small text-muted mb-2">Hapus laporan temuan jika terbukti spam, fiktif, atau bermasalah.</p>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill w-100" data-bs-toggle="modal" data-bs-target="#modal-admin-delete-found">
                        Hapus Laporan Bermasalah
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal Admin Moderasi: Hapus Laporan Temuan Bermasalah / Spam -->
<?php if (($user['role_name'] ?? '') === 'admin' && !$isOwner): ?>
    <div class="modal fade" id="modal-admin-delete-found" tabindex="-1" aria-labelledby="modalAdminDeleteFoundLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <form action="/admin/reports/found/<?= $report['id'] ?>/delete" method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold text-danger" id="modalAdminDeleteFoundLabel">
                            <i class="bi bi-shield-x me-1"></i> Hapus Laporan Temuan Bermasalah / Spam
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="alert alert-danger border-0 small rounded-3 mb-3">
                            <strong>Perhatian Administrator:</strong> Tindakan ini akan menghapus data laporan temuan <strong><?= htmlspecialchars($report['report_number']) ?></strong> secara permanen beserta berkas fotonya. Tindakan ini hanya boleh dilakukan untuk laporan spam atau pelanggaran etika.
                        </div>

                        <div class="mb-3">
                            <label for="admin-delete-found-reason" class="form-label small fw-semibold text-dark">Alasan Penghapusan (Wajib dicatat di Audit Log)</label>
                            <textarea name="deletion_reason" id="admin-delete-found-reason" class="form-control" rows="3" 
                                      placeholder="Tuliskan alasan spesifik (misal: Barang temuan palsu / spam)..." 
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
