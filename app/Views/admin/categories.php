<?php
/**
 * View: Master Data Kategori Barang (Admin)
 * File: app/Views/admin/categories.php
 */

$activeCount = count(array_filter($categories ?? [], fn($c) => !empty($c['is_active'])));
$totalCount = count($categories ?? []);
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/admin">Beranda admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kategori barang</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1 d-flex align-items-center flex-wrap gap-2">
                <span>Kategori klasifikasi barang</span>
                <span class="cf-badge cf-badge-found">
                    <?= $activeCount ?> Aktif
                </span>
                <?php if ($totalCount > $activeCount): ?>
                    <span class="cf-badge cf-badge-neutral">
                        <?= ($totalCount - $activeCount) ?> Nonaktif
                    </span>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0">Kelola kategori klasifikasi laporan barang hilang dan temuan kampus.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateCategory">
                <i class="bi bi-plus-lg me-1"></i> Tambah kategori
            </button>
        </div>
    </div>

    <!-- Category Grid -->
    <?php if (empty($categories)): ?>
        <div class="cf-card p-4">
            <?php
            $icon = 'bi-tags';
            $title = 'Belum Ada Kategori';
            $message = 'Belum ada kategori barang yang ditambahkan. Silakan buat kategori pertama Anda.';
            $actionText = 'Tambah Kategori';
            $actionAttributes = 'data-bs-toggle="modal" data-bs-target="#modalCreateCategory"';
            $actionIcon = 'bi-plus-lg';
            require BASE_PATH . '/app/Views/partials/empty_state.php';
            ?>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($categories as $cat): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="cf-card cf-card-interactive h-100 p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-3 rounded-3 bg-primary-subtle text-primary fs-4">
                                    <i class="bi <?= htmlspecialchars((string)($cat['icon'] ?? 'bi-tag'), ENT_QUOTES, 'UTF-8') ?>"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars((string)($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h5>
                                    <code class="extra-small text-muted"><?= htmlspecialchars((string)($cat['slug'] ?? $cat['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                                </div>
                            </div>
                            <span class="cf-badge <?= !empty($cat['is_active']) ? 'cf-badge-found' : 'cf-badge-neutral' ?>">
                                <?= !empty($cat['is_active']) ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </div>

                        <p class="text-muted small mb-3">
                            <?= !empty($cat['description']) ? htmlspecialchars((string)$cat['description'], ENT_QUOTES, 'UTF-8') : 'Tidak ada deskripsi tambahan.' ?>
                        </p>

                        <div class="d-flex align-items-center justify-content-between pt-3 border-top mt-auto">
                            <div class="extra-small text-muted">
                                <span class="text-danger fw-bold"><?= (int)($cat['lost_count'] ?? 0) ?></span> Hilang &bull; 
                                <span class="text-info fw-bold"><?= (int)($cat['found_count'] ?? 0) ?></span> Temuan
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary px-3" 
                                        data-bs-toggle="modal" data-bs-target="#modalEditCat<?= (int)$cat['id'] ?>">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </button>
                                <form action="/admin/categories/<?= (int)$cat['id'] ?>/toggle" method="POST" class="d-inline">
                                    <?= \App\Middleware\CsrfMiddleware::field() ?>
                                    <button type="submit" class="btn btn-sm <?= !empty($cat['is_active']) ? 'btn-outline-danger' : 'btn-outline-success' ?> px-3">
                                        <?= !empty($cat['is_active']) ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Modal Edit Category -->
            <div class="modal fade" id="modalEditCat<?= (int)$cat['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark">Edit Kategori Barang</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="/admin/categories/<?= (int)$cat['id'] ?>/update" method="POST">
                            <?= \App\Middleware\CsrfMiddleware::field() ?>
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Nama Kategori <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars((string)($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Kode Unik (Slug)</label>
                                    <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars((string)($cat['slug'] ?? $cat['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Icon Bootstrap Class</label>
                                    <input type="text" name="icon" class="form-control" value="<?= htmlspecialchars((string)($cat['icon'] ?? 'bi-tag'), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Deskripsi</label>
                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars((string)($cat['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Create Category -->
<div class="modal fade" id="modalCreateCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Tambah Kategori Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/admin/categories/create" method="POST">
                <?= \App\Middleware\CsrfMiddleware::field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Elektronik &amp; Gadget">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Kode Unik (Slug)</label>
                        <input type="text" name="slug" class="form-control" placeholder="elektronik_gadget">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Icon (Bootstrap Icon Class)</label>
                        <input type="text" name="icon" class="form-control" value="bi-laptop" placeholder="bi-laptop">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Deskripsi Singkat</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Deskripsi jenis barang dalam kategori ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>
