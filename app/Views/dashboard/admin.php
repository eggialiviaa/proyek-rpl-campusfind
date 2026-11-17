<?php
/**
 * app/Views/dashboard/admin.php
 * Dashboard khusus Administrator Sistem (Super User)
 */
?>

<!-- Header -->
<div class="cf-dashboard-header">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="cf-badge cf-badge-danger">
                        <i class="bi bi-shield-lock-fill me-1"></i> Administrator
                    </span>
                </div>
                <h1 class="h4 fw-bold text-dark mb-1">
                    Panel Administrasi Utama
                </h1>
                <p class="text-muted small mb-0">
                    Halo, <strong><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>). Anda memiliki akses penuh konfigurasi dan audit sistem.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="/admin/users" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-people me-1"></i> Kelola Pengguna
                </a>
                <a href="/admin/audit-logs" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-journal-text me-1"></i> Audit Trail
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">

    <!-- Stat Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="cf-stat-card">
                <div class="cf-stat-icon primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="cf-stat-value"><?= $stats['users'] ?? 0 ?></div>
                    <div class="cf-stat-label">Total Pengguna Terdaftar</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="cf-stat-card">
                <div class="cf-stat-icon success">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
                <div>
                    <div class="cf-stat-value"><?= $stats['items'] ?? 0 ?></div>
                    <div class="cf-stat-label">Barang Fisik di Center</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="cf-stat-card">
                <div class="cf-stat-icon warning">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
                <div>
                    <div class="cf-stat-value"><?= $stats['reports'] ?? 0 ?></div>
                    <div class="cf-stat-label">Total Laporan (Hilang/Temuan)</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="cf-stat-card">
                <div class="cf-stat-icon info">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <div class="cf-stat-value"><?= $stats['audits'] ?? 0 ?></div>
                    <div class="cf-stat-label">Catatan Audit Keamanan</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Users & System Scope -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="cf-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold text-dark mb-0">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i>Pengguna Terbaru Terdaftar
                    </h2>
                    <a href="/admin/users" class="small text-primary">Lihat Semua &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $ru): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ru['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td class="text-muted small"><?= htmlspecialchars($ru['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="cf-badge cf-badge-neutral"><?= htmlspecialchars($ru['role_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td>
                                        <span class="cf-badge cf-badge-found">Aktif</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="cf-card p-4">
                <h2 class="h6 fw-bold text-dark mb-3">
                    <i class="bi bi-gear-fill text-secondary me-2"></i>Lingkup Hak Akses Admin
                </h2>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> Mengelola seluruh akun & hak akses role</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> Memantau log aktivitas & keamanan sistem</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> Mengatur master data kategori & lokasi kampus</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> Konfigurasi sistem dan integrasi API AI</li>
                </ul>
            </div>
        </div>
    </div>

</div>
