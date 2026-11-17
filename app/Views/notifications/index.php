<?php
/**
 * app/Views/notifications/index.php
 * Riwayat Notifikasi In-App — Prompt 11
 */
?>

<div class="container py-4 cf-content-narrow">

    <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
    <?php
    $unreadBadge = ($unreadCount > 0)
        ? '<span class="cf-badge cf-badge-danger px-3 py-2"><i class="bi bi-bell-fill me-1"></i>' . (int) $unreadCount . ' Belum Dibaca</span>'
        : null;

    $headerConfig = [
        'eyebrow'     => 'Notifikasi',
        'title'       => 'Riwayat Notifikasi',
        'icon'        => 'bi bi-bell-fill',
        'description' => 'Semua notifikasi in-app yang Anda terima terkait klaim, chat, dan penanganan laporan barang.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Notifikasi'],
        ],
        'badge'       => $unreadBadge,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <!-- Toolbar: filter + mark-all -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <!-- Filter tab -->
        <ul class="nav nav-pills mb-0" id="notifFilterPills" role="tablist">
            <li class="nav-item">
                <a href="/notifications?filter=all"
                   class="nav-link <?= ($filter ?? 'all') === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-list-ul"></i> Semua <span class="badge bg-light text-dark ms-1"><?= (int) $totalCount ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/notifications?filter=unread"
                   class="nav-link <?= ($filter ?? 'all') === 'unread' ? 'active' : '' ?>">
                    <i class="bi bi-envelope"></i> Belum Dibaca
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger text-white ms-1"><?= (int) $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <!-- Mark all as read -->
        <?php if ($unreadCount > 0): ?>
            <form method="POST" action="/notifications/read-all" id="form-mark-all-read">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="bi bi-check2-all me-1"></i>Tandai Semua Dibaca
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Notification List -->
    <?php if (empty($notifications)): ?>
        <div class="cf-card p-4">
            <?php
            $emptyIcon = 'bi-bell-slash';
            $emptyTitle = ($filter ?? 'all') === 'unread' ? 'Tidak Ada Notifikasi Baru' : 'Belum Ada Notifikasi';
            $emptyMessage = 'Notifikasi akan muncul secara otomatis saat ada pembaruan status klaim, pesan baru, atau barang Anda diterima di pusat L&F.';
            $primaryActionUrl = '/dashboard';
            $primaryActionText = 'Kembali ke Dashboard';
            $primaryActionIcon = 'bi-grid';
            include __DIR__ . '/../partials/empty_state.php';
            ?>
        </div>
    <?php else: ?>
        <div class="cf-card overflow-hidden">
            <?php foreach ($notifications as $notif):
                $payload    = $notif['payload'] ?? [];
                $title      = htmlspecialchars($payload['title'] ?? 'Notifikasi', ENT_QUOTES, 'UTF-8');
                $message    = htmlspecialchars($payload['message'] ?? '', ENT_QUOTES, 'UTF-8');
                $url        = htmlspecialchars($payload['url'] ?? '#', ENT_QUOTES, 'UTF-8');
                $icon       = htmlspecialchars($payload['icon'] ?? 'bi-bell-fill', ENT_QUOTES, 'UTF-8');
                $badgeClass = htmlspecialchars($payload['badge_class'] ?? 'primary', ENT_QUOTES, 'UTF-8');
                $isRead     = (bool) ($notif['is_read'] ?? false);
                $createdAt  = $notif['created_at'] ?? '';
                $notifId    = (int) $notif['id'];
                ?>
                <div class="d-flex align-items-start gap-3 px-4 py-3 border-bottom notif-history-item <?= !$isRead ? 'bg-primary-subtle' : '' ?>"
                     id="notif-row-<?= $notifId ?>"
                     data-notif-id="<?= $notifId ?>"
                     data-notif-url="<?= $url ?>"
                     style="cursor:<?= $url !== '#' ? 'pointer' : 'default' ?>; transition: background 0.2s;">

                    <!-- Icon Badge -->
                    <div class="cf-stat-icon <?= $badgeClass ?> flex-shrink-0 mt-1" style="width:38px;height:38px;font-size:1rem;">
                        <i class="bi <?= $icon ?>"></i>
                    </div>

                    <!-- Content -->
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="fw-semibold text-dark small"><?= $title ?></div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                <?php if (!$isRead): ?>
                                    <span class="cf-badge cf-badge-found" style="font-size:0.65rem;">Baru</span>
                                <?php endif; ?>
                                <span class="text-muted" style="font-size:0.72rem; white-space:nowrap;">
                                    <?= $createdAt ? date('d M Y, H:i', strtotime($createdAt)) : '' ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-muted" style="font-size:0.82rem; margin-top:2px;"><?= $message ?></div>
                    </div>

                    <!-- Mark as read quick action (only for unread) -->
                    <?php if (!$isRead): ?>
                        <button type="button"
                                class="btn btn-ghost btn-sm flex-shrink-0 notif-mark-btn text-muted p-1"
                                title="Tandai sudah dibaca"
                                data-notif-id="<?= $notifId ?>"
                                onclick="event.stopPropagation();"
                                style="font-size:0.75rem;">
                            <i class="bi bi-check2"></i>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginasi -->
        <?php if (($totalPages ?? 1) > 1): ?>
            <nav aria-label="Paginasi notifikasi" class="mt-4">
                <ul class="pagination justify-content-center pagination-sm">
                    <?php if ($currentPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="/notifications?filter=<?= $filter ?>&page=<?= $currentPage - 1 ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="/notifications?filter=<?= $filter ?>&page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="/notifications?filter=<?= $filter ?>&page=<?= $currentPage + 1 ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';
    const CSRF = <?= json_encode($csrfToken) ?>;

    function markRead(notifId, callback) {
        fetch('/notifications/' + notifId + '/read', {
            method:  'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type':      'application/x-www-form-urlencoded',
                'X-CSRF-Token':      CSRF,
                'X-Requested-With':  'XMLHttpRequest',
            },
            body: 'csrf_token=' + encodeURIComponent(CSRF),
        })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (data && data.success && typeof callback === 'function') {
                callback(data);
            }
        })
        .catch(() => {});
    }

    // Click on row → mark as read → navigate
    document.querySelectorAll('.notif-history-item').forEach(row => {
        const url = row.dataset.notifUrl;
        if (!url || url === '#') return;

        row.addEventListener('click', function () {
            const nid = this.dataset.notifId;
            if (!nid) return;

            markRead(nid, (data) => {
                // Visual feedback
                this.classList.remove('bg-primary-subtle');
                const badge = this.querySelector('.badge.text-bg-primary');
                if (badge) badge.remove();
                const btn = this.querySelector('.notif-mark-btn');
                if (btn) btn.remove();
                // Navigate
                if (url !== window.location.pathname) {
                    window.location.href = url;
                }
            });
            // Optimistic navigation
            if (url !== window.location.pathname) {
                window.location.href = url;
            }
        });
    });

    // Quick "✓" button — mark single item as read without navigating
    document.querySelectorAll('.notif-mark-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const nid = this.dataset.notifId;
            if (!nid) return;
            const row = document.getElementById('notif-row-' + nid);

            markRead(nid, () => {
                if (row) {
                    row.classList.remove('bg-primary-subtle');
                    const badge = row.querySelector('.badge.text-bg-primary');
                    if (badge) badge.remove();
                    this.remove();
                }
            });
        });
    });
})();
</script>
