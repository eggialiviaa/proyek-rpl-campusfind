<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '500 — Terjadi Kesalahan Server | Campus Find', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/app.css?v=<?= file_exists(BASE_PATH . '/public/css/app.css') ? filemtime(BASE_PATH . '/public/css/app.css') : time() ?>">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #F8FAFC;
        }
        .error-card {
            max-width: 520px;
            border-radius: 16px;
            background: white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 2.5rem;
            text-align: center;
        }
        .error-icon {
            width: 72px;
            height: 72px;
            background: #FEF3C7;
            color: #D97706;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center p-3">
        <div class="error-card w-100">
            <div class="error-icon">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <h1 class="h3 fw-bold text-dark mb-2">500 — Terjadi Kendala Server</h1>
            <p class="text-muted mb-4">
                Mohon maaf, sistem mengalami kendala teknis internal saat memproses permintaan Anda.
                Laporan kendala telah dicatat ke log server untuk segera ditangani.
            </p>

            <?php
            $homeUrl = (class_exists(\App\Middleware\AuthMiddleware::class) && \App\Middleware\AuthMiddleware::isLoggedIn()) ? '/dashboard' : '/';
            ?>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary px-4" id="btn-500-home">
                    <i class="bi bi-house-door me-1"></i> Kembali ke Beranda
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary px-3" id="btn-500-back">
                    Halaman Sebelumnya
                </a>
            </div>
        </div>
    </div>
</body>
</html>
