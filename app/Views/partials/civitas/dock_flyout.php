<?php
/**
 * app/Views/partials/civitas/dock_flyout.php
 * Additive Floating Submenu Flyout for Civitas Dock (PAGE-01.2C.2)
 *
 * Anchored adjacent to the Sidebar Dock on desktop without altering existing dock code.
 * Provides instant categorization, direct navigation links, and seamless dismissal.
 */
?>
<aside class="cf-dock-flyout" id="cf-dock-flyout" aria-label="Submenu Navigasi Cepat Civitas" role="region" hidden>
    <div class="cf-dock-flyout-inner">
        <div class="cf-dock-flyout-header d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom border-white border-opacity-25">
            <div class="d-flex align-items-center gap-2">
                <div class="cf-dock-flyout-badge-icon rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 28px; height: 28px; background: linear-gradient(135deg, #A77B5B, #8B6346); font-size: 0.8rem;">
                    <i class="bi bi-compass"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark" style="font-size: 0.85rem; line-height: 1.2;">Menu Akses Cepat</div>
                    <div class="text-muted" style="font-size: 0.72rem;">Navigasi Mandiri Civitas</div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none" id="cf-dock-flyout-close" aria-label="Tutup Menu">
                <i class="bi bi-x-lg" style="font-size: 0.85rem;"></i>
            </button>
        </div>

        <div class="cf-dock-flyout-body">
            <!-- Group 1: Dasbor & Ringkasan -->
            <div class="cf-flyout-group mb-3">
                <div class="cf-flyout-group-title text-uppercase text-muted fw-bold px-2 mb-1" style="font-size: 0.68rem; letter-spacing: 0.04em;">
                    Ringkasan Utama
                </div>
                <div class="cf-flyout-items d-flex flex-column gap-1">
                    <a href="/dashboard" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-grid-fill text-primary" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Dasbor Ringkasan</span>
                    </a>
                </div>
            </div>

            <!-- Group 2: Laporan & Penemuan -->
            <div class="cf-flyout-group mb-3">
                <div class="cf-flyout-group-title text-uppercase text-muted fw-bold px-2 mb-1" style="font-size: 0.68rem; letter-spacing: 0.04em;">
                    Laporan Barang
                </div>
                <div class="cf-flyout-items d-flex flex-column gap-1">
                    <a href="/civitas/my-reports" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-collection-fill text-primary" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Daftar Laporan Saya</span>
                    </a>
                    <a href="/reports/lost/create" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-plus-circle-fill text-danger" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Lapor Kehilangan</span>
                    </a>
                    <a href="/reports/found/create" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-box-arrow-in-down text-success" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Lapor Menemukan</span>
                    </a>
                    <a href="/search" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-search text-info" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Pencarian Terpadu</span>
                    </a>
                    <a href="/reports/lost" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-question-circle text-muted" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Katalog Kehilangan</span>
                    </a>
                    <a href="/reports/found" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-check2-circle text-muted" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Katalog Temuan</span>
                    </a>
                </div>
            </div>

            <!-- Group 3: Klaim Kepemilikan -->
            <div class="cf-flyout-group mb-3">
                <div class="cf-flyout-group-title text-uppercase text-muted fw-bold px-2 mb-1" style="font-size: 0.68rem; letter-spacing: 0.04em;">
                    Klaim Kepemilikan
                </div>
                <div class="cf-flyout-items d-flex flex-column gap-1">
                    <a href="/civitas/my-claims" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-patch-check-fill text-warning" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Semua Klaim Saya</span>
                    </a>
                </div>
            </div>

            <!-- Group 4: Komunikasi & Pengaturan -->
            <div class="cf-flyout-group">
                <div class="cf-flyout-group-title text-uppercase text-muted fw-bold px-2 mb-1" style="font-size: 0.68rem; letter-spacing: 0.04em;">
                    Komunikasi & Akun
                </div>
                <div class="cf-flyout-items d-flex flex-column gap-1">
                    <a href="/chats" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-chat-dots-fill text-primary" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Pesan & Diskusi</span>
                    </a>
                    <a href="/notifications" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-bell-fill text-warning" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Notifikasi Sistem</span>
                    </a>
                    <a href="/profile" class="cf-flyout-link d-flex align-items-center gap-2 px-2 py-1 rounded-3 text-decoration-none">
                        <i class="bi bi-person-circle text-secondary" style="font-size: 0.9rem;"></i>
                        <span class="text-dark fw-medium" style="font-size: 0.82rem;">Profil & Preferensi</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="cf-dock-flyout-footer pt-2 mt-2 border-top border-white border-opacity-25 text-center">
            <span class="text-muted" style="font-size: 0.68rem;">Tekan <kbd class="bg-light text-dark border px-1 rounded">Esc</kbd> untuk menutup menu</span>
        </div>
    </div>
</aside>

