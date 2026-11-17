<?php
/**
 * app/Views/auth/_login_form.php
 * Shared CampusFind Login Form Partial
 *
 * Reused identically by:
 * - Standalone /login page (app/Views/auth/login.php)
 * - Landing Scene 08 Full-Screen Liquid Glass Login (app/Views/home/_closing_login.php)
 *
 * Preserves canonical POST /login action, _csrf_token, field names, rate-limit lockout notice,
 * flash & inline errors, password visibility toggle, and admin-assisted account policy.
 */

$loginFormVariant = $loginFormVariant ?? 'standalone';
$isGlassVariant = ($loginFormVariant === 'glass');

$activeFlash = $_SESSION['_flash'] ?? [];
$oldIdentity = $_SESSION['_old_input']['identity'] ?? '';
if (isset($_SESSION['_old_input']['identity'])) {
    unset($_SESSION['_old_input']['identity']);
}

$loginInlineError = $_SESSION['login_inline_error'] ?? null;
if (isset($_SESSION['login_inline_error'])) {
    unset($_SESSION['login_inline_error']);
}

if (empty($loginInlineError) && !empty($activeFlash['error'])) {
    $loginInlineError = (string) $activeFlash['error'];
}
$hasInlineError = !empty($loginInlineError);

$bannerFlashes = array_filter($activeFlash, static function ($k) use ($hasInlineError) {
    return !($hasInlineError && ($k === 'error' || $k === 'danger'));
}, ARRAY_FILTER_USE_KEY);
?>

<!-- Lockout Notice (jika ada) -->
<?php if (!empty($lockInfo['is_locked'])): ?>
    <div class="alert alert-danger border-0 d-flex align-items-start gap-2 mb-3 rounded-3" role="alert">
        <span class="fs-5">&#x26D4;</span>
        <div class="small">
            <strong>Akun/Perangkat Terkunci Sementara</strong><br>
            Terlalu banyak percobaan gagal. Silakan tunggu
            <strong><?= ceil($lockInfo['remaining_seconds'] / 60) ?> menit</strong> sebelum mencoba lagi.
        </div>
    </div>
<?php endif; ?>

<!-- Non-Credential Flash Message (Success / Info / Warning) -->
<?php if (!empty($bannerFlashes)): ?>
    <?php foreach ($bannerFlashes as $fType => $fMsg): ?>
        <?php
        $alertVariant = match ($fType) {
            'warning' => 'warning',
            'success' => 'success',
            default   => 'info',
        };
        $iconClass = match ($alertVariant) {
            'warning' => 'bi-exclamation-circle-fill text-warning',
            'success' => 'bi-check-circle-fill text-success',
            default   => 'bi-info-circle-fill text-primary',
        };
        $msgText = is_array($fMsg) ? implode(' ', array_filter($fMsg, 'is_scalar')) : (string) $fMsg;
        ?>
        <div class="alert alert-<?= $alertVariant ?> border-0 d-flex align-items-center gap-2 mb-3 rounded-3 shadow-sm text-start" role="alert">
            <i class="bi <?= $iconClass ?> fs-5 flex-shrink-0"></i>
            <div class="small fw-medium flex-grow-1">
                <?= htmlspecialchars($msgText, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Inline Credential Validation Message -->
<?php if ($hasInlineError): ?>
    <div class="cf-login-inline-error text-danger mb-3 p-2.5 px-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 d-flex align-items-center gap-2 small" id="login-inline-error" role="alert">
        <i class="bi bi-exclamation-circle-fill flex-shrink-0 text-danger"></i>
        <div class="fw-medium flex-grow-1"><?= htmlspecialchars($loginInlineError, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
<?php endif; ?>

<!-- Form Login (Canonical POST /login) -->
<form action="/login"
      method="POST"
      id="form-login"
      class="<?= $isGlassVariant ? 'cf-login-form--glass' : '' ?>"
      novalidate
      aria-label="Formulir login pengguna">

    <!-- CSRF Token -->
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <!-- Identity Input (Email / NIM / NIP) -->
    <div class="mb-3 cf-login-group-identity">
        <label for="input-identity" class="form-label fw-semibold small text-dark">
            Email atau NIM / NIP
        </label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted border-end-0 <?= $hasInlineError ? 'border-danger' : '' ?>"><i class="bi bi-person-badge"></i></span>
            <input type="text"
                   id="input-identity"
                   name="identity"
                   class="form-control border-start-0 <?= $hasInlineError ? 'is-invalid border-danger' : '' ?>"
                   placeholder="nama@campusfind.test atau NIM/NIP"
                   value="<?= htmlspecialchars($oldIdentity, ENT_QUOTES, 'UTF-8') ?>"
                   autocomplete="username"
                   required
                   aria-required="true">
        </div>
    </div>

    <!-- Password Input with Visibility Toggle -->
    <div class="mb-3 cf-login-group-password">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="input-password" class="form-label fw-semibold small text-dark mb-0">
                Password
            </label>
            <a href="/forgot-password" class="small text-muted text-decoration-none" id="cf-forgot-pwd-link">
                Lupa password?
            </a>
        </div>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted border-end-0 <?= $hasInlineError ? 'border-danger' : '' ?>"><i class="bi bi-lock"></i></span>
            <input type="password"
                   id="input-password"
                   name="password"
                   class="form-control border-start-0 border-end-0 <?= $hasInlineError ? 'is-invalid border-danger' : '' ?>"
                   placeholder="Masukkan password"
                   autocomplete="current-password"
                   required
                   aria-required="true">
            <button type="button"
                    class="btn btn-outline-secondary border-start-0 <?= $hasInlineError ? 'border-danger' : '' ?>"
                    id="btn-toggle-login-password"
                    aria-label="Tampilkan kata sandi"
                    aria-pressed="false"
                    onclick="togglePasswordVisibility('input-password', this)">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <!-- Submit Button -->
    <button type="submit"
            class="btn <?= $isGlassVariant ? 'cf-login-glass-submit' : 'btn-primary' ?> w-100 py-2 fw-semibold mt-2 rounded-pill shadow-sm"
            id="btn-submit-login">
        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
    </button>

    <!-- Onboarding Info: Admin-assisted registration policy -->
    <div class="text-center pt-3 border-top mt-3">
        <div class="small text-muted mb-1">
            <i class="bi bi-shield-check text-success me-1"></i>
            Akun baru civitas didaftarkan oleh <strong>Administrator</strong> dengan kata sandi sementara.
        </div>
        <div class="extra-small text-muted">
            Belum menerima kredensial akun? Silakan hubungi <strong>Administrator Kampus</strong> atau bagian IT/L&amp;F Center.
        </div>
    </div>
</form>

