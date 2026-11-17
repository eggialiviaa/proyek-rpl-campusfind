<?php
/**
 * View: Riwayat Percakapan Chatbot AI C-Find (Admin Monitoring)
 * File: app/Views/admin/chat_history.php
 * Prompt 36C — Menggantikan Prompt 27
 */

$rows = $historyData['rows'] ?? [];
$total = $historyData['total'] ?? 0;
$page = $historyData['page'] ?? 1;
$limit = $historyData['limit'] ?? 25;
$totalPages = $historyData['total_pages'] ?? 1;
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/admin">Beranda admin</a></li>
                    <li class="breadcrumb-item">Monitoring</li>
                    <li class="breadcrumb-item active" aria-current="page">Riwayat Chat AI</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1">
                <i class="bi bi-robot text-primary me-2"></i>Riwayat Percakapan Chat AI (C-Find)
            </h1>
            <p class="text-muted mb-0">
                Tinjau seluruh percakapan pengguna (Civitas, Petugas, Admin, Tamu) dengan asisten cerdas C-Find. Total tercatat: <strong><?= number_format($total) ?> Percakapan</strong> (Retensi: 30 hari).
            </p>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-body">
        <form action="/admin/chat-history" method="GET" class="row g-3">
            <div class="col-12 col-md-3">
                <label class="form-label extra-small text-muted fw-bold">CARI KATA KUNCI</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="keyword" class="form-control bg-light border-start-0 ps-0" 
                           placeholder="Pertanyaan, jawaban, intent..." value="<?= htmlspecialchars($filters['keyword'] ?? '') ?>">
                </div>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label extra-small text-muted fw-bold">ROLE</label>
                <select name="role" class="form-select bg-light">
                    <option value="">-- Semua Role --</option>
                    <option value="admin" <?= (($filters['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                    <option value="petugas" <?= (($filters['role'] ?? '') === 'petugas') ? 'selected' : '' ?>>Petugas</option>
                    <option value="civitas" <?= (($filters['role'] ?? '') === 'civitas') ? 'selected' : '' ?>>Civitas</option>
                    <option value="guest" <?= (($filters['role'] ?? '') === 'guest') ? 'selected' : '' ?>>Tamu (Guest)</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label extra-small text-muted fw-bold">STATUS</label>
                <select name="status" class="form-select bg-light">
                    <option value="">-- Semua Status --</option>
                    <option value="dijawab" <?= (($filters['status'] ?? '') === 'dijawab') ? 'selected' : '' ?>>Dijawab</option>
                    <option value="ditolak" <?= (($filters['status'] ?? '') === 'ditolak') ? 'selected' : '' ?>>Ditolak (Guardrail/RBAC)</option>
                    <option value="tidak bisa dijawab" <?= (($filters['status'] ?? '') === 'tidak bisa dijawab') ? 'selected' : '' ?>>Tidak Bisa Dijawab</option>
                    <option value="gagal" <?= (($filters['status'] ?? '') === 'gagal') ? 'selected' : '' ?>>Gagal (Error/Timeout)</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label extra-small text-muted fw-bold">PENGGUNA</label>
                <input type="text" name="user" class="form-control bg-light" 
                       placeholder="Nama, email, NIM..." value="<?= htmlspecialchars($filters['user'] ?? '') ?>">
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label extra-small text-muted fw-bold">RENTANG TANGGAL</label>
                <div class="input-group">
                    <input type="date" name="date_from" class="form-control bg-light" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" title="Dari Tanggal">
                    <span class="input-group-text bg-light">-</span>
                    <input type="date" name="date_to" class="form-control bg-light" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" title="Sampai Tanggal">
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                <a href="/admin/chat-history" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                    <i class="bi bi-funnel me-1"></i>Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="cf-card overflow-hidden">
        <div class="card-header bg-body border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">Daftar Percakapan C-Find</h5>
            <div class="d-flex align-items-center gap-2">
                <span class="cf-badge cf-badge-neutral">
                    <i class="bi bi-info-circle me-1"></i>Baris berwarna menandai pertanyaan yang perlu evaluasi
                </span>
            </div>
        </div>

        <div class="table-responsive cf-table-wrapper">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light extra-small text-uppercase text-muted">
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th style="width: 150px;">Waktu</th>
                        <th style="width: 100px;">Role</th>
                        <th style="width: 180px;">Pengguna</th>
                        <th>Pertanyaan</th>
                        <th style="width: 140px;">Intent / Metrik</th>
                        <th style="width: 150px;">Status</th>
                        <th style="width: 90px;" class="text-end">Durasi</th>
                        <th style="width: 90px;" class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <?php
                        $emptyIcon = 'bi-chat-left-dots';
                        $emptyTitle = 'Belum Ada Riwayat Percakapan';
                        $emptyMessage = 'Belum ada riwayat percakapan C-Find yang cocok dengan filter.';
                        $tableColspan = 9;
                        include __DIR__ . '/../partials/empty_state.php';
                        ?>
                    <?php else: ?>
                        <?php foreach ($rows as $idx => $r): ?>
                            <?php
                            $rowNum = ($page - 1) * $limit + $idx + 1;
                            $status = $r['status'] ?? 'dijawab';
                            $isAttentionNeeded = in_array($status, ['tidak bisa dijawab', 'ditolak', 'gagal'], true);
                            
                            // Highlighting baris & badge
                            $rowClass = match ($status) {
                                'gagal'              => 'table-danger border-start border-danger border-4',
                                'tidak bisa dijawab' => 'table-warning border-start border-warning border-4',
                                'ditolak'            => 'table-warning border-start border-warning border-4',
                                default              => '',
                            };

                            $badgeClass = match ($status) {
                                'dijawab'            => 'cf-badge cf-badge-found',
                                'ditolak'            => 'cf-badge cf-badge-warning',
                                'tidak bisa dijawab' => 'cf-badge cf-badge-warning',
                                'gagal'              => 'cf-badge cf-badge-danger',
                                default              => 'cf-badge cf-badge-neutral',
                            };

                            $roleBadgeClass = match (strtolower((string)$r['role'])) {
                                'admin'   => 'cf-badge cf-badge-danger',
                                'petugas' => 'cf-badge cf-badge-ai',
                                'civitas' => 'cf-badge cf-badge-neutral',
                                default   => 'cf-badge cf-badge-neutral',
                            };
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td class="ps-4 fw-bold text-muted extra-small"><?= $rowNum ?></td>
                                <td class="extra-small text-muted font-monospace">
                                    <i class="bi bi-clock me-1"></i><?= htmlspecialchars($r['created_at']) ?>
                                </td>
                                <td>
                                    <span class="<?= $roleBadgeClass ?>">
                                        <?= htmlspecialchars(ucfirst($r['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($r['user_id'])): ?>
                                        <div class="fw-semibold text-dark small text-truncate" style="max-width: 170px;">
                                            <?= htmlspecialchars($r['user_name'] ?? 'User #' . $r['user_id']) ?>
                                        </div>
                                        <div class="extra-small text-muted text-truncate font-monospace" style="max-width: 170px;">
                                            <?= htmlspecialchars($r['user_email'] ?? '') ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="cf-badge cf-badge-neutral font-monospace" title="Sesi hash tamu: <?= htmlspecialchars($r['session_hash']) ?>">
                                            <i class="bi bi-incognito me-1"></i>Tamu: <?= htmlspecialchars(substr($r['session_hash'], 0, 8)) ?>...
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-dark fw-medium text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($r['question']) ?>">
                                        <?= htmlspecialchars($r['question']) ?>
                                    </div>
                                    <div class="extra-small text-muted text-truncate" style="max-width: 320px;">
                                        <?= htmlspecialchars($r['answer']) ?>
                                    </div>
                                </td>
                                <td>
                                    <code class="extra-small text-primary font-monospace bg-light px-2 py-1 rounded">
                                        <?= htmlspecialchars($r['intent'] ?? '-') ?>
                                    </code>
                                </td>
                                <td>
                                    <span class="<?= $badgeClass ?>">
                                        <?php if ($status === 'gagal'): ?>
                                            <i class="bi bi-exclamation-octagon-fill me-1"></i>
                                        <?php elseif ($isAttentionNeeded): ?>
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        <?php else: ?>
                                            <i class="bi bi-check-circle-fill me-1"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars(ucwords($status)) ?>
                                    </span>
                                </td>
                                <td class="text-end font-monospace extra-small text-muted">
                                    <?= number_format((int)$r['duration_ms']) ?> ms
                                </td>
                                <td class="text-center pe-4">
                                    <a href="/admin/chat-history/<?= (int)$r['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 extra-small fw-semibold"
                                       title="Lihat Detail Percakapan">
                                        <i class="bi bi-eye me-1"></i>Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-body border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="extra-small text-muted">
                    Menampilkan <strong><?= count($rows) ?></strong> dari <strong><?= number_format($total) ?></strong> percakapan (Halaman <?= $page ?> dari <?= $totalPages ?>)
                </span>
                <nav aria-label="Navigasi Halaman Riwayat">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="/admin/chat-history?page=<?= $page - 1 ?>&<?= $queryString ?>" aria-label="Sebelumnya">
                                &laquo;
                            </a>
                        </li>
                        <?php
                        $startP = max(1, $page - 2);
                        $endP = min($totalPages, $page + 2);
                        for ($p = $startP; $p <= $endP; $p++):
                        ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                                <a class="page-link" href="/admin/chat-history?page=<?= $p ?>&<?= $queryString ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="/admin/chat-history?page=<?= $page + 1 ?>&<?= $queryString ?>" aria-label="Berikutnya">
                                &raquo;
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

