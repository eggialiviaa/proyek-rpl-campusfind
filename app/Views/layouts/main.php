<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- SEO -->
    <title><?= htmlspecialchars($pageTitle ?? 'Campus Find', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'Campus Find — Sistem Lost & Found Digital untuk lingkungan kampus.', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="index, follow">

    <!-- Favicon placeholder -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔍</text></svg>">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/plus-jakarta-sans.css">

    <!-- Bootstrap 5.3 CSS CDN -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS with Cache Busting -->
    <link rel="stylesheet" href="/css/app.css?v=<?= file_exists(BASE_PATH . '/public/css/app.css') ? filemtime(BASE_PATH . '/public/css/app.css') : time() ?>">

    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body class="cf-page-enter<?= !empty($isLandingPage) ? ' cf-landing' : '' ?>">

    <?php
    // Ambil flash messages sebelum render
    // getFlash() tersedia di BaseController, tapi layout dipanggil di luar controller
    // Jadi kita baca langsung dari session di sini
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flashMessages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    ?>

    <!-- Navbar -->
    <?php
    $currentUriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $isLoginPage = ($view ?? '') === 'auth/login' || str_starts_with($currentUriPath, '/login');
    $isPrintPage = !empty($isPrint) || str_contains($currentUriPath, '/print') || str_contains($currentUriPath, '/cetak') || (isset($_GET['print']) && $_GET['print'] === '1');
    ?>
    <?php if (!$isLoginPage && !$isPrintPage): ?>
        <?php require BASE_PATH . '/app/Views/partials/navbar.php'; ?>
    <?php endif; ?>

    <!-- Flash Messages -->
    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>

    <!-- Main Content -->
    <main id="main-content" role="main">
        <?= $content ?>
    </main>

    <!-- Floating Chatbot C-Find (Prompt 26) -->
    <?php
    if (!$isLoginPage && !$isPrintPage && empty($hideCFind)) {
        require BASE_PATH . '/app/Views/partials/cfind_widget.php';
    }
    ?>

    <!-- Reusable Confirmation Dialog Modal -->
    <?php require BASE_PATH . '/app/Views/partials/confirm_modal.php'; ?>

    <!-- Footer -->
    <?php if (!$isLoginPage && !$isPrintPage): ?>
        <?php require BASE_PATH . '/app/Views/partials/footer.php'; ?>
    <?php endif; ?>

    <!-- Bootstrap 5 JS Bundle (termasuk Popper) CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>

    <!-- Lucide Icons Modern SVG Engine -->
    <script src="/js/lucide.min.js"></script>

    <!-- Custom JS -->
    <script src="/js/app.js"></script>

    <?php if (isset($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>

</body>
</html>
