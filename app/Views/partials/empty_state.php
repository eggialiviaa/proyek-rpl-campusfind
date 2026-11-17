<?php
/**
 * app/Views/partials/empty_state.php
 * Reusable Clean Editorial Empty State Partial
 * 
 * Supported Parameters:
 * - $icon: Bootstrap Icon class (e.g. 'bi-inbox', 'bi-search', 'bi-check2-circle')
 * - $iconClass: Color/accent class for the icon (e.g. 'text-primary', 'text-success', 'text-warning')
 * - $title: Main title (e.g. 'Tidak Ada Data')
 * - $message / $description: Subtitle explanation text
 * - $actionUrl: Primary action link URL (optional)
 * - $actionText: Primary action button label (optional)
 * - $actionIcon: Primary action button icon (optional)
 * - $actionClass: Button variant class (default: 'btn-primary btn-sm')
 * - $actionAttributes: Extra attributes (e.g. data-bs-toggle="modal" data-bs-target="#...")
 * - $secondaryActionUrl: Secondary action URL (optional)
 * - $secondaryActionText: Secondary action button label (optional)
 * - $secondaryActionIcon: Secondary action button icon (optional)
 * - $secondaryActionClass: Button variant class (default: 'btn-outline-secondary btn-sm ms-2')
 * - $tableColspan: If set, wraps the empty state in <tr><td colspan="..."> for table bodies.
 */

$emptyIcon    = $icon ?? 'bi-inbox';
$emptyIconCls = $iconClass ?? '';
$emptyTitle   = $title ?? 'Tidak Ada Data';
$emptyDesc    = $message ?? $description ?? 'Belum ada data yang tersedia pada kriteria ini.';
$hasAction    = (!empty($actionText) && (!empty($actionUrl) || !empty($actionAttributes)));
$hasSecAction = (!empty($secondaryActionText) && !empty($secondaryActionUrl));
?>

<?php if (!empty($tableColspan)): ?>
<tr>
    <td colspan="<?= (int)$tableColspan ?>" class="p-0 border-0">
<?php endif; ?>

<div class="cf-empty-state">
    <div class="cf-empty-icon <?= htmlspecialchars($emptyIconCls, ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi <?= htmlspecialchars($emptyIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
    </div>
    <div class="cf-empty-title text-dark"><?= htmlspecialchars($emptyTitle, ENT_QUOTES, 'UTF-8') ?></div>
    <p class="cf-empty-desc text-muted mb-3"><?= htmlspecialchars($emptyDesc, ENT_QUOTES, 'UTF-8') ?></p>
    
    <?php if ($hasAction || $hasSecAction): ?>
        <div class="d-inline-flex flex-wrap align-items-center justify-content-center gap-2">
            <?php if ($hasAction): ?>
                <a href="<?= htmlspecialchars($actionUrl ?? '#', ENT_QUOTES, 'UTF-8') ?>" 
                   class="btn <?= htmlspecialchars($actionClass ?? 'btn-primary btn-sm', ENT_QUOTES, 'UTF-8') ?>"
                   <?= $actionAttributes ?? '' ?>>
                    <?php if (!empty($actionIcon)): ?>
                        <i class="bi <?= htmlspecialchars($actionIcon, ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endif; ?>

            <?php if ($hasSecAction): ?>
                <a href="<?= htmlspecialchars($secondaryActionUrl, ENT_QUOTES, 'UTF-8') ?>" 
                   class="btn <?= htmlspecialchars($secondaryActionClass ?? 'btn-outline-secondary btn-sm', ENT_QUOTES, 'UTF-8') ?>">
                    <?php if (!empty($secondaryActionIcon)): ?>
                        <i class="bi <?= htmlspecialchars($secondaryActionIcon, ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($secondaryActionText, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($tableColspan)): ?>
    </td>
</tr>
<?php endif; ?>

