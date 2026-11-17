<?php
/**
 * app/Views/petugas/storage_locations.php
 * Manajemen Lokasi Rak & Lemari Penyimpanan di Lost & Found Center
 */
?>

<div class="container py-4 cf-workspace-wide cf-storage-container">
    <!-- Standalone Transparent Page Header (Aura Liquid Glass) -->
    <?php
    $storageActions = '<button type="button" class="btn btn-sm cf-btn-primary-glass px-3 py-2 shadow-sm d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalAddStorage">'
        . '<i class="bi bi-plus-lg"></i> <span class="fw-semibold">Tambah Lokasi Rak</span>'
        . '</button>';

    $headerConfig = [
        'eyebrow'     => 'Fasilitas & Gudang Posko',
        'title'       => 'Manajemen Lokasi Rak & Lemari Gudang',
        'icon'        => 'bi bi-archive-fill',
        'description' => 'Daftar rak berkunci, lemari arsip, dan zona penyimpanan fisik di posko Lost & Found Center.',
        'breadcrumbs' => [
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Lokasi Penyimpanan'],
        ],
        'actions'     => $storageActions,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Storage Locations Cards Overview -->
    <?php if (empty($locations)): ?>
        <div class="cf-card p-4 mb-4">
            <?php
            $icon = 'bi-box-seam';
            $title = 'Belum Ada Lokasi Rak';
            $message = 'Belum ada data rak atau lemari penyimpanan di Lost & Found Center.';
            $actionText = 'Tambah Lokasi Rak';
            $actionAttributes = 'data-bs-toggle="modal" data-bs-target="#modalAddStorage"';
            $actionIcon = 'bi-plus-lg';
            require BASE_PATH . '/app/Views/partials/empty_state.php';
            ?>
        </div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <?php foreach ($locations as $loc): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="cf-card cf-card-interactive p-3 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="cf-badge cf-badge-stored font-monospace fw-bold">
                                <?= htmlspecialchars($loc['code']) ?>
                            </span>
                            <span class="cf-badge <?= ($loc['remaining_capacity'] <= 5) ? 'cf-badge-danger' : 'cf-badge-found' ?>">
                                <?= $loc['current_items_count'] ?> / <?= $loc['capacity'] ?> Item
                            </span>
                        </div>

                        <h2 class="h6 fw-bold text-dark mb-1"><?= htmlspecialchars($loc['name']) ?></h2>
                        <div class="text-muted small mb-2"><i class="bi bi-geo me-1"></i><?= htmlspecialchars($loc['zone'] ?? 'Zona Umum') ?></div>
                        
                        <?php if (!empty($loc['description'])): ?>
                            <p class="text-muted small mb-3 flex-grow-1" style="font-size:0.8rem;"><?= htmlspecialchars($loc['description']) ?></p>
                        <?php endif; ?>

                        <!-- Capacity Progress Bar -->
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between small text-muted mb-1" style="font-size:0.75rem;">
                                <span>Kapasitas Terisi</span>
                                <span><?= $loc['usage_percentage'] ?? 0 ?>%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?= ($loc['usage_percentage'] >= 85) ? 'bg-danger' : (($loc['usage_percentage'] >= 50) ? 'bg-warning' : 'bg-success') ?>" 
                                     role="progressbar" 
                                     style="width: <?= min(100, $loc['usage_percentage'] ?? 0) ?>%;" 
                                     aria-valuenow="<?= $loc['usage_percentage'] ?? 0 ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Table of Storage Locations -->
        <div class="cf-card p-4">
            <h2 class="h6 fw-bold text-dark mb-3">Detail Seluruh Lokasi Rak Gudang</h2>
            <div class="table-responsive cf-table-wrapper">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Rak</th>
                            <th>Nama Rak / Lemari</th>
                            <th>Zona Posko</th>
                            <th>Kapasitas Maksimal</th>
                            <th>Item Tersimpan</th>
                            <th>Sisa Kapasitas</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $loc): ?>
                            <tr>
                                <td><code class="fw-bold"><?= htmlspecialchars($loc['code']) ?></code></td>
                                <td><strong><?= htmlspecialchars($loc['name']) ?></strong></td>
                                <td><?= htmlspecialchars($loc['zone'] ?? 'Zona Umum') ?></td>
                                <td><?= $loc['capacity'] ?> unit</td>
                                <td><span class="cf-badge cf-badge-neutral"><?= $loc['current_items_count'] ?> item</span></td>
                                <td>
                                    <span class="<?= ($loc['remaining_capacity'] <= 5) ? 'text-danger fw-bold' : 'text-success' ?>">
                                        <?= $loc['remaining_capacity'] ?> slot
                                    </span>
                                </td>
                                <td><span class="cf-badge cf-badge-found">Aktif</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Modal Tambah Lokasi Rak Baru -->
<div class="modal fade" id="modalAddStorage" tabindex="-1" aria-labelledby="modalAddStorageLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fs-6 fw-bold text-dark" id="modalAddStorageLabel">
                    <i class="bi bi-plus-circle text-primary me-1"></i> Tambah Lokasi Rak Penyimpanan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form action="/petugas/storage-locations/create" method="POST" id="form-add-storage">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label for="input-storage-code" class="cf-form-label">
                            Kode Rak / Lemari <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="cf-form-control font-monospace" 
                               id="input-storage-code" 
                               name="code" 
                               placeholder="Contoh: STG-EL-02, RAK-A-03" 
                               required 
                               maxlength="50">
                        <div class="form-text small">Kode unik lokasi rak di gudang (tidak boleh sama).</div>
                    </div>

                    <div class="mb-3">
                        <label for="input-storage-name" class="cf-form-label">
                            Nama Rak / Lemari <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="cf-form-control" 
                               id="input-storage-name" 
                               name="name" 
                               placeholder="Contoh: Lemari Kaca Elektronik B-02" 
                               required 
                               maxlength="100">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-7">
                            <label for="input-storage-zone" class="cf-form-label">
                                Zona / Area Posko
                            </label>
                            <input type="text" 
                                   class="cf-form-control" 
                                   id="input-storage-zone" 
                                   name="zone" 
                                   placeholder="Contoh: Zona A (High Security)" 
                                   maxlength="50">
                        </div>
                        <div class="col-md-5">
                            <label for="input-storage-capacity" class="cf-form-label">
                                Kapasitas (Unit) <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="cf-form-control" 
                                   id="input-storage-capacity" 
                                   name="capacity" 
                                   value="50" 
                                   min="1" 
                                   max="500" 
                                   required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="textarea-storage-desc" class="cf-form-label">
                            Deskripsi / Catatan Peruntukan
                        </label>
                        <textarea class="cf-form-control" 
                                  id="textarea-storage-desc" 
                                  name="description" 
                                  rows="2" 
                                  placeholder="Contoh: Khusus penyimpanan laptop, tablet, dan kamera berharga."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top gap-2">
                    <button type="button" class="btn btn-glass btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="btn-save-storage">
                        <i class="bi bi-check2 me-1"></i> Simpan Lokasi Rak
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
