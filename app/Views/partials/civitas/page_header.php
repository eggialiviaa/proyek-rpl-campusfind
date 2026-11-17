<?php
/**
 * app/Views/partials/civitas/page_header.php
 * Reusable Civitas Page Header Component (PAGE-01.2A.3)
 *
 * Parameters expected in $headerConfig (or standalone variables):
 * - $eyebrow     (string, optional) : Category / section eyebrow (e.g. 'Civitas Kampus', 'Pusat Klaim')
 * - $title       (string, required) : Semantic page H1 title
 * - $description (string, optional) : Supporting description text
 * - $breadcrumbs (array, optional)  : Breadcrumb trail [['label' => '...', 'url' => '...'], ['label' => '...']]
 * - $actions     (string, optional) : HTML action buttons / slots
 * - $icon        (string, optional) : Bootstrap icon class (e.g. 'bi bi-shield-check')
 * - $badge       (string, optional) : HTML badge string next to title
 */

$eyebrow     = $headerConfig['eyebrow']     ?? $eyebrow     ?? null;
$title       = $headerConfig['title']       ?? $title       ?? ($pageTitle ?? 'Halaman');
$description = $headerConfig['description'] ?? $description ?? null;
$breadcrumbs = $headerConfig['breadcrumbs'] ?? $breadcrumbs ?? [];
$actions     = $headerConfig['actions']     ?? $actions     ?? null;
$icon        = $headerConfig['icon']        ?? $icon        ?? null;
$badge       = $headerConfig['badge']       ?? $badge       ?? null;

$headerLucideMap = [
    'bi bi-person-gear'                   => 'user-cog',
    'bi bi-journal-text'                  => 'file-text',
    'bi bi-search'                        => 'search',
    'bi bi-bell-fill'                     => 'bell',
    'bi bi-bell'                          => 'bell',
    'bi bi-shield-check'                  => 'shield-check',
    'bi bi-speedometer2'                  => 'gauge',
    'bi bi-chat-dots-fill'                => 'message-square',
    'bi bi-chat-dots'                     => 'message-square',
    'bi bi-compass'                       => 'compass',
    'bi bi-plus-lg'                       => 'plus',
    'bi bi-plus-circle'                   => 'plus-circle',
    'bi bi-grid-fill'                     => 'layout-dashboard',
    'bi bi-collection-fill'               => 'folder-archive',
    'bi bi-patch-check-fill'              => 'shield-check',
    'bi bi-boxes'                         => 'package',
    'bi bi-box-arrow-in-down'             => 'package-plus',
    'bi bi-question-circle'               => 'help-circle',
    'bi bi-check2-circle'                 => 'check-circle-2',
    'bi bi-person-check-fill'             => 'user-check',
    'bi bi-people-fill'                   => 'users',
    'bi bi-file-earmark-spreadsheet-fill' => 'file-spreadsheet',
    'bi bi-tags-fill'                     => 'tags',
    'bi bi-building'                      => 'building',
    'bi bi-robot'                         => 'bot',
    'bi bi-sliders'                       => 'sliders',
];
$lucideHeaderIcon = $headerLucideMap[$icon] ?? (str_starts_with((string)$icon, 'bi ') ? null : $icon);
?>
<header class="cf-page-header mb-4" aria-label="<?= htmlspecialchars(strip_tags((string)$title), ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
        <nav aria-label="Breadcrumb" class="cf-page-breadcrumb-nav mb-2">
            <ol class="cf-page-breadcrumb list-unstyled d-flex flex-wrap align-items-center mb-0">
                <?php 
                $bcCount = count($breadcrumbs);
                $bcIndex = 0;
                foreach ($breadcrumbs as $crumb): 
                    $bcIndex++;
                    $isLast = ($bcIndex === $bcCount) || empty($crumb['url']);
                ?>
                    <li class="cf-page-breadcrumb-item d-flex align-items-center<?= $isLast ? ' active' : '' ?>"<?= $isLast ? ' aria-current="page"' : '' ?>>
                        <?php if (!$isLast && !empty($crumb['url'])): ?>
                            <a href="<?= htmlspecialchars((string)$crumb['url'], ENT_QUOTES, 'UTF-8') ?>" class="cf-page-breadcrumb-link">
                                <?= htmlspecialchars((string)$crumb['label'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <span class="cf-page-breadcrumb-sep mx-2" aria-hidden="true">/</span>
                        <?php else: ?>
                            <span class="cf-page-breadcrumb-current"><?= htmlspecialchars((string)$crumb['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <div class="cf-page-header-body d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div class="cf-page-header-main">
            <?php if (!empty($eyebrow)): ?>
                <div class="cf-page-eyebrow mb-1">
                    <?= htmlspecialchars((string)$eyebrow, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="cf-page-title-wrap d-flex flex-wrap align-items-center gap-2">
                <h1 class="cf-page-h1 mb-0">
                    <?php if (!empty($icon)): ?>
                        <?php if ($lucideHeaderIcon): ?>
                            <i data-lucide="<?= htmlspecialchars($lucideHeaderIcon, ENT_QUOTES, 'UTF-8') ?>" class="cf-page-title-icon me-2" aria-hidden="true"></i>
                        <?php else: ?>
                            <i class="<?= htmlspecialchars((string)$icon, ENT_QUOTES, 'UTF-8') ?> cf-page-title-icon me-2" aria-hidden="true"></i>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <?php if (!empty($badge)): ?>
                    <div class="cf-page-title-badge d-inline-flex align-items-center">
                        <?= $badge ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($description)): ?>
                <p class="cf-page-description mb-0 mt-2">
                    <?= htmlspecialchars((string)$description, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (!empty($actions)): ?>
            <div class="cf-page-header-actions d-flex flex-wrap align-items-center gap-2 mt-1">
                <?= $actions ?>
            </div>
        <?php endif; ?>
    </div>
</header>

