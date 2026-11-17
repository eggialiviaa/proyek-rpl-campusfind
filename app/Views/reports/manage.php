<?php
/**
 * app/Views/reports/manage.php
 * Halaman Manajemen Laporan Kehilangan & Temuan (Petugas & Admin)
 * Sesuai Prompt 33B
 */

$userRole = $user['role_name'] ?? 'petugas';
$isLostTab = ($activeTab === 'lost');

// Helper query string builder
$buildQuery = function(array $newParams) use ($activeTab, $selectedStatus, $selectedCategory, $selectedLocation, $dateFrom, $dateTo, $searchQuery, $reporterQuery): string {
    $current = [
        'tab'         => $activeTab,
        'status'      => $selectedStatus,
        'category_id' => $selectedCategory,
        'location_id' => $selectedLocation,
        'date_from'   => $dateFrom,
        'date_to'     => $dateTo,
        'q'           => $searchQuery,
        'reporter'    => $reporterQuery,
    ];
    $merged = array_merge($current, $newParams);
    // Hapus parameter kosong
    $clean = array_filter($merged, fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($clean);
};
?>

<div class="container py-4 cf-workspace-wide cf-manage-reports-container">
    <!-- Standalone Transparent Page Header (Aura Liquid Glass) -->
    <?php
    $manageActions = '<div class="d-flex flex-wrap gap-2 align-items-center">'
        . '<a href="/reports/lost/create" class="btn btn-sm cf-btn-danger-glass px-3 py-2 shadow-sm d-inline-flex align-items-center gap-1.5">'
        . '<i class="bi bi-plus-circle text-danger"></i> <span class="fw-semibold">Catat Kehilangan</span>'
        . '</a>'
        . '<a href="/reports/found/create" class="btn btn-sm cf-btn-primary-glass px-3 py-2 shadow-sm d-inline-flex align-items-center gap-1.5">'
        . '<i class="bi bi-box-seam"></i> <span class="fw-semibold">Catat Temuan Baru</span>'
        . '</a>'
        . '</div>';

    $headerConfig = [
        'eyebrow'     => ($userRole === 'admin') ? 'Pengawasan Sistem' : 'Operasional Posko',
        'title'       => 'Pusat Manajemen Laporan',
        'icon'        => 'bi bi-journal-check',
        'description' => 'Pengawasan, verifikasi kecocokan, dan pembaruan status laporan kehilangan serta penemuan barang di lingkungan kampus.',
        'breadcrumbs' => [
            ['label' => ($userRole === 'admin' ? 'Admin Panel' : 'Dasbor'), 'url' => ($userRole === 'admin' ? '/admin' : '/dashboard')],
            ['label' => 'Manajemen Laporan'],
        ],
        'actions'     => $manageActions,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Tab Navigasi Utama: Kehilangan vs Temuan -->
    <div class="cf-card mb-4">
        <div class="card-body p-2 p-md-3">
            <ul class="nav nav-pills nav-fill gap-2" role="tablist">
                <li class="nav-item" role="presentation">
                    <a href="?tab=lost" 
                       class="nav-link rounded-3 py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2 <?= $isLostTab ? 'active shadow-sm' : '' ?>"
                       role="tab">
                        <i class="bi bi-search"></i>
                        <span>Laporan Kehilangan</span>
                        <span class="badge rounded-pill ms-1">
                            <?= number_format($totalLostCount) ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="?tab=found" 
                       class="nav-link rounded-3 py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2 <?= !$isLostTab ? 'active shadow-sm' : '' ?>"
                       role="tab">
                        <i class="bi bi-box2-heart"></i>
                        <span>Laporan Barang Temuan</span>
                        <span class="badge rounded-pill ms-1">
                            <?= number_format($totalFoundCount) ?>
                        </span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Quick Status Filter Pills -->
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <span class="small fw-semibold text-muted me-1"><i class="bi bi-funnel"></i> Status:</span>
        <a href="<?= $buildQuery(['status' => null, 'page' => 1]) ?>" 
           class="cf-filter-chip <?= empty($selectedStatus) ? 'active' : '' ?>">
            Semua (<?= $isLostTab ? number_format($totalLostCount) : number_format($totalFoundCount) ?>)
        </a>
        <?php foreach ($tabCounts as $stKey => $stCount): ?>
            <?php
            $stLabel = $isLostTab ? \App\Models\LostReportModel::getStatusLabel($stKey) : \App\Models\FoundReportModel::getStatusLabel($stKey);
            $isActive = ($selectedStatus === $stKey);
            ?>
            <a href="<?= $buildQuery(['status' => $stKey, 'page' => 1]) ?>" 
               class="cf-filter-chip <?= $isActive ? 'active' : '' ?>">
                <?= htmlspecialchars($stLabel) ?> (<?= number_format($stCount) ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter & Pencarian Form Card -->
    <div class="cf-card mb-4">
        <div class="card-body p-3 p-md-4">
            <form action="/reports/manage" method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                <?php if (!empty($selectedStatus)): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($selectedStatus) ?>">
                <?php endif; ?>

                <div class="col-lg-3 col-md-6">
                    <label for="filter-search" class="form-label small fw-semibold text-muted mb-1">Cari Kata Kunci / No. Laporan</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" id="filter-search" class="form-control form-control-sm border-start-0" 
                               placeholder="Nama barang, deskripsi, no. tiket..." 
                               value="<?= htmlspecialchars($searchQuery ?? '') ?>">
                    </div>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="filter-category" class="form-label small fw-semibold text-muted mb-1">Kategori Barang</label>
                    <select name="category_id" id="filter-category" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ((int)$selectedCategory === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="filter-location" class="form-label small fw-semibold text-muted mb-1">Lokasi Kampus</label>
                    <select name="location_id" id="filter-location" class="form-select form-select-sm">
                        <option value="">Semua Lokasi</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= ((int)$selectedLocation === (int)$loc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="filter-reporter" class="form-label small fw-semibold text-muted mb-1">Pelapor (Nama / NIM)</label>
                    <input type="text" name="reporter" id="filter-reporter" class="form-control form-control-sm" 
                           placeholder="Nama atau NIM..." 
                           value="<?= htmlspecialchars($reporterQuery ?? '') ?>">
                </div>

                <div class="col-lg-3 col-md-12 d-flex gap-2">
                    <div class="flex-grow-1">
                        <label for="filter-date-from" class="form-label small fw-semibold text-muted mb-1">Tanggal Mulai</label>
                        <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-sm" 
                               value="<?= htmlspecialchars($dateFrom ?? '') ?>">
                    </div>
                    <div class="flex-grow-1">
                        <label for="filter-date-to" class="form-label small fw-semibold text-muted mb-1">Tanggal Akhir</label>
                        <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-sm" 
                               value="<?= htmlspecialchars($dateTo ?? '') ?>">
                    </div>
                </div>

                <div class="col-12 text-end mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="small text-muted">
                        Ditemukan <strong><?= number_format($totalCount) ?></strong> laporan sesuai kriteria.
                    </span>
                    <div class="d-flex gap-2">
                        <a href="/reports/manage?tab=<?= htmlspecialchars($activeTab) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm">
                            <i class="bi bi-funnel"></i> Terapkan Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Data List Section (Daftar Bernomor) -->
    <div class="cf-card overflow-hidden mb-4">
        <div class="table-responsive cf-table-wrapper">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-3 py-3" style="width: 50px;">No</th>
                        <th style="width: 70px;">Foto</th>
                        <th>Nomor &amp; Judul Barang</th>
                        <th>Kategori &amp; Lokasi</th>
                        <th>Pelapor</th>
                        <th>Tanggal Kejadian</th>
                        <th>Status</th>
                        <th class="pe-3 text-end" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if (empty($reports)): ?>
                        <?php
                        $tableColspan = 8;
                        $icon = 'bi-inbox';
                        $title = 'Tidak Ada Laporan Ditemukan';
                        $message = 'Coba sesuaikan filter status, kategori, atau kata kunci pencarian Anda.';
                        require BASE_PATH . '/app/Views/partials/empty_state.php';
                        ?>
                    <?php else: ?>
                        <?php foreach ($reports as $index => $rep): ?>
                            <?php
                            $rowNumber = ($currentPage - 1) * $limit + $index + 1;
                            $detailUrl = $isLostTab ? '/reports/lost/' . $rep['id'] : '/reports/found/' . $rep['id'];
                            $dateVal   = $isLostTab ? ($rep['lost_date'] ?? '-') : ($rep['found_date'] ?? '-');
                            $timeVal   = $isLostTab ? ($rep['lost_time'] ?? '') : ($rep['found_time'] ?? '');
                            $statusVal = $rep['status'];

                            // Badge styling
                            $badgeClass = match ($statusVal) {
                                'published'              => 'cf-badge-found',
                                'investigating'          => 'cf-badge-warning',
                                'pending_handover'       => 'cf-badge-warning',
                                'handed_over_to_center'  => 'cf-badge-stored',
                                'verified', 'resolved', 'completed' => 'cf-badge-success',
                                'matched'                => 'cf-badge-ai',
                                'claimed'                => 'cf-badge-claim',
                                'closed', 'cancelled'    => 'cf-badge-neutral',
                                default                  => 'cf-badge-neutral',
                            };

                            $statusLabel = $isLostTab 
                                ? \App\Models\LostReportModel::getStatusLabel($statusVal)
                                : \App\Models\FoundReportModel::getStatusLabel($statusVal);
                            ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-muted">
                                    <?= $rowNumber ?>
                                </td>
                                <td>
                                    <?php if (!empty($rep['primary_image'])): ?>
                                        <img src="<?= htmlspecialchars($rep['primary_image']) ?>" 
                                             alt="<?= htmlspecialchars($rep['title']) ?>" 
                                             class="rounded-3 object-fit-cover shadow-sm border" 
                                             style="width: 48px; height: 48px;">
                                    <?php else: ?>
                                        <div class="rounded-3 bg-light border d-flex align-items-center justify-content-center text-muted" 
                                             style="width: 48px; height: 48px;">
                                            <i class="bi bi-card-image fs-5"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark mb-0">
                                        <a href="<?= $detailUrl ?>" class="text-decoration-none text-dark hover-primary">
                                            <?= htmlspecialchars($rep['title']) ?>
                                        </a>
                                    </div>
                                    <div class="small text-muted font-monospace d-flex align-items-center gap-1">
                                        <span class="cf-badge cf-badge-neutral py-0 px-1">
                                            <?= htmlspecialchars($rep['report_number']) ?>
                                        </span>
                                        <?php if (!$isLostTab && !empty($rep['item_code'])): ?>
                                            <span class="cf-badge cf-badge-stored py-0 px-1" title="Kode Fisik Barang">
                                                <i class="bi bi-tag-fill me-1"></i><?= htmlspecialchars($rep['item_code']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <i class="bi <?= htmlspecialchars($rep['category_icon'] ?? 'bi-box') ?> text-secondary me-1"></i>
                                        <?= htmlspecialchars($rep['category_name'] ?? 'Umum') ?>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($rep['location_name'] ?? '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <?= htmlspecialchars($rep['reporter_name'] ?? 'Civitas') ?>
                                    </div>
                                    <div class="small text-muted font-monospace">
                                        NIM: <?= htmlspecialchars($rep['reporter_identity'] ?? '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-dark fw-medium">
                                        <?= date('d M Y', strtotime($dateVal)) ?>
                                    </div>
                                    <?php if (!empty($timeVal)): ?>
                                        <div class="small text-muted">
                                            <i class="bi bi-clock me-1"></i><?= substr($timeVal, 0, 5) ?> WIB
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cf-badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($statusLabel) ?>
                                    </span>
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="cf-table-actions">
                                        <a href="<?= $detailUrl ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                            <span>Periksa</span> <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Section -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <span class="small text-muted">
                    Menampilkan halaman <strong><?= $currentPage ?></strong> dari <strong><?= $totalPages ?></strong> (Total <?= number_format($totalCount) ?> data)
                </span>
                <nav aria-label="Navigasi Halaman Laporan">
                    <ul class="pagination pagination-sm mb-0">
                        <!-- Tombol Sebelumnya -->
                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link rounded-start-pill" href="<?= $buildQuery(['page' => $currentPage - 1]) ?>" aria-label="Sebelumnya">
                                &laquo;
                            </a>
                        </li>
                        
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage   = min($totalPages, $currentPage + 2);
                        ?>
                        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $buildQuery(['page' => $p]) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Tombol Berikutnya -->
                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link rounded-end-pill" href="<?= $buildQuery(['page' => $currentPage + 1]) ?>" aria-label="Berikutnya">
                                &raquo;
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>
