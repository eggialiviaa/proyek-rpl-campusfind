<?php

// Verification script for PAGE-00B.3.2 Sidebar State & Navigation Polish

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', dirname(__DIR__) . '/public');
require_once BASE_PATH . '/vendor/autoload.php';

if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

$errors = [];
$passes = [];

echo "========================================================\n";
echo "   PAGE-00B.3.2: SIDEBAR STATE & NAVIGATION POLISH TEST\n";
echo "========================================================\n\n";

// ─────────────────────────────────────────────────────────────
// TEST 1 — BUG 1: CIVITAS DASHBOARD ACTIVE ITEM & NO STRAY HIGHLIGHT
// ─────────────────────────────────────────────────────────────
echo "--- TEST 1: Bug 1 — Civitas Dashboard Active State Audit ---\n";

// Set up Civitas session
$_SERVER['REQUEST_URI'] = '/dashboard';
$_SERVER['REQUEST_METHOD'] = 'GET';
$civitasUser = [
    'id' => 3,
    'name' => 'Budi Santoso',
    'email' => 'budi@mahasiswa.campusfind.test',
    'role' => 'civitas',
    'role_name' => 'civitas',
    'role_label' => 'Civitas Kampus',
    'is_active' => 1
];
\App\Middleware\AuthMiddleware::login($civitasUser);

ob_start();
$pageTitle = 'Dasbor Civitas';
$content = '<div id="test-content">Dashboard Content</div>';
require BASE_PATH . '/app/Views/layouts/app_shell.php';
$html = ob_get_clean();

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

// 1.1 Check active links
$activeLinks = $xpath->query('//a[contains(@class, "cf-sidebar-link") and contains(@class, "active")]');
echo "Active links found: " . $activeLinks->length . "\n";
if ($activeLinks->length === 1) {
    $activeText = trim($activeLinks->item(0)->getAttribute('data-label'));
    $activeHref = $activeLinks->item(0)->getAttribute('href');
    $ariaCurrent = $activeLinks->item(0)->getAttribute('aria-current');
    if ($activeHref === '/dashboard' && $ariaCurrent === 'page') {
        $passes[] = "Only '{$activeText}' is active with aria-current='page'.";
        echo "[PASS] Only 1 active item found: '{$activeText}' ({$activeHref}) with aria-current='page'\n";
    } else {
        $errors[] = "Active item is '{$activeText}' ({$activeHref}), expected '/dashboard' with aria-current='page'";
    }
} else {
    $found = [];
    foreach ($activeLinks as $l) {
        $found[] = $l->getAttribute('data-label') . ' (' . $l->getAttribute('href') . ')';
    }
    $errors[] = "Expected exactly 1 active link, found " . $activeLinks->length . ": " . implode(', ', $found);
}

// 1.2 Check 'Lapor Barang Hilang' item
$laporLink = $xpath->query('//a[contains(@class, "cf-sidebar-link") and @href="/reports/lost/create"]');
if ($laporLink->length === 1) {
    $classes = $laporLink->item(0)->getAttribute('class');
    echo "'Lapor Barang Hilang' classes: '{$classes}'\n";
    if (strpos($classes, 'cf-sidebar-cta') === false && strpos($classes, 'active') === false) {
        $passes[] = "'Lapor Barang Hilang' has NO 'cf-sidebar-cta' and NO 'active' class on dashboard.";
        echo "[PASS] 'Lapor Barang Hilang' is cleanly styled without stray CTA class\n";
    } else {
        $errors[] = "'Lapor Barang Hilang' has unexpected classes: '{$classes}'";
    }
} else {
    $errors[] = "Could not find 'Lapor Barang Hilang' link (/reports/lost/create)";
}

// 1.3 Check CSS neutralization in app.css
$appCss = file_get_contents(BASE_PATH . '/public/css/app.css');
if (strpos($appCss, 'rgba(167, 123, 91, 0.12)') === false) {
    $passes[] = "Beige pill rgba(167, 123, 91, 0.12) completely removed from app.css.";
    echo "[PASS] Beige background color neutralized in app.css\n";
} else {
    $errors[] = "Stray beige background rgba(167, 123, 91, 0.12) still found in app.css";
}

// 1.4 Check stable IDs on nav links
$navLinks = $xpath->query('//a[contains(@class, "cf-sidebar-link")]');
$missingIds = 0;
foreach ($navLinks as $nl) {
    $id = $nl->getAttribute('id');
    $menuId = $nl->getAttribute('data-menu-id');
    if (!$id || !$menuId || $id !== $menuId) {
        $missingIds++;
    }
}
if ($missingIds === 0 && $navLinks->length > 0) {
    $passes[] = "All {$navLinks->length} sidebar navigation links have stable id and data-menu-id attributes.";
    echo "[PASS] All {$navLinks->length} links have stable id and data-menu-id attributes (e.g. cf-nav-dashboard)\n";
} else {
    $errors[] = "{$missingIds} links are missing stable IDs";
}


// ─────────────────────────────────────────────────────────────
// TEST 2 — BUG 2: COLLAPSED PREPAINT & NO-TRANSITION CLASS
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 2: Bug 2 — Collapsed Prepaint & No-Transition Audit ---\n";

// 2.1 Check <head> prepaint script
if (strpos($html, "document.documentElement.classList.add('no-transition')") !== false &&
    strpos($html, "document.documentElement.classList.add('cf-sidebar-collapsed')") !== false) {
    $passes[] = "Prepaint script immediately applies 'cf-sidebar-collapsed' and 'no-transition' before first paint.";
    echo "[PASS] Prepaint script in <head> adds 'cf-sidebar-collapsed' and 'no-transition'\n";
} else {
    $errors[] = "Prepaint script missing 'no-transition' or 'cf-sidebar-collapsed' logic";
}

// 2.2 Check sidebar-glass.css scoped no-transition rule
$sidebarGlassCss = file_get_contents(BASE_PATH . '/public/css/sidebar-glass.css');
if (strpos($sidebarGlassCss, 'html.no-transition .cf-sidebar') !== false &&
    strpos($sidebarGlassCss, 'transition: none !important') !== false) {
    $passes[] = "sidebar-glass.css defines scoped .no-transition rule for sidebar and workspace offset.";
    echo "[PASS] Scoped no-transition rule present in sidebar-glass.css\n";
} else {
    $errors[] = "sidebar-glass.css missing scoped .no-transition rule";
}

// 2.3 Check sidebar-glass.css collapsed width rule precedence
if (strpos($sidebarGlassCss, 'width: var(--cf-app-sidebar-collapsed, 64px) !important') !== false) {
    $passes[] = "sidebar-glass.css enforces collapsed 64px width with !important.";
    echo "[PASS] Collapsed width strictly enforced in sidebar-glass.css\n";
} else {
    $errors[] = "sidebar-glass.css missing !important on collapsed width";
}


// ─────────────────────────────────────────────────────────────
// TEST 3 — BUG 3: FLIP TRANSITION IN JS SCRIPT
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 3: Bug 3 — FLIP Cross-Page Transition Audit ---\n";

$sidebarDockJs = file_get_contents(BASE_PATH . '/public/js/sidebar-dock.js');
if (strpos($sidebarDockJs, 'cf_nav_flip') !== false &&
    strpos($sidebarDockJs, 'cf_sidebar_scroll') !== false &&
    strpos($sidebarDockJs, 'restoreSidebarScroll') !== false &&
    strpos($sidebarDockJs, 'top 300ms cubic-bezier') !== false) {
    $passes[] = "sidebar-dock.js implements sessionStorage FLIP snapshot, scroll restoration, and 300ms transition.";
    echo "[PASS] sidebar-dock.js implements sessionStorage FLIP snapshot, scroll restoration, and 300ms transition\n";
} else {
    $errors[] = "sidebar-dock.js missing FLIP transition or scroll restoration implementation";
}

if (strpos($sidebarDockJs, 'hasGenuinePointerMoved') !== false) {
    $passes[] = "sidebar-dock.js guards hover effects until first genuine pointer movement.";
    echo "[PASS] Genuine pointer move guard present in sidebar-dock.js\n";
} else {
    $errors[] = "sidebar-dock.js missing genuine pointer move guard";
}


// ─────────────────────────────────────────────────────────────
// TEST 4 — BUG 4: LOGOUT & FRESH LOGIN RESET LIFECYCLE
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 4: Bug 4 — Login Reset Lifecycle Audit ---\n";

// 4.1 Check AuthMiddleware::login sets _sidebar_fresh_login
$authMiddlewareCode = file_get_contents(BASE_PATH . '/app/Middleware/AuthMiddleware.php');
if (strpos($authMiddlewareCode, "\$_SESSION['_sidebar_fresh_login'] = true;") !== false &&
    strpos($authMiddlewareCode, "unset(\$_SESSION['_sidebar_fresh_login']);") !== false) {
    $passes[] = "AuthMiddleware::login sets _sidebar_fresh_login, and logout unsets it.";
    echo "[PASS] AuthMiddleware sets and cleans up _sidebar_fresh_login flag\n";
} else {
    $errors[] = "AuthMiddleware missing _sidebar_fresh_login handling";
}

// 4.2 Simulate fresh login in app_shell.php
$_SESSION['_sidebar_fresh_login'] = true;
ob_start();
require BASE_PATH . '/app/Views/layouts/app_shell.php';
$htmlFreshLogin = ob_get_clean();

// Check if _sidebar_fresh_login was consumed
if (empty($_SESSION['_sidebar_fresh_login'])) {
    $passes[] = "_sidebar_fresh_login flag was cleanly consumed by app_shell.php on first render.";
    echo "[PASS] _sidebar_fresh_login consumed and unset during first render\n";
} else {
    $errors[] = "_sidebar_fresh_login was NOT unset after first render";
}

// Check if app_shell rendered with isFreshLogin = true
if (strpos($htmlFreshLogin, 'var isFreshLogin = true;') !== false &&
    strpos($htmlFreshLogin, "localStorage.setItem('cf_sidebar_collapsed', 'true')") !== false &&
    strpos($htmlFreshLogin, "sessionStorage.removeItem('cf_nav_flip')") !== false) {
    $passes[] = "app_shell.php correctly injected isFreshLogin=true, resetting localStorage and clearing snapshots.";
    echo "[PASS] app_shell.php renders with isFreshLogin=true and executes reset commands in <head>\n";
} else {
    $errors[] = "app_shell.php did not inject isFreshLogin=true reset script";
}

// 4.3 Simulate subsequent navigation in the same session
ob_start();
require BASE_PATH . '/app/Views/layouts/app_shell.php';
$htmlSubsequentNav = ob_get_clean();

if (strpos($htmlSubsequentNav, 'var isFreshLogin = false;') !== false) {
    $passes[] = "Subsequent navigation in same session has isFreshLogin=false, preserving user preference.";
    echo "[PASS] Subsequent navigation has isFreshLogin=false (user expand/collapse preserved)\n";
} else {
    $errors[] = "Subsequent navigation still has isFreshLogin=true";
}


// ─────────────────────────────────────────────────────────────
// MULTI-ROLE COVERAGE AUDIT
// ─────────────────────────────────────────────────────────────
echo "\n--- Multi-Role Coverage Audit (Admin, Petugas, Civitas) ---\n";
$rolesToTest = [
    'petugas' => [
        'id' => 2,
        'name' => 'Petugas Posko',
        'email' => 'zetaarienn@gmail.com',
        'role' => 'petugas',
        'role_name' => 'petugas',
        'role_label' => 'Petugas Posko'
    ],
    'admin' => [
        'id' => 1,
        'name' => 'Admin Utama',
        'email' => 'admin@campusfind.test',
        'role' => 'admin',
        'role_name' => 'admin',
        'role_label' => 'Administrator'
    ]
];

foreach ($rolesToTest as $roleName => $uData) {
    \App\Middleware\AuthMiddleware::login($uData);
    $_SERVER['REQUEST_URI'] = ($roleName === 'admin') ? '/admin' : '/dashboard';
    
    ob_start();
    require BASE_PATH . '/app/Views/layouts/app_shell.php';
    $roleHtml = ob_get_clean();
    
    $rDom = new DOMDocument();
    libxml_use_internal_errors(true);
    $rDom->loadHTML('<?xml encoding="utf-8" ?>' . $roleHtml);
    libxml_clear_errors();
    $rXpath = new DOMXPath($rDom);
    
    $rActiveLinks = $rXpath->query('//a[contains(@class, "cf-sidebar-link") and contains(@class, "active")]');
    if ($rActiveLinks->length === 1) {
        $lbl = $rActiveLinks->item(0)->getAttribute('data-label');
        echo "[PASS] Role {$roleName}: Exactly 1 active item '{$lbl}'\n";
    } else {
        $errors[] = "Role {$roleName}: Expected 1 active item, found " . $rActiveLinks->length;
    }
}


// ─────────────────────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────────────────────
echo "\n========================================================\n";
echo "SUMMARY: " . count($passes) . " passed, " . count($errors) . " failed.\n";
if (empty($errors)) {
    echo "ALL PAGE-00B.3.2 AUTOMATED VERIFICATIONS PASSED!\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "FAILED CHECKS:\n";
    foreach ($errors as $e) {
        echo " - {$e}\n";
    }
    echo "========================================================\n";
    exit(1);
}
