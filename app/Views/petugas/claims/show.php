<?php
/**
 * app/Views/petugas/claims/show.php
 * Workspace Verifikasi Klaim Petugas — Lost & Found Center
 */
use App\Models\ClaimModel;
?>

<div class="container py-4 cf-workspace-wide cf-claims-detail-container">
    <!-- Standalone Transparent Page Header (Aura Liquid Glass) -->
    <?php
    $claimActions = '<div class="d-flex flex-wrap gap-2 align-items-center">'
        . '<a href="/chats/claim/' . (int)$claim['id'] . '" class="btn btn-sm btn-primary rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-1.5">'
        . '<i class="bi bi-chat-dots-fill"></i> Hubungi Pemohon (Chat)'
        . '</a>'
        . ($claim['status'] === 'approved' ? '<a href="/petugas/claims/' . (int)$claim['id'] . '/handover" class="btn btn-sm btn-success rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-1.5"><i class="bi bi-box-arrow-right"></i> Serah Terima Barang</a>' : '')
        . '<a href="/petugas/claims" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1.5">'
        . '<i class="bi bi-arrow-left"></i> Kembali'
        . '</a>'
        . '</div>';

    $headerConfig = [
        'eyebrow'     => 'Pemeriksaan Berkas Klaim',
        'title'       => 'Pemeriksaan Klaim #' . htmlspecialchars($claim['claim_number'], ENT_QUOTES, 'UTF-8'),
        'icon'        => 'bi bi-shield-check',
        'badge'       => ClaimModel::getStatusBadge($claim['status']),
        'description' => 'Tinjau jawaban rahasia, bandingkan bukti kepemilikan pelapor, dan berikan keputusan verifikasi.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Antrean Klaim', 'url' => '/petugas/claims'],
            ['label' => htmlspecialchars($claim['claim_number'], ENT_QUOTES, 'UTF-8')],
        ],
        'actions'     => $claimActions,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Concurrency Alert: Klaim Kompetitor -->
    <?php if (!empty($competingClaims)): ?>
        <div class="alert alert-warning border-0 p-3 mb-4 shadow-sm">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-warning"></i>
                <h2 class="h6 fw-bold mb-0">Perhatian: Ada Klaim Ganda untuk Item Ini!</h2>
            </div>
            <p class="small mb-2">
                Terdapat <strong><?= count($competingClaims) ?> pengajuan klaim lain</strong> untuk barang yang sama (<strong><?= htmlspecialchars($claim['item_code'] ?? 'L&F', ENT_QUOTES, 'UTF-8') ?></strong>). 
                Sesuai aturan sistem, hanya <strong>SATU</strong> klaim yang boleh disetujui. Jika Anda menyetujui klaim ini, seluruh klaim lain akan <strong>otomatis ditutup</strong> oleh sistem.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($competingClaims as $comp): ?>
                    <a href="/petugas/claims/<?= $comp['id'] ?>" class="cf-badge cf-badge-neutral text-decoration-none py-2 px-3">
                        [<?= htmlspecialchars($comp['claim_number'], ENT_QUOTES, 'UTF-8') ?>] oleh <?= htmlspecialchars($comp['claimant_name'], ENT_QUOTES, 'UTF-8') ?> 
                        (Status: <?= ClaimModel::getStatusLabel($comp['status']) ?>) &rarr;
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Kolom Kiri: Bukti Privat Klaim & Tindakan Verifikasi -->
        <div class="col-lg-8">
            
            <!-- Panel Bukti Privat -->
            <div class="cf-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h2 class="h6 fw-bold text-dark mb-0">
                        <i class="bi bi-lock-fill text-primary me-2"></i>Data Bukti Verifikasi Privat (Rahasia)
                    </h2>
                    <span class="cf-badge cf-badge-info">
                        <i class="bi bi-shield-lock me-1"></i>Hanya Petugas & Pemilik
                    </span>
                </div>

                <div class="mb-3">
                    <span class="text-muted small fw-semibold d-block">Alasan Pengajuan Klaim:</span>
                    <p class="text-dark small mb-0 p-2 rounded bg-light">
                        <?= nl2br(htmlspecialchars($claim['claim_reason'], ENT_QUOTES, 'UTF-8')) ?>
                    </p>
                </div>

                <div class="mb-3">
                    <span class="text-muted small fw-semibold d-block">Deskripsi Bukti Kepemilikan:</span>
                    <p class="text-dark small mb-0 p-2 rounded bg-light">
                        <?= nl2br(htmlspecialchars($claim['ownership_proof_description'], ENT_QUOTES, 'UTF-8')) ?>
                    </p>
                </div>

                <!-- Detail Unik Rahasia (Highlight Box) -->
                <div class="mb-3 cf-private-proof-box">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-warning-emphasis small">
                            <i class="bi bi-key-fill me-1"></i>Detail Unik &amp; Rahasia Barang:
                        </span>
                        <span class="cf-private-badge"><i class="bi bi-shield-lock"></i> Periksa ke Fisik Barang</span>
                    </div>
                    <p class="text-dark small mb-0 font-monospace" style="white-space: pre-wrap;"><?= htmlspecialchars($claim['unique_features'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <?php if (!empty($claim['additional_notes'])): ?>
                    <div class="mb-3">
                        <span class="text-muted small fw-semibold d-block">Catatan Tambahan dari Claimant:</span>
                        <p class="text-muted small mb-0">
                            <?= htmlspecialchars($claim['additional_notes'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Dokumen Bukti / Foto Lampiran -->
                <div class="mt-4 border-top pt-3">
                    <span class="fw-semibold text-dark small d-block mb-2">
                        <i class="bi bi-paperclip me-1"></i>Berkas Lampiran Bukti:
                    </span>
                    <?php if (empty($claim['evidence'])): ?>
                        <span class="text-muted small fst-italic">Claimant tidak mengunggah file dokumen lampiran.</span>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($claim['evidence'] as $ev): ?>
                                <?php if (!empty($ev['file_path'])): ?>
                                    <?php $isPdf = str_ends_with(strtolower($ev['file_path']), '.pdf'); ?>
                                    <div class="col-sm-6">
                                        <div class="p-3 border rounded bg-white shadow-sm">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="bi <?= $isPdf ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-primary' ?> fs-3"></i>
                                                <div class="overflow-hidden flex-grow-1">
                                                    <div class="small fw-bold text-truncate"><?= htmlspecialchars($ev['evidence_value'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <span class="cf-badge cf-badge-neutral"><?= htmlspecialchars($ev['evidence_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            </div>

                                            <?php if (!$isPdf): ?>
                                                <div style="height: 120px; overflow: hidden; border-radius: 4px; background: #f8fafc;" class="mb-2">
                                                    <img src="<?= htmlspecialchars($ev['file_path'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: contain;" alt="Lampiran">
                                                </div>
                                            <?php endif; ?>

                                            <a href="<?= htmlspecialchars($ev['file_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-outline-secondary btn-sm w-100">
                                                Buka Berkas Penuh <i class="bi bi-box-arrow-up-right ms-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Panel Tindakan Keputusan Petugas -->
            <div class="cf-card p-4">
                <h2 class="h6 fw-bold text-dark mb-3 border-bottom pb-2">
                    <i class="bi bi-pencil-square text-secondary me-2"></i>Tindakan Verifikasi Petugas
                </h2>

                <?php if ($claim['status'] === 'completed'): ?>
                    <div class="alert alert-success border-0 mb-0 small">
                        <i class="bi bi-check-circle-fill me-1"></i> Klaim ini telah selesai dan barang fisik telah diserahkan kepada pemilik sah.
                    </div>
                <?php elseif ($claim['status'] === 'closed_duplicate'): ?>
                    <div class="alert alert-secondary border-0 mb-0 small">
                        <i class="bi bi-slash-circle me-1"></i> Klaim ini telah ditutup otomatis karena item diserahkan ke pihak lain.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        
                        <!-- 1. Tombol Mulai Review -->
                        <?php if ($claim['status'] === 'pending'): ?>
                            <form action="/petugas/claims/<?= $claim['id'] ?>/review" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn-info text-dark btn-sm">
                                    <i class="bi bi-search me-1"></i> Mulai Pemeriksaan (Under Review)
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- 2. Tombol Minta Info Tambahan -->
                        <?php if (in_array($claim['status'], ['pending', 'under_review', 'need_more_info'], true)): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRequestInfo">
                                <i class="bi bi-question-circle me-1"></i> Minta Info Tambahan
                            </button>
                        <?php endif; ?>

                        <!-- 3. Tombol Tolak Klaim -->
                        <?php if (in_array($claim['status'], ['pending', 'under_review', 'need_more_info'], true)): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalReject">
                                <i class="bi bi-x-circle me-1"></i> Tolak Klaim
                            </button>
                        <?php endif; ?>

                        <!-- 4. Tombol Setujui Klaim -->
                        <?php if (in_array($claim['status'], ['pending', 'under_review', 'need_more_info'], true)): ?>
                            <button type="button" class="btn btn-success btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#modalApprove">
                                <i class="bi bi-check-lg me-1"></i> Setujui Klaim Kepemilikan
                            </button>
                        <?php endif; ?>

                        <!-- 5. Tombol Serah Terima jika sudah Approved -->
                        <?php if ($claim['status'] === 'approved'): ?>
                            <a href="/petugas/claims/<?= $claim['id'] ?>/handover" class="btn btn-success btn-sm ms-auto">
                                <i class="bi bi-box-arrow-right me-1"></i> Serah Terima Fisik Barang
                            </a>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Kolom Kanan: Info Fisik Barang di Rak & Data Pelapor -->
        <div class="col-lg-4">
            
            <!-- Identitas Pelapor -->
            <div class="cf-card p-3 mb-4">
                <h3 class="h6 fw-bold text-dark mb-2 border-bottom pb-2">
                    <i class="bi bi-person text-primary me-2"></i>Identitas Pengaju Klaim
                </h3>
                <div class="small">
                    <div class="fw-bold text-dark"><?= htmlspecialchars($claim['claimant_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($claim['claimant_email'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($claim['claimant_phone'])): ?>
                        <div class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($claim['claimant_phone'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <div class="text-muted mt-1"><i class="bi bi-clock me-1"></i>Diajukan: <?= date('d/m/Y H:i', strtotime($claim['created_at'])) ?></div>
                </div>
            </div>

            <!-- Laporan Terkait (Prompt 34F: Detail klaim menautkan ke laporan) -->
            <div class="cf-card p-3 mb-4">
                <h3 class="h6 fw-bold text-dark mb-2 border-bottom pb-2">
                    <i class="bi bi-file-earmark-text text-primary me-2"></i>Laporan Terkait
                </h3>
                <div class="small">
                    <?php if (!empty($claim['found_report_id'])): ?>
                        <div class="p-2 bg-light rounded border mb-2">
                            <span class="text-muted d-block" style="font-size: 0.75rem;">Laporan Barang Temuan:</span>
                            <a href="/reports/found/<?= (int) $claim['found_report_id'] ?>" class="fw-semibold text-primary text-decoration-none">
                                <i class="bi bi-box2-heart me-1"></i>[<?= htmlspecialchars($claim['found_report_number'] ?? ('FND-' . $claim['found_report_id'])) ?>] 
                                <?= htmlspecialchars($claim['found_report_title'] ?? $claim['item_title'] ?? 'Detail Temuan') ?> &rarr;
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($claim['lost_report_id'])): ?>
                        <div class="p-2 bg-light rounded border mb-2">
                            <span class="text-muted d-block" style="font-size: 0.75rem;">Laporan Barang Kehilangan:</span>
                            <a href="/reports/lost/<?= (int) $claim['lost_report_id'] ?>" class="fw-semibold text-danger text-decoration-none">
                                <i class="bi bi-search me-1"></i>[<?= htmlspecialchars($claim['lost_report_number'] ?? ('LST-' . $claim['lost_report_id'])) ?>] 
                                <?= htmlspecialchars($claim['lost_report_title'] ?? 'Detail Kehilangan') ?> &rarr;
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($claim['found_report_id']) && empty($claim['lost_report_id'])): ?>
                        <span class="text-muted">Tidak ada laporan spesifik yang terhubung langsung.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fisik Barang di L&F Center -->
            <div class="cf-card p-3 mb-4">
                <h3 class="h6 fw-bold text-dark mb-2 border-bottom pb-2">
                    <i class="bi bi-box-seam text-secondary me-2"></i>Barang Fisik di Inventaris
                </h3>
                <?php if (!empty($claim['item_code'])): ?>
                    <div class="text-center mb-2" style="height: 150px; background: #f8fafc; border-radius: 6px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($claim['item_image'])): ?>
                            <img src="<?= htmlspecialchars($claim['item_image'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Barang">
                        <?php else: ?>
                            <i class="bi bi-box-seam fs-1 text-muted opacity-50"></i>
                        <?php endif; ?>
                    </div>
                    <span class="cf-badge cf-badge-neutral font-monospace">
                        <?= htmlspecialchars($claim['item_code'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="cf-badge cf-badge-neutral">
                        <?= htmlspecialchars($claim['category_name'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <div class="fw-bold text-dark small mt-1"><?= htmlspecialchars($claim['item_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($claim['item_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

                    <div class="p-2 bg-light rounded small border">
                        <div><strong>Lokasi Rak:</strong> <?= htmlspecialchars($claim['storage_location_name'] ?? 'Gudang Center', ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Kondisi Fisik:</strong> <?= htmlspecialchars(ucwords(str_replace('_', ' ', $claim['physical_condition'] ?? 'Baik')), ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Status Fisik:</strong> <?= htmlspecialchars($claim['item_physical_status'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if (!empty($claim['item_internal_notes'])): ?>
                            <div class="mt-1 text-danger-emphasis">
                                <strong>Catatan Rak:</strong> <?= htmlspecialchars($claim['item_internal_notes'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <span class="text-muted small">Barang temuan belum diregistrasi ke rak gudang.</span>
                <?php endif; ?>
            </div>

            <!-- Riwayat Jejak Audit -->
            <?php if (!empty($claim['status_history'])): ?>
                <div class="cf-card p-3">
                    <h3 class="h6 fw-bold text-dark mb-2 border-bottom pb-2">
                        <i class="bi bi-clock-history text-secondary me-2"></i>Riwayat Audit Status
                    </h3>
                    <div class="timeline small">
                        <?php foreach ($claim['status_history'] as $sh): ?>
                            <div class="mb-2 pb-1 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold text-dark"><?= ClaimModel::getStatusLabel($sh['new_status']) ?></span>
                                    <span class="text-muted" style="font-size: 0.7rem;"><?= date('d/m H:i', strtotime($sh['created_at'])) ?></span>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">Oleh: <?= htmlspecialchars($sh['changed_by_name'] ?? 'Sistem', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($sh['notes'])): ?>
                                    <div class="text-secondary mt-1" style="font-size: 0.75rem;"><?= htmlspecialchars($sh['notes'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<!-- MODAL: Setujui Klaim (Approve) -->
<div class="modal fade" id="modalApprove" tabindex="-1" aria-labelledby="modalApproveLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/petugas/claims/<?= $claim['id'] ?>/approve" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title h6 fw-bold" id="modalApproveLabel">
                        <i class="bi bi-check-circle me-1"></i> Konfirmasi Persetujuan Klaim
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="small mb-3">
                        Apakah Anda yakin bukti verifikasi kepemilikan oleh <strong><?= htmlspecialchars($claim['claimant_name'], ENT_QUOTES, 'UTF-8') ?></strong> sudah sesuai dan sah?
                    </p>

                    <div class="alert alert-warning small p-2 mb-3">
                        <i class="bi bi-shield-exclamation me-1"></i><strong>Aturan Sistem:</strong>
                        Saat klaim ini disetujui, jika terdapat pengajuan klaim lain yang masih aktif untuk barang ini, klaim-klaim lain tersebut akan <strong>otomatis ditutup oleh sistem</strong> dalam satu transaksi aman.
                    </div>

                    <div class="mb-3">
                        <label for="approve_notes" class="form-label small fw-semibold">Catatan Persetujuan (Opsional):</label>
                        <textarea class="form-control form-control-sm" id="approve_notes" name="notes" rows="2" placeholder="Contoh: Bukti foto dan detail unik sangat cocok. Pemilik dipersilakan mengambil barang."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm">Ya, Setujui Klaim Ini</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Tolak Klaim (Reject) -->
<div class="modal fade" id="modalReject" tabindex="-1" aria-labelledby="modalRejectLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/petugas/claims/<?= $claim['id'] ?>/reject" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title h6 fw-bold" id="modalRejectLabel">
                        <i class="bi bi-x-circle me-1"></i> Tolak Klaim Kepemilikan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="small mb-3">
                        Silakan sebutkan alasan penolakan agar pelapor klaim dapat mengetahuinya secara transparan.
                    </p>

                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span>:</label>
                        <textarea class="form-control form-control-sm" id="rejection_reason" name="rejection_reason" rows="3" required placeholder="Contoh: Bukti kepemilikan yang dilampirkan tidak sesuai dengan tanda fisik barang..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">Tolak Klaim</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Minta Info Tambahan -->
<div class="modal fade" id="modalRequestInfo" tabindex="-1" aria-labelledby="modalRequestInfoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/petugas/claims/<?= $claim['id'] ?>/request-info" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title h6 fw-bold" id="modalRequestInfoLabel">
                        <i class="bi bi-question-circle me-1"></i> Minta Informasi Tambahan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="small mb-3">
                        Tuliskan detail atau berkas apa yang masih kurang agar claimant dapat memperjelas kepemilikannya.
                    </p>

                    <div class="mb-3">
                        <label for="request_notes" class="form-label small fw-semibold">Informasi yang Dibutuhkan <span class="text-danger">*</span>:</label>
                        <textarea class="form-control form-control-sm" id="request_notes" name="notes" rows="3" required placeholder="Contoh: Mohon sebutkan warna casing bagian dalam atau foto pendukung nomor seri..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Kirim Permintaan</button>
                </div>
            </form>
        </div>
    </div>
</div>
