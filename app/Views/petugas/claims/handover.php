<?php
/**
 * app/Views/petugas/claims/handover.php
 * Formulir Serah Terima Fisik Barang Kepada Pemilik (Handover)
 */
?>

<div class="container py-4 cf-workspace-wide">
    <!-- Standalone Transparent Page Header (Aura Liquid Glass) -->
    <?php
    $headerConfig = [
        'eyebrow'     => 'Klaim & Penyerahan Fisik',
        'title'       => 'Serah Terima Fisik Barang',
        'icon'        => 'bi bi-box-arrow-right',
        'description' => 'Catat data serah terima dan pengembalian barang fisik kepada pemilik sah yang klaimnya telah disetujui.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Antrean Klaim', 'url' => '/petugas/claims'],
            ['label' => htmlspecialchars($claim['claim_number'], ENT_QUOTES, 'UTF-8'), 'url' => '/petugas/claims/' . (int)$claim['id']],
            ['label' => 'Serah Terima Fisik'],
        ],
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>
    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
            <div class="cf-card p-4">
                
                <!-- Ringkasan Barang & Pemilik -->
                <div class="p-3 rounded mb-4 bg-light border">
                    <div class="row g-2 align-items-center">
                        <div class="col-sm-3 text-center">
                            <?php if (!empty($claim['item_image'])): ?>
                                <img src="<?= htmlspecialchars($claim['item_image'], ENT_QUOTES, 'UTF-8') ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px;" alt="Barang">
                            <?php else: ?>
                                <i class="bi bi-box-seam fs-1 text-muted"></i>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-9">
                            <span class="cf-badge cf-badge-neutral font-monospace">
                                <?= htmlspecialchars($claim['item_code'] ?? 'L&F', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <h2 class="h6 fw-bold text-dark mb-1">
                                <?= htmlspecialchars($claim['item_title'] ?? 'Barang Temuan', ENT_QUOTES, 'UTF-8') ?>
                            </h2>
                            <div class="small text-muted">
                                <div><strong>Pemilik Sah:</strong> <?= htmlspecialchars($claim['claimant_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($claim['claimant_email'], ENT_QUOTES, 'UTF-8') ?>)</div>
                                <div><strong>Lokasi Rak Saat Ini:</strong> <?= htmlspecialchars($claim['storage_location_name'] ?? 'Gudang Center', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Serah Terima -->
                <form action="/petugas/claims/<?= $claim['id'] ?>/handover" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Tanggal & Jam Serah Terima -->
                    <div class="mb-3">
                        <label for="handover_date" class="form-label small fw-semibold text-dark">
                            Tanggal & Waktu Serah Terima <span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local" 
                               class="form-control" 
                               id="handover_date" 
                               name="handover_date" 
                               value="<?= date('Y-m-d\TH:i') ?>" 
                               required>
                    </div>

                    <!-- Petugas Penyerah -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark">
                            Petugas yang Menyerahkan
                        </label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>)" readonly>
                    </div>

                    <!-- Catatan Serah Terima / Bukti Identitas Pengambil -->
                    <div class="mb-4">
                        <label for="handover_notes" class="form-label small fw-semibold text-dark">
                            Catatan Administrasi Serah Terima <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  id="handover_notes" 
                                  name="handover_notes" 
                                  rows="4" 
                                  required 
                                  placeholder="Wajib mencatat:
1. Nomor identitas pengambil (NIM / NIP / NIK KTP)
2. Nama penerima fisik (jika diwakilkan, sebutkan nama & relasi)
3. Kondisi barang saat diserahkan (misal: kondisi baik, charger lengkap, menyala)"></textarea>
                        <div class="form-text small">Catatan ini akan tersimpan permanen di jejak audit pengembalian barang kampus.</div>
                    </div>

                    <!-- Konsekuensi Status -->
                    <div class="alert alert-info border-0 small p-3 mb-4">
                        <div class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i>Dampak Sistem Setelah Disimpan:</div>
                        <ul class="mb-0 ps-3">
                            <li>Status fisik barang (<code>items</code>) berubah menjadi <strong>Returned (Dikembalikan)</strong>.</li>
                            <li>Status laporan temuan (<code>found_reports</code>) berubah menjadi <strong>Completed (Dikembalikan kepada Pemilik)</strong>.</li>
                            <li>Status klaim (<code>claims</code>) berubah menjadi <strong>Completed (Selesai)</strong>.</li>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="/petugas/claims/<?= $claim['id'] ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-success px-4">
                            <i class="bi bi-check2-circle me-1"></i> Selesaikan Serah Terima Barang
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>
