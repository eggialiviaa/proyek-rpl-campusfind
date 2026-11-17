<?php
/**
 * View: Impor Data Civitas dari Excel (Admin)
 * File: app/Views/admin/import_civitas.php
 * Prompt 31: Impor Data Civitas dari Excel
 */
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/admin">Beranda Admin</a></li>
                    <li class="breadcrumb-item"><a href="/admin/users">Manajemen Pengguna</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Impor Civitas</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-excel text-success"></i> Impor Massal Data Civitas
            </h1>
            <p class="text-muted mb-0">
                Daftarkan akun Civitas Akademika (Mahasiswa) secara massal dari file spreadsheet Excel (.xlsx) atau CSV.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/users/import/template" class="btn btn-outline-success rounded-pill px-3 shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-download"></i> Unduh Templat Excel (.xlsx)
            </a>
            <a href="/admin/users" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Kembali ke Pengguna
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center gap-3 p-3 mb-4" role="alert">
            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center p-2" style="width: 36px; height: 36px;">
                <i class="bi bi-check-lg fs-5"></i>
            </div>
            <div class="flex-grow-1">
                <strong class="d-block mb-1">Sukses!</strong>
                <span><?= htmlspecialchars(is_array($flashSuccess) ? implode(' ', array_filter($flashSuccess, 'is_scalar')) : (string)$flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center gap-3 p-3 mb-4" role="alert">
            <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center p-2" style="width: 36px; height: 36px;">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            </div>
            <div class="flex-grow-1">
                <strong class="d-block mb-1">Terjadi Galat:</strong>
                <span><?= htmlspecialchars(is_array($flashError) ? implode(' ', array_filter($flashError, 'is_scalar')) : (string)$flashError, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Ringkasan Hasil Eksekusi Terakhir (Jika Ada) -->
    <?php if (!empty($lastResult)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white border-start border-4 border-success">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-success-subtle text-success p-2 rounded-3">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">Hasil Eksekusi Impor Terakhir</h5>
                            <small class="text-muted">Waktu: <?= htmlspecialchars((string)($lastResult['executed_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> &bull; Mode: <strong><?= ($lastResult['mode'] ?? 'skip') === 'update' ? 'Perbarui Data Yang Ada' : 'Lewati Duplikat' ?></strong></small>
                        </div>
                    </div>
                    <div>
                        <a href="/admin/users/import/report" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm d-flex align-items-center gap-1">
                            <i class="bi bi-file-earmark-spreadsheet-fill"></i> Unduh Laporan Eksekusi (.xlsx)
                        </a>
                    </div>
                </div>

                <!-- Kartu Metrik Angka Bertema -->
                <div class="row g-3 text-center mb-4">
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 bg-light border">
                            <div class="text-muted extra-small fw-bold">TOTAL DIPROSES</div>
                            <div class="fs-4 fw-bold text-dark"><?= number_format((int)($lastResult['total'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 bg-success-subtle border border-success-subtle">
                            <div class="text-success extra-small fw-bold">AKUN DIBUAT (BARU)</div>
                            <div class="fs-4 fw-bold text-success">+<?= number_format((int)($lastResult['created_count'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 bg-primary-subtle border border-primary-subtle">
                            <div class="text-primary extra-small fw-bold">DIPERBARUI</div>
                            <div class="fs-4 fw-bold text-primary"><?= number_format((int)($lastResult['updated_count'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 bg-secondary-subtle border">
                            <div class="text-secondary extra-small fw-bold">DILEWATI / GAGAL</div>
                            <div class="fs-4 fw-bold text-secondary"><?= number_format((int)($lastResult['skipped_count'] ?? 0) + (int)($lastResult['failed_count'] ?? 0)) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Tabel Rincian Hasil Per Baris (Jika Ada) -->
                <?php if (!empty($lastResult['results']) && is_array($lastResult['results'])): ?>
                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-card-checklist text-primary"></i> Rincian Status Eksekusi Per Baris:
                    </h6>
                    <div class="table-responsive rounded-4 border bg-white shadow-sm" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 50px;">Baris</th>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Program Studi</th>
                                    <th>Status Tindakan</th>
                                    <th>Keterangan Hasil</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lastResult['results'] as $resRow): ?>
                                    <?php
                                        $act = (string)($resRow['action'] ?? '');
                                        $actBadge = match($act) {
                                            'created' => 'bg-success',
                                            'updated' => 'bg-primary',
                                            'skipped' => 'bg-warning text-dark',
                                            'failed'  => 'bg-danger',
                                            default   => 'bg-secondary',
                                        };
                                        $actLabel = match($act) {
                                            'created' => 'DIBUAT (BARU)',
                                            'updated' => 'DIPERBARUI',
                                            'skipped' => 'DILEWATI',
                                            'failed'  => 'GAGAL',
                                            default   => strtoupper($act),
                                        };
                                    ?>
                                    <tr>
                                        <td class="text-muted fw-bold"><?= (int)($resRow['row_num'] ?? 0) ?></td>
                                        <td class="fw-bold font-monospace"><?= htmlspecialchars((string)($resRow['nim'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($resRow['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-muted font-monospace"><?= htmlspecialchars((string)($resRow['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($resRow['study_program'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?= $actBadge ?> px-2 py-1"><?= $actLabel ?></span>
                                        </td>
                                        <td>
                                            <small class="<?= $act === 'failed' ? 'text-danger fw-semibold' : ($act === 'created' ? 'text-success' : 'text-muted') ?>">
                                                <?= htmlspecialchars((string)($resRow['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- LANGKAH 1: Form Unggah Berkas -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <span class="badge rounded-circle bg-primary text-white p-2 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">1</span>
                Unggah Berkas Spreadsheet
            </h5>

            <form action="/admin/users/import/preview" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                <?= \App\Middleware\CsrfMiddleware::field() ?>

                <div class="col-12 col-md-8">
                    <label class="form-label extra-small text-muted fw-bold">PILIH FILE EXCEL (.XLSX) ATAU CSV</label>
                    <div class="input-group">
                        <input type="file" name="import_file" class="form-control form-control-lg bg-light" accept=".xlsx, .csv" required>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm d-flex align-items-center gap-2">
                            <i class="bi bi-search"></i> Analisis &amp; Pratinjau
                        </button>
                    </div>
                    <div class="form-text mt-2 text-muted">
                        <i class="bi bi-info-circle me-1"></i> Format berkas: <strong>.xlsx</strong> atau <strong>.csv</strong> &bull; Ukuran maksimal: <strong>2 MB</strong> &bull; Batas maksimal: <strong>1.000 baris data</strong>.
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="p-3 bg-light rounded-4 small">
                        <div class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-1"></i> Ketentuan Kolom:</div>
                        <ul class="mb-0 ps-3 text-muted">
                            <li><strong class="text-dark">Wajib:</strong> NIM, Nama, Email</li>
                            <li><strong>Opsional:</strong> Program Studi, Angkatan, No WhatsApp</li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- LANGKAH 2: Pratinjau Validasi & Konfirmasi Simpan (Jika Preview Ada) -->
    <?php if (!empty($previewData)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
            <div class="card-header bg-white border-bottom p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="fw-bold mb-1 d-flex align-items-center gap-2">
                            <span class="badge rounded-circle bg-success text-white p-2 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">2</span>
                            Pratinjau Hasil Analisis Berkas: <span class="text-primary"><?= htmlspecialchars($previewData['filename']) ?></span>
                        </h5>
                        <p class="text-muted small mb-0">
                            Periksa hasil validasi baris di bawah sebelum mengonfirmasi penyimpanan ke basis data.
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <form action="/admin/users/import/cancel" method="POST" class="d-inline">
                            <?= \App\Middleware\CsrfMiddleware::field() ?>
                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                <i class="bi bi-x-circle me-1"></i> Batalkan Pratinjau
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Metrik Ringkasan Pratinjau -->
                <div class="row g-3 mt-2">
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 bg-light border text-center">
                            <span class="extra-small text-muted fw-bold d-block">TOTAL BARIS DATA</span>
                            <span class="fs-4 fw-bold text-dark"><?= number_format($previewData['total_rows']) ?></span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 bg-success-subtle border border-success-subtle text-center">
                            <span class="extra-small text-success fw-bold d-block">BARIS VALID (SIAP DAFTAR)</span>
                            <span class="fs-4 fw-bold text-success"><?= number_format($previewData['valid_count']) ?></span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 bg-warning-subtle border border-warning-subtle text-center">
                            <span class="extra-small text-warning-emphasis fw-bold d-block">DUPLIKAT (DATABASE / FILE)</span>
                            <span class="fs-4 fw-bold text-warning-emphasis"><?= number_format($previewData['duplicate_count']) ?></span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 bg-danger-subtle border border-danger-subtle text-center">
                            <span class="extra-small text-danger fw-bold d-block">GALAT VALIDASI</span>
                            <span class="fs-4 fw-bold text-danger"><?= number_format($previewData['error_count']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Konfirmasi Simpan -->
            <div class="card-body p-4 bg-body-tertiary">
                <form action="/admin/users/import/confirm" method="POST" id="formConfirmImport">
                    <?= \App\Middleware\CsrfMiddleware::field() ?>
                    <input type="hidden" name="preview_id" value="<?= htmlspecialchars($previewData['preview_id']) ?>">

                    <div class="p-3 bg-white rounded-4 border shadow-sm mb-4">
                        <h6 class="fw-bold mb-2"><i class="bi bi-gear-wide-connected text-primary me-2"></i>Opsi Penanganan Data Duplikat:</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="form-check p-3 rounded-3 border bg-light h-100">
                                    <input class="form-check-input ms-0 me-2" type="radio" name="mode" id="modeSkip" value="skip" checked>
                                    <label class="form-check-label fw-bold text-dark" for="modeSkip">
                                        Lewati baris duplikat (Rekomendasi Default)
                                    </label>
                                    <p class="text-muted extra-small mb-0 mt-1">
                                        Data dengan NIM atau Email yang sudah terdaftar di sistem akan diabaikan tanpa mengubah akun yang ada.
                                    </p>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-check p-3 rounded-3 border bg-light h-100">
                                    <input class="form-check-input ms-0 me-2" type="radio" name="mode" id="modeUpdate" value="update">
                                    <label class="form-check-label fw-bold text-dark" for="modeUpdate">
                                        Perbarui data yang sudah ada (Update)
                                    </label>
                                    <p class="text-muted extra-small mb-0 mt-1">
                                        Jika NIM atau Email sudah ada di database, sistem akan memperbarui nama, prodi, angkatan, dan no telepon.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 p-3 bg-primary-subtle text-primary rounded-3 small d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle-fill fs-5"></i>
                            <div>
                                Akun Civitas yang baru dibuat akan berstatus <strong>"Belum Aktif"</strong> dengan password acak aman (tanpa password dikirim/ditampilkan). Persetujuan notifikasi email berstatus <strong>nonaktif (default)</strong> sampai pengguna menyetujuinya sendiri.
                            </div>
                        </div>
                    </div>

                    <!-- Filter Tab Tabel Pratinjau -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <ul class="nav nav-pills small" id="pills-tab" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active rounded-pill px-3 py-1" type="button" onclick="filterTable('all')">
                                    Semua Baris (<?= count($previewData['rows']) ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link rounded-pill px-3 py-1 text-success" type="button" onclick="filterTable('valid')">
                                    Valid (<?= $previewData['valid_count'] ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link rounded-pill px-3 py-1 text-warning-emphasis" type="button" onclick="filterTable('duplicate')">
                                    Duplikat (<?= $previewData['duplicate_count'] ?>)
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link rounded-pill px-3 py-1 text-danger" type="button" onclick="filterTable('error')">
                                    Galat (<?= $previewData['error_count'] ?>)
                                </button>
                            </li>
                        </ul>

                        <div class="extra-small text-muted">
                            <i class="bi bi-shield-lock me-1"></i> Email dan Nomor WhatsApp disamarkan (dimasker) untuk privasi data.
                        </div>
                    </div>

                    <!-- Tabel Pratinjau -->
                    <div class="table-responsive rounded-4 border bg-white shadow-sm mb-4" style="max-height: 450px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0" id="tablePreview">
                            <thead class="table-light sticky-top small">
                                <tr>
                                    <th style="width: 60px;">Baris</th>
                                    <th>NIM</th>
                                    <th>Nama Mahasiswa</th>
                                    <th>Email (Masked)</th>
                                    <th>Program Studi</th>
                                    <th>Angkatan</th>
                                    <th>No WA (Masked)</th>
                                    <th>Status Validasi</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($previewData['rows'] as $r): ?>
                                    <?php 
                                        $rowClass = match($r['status']) {
                                            'valid'          => 'row-valid',
                                            'duplicate_db', 
                                            'duplicate_file' => 'row-duplicate',
                                            'error'          => 'row-error',
                                            default          => '',
                                        };
                                        $badgeClass = match($r['status']) {
                                            'valid'          => 'bg-success',
                                            'duplicate_db'   => 'bg-warning text-dark',
                                            'duplicate_file' => 'bg-warning text-dark',
                                            'error'          => 'bg-danger',
                                            default          => 'bg-secondary',
                                        };
                                        $badgeLabel = match($r['status']) {
                                            'valid'          => 'VALID',
                                            'duplicate_db'   => 'DUPLIKAT DB',
                                            'duplicate_file' => 'DUPLIKAT FILE',
                                            'error'          => 'GALAT',
                                            default          => strtoupper($r['status']),
                                        };
                                    ?>
                                    <tr class="item-row <?= $rowClass ?>">
                                        <td class="text-muted fw-bold"><?= $r['row_num'] ?></td>
                                        <td class="fw-bold font-monospace"><?= htmlspecialchars($r['nim']) ?></td>
                                        <td><?= htmlspecialchars($r['name']) ?></td>
                                        <td class="font-monospace text-muted"><?= htmlspecialchars($r['email_masked']) ?></td>
                                        <td><?= htmlspecialchars($r['study_program'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($r['batch_year'] ?: '-') ?></td>
                                        <td class="font-monospace text-muted"><?= htmlspecialchars($r['phone_masked']) ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?= $badgeClass ?> px-2 py-1"><?= $badgeLabel ?></span>
                                        </td>
                                        <td>
                                            <small class="<?= $r['status'] === 'error' ? 'text-danger fw-bold' : ($r['status'] === 'valid' ? 'text-success' : 'text-warning-emphasis') ?>">
                                                <?= htmlspecialchars($r['status_reason']) ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tombol Eksekusi Konfirmasi -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="small text-muted">
                            Pastikan data di atas telah sesuai sebelum menekan tombol simpan.
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success btn-lg rounded-pill px-4 fw-bold shadow d-flex align-items-center gap-2"
                                    data-cf-confirm="Simpan data civitas yang valid ke basis data? Seluruh baris yang lolos validasi akan dimasukkan atau diperbarui secara otomatis dalam satu transaksi aman."
                                    data-cf-title="Konfirmasi Impor Civitas Akademika"
                                    data-cf-confirm-text="Simpan ke Basis Data"
                                    data-cf-variant="success">
                                <i class="bi bi-check-circle-fill"></i> Konfirmasi &amp; Simpan ke Database
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function filterTable(type) {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach(r => {
        if (type === 'all') {
            r.style.display = '';
        } else if (type === 'valid') {
            r.style.display = r.classList.contains('row-valid') ? '' : 'none';
        } else if (type === 'duplicate') {
            r.style.display = r.classList.contains('row-duplicate') ? '' : 'none';
        } else if (type === 'error') {
            r.style.display = r.classList.contains('row-error') ? '' : 'none';
        }
    });

    // Update active class on nav pills
    const buttons = document.querySelectorAll('#pills-tab button');
    buttons.forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');
}
</script>
