<?php
/**
 * app/Views/claims/my_claims.php
 * Riwayat Pengajuan Klaim Kepemilikan Barang Milik Civitas
 */
use App\Models\ClaimModel;
?>

<div class="container py-4 cf-content-bounded">

    <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
    <?php
    $headerConfig = [
        'eyebrow'     => 'Civitas Kampus',
        'title'       => 'Daftar Klaim Kepemilikan Saya',
        'icon'        => 'bi bi-shield-check',
        'description' => 'Pantau status dan hasil verifikasi pengajuan klaim barang temuan yang Anda laporkan.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Klaim Saya'],
        ],
        'actions'     => '<a href="/search?type=found" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i> Cari barang temuan</a>',
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <?php if (empty($claims)): ?>
        <div class="cf-card p-5 text-center">
            <?php
            $icon = 'bi-shield-check';
            $title = 'Belum Ada Pengajuan Klaim';
            $message = 'Anda belum pernah mengajukan klaim kepemilikan barang. Jika Anda kehilangan barang dan melihat barang yang cocok di daftar barang temuan, silakan ajukan klaim.';
            $actionUrl = '/search?type=found';
            $actionText = 'Telusuri Barang Temuan';
            $actionIcon = 'bi-search';
            $actionClass = 'btn-outline-primary btn-sm';
            require BASE_PATH . '/app/Views/partials/empty_state.php';
            ?>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($claims as $c): ?>
                <div class="col-12">
                    <div class="cf-listing-card d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 72px; height: 72px; border-radius: 10px; overflow: hidden; background: #f1f5f9; border: 1px solid #e2e8f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($c['item_image'])): ?>
                                    <img src="<?= htmlspecialchars($c['item_image'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Barang">
                                <?php else: ?>
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                        <i class="bi bi-box-seam fs-3"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <span class="font-monospace text-muted small" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars($c['claim_number'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?= ClaimModel::getStatusBadge($c['status']) ?>
                                </div>
                                <h2 class="h6 fw-bold text-dark mb-1">
                                    <?= htmlspecialchars($c['item_title'] ?? 'Barang Temuan di L&F Center', ENT_QUOTES, 'UTF-8') ?>
                                </h2>
                                <div class="small text-muted">
                                    <span class="me-3"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($c['category_name'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?></span>
                                    <span><i class="bi bi-calendar3 me-1"></i>Diajukan: <?= date('d/m/Y', strtotime($c['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <a href="/claims/<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 text-nowrap">
                                Lihat Status & Bukti &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
