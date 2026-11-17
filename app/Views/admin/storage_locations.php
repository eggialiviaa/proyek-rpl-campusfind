<?php
/**
 * View: Master Data Gudang & Rak Penyimpanan L&F Center (Admin)
 * File: app/Views/admin/storage_locations.php
 */
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/admin">Beranda admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Lokasi penyimpanan</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1">
                Gudang &amp; rak penyimpanan L&amp;F
            </h1>
            <p class="text-muted mb-0">Kelola kapasitas rak, lemari brankas, dan zona penyimpanan fisik di Lost &amp; Found Center.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateStorage">
                <i class="bi bi-plus-lg me-1"></i> Tambah lokasi rak
            </button>
        </div>
    </div>

    <!-- Storage Locations Grid -->
    <?php if (empty($storageLocations)): ?>
        <div class="cf-card p-4">
            <?php
            $icon = 'bi-box-seam';
            $title = 'Belum Ada Lokasi Rak';
            $message = 'Belum ada data rak atau lemari penyimpanan di Lost & Found Center.';
            $actionText = 'Tambah Lokasi Rak';
            $actionAttributes = 'data-bs-toggle="modal" data-bs-target="#modalCreateStorage"';
            $actionIcon = 'bi-plus-lg';
            require BASE_PATH . '/app/Views/partials/empty_state.php';
            ?>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($storageLocations as $sl): ?>
                <?php
                $usage = (float) ($sl['usage_percentage'] ?? 0);
                $progressClass = match (true) {
                    $usage >= 90 => 'bg-danger',
                    $usage >= 70 => 'bg-warning',
                    default      => 'bg-success',
                };
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="cf-card cf-card-interactive h-100 p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-3 rounded-3 bg-warning-subtle text-warning fs-4">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($sl['name']) ?></h5>
                                    <code class="extra-small text-muted font-monospace"><?= htmlspecialchars($sl['code']) ?></code>
                                </div>
                            </div>
                            <span class="cf-badge <?= $sl['is_active'] ? 'cf-badge-found' : 'cf-badge-neutral' ?>">
                                <?= $sl['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <span class="cf-badge cf-badge-stored extra-small">
                                <i class="bi bi-geo-alt me-1 text-warning"></i> <?= htmlspecialchars($sl['zone'] ?? 'Zona Umum') ?>
                            </span>
                        </div>

                        <p class="text-muted small mb-3">
                            <?= !empty($sl['description']) ? htmlspecialchars($sl['description']) : 'Tidak ada keterangan khusus.' ?>
                        </p>

                        <!-- Capacity Progress Bar -->
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1 small">
                                <span class="text-muted extra-small text-uppercase fw-bold">Kapasitas Rak</span>
                                <span class="fw-bold text-dark"><?= (int)$sl['current_items_count'] ?> / <?= (int)$sl['capacity'] ?> Item</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar <?= $progressClass ?>" role="progressbar" style="width: <?= min(100, $usage) ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1 extra-small text-muted">
                                <span>Terisi <?= number_format($usage, 1) ?>%</span>
                                <span>Sisa: <?= (int)$sl['remaining_capacity'] ?> slot</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-end pt-2 border-top gap-2 mt-auto">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" 
                                    data-bs-toggle="modal" data-bs-target="#modalEditStorage<?= (int)$sl['id'] ?>">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </button>
                            <form action="/admin/storage-locations/<?= (int)$sl['id'] ?>/toggle" method="POST" class="d-inline">
                                <?= \App\Middleware\CsrfMiddleware::field() ?>
                                <button type="submit" class="btn btn-sm <?= $sl['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> px-3">
                                    <?= $sl['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            <!-- Modal Edit Storage Location -->
            <div class="modal fade" id="modalEditStorage<?= (int)$sl['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark">Edit Lokasi Penyimpanan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="/admin/storage-locations/<?= (int)$sl['id'] ?>/update" method="POST">
                            <?= \App\Middleware\CsrfMiddleware::field() ?>
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Kode Rak / Lemari <span class="text-danger">*</span></label>
                                    <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($sl['code']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Nama Rak / Wadah <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($sl['name']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Zona Penyimpanan</label>
                                    <input type="text" name="zone" class="form-control" value="<?= htmlspecialchars($sl['zone'] ?? 'Zona Umum') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Kapasitas Maksimal (Item) <span class="text-danger">*</span></label>
                                    <input type="number" name="capacity" class="form-control" value="<?= (int)$sl['capacity'] ?>" min="1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Keterangan Tambahan</label>
                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($sl['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-warning rounded-pill px-4 text-dark fw-semibold">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Create Storage Location -->
<div class="modal fade" id="modalCreateStorage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Tambah Lokasi Penyimpanan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/admin/storage-locations/create" method="POST">
                <?= \App\Middleware\CsrfMiddleware::field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Kode Rak / Lemari <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required placeholder="Contoh: RAK-ELK-01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Rak / Wadah <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Lemari Elektronik &amp; Gadget">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Zona Penyimpanan</label>
                        <input type="text" name="zone" class="form-control" value="Zona Gudang Utama" placeholder="Zona Brankas / Zona Terbuka">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Kapasitas Maksimal (Item) <span class="text-danger">*</span></label>
                        <input type="number" name="capacity" class="form-control" value="50" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Keterangan Tambahan</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Informasi keamanan, kunci, atau dimensi rak..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 text-dark fw-semibold">Simpan Lokasi</button>
                </div>
            </form>
        </div>
    </div>
</div>
