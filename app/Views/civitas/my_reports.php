<?php
/**
 * app/Views/civitas/my_reports.php
 * Daftar Laporan Kehilangan dan Penemuan Milik Civitas (Liquid Glass)
 */
?>

<div class="container py-4 cf-workspace-wide">
    <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
    <?php
    $headerConfig = [
        'eyebrow'     => 'Civitas Kampus',
        'title'       => 'Riwayat Laporan Saya',
        'icon'        => 'bi bi-journal-text',
        'description' => 'Kelola seluruh laporan barang hilang dan laporan barang temuan yang pernah Anda ajukan.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Laporan Saya'],
        ],
        'actions'     => '<a href="/reports/lost/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Buat Laporan Baru</a>',
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Nav tabs -->
    <ul class="nav nav-pills mb-3" id="reportsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="lost-tab" data-bs-toggle="pill" data-bs-target="#lost-pane" type="button" role="tab">
                <i class="bi bi-search"></i> Laporan Kehilangan <span class="badge bg-light text-dark ms-1"><?= count($lostReports) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="found-tab" data-bs-toggle="pill" data-bs-target="#found-pane" type="button" role="tab">
                <i class="bi bi-hand-thumbs-up"></i> Laporan Temuan <span class="badge bg-light text-dark ms-1"><?= count($foundReports) ?></span>
            </button>
        </li>
    </ul>

    <!-- Tab contents -->
    <div class="tab-content" id="reportsTabContent">
        <!-- Lost Reports Pane -->
        <div class="tab-pane fade show active" id="lost-pane" role="tabpanel" tabindex="0">
            <div class="cf-card p-0 overflow-hidden">
                <div class="table-responsive cf-table-wrapper border-0 rounded-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. Laporan</th>
                                <th>Nama Barang &amp; Kategori</th>
                                <th>Lokasi Terakhir</th>
                                <th>Tanggal Hilang</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lostReports)): ?>
                                <?php
                                $emptyIcon = 'bi-search';
                                $emptyTitle = 'Belum Ada Laporan Kehilangan';
                                $emptyMessage = 'Anda belum pernah membuat laporan barang hilang.';
                                $primaryActionUrl = '/reports/lost/create';
                                $primaryActionText = 'Buat Laporan Kehilangan';
                                $primaryActionIcon = 'bi-plus-lg';
                                $tableColspan = 6;
                                include __DIR__ . '/../partials/empty_state.php';
                                ?>
                            <?php else: ?>
                                <?php foreach ($lostReports as $lr): ?>
                                    <tr>
                                        <td><code class="fw-bold"><?= htmlspecialchars($lr['report_number'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                        <td>
                                            <strong><?= htmlspecialchars($lr['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <div class="text-muted small"><?= htmlspecialchars($lr['category_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($lr['location_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= date('d/m/Y', strtotime($lr['lost_date'])) ?></td>
                                        <td><span class="cf-badge cf-badge-lost"><?= htmlspecialchars(\App\Models\LostReportModel::getStatusLabel($lr['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td class="text-end">
                                            <div class="cf-table-actions">
                                                <a href="/reports/lost/<?= $lr['id'] ?>" class="btn btn-outline-primary btn-sm btn-icon" title="Lihat Detail">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="/reports/lost/<?= $lr['id'] ?>/edit" class="btn btn-outline-secondary btn-sm btn-icon" title="Edit Laporan">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Found Reports Pane -->
        <div class="tab-pane fade" id="found-pane" role="tabpanel" tabindex="0">
            <div class="cf-card p-0 overflow-hidden">
                <div class="table-responsive cf-table-wrapper border-0 rounded-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. Laporan</th>
                                <th>Nama Barang &amp; Kategori</th>
                                <th>Lokasi Ditemukan</th>
                                <th>Tanggal Ditemukan</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($foundReports)): ?>
                                <?php
                                $emptyIcon = 'bi-hand-thumbs-up';
                                $emptyTitle = 'Belum Ada Laporan Temuan';
                                $emptyMessage = 'Anda belum pernah membuat laporan barang temuan.';
                                $primaryActionUrl = '/reports/found/create';
                                $primaryActionText = 'Buat Laporan Temuan';
                                $primaryActionIcon = 'bi-plus-lg';
                                $primaryActionVariant = 'btn-success';
                                $tableColspan = 6;
                                include __DIR__ . '/../partials/empty_state.php';
                                ?>
                            <?php else: ?>
                                <?php foreach ($foundReports as $fr): ?>
                                    <tr>
                                        <td><code class="fw-bold"><?= htmlspecialchars($fr['report_number'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                        <td>
                                            <strong><?= htmlspecialchars($fr['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <div class="text-muted small"><?= htmlspecialchars($fr['category_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($fr['location_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= date('d/m/Y', strtotime($fr['found_date'])) ?></td>
                                        <td><span class="cf-badge cf-badge-found"><?= htmlspecialchars(\App\Models\FoundReportModel::getStatusLabel($fr['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td class="text-end">
                                            <div class="cf-table-actions">
                                                <a href="/reports/found/<?= $fr['id'] ?>" class="btn btn-outline-success btn-sm btn-icon" title="Lihat Detail">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if (in_array($fr['status'], ['draft', 'published'], true)): ?>
                                                    <a href="/reports/found/<?= $fr['id'] ?>/edit" class="btn btn-outline-secondary btn-sm btn-icon" title="Edit Laporan">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
