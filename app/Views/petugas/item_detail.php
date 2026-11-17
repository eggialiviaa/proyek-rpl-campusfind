<?php
/**
 * app/Views/petugas/item_detail.php
 * Detail Barang Fisik Kanonik & Manajemen Status Fisik di Lost & Found Center
 */
?>

<div class="container py-4 cf-workspace-wide">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/petugas/inventory">Center Inventory</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($item['item_code']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Main Physical Item Details -->
        <div class="col-lg-8">
            <div class="cf-card p-4 p-md-5 mb-4">

                <!-- Header Info -->
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="cf-badge cf-badge-info font-monospace fs-6 px-2 py-1">
                                <i class="bi bi-upc-scan me-1"></i><?= htmlspecialchars($item['item_code']) ?>
                            </span>
                            <span class="cf-badge cf-badge-neutral">
                                ID: #<?= $item['id'] ?>
                            </span>
                        </div>
                        <h1 class="h3 fw-bold text-dark mb-1">
                            <?= htmlspecialchars($item['title']) ?>
                        </h1>
                        <div class="text-muted small">
                            Kategori: <strong><?= htmlspecialchars($item['category_name']) ?></strong> &bull; Didaftarkan: <?= date('d F Y, H:i', strtotime($item['registered_at'])) ?> WIB
                        </div>
                    </div>

                    <!-- 2 Distinct Status Badges -->
                    <div class="text-end">
                        <?php
                        $isTerminal = in_array($item['status'], ['returned', 'donated', 'disposed'], true);
                        $statusBadgeClass = match($item['status']) {
                            'returned' => 'cf-badge cf-badge-neutral',
                            'donated', 'disposed' => 'cf-badge cf-badge-danger',
                            'in_claim' => 'cf-badge cf-badge-warning',
                            'ready_for_handover' => 'cf-badge cf-badge-info',
                            'stored' => 'cf-badge cf-badge-stored',
                            default => 'cf-badge cf-badge-info',
                        };
                        $statusText = ($item['status'] === 'returned') ? 'Sudah Diserahkan kepada Pemilik' : \App\Models\ItemModel::getPhysicalStatusLabel($item['status']);
                        ?>
                        <div class="mb-1">
                            <span class="<?= $statusBadgeClass ?> fs-6 px-3 py-2">
                                <i class="bi bi-box-seam me-1"></i> Fisik: <?= $statusText ?>
                            </span>
                        </div>
                        <?php if (!empty($item['found_report_status'])): ?>
                            <div>
                                <span class="cf-badge cf-badge-info small">
                                    <i class="bi bi-file-earmark-text me-1"></i> Laporan: <?= \App\Models\FoundReportModel::getStatusLabel($item['found_report_status']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Image Preview (jika ada) -->
                <?php if (!empty($item['primary_image'])): ?>
                    <div class="mb-4 text-center p-2 rounded-3" style="background: rgba(241, 245, 249, 0.8); border: 1px solid var(--cf-border);">
                        <img src="<?= htmlspecialchars($item['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                             alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>" 
                             class="img-fluid rounded-3" 
                             style="max-height: 380px; object-fit: contain;">
                    </div>
                <?php endif; ?>

                <!-- Physical Attributes Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="p-3 rounded bg-light border">
                            <span class="text-muted small d-block">Lokasi Rak / Status Penyimpanan</span>
                            <?php if ($item['status'] === 'returned' || empty($item['storage_location_id'])): ?>
                                <strong class="text-secondary fs-6">
                                    <i class="bi bi-box-arrow-up-right me-1 text-secondary"></i> Sudah Diserahkan (Dilepas dari Rak)
                                </strong>
                                <?php if (!empty($item['returned_at'])): ?>
                                    <div class="text-muted small mt-1">Diserahkan: <?= date('d/m/Y H:i', strtotime($item['returned_at'])) ?> WIB</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <strong class="text-primary fs-6">
                                    <i class="bi bi-grid-3x3 me-1"></i><?= htmlspecialchars($item['storage_name'] ?? 'Belum dialokasikan') ?>
                                </strong>
                                <?php if (!empty($item['storage_code'])): ?>
                                    <span class="cf-badge cf-badge-neutral ms-1 font-monospace"><?= htmlspecialchars($item['storage_code']) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded bg-light border">
                            <span class="text-muted small d-block">Kondisi Fisik Terverifikasi</span>
                            <strong class="text-success">
                                <i class="bi bi-check2-circle me-1"></i><?= \App\Models\ItemModel::getConditionLabel($item['physical_condition']) ?>
                            </strong>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded bg-light border">
                            <span class="text-muted small d-block">Petugas Penerima (Intake)</span>
                            <strong class="text-dark">
                                <i class="bi bi-person-badge me-1 text-primary"></i><?= htmlspecialchars($item['verifier_name'] ?? 'Petugas') ?>
                            </strong>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded bg-light border">
                            <span class="text-muted small d-block">Laporan Digital Terkait</span>
                            <?php if (!empty($item['found_report_number'])): ?>
                                <a href="/reports/found/<?= $item['found_report_id'] ?>" class="fw-bold text-decoration-none">
                                    <i class="bi bi-link-45deg me-1"></i><?= htmlspecialchars($item['found_report_number']) ?>
                                </a>
                                <span class="text-muted small d-block">Oleh: <?= htmlspecialchars($item['reporter_name'] ?? '-') ?></span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Item mandiri tanpa laporan temuan</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Deskripsi Barang Fisik -->
                <div class="mb-4">
                    <h2 class="h6 fw-bold text-dark mb-2">Deskripsi Barang Fisik</h2>
                    <div class="p-3 rounded bg-white border text-dark" style="line-height: 1.7; white-space: pre-line;">
                        <?= htmlspecialchars($item['description']) ?>
                    </div>
                </div>

                <!-- Catatan Internal Petugas -->
                <?php if (!empty($item['internal_notes'])): ?>
                    <div class="p-4 rounded-3 mb-4" style="background: rgba(254, 243, 199, 0.7); border: 1px solid rgba(252, 211, 77, 0.9);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-lock-fill text-warning-emphasis fs-5"></i>
                            <h3 class="h6 fw-bold text-warning-emphasis mb-0">Catatan Internal &amp; Berita Acara Petugas (Privat)</h3>
                        </div>
                        <div class="bg-white p-3 rounded border text-dark small" style="line-height: 1.6; white-space: pre-line;">
                            <?= htmlspecialchars($item['internal_notes']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Physical Item Status History (Item History) -->
                <div class="mb-4">
                    <h3 class="h6 fw-bold text-dark mb-3">
                        <i class="bi bi-clock-history me-1 text-secondary"></i> Riwayat Transisi Status Fisik Barang (Item History)
                    </h3>
                    <?php if (empty($itemHistories)): ?>
                        <p class="text-muted small">Belum ada riwayat transisi status fisik.</p>
                    <?php else: ?>
                        <div class="border-start ps-3 ms-2">
                            <?php foreach ($itemHistories as $h): ?>
                                <div class="position-relative mb-3">
                                    <div style="position: absolute; left: -21px; top: 2px; width: 10px; height: 10px; border-radius: 50%; background: var(--cf-primary);"></div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="cf-badge cf-badge-neutral">
                                            <?= \App\Models\ItemModel::getPhysicalStatusLabel($h['new_status']) ?>
                                        </span>
                                        <span class="text-muted" style="font-size:0.75rem;">
                                            <?= date('d M Y, H:i', strtotime($h['created_at'])) ?> WIB
                                        </span>
                                    </div>
                                    <?php if (!empty($h['notes'])): ?>
                                        <div class="small text-muted mt-1"><?= htmlspecialchars($h['notes']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($h['changed_by_name'])): ?>
                                        <div class="text-muted" style="font-size:0.7rem;">Oleh: <?= htmlspecialchars($h['changed_by_name']) ?> (Petugas)</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Digital Report Status History (jika ada) -->
                <?php if (!empty($reportHistories)): ?>
                    <div class="mb-4 pt-3 border-top">
                        <h3 class="h6 fw-bold text-dark mb-3">
                            <i class="bi bi-file-earmark-text me-1 text-secondary"></i> Riwayat Laporan Digital Terkait (<?= htmlspecialchars($item['found_report_number'] ?? '') ?>)
                        </h3>
                        <div class="border-start ps-3 ms-2">
                            <?php foreach ($reportHistories as $rh): ?>
                                <div class="position-relative mb-2">
                                    <div style="position: absolute; left: -21px; top: 2px; width: 10px; height: 10px; border-radius: 50%; background: #64748b;"></div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="cf-badge cf-badge-neutral">
                                            <?= \App\Models\FoundReportModel::getStatusLabel($rh['new_status']) ?>
                                        </span>
                                        <span class="text-muted" style="font-size:0.75rem;">
                                            <?= date('d M Y, H:i', strtotime($rh['created_at'])) ?> WIB
                                        </span>
                                    </div>
                                    <?php if (!empty($rh['notes'])): ?>
                                        <div class="small text-muted mt-1"><?= htmlspecialchars($rh['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pt-3 border-top">
                    <a href="/petugas/inventory" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Inventaris
                    </a>
                </div>

            </div>
        </div>

        <!-- Sidebar / Operations Panel -->
        <div class="col-lg-4">

            <?php if ($isTerminal): ?>
                <!-- Card Terminal Locked Status (Handover Telah Selesai) -->
                <div class="cf-card p-4 mb-4 border-start border-4 border-secondary">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-lock-fill text-secondary fs-5"></i>
                        <h3 class="h6 fw-bold text-dark mb-0">Status Barang Final (Terkunci)</h3>
                    </div>
                    <p class="small text-muted mb-3">
                        Barang fisik ini telah selesai diserahterimakan kepada pemilik sah (atau berstatus final). Kapasitas rak gudang telah dilepaskan dan item terkunci dari pengubahan status fisik maupun alokasi rak penyimpanan.
                    </p>
                    <div class="p-3 bg-light rounded small border">
                        <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                            <span class="text-muted">Status Fisik:</span>
                            <span class="cf-badge cf-badge-neutral"><?= $statusText ?></span>
                        </div>
                        <?php if (!empty($item['returned_at'])): ?>
                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">Waktu Serah Terima:</span>
                                <span class="fw-semibold text-dark"><?= date('d/m/Y H:i', strtotime($item['returned_at'])) ?> WIB</span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['claimant_name'])): ?>
                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">Penerima Sah:</span>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($item['claimant_name']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['claim_number'])): ?>
                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">Nomor Klaim:</span>
                                <a href="/petugas/claims/<?= $item['claim_id'] ?? '' ?>" class="cf-badge cf-badge-info text-decoration-none">
                                    <?= htmlspecialchars($item['claim_number']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($item['handover_notes'])): ?>
                            <div class="mt-2 pt-1">
                                <span class="text-muted d-block mb-1">Catatan Serah Terima:</span>
                                <div class="p-2 bg-white rounded border fst-italic text-dark">
                                    <?= nl2br(htmlspecialchars($item['handover_notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Card 1: Change Physical Status Form -->
                <div class="cf-card p-4 mb-4">
                    <h3 class="h6 fw-bold text-dark mb-3">
                        <i class="bi bi-sliders text-primary me-1"></i> Ubah Status Fisik Barang
                    </h3>
                    <form action="/petugas/items/<?= $item['id'] ?>/status" method="POST" id="form-update-status">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="select-physical-status" class="cf-form-label">
                                Status Fisik Baru <span class="text-danger">*</span>
                            </label>
                            <select class="form-select cf-form-control form-select-sm" id="select-physical-status" name="physical_status" required>
                                <option value="stored" <?= ($item['status'] === 'stored') ? 'selected' : '' ?>>Tersimpan di Gudang</option>
                                <option value="in_inspection" <?= ($item['status'] === 'in_inspection') ? 'selected' : '' ?>>Sedang Diperiksa</option>
                                <option value="in_claim" <?= ($item['status'] === 'in_claim') ? 'selected' : '' ?>>Dalam Proses Klaim</option>
                                <option value="ready_for_handover" <?= ($item['status'] === 'ready_for_handover') ? 'selected' : '' ?>>Siap Diserahkan ke Pemilik</option>
                                <option value="returned" <?= ($item['status'] === 'returned') ? 'selected' : '' ?>>Dikembalikan ke Pemilik</option>
                                <option value="donated" <?= ($item['status'] === 'donated') ? 'selected' : '' ?>>Didonasikan</option>
                                <option value="disposed" <?= ($item['status'] === 'disposed') ? 'selected' : '' ?>>Dimusnahkan</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="textarea-status-notes" class="cf-form-label">
                                Catatan Perubahan Status
                            </label>
                            <textarea class="cf-form-control form-control-sm" 
                                      id="textarea-status-notes" 
                                      name="status_notes" 
                                      rows="2" 
                                      placeholder="Contoh: Barang selesai diperiksa fisik dan siap disimpan di rak utama."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100" id="btn-submit-status">
                            <i class="bi bi-check2-circle me-1"></i> Perbarui Status Fisik
                        </button>
                    </form>
                </div>

                <!-- Card 2: Relocate Storage Location Form -->
                <div class="cf-card p-4 mb-4">
                    <h3 class="h6 fw-bold text-dark mb-3">
                        <i class="bi bi-arrow-left-right text-success me-1"></i> Pindahkan Rak Penyimpanan
                    </h3>
                    <form action="/petugas/items/<?= $item['id'] ?>/relocate" method="POST" id="form-relocate-storage">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="select-new-storage" class="cf-form-label">
                                Pilih Lokasi Rak Baru <span class="text-danger">*</span>
                            </label>
                            <select class="form-select cf-form-control form-select-sm" id="select-new-storage" name="storage_location_id" required>
                                <?php foreach ($storageLocations as $sl): ?>
                                    <option value="<?= $sl['id'] ?>" <?= ((int) $item['storage_location_id'] === (int) $sl['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sl['code']) ?> — <?= htmlspecialchars($sl['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="textarea-relocate-notes" class="cf-form-label">
                                Alasan / Catatan Pemindahan
                            </label>
                            <textarea class="cf-form-control form-control-sm" 
                                      id="textarea-relocate-notes" 
                                      name="relocate_notes" 
                                      rows="2" 
                                      placeholder="Contoh: Dipindahkan ke rak penyimpanan elektronik berkunci."></textarea>
                        </div>

                        <button type="submit" class="btn btn-outline-success btn-sm w-100" id="btn-submit-relocate">
                            <i class="bi bi-box-arrow-right me-1"></i> Pindahkan Lokasi
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Card 3: Rekomendasi AI Matching Laporan Kehilangan (Asynchronous / Decoupled) -->
            <?php if (!empty($item['found_report_id'])): ?>
                <div class="cf-card p-4 mb-4" id="ai-match-card" data-found-id="<?= (int)$item['found_report_id'] ?>" data-auto-match="<?= !empty($autoMatch) ? '1' : '0' ?>">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h3 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                            <i class="bi bi-stars text-primary"></i> AI Matching Laporan
                        </h3>
                        <span id="ai-match-status-badge" class="cf-badge cf-badge-neutral small">
                            <?= !empty($matchingCandidates) ? count($matchingCandidates) . ' Rekomendasi' : 'Belum Dipindai' ?>
                        </span>
                    </div>
                    <p class="text-muted small mb-3">
                        Pencocokan otomatis barang fisik ini dengan laporan kehilangan civitas kampus.
                    </p>

                    <!-- Background Processing Spinner -->
                    <div id="ai-match-loading" class="<?= !empty($autoMatch) ? '' : 'd-none' ?> text-center p-3 mb-3 bg-light rounded-3">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span class="small text-muted fw-semibold" id="ai-match-loading-text">Sedang mencari kemungkinan kecocokan...</span>
                        <div class="text-muted extra-small mt-1" style="font-size: 0.75rem;">Mengevaluasi kesesuaian kategori barang, deskripsi, lokasi, dan tanggal laporan...</div>
                    </div>

                    <!-- Result List Container -->
                    <div id="ai-match-results">
                        <?php if (!empty($matchingCandidates)): ?>
                            <div class="list-group list-group-flush mb-3">
                                <?php foreach (array_slice($matchingCandidates, 0, 3) as $mc): ?>
                                    <div class="list-group-item px-0 py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="me-2">
                                                <span class="fw-semibold small text-dark d-block">
                                                    <?= htmlspecialchars($mc['lost_title'] ?? 'Laporan Kehilangan') ?>
                                                </span>
                                                <span class="text-muted font-monospace" style="font-size:0.75rem;">
                                                    <?= htmlspecialchars($mc['lost_report_number'] ?? '') ?>
                                                </span>
                                            </div>
                                            <span class="cf-badge cf-badge-found small">
                                                <?= round((float)($mc['relevance_score'] ?? 0)) ?>% Match
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif (empty($autoMatch)): ?>
                            <p class="text-muted small fst-italic mb-3" id="ai-match-empty-text">
                                Belum ada rekomendasi pencocokan yang tersimpan.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Action Controls -->
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" id="btn-trigger-ai-match">
                            <i class="bi bi-arrow-clockwise me-1"></i> Pindai Ulang AI
                        </button>
                        <a href="/reports/found/<?= (int)$item['found_report_id'] ?>" class="btn btn-light btn-sm rounded-pill border">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Buka Laporan Temuan
                        </a>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const card = document.getElementById('ai-match-card');
                    if (!card) return;

                    const foundId = card.getAttribute('data-found-id');
                    const autoMatch = card.getAttribute('data-auto-match') === '1';
                    const loadingEl = document.getElementById('ai-match-loading');
                    const resultsEl = document.getElementById('ai-match-results');
                    const badgeEl = document.getElementById('ai-match-status-badge');
                    const triggerBtn = document.getElementById('btn-trigger-ai-match');
                    const csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>';

                    async function runAiMatch() {
                        if (loadingEl) loadingEl.classList.remove('d-none');
                        if (triggerBtn) {
                            triggerBtn.disabled = true;
                            triggerBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Sedang memindai...';
                        }

                        const abortController = new AbortController();
                        const abortTimeout = setTimeout(() => abortController.abort(), 12000);

                        try {
                            const formData = new FormData();
                            formData.append('_csrf_token', csrfToken);
                            formData.append('format', 'json');

                            const res = await fetch('/ai-match/found/' + foundId + '/run', {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                },
                                body: formData,
                                signal: abortController.signal
                            });

                            clearTimeout(abortTimeout);
                            if (!res.ok) throw new Error('HTTP ' + res.status);
                            const data = await res.json();

                            if (data.success) {
                                if (badgeEl) {
                                    if (data.result_status === 'match_found' || data.result_status === 'completed') {
                                        badgeEl.className = 'badge bg-success-subtle text-success small';
                                        badgeEl.textContent = data.matches_count + ' Cocok (AI)';
                                    } else if (data.result_status === 'completed_fallback') {
                                        badgeEl.className = 'badge bg-info-subtle text-info small';
                                        badgeEl.textContent = data.matches_count + ' Rekomendasi (Fallback)';
                                    } else if (data.result_status === 'provider_timeout') {
                                        badgeEl.className = 'badge bg-warning-subtle text-warning small';
                                        badgeEl.textContent = 'Timeout (Fallback)';
                                    } else {
                                        badgeEl.className = 'badge bg-secondary-subtle text-secondary small';
                                        badgeEl.textContent = 'Tidak Ada Cocok';
                                    }
                                }

                                if (Array.isArray(data.candidates) && data.candidates.length > 0) {
                                    let html = '';
                                    if (data.result_status === 'completed_fallback') {
                                        html += '<div class="alert alert-info border-0 p-2 rounded-3 small mb-2 d-flex align-items-start gap-2" style="font-size:0.78rem;">'
                                              + '<i class="bi bi-info-circle-fill text-info flex-shrink-0 mt-1"></i>'
                                              + '<div>' + (data.fallback_explanation || 'Rekomendasi awal berdasarkan analisis atribut dasar.') + '</div>'
                                              + '</div>';
                                    }
                                    html += '<div class="list-group list-group-flush mb-3">';
                                    data.candidates.slice(0, 3).forEach(function(c) {
                                        html += '<div class="list-group-item px-0 py-2 border-bottom">'
                                              + '<div class="d-flex justify-content-between align-items-start">'
                                              + '<div class="me-2"><span class="fw-semibold small text-dark d-block">' + (c.title || 'Laporan') + '</span></div>'
                                              + '<span class="cf-badge cf-badge-found small">' + Math.round(c.relevance_score) + '% Match</span>'
                                              + '</div></div>';
                                    });
                                    html += '</div>';
                                    if (resultsEl) resultsEl.innerHTML = html;
                                } else {
                                    if (resultsEl) {
                                        resultsEl.innerHTML = '<p class="text-muted small fst-italic mb-3">' + (data.message || 'Tidak ada kecocokan ditemukan.') + '</p>';
                                    }
                                }
                            } else {
                                if (badgeEl) {
                                    badgeEl.className = 'cf-badge cf-badge-warning small';
                                    badgeEl.textContent = 'Evaluasi Gagal';
                                }
                            }
                        } catch (err) {
                            console.warn('[AI Match Background]', err);
                            if (badgeEl) {
                                badgeEl.className = 'badge bg-secondary-subtle text-secondary small';
                                badgeEl.textContent = err.name === 'AbortError' ? 'Batas Waktu Habis' : 'Layanan Offline';
                            }
                            if (resultsEl) {
                                resultsEl.innerHTML = '<p class="text-muted small fst-italic mb-3">AI eksternal belum berhasil merespons. Silakan coba lagi nanti.</p>';
                            }
                        } finally {
                            clearTimeout(abortTimeout);
                            if (loadingEl) loadingEl.classList.add('d-none');
                            if (triggerBtn) {
                                triggerBtn.disabled = false;
                                triggerBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Pindai Ulang AI';
                            }
                        }
                    }

                    if (autoMatch) {
                        runAiMatch();
                    }

                    if (triggerBtn) {
                        triggerBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            runAiMatch();
                        });
                    }
                });
                </script>
            <?php endif; ?>

        </div>
    </div>

</div>
