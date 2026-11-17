<?php
/**
 * app/Views/claims/show.php
 * Detail Klaim Kepemilikan Barang (Tampilan Civitas / Pengaju Klaim)
 */
use App\Models\ClaimModel;
?>

<div class="container py-4">

    <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
    <?php
    $claimBadge = ClaimModel::getStatusBadge($claim['status']);
    $claimActions = '<a href="/chats/claim/' . (int) $claim['id'] . '" class="btn btn-primary btn-sm shadow-sm"><i class="bi bi-chat-dots-fill me-1"></i> Hubungi Petugas (Chat)</a>'
                  . '<a href="/civitas/my-claims" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Klaim</a>';

    $headerConfig = [
        'eyebrow'     => 'Detail Klaim',
        'title'       => 'Klaim #' . htmlspecialchars($claim['claim_number'], ENT_QUOTES, 'UTF-8'),
        'icon'        => 'bi bi-shield-check',
        'description' => 'Status dan riwayat verifikasi klaim kepemilikan barang oleh Petugas Posko L&F Center.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Klaim Saya', 'url' => '/civitas/my-claims'],
            ['label' => htmlspecialchars($claim['claim_number'], ENT_QUOTES, 'UTF-8')],
        ],
        'badge'       => $claimBadge,
        'actions'     => $claimActions,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Status Timeline / Step Indicator -->
    <div class="cf-card p-4 mb-4">
        <h2 class="h6 fw-bold text-dark mb-3">Status Verifikasi Klaim</h2>
        <div class="row g-2 text-center small">
            <?php
            $steps = [
                'pending'      => ['label' => 'Diajukan', 'icon' => 'bi-send'],
                'under_review' => ['label' => 'Diperiksa Petugas', 'icon' => 'bi-search'],
                'approved'     => ['label' => 'Disetujui', 'icon' => 'bi-check-circle'],
                'completed'    => ['label' => 'Barang Diserahkan', 'icon' => 'bi-box2-heart'],
            ];
            $currentStatus = $claim['status'];
            $isRejected = ($currentStatus === 'rejected');
            $isClosedDup = ($currentStatus === 'closed_duplicate');
            $isNeedInfo = ($currentStatus === 'need_more_info');

            $stepOrder = ['pending', 'under_review', 'approved', 'completed'];
            $currentIndex = array_search($currentStatus, $stepOrder);
            if ($currentIndex === false) {
                $currentIndex = ($isNeedInfo) ? 1 : (($isRejected || $isClosedDup) ? 1 : 0);
            }
            ?>
            <?php foreach ($stepOrder as $idx => $sKey): ?>
                <?php
                $step = $steps[$sKey];
                $isCompleted = ($idx < $currentIndex) || ($currentStatus === $sKey);
                $isCurrent   = ($currentStatus === $sKey);
                ?>
                <div class="col-3">
                    <div class="cf-step-pill <?= $isCurrent ? 'active' : ($isCompleted ? 'completed' : 'pending') ?>">
                        <i class="bi <?= $step['icon'] ?> fs-5 d-block mb-1"></i>
                        <span class="fw-semibold" style="font-size: 0.8rem;"><?= $step['label'] ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Alert Informasi Khusus Berdasarkan Status -->
        <?php if ($claim['status'] === 'need_more_info'): ?>
            <div class="alert alert-warning border-0 mt-3 mb-0 p-3 small">
                <h3 class="fw-bold mb-1 h6"><i class="bi bi-exclamation-triangle-fill me-1"></i>Petugas Meminta Informasi Tambahan:</h3>
                <p class="mb-0"><?= nl2br(htmlspecialchars($claim['reviewer_notes'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
        <?php elseif ($claim['status'] === 'approved'): ?>
            <div class="alert alert-success border-0 mt-3 mb-0 p-3 small">
                <h3 class="fw-bold mb-1 h6"><i class="bi bi-check-circle-fill me-1"></i>Selamat! Klaim Kepemilikan Anda Telah Disetujui:</h3>
                <p class="mb-1">Silakan datang ke <strong>Lost & Found Center Kampus</strong> untuk serah terima fisik barang dengan membawa kartu identitas (KTM / KTP / SIM).</p>
                <?php if (!empty($claim['reviewer_notes'])): ?>
                    <div class="text-muted small border-top pt-1 mt-1">
                        <strong>Catatan Petugas:</strong> <?= htmlspecialchars($claim['reviewer_notes'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($claim['status'] === 'completed'): ?>
            <div class="alert alert-success border-0 mt-3 mb-0 p-3 small">
                <h3 class="fw-bold mb-1 h6"><i class="bi bi-award-fill me-1"></i>Proses Selesai:</h3>
                <p class="mb-1">Barang telah diserahterimakan pada tanggal: <strong><?= date('d/m/Y H:i', strtotime($claim['handed_over_at'])) ?></strong></p>
                <?php if (!empty($claim['handover_notes'])): ?>
                    <p class="mb-0 text-muted"><strong>Catatan Serah Terima:</strong> <?= htmlspecialchars($claim['handover_notes'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        <?php elseif ($claim['status'] === 'rejected'): ?>
            <div class="alert alert-danger border-0 mt-3 mb-0 p-3 small">
                <h3 class="fw-bold mb-1 h6"><i class="bi bi-x-octagon-fill me-1"></i>Klaim Ditolak:</h3>
                <p class="mb-0"><strong>Alasan:</strong> <?= htmlspecialchars($claim['rejection_reason'] ?? 'Bukti tidak mencukupi untuk memverifikasi kepemilikan barang.', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php elseif ($claim['status'] === 'closed_duplicate'): ?>
            <div class="alert alert-secondary border-0 mt-3 mb-0 p-3 small">
                <h3 class="fw-bold mb-1 h6"><i class="bi bi-slash-circle me-1"></i>Klaim Ditutup Otomatis:</h3>
                <p class="mb-0">Barang ini telah diverifikasi dan diserahkan kepada pihak pengklaim lain yang bukti kepemilikannya dinyatakan sah oleh Petugas.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        
        <!-- Kolom Kiri: Detail Pengajuan Klaim & Bukti Privat -->
        <div class="col-lg-7">
            <div class="cf-card p-4 mb-4">
                <h2 class="h6 fw-bold text-dark mb-3 border-bottom pb-2">
                    <i class="bi bi-file-earmark-lock text-primary me-2"></i>Bukti & Data Verifikasi Privat Anda
                </h2>

                <div class="mb-3">
                    <span class="text-muted small d-block">Alasan Klaim:</span>
                    <p class="text-dark small mb-0">
                        <?= nl2br(htmlspecialchars($claim['claim_reason'], ENT_QUOTES, 'UTF-8')) ?>
                    </p>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block">Deskripsi Bukti Kepemilikan:</span>
                    <p class="text-dark small mb-0">
                        <?= nl2br(htmlspecialchars($claim['ownership_proof_description'], ENT_QUOTES, 'UTF-8')) ?>
                    </p>
                </div>

                <div class="mb-3 p-3 rounded" style="background: rgba(248, 250, 252, 0.8); border: 1px solid #e2e8f0;">
                    <span class="text-warning-emphasis fw-semibold small d-block mb-1">
                        <i class="bi bi-key-fill me-1"></i>Detail Unik Rahasia (Private):
                    </span>
                    <p class="text-dark small mb-0 font-monospace">
                        <?= nl2br(htmlspecialchars($claim['unique_features'], ENT_QUOTES, 'UTF-8')) ?>
                    </p>
                </div>

                <?php if (!empty($claim['additional_notes'])): ?>
                    <div class="mb-3">
                        <span class="text-muted small d-block">Catatan Tambahan Anda:</span>
                        <p class="text-muted small mb-0">
                            <?= htmlspecialchars($claim['additional_notes'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Lampiran Dokumen Bukti -->
                <div class="mt-4 border-top pt-3">
                    <span class="fw-semibold text-dark small d-block mb-2">
                        <i class="bi bi-paperclip me-1"></i>Dokumen / Foto Lampiran Bukti:
                    </span>
                    <?php if (empty($claim['evidence'])): ?>
                        <span class="text-muted small fst-italic">Tidak ada file lampiran diunggah.</span>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($claim['evidence'] as $ev): ?>
                                <?php if (!empty($ev['file_path'])): ?>
                                    <?php $isPdf = str_ends_with(strtolower($ev['file_path']), '.pdf'); ?>
                                    <div class="col-sm-6">
                                        <div class="p-2 border rounded d-flex align-items-center gap-2 bg-white">
                                            <i class="bi <?= $isPdf ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-primary' ?> fs-3"></i>
                                            <div class="flex-grow-1 overflow-hidden">
                                                <div class="small fw-semibold text-truncate"><?= htmlspecialchars($ev['evidence_value'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <a href="<?= htmlspecialchars($ev['file_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="small text-decoration-none">
                                                    Lihat Dokumen <i class="bi bi-box-arrow-up-right ms-1"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- Kolom Kanan: Barang Terkait & Riwayat Transisi -->
        <div class="col-lg-5">
            
            <!-- Info Barang -->
            <div class="cf-card p-4 mb-4">
                <h2 class="h6 fw-bold text-dark mb-3 border-bottom pb-2">
                    <i class="bi bi-box-seam text-secondary me-2"></i>Barang di Lost & Found Center
                </h2>

                <?php if (!empty($claim['item_title'])): ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="width: 70px; height: 70px; border-radius: 8px; overflow: hidden; background: #f1f5f9; flex-shrink: 0;">
                            <?php if (!empty($claim['item_image'])): ?>
                                <img src="<?= htmlspecialchars($claim['item_image'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Barang">
                            <?php else: ?>
                                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                    <i class="bi bi-camera"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="cf-badge cf-badge-neutral font-monospace">
                                <?= htmlspecialchars($claim['item_code'] ?? 'L&F', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <div class="fw-bold text-dark small"><?= htmlspecialchars($claim['item_title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($claim['storage_location_name'] ?? 'Lost & Found Center', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($claim['found_report_id']) || !empty($claim['found_report_number'])): ?>
                    <div class="p-2 bg-light rounded small mb-2 border">
                        <span class="text-muted d-block" style="font-size: 0.75rem;">Terhubung dengan Laporan Temuan:</span>
                        <div class="fw-semibold">
                            <a href="/reports/found/<?= (int) $claim['found_report_id'] ?>" class="text-decoration-none text-primary">
                                [<?= htmlspecialchars($claim['found_report_number'] ?? ('FND-' . $claim['found_report_id']), ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars($claim['found_report_title'] ?? $claim['item_title'] ?? 'Laporan Temuan', ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($claim['lost_report_number']) || !empty($claim['lost_report_id'])): ?>
                    <div class="p-2 bg-light rounded small mb-2 border">
                        <span class="text-muted d-block" style="font-size: 0.75rem;">Terhubung dengan Laporan Kehilangan Anda:</span>
                        <div class="fw-semibold">
                            <a href="/reports/lost/<?= (int) $claim['lost_report_id'] ?>" class="text-decoration-none text-danger">
                                [<?= htmlspecialchars($claim['lost_report_number'] ?? ('LST-' . $claim['lost_report_id']), ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars($claim['lost_report_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="small text-muted mt-3">
                    <div><strong>Diajukan:</strong> <?= date('d/m/Y H:i', strtotime($claim['created_at'])) ?></div>
                    <?php if (!empty($claim['reviewed_at'])): ?>
                        <div><strong>Diperiksa:</strong> <?= date('d/m/Y H:i', strtotime($claim['reviewed_at'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timeline Jejak Audit -->
            <?php if (!empty($claim['status_history'])): ?>
                <div class="cf-card p-4">
                    <h2 class="h6 fw-bold text-dark mb-3 border-bottom pb-2">
                        <i class="bi bi-clock-history text-secondary me-2"></i>Riwayat Status Klaim
                    </h2>
                    <div class="timeline small">
                        <?php foreach ($claim['status_history'] as $sh): ?>
                            <div class="mb-2 pb-2 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold text-dark"><?= ClaimModel::getStatusLabel($sh['new_status']) ?></span>
                                    <span class="text-muted" style="font-size: 0.75rem;"><?= date('d/m/Y H:i', strtotime($sh['created_at'])) ?></span>
                                </div>
                                <?php if (!empty($sh['notes'])): ?>
                                    <div class="text-muted small mt-1"><?= htmlspecialchars($sh['notes'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>
