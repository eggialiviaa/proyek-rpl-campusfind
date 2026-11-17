<?php
/**
 * app/Views/reports/lost/edit.php
 * Formulir Edit Laporan Kehilangan Barang (Hanya Pemilik / Admin)
 */
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-9 cf-form-wrap">

            <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
            <?php
            $statusBadge = '<span class="cf-badge cf-badge-lost"><i class="bi bi-search"></i> ' . ucfirst($report['status']) . '</span>';
            $headerConfig = [
                'eyebrow'     => 'Edit Laporan',
                'title'       => 'Edit Laporan Kehilangan',
                'icon'        => 'bi bi-pencil',
                'description' => 'Perbarui data dan rincian laporan barang hilang nomor ' . htmlspecialchars($report['report_number'], ENT_QUOTES, 'UTF-8') . '.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => '/dashboard'],
                    ['label' => 'Barang Hilang', 'url' => '/reports/lost'],
                    ['label' => htmlspecialchars($report['report_number'], ENT_QUOTES, 'UTF-8'), 'url' => '/reports/lost/' . $report['id']],
                    ['label' => 'Edit'],
                ],
                'badge'       => $statusBadge,
            ];
            require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
            ?>

            <!-- Card Form -->
            <div class="cf-card cf-form-card">

                <form action="/reports/lost/<?= $report['id'] ?>/edit" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      id="form-edit-lost-report">

                    <!-- CSRF Token -->
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Bagian 1: Informasi Dasar -->
                    <div class="cf-form-section">
                        <h2 class="cf-form-section-title">
                            <i class="bi bi-info-circle text-primary"></i> Informasi Dasar Barang (Publik)
                        </h2>
                        </h2>

                        <div class="row g-3">
                            <!-- Judul Barang -->
                            <div class="col-12">
                                <label for="input-title" class="cf-form-label">
                                    Nama / Judul Barang <span class="text-danger">*</span>
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
                                        <option value="<?= $cat['id'] ?>" <?= ($report['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Lokasi Terakhir Terlihat -->
                            <div class="col-md-6">
                                <label for="select-location" class="cf-form-label">
                                    Lokasi Terakhir Terlihat <span class="text-danger">*</span>
                                </label>
                                <select class="form-select cf-form-control" id="select-location" name="last_seen_location_id" required>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>" <?= ($report['last_seen_location_id'] == $loc['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Detail Spesifik Lokasi -->
                            <div class="col-12">
                                <label for="input-location-detail" class="cf-form-label">
                                    Detail Lokasi Spesifik (Opsional)
                                </label>
                                <input type="text" 
                                       class="cf-form-control" 
                                       id="input-location-detail" 
                                       name="location_detail" 
                                       value="<?= htmlspecialchars($report['location_detail'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <!-- Tanggal & Waktu Kehilangan -->
                            <div class="col-md-6">
                                <label for="input-lost-date" class="cf-form-label">
                                    Tanggal Terakhir Terlihat <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       class="cf-form-control" 
                                       id="input-lost-date" 
                                       name="lost_date" 
                                       max="<?= date('Y-m-d') ?>" 
                                       value="<?= htmlspecialchars($report['lost_date'], ENT_QUOTES, 'UTF-8') ?>" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="input-lost-time" class="cf-form-label">
                                    Perkiraan Waktu (Opsional)
                                </label>
                                <input type="time" 
                                       class="cf-form-control" 
                                       id="input-lost-time" 
                                       name="lost_time"
                                       value="<?= htmlspecialchars($report['lost_time'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
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

                            <!-- Foto Barang -->
                            <div class="col-12">
                                <label for="input-image" class="cf-form-label">
                                    Ganti Foto Barang (Opsional)
                                </label>
                                <?php if (!empty($report['primary_image'])): ?>
                                    <div class="d-flex align-items-center gap-3 mb-2 p-2 bg-light rounded border">
                                        <img src="<?= htmlspecialchars($report['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                             alt="Foto saat ini" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                        <span class="text-muted small">Foto tersimpan saat ini. Unggah file baru untuk mengganti.</span>
                                    </div>
                                <?php endif; ?>
                                <input type="file" 
                                       class="form-control cf-form-control" 
                                       id="input-image" 
                                       name="primary_image" 
                                       accept="image/jpeg,image/png,image/webp">
                            </div>
                        </div>
                    </div>

                    <!-- Bagian 2: Informasi Sensitif / Privat -->
                    <div class="cf-private-box" style="background: rgba(254, 242, 242, 0.7); border-color: rgba(254, 202, 202, 0.9);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-shield-lock-fill text-danger fs-5"></i>
                            <h2 class="h6 fw-bold text-danger mb-0">Informasi Sensitif / Verifikasi Rahasia (Privat)</h2>
                        </div>
                        <p class="text-muted small mb-3">
                            🔒 Data ini <strong>HANYA DILIHAT OLEH ANDA &amp; PETUGAS</strong>.
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
                        <a href="/reports/lost/<?= $report['id'] ?>" class="btn btn-outline-secondary px-4">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>
