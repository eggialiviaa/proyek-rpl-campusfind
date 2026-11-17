<?php
/**
 * app/Views/search/index.php
 * Halaman Pencarian & Penemuan Terpadu (Unified Search & Discovery)
 * 
 * Sesuai PRD Prompt 8:
 * - Keyword search, Category filter, Location filter, Date filter, Status/Type filter
 * - Sort options, Reset filter, Pagination
 * - Privacy safe: hanya data publik aman yang ditampilkan
 * - Liquid Glass aesthetic
 */

// Helper to build URL with query params
$buildUrl = function(array $newParams = []) use ($filters): string {
    $current = array_filter($filters, fn($v) => $v !== null && $v !== '');
    $merged = array_merge($current, $newParams);
    // Remove empty parameters
    $clean = array_filter($merged, fn($v) => $v !== null && $v !== '');
    return '/search' . (!empty($clean) ? '?' . http_build_query($clean) : '');
};
?>

<?php
$authUser = \App\Middleware\AuthMiddleware::user();
$userRole = $authUser ? strtolower(trim((string)($authUser['role_name'] ?? $authUser['role'] ?? ''))) : null;
$isCivitas = \App\Middleware\AuthMiddleware::isLoggedIn() && ($userRole === 'civitas');
?>

<?php if ($isCivitas): ?>
<div class="container py-4 cf-workspace-wide cf-search-container">
    <!-- Standalone Transparent Page Header for Civitas (PAGE-01.2A.3) -->
    <?php
    $searchActions = '<a href="/reports/lost/create" class="btn btn-outline-warning btn-sm"><i class="bi bi-plus-lg me-1"></i> Lapor Kehilangan</a>'
                   . '<a href="/reports/found/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Lapor Temuan</a>';
    $headerConfig = [
        'eyebrow'     => 'Eksplorasi Kampus',
        'title'       => 'Pencarian & Penemuan Barang',
        'icon'        => 'bi bi-search',
        'description' => 'Temukan laporan barang di lingkungan kampus dengan filter terpadu berbasis kategori, lokasi, dan tanggal.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Pencarian & Penemuan'],
        ],
        'actions'     => $searchActions,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>
<?php else: ?>
<!-- Hero / Page Header (Public & Staff) -->
<div class="py-4 border-bottom" style="background: rgba(255,255,255,0.6); backdrop-filter: blur(8px);">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small mb-1">
                        <li class="breadcrumb-item"><a href="/">Beranda</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Pencarian & Penemuan</li>
                    </ol>
                </nav>
                <h1 class="h4 fw-bold text-dark mb-1">
                    <i class="bi bi-search text-primary me-2"></i>Cari Barang Hilang & Temuan
                </h1>
                <p class="text-muted small mb-0">
                    Temukan laporan barang di lingkungan kampus dengan filter terpadu berbasis kategori, lokasi, dan tanggal.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="/reports/lost/create" class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Lapor Kehilangan
                </a>
                <a href="/reports/found/create" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Lapor Temuan
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
<?php endif; ?>

    <!-- Search & Filter Controls -->
    <div class="cf-filter-bar mb-4">
        <form action="/search" method="GET" id="search-filter-form">
            
            <!-- Row 1: Search Keyword & Type Selection -->
            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <label for="search-input" class="form-label small fw-semibold text-muted">Kata Kunci Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               id="search-input"
                               name="q" 
                               class="form-control border-start-0 ps-0" 
                               placeholder="Cari nama barang atau deskripsi umum (contoh: Tumbler, Kunci Motor, Dompet)..." 
                               value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               autocomplete="off">
                        <?php if (!empty($filters['q'])): ?>
                            <a href="<?= $buildUrl(['q' => '']) ?>" class="btn btn-outline-secondary border-start-0" title="Hapus kata kunci">
                                <i class="bi bi-x"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-4">
                    <label for="filter-type" class="form-label small fw-semibold text-muted">Jenis Laporan</label>
                    <select name="type" id="filter-type" class="form-select">
                        <option value="all" <?= ($filters['type'] === 'all' || empty($filters['type'])) ? 'selected' : '' ?>>Semua Laporan (Hilang & Temuan)</option>
                        <option value="lost" <?= ($filters['type'] === 'lost') ? 'selected' : '' ?>>Hanya Barang Hilang</option>
                        <option value="found" <?= ($filters['type'] === 'found') ? 'selected' : '' ?>>Hanya Barang Temuan</option>
                    </select>
                </div>
            </div>

            <!-- Row 2: Advanced Filters (Category, Location, Date Range, Sort) -->
            <div class="row g-3 align-items-end">
                <!-- Category Filter -->
                <div class="col-md-6 col-lg-3">
                    <label for="filter-category" class="form-label small fw-semibold text-muted">Kategori</label>
                    <select name="category" id="filter-category" class="form-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($filters['category'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Location Filter -->
                <div class="col-md-6 col-lg-3">
                    <label for="filter-location" class="form-label small fw-semibold text-muted">Lokasi Kampus</label>
                    <select name="location" id="filter-location" class="form-select">
                        <option value="">Semua Lokasi Kampus</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= ($filters['location'] == $loc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date Range: From -->
                <div class="col-sm-6 col-lg-2">
                    <label for="filter-date-from" class="form-label small fw-semibold text-muted">Dari Tanggal</label>
                    <input type="date" 
                           id="filter-date-from" 
                           name="date_from" 
                           class="form-control" 
                           value="<?= htmlspecialchars($filters['date_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Date Range: To -->
                <div class="col-sm-6 col-lg-2">
                    <label for="filter-date-to" class="form-label small fw-semibold text-muted">Sampai Tanggal</label>
                    <input type="date" 
                           id="filter-date-to" 
                           name="date_to" 
                           class="form-control" 
                           value="<?= htmlspecialchars($filters['date_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Sort & Buttons -->
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" id="btn-submit-search">
                        <i class="bi bi-funnel me-1"></i> Terapkan
                    </button>
                    <?php if ($hasActiveFilters): ?>
                        <a href="/search" class="btn btn-outline-secondary" title="Reset Semua Filter" id="btn-reset-filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 3: Sort Options (inline) -->
            <div class="d-flex flex-wrap align-items-center justify-content-between mt-3 pt-3 border-top gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted fw-semibold"><i class="bi bi-arrow-down-up me-1"></i>Urutkan:</span>
                    <select name="sort" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="newest" <?= ($filters['sort'] === 'newest') ? 'selected' : '' ?>>Terbaru Ditambahkan</option>
                        <option value="oldest" <?= ($filters['sort'] === 'oldest') ? 'selected' : '' ?>>Terlama Ditambahkan</option>
                        <option value="date_desc" <?= ($filters['sort'] === 'date_desc') ? 'selected' : '' ?>>Tanggal Kejadian Terbaru</option>
                        <option value="date_asc" <?= ($filters['sort'] === 'date_asc') ? 'selected' : '' ?>>Tanggal Kejadian Terlama</option>
                    </select>
                </div>

                <!-- Quick Category Pills -->
                <div class="d-none d-md-flex flex-wrap gap-1">
                    <span class="small text-muted align-self-center me-1">Kategori Populer:</span>
                    <?php foreach (array_slice($categories, 0, 5) as $quickCat): ?>
                        <a href="<?= $buildUrl(['category' => $quickCat['id'], 'page' => 1]) ?>" 
                           class="cf-category-pill <?= ($filters['category'] == $quickCat['id']) ? 'active' : '' ?>"
                           style="font-size: 0.75rem; padding: 3px 10px;">
                            <?= htmlspecialchars($quickCat['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </form>
    </div>

    <!-- Active Filters Badge Summary -->
    <?php if ($hasActiveFilters): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="small text-muted fw-semibold">Filter Aktif:</span>
            
            <?php if (!empty($filters['q'])): ?>
                <span class="cf-badge cf-badge-neutral px-2 py-1">
                    Kata Kunci: "<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>"
                    <a href="<?= $buildUrl(['q' => '', 'page' => 1]) ?>" class="text-muted ms-1 text-decoration-none">&times;</a>
                </span>
            <?php endif; ?>

            <?php if (!empty($filters['type']) && $filters['type'] !== 'all'): ?>
                <span class="cf-badge cf-badge-neutral px-2 py-1">
                    Jenis: <?= $filters['type'] === 'lost' ? 'Barang Hilang' : 'Barang Temuan' ?>
                    <a href="<?= $buildUrl(['type' => 'all', 'page' => 1]) ?>" class="text-muted ms-1 text-decoration-none">&times;</a>
                </span>
            <?php endif; ?>

            <?php if (!empty($filters['category'])): ?>
                <?php 
                $catName = '';
                foreach ($categories as $c) {
                    if ($c['id'] == $filters['category']) { $catName = $c['name']; break; }
                }
                ?>
                <span class="cf-badge cf-badge-neutral px-2 py-1">
                    Kategori: <?= htmlspecialchars($catName ?: (string) $filters['category'], ENT_QUOTES, 'UTF-8') ?>
                    <a href="<?= $buildUrl(['category' => '', 'page' => 1]) ?>" class="text-muted ms-1 text-decoration-none">&times;</a>
                </span>
            <?php endif; ?>

            <?php if (!empty($filters['location'])): ?>
                <?php 
                $locName = '';
                foreach ($locations as $l) {
                    if ($l['id'] == $filters['location']) { $locName = $l['name']; break; }
                }
                ?>
                <span class="cf-badge cf-badge-neutral px-2 py-1">
                    Lokasi: <?= htmlspecialchars($locName ?: (string) $filters['location'], ENT_QUOTES, 'UTF-8') ?>
                    <a href="<?= $buildUrl(['location' => '', 'page' => 1]) ?>" class="text-muted ms-1 text-decoration-none">&times;</a>
                </span>
            <?php endif; ?>

            <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                <span class="cf-badge cf-badge-neutral px-2 py-1">
                    Tanggal: <?= htmlspecialchars($filters['date_from'] ?: '...', ENT_QUOTES, 'UTF-8') ?> s/d <?= htmlspecialchars($filters['date_to'] ?: '...', ENT_QUOTES, 'UTF-8') ?>
                    <a href="<?= $buildUrl(['date_from' => '', 'date_to' => '', 'page' => 1]) ?>" class="text-muted ms-1 text-decoration-none">&times;</a>
                </span>
            <?php endif; ?>

            <a href="/search" class="small text-danger ms-2 text-decoration-none">
                <i class="bi bi-x-circle me-1"></i>Hapus Semua
            </a>
        </div>
    <?php endif; ?>

    <!-- Results Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="small text-muted">
            Menampilkan <strong class="text-dark"><?= count($results) ?></strong> dari <strong class="text-dark"><?= $totalCount ?></strong> laporan publik
        </div>
    </div>

    <!-- Results Grid -->
    <?php if (empty($results)): ?>
        <div class="cf-card p-5">
            <?php
            $emptyIcon = 'bi-search';
            $emptyTitle = 'Tidak Ada Laporan yang Cocok';
            $emptyMessage = 'Tidak ditemukan laporan barang hilang atau temuan yang sesuai dengan kriteria pencarian dan filter Anda. Coba sesuaikan kata kunci atau bersihkan filter.';
            $primaryActionUrl = '/reports/lost/create';
            $primaryActionText = 'Lapor Barang Hilang';
            $primaryActionIcon = 'bi-plus-lg';
            if ($hasActiveFilters) {
                $secondaryActionUrl = '/search';
                $secondaryActionText = 'Reset Filter';
                $secondaryActionIcon = 'bi-arrow-counterclockwise';
            }
            include __DIR__ . '/../partials/empty_state.php';
            ?>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <?php foreach ($results as $item): ?>
                <?php 
                $isLost = ($item['type'] === 'lost');
                $detailUrl = $isLost ? '/reports/lost/' . $item['id'] : '/reports/found/' . $item['id'];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="cf-catalog-card">
                        
                        <!-- Thumbnail Area -->
                        <div class="cf-card-img-wrap">
                            <?php if (!empty($item['primary_image'])): ?>
                                <?php if (\App\Middleware\AuthMiddleware::isLoggedIn()): ?>
                                    <img src="<?= htmlspecialchars($item['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                         alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php else: ?>
                                    <div class="text-center text-muted p-2" title="Login untuk melihat foto">
                                        <i class="bi bi-shield-lock text-primary fs-2 d-block mb-1"></i>
                                        <span class="small" style="font-size:0.75rem;"><a href="/login" class="text-primary text-decoration-none fw-semibold">Login</a> untuk melihat</span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center text-muted opacity-50">
                                    <i class="bi bi-camera fs-1 d-block mb-1"></i>
                                    <span class="small" style="font-size:0.75rem;">Tanpa Foto</span>
                                </div>
                            <?php endif; ?>

                            <!-- Type Badge (Hilang vs Temuan) -->
                            <div class="cf-card-badge-topright">
                                <?php if ($isLost): ?>
                                    <span class="cf-badge cf-badge-lost shadow-sm">
                                        <i class="bi bi-search"></i> Hilang
                                    </span>
                                <?php else: ?>
                                    <span class="cf-badge cf-badge-found shadow-sm">
                                        <i class="bi bi-hand-thumbs-up"></i> Temuan
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Category Badge -->
                            <div class="cf-card-badge-bottomleft">
                                <span class="cf-badge cf-badge-neutral shadow-sm">
                                    <?php if (!empty($item['category_icon'])): ?>
                                        <i class="bi <?= htmlspecialchars($item['category_icon'], ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($item['category_name'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-3 flex-grow-1 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small font-monospace" style="font-size:0.75rem;">
                                    <?= htmlspecialchars($item['report_number'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!$isLost && !empty($item['item_condition'])): ?>
                                    <span class="cf-badge cf-badge-neutral">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $item['item_condition'])), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h2 class="h6 fw-bold text-dark mb-2 text-truncate" title="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h2>

                            <p class="text-muted small mb-3 text-truncate-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height:1.4;">
                                <?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <div class="mt-auto pt-2 border-top">
                                <div class="d-flex justify-content-between align-items-center small text-muted mb-2" style="font-size:0.8rem;">
                                    <span class="text-truncate me-2" title="<?= htmlspecialchars($item['location_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars(substr($item['location_name'] ?? 'Kampus', 0, 22), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="text-nowrap">
                                        <i class="bi bi-calendar3 me-1"></i><?= !empty($item['date']) ? date('d/m/Y', strtotime($item['date'])) : '-' ?>
                                    </span>
                                </div>
                                <a href="<?= $detailUrl ?>" class="btn <?= $isLost ? 'btn-outline-warning' : 'btn-outline-primary' ?> btn-sm w-100">
                                    Lihat Detail Laporan &rarr;
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center pt-2">
                <span class="text-muted small">Halaman <?= $currentPage ?> dari <?= $totalPages ?></span>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildUrl(['page' => $currentPage - 1]) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php 
                    $startPage = max(1, $currentPage - 2);
                    $endPage   = min($totalPages, $currentPage + 2);
                    for ($p = $startPage; $p <= $endPage; $p++): 
                    ?>
                        <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $buildUrl(['page' => $p]) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildUrl(['page' => $currentPage + 1]) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
