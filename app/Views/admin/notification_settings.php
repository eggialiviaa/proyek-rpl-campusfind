<?php
/**
 * View: app/Views/admin/notification_settings.php — Pengaturan Notifikasi & Antrean Outbox
 */
use App\Middleware\CsrfMiddleware;
$csrfToken = CsrfMiddleware::getToken();
$adminEmail = $user['email'] ?? '';
$appEnv = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development'));
?>

<div class="container py-4">
    <!-- Breadcrumb & Page Title -->
    <div class="row mb-4 align-items-center">
        <div class="col-12 col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb extra-small mb-2">
                    <li class="breadcrumb-item"><a href="/admin" class="text-decoration-none">Dasbor Admin</a></li>
                    <li class="breadcrumb-item"><a href="/admin/settings" class="text-decoration-none">Pengaturan Sistem</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Notifikasi Email &amp; Outbox</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-envelope-paper-heart text-primary me-2"></i>Pengaturan Notifikasi Email &amp; Outbox
            </h2>
            <p class="text-muted small mb-0">Kelola konfigurasi SMTP, kredensial App Password terenkripsi, templat pesan, dan antrean pengiriman email.</p>
        </div>
        <div class="col-12 col-md-5 text-md-end mt-3 mt-md-0 d-flex flex-wrap gap-2 justify-content-md-end align-items-center">
            <input type="hidden" id="globalCsrfToken" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" class="btn btn-primary rounded-3 btn-sm shadow-sm d-inline-flex align-items-center" id="btnRunWorker" onclick="runNotificationWorker(event)">
                <i class="bi bi-play-fill me-1"></i> Jalankan Worker
            </button>
            <?php if (!empty($stats['circuit_breaker'])): ?>
                <button type="button" class="btn btn-outline-danger rounded-3 btn-sm shadow-sm d-inline-flex align-items-center" id="btnResetCircuitTop" onclick="resetCircuitBreakerAjax(event)">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Circuit Breaker
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Production Notice Note (Requirement 5) -->
    <div class="alert alert-light border border-secondary-subtle rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 small text-muted">
            <i class="bi bi-info-circle-fill text-primary fs-5"></i>
            <div>
                <strong>Catatan Operasional:</strong> Tombol <em>"Jalankan Worker"</em> disediakan khusus untuk <strong>demo dan pengujian pengembangan</strong>. Pada lingkungan produksi, antrean outbox diproses secara terjadwal setiap menit via <strong>Windows Task Scheduler</strong> (perintah <code>cli/notifications_worker.php</code>).
            </div>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 extra-small fw-semibold">
            <i class="bi bi-clock-history me-1"></i> Produksi: Task Scheduler 1m
        </span>
    </div>

    <!-- Ringkasan Angka Bertema (Themed Summary Numbers - Requirement 2) -->
    <div id="workerSummaryContainer" class="d-none mb-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-4 border-primary">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-cpu-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Hasil Eksekusi Worker Manual</h6>
                        <p class="small text-muted mb-0" id="workerSummaryMessage">Memproses antrean...</p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="border rounded-3 px-3 py-1.5 text-center bg-success-subtle text-success border-success-subtle">
                        <div class="extra-small text-uppercase fw-bold text-success-emphasis">Terkirim</div>
                        <div class="fs-5 fw-bold" id="workerStatSent">0</div>
                    </div>
                    <div class="border rounded-3 px-3 py-1.5 text-center bg-danger-subtle text-danger border-danger-subtle">
                        <div class="extra-small text-uppercase fw-bold text-danger-emphasis">Gagal</div>
                        <div class="fs-5 fw-bold" id="workerStatFailed">0</div>
                    </div>
                    <div class="border rounded-3 px-3 py-1.5 text-center bg-warning-subtle text-warning border-warning-subtle">
                        <div class="extra-small text-uppercase fw-bold text-warning-emphasis">Diblokir</div>
                        <div class="fs-5 fw-bold" id="workerStatBlocked">0</div>
                    </div>
                    <div class="border rounded-3 px-3 py-1.5 text-center bg-secondary-subtle text-secondary border-secondary-subtle">
                        <div class="extra-small text-uppercase fw-bold text-secondary-emphasis">Dilewati / Tunda</div>
                        <div class="fs-5 fw-bold" id="workerStatSkipped">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Alerts -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                <div><?= htmlspecialchars(is_array($flashSuccess) ? implode(' ', array_filter($flashSuccess, 'is_scalar')) : (string)$flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                <div><?= htmlspecialchars(is_array($flashError) ? implode(' ', array_filter($flashError, 'is_scalar')) : (string)$flashError, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Circuit Breaker Warning Alert -->
    <?php if (!empty($stats['circuit_breaker'])): ?>
        <div class="alert alert-danger border-0 rounded-4 shadow-sm p-4 mb-4">
            <div class="d-flex align-items-start gap-3">
                <div class="fs-2 text-danger">
                    <i class="bi bi-shield-slash-fill"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold mb-1 text-danger">Circuit Breaker Notifikasi Sedang Aktif!</h5>
                    <p class="small text-danger-emphasis mb-2">
                        Sistem mendeteksi <strong><?= (int)$stats['consecutive_failures'] ?> kegagalan beruntun</strong> saat mencoba mengirim email via SMTP.
                        Pengiriman email otomatis telah ditangguhkan sementara guna mencegah penguncian akun SMTP atau spamming log.
                    </p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-danger rounded-3" onclick="testSmtpConnection()">
                            <i class="bi bi-lightning-charge me-1"></i> Tes Ulang Koneksi
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger bg-white rounded-3" onclick="resetCircuitBreakerAjax(event)">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Status Normal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 1. METRICS OVERVIEW CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-body h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-warning-subtle text-warning-emphasis rounded-3 fs-4">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <div class="extra-small text-muted text-uppercase fw-bold">Menunggu (Pending)</div>
                        <div class="fs-4 fw-bold text-dark"><?= number_format($stats['pending'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-body h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-success-subtle text-success rounded-3 fs-4">
                        <i class="bi bi-check2-all"></i>
                    </div>
                    <div>
                        <div class="extra-small text-muted text-uppercase fw-bold">Terkirim (Sent)</div>
                        <div class="fs-4 fw-bold text-dark"><?= number_format($stats['sent'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-body h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-danger-subtle text-danger rounded-3 fs-4">
                        <i class="bi bi-x-octagon"></i>
                    </div>
                    <div>
                        <div class="extra-small text-muted text-uppercase fw-bold">Gagal (Failed)</div>
                        <div class="fs-4 fw-bold text-dark"><?= number_format($stats['failed'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-body h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-secondary-subtle text-secondary-emphasis rounded-3 fs-4">
                        <i class="bi bi-skip-forward-circle"></i>
                    </div>
                    <div>
                        <div class="extra-small text-muted text-uppercase fw-bold">Dilewati (Skipped)</div>
                        <div class="fs-4 fw-bold text-dark"><?= number_format($stats['skipped'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-body h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-info-subtle text-info rounded-3 fs-4">
                        <i class="bi bi-speedometer2"></i>
                    </div>
                    <div>
                        <div class="extra-small text-muted text-uppercase fw-bold">Kuota Harian</div>
                        <div class="fs-4 fw-bold text-dark">
                            <?= number_format($stats['sent_today'] ?? 0) ?> <span class="fs-6 text-muted fw-normal">/ <?= (int)($stats['daily_limit'] ?? 100) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. MAIN CONFIGURATION FORM -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-body border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3">
                    <i class="bi bi-gear-wide-connected fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Konfigurasi SMTP &amp; Pengaman</h5>
                    <small class="text-muted">Kredensial disimpan terenkripsi (AES-256-CBC) dan diaudit tanpa membocorkan rahasia</small>
                </div>
            </div>
        </div>
        <div class="card-body p-4 pt-2">
            <form method="POST" action="/admin/notification-settings" id="formNotificationSettings">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Toggles: Enable Email & Channel -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <div class="p-3 border rounded-3 bg-body h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold small text-dark">Layanan Email Eksternal</div>
                                <div class="extra-small text-muted">Aktifkan pengiriman email outbox oleh worker</div>
                            </div>
                            <div class="form-check form-switch fs-4 mb-0 ms-3">
                                <input class="form-check-input cursor-pointer" 
                                       type="checkbox" 
                                       role="switch" 
                                       id="notif_email_status" 
                                       name="notif_email_status" 
                                       value="1" 
                                       <?= !empty($settings['notif_email_status']['value']) ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="p-3 border rounded-3 bg-body h-100">
                            <label for="notif_external_channel" class="fw-bold small text-dark mb-1 d-block">
                                Saluran Prioritas Eksternal
                            </label>
                            <select class="form-select form-select-sm" id="notif_external_channel" name="notif_external_channel">
                                <option value="email" <?= ($settings['notif_external_channel']['value'] ?? '') === 'email' ? 'selected' : '' ?>>Email SMTP (Aktif)</option>
                                <option value="none" <?= ($settings['notif_external_channel']['value'] ?? '') === 'none' ? 'selected' : '' ?>>Nonaktif (Hanya In-App Notifications)</option>
                            </select>
                            <div class="extra-small text-muted mt-1">Saluran lain (misal WhatsApp) tidak aktif saat ini.</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 border rounded-3 bg-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold small text-dark">Email Laporan Kehilangan Baru ke Petugas</div>
                                <div class="extra-small text-muted">Kirimkan notifikasi email ke Petugas saat ada laporan kehilangan baru (bawaan: nonaktif, Petugas hanya menerima di aplikasi)</div>
                            </div>
                            <div class="form-check form-switch fs-4 mb-0 ms-3">
                                <input class="form-check-input cursor-pointer" 
                                       type="checkbox" 
                                       role="switch" 
                                       id="notif_email_new_lost_report" 
                                       name="notif_email_new_lost_report" 
                                       value="1" 
                                       <?= !empty($settings['notif_email_new_lost_report']['value']) ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark small text-uppercase mb-3">
                    <i class="bi bi-server me-1"></i> Parameter Server SMTP
                </h6>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-5">
                        <label for="notif_smtp_host" class="form-label small fw-semibold">SMTP Host</label>
                        <input type="text" class="form-control" id="notif_smtp_host" name="notif_smtp_host" 
                               value="<?= htmlspecialchars((string)($settings['notif_smtp_host']['value'] ?? 'smtp.gmail.com'), ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="smtp.gmail.com" required>
                        <div class="extra-small text-muted mt-1">Contoh: <code>smtp.gmail.com</code> atau host relay kampus.</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="notif_smtp_port" class="form-label small fw-semibold">SMTP Port</label>
                        <input type="number" class="form-control" id="notif_smtp_port" name="notif_smtp_port" 
                               value="<?= htmlspecialchars((string)($settings['notif_smtp_port']['value'] ?? '587'), ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="587" required>
                        <div class="extra-small text-muted mt-1">Port <code>587</code> (STARTTLS) atau <code>465</code> (SSL).</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="notif_smtp_encryption" class="form-label small fw-semibold">Enkripsi Koneksi</label>
                        <select class="form-select" id="notif_smtp_encryption" name="notif_smtp_encryption">
                            <?php $enc = strtolower((string)($settings['notif_smtp_encryption']['value'] ?? 'tls')); ?>
                            <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>STARTTLS (Rekomendasi Port 587)</option>
                            <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL / SMTPS (Port 465)</option>
                            <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>Tanpa Enkripsi (Testing Port 25)</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="notif_smtp_username" class="form-label small fw-semibold">SMTP Username / Email Akun</label>
                        <input type="text" class="form-control" id="notif_smtp_username" name="notif_smtp_username" 
                               value="<?= htmlspecialchars((string)($settings['notif_smtp_username']['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="admin@gmail.com atau kampus@ac.id">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="notif_smtp_password" class="form-label small fw-semibold d-flex justify-content-between">
                            <span>SMTP App Password (Terkunci)</span>
                            <?php if (!empty($settings['notif_smtp_password']['is_set'])): ?>
                                <span class="badge bg-success-subtle text-success extra-small">Tersimpan: <?= htmlspecialchars($settings['notif_smtp_password']['value'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </label>
                        <input type="password" class="form-control" id="notif_smtp_password" name="notif_smtp_password" 
                               placeholder="<?= !empty($settings['notif_smtp_password']['is_set']) ? 'Kosongkan jika tidak ingin mengubah password' : 'Masukkan 16-digit App Password Gmail' ?>" autocomplete="new-password">
                        <div class="extra-small text-muted mt-1">Gunakan App Password 16 karakter dari Google Account jika memakai Gmail.</div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="notif_smtp_from_name" class="form-label small fw-semibold">Nama Pengirim (From Name)</label>
                        <input type="text" class="form-control" id="notif_smtp_from_name" name="notif_smtp_from_name" 
                               value="<?= htmlspecialchars((string)($settings['notif_smtp_from_name']['value'] ?? 'Campus Find L&F'), ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="Campus Find L&F">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="notif_smtp_from_address" class="form-label small fw-semibold">Alamat Email Pengirim (From Email)</label>
                        <input type="email" class="form-control" id="notif_smtp_from_address" name="notif_smtp_from_address" 
                               value="<?= htmlspecialchars((string)($settings['notif_smtp_from_address']['value'] ?? 'no-reply@campusfind.ac.id'), ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="no-reply@campusfind.ac.id">
                    </div>
                </div>

                <!-- Alert Peringatan Gmail SMTP (Requirement 2) -->
                <div id="gmailWarningAlert" class="alert alert-warning py-2.5 px-3 rounded-3 small mb-4 d-none border-warning-subtle">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0"></i>
                        <div>
                            <strong>Peringatan Gmail SMTP:</strong> Anda menggunakan host <code>smtp.gmail.com</code>, namun <em>Alamat Email Pengirim (From Email)</em> berbeda dari <em>SMTP Username</em>. Gmail umumnya menolak pengiriman atau secara otomatis menimpa (overwrite) alamat pengirim dengan akun Google yang diautentikasi (SMTP Username). Disarankan agar From Email disamakan dengan SMTP Username.
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark small text-uppercase mb-3">
                    <i class="bi bi-shield-check me-1"></i> Kebijakan Pengaman &amp; Kuota
                </h6>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label for="notif_daily_limit" class="form-label small fw-semibold">Batas Kuota Harian (Emails/Hari)</label>
                        <input type="number" class="form-control" id="notif_daily_limit" name="notif_daily_limit" 
                               value="<?= htmlspecialchars((string)($settings['notif_daily_limit']['value'] ?? '100'), ENT_QUOTES, 'UTF-8') ?>" 
                               min="10" max="1000" required>
                        <div class="extra-small text-muted mt-1">Default 100 email. Antrean setelah batas ditunda ke hari esok (tidak dibuang).</div>
                    </div>
                    <div class="col-12 col-md-8">
                        <label for="notif_allowlist" class="form-label small fw-semibold">NOTIFY_ALLOWLIST (Lingkungan Non-Produksi)</label>
                        <input type="text" class="form-control" id="notif_allowlist" name="notif_allowlist" 
                               value="<?= htmlspecialchars((string)($settings['notif_allowlist']['value'] ?? 'admin@campusfind.test'), ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="admin@campusfind.test, petugas@campusfind.test, *@campusfind.test">
                        <div class="extra-small text-muted mt-1">Daftar email yang diizinkan (pisahkan koma). Mencegah pengiriman ke mahasiswa sungguhan saat demo.</div>
                    </div>
                </div>

                <!-- Panel Uji Coba Pengiriman & Koneksi SMTP (Requirement 1 & 3) -->
                <div class="p-3 bg-light-subtle rounded-3 border mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                        <label for="testEmailRecipient" class="form-label small fw-bold mb-0 text-dark">
                            <i class="bi bi-send-check text-primary me-1"></i> Uji Pengiriman Email:
                        </label>
                        <!-- (3) Indikator Realtime Allowlist di dekat tombol -->
                        <div id="allowlistStatusBadge">
                            <span class="badge bg-secondary-subtle text-secondary extra-small">
                                <i class="bi bi-hourglass-split me-1"></i> Memeriksa status allowlist...
                            </span>
                        </div>
                    </div>

                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-7">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="testEmailRecipient" name="test_target_email" 
                                       value="<?= htmlspecialchars($defaultTestEmail ?? $adminEmail, ENT_QUOTES, 'UTF-8') ?>" 
                                       placeholder="admin@campusfind.test" 
                                       title="Alamat email penerima uji coba (default data Admin dari database)">
                            </div>
                            <div class="form-text extra-small text-muted mt-1">
                                <i class="bi bi-info-circle text-primary me-1"></i>
                                <strong>Tentang NOTIFY_ALLOWLIST:</strong> Di luar lingkungan produksi (mis. <code>development</code> atau <code>staging</code>), pengiriman dibatasi hanya ke penerima dalam daftar allowlist agar tidak membocorkan email ke pihak luar selama pengujian sistem.
                            </div>
                            <div id="allowlistFeedback" class="extra-small mt-1 text-muted"></div>
                        </div>
                        <div class="col-12 col-md-5 d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-3 flex-grow-1" id="btnSendTestEmail" onclick="sendTestEmail()">
                                <i class="bi bi-send me-1"></i> Kirim Email Uji
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" id="btnTestConnection" onclick="testSmtpConnection()" title="Tes Koneksi Port & Handshake SMTP">
                                <i class="bi bi-lightning me-1"></i> Tes Koneksi
                            </button>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center pt-3 border-top">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i> Simpan konfigurasi jika Anda baru saja mengubah pengaturan di atas.
                    </small>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm">
                        <i class="bi bi-save me-1"></i> Simpan Konfigurasi Notifikasi
                    </button>
                </div>
            </form>

            <!-- Test Connection Results Container -->
            <div id="testResultBox" class="mt-3 d-none"></div>
        </div>
    </div>

    <!-- 3. TEMPLATE MANAGEMENT -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-body border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-success-subtle text-success rounded-3">
                    <i class="bi bi-file-earmark-text-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Templat Pesan Email Notifikasi</h5>
                    <small class="text-muted">Isi pesan dibuat ringkas, informatif, dan tidak memuat data pribadi/sensitif</small>
                </div>
            </div>
        </div>
        <div class="card-body p-4 pt-2">
            <div class="accordion" id="accordionTemplates">
                <?php foreach ($templates as $idx => $tpl): ?>
                    <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                        <h2 class="accordion-header" id="headingTpl<?= $idx ?>">
                            <button class="accordion-button collapsed py-2.5 px-3 bg-light-subtle" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTpl<?= $idx ?>" aria-expanded="false" aria-controls="collapseTpl<?= $idx ?>">
                                <div class="d-flex align-items-center gap-2 w-100">
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill font-monospace"><?= htmlspecialchars($tpl['template_key'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="fw-bold text-dark small"><?= htmlspecialchars($tpl['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapseTpl<?= $idx ?>" class="accordion-collapse collapse" aria-labelledby="headingTpl<?= $idx ?>" data-bs-parent="#accordionTemplates">
                            <div class="accordion-body p-3">
                                <form method="POST" action="/admin/notification-settings">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Subject Email</label>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="templates[<?= $tpl['template_key'] ?>][subject]" 
                                               value="<?= htmlspecialchars($tpl['subject'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Konten Body HTML</label>
                                        <textarea class="form-control form-control-sm font-monospace" rows="4" 
                                                  name="templates[<?= $tpl['template_key'] ?>][body_html]" required><?= htmlspecialchars($tpl['body_html'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                        <?php
                                        $tplDef = \App\Services\Notifications\NotificationTemplateService::getDefinition($tpl['template_key']);
                                        $placeholders = $tplDef['placeholders'] ?? [];
                                        $aliases = $tplDef['aliases'] ?? [];
                                        ?>
                                        <div class="p-2.5 bg-light rounded-3 border mt-2">
                                            <div class="extra-small fw-bold text-dark mb-1.5 d-flex align-items-center gap-1">
                                                <i class="bi bi-braces text-primary"></i> Daftar Placeholder Sah &amp; Variabel yang Tersedia:
                                            </div>
                                            <div class="d-flex flex-wrap gap-1.5">
                                                <?php foreach ($placeholders as $ph => $phDesc): ?>
                                                    <span class="badge bg-white text-dark border extra-small font-monospace py-1 px-2" title="<?= htmlspecialchars($phDesc, ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="text-primary fw-bold">{{<?= $ph ?>}}</span>
                                                        <span class="text-muted fw-normal ms-1">(<?= htmlspecialchars($phDesc, ENT_QUOTES, 'UTF-8') ?>)</span>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php foreach ($aliases as $alias => $target): ?>
                                                    <span class="badge bg-white text-secondary border extra-small font-monospace py-1 px-2" title="Alias resmi untuk {{<?= $target ?>}}">
                                                        <span class="text-secondary fw-semibold">{{<?= $alias ?>}}</span>
                                                        <span class="text-muted fw-normal ms-1">(alias {{<?= $target ?>}})</span>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="extra-small text-muted mt-1.5">
                                                <i class="bi bi-shield-check text-success me-1"></i> Mendukung format <code>{{variabel}}</code> dan <code>{variabel}</code>. Variabel di luar daftar sah di atas akan otomatis ditolak demi keamanan pengiriman.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-3">
                                            <i class="bi bi-check2 me-1"></i> Simpan Templat Ini
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 4. OUTBOX QUEUE TABLE -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-body border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0"><i class="bi bi-inbox-fill text-primary me-2"></i>Antrean Notifikasi Outbox</h5>
                <small class="text-muted">Total antrean: <strong><?= number_format($totalOutbox) ?> data</strong></small>
            </div>
            <!-- Status Filter Pills -->
            <div class="d-flex gap-1 flex-wrap">
                <a href="/admin/notification-settings" class="btn btn-sm rounded-pill px-3 <?= empty($statusFilter) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Semua
                </a>
                <a href="/admin/notification-settings?status=pending" class="btn btn-sm rounded-pill px-3 <?= $statusFilter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?>">
                    Pending
                </a>
                <a href="/admin/notification-settings?status=sent" class="btn btn-sm rounded-pill px-3 <?= $statusFilter === 'sent' ? 'btn-success' : 'btn-outline-success' ?>">
                    Sent
                </a>
                <a href="/admin/notification-settings?status=failed" class="btn btn-sm rounded-pill px-3 <?= $statusFilter === 'failed' ? 'btn-danger' : 'btn-outline-danger' ?>">
                    Failed
                </a>
                <a href="/admin/notification-settings?status=cancelled" class="btn btn-sm rounded-pill px-3 <?= $statusFilter === 'cancelled' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                    Cancelled
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($outboxList)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    <p class="mb-0">Tidak ada antrean notifikasi <?= !empty($statusFilter) ? "dengan status <strong>{$statusFilter}</strong>" : "" ?>.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Penerima</th>
                                <th>Templat</th>
                                <th>Status</th>
                                <th>Percobaan</th>
                                <th>Waktu Terkirim / Jadwal</th>
                                <th>Alasan Error</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outboxList as $row): 
                                $statusBadge = match ($row['status']) {
                                    'sent'      => 'bg-success-subtle text-success border border-success-subtle',
                                    'pending'   => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                    'sending'   => 'bg-info-subtle text-info border border-info-subtle',
                                    'failed'    => 'bg-danger-subtle text-danger border border-danger-subtle',
                                    'cancelled' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                    default     => 'bg-light text-dark',
                                };
                            ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-secondary">#<?= $row['id'] ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($row['user_name'] ?? 'Pengguna #' . $row['user_id'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="extra-small text-muted"><?= htmlspecialchars($row['recipient'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace extra-small"><?= htmlspecialchars($row['template_key'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $statusBadge ?> rounded-pill px-2.5 py-1">
                                            <?= strtoupper($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= (int)$row['attempts'] ?></span> / 3
                                    </td>
                                    <td class="extra-small">
                                        <?php if (!empty($row['sent_at'])): ?>
                                            <span class="text-success"><i class="bi bi-check-circle me-1"></i><?= date('d/m/Y H:i', strtotime($row['sent_at'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="bi bi-clock me-1"></i>Next: <?= date('d/m/Y H:i', strtotime($row['next_try_at'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="extra-small text-muted" style="max-width: 250px;">
                                        <?php if (!empty($row['error_reason'])): ?>
                                            <span class="text-danger d-inline-block text-truncate" style="max-width: 240px;" title="<?= htmlspecialchars($row['error_reason'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($row['error_reason'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if (in_array($row['status'], ['failed', 'cancelled'], true)): ?>
                                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2.5 py-0.5 extra-small" title="Kirim Ulang" onclick="retryOutboxAjax(<?= (int)$row['id'] ?>, event)">
                                                <i class="bi bi-arrow-repeat me-1"></i>Kirim Ulang
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="p-3 border-top d-flex justify-content-between align-items-center">
                        <small class="text-muted">Halaman <?= $currentPage ?> dari <?= $totalPages ?></small>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="/admin/notification-settings?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>">&laquo;</a>
                                </li>
                            <?php endif; ?>
                            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="/admin/notification-settings?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="/admin/notification-settings?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>">&raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. JEJAK KEPUTUSAN NOTIFIKASI (DECISION LOGS) -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" id="decision-log">
        <div class="card-header bg-body border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-info-subtle text-info rounded-3">
                    <i class="bi bi-diagram-3-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Jejak Keputusan Notifikasi</h5>
                    <small class="text-muted">Hasil evaluasi setiap pemicu peristiwa: apakah diantrekan (menunggu/terkirim) atau dilewati beserta alasannya</small>
                </div>
            </div>
            <div class="d-flex gap-1 flex-wrap align-items-center">
                <?php $curDecFilter = $_GET['decision_filter'] ?? ''; ?>
                <a href="/admin/notification-settings#decision-log" class="btn btn-sm rounded-pill px-3 <?= empty($curDecFilter) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Semua
                </a>
                <a href="/admin/notification-settings?decision_filter=queued#decision-log" class="btn btn-sm rounded-pill px-3 <?= $curDecFilter === 'queued' ? 'btn-success' : 'btn-outline-success' ?>">
                    <i class="bi bi-hourglass-split me-1"></i>Diantre / Menunggu
                </a>
                <a href="/admin/notification-settings?decision_filter=skipped#decision-log" class="btn btn-sm rounded-pill px-3 <?= $curDecFilter === 'skipped' ? 'btn-danger' : 'btn-outline-danger' ?>">
                    <i class="bi bi-slash-circle me-1"></i>Dilewati (<?= number_format($stats['skipped'] ?? 0) ?>)
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($decisions)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-clipboard-check fs-1 d-block mb-2 text-secondary"></i>
                    Belum ada jejak evaluasi keputusan notifikasi yang tercatat <?= !empty($curDecFilter) ? "dengan filter <strong>{$curDecFilter}</strong>" : "" ?>.
                </div>
            <?php else: ?>
                <div class="table-responsive cf-table-wrapper">
                    <table class="table table-hover align-middle mb-0 extra-small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Waktu</th>
                                <th>Peristiwa (Event)</th>
                                <th>Penerima</th>
                                <th>Keputusan &amp; Status</th>
                                <th>Alasan Keputusan</th>
                                <th class="pe-4 text-end">Saluran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($decisions as $d): ?>
                                <tr>
                                    <td class="ps-4 text-muted text-nowrap"><?= date('d M Y H:i:s', strtotime($d['created_at'])) ?></td>
                                    <td>
                                        <span class="cf-badge cf-badge-neutral font-monospace">
                                            <?= htmlspecialchars($d['event_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($d['recipient_email'] ?: '-') ?></div>
                                        <?php if (!empty($d['user_name'])): ?>
                                            <div class="extra-small text-muted"><?= htmlspecialchars($d['user_name']) ?> (<?= htmlspecialchars($d['role_name'] ?? '') ?>)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($d['decision'] === 'queued'): ?>
                                            <?php 
                                             $outboxStatus = $d['outbox_status'] ?? null;
                                            if ($outboxStatus === 'sent'): ?>
                                                <span class="cf-badge cf-badge-found">
                                                    <i class="bi bi-check2-circle me-1"></i>Diantre: Terkirim
                                                </span>
                                            <?php elseif ($outboxStatus === 'pending'): ?>
                                                <span class="cf-badge cf-badge-warning">
                                                    <i class="bi bi-hourglass-split me-1"></i>Diantre: Menunggu
                                                </span>
                                            <?php elseif ($outboxStatus === 'failed'): ?>
                                                <span class="cf-badge cf-badge-danger">
                                                    <i class="bi bi-x-octagon me-1"></i>Diantre: Gagal
                                                </span>
                                            <?php else: ?>
                                                <span class="cf-badge cf-badge-found">
                                                    <i class="bi bi-check-circle me-1"></i>Diantre
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="cf-badge cf-badge-danger">
                                                <i class="bi bi-slash-circle me-1"></i>Dilewati
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="<?= $d['decision'] === 'skipped' ? 'text-danger fw-semibold' : 'text-success-emphasis' ?>">
                                            <?= htmlspecialchars($d['reason']) ?>
                                        </div>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <span class="cf-badge cf-badge-neutral"><?= htmlspecialchars(strtoupper($d['channel'])) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 6. PANEL KESIAPAN PENERIMA NOTIFIKASI -->
    <div class="cf-card overflow-hidden mb-4" id="readiness-panel">
        <div class="card-header bg-body border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-success-subtle text-success rounded-3">
                    <i class="bi bi-person-check-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Panel Kesiapan Penerima Notifikasi</h5>
                    <small class="text-muted">Status akun: verifikasi email, persetujuan opt-in, allowlist, dan alasan bila belum siap menerima email</small>
                </div>
            </div>
            <div class="extra-small text-muted">
                Lingkungan: <span class="cf-badge cf-badge-neutral"><?= strtoupper($appEnv) ?></span>
            </div>
        </div>
        <div class="p-3 bg-light border-bottom extra-small text-muted">
            <i class="bi bi-info-circle-fill text-primary me-1"></i>
            <strong>Kriteria Kesiapan Penerima (Prompt 34C):</strong> Di luar production, email diizinkan jika: <strong>(a)</strong> ada di <code>NOTIFY_ALLOWLIST</code> (alasan: <em>allowlist</em>), ATAU <strong>(b)</strong> email sudah terverifikasi lewat OTP (bukan penandaan manual) DAN pengguna sudah opt-in (alasan: <em>terverifikasi OTP + opt-in</em>). Di production, allowlist tidak berlaku namun syarat terverifikasi OTP + opt-in bersifat mutlak. Domain reservasi (<code>.test</code>, <code>.example</code>, dll) diblokir permanen di semua lingkungan.
        </div>
        <div class="card-body p-0">
            <?php if (empty($readinessList)): ?>
                <div class="p-4 text-center text-muted">
                    Tidak ada akun pengguna aktif yang ditemukan.
                </div>
            <?php else: ?>
                <div class="table-responsive cf-table-wrapper">
                    <table class="table table-hover align-middle mb-0 extra-small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Pengguna</th>
                                <th>Role</th>
                                <th>Email (Disamarkan)</th>
                                <th class="text-center">Verifikasi Email</th>
                                <th class="text-center">Opt-In?</th>
                                <th class="text-center">Allowlist?</th>
                                <th>Status Kesiapan &amp; Alasan</th>
                                <th class="pe-4 text-center">Aksi Kasus Khusus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readinessList as $r): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark"><?= htmlspecialchars($r['name']) ?></td>
                                    <td>
                                        <span class="cf-badge cf-badge-neutral"><?= htmlspecialchars(ucfirst($r['role_name'])) ?></span>
                                    </td>
                                    <td><code><?= htmlspecialchars($r['email_masked']) ?></code></td>
                                    <td class="text-center">
                                        <?php if (!empty($r['is_otp_verified'])): ?>
                                            <span class="cf-badge cf-badge-found">
                                                <i class="bi bi-shield-check me-1"></i>OTP
                                            </span>
                                        <?php elseif (!empty($r['is_manual_verified'])): ?>
                                            <span class="cf-badge cf-badge-warning" title="Hanya ditandai manual oleh Admin (bukan verifikasi OTP)">
                                                <i class="bi bi-person-gear me-1"></i>Manual (Bukan OTP)
                                            </span>
                                        <?php else: ?>
                                            <span class="cf-badge cf-badge-danger">
                                                <i class="bi bi-x-circle me-1"></i>Belum
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($r['has_opt_in']): ?>
                                            <span class="text-success"><i class="bi bi-check-circle-fill"></i> Ya</span>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="bi bi-slash-circle"></i> Tidak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($appEnv === 'production'): ?>
                                            <span class="text-muted">N/A (Prod)</span>
                                        <?php elseif ($r['is_allowed']): ?>
                                            <span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Ya</span>
                                        <?php else: ?>
                                            <span class="text-warning"><i class="bi bi-shield-x"></i> Tidak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['is_ready']): ?>
                                            <div class="d-flex flex-wrap align-items-center gap-1">
                                                <span class="cf-badge cf-badge-found">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Siap Menerima Email
                                                </span>
                                                <?php if ($r['readiness_reason'] === 'allowlist'): ?>
                                                    <span class="cf-badge cf-badge-neutral">
                                                        <i class="bi bi-bookmark-check me-1"></i>allowlist
                                                    </span>
                                                <?php elseif ($r['readiness_reason'] === 'terverifikasi OTP + opt-in'): ?>
                                                    <span class="cf-badge cf-badge-found">
                                                        <i class="bi bi-shield-lock-fill me-1"></i>terverifikasi OTP + opt-in
                                                    </span>
                                                <?php elseif (!empty($r['readiness_reason'])): ?>
                                                    <span class="cf-badge cf-badge-neutral">
                                                        <?= htmlspecialchars($r['readiness_reason']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="cf-badge cf-badge-warning mb-1">
                                                <i class="bi bi-exclamation-triangle me-1"></i>Belum Siap
                                            </span>
                                            <div class="extra-small text-danger">
                                                <?= htmlspecialchars(implode(', ', $r['unready_reasons'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <?php if (!empty($r['can_add_to_allowlist'])): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary btn-add-allowlist py-1 px-2.5 rounded-pill extra-small"
                                                    data-user-id="<?= $r['id'] ?>"
                                                    data-user-name="<?= htmlspecialchars($r['name']) ?>"
                                                    data-user-email="<?= htmlspecialchars($r['email_masked']) ?>">
                                                <i class="bi bi-plus-circle me-1"></i>+ Allowlist
                                            </button>
                                        <?php elseif (!empty($r['is_reserved'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill extra-small">
                                                <i class="bi bi-ban me-1"></i>Dilarang (.test)
                                            </span>
                                        <?php elseif (!empty($r['is_allowed'])): ?>
                                            <span class="text-success extra-small">
                                                <i class="bi bi-check2-all me-1"></i>Terdaftar
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted extra-small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function getCsrfToken() {
    return document.getElementById('globalCsrfToken')?.value
        || document.querySelector('input[name="_csrf_token"]')?.value
        || document.querySelector('input[name="csrf_token"]')?.value
        || '';
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

// 1. Eksekusi Worker Manual (AJAX + AppToast + Ringkasan Angka Bertema)
async function runNotificationWorker(e) {
    if (e) e.preventDefault();
    const btn = document.getElementById('btnRunWorker');
    if (!btn) return;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menjalankan...';

    const csrfToken = getCsrfToken();
    const formData = new FormData();
    formData.append('_csrf_token', csrfToken);
    formData.append('batch_size', '20');

    try {
        const res = await fetch('/admin/notification-settings/process-now', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        const contentType = res.headers.get('content-type') || '';
        let data = null;
        if (contentType.includes('application/json')) {
            data = await res.json();
        } else {
            throw new Error(res.status === 403
                ? 'Sesi Anda telah kedaluwarsa atau token CSRF tidak valid. Silakan muat ulang halaman.'
                : 'Server mengembalikan respons yang tidak terduga.');
        }

        if (res.ok && data.ok) {
            displayWorkerSummary(data);
            if (window.AppToast) {
                AppToast.success(data.message || 'Worker berhasil dijalankan.');
            }
        } else {
            const errMsg = data?.message || (res.status === 429
                ? 'Terlalu banyak permintaan. Harap tunggu beberapa detik sebelum menjalankan kembali.'
                : 'Gagal memproses antrean notifikasi.');
            if (window.AppToast) {
                AppToast.error(errMsg);
            }
        }
    } catch (err) {
        const msg = err.message || 'Terjadi kesalahan jaringan saat memproses worker.';
        if (window.AppToast) {
            AppToast.error(msg);
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

function displayWorkerSummary(data) {
    const container = document.getElementById('workerSummaryContainer');
    if (!container) return;
    container.classList.remove('d-none');

    const msgEl = document.getElementById('workerSummaryMessage');
    const sentEl = document.getElementById('workerStatSent');
    const failedEl = document.getElementById('workerStatFailed');
    const blockedEl = document.getElementById('workerStatBlocked');
    const skippedEl = document.getElementById('workerStatSkipped');

    if (msgEl) msgEl.textContent = data.message || 'Pemrosesan antrean selesai.';
    if (sentEl) sentEl.textContent = data.sent ?? 0;
    if (failedEl) failedEl.textContent = data.failed ?? 0;
    if (blockedEl) blockedEl.textContent = data.blocked ?? 0;
    if (skippedEl) skippedEl.textContent = data.skipped ?? 0;

    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// 2. Reset Circuit Breaker via AJAX
async function resetCircuitBreakerAjax(e) {
    if (e) e.preventDefault();
    const btn = e.target.closest('button');
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mereset...';
    }

    const formData = new FormData();
    formData.append('_csrf_token', getCsrfToken());

    try {
        const res = await fetch('/admin/notification-settings/reset-circuit', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const contentType = res.headers.get('content-type') || '';
        let data = null;
        if (contentType.includes('application/json')) {
            data = await res.json();
        } else {
            throw new Error(res.status === 403 ? 'Sesi telah kedaluwarsa. Silakan muat ulang.' : 'Gagal mereset circuit breaker.');
        }

        if (res.ok && data.ok) {
            if (window.AppToast) {
                AppToast.success(data.message || 'Circuit breaker berhasil di-reset.');
            }
            setTimeout(() => window.location.reload(), 1200);
        } else {
            if (window.AppToast) {
                AppToast.error(data?.message || 'Gagal mereset circuit breaker.');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    } catch (err) {
        if (window.AppToast) {
            AppToast.error(err.message || 'Terjadi kesalahan jaringan.');
        }
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
}

// 3. Retry Outbox Item via AJAX
async function retryOutboxAjax(id, e) {
    if (e) e.preventDefault();
    const btn = e.target.closest('button') || e.target;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const formData = new FormData();
    formData.append('_csrf_token', getCsrfToken());

    try {
        const res = await fetch('/admin/notification-settings/retry/' + id, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const contentType = res.headers.get('content-type') || '';
        let data = null;
        if (contentType.includes('application/json')) {
            data = await res.json();
        } else {
            throw new Error(res.status === 403 ? 'Sesi telah kedaluwarsa. Silakan muat ulang.' : 'Gagal menjadwalkan ulang item.');
        }

        if (res.ok && data.ok) {
            if (window.AppToast) {
                AppToast.success(data.message || 'Item berhasil dijadwalkan ulang.');
            }
            setTimeout(() => window.location.reload(), 1000);
        } else {
            if (window.AppToast) {
                AppToast.error(data?.message || 'Gagal menjadwalkan ulang item.');
            }
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        if (window.AppToast) {
            AppToast.error(err.message || 'Terjadi kesalahan jaringan.');
        }
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

// 4. Uji Koneksi Live SMTP (AJAX)
async function testSmtpConnection() {
    const btn = document.getElementById('btnTestConnection');
    const box = document.getElementById('testResultBox');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menguji Koneksi...';
    box.classList.remove('d-none');
    box.innerHTML = '<div class="alert alert-info py-2 small mb-0"><span class="spinner-border spinner-border-sm me-1"></span> Menguji handshake dan autentikasi dengan server SMTP...</div>';

    const form = document.getElementById('formNotificationSettings');
    const formData = new FormData(form);

    try {
        const res = await fetch('/admin/notification-settings/test-connection', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const contentType = res.headers.get('content-type') || '';
        let data = null;
        if (contentType.includes('application/json')) {
            data = await res.json();
        } else {
            throw new Error(res.status === 403 ? 'Sesi Anda telah kedaluwarsa. Silakan muat ulang.' : 'Respons server tidak valid.');
        }

        if (data.success || data.ok) {
            const msg = data.message || 'Koneksi SMTP berhasil diverifikasi.';
            box.innerHTML = `
                <div class="alert alert-success py-2 small mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                    <div>${escapeHtml(msg)}</div>
                </div>
            `;
            if (window.AppToast) {
                AppToast.success(msg);
            }
        } else {
            const errMsg = data.message || data.error || 'Koneksi SMTP gagal.';
            box.innerHTML = `
                <div class="alert alert-danger py-2 small mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                    <div>${escapeHtml(errMsg)}</div>
                </div>
            `;
            if (window.AppToast) {
                AppToast.error(errMsg);
            }
        }
    } catch (err) {
        const errText = err.message || 'Terjadi kesalahan jaringan saat menguji koneksi.';
        box.innerHTML = `
            <div class="alert alert-danger py-2 small mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                <div>${escapeHtml(errText)}</div>
            </div>
        `;
        if (window.AppToast) {
            AppToast.error(errText);
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// 5. Pengecekan Peringatan Gmail SMTP (Requirement 2)
function checkGmailWarning() {
    const hostEl = document.getElementById('notif_smtp_host');
    const userEl = document.getElementById('notif_smtp_username');
    const fromEl = document.getElementById('notif_smtp_from_address');
    const warnEl = document.getElementById('gmailWarningAlert');
    if (!hostEl || !userEl || !fromEl || !warnEl) return;

    const host = (hostEl.value || '').trim().toLowerCase();
    const user = (userEl.value || '').trim().toLowerCase();
    const from = (fromEl.value || '').trim().toLowerCase();

    const isGmail = host.includes('smtp.gmail.com') || host === 'smtp.gmail.com';
    const isDifferent = user !== '' && from !== '' && user !== from;

    if (isGmail && isDifferent) {
        warnEl.classList.remove('d-none');
    } else {
        warnEl.classList.add('d-none');
    }
}

// 6. Pengecekan Realtime NOTIFY_ALLOWLIST (Requirement 3)
const appEnvironment = '<?= htmlspecialchars($appEnv, ENT_QUOTES, 'UTF-8') ?>';

function checkAllowlistStatus() {
    const emailInput = document.getElementById('testEmailRecipient');
    const allowlistInput = document.getElementById('notif_allowlist');
    const badge = document.getElementById('allowlistStatusBadge');
    const feedback = document.getElementById('allowlistFeedback');
    if (!emailInput || !badge) return;

    const email = (emailInput.value || '').trim().toLowerCase();

    if (appEnvironment === 'production') {
        badge.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-shield-check me-1"></i> Allowlist Nonaktif (Mode Produksi)</span>';
        if (feedback) feedback.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i> Semua alamat email tujuan diizinkan pada lingkungan produksi.</span>';
        return;
    }

    if (!email) {
        badge.innerHTML = '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-dash-circle me-1"></i> Masukkan email tujuan</span>';
        if (feedback) feedback.innerHTML = '<span class="text-muted">Ketikkan alamat email tujuan untuk memeriksa status allowlist.</span>';
        return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        badge.innerHTML = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-exclamation-circle me-1"></i> Format Email Tidak Valid</span>';
        if (feedback) feedback.innerHTML = '<span class="text-warning">Format email belum valid. Mohon periksa kembali.</span>';
        return;
    }

    const allowlistVal = allowlistInput ? allowlistInput.value : '';
    const patterns = allowlistVal.toLowerCase().split(/[,\s;]+/).map(s => s.trim()).filter(Boolean);

    let isAllowed = false;
    for (const pat of patterns) {
        if (pat === email) {
            isAllowed = true;
            break;
        }
        if (pat.startsWith('*@')) {
            const domain = pat.substring(2);
            if (email.endsWith('@' + domain)) {
                isAllowed = true;
                break;
            }
        }
    }

    if (isAllowed) {
        badge.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-shield-check me-1"></i> Terdaftar di Allowlist (Diizinkan)</span>';
        if (feedback) feedback.innerHTML = '<span class="text-success"><i class="bi bi-check2-circle me-1"></i> Email <strong>' + escapeHtml(email) + '</strong> diizinkan untuk menerima pengiriman uji coba di lingkungan ' + escapeHtml(appEnvironment) + '.</span>';
    } else {
        badge.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-shield-slash me-1"></i> Di luar Allowlist (Akan Diblokir)</span>';
        if (feedback) feedback.innerHTML = '<span class="text-danger"><i class="bi bi-shield-x me-1"></i> Email <strong>' + escapeHtml(email) + '</strong> tidak ada dalam NOTIFY_ALLOWLIST dan akan diblokir oleh pengaman sistem.</span>';
    }
}

// 7. Kirim Email Uji Coba (AJAX - Requirement 1)
async function sendTestEmail() {
    const btn = document.getElementById('btnSendTestEmail');
    const box = document.getElementById('testResultBox');
    const recipientInput = document.getElementById('testEmailRecipient');
    const recipient = (recipientInput?.value || '').trim();
    const originalText = btn.innerHTML;

    if (!recipient) {
        if (window.AppToast) {
            AppToast.error('Masukkan alamat email tujuan pengujian.');
        }
        recipientInput?.focus();
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim Email Uji...';
    box.classList.remove('d-none');
    box.innerHTML = `<div class="alert alert-info py-2 small mb-0"><span class="spinner-border spinner-border-sm me-1"></span> Mengirimkan email uji coba ke <strong>${escapeHtml(recipient)}</strong>...</div>`;

    const csrfToken = getCsrfToken();
    const formData = new FormData();
    formData.append('_csrf_token', csrfToken);
    formData.append('csrf_token', csrfToken);
    formData.append('target_email', recipient);

    try {
        const res = await fetch('/admin/notification-settings/test-email', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const contentType = res.headers.get('content-type') || '';
        let data = null;
        if (contentType.includes('application/json')) {
            data = await res.json();
        } else {
            throw new Error(res.status === 403 ? 'Sesi Anda telah kedaluwarsa. Silakan muat ulang.' : 'Respons server tidak valid.');
        }

        if (data.success || data.ok) {
            const msg = data.message || `Email uji berhasil dikirimkan ke ${recipient}.`;
            let warningMarkup = '';
            if (data.warning) {
                warningMarkup = `
                    <div class="alert alert-warning py-2 small mt-2 mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-warning flex-shrink-0"></i>
                        <div>${escapeHtml(data.warning)}</div>
                    </div>
                `;
            }
            box.innerHTML = `
                <div class="alert alert-success py-2 small mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-5 flex-shrink-0"></i>
                    <div><strong>Sukses!</strong> ${escapeHtml(msg)} Silakan periksa folder Inbox (atau Spam).</div>
                </div>
                ${warningMarkup}
            `;
            if (window.AppToast) {
                AppToast.success(msg);
            }
        } else if (data.blocked) {
            // (1) Bila diblokir oleh NOTIFY_ALLOWLIST, tampilkan alasannya dengan jelas
            const errMsg = data.message || `Alamat email ${recipient} diblokir oleh NOTIFY_ALLOWLIST.`;
            const reasonMsg = data.reason || 'Alamat email tidak terdaftar dalam NOTIFY_ALLOWLIST pada lingkungan non-produksi.';
            box.innerHTML = `
                <div class="alert alert-warning py-3 px-3 rounded-3 mb-0 border-warning-subtle">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-shield-slash-fill text-danger fs-4 flex-shrink-0"></i>
                        <div>
                            <h6 class="fw-bold text-dark mb-1">Pengiriman Email Uji Diblokir oleh NOTIFY_ALLOWLIST</h6>
                            <p class="small text-danger mb-2">${escapeHtml(errMsg)}</p>
                            <div class="extra-small text-muted bg-white p-2.5 rounded border border-warning-subtle">
                                <div class="fw-semibold text-dark mb-1"><i class="bi bi-info-circle me-1"></i> Alasan &amp; Tindakan:</div>
                                <div>${escapeHtml(reasonMsg)}</div>
                                <div class="mt-1">Untuk mengizinkannya, tambahkan alamat ini atau wildcard domain (contoh: <code>*@domain.com</code>) ke formulir <em>NOTIFY_ALLOWLIST</em> di atas lalu klik <strong>Simpan Konfigurasi Notifikasi</strong>.</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            if (window.AppToast) {
                AppToast.error('Pengiriman diblokir oleh NOTIFY_ALLOWLIST');
            }
        } else {
            const errMsg = data.message || data.error || 'Pengiriman email uji gagal.';
            box.innerHTML = `
                <div class="alert alert-danger py-2 small mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5 flex-shrink-0"></i>
                    <div><strong>Pengiriman Gagal:</strong> ${escapeHtml(errMsg)}</div>
                </div>
            `;
            if (window.AppToast) {
                AppToast.error(errMsg);
            }
        }
    } catch (err) {
        const errText = err.message || 'Terjadi kesalahan jaringan saat mengirim email uji.';
        box.innerHTML = `
            <div class="alert alert-danger py-2 small mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-5 flex-shrink-0"></i>
                <div>${escapeHtml(errText)}</div>
            </div>
        `;
        if (window.AppToast) {
            AppToast.error(errText);
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Inisialisasi event listeners saat halaman dimuat
document.addEventListener('DOMContentLoaded', () => {
    // Listener untuk Gmail warning (Requirement 2)
    const hostEl = document.getElementById('notif_smtp_host');
    const userEl = document.getElementById('notif_smtp_username');
    const fromEl = document.getElementById('notif_smtp_from_address');
    [hostEl, userEl, fromEl].forEach(el => {
        if (el) {
            el.addEventListener('input', checkGmailWarning);
            el.addEventListener('change', checkGmailWarning);
        }
    });
    checkGmailWarning();

    // Listener untuk Allowlist indicator (Requirement 3)
    const targetEmailEl = document.getElementById('testEmailRecipient');
    const allowlistEl = document.getElementById('notif_allowlist');
    if (targetEmailEl) {
        targetEmailEl.addEventListener('input', checkAllowlistStatus);
        targetEmailEl.addEventListener('change', checkAllowlistStatus);
    }
    if (allowlistEl) {
        allowlistEl.addEventListener('input', checkAllowlistStatus);
        allowlistEl.addEventListener('change', checkAllowlistStatus);
    }
    checkAllowlistStatus();

    // Prompt 34C: Event listener untuk tombol "+ Allowlist" per akun di Panel Kesiapan Penerima
    document.querySelectorAll('.btn-add-allowlist').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name') || 'Pengguna';
            const userEmail = this.getAttribute('data-user-email') || '';

            let confirmed = false;
            if (window.AppDialog && typeof window.AppDialog.confirm === 'function') {
                confirmed = await window.AppDialog.confirm({
                    title: 'Tambah ke NOTIFY_ALLOWLIST?',
                    message: `Apakah Anda yakin ingin menambahkan alamat email ${userEmail} milik ${userName} ke NOTIFY_ALLOWLIST untuk kasus khusus? Tindakan ini akan dicatat dalam Audit Log sistem.`,
                    confirmText: 'Ya, Tambahkan ke Allowlist',
                    confirmVariant: 'primary'
                });
            } else {
                confirmed = true;
            }

            if (!confirmed) {
                return;
            }

            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menambahkan...';

            try {
                const response = await fetch('/admin/notification-settings/allowlist/add-user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: '_csrf_token=' + encodeURIComponent(getCsrfToken()) + '&user_id=' + encodeURIComponent(userId),
                });

                const resData = await response.json();

                if (response.ok && resData.success) {
                    if (window.AppDialog && typeof window.AppDialog.result === 'function') {
                        await window.AppDialog.result({
                            title: 'Berhasil Ditambahkan',
                            message: resData.message || 'Alamat email berhasil ditambahkan ke NOTIFY_ALLOWLIST.',
                            variant: 'success'
                        });
                    } else if (window.AppToast && typeof window.AppToast.show === 'function') {
                        window.AppToast.show(resData.message, 'success');
                    }
                    window.location.reload();
                } else {
                    const errMsg = resData.message || 'Gagal menambahkan email ke NOTIFY_ALLOWLIST.';
                    if (window.AppDialog && typeof window.AppDialog.result === 'function') {
                        await window.AppDialog.result({
                            title: 'Gagal Menambahkan',
                            message: errMsg,
                            variant: 'danger'
                        });
                    } else if (window.AppToast && typeof window.AppToast.show === 'function') {
                        window.AppToast.show(errMsg, 'error');
                    }
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                }
            } catch (err) {
                const errText = err.message || 'Terjadi kesalahan jaringan saat menambahkan email ke allowlist.';
                if (window.AppDialog && typeof window.AppDialog.result === 'function') {
                    await window.AppDialog.result({
                        title: 'Kesalahan Sistem',
                        message: errText,
                        variant: 'danger'
                    });
                } else if (window.AppToast && typeof window.AppToast.show === 'function') {
                    window.AppToast.show(errText, 'error');
                }
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });
    });
});
</script>
