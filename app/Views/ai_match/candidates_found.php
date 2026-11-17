<?php
/**
 * View: Rekomendasi Kecocokan AI & Komparasi Visual (Found Report Source â€” Petugas / Admin)
 * File: app/Views/ai_match/candidates_found.php
 * Architecture: PHP Native MVC + Bootstrap 5 + Vanilla JS
 */
?>

<div class="container py-4 cf-workspace-wide cf-ai-match-container">
    <!-- Standalone Transparent Page Header (Aura Liquid Glass) -->
    <?php
    $aiActions = '<div class="d-flex flex-wrap gap-2 align-items-center">'
               . '<form action="/ai-match/found/' . (int)$foundReport['id'] . '/run" method="POST" class="d-inline ai-match-scan-form" data-report-id="' . (int)$foundReport['id'] . '" data-report-type="found">'
               . \App\Middleware\CsrfMiddleware::field()
               . '<button type="submit" class="btn btn-purple shadow-sm rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1.5"><i class="bi bi-arrow-clockwise"></i> Pindai Ulang AI</button>'
               . '</form>'
               . '<a href="/reports/found/' . (int)$foundReport['id'] . '" class="btn btn-outline-secondary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1.5"><i class="bi bi-arrow-left"></i> Kembali ke Detail</a>'
               . '</div>';

    $headerConfig = [
        'eyebrow'     => 'AI Intelligence Assist (Petugas)',
        'title'       => 'Rekomendasi Kecocokan AI & Komparasi Visual',
        'icon'        => 'bi bi-columns-gap',
        'description' => 'Pencocokan berbasis AI Assistance dengan komparasi visual foto asli untuk barang temuan: ' . htmlspecialchars($foundReport['title'], ENT_QUOTES, 'UTF-8'),
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Laporan Temuan', 'url' => '/reports/manage?tab=found'],
            ['label' => htmlspecialchars($foundReport['report_number'], ENT_QUOTES, 'UTF-8'), 'url' => '/reports/found/' . (int)$foundReport['id']],
            ['label' => 'AI Match'],
        ],
        'actions'     => $aiActions,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Active AI Engine & Safety Banner -->
    <div class="cf-card-subtle mb-4 overflow-hidden p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="cf-badge cf-badge-neutral px-3 py-2">
                <i class="bi bi-robot me-1"></i> Engine: <?= htmlspecialchars($providerName) ?>
            </span>
            <?php if ($isNimConfigured): ?>
                <span class="cf-badge cf-badge-found">
                    <i class="bi bi-check-circle-fill me-1"></i> NVIDIA Hosted API Catalog Active
                </span>
            <?php else: ?>
                <span class="cf-badge cf-badge-warning">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Fallback Algorithm (API Key Not Configured)
                </span>
            <?php endif; ?>
        </div>
        <small class="text-muted">
            <i class="bi bi-shield-check me-1"></i> Panel Khusus Petugas Posko Lost &amp; Found Center
        </small>
    </div>

    <!-- MANDATORY AI DISCLAIMER BANNER -->
    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4" role="alert">
        <div class="d-flex align-items-start gap-3">
            <div class="fs-2 text-warning lh-1 mt-1">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-1">Catatan Penting â€” Verifikasi Fisik oleh Petugas (Human-in-the-Loop)</h5>
                <p class="mb-0 small text-dark opacity-90">
                    Kandidat laporan kehilangan yang dicocokkan di bawah ini disusun oleh <strong>AI Matching Assistance</strong> berdasarkan atribut publik dan deskripsi laporan.
                    <strong>Rekomendasi AI bukan merupakan bukti sah kepemilikan.</strong> Petugas wajib memeriksa fisik barang, mencocokkan ciri khusus, dan memverifikasi bukti rahasia pemilik sebelum menyetujui klaim serah terima.
                </p>
            </div>
        </div>
    </div>

    <!-- STATE NOTIFICATION BANNER -->
    <?php
    $resStatus = $diagnostic['result_status'] ?? (empty($candidates) ? 'no_candidates' : 'match_found');
    $isFallback = !empty($diagnostic['is_fallback']) || $resStatus === 'completed_fallback';
    ?>

    <?php if ($resStatus === 'completed_fallback' || ($isFallback && !empty($candidates))): ?>
        <div class="alert alert-info border-0 shadow-sm rounded-4 p-3 mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill text-info fs-5"></i>
                    <span class="fw-semibold text-dark small">
                        Rekomendasi Berbasis Analisis Atribut Dasar: Ditemukan <strong><?= count($candidates) ?> laporan kehilangan</strong> potensial.
                    </span>
                </div>
                <span class="badge bg-info text-white rounded-pill px-3 py-1 small">
                    Baseline Evaluation
                </span>
            </div>
        </div>
    <?php elseif ($resStatus === 'match_found' || $resStatus === 'completed'): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-3 mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-stars text-success fs-5"></i>
                    <span class="fw-semibold text-dark small">
                        Analisis AI Selesai: Ditemukan <strong><?= count($candidates) ?> laporan kehilangan</strong> yang berpotensi cocok.
                    </span>
                </div>
                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 small">
                    NVIDIA NIM Active
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- EMPTY STATES -->
    <?php if (empty($candidates)): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center p-5 mb-4">
            <div class="py-4">
                <i class="bi bi-inboxes text-muted display-4 d-block mb-3 opacity-50"></i>
                <h4 class="fw-bold text-dark mb-2">Belum Ada Laporan Kehilangan yang Cocok</h4>
                <p class="text-muted max-w-md mx-auto mb-3">
                    Saat ini belum ditemukan laporan kehilangan aktif yang memiliki kesesuaian kategori, rentang waktu, dan deskripsi dengan barang temuan ini.
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <form action="/ai-match/found/<?= (int)$foundReport['id'] ?>/run" method="POST" class="d-inline ai-match-scan-form" data-report-id="<?= (int)$foundReport['id'] ?>" data-report-type="found">
                        <?= \App\Middleware\CsrfMiddleware::field() ?>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-arrow-clockwise me-1"></i> Pindai Ulang AI
                        </button>
                    </form>
                    <a href="/reports/found/<?= (int)$foundReport['id'] ?>" class="btn btn-outline-secondary rounded-pill px-4">
                        Kembali ke Detail Laporan
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- CANDIDATES WORKSPACE -->
    <?php if (!empty($candidates)): ?>
        <?php
        $selectedIdx = 0;
        $activeCandidateId = isset($_GET['candidate_id']) ? (int)$_GET['candidate_id'] : 0;
        if ($activeCandidateId > 0) {
            foreach ($candidates as $idx => $c) {
                if ((int)$c['id'] === $activeCandidateId) {
                    $selectedIdx = $idx;
                    break;
                }
            }
        }
        ?>

        <!-- Multi-candidate Quick Switcher (Tabs) -->
        <?php if (count($candidates) > 1): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <span class="text-muted small fw-semibold">
                            <i class="bi bi-collection me-1 text-purple"></i> Pilih Laporan Kehilangan untuk Dibandingkan (<?= count($candidates) ?> kandidat):
                        </span>
                        <span class="text-muted extra-small">Klik tab untuk beralih tampilan komparasi</span>
                    </div>
                    <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto py-1" id="candidateFoundTabs" role="tablist">
                        <?php foreach ($candidates as $idx => $cand): ?>
                            <?php
                            $cScore = (float)$cand['relevance_score'];
                            $badgeColor = $cScore >= 70 ? 'success' : ($cScore >= 45 ? 'warning' : 'secondary');
                            $isActive = ($idx === $selectedIdx);
                            ?>
                            <li class="nav-item flex-shrink-0" role="presentation">
                                <button class="nav-link rounded-pill px-3 py-2 text-start d-flex align-items-center gap-2 <?= $isActive ? 'active' : '' ?>"
                                        id="found-tab-btn-<?= (int)$cand['id'] ?>"
                                        data-bs-toggle="pill"
                                        data-bs-target="#found-candidate-pane-<?= (int)$cand['id'] ?>"
                                        type="button"
                                        role="tab"
                                        aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                                    <span class="badge bg-<?= $badgeColor ?> rounded-pill small"><?= round($cScore) ?>%</span>
                                    <span class="text-truncate fw-medium" style="max-width: 180px;"><?= htmlspecialchars($cand['lost_title']) ?></span>
                                    <code>#<?= htmlspecialchars($cand['lost_report_number']) ?></code>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tab Content Panes -->
        <div class="tab-content" id="candidateFoundTabsContent">
            <?php foreach ($candidates as $idx => $candidate): ?>
                <?php
                $score = (float) $candidate['relevance_score'];
                $status = $candidate['status'] ?? 'pending';
                $isActive = ($idx === $selectedIdx);

                $scoreBadgeClass = match (true) {
                    $score >= 70.0 => 'bg-success text-white',
                    $score >= 45.0 => 'bg-warning text-dark',
                    default        => 'bg-secondary text-white',
                };

                $statusBadge = match ($status) {
                    'confirmed_match' => '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Dikonfirmasi Cocok (Petugas)</span>',
                    'reviewed'        => '<span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2 rounded-pill"><i class="bi bi-eye-fill me-1"></i> Sudah Ditinjau</span>',
                    'rejected'        => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i> Ditolak (Tidak Cocok)</span>',
                    default           => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill"><i class="bi bi-hourglass-split me-1"></i> Rekomendasi Baru</span>',
                };
                ?>
                <div class="tab-pane fade <?= $isActive ? 'show active' : '' ?>" id="found-candidate-pane-<?= (int)$candidate['id'] ?>" role="tabpanel">
                    
                    <!-- DEDICATED SIDE-BY-SIDE VISUAL COMPARISON CARD -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                        <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-purple text-white rounded-pill px-3 py-2">
                                    <i class="bi bi-columns-gap me-1"></i> Komparasi Visual Dua Sisi
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1 small">
                                    Kandidat #<?= $idx + 1 ?> dari <?= count($candidates) ?>
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge rounded-pill px-3 py-2 fs-6 shadow-sm <?= $scoreBadgeClass ?>">
                                    <i class="bi bi-stars me-1"></i> <?= number_format($score, 1) ?>% Match
                                </span>
                                <div id="status-badge-container-found-<?= (int)$candidate['id'] ?>">
                                    <?= $statusBadge ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <!-- SIDE-BY-SIDE GRID -->
                            <div class="row g-4 align-items-stretch">
                                <!-- LEFT COLUMN: BARANG DITEMUKAN (SUMBER TEMUAN) -->
                                <div class="col-12 col-lg-6 border-lg-end">
                                    <div class="p-3 bg-light rounded-4 h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <span class="badge bg-success text-white rounded-pill px-3 py-1">
                                                <i class="bi bi-box-seam me-1"></i> Barang Ditemukan (Laporan Sumber)
                                            </span>
                                            <span class="text-muted small font-monospace">
                                                <code><?= htmlspecialchars($foundReport['report_number']) ?></code>
                                            </span>
                                        </div>

                                        <!-- Photo Container Left -->
                                        <div class="cf-compare-img-box mb-3 text-center position-relative">
                                            <?php if (!empty($foundReport['primary_image'])): ?>
                                                <img src="<?= htmlspecialchars($foundReport['primary_image'], ENT_QUOTES, 'UTF-8') ?>"
                                                     alt="<?= htmlspecialchars($foundReport['title'], ENT_QUOTES, 'UTF-8') ?>"
                                                     class="img-fluid rounded-3 cf-zoomable-img"
                                                     onclick="openImageLightbox(this.src, '<?= htmlspecialchars(addslashes($foundReport['title']), ENT_QUOTES, 'UTF-8') ?> (Barang Temuan)')">
                                                <div class="cf-zoom-hint">
                                                    <i class="bi bi-zoom-in me-1"></i> Klik untuk memperbesar
                                                </div>
                                            <?php else: ?>
                                                <div class="cf-no-img-placeholder">
                                                    <i class="bi bi-image text-muted display-4 d-block mb-2 opacity-50"></i>
                                                    <span class="text-muted small">Foto fisik tidak dilampirkan oleh penemu</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Attributes Left -->
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold text-dark mb-2">
                                                <?= htmlspecialchars($foundReport['title']) ?>
                                            </h5>
                                            <table class="table table-sm table-borderless small mb-3">
                                                <tbody>
                                                    <tr>
                                                        <td class="text-muted py-1" style="width: 130px;">Kategori</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-tag text-purple me-1"></i> <?= htmlspecialchars($foundReport['category_name'] ?? 'Umum') ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Lokasi Temuan</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-geo-alt text-danger me-1"></i> <?= htmlspecialchars($foundReport['location_name'] ?? '-') ?>
                                                            <?php if (!empty($foundReport['location_detail'])): ?>
                                                                <span class="text-muted fw-normal">(<?= htmlspecialchars($foundReport['location_detail']) ?>)</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Waktu Temuan</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-calendar3 me-1 text-muted"></i> <?= date('d M Y', strtotime($foundReport['found_date'])) ?>
                                                            <?php if (!empty($foundReport['found_time'])): ?>
                                                                <span class="text-muted fw-normal">pk <?= htmlspecialchars(substr($foundReport['found_time'], 0, 5)) ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Kondisi Fisik</td>
                                                        <td class="py-1">
                                                            <span class="badge bg-light text-dark border">
                                                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $foundReport['item_condition'] ?? 'baik'))) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>

                                            <div class="mb-2">
                                                <div class="text-muted extra-small fw-bold text-uppercase mb-1">Deskripsi Publik Barang Temuan:</div>
                                                <div class="p-2 bg-white rounded-3 small text-dark border">
                                                    <?= nl2br(htmlspecialchars($foundReport['description'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- RIGHT COLUMN: LAPORAN KEHILANGAN (KANDIDAT) -->
                                <div class="col-12 col-lg-6">
                                    <div class="p-3 bg-light rounded-4 h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <span class="badge bg-danger text-white rounded-pill px-3 py-1">
                                                <i class="bi bi-search me-1"></i> Barang Hilang (Kandidat Kehilangan)
                                            </span>
                                            <span class="text-muted small font-monospace">
                                                <code><?= htmlspecialchars($candidate['lost_report_number']) ?></code>
                                            </span>
                                        </div>

                                        <!-- Photo Container Right -->
                                        <div class="cf-compare-img-box mb-3 text-center position-relative">
                                            <?php if (!empty($candidate['lost_image'])): ?>
                                                <img src="<?= htmlspecialchars($candidate['lost_image'], ENT_QUOTES, 'UTF-8') ?>"
                                                     alt="<?= htmlspecialchars($candidate['lost_title'], ENT_QUOTES, 'UTF-8') ?>"
                                                     class="img-fluid rounded-3 cf-zoomable-img"
                                                     onclick="openImageLightbox(this.src, '<?= htmlspecialchars(addslashes($candidate['lost_title']), ENT_QUOTES, 'UTF-8') ?> (Laporan Kehilangan)')">
                                                <div class="cf-zoom-hint">
                                                    <i class="bi bi-zoom-in me-1"></i> Klik untuk memperbesar
                                                </div>
                                            <?php else: ?>
                                                <div class="cf-no-img-placeholder">
                                                    <i class="bi bi-image text-muted display-4 d-block mb-2 opacity-50"></i>
                                                    <span class="text-muted small">Foto fisik tidak dilampirkan oleh pelapor</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Attributes Right -->
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold text-dark mb-2">
                                                <?= htmlspecialchars($candidate['lost_title']) ?>
                                            </h5>
                                            <table class="table table-sm table-borderless small mb-3">
                                                <tbody>
                                                    <tr>
                                                        <td class="text-muted py-1" style="width: 130px;">Kategori</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-tag text-purple me-1"></i> <?= htmlspecialchars($candidate['category_name'] ?? 'Umum') ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Lokasi Terakhir</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-geo-alt text-danger me-1"></i> <?= htmlspecialchars($candidate['location_name'] ?? '-') ?>
                                                            <?php if (!empty($candidate['location_detail'])): ?>
                                                                <span class="text-muted fw-normal">(<?= htmlspecialchars($candidate['location_detail']) ?>)</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Waktu Hilang</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-calendar3 me-1 text-muted"></i> <?= date('d M Y', strtotime($candidate['lost_date'])) ?>
                                                            <?php if (!empty($candidate['lost_time'])): ?>
                                                                <span class="text-muted fw-normal">pk <?= htmlspecialchars(substr($candidate['lost_time'], 0, 5)) ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Status Laporan</td>
                                                        <td class="py-1">
                                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill">
                                                                <?= htmlspecialchars(ucfirst($candidate['lost_status'] ?? 'published')) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>

                                            <div class="mb-2">
                                                <div class="text-muted extra-small fw-bold text-uppercase mb-1">Deskripsi Publik Barang Hilang:</div>
                                                <div class="p-2 bg-white rounded-3 small text-dark border">
                                                    <?= nl2br(htmlspecialchars($candidate['lost_description'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MATCH ANALYSIS & EXPLANATION -->
                            <div class="mt-4 p-3 rounded-4 bg-light-purple border border-purple-subtle">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <div class="fw-bold text-purple extra-small text-uppercase tracking-wider">
                                        <?php if ($isFallback): ?>
                                            <i class="bi bi-tag-fill me-1"></i> Analisis Atribut Dasar (Local Baseline)
                                        <?php else: ?>
                                            <i class="bi bi-cpu-fill me-1"></i> Analisis Kecerdasan Buatan (NVIDIA NIM AI Assistance)
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-white text-purple border shadow-xs rounded-pill small">
                                        Skor Kesamaan: <?= number_format($score, 1) ?>%
                                    </span>
                                </div>
                                <div class="small text-dark lh-base">
                                    <?= htmlspecialchars($candidate['explanation'] ?? ($candidate['matching_explanation'] ?? 'Pencocokan atribut dasar berdasarkan kategori, tanggal, lokasi, dan kata kunci deskripsi.')) ?>
                                </div>
                            </div>

                            <!-- OFFICER ACTION BAR -->
                            <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div class="d-flex gap-2">
                                    <a href="/reports/lost/<?= (int)$candidate['lost_report_id'] ?>" class="btn btn-outline-secondary rounded-pill px-3 btn-sm">
                                        <i class="bi bi-search me-1"></i> Buka Laporan Kehilangan
                                    </a>
                                    <a href="/reports/found/<?= (int)$foundReport['id'] ?>" class="btn btn-outline-secondary rounded-pill px-3 btn-sm">
                                        <i class="bi bi-box-seam me-1"></i> Buka Laporan Temuan
                                    </a>
                                </div>

                                <!-- Officer Verification Controls -->
                                <div class="dropdown">
                                    <button class="btn btn-purple rounded-pill btn-sm dropdown-toggle px-4 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-shield-check me-1"></i> Keputusan Petugas
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <button type="button" class="dropdown-item text-success candidate-status-btn"
                                                    data-candidate-id="<?= (int)$candidate['id'] ?>"
                                                    data-status="confirmed_match"
                                                    data-csrf="<?= \App\Middleware\CsrfMiddleware::getToken() ?>">
                                                <i class="bi bi-check-circle me-2"></i> Konfirmasi Cocok
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item text-info candidate-status-btn"
                                                    data-candidate-id="<?= (int)$candidate['id'] ?>"
                                                    data-status="reviewed"
                                                    data-csrf="<?= \App\Middleware\CsrfMiddleware::getToken() ?>">
                                                <i class="bi bi-eye me-2"></i> Tandai Sudah Ditinjau
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger candidate-status-btn"
                                                    data-candidate-id="<?= (int)$candidate['id'] ?>"
                                                    data-status="rejected"
                                                    data-csrf="<?= \App\Middleware\CsrfMiddleware::getToken() ?>">
                                                <i class="bi bi-x-circle me-2"></i> Tandai Tidak Cocok
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================== -->
<!-- IMAGE LIGHTBOX MODAL -->
<!-- ========================================== -->
<div class="modal fade" id="cf-image-lightbox-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-dark">
            <div class="modal-header border-0 bg-dark text-white py-2 px-3">
                <span class="modal-title small fw-semibold text-truncate" id="cf-lightbox-title">Pratinjau Foto</span>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 text-center bg-black d-flex align-items-center justify-content-center" style="min-height: 400px; max-height: 80vh;">
                <img src="" id="cf-lightbox-img" class="img-fluid" style="max-height: 75vh; object-fit: contain;" alt="Enlarged photo">
            </div>
            <div class="modal-footer border-0 bg-dark text-muted small py-2 px-3 justify-content-between">
                <span class="extra-small text-white-50"><i class="bi bi-info-circle me-1"></i> Foto laporan untuk komparasi verifikasi visual</span>
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --cf-sage-green: #84a98c;
    --cf-sage-green-dark: #52796f;
    --cf-deep-olive: #354f52;
    --cf-dark-olive: #2f3e46;
    --cf-soft-white: #f8f9fa;
}

.text-purple { color: #6f42c1; }
.bg-purple { background-color: #6f42c1; }
.btn-purple { background-color: #6f42c1; color: #fff; }
.btn-purple:hover { background-color: #59339d; color: #fff; }
.btn-outline-purple { border-color: #6f42c1; color: #6f42c1; }
.btn-outline-purple:hover { background-color: #6f42c1; color: #fff; }
.bg-purple-subtle { background-color: rgba(111, 66, 193, 0.12); }
.border-purple-subtle { border-color: rgba(111, 66, 193, 0.25) !important; }
.bg-light-purple { background-color: rgba(111, 66, 193, 0.05); }

.cf-olive-text { color: var(--cf-dark-olive); }
.text-sage { color: var(--cf-sage-green-dark); }

.cf-compare-img-box {
    height: 280px;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 0 10px rgba(0,0,0,0.02);
}
.cf-compare-img-box img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.25s ease;
}
.cf-compare-img-box img:hover {
    transform: scale(1.02);
}
.cf-zoom-hint {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(0, 0, 0, 0.65);
    color: #ffffff;
    font-size: 0.72rem;
    padding: 3px 8px;
    border-radius: 20px;
    backdrop-filter: blur(4px);
    pointer-events: none;
    opacity: 0.85;
}
.cf-zoomable-img { cursor: zoom-in; }
.cf-no-img-placeholder { padding: 2rem; }

.extra-small { font-size: 0.75rem; }
@media (min-width: 992px) {
    .border-lg-end { border-right: 1px solid #dee2e6 !important; }
}
</style>

<script>
function openImageLightbox(src, title) {
    const modalEl = document.getElementById('cf-image-lightbox-modal');
    const imgEl = document.getElementById('cf-lightbox-img');
    const titleEl = document.getElementById('cf-lightbox-title');
    if (!modalEl || !imgEl) return;

    imgEl.src = src;
    if (titleEl) titleEl.textContent = title || 'Pratinjau Foto';
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const statusBtns = document.querySelectorAll('.candidate-status-btn');
    statusBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const candidateId = this.dataset.candidateId;
            const status = this.dataset.status;
            const csrfToken = this.dataset.csrf;

            const formData = new FormData();
            formData.append('status', status);
            formData.append('_csrf_token', csrfToken);
            formData.append('format', 'json');

            fetch('/ai-match/' + candidateId + '/status', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badgeContainer = document.getElementById('status-badge-container-found-' + candidateId);
                    if (badgeContainer) {
                        let badgeHtml = '';
                        if (status === 'confirmed_match') {
                            badgeHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Dikonfirmasi Cocok (Petugas)</span>';
                        } else if (status === 'reviewed') {
                            badgeHtml = '<span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2 rounded-pill"><i class="bi bi-eye-fill me-1"></i> Sudah Ditinjau</span>';
                        } else if (status === 'rejected') {
                            badgeHtml = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i> Ditolak (Tidak Cocok)</span>';
                        } else {
                            badgeHtml = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill"><i class="bi bi-hourglass-split me-1"></i> Rekomendasi Baru</span>';
                        }
                        badgeContainer.innerHTML = badgeHtml;
                    }

                    if (window.AppToast && typeof window.AppToast.show === 'function') {
                        window.AppToast.show('success', data.message || 'Status kandidat diperbarui.');
                    }
                }
            })
            .catch(err => console.error(err));
        });
    });
});
</script>

