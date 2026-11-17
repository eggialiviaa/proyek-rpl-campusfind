<?php
/**
 * app/Views/chats/index.php
 * Antarmuka Daftar Percakapan Split-Pane (PAGE-01.2C.2)
 */
?>

<div class="container py-4 cf-chat-workspace">

    <!-- Standalone Transparent Page Header (PAGE-01.2A.3) -->
    <?php
    $unreadBadge = (($totalUnread ?? 0) > 0)
        ? '<span class="cf-badge cf-badge-danger px-3 py-2"><i class="bi bi-envelope-fill me-1"></i>' . (int) $totalUnread . ' Pesan Belum Dibaca</span>'
        : null;

    $headerConfig = [
        'eyebrow'     => 'Komunikasi',
        'title'       => 'Pesan & Diskusi Internal',
        'icon'        => 'bi bi-chat-dots-fill',
        'description' => 'Komunikasi resmi dan privat terkait proses penanganan laporan serta verifikasi klaim barang.',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Pesan & Diskusi'],
        ],
        'badge'       => $unreadBadge,
    ];
    require BASE_PATH . '/app/Views/partials/civitas/page_header.php';
    ?>

    <?php if (empty($rooms)): ?>
        <!-- Empty State -->
        <div class="cf-card p-5 text-center my-4 mx-auto" style="max-width: 620px;">
            <div class="mb-3">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle" 
                     style="width: 72px; height: 72px; background: rgba(37, 99, 235, 0.1); color: var(--cf-primary);">
                    <i class="bi bi-chat-left-text fs-1"></i>
                </div>
            </div>
            <h2 class="h5 fw-bold text-dark mb-2">Belum Ada Percakapan Aktif</h2>
            <p class="text-muted small mb-4">
                Ruang percakapan akan otomatis dibuat saat Anda mengajukan klaim kepemilikan barang atau saat Petugas menghubungi Anda mengenai laporan barang hilang/temuan.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="/search" class="btn btn-primary btn-sm px-4">
                    <i class="bi bi-search me-1"></i>Cari Barang Temuan
                </a>
                <a href="/civitas/my-claims" class="btn btn-outline-secondary btn-sm px-4">
                    <i class="bi bi-shield-check me-1"></i>Lihat Klaim Saya
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Split-Pane Desktop Messaging Container (PAGE-01.2C.2) -->
        <div class="cf-chat-split-container shadow-sm">
            
            <!-- Left Master Pane: Conversation List -->
            <div class="cf-chat-pane-left">
                <div class="cf-chat-pane-left-header">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold text-dark" style="font-size: 0.88rem;">Daftar Percakapan</span>
                        <span class="badge bg-secondary bg-opacity-25 text-dark rounded-pill" style="font-size: 0.72rem;"><?= count($rooms) ?> Sesi</span>
                    </div>
                    <div class="position-relative">
                        <input type="text" 
                               id="chatListSearch" 
                               class="cf-chat-pane-left-search" 
                               placeholder="Cari ruang percakapan..." 
                               aria-label="Cari percakapan">
                    </div>
                </div>

                <div class="cf-chat-pane-left-list" id="chatListContainer">
                    <?php foreach ($rooms as $r): ?>
                        <?php
                            $isUnread = ($r['unread_count'] ?? 0) > 0;
                        ?>
                        <a href="/chats/<?= (int) $r['id'] ?>" class="cf-chat-room-item" data-search-text="<?= htmlspecialchars(strtolower(($r['display_title'] ?? '') . ' ' . ($r['display_subtitle'] ?? '') . ' ' . ($r['latest_message'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width: 42px; height: 42px; background: <?= $isUnread ? 'linear-gradient(135deg, #14283C, #0C1926)' : 'rgba(255, 255, 255, 0.75)' ?>; color: <?= $isUnread ? '#ffffff' : '#14283C' ?>; border: 1px solid rgba(255, 255, 255, 0.6);">
                                <i class="bi bi-chat-text-fill" style="font-size: 1.1rem;"></i>
                            </div>

                            <div class="flex-grow-1 min-w-0" style="min-width: 0;">
                                <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                    <span class="fw-bold text-dark text-truncate" style="font-size: 0.84rem; max-width: 170px;">
                                        <?= htmlspecialchars($r['display_title'] ?? 'Diskusi', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if (!empty($r['latest_message_time'])): ?>
                                        <span class="text-muted flex-shrink-0" style="font-size: 0.70rem;">
                                            <?= date('d M, H:i', strtotime($r['latest_message_time'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="text-muted small text-truncate" style="font-size: 0.76rem;">
                                    <?= htmlspecialchars($r['display_subtitle'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <p class="text-muted small mb-0 text-truncate opacity-75" style="font-size: 0.74rem;">
                                    <?php if (!empty($r['latest_message'])): ?>
                                        <?= htmlspecialchars($r['latest_message'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span class="fst-italic">Percakapan dimulai...</span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <?php if ($isUnread): ?>
                                <span class="badge bg-danger rounded-pill px-2 py-1 flex-shrink-0" style="font-size: 0.70rem;">
                                    <?= (int) $r['unread_count'] ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Detail Pane: Placeholder State (Desktop) -->
            <div class="cf-chat-pane-right is-index-view d-none d-lg-flex">
                <div class="cf-chat-placeholder">
                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm mb-3"
                         style="width: 76px; height: 76px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(255, 255, 255, 0.7); backdrop-filter: blur(12px);">
                        <i class="bi bi-chat-heart-fill text-primary" style="font-size: 2.2rem;"></i>
                    </div>
                    <h2 class="h5 fw-bold text-dark mb-2">Pusat Komunikasi Resmi</h2>
                    <p class="text-muted small mb-4" style="max-width: 440px; line-height: 1.6;">
                        Pilih percakapan dari daftar di sebelah kiri untuk melihat pesan masuk, berkoordinasi dengan petugas posko, atau memeriksa status verifikasi klaim barang Anda.
                    </p>
                    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-center">
                        <a href="/civitas/my-claims" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm">
                            <i class="bi bi-shield-check me-1"></i>Klaim Saya
                        </a>
                        <a href="/search" class="btn btn-outline-secondary btn-sm rounded-pill px-4">
                            <i class="bi bi-search me-1"></i>Cari Barang
                        </a>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('chatListSearch');
    const items = document.querySelectorAll('.cf-chat-room-item');
    if (!searchInput || !items.length) return;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        items.forEach(function(item) {
            const text = item.getAttribute('data-search-text') || '';
            item.style.display = (!query || text.includes(query)) ? 'flex' : 'none';
        });
    });
});
</script>
