<?php
/**
 * View: Master Data Lokasi Area Kampus (Admin)
 * File: app/Views/admin/locations.php
 */
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/admin">Beranda admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Lokasi kampus</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1">
                Lokasi area kampus
            </h1>
            <p class="text-muted mb-0">Kelola gedung, fasilitas, dan zona lokasi kejadian barang hilang/temuan.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateLocation">
                <i class="bi bi-plus-lg me-1"></i> Tambah lokasi
            </button>
        </div>
    </div>

    <!-- Location Grid -->
    <?php if (empty($locations)): ?>
        <div class="cf-card p-4">
            <?php
            $icon = 'bi-geo-alt';
            $title = 'Belum Ada Lokasi';
            $message = 'Belum ada data gedung atau lokasi area kampus yang ditambahkan.';
            $actionText = 'Tambah Lokasi';
            $actionAttributes = 'data-bs-toggle="modal" data-bs-target="#modalCreateLocation"';
            $actionIcon = 'bi-plus-lg';
            require BASE_PATH . '/app/Views/partials/empty_state.php';
            ?>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($locations as $loc): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="cf-card cf-card-interactive h-100 p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-3 rounded-3 bg-danger-subtle text-danger fs-4">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($loc['name']) ?></h5>
                                    <code class="extra-small text-muted"><?= htmlspecialchars($loc['code']) ?></code>
                                </div>
                            </div>
                            <span class="cf-badge <?= $loc['is_active'] ? 'cf-badge-found' : 'cf-badge-neutral' ?>">
                                <?= $loc['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </div>

                        <div class="mb-2">
                            <span class="cf-badge cf-badge-neutral extra-small">
                                <i class="bi bi-compass me-1 text-danger"></i> <?= htmlspecialchars($loc['campus_zone'] ?? 'Zona Utama') ?>
                            </span>
                        </div>

                        <p class="text-muted small mb-3">
                            <?= !empty($loc['description']) ? htmlspecialchars($loc['description']) : 'Tidak ada deskripsi lokasi.' ?>
                        </p>

                        <div class="d-flex align-items-center justify-content-between pt-3 border-top mt-auto">
                            <div class="extra-small text-muted">
                                <span class="text-danger fw-bold"><?= (int)$loc['lost_count'] ?></span> Hilang &bull; 
                                <span class="text-info fw-bold"><?= (int)$loc['found_count'] ?></span> Temuan
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary px-3" 
                                        data-bs-toggle="modal" data-bs-target="#modalEditLoc<?= (int)$loc['id'] ?>">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </button>
                                <form action="/admin/locations/<?= (int)$loc['id'] ?>/toggle" method="POST" class="d-inline">
                                    <?= \App\Middleware\CsrfMiddleware::field() ?>
                                    <button type="submit" class="btn btn-sm <?= $loc['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> px-3">
                                        <?= $loc['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Modal Edit Location -->
            <div class="modal fade" id="modalEditLoc<?= (int)$loc['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark">Edit Lokasi Kampus</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="/admin/locations/<?= (int)$loc['id'] ?>/update" method="POST">
                            <?= \App\Middleware\CsrfMiddleware::field() ?>
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Nama Gedung / Lokasi <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($loc['name']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Kode Gedung</label>
                                    <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($loc['code']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Zona Kampus</label>
                                    <input type="text" name="campus_zone" class="form-control" value="<?= htmlspecialchars($loc['campus_zone'] ?? 'Zona Utama') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Deskripsi Lokasi</label>
                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($loc['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-danger rounded-pill px-4">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Create Location -->
<div class="modal fade" id="modalCreateLocation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Tambah Lokasi Kampus Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/admin/locations/create" method="POST">
                <?= \App\Middleware\CsrfMiddleware::field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Gedung / Lokasi <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Perpustakaan Pusat Lantai 2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Kode Singkat</label>
                        <input type="text" name="code" class="form-control" placeholder="PERPUS-L2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Zona Kampus</label>
                        <input type="text" name="campus_zone" class="form-control" value="Zona Utama" placeholder="Zona Utara / Zona Selatan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Deskripsi Tambahan</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Patokan atau keterangan khusus gedung..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Simpan Lokasi</button>
                </div>
            </form>
        </div>
    </div>
</div>
