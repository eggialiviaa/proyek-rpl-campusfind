<?php
/**
 * app/Views/partials/topbar.php
 * Compact Authenticated Topbar for CampusFind Application Shell
 *
 * Elemen:
 * - Tombol hamburger mobile untuk off-canvas drawer
 * - Judul halaman dinamis & breadcrumb
 * - Pintasan pencarian (/search)
 * - Lonceng notifikasi (/notifications) dengan indikator unread
 * - Profil pengguna & menu dropdown dengan tombol keluar aman (POST + CSRF)
 */

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

$authUser = AuthMiddleware::user();
$userRole = $authUser ? strtolower(trim((string)($authUser['role_name'] ?? $authUser['role'] ?? 'civitas'))) : 'civitas';
$userName = htmlspecialchars($authUser['name'] ?? 'Pengguna', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($authUser['email'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken = CsrfMiddleware::getToken();

// Format inisial avatar
$nameParts = explode(' ', trim($authUser['name'] ?? 'U'));
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

// Pembersihan judul halaman untuk tampilan ringkas topbar
$cleanTitle = htmlspecialchars($pageTitle ?? 'Panel Pengelolaan', ENT_QUOTES, 'UTF-8');
if (str_contains($cleanTitle, ' — ')) {
    $cleanTitle = trim(explode(' — ', $cleanTitle)[0]);
}

$roleLabel = match ($userRole) {
    'admin'   => 'Administrator',
    'petugas' => 'Petugas Posko',
    'civitas' => 'Civitas',
    default   => 'Pengguna',
};

$roleBadgeClass = match ($userRole) {
    'admin'   => 'cf-badge cf-badge-danger',
    'petugas' => 'cf-badge cf-badge-warning',
    'civitas' => 'cf-badge cf-badge-info',
    default   => 'cf-badge cf-badge-neutral',
};

$homeUrl = ($userRole === 'admin') ? '/admin' : '/dashboard';
?>

<header class="cf-topbar" id="cf-topbar" role="banner">
    <div class="cf-topbar-inner">
        <!-- Sisi Kiri: Hamburger Mobile & Judul Halaman -->
        <div class="cf-topbar-left">
            <!-- Mobile Toggle Hamburger Button -->
            <button type="button" 
                    class="cf-topbar-btn cf-topbar-mobile-toggle d-lg-none" 
                    id="cf-mobile-toggle" 
                    aria-label="Buka Navigasi Sistem" 
                    aria-controls="cf-sidebar" 
                    aria-expanded="false">
                <i data-lucide="menu"></i>
            </button>

            <!-- Breadcrumb / Header Title -->
            <div class="cf-topbar-title-block">
                <span class="cf-topbar-breadcrumb-role <?= $roleBadgeClass ?> d-none d-sm-inline-flex">
                    <?= $roleLabel ?>
                </span>
                <span class="cf-topbar-title h6 mb-0 fw-semibold text-truncate" title="<?= $cleanTitle ?>">
                    <?= $cleanTitle ?>
                </span>
            </div>
        </div>

        <!-- Sisi Kanan: Search, Notifikasi & Profil Dropdown -->
        <div class="cf-topbar-right">
            <!-- Pintasan Pencarian Global -->
            <a href="/search" 
               class="cf-topbar-btn" 
               title="Cari Laporan &amp; Barang Kampus" 
               aria-label="Cari Barang">
                <i data-lucide="search"></i>
            </a>

            <!-- Lonceng Notifikasi Terpadu -->
            <?php
            $sessionNotifCount = (int)($_SESSION['_unread_counts']['notif'] ?? 0);
            $hasSessionNotif = ($sessionNotifCount > 0);
            ?>
            <a href="/notifications" 
               class="cf-topbar-btn position-relative" 
               id="cf-topbar-notif-btn" 
               title="Pusat Notifikasi" 
               aria-label="Pusat Notifikasi">
                <i data-lucide="bell"></i>
                <span class="cf-topbar-notif-indicator<?= $hasSessionNotif ? '' : ' d-none' ?>" 
                      id="cf-topbar-notif-badge" 
                      aria-hidden="true">
                </span>
            </a>

            <!-- Dropdown Profil Pengguna (Apple Liquid Glass) -->
            <div class="dropdown cf-topbar-user-dropdown">
                <button type="button" 
                        class="cf-topbar-user-btn" 
                        id="cf-topbar-user-menu" 
                        data-bs-toggle="dropdown" 
                        aria-expanded="false" 
                        aria-label="Menu Akun Pengguna">
                    <div class="cf-topbar-avatar" aria-hidden="true">
                        <?= $initials ?>
                    </div>
                    <span class="cf-topbar-user-name d-none d-md-inline-block text-truncate">
                        <?= $userName ?>
                    </span>
                    <i data-lucide="chevron-down" class="cf-topbar-caret d-none d-md-inline-block"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end cf-user-dropdown-menu" 
                    aria-labelledby="cf-topbar-user-menu">
                    <!-- Ringkasan Info Pengguna -->
                    <li class="px-3 py-2 border-bottom border-secondary border-opacity-10 mb-2">
                        <div class="fw-semibold cf-user-menu-name text-truncate"><?= $userName ?></div>
                        <div class="small cf-user-menu-email text-truncate font-monospace" style="font-size: 0.78rem;"><?= $userEmail ?></div>
                        <span class="<?= $roleBadgeClass ?> mt-1 font-sans" style="font-size: 0.7rem;">
                            <?= $roleLabel ?>
                        </span>
                    </li>

                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= $homeUrl ?>">
                            <i data-lucide="layout-dashboard"></i>
                            <span>Dasbor Utama</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="/profile">
                            <i data-lucide="user"></i>
                            <span>Profil &amp; Keamanan</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="/notifications">
                            <i data-lucide="bell"></i>
                            <span>Semua Notifikasi</span>
                        </a>
                    </li>

                    <li><hr class="dropdown-divider my-2"></li>

                    <!-- Tombol Keluar dengan POST + CSRF -->
                    <li>
                        <form action="/logout" method="POST" class="m-0 p-0">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2 text-danger hover-danger">
                                <i data-lucide="log-out"></i>
                                <span>Keluar dari Akun</span>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>

