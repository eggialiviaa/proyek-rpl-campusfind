<?php
/**
 * app/Views/claims/create.php
 * Formulir Pengajuan Klaim Kepemilikan Barang (Liquid Glass UI)
 */
?>

<div class="container py-4">

    <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
    <?php
    $headerConfig = [
        'eyebrow'     => 'Klaim Barang',
        'title'       => 'Formulir Klaim Kepemilikan Barang',
        'icon'        => 'bi bi-shield-check',
        'description' => 'Berikan informasi dan bukti privat kepemilikan untuk diverifikasi oleh Petugas Lost & Found Center.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Klaim Saya', 'url' => '/civitas/my-claims'],
            ['label' => 'Ajukan Klaim'],
        ],
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>
    <div class="row g-4">
        
        <!-- Kolom Kiri: Ringkasan Barang yang Diklaim -->
        <div class="col-lg-4">
            <div class="cf-card p-4 sticky-top" style="top: 20px;">
                <h2 class="h6 fw-bold text-dark mb-3">
                    <i class="bi bi-box-seam text-secondary me-2"></i>Barang yang Diklaim
                </h2>

                <?php if ($targetItem): ?>
                    <div class="mb-3 text-center" style="height: 180px; background: rgba(241, 245, 249, 0.8); border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($targetItem['primary_image'])): ?>
                            <img src="<?= htmlspecialchars($targetItem['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="<?= htmlspecialchars($targetItem['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div class="text-muted opacity-50">
                                <i class="bi bi-camera fs-1 d-block mb-1"></i>
                                <span class="small">Tanpa Foto</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-2">
                        <span class="cf-badge cf-badge-neutral font-monospace">
                            <?= htmlspecialchars($targetItem['item_code'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="cf-badge cf-badge-neutral ms-1">
                            <?= htmlspecialchars($targetItem['category_name'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <h3 class="h6 fw-bold text-dark mb-2">
                        <?= htmlspecialchars($targetItem['title'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <p class="text-muted small mb-3">
                        <?= htmlspecialchars($targetItem['description'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <div class="small text-muted border-top pt-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span><i class="bi bi-geo-alt me-1"></i>Lokasi Gudang:</span>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($targetItem['storage_location_name'] ?? 'Lost & Found Center', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="bi bi-heart-pulse me-1"></i>Kondisi Fisik:</span>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $targetItem['physical_condition'] ?? 'Baik')), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                <?php elseif ($foundReport): ?>
                    <div class="mb-3 text-center" style="height: 180px; background: rgba(241, 245, 249, 0.8); border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($foundReport['primary_image'])): ?>
                            <img src="<?= htmlspecialchars($foundReport['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="<?= htmlspecialchars($foundReport['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div class="text-muted opacity-50">
                                <i class="bi bi-camera fs-1 d-block mb-1"></i>
                                <span class="small">Tanpa Foto</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-2">
                        <span class="cf-badge cf-badge-neutral font-monospace">
                            <?= htmlspecialchars($foundReport['report_number'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <h3 class="h6 fw-bold text-dark mb-2">
                        <?= htmlspecialchars($foundReport['title'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>
                    <p class="text-muted small mb-0">
                        <?= htmlspecialchars($foundReport['description'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <!-- Peringatan Privasi -->
                <div class="alert alert-info border-0 mt-3 mb-0 p-3 small" style="background: rgba(14, 165, 233, 0.1); border-left: 3px solid #0284c7 !important;">
                    <div class="fw-bold text-primary mb-1">
                        <i class="bi bi-lock-fill me-1"></i>Jaminan Privasi Bukti
                    </div>
                    Informasi detail unik, nomor seri, dan dokumen bukti yang Anda masukkan bersifat <strong>rahasia (private)</strong>. Pengguna lain tidak dapat melihatnya. Hanya Petugas berwenang yang dapat memeriksa untuk verifikasi kepemilikan.
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Form Klaim -->
        <div class="col-lg-8">
            <div class="cf-card cf-form-card">
                
                <form action="/claims" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="item_id" value="<?= htmlspecialchars((string) ($itemId ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="found_report_id" value="<?= htmlspecialchars((string) ($foundId ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Alasan Klaim -->
                    <div class="mb-3">
                        <label for="claim_reason" class="form-label fw-semibold text-dark">
                            Alasan Mengajukan Klaim <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  id="claim_reason" 
                                  name="claim_reason" 
                                  rows="3" 
                                  required 
                                  placeholder="Jelaskan secara singkat bagaimana dan kapan Anda kehilangan barang ini sehingga yakin ini adalah milik Anda..."><?= htmlspecialchars($_POST['claim_reason'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="form-text small">Minimal 10 karakter. Berikan konteks kejadian kehilangan Anda.</div>
                    </div>

                    <!-- Informasi & Deskripsi Bukti Kepemilikan -->
                    <div class="mb-3">
                        <label for="ownership_proof_description" class="form-label fw-semibold text-dark">
                            Informasi Bukti Kepemilikan <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  id="ownership_proof_description" 
                                  name="ownership_proof_description" 
                                  rows="3" 
                                  required 
                                  placeholder="Contoh: Saya memiliki struk pembelian toko X / kardus asli / foto saya bersama barang ini / bukti mutasi e-wallet pembelian..."><?= htmlspecialchars($_POST['ownership_proof_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="form-text small">Sebutkan dokumen atau bukti pendukung apa yang Anda miliki atas barang ini.</div>
                    </div>

                    <!-- Detail Unik Barang (Private / Rahasia) -->
                    <div class="mb-3 p-3 rounded" style="background: rgba(248, 250, 252, 0.8); border: 1px dashed #cbd5e1;">
                        <label for="unique_features" class="form-label fw-semibold text-dark">
                            <i class="bi bi-key-fill text-warning me-1"></i>Detail Unik & Rahasia Barang <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control bg-white" 
                                  id="unique_features" 
                                  name="unique_features" 
                                  rows="4" 
                                  required 
                                  placeholder="Contoh tanda pengenal spesifik yang TIDAK tampak di deskripsi umum:
- Password / pola kunci layar
- Goresan kecil di sudut kanan bawah
- Stiker anime tersembunyi di balik casing
- Nomor IMEI / Serial Number
- Rincian isi di dalam kantong / dompet"><?= htmlspecialchars($_POST['unique_features'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="form-text text-muted small mt-1">
                            <i class="bi bi-info-circle me-1"></i>Informasi ini hanya dibuka oleh Petugas saat mencocokkan barang fisik. Semakin spesifik detail rahasia Anda, semakin cepat klaim diverifikasi.
                        </div>
                    </div>

                    <!-- Upload Berkas Bukti (Foto / Invoice / Struk) -->
                    <div class="mb-3">
                        <label for="evidence" class="form-label fw-semibold text-dark">
                            Unggah Foto Bukti atau Dokumen Pembelian (Opsional)
                        </label>
                        <input type="file" 
                               class="form-control" 
                               id="evidence" 
                               name="evidence" 
                               accept="image/jpeg,image/png,image/webp,application/pdf">
                        <div class="form-text small">
                            Format diizinkan: <strong>JPG, PNG, WebP, atau PDF</strong>. Maksimal <strong>5 MB</strong>. Contoh: struk belanja, kartu garansi, foto lama saat memakai barang, screenshot invoice.
                        </div>
                    </div>

                    <!-- Referensi ke Laporan Kehilangan Milik Sendiri (Jika ada) -->
                    <?php if (!empty($userLostReports)): ?>
                        <div class="mb-3">
                            <label for="lost_report_id" class="form-label fw-semibold text-dark">
                                Hubungkan dengan Laporan Kehilangan Anda (Opsional)
                            </label>
                            <select name="lost_report_id" id="lost_report_id" class="form-select">
                                <option value="">-- Tidak ada / Buat klaim mandiri --</option>
                                <?php foreach ($userLostReports as $lr): ?>
                                    <option value="<?= $lr['id'] ?>">
                                        [<?= htmlspecialchars($lr['report_number'], ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars($lr['title'], ENT_QUOTES, 'UTF-8') ?> (Hilang: <?= date('d/m/Y', strtotime($lr['lost_date'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Membantu Petugas melihat riwayat pelaporan Anda sebelumnya untuk mempercepat pencocokan.</div>
                        </div>
                    <?php endif; ?>

                    <!-- Catatan Tambahan -->
                    <div class="mb-4">
                        <label for="additional_notes" class="form-label fw-semibold text-muted">
                            Catatan Tambahan untuk Petugas (Opsional)
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="additional_notes" 
                               name="additional_notes" 
                               placeholder="Contoh: Saya bisa datang ke Center hari Selasa siang..." 
                               value="<?= htmlspecialchars($_POST['additional_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="cf-form-actions justify-content-between">
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-send-check me-1"></i> Kirim Pengajuan Klaim
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>
