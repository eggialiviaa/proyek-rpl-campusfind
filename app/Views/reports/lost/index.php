<?php
/**
 * app/Views/reports/lost/index.php
 * Katalog Publik Barang Hilang — Campus Find
 * Extended filters: keyword, category, location, date range, sort
 */

$hasActiveFilters = !empty($searchQuery) || !empty($selectedCategory) || !empty($selectedLocation)
                 || !empty($dateFrom) || !empty($dateTo) || ($sortBy !== 'newest');

$buildFilterUrl = function(array $overrides = []) use ($selectedCategory, $selectedLocation, $searchQuery, $dateFrom, $dateTo, $sortBy): string {
    $params = [
        'category'  => $selectedCategory ?: null,
        'location'  => $selectedLocation ?: null,
        'q'         => $searchQuery ?: null,
        'date_from' => $dateFrom ?: null,
        'date_to'   => $dateTo ?: null,
        'sort'      => $sortBy !== 'newest' ? $sortBy : null,
    ];
    $merged = array_merge($params, $overrides);
    $clean = array_filter($merged, fn($v) => $v !== null && $v !== '');
    return '/reports/lost' . (!empty($clean) ? '?' . http_build_query($clean) : '');
};
?>

<?php
$authUser = \App\Middleware\AuthMiddleware::user();
$userRole = $authUser ? strtolower(trim((string)($authUser['role_name'] ?? $authUser['role'] ?? ''))) : null;
$isCivitas = \App\Middleware\AuthMiddleware::isLoggedIn() && ($userRole === 'civitas');
?>

<?php if ($isCivitas): ?>
<div class="container py-4 cf-workspace-wide cf-catalog-container">
    <!-- Standalone Transparent Page Header for Civitas (PAGE-01.2A.3) -->
    <?php
    $lostActions = '<a href="/search?type=lost" class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel me-1"></i> Mode Pencarian Penuh</a>'
                 . '<a href="/reports/lost/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Laporkan Barang Hilang</a>';
    $headerConfig = [
        'eyebrow'     => 'Katalog Publik',
        'title'       => 'Daftar Laporan Barang Hilang',
        'icon'        => 'bi bi-search',
        'description' => 'Temukan dan pantau laporan kehilangan barang dari civitas akademika kampus.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Barang Hilang'],
        ],
        'actions'     => $lostActions,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>
<?php else: ?>
<!-- Header -->
<div class="py-4 border-bottom" style="background: rgba(255,255,255,0.6); backdrop-filter: blur(8px);">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small mb-1">
                        <li class="breadcrumb-item"><a href="/">Beranda</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Barang Hilang</li>
                    </ol>
                </nav>
                <h1 class="h4 fw-bold text-dark mb-0">
                    <i class="bi bi-search text-warning me-2"></i>Daftar Laporan Barang Hilang
                </h1>
                <p class="text-muted small mb-0">
                    Temukan laporan kehilangan dari civitas akademika kampus.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="/search?type=lost" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-funnel me-1"></i> Mode Pencarian Penuh
                </a>
                <a href="/reports/lost/create" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Laporkan Barang Hilang
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
<?php endif; ?>

    <!-- Search & Filter Bar -->
    <div class="cf-filter-bar mb-4">
        <form action="/reports/lost" method="GET" class="row g-2 align-items-end">
            <!-- Search Keyword -->
            <div class="col-md-4">
                <label for="lost-search-q" class="form-label small fw-semibold text-muted mb-1">Cari Barang</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           id="lost-search-q"
                           name="q" 
                           class="form-control border-start-0" 
                           placeholder="Nama barang atau deskripsi..." 
                           value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <!-- Category Filter -->
            <div class="col-md-2 col-sm-6">
                <label for="lost-filter-cat" class="form-label small fw-semibold text-muted mb-1">Kategori</label>
                <select name="category" id="lost-filter-cat" class="form-select">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($selectedCategory == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Location Filter -->
            <div class="col-md-2 col-sm-6">
                <label for="lost-filter-loc" class="form-label small fw-semibold text-muted mb-1">Lokasi Kampus</label>
                <select name="location" id="lost-filter-loc" class="form-select">
                    <option value="">Semua Lokasi</option>
                    <?php if (!empty($locations)): ?>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= (($selectedLocation ?? null) == $loc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Date Range: From & To -->
            <div class="col-md-2 col-sm-6">
                <label for="lost-filter-date" class="form-label small fw-semibold text-muted mb-1">Tanggal Hilang</label>
                <input type="date" 
                       id="lost-filter-date" 
                       name="date_from" 
                       class="form-control" 
                       title="Dari tanggal"
                       value="<?= htmlspecialchars($dateFrom ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <!-- Submit & Reset -->
            <div class="col-md-2 col-sm-6 d-flex gap-1">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <?php if ($hasActiveFilters): ?>
                    <a href="/reports/lost" class="btn btn-outline-secondary" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Sort Row -->
            <div class="col-12 d-flex justify-content-between align-items-center pt-2 border-top mt-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted fw-semibold"><i class="bi bi-arrow-down-up me-1"></i>Urutkan:</span>
                    <select name="sort" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="newest" <?= (($sortBy ?? 'newest') === 'newest') ? 'selected' : '' ?>>Terbaru Ditambahkan</option>
                        <option value="oldest" <?= (($sortBy ?? '') === 'oldest') ? 'selected' : '' ?>>Terlama Ditambahkan</option>
                        <option value="date_desc" <?= (($sortBy ?? '') === 'date_desc') ? 'selected' : '' ?>>Tanggal Hilang Terbaru</option>
                        <option value="date_asc" <?= (($sortBy ?? '') === 'date_asc') ? 'selected' : '' ?>>Tanggal Hilang Terlama</option>
                    </select>
                </div>
                <div class="small text-muted">
                    Total: <strong class="text-dark"><?= $totalCount ?></strong> laporan
                </div>
            </div>
        </form>
    </div>

    <!-- Category Pills -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="<?= $buildFilterUrl(['category' => null, 'page' => 1]) ?>" 
           class="cf-category-pill <?= empty($selectedCategory) ? 'active' : '' ?>">
            Semua
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= $buildFilterUrl(['category' => $cat['id'], 'page' => 1]) ?>" 
               class="cf-category-pill <?= ($selectedCategory == $cat['id']) ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Reports Grid -->
    <?php if (empty($reports)): ?>
        <div class="cf-card p-5">
            <?php
            $emptyIcon = 'bi-search';
            $emptyTitle = 'Tidak Ada Laporan Ditemukan';
            $emptyMessage = 'Belum ada laporan kehilangan yang cocok dengan filter atau kata kunci pencarian Anda.';
            $primaryActionUrl = '/reports/lost/create';
            $primaryActionText = 'Buat Laporan Baru';
            if ($hasActiveFilters) {
                $secondaryActionUrl = '/reports/lost';
                $secondaryActionText = 'Reset Filter';
                $secondaryActionIcon = 'bi-arrow-counterclockwise';
            }
            include __DIR__ . '/../../partials/empty_state.php';
            ?>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <?php foreach ($reports as $r): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="cf-catalog-card">
                        <!-- Image / Thumbnail -->
                        <div class="cf-card-img-wrap">
                            <?php if (!empty($r['primary_image'])): ?>
                                <?php if (\App\Middleware\AuthMiddleware::isLoggedIn()): ?>
                                    <img src="<?= htmlspecialchars($r['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                         alt="<?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>">
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

                            <!-- Badge status floating -->
                            <div class="cf-card-badge-topright">
                                <span class="cf-badge cf-badge-lost shadow-sm">
                                    <i class="bi bi-search"></i> Hilang
                                </span>
                            </div>

                            <!-- Category badge floating -->
                            <div class="cf-card-badge-bottomleft">
                                <span class="cf-badge cf-badge-neutral shadow-sm">
                                    <?= htmlspecialchars($r['category_name'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="p-3 flex-grow-1 d-flex flex-column">
                            <div class="text-muted small mb-1 font-monospace" style="font-size:0.75rem;">
                                <?= htmlspecialchars($r['report_number'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <h3 class="h6 fw-bold text-dark mb-2 text-truncate" title="<?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <p class="text-muted small mb-3 text-truncate-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height:1.4;">
                                <?= htmlspecialchars($r['description'], ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <div class="mt-auto pt-2 border-top">
                                <div class="d-flex justify-content-between align-items-center small text-muted mb-2" style="font-size:0.8rem;">
                                    <span class="text-truncate me-2" title="<?= htmlspecialchars($r['location_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars(substr($r['location_name'] ?? 'Kampus', 0, 22), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="text-nowrap"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($r['lost_date'])) ?></span>
                                </div>
                                <a href="/reports/lost/<?= $r['id'] ?>" class="btn btn-outline-primary btn-sm w-100 rounded-pill">
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
                <span class="text-muted small">Halaman <?= $currentPage ?> dari <?= $totalPages ?> (Total <?= $totalCount ?>)</span>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildFilterUrl(['page' => $currentPage - 1]) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $buildFilterUrl(['page' => $p]) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildFilterUrl(['page' => $currentPage + 1]) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
