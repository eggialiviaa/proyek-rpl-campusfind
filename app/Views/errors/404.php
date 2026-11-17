<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Halaman Tidak Ditemukan | Campus Find</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="/css/app.css?v=<?= file_exists(BASE_PATH . '/public/css/app.css') ? filemtime(BASE_PATH . '/public/css/app.css') : time() ?>">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    </style>
</head>
<body class="bg-light">
    <div class="text-center px-4">
        <div style="font-size: 5rem; line-height: 1; margin-bottom: 1rem;">🔍</div>
        <h1 class="display-6 fw-bold text-dark mb-2">Halaman Tidak Ditemukan</h1>
        <p class="text-muted mb-4" style="max-width: 400px; margin: 0 auto 1.5rem;">
            Halaman yang Anda cari tidak ada atau telah dipindahkan.
        </p>
        <?php
        $homeUrl = (class_exists(\App\Middleware\AuthMiddleware::class) && \App\Middleware\AuthMiddleware::isLoggedIn()) ? '/dashboard' : '/';
        ?>
        <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary px-5" id="btn-404-home">
            Kembali ke Beranda
        </a>
    </div>
</body>
</html>
