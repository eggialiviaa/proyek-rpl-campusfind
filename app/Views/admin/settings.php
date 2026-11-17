<?php
/**
 * View: Pengaturan Sistem Administrator (Prompt 21 & Prompt 21B)
 * File: app/Views/admin/settings.php
 */

use App\Middleware\CsrfMiddleware;

$csrfToken = CsrfMiddleware::getToken();
$cfindTokens  = (int)($settings['cfind_max_output_tokens']['value'] ?? 1024);
$cfindTimeout = (int)($settings['cfind_timeout_seconds']['value'] ?? 15);

$roles = [
    'guest'   => ['label' => 'Tamu (Guest)',       'badge' => 'bg-secondary-subtle text-secondary border'],
    'civitas' => ['label' => 'Civitas Akademika',  'badge' => 'bg-primary-subtle text-primary border border-primary-subtle'],
    'petugas' => ['label' => 'Petugas L&F Center', 'badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
    'admin'   => ['label' => 'Administrator',      'badge' => 'bg-danger-subtle text-danger border border-danger-subtle'],
];

$notif = [
    'channel'        => $settings['notif_external_channel']['value'] ?? 'none',
    'email_status'   => (bool)($settings['notif_email_status']['value'] ?? false),
    'webhook_status' => (bool)($settings['notif_webhook_status']['value'] ?? false),
    'webhook_url'    => $settings['notif_webhook_url']['value'] ?? '',
];
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="/admin" class="text-decoration-none">Beranda admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengaturan sistem</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1">
                Pengaturan sistem
            </h1>
            <p class="text-muted mb-0 small">
                Konfigurasi model AI per fitur, penyedia layanan (AI NIM), batas peran chatbot C-Find, dan saluran notifikasi eksternal.
            </p>
        </div>
    </div>

    <!-- Security Info Banner -->
    <div class="alert alert-info border-0 shadow-sm rounded-4 d-flex align-items-start gap-3 p-3 mb-4 bg-info-subtle text-info-emphasis">
        <i class="bi bi-shield-lock-fill fs-3 text-info flex-shrink-0 mt-1"></i>
        <div class="small">
            <span class="fw-bold d-block mb-1">Keamanan &amp; Perlindungan Data Rahasia</span>
            Pengaturan di halaman ini diisolasi khusus untuk Administrator. <strong>API Key disimpan terenkripsi di database dan tidak pernah ditampilkan utuh</strong> (hanya 4 karakter terakhir dimasker).
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TASK 14: UNIFIED AI CONFIGURATION CENTER (SECTION A & SECTION B)             -->
    <!-- ========================================================================= -->

    <style>
    .btn-collapse-chevron .bi-chevron-down {
        display: inline-block;
        transition: transform 0.2s ease-in-out;
    }
    .btn-collapse-chevron:not(.collapsed) .bi-chevron-down {
        transform: rotate(180deg);
    }
    </style>

    <!-- SECTION A: PROVIDER CONNECTIONS -->
    <?php
    $connectedCount = 0;
    foreach ($providerConnections as $c) {
        if (!empty($c['is_configured'])) {
            $connectedCount++;
        }
    }
    ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" id="sectionProviderConnections">
        <div class="card-header bg-body border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-primary text-white rounded-3 shadow-sm">
                    <i class="bi bi-hdd-network-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Langkah 1 - Hubungkan provider</h5>
                    <div class="text-muted extra-small mt-1" id="section-a-subtitle">
                        <span id="connected-providers-count" class="fw-semibold text-primary"><?= $connectedCount ?></span> provider terhubung
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-primary btn-sm rounded-pill px-3 d-flex align-items-center gap-2 shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalAddCustomProvider" id="btnAddProvider">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Tambah Provider</span>
                </button>
            </div>
        </div>

        <div class="card-body p-4 pt-2">
            <!-- Global Action Feedback Alert -->
            <div id="providerActionAlert" class="alert d-none py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2">
                <i class="bi fs-5" id="providerActionAlertIcon"></i>
                <div class="flex-grow-1" id="providerActionAlertMsg"></div>
                <button type="button" class="btn-close btn-close-sm" aria-label="Close" onclick="document.getElementById('providerActionAlert').classList.add('d-none')"></button>
            </div>

            <!-- Provider Connection Collapsible Rows -->
            <div class="d-flex flex-column gap-3" id="providerConnectionsList">
                <?php foreach ($providerConnections as $pId => $conn): 
                    $isDb = !empty($conn['is_from_db']);
                    $isConfigured = !empty($conn['is_configured']);
                ?>
                    <div class="card border rounded-4 bg-body shadow-sm overflow-hidden" id="card-provider-<?= $pId ?>">
                        <!-- Header Row (Always Visible) -->
                        <div class="card-header bg-transparent border-0 p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 <?= $pId === 'nvidia_nim' ? 'bg-success-subtle text-success' : ($pId === 'google_gemini' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info') ?> rounded-3">
                                    <i class="bi <?= htmlspecialchars($conn['icon'] ?? 'bi-cpu-fill', ENT_QUOTES, 'UTF-8') ?> fs-5"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="fw-bold mb-0 text-dark" id="provider-title-<?= $pId ?>"><?= htmlspecialchars($conn['name'], ENT_QUOTES, 'UTF-8') ?></h6>
                                        <span class="badge bg-light text-secondary border extra-small"><?= htmlspecialchars($conn['badge'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <small class="text-muted extra-small d-none d-md-block"><?= htmlspecialchars($conn['description'], ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Status Badge -->
                                <span class="badge rounded-pill px-3 py-1 extra-small <?= $isDb ? 'bg-success-subtle text-success border border-success-subtle' : ($isConfigured ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-secondary-subtle text-secondary border') ?>" id="badge-provider-<?= $pId ?>">
                                    <i class="bi <?= $isDb ? 'bi-database-check' : ($isConfigured ? 'bi-file-earmark-check' : 'bi-dash-circle') ?> me-1"></i>
                                    <span id="badge-provider-text-<?= $pId ?>">
                                        <?= $isDb ? 'Terhubung (Database)' : 'Belum Dikonfigurasi' ?>
                                    </span>
                                </span>

                                <!-- Masked Key in Header -->
                                <span class="font-monospace extra-small bg-light px-2 py-1 rounded border text-muted" id="masked-key-<?= $pId ?>">
                                    <?= htmlspecialchars((string)$conn['api_key_masked'], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <!-- Kelola Button with Chevron -->
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 extra-small d-flex align-items-center gap-1 btn-collapse-chevron <?= $isConfigured ? 'collapsed' : '' ?>" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapse-provider-<?= $pId ?>" 
                                        aria-expanded="<?= $isConfigured ? 'false' : 'true' ?>" 
                                        aria-controls="collapse-provider-<?= $pId ?>">
                                    <span>Kelola</span>
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Collapsible Body -->
                        <div class="collapse <?= $isConfigured ? '' : 'show' ?>" id="collapse-provider-<?= $pId ?>">
                            <div class="card-body p-4 pt-1 border-top">
                                <p class="text-muted extra-small mb-3 d-md-none"><?= htmlspecialchars($conn['description'], ENT_QUOTES, 'UTF-8') ?></p>

                                <?php if (!empty($conn['is_custom'])): ?>
                                    <!-- Custom Provider Name Input -->
                                    <div class="mb-3">
                                        <label for="provider_name_<?= $pId ?>" class="form-label fw-semibold extra-small text-secondary mb-1">
                                            Nama Provider
                                        </label>
                                        <input type="text" 
                                               class="form-control form-control-sm extra-small font-monospace" 
                                               id="provider_name_<?= $pId ?>" 
                                               value="<?= htmlspecialchars((string)$conn['name'], ENT_QUOTES, 'UTF-8') ?>" 
                                               maxlength="40">
                                    </div>
                                <?php endif; ?>

                                <!-- Base URL Input -->
                                <div class="mb-3">
                                    <label for="provider_url_<?= $pId ?>" class="form-label fw-semibold extra-small text-secondary mb-1">
                                        Endpoint Base URL
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-globe2 text-muted"></i></span>
                                        <input type="url" 
                                               class="form-control border-start-0 font-monospace extra-small" 
                                               id="provider_url_<?= $pId ?>" 
                                               value="<?= htmlspecialchars((string)$conn['base_url'], ENT_QUOTES, 'UTF-8') ?>" 
                                               placeholder="<?= htmlspecialchars((string)$conn['default_url'], ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>

                                <!-- Source Info Box -->
                                <div class="p-2 bg-light rounded-3 border mb-3 d-flex justify-content-between align-items-center">
                                    <span class="extra-small text-muted font-monospace" id="source-provider-<?= $pId ?>">
                                        Sumber: <strong id="source-provider-text-<?= $pId ?>"><?= htmlspecialchars($conn['source'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </span>
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 extra-small btn-delete-provider <?= $isDb ? '' : 'd-none' ?>" 
                                            data-provider="<?= $pId ?>" 
                                            id="btn-delete-provider-<?= $pId ?>"
                                            title="Hapus API key dari database">
                                        <span class="spinner-border spinner-border-sm d-none delete-key-spinner" role="status"></span>
                                        <i class="bi bi-trash3 me-1 delete-key-icon"></i>
                                        <span>Hapus Key</span>
                                    </button>
                                </div>

                                <!-- Replace API Key Input with Eye Toggle -->
                                <div class="mb-3">
                                    <label for="provider_key_<?= $pId ?>" class="form-label fw-semibold extra-small text-secondary mb-1">
                                        Ganti API Key <?= htmlspecialchars($conn['name'], ENT_QUOTES, 'UTF-8') ?> (Rahasia)
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-key text-muted"></i></span>
                                        <input type="password" 
                                               class="form-control border-start-0 font-monospace extra-small" 
                                               id="provider_key_<?= $pId ?>" 
                                               placeholder="Biarkan kosong jika tidak ingin mengubah kunci..." 
                                               autocomplete="off"
                                               autocapitalize="none"
                                               spellcheck="false">
                                        <button class="btn btn-outline-secondary btn-toggle-eye" 
                                                type="button" 
                                                data-target="provider_key_<?= $pId ?>" 
                                                aria-label="Tampilkan atau sembunyikan API key" 
                                                aria-pressed="false"
                                                title="Tampilkan atau sembunyikan API key">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Provider Action Buttons -->
                                <div class="d-flex flex-wrap gap-2 pt-3 border-top mt-auto align-items-center">
                                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 btn-save-provider" data-provider="<?= $pId ?>">
                                        <span class="spinner-border spinner-border-sm d-none save-spinner" role="status" aria-hidden="true"></span>
                                        <i class="bi bi-cloud-arrow-up-fill save-icon me-1"></i>
                                        <span>Simpan Kredensial</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm rounded-pill px-3 btn-test-provider" data-provider="<?= $pId ?>">
                                        <span class="spinner-border spinner-border-sm d-none test-spinner" role="status" aria-hidden="true"></span>
                                        <i class="bi bi-lightning-charge-fill test-icon me-1"></i>
                                        <span class="test-btn-text">Uji Koneksi</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 btn-refresh-provider-models" data-provider="<?= $pId ?>">
                                        <span class="spinner-border spinner-border-sm d-none refresh-spinner" role="status" aria-hidden="true"></span>
                                        <i class="bi bi-arrow-clockwise refresh-icon me-1"></i>
                                        <span>Muat Model</span>
                                    </button>
                                    <?php if (!empty($conn['is_custom'])): ?>
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm rounded-pill px-3 ms-auto btn-delete-custom-provider" 
                                                data-provider="<?= $pId ?>" 
                                                data-name="<?= htmlspecialchars($conn['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                title="Hapus Provider Kustom">
                                            <i class="bi bi-trash3 me-1"></i>Hapus Provider
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Provider Test Result Feedback Alert -->
                                <div id="test-feedback-provider-<?= $pId ?>" class="mt-3 d-none">
                                    <div class="alert mb-0 py-2 px-3 small rounded-3 d-flex align-items-center gap-2" id="alert-box-provider-<?= $pId ?>">
                                        <i class="bi fs-5" id="alert-icon-provider-<?= $pId ?>"></i>
                                        <div class="flex-grow-1" id="alert-msg-provider-<?= $pId ?>"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- SECTION B: AI FEATURE ASSIGNMENT (DEFAULT & INDEPENDENT OVERRIDES) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" id="sectionFeatureAssignment">
        <div class="card-header bg-body border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-info-subtle text-info rounded-3 shadow-sm">
                    <i class="bi bi-sliders2-vertical fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Langkah 2 - Pilih model yang dipakai fitur AI</h5>
                    <small class="text-muted">Pilih model default untuk seluruh sistem. Chatbot C-Find dan Pencocokan AI otomatis mengikuti default kecuali Anda mengaturnya terpisah.</small>
                </div>
            </div>
        </div>

        <div class="card-body p-4 pt-2">
            <!-- Feature Assignment Feedback Alert -->
            <div id="featureActionAlert" class="alert d-none py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2">
                <i class="bi fs-5" id="featureActionAlertIcon"></i>
                <div class="flex-grow-1" id="featureActionAlertMsg"></div>
                <button type="button" class="btn-close btn-close-sm" aria-label="Close" onclick="document.getElementById('featureActionAlert').classList.add('d-none')"></button>
            </div>

            <?php
            $defaultModelInCatalog = false;
            foreach ($defaultModels as $dm) {
                if (($dm['id'] ?? '') === $defaultAssignment['model']) {
                    $defaultModelInCatalog = true;
                    break;
                }
            }
            $cfindModelInCatalog = false;
            foreach ($cfindModels as $cm) {
                if (($cm['id'] ?? '') === $assignmentCfind['model']) {
                    $cfindModelInCatalog = true;
                    break;
                }
            }
            $matchingModelInCatalog = false;
            foreach ($matchingModels as $mm) {
                if (($mm['id'] ?? '') === $assignmentMatching['model']) {
                    $matchingModelInCatalog = true;
                    break;
                }
            }
            $hasAnyOverride = !empty($assignmentCfind['is_override']) || !empty($assignmentMatching['is_override']);
            ?>

            <!-- BLOK 1: MODEL DEFAULT (UTAMA) -->
            <div class="card border border-2 border-primary-subtle rounded-4 p-4 mb-4 bg-primary-subtle bg-opacity-10 shadow-sm" id="cardModelDefault">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="p-2 bg-primary text-white rounded-3 shadow-sm">
                        <i class="bi bi-stars fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Model Default (Utama)</h6>
                        <small class="text-muted extra-small">Seluruh fitur AI otomatis menggunakan model ini kecuali Anda mengatur override per fitur di bawah.</small>
                    </div>
                </div>

                <div class="row g-3">
                    <!-- Default Provider Selector -->
                    <div class="col-12 col-md-6">
                        <label for="default_provider" class="form-label fw-semibold small text-secondary">
                            Pilih Provider Default <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-hdd-network text-muted"></i></span>
                            <select class="form-select border-start-0" id="default_provider">
                                <?php foreach ($registeredProviders as $rpId => $rpMeta): ?>
                                    <option value="<?= $rpId ?>" <?= $defaultAssignment['provider'] === $rpId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rpMeta['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($rpMeta['badge'], ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Default Dynamic Model Selector -->
                    <div class="col-12 col-md-6">
                        <label for="default_model" class="form-label fw-semibold small text-secondary">
                            Pilih Model Default <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-box-seam text-muted"></i></span>
                            <select class="form-select border-start-0 font-monospace" id="default_model">
                                <?php if (!$defaultModelInCatalog && !empty($defaultAssignment['model'])): ?>
                                    <option value="<?= htmlspecialchars($defaultAssignment['model'], ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($defaultAssignment['model'], ENT_QUOTES, 'UTF-8') ?> (Model tersimpan, tidak ada di katalog)
                                    </option>
                                <?php endif; ?>
                                <?php foreach ($defaultModels as $m): 
                                    $mId = $m['id'] ?? '';
                                    $isSel = ($mId === $defaultAssignment['model']);
                                ?>
                                    <option value="<?= htmlspecialchars($mId, ENT_QUOTES, 'UTF-8') ?>" <?= $isSel ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['label'] ?? $mId, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-warning py-1 px-2 extra-small mt-2 mb-0 <?= $defaultModelInCatalog ? 'd-none' : '' ?>" id="warning-catalog-default">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Model tidak lagi tersedia di provider ini, pilih model lain.
                        </div>
                    </div>
                </div>

                <!-- Default Action Buttons -->
                <div class="d-flex flex-wrap gap-2 pt-3 border-top mt-3 align-items-center">
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-4" id="btnSaveDefault">
                        <span class="spinner-border spinner-border-sm d-none save-spinner" role="status" aria-hidden="true"></span>
                        <i class="bi bi-check2-circle save-icon me-1"></i>
                        <span>Simpan Model Default</span>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-4 ms-auto" id="btnTestDefault">
                        <span class="spinner-border spinner-border-sm d-none test-spinner" role="status" aria-hidden="true"></span>
                        <i class="bi bi-play-circle-fill test-icon me-1"></i>
                        <span class="test-btn-text">Uji Model Default</span>
                    </button>
                </div>

                <!-- Single Status Line for Default -->
                <div class="mt-2 text-muted small" id="status-line-default">
                    Aktif: <strong class="text-dark font-monospace" id="status-text-default"><?= htmlspecialchars($defaultAssignment['provider_name'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string)$defaultAssignment['model'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <!-- Default Test Feedback Box -->
                <div id="test-feedback-default" class="mt-3 d-none">
                    <div class="alert mb-0 py-2 px-3 small rounded-3 d-flex align-items-center gap-2" id="alert-box-default">
                        <i class="bi fs-5" id="alert-icon-default"></i>
                        <div class="flex-grow-1" id="alert-msg-default"></div>
                    </div>
                </div>
            </div>

            <!-- BLOK 2: COLLAPSIBLE ACCORDION — ATUR TERPISAH PER FITUR (OPSIONAL) -->
            <div class="accordion border-0" id="accordionFeatureOverrides">
                <div class="accordion-item border rounded-4 overflow-hidden shadow-sm">
                    <h2 class="accordion-header" id="headingOverrides">
                        <button class="accordion-button collapsed bg-light text-dark fw-bold py-3 px-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOverrides" aria-expanded="false" aria-controls="collapseOverrides">
                            <i class="bi bi-sliders me-2 text-primary fs-5"></i>
                            <div class="flex-grow-1 d-flex flex-wrap align-items-center justify-content-between gap-2 me-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>Atur Terpisah per Fitur</span>
                                    <span class="badge bg-secondary-subtle text-muted border px-2 py-0 extra-small">Opsional</span>
                                </div>
                                <span class="badge <?= $hasAnyOverride ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?> rounded-pill px-3 py-1 extra-small" id="badge-accordion-status">
                                    <?= $hasAnyOverride ? 'Ada Fitur Diatur Terpisah' : 'Semua Fitur Mengikuti Default' ?>
                                </span>
                            </div>
                        </button>
                    </h2>
                    <div id="collapseOverrides" class="accordion-collapse collapse" aria-labelledby="headingOverrides">
                        <div class="accordion-body p-4 bg-body">
                            <div class="row g-4">
                                <!-- FEATURE 1: CHATBOT C-FIND -->
                                <div class="col-12 col-xl-6">
                                    <div class="card border rounded-4 p-4 h-100 bg-body hover-elevate transition-all shadow-sm d-flex flex-column">
                                        <!-- ONE Summary Row: Title + Status Line + Ubah/Atur Button -->
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="p-2 bg-primary text-white rounded-3 shadow-sm">
                                                    <i class="bi bi-chat-dots-fill fs-5"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-0 text-dark">Chatbot C-Find</h6>
                                                    <div class="text-muted small" id="status-line-cfind">
                                                        <span id="status-text-cfind">
                                                            <?= $assignmentCfind['is_override'] ? 'Diatur terpisah: ' : 'Mengikuti default: ' ?><strong><?= htmlspecialchars($assignmentCfind['provider_name'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($assignmentCfind['model'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 flex-shrink-0" type="button" data-bs-toggle="collapse" data-bs-target="#edit-feature-cfind" aria-expanded="false" id="btnToggleEditCfind">
                                                <i class="bi bi-pencil-square me-1"></i> Ubah / Atur
                                            </button>
                                        </div>

                                        <!-- Collapsible Edit Container -->
                                        <div class="collapse mt-3" id="edit-feature-cfind">
                                            <!-- Provider Selector Dropdown -->
                                            <div class="mb-3">
                                                <label for="feature_provider_cfind" class="form-label fw-semibold small text-secondary">
                                                    Pilih Koneksi Provider <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-hdd-network text-muted"></i></span>
                                                    <select class="form-select border-start-0 select-feature-provider" id="feature_provider_cfind" data-feature="cfind">
                                                        <?php foreach ($registeredProviders as $rpId => $rpMeta): ?>
                                                            <option value="<?= $rpId ?>" <?= $assignmentCfind['provider'] === $rpId ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($rpMeta['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($rpMeta['badge'], ENT_QUOTES, 'UTF-8') ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Dynamic Model Selector Dropdown -->
                                            <div class="mb-3">
                                                <label for="feature_model_cfind" class="form-label fw-semibold small text-secondary">
                                                    Pilih Model <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-box-seam text-muted"></i></span>
                                                    <select class="form-select border-start-0 font-monospace select-feature-model" id="feature_model_cfind" data-feature="cfind">
                                                        <?php if (!$cfindModelInCatalog && !empty($assignmentCfind['model'])): ?>
                                                            <option value="<?= htmlspecialchars($assignmentCfind['model'], ENT_QUOTES, 'UTF-8') ?>" selected>
                                                                <?= htmlspecialchars($assignmentCfind['model'], ENT_QUOTES, 'UTF-8') ?> (Model tersimpan, tidak ada di katalog)
                                                            </option>
                                                        <?php endif; ?>
                                                        <?php foreach ($cfindModels as $m): 
                                                            $mId = $m['id'] ?? '';
                                                            $isSel = ($mId === $assignmentCfind['model']);
                                                        ?>
                                                            <option value="<?= htmlspecialchars($mId, ENT_QUOTES, 'UTF-8') ?>" <?= $isSel ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($m['label'] ?? $mId, ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="alert alert-warning py-1 px-2 extra-small mt-2 mb-0 <?= $cfindModelInCatalog ? 'd-none' : '' ?>" id="warning-catalog-cfind">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Model tidak lagi tersedia di provider ini, pilih model lain.
                                                </div>
                                            </div>

                                            <!-- Feature Action Buttons -->
                                            <div class="d-flex flex-wrap gap-2 pt-3 border-top align-items-center">
                                                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 btn-save-feature" data-feature="cfind">
                                                    <span class="spinner-border spinner-border-sm d-none save-spinner" role="status" aria-hidden="true"></span>
                                                    <i class="bi bi-check2-circle save-icon me-1"></i>
                                                    <span>Simpan Penugasan C-Find</span>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 btn-revert-feature <?= $assignmentCfind['is_override'] ? '' : 'd-none' ?>" data-feature="cfind" id="btnRevertCfind">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                                    <span>Kembali ke Default</span>
                                                </button>
                                                <button type="button" class="btn btn-outline-info btn-sm rounded-pill px-3 btn-test-feature ms-auto" data-feature="cfind">
                                                    <span class="spinner-border spinner-border-sm d-none test-spinner" role="status" aria-hidden="true"></span>
                                                    <i class="bi bi-play-circle-fill test-icon me-1"></i>
                                                    <span class="test-btn-text">Tes Konfigurasi C-Find</span>
                                                </button>
                                            </div>

                                            <!-- Feature Test Feedback Result Box -->
                                            <div id="test-feedback-feature-cfind" class="mt-3 d-none">
                                                <div class="alert mb-0 py-2 px-3 small rounded-3 d-flex align-items-center gap-2" id="alert-box-feature-cfind">
                                                    <i class="bi fs-5" id="alert-icon-feature-cfind"></i>
                                                    <div class="flex-grow-1" id="alert-msg-feature-cfind"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- FEATURE 2: AI MATCHING (PENCOCOKAN AI) -->
                                <div class="col-12 col-xl-6">
                                    <div class="card border rounded-4 p-4 h-100 bg-body hover-elevate transition-all shadow-sm d-flex flex-column">
                                        <!-- ONE Summary Row: Title + Status Line + Ubah/Atur Button -->
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="p-2 bg-success text-white rounded-3 shadow-sm">
                                                    <i class="bi bi-search-heart-fill fs-5"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-0 text-dark">Pencocokan AI (AI Matching)</h6>
                                                    <div class="text-muted small" id="status-line-matching">
                                                        <span id="status-text-matching">
                                                            <?= $assignmentMatching['is_override'] ? 'Diatur terpisah: ' : 'Mengikuti default: ' ?><strong><?= htmlspecialchars($assignmentMatching['provider_name'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($assignmentMatching['model'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 flex-shrink-0" type="button" data-bs-toggle="collapse" data-bs-target="#edit-feature-matching" aria-expanded="false" id="btnToggleEditMatching">
                                                <i class="bi bi-pencil-square me-1"></i> Ubah / Atur
                                            </button>
                                        </div>

                                        <!-- Collapsible Edit Container -->
                                        <div class="collapse mt-3" id="edit-feature-matching">
                                            <!-- Provider Selector Dropdown -->
                                            <div class="mb-3">
                                                <label for="feature_provider_matching" class="form-label fw-semibold small text-secondary">
                                                    Pilih Koneksi Provider <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-hdd-network text-muted"></i></span>
                                                    <select class="form-select border-start-0 select-feature-provider" id="feature_provider_matching" data-feature="matching">
                                                        <?php foreach ($registeredProviders as $rpId => $rpMeta): ?>
                                                            <option value="<?= $rpId ?>" <?= $assignmentMatching['provider'] === $rpId ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($rpMeta['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($rpMeta['badge'], ENT_QUOTES, 'UTF-8') ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Dynamic Model Selector Dropdown -->
                                            <div class="mb-3">
                                                <label for="feature_model_matching" class="form-label fw-semibold small text-secondary">
                                                    Pilih Model <span class="text-danger">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-box-seam text-muted"></i></span>
                                                    <select class="form-select border-start-0 font-monospace select-feature-model" id="feature_model_matching" data-feature="matching">
                                                        <?php if (!$matchingModelInCatalog && !empty($assignmentMatching['model'])): ?>
                                                            <option value="<?= htmlspecialchars($assignmentMatching['model'], ENT_QUOTES, 'UTF-8') ?>" selected>
                                                                <?= htmlspecialchars($assignmentMatching['model'], ENT_QUOTES, 'UTF-8') ?> (Model tersimpan, tidak ada di katalog)
                                                            </option>
                                                        <?php endif; ?>
                                                        <?php foreach ($matchingModels as $m): 
                                                            $mId = $m['id'] ?? '';
                                                            $isSel = ($mId === $assignmentMatching['model']);
                                                        ?>
                                                            <option value="<?= htmlspecialchars($mId, ENT_QUOTES, 'UTF-8') ?>" <?= $isSel ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($m['label'] ?? $mId, ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="alert alert-warning py-1 px-2 extra-small mt-2 mb-0 <?= $matchingModelInCatalog ? 'd-none' : '' ?>" id="warning-catalog-matching">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Model tidak lagi tersedia di provider ini, pilih model lain.
                                                </div>
                                            </div>

                                            <!-- Feature Action Buttons -->
                                            <div class="d-flex flex-wrap gap-2 pt-3 border-top align-items-center">
                                                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 btn-save-feature" data-feature="matching">
                                                    <span class="spinner-border spinner-border-sm d-none save-spinner" role="status" aria-hidden="true"></span>
                                                    <i class="bi bi-check2-circle save-icon me-1"></i>
                                                    <span>Simpan Penugasan Matching</span>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 btn-revert-feature <?= $assignmentMatching['is_override'] ? '' : 'd-none' ?>" data-feature="matching" id="btnRevertMatching">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                                    <span>Kembali ke Default</span>
                                                </button>
                                                <button type="button" class="btn btn-outline-info btn-sm rounded-pill px-3 btn-test-feature ms-auto" data-feature="matching">
                                                    <span class="spinner-border spinner-border-sm d-none test-spinner" role="status" aria-hidden="true"></span>
                                                    <i class="bi bi-play-circle-fill test-icon me-1"></i>
                                                    <span class="test-btn-text">Tes Konfigurasi Matching</span>
                                                </button>
                                            </div>

                                            <!-- Feature Test Feedback Result Box -->
                                            <div id="test-feedback-feature-matching" class="mt-3 d-none">
                                                <div class="alert mb-0 py-2 px-3 small rounded-3 d-flex align-items-center gap-2" id="alert-box-feature-matching">
                                                    <i class="bi fs-5" id="alert-icon-feature-matching"></i>
                                                    <div class="flex-grow-1" id="alert-msg-feature-matching"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN SETTINGS FORM (GLOBAL PROVIDER & ROLE LIMITS) -->
    <form method="POST" action="/admin/settings" id="systemSettingsForm">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="row g-4">
            <!-- LEFT COLUMN: AI & Provider Config -->
            <div class="col-12 col-xl-7">
                <!-- Parameter Operasional C-Find (Token & Timeout) Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                    <div class="card-header bg-body border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-secondary-subtle text-secondary rounded-3">
                                <i class="bi bi-sliders fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">Parameter Operasional C-Find</h5>
                                <small class="text-muted">Batas token percakapan dan batas penantian timeout cURL</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 pt-2">
                        <!-- Max Tokens & Timeout Grid -->
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="cfind_max_output_tokens" class="form-label fw-semibold small text-secondary">
                                    Max Output Tokens
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-braces text-muted"></i></span>
                                    <input type="number" 
                                           class="form-control border-start-0" 
                                           id="cfind_max_output_tokens" 
                                           name="cfind_max_output_tokens" 
                                           value="<?= $cfindTokens ?>" 
                                           min="100" 
                                           max="4096" 
                                           required>
                                </div>
                                <div class="form-text extra-small">Batas panjang respons per giliran percakapan (default: 1024).</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="cfind_timeout_seconds" class="form-label fw-semibold small text-secondary">
                                    Timeout Koneksi (detik)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-stopwatch text-muted"></i></span>
                                    <input type="number" 
                                           class="form-control border-start-0" 
                                           id="cfind_timeout_seconds" 
                                           name="cfind_timeout_seconds" 
                                           value="<?= $cfindTimeout ?>" 
                                           min="5" 
                                           max="60" 
                                           required>
                                </div>
                                <div class="form-text extra-small">Batas maksimal penantian respons HTTP cURL (default: 15s).</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- External Notifications Card -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-body border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-info-subtle text-info rounded-3">
                                <i class="bi bi-bell-fill fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">Saluran Notifikasi Eksternal</h5>
                                <small class="text-muted">Integrasi pengiriman pesan otomatis ke saluran luar</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <!-- Primary Channel Selector -->
                        <div class="mb-3">
                            <label for="notif_external_channel" class="form-label fw-semibold small text-secondary">
                                Saluran Notifikasi Utama
                            </label>
                            <select class="form-select" id="notif_external_channel" name="notif_external_channel">
                                <option value="none" <?= $notif['channel'] === 'none' ? 'selected' : '' ?>>Nonaktif (Hanya In-App Notifications)</option>
                                <option value="email" <?= $notif['channel'] === 'email' ? 'selected' : '' ?>>Email SMTP / Provider</option>
                                <option value="webhook" <?= $notif['channel'] === 'webhook' ? 'selected' : '' ?>>Webhook HTTP Endpoint (Telegram / WhatsApp / Discord)</option>
                            </select>
                            <div class="form-text extra-small">Menentukan saluran prioritas pengiriman notifikasi penting saat klaim disetujui atau barang cocok.</div>
                        </div>

                        <!-- Switches for Email & Webhook -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <div class="p-3 border rounded-3 bg-body h-100 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold small text-dark">Layanan Email</div>
                                        <div class="extra-small text-muted">Aktifkan pengiriman via email</div>
                                    </div>
                                    <div class="form-check form-switch fs-5 mb-0">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               role="switch" 
                                               id="notif_email_status" 
                                               name="notif_email_status" 
                                               value="1" 
                                               <?= $notif['email_status'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-3 border rounded-3 bg-body h-100 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold small text-dark">Layanan Webhook</div>
                                        <div class="extra-small text-muted">Kirim event payload ke endpoint</div>
                                    </div>
                                    <div class="form-check form-switch fs-5 mb-0">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               role="switch" 
                                               id="notif_webhook_status" 
                                               name="notif_webhook_status" 
                                               value="1" 
                                               <?= $notif['webhook_status'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Webhook URL input -->
                        <div class="mb-2">
                            <label for="notif_webhook_url" class="form-label fw-semibold small text-secondary">
                                Webhook Endpoint URL
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-link-45deg text-muted"></i></span>
                                <input type="url" 
                                       class="form-control border-start-0" 
                                       id="notif_webhook_url" 
                                       name="notif_webhook_url" 
                                       value="<?= htmlspecialchars((string)$notif['webhook_url'], ENT_QUOTES, 'UTF-8') ?>" 
                                       placeholder="https://api.telegram.org/bot.../sendMessage atau webhook gateway">
                            </div>
                            <div class="form-text extra-small">URL tujuan POST payload saat ada kejadian notifikasi baru.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Chatbot C-Find Role Limits & Switches -->
            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                    <div class="card-header bg-body border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-success-subtle text-success rounded-3">
                                <i class="bi bi-chat-heart-fill fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">Batas &amp; Status Chatbot C-Find</h5>
                                <small class="text-muted">Kontrol sakelar dan kuota token per Role</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 pt-2">
                        <div class="alert alert-warning border-0 p-3 rounded-3 small mb-4 bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-info-circle me-1"></i> Matikan chatbot per role jika terjadi pemeliharaan atau lonjakan beban kuota API.
                        </div>

                        <?php foreach ($roles as $roleKey => $roleMeta): 
                            $enabledKey = "cfind_enabled_{$roleKey}";
                            $rateKey    = "cfind_rate_limit_{$roleKey}";
                            $budgetKey  = "cfind_daily_budget_{$roleKey}";

                            $isEnabled   = (bool)($settings[$enabledKey]['value'] ?? true);
                            $rateLimit   = (int)($settings[$rateKey]['value'] ?? 10);
                            $dailyBudget = (int)($settings[$budgetKey]['value'] ?? 10000);
                        ?>
                            <div class="border rounded-4 p-3 mb-3 bg-body hover-elevate transition-all">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge <?= $roleMeta['badge'] ?> rounded-pill px-3 py-1">
                                            <?= htmlspecialchars($roleMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="extra-small text-muted" id="status-label-<?= $roleKey ?>">
                                            <?= $isEnabled ? '<span class="text-success fw-bold">Aktif</span>' : '<span class="text-danger fw-bold">Nonaktif</span>' ?>
                                        </span>
                                        <div class="form-check form-switch fs-5 mb-0">
                                            <input class="form-check-input chatbot-role-toggle" 
                                                   type="checkbox" 
                                                   role="switch" 
                                                   id="<?= $enabledKey ?>" 
                                                   name="<?= $enabledKey ?>" 
                                                   value="1" 
                                                   data-role="<?= $roleKey ?>"
                                                   <?= $isEnabled ? 'checked' : '' ?>>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <label for="<?= $rateKey ?>" class="form-label extra-small fw-semibold text-secondary mb-1">
                                            Rate Limit (msg/menit)
                                        </label>
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               id="<?= $rateKey ?>" 
                                               name="<?= $rateKey ?>" 
                                               value="<?= $rateLimit ?>" 
                                               min="1" 
                                               max="120" 
                                               required>
                                    </div>
                                    <div class="col-6">
                                        <label for="<?= $budgetKey ?>" class="form-label extra-small fw-semibold text-secondary mb-1">
                                            Anggaran Token Harian
                                        </label>
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               id="<?= $budgetKey ?>" 
                                               name="<?= $budgetKey ?>" 
                                               value="<?= $dailyBudget ?>" 
                                               min="1000" 
                                               step="1000" 
                                               required>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Live Daily Budget Tracking Card & Reset Button (Prompt 29D) -->
                        <?php 
                        $cfindBudget = $cfindBudgetInfo ?? (new \App\Services\CFindService())->getDailyBudgetInfo();
                        $byRole = $cfindBudget['by_role'] ?? [];
                        ?>
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold small text-dark"><i class="bi bi-speedometer2 me-1 text-primary"></i> Penggunaan Token Hari Ini</span>
                                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2 py-1 extra-small" id="btnResetCfindBudget">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Penghitung
                                </button>
                            </div>
                            <div class="small text-muted mb-2 extra-small">
                                Tanggal: <strong><?= htmlspecialchars($cfindBudget['date'] ?? date('Y-m-d')) ?></strong> | Total: <strong id="cfind-total-tokens"><?= number_format((int)($cfindBudget['tokens_used'] ?? 0)) ?></strong> token
                            </div>
                            <div class="row g-2 extra-small" id="cfind-role-tokens-row">
                                <?php foreach ($roles as $rK => $rM): 
                                    $used = (int)($byRole[$rK] ?? 0);
                                    $bKey = "cfind_daily_budget_{$rK}";
                                    $bMax = (int)($settings[$bKey]['value'] ?? 10000);
                                    $pct = $bMax > 0 ? min(100, round(($used / $bMax) * 100)) : 0;
                                ?>
                                    <div class="col-6">
                                        <div class="p-2 border rounded-3 bg-white">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span><?= htmlspecialchars($rM['label']) ?></span>
                                                <strong id="token-val-<?= $rK ?>"><?= number_format($used) ?></strong>
                                            </div>
                                            <div class="progress" style="height: 4px;">
                                                <div class="progress-bar <?= $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-primary') ?>" id="token-bar-<?= $rK ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Action Card -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-body text-center sticky-bottom" style="bottom: 1.5rem; z-index: 10;">
                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnSaveSettings">
                            <span class="spinner-border spinner-border-sm d-none" id="saveSettingsSpinner" role="status" aria-hidden="true"></span>
                            <i class="bi bi-cloud-check-fill fs-5" id="saveSettingsIcon"></i>
                            <span id="saveSettingsText">Simpan Semua Pengaturan</span>
                        </button>
                        <small class="text-muted extra-small">
                            Perubahan akan langsung berlaku untuk semua pengguna &amp; sesi sistem tanpa perlu me-restart server.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal Tambah Custom Provider (OpenAI-Compatible) -->
<div class="modal fade" id="modalAddCustomProvider" tabindex="-1" aria-labelledby="modalAddCustomProviderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3">
                        <i class="bi bi-cpu-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold" id="modalAddCustomProviderLabel">Tambah Provider AI</h5>
                        <small class="text-muted">Koneksikan provider OpenAI-compatible baru ke Campus Find.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAddCustomProvider">
                <div class="modal-body py-4">
                    <div id="addProviderModalAlert" class="alert d-none py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2">
                        <i class="bi fs-5" id="addProviderModalAlertIcon"></i>
                        <div class="flex-grow-1" id="addProviderModalAlertMsg"></div>
                    </div>

                    <div class="mb-3">
                        <label for="custom_provider_name" class="form-label fw-semibold small">Nama Provider <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" id="custom_provider_name" name="name" placeholder="Misal: Groq, DeepSeek, Local AI" maxlength="40" required>
                        <div class="form-text extra-small">Maksimal 40 karakter, alfanumerik, spasi, dash, atau underscore.</div>
                    </div>

                    <div class="mb-3">
                        <label for="custom_provider_base_url" class="form-label fw-semibold small">Base URL (OpenAI-compatible) <span class="text-danger">*</span></label>
                        <input type="url" class="form-control font-monospace extra-small rounded-3" id="custom_provider_base_url" name="base_url" placeholder="https://api.example.com/v1" required>
                        <div class="form-text extra-small">Wajib HTTPS, port 443, tidak boleh localhost atau private/internal IP.</div>
                    </div>

                    <div class="mb-3">
                        <label for="custom_provider_api_key" class="form-label fw-semibold small">API Key <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body-tertiary border-end-0"><i class="bi bi-key text-muted"></i></span>
                            <input type="password" class="form-control border-start-0 font-monospace extra-small" id="custom_provider_api_key" name="api_key" placeholder="Masukkan API Key provider..." autocomplete="off" required>
                            <button class="btn btn-outline-secondary btn-toggle-eye" type="button" data-target="custom_provider_api_key" aria-label="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text extra-small text-muted"><i class="bi bi-shield-check text-success me-1"></i>Dienkripsi dengan aman di database.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4" id="btnSubmitAddCustomProvider">
                        <span class="spinner-border spinner-border-sm d-none submit-spinner" role="status" aria-hidden="true"></span>
                        <i class="bi bi-plus-circle me-1 submit-icon"></i>
                        <span>Simpan &amp; Uji Koneksi</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for Live Testing, Models Refresh, and Feature Model Switching -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = <?= json_encode($csrfToken) ?>;

    // Relocate modal ke document.body agar tidak terkurung dalam stacking context .cf-shell-main
    const modalAddEl = document.getElementById('modalAddCustomProvider');
    if (modalAddEl && modalAddEl.parentNode !== document.body) {
        document.body.appendChild(modalAddEl);
    }

    if (modalAddEl) {
        // Inisialisasi single Bootstrap Modal instance
        const customModalInst = bootstrap.Modal.getOrCreateInstance(modalAddEl);

        // Safety Net: Pada hidden.bs.modal, bersihkan sisa backdrop, status body, dan reset form
        modalAddEl.addEventListener('hidden.bs.modal', function () {
            const formAdd = document.getElementById('formAddCustomProvider');
            if (formAdd) {
                formAdd.reset();
            }
            resetEyeToggle('custom_provider_api_key');
            document.querySelectorAll('.modal-backdrop').forEach(function (el) {
                el.remove();
            });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        });
    }

    // Auto-expand newly added custom provider after modal creation reload
    const lastAdded = sessionStorage.getItem('last_added_provider');
    if (lastAdded) {
        const colEl = document.getElementById('collapse-provider-' + lastAdded);
        if (colEl) {
            const bsCol = bootstrap.Collapse.getOrCreateInstance(colEl, { toggle: false });
            bsCol.show();
        }
        sessionStorage.removeItem('last_added_provider');
    }

    // Toggle password/text visibility for any eye button
    document.querySelectorAll('.btn-toggle-eye').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');
            if (!targetInput) return;

            const isPassword = targetInput.type === 'password';
            targetInput.type = isPassword ? 'text' : 'password';
            if (icon) {
                icon.classList.toggle('bi-eye', !isPassword);
                icon.classList.toggle('bi-eye-slash', isPassword);
            }
            this.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        });
    });

    // Form submit state & feedback
    const systemSettingsForm = document.getElementById('systemSettingsForm');
    const btnSaveSettings = document.getElementById('btnSaveSettings');
    const saveSettingsSpinner = document.getElementById('saveSettingsSpinner');
    const saveSettingsIcon = document.getElementById('saveSettingsIcon');
    const saveSettingsText = document.getElementById('saveSettingsText');

    if (systemSettingsForm && btnSaveSettings) {
        systemSettingsForm.addEventListener('submit', function () {
            btnSaveSettings.disabled = true;
            if (saveSettingsSpinner) saveSettingsSpinner.classList.remove('d-none');
            if (saveSettingsIcon) saveSettingsIcon.classList.add('d-none');
            if (saveSettingsText) saveSettingsText.textContent = 'Menyimpan konfigurasi...';
        });
    }

    // Role toggle label reactive update
    document.querySelectorAll('.chatbot-role-toggle').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            const role = this.getAttribute('data-role');
            const labelEl = document.getElementById('status-label-' + role);
            if (labelEl) {
                labelEl.innerHTML = this.checked 
                    ? '<span class="text-success fw-bold">Aktif</span>' 
                    : '<span class="text-danger fw-bold">Nonaktif</span>';
            }
        });
    });

    // Helper: Show action alert
    function showUnifiedAlert(containerId, iconId, msgId, type, message) {
        const box = document.getElementById(containerId);
        const icon = document.getElementById(iconId);
        const msg = document.getElementById(msgId);
        if (!box || !icon || !msg) return;

        box.className = `alert alert-${type} py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2`;
        icon.className = type === 'success' ? 'bi bi-check-circle-fill text-success fs-5' : 'bi bi-exclamation-triangle-fill text-danger fs-5';
        msg.innerHTML = message;
        box.classList.remove('d-none');
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ==========================================
    // SHARED SAFE FETCH HELPER FOR AI SETTINGS
    // ==========================================

    /**
     * Shared Safe AI Fetch Helper
     * - Memeriksa status HTTP respons (response.ok)
     * - Membaca isi respons sebagai text terlebih dahulu
     * - Melakukan JSON.parse secara aman
     * - Menangkap format non-JSON dengan pesan ramah bahasa Indonesia dan mencatat ke console.error
     * - Menangani kegagalan jaringan atau abort tanpa error JS mentah
     *
     * @param {string} url
     * @param {object} options
     * @returns {Promise<{ok: boolean, status: number, data: object}>}
     */
    async function safeAiFetch(url, options = {}) {
        const defaultHeaders = {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        };
        const headers = Object.assign({}, defaultHeaders, options.headers || {});
        const fetchOpts = Object.assign({}, options, { headers: headers });

        let res;
        try {
            res = await fetch(url, fetchOpts);
        } catch (netErr) {
            console.error('[AI Fetch] Network error:', netErr);
            return {
                ok: false,
                status: 0,
                data: {
                    success: false,
                    message: 'Koneksi jaringan terputus atau permintaan gagal (' + (netErr.message || 'NetworkError') + '). Silakan periksa koneksi internet Anda.'
                }
            };
        }

        let rawText = '';
        try {
            rawText = await res.text();
        } catch (readErr) {
            console.error('[AI Fetch] Gagal membaca isi respons:', readErr);
            return {
                ok: false,
                status: res.status,
                data: {
                    success: false,
                    message: `Gagal membaca isi respons server (HTTP ${res.status}).`
                }
            };
        }

        let parsedJson = null;
        try {
            parsedJson = JSON.parse(rawText);
        } catch (jsonErr) {
            console.error(`[AI Fetch] Respons non-JSON diterima (HTTP ${res.status}):`, rawText.substring(0, 200));
            return {
                ok: false,
                status: res.status,
                data: {
                    success: false,
                    message: `Respons server tidak valid (HTTP ${res.status}): format respons bukan JSON.`
                }
            };
        }

        return {
            ok: res.ok,
            status: res.status,
            data: parsedJson
        };
    }

    // ==========================================
    // SECTION A: PROVIDER CONNECTIONS HANDLERS
    // ==========================================

    function resetEyeToggle(inputId) {
        const targetInput = document.getElementById(inputId);
        if (targetInput) {
            targetInput.type = 'password';
            const eyeBtn = document.querySelector(`.btn-toggle-eye[data-target="${inputId}"]`);
            if (eyeBtn) {
                eyeBtn.setAttribute('aria-pressed', 'false');
                const icon = eyeBtn.querySelector('i');
                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }
        }
    }

    function updateProviderCardUI(providerId, connData) {
        if (!connData) return;

        // 0. Update Nama Provider di Judul Kartu jika ada
        if (connData.name) {
            const titleEl = document.getElementById('provider-title-' + providerId);
            if (titleEl) {
                titleEl.textContent = connData.name;
            }
        }

        // 1. Kunci Aktif
        const maskedEl = document.getElementById('masked-key-' + providerId);
        if (maskedEl) {
            maskedEl.textContent = connData.key_masked || (connData.key_last4 ? ('••••••••' + connData.key_last4) : 'Belum disetel');
        }

        // 2. Sumber Kunci
        const sourceTextEl = document.getElementById('source-provider-text-' + providerId);
        if (sourceTextEl) {
            sourceTextEl.textContent = connData.source || (connData.is_db ? 'Database' : 'Belum Disetel');
        }

        // 3. Status Badge
        const badgeEl = document.getElementById('badge-provider-' + providerId);
        if (badgeEl) {
            if (connData.is_db || connData.is_configured) {
                badgeEl.className = 'badge rounded-pill px-3 py-1 extra-small bg-success-subtle text-success border border-success-subtle';
                badgeEl.innerHTML = '<i class="bi bi-database-check me-1"></i><span id="badge-provider-text-' + providerId + '">Terhubung (Database)</span>';
            } else {
                badgeEl.className = 'badge rounded-pill px-3 py-1 extra-small bg-secondary-subtle text-secondary border';
                badgeEl.innerHTML = '<i class="bi bi-dash-circle me-1"></i><span id="badge-provider-text-' + providerId + '">Belum Dikonfigurasi</span>';
            }
        }

        // 4. Tombol Hapus Key
        const delBtn = document.getElementById('btn-delete-provider-' + providerId);
        if (delBtn) {
            if (connData.is_db) {
                delBtn.classList.remove('d-none');
            } else {
                delBtn.classList.add('d-none');
            }
        }

        // 5. Update Section B jika Section B saat ini menggunakan provider ini
        const activeMaskedText = connData.key_masked || (connData.key_last4 ? ('••••••••' + connData.key_last4) : 'Belum disetel');

        // Default Assignment Card
        const defaultProv = document.getElementById('default_provider');
        if (defaultProv && defaultProv.value === providerId) {
            const defMasked = document.getElementById('default-key-masked');
            if (defMasked) defMasked.textContent = activeMaskedText;
        }

        // Feature Assignment Cards (cfind, matching)
        ['cfind', 'matching'].forEach(function(feat) {
            const featProv = document.getElementById('feature_provider_' + feat);
            if (featProv && featProv.value === providerId) {
                const featMasked = document.getElementById('feature-key-masked-' + feat);
                if (featMasked) featMasked.textContent = activeMaskedText;
            }
        });

        // 6. Sinkronkan Subtitle Counter "<N> provider terhubung"
        updateConnectedProvidersCount();
    }

    function updateConnectedProvidersCount() {
        const counterEl = document.getElementById('connected-providers-count');
        if (!counterEl) return;
        let count = 0;
        document.querySelectorAll('[id^="badge-provider-text-"]').forEach(function(el) {
            if (el.textContent && el.textContent.includes('Terhubung')) {
                count++;
            }
        });
        counterEl.textContent = count;
    }

    // Save Provider Connection API Key
    document.querySelectorAll('.btn-save-provider').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const providerId = this.getAttribute('data-provider');
            const inputKey = document.getElementById('provider_key_' + providerId);
            const inputUrl = document.getElementById('provider_url_' + providerId);
            const spinner = this.querySelector('.save-spinner');
            const icon = this.querySelector('.save-icon');
            const keyVal = inputKey ? inputKey.value.trim() : '';

            btn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('provider_id', providerId);
            const inputName = document.getElementById('provider_name_' + providerId);
            if (inputName && inputName.value.trim()) {
                payload.append('name', inputName.value.trim());
            }
            if (keyVal) {
                payload.append('api_key', keyVal);
            }
            if (inputUrl && inputUrl.value.trim()) {
                payload.append('base_url', inputUrl.value.trim());
            }

            const { ok, status, data } = await safeAiFetch('/admin/ai/provider/save', {
                method: 'POST',
                body: payload.toString()
            });

            if (data && data.success) {
                updateProviderCardUI(providerId, data);
                if (data.assignment_state) {
                    renderAiAssignmentState(data.assignment_state);
                }
                if (inputKey) {
                    inputKey.value = '';
                    resetEyeToggle('provider_key_' + providerId);
                }
                showUnifiedAlert('providerActionAlert', 'providerActionAlertIcon', 'providerActionAlertMsg', 'success', `<strong>Kredensial Tersimpan!</strong> ${data.message}`);
            } else {
                const errMsg = (data && data.message) ? data.message : `Gagal menyimpan konfigurasi provider (HTTP ${status}).`;
                showUnifiedAlert('providerActionAlert', 'providerActionAlertIcon', 'providerActionAlertMsg', 'danger', `<strong>Gagal Menyimpan:</strong> ${errMsg}`);
            }

            btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.classList.remove('d-none');
        });
    });

    // Delete Custom Provider
    document.querySelectorAll('.btn-delete-custom-provider').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const providerId = this.getAttribute('data-provider');
            const providerName = this.getAttribute('data-name') || providerId;

            const confirmed = await window.AppDialog.confirm({
                title: 'Hapus Provider Kustom',
                message: `Hapus provider "${providerName}"? Seluruh konfigurasi dan API key akan dihapus permanen dari sistem.`,
                confirmText: 'Ya, Hapus Provider',
                cancelText: 'Batal',
                variant: 'danger'
            });
            if (!confirmed) return;

            btn.disabled = true;

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('provider_id', providerId);

            const { ok, status, data } = await safeAiFetch('/admin/ai/provider/custom/delete', {
                method: 'POST',
                body: payload.toString()
            });

            if (data && data.success) {
                showUnifiedAlert('providerActionAlert', 'providerActionAlertIcon', 'providerActionAlertMsg', 'success', `<strong>Provider Dihapus:</strong> ${data.message}`);
                if (data.assignment_state) {
                    renderAiAssignmentState(data.assignment_state);
                }
                const card = document.getElementById('card-provider-' + providerId);
                if (card) {
                    card.style.transition = 'all 0.35s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        card.remove();
                        updateConnectedProvidersCount();
                    }, 350);
                }
                // Hapus option dari dropdown Section B jika ada
                document.querySelectorAll('#default_provider option, #feature_provider_cfind option, #feature_provider_matching option').forEach(function(opt) {
                    if (opt.value === providerId) {
                        const sel = opt.parentElement;
                        opt.remove();
                        if (sel && sel.value === providerId) {
                            sel.value = 'nvidia_nim';
                            sel.dispatchEvent(new Event('change'));
                        }
                    }
                });
            } else {
                const errMsg = (data && data.message) ? data.message : `Gagal menghapus provider (HTTP ${status}).`;
                showUnifiedAlert('providerActionAlert', 'providerActionAlertIcon', 'providerActionAlertMsg', 'danger', `<strong>Gagal Menghapus:</strong> ${errMsg}`);
                btn.disabled = false;
            }
        });
    });

    // Add Custom Provider Form Submit
    const formAddCustom = document.getElementById('formAddCustomProvider');
    if (formAddCustom) {
        formAddCustom.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitAddCustomProvider');
            const spinner = btn ? btn.querySelector('.submit-spinner') : null;
            const icon = btn ? btn.querySelector('.submit-icon') : null;
            const nameInput = document.getElementById('custom_provider_name');
            const urlInput = document.getElementById('custom_provider_base_url');
            const keyInput = document.getElementById('custom_provider_api_key');

            if (btn) btn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('name', nameInput ? nameInput.value.trim() : '');
            payload.append('base_url', urlInput ? urlInput.value.trim() : '');
            payload.append('api_key', keyInput ? keyInput.value.trim() : '');

            const { ok, status, data } = await safeAiFetch('/admin/ai/provider/custom/add', {
                method: 'POST',
                body: payload.toString()
            });

            if (data && data.success) {
                if (data.provider_id) {
                    sessionStorage.setItem('last_added_provider', data.provider_id);
                }
                const modalEl = document.getElementById('modalAddCustomProvider');
                if (modalEl) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.hide();
                }
                formAddCustom.reset();
                let msg = `<strong>Provider Ditambahkan!</strong> ${data.message}`;
                if (data.test && data.test.success) {
                    msg += ` (Uji Koneksi Berhasil: ${data.test.models_count || 0} model ditemukan)`;
                } else if (data.test && !data.test.success) {
                    msg += ` <span class="text-warning">(Uji koneksi awal: ${data.test.message})</span>`;
                }
                showUnifiedAlert('providerActionAlert', 'providerActionAlertIcon', 'providerActionAlertMsg', 'success', msg);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                const errMsg = (data && data.message) ? data.message : `Gagal menambahkan provider (HTTP ${status}).`;
                showUnifiedAlert('addProviderModalAlert', 'addProviderModalAlertIcon', 'addProviderModalAlertMsg', 'danger', `<strong>Gagal:</strong> ${errMsg}`);
            }

            if (btn) btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.classList.remove('d-none');
        });
    }

    // Delete Provider Key from Database
    document.querySelectorAll('.btn-delete-provider').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const providerId = this.getAttribute('data-provider');
            const inputKey = document.getElementById('provider_key_' + providerId);
            const pName = providerId === 'nvidia_nim' ? 'NVIDIA NIM' : (providerId === 'google_gemini' ? 'Google Gemini' : providerId);

            const confirmed = await window.AppDialog.confirm({
                title: 'Hapus API Key',
                message: `Hapus API key ${pName} dari database? Provider tidak akan bisa digunakan sampai key baru diisi.`,
                confirmText: 'Ya, Hapus Key',
                cancelText: 'Batal',
                variant: 'danger'
            });
            if (!confirmed) return;

            const spinner = this.querySelector('.delete-key-spinner');
            const icon = this.querySelector('.delete-key-icon');

            btn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('provider_id', providerId);

            const { ok, status, data } = await safeAiFetch('/admin/ai/provider/delete-key', {
                method: 'POST',
                body: payload.toString()
            });

            if (data && data.success) {
                updateProviderCardUI(providerId, data);
                if (data.assignment_state) {
                    renderAiAssignmentState(data.assignment_state);
                }
                if (inputKey) {
                    inputKey.value = '';
                    resetEyeToggle('provider_key_' + providerId);
                }
                showUnifiedAlert('providerActionAlert', 'providerActionAlertIcon', 'providerActionAlertMsg', 'success', `<strong>Kredensial Dihapus:</strong> ${data.message}`);
            } else {
                const errMsg = (data && data.message) ? data.message : `Gagal menghapus kunci provider (HTTP ${status}).`;
                showUnifiedAlert('providerActionAlert', 'providerActionAlertIcon', 'providerActionAlertMsg', 'danger', `<strong>Gagal Menghapus:</strong> ${errMsg}`);
            }

            btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.classList.remove('d-none');
        });
    });

    // Test Provider Connection Live
    document.querySelectorAll('.btn-test-provider').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const providerId = this.getAttribute('data-provider');
            const spinner = this.querySelector('.test-spinner');
            const icon = this.querySelector('.test-icon');
            const textEl = this.querySelector('.test-btn-text');
            const feedbackBox = document.getElementById('test-feedback-provider-' + providerId);
            const alertBox = document.getElementById('alert-box-provider-' + providerId);
            const alertIcon = document.getElementById('alert-icon-provider-' + providerId);
            const alertMsg = document.getElementById('alert-msg-provider-' + providerId);
            const inputKey = document.getElementById('provider_key_' + providerId);

            btn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');
            if (textEl) textEl.textContent = 'Menguji...';
            if (feedbackBox) feedbackBox.classList.add('d-none');

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('provider_id', providerId);
            if (inputKey && inputKey.value.trim()) {
                payload.append('api_key', inputKey.value.trim());
            }

            const { ok, status, data } = await safeAiFetch('/admin/ai/provider/test', {
                method: 'POST',
                body: payload.toString()
            });

            if (feedbackBox && alertBox && alertIcon && alertMsg) {
                feedbackBox.classList.remove('d-none');
                alertBox.classList.remove('alert-success', 'alert-danger');

                if (data && data.success) {
                    alertBox.classList.add('alert-success');
                    alertIcon.className = 'bi bi-check-circle-fill text-success fs-5';
                    let successHtml = `<strong>Koneksi Berhasil!</strong> ${data.message} Latensi: <strong>${data.duration_sec || 0}s</strong> (HTTP ${data.http_status || 200}).`;
                    if (data.reply_snippet) {
                        successHtml += ` <br><small class="text-muted font-monospace">Respons: "${data.reply_snippet}"</small>`;
                    }
                    alertMsg.innerHTML = successHtml;
                } else {
                    alertBox.classList.add('alert-danger');
                    alertIcon.className = 'bi bi-x-circle-fill text-danger fs-5';
                    const errMsg = (data && data.message) ? data.message : `Uji koneksi gagal (HTTP ${status}).`;
                    alertMsg.innerHTML = `<strong>Koneksi Gagal:</strong> ${errMsg} (${data && data.duration_sec ? data.duration_sec + 's, ' : ''}HTTP ${data && data.http_status ? data.http_status : status}).`;
                }
            }

            btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.classList.remove('d-none');
            if (textEl) textEl.textContent = 'Uji Koneksi';
        });
    });

    // Refresh Provider Models Catalog
    document.querySelectorAll('.btn-refresh-provider-models').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const providerId = this.getAttribute('data-provider');
            const spinner = this.querySelector('.refresh-spinner');
            const icon = this.querySelector('.refresh-icon');

            btn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('provider_id', providerId);

            const { ok, status, data } = await safeAiFetch('/admin/ai/models', {
                method: 'POST',
                body: payload.toString()
            });

            if (data && data.success && Array.isArray(data.models)) {
                ['cfind', 'matching'].forEach(function(feat) {
                    const selProv = document.getElementById('feature_provider_' + feat);
                    if (selProv && selProv.value === providerId) {
                        populateFeatureModels(feat, data.models);
                    }
                });
                showUnifiedAlert('providerActionAlert', 'providerActionAlertIcon', 'providerActionAlertMsg', 'success', `<strong>Katalog Model Dimuat:</strong> Berhasil memperbarui <strong>${data.count} model</strong> untuk ${providerId}.`);
            } else {
                const errMsg = (data && (data.error || data.message)) ? (data.error || data.message) : `Gagal memuat katalog model (HTTP ${status}).`;
                showUnifiedAlert('providerActionAlert', 'providerActionAlertIcon', 'providerActionAlertMsg', 'danger', `<strong>Gagal Memuat Model:</strong> ${errMsg}`);
            }

            btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.classList.remove('d-none');
        });
    });

    // ==========================================
    // SECTION B: AI FEATURE ASSIGNMENT HANDLERS (DEFAULT & OVERRIDES)
    // ==========================================

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderAiAssignmentState(state) {
        if (!state) return;

        // 1. Render Default status line
        if (state.default) {
            const statusTextDefault = document.getElementById('status-text-default');
            if (statusTextDefault) {
                statusTextDefault.textContent = (state.default.provider_name || state.default.provider_id) + ' / ' + state.default.model;
            }
        }

        // 2. Render each feature status line & revert button
        if (state.features) {
            ['cfind', 'matching'].forEach(function(feat) {
                const featState = state.features[feat];
                if (!featState) return;

                const statusTextEl = document.getElementById('status-text-' + feat);
                const revertBtn = document.getElementById(feat === 'cfind' ? 'btnRevertCfind' : 'btnRevertMatching');

                if (statusTextEl) {
                    const prefix = featState.is_override ? 'Diatur terpisah: ' : 'Mengikuti default: ';
                    statusTextEl.innerHTML = prefix + '<strong>' + escapeHtml(featState.provider_name || featState.provider_id) + ' / ' + escapeHtml(featState.model) + '</strong>';
                }

                if (revertBtn) {
                    if (featState.is_override) {
                        revertBtn.classList.remove('d-none');
                    } else {
                        revertBtn.classList.add('d-none');
                    }
                }
            });

            // 3. Render Accordion Header status badge
            const badgeAcc = document.getElementById('badge-accordion-status');
            if (badgeAcc) {
                const hasOverride = !!(state.features.cfind?.is_override || state.features.matching?.is_override);
                if (hasOverride) {
                    badgeAcc.className = 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 extra-small';
                    badgeAcc.textContent = 'Ada Fitur Diatur Terpisah';
                } else {
                    badgeAcc.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1 extra-small';
                    badgeAcc.textContent = 'Semua Fitur Mengikuti Default';
                }
            }
        }
    }

    function populateModelDropdown(selectEl, warningEl, models, selectedModel) {
        if (!selectEl) return;
        const currentVal = selectedModel || selectEl.value;
        selectEl.innerHTML = '';
        let found = false;

        models.forEach(function(m) {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.label || m.id;
            if (m.id === currentVal) {
                opt.selected = true;
                found = true;
            }
            selectEl.appendChild(opt);
        });

        if (!found && currentVal) {
            const opt = document.createElement('option');
            opt.value = currentVal;
            opt.textContent = currentVal + ' (Model tersimpan, tidak ada di katalog)';
            opt.selected = true;
            selectEl.insertBefore(opt, selectEl.firstChild);
            if (warningEl) warningEl.classList.remove('d-none');
        } else {
            if (warningEl) warningEl.classList.add('d-none');
        }

        if (!found && !currentVal && models.length > 0) {
            selectEl.selectedIndex = 0;
            if (warningEl) warningEl.classList.add('d-none');
        }
    }

    // Dynamic model loader for DEFAULT provider
    const selDefaultProvider = document.getElementById('default_provider');
    const selDefaultModel = document.getElementById('default_model');
    const warningDefaultCatalog = document.getElementById('warning-catalog-default');

    if (selDefaultProvider && selDefaultModel) {
        selDefaultProvider.addEventListener('change', async function() {
            const newProviderId = this.value;
            selDefaultModel.disabled = true;
            selDefaultModel.innerHTML = '<option value="">Memuat model...</option>';

            try {
                const res = await fetch(`/admin/ai/models?provider_id=${encodeURIComponent(newProviderId)}&feature=cfind`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success && Array.isArray(data.models)) {
                    populateModelDropdown(selDefaultModel, warningDefaultCatalog, data.models, '');
                }
            } catch (err) {
                console.error('Gagal memuat model untuk default provider:', err);
            } finally {
                selDefaultModel.disabled = false;
            }
        });

        selDefaultModel.addEventListener('change', function() {
            if (warningDefaultCatalog) {
                warningDefaultCatalog.classList.add('d-none');
            }
        });
    }

    // Dynamic model loader when feature provider changes
    document.querySelectorAll('.select-feature-provider').forEach(function(sel) {
        sel.addEventListener('change', async function() {
            const feature = this.getAttribute('data-feature');
            const newProviderId = this.value;
            const selModel = document.getElementById('feature_model_' + feature);
            const warningEl = document.getElementById('warning-catalog-' + feature);
            if (selModel) {
                selModel.disabled = true;
                selModel.innerHTML = '<option value="">Memuat model...</option>';
            }

            try {
                const res = await fetch(`/admin/ai/models?provider_id=${encodeURIComponent(newProviderId)}&feature=${encodeURIComponent(feature)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success && Array.isArray(data.models)) {
                    populateModelDropdown(selModel, warningEl, data.models, '');
                }
            } catch (err) {
                console.error('Gagal memuat model untuk provider:', err);
            } finally {
                if (selModel) selModel.disabled = false;
            }
        });
    });

    document.querySelectorAll('.select-feature-model').forEach(function(sel) {
        sel.addEventListener('change', function() {
            const feature = this.getAttribute('data-feature');
            const warningEl = document.getElementById('warning-catalog-' + feature);
            if (warningEl) warningEl.classList.add('d-none');
        });
    });

    // Save Default Assignment
    const btnSaveDefault = document.getElementById('btnSaveDefault');
    if (btnSaveDefault) {
        btnSaveDefault.addEventListener('click', async function() {
            const selProvider = document.getElementById('default_provider');
            const selModel = document.getElementById('default_model');
            const spinner = this.querySelector('.save-spinner');
            const icon = this.querySelector('.save-icon');

            if (!selProvider || !selModel) return;
            const providerId = selProvider.value;
            const modelId = selModel.value;

            this.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('provider_id', providerId);
            payload.append('model', modelId);

            const { ok, status, data } = await safeAiFetch('/admin/ai/default/save', {
                method: 'POST',
                body: payload.toString()
            });

            if (data && data.success) {
                if (data.assignment_state) {
                    renderAiAssignmentState(data.assignment_state);
                }
                showUnifiedAlert('featureActionAlert', 'featureActionAlertIcon', 'featureActionAlertMsg', 'success', `<strong>Model Default Disimpan!</strong> ${data.message}`);
            } else {
                const errMsg = (data && data.message) ? data.message : `Gagal menyimpan default (HTTP ${status}).`;
                showUnifiedAlert('featureActionAlert', 'featureActionAlertIcon', 'featureActionAlertMsg', 'danger', `<strong>Gagal Menyimpan Default:</strong> ${errMsg}`);
            }

            btnSaveDefault.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.classList.remove('d-none');
        });
    }

    // Test Default Connection Live
    const btnTestDefault = document.getElementById('btnTestDefault');
    if (btnTestDefault) {
        btnTestDefault.addEventListener('click', async function() {
            const spinner = this.querySelector('.test-spinner');
            const icon = this.querySelector('.test-icon');
            const textEl = this.querySelector('.test-btn-text');
            const feedbackBox = document.getElementById('test-feedback-default');
            const alertBox = document.getElementById('alert-box-default');
            const alertIcon = document.getElementById('alert-icon-default');
            const alertMsg = document.getElementById('alert-msg-default');

            this.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');
            if (textEl) textEl.textContent = 'Menguji...';
            if (feedbackBox) feedbackBox.classList.add('d-none');

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);

            const { ok, status, data } = await safeAiFetch('/admin/ai/default/test', {
                method: 'POST',
                body: payload.toString()
            });

            if (feedbackBox && alertBox && alertIcon && alertMsg) {
                feedbackBox.classList.remove('d-none');
                alertBox.classList.remove('alert-success', 'alert-danger');

                if (data && data.success) {
                    alertBox.classList.add('alert-success');
                    alertIcon.className = 'bi bi-check-circle-fill text-success fs-5';
                    alertMsg.innerHTML = `<strong>Uji Model Default Berhasil!</strong> ${data.message} Provider: <strong>${data.provider_name}</strong>, Model: <code>${data.model}</code>. Latensi: <strong>${data.duration_sec || 0}s</strong>. <br><small class="text-muted font-monospace">Respons: "${escapeHtml(data.snippet || '')}"</small>`;
                } else {
                    alertBox.classList.add('alert-danger');
                    alertIcon.className = 'bi bi-x-circle-fill text-danger fs-5';
                    const errMsg = (data && data.message) ? data.message : `Uji koneksi default gagal (HTTP ${status}).`;
                    alertMsg.innerHTML = `<strong>Uji Model Default Gagal:</strong> ${errMsg} (${data && data.duration_sec ? data.duration_sec + 's, ' : ''}HTTP ${data && data.http_status ? data.http_status : status}).`;
                }
            }

            btnTestDefault.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.classList.remove('d-none');
            if (textEl) textEl.textContent = 'Uji Model Default';
        });
    }

    // Save Feature Assignment (Override)
    document.querySelectorAll('.btn-save-feature').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const feature = this.getAttribute('data-feature');
            const selProvider = document.getElementById('feature_provider_' + feature);
            const selModel = document.getElementById('feature_model_' + feature);
            const spinner = this.querySelector('.save-spinner');
            const icon = this.querySelector('.save-icon');

            if (!selProvider || !selModel) return;
            const providerId = selProvider.value;
            const modelId = selModel.value;

            btn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('feature', feature);
            payload.append('provider_id', providerId);
            payload.append('model', modelId);

            const { ok, status, data } = await safeAiFetch('/admin/ai/feature/save', {
                method: 'POST',
                body: payload.toString()
            });

            if (data && data.success) {
                if (data.assignment_state) {
                    renderAiAssignmentState(data.assignment_state);
                }
                showUnifiedAlert('featureActionAlert', 'featureActionAlertIcon', 'featureActionAlertMsg', 'success', `<strong>Penugasan Disimpan!</strong> ${data.message}`);
            } else {
                const errMsg = (data && data.message) ? data.message : `Gagal menyimpan penugasan (HTTP ${status}).`;
                showUnifiedAlert('featureActionAlert', 'featureActionAlertIcon', 'featureActionAlertMsg', 'danger', `<strong>Gagal Menyimpan:</strong> ${errMsg}`);
            }

            btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.classList.remove('d-none');
        });
    });

    // Revert Feature to Default
    document.querySelectorAll('.btn-revert-feature').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const feature = this.getAttribute('data-feature');
            const featureTitle = feature === 'cfind' ? 'Chatbot C-Find' : 'Pencocokan AI';

            const confirmed = await window.AppDialog.confirm({
                title: `Kembalikan ${featureTitle} ke Default`,
                message: `Apakah Anda yakin ingin menghapus pengaturan terpisah ${featureTitle} dan mengembalikannya untuk mengikuti Model Default?`,
                confirmText: 'Kembalikan ke Default',
                cancelText: 'Batal',
                variant: 'info'
            });
            if (!confirmed) return;

            btn.disabled = true;

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('feature', feature);

            const { ok, status, data } = await safeAiFetch('/admin/ai/feature/revert-default', {
                method: 'POST',
                body: payload.toString()
            });

            if (data && data.success) {
                if (data.assignment_state) {
                    renderAiAssignmentState(data.assignment_state);
                }
                if (data.assignment) {
                    const selProvider = document.getElementById('feature_provider_' + feature);
                    const selModel = document.getElementById('feature_model_' + feature);
                    const warningEl = document.getElementById('warning-catalog-' + feature);

                    if (selProvider) selProvider.value = data.assignment.provider;
                    if (selModel) {
                        try {
                            const mRes = await fetch(`/admin/ai/models?provider_id=${encodeURIComponent(data.assignment.provider)}&feature=${encodeURIComponent(feature)}`, {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const mData = await mRes.json();
                            if (mData.success && Array.isArray(mData.models)) {
                                populateModelDropdown(selModel, warningEl, mData.models, data.assignment.model);
                            }
                        } catch (e) {
                            // ignore
                        }
                    }
                }

                showUnifiedAlert('featureActionAlert', 'featureActionAlertIcon', 'featureActionAlertMsg', 'success', `<strong>Berhasil Dikembalikan ke Default:</strong> ${data.message}`);
            } else {
                const errMsg = (data && data.message) ? data.message : `Gagal mengembalikan penugasan ke default (HTTP ${status}).`;
                showUnifiedAlert('featureActionAlert', 'featureActionAlertIcon', 'featureActionAlertMsg', 'danger', `<strong>Gagal:</strong> ${errMsg}`);
            }

            btn.disabled = false;
        });
    });

    // Test Feature Configuration Live
    document.querySelectorAll('.btn-test-feature').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const feature = this.getAttribute('data-feature');
            const spinner = this.querySelector('.test-spinner');
            const icon = this.querySelector('.test-icon');
            const textEl = this.querySelector('.test-btn-text');
            const feedbackBox = document.getElementById('test-feedback-feature-' + feature);
            const alertBox = document.getElementById('alert-box-feature-' + feature);
            const alertIcon = document.getElementById('alert-icon-feature-' + feature);
            const alertMsg = document.getElementById('alert-msg-feature-' + feature);

            btn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');
            if (textEl) textEl.textContent = 'Menguji...';
            if (feedbackBox) feedbackBox.classList.add('d-none');

            const payload = new URLSearchParams();
            payload.append('_csrf_token', csrfToken);
            payload.append('feature', feature);

            const { ok, status, data } = await safeAiFetch('/admin/ai/feature/test', {
                method: 'POST',
                body: payload.toString()
            });

            if (feedbackBox && alertBox && alertIcon && alertMsg) {
                feedbackBox.classList.remove('d-none');
                alertBox.classList.remove('alert-success', 'alert-danger');

                if (data && data.success) {
                    alertBox.classList.add('alert-success');
                    alertIcon.className = 'bi bi-check-circle-fill text-success fs-5';
                    alertMsg.innerHTML = `<strong>Uji Konfigurasi Berhasil!</strong> ${data.message} Provider: <strong>${data.provider_name}</strong>, Model: <code>${data.model}</code>. Latensi: <strong>${data.duration_sec || 0}s</strong>. <br><small class="text-muted font-monospace">Hasil: "${escapeHtml(data.snippet || (data.scores ? 'Skor Match: ' + data.scores.match + '%, Serupa: ' + data.scores.similar + '%, Berbeda: ' + data.scores.unrelated + '%' : ''))}"</small>`;
                } else {
                    alertBox.classList.add('alert-danger');
                    alertIcon.className = 'bi bi-x-circle-fill text-danger fs-5';
                    const errMsg = (data && data.message) ? data.message : `Uji konfigurasi gagal (HTTP ${status}).`;
                    alertMsg.innerHTML = `<strong>Uji Konfigurasi Gagal:</strong> ${errMsg} (${data && data.duration_sec ? data.duration_sec + 's, ' : ''}HTTP ${data && data.http_status ? data.http_status : status}).`;
                }
            }

            btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
            if (icon) icon.classList.remove('d-none');
            if (textEl) textEl.textContent = feature === 'cfind' ? 'Tes Konfigurasi C-Find' : 'Tes Konfigurasi Matching';
        });
    });

    // Handler Reset Anggaran Token C-Find (Prompt 29D)
    const btnResetBudget = document.getElementById('btnResetCfindBudget');
    if (btnResetBudget) {
        btnResetBudget.addEventListener('click', async function () {
            const confirmed = await window.AppDialog.confirm({
                title: 'Reset Anggaran Token C-Find',
                message: 'Apakah Anda yakin ingin mereset penghitung penggunaan token C-Find hari ini ke 0? Seluruh limit harian akan dikembalikan penuh.',
                confirmText: 'Reset ke 0',
                cancelText: 'Batal',
                variant: 'warning'
            });
            if (!confirmed) return;

            btnResetBudget.disabled = true;
            try {
                const res = await fetch('/admin/settings/reset-cfind-budget', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': '<?= $csrfToken ?>',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    await window.AppDialog.result({
                        title: 'Anggaran Token Direset',
                        message: data.message || 'Penghitung penggunaan token C-Find hari ini berhasil dikembalikan ke 0.',
                        variant: 'success'
                    });
                    const totalEl = document.getElementById('cfind-total-tokens');
                    if (totalEl) totalEl.textContent = '0';
                    ['guest', 'civitas', 'petugas', 'admin'].forEach(r => {
                        const valEl = document.getElementById('token-val-' + r);
                        if (valEl) valEl.textContent = '0';
                        const barEl = document.getElementById('token-bar-' + r);
                        if (barEl) barEl.style.width = '0%';
                    });
                } else {
                    await window.AppDialog.result({
                        title: 'Gagal Mereset Anggaran',
                        message: data.message || 'Sistem tidak dapat mereset kuota token C-Find.',
                        variant: 'danger'
                    });
                }
            } catch (err) {
                await window.AppDialog.result({
                    title: 'Gagal Mereset Anggaran',
                    message: 'Terjadi kesalahan sistem saat mereset anggaran: ' + err.message,
                    variant: 'danger'
                });
            } finally {
                btnResetBudget.disabled = false;
            }
        });
    }
});
</script>
