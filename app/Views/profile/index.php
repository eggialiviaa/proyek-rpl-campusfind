<?php
/**
 * View: app/Views/profile/index.php — Halaman Profil Pengguna & Preferensi Notifikasi (PAGE-01.2C.2)
 * Liquid Glass Composition: Compact Identity Capsule & Segmented Tabs
 */
use App\Middleware\CsrfMiddleware;
$csrfToken = CsrfMiddleware::getToken();
$isVerified = !empty($user['email_verified_at']);
$hasOptIn = (int)($user['email_notifications_enabled'] ?? 0) === 1;

// Deteksi tab aktif awal jika sedang dalam alur verifikasi email
$defaultActiveTab = (!empty($emailChangeInProgress) || !empty($emailVerificationInProgress)) ? 'kontak' : 'identitas';
?>

<div class="container-fluid py-4 px-3 px-md-4 cf-profile-container">
    <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
    <?php
    $userRole = strtolower(trim((string)($user['role_name'] ?? $user['role'] ?? 'civitas')));
    $roleEyebrow = match ($userRole) {
        'admin'   => 'Akun Administrator',
        'petugas' => 'Akun Petugas Posko',
        default   => 'Akun Civitas',
    };
    $homeUrl = ($userRole === 'admin') ? '/admin' : '/dashboard';
    $homeLabel = ($userRole === 'admin') ? 'Admin Panel' : 'Dashboard';

    $headerConfig = [
        'eyebrow'     => $roleEyebrow,
        'title'       => 'Profil & Preferensi Akun',
        'icon'        => 'bi bi-person-gear',
        'description' => 'Kelola informasi identitas, kontak resmi, keamanan kata sandi, dan persetujuan notifikasi email.',
        'breadcrumbs' => [
            ['label' => $homeLabel, 'url' => $homeUrl],
            ['label' => 'Profil Pengguna'],
        ],
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Flash Alerts -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm text-center py-3 mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                <span class="fw-semibold"><?= htmlspecialchars(is_array($flashSuccess) ? implode(' ', array_filter($flashSuccess, 'is_scalar')) : (string)$flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4 shadow-sm text-center py-3 mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                <span class="fw-semibold"><?= htmlspecialchars(is_array($flashError) ? implode(' ', array_filter($flashError, 'is_scalar')) : (string)$flashError, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Compact Identity Summary Capsule (PAGE-01.2C.2) -->
    <div class="cf-profile-identity-capsule">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="cf-profile-avatar">
                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h2 class="h5 fw-bold text-dark mb-0"><?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                            <?= htmlspecialchars($user['role_label'] ?? ucfirst($user['role_name'] ?? 'Civitas'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if ($isVerified): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                                <i data-lucide="check-circle-2" class="me-1" style="width: 13px; height: 13px;"></i>Email Terverifikasi
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                                <i data-lucide="clock" class="me-1" style="width: 13px; height: 13px;"></i>Email Belum Terverifikasi
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small d-flex align-items-center gap-2 flex-wrap" style="font-size: 0.82rem;">
                        <span><i data-lucide="id-card" class="me-1" style="width: 14px; height: 14px;"></i>NIM: <strong class="text-dark"><?= htmlspecialchars($user['identity_number'] ?: '-', ENT_QUOTES, 'UTF-8') ?></strong></span>
                        <?php if (!empty($user['study_program'])): ?>
                            <span>&bull;</span>
                            <span><i data-lucide="graduation-cap" class="me-1" style="width: 14px; height: 14px;"></i><strong class="text-dark"><?= htmlspecialchars($user['study_program'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($user['batch_year'])): ?>
                            <span>&bull;</span>
                            <span><i data-lucide="calendar" class="me-1" style="width: 14px; height: 14px;"></i>Angkatan <strong class="text-dark"><?= htmlspecialchars($user['batch_year'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="text-end text-muted small ms-auto d-none d-md-block" style="font-size: 0.76rem;">
                <div><i data-lucide="calendar" class="me-1" style="width: 13px; height: 13px;"></i>Terdaftar sejak</div>
                <div class="fw-semibold text-dark"><?= date('d F Y', strtotime($user['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Segmented Glass Navigation Tabs (PAGE-01.2C.2) -->
    <div class="cf-segmented-tabs" role="tablist" aria-label="Navigasi Pengaturan Akun">
        <button type="button" 
                class="cf-segmented-tab-btn<?= $defaultActiveTab === 'identitas' ? ' active' : '' ?>" 
                data-tab="tab-identitas" 
                role="tab" 
                aria-selected="<?= $defaultActiveTab === 'identitas' ? 'true' : 'false' ?>" 
                aria-controls="tab-identitas">
            <i data-lucide="user" class="me-2" style="width: 16px; height: 16px;"></i>Data Identitas
        </button>
        <button type="button" 
                class="cf-segmented-tab-btn<?= $defaultActiveTab === 'kontak' ? ' active' : '' ?>" 
                data-tab="tab-kontak" 
                role="tab" 
                aria-selected="<?= $defaultActiveTab === 'kontak' ? 'true' : 'false' ?>" 
                aria-controls="tab-kontak">
            <i data-lucide="mail" class="me-2" style="width: 16px; height: 16px;"></i>Kontak &amp; Email
        </button>
        <button type="button" 
                class="cf-segmented-tab-btn<?= $defaultActiveTab === 'keamanan' ? ' active' : '' ?>" 
                data-tab="tab-keamanan" 
                role="tab" 
                aria-selected="<?= $defaultActiveTab === 'keamanan' ? 'true' : 'false' ?>" 
                aria-controls="tab-keamanan">
            <i data-lucide="shield-check" class="me-2" style="width: 16px; height: 16px;"></i>Keamanan Sandi
        </button>
        <button type="button" 
                class="cf-segmented-tab-btn<?= $defaultActiveTab === 'notifikasi' ? ' active' : '' ?>" 
                data-tab="tab-notifikasi" 
                role="tab" 
                aria-selected="<?= $defaultActiveTab === 'notifikasi' ? 'true' : 'false' ?>" 
                aria-controls="tab-notifikasi">
            <i data-lucide="bell" class="me-2" style="width: 16px; height: 16px;"></i>Preferensi Notifikasi
        </button>
    </div>

    <!-- TAB 1: DATA IDENTITAS KAMPUS -->
    <div class="cf-tab-pane<?= $defaultActiveTab === 'identitas' ? ' active' : '' ?>" id="tab-identitas" role="tabpanel">
        <div class="cf-card p-4">
            <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom border-secondary border-opacity-10">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
                        <i data-lucide="user-check" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div>
                        <h3 class="h6 fw-bold mb-0 text-dark">Data Direktori Akademik</h3>
                        <div class="text-muted small" style="font-size: 0.78rem;">Informasi identitas resmi civitas terdaftar</div>
                    </div>
                </div>
                <span class="cf-badge cf-badge-neutral"><i data-lucide="lock" class="me-1" style="width: 13px; height: 13px;"></i>Hanya-baca Terverifikasi</span>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-light bg-opacity-60 rounded-3 border border-secondary border-opacity-10">
                        <div class="text-muted extra-small mb-1"><i data-lucide="user" class="me-1" style="width: 13px; height: 13px;"></i>Nama Lengkap</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-light bg-opacity-60 rounded-3 border border-secondary border-opacity-10">
                        <div class="text-muted extra-small mb-1"><i data-lucide="id-card" class="me-1" style="width: 13px; height: 13px;"></i>Nomor Identitas (NIM)</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($user['identity_number'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-light bg-opacity-60 rounded-3 border border-secondary border-opacity-10">
                        <div class="text-muted extra-small mb-1"><i data-lucide="graduation-cap" class="me-1" style="width: 13px; height: 13px;"></i>Program Studi</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($user['study_program'] ?: 'Belum diatur', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-light bg-opacity-60 rounded-3 border border-secondary border-opacity-10">
                        <div class="text-muted extra-small mb-1"><i data-lucide="calendar" class="me-1" style="width: 13px; height: 13px;"></i>Tahun Angkatan</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($user['batch_year'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-light bg-opacity-60 rounded-3 border border-secondary border-opacity-10">
                        <div class="text-muted extra-small mb-1"><i data-lucide="shield" class="me-1" style="width: 13px; height: 13px;"></i>Peran Pengguna</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($user['role_label'] ?? ucfirst($user['role_name'] ?? 'Civitas'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-light bg-opacity-60 rounded-3 border border-secondary border-opacity-10">
                        <div class="text-muted extra-small mb-1"><i data-lucide="clock" class="me-1" style="width: 13px; height: 13px;"></i>Waktu Pendaftaran</div>
                        <div class="fw-bold text-dark"><?= date('d F Y, H:i', strtotime($user['created_at'])) ?> WIB</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 p-3 bg-info bg-opacity-10 border border-info border-opacity-25 rounded-3 d-flex align-items-start gap-2">
                <i data-lucide="info" class="text-info mt-0.5" style="width: 16px; height: 16px;"></i>
                <div class="small text-muted" style="font-size: 0.80rem;">
                    Data nama, NIM, dan program studi disinkronkan secara otomatis dari sistem informasi akademik universitas. Jika terdapat kekeliruan data, silakan hubungi bagian registrasi kampus atau Petugas posko.
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: KONTAK & EMAIL -->
    <div class="cf-tab-pane<?= $defaultActiveTab === 'kontak' ? ' active' : '' ?>" id="tab-kontak" role="tabpanel">
        <div class="row g-4">
            <!-- Left: WhatsApp & Email Status -->
            <div class="col-12 col-lg-6">
                <!-- Kelola Nomor WhatsApp -->
                <div class="cf-card p-4 mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="rounded-circle p-2 bg-success bg-opacity-10 text-success">
                            <i data-lucide="phone" style="width: 20px; height: 20px;"></i>
                        </div>
                        <div>
                            <h3 class="h6 fw-bold mb-0 text-dark">Nomor WhatsApp / Telepon</h3>
                            <div class="text-muted small" style="font-size: 0.78rem;">Saluran kontak darurat penyerahan barang</div>
                        </div>
                    </div>

                    <form method="POST" action="/profile/phone">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-dark">Nomor Aktif</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i data-lucide="phone" style="width: 15px; height: 15px;"></i></span>
                                <input type="text" 
                                       name="phone" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       placeholder="08123456789 atau 628123456789">
                                <button type="submit" class="btn btn-primary px-3">
                                    <i data-lucide="check" class="me-1" style="width: 15px; height: 15px;"></i>Simpan
                                </button>
                            </div>
                        </div>
                        <div class="form-text extra-small text-muted">
                            Nomor otomatis dinormalisasi ke format standar internasional (contoh: 628...).
                        </div>
                    </form>
                </div>

                <!-- Status Email Saat Ini & OTP Verifikasi -->
                <div class="cf-card p-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
                            <i data-lucide="mail" style="width: 20px; height: 20px;"></i>
                        </div>
                        <div>
                            <h3 class="h6 fw-bold mb-0 text-dark">Alamat Email Terdaftar</h3>
                            <div class="text-muted small" style="font-size: 0.78rem;">Status verifikasi alamat email akun</div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <div class="text-muted extra-small">Email Utama</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div>
                            <?php if ($isVerified): ?>
                                <span class="cf-badge cf-badge-found">
                                    <i data-lucide="check-circle-2" class="me-1" style="width: 13px; height: 13px;"></i>Terverifikasi
                                </span>
                            <?php else: ?>
                                <span class="cf-badge cf-badge-warning">
                                    <i data-lucide="clock" class="me-1" style="width: 13px; height: 13px;"></i>Belum Terverifikasi
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$isVerified && empty($emailVerificationInProgress)): ?>
                        <div class="alert alert-warning border-0 rounded-3 small p-3 mb-0">
                            <p class="mb-2">Alamat email Anda belum diverifikasi. Lakukan verifikasi untuk mengaktifkan notifikasi eksternal.</p>
                            <form method="POST" action="/profile/email/verify/request-otp">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn-warning btn-sm rounded-pill px-3 fw-semibold">
                                    <i data-lucide="send" class="me-1" style="width: 14px; height: 14px;"></i>Kirim Kode OTP Verifikasi
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isVerified && !empty($emailVerificationInProgress)): ?>
                        <div class="p-3 bg-primary-subtle border border-primary-subtle rounded-3">
                            <h6 class="fw-bold mb-1 text-primary-emphasis extra-small">
                                <i data-lucide="shield-check" class="me-1" style="width: 14px; height: 14px;"></i>Masukkan Kode OTP Verifikasi
                            </h6>
                            <p class="extra-small text-muted mb-2">
                                Kode 6 digit telah dikirim ke <strong><?= htmlspecialchars($emailVerificationInProgress['email_masked'] ?? '') ?></strong> (berlaku 5 menit).
                            </p>
                            <form method="POST" action="/profile/email/verify/confirm">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="input-group input-group-sm mb-2">
                                    <input type="text" name="otp_code" class="form-control rounded-start-3 text-center fw-bold font-monospace" placeholder="123456" maxlength="6" pattern="[0-9]{6}" required autocomplete="one-time-code">
                                    <button type="submit" class="btn btn-primary px-3 rounded-end-3">
                                        <i data-lucide="check-circle-2" class="me-1" style="width: 14px; height: 14px;"></i>Verifikasi
                                    </button>
                                </div>
                            </form>
                            <div class="d-flex justify-content-between align-items-center extra-small mt-1">
                                <form method="POST" action="/profile/email/verify/request-otp" class="d-inline">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-link p-0 extra-small text-decoration-none">
                                        <i data-lucide="rotate-ccw" class="me-1" style="width: 12px; height: 12px;"></i>Kirim Ulang OTP
                                    </button>
                                </form>
                                <form method="POST" action="/profile/email/verify/cancel" class="d-inline">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-link p-0 extra-small text-danger text-decoration-none">
                                        Batal
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Ubah Alamat Email Mandiri via OTP -->
            <div class="col-12 col-lg-6">
                <div class="cf-card p-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="rounded-circle p-2 bg-info bg-opacity-10 text-info">
                            <i data-lucide="mail" style="width: 20px; height: 20px;"></i>
                        </div>
                        <div>
                            <h3 class="h6 fw-bold mb-0 text-dark">Ubah Alamat Email Mandiri</h3>
                            <div class="text-muted small" style="font-size: 0.78rem;">Email baru berlaku setelah diverifikasi via kode OTP</div>
                        </div>
                    </div>

                    <?php if (!empty($emailChangeInProgress)): ?>
                        <div class="p-3 bg-info-subtle border border-info-subtle rounded-3 mb-3">
                            <div class="alert alert-info border-0 rounded-3 small mb-3">
                                <i data-lucide="info" class="me-1" style="width: 14px; height: 14px;"></i>
                                Kode OTP 6 digit telah dikirimkan ke <strong><?= htmlspecialchars($emailChangeInProgress['email_masked']) ?></strong> (berlaku 5 menit).
                            </div>
                            <form method="POST" action="/profile/email/verify-otp">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-dark">Masukkan 6 Digit Kode OTP</label>
                                    <input type="text" name="otp_code" class="form-control text-center font-monospace fs-4 rounded-3" maxlength="6" placeholder="------" required autofocus>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary rounded-3">
                                        <i data-lucide="check-circle-2" class="me-1" style="width: 15px; height: 15px;"></i>Verifikasi &amp; Terapkan Email Baru
                                    </button>
                                </div>
                            </form>
                            <form method="POST" action="/profile/email/cancel" class="mt-2 text-center">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn-link btn-sm text-muted text-decoration-none extra-small">
                                    Batal Ubah Email
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="/profile/email/request-otp">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-dark">Alamat Email Baru <span class="text-danger">*</span></label>
                                <input type="email" name="new_email" class="form-control" placeholder="emailbaru@kampus.ac.id" required>
                                <div class="form-text extra-small text-muted">Kode OTP 6 digit akan dikirimkan ke alamat email baru ini.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-dark">Konfirmasi Kata Sandi Saat Ini <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" placeholder="Masukkan kata sandi akun Anda" required>
                                <div class="form-text extra-small text-muted">Dibutuhkan untuk memastikan keamanan kepemilikan akun.</div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-outline-primary rounded-pill px-4">
                                    <i data-lucide="send" class="me-1" style="width: 14px; height: 14px;"></i>Kirim Kode OTP ke Email Baru
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: KEAMANAN SANDI -->
    <div class="cf-tab-pane<?= $defaultActiveTab === 'keamanan' ? ' active' : '' ?>" id="tab-keamanan" role="tabpanel">
        <div class="cf-card p-4">
            <div class="d-flex align-items-center gap-2 pb-3 mb-3 border-bottom border-secondary border-opacity-10">
                <div class="rounded-circle p-2 bg-warning bg-opacity-10 text-warning-emphasis">
                    <i data-lucide="shield-check" style="width: 20px; height: 20px;"></i>
                </div>
                <div>
                    <h3 class="h6 fw-bold mb-0 text-dark">Perbarui Kata Sandi Akun</h3>
                    <div class="text-muted small" style="font-size: 0.78rem;">Pastikan menggunakan kombinasi kata sandi yang kuat dan unik</div>
                </div>
            </div>

            <form method="POST" action="/profile/password">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="mb-3">
                    <label for="profile_current_password" class="form-label small fw-semibold text-dark">Kata Sandi Saat Ini <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i data-lucide="key-round" style="width: 15px; height: 15px;"></i></span>
                        <input type="password" id="profile_current_password" name="current_password" class="form-control border-start-0 border-end-0" placeholder="Masukkan kata sandi saat ini" required autocomplete="current-password">
                        <button type="button" class="btn btn-outline-secondary border-start-0" id="btn-toggle-profile-current-password" aria-label="Tampilkan kata sandi saat ini" aria-pressed="false" onclick="togglePasswordVisibility('profile_current_password', this)">
                            <i data-lucide="eye" style="width: 15px; height: 15px;"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="profile_new_password" class="form-label small fw-semibold text-dark">Kata Sandi Baru <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i data-lucide="lock" style="width: 15px; height: 15px;"></i></span>
                        <input type="password" id="profile_new_password" name="new_password" class="form-control border-start-0 border-end-0" placeholder="Minimal 8 karakter baru" minlength="8" required autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary border-start-0" id="btn-toggle-profile-new-password" aria-label="Tampilkan kata sandi baru" aria-pressed="false" onclick="togglePasswordVisibility('profile_new_password', this)">
                            <i data-lucide="eye" style="width: 15px; height: 15px;"></i>
                        </button>
                    </div>
                    <div class="form-text extra-small text-muted">Minimal 8 karakter, kombinasi huruf besar, huruf kecil, dan angka.</div>
                </div>

                <div class="mb-3">
                    <label for="profile_new_password_confirmation" class="form-label small fw-semibold text-dark">Ulangi Kata Sandi Baru <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i data-lucide="lock" style="width: 15px; height: 15px;"></i></span>
                        <input type="password" id="profile_new_password_confirmation" name="new_password_confirmation" class="form-control border-start-0 border-end-0" placeholder="Ketik ulang kata sandi baru" minlength="8" required autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary border-start-0" id="btn-toggle-profile-new-password-confirmation" aria-label="Tampilkan konfirmasi kata sandi baru" aria-pressed="false" onclick="togglePasswordVisibility('profile_new_password_confirmation', this)">
                            <i data-lucide="eye" style="width: 15px; height: 15px;"></i>
                        </button>
                    </div>
                </div>

                <div class="p-3 bg-light bg-opacity-75 rounded-3 border extra-small text-muted mb-4 d-flex align-items-start gap-2">
                    <i data-lucide="info" class="text-primary mt-0.5" style="width: 16px; height: 16px;"></i>
                    <div>
                        Setelah kata sandi berhasil diperbarui, seluruh sesi login pada perangkat atau browser lain akan diakhiri secara otomatis demi keamanan akun Anda.
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i data-lucide="shield-check" class="me-1" style="width: 15px; height: 15px;"></i> Perbarui Kata Sandi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 4: PREFERENSI NOTIFIKASI -->
    <div class="cf-tab-pane<?= $defaultActiveTab === 'notifikasi' ? ' active' : '' ?>" id="tab-notifikasi" role="tabpanel">
        <div class="cf-card p-4 mb-4">
            <div class="d-flex align-items-center gap-2 pb-3 mb-3 border-bottom border-secondary border-opacity-10">
                <div class="rounded-circle p-2 bg-info bg-opacity-10 text-info">
                    <i data-lucide="mail-check" style="width: 20px; height: 20px;"></i>
                </div>
                <div>
                    <h3 class="h6 fw-bold mb-0 text-dark">Persetujuan Notifikasi Email</h3>
                    <div class="text-muted small" style="font-size: 0.78rem;">Kontrol pengiriman pemberitahuan eksternal ke kotak masuk Anda</div>
                </div>
            </div>

            <form method="POST" action="/profile/notifications">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Status Notice Box -->
                <div class="p-3 mb-3 rounded-3 border <?= $hasOptIn ? 'bg-success-subtle border-success-subtle' : 'bg-body-tertiary border-secondary-subtle' ?>">
                    <div class="d-flex align-items-start gap-3">
                        <div class="fs-4 <?= $hasOptIn ? 'text-success' : 'text-secondary' ?>">
                            <i data-lucide="<?= $hasOptIn ? 'bell' : 'bell-off' ?>" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 <?= $hasOptIn ? 'text-success' : 'text-dark' ?>">
                                <?= $hasOptIn ? 'Notifikasi Email Sedang Aktif' : 'Notifikasi Email Nonaktif' ?>
                            </h6>
                            <p class="small mb-0 text-muted">
                                <?= $hasOptIn 
                                    ? 'Anda akan menerima email pemberitahuan saat status laporan atau pengajuan klaim Anda diperbarui.' 
                                    : 'Anda hanya akan menerima pemberitahuan di dalam aplikasi Campus Find (in-app).' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Main Switch Toggle -->
                <div class="p-3 border rounded-3 bg-body mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <label for="email_notifications_enabled" class="fw-bold small text-dark d-block mb-1 cursor-pointer">
                            Terima Notifikasi via Email
                        </label>
                        <span class="extra-small text-muted d-block">
                            Saya menyetujui untuk menerima notifikasi eksternal via email ke <strong><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></strong>.
                        </span>
                    </div>
                    <div class="form-check form-switch fs-4 mb-0 ms-3">
                        <input class="form-check-input cursor-pointer" 
                               type="checkbox" 
                               role="switch" 
                               id="email_notifications_enabled" 
                               name="email_notifications_enabled" 
                               value="1" 
                               <?= $hasOptIn ? 'checked' : '' ?>>
                    </div>
                </div>

                <!-- Consent Information -->
                <div class="bg-body-secondary p-3 rounded-3 small mb-4">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i data-lucide="shield-check" class="text-secondary" style="width: 15px; height: 15px;"></i>
                        <span class="fw-bold text-dark">Ketentuan Privasi &amp; Persetujuan Notifikasi</span>
                    </div>
                    <ul class="extra-small text-muted mb-2 ps-3">
                        <li>Versi teks ketentuan persetujuan: <code><?= htmlspecialchars($user['email_consent_text_version'] ?: 'v1.0', ENT_QUOTES, 'UTF-8') ?></code></li>
                        <li>Tanggal persetujuan terakhir: <strong><?= $user['email_consent_at'] ? date('d M Y H:i', strtotime($user['email_consent_at'])) . ' WIB' : 'Belum pernah disetujui' ?></strong></li>
                        <li>Isi email bersifat singkat dan tidak memuat bukti klaim, rincian unik barang, maupun catatan internal.</li>
                        <li>Anda dapat mencabut persetujuan ini kapan saja dengan mematikan sakelar di atas.</li>
                    </ul>
                    <?php if (!$isVerified): ?>
                        <div class="text-danger extra-small mt-1">
                            <i data-lucide="alert-circle" class="me-1" style="width: 13px; height: 13px;"></i> Perhatian: Alamat email Anda belum terverifikasi. Notifikasi eksternal hanya dikirimkan ke email terverifikasi.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i data-lucide="check" class="me-1" style="width: 15px; height: 15px;"></i> Simpan Preferensi
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Outbox Log -->
        <?php if (!empty($recentOutbox)): ?>
            <div class="cf-card p-0 mx-auto cf-content-narrow overflow-hidden">
                <div class="p-3 border-bottom bg-light bg-opacity-50">
                    <h6 class="fw-bold mb-0 text-dark small"><i class="bi bi-clock-history me-1"></i> Riwayat Pengiriman Notifikasi Terbaru</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 extra-small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Kejadian</th>
                                <th>Status</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOutbox as $ro): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold"><?= htmlspecialchars($ro['template_key'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($ro['status'] === 'sent'): ?>
                                            <span class="cf-badge cf-badge-found">Terkirim</span>
                                        <?php elseif ($ro['status'] === 'pending'): ?>
                                            <span class="cf-badge cf-badge-warning">Menunggu</span>
                                        <?php elseif ($ro['status'] === 'failed'): ?>
                                            <span class="cf-badge cf-badge-danger">Gagal</span>
                                        <?php else: ?>
                                            <span class="cf-badge cf-badge-neutral"><?= htmlspecialchars($ro['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?= date('d/m/Y H:i', strtotime($ro['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    btn.setAttribute('aria-pressed', isPass ? 'true' : 'false');
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
}
</script>
