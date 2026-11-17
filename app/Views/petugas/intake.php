<?php
/**
 * app/Views/petugas/intake.php
 * Formulir Penerimaan Fisik Barang (Intake) ke Lost & Found Center
 */
?>

<div class="container py-4 cf-workspace-wide">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/petugas/handover">Pending Handover</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Penerimaan Barang (Intake)</li>
                </ol>
            </nav>

            <div class="cf-card p-4 p-md-5">

                <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                    <div class="cf-stat-icon primary">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                    <div>
                        <h1 class="h4 fw-bold text-dark mb-0">Penerimaan &amp; Pemeriksaan Fisik Barang (Intake)</h1>
                        <p class="text-muted small mb-0">
                            Registrasikan barang temuan fisik yang diterima di posko ke dalam inventaris kanonik Lost &amp; Found Center.
                        </p>
                    </div>
                </div>

                <!-- Ringkasan Laporan Temuan Asal -->
                <div class="p-3 rounded-3 mb-4 bg-light border">
                    <h2 class="h6 fw-bold text-dark mb-2">
                        <i class="bi bi-file-earmark-text text-primary me-1"></i> Data Laporan Digital Penemu:
                    </h2>
                    <div class="row g-2 small text-dark">
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Nomor Laporan Digital:</span>
                            <code><strong><?= htmlspecialchars($report['report_number']) ?></strong></code>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Nama Barang:</span>
                            <strong><?= htmlspecialchars($report['title']) ?></strong> (<?= htmlspecialchars($report['category_name']) ?>)
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Penemu / Civitas:</span>
                            <strong><?= htmlspecialchars($report['reporter_name']) ?></strong> (<?= htmlspecialchars($report['reporter_email']) ?>)
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Lokasi &amp; Tanggal Ditemukan:</span>
                            <?= htmlspecialchars($report['location_name']) ?> &bull; <?= date('d/m/Y', strtotime($report['found_date'])) ?>
                        </div>
                        <?php if (!empty($report['private_details'])): ?>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <span class="text-danger fw-semibold d-block"><i class="bi bi-shield-lock me-1"></i>Catatan Rahasia / Verifikasi Penemu:</span>
                                <div class="bg-white p-2 rounded border text-muted small mt-1"><?= htmlspecialchars($report['private_details']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Form Intake -->
                <form action="/petugas/intake/<?= $report['id'] ?>" method="POST" id="form-intake-store">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <h2 class="h6 fw-bold text-primary mb-3">
                        <i class="bi bi-boxes me-1"></i> Parameter Penyimpanan Fisik &amp; Hasil Pemeriksaan Awal
                    </h2>

                    <div class="row g-3 mb-4">
                        <!-- Kode Penyimpanan / Item Code Unik -->
                        <div class="col-md-6">
                            <label for="input-item-code" class="cf-form-label">
                                Kode Barang Fisik (Item Code) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted font-monospace"><i class="bi bi-upc-scan"></i></span>
                                <input type="text" 
                                       class="cf-form-control font-monospace fw-bold" 
                                       id="input-item-code" 
                                       name="item_code" 
                                       value="<?= htmlspecialchars($suggestedCode) ?>" 
                                       required 
                                       maxlength="50"
                                       aria-describedby="itemCodeHelp">
                            </div>
                            <div class="form-text small" id="itemCodeHelp">Kode unik fisik kanonik. Format otomatis disarankan (<code>ITM-...</code>).</div>
                        </div>

                        <!-- Lokasi Rak / Lemari Penyimpanan -->
                        <div class="col-md-6">
                            <label for="select-storage" class="cf-form-label">
                                Alokasi Rak / Lemari Gudang <span class="text-danger">*</span>
                            </label>
                            <select class="form-select cf-form-control" id="select-storage" name="storage_location_id" required>
                                <option value="" selected disabled>-- Pilih Lokasi Rak / Lemari --</option>
                                <?php foreach ($storageLocations as $sl): ?>
                                    <option value="<?= $sl['id'] ?>">
                                        <?= htmlspecialchars($sl['code']) ?> — <?= htmlspecialchars($sl['name']) ?> (<?= $sl['current_items_count'] ?? 0 ?>/<?= $sl['capacity'] ?> terisi)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Kondisi Fisik Aktual Hasil Pemeriksaan -->
                        <div class="col-md-6">
                            <label for="select-physical-condition" class="cf-form-label">
                                Kondisi Fisik Hasil Pemeriksaan Awal <span class="text-danger">*</span>
                            </label>
                            <select class="form-select cf-form-control" id="select-physical-condition" name="physical_condition" required>
                                <option value="sangat_baik" <?= ($report['item_condition'] === 'sangat_baik') ? 'selected' : '' ?>>Sangat Baik (Mulus / Berfungsi Normal)</option>
                                <option value="baik" <?= ($report['item_condition'] === 'baik') ? 'selected' : '' ?>>Baik (Normal / Wajar)</option>
                                <option value="rusak_ringan" <?= ($report['item_condition'] === 'rusak_ringan') ? 'selected' : '' ?>>Rusak Ringan (Gores / Lecet)</option>
                                <option value="rusak_berat" <?= ($report['item_condition'] === 'rusak_berat') ? 'selected' : '' ?>>Rusak Berat (Pecah / Mati Total)</option>
                            </select>
                        </div>

                        <!-- Status Fisik Awal Barang -->
                        <div class="col-md-6">
                            <label for="select-physical-status" class="cf-form-label">
                                Status Fisik Barang di Center <span class="text-danger">*</span>
                            </label>
                            <select class="form-select cf-form-control" id="select-physical-status" name="physical_status" required>
                                <option value="stored" selected>Tersimpan di Gudang (Stored)</option>
                                <option value="in_inspection">Sedang Diperiksa (In Inspection)</option>
                                <option value="pending_inspection">Menunggu Pemeriksaan Teknis</option>
                            </select>
                        </div>

                        <!-- Catatan Internal Petugas -->
                        <div class="col-12">
                            <label for="textarea-internal-notes" class="cf-form-label">
                                Catatan Pemeriksaan &amp; Dokumen Internal Petugas (Privat)
                            </label>
                            <textarea class="cf-form-control" 
                                      id="textarea-internal-notes" 
                                      name="internal_notes" 
                                      rows="3" 
                                      placeholder="Contoh: Diterima di meja piket oleh Petugas. Dicek charger masih menyala. Disimpan di rak berkunci baris ke-2."></textarea>
                            <div class="form-text small">Catatan ini hanya dapat dibaca oleh Petugas &amp; Admin (tidak muncul ke publik).</div>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/petugas/handover" class="btn btn-glass px-4">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-primary px-4" id="btn-submit-intake">
                            <i class="bi bi-box-arrow-in-down me-1"></i> Konfirmasi Penerimaan &amp; Simpan ke Gudang
                        </button>
                    </div>
                </form>

            </div>

        </div>
    </div>
</div>
