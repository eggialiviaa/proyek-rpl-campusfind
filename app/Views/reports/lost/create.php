<?php
/**
 * app/Views/reports/lost/create.php
 * Formulir Pembuatan Laporan Kehilangan Barang (Civitas)
 */
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-9 cf-form-wrap">

            <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
            <?php
            $headerConfig = [
                'eyebrow'     => 'Lapor Kehilangan',
                'title'       => 'Buat Laporan Barang Hilang',
                'icon'        => 'bi bi-search',
                'description' => 'Isi detail barang yang hilang dengan sejelas mungkin agar sistem dan petugas dapat mencocokkannya.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => '/dashboard'],
                    ['label' => 'Barang Hilang', 'url' => '/reports/lost'],
                    ['label' => 'Buat Laporan'],
                ],
            ];
            require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
            ?>

            <!-- Card Form -->
            <div class="cf-card cf-form-card">

                <form action="/reports/lost/create" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      id="form-lost-report">

                    <!-- CSRF Token -->
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Bagian 1: Informasi Umum Barang (Publik) -->
                    <div class="cf-form-section">
                        <h2 class="cf-form-section-title">
                            <i class="bi bi-info-circle text-primary"></i> Informasi Dasar Barang (Ditampilkan ke Publik)
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
                                       placeholder="Contoh: Dompet Kulit Cokelat Braun Buffel, Laptop MacBook Air M1" 
                                       required 
                                       maxlength="150"
                                       autofocus>
                                <div class="form-text small">Sebutkan merk, tipe, atau warna utama barang.</div>
                            </div>

                            <!-- Kategori Barang -->
                            <div class="col-md-6">
                                <label for="select-category" class="cf-form-label">
                                    Kategori Barang <span class="text-danger">*</span>
                                </label>
                                <select class="form-select cf-form-control" id="select-category" name="category_id" required>
                                    <option value="" selected disabled>-- Pilih Kategori --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>">
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
                                    <option value="" selected disabled>-- Pilih Lokasi Kampus --</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>">
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
                                       placeholder="Contoh: Lantai 2 Meja Belajar No. 14, dekat rak buku sains">
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
                                       value="<?= date('Y-m-d') ?>" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="input-lost-time" class="cf-form-label">
                                    Perkiraan Waktu (Opsional)
                                </label>
                                <input type="time" 
                                       class="cf-form-control" 
                                       id="input-lost-time" 
                                       name="lost_time">
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
                                          placeholder="Jelaskan ciri-ciri umum seperti warna, bahan, gantungan, stiker luar, atau kondisi fisik barang..." 
                                          required></textarea>
                                <div class="form-text small">Informasi ini akan dapat dibaca oleh civitas akademika lain.</div>
                            </div>

                            <!-- Foto Barang Opsional -->
                            <div class="col-12">
                                <label for="input-image" class="cf-form-label">
                                    Foto Barang (Opsional)
                                </label>
                                <input type="file" 
                                       class="form-control cf-form-control" 
                                       id="input-image" 
                                       name="primary_image" 
                                       accept="image/jpeg,image/png,image/webp">
                                <div class="form-text small">Format JPG, PNG, atau WebP (Maksimal 3 MB).</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian 2: Informasi Sensitif / Privat (Privasi Terjaga) -->
                    <div class="cf-private-box" style="background: rgba(254, 242, 242, 0.7); border-color: rgba(254, 202, 202, 0.9);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-shield-lock-fill text-danger fs-5"></i>
                            <h2 class="h6 fw-bold text-danger mb-0">Informasi Sensitif / Verifikasi Rahasia (Privat)</h2>
                        </div>
                        <p class="text-muted small mb-3">
                            🔒 <strong>Penting:</strong> Data di bawah ini <strong>TIDAK AKAN DITAMPILKAN KE PUBLIK</strong>. Hanya Anda dan Petugas Lost &amp; Found Center yang dapat melihatnya untuk memverifikasi keaslian barang saat ditemukan (mencegah klaim palsu).
                        </p>

                        <div>
                            <label for="textarea-private" class="cf-form-label text-dark">
                                Detail Ciri Rahasia / Bukti Unik (Opsional tapi Sangat Disarankan)
                            </label>
                            <textarea class="cf-form-control bg-white" 
                                      id="textarea-private" 
                                      name="private_details" 
                                      rows="3" 
                                      placeholder="Contoh: Nomor serial/IMEI, isi kartu di dalam dompet, wallpaper lockscreen, goresan di sudut bawah, nominal uang..."></textarea>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="cf-form-actions">
                        <a href="/reports/lost" class="btn btn-outline-secondary px-4">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-primary px-4" id="btn-submit-report">
                            <i class="bi bi-check2-circle me-1"></i> Terbitkan Laporan
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>
