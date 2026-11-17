<?php
/**
 * app/Views/errors/403.php
 * Halaman 403 Forbidden — Akses Ditolak (RBAC Guard)
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '403 — Akses Ditolak | Campus Find', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/app.css?v=<?= file_exists(BASE_PATH . '/public/css/app.css') ? filemtime(BASE_PATH . '/public/css/app.css') : time() ?>">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #F8FAFC; }
        .error-card { max-width: 540px; border-radius: 16px; background: white; box-shadow: 0 10px 25px rgba(0,0,0,0.08); padding: 2.5rem; text-align: center; }
        .error-icon { width: 72px; height: 72px; background: #FEE2E2; color: #DC2626; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center p-3">
        <div class="error-card w-100">
            <div class="error-icon">
                <i class="bi bi-shield-slash"></i>
            </div>
            <h1 class="h3 fw-bold text-dark mb-2">403 — Akses Ditolak</h1>
            <p class="text-muted mb-4">
                <?= !empty($message) ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8') : 'Anda tidak memiliki hak akses (otorisasi) untuk membuka halaman ini. Hak akses dibatasi oleh kebijakan keamanan Role-Based Access Control (RBAC).' ?>
            </p>

            <?php if (!empty($requiredRole) || !empty($currentRole)): ?>
                <div class="alert alert-light border text-start small mb-4 py-2 px-3">
                    <?php if (!empty($currentRole)): ?>
                        <div>Role Anda: <span class="cf-badge cf-badge-neutral"><?= htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($requiredRole)): ?>
                        <div class="mt-1">Role yang Diperlukan: <span class="cf-badge cf-badge-danger"><?= htmlspecialchars($requiredRole, ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-center gap-2">
                <a href="/dashboard" class="btn btn-primary px-4" id="btn-return-dashboard">
                    <i class="bi bi-house-door me-1"></i> Kembali ke Dashboard
                </a>
                <a href="/logout" class="btn btn-outline-secondary px-3" id="btn-switch-account">
                    Ganti Akun
                </a>
            </div>
        </div>
    </div>
</body>
</html>
