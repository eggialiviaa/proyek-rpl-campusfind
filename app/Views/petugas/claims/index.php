<?php
/**
 * app/Views/petugas/claims/index.php
 * Antrean Verifikasi Klaim Masuk — Lost & Found Center
 * Refactored per Prompt 25B (Audit UI & Information Architecture)
 */
use App\Models\ClaimModel;
?>

<div class="container py-4 cf-workspace-wide cf-claims-container">
    <!-- Standalone Transparent Page Header (Aura Liquid Glass) -->
    <?php
    $headerConfig = [
        'eyebrow'     => 'Klaim & Verifikasi',
        'title'       => 'Verifikasi Klaim Kepemilikan',
        'icon'        => 'bi bi-shield-check',
        'description' => 'Tinjau jawaban rahasia, bandingkan bukti kepemilikan, dan proses serah terima barang fisik.',
        'breadcrumbs' => [
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Verifikasi Klaim'],
        ],
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Primary Filter Bar: Consolidated Action vs Archive Tabs + Quick Status Dropdown -->
    <div class="cf-card p-3 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <!-- Main Tabs -->
            <ul class="nav nav-pills gap-1">
                <li class="nav-item">
                    <a href="/petugas/claims?tab=action" class="nav-link <?= ($activeTab === 'action' && empty($selectedStatus)) ? 'active shadow-sm' : '' ?> fw-medium py-2 px-3 rounded-3">
                        <i class="bi bi-clock-history me-1"></i> Perlu tindakan
                        <span class="badge ms-1">
                            <?= $actionCount ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/petugas/claims?tab=archive" class="nav-link <?= ($activeTab === 'archive' && empty($selectedStatus)) ? 'active shadow-sm' : '' ?> fw-medium py-2 px-3 rounded-3">
                        <i class="bi bi-check2-all me-1"></i> Riwayat selesai
                        <span class="badge ms-1">
                            <?= $archiveCount ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/petugas/claims?tab=all" class="nav-link <?= ($activeTab === 'all' && empty($selectedStatus)) ? 'active shadow-sm' : '' ?> fw-medium py-2 px-3 rounded-3">
                        <i class="bi bi-list-ul me-1"></i> Semua klaim
                        <span class="badge ms-1">
                            <?= $allCount ?>
                        </span>
                    </a>
                </li>
            </ul>

            <!-- Specific Status Filter Dropdown -->
            <div class="d-flex align-items-center gap-2">
                <label for="statusFilterSelect" class="small text-muted text-nowrap mb-0 d-none d-sm-inline">Status khusus:</label>
                <select id="statusFilterSelect" class="form-select form-select-sm" style="min-width: 200px;" onchange="if(this.value){ window.location.href=this.value; }">
                    <option value="/petugas/claims?tab=<?= $activeTab ?>" <?= empty($selectedStatus) ? 'selected' : '' ?>>
                        -- Tampilkan semua status di tab ini --
                    </option>
                    <optgroup label="Perlu Tindakan">
                        <option value="/petugas/claims?status=pending" <?= ($selectedStatus === 'pending') ? 'selected' : '' ?>>
                            Menunggu pemeriksaan (<?= $counts['pending'] ?>)
                        </option>
                        <option value="/petugas/claims?status=under_review" <?= ($selectedStatus === 'under_review') ? 'selected' : '' ?>>
                            Sedang diperiksa (<?= $counts['review'] ?>)
                        </option>
                        <option value="/petugas/claims?status=need_more_info" <?= ($selectedStatus === 'need_more_info') ? 'selected' : '' ?>>
                            Butuh info tambahan (<?= $counts['need_info'] ?>)
                        </option>
                    </optgroup>
                    <optgroup label="Riwayat &amp; Selesai">
                        <option value="/petugas/claims?status=approved" <?= ($selectedStatus === 'approved') ? 'selected' : '' ?>>
                            Disetujui / Siap ambil (<?= $counts['approved'] ?>)
                        </option>
                        <option value="/petugas/claims?status=completed" <?= ($selectedStatus === 'completed') ? 'selected' : '' ?>>
                            Selesai diserahkan (<?= $counts['completed'] ?>)
                        </option>
                        <option value="/petugas/claims?status=rejected" <?= ($selectedStatus === 'rejected') ? 'selected' : '' ?>>
                            Ditolak (<?= $counts['rejected'] ?>)
                        </option>
                        <option value="/petugas/claims?status=closed_duplicate" <?= ($selectedStatus === 'closed_duplicate') ? 'selected' : '' ?>>
                            Ditutup otomatis (<?= $counts['closed_duplicate'] ?>)
                        </option>
                    </optgroup>
                </select>
                <?php if (!empty($selectedStatus)): ?>
                    <a href="/petugas/claims?tab=<?= $activeTab ?>" class="btn btn-sm btn-outline-secondary" title="Hapus filter spesifik">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Table of Claims -->
    <div class="cf-card overflow-hidden">
        <?php if (empty($claims)): ?>
            <?php
            $icon = 'bi-inbox';
            $title = 'Tidak Ada Pengajuan Klaim';
            $message = 'Tidak ditemukan data pengajuan klaim pada kriteria filter ini.';
            require BASE_PATH . '/app/Views/partials/empty_state.php';
            ?>
        <?php else: ?>
            <div class="table-responsive cf-table-wrapper">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th class="ps-3">No. klaim</th>
                            <th>Barang temuan &amp; lokasi</th>
                            <th>Pelapor</th>
                            <th>Status klaim</th>
                            <th>Bukti</th>
                            <th>Tanggal masuk</th>
                            <th class="text-end pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($claims as $c): ?>
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-dark">
                                    <a href="/petugas/claims/<?= $c['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($c['claim_number'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($c['item_code'])): ?>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($c['item_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($c['item_title'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-1 mt-1">
                                            <span class="cf-badge cf-badge-neutral font-monospace" style="font-size: 0.65rem;">
                                                <?= htmlspecialchars($c['item_code'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <span class="text-muted small" style="font-size: 0.75rem;">
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($c['storage_location_name'] ?? 'Posko L&F', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Barang temuan publik</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($c['claimant_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <span class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($c['claimant_email'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td>
                                    <?= ClaimModel::getStatusBadge($c['status']) ?>
                                </td>
                                <td>
                                    <span class="cf-badge cf-badge-neutral">
                                        <i class="bi bi-paperclip me-1"></i><?= $c['evidence_count'] ?> bukti
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="cf-table-actions">
                                        <?php if ($c['status'] === 'approved'): ?>
                                            <a href="/petugas/claims/<?= $c['id'] ?>/handover" class="btn btn-success btn-sm">
                                                <i class="bi bi-box-arrow-right me-1"></i> Serah terima
                                            </a>
                                            <a href="/petugas/claims/<?= $c['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Lihat detail">
                                                Detail
                                            </a>
                                        <?php else: ?>
                                            <a href="/petugas/claims/<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                Periksa &rarr;
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="p-3 d-flex justify-content-between align-items-center border-top">
                    <span class="text-muted small">Halaman <?= $currentPage ?> dari <?= $totalPages ?> (Total <?= $totalCount ?>)</span>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $currentPage - 1 ?><?= !empty($selectedStatus) ? '&status=' . $selectedStatus : '' ?><?= !empty($activeTab) ? '&tab=' . $activeTab : '' ?><?= !empty($selectedItemId) ? '&item_id=' . $selectedItemId : '' ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?><?= !empty($selectedStatus) ? '&status=' . $selectedStatus : '' ?><?= !empty($activeTab) ? '&tab=' . $activeTab : '' ?><?= !empty($selectedItemId) ? '&item_id=' . $selectedItemId : '' ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $currentPage + 1 ?><?= !empty($selectedStatus) ? '&status=' . $selectedStatus : '' ?><?= !empty($activeTab) ? '&tab=' . $activeTab : '' ?><?= !empty($selectedItemId) ? '&item_id=' . $selectedItemId : '' ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
