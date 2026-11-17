<?php
/**
 * View: Detail Percakapan Chatbot AI C-Find (Hanya-Baca)
 * File: app/Views/admin/chat_history_detail.php
 * Prompt 36C
 */

$id = (int)($detail['id'] ?? 0);
$status = $detail['status'] ?? 'dijawab';
$role = strtolower((string)($detail['role'] ?? 'guest'));

$badgeClass = match ($status) {
    'dijawab'            => 'bg-success-subtle text-success border border-success-subtle',
    'ditolak'            => 'bg-warning-subtle text-dark border border-warning-subtle fw-bold',
    'tidak bisa dijawab' => 'bg-danger-subtle text-danger border border-danger-subtle fw-bold',
    'gagal'              => 'bg-danger text-white fw-bold shadow-sm',
    default              => 'bg-secondary text-white',
};

$roleBadgeClass = match ($role) {
    'admin'   => 'bg-danger-subtle text-danger border border-danger-subtle',
    'petugas' => 'bg-info-subtle text-info border border-info-subtle',
    'civitas' => 'bg-primary-subtle text-primary border border-primary-subtle',
    default   => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
};
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/admin">Beranda admin</a></li>
                    <li class="breadcrumb-item"><a href="/admin/chat-history">Riwayat Chat AI</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Detail #<?= $id ?></li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1">
                <i class="bi bi-chat-left-text text-primary me-2"></i>Detail Percakapan Chat AI #<?= $id ?>
            </h1>
            <p class="text-muted mb-0">Peninjauan detail interaksi hanya-baca (read-only) untuk evaluasi kualitas dan keamanan layanan.</p>
        </div>
        <div>
            <a href="/admin/chat-history" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Riwayat
            </a>
        </div>
    </div>

    <!-- Security & Audit Notice -->
    <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3">
        <i class="bi bi-shield-check fs-3 text-info"></i>
        <div class="small">
            <strong>Catatan Keamanan &amp; Akuntabilitas:</strong> Pembukaan detail percakapan ini telah dicatat pada <strong>Audit Log Keamanan</strong> sistem. Seluruh data kontak (email dan nomor telepon) telah disamarkan dan tabel hasil disajikan dalam bentuk ringkasan tanpa menduplikasi data pribadi.
        </div>
    </div>

    <div class="row g-4">
        <!-- Metadata Column -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-body h-100">
                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                    <i class="bi bi-info-circle me-1 text-primary"></i>Metadata Percakapan
                </h5>

                <div class="mb-3">
                    <label class="extra-small text-muted text-uppercase fw-bold d-block">Status Respon</label>
                    <span class="badge rounded-pill px-3 py-2 <?= $badgeClass ?> mt-1">
                        <?= htmlspecialchars(ucwords($status)) ?>
                    </span>
                </div>

                <div class="mb-3">
                    <label class="extra-small text-muted text-uppercase fw-bold d-block">Waktu Interaksi</label>
                    <span class="small font-monospace text-dark">
                        <i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($detail['created_at']) ?>
                    </span>
                </div>

                <div class="mb-3">
                    <label class="extra-small text-muted text-uppercase fw-bold d-block">Peran Pengguna (Role)</label>
                    <span class="badge rounded-pill <?= $roleBadgeClass ?> mt-1">
                        <?= htmlspecialchars(ucfirst($role)) ?>
                    </span>
                </div>

                <div class="mb-3">
                    <label class="extra-small text-muted text-uppercase fw-bold d-block">Identitas Pengguna</label>
                    <?php if (!empty($detail['user_id'])): ?>
                        <div class="fw-semibold text-dark small">
                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($detail['user_name'] ?? 'User #' . $detail['user_id']) ?>
                        </div>
                        <div class="extra-small text-muted font-monospace">
                            <?= htmlspecialchars($detail['user_email'] ?? '') ?>
                        </div>
                    <?php else: ?>
                        <div class="cf-badge cf-badge-neutral font-monospace mt-1">
                            <i class="bi bi-incognito me-1"></i>Tamu (Guest Session)
                        </div>
                        <div class="extra-small text-muted font-monospace mt-1 text-break">
                            Hash: <?= htmlspecialchars($detail['session_hash']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="extra-small text-muted text-uppercase fw-bold d-block">Intent / Metrik</label>
                    <code class="extra-small text-primary font-monospace bg-light p-2 rounded d-block mt-1 text-break">
                        <?= htmlspecialchars($detail['intent'] ?? '-') ?>
                    </code>
                </div>

                <div class="row g-2 pt-2 border-top">
                    <div class="col-6">
                        <label class="extra-small text-muted text-uppercase fw-bold d-block">Durasi Proses</label>
                        <span class="fw-bold font-monospace small text-dark">
                            <?= number_format((int)$detail['duration_ms']) ?> ms
                        </span>
                    </div>
                    <div class="col-6">
                        <label class="extra-small text-muted text-uppercase fw-bold d-block">Token Digunakan</label>
                        <span class="fw-bold font-monospace small text-dark">
                            <?= number_format((int)$detail['tokens_used']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Column -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-body mb-4">
                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-person-circle text-secondary"></i>
                    Pertanyaan Pengguna
                </h5>
                <div class="p-3 rounded-3 bg-light border text-dark font-monospace small text-break">
                    <?= nl2br(htmlspecialchars($detail['question'])) ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 p-4 bg-body">
                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-robot text-primary"></i>
                    Jawaban Chatbot (Tersamar &amp; Diringkas)
                </h5>
                <div class="p-3 rounded-3 bg-light border text-dark small text-break">
                    <?= nl2br(htmlspecialchars($detail['answer'])) ?>
                </div>
            </div>
        </div>
    </div>
</div>

