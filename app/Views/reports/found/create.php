<?php
/**
 * app/Views/reports/found/create.php
 * Formulir Pelaporan Barang Ditemukan (Civitas) — Liquid Glass
 */
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-9 cf-form-wrap">

            <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
            <?php
            $headerConfig = [
                'eyebrow'     => 'Lapor Temuan',
                'title'       => 'Laporkan Barang yang Anda Temukan',
                'icon'        => 'bi bi-hand-thumbs-up',
                'description' => 'Terima kasih atas kepedulian Anda! Isi detail barang temuan agar dapat segera kembali ke pemiliknya.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => '/dashboard'],
                    ['label' => 'Barang Temuan', 'url' => '/reports/found'],
                    ['label' => 'Buat Laporan Temuan'],
                ],
            ];
            require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
            ?>

            <!-- Card Form -->
            <div class="cf-card cf-form-card">

                <form action="/reports/found/create" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      id="form-found-report">

                    <!-- CSRF Token -->
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Bagian 1: Informasi Dasar Barang (Publik) -->
                    <div class="cf-form-section">
                        <h2 class="cf-form-section-title">
                            <i class="bi bi-info-circle text-success"></i> Informasi Dasar Barang Temuan (Ditampilkan ke Publik)
                        </h2>

                        <div class="row g-3">
                            <!-- Judul / Nama Barang -->
                            <div class="col-12">
                                <label for="input-title" class="cf-form-label">
                                    Nama / Judul Barang Temuan <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="cf-form-control" 
                                       id="input-title" 
                                       name="title" 
                                       placeholder="Contoh: Flashdisk SanDisk 64GB Hitam, Jaket Parasut Biru Navy" 
                                       required 
                                       maxlength="150"
                                       autofocus>
                                <div class="form-text small">Sebutkan merk, tipe umum, atau warna dominan barang.</div>
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

                            <!-- Kondisi Barang Saat Ditemukan -->
                            <div class="col-md-6">
                                <label for="select-condition" class="cf-form-label">
                                    Kondisi Fisik Barang <span class="text-danger">*</span>
                                </label>
                                <select class="form-select cf-form-control" id="select-condition" name="item_condition" required>
                                    <option value="sangat_baik">Sangat Baik (Mulus / Normal)</option>
                                    <option value="baik" selected>Baik (Normal / Wajar)</option>
                                    <option value="rusak_ringan">Rusak Ringan (Lecet / Gores)</option>
                                    <option value="rusak_berat">Rusak Berat (Pecah / Mati)</option>
                                </select>
                            </div>

                            <!-- Lokasi Ditemukan -->
                            <div class="col-md-6">
                                <label for="select-location" class="cf-form-label">
                                    Lokasi Kampus Ditemukan <span class="text-danger">*</span>
                                </label>
                                <select class="form-select cf-form-control" id="select-location" name="location_id" required>
                                    <option value="" selected disabled>-- Pilih Area Lokasi Kampus --</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>">
                                            <?= htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Detail Spesifik Lokasi -->
                            <div class="col-md-6">
                                <label for="input-location-detail" class="cf-form-label">
                                    Detail Posisi Spesifik (Opsional)
                                </label>
                                <input type="text" 
                                       class="cf-form-control" 
                                       id="input-location-detail" 
                                       name="location_detail" 
                                       placeholder="Contoh: Di bawah kursi baris kedua kelas 204">
                            </div>

                            <!-- Tanggal & Waktu Penemuan -->
                            <div class="col-md-6">
                                <label for="input-found-date" class="cf-form-label">
                                    Tanggal Ditemukan <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       class="cf-form-control" 
                                       id="input-found-date" 
                                       name="found_date" 
                                       max="<?= date('Y-m-d') ?>" 
                                       value="<?= date('Y-m-d') ?>" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="input-found-time" class="cf-form-label">
                                    Perkiraan Waktu (Opsional)
                                </label>
                                <input type="time" 
                                       class="cf-form-control" 
                                       id="input-found-time" 
                                       name="found_time">
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
                                          placeholder="Jelaskan ciri-ciri umum yang tampak luar (warna, bentuk, merk, kondisi saat ditemukan)..." 
                                          required></textarea>
                                <div class="form-text small">Hindari menulis nomor seri lengkap di sini agar pemilik asli yang membuktikannya nanti.</div>
                            </div>

                            <!-- Foto Barang Opsional -->
                            <div class="col-12">
                                <label for="input-image" class="cf-form-label">
                                    Foto Barang Temuan (Opsional tapi Sangat Membantu)
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

                    <!-- Bagian 2: Informasi Sensitif / Rahasia & Lokasi Penyimpanan Sementara -->
                    <div class="cf-private-box" style="background: rgba(254, 242, 242, 0.7); border-color: rgba(254, 202, 202, 0.9);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-shield-lock-fill text-danger fs-5"></i>
                            <h2 class="h6 fw-bold text-danger mb-0">Informasi Internal &amp; Ciri Rahasia (Data Privat)</h2>
                        </div>
                        <p class="text-muted small mb-3">
                            🔒 <strong>Terlindungi:</strong> Informasi ini <strong>TIDAK AKAN DITAMPILKAN KE PUBLIK</strong>. Hanya Anda dan Petugas Lost &amp; Found Center yang dapat melihatnya.
                        </p>

                        <div>
                            <label for="textarea-private" class="cf-form-label text-dark">
                                Ciri Rahasia / Catatan Penyimpanan Sementara (Opsional)
                            </label>
                            <textarea class="cf-form-control bg-white" 
                                      id="textarea-private" 
                                      name="private_details" 
                                      rows="3" 
                                      placeholder="Contoh: Nomor seri barang, isi spesifik dompet, ciri fisik tersembunyi, atau catatan lokasi penyimpanan sementara jika barang masih Anda pegang..."></textarea>
                        </div>
                    </div>

                    <!-- Guidance Info Box -->
                    <div class="p-3 rounded-3 mb-4 bg-light border">
                        <div class="d-flex gap-2 align-items-start">
                            <i class="bi bi-geo-alt-fill text-primary fs-5 mt-1"></i>
                            <div class="small">
                                <strong>Alur Penyerahan Fisik Barang:</strong><br>
                                <span class="text-muted">
                                    Setelah membuat laporan ini, Anda disarankan untuk menyerahkan barang fisik ke <strong>Lost &amp; Found Center</strong> (Gedung Rektorat Lt. 1) agar petugas dapat memverifikasi dan menyimpannya di gudang berkunci yang aman.
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="cf-form-actions">
                        <a href="/reports/found" class="btn btn-outline-secondary px-4">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-success px-4" id="btn-submit-found">
                            <i class="bi bi-check2-circle me-1"></i> Terbitkan Laporan Temuan
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>
