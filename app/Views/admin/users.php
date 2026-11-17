<?php
/**
 * View: Manajemen Pengguna System (Admin User Management)
 * File: app/Views/admin/users.php
 */
$appEnv = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development'));
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/admin">Beranda admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Manajemen pengguna</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1">
                Manajemen pengguna sistem
            </h1>
            <p class="text-muted mb-0">
                Kelola akun civitas, petugas L&amp;F Center, dan administrator kampus. Total: <strong><?= number_format($totalUsers) ?> User</strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/users/import" class="btn btn-outline-primary rounded-pill px-3 shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Impor Civitas (.xlsx)
            </a>
            <button type="button" class="btn btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateUser">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah pengguna baru
            </button>
        </div>
    </div>

    <!-- Tampilan SEKALI Kata Sandi Sementara Massal dari Admin (Prompt 34B) -->
    <?php if (!empty($_SESSION['bulk_temp_passwords_reveal'])): ?>
        <?php 
        $bulkList = $_SESSION['bulk_temp_passwords_reveal']; 
        $bulkCount = count($bulkList);
        ?>
        <div class="alert alert-warning border-warning shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex align-items-start gap-3">
                <div class="p-2 bg-warning text-dark rounded-circle flex-shrink-0">
                    <i class="bi bi-shield-lock-fill fs-3"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-key-fill text-warning me-1"></i> Kata Sandi Sementara Massal Berhasil Dibuat (<?= $bulkCount ?> Akun)
                        </h5>
                        <a href="/admin/users/download-temp-passwords-csv" class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm">
                            <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> Unduh Berkas CSV (NIM &amp; Kata Sandi)
                        </a>
                    </div>
                    <div class="alert alert-danger py-2 px-3 small border-0 mb-3 rounded-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> 
                        <strong>PENTING &amp; RAHASIA:</strong> Daftar kata sandi ini <strong>hanya dapat dilihat/diunduh SEKALI</strong> dan tidak disimpan di server maupun catatan log sistem. Pastikan Anda mengunduh CSV atau mencatatnya sekarang sebelum berpindah halaman. Bagikan kata sandi secara rahasia dan terpisah ke masing-masing mahasiswa/pengguna. Pengguna <strong>wajib mengganti kata sandi</strong> saat pertama kali masuk (berlaku 7 hari).
                    </div>
                    <div class="table-responsive bg-white rounded-3 border border-warning-subtle p-2" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-striped align-middle mb-0 extra-small">
                            <thead class="table-light">
                                <tr>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Kata Sandi Sementara</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bulkList as $row): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold"><?= htmlspecialchars($row['nim']) ?></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['email']) ?></td>
                                        <td><code class="text-primary fw-bold fs-6"><?= htmlspecialchars($row['temp_password']) ?></code></td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="copyToClipboard('<?= htmlspecialchars($row['temp_password'], ENT_QUOTES, 'UTF-8') ?>', this, 'Kata sandi')">
                                                <i class="bi bi-clipboard"></i> Salin
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tampilan SEKALI Kata Sandi Sementara dari Admin -->
    <?php if (!empty($_SESSION['temp_password_reveal'])): ?>
        <?php 
        $tp = $_SESSION['temp_password_reveal']; 
        unset($_SESSION['temp_password_reveal']); 
        ?>
        <div class="alert alert-warning border-warning shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex align-items-start gap-3">
                <div class="p-2 bg-warning text-dark rounded-circle flex-shrink-0">
                    <i class="bi bi-key-fill fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-eye-slash-fill me-1"></i> Kata Sandi Sementara Berhasil Dibuat (Hanya Ditampilkan Sekali!)
                    </h5>
                    <p class="small text-dark mb-2">
                        Berikan kata sandi ini secara aman kepada pengguna <strong><?= htmlspecialchars($tp['user_name']) ?></strong> (<code><?= htmlspecialchars($tp['email']) ?></code>). Pengguna <strong>wajib</strong> menggantinya saat pertama kali masuk ke sistem.
                    </p>
                    <div class="d-flex align-items-center gap-2 bg-white p-2.5 rounded-3 border border-warning-subtle" style="max-width: 420px;">
                        <span class="font-monospace fs-5 fw-bold text-primary px-2" id="tempPwdText"><?= htmlspecialchars($tp['temp_password']) ?></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="copyToClipboard('<?= htmlspecialchars($tp['temp_password'], ENT_QUOTES, 'UTF-8') ?>', this, 'Kata sandi sementara')">
                            <i class="bi bi-clipboard"></i> Salin Kata Sandi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Panduan Jalur Aktivasi Akun Pengguna (Prompt 35B) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light-subtle overflow-hidden">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-diagram-3-fill text-primary me-2"></i>Panduan Jalur Aktivasi Akun Pengguna
                </h6>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 extra-small">
                    Total Belum Aktif: <strong><?= (int)($inactiveCount ?? 0) ?> akun</strong>
                </span>
            </div>
            <div class="row g-3">
                <!-- Jalur A: Mandiri -->
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 extra-small">Jalur A</span>
                            <span class="fw-bold text-dark small">Pengguna Mandiri</span>
                        </div>
                        <p class="extra-small text-muted mb-2">
                            Admin mengimpor akun (status belum aktif). Pengguna membuka menu <strong>Aktivasi Akun</strong> di halaman login, memasukkan NIM/email, menerima kode OTP di emailnya, dan membuat kata sandi sendiri. Akun otomatis aktif setelah OTP terverifikasi.
                        </p>
                        <div class="p-2 bg-light rounded-2 extra-small text-primary border-start border-primary border-3">
                            <i class="bi bi-info-circle me-1"></i>Aksi massal <em>"Kirim ulang aktivasi OTP"</em> mengirim ulang kode/undangan ke email terdaftar (memerlukan email asli yang aktif).
                        </div>
                    </div>
                </div>

                <!-- Jalur B: Dibantu Admin -->
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 extra-small">Jalur B</span>
                            <span class="fw-bold text-dark small">Dibantu Administrator</span>
                        </div>
                        <p class="extra-small text-muted mb-2">
                            Untuk akun dengan email palsu, belum memiliki email kampus, atau kendala akses. Admin menggunakan aksi <strong>"Setel password sementara"</strong> (massal atau per akun). Kata sandi acak unik (14 karakter) digenerate per akun.
                        </p>
                        <div class="p-2 bg-light rounded-2 extra-small text-warning-emphasis border-start border-warning border-3">
                            <i class="bi bi-key-fill me-1"></i>Akun langsung dapat login dan <strong>wajib mengganti kata sandi</strong> saat login pertama (berlaku 7 hari).
                        </div>
                    </div>
                </div>

                <!-- Status Aktif / Nonaktif -->
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-secondary text-white rounded-pill px-2.5 py-1 extra-small">Status</span>
                            <span class="fw-bold text-dark small">Aktifkan / Nonaktifkan</span>
                        </div>
                        <p class="extra-small text-muted mb-2">
                            Aksi <strong>Aktifkan / Nonaktifkan</strong> (satu per satu atau massal) hanya mengubah status akses akun yang <strong>SUDAH memiliki kata sandi</strong>.
                        </p>
                        <div class="p-2 bg-light rounded-2 extra-small text-danger border-start border-danger border-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Perhatian:</strong> Jangan tertukar! Akun baru tanpa kata sandi tidak bisa login hanya dengan diaktifkan statusnya. Gunakan Jalur A atau Jalur B.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="cf-card p-4 mb-4">
        <form action="/admin/users" method="GET" class="row g-3">
            <div class="col-12 col-md-5">
                <label class="form-label extra-small text-muted fw-bold">CARI USER</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="keyword" class="form-control bg-light border-start-0 ps-0" 
                           placeholder="Nama, email, NPM/NIP, telepon..." value="<?= htmlspecialchars($keyword) ?>">
                </div>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label extra-small text-muted fw-bold">FILTER ROLE</label>
                <select name="role_id" class="form-select bg-light">
                    <option value="">-- Semua Role --</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= ((int)$roleId === (int)$r['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['label']) ?> (<?= htmlspecialchars($r['name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label extra-small text-muted fw-bold">FILTER STATUS</label>
                <select name="status" class="form-select bg-light">
                    <option value="">-- Semua Status --</option>
                    <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>Active (Aktif)</option>
                    <option value="inactive" <?= ($status === 'inactive') ? 'selected' : '' ?>>Belum aktif (<?= (int)($inactiveCount ?? 0) ?>)</option>
                    <option value="suspended" <?= ($status === 'suspended') ? 'selected' : '' ?>>Suspended (Ditangguhkan)</option>
                    <option value="deleted" <?= ($status === 'deleted') ? 'selected' : '' ?>>Dihapus Permanen (<?= (int)($deletedCount ?? 0) ?>)</option>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <?php if (!empty($keyword) || !empty($roleId) || !empty($status)): ?>
                    <a href="/admin/users" class="btn btn-outline-secondary btn-icon" title="Reset Filter">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php
    $effectiveInactiveCount = (int)($status === 'inactive' ? ($totalUsers ?? 0) : ($inactiveFilteredCount ?? $inactiveCount ?? 0));
    ?>

    <!-- Users Table Card with Bulk Action Bar (Prompt 34B & Prompt 35B) -->
    <div class="cf-card overflow-hidden"
         id="usersTableCard"
         data-total-inactive="<?= $effectiveInactiveCount ?>">
        <!-- Bulk Action Toolbar -->
        <div class="bg-light-subtle border-bottom px-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-pill px-2.5 py-1 extra-small" id="selectedBadge">0 Dipilih</span>
                <span class="small text-muted" id="selectedHelpText">Pilih akun belum aktif untuk menjalankan aksi massal (maks 100).</span>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnSelectAllInactive">
                    <i class="bi bi-check2-square me-1"></i> Pilih Semua Belum Aktif
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="btnTriggerBulkResend" disabled>
                    <i class="bi bi-envelope-arrow-up-fill me-1"></i> Kirim Ulang OTP Massal
                </button>
                <button type="button" class="btn btn-sm btn-warning rounded-pill px-3 text-dark fw-semibold shadow-sm" id="btnTriggerBulkPassword" disabled>
                    <i class="bi bi-key-fill me-1"></i> Setel Password Sementara Massal
                </button>
            </div>
        </div>

        <form id="formBulkAction" action="/admin/users/bulk-action" method="POST">
            <?= \App\Middleware\CsrfMiddleware::field() ?>
            <input type="hidden" name="action" id="bulkActionInput" value="">
            <input type="hidden" name="select_all_inactive" id="bulkSelectAllInactive" value="0">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="filter_keyword" value="<?= htmlspecialchars($keyword ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="filter_role_id" value="<?= (int)($roleId ?? 0) ?>">
            <div id="bulkSelectedIdsContainer"></div>

            <div class="table-responsive cf-table-wrapper">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light extra-small text-uppercase tracking-wider text-muted">
                        <tr>
                            <th class="ps-4" style="width: 40px;">
                                <input type="checkbox" id="checkAllTable" class="form-check-input" title="Pilih Semua di Halaman Ini">
                            </th>
                            <th>Nama &amp; Identity</th>
                            <th>Email</th>
                            <th>Role Hak Akses</th>
                            <th>Status Akun</th>
                            <th>Departemen / Unit</th>
                            <th>Terdaftar</th>
                            <th class="pe-4 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <?php
                            $tableColspan = 8;
                            $icon = 'bi-person-x';
                            $title = 'Tidak Ada Pengguna Ditemukan';
                            $message = 'Tidak ada pengguna yang sesuai dengan kriteria filter atau pencarian Anda.';
                            require BASE_PATH . '/app/Views/partials/empty_state.php';
                            ?>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <?php
                                $isDeleted = !empty($u['deleted_at']);
                                $isSelf = ((int)$u['id'] === (int)($user['id'] ?? 0));
                                $roleBadge = match ($u['role_name'] ?? '') {
                                    'admin'   => 'cf-badge-danger',
                                    'petugas' => 'cf-badge-ai',
                                    default   => 'cf-badge-neutral',
                                };

                                if ($isDeleted) {
                                    $statusBadge = 'cf-badge-neutral';
                                    $statusLabel = 'Dihapus';
                                } else {
                                    $statusBadge = match ($u['status'] ?? 'active') {
                                        'active'    => 'cf-badge-found',
                                        'inactive'  => 'cf-badge-warning',
                                        'suspended' => 'cf-badge-danger',
                                        default     => 'cf-badge-neutral',
                                    };
                                    $statusLabel = ucfirst(htmlspecialchars($u['status'] ?? 'active'));
                                }

                                $isInactive = ((int)($u['is_active'] ?? 1) === 0) || (($u['status'] ?? '') === 'inactive');
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if ($isDeleted): ?>
                                            <input type="checkbox" class="form-check-input" disabled title="Akun telah dihapus permanen">
                                        <?php else: ?>
                                            <input type="checkbox" class="form-check-input user-row-checkbox" 
                                                   value="<?= (int)$u['id'] ?>" 
                                                   data-is-inactive="<?= $isInactive ? '1' : '0' ?>"
                                                   data-user-name="<?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                   data-nim="<?= htmlspecialchars($u['identity_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($u['name']) ?></div>
                                        <?php if (!empty($u['identity_number'])): ?>
                                            <div class="extra-small text-muted font-monospace"><i class="bi bi-card-text me-1"></i><?= htmlspecialchars($u['identity_number']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                    <div class="small text-dark"><?= htmlspecialchars($u['email']) ?></div>
                                    <?php if (!empty($u['phone'])): ?>
                                        <div class="extra-small text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($u['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cf-badge <?= $roleBadge ?>">
                                        <?= htmlspecialchars($u['role_label'] ?? $u['role_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cf-badge <?= $statusBadge ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-dark"><?= htmlspecialchars($u['department'] ?? '-') ?></span>
                                </td>
                                <td class="extra-small text-muted">
                                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php if ($isDeleted): ?>
                                        <span class="cf-badge cf-badge-neutral">Dihapus</span>
                                    <?php else: ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Kelola Akun
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                            <!-- Ubah Data Akun -->
                                            <li class="dropdown-header extra-small text-uppercase">Data Akun</li>
                                            <li>
                                                <button type="button" class="dropdown-item small text-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= (int)$u['id'] ?>">
                                                    <i class="bi bi-pencil-square me-1"></i> Ubah Data Akun
                                                </button>
                                            </li>

                                            <li><hr class="dropdown-divider"></li>
                                            <!-- Ubah Role -->
                                            <li class="dropdown-header extra-small text-uppercase">Ubah Role</li>
                                            <?php foreach ($roles as $r): ?>
                                                <?php if ((int)$r['id'] !== (int)$u['role_id']): ?>
                                                    <li>
                                                        <form action="/admin/users/<?= (int)$u['id'] ?>/role" method="POST"
                                                              data-cf-confirm="Ubah role akun <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?> menjadi <?= htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8') ?>?"
                                                              data-cf-title="Konfirmasi Perubahan Role"
                                                              data-cf-confirm-text="Ubah Role"
                                                              data-cf-variant="warning">
                                                            <?= \App\Middleware\CsrfMiddleware::field() ?>
                                                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="role_id" value="<?= (int)$r['id'] ?>">
                                                            <button type="submit" class="dropdown-item small">
                                                                &rarr; Ubah ke <?= htmlspecialchars($r['label']) ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>

                                            <li><hr class="dropdown-divider"></li>
                                            <li class="dropdown-header extra-small text-uppercase">Ubah Status Akun</li>
                                            <li class="px-3 py-1 extra-small text-muted fst-italic">Hanya untuk akun yang sudah memiliki kata sandi. Akun belum aktif memerlukan aktivasi via Jalur A atau B.</li>
                                            <?php if ($u['status'] !== 'active'): ?>
                                                <li>
                                                    <form action="/admin/users/<?= (int)$u['id'] ?>/status" method="POST"
                                                          data-cf-confirm="Aktifkan kembali akses login akun <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>? Perhatian: Aksi ini hanya berlaku efektif jika akun sudah memiliki kata sandi."
                                                          data-cf-title="Konfirmasi Aktifkan Akun"
                                                          data-cf-confirm-text="Aktifkan Akun"
                                                          data-cf-variant="success">
                                                        <?= \App\Middleware\CsrfMiddleware::field() ?>
                                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="status" value="active">
                                                        <button type="submit" class="dropdown-item small text-success">
                                                            <i class="bi bi-check-circle me-1"></i> Aktifkan Akun (Active)
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($u['status'] !== 'inactive'): ?>
                                                <li>
                                                    <form action="/admin/users/<?= (int)$u['id'] ?>/status" method="POST"
                                                          data-cf-confirm="Nonaktifkan akun <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>? Pengguna tidak akan dapat mengakses sistem."
                                                          data-cf-title="Konfirmasi Nonaktifkan Akun"
                                                          data-cf-confirm-text="Nonaktifkan"
                                                          data-cf-variant="warning">
                                                        <?= \App\Middleware\CsrfMiddleware::field() ?>
                                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="status" value="inactive">
                                                        <button type="submit" class="dropdown-item small text-secondary">
                                                            <i class="bi bi-pause-circle me-1"></i> Nonaktifkan (Inactive)
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($u['status'] !== 'suspended'): ?>
                                                <li>
                                                    <form action="/admin/users/<?= (int)$u['id'] ?>/status" method="POST"
                                                          data-cf-confirm="Tangguhkan (suspend) akun <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>? Semua sesi aktif akan dihentikan dan hak akses dibekukan."
                                                          data-cf-title="Konfirmasi Penangguhan Akun"
                                                          data-cf-confirm-text="Tangguhkan Akun"
                                                          data-cf-variant="danger">
                                                        <?= \App\Middleware\CsrfMiddleware::field() ?>
                                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="status" value="suspended">
                                                        <button type="submit" class="dropdown-item small text-danger">
                                                            <i class="bi bi-slash-circle me-1"></i> Tangguhkan (Suspend)
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>

                                            <!-- Tindakan Keamanan & Kredensial -->
                                            <li><hr class="dropdown-divider"></li>
                                            <li class="dropdown-header extra-small text-uppercase">Tindakan Keamanan</li>
                                            <li>
                                                <form action="/admin/users/<?= (int)$u['id'] ?>/resend-activation" method="POST"
                                                      data-cf-confirm="Kode OTP aktivasi baru akan di-generate dan dikirimkan ke email <?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>. Kode sebelumnya tidak dapat digunakan lagi."
                                                      data-cf-title="Kirim Ulang OTP Aktivasi"
                                                      data-cf-confirm-text="Kirimkan OTP"
                                                      data-cf-variant="primary">
                                                    <?= \App\Middleware\CsrfMiddleware::field() ?>
                                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="dropdown-item small text-primary">
                                                        <i class="bi bi-send-check me-1"></i> Kirim Ulang Aktivasi OTP
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form action="/admin/users/<?= (int)$u['id'] ?>/temp-password" method="POST"
                                                      data-cf-confirm="Setel kata sandi sementara baru untuk akun <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>. Pengguna wajib menggantinya saat pertama kali login kembali."
                                                      data-cf-title="Setel Password Sementara"
                                                      data-cf-confirm-text="Setel Password"
                                                      data-cf-variant="warning">
                                                    <?= \App\Middleware\CsrfMiddleware::field() ?>
                                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="dropdown-item small text-warning text-dark">
                                                        <i class="bi bi-key-fill me-1"></i> Setel Password Sementara
                                                    </button>
                                                </form>
                                            </li>
                                            <?php if ($appEnv === 'local' && empty($u['email_verified_at'])): ?>
                                                <li>
                                                    <form action="/admin/users/<?= (int)$u['id'] ?>/mark-verified" method="POST"
                                                          data-cf-confirm="Tandai alamat email <?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?> milik <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?> sebagai terverifikasi tanpa verifikasi OTP."
                                                          data-cf-title="Verifikasi Email Manual"
                                                          data-cf-confirm-text="Tandai Terverifikasi"
                                                          data-cf-variant="success">
                                                        <?= \App\Middleware\CsrfMiddleware::field() ?>
                                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="dropdown-item small text-success">
                                                            <i class="bi bi-patch-check-fill me-1"></i> Tandai Terverifikasi (Local Dev)
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($isInactive && !$isSelf): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li class="dropdown-header extra-small text-uppercase text-danger fw-bold">Hapus Akun</li>
                                                <li>
                                                    <button type="button" 
                                                            class="dropdown-item small text-danger btn-open-delete-modal"
                                                            data-user-id="<?= (int)$u['id'] ?>"
                                                            data-user-name="<?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-user-email="<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-user-role="<?= htmlspecialchars($u['role_label'] ?? $u['role_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-user-status="<?= ucfirst(htmlspecialchars($u['status'] ?? 'inactive')) ?>">
                                                        <i class="bi bi-trash3-fill me-1"></i> Hapus Permanen
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>

                                    <!-- Modal Ubah Data Akun -->
                                    <div class="modal fade text-start" id="editUserModal<?= (int)$u['id'] ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?= (int)$u['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow rounded-4">
                                                <form action="/admin/users/<?= (int)$u['id'] ?>/edit" method="POST">
                                                    <?= \App\Middleware\CsrfMiddleware::field() ?>
                                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/users', ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="modal-header border-0 pb-0">
                                                        <div>
                                                            <h5 class="modal-title fw-bold" id="editUserModalLabel<?= (int)$u['id'] ?>">
                                                                <i class="bi bi-person-gear text-primary me-2"></i>Ubah Data Akun
                                                            </h5>
                                                            <p class="text-muted extra-small mb-0">Perbarui data identitas pengguna #<?= (int)$u['id'] ?> (<?= htmlspecialchars($u['name']) ?>)</p>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body py-3">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
                                                            <input type="text" name="name" class="form-control rounded-3" value="<?= htmlspecialchars($u['name']) ?>" required>
                                                        </div>

                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold text-dark">NIM / Nomor Identitas</label>
                                                                <input type="text" name="identity_number" class="form-control rounded-3" value="<?= htmlspecialchars($u['identity_number'] ?? '') ?>" placeholder="NIM / NIP">
                                                                <div class="form-text extra-small text-muted">Harus unik jika diisi.</div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold text-dark">Alamat Email <span class="text-danger">*</span></label>
                                                                <input type="email" name="email" class="form-control rounded-3" value="<?= htmlspecialchars($u['email']) ?>" required>
                                                                <div class="form-text extra-small text-muted">Harus unik.</div>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold text-dark">No. WhatsApp / Telepon</label>
                                                            <input type="text" name="phone" class="form-control rounded-3" value="<?= htmlspecialchars($u['phone'] ?? '') ?>" placeholder="08123456789 atau 628123456789">
                                                            <div class="form-text extra-small text-muted">Nomor dinormalisasi ke format standar secara otomatis.</div>
                                                        </div>

                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-7">
                                                                <label class="form-label small fw-semibold text-dark">Program Studi</label>
                                                                <input type="text" name="study_program" class="form-control rounded-3" value="<?= htmlspecialchars($u['study_program'] ?? '') ?>" placeholder="Contoh: Teknik Informatika">
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label small fw-semibold text-dark">Angkatan</label>
                                                                <input type="text" name="batch_year" class="form-control rounded-3" value="<?= htmlspecialchars($u['batch_year'] ?? '') ?>" placeholder="Contoh: 2023">
                                                            </div>
                                                        </div>

                                                        <div class="mb-2 p-3 bg-light rounded-3 border">
                                                            <label class="form-label small fw-semibold text-dark mb-1">
                                                                Alasan Perubahan <span class="text-muted extra-small">(Wajib jika mengubah Email atau NIM)</span>
                                                            </label>
                                                            <textarea name="change_reason" class="form-control bg-white rounded-2" rows="2" placeholder="Tuliskan alasan perubahan data (misal: koreksi NIM dari data BAAK, dsb)..."></textarea>
                                                            <div class="form-text extra-small text-muted mt-1">
                                                                <i class="bi bi-info-circle me-1"></i>Perubahan email membatalkan antrean notifikasi lama, mereset verifikasi, dan mengirim peringatan keamanan ke email lama.
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4">
                                                            <i class="bi bi-check2 me-1"></i>Simpan Perubahan
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </form>

        <!-- Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 p-3 d-flex justify-content-between align-items-center">
                <small class="text-muted">Halaman <?= $page ?> dari <?= $totalPages ?></small>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="/admin/users?page=<?= $page - 1 ?>&keyword=<?= urlencode($keyword) ?>&role_id=<?= $roleId ?>&status=<?= $status ?>">&laquo; Prev</a></li>
                    <?php endif; ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="/admin/users?page=<?= $p ?>&keyword=<?= urlencode($keyword) ?>&role_id=<?= $roleId ?>&status=<?= $status ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="/admin/users?page=<?= $page + 1 ?>&keyword=<?= urlencode($keyword) ?>&role_id=<?= $roleId ?>&status=<?= $status ?>">Next &raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah User Baru oleh Admin -->
<div class="modal fade" id="modalCreateUser" tabindex="-1" aria-labelledby="modalCreateUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalCreateUserLabel">
                    <i class="bi bi-person-plus-fill text-primary me-2"></i> Tambah Pengguna Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/admin/users/create" method="POST">
                <?= \App\Middleware\CsrfMiddleware::field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Contoh: Budi Santoso">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Kampus / Akun <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="user@campus.ac.id">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required placeholder="Minimal 6 karakter" minlength="6">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Role Hak Akses <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= ((int)$r['id'] === 3) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['label']) ?> (<?= htmlspecialchars($r['name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nomor Identitas (NPM / NIP / NIK)</label>
                            <input type="text" name="identity_number" class="form-control" placeholder="Contoh: 21081010012">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="phone" class="form-control" placeholder="08123456789">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Fakultas / Departemen / Unit</label>
                            <input type="text" name="department" class="form-control" placeholder="Fakultas Ilmu Komputer">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Status Akun</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active (Aktif)</option>
                                <option value="inactive">Inactive (Nonaktif)</option>
                                <option value="suspended">Suspended (Ditangguhkan)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Kirim Ulang OTP Massal (Prompt 34B) -->
<div class="modal fade" id="modalBulkResendOtp" tabindex="-1" aria-labelledby="modalBulkResendOtpLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalBulkResendOtpLabel">
                    <i class="bi bi-envelope-arrow-up-fill text-primary me-2"></i> Konfirmasi Kirim Ulang OTP Massal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-2">
                    Target Akun: <strong id="resendTargetCount" class="text-dark">0 akun terpilih</strong>.
                </p>
                <div class="alert alert-info py-2 px-3 small border-0 mb-3 rounded-3">
                    <i class="bi bi-info-circle-fill me-1"></i> <strong>Ringkasan Dampak:</strong> Email berisi kode OTP aktivasi baru akan diantrekan ke seluruh akun terpilih (tunduk pada jeda 60 detik &amp; allowlist notifikasi). Akun tetap berstatus belum aktif hingga pengguna menyelesaikan verifikasi kode OTP dan membuat kata sandi mandiri (Jalur A).
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" id="btnConfirmBulkResend">
                    <i class="bi bi-send-fill me-1"></i> Jalankan Pengiriman
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Setel Password Sementara Massal (Prompt 34B) -->
<div class="modal fade" id="modalBulkTempPassword" tabindex="-1" aria-labelledby="modalBulkTempPasswordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalBulkTempPasswordLabel">
                    <i class="bi bi-shield-lock-fill text-warning me-2"></i> Konfirmasi Setel Kata Sandi Sementara Massal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning border-0 rounded-3 p-3 mb-3">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Keamanan Ketat &amp; Kerahasiaan Data (Prompt 34B)</h6>
                    <ul class="extra-small text-dark mb-0 ps-3">
                        <li><strong>TIDAK ADA satu password bersama:</strong> Setiap akun akan menerima kata sandi acak <strong>UNIK (14 karakter)</strong>.</li>
                        <li>Kata sandi sementara disimpan <strong>hanya dalam bentuk hash</strong> dan berlaku selama <strong>7 hari</strong>.</li>
                        <li>Pengguna <strong>wajib mengganti kata sandi pribadi</strong> saat pertama kali masuk ke sistem.</li>
                        <li>Daftar NIM + Kata Sandi hanya dapat dilihat/diunduh <strong>SEKALI (CSV)</strong> setelah proses selesai, tidak disimpan di server, dan tidak dicatat di log sistem.</li>
                        <li>Daftar kata sandi tersebut bersifat sangat rahasia dan wajib Anda bagikan secara terpisah ke masing-masing pemilik akun.</li>
                    </ul>
                </div>

                <p class="text-dark small mb-2">
                    Target Akun: <strong id="tempTargetCount" class="text-primary fs-6">0 akun terpilih</strong>.
                </p>
                <div class="alert alert-warning-subtle py-2 px-3 small border border-warning-subtle mb-3 rounded-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> <strong>Ringkasan Dampak:</strong> Kata sandi acak unik (14 karakter) akan disetel per akun, dan status akun langsung aktif dengan kewajiban mengganti kata sandi saat login pertama (Jalur B). Harap segera unduh berkas CSV setelah konfirmasi karena daftar kata sandi tidak disimpan di server.
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">
                        Masukkan Kata Sandi Administrator Anda <span class="text-danger">*</span>
                    </label>
                    <input type="password" id="inputAdminPassword" class="form-control rounded-3" 
                           placeholder="Ketik kata sandi akun Admin Anda untuk verifikasi identitas..." required autocomplete="current-password">
                    <div class="form-text extra-small text-muted">
                        Otorisasi diperlukan untuk mencegah tindakan tidak sah terhadap akun pengguna.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning rounded-pill px-4 text-dark fw-bold shadow-sm" id="btnConfirmBulkPassword">
                    <i class="bi bi-key-fill me-1"></i> Proses Setel Kata Sandi Massal
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Akun Permanen (BUG FIX 03) -->
<div class="modal fade text-start" id="deleteUserPermanentModal" tabindex="-1" aria-labelledby="deleteUserPermanentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="deleteUserPermanentModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Hapus Akun Permanen
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formDeleteUserPermanent" method="POST" action="">
                <?= \App\Middleware\CsrfMiddleware::field() ?>
                <div class="modal-body p-4">
                    <div class="alert alert-danger-subtle border border-danger-subtle rounded-3 p-3 mb-3 text-danger-emphasis extra-small">
                        <strong class="d-block mb-1"><i class="bi bi-shield-slash-fill me-1"></i> Tindakan ini tidak dapat dibatalkan!</strong>
                        Akun akan dihapus secara permanen dari sistem. Jika akun memiliki histori laporan kehilangan/temuan, klaim, atau percakapan, data histori transaksi akan tetap tersimpan demi integritas sistem, namun seluruh identitas akun akan dianonimkan (anonymized).
                    </div>

                    <div class="card bg-light border-0 rounded-3 p-3 mb-3">
                        <div class="row g-2 extra-small">
                            <div class="col-4 text-muted fw-semibold">Nama Pengguna:</div>
                            <div class="col-8 fw-bold text-dark" id="delModalUserName">-</div>
                            <div class="col-4 text-muted fw-semibold">Alamat Email:</div>
                            <div class="col-8 font-monospace text-dark" id="delModalUserEmail">-</div>
                            <div class="col-4 text-muted fw-semibold">Role Sistem:</div>
                            <div class="col-8 text-dark" id="delModalUserRole">-</div>
                            <div class="col-4 text-muted fw-semibold">Status Saat Ini:</div>
                            <div class="col-8 text-dark" id="delModalUserStatus">-</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="delModalConfirmInput" class="form-label small fw-bold text-dark">
                            Ketik <span class="badge bg-danger text-white font-monospace">HAPUS PERMANEN</span> untuk mengonfirmasi:
                        </label>
                        <input type="text" 
                               id="delModalConfirmInput" 
                               name="confirmation" 
                               class="form-control rounded-3 font-monospace" 
                               placeholder="HAPUS PERMANEN" 
                               autocomplete="off" 
                               required>
                        <div class="form-text extra-small text-muted">
                            Tombol hapus hanya akan aktif setelah Anda mengetik frasa di atas secara tepat dengan huruf besar.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pe-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="delModalSubmitBtn" class="btn btn-danger rounded-pill px-4 fw-semibold shadow-sm" disabled>
                        <i class="bi bi-trash3-fill me-1"></i> Hapus Permanen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAllTable');
    const rowCheckboxes = document.querySelectorAll('.user-row-checkbox');
    const btnSelectAllInactive = document.getElementById('btnSelectAllInactive');
    const btnTriggerBulkResend = document.getElementById('btnTriggerBulkResend');
    const btnTriggerBulkPassword = document.getElementById('btnTriggerBulkPassword');
    const selectedBadge = document.getElementById('selectedBadge');
    const formBulkAction = document.getElementById('formBulkAction');
    const bulkActionInput = document.getElementById('bulkActionInput');
    const bulkSelectAllInactive = document.getElementById('bulkSelectAllInactive');
    const bulkSelectedIdsContainer = document.getElementById('bulkSelectedIdsContainer');

    let selectAllInactiveFlag = false;
    const totalInactiveFromData = parseInt(document.getElementById('usersTableCard')?.dataset?.totalInactive || '0', 10);

    function getSelectedCheckboxes() {
        return Array.from(rowCheckboxes).filter(cb => cb.checked);
    }

    function updateToolbar() {
        const selected = getSelectedCheckboxes();
        let targetText = '';
        let badgeText = '';

        if (selectAllInactiveFlag) {
            const displayCount = totalInactiveFromData > 0 ? totalInactiveFromData : selected.length;
            badgeText = `${displayCount} akun dipilih`;
            targetText = `${displayCount} akun (seluruh hasil filter lintas halaman, maks. 100 per proses)`;
            const helpElem = document.getElementById('selectedHelpText');
            if (helpElem) {
                helpElem.textContent = `Seluruh ${displayCount} akun belum aktif hasil filter lintas halaman telah dipilih (maks. 100 per proses).`;
            }
        } else {
            badgeText = `${selected.length} Dipilih`;
            targetText = `${selected.length} akun terpilih`;
            const helpElem = document.getElementById('selectedHelpText');
            if (helpElem) {
                helpElem.textContent = `Pilih akun belum aktif untuk menjalankan aksi massal (maks 100).`;
            }
        }
        
        selectedBadge.textContent = badgeText;
        
        const hasSelection = (selectAllInactiveFlag && totalInactiveFromData > 0) || selected.length > 0;
        btnTriggerBulkResend.disabled = !hasSelection;
        btnTriggerBulkPassword.disabled = !hasSelection;

        const resendTargetElem = document.getElementById('resendTargetCount');
        const tempTargetElem = document.getElementById('tempTargetCount');
        if (resendTargetElem) resendTargetElem.textContent = targetText;
        if (tempTargetElem) tempTargetElem.textContent = targetText;
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            selectAllInactiveFlag = false;
            rowCheckboxes.forEach(cb => {
                cb.checked = checkAll.checked;
            });
            updateToolbar();
        });
    }

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            selectAllInactiveFlag = false;
            updateToolbar();
        });
    });

    if (btnSelectAllInactive) {
        btnSelectAllInactive.addEventListener('click', function() {
            selectAllInactiveFlag = true;
            rowCheckboxes.forEach(cb => {
                cb.checked = (cb.dataset.isInactive === '1');
            });
            updateToolbar();
        });
    }

    function populateSelectedIdsToForm() {
        bulkSelectedIdsContainer.innerHTML = '';
        bulkSelectAllInactive.value = selectAllInactiveFlag ? '1' : '0';

        if (!selectAllInactiveFlag) {
            const selected = getSelectedCheckboxes();
            selected.forEach(cb => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'user_ids[]';
                hidden.value = cb.value;
                bulkSelectedIdsContainer.appendChild(hidden);
            });
        }
    }

    // Modal Triggers
    if (btnTriggerBulkResend) {
        btnTriggerBulkResend.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('modalBulkResendOtp'));
            modal.show();
        });
    }

    const btnConfirmBulkResend = document.getElementById('btnConfirmBulkResend');
    if (btnConfirmBulkResend) {
        btnConfirmBulkResend.addEventListener('click', function() {
            populateSelectedIdsToForm();
            bulkActionInput.value = 'resend_activation';
            formBulkAction.submit();
        });
    }

    if (btnTriggerBulkPassword) {
        btnTriggerBulkPassword.addEventListener('click', function() {
            document.getElementById('inputAdminPassword').value = '';
            const modal = new bootstrap.Modal(document.getElementById('modalBulkTempPassword'));
            modal.show();
        });
    }

    const btnConfirmBulkPassword = document.getElementById('btnConfirmBulkPassword');
    if (btnConfirmBulkPassword) {
        btnConfirmBulkPassword.addEventListener('click', function() {
            const adminPwd = document.getElementById('inputAdminPassword').value.trim();
            if (!adminPwd) {
                if (window.AppDialog && typeof window.AppDialog.result === 'function') {
                    window.AppDialog.result({
                        title: 'Kata Sandi Diperlukan',
                        message: 'Silakan masukkan kata sandi Administrator Anda terlebih dahulu.',
                        variant: 'warning'
                    });
                } else if (window.AppToast && typeof window.AppToast.show === 'function') {
                    window.AppToast.show('Silakan masukkan kata sandi Administrator Anda terlebih dahulu.', 'warning');
                }
                document.getElementById('inputAdminPassword').focus();
                return;
            }

            populateSelectedIdsToForm();
            bulkActionInput.value = 'set_temp_password';

            // Masukkan password admin ke form
            let adminPwdHidden = document.getElementById('hiddenAdminPassword');
            if (!adminPwdHidden) {
                adminPwdHidden = document.createElement('input');
                adminPwdHidden.type = 'hidden';
                adminPwdHidden.name = 'admin_password';
                adminPwdHidden.id = 'hiddenAdminPassword';
                bulkSelectedIdsContainer.appendChild(adminPwdHidden);
            }
            adminPwdHidden.value = adminPwd;

            formBulkAction.submit();
        });
    }

    // Modal Hapus Akun Permanen (BUG FIX 03)
    const delModalEl = document.getElementById('deleteUserPermanentModal');
    const delModalForm = document.getElementById('formDeleteUserPermanent');
    const delModalConfirmInput = document.getElementById('delModalConfirmInput');
    const delModalSubmitBtn = document.getElementById('delModalSubmitBtn');

    document.querySelectorAll('.btn-open-delete-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            const userEmail = this.dataset.userEmail;
            const userRole = this.dataset.userRole;
            const userStatus = this.dataset.userStatus;

            if (delModalForm) {
                delModalForm.action = '/admin/users/' + encodeURIComponent(userId) + '/delete';
            }
            const nameEl = document.getElementById('delModalUserName');
            const emailEl = document.getElementById('delModalUserEmail');
            const roleEl = document.getElementById('delModalUserRole');
            const statusEl = document.getElementById('delModalUserStatus');

            if (nameEl) nameEl.textContent = userName;
            if (emailEl) emailEl.textContent = userEmail;
            if (roleEl) roleEl.textContent = userRole;
            if (statusEl) statusEl.textContent = userStatus;

            if (delModalConfirmInput) delModalConfirmInput.value = '';
            if (delModalSubmitBtn) delModalSubmitBtn.disabled = true;

            if (delModalEl && typeof bootstrap !== 'undefined') {
                const modalInstance = bootstrap.Modal.getInstance(delModalEl) || new bootstrap.Modal(delModalEl);
                modalInstance.show();
            }
        });
    });

    if (delModalConfirmInput && delModalSubmitBtn) {
        delModalConfirmInput.addEventListener('input', function() {
            delModalSubmitBtn.disabled = (this.value.trim() !== 'HAPUS PERMANEN');
        });
    }
});
</script>

<style>
.bg-purple {
    background-color: #6f42c1;
}
.extra-small {
    font-size: 0.75rem;
}
.btn-xs {
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
}
</style>
