<?php
use App\Middleware\AuthMiddleware;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$isFreshLogin = !empty($_SESSION['_sidebar_fresh_login']);
if ($isFreshLogin) {
    unset($_SESSION['_sidebar_fresh_login']);
}
$authUser = AuthMiddleware::user();
$userRole = $authUser ? strtolower(trim((string)($authUser['role_name'] ?? $authUser['role'] ?? 'civitas'))) : 'civitas';
$flashMessages = $_SESSION['_flash'] ?? [];
unset($_SESSION['_flash']);
$currentUriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$isCivitasUser = AuthMiddleware::isLoggedIn() && ($userRole === 'civitas');
$isCivitasDashboard = ($view ?? '') === 'dashboard/civitas' || ($userRole === 'civitas' && str_starts_with($currentUriPath, '/dashboard'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Anti-Layout-Flash Script: Sinkronisasi status collapsed & no-transition sebelum paint -->
    <script>
    (function() {
        try {
            var isFreshLogin = <?= json_encode($isFreshLogin) ?>;
            if (isFreshLogin) {
                try {
                    localStorage.setItem('cf_sidebar_collapsed', 'true');
                    sessionStorage.removeItem('cf_nav_flip');
                    sessionStorage.removeItem('cf_sidebar_scroll');
                } catch (e) {}
            }
            var savedCollapsed = localStorage.getItem('cf_sidebar_collapsed');
            var isCollapsed = savedCollapsed === null ? true : (savedCollapsed === 'true');
            if (isCollapsed && window.innerWidth >= 992) {
                document.documentElement.classList.add('cf-sidebar-collapsed');
            }
            document.documentElement.classList.add('no-transition');
        } catch (e) {}
    })();
    </script>

    <!-- SEO -->
    <title><?= htmlspecialchars($pageTitle ?? 'Panel Pengelolaan — Campus Find', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'Panel administrasi dan operasional Campus Find Lost & Found System.', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="noindex, nofollow">

    <!-- Favicon -->
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

    <!-- Global Design System CSS with Cache Busting -->
    <link rel="stylesheet" href="/css/app.css?v=<?= file_exists(BASE_PATH . '/public/css/app.css') ? filemtime(BASE_PATH . '/public/css/app.css') : time() ?>">

    <!-- Floating Liquid Glass Dock Module (PAGE-00B.3.1) -->
    <link rel="stylesheet" href="/css/sidebar-glass.css?v=<?= file_exists(BASE_PATH . '/public/css/sidebar-glass.css') ? filemtime(BASE_PATH . '/public/css/sidebar-glass.css') : time() ?>">

    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body class="cf-app-shell cf-role-<?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?><?= $isCivitasDashboard ? ' cf-route-civitas-dashboard' : '' ?>">

    <!-- App Shell Outer Layout Wrapper with Role-Scoped Atmospheric Canvas -->
    <div class="cf-shell-wrapper<?= $isFreshLogin ? ' cf-sidebar-collapsed' : '' ?>" id="cf-shell-wrapper" data-role="<?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($isCivitasUser || $userRole === 'petugas' || $userRole === 'admin'): ?>
        <!-- Dedicated Fixed Full-Viewport Aura Canvas (PAGE-01.2A.2) -->
        <div class="cf-aura-canvas" id="cf-civitas-aura-canvas" aria-hidden="true">
            <svg class="cf-aura-mesh-svg" viewBox="0 0 1440 960" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <!-- Navy Ambient Glow -->
                    <radialGradient id="cf-aura-grad-navy" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#14283C" stop-opacity="0.08" />
                        <stop offset="60%" stop-color="#14283C" stop-opacity="0.02" />
                        <stop offset="100%" stop-color="#14283C" stop-opacity="0" />
                    </radialGradient>
                    <!-- Warm Cognac Ambient Aura -->
                    <radialGradient id="cf-aura-grad-cognac" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#A77B5B" stop-opacity="0.12" />
                        <stop offset="50%" stop-color="#A77B5B" stop-opacity="0.04" />
                        <stop offset="100%" stop-color="#A77B5B" stop-opacity="0" />
                    </radialGradient>
                    <!-- Muted Sage Atmospheric Glow -->
                    <radialGradient id="cf-aura-grad-sage" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#A9B8A4" stop-opacity="0.10" />
                        <stop offset="55%" stop-color="#A9B8A4" stop-opacity="0.03" />
                        <stop offset="100%" stop-color="#A9B8A4" stop-opacity="0" />
                    </radialGradient>
                    <!-- Secondary Cognac Soft Light -->
                    <radialGradient id="cf-aura-grad-cognac2" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#A77B5B" stop-opacity="0.09" />
                        <stop offset="65%" stop-color="#A77B5B" stop-opacity="0.02" />
                        <stop offset="100%" stop-color="#A77B5B" stop-opacity="0" />
                    </radialGradient>
                </defs>

                <!-- Organic Gradient Mesh Orbs -->
                <circle cx="100" cy="160" r="320" fill="url(#cf-aura-grad-navy)" />
                <circle cx="1180" cy="140" r="360" fill="url(#cf-aura-grad-cognac)" />
                <circle cx="200" cy="740" r="350" fill="url(#cf-aura-grad-sage)" />
                <circle cx="1300" cy="820" r="380" fill="url(#cf-aura-grad-cognac2)" />

                <!-- Thin Geometric Outline Rings -->
                <circle cx="280" cy="200" r="210" fill="none" stroke="rgba(20, 40, 60, 0.05)" stroke-width="1.5" stroke-dasharray="6,6" />
                <circle cx="1140" cy="220" r="260" fill="none" stroke="rgba(167, 123, 91, 0.08)" stroke-width="1.5" />
                <circle cx="1140" cy="220" r="180" fill="none" stroke="rgba(167, 123, 91, 0.05)" stroke-width="1" stroke-dasharray="8,8" />
                <circle cx="720" cy="520" r="300" fill="none" stroke="rgba(169, 184, 164, 0.06)" stroke-width="1.5" />

                <!-- Organic Linear Elements Passing Behind Glass Cards -->
                <path d="M -80 340 C 350 240, 780 420, 1520 270" fill="none" stroke="rgba(167, 123, 91, 0.07)" stroke-width="1.5" />
                <path d="M -50 720 C 420 810, 920 650, 1500 760" fill="none" stroke="rgba(20, 40, 60, 0.04)" stroke-width="1.5" />
            </svg>
        </div>
        <?php endif; ?>
        <!-- Dedicated Atmospheric Glass Refraction Canvas (positioned behind dock) -->
        <div class="cf-dock-backdrop-canvas" id="cf-dock-backdrop-canvas" aria-hidden="true">
            <div class="cf-dock-ambient-orb cf-orb-1"></div>
            <div class="cf-dock-ambient-orb cf-orb-2"></div>
            <div class="cf-dock-ambient-orb cf-orb-3"></div>
            <div class="cf-dock-ambient-grid"></div>
        </div>

        <!-- Unified Floating Liquid Glass Dock / Capsule Sidebar -->
        <?php require BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

        <!-- Submenu flyout removed per user directive -->

        <!-- Backdrop Overlay untuk Mobile Drawer (<992px) -->
        <div class="cf-sidebar-backdrop" id="cf-sidebar-backdrop" aria-hidden="true"></div>

        <!-- Main Workspace Area -->
        <div class="cf-shell-main" id="cf-shell-main">
            <!-- Compact Authenticated Topbar Component -->
            <?php require BASE_PATH . '/app/Views/partials/topbar.php'; ?>

            <!-- Flash Alerts -->
            <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>

            <!-- Main Content View Area -->
            <main class="cf-main-content" id="main-content" role="main">
                <?= $content ?>
            </main>

            <!-- Application Footer -->
            <?php require BASE_PATH . '/app/Views/partials/footer.php'; ?>
        </div>
    </div>

    <!-- Floating Chatbot C-Find (Prompt 26) -->
    <?php
    $currentUriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $isLoginPage = ($view ?? '') === 'auth/login' || str_starts_with($currentUriPath, '/login') || !empty($hideCFind);
    $isPrintPage = !empty($isPrint) || str_contains($currentUriPath, '/print') || str_contains($currentUriPath, '/cetak') || (isset($_GET['print']) && $_GET['print'] === '1');
    if (!$isLoginPage && !$isPrintPage) {
        require BASE_PATH . '/app/Views/partials/cfind_widget.php';
    }
    ?>

    <!-- Reusable Confirmation Dialog Modal (AppDialog) -->
    <?php require BASE_PATH . '/app/Views/partials/confirm_modal.php'; ?>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>

    <!-- Lucide Icons Modern SVG Engine -->
    <script src="/js/lucide.min.js"></script>

    <!-- Liquid Glass UI JS Helper & Shell Controller -->
    <script src="/js/app.js?v=<?= file_exists(BASE_PATH . '/public/js/app.js') ? filemtime(BASE_PATH . '/public/js/app.js') : time() ?>"></script>

    <!-- Floating Liquid Glass Dock Interaction Controller (PAGE-00B.3.1) -->
    <script src="/js/sidebar-dock.js?v=<?= file_exists(BASE_PATH . '/public/js/sidebar-dock.js') ? filemtime(BASE_PATH . '/public/js/sidebar-dock.js') : time() ?>"></script>

    <?php if (isset($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>

</body>
</html>
