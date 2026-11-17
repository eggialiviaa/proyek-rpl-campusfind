<?php
/**
 * app/Views/partials/sidebar.php
 * Unified Liquid Glass Sidebar Component for CampusFind Authenticated Application Shell
 *
 * Mendukung ketiga role terautentikasi (Civitas, Petugas, Admin) dengan:
 * - Dark Navy Liquid Glass surface (#14283C dengan progressive blur & fallback)
 * - Soft White active navigation pill & Cognac active indicator (#A77B5B)
 * - Expandable (252px) dan Collapsed (74px) di desktop dengan state persistence
 * - Mobile off-canvas drawer (<992px) dengan backdrop dismissal & touch-friendly tap targets
 * - Server-side role-aware rendering berdasarkan spesifikasi PAGE-00A.1
 */

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

$authUser = AuthMiddleware::user();
$userRole = $authUser ? strtolower(trim((string)($authUser['role_name'] ?? $authUser['role'] ?? 'civitas'))) : 'civitas';
$userName = htmlspecialchars($authUser['name'] ?? 'Pengguna', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($authUser['email'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken = CsrfMiddleware::getToken();

// Ambil path dan query parameter untuk pencocokan status aktif yang presisi
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$currentPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$currentStatus = $_GET['status'] ?? null;
$sessionUnreadCounts = $_SESSION['_unread_counts'] ?? [];

// Tentukan inisial untuk avatar pengguna
$nameParts = explode(' ', trim($authUser['name'] ?? 'U'));
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

// Label peran ramah pengguna
$roleLabel = match ($userRole) {
    'admin'   => 'Administrator',
    'petugas' => 'Petugas Posko',
    'civitas' => 'Civitas Kampus',
    default   => 'Pengguna',
};

// URL Beranda sesuai peran
$homeUrl = ($userRole === 'admin') ? '/admin' : '/dashboard';

// Lucide Icon Mapping for Consistent Outline Aesthetics (stroke-width: 1.65px)
$biToLucideMap = [
    'bi-grid-fill'                    => 'layout-dashboard',
    'bi-collection-fill'              => 'folder-archive',
    'bi-patch-check-fill'             => 'shield-check',
    'bi-plus-circle-fill'             => 'plus-circle',
    'bi-box-arrow-in-down'            => 'package-plus',
    'bi-search'                       => 'search',
    'bi-question-circle'              => 'help-circle',
    'bi-check2-circle'                => 'check-circle-2',
    'bi-chat-dots-fill'               => 'message-square',
    'bi-bell-fill'                    => 'bell',
    'bi-person-gear'                  => 'user-cog',
    'bi-speedometer2'                 => 'gauge',
    'bi-journal-check'                => 'clipboard-check',
    'bi-boxes'                        => 'package',
    'bi-archive-fill'                 => 'archive',
    'bi-shield-check'                 => 'shield-check',
    'bi-person-check-fill'            => 'user-check',
    'bi-person-circle'                => 'user',
    'bi-graph-up-arrow'               => 'trending-up',
    'bi-journal-text'                 => 'file-text',
    'bi-shield-lock-fill'             => 'shield-alert',
    'bi-people-fill'                  => 'users',
    'bi-file-earmark-spreadsheet-fill'=> 'file-spreadsheet',
    'bi-tags-fill'                    => 'tags',
    'bi-building'                     => 'building',
    'bi-robot'                        => 'bot',
    'bi-sliders'                      => 'sliders',
    'bi-envelope-check-fill'          => 'mail-check',
    'bi-box-arrow-right'              => 'log-out',
    'bi-chevron-left'                 => 'chevron-left',
    'bi-chevron-right'                => 'chevron-right',
];

// ─────────────────────────────────────────────────────────────
// DEFINISI STRUKTUR MENU BERDASARKAN SPESIFIKASI PAGE-00A.1
// ─────────────────────────────────────────────────────────────
$sidebarMenuGroups = [];

if ($userRole === 'civitas') {
    $sidebarMenuGroups = [
        'Aktivitas Saya' => [
            [
                'label'   => 'Dasbor Utama',
                'route'   => '/dashboard',
                'icon'    => 'bi-grid-fill',
                'active'  => ($currentPath === '/dashboard'),
            ],
            [
                'label'   => 'Laporan Saya',
                'route'   => '/civitas/my-reports',
                'icon'    => 'bi-collection-fill',
                'active'  => (str_starts_with($currentPath, '/civitas/my-reports') || str_starts_with($currentPath, '/ai-match/lost')),
            ],
            [
                'label'   => 'Klaim Saya',
                'route'   => '/civitas/my-claims',
                'icon'    => 'bi-patch-check-fill',
                'active'  => (str_starts_with($currentPath, '/civitas/my-claims') || str_starts_with($currentPath, '/claims')),
            ],
        ],
        'Lapor & Cari' => [
            [
                'label'   => 'Lapor Barang Hilang',
                'route'   => '/reports/lost/create',
                'icon'    => 'bi-plus-circle-fill',
                'active'  => ($currentPath === '/reports/lost/create'),
            ],
            [
                'label'   => 'Lapor Barang Temuan',
                'route'   => '/reports/found/create',
                'icon'    => 'bi-box-arrow-in-down',
                'active'  => ($currentPath === '/reports/found/create'),
            ],
            [
                'label'   => 'Cari Barang Kampus',
                'route'   => '/search',
                'icon'    => 'bi-search',
                'active'  => ($currentPath === '/search'),
            ],
            [
                'label'   => 'Katalog Kehilangan',
                'route'   => '/reports/lost',
                'icon'    => 'bi-question-circle',
                'active'  => ($currentPath === '/reports/lost' || preg_match('#^/reports/lost/\d+#', $currentPath)),
            ],
            [
                'label'   => 'Katalog Temuan',
                'route'   => '/reports/found',
                'icon'    => 'bi-check2-circle',
                'active'  => ($currentPath === '/reports/found' || preg_match('#^/reports/found/\d+#', $currentPath)),
            ],
        ],
        'Komunikasi & Akun' => [
            [
                'label'   => 'Pesan Masuk',
                'route'   => '/chats',
                'icon'    => 'bi-chat-dots-fill',
                'active'  => str_starts_with($currentPath, '/chats'),
                'badge'   => 'chats',
            ],
            [
                'label'   => 'Pusat Notifikasi',
                'route'   => '/notifications',
                'icon'    => 'bi-bell-fill',
                'active'  => str_starts_with($currentPath, '/notifications'),
                'badge'   => 'notif',
            ],
            [
                'label'   => 'Pengaturan Akun',
                'route'   => '/profile',
                'icon'    => 'bi-person-gear',
                'active'  => str_starts_with($currentPath, '/profile'),
            ],
        ],
    ];
} elseif ($userRole === 'petugas') {
    $sidebarMenuGroups = [
        'Operasional Utama' => [
            [
                'label'   => 'Ringkasan Posko',
                'route'   => '/dashboard',
                'icon'    => 'bi-speedometer2',
                'active'  => ($currentPath === '/dashboard'),
            ],
            [
                'label'   => 'Kelola Semua Laporan',
                'route'   => '/reports/manage',
                'icon'    => 'bi-journal-check',
                // Aktif hanya bila bukan filter khusus pending_handover
                'active'  => (str_starts_with($currentPath, '/reports/manage') && $currentStatus !== 'pending_handover')
                             || (preg_match('#^/reports/(lost|found)/\d+#', $currentPath)),
            ],
        ],
        'Gudang & Inventaris' => [
            [
                'label'   => 'Inventaris Posko',
                'route'   => '/petugas/inventory',
                'icon'    => 'bi-boxes',
                'active'  => (str_starts_with($currentPath, '/petugas/inventory') || str_starts_with($currentPath, '/petugas/items/')),
            ],
            [
                'label'   => 'Rak Penyimpanan',
                'route'   => '/petugas/storage-locations',
                'icon'    => 'bi-archive-fill',
                'active'  => str_starts_with($currentPath, '/petugas/storage-locations'),
            ],
        ],
        'Klaim & Serah Terima' => [
            [
                'label'   => 'Antrean Verifikasi Klaim',
                'route'   => '/petugas/claims',
                'icon'    => 'bi-shield-check',
                'active'  => str_starts_with($currentPath, '/petugas/claims'),
            ],
            [
                'label'   => 'Menunggu Penyerahan',
                'route'   => '/reports/manage?tab=found&status=pending_handover',
                'icon'    => 'bi-person-check-fill',
                // Aktif bila berada di reports/manage dengan status=pending_handover atau di form intake / redirect handover
                'active'  => (str_starts_with($currentPath, '/reports/manage') && $currentStatus === 'pending_handover')
                             || str_starts_with($currentPath, '/petugas/intake')
                             || ($currentPath === '/petugas/handover'),
            ],
        ],
        'Komunikasi & Akun' => [
            [
                'label'   => 'Pesan Tiket',
                'route'   => '/chats',
                'icon'    => 'bi-chat-dots-fill',
                'active'  => str_starts_with($currentPath, '/chats'),
                'badge'   => 'chats',
            ],
            [
                'label'   => 'Notifikasi Posko',
                'route'   => '/notifications',
                'icon'    => 'bi-bell-fill',
                'active'  => str_starts_with($currentPath, '/notifications'),
                'badge'   => 'notif',
            ],
            [
                'label'   => 'Profil Petugas',
                'route'   => '/profile',
                'icon'    => 'bi-person-circle',
                'active'  => str_starts_with($currentPath, '/profile'),
            ],
        ],
    ];
} elseif ($userRole === 'admin') {
    $sidebarMenuGroups = [
        'Pengawasan Sistem' => [
            [
                'label'   => 'Ringkasan Eksekutif',
                'route'   => '/admin',
                'icon'    => 'bi-graph-up-arrow',
                'active'  => ($currentPath === '/admin' || $currentPath === '/admin/dashboard'),
            ],
            [
                'label'   => 'Pengawasan Laporan',
                'route'   => '/reports/manage',
                'icon'    => 'bi-journal-text',
                'active'  => str_starts_with($currentPath, '/reports/manage') || (preg_match('#^/reports/(lost|found)/\d+#', $currentPath)),
            ],
            [
                'label'   => 'Audit Trail Keamanan',
                'route'   => '/admin/audit-logs',
                'icon'    => 'bi-shield-lock-fill',
                'active'  => str_starts_with($currentPath, '/admin/audit-logs'),
            ],
        ],
        'Manajemen Pengguna' => [
            [
                'label'   => 'Direktori Pengguna',
                'route'   => '/admin/users',
                'icon'    => 'bi-people-fill',
                'active'  => ($currentPath === '/admin/users'),
            ],
            [
                'label'   => 'Impor Roster Civitas',
                'route'   => '/admin/users/import',
                'icon'    => 'bi-file-earmark-spreadsheet-fill',
                'active'  => str_starts_with($currentPath, '/admin/users/import'),
            ],
        ],
        'Master Data Kampus' => [
            [
                'label'   => 'Kategori Barang',
                'route'   => '/admin/categories',
                'icon'    => 'bi-tags-fill',
                'active'  => str_starts_with($currentPath, '/admin/categories'),
            ],
            [
                'label'   => 'Gedung & Lokasi Kampus',
                'route'   => '/admin/locations',
                'icon'    => 'bi-building',
                'active'  => str_starts_with($currentPath, '/admin/locations'),
            ],
            [
                'label'   => 'Master Rak Penyimpanan',
                'route'   => '/admin/storage-locations',
                'icon'    => 'bi-archive-fill',
                'active'  => str_starts_with($currentPath, '/admin/storage-locations'),
            ],
        ],
        'AI & Integrasi' => [
            [
                'label'   => 'Riwayat Asisten C-Find',
                'route'   => '/admin/chat-history',
                'icon'    => 'bi-robot',
                'active'  => str_starts_with($currentPath, '/admin/chat-history'),
            ],
            [
                'label'   => 'Pengaturan Sistem & AI',
                'route'   => '/admin/settings',
                'icon'    => 'bi-sliders',
                'active'  => str_starts_with($currentPath, '/admin/settings'),
            ],
            [
                'label'   => 'Gateway Notifikasi',
                'route'   => '/admin/notification-settings',
                'icon'    => 'bi-envelope-check-fill',
                'active'  => str_starts_with($currentPath, '/admin/notification-settings'),
            ],
        ],
        'Akun' => [
            [
                'label'   => 'Profil Administrator',
                'route'   => '/profile',
                'icon'    => 'bi-person-circle',
                'active'  => str_starts_with($currentPath, '/profile'),
            ],
        ],
    ];
}
?>

<!-- CampusFind Floating Liquid Glass Dock V5 Component (PAGE-00B.3.1 Elastic Glass) -->
<aside class="cf-sidebar cf-sidebar-dock" id="cf-sidebar" aria-label="Navigasi Utama Sistem">
    <!-- Independent Elastic Stretched Liquid Glass Background Layer -->
    <div class="dock-glass cf-dock-glass" id="cf-dock-glass" aria-hidden="true">
        <!-- Moving Radial Specular Light that follows pointer -->
        <div class="cf-sidebar-specular" id="cf-sidebar-specular" aria-hidden="true"></div>
    </div>

    <!-- Foreground Content Layer: Interactive elements, hitboxes and text remain undistorted -->
    <div class="dock-content cf-dock-content" id="cf-dock-content">
        <!-- Brand & Toggle Header -->
    <div class="cf-sidebar-brand">
        <a href="<?= $homeUrl ?>" class="cf-sidebar-brand-link" title="CampusFind Beranda" data-label="CampusFind Beranda">
            <span class="cf-sidebar-brand-icon">
                <i data-lucide="compass"></i>
            </span>
            <span class="cf-sidebar-brand-text">
                <span class="cf-brand-campus">CAMPUS</span><span class="cf-brand-find">FIND</span>
            </span>
        </a>

        <!-- Desktop Collapse Button -->
        <button type="button" 
                class="cf-sidebar-toggle-btn d-none d-lg-inline-flex" 
                id="cf-sidebar-toggle" 
                aria-label="Perkecil Navigasi" 
                title="Perkecil / Perbesar Navigasi (Ctrl+B)"
                data-label="Perkecil / Perbesar (Ctrl+B)">
            <i data-lucide="chevron-left" id="cf-sidebar-toggle-icon"></i>
        </button>

        <!-- Mobile Drawer Close Button -->
        <button type="button" 
                class="cf-sidebar-close-btn d-lg-none" 
                id="cf-sidebar-close" 
                aria-label="Tutup Menu Navigasi">
            <i data-lucide="x"></i>
        </button>
    </div>

    <!-- Quick Search Input (Visible in Expanded Mode) -->
    <div class="cf-sidebar-search">
        <form class="cf-sidebar-search-form" onsubmit="return false;">
            <i data-lucide="search" class="cf-sidebar-search-icon" aria-hidden="true"></i>
            <input type="text" 
                   id="cf-sidebar-search-input"
                   name="q" 
                   class="cf-sidebar-search-input" 
                   placeholder="Cari menu..." 
                   aria-label="Cari menu"
                   autocomplete="off">
        </form>
    </div>

    <!-- Scrollable Navigation Menu Body -->
    <nav class="cf-sidebar-nav" id="cf-sidebar-nav" role="navigation">
        <!-- Floating Single Sliding Glass Bubble -->
        <div class="cf-sidebar-sliding-bubble" id="cf-sidebar-sliding-bubble" aria-hidden="true"></div>

        <?php $groupIndex = 0; ?>
        <?php foreach ($sidebarMenuGroups as $groupTitle => $menuItems): ?>
            <?php if ($groupIndex > 0): ?>
                <div class="cf-sidebar-group-divider" aria-hidden="true"></div>
            <?php endif; ?>
            <?php $groupIndex++; ?>

            <div class="cf-sidebar-group">
                <div class="cf-sidebar-heading" role="presentation">
                    <span class="cf-sidebar-heading-text"><?= htmlspecialchars($groupTitle) ?></span>
                </div>
                <ul class="cf-sidebar-menu-list">
                    <?php foreach ($menuItems as $item): ?>
                        <?php
                        $badgeType = !empty($item['badge']) ? (string)$item['badge'] : null;
                        $cachedCount = $badgeType ? (int)($sessionUnreadCounts[$badgeType] ?? 0) : 0;
                        $hasBadge = ($cachedCount > 0);
                        $badgeDisplay = ($cachedCount > 99) ? '99+' : (string)$cachedCount;
                        $navSlug = preg_replace('/[^a-zA-Z0-9_-]/', '-', trim($item['route'], '/'));
                        if ($navSlug === '') $navSlug = 'home';
                        $navId = 'cf-nav-' . $navSlug;
                        $lucideName = $item['lucide'] ?? ($biToLucideMap[$item['icon']] ?? 'circle');
                        ?>
                        <li class="cf-sidebar-menu-item">
                            <a href="<?= $item['route'] ?>" 
                               id="<?= $navId ?>"
                               data-menu-id="<?= $navId ?>"
                               class="cf-sidebar-link<?= !empty($item['active']) ? ' active' : '' ?>"
                               title="<?= htmlspecialchars($item['label']) ?>"
                               data-label="<?= htmlspecialchars($item['label']) ?>"
                               <?= !empty($item['active']) ? 'aria-current="page"' : '' ?>>
                                <span class="cf-sidebar-active-indicator" aria-hidden="true"></span>
                                <span class="cf-sidebar-icon-wrapper">
                                    <i data-lucide="<?= $lucideName ?>" class="cf-sidebar-icon"></i>
                                    <?php if ($badgeType): ?>
                                        <span class="cf-sidebar-dot<?= $hasBadge ? '' : ' d-none' ?>" 
                                              id="cf-sidebar-dot-<?= htmlspecialchars($badgeType) ?>" 
                                              aria-hidden="true"></span>
                                    <?php endif; ?>
                                </span>
                                <span class="cf-sidebar-label"><?= htmlspecialchars($item['label']) ?></span>
                                <?php if ($badgeType): ?>
                                    <span class="cf-sidebar-badge<?= $hasBadge ? '' : ' d-none' ?>" 
                                          id="cf-sidebar-badge-<?= htmlspecialchars($badgeType) ?>"
                                          data-badge="<?= htmlspecialchars($badgeType) ?>">
                                        <?= $hasBadge ? $badgeDisplay : '' ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar Bottom User & Logout Footer -->
    <div class="cf-sidebar-footer">
        <div class="cf-sidebar-user-block">
            <a href="/profile" class="cf-sidebar-user-link" title="Profil Pengguna" data-label="Profil Pengguna">
                <div class="cf-sidebar-avatar" aria-hidden="true">
                    <?= $initials ?>
                </div>
                <div class="cf-sidebar-user-info">
                    <div class="cf-sidebar-user-name" title="<?= $userName ?>"><?= $userName ?></div>
                    <div class="cf-sidebar-user-role"><?= $roleLabel ?></div>
                </div>
            </a>
            <form action="/logout" method="POST" class="cf-sidebar-logout-form m-0 p-0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" 
                        class="cf-sidebar-logout-btn" 
                        title="Keluar dari Akun" 
                        aria-label="Keluar dari Akun"
                        data-label="Keluar dari Akun">
                    <i data-lucide="log-out"></i>
                </button>
            </form>
        </div>
    </div>
    </div><!-- /.dock-content -->
</aside>
