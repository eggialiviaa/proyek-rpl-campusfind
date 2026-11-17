<?php
/**
 * app/Views/petugas/handover.php
 * Antrean Barang Temuan & Serah Terima — Lost & Found Center
 * Refactored per Prompt 25B (Audit UI & Information Architecture)
 */
$baseRoute = $baseRoute ?? '/petugas/handover';
?>

<div class="container py-4 cf-workspace-wide">
    <!-- Standalone Transparent Page Header (Aura Liquid Glass) -->
    <?php
    $headerConfig = [
        'eyebrow'     => 'Klaim & Serah Terima',
        'title'       => 'Antrean Barang Temuan & Serah Terima',
        'icon'        => 'bi bi-person-check-fill',
        'description' => 'Daftar barang temuan civitas yang menunggu serah terima fisik di meja piket posko Lost & Found.',
        'breadcrumbs' => [
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Antrean Serah Terima'],
        ],
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Filter Tabs (Consolidated) -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="<?= $baseRoute ?>?status=pending_handover" class="cf-filter-chip <?= ($statusFilter === 'pending_handover') ? 'active' : '' ?>">
            <i class="bi bi-clock-history me-1"></i> Menunggu serah terima fisik
            <span class="badge <?= ($statusFilter === 'pending_handover') ? 'bg-white text-primary' : 'bg-warning-subtle text-warning-emphasis' ?> ms-1">
                <?= $pendingHandoverCount ?>
            </span>
        </a>
        <a href="<?= $baseRoute ?>?status=published" class="cf-filter-chip <?= ($statusFilter === 'published') ? 'active' : '' ?>">
            <i class="bi bi-megaphone me-1"></i> Laporan publik baru
            <span class="badge <?= ($statusFilter === 'published') ? 'bg-white text-primary' : 'bg-info-subtle text-info-emphasis' ?> ms-1">
                <?= $publishedCount ?>
            </span>
        </a>
        <a href="<?= $baseRoute ?>?status=all" class="cf-filter-chip <?= empty($statusFilter) ? 'active' : '' ?>">
            <i class="bi bi-list-ul me-1"></i> Semua antrean
            <span class="badge <?= empty($statusFilter) ? 'bg-white text-primary' : 'bg-light text-muted border' ?> ms-1">
                <?= $allCount ?>
            </span>
        </a>
    </div>

    <!-- Handover Queue Table Card -->
    <div class="cf-card p-4">
        <?php if (empty($reports)): ?>
            <?php
            $emptyIcon = 'bi-check2-circle';
            $emptyTitle = 'Tidak ada antrean tertunda';
            $emptyMessage = 'Semua laporan barang temuan telah selesai diverifikasi atau belum ada penyerahan baru.';
            include __DIR__ . '/../partials/empty_state.php';
            ?>
        <?php else: ?>
            <div class="table-responsive cf-table-wrapper">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th class="ps-3">No. laporan</th>
                            <th>Nama barang &amp; kategori</th>
                            <th>Penemu / Pelapor</th>
                            <th>Lokasi temuan</th>
                            <th>Tanggal temuan</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($reports as $r): ?>
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-dark">
                                    <a href="/reports/found/<?= $r['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($r['report_number'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="text-muted small"><?= htmlspecialchars($r['category_name'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($r['reporter_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($r['reporter_phone'] ?? $r['reporter_email'] ?? '-') ?></div>
                                </td>
                                <td><?= htmlspecialchars($r['location_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-muted"><?= date('d/m/Y', strtotime($r['found_date'])) ?></td>
                                <td>
                                    <span class="cf-badge <?= ($r['status'] === 'pending_handover') ? 'cf-badge-warning' : 'cf-badge-found' ?>">
                                        <?= \App\Models\FoundReportModel::getStatusLabel($r['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="cf-table-actions">
                                        <a href="/petugas/intake/<?= $r['id'] ?>" class="btn btn-primary btn-sm" title="Proses penerimaan fisik ke rak">
                                            <i class="bi bi-box-arrow-in-down me-1"></i> Proses intake
                                        </a>
                                        <a href="/reports/found/<?= $r['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Lihat detail laporan">
                                            Detail
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
                    <span class="text-muted small">Total: <?= $totalCount ?> antrean</span>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $currentPage - 1 ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $currentPage + 1 ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
