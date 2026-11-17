<?php
/**
 * app/Views/petugas/inventory.php
 * Center Inventory — Inventaris Fisik Lost & Found Center (Liquid Glass)
 * Mendukung pemisahan Inventaris Aktif vs Riwayat Barang (Task 12)
 */
?>

<div class="container py-4 cf-workspace-wide cf-inventory-container">
    <!-- Standalone Transparent Page Header (Aura Liquid Glass) -->
    <?php
    $intakeAction = '<a href="/petugas/handover" class="btn btn-sm cf-btn-primary-glass px-3 py-2 shadow-sm d-inline-flex align-items-center gap-1.5">'
        . '<i class="bi bi-box-arrow-in-down"></i> <span class="fw-semibold">Penerimaan Baru (Intake)</span>'
        . '</a>';

    $headerConfig = [
        'eyebrow'     => 'Logistik & Inventaris',
        'title'       => ($tab === 'history') ? 'Riwayat Barang L&F Center' : 'Inventaris Fisik Lost & Found Center',
        'icon'        => 'bi bi-boxes',
        'description' => ($tab === 'history') 
            ? 'Arsip kanonik seluruh barang fisik yang telah diserahterimakan ke pemilik, didonasikan, atau dimusnahkan.' 
            : 'Katalog kanonik seluruh barang fisik yang saat ini berada di bawah pengawasan dan tersimpan di posko gudang L&F Center.',
        'breadcrumbs' => [
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => ($tab === 'history') ? 'Riwayat Barang Selesai' : 'Inventaris Posko Aktif'],
        ],
        'actions'     => $intakeAction,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Navigation Tabs: Inventaris Aktif vs Riwayat Barang -->
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link py-2 px-3 fw-semibold <?= ($tab === 'active') ? 'active shadow-sm' : '' ?>" 
               href="/petugas/inventory?tab=active">
                <i class="bi bi-box-seam me-1"></i> Inventaris Aktif di Posko
                <span class="badge ms-1">
                    <?= $activeCount ?? 0 ?>
                </span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 px-3 fw-semibold <?= ($tab === 'history') ? 'active shadow-sm' : '' ?>" 
               href="/petugas/inventory?tab=history">
                <i class="bi bi-clock-history me-1"></i> Riwayat Barang (Diserahkan/Selesai)
                <span class="badge ms-1">
                    <?= $historyCount ?? 0 ?>
                </span>
            </a>
        </li>
    </ul>

    <!-- Storage Locations Overview Cards (Hanya Tampil di Inventaris Aktif) -->
    <?php if ($tab === 'active'): ?>
        <div class="row g-3 mb-4">
            <?php foreach ($storageLocations as $stg): ?>
                <div class="col-md-4 col-xl">
                    <a href="/petugas/inventory?tab=active&storage=<?= $stg['id'] ?>" class="text-decoration-none">
                        <div class="cf-card cf-card-interactive p-3 h-100 <?= ($selectedStorage == $stg['id']) ? 'border-primary shadow' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="cf-badge cf-badge-stored font-monospace"><?= htmlspecialchars($stg['code']) ?></span>
                                <span class="text-muted small"><i class="bi bi-box me-1"></i><?= $stg['current_items_count'] ?? 0 ?> / <?= $stg['capacity'] ?></span>
                            </div>
                            <div class="fw-semibold text-dark mb-1" style="font-size: 0.9rem;"><?= htmlspecialchars($stg['name']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($stg['zone'] ?? 'Zona Umum') ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Search & Filter Card -->
    <div class="cf-card p-3 mb-4">
        <form action="/petugas/inventory" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           name="q" 
                           class="form-control border-start-0" 
                           placeholder="<?= ($tab === 'history') ? 'Cari kode barang, nama barang, atau penerima...' : 'Cari kode barang, nama barang, atau deskripsi...' ?>" 
                           value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select">
                    <?php if ($tab === 'active'): ?>
                        <option value="">Semua Status Aktif</option>
                        <option value="stored" <?= ($selectedStatus === 'stored') ? 'selected' : '' ?>>Tersimpan di Gudang</option>
                        <option value="in_claim" <?= ($selectedStatus === 'in_claim') ? 'selected' : '' ?>>Dalam Proses Klaim</option>
                        <option value="ready_for_handover" <?= ($selectedStatus === 'ready_for_handover') ? 'selected' : '' ?>>Siap Diserahkan</option>
                        <option value="in_inspection" <?= ($selectedStatus === 'in_inspection') ? 'selected' : '' ?>>Sedang Diperiksa</option>
                        <option value="registered" <?= ($selectedStatus === 'registered') ? 'selected' : '' ?>>Baru Terdaftar</option>
                    <?php else: ?>
                        <option value="">Semua Status Riwayat</option>
                        <option value="returned" <?= ($selectedStatus === 'returned') ? 'selected' : '' ?>>Sudah Dikembalikan</option>
                        <option value="donated" <?= ($selectedStatus === 'donated') ? 'selected' : '' ?>>Didonasikan</option>
                        <option value="disposed" <?= ($selectedStatus === 'disposed') ? 'selected' : '' ?>>Dimusnahkan</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="storage" class="form-select" <?= ($tab === 'history') ? 'disabled' : '' ?>>
                    <option value="">Semua Rak</option>
                    <?php foreach ($storageLocations as $sl): ?>
                        <option value="<?= $sl['id'] ?>" <?= ($selectedStorage == $sl['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sl['code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Inventory Table Card -->
    <div class="cf-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold text-dark mb-0">
                <?= ($tab === 'history') ? 'Daftar Riwayat Barang Keluar / Selesai' : 'Daftar Barang Fisik Aktif di Posko' ?>
            </h2>
            <span class="text-muted small">Total: <?= $totalCount ?> item</span>
        </div>

        <?php if (empty($items)): ?>
            <?php
            $icon = 'bi-inbox';
            $title = 'Tidak Ada Barang Ditemukan';
            $message = 'Belum ada barang fisik yang cocok dengan filter atau pencarian Anda pada tab ini.';
            require BASE_PATH . '/app/Views/partials/empty_state.php';
            ?>
        <?php else: ?>
            <div class="table-responsive cf-table-wrapper">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <?php if ($tab === 'active'): ?>
                            <tr>
                                <th>Kode Barang</th>
                                <th>Judul &amp; Kategori</th>
                                <th>Lokasi Rak/Lemari</th>
                                <th>Kondisi Fisik</th>
                                <th>Status Fisik</th>
                                <th>Petugas Penerima</th>
                                <th>Tgl Registrasi</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th>Kode Barang</th>
                                <th>Judul &amp; Kategori</th>
                                <th>Status Akhir</th>
                                <th>Tgl Selesai / Serah Terima</th>
                                <th>Penerima / Pemilik Sah</th>
                                <th>No. Klaim Terkait</th>
                                <th>Petugas</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><code class="fw-bold"><?= htmlspecialchars($item['item_code']) ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['title']) ?></strong>
                                    <div class="text-muted small"><?= htmlspecialchars($item['category_name']) ?></div>
                                </td>

                                <?php if ($tab === 'active'): ?>
                                    <td>
                                        <span class="cf-badge cf-badge-stored">
                                            <?= htmlspecialchars($item['storage_name'] ?? 'Belum dialokasikan') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cf-badge cf-badge-neutral">
                                            <?= \App\Models\ItemModel::getConditionLabel($item['physical_condition']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cf-badge cf-badge-found">
                                            <?= \App\Models\ItemModel::getPhysicalStatusLabel($item['status']) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($item['verifier_name'] ?? 'Petugas') ?></td>
                                    <td class="text-muted small"><?= date('d/m/Y', strtotime($item['registered_at'])) ?></td>
                                <?php else: ?>
                                    <td>
                                        <span class="cf-badge cf-badge-neutral">
                                            <i class="bi bi-check-circle me-1"></i>
                                            <?= \App\Models\ItemModel::getPhysicalStatusLabel($item['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?php if (!empty($item['returned_at'])): ?>
                                            <?= date('d/m/Y H:i', strtotime($item['returned_at'])) ?>
                                        <?php elseif (!empty($item['handed_over_at'])): ?>
                                            <?= date('d/m/Y H:i', strtotime($item['handed_over_at'])) ?>
                                        <?php else: ?>
                                            <?= date('d/m/Y', strtotime($item['updated_at'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['receiver_name'])): ?>
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($item['receiver_name']) ?></span>
                                        <?php elseif (!empty($item['handover_notes'])): ?>
                                            <span class="small text-muted" title="<?= htmlspecialchars($item['handover_notes']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($item['handover_notes'], 0, 30, '...')) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['claim_number'])): ?>
                                            <a href="/petugas/claims/<?= $item['claim_id'] ?? '' ?>" class="cf-badge cf-badge-claim text-decoration-none">
                                                <?= htmlspecialchars($item['claim_number']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($item['verifier_name'] ?? 'Petugas') ?></td>
                                <?php endif; ?>

                                <td class="text-end">
                                    <div class="cf-table-actions">
                                        <a href="/petugas/items/<?= $item['id'] ?>" class="btn btn-outline-primary btn-sm" title="Lihat Detail & Kelola Status">
                                            <i class="bi bi-eye me-1"></i> Detail
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
                    <span class="text-muted small">Halaman <?= $currentPage ?> dari <?= $totalPages ?></span>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $currentPage - 1 ?>&tab=<?= $tab ?><?= !empty($selectedStatus) ? '&status=' . urlencode($selectedStatus) : '' ?><?= !empty($selectedStorage) ? '&storage=' . $selectedStorage : '' ?><?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?>&tab=<?= $tab ?><?= !empty($selectedStatus) ? '&status=' . urlencode($selectedStatus) : '' ?><?= !empty($selectedStorage) ? '&storage=' . $selectedStorage : '' ?><?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $currentPage + 1 ?>&tab=<?= $tab ?><?= !empty($selectedStatus) ? '&status=' . urlencode($selectedStatus) : '' ?><?= !empty($selectedStorage) ? '&storage=' . $selectedStorage : '' ?><?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
