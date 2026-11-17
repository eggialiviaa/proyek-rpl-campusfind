<?php
/**
 * app/Views/reports/found/edit.php
 * Formulir Edit Laporan Barang Temuan (Civitas) — Liquid Glass
 */
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-9 cf-form-wrap">

            <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
            <?php
            $headerConfig = [
                'eyebrow'     => 'Edit Laporan',
                'title'       => 'Edit Laporan Barang Temuan',
                'icon'        => 'bi bi-pencil',
                'description' => 'Perbarui data dan rincian laporan barang temuan nomor ' . htmlspecialchars($report['report_number'], ENT_QUOTES, 'UTF-8') . '.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => '/dashboard'],
                    ['label' => 'Barang Temuan', 'url' => '/reports/found'],
                    ['label' => htmlspecialchars($report['report_number'], ENT_QUOTES, 'UTF-8'), 'url' => '/reports/found/' . $report['id']],
                    ['label' => 'Edit'],
                ],
            ];
            require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
            ?>

            <!-- Card Form -->
            <div class="cf-card cf-form-card">

                <form action="/reports/found/<?= $report['id'] ?>/edit" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      id="form-edit-found">

                    <!-- CSRF Token -->
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Bagian 1: Informasi Dasar Barang (Publik) -->
                    <div class="cf-form-section">
                        <h2 class="cf-form-section-title">
                            <i class="bi bi-info-circle text-primary"></i> Informasi Dasar Barang
                        </h2>

                        <div class="row g-3">
                            <!-- Judul Barang -->
                            <div class="col-12">
                                <label for="input-title" class="cf-form-label">
                                    Nama / Judul Barang Temuan <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="cf-form-control" 
                                       id="input-title" 
                                       name="title" 
                                       value="<?= htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                       required 
                                       maxlength="150">
                            </div>

                            <!-- Kategori Barang -->
                            <div class="col-md-6">
                                <label for="select-category" class="cf-form-label">
                                    Kategori Barang <span class="text-danger">*</span>
                                </label>
                                <select class="form-select cf-form-control" id="select-category" name="category_id" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ((int) $report['category_id'] === (int) $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Kondisi Fisik -->
                            <div class="col-md-6">
                                <label for="select-condition" class="cf-form-label">
                                    Kondisi Fisik Barang <span class="text-danger">*</span>
                                </label>
                                <select class="form-select cf-form-control" id="select-condition" name="item_condition" required>
                                    <option value="sangat_baik" <?= ($report['item_condition'] === 'sangat_baik') ? 'selected' : '' ?>>Sangat Baik (Mulus / Normal)</option>
                                    <option value="baik" <?= ($report['item_condition'] === 'baik') ? 'selected' : '' ?>>Baik (Normal / Wajar)</option>
                                    <option value="rusak_ringan" <?= ($report['item_condition'] === 'rusak_ringan') ? 'selected' : '' ?>>Rusak Ringan (Lecet / Gores)</option>
                                    <option value="rusak_berat" <?= ($report['item_condition'] === 'rusak_berat') ? 'selected' : '' ?>>Rusak Berat (Pecah / Mati)</option>
                                </select>
                            </div>

                            <!-- Lokasi Kampus -->
                            <div class="col-md-6">
                                <label for="select-location" class="cf-form-label">
                                    Lokasi Kampus Ditemukan <span class="text-danger">*</span>
                                </label>
                                <select class="form-select cf-form-control" id="select-location" name="location_id" required>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>" <?= ((int) $report['location_id'] === (int) $loc['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Detail Spesifik Lokasi -->
                            <div class="col-md-6">
                                <label for="input-location-detail" class="cf-form-label">
                                    Detail Lokasi Spesifik
                                </label>
                                <input type="text" 
                                       class="cf-form-control" 
                                       id="input-location-detail" 
                                       name="location_detail" 
                                       value="<?= htmlspecialchars($report['location_detail'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <!-- Tanggal & Waktu -->
                            <div class="col-md-6">
                                <label for="input-found-date" class="cf-form-label">
                                    Tanggal Ditemukan <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       class="cf-form-control" 
                                       id="input-found-date" 
                                       name="found_date" 
                                       max="<?= date('Y-m-d') ?>" 
                                       value="<?= htmlspecialchars($report['found_date'], ENT_QUOTES, 'UTF-8') ?>" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="input-found-time" class="cf-form-label">
                                    Perkiraan Waktu
                                </label>
                                <input type="time" 
                                       class="cf-form-control" 
                                       id="input-found-time" 
                                       name="found_time" 
                                       value="<?= htmlspecialchars(substr($report['found_time'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <!-- Deskripsi Umum -->
                            <div class="col-12">
                                <label for="textarea-description" class="cf-form-label">
                                    Deskripsi Umum Barang <span class="text-danger">*</span>
                                </label>
                                <textarea class="cf-form-control" 
                                          id="textarea-description" 
                                          name="description" 
                                          rows="4" 
                                          required><?= htmlspecialchars($report['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <!-- Foto Barang Saat Ini & Unggah Baru -->
                            <div class="col-12">
                                <label class="cf-form-label">Foto Barang Saat Ini</label>
                                <?php if (!empty($report['primary_image'])): ?>
                                    <div class="mb-2 p-2 border rounded bg-light" style="max-width: 250px;">
                                        <img src="<?= htmlspecialchars($report['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                             alt="Foto Barang" 
                                             class="img-fluid rounded" 
                                             style="max-height: 150px; object-fit: cover;">
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small">Belum ada foto yang diunggah.</p>
                                <?php endif; ?>

                                <label for="input-image" class="cf-form-label">
                                    Unggah Foto Pengganti (Opsional)
                                </label>
                                <input type="file" 
                                       class="form-control cf-form-control" 
                                       id="input-image" 
                                       name="primary_image" 
                                       accept="image/jpeg,image/png,image/webp">
                                <div class="form-text small">Pilih file baru jika ingin mengganti foto saat ini.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian 2: Informasi Privat -->
                    <div class="cf-private-box" style="background: rgba(254, 242, 242, 0.7); border-color: rgba(254, 202, 202, 0.9);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-shield-lock-fill text-danger fs-5"></i>
                            <h2 class="h6 fw-bold text-danger mb-0">Informasi Internal &amp; Ciri Rahasia (Data Privat)</h2>
                        </div>
                        <p class="text-muted small mb-3">
                            🔒 Hanya Anda dan Petugas Lost &amp; Found yang dapat membaca catatan ini.
                        </p>

                        <div>
                            <label for="textarea-private" class="cf-form-label text-dark">
                                Detail Ciri Rahasia / Bukti Unik
                            </label>
                            <textarea class="cf-form-control bg-white" 
                                      id="textarea-private" 
                                      name="private_details" 
                                      rows="3"><?= htmlspecialchars($report['private_details'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="cf-form-actions">
                        <a href="/reports/found/<?= $report['id'] ?>" class="btn btn-outline-secondary px-4">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-primary px-4" id="btn-save-found">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>
