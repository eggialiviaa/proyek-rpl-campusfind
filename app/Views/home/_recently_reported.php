<?php
/**
 * app/Views/home/_recently_reported.php
 * Phase 2: Cool Gray Editorial "Recently Reported." Carousel & Compact Preview Modal
 *
 * Uses strictly sanitized Phase 0 variables from HomeController::index():
 * - $canBrowseCatalog (bool)
 * - $isLoggedInUser (bool)
 * - $recentItemsByType (array{all: array, lost: array, found: array})
 */

$canBrowse = !empty($canBrowseCatalog);
$isLoggedIn = !empty($isLoggedInUser);

$formatReportDate = static function (string $rawDate): string {
    $rawDate = trim($rawDate);
    if ($rawDate === '') {
        return '-';
    }
    try {
        return (new DateTimeImmutable($rawDate))->format('d M Y');
    } catch (\Throwable $e) {
        return $rawDate;
    }
};

$sanitizeItemForCarousel = static function (array $item) use ($formatReportDate, $isLoggedIn): array {
    $type = (($item['type'] ?? '') === 'lost') ? 'lost' : 'found';
    $id = (int) ($item['id'] ?? 0);
    $rawDesc = trim((string) ($item['description'] ?? ''));
    if (function_exists('mb_strimwidth')) {
        $shortDesc = mb_strimwidth($rawDesc, 0, 180, '...');
    } else {
        $shortDesc = strlen($rawDesc) > 180 ? substr($rawDesc, 0, 177) . '...' : $rawDesc;
    }

    // Strict FileController parity: only expose image_url when server verified authorization
    $canViewImage = !empty($item['can_view_image']) && !empty($item['image_url']);

    return [
        'key'                 => $type . '-' . $id,
        'type'                => $type,
        'type_label'          => $type === 'lost' ? 'Lost' : 'Found',
        'status_label'        => (string) ($item['status_label'] ?? 'Active'),
        'title'               => (string) ($item['title'] ?? ''),
        'description'         => $shortDesc,
        'category_name'       => (string) ($item['category_name'] ?? 'Umum'),
        'category_icon'       => (string) ($item['category_icon'] ?? 'bi-box-seam'),
        'location_name'       => (string) ($item['location_name'] ?? 'Campus Area'),
        'date_formatted'      => $formatReportDate((string) ($item['date'] ?? '')),
        'can_view_image'      => $canViewImage,
        'has_protected_image' => !empty($item['has_protected_image']),
        'image_url'           => $canViewImage ? (string) $item['image_url'] : null,
        'detail_url'          => (string) ($item['detail_url'] ?? '/login'),
        'is_logged_in'        => $isLoggedIn,
    ];
};

$carouselDatasets = [
    'all'   => [],
    'lost'  => [],
    'found' => [],
];

if ($canBrowse && is_array($recentItemsByType ?? null)) {
    foreach (['all', 'lost', 'found'] as $filterKey) {
        $rawList = $recentItemsByType[$filterKey] ?? [];
        if (is_array($rawList)) {
            $carouselDatasets[$filterKey] = array_values(array_map($sanitizeItemForCarousel, $rawList));
        }
    }
}

$initialItems = $carouselDatasets['all'];
?>

<section class="cf-recent" id="recently-reported" aria-labelledby="recent-heading">
    <div class="container cf-recent-container">

        <!-- Section Header -->
        <div class="cf-recent-header">
            <div class="cf-recent-heading-wrap">
                <h2 id="recent-heading" class="cf-recent-title">Recently Reported.</h2>
                <p class="cf-recent-subtitle">
                    A glimpse of what&rsquo;s happening around your campus.
                </p>
            </div>

            <?php if ($canBrowse): ?>
                <div class="cf-recent-controls-bar">
                    <!-- Single-Carousel Filter Tabs -->
                    <div class="cf-recent-filters" role="tablist" aria-label="Filter recent reports">
                        <button type="button"
                                class="cf-recent-filter-btn is-active"
                                role="tab"
                                aria-selected="true"
                                data-filter-value="all"
                                id="cf-filter-all">
                            All
                        </button>
                        <button type="button"
                                class="cf-recent-filter-btn"
                                role="tab"
                                aria-selected="false"
                                data-filter-value="lost"
                                id="cf-filter-lost">
                            Lost Items
                        </button>
                        <button type="button"
                                class="cf-recent-filter-btn"
                                role="tab"
                                aria-selected="false"
                                data-filter-value="found"
                                id="cf-filter-found">
                            Found Items
                        </button>
                    </div>

                    <!-- Manual Prev / Next Controls -->
                    <div class="cf-recent-nav-btns" aria-label="Carousel navigation">
                        <button type="button"
                                class="cf-recent-arrow"
                                id="cf-recent-prev"
                                aria-label="Previous reports">
                            <span aria-hidden="true">&larr;</span>
                        </button>
                        <button type="button"
                                class="cf-recent-arrow"
                                id="cf-recent-next"
                                aria-label="Next reports">
                            <span aria-hidden="true">&rarr;</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$canBrowse): ?>
            <!-- Gated Editorial Showcase (PUBLIC_BROWSE_ENABLED=false for unauthenticated Guests) -->
            <div class="cf-recent-gated" id="cf-recent-gated">
                <div class="cf-recent-gated-copy">
                    <span class="cf-recent-gated-eyebrow">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        Campus Member Catalog
                    </span>
                    <h3 class="cf-recent-gated-title">
                        Verified campus reports stay protected until you sign in.
                    </h3>
                    <p class="cf-recent-gated-desc">
                        To protect student privacy and prevent unauthorized claim attempts, live lost &amp; found listings and item photographs are accessible exclusively to signed-in campus members.
                    </p>
                    <div class="cf-recent-gated-actions">
                        <a href="/login" class="cf-hero-btn cf-hero-btn--primary" id="cf-recent-login-cta">
                            <span>Sign In to Browse Reports</span>
                            <span class="cf-hero-btn-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>

                <div class="cf-recent-gated-pillars" aria-label="Catalog privacy protections">
                    <div class="cf-recent-pillar">
                        <span class="cf-recent-pillar-icon" aria-hidden="true">
                            <i class="bi bi-eye-slash"></i>
                        </span>
                        <div>
                            <div class="cf-recent-pillar-label">Privacy-Gated Listings</div>
                            <p class="cf-recent-pillar-text">Active reports and campus pickup status remain hidden from public visitors.</p>
                        </div>
                    </div>
                    <div class="cf-recent-pillar">
                        <span class="cf-recent-pillar-icon" aria-hidden="true">
                            <i class="bi bi-camera"></i>
                        </span>
                        <div>
                            <div class="cf-recent-pillar-label">Protected Item Photos</div>
                            <p class="cf-recent-pillar-text">Uploaded evidence images are authorization-checked per user role and verification state.</p>
                        </div>
                    </div>
                    <div class="cf-recent-pillar">
                        <span class="cf-recent-pillar-icon" aria-hidden="true">
                            <i class="bi bi-patch-check"></i>
                        </span>
                        <div>
                            <div class="cf-recent-pillar-label">Verified Handover</div>
                            <p class="cf-recent-pillar-text">Claims require confidential ownership details reviewed by Lost &amp; Found Center staff.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- One Shared Editorial Carousel -->
            <div class="cf-recent-carousel"
                 id="cf-recent-carousel"
                 tabindex="0"
                 role="region"
                 aria-roledescription="carousel"
                 aria-label="Recently reported items">

                <div class="cf-recent-viewport" id="cf-recent-viewport">
                    <div class="cf-recent-track" id="cf-recent-track">
                        <?php if (empty($initialItems)): ?>
                            <div class="cf-recent-empty-card">
                                <div class="cf-recent-empty-icon" aria-hidden="true">
                                    <i class="bi bi-inbox"></i>
                                </div>
                                <h3 class="cf-recent-empty-title">No active reports yet</h3>
                                <p class="cf-recent-empty-text">
                                    Newly published lost and found reports around campus will appear here.
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($initialItems as $idx => $item): ?>
                                <?php $isLost = ($item['type'] === 'lost'); ?>
                                <article class="cf-recent-slide" data-item-index="<?= $idx ?>" data-item-key="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="button"
                                            class="cf-recent-card"
                                            data-preview-key="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>"
                                            aria-label="Preview <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>">

                                        <!-- 5:4 Image Region -->
                                        <div class="cf-recent-media">
                                            <?php if ($item['can_view_image'] && $item['image_url'] !== null): ?>
                                                <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                                     alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>"
                                                     class="cf-recent-media-img"
                                                     loading="lazy"
                                                     decoding="async">
                                            <?php elseif ($item['has_protected_image']): ?>
                                                <div class="cf-recent-placeholder cf-recent-placeholder--protected">
                                                    <span class="cf-recent-placeholder-badge">
                                                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                                    </span>
                                                    <span class="cf-recent-placeholder-title">
                                                        <?= $isLoggedIn ? 'Photo Pending Verification' : 'Sign In to View Photo' ?>
                                                    </span>
                                                    <span class="cf-recent-placeholder-sub">
                                                        <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="cf-recent-placeholder cf-recent-placeholder--category">
                                                    <span class="cf-recent-placeholder-badge">
                                                        <i class="bi <?= htmlspecialchars($item['category_icon'] ?: 'bi-box-seam', ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                                                    </span>
                                                    <span class="cf-recent-placeholder-title">
                                                        <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <span class="cf-recent-placeholder-sub">No photo provided</span>
                                                </div>
                                            <?php endif; ?>

                                            <span class="cf-recent-type-pill <?= $isLost ? 'is-lost' : 'is-found' ?>">
                                                <?= htmlspecialchars($item['type_label'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>

                                        <!-- Card Body -->
                                        <div class="cf-recent-card-body">
                                            <div class="cf-recent-card-status">
                                                <?= htmlspecialchars($item['status_label'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <h3 class="cf-recent-card-title">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </h3>
                                            <div class="cf-recent-card-meta">
                                                <span class="cf-recent-meta-loc">
                                                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                                    <?= htmlspecialchars($item['location_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <span class="cf-recent-meta-date">
                                                    <?= htmlspecialchars($item['date_formatted'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                        </div>
                                    </button>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination Dots & Live Status -->
                <div class="cf-recent-footer-bar">
                    <div class="cf-recent-dots" id="cf-recent-dots" role="tablist" aria-label="Carousel pages"></div>
                    <a href="<?= $isLoggedIn ? '/search' : '/login' ?>" class="cf-recent-all-link">
                        <span><?= $isLoggedIn ? 'View full catalog' : 'Sign in for full search' ?></span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Sanitized JSON Datasets for Shared Carousel Filter & Modal -->
            <script type="application/json" id="cf-recent-data"><?= json_encode($carouselDatasets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

            <!-- Compact Preview Modal (Strictly Sanitized Public Fields) -->
            <div class="cf-recent-modal-backdrop" id="cf-recent-modal" role="dialog" aria-modal="true" aria-labelledby="cf-modal-title" hidden>
                <div class="cf-recent-modal-dialog">
                    <button type="button" class="cf-recent-modal-close" id="cf-recent-modal-close" aria-label="Close preview">
                        <span aria-hidden="true">&times;</span>
                    </button>

                    <div class="cf-recent-modal-media" id="cf-modal-media"></div>

                    <div class="cf-recent-modal-content">
                        <div class="cf-recent-modal-badges">
                            <span class="cf-recent-type-pill" id="cf-modal-type-pill"></span>
                            <span class="cf-recent-modal-status" id="cf-modal-status"></span>
                        </div>

                        <h3 class="cf-recent-modal-title" id="cf-modal-title"></h3>

                        <div class="cf-recent-modal-meta">
                            <span><i class="bi bi-tag" aria-hidden="true"></i> <span id="cf-modal-category"></span></span>
                            <span><i class="bi bi-geo-alt" aria-hidden="true"></i> <span id="cf-modal-location"></span></span>
                            <span><i class="bi bi-calendar3" aria-hidden="true"></i> <span id="cf-modal-date"></span></span>
                        </div>

                        <p class="cf-recent-modal-desc" id="cf-modal-desc"></p>

                        <div class="cf-recent-modal-actions">
                            <a href="/login" class="cf-hero-btn cf-hero-btn--primary cf-recent-modal-cta" id="cf-modal-detail-link">
                                <span>View Full Report</span>
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                            <button type="button" class="cf-hero-btn cf-hero-btn--secondary cf-recent-modal-dismiss" id="cf-recent-modal-dismiss">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>

