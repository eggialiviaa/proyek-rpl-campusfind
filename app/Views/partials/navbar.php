<?php
/**
 * app/Views/partials/navbar.php
 * Global Apple-like Clean Navigation Component (Prompt 24)
 *
 * Spesifikasi:
 * - Bar atas tipis (±48px, translucent + blur), menu teks kecil, TANPA sidebar.
 * - Server-side rendering menu HANYA untuk role yang sedang aktif (Tamu, Civitas, Petugas, Admin).
 * - Desktop hover dengan jeda anti-flicker (110ms / 150ms) & scrim blur background.
 * - Aksesibilitas: Keyboard (Enter, Space, ArrowDown, Esc, Tab), aria-current="page".
 * - Layar < 900px: Full-screen mobile panel dengan accordion & touch target ≥ 44px.
 * - Link "Keluar" berupa Form POST dengan token CSRF.
 * - Lonceng notifikasi in-app dengan indikator unread count di sisi kanan.
 * - Z-index: Modal AppDialog (1055/1050) > Nav Open (1045/1041) > C-Find (1040) > Base Nav (1030).
 */

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

$authUser = AuthMiddleware::user();
$userRole = $authUser ? strtolower(trim((string)($authUser['role_name'] ?? $authUser['role'] ?? 'civitas'))) : 'tamu';
$csrfToken = CsrfMiddleware::getToken();

// Ambil path URI saat ini untuk penandaan aria-current="page"
$currentUri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

// ─────────────────────────────────────────────────────────────
// 1. DEFINISI MENU SERVER-SIDE HANYA UNTUK ROLE AKTIF
// ─────────────────────────────────────────────────────────────
$roleMenus = [
    'tamu' => [
        ['l' => 'Beranda', 'h' => '/'],
        ['l' => 'Cara Kerja', 'h' => '/cara-kerja'],
        ['l' => 'Bantuan', 'cols' => [
            ['h' => 'Bantuan & Panduan', 'big' => true, 'links' => [
                ['t' => 'Panduan Alur Sistem', 'h' => '/cara-kerja'],
                ['t' => 'Tanya Asisten C-Find', 'h' => '/cara-kerja', 'is_cfind' => true],
            ]],
        ]],
        ['l' => 'Masuk', 'h' => '/login', 'end' => true],
    ],

    'civitas' => [
        ['l' => 'Beranda', 'h' => '/dashboard'],
        ['l' => 'Laporan', 'cols' => [
            ['h' => 'Buat Laporan', 'big' => true, 'links' => [
                ['t' => 'Lapor Barang Hilang', 'h' => '/reports/lost/create'],
                ['t' => 'Lapor Barang Ditemukan', 'h' => '/reports/found/create'],
            ]],
            ['h' => 'Pantau Laporan', 'big' => false, 'links' => [
                ['t' => 'Laporan Saya', 'h' => '/civitas/my-reports'],
            ]],
        ]],
        ['l' => 'Cari Barang', 'h' => '/search'],
        ['l' => 'Klaim Saya', 'h' => '/civitas/my-claims'],
        ['l' => 'Pesan', 'h' => '/chats'],
        ['l' => 'Akun', 'end' => true, 'cols' => [
            ['h' => 'Akun Pengguna', 'big' => true, 'links' => [
                ['t' => 'Profil & Notifikasi', 'h' => '/profile'],
                ['t' => 'Dasbor Utama', 'h' => '/dashboard'],
                ['t' => 'Keluar', 'is_logout' => true],
            ]],
        ]],
    ],

    'petugas' => [
        ['l' => 'Ringkasan', 'h' => '/dashboard'],
        ['l' => 'Laporan', 'cols' => [
            ['h' => 'Manajemen Laporan', 'big' => true, 'links' => [
                ['t' => 'Semua Laporan', 'h' => '/reports/manage'],
                ['t' => 'Laporan Kehilangan', 'h' => '/reports/manage?tab=lost'],
                ['t' => 'Laporan Temuan', 'h' => '/reports/manage?tab=found'],
            ]],
        ]],
        ['l' => 'Verifikasi Klaim', 'cols' => [
            ['h' => 'Verifikasi Klaim', 'big' => true, 'links' => [
                ['t' => 'Antrean masuk', 'h' => '/petugas/claims'],
                ['t' => 'Riwayat selesai', 'h' => '/petugas/claims?status=completed'],
            ]],
        ]],
        ['l' => 'Barang dan Gudang', 'cols' => [
            ['h' => 'Lost & Found Center', 'big' => true, 'links' => [
                ['t' => 'Inventaris Posko', 'h' => '/petugas/inventory'],
                ['t' => 'Menunggu Penyerahan', 'h' => '/reports/manage?tab=found&status=pending_handover'],
            ]],
            ['h' => 'Kelola Lokasi', 'big' => false, 'links' => [
                ['t' => 'Lokasi Penyimpanan', 'h' => '/petugas/storage-locations'],
            ]],
        ]],
        ['l' => 'Pesan', 'h' => '/chats'],
        ['l' => 'Akun', 'end' => true, 'cols' => [
            ['h' => 'Akun Petugas', 'big' => true, 'links' => [
                ['t' => 'Profil & Notifikasi', 'h' => '/profile'],
                ['t' => 'Dasbor Utama', 'h' => '/dashboard'],
                ['t' => 'Keluar', 'is_logout' => true],
            ]],
        ]],
    ],

    'admin' => [
        ['l' => 'Ringkasan', 'h' => '/admin'],
        ['l' => 'Pengguna', 'cols' => [
            ['h' => 'Kelola Pengguna', 'big' => true, 'links' => [
                ['t' => 'Daftar Pengguna', 'h' => '/admin/users'],
                ['t' => 'Impor Civitas', 'h' => '/admin/users/import'],
            ]],
        ]],
        ['l' => 'Laporan', 'cols' => [
            ['h' => 'Pengawasan Laporan', 'big' => true, 'links' => [
                ['t' => 'Semua Laporan', 'h' => '/reports/manage'],
                ['t' => 'Laporan Kehilangan', 'h' => '/reports/manage?tab=lost'],
                ['t' => 'Laporan Temuan', 'h' => '/reports/manage?tab=found'],
            ]],
        ]],
        ['l' => 'Master Data', 'cols' => [
            ['h' => 'Master Data Kampus', 'big' => true, 'links' => [
                ['t' => 'Kategori', 'h' => '/admin/categories'],
                ['t' => 'Lokasi', 'h' => '/admin/locations'],
                ['t' => 'Lokasi penyimpanan', 'h' => '/admin/storage-locations'],
            ]],
        ]],
        ['l' => 'Monitoring', 'cols' => [
            ['h' => 'Monitoring & Audit', 'big' => true, 'links' => [
                ['t' => 'Audit Log Keamanan', 'h' => '/admin/audit-logs'],
                ['t' => 'Riwayat Chat AI', 'h' => '/admin/chat-history'],
                ['t' => 'Kesiapan & Antrean Notifikasi', 'h' => '/admin/notification-settings'],
            ]],
        ]],
        ['l' => 'Pengaturan', 'cols' => [
            ['h' => 'Konfigurasi Sistem', 'big' => true, 'links' => [
                ['t' => 'Pengaturan Sistem & AI', 'h' => '/admin/settings'],
                ['t' => 'Pengaturan Notifikasi Email', 'h' => '/admin/notification-settings'],
            ]],
        ]],
        ['l' => 'Akun', 'end' => true, 'cols' => [
            ['h' => 'Akun Administrator', 'big' => true, 'links' => [
                ['t' => 'Profil & Notifikasi', 'h' => '/profile'],
                ['t' => 'Dasbor Utama', 'h' => '/dashboard'],
                ['t' => 'Keluar', 'is_logout' => true],
            ]],
        ]],
    ],
];

// Pastikan hanya konfigurasi role aktif yang diambil
$activeMenu = $roleMenus[$userRole] ?? $roleMenus['tamu'];

/**
 * Helper untuk menentukan apakah rute sedang aktif
 */
$isRouteActive = function (string $href, string $current) : bool {
    if ($href === '/' && $current === '/') return true;
    if ($href !== '/' && $href !== '#' && str_starts_with($current, $href)) return true;
    return false;
};

// Strict Role & Route Scope (Phase 4B-2):
// Smart Liquid Glass Navbar applies ONLY to unauthenticated Guest users on the landing page.
// Civitas, Petugas, Admin, and Guest on /login or /search preserve the existing navbar untouched.
$useSmartGuestNavbar = (!$authUser && $userRole === 'tamu' && !empty($isLandingPage));
?>

<?php if ($useSmartGuestNavbar): ?>
<!-- Hidden (0x0 layout, non-display-none so Chromium resolves url(#filter)) Optical SVG Filter -->
<svg class="cf-lg-svg-defs" aria-hidden="true" width="0" height="0" focusable="false">
    <defs>
        <filter id="cf-lg-nav-filter" x="-5%" y="-5%" width="110%" height="110%" color-interpolation-filters="sRGB">
            <feImage id="cf-lg-nav-map" x="0" y="0" width="100%" height="100%" preserveAspectRatio="none" result="dispMap" />
            <feGaussianBlur in="SourceGraphic" stdDeviation="1.35" result="softBg" />
            <feDisplacementMap in="softBg" in2="dispMap" scale="32" xChannelSelector="R" yChannelSelector="G" result="dispR" />
            <feColorMatrix in="dispR" type="matrix" values="1 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 1 0" result="chanR" />
            <feDisplacementMap in="softBg" in2="dispMap" scale="27" xChannelSelector="R" yChannelSelector="G" result="dispG" />
            <feColorMatrix in="dispG" type="matrix" values="0 0 0 0 0  0 1 0 0 0  0 0 0 0 0  0 0 0 1 0" result="chanG" />
            <feDisplacementMap in="softBg" in2="dispMap" scale="22" xChannelSelector="R" yChannelSelector="G" result="dispB" />
            <feColorMatrix in="dispB" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 1 0 0  0 0 0 1 0" result="chanB" />
            <feComposite in="chanR" in2="chanG" operator="arithmetic" k1="0" k2="1" k3="1" k4="0" result="chanRG" />
            <feComposite in="chanRG" in2="chanB" operator="arithmetic" k1="0" k2="1" k3="1" k4="0" result="refractedRgb" />
            <feComposite in="refractedRgb" in2="SourceGraphic" operator="atop" />
        </filter>
    </defs>
</svg>

<!-- Phase 4B-2: Smart Optical Liquid Glass Navbar (Guest Landing Page Only) -->
<header class="cf-guest-nav-dock" id="cf-guest-nav" role="banner">
    <div class="cf-guest-nav-shell">
        <!-- Left: Separate CampusFind Brand -->
        <div class="cf-guest-nav-left">
            <a class="cf-guest-brand"
               href="#hero"
               data-nav-target="#hero"
               aria-label="CampusFind — Home">
                <span class="cf-guest-brand-mark" aria-hidden="true"></span>
                <span>CampusFind</span>
            </a>
        </div>

        <!-- Center: Floating Optical Liquid Glass Capsule -->
        <nav class="cf-lg-capsule" id="cf-lg-capsule" aria-label="Main navigation">
            <div class="cf-lg-refraction-layer" id="cf-lg-nav-refraction" aria-hidden="true"></div>
            <div class="cf-lg-specular-layer" aria-hidden="true"></div>
            <div class="cf-lg-rim-layer" aria-hidden="true"></div>

            <div class="cf-lg-capsule-inner" id="cf-lg-capsule-inner">
                <span class="cf-lg-active-indicator" id="cf-lg-active-indicator" aria-hidden="true"></span>
                <ul class="cf-lg-nav-list" id="cf-lg-nav-list" role="menubar" aria-label="Primary landing links">
                    <li role="none">
                        <a role="menuitem"
                           href="#hero"
                           class="cf-lg-nav-link is-active"
                           data-nav-section="hero"
                           data-nav-target="#hero"
                           aria-current="location">
                            Home
                        </a>
                    </li>
                    <li role="none">
                        <a role="menuitem"
                           href="#recently-reported"
                           class="cf-lg-nav-link"
                           data-nav-section="recently-reported"
                           data-nav-target="#recently-reported">
                            Recently Found
                        </a>
                    </li>
                    <li role="none" class="cf-lg-help-item" id="cf-lg-help-item">
                        <button type="button"
                                id="cf-lg-help-btn"
                                class="cf-lg-nav-link cf-lg-help-btn"
                                role="menuitem"
                                aria-haspopup="true"
                                aria-expanded="false"
                                aria-controls="cf-lg-help-menu"
                                data-nav-section="how-it-works">
                            <span>Help</span>
                            <svg class="cf-lg-chevron" width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.5 4.5L6 8l3.5-3.5" />
                            </svg>
                        </button>

                        <div class="cf-lg-dropdown"
                             id="cf-lg-help-menu"
                             role="menu"
                             aria-labelledby="cf-lg-help-btn"
                             aria-hidden="true"
                             hidden>
                            <a role="menuitem"
                               href="#how-it-works"
                               class="cf-lg-dropdown-item"
                               id="cf-lg-link-hiw"
                               data-nav-target="#how-it-works">
                                <span class="cf-lg-dropdown-title">How It Works</span>
                                <span class="cf-lg-dropdown-sub">Step-by-step lost &amp; found guide</span>
                            </a>
                            <button type="button"
                                    role="menuitem"
                                    class="cf-lg-dropdown-item"
                                    id="cf-lg-link-cfind"
                                    data-open-cfind="1">
                                <span class="cf-lg-dropdown-title">AI Chatbot</span>
                                <span class="cf-lg-dropdown-sub">Ask C-Find virtual assistant</span>
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Right: Separate Login Button + Mobile Hamburger (< 900px) -->
        <div class="cf-guest-nav-right">
            <a href="/login"
               class="cf-guest-login-btn"
               id="cf-guest-login-btn">
                Login
            </a>

            <button class="cf-guest-burger"
                    id="cf-guest-burger"
                    type="button"
                    aria-label="Open menu"
                    aria-expanded="false"
                    aria-controls="cf-guest-mob">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path d="M2.5 6h13M2.5 12h13" />
                </svg>
            </button>
        </div>
    </div>
</header>

<!-- Guest Landing Mobile Overlay Navigation (< 900px) -->
<nav class="cf-guest-mob" id="cf-guest-mob" aria-label="Mobile navigation" aria-hidden="true" hidden>
    <div class="cf-guest-mob-inner">
        <a href="#hero" class="cf-guest-mob-link" data-nav-target="#hero">Home</a>
        <a href="#recently-reported" class="cf-guest-mob-link" data-nav-target="#recently-reported">Recently Found</a>

        <details class="cf-guest-mob-accordion" open>
            <summary>Help</summary>
            <div class="cf-guest-mob-sub">
                <a href="#how-it-works" data-nav-target="#how-it-works">How It Works</a>
                <button type="button" id="cf-guest-mob-cfind" data-open-cfind="1">AI Chatbot (C-Find)</button>
            </div>
        </details>

        <a href="/login" class="cf-guest-mob-login" id="cf-guest-mob-login">Login</a>
    </div>
</nav>

<?php else: ?>
<!-- ─────────────────────────────────────────────────────────────
     NAVBAR TOPBAR UTAMA (Apple-like Thin Translucent Bar)
     ───────────────────────────────────────────────────────────── -->
<header class="cf-nav" id="cf-nav" role="banner">
    <div class="cf-bar">
        <!-- Brand Logo -->
        <a class="cf-logo" href="<?= $authUser ? '/dashboard' : '/' ?>" aria-label="Campus Find — Halaman Utama">
            Campus Find
        </a>

        <!-- Desktop Menu Links (Server-Side Rendered) -->
        <ul class="cf-links" id="cf-links" role="menubar" aria-label="Menu utama">
            <?php foreach ($activeMenu as $idx => $item): ?>
                <?php if (!empty($item['cols'])): ?>
                    <li role="none">
                        <button type="button" 
                                role="menuitem" 
                                aria-haspopup="true" 
                                aria-expanded="false" 
                                aria-controls="cf-fly" 
                                data-i="<?= $idx ?>"
                                class="cf-nav-btn">
                            <?= htmlspecialchars($item['l'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </li>
                <?php else: 
                    $isActive = $isRouteActive($item['h'], $currentUri);
                    $isEndBtn = !empty($item['end']);
                ?>
                    <li role="none">
                        <a role="menuitem" 
                           href="<?= htmlspecialchars($item['h'], ENT_QUOTES, 'UTF-8') ?>" 
                           class="<?= $isActive ? 'active ' : '' ?><?= $isEndBtn ? 'cf-nav-cta' : '' ?>"
                           <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <?= htmlspecialchars($item['l'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <!-- Right Side: Lonceng Notifikasi & Mobile Hamburger -->
        <div class="cf-end">
            <?php if ($authUser): ?>
                <!-- Notifikasi In-App Bell Button -->
                <div class="cf-notif-wrapper">
                    <button type="button" 
                            id="cf-notif-btn" 
                            class="cf-notif-btn" 
                            aria-label="Notifikasi" 
                            aria-expanded="false"
                            aria-controls="cf-notif-popover">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>
                        </svg>
                        <i id="cf-notif-dot" class="d-none" aria-hidden="true"></i>
                    </button>

                    <!-- Notifikasi Popover Mini -->
                    <div class="cf-notif-popover" id="cf-notif-popover" aria-hidden="true">
                        <div class="cf-notif-head">
                            <span>Notifikasi</span>
                            <a href="/notifications" class="cf-notif-link">Lihat semua</a>
                        </div>
                        <div class="cf-notif-body" id="cf-notif-body">
                            <div class="cf-notif-empty" id="cf-notif-empty">Belum ada notifikasi baru</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Hamburger Button for Mobile (< 900px) -->
            <button class="cf-burger" id="cf-burger" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="cf-mob">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                    <path d="M2 6h14M2 12h14"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Desktop Flyout Sub-Menu Container (Server-Side Panes) -->
    <div class="cf-fly" id="cf-fly" aria-hidden="true">
        <div class="cf-fly-in" id="cf-fly-in">
            <?php foreach ($activeMenu as $idx => $item): ?>
                <?php if (!empty($item['cols'])): ?>
                    <div class="cf-fly-pane" data-pane="<?= $idx ?>" style="display:none;">
                        <?php foreach ($item['cols'] as $col): ?>
                            <div class="cf-col<?= !empty($col['big']) ? ' big' : '' ?>">
                                <h6><?= htmlspecialchars($col['h'], ENT_QUOTES, 'UTF-8') ?></h6>
                                <?php foreach ($col['links'] as $link): ?>
                                    <?php if (!empty($link['is_logout'])): ?>
                                        <form action="/logout" method="POST" class="cf-logout-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="cf-link-btn text-danger">Keluar</button>
                                        </form>
                                    <?php elseif (!empty($link['is_cfind'])): ?>
                                        <a href="<?= htmlspecialchars($link['h'], ENT_QUOTES, 'UTF-8') ?>" data-open-cfind="1">
                                            <?= htmlspecialchars($link['t'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php else: 
                                        $isSubActive = $isRouteActive($link['h'], $currentUri);
                                    ?>
                                        <a href="<?= htmlspecialchars($link['h'], ENT_QUOTES, 'UTF-8') ?>" <?= $isSubActive ? 'aria-current="page"' : '' ?>>
                                            <?= htmlspecialchars($link['t'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</header>

<!-- Scrim Background Blur Overlay -->
<div class="cf-scrim" id="cf-scrim" aria-hidden="true"></div>

<!-- Mobile Full-Screen Overlay Accordion Menu (< 900px) -->
<nav class="cf-m" id="cf-mob" aria-label="Menu seluler" aria-hidden="true">
    <?php foreach ($activeMenu as $idx => $item): ?>
        <?php if (!empty($item['cols'])): ?>
            <details>
                <summary><?= htmlspecialchars($item['l'], ENT_QUOTES, 'UTF-8') ?></summary>
                <div class="cf-m-sub">
                    <?php foreach ($item['cols'] as $col): ?>
                        <?php foreach ($col['links'] as $link): ?>
                            <?php if (!empty($link['is_logout'])): ?>
                                <form action="/logout" method="POST" class="cf-logout-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="cf-link-btn text-danger">Keluar</button>
                                </form>
                            <?php elseif (!empty($link['is_cfind'])): ?>
                                <a href="<?= htmlspecialchars($link['h'], ENT_QUOTES, 'UTF-8') ?>" data-open-cfind="1">
                                    <?= htmlspecialchars($link['t'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php else: 
                                $isSubActive = $isRouteActive($link['h'], $currentUri);
                            ?>
                                <a href="<?= htmlspecialchars($link['h'], ENT_QUOTES, 'UTF-8') ?>" <?= $isSubActive ? 'aria-current="page"' : '' ?>>
                                    <?= htmlspecialchars($link['t'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php else: 
            $isActive = $isRouteActive($item['h'], $currentUri);
        ?>
            <a href="<?= htmlspecialchars($item['h'], ENT_QUOTES, 'UTF-8') ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($item['l'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<!-- ─────────────────────────────────────────────────────────────
     STYLES: APPLE-LIKE DESIGN TOKENS & RESPONSIVE BEHAVIOR
     ───────────────────────────────────────────────────────────── -->
<style>
/* Global navigation styling — Design Tokens diimpor dari public/css/app.css */

/* Nav Base Sticky Bar */
.cf-nav {
    position: sticky;
    top: env(safe-area-inset-top, 0px);
    z-index: 1030;
    background: var(--bar);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--cf-border);
    font-family: var(--cf-font-sans);
    transition: background 0.2s ease, border-color 0.2s ease;
}
.cf-nav.cf-active-open {
    z-index: 1045; /* Di atas widget cfind (1040) tapi di bawah modal (1050/1055) */
}

.cf-bar {
    height: 48px;
    max-width: 1360px;
    margin: 0 auto;
    padding: 0 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    box-sizing: border-box;
}

.cf-logo {
    font-weight: 700;
    font-size: 14px;
    letter-spacing: -0.01em;
    white-space: nowrap;
    color: var(--cf-text-main);
    text-decoration: none;
    opacity: 0.95;
    transition: opacity 0.15s, color 0.15s;
}
.cf-logo:hover {
    opacity: 1;
    color: var(--cf-primary-dark);
}

/* Menu horizontal */
.cf-links {
    display: flex;
    align-items: center;
    flex: 1;
    justify-content: space-between;
    gap: 0;
    margin: 0 20px;
    padding: 0;
    list-style: none;
}
.cf-links > li {
    margin: 0;
    padding: 0;
}
.cf-links > li > a,
.cf-links > li > button {
    font-family: inherit;
    font-size: 12.5px;
    font-weight: 500;
    color: var(--cf-text-main);
    opacity: 0.88;
    background: none;
    border: 0;
    padding: 13px 10px;
    cursor: pointer;
    display: block;
    border-radius: 6px;
    text-decoration: none;
    line-height: 1;
    transition: opacity 0.15s, color 0.15s, background-color 0.15s;
}
.cf-links > li > a:hover,
.cf-links > li > button:hover,
.cf-links > li > [aria-expanded="true"] {
    opacity: 1;
    color: var(--cf-primary);
    background-color: var(--cf-accent-light);
}
.cf-links > li > a[aria-current="page"] {
    opacity: 1;
    font-weight: 600;
    color: var(--cf-primary);
    position: relative;
}
.cf-links > li > a[aria-current="page"]::after {
    content: '';
    position: absolute;
    bottom: 4px;
    left: 10px;
    right: 10px;
    height: 2px;
    background: var(--cf-accent);
    border-radius: 2px;
}
.cf-links > li > a.cf-nav-cta {
    background: var(--cf-primary);
    color: #ffffff;
    padding: 7px 16px;
    border-radius: 999px;
    opacity: 1;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(46, 61, 40, 0.20);
}
.cf-links > li > a.cf-nav-cta:hover {
    background: var(--cf-primary-dark);
    opacity: 1;
    color: #ffffff;
}
}

/* Right Section */
.cf-end {
    display: flex;
    align-items: center;
    gap: 8px;
}
.cf-notif-wrapper {
    position: relative;
}
.cf-notif-btn {
    background: none;
    border: 0;
    color: var(--tx);
    opacity: 0.86;
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    cursor: pointer;
    position: relative;
    border-radius: 6px;
    padding: 0;
}
.cf-notif-btn:hover {
    opacity: 1;
}
.cf-notif-btn i {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--cf-accent);
}

/* Notifikasi Popover */
.cf-notif-popover {
    position: absolute;
    top: 40px;
    right: 0;
    width: 300px;
    background: var(--bg);
    border: 1px solid var(--ln);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
    display: none;
    z-index: 1046;
    overflow: hidden;
}
.cf-notif-popover.on {
    display: block;
}
.cf-notif-head {
    padding: 10px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--ln);
    font-size: 12px;
    font-weight: 600;
}
.cf-notif-head a {
    color: var(--ac);
    font-weight: 400;
    text-decoration: none;
}
.cf-notif-body {
    max-height: 260px;
    overflow-y: auto;
    font-size: 13px;
}
.cf-notif-empty {
    padding: 24px 16px;
    text-align: center;
    color: var(--mu);
    font-size: 12px;
}
.cf-notif-item {
    display: block;
    padding: 10px 14px;
    border-bottom: 1px solid var(--ln);
    color: var(--tx);
    text-decoration: none;
}
.cf-notif-item:hover {
    background: var(--bg2);
}
.cf-notif-item.unread {
    background: var(--cf-accent-subtle);
}

/* Burger Mobile Button */
.cf-burger {
    display: none;
    background: none;
    border: 0;
    width: 44px;
    height: 44px;
    color: var(--tx);
    cursor: pointer;
    padding: 0;
}

/* Desktop Flyout Sub-menu */
.cf-fly {
    position: absolute;
    left: 0;
    right: 0;
    top: 48px;
    background: var(--bg2);
    border-bottom: 1px solid var(--ln);
    visibility: hidden;
    opacity: 0;
    transform: translateY(-6px);
    transition: opacity 0.2s, transform 0.2s, visibility 0s 0.2s;
    z-index: 1045;
}
.cf-fly.on {
    visibility: visible;
    opacity: 1;
    transform: none;
    transition: opacity 0.2s, transform 0.2s;
}
.cf-fly-in {
    max-width: 1360px;
    margin: 0 auto;
    padding: 32px 1.5rem 48px;
    min-height: 180px;
}
.cf-fly-pane {
    display: flex;
    gap: 64px;
}
.cf-col h6 {
    margin: 0 0 12px;
    font-size: 12px;
    font-weight: 400;
    color: var(--mu);
    text-transform: none;
    letter-spacing: 0;
}
.cf-col a,
.cf-col button.cf-link-btn {
    display: block;
    font-size: 13px;
    font-weight: 600;
    padding: 5px 0;
    opacity: 0.86;
    color: var(--tx);
    text-decoration: none;
    background: none;
    border: 0;
    text-align: left;
    width: 100%;
    cursor: pointer;
    font-family: inherit;
    transition: opacity 0.15s, color 0.15s;
}
.cf-col a:hover,
.cf-col button.cf-link-btn:hover {
    opacity: 1;
    color: var(--ac);
}
.cf-col.big a,
.cf-col.big button.cf-link-btn {
    font-size: 28px;
    line-height: 1.14;
    padding: 5px 0;
    letter-spacing: -0.01em;
}

/* Scrim Blur Overlay */
.cf-scrim {
    position: fixed;
    inset: 0;
    z-index: 1041; /* Di atas widget cfind (1040), di bawah nav aktif (1045) dan modal (1050+) */
    background: var(--scrim);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s, visibility 0s 0.2s;
}
.cf-scrim.on {
    opacity: 1;
    visibility: visible;
    transition: opacity 0.2s;
}

/* Mobile Overlay Menu (< 900px) */
.cf-m {
    display: none;
}

@media (max-width: 899px) {
    .cf-links {
        display: none;
    }
    .cf-burger {
        display: block;
        margin-right: -10px;
    }
    .cf-logo {
        margin: 0 auto 0 0;
    }
    .cf-fly {
        display: none !important;
    }
    .cf-m.on {
        display: block;
        position: fixed;
        inset: 44px 0 0;
        z-index: 1048; /* Di bawah modal (1050+) */
        background: var(--bg);
        overflow-y: auto;
        padding: 8px 20px 48px;
        box-sizing: border-box;
    }
    .cf-m details {
        border-bottom: 1px solid var(--ln);
    }
    .cf-m summary,
    .cf-m > a {
        display: block;
        list-style: none;
        font-size: 22px;
        font-weight: 600;
        padding: 14px 0;
        min-height: 48px;
        cursor: pointer;
        color: var(--tx);
        text-decoration: none;
        box-sizing: border-box;
    }
    .cf-m summary::-webkit-details-marker {
        display: none;
    }
    .cf-m details a,
    .cf-m details button.cf-link-btn {
        display: block;
        padding: 11px 0;
        color: var(--mu);
        font-size: 17px;
        font-weight: 400;
        min-height: 44px;
        text-decoration: none;
        background: none;
        border: 0;
        text-align: left;
        width: 100%;
        cursor: pointer;
        font-family: inherit;
        box-sizing: border-box;
    }
    .cf-m details a:hover,
    .cf-m details button.cf-link-btn:hover {
        color: var(--ac);
    }
}

/* Accessibility Focus Ring */
.cf-links > li > a:focus-visible,
.cf-links > li > button:focus-visible,
.cf-fly a:focus-visible,
.cf-fly button:focus-visible,
.cf-burger:focus-visible,
.cf-notif-btn:focus-visible {
    outline: 2px solid var(--ac);
    outline-offset: -2px;
}

/* Reduced Motion Support */
@media (prefers-reduced-motion: reduce) {
    .cf-fly,
    .cf-scrim,
    .cf-nav {
        transition: none !important;
        animation: none !important;
    }
}
</style>

<!-- ─────────────────────────────────────────────────────────────
     JAVASCRIPT: HOVER WITH DEBOUNCE, KEYBOARD & TOUCH NAVIGATION
     ───────────────────────────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    const nav = document.getElementById('cf-nav');
    const links = document.getElementById('cf-links');
    const fly = document.getElementById('cf-fly');
    const flyIn = document.getElementById('cf-fly-in');
    const scrim = document.getElementById('cf-scrim');
    const mob = document.getElementById('cf-mob');
    const burger = document.getElementById('cf-burger');
    const notifBtn = document.getElementById('cf-notif-btn');
    const notifPopover = document.getElementById('cf-notif-popover');
    const notifDot = document.getElementById('cf-notif-dot');
    const notifBody = document.getElementById('cf-notif-body');
    const notifEmpty = document.getElementById('cf-notif-empty');

    let openIdx = -1;
    let tIn = 0;
    let tOut = 0;

    /**
     * Buka panel sub-menu desktop flyout
     */
    function openFlyout(idx) {
        clearTimeout(tOut);
        const pane = flyIn.querySelector(`.cf-fly-pane[data-pane="${idx}"]`);
        if (!pane) return;

        // Sembunyikan pane lain & tampilkan pane yang dituju
        flyIn.querySelectorAll('.cf-fly-pane').forEach(p => p.style.display = 'none');
        pane.style.display = 'flex';

        fly.classList.add('on');
        fly.setAttribute('aria-hidden', 'false');
        scrim.classList.add('on');
        nav.classList.add('cf-active-open');
        openIdx = idx;

        // Tandai aria-expanded
        links.querySelectorAll('[data-i]').forEach(b => {
            b.setAttribute('aria-expanded', String(b.dataset.i === String(idx)));
        });

        // Tutup notif popover jika sedang terbuka
        closeNotif();
    }

    /**
     * Tutup panel flyout dengan toleransi jeda
     */
    function closeFlyout(now) {
        clearTimeout(tIn);
        const executeClose = () => {
            fly.classList.remove('on');
            fly.setAttribute('aria-hidden', 'true');
            scrim.classList.remove('on');
            nav.classList.remove('cf-active-open');
            openIdx = -1;
            links.querySelectorAll('[data-i]').forEach(b => b.setAttribute('aria-expanded', 'false'));
        };

        if (now) {
            executeClose();
        } else {
            clearTimeout(tOut);
            tOut = setTimeout(executeClose, 150);
        }
    }

    // ── Desktop Hover Interaksi (Hanya Pointer Mouse) ────────────
    if (links) {
        links.addEventListener('pointerover', function (e) {
            if (e.pointerType !== 'mouse') return;
            const btn = e.target.closest('[data-i]');
            clearTimeout(tIn);
            if (btn) {
                const idx = +btn.dataset.i;
                tIn = setTimeout(() => openFlyout(idx), openIdx > -1 ? 0 : 110);
            } else if (e.target.closest('a') && openIdx > -1) {
                closeFlyout();
            }
        });

        links.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-i]');
            if (btn) {
                e.preventDefault();
                const idx = +btn.dataset.i;
                idx === openIdx ? closeFlyout(true) : openFlyout(idx);
            }
        });

        links.addEventListener('keydown', function (e) {
            const btn = e.target.closest('[data-i]');
            if (btn && (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                const idx = +btn.dataset.i;
                openFlyout(idx);
                const pane = flyIn.querySelector(`.cf-fly-pane[data-pane="${idx}"]`);
                const firstInteractive = pane ? pane.querySelector('a, button') : null;
                if (firstInteractive) firstInteractive.focus();
            }
        });
    }

    if (nav) {
        nav.addEventListener('pointerleave', function (e) {
            if (e.pointerType === 'mouse') closeFlyout();
        });
    }

    if (fly) {
        fly.addEventListener('pointerenter', function () {
            clearTimeout(tOut);
        });
    }

    // ── Global Keyboard (Escape) ─────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const was = openIdx;
            closeFlyout(true);
            setMobile(false);
            closeNotif();
            if (was > -1 && links) {
                const triggerBtn = links.querySelector(`[data-i="${was}"]`);
                if (triggerBtn) triggerBtn.focus();
            }
        }
    });

    if (scrim) {
        scrim.addEventListener('click', function () {
            closeFlyout(true);
            closeNotif();
        });
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#cf-nav') && !e.target.closest('#cf-mob')) {
            closeFlyout(true);
            closeNotif();
        }
    });

    // ── Mobile Menu Toggle (< 900px) ─────────────────────────────
    function setMobile(on) {
        if (!mob || !burger) return;
        mob.classList.toggle('on', on);
        mob.setAttribute('aria-hidden', String(!on));
        burger.setAttribute('aria-expanded', String(on));
        burger.setAttribute('aria-label', on ? 'Tutup menu' : 'Buka menu');
        document.body.style.overflow = on ? 'hidden' : '';
        if (on) closeFlyout(true);
    }

    if (burger) {
        burger.addEventListener('click', function () {
            setMobile(!mob.classList.contains('on'));
        });
    }

    window.matchMedia('(min-width: 900px)').addEventListener('change', function (e) {
        if (e.matches) setMobile(false);
    });

    // ── C-Find Widget Interoperabilitas ──────────────────────────
    document.addEventListener('click', function (e) {
        const cfindLink = e.target.closest('[data-open-cfind]');
        if (cfindLink) {
            e.preventDefault();
            closeFlyout(true);
            setMobile(false);
            if (typeof window.openCFind === 'function') {
                window.openCFind(true);
            } else {
                const launcher = document.getElementById('cfind-launcher');
                if (launcher) {
                    launcher.click();
                } else {
                    window.location.href = '/cara-kerja';
                }
            }
        }
    });

    // ── Notifikasi In-App Polling & Popover ───────────────────────
    function closeNotif() {
        if (notifPopover) {
            notifPopover.classList.remove('on');
            notifPopover.setAttribute('aria-hidden', 'true');
        }
        if (notifBtn) {
            notifBtn.setAttribute('aria-expanded', 'false');
        }
    }

    if (notifBtn && notifPopover) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = notifPopover.classList.contains('on');
            if (isOpen) {
                closeNotif();
            } else {
                closeFlyout(true);
                notifPopover.classList.add('on');
                notifPopover.setAttribute('aria-hidden', 'false');
                notifBtn.setAttribute('aria-expanded', 'true');
                fetchRecentNotifs();
            }
        });
    }

    function fetchRecentNotifs() {
        if (!notifBody) return;
        fetch('/notifications/recent?limit=5', { credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data || !data.success) return;
                updateNotifIndicator(data.unread_count || 0);

                const list = data.notifications || [];
                if (list.length === 0) {
                    if (notifEmpty) notifEmpty.style.display = 'block';
                    return;
                }
                if (notifEmpty) notifEmpty.style.display = 'none';

                // Bersihkan konten lama selain empty state
                [...notifBody.children].forEach(child => {
                    if (child !== notifEmpty) child.remove();
                });

                list.forEach(n => {
                    const p = n.payload || {};
                    const a = document.createElement('a');
                    a.href = p.url || '/notifications';
                    a.className = 'cf-notif-item' + (!n.is_read ? ' unread' : '');
                    a.innerHTML = `<strong>${escHtml(p.title || 'Notifikasi')}</strong><div style="font-size:11px;color:var(--mu);">${escHtml(p.message || '')}</div>`;
                    notifBody.appendChild(a);
                });
            })
            .catch(() => {});
    }

    function updateNotifIndicator(unreadCount) {
        if (!notifBtn) return;
        if (unreadCount > 0) {
            if (notifDot) notifDot.classList.remove('d-none');
            notifBtn.setAttribute('aria-label', `Notifikasi, ${unreadCount} belum dibaca`);
        } else {
            if (notifDot) notifDot.classList.add('d-none');
            notifBtn.setAttribute('aria-label', 'Notifikasi');
        }
    }

    function pollUnreadCount() {
        if (!notifBtn) return;
        fetch('/notifications/unread-count', { credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (data && typeof data.unread_count !== 'undefined') {
                    updateNotifIndicator(data.unread_count);
                }
            })
            .catch(() => {});
    }

    function escHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    // Jalankan polling saat halaman siap jika user terautentikasi
    if (notifBtn) {
        document.addEventListener('DOMContentLoaded', function () {
            pollUnreadCount();
            setInterval(pollUnreadCount, 30000); // Poll setiap 30 detik
        });
    }

})();
</script>
<?php endif; ?>
