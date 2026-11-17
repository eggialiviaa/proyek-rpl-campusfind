<?php
/**
 * View: Rekomendasi Kecocokan AI & Komparasi Visual (Lost Report Source)
 * File: app/Views/ai_match/candidates.php
 * Architecture: PHP Native MVC + Bootstrap 5 + Vanilla JS
 */
?>

<?php
$authUser = \App\Middleware\AuthMiddleware::user();
$userRole = $authUser ? strtolower(trim((string)($authUser['role_name'] ?? $authUser['role'] ?? ''))) : null;
$isCivitas = \App\Middleware\AuthMiddleware::isLoggedIn() && ($userRole === 'civitas');
?>

<div class="container py-4 cf-workspace-wide cf-ai-match-container">
    <!-- Standalone Transparent Page Header (Aura Liquid Glass) -->
    <?php
    $aiActions = '<div class="d-flex flex-wrap gap-2 align-items-center">'
               . '<form action="/ai-match/lost/' . (int)$lostReport['id'] . '/run" method="POST" class="d-inline ai-match-scan-form" data-report-id="' . (int)$lostReport['id'] . '" data-report-type="lost">'
               . \App\Middleware\CsrfMiddleware::field()
               . '<button type="submit" class="btn btn-purple shadow-sm rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1.5"><i class="bi bi-arrow-clockwise"></i> Pindai Ulang AI</button>'
               . '</form>'
               . '<a href="/reports/lost/' . (int)$lostReport['id'] . '" class="btn btn-outline-secondary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1.5"><i class="bi bi-arrow-left"></i> Kembali ke Detail</a>'
               . '</div>';

    $backListUrl = $isCivitas ? '/reports/lost' : '/reports/manage?tab=lost';
    $backListLabel = $isCivitas ? 'Barang Hilang' : 'Laporan Kehilangan';

    $headerConfig = [
        'eyebrow'     => 'AI Intelligence Assist',
        'title'       => 'Rekomendasi Kecocokan AI & Komparasi Visual',
        'icon'        => 'bi bi-columns-gap',
        'description' => 'Pencocokan berbasis AI Assistance dengan komparasi visual foto asli untuk laporan: ' . htmlspecialchars($lostReport['title'], ENT_QUOTES, 'UTF-8'),
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => $backListLabel, 'url' => $backListUrl],
            ['label' => htmlspecialchars($lostReport['report_number'], ENT_QUOTES, 'UTF-8'), 'url' => '/reports/lost/' . (int)$lostReport['id']],
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
            <i class="bi bi-shield-check me-1"></i> Data private &amp; bukti rahasia tidak dikirim ke API eksternal.
        </small>
    </div>

    <!-- MANDATORY AI DISCLAIMER BANNER -->
    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4" role="alert">
        <div class="d-flex align-items-start gap-3">
            <div class="fs-2 text-warning lh-1 mt-1">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-1">Catatan Penting â€” Batasan &amp; Verifikasi Manusia (Human-in-the-Loop)</h5>
                <p class="mb-0 small text-dark opacity-90">
                    Hasil analisis disusun oleh <strong>AI Matching Assistance</strong> sebagai sarana pendukung keputusan <em>(decision support)</em> berdasarkan kesesuaian atribut dan kemiripan deskripsi publik.
                    <strong>Rekomendasi AI adalah indikasi sistem, BUKAN bukti sah kepemilikan.</strong> Keputusan verifikasi, pemeriksaan fisik, dan persetujuan klaim sepenuhnya berada pada <strong>Petugas Posko Lost &amp; Found Center</strong>.
                </p>
            </div>
        </div>
    </div>

    <!-- PANEL DIAGNOSTIK KEPUTUSAN AI (KHUSUS PETUGAS & ADMIN) -->
    <?php if ($isOfficer && !empty($diagnostic)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-cpu text-purple"></i> Log Diagnostik Pemindaian AI (Transparansi Teknis)
                </h6>
                <span class="badge bg-purple-subtle text-purple rounded-pill px-3 py-1 small">
                    Ambang Possible Match: <?= (int)($threshold ?? 60) ?>%
                </span>
            </div>
            <div class="card-body p-3 small">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <span class="text-muted d-block">Pool &amp; Pra-Seleksi:</span>
                        <strong><?= (int)($diagnostic['pool_count'] ?? 0) ?> kandidat dicek</strong> â†’
                        <span class="text-success fw-bold"><?= (int)($diagnostic['preselected_count'] ?? 0) ?> lolos</span>
                        <?php if (!empty($diagnostic['eliminated_reasons'])): ?>
                            <div class="text-muted extra-small mt-1">
                                Tersingkir:
                                Kategori (<?= (int)($diagnostic['eliminated_reasons']['category_mismatch'] ?? 0) ?>),
                                Tanggal (<?= (int)($diagnostic['eliminated_reasons']['date_out_of_range'] ?? 0) ?>),
                                Status (<?= (int)($diagnostic['eliminated_reasons']['ineligible_status'] ?? 0) ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-3">
                        <span class="text-muted d-block">Model &amp; Durasi:</span>
                        <code class="text-dark d-block text-truncate" title="<?= htmlspecialchars($diagnostic['model_used'] ?? '-') ?>"><?= htmlspecialchars($diagnostic['model_used'] ?? '-') ?></code>
                        <span class="badge bg-secondary-subtle text-secondary"><?= (int)($diagnostic['duration_ms'] ?? 0) ?> ms</span>
                    </div>
                    <div class="col-12 col-md-3">
                        <span class="text-muted d-block">Status Panggilan:</span>
                        <?php
                        $cStatus = $diagnostic['call_status'] ?? 'unknown';
                        $statusBadgeClass = match ($cStatus) {
                            'success', 'completed'                    => 'bg-success text-white',
                            'fallback_local', 'completed_fallback'   => 'bg-info text-white',
                            'timeout', 'provider_timeout'            => 'bg-danger text-white',
                            'no_candidates'                          => 'bg-secondary text-white',
                            'invalid_key', 'model_not_found',
                            'quota_exceeded', 'content_truncated'    => 'bg-danger-subtle text-danger border border-danger-subtle',
                            default                                  => 'bg-warning text-dark',
                        };
                        ?>
                        <span class="badge <?= $statusBadgeClass ?> rounded-pill px-2 py-1">
                            <?= htmlspecialchars(strtoupper($cStatus)) ?>
                        </span>
                        <?php if (!empty($diagnostic['error'])): ?>
                            <?php
                            $errDesc = match ($cStatus) {
                                'content_truncated' => 'Output model terpotong / model parser khusus',
                                'model_not_found'   => 'Model tidak ditemukan / sudah EOL',
                                'invalid_key'       => 'API Key tidak valid / otorisasi gagal',
                                'quota_exceeded'    => 'Limit kuota provider terlampaui',
                                'provider_timeout'  => 'Waktu panggilan provider habis',
                                default             => htmlspecialchars($diagnostic['error'])
                            };
                            ?>
                            <div class="text-danger extra-small mt-1 text-truncate" title="<?= htmlspecialchars($diagnostic['error']) ?>">
                                <?= $errDesc ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-3">
                        <span class="text-muted d-block">Klasifikasi Logika:</span>
                        <?php
                        $resStatus = $diagnostic['result_status'] ?? 'unknown';
                        $resLabel = match ($resStatus) {
                            'match_found', 'completed'        => 'Possible Match Ditemukan',
                            'no_match'                        => 'Tidak Ada Kecocokan',
                            'no_candidates'                   => 'Belum Ada Temuan Layak',
                            'completed_fallback'              => 'Rekomendasi Fallback (Atribut)',
                            'provider_timeout'                => 'Waktu Habis (Timeout)',
                            'provider_error', 'ai_failed'     => 'Gangguan Layanan AI',
                            'ineligible_status'               => 'Status Tidak Layak',
                            default                           => $resStatus,
                        };
                        ?>
                        <strong class="text-dark d-block"><?= htmlspecialchars($resLabel) ?></strong>
                        <span class="text-muted extra-small">Skor mentah: <?= !empty($diagnostic['raw_scores']) ? implode(', ', array_map(fn($k, $v) => "#{$k}: {$v}%", array_keys($diagnostic['raw_scores']), $diagnostic['raw_scores'])) : 'Tidak ada' ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
                        Rekomendasi Berbasis Analisis Atribut Dasar: Ditemukan <strong><?= count($candidates) ?> kandidat</strong> awal.
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
                        Analisis AI Selesai: Ditemukan <strong><?= count($candidates) ?> kandidat</strong> Possible Match yang berpotensi cocok.
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
        <?php if ($resStatus === 'no_candidates'): ?>
            <div class="card border-0 shadow-sm rounded-4 text-center p-5 mb-4">
                <div class="py-4">
                    <i class="bi bi-inboxes text-muted display-4 d-block mb-3 opacity-50"></i>
                    <h4 class="fw-bold text-dark mb-2">Belum Ada Barang Temuan yang Bisa Dibandingkan</h4>
                    <p class="text-muted max-w-md mx-auto mb-3">
                        Saat ini belum ada laporan barang temuan aktif yang memenuhi kriteria pra-seleksi (kategori yang sama, rentang tanggal masuk akal, dan berstatus temuan yang layak diklaim).
                    </p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <form action="/ai-match/lost/<?= (int)$lostReport['id'] ?>/run" method="POST" class="d-inline ai-match-scan-form" data-report-id="<?= (int)$lostReport['id'] ?>" data-report-type="lost">
                            <?= \App\Middleware\CsrfMiddleware::field() ?>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="bi bi-arrow-clockwise me-1"></i> Pindai Ulang Katalog
                            </button>
                        </form>
                        <a href="/reports/lost/<?= (int)$lostReport['id'] ?>" class="btn btn-outline-secondary rounded-pill px-4">
                            Kembali ke Detail Laporan
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($resStatus === 'no_match'): ?>
            <div class="card border-0 shadow-sm rounded-4 text-center p-5 mb-4">
                <div class="py-4">
                    <i class="bi bi-clipboard-x text-muted display-4 d-block mb-3 opacity-50"></i>
                    <h4 class="fw-bold text-dark mb-2">Tidak Ada Kecocokan Signifikan</h4>
                    <p class="text-muted max-w-md mx-auto mb-4">
                        Sistem telah membandingkan <?= (int)($diagnostic['preselected_count'] ?? 0) ?> barang temuan berkategori sama, namun tingkat kemiripan atribut belum mencapai ambang batas Possible Match (ambang: <?= (int)($threshold ?? 60) ?>%).
                    </p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <form action="/ai-match/lost/<?= (int)$lostReport['id'] ?>/run" method="POST" class="d-inline ai-match-scan-form" data-report-id="<?= (int)$lostReport['id'] ?>" data-report-type="lost">
                            <?= \App\Middleware\CsrfMiddleware::field() ?>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="bi bi-arrow-clockwise me-1"></i> Pindai Ulang AI
                            </button>
                        </form>
                        <a href="/reports/lost/<?= (int)$lostReport['id'] ?>" class="btn btn-outline-secondary rounded-pill px-4">
                            Kembali ke Detail Laporan
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($resStatus === 'provider_timeout'): ?>
            <div class="card border-0 shadow-sm rounded-4 text-center p-5 mb-4">
                <div class="py-4">
                    <i class="bi bi-clock-history text-warning display-4 d-block mb-3"></i>
                    <h4 class="fw-bold text-dark mb-2">Waktu Permintaan AI Habis (Timeout)</h4>
                    <p class="text-muted max-w-md mx-auto mb-3">
                        AI eksternal belum berhasil merespons dalam batas waktu. Campus Find telah mengevaluasi kategori barang, deskripsi, lokasi, dan tanggal laporan, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.
                    </p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <form action="/ai-match/lost/<?= (int)$lostReport['id'] ?>/run" method="POST" class="d-inline ai-match-scan-form" data-report-id="<?= (int)$lostReport['id'] ?>" data-report-type="lost">
                            <?= \App\Middleware\CsrfMiddleware::field() ?>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="bi bi-arrow-clockwise me-1"></i> Coba Lagi
                            </button>
                        </form>
                        <a href="/reports/lost/<?= (int)$lostReport['id'] ?>" class="btn btn-outline-secondary rounded-pill px-4">
                            Kembali ke Detail Laporan
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 text-center p-5 mb-4">
                <div class="py-4">
                    <i class="bi bi-exclamation-octagon-fill text-danger display-4 d-block mb-3"></i>
                    <h4 class="fw-bold text-dark mb-2">Kendala Layanan AI Eksternal</h4>
                    <p class="text-muted max-w-md mx-auto mb-3">
                        Layanan AI eksternal sedang mengalami gangguan teknis. Sistem telah mengevaluasi atribut dasar, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.
                    </p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <form action="/ai-match/lost/<?= (int)$lostReport['id'] ?>/run" method="POST" class="d-inline ai-match-scan-form" data-report-id="<?= (int)$lostReport['id'] ?>" data-report-type="lost">
                            <?= \App\Middleware\CsrfMiddleware::field() ?>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="bi bi-arrow-clockwise me-1"></i> Coba Lagi
                            </button>
                        </form>
                        <a href="/reports/lost/<?= (int)$lostReport['id'] ?>" class="btn btn-outline-secondary rounded-pill px-4">
                            Kembali ke Detail Laporan
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
                            <i class="bi bi-collection me-1 text-purple"></i> Pilih Kandidat Temuan untuk Dibandingkan (<?= count($candidates) ?> ditemukan):
                        </span>
                        <span class="text-muted extra-small">Klik tab di bawah untuk beralih tampilan komparasi</span>
                    </div>
                    <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto py-1" id="candidateTabs" role="tablist">
                        <?php foreach ($candidates as $idx => $cand): ?>
                            <?php
                            $cScore = (float)$cand['relevance_score'];
                            $badgeColor = $cScore >= 70 ? 'success' : ($cScore >= 45 ? 'warning' : 'secondary');
                            $isActive = ($idx === $selectedIdx);
                            ?>
                            <li class="nav-item flex-shrink-0" role="presentation">
                                <button class="nav-link rounded-pill px-3 py-2 text-start d-flex align-items-center gap-2 <?= $isActive ? 'active' : '' ?>"
                                        id="tab-btn-<?= (int)$cand['id'] ?>"
                                        data-bs-toggle="pill"
                                        data-bs-target="#candidate-pane-<?= (int)$cand['id'] ?>"
                                        type="button"
                                        role="tab"
                                        aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                                    <span class="badge bg-<?= $badgeColor ?> rounded-pill small"><?= round($cScore) ?>%</span>
                                    <span class="text-truncate fw-medium" style="max-width: 180px;"><?= htmlspecialchars($cand['found_title']) ?></span>
                                    <code>#<?= htmlspecialchars($cand['found_report_number']) ?></code>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tab Content Panes -->
        <div class="tab-content" id="candidateTabsContent">
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
                    default           => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill"><i class="bi bi-hourglass-split me-1"></i> Rekomendasi Baru (Menunggu Peninjauan)</span>',
                };
                ?>
                <div class="tab-pane fade <?= $isActive ? 'show active' : '' ?>" id="candidate-pane-<?= (int)$candidate['id'] ?>" role="tabpanel">
                    
                    <!-- DEDICATED SIDE-BY-SIDE VISUAL COMPARISON CARD -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                        <!-- Card Top Bar: Match Score & Verification Status -->
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
                                <div id="status-badge-container-<?= (int)$candidate['id'] ?>">
                                    <?= $statusBadge ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <!-- SIDE-BY-SIDE GRID -->
                            <div class="row g-4 align-items-stretch">
                                <!-- LEFT COLUMN: LAPORAN KEHILANGAN (SUMBER) -->
                                <div class="col-12 col-lg-6 border-lg-end">
                                    <div class="p-3 bg-light rounded-4 h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <span class="badge bg-danger text-white rounded-pill px-3 py-1">
                                                <i class="bi bi-search me-1"></i> Barang Hilang (Laporan Sumber)
                                            </span>
                                            <span class="text-muted small font-monospace">
                                                <code><?= htmlspecialchars($lostReport['report_number']) ?></code>
                                            </span>
                                        </div>

                                        <!-- Photo Container Left -->
                                        <div class="cf-compare-img-box mb-3 text-center position-relative">
                                            <?php if (!empty($lostReport['primary_image'])): ?>
                                                <img src="<?= htmlspecialchars($lostReport['primary_image'], ENT_QUOTES, 'UTF-8') ?>"
                                                     alt="<?= htmlspecialchars($lostReport['title'], ENT_QUOTES, 'UTF-8') ?>"
                                                     class="img-fluid rounded-3 cf-zoomable-img"
                                                     onclick="openImageLightbox(this.src, '<?= htmlspecialchars(addslashes($lostReport['title']), ENT_QUOTES, 'UTF-8') ?> (Laporan Kehilangan)')">
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

                                        <!-- Detail Attributes Left -->
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold text-dark mb-2">
                                                <?= htmlspecialchars($lostReport['title']) ?>
                                            </h5>
                                            <table class="table table-sm table-borderless small mb-3">
                                                <tbody>
                                                    <tr>
                                                        <td class="text-muted py-1" style="width: 130px;">Kategori</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-tag text-purple me-1"></i> <?= htmlspecialchars($lostReport['category_name'] ?? 'Umum') ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Lokasi Terakhir</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-geo-alt text-danger me-1"></i> <?= htmlspecialchars($lostReport['location_name'] ?? '-') ?>
                                                            <?php if (!empty($lostReport['location_detail'])): ?>
                                                                <span class="text-muted fw-normal">(<?= htmlspecialchars($lostReport['location_detail']) ?>)</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Waktu Hilang</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-calendar3 me-1 text-muted"></i> <?= date('d M Y', strtotime($lostReport['lost_date'])) ?>
                                                            <?php if (!empty($lostReport['lost_time'])): ?>
                                                                <span class="text-muted fw-normal">pk <?= htmlspecialchars(substr($lostReport['lost_time'], 0, 5)) ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Status Laporan</td>
                                                        <td class="py-1">
                                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill">
                                                                <?= htmlspecialchars(ucfirst($lostReport['status'] ?? 'published')) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>

                                            <div class="mb-2">
                                                <div class="text-muted extra-small fw-bold text-uppercase mb-1">Deskripsi Publik Barang Hilang:</div>
                                                <div class="p-2 bg-white rounded-3 small text-dark border">
                                                    <?= nl2br(htmlspecialchars($lostReport['description'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- RIGHT COLUMN: LAPORAN TEMUAN (KANDIDAT) -->
                                <div class="col-12 col-lg-6">
                                    <div class="p-3 bg-light rounded-4 h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <span class="badge bg-success text-white rounded-pill px-3 py-1">
                                                <i class="bi bi-box-seam me-1"></i> Barang Ditemukan (Kandidat Temuan)
                                            </span>
                                            <span class="text-muted small font-monospace">
                                                <code><?= htmlspecialchars($candidate['found_report_number']) ?></code>
                                            </span>
                                        </div>

                                        <!-- Photo Container Right -->
                                        <div class="cf-compare-img-box mb-3 text-center position-relative">
                                            <?php if (!empty($candidate['found_image'])): ?>
                                                <img src="<?= htmlspecialchars($candidate['found_image'], ENT_QUOTES, 'UTF-8') ?>"
                                                     alt="<?= htmlspecialchars($candidate['found_title'], ENT_QUOTES, 'UTF-8') ?>"
                                                     class="img-fluid rounded-3 cf-zoomable-img"
                                                     onclick="openImageLightbox(this.src, '<?= htmlspecialchars(addslashes($candidate['found_title']), ENT_QUOTES, 'UTF-8') ?> (Barang Temuan)')">
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

                                        <!-- Detail Attributes Right -->
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold text-dark mb-2">
                                                <?= htmlspecialchars($candidate['found_title']) ?>
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
                                                        <td class="text-muted py-1">Lokasi Temuan</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-geo-alt text-danger me-1"></i> <?= htmlspecialchars($candidate['location_name'] ?? '-') ?>
                                                            <?php if (!empty($candidate['location_detail'])): ?>
                                                                <span class="text-muted fw-normal">(<?= htmlspecialchars($candidate['location_detail']) ?>)</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Waktu Temuan</td>
                                                        <td class="fw-semibold text-dark py-1">
                                                            <i class="bi bi-calendar3 me-1 text-muted"></i> <?= date('d M Y', strtotime($candidate['found_date'])) ?>
                                                            <?php if (!empty($candidate['found_time'])): ?>
                                                                <span class="text-muted fw-normal">pk <?= htmlspecialchars(substr($candidate['found_time'], 0, 5)) ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted py-1">Kondisi Fisik</td>
                                                        <td class="py-1">
                                                            <span class="badge bg-light text-dark border">
                                                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $candidate['item_condition'] ?? 'baik'))) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>

                                            <div class="mb-2">
                                                <div class="text-muted extra-small fw-bold text-uppercase mb-1">Deskripsi Publik Barang Temuan:</div>
                                                <div class="p-2 bg-white rounded-3 small text-dark border">
                                                    <?= nl2br(htmlspecialchars($candidate['found_description'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MATCH ANALYSIS & SYSTEM EXPLANATION -->
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
                                    <?= htmlspecialchars($candidate['matching_explanation'] ?? 'Pencocokan atribut dasar berdasarkan kategori, tanggal, lokasi, dan kata kunci deskripsi.') ?>
                                </div>
                            </div>

                            <!-- ROLE-BASED ACTION FOOTER -->
                            <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <a href="/reports/found/<?= (int)$candidate['found_report_id'] ?>" class="btn btn-outline-secondary rounded-pill px-3 btn-sm me-2">
                                        <i class="bi bi-eye me-1"></i> Lihat Laporan Temuan Lengkap
                                    </a>
                                </div>

                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <?php if ($isOwner): ?>
                                        <!-- Civitas Owner Action -->
                                        <a href="/claims/create?found_id=<?= (int)$candidate['found_report_id'] ?>&lost_report_id=<?= (int)$lostReport['id'] ?>" class="btn btn-success rounded-pill px-4 shadow-sm btn-sm">
                                            <i class="bi bi-shield-check me-1"></i> Ajukan Klaim Kepemilikan
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($isOfficer): ?>
                                        <!-- Officer Verification Controls -->
                                        <div class="dropdown">
                                            <button class="btn btn-outline-purple rounded-pill btn-sm dropdown-toggle px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-check2-circle me-1"></i> Tindakan Petugas
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
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Candidate Cards Grid Summary -->
        <?php if (count($candidates) > 1): ?>
            <div class="mt-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-grid me-1 text-purple"></i> Seluruh Kandidat Temuan Terpilih (<?= count($candidates) ?>)
                </h6>
                <div class="row g-3">
                    <?php foreach ($candidates as $cIdx => $cItem): ?>
                        <?php
                        $cScore = (float)$cItem['relevance_score'];
                        $bClass = $cScore >= 70 ? 'bg-success' : ($cScore >= 45 ? 'bg-warning text-dark' : 'bg-secondary');
                        ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card border-0 shadow-xs rounded-4 h-100 hover-elevate p-3 bg-white">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="cf-thumb-box flex-shrink-0">
                                        <?php if (!empty($cItem['found_image'])): ?>
                                            <img src="<?= htmlspecialchars($cItem['found_image'], ENT_QUOTES, 'UTF-8') ?>" class="w-100 h-100 rounded-3" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="w-100 h-100 rounded-3 bg-light d-flex align-items-center justify-content-center text-muted">
                                                <i class="bi bi-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge <?= $bClass ?> rounded-pill extra-small"><?= round($cScore) ?>%</span>
                                            <code class="extra-small">#<?= htmlspecialchars($cItem['found_report_number']) ?></code>
                                        </div>
                                        <div class="fw-semibold text-dark small text-truncate"><?= htmlspecialchars($cItem['found_title']) ?></div>
                                        <div class="text-muted extra-small text-truncate"><?= htmlspecialchars($cItem['location_name'] ?? '-') ?> &bull; <?= date('d M Y', strtotime($cItem['found_date'])) ?></div>
                                        <button class="btn btn-link btn-sm p-0 extra-small text-purple mt-2" onclick="switchToCandidateTab(<?= (int)$cItem['id'] ?>)">
                                            Bandingkan di atas &rarr;
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
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
                <span class="extra-small text-white-50"><i class="bi bi-info-circle me-1"></i> Foto diunggah oleh pelapor untuk verifikasi visual</span>
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Campus Find AI Workspace & Scanner Styles */
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

/* Symmetrical Compare Image Container */
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
.cf-no-img-placeholder {
    padding: 2rem;
}
.cf-thumb-box {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    overflow: hidden;
    background: #f1f3f5;
}

.extra-small { font-size: 0.75rem; }
.hover-elevate {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.hover-elevate:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.06) !important;
}
@media (min-width: 992px) {
    .border-lg-end { border-right: 1px solid #dee2e6 !important; }
}
</style>

<script>
// Lightbox Opener
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

// Quick Switcher Function
function switchToCandidateTab(candidateId) {
    const tabBtn = document.getElementById('tab-btn-' + candidateId);
    if (tabBtn) {
        const bsTab = bootstrap.Tab.getOrCreateInstance(tabBtn);
        bsTab.show();
        tabBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Officer Candidate Status Quick Update Handler
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
                    const badgeContainer = document.getElementById('status-badge-container-' + candidateId);
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
                } else {
                    if (window.AppToast && typeof window.AppToast.show === 'function') {
                        window.AppToast.show('danger', data.message || 'Gagal memperbarui status.');
                    }
                }
            })
            .catch(err => {
                console.error(err);
                if (window.AppToast && typeof window.AppToast.show === 'function') {
                    window.AppToast.show('danger', 'Gagal menghubungi server.');
                }
            });
        });
    });
});
</script>

