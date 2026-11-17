<?php
/**
 * View: Audit Trail & System Log Viewer (Admin)
 * File: app/Views/admin/audit_logs.php
 */
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/admin">Beranda admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Jejak audit</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1">
                Jejak audit &amp; keamanan sistem
            </h1>
            <p class="text-muted mb-0">
                Pencatatan aktivitas login, pelaporan barang, persetujuan klaim, serah terima, dan perubahan hak akses. Total: <strong><?= number_format($totalLogs) ?> Aktivitas</strong>
            </p>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="cf-card p-4 mb-4">
        <form action="/admin/audit-logs" method="GET" class="row g-3">
            <div class="col-12 col-md-5">
                <label class="form-label extra-small text-muted fw-bold">CARI LOG</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="keyword" class="form-control bg-light border-start-0 ps-0" 
                           placeholder="Nama user, email, aksi, IP address..." value="<?= htmlspecialchars($keyword) ?>">
                </div>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label extra-small text-muted fw-bold">FILTER AKSI</label>
                <select name="action" class="form-select bg-light">
                    <option value="">-- Semua Aksi --</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?= htmlspecialchars($act) ?>" <?= ($action === $act) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($act) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label extra-small text-muted fw-bold">FILTER ENTITAS</label>
                <select name="entity_type" class="form-select bg-light">
                    <option value="">-- Semua Entitas --</option>
                    <option value="users" <?= ($entityType === 'users') ? 'selected' : '' ?>>users</option>
                    <option value="lost_reports" <?= ($entityType === 'lost_reports') ? 'selected' : '' ?>>lost_reports</option>
                    <option value="found_reports" <?= ($entityType === 'found_reports') ? 'selected' : '' ?>>found_reports</option>
                    <option value="claims" <?= ($entityType === 'claims') ? 'selected' : '' ?>>claims</option>
                    <option value="items" <?= ($entityType === 'items') ? 'selected' : '' ?>>items</option>
                    <option value="categories" <?= ($entityType === 'categories') ? 'selected' : '' ?>>categories</option>
                    <option value="locations" <?= ($entityType === 'locations') ? 'selected' : '' ?>>locations</option>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <?php if (!empty($keyword) || !empty($action) || !empty($entityType)): ?>
                    <a href="/admin/audit-logs" class="btn btn-outline-secondary btn-icon" title="Reset Filter">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Logs Table Card -->
    <div class="cf-card overflow-hidden">
        <div class="table-responsive cf-table-wrapper">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light extra-small text-uppercase tracking-wider text-muted">
                    <tr>
                        <th class="ps-4">ID &amp; Waktu</th>
                        <th>Aktor / Pengguna</th>
                        <th>Aksi / Kegiatan</th>
                        <th>Entitas Terkait</th>
                        <th>IP &amp; Device</th>
                        <th class="pe-4 text-end">Payload Metadata</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <?php
                        $tableColspan = 6;
                        $icon = 'bi-journal-x';
                        $title = 'Tidak Ditemukan Data Log';
                        $message = 'Tidak ditemukan data audit log yang sesuai dengan filter pencarian.';
                        require BASE_PATH . '/app/Views/partials/empty_state.php';
                        ?>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $actionBadge = match (true) {
                                str_contains($log['action'], 'login')    => 'cf-badge-found',
                                str_contains($log['action'], 'logout')   => 'cf-badge-neutral',
                                str_contains($log['action'], 'approve')  => 'cf-badge-success',
                                str_contains($log['action'], 'reject')   => 'cf-badge-danger',
                                str_contains($log['action'], 'delete')   => 'cf-badge-danger',
                                str_contains($log['action'], 'role')     => 'cf-badge-ai',
                                str_contains($log['action'], 'handover') => 'cf-badge-stored',
                                default                                  => 'cf-badge-neutral',
                            };
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="font-monospace extra-small text-muted">#<?= (int)$log['id'] ?></div>
                                    <div class="small fw-semibold text-dark"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                                    <div class="extra-small text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?> WIB</div>
                                </td>
                                <td>
                                    <?php if (!empty($log['user_id'])): ?>
                                        <div class="fw-bold text-dark small"><?= htmlspecialchars($log['user_name'] ?? 'User #' . $log['user_id']) ?></div>
                                        <div class="extra-small text-muted"><?= htmlspecialchars($log['user_email'] ?? '') ?></div>
                                        <?php if (!empty($log['role_label'])): ?>
                                            <span class="cf-badge cf-badge-neutral extra-small mt-1"><?= htmlspecialchars($log['role_label']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="cf-badge cf-badge-neutral extra-small">System / Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cf-badge font-monospace extra-small <?= $actionBadge ?>">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log['entity_type'])): ?>
                                        <span class="cf-badge cf-badge-neutral extra-small">
                                            <?= htmlspecialchars($log['entity_type']) ?> #<?= (int)$log['entity_id'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted extra-small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-monospace extra-small text-dark"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></div>
                                    <div class="extra-small text-muted text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                                        <?= htmlspecialchars($log['user_agent'] ?? '-') ?>
                                    </div>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php if (!empty($log['payload'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 extra-small"
                                                data-bs-toggle="modal" data-bs-target="#modalPayload<?= (int)$log['id'] ?>">
                                            <i class="bi bi-code-slash me-1"></i> Lihat Data
                                        </button>

                                        <!-- Modal View Payload -->
                                        <div class="modal fade text-start" id="modalPayload<?= (int)$log['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow-lg rounded-4">
                                                    <div class="modal-header border-0 pb-0">
                                                        <h5 class="modal-title fw-bold text-dark fs-6">
                                                            <i class="bi bi-shield-check text-primary me-2"></i> Metadata Payload #<?= (int)$log['id'] ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="p-3 bg-dark text-light rounded-3 font-monospace extra-small overflow-auto" style="max-height: 350px;">
                                                            <?php
                                                            $decoded = json_decode($log['payload'], true);
                                                            echo htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                                            ?>
                                                        </div>
                                                        <small class="text-muted mt-2 d-block extra-small">
                                                            🔒 Seluruh kredensial sensitif otomatis dibersihkan oleh AuditLogService.
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted extra-small fst-italic">Kosong</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 p-3 d-flex justify-content-between align-items-center">
                <small class="text-muted">Halaman <?= $page ?> dari <?= $totalPages ?></small>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="/admin/audit-logs?page=<?= $page - 1 ?>&keyword=<?= urlencode($keyword) ?>&action=<?= urlencode($action) ?>&entity_type=<?= urlencode($entityType) ?>">&laquo; Prev</a></li>
                    <?php endif; ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="/admin/audit-logs?page=<?= $p ?>&keyword=<?= urlencode($keyword) ?>&action=<?= urlencode($action) ?>&entity_type=<?= urlencode($entityType) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="/admin/audit-logs?page=<?= $page + 1 ?>&keyword=<?= urlencode($keyword) ?>&action=<?= urlencode($action) ?>&entity_type=<?= urlencode($entityType) ?>">Next &raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.extra-small {
    font-size: 0.75rem;
}
.bg-purple-subtle {
    background-color: rgba(111, 66, 193, 0.12);
}
.text-purple {
    color: #6f42c1;
}
.border-purple-subtle {
    border-color: rgba(111, 66, 193, 0.25) !important;
}
</style>
