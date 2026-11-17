<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use Core\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\UserModel;
use App\Models\CategoryModel;
use App\Models\LocationModel;
use App\Models\StorageLocationModel;
use App\Services\AuditLogService;
use App\Services\SettingService;
use App\Services\CFind\CFindHistoryService;
use PDO;

/**
 * AdminController — Khusus untuk Super User / Administrator
 * 
 * Mengelola User Management, Master Data (Category, Location, Storage), 
 * Dashboard Monitoring Statistics, dan System Audit Logs.
 */
class AdminController extends BaseController
{
    private UserModel $userModel;
    private CategoryModel $categoryModel;
    private LocationModel $locationModel;
    private StorageLocationModel $storageModel;

    public function __construct()
    {
        // Guard ketat backend: hanya admin yang diizinkan
        AuthMiddleware::requireRole('admin');

        $this->userModel = new UserModel();
        $this->categoryModel = new CategoryModel();
        $this->locationModel = new LocationModel();
        $this->storageModel = new StorageLocationModel();
    }

    /**
     * GET /admin /admin/dashboard
     * Dashboard Utama Administrator
     */
    public function dashboard(array $params = []): void
    {
        $stats = $this->userModel->getDashboardStats();

        // 10 Aktivitas Audit Terbaru
        $pdo = Database::getInstance()->getPdo();
        $recentLogs = $pdo->query("
            SELECT a.*, u.name AS user_name, u.email AS user_email, r.label AS role_label
            FROM audit_logs a 
            LEFT JOIN users u ON a.user_id = u.id 
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY a.id DESC 
            LIMIT 10
        ")->fetchAll();

        $this->render('admin/dashboard', [
            'pageTitle'  => 'Dashboard Super User — Admin Campus Find',
            'stats'      => $stats,
            'recentLogs' => $recentLogs,
            'user'       => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * GET /admin/users
     * Manajemen Pengguna Sistem (Searching, Filtering, Modals)
     */
    public function users(array $params = []): void
    {
        $keyword = trim($_GET['keyword'] ?? '');
        $roleId = !empty($_GET['role_id']) ? (int)$_GET['role_id'] : null;
        $status = !empty($_GET['status']) ? trim($_GET['status']) : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $users = $this->userModel->searchAndFilter($keyword, $roleId, $status, $limit, $offset);
        $totalUsers = $this->userModel->countSearchAndFilter($keyword, $roleId, $status);
        $totalPages = (int) ceil($totalUsers / $limit);
        $roles = $this->userModel->getAllRoles();

        $inactiveCount = (int) $this->userModel->countSearchAndFilter('', null, 'inactive');
        $inactiveFilteredCount = (int) $this->userModel->countSearchAndFilter($keyword, $roleId, 'inactive');
        $deletedCount = (int) $this->userModel->countSearchAndFilter('', null, 'deleted');

        $this->render('admin/users', [
            'pageTitle'             => 'Manajemen Pengguna — Admin Campus Find',
            'users'                 => $users,
            'roles'                 => $roles,
            'keyword'               => $keyword,
            'roleId'                => $roleId,
            'status'                => $status,
            'page'                  => $page,
            'totalPages'            => $totalPages,
            'totalUsers'            => $totalUsers,
            'inactiveCount'         => $inactiveCount,
            'inactiveFilteredCount' => $inactiveFilteredCount,
            'deletedCount'          => $deletedCount,
            'user'                  => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * POST /admin/users/create
     * Tambah Pengguna Baru oleh Admin
     */
    public function createUser(array $params = []): void
    {
        CsrfMiddleware::validate();

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $roleId = (int) ($_POST['role_id'] ?? 3);
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email) || empty($password)) {
            $this->flash('danger', 'Nama, Email, dan Password wajib diisi.');
            $this->redirect('/admin/users');
            return;
        }

        try {
            $userId = $this->userModel->createByAdmin([
                'name'            => $name,
                'email'           => $email,
                'password'        => $password,
                'role_id'         => $roleId,
                'status'          => $status,
                'phone'           => $_POST['phone'] ?? null,
                'identity_number' => $_POST['identity_number'] ?? null,
                'department'      => $_POST['department'] ?? null,
            ]);

            AuditLogService::log('create_user', 'users', $userId, [
                'created_user_id' => $userId,
                'email'           => $email,
                'role_id'         => $roleId,
            ]);

            $this->flash('success', "Pengguna {$name} ({$email}) berhasil dibuat.");
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash('danger', 'Gagal membuat pengguna baru: ' . $e->getMessage());
        }

        $this->redirect('/admin/users');
    }

    /**
     * POST /admin/users/{id}/role
     * Ubah Role Pengguna
     */
    public function updateUserRole(array $params = []): void
    {
        CsrfMiddleware::validate();

        $targetUserId = (int) ($params['id'] ?? 0);
        $newRoleId = (int) ($_POST['role_id'] ?? 0);

        if ($targetUserId <= 0 || $newRoleId <= 0) {
            $this->flash('danger', 'Data role atau pengguna tidak valid.');
            $this->redirectBack('/admin/users');
            return;
        }

        $targetUser = $this->userModel->findWithRole($targetUserId);
        if (!$targetUser) {
            $this->flash('danger', 'Pengguna tidak ditemukan.');
            $this->redirectBack('/admin/users');
            return;
        }

        // Cegah perubahan role pada akun yang telah dihapus permanen (Section 4 Rule 5)
        if (!empty($targetUser['deleted_at'])) {
            $this->flash('danger', 'Akun yang telah dihapus permanen tidak dapat diubah role-nya.');
            $this->redirectBack('/admin/users');
            return;
        }

        $updated = $this->userModel->updateRole($targetUserId, $newRoleId);
        if ($updated) {
            AuditLogService::log('role_changed', 'users', $targetUserId, [
                'target_user_id' => $targetUserId,
                'old_role_id'    => $targetUser['role_id'],
                'old_role_name'  => $targetUser['role_name'],
                'new_role_id'    => $newRoleId,
            ]);

            $this->flash('success', "Role pengguna {$targetUser['name']} berhasil diperbarui.");
        } else {
            $this->flash('danger', 'Gagal mengubah role pengguna.');
        }

        $this->redirectBack('/admin/users');
    }



    

    /**
     * POST /admin/users/{id}/status
     * Ubah Status Pengguna (active, inactive, suspended)
     */
    public function updateUserStatus(array $params = []): void
    {
        CsrfMiddleware::validate();

        $targetUserId = (int) ($params['id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        $allowedStatuses = ['active', 'inactive', 'suspended'];
        if ($targetUserId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            $this->flash('danger', 'Status pengguna tidak valid.');
            $this->redirectBack('/admin/users');
            return;
        }

        $targetUser = $this->userModel->findWithRole($targetUserId);
        if (!$targetUser) {
            $this->flash('danger', 'Pengguna tidak ditemukan.');
            $this->redirectBack('/admin/users');
            return;
        }

        // Cegah perubahan status pada akun yang telah dihapus permanen (Section 4 Rule 5)
        if (!empty($targetUser['deleted_at'])) {
            $this->flash('danger', 'Akun yang telah dihapus permanen tidak dapat diaktifkan kembali atau diubah statusnya.');
            $this->redirectBack('/admin/users');
            return;
        }

        $updated = $this->userModel->updateStatus($targetUserId, $newStatus);
        if ($updated) {
            AuditLogService::log('user_status_changed', 'users', $targetUserId, [
                'target_user_id' => $targetUserId,
                'old_status'     => $targetUser['status'],
                'new_status'     => $newStatus,
            ]);

            $this->flash('success', "Status akun {$targetUser['name']} diubah menjadi '{$newStatus}'.");
        } else {
            $this->flash('danger', 'Gagal memperbarui status akun pengguna.');
        }

        $this->redirectBack('/admin/users');
    }



    

    /**
     * POST /admin/users/{id}/temp-password
     * Admin menyetel kata sandi sementara acak untuk satu akun pengguna (Prompt 20B).
     * Ditampilkan SEKALI kepada Admin (flash session), pengguna wajib menggantinya saat login pertama.
     */
    public function setTempPassword(array $params = []): void
    {
        CsrfMiddleware::validate();

        $targetUserId = (int) ($params['id'] ?? 0);
        if ($targetUserId <= 0) {
            $this->flash('danger', 'Pengguna tidak valid.');
            $this->redirectBack('/admin/users');
            return;
        }

        $targetUser = $this->userModel->findWithRole($targetUserId);
        if (!$targetUser) {
            $this->flash('danger', 'Pengguna tidak ditemukan.');
            $this->redirectBack('/admin/users');
            return;
        }

        // Cegah penerbitan kata sandi sementara pada akun yang telah dihapus permanen (Section 4 Rule 5)
        if (!empty($targetUser['deleted_at'])) {
            $this->flash('danger', 'Akun yang telah dihapus permanen tidak dapat menerima kata sandi sementara.');
            $this->redirectBack('/admin/users');
            return;
        }

        // Generate kata sandi sementara acak aman (Prompt 34B: min. 12 karakter)
        $plainTempPassword = \App\Services\OtpService::generateTemporaryPassword(14);
        $hash = password_hash($plainTempPassword, PASSWORD_BCRYPT);

        $pdo = \Core\Database::getInstance()->getPdo();
        $stmt = $pdo->prepare("
            UPDATE users SET 
                password_hash = ?,
                must_change_password = 1,
                temp_password_expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
                is_active = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$hash, $targetUserId]);

        // Audit log kejadian (TANPA menyimpan atau mencatat kata sandi)
        AuditLogService::log('ADMIN_RESET_PASSWORD', $targetUserId, 'users', $targetUserId, [
            'target_user_id' => $targetUserId,
            'target_email'   => $targetUser['email'],
        ]);

        // Simpan ke flash session agar ditampilkan SEKALI kepada Admin
        $_SESSION['temp_password_reveal'] = [
            'user_name'     => $targetUser['name'],
            'email'         => $targetUser['email'],
            'temp_password' => $plainTempPassword,
        ];

        $this->flash('success', "Kata sandi sementara untuk pengguna {$targetUser['name']} ({$targetUser['email']}) berhasil dibuat. Harap catat atau salin segera, kata sandi ini hanya ditampilkan sekali.");
        $this->redirectBack('/admin/users');
    }



    

    /**
     * POST /admin/users/{id}/resend-activation
     * Admin mengirim ulang kode OTP aktivasi akun (Prompt 20B)
     */
    public function resendActivation(array $params = []): void
    {
        CsrfMiddleware::validate();

        $targetUserId = (int) ($params['id'] ?? 0);
        if ($targetUserId <= 0) {
            $this->flash('danger', 'Pengguna tidak valid.');
            $this->redirectBack('/admin/users');
            return;
        }

        $targetUser = $this->userModel->findWithRole($targetUserId);
        if (!$targetUser) {
            $this->flash('danger', 'Pengguna tidak ditemukan.');
            $this->redirectBack('/admin/users');
            return;
        }

        // Cegah pengiriman kode aktivasi pada akun yang telah dihapus permanen (Section 4 Rule 5)
        if (!empty($targetUser['deleted_at'])) {
            $this->flash('danger', 'Akun yang telah dihapus permanen tidak dapat menerima kode aktivasi.');
            $this->redirectBack('/admin/users');
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $res = \App\Services\OtpService::generate($targetUserId, 'activation', $ip);

        AuditLogService::log('ADMIN_RESEND_ACTIVATION', $targetUserId, 'users', $targetUserId, [
            'target_user_id' => $targetUserId,
            'target_email'   => $targetUser['email'],
        ]);

        if (!empty($res['cooldown'])) {
            $this->flash('warning', $res['message']);
        } elseif ($res['success']) {
            $this->flash('success', "Kode OTP aktivasi akun berhasil dikirimkan ke email {$targetUser['email']}.");
        } else {
            $this->flash('danger', $res['message'] ?? 'Gagal mengirimkan kode OTP aktivasi.');
        }

        $this->redirectBack('/admin/users');
    }



    

    /**
     * POST /admin/users/bulk-action
     * Aksi massal akun pengguna (Prompt 34B):
     * a. Kirim ulang aktivasi OTP massal (lewat antrean email)
     * b. Setel password sementara massal (unik per akun, hash, berlaku 7 hari, konfirmasi password Admin)
     */
    public function bulkUserAction(array $params = []): void
    {
        AuthMiddleware::requireRole('admin');
        CsrfMiddleware::validate();

        $action = trim((string)($_POST['action'] ?? ''));
        $currentAdmin = AuthMiddleware::user();
        $adminId = (int)($currentAdmin['id'] ?? 0);

        if (!in_array($action, ['resend_activation', 'set_temp_password'], true)) {
            $this->flash('danger', 'Aksi massal tidak valid.');
            $this->redirectBack('/admin/users');
            return;
        }

        $pdo = \Core\Database::getInstance()->getPdo();
        $selectAllInactive = !empty($_POST['select_all_inactive']);
        $filterKeyword     = trim((string)($_POST['filter_keyword'] ?? ''));
        $filterRoleId      = !empty($_POST['filter_role_id']) ? (int)$_POST['filter_role_id'] : null;

        $returnUrl = trim((string)($_POST['return_url'] ?? ''));
        if (empty($returnUrl) || !str_starts_with($returnUrl, '/admin/users')) {
            $ref = $_SERVER['HTTP_REFERER'] ?? '';
            $refPath = parse_url($ref, PHP_URL_PATH) ?? '';
            if (str_starts_with($refPath, '/admin/users')) {
                $returnUrl = $ref;
            } else {
                $returnUrl = '/admin/users';
            }
        }

        if ($selectAllInactive) {
            // Ambil seluruh hasil filter lintas halaman (is_active = 0, maks 100 per proses)
            $where = ["u.is_active = 0"];
            $params = [];
            if (!empty($filterKeyword)) {
                $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.identity_number LIKE ? OR u.phone LIKE ?)";
                $kw = '%' . $filterKeyword . '%';
                $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
            }
            if ($filterRoleId !== null && $filterRoleId > 0) {
                $where[] = "u.role_id = ?";
                $params[] = $filterRoleId;
            }
            $whereSql = implode(' AND ', $where);
            $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.identity_number FROM users u WHERE {$whereSql} ORDER BY u.id ASC LIMIT 100");
            $stmt->execute($params);
            $targetUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $rawIds = (array)($_POST['user_ids'] ?? []);
            $cleanIds = array_slice(array_unique(array_filter(array_map('intval', $rawIds))), 0, 100);

            if (empty($cleanIds)) {
                $this->flash('warning', 'Silakan pilih minimal satu akun yang belum aktif.');
                $this->redirect($returnUrl);
                return;
            }

            $inPlaceholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $stmt = $pdo->prepare("SELECT id, name, email, identity_number FROM users WHERE id IN ($inPlaceholders) AND is_active = 0 ORDER BY id ASC");
            $stmt->execute($cleanIds);
            $targetUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        if (empty($targetUsers)) {
            $this->flash('warning', 'Tidak ada akun dengan status belum aktif di antara akun yang dipilih (maksimal 100 akun per proses).');
            $this->redirect($returnUrl);
            return;
        }

        // AKSI A: KIRIM ULANG AKTIVASI OTP MASSAL
        if ($action === 'resend_activation') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $queuedCount = 0;
            $skippedCount = 0;

            foreach ($targetUsers as $user) {
                $uid = (int) $user['id'];
                $res = \App\Services\OtpService::generate($uid, 'activation', $ip);
                if (!empty($res['success'])) {
                    $queuedCount++;
                } else {
                    $skippedCount++;
                }
            }

            // Audit log kejadian massal (tanpa password / data rahasia)
            AuditLogService::log('BULK_RESEND_ACTIVATION', $adminId, 'users', null, [
                'total_targeted' => count($targetUsers),
                'queued_count'   => $queuedCount,
                'skipped_count'  => $skippedCount,
            ]);

            $msg = "Aksi massal selesai: {$queuedCount} email kode OTP aktivasi berhasil diantrekan";
            if ($skippedCount > 0) {
                $msg .= ", {$skippedCount} akun dilewati (cooldown 60 detik atau batasan allowlist/domain).";
                $this->flash('warning', $msg);
            } else {
                $msg .= " ke seluruh akun terpilih.";
                $this->flash('success', $msg);
            }

            $this->redirect($returnUrl);
            return;
        }

        // AKSI B: SETEL PASSWORD SEMENTARA MASSAL
        if ($action === 'set_temp_password') {
            // Wajib konfirmasi dengan kata sandi Admin sendiri
            $adminPasswordInput = (string)($_POST['admin_password'] ?? '');
            if (empty($adminPasswordInput)) {
                $this->flash('danger', 'Konfirmasi kata sandi Administrator wajib diisi untuk melakukan setel kata sandi sementara massal.');
                $this->redirect($returnUrl);
                return;
            }

            $stmtAdmin = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
            $stmtAdmin->execute([$adminId]);
            $adminHash = (string) $stmtAdmin->fetchColumn();

            if (!password_verify($adminPasswordInput, $adminHash)) {
                $this->flash('danger', 'Konfirmasi kata sandi Administrator salah. Aksi massal dibatalkan demi keamanan.');
                $this->redirect($returnUrl);
                return;
            }

            $credentialsList = [];
            $stmtUpdate = $pdo->prepare("
                UPDATE users SET 
                    password_hash = ?,
                    must_change_password = 1,
                    temp_password_expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
                    is_active = 1,
                    updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($targetUsers as $user) {
                $uid = (int) $user['id'];
                // TIDAK ADA SATU PASSWORD BERSAMA: Password acak UNIK per akun (min. 12 karakter)
                $plainTemp = \App\Services\OtpService::generateTemporaryPassword(14);
                $hash = password_hash($plainTemp, PASSWORD_BCRYPT);

                $stmtUpdate->execute([$hash, $uid]);

                $credentialsList[] = [
                    'id'            => $uid,
                    'nim'           => !empty($user['identity_number']) ? $user['identity_number'] : '-',
                    'name'          => $user['name'],
                    'email'         => $user['email'],
                    'temp_password' => $plainTemp,
                ];
            }

            // Simpan HANYA ke flash session untuk satu kali tampilan & unduh CSV
            // TIDAK disimpan ke database atau file server
            $_SESSION['bulk_temp_passwords_reveal'] = $credentialsList;

            // Audit log: HANYA mencatat siapa, kapan, dan jumlah akun (TIDAK ADA PASSWORD)
            AuditLogService::log('BULK_SET_TEMP_PASSWORD', $adminId, 'users', null, [
                'total_accounts' => count($credentialsList),
            ]);

            $this->flash('success', "Berhasil menyetel kata sandi sementara acak unik untuk " . count($credentialsList) . " akun. Harap unduh berkas CSV atau salin daftar akun sekarang. Data kata sandi ini hanya ditampilkan sekali dan tidak disimpan di server.");
            $this->redirect($returnUrl);
            return;
        }
    }

    /**
     * GET /admin/users/download-temp-passwords-csv
     * Mengunduh SEKALI daftar NIM + kata sandi sementara dalam format CSV (Prompt 34B)
     */
    public function downloadBulkTempPasswordsCsv(array $params = []): void
    {
        AuthMiddleware::requireRole('admin');

        $credentialsList = $_SESSION['bulk_temp_passwords_reveal'] ?? null;
        if (empty($credentialsList) || !is_array($credentialsList)) {
            $this->flash('warning', 'Daftar kata sandi sementara tidak ditemukan atau sudah pernah diunduh sebelumnya.');
            $this->redirectBack('/admin/users');
            return;
        }

        // Bersihkan session agar hanya dapat diunduh sekali
        unset($_SESSION['bulk_temp_passwords_reveal']);

        $filename = 'password_sementara_civitas_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // Tambahkan BOM untuk kompatibilitas Microsoft Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['NIM', 'Nama Mahasiswa/Civitas', 'Alamat Email', 'Kata Sandi Sementara', 'Masa Berlaku', 'Instruksi Keamanan']);

        foreach ($credentialsList as $item) {
            fputcsv($output, [
                $item['nim'],
                $item['name'],
                $item['email'],
                $item['temp_password'],
                '7 Hari sejak diterbitkan',
                'Wajib ganti kata sandi pribadi saat pertama kali masuk ke sistem. RAHASIA: Bagikan secara pribadi kepada pemilik akun.',
            ]);
        }

        fclose($output);
        if (php_sapi_name() !== 'cli' && !defined('CAMPUS_FIND_TESTING')) {
            exit;
        }
    }

    /**
     * POST /admin/users/{id}/edit
     * Admin mengubah data pengguna (nama, NIM, email, No WA, prodi, angkatan)
     */
    public function updateUserData(array $params = []): void
    {
        AuthMiddleware::requireRole('admin');
        CsrfMiddleware::validate();

        $targetUserId = (int) ($params['id'] ?? 0);
        if ($targetUserId <= 0) {
            $this->flash('danger', 'ID pengguna tidak valid.');
            $this->redirectBack('/admin/users');
            return;
        }

        $adminUser = AuthMiddleware::user();
        $adminUserId = (int) ($adminUser['id'] ?? 0);

        try {
            $result = \App\Services\AccountManagementService::adminUpdateAccount($targetUserId, $_POST, $adminUserId);
            $this->flash('success', $result['message']);
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[AdminController::updateUserData] Galat: ' . $e->getMessage());
            $this->flash('danger', 'Terjadi kesalahan sistem saat memperbarui data pengguna: ' . $e->getMessage());
        }

        $this->redirectBack('/admin/users');
    }

    /**
     * POST /admin/users/{id}/delete atau /admin/users/{id}/permanent-delete
     * Hapus Permanen Akun Pengguna oleh Administrator (BUG FIX 03)
     */
    public function deleteUserPermanently(array $params = []): void
    {
        AuthMiddleware::requireRole('admin');
        CsrfMiddleware::validate();

        $targetUserId = (int) ($params['id'] ?? 0);
        if ($targetUserId <= 0) {
            $this->flash('danger', 'ID pengguna tidak valid.');
            $this->redirectBack('/admin/users');
            return;
        }

        $adminUser = AuthMiddleware::user();
        $adminUserId = (int) ($adminUser['id'] ?? 0);
        $confirmation = trim((string) ($_POST['confirmation'] ?? $_POST['confirm_phrase'] ?? ''));

        try {
            $result = \App\Services\AccountManagementService::deleteAccountPermanently(
                $targetUserId,
                $adminUserId,
                $confirmation
            );
            $this->flash('success', $result['message']);
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[AdminController::deleteUserPermanently] Galat: ' . $e->getMessage());
            $this->flash('danger', 'Terjadi kesalahan sistem saat memproses penghapusan akun: ' . $e->getMessage());
        }

        $this->redirectBack('/admin/users');
    }



    

    /**
     * POST /admin/users/{id}/mark-verified
     * Menandai email pengguna terverifikasi secara manual (HANYA jika APP_ENV=local)
     */
    public function markEmailVerifiedLocal(array $params = []): void
    {
        AuthMiddleware::requireRole('admin');
        CsrfMiddleware::validate();

        $appEnv = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development'));
        if ($appEnv !== 'local') {
            http_response_code(403);
            $this->render('errors/403', [
                'message' => 'Tindakan ini hanya diizinkan pada lingkungan lokal (APP_ENV=local).',
            ], 'app_shell');
            return;
        }

        $userId = (int) ($params['id'] ?? 0);
        $targetUser = $this->userModel->findWithRole($userId);
        if (!$targetUser) {
            $this->flash('danger', 'Pengguna tidak ditemukan.');
            $this->redirectBack('/admin/users');
            return;
        }

        $pdo = Database::getInstance()->getPdo();
        $pdo->prepare("UPDATE users SET email_verified_at = NOW(), email_verified_by_otp = 0, updated_at = NOW() WHERE id = ?")
            ->execute([$userId]);

        AuditLogService::log('MANUAL_MARK_EMAIL_VERIFIED_LOCAL', (int)AuthMiddleware::user()['id'], 'users', $userId, [
            'target_user_id' => $userId,
            'email'          => \App\Services\OtpService::maskEmail($targetUser['email']),
            'env'            => 'local',
        ]);

        $this->flash('success', "Email pengguna {$targetUser['name']} berhasil ditandai terverifikasi (Dev Local).");
        $this->redirectBack('/admin/users');
    }



    

    /**
     * GET /admin/categories
     * Kelola Master Data Kategori Barang
     */
    public function categories(array $params = []): void
    {
        $categories = $this->categoryModel->getAllWithCounts();

        $this->render('admin/categories', [
            'pageTitle'  => 'Kelola Master Kategori — Admin Campus Find',
            'categories' => $categories,
            'user'       => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * POST /admin/categories/create
     */
    public function createCategory(array $params = []): void
    {
        CsrfMiddleware::validate();

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            $this->flash('danger', 'Nama kategori wajib diisi.');
            $this->redirect('/admin/categories');
            return;
        }

        // Duplicate prevention: check both active and inactive categories
        $existing = $this->categoryModel->findByNameNormalized($name);
        if ($existing) {
            if (!empty($existing['is_active'])) {
                $this->flash('danger', "Kategori '{$name}' sudah ada.");
            } else {
                $this->flash('warning', "Kategori '{$name}' sudah ada dalam sistem tetapi berstatus nonaktif. Silakan aktifkan kembali kategori tersebut.");
            }
            $this->redirect('/admin/categories');
            return;
        }

        $slug = !empty($_POST['slug']) ? trim($_POST['slug']) : (!empty($_POST['code']) ? trim($_POST['code']) : null);
        $catId = $this->categoryModel->create([
            'name'        => $name,
            'slug'        => $slug,
            'code'        => $slug,
            'icon'        => !empty($_POST['icon']) ? trim($_POST['icon']) : 'bi-tag',
            'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
        ]);

        AuditLogService::log('create_category', 'categories', $catId, ['name' => $name]);
        $this->flash('success', "Kategori '{$name}' berhasil ditambahkan.");
        $this->redirect('/admin/categories');
    }

    /**
     * POST /admin/categories/{id}/update
     */
    public function updateCategory(array $params = []): void
    {
        CsrfMiddleware::validate();

        $catId = (int) ($params['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($catId <= 0 || empty($name)) {
            $this->flash('danger', 'Data kategori tidak valid.');
            $this->redirect('/admin/categories');
            return;
        }

        // Duplicate prevention: check if another category has the same normalized name
        $existing = $this->categoryModel->findByNameNormalized($name, $catId);
        if ($existing) {
            $this->flash('danger', "Kategori lain dengan nama '{$name}' sudah ada.");
            $this->redirect('/admin/categories');
            return;
        }

        $slug = !empty($_POST['slug']) ? trim($_POST['slug']) : (!empty($_POST['code']) ? trim($_POST['code']) : null);
        $this->categoryModel->updateCategory($catId, [
            'name'        => $name,
            'slug'        => $slug,
            'code'        => $slug,
            'icon'        => !empty($_POST['icon']) ? trim($_POST['icon']) : 'bi-tag',
            'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
        ]);

        AuditLogService::log('update_category', 'categories', $catId, ['name' => $name]);
        $this->flash('success', "Kategori '{$name}' berhasil diperbarui.");
        $this->redirect('/admin/categories');
    }

    /**
     * POST /admin/categories/{id}/toggle
     */
    public function toggleCategory(array $params = []): void
    {
        CsrfMiddleware::validate();

        $catId = (int) ($params['id'] ?? 0);
        if ($catId > 0) {
            $this->categoryModel->toggleStatus($catId);
            AuditLogService::log('toggle_category', 'categories', $catId);
            $this->flash('success', 'Status kategori berhasil diperbarui.');
        }
        $this->redirect('/admin/categories');
    }

    /**
     * GET /admin/locations
     * Kelola Master Data Lokasi Kampus
     */
    public function locations(array $params = []): void
    {
        $locations = $this->locationModel->getAllWithCounts();

        $this->render('admin/locations', [
            'pageTitle' => 'Kelola Master Lokasi Kampus — Admin Campus Find',
            'locations' => $locations,
            'user'      => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * POST /admin/locations/create
     */
    public function createLocation(array $params = []): void
    {
        CsrfMiddleware::validate();

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            $this->flash('danger', 'Nama lokasi kampus wajib diisi.');
            $this->redirect('/admin/locations');
            return;
        }

        $locId = $this->locationModel->create([
            'name'        => $name,
            'code'        => $_POST['code'] ?? null,
            'campus_zone' => $_POST['campus_zone'] ?? 'Zona Utama',
            'description' => $_POST['description'] ?? null,
        ]);

        AuditLogService::log('create_location', 'locations', $locId, ['name' => $name]);
        $this->flash('success', "Lokasi kampus '{$name}' berhasil ditambahkan.");
        $this->redirect('/admin/locations');
    }

    /**
     * POST /admin/locations/{id}/update
     */
    public function updateLocation(array $params = []): void
    {
        CsrfMiddleware::validate();

        $locId = (int) ($params['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($locId <= 0 || empty($name)) {
            $this->flash('danger', 'Data lokasi tidak valid.');
            $this->redirect('/admin/locations');
            return;
        }

        $this->locationModel->updateLocation($locId, [
            'name'        => $name,
            'code'        => $_POST['code'] ?? null,
            'campus_zone' => $_POST['campus_zone'] ?? 'Zona Utama',
            'description' => $_POST['description'] ?? null,
        ]);

        AuditLogService::log('update_location', 'locations', $locId, ['name' => $name]);
        $this->flash('success', "Lokasi kampus '{$name}' berhasil diperbarui.");
        $this->redirect('/admin/locations');
    }

    /**
     * POST /admin/locations/{id}/toggle
     */
    public function toggleLocation(array $params = []): void
    {
        CsrfMiddleware::validate();

        $locId = (int) ($params['id'] ?? 0);
        if ($locId > 0) {
            $this->locationModel->toggleStatus($locId);
            AuditLogService::log('toggle_location', 'locations', $locId);
            $this->flash('success', 'Status lokasi kampus berhasil diperbarui.');
        }
        $this->redirect('/admin/locations');
    }

    /**
     * GET /admin/storage-locations
     * Kelola Master Data Gudang / Rak Penyimpanan Physical Center
     */
    public function storageLocations(array $params = []): void
    {
        $storageLocations = $this->storageModel->getAllWithCapacity();

        $this->render('admin/storage_locations', [
            'pageTitle'        => 'Kelola Gudang & Rak Penyimpanan — Admin',
            'storageLocations' => $storageLocations,
            'user'             => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * POST /admin/storage-locations/create
     */
    public function createStorageLocation(array $params = []): void
    {
        CsrfMiddleware::validate();

        try {
            $id = $this->storageModel->create([
                'code'        => $_POST['code'] ?? '',
                'name'        => $_POST['name'] ?? '',
                'zone'        => $_POST['zone'] ?? 'Zona Umum',
                'capacity'    => (int) ($_POST['capacity'] ?? 50),
                'description' => $_POST['description'] ?? null,
            ]);

            AuditLogService::log('create_storage_location', 'storage_locations', $id, ['code' => $_POST['code'] ?? '']);
            $this->flash('success', 'Lokasi penyimpanan gudang berhasil dibuat.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash('danger', 'Gagal membuat lokasi penyimpanan: ' . $e->getMessage());
        }

        $this->redirect('/admin/storage-locations');
    }

    /**
     * POST /admin/storage-locations/{id}/update
     */
    public function updateStorageLocation(array $params = []): void
    {
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        try {
            $this->storageModel->updateLocation($id, [
                'code'        => $_POST['code'] ?? '',
                'name'        => $_POST['name'] ?? '',
                'zone'        => $_POST['zone'] ?? 'Zona Umum',
                'capacity'    => (int) ($_POST['capacity'] ?? 50),
                'description' => $_POST['description'] ?? null,
            ]);

            AuditLogService::log('update_storage_location', 'storage_locations', $id, ['code' => $_POST['code'] ?? '']);
            $this->flash('success', 'Lokasi penyimpanan berhasil diperbarui.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash('danger', 'Gagal memperbarui lokasi penyimpanan: ' . $e->getMessage());
        }

        $this->redirect('/admin/storage-locations');
    }

    /**
     * POST /admin/storage-locations/{id}/toggle
     */
    public function toggleStorageLocation(array $params = []): void
    {
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        if ($id > 0) {
            $this->storageModel->toggleStatus($id);
            AuditLogService::log('toggle_storage_location', 'storage_locations', $id);
            $this->flash('success', 'Status lokasi penyimpanan berhasil diperbarui.');
        }
        $this->redirect('/admin/storage-locations');
    }

    /**
     * GET /admin/audit-logs
     * Jejak Audit Aktivitas Sistem
     */
    public function auditLogs(array $params = []): void
    {
        $keyword = trim($_GET['keyword'] ?? '');
        $action = trim($_GET['action'] ?? '');
        $entityType = trim($_GET['entity_type'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $pdo = Database::getInstance()->getPdo();

        $where = ["1=1"];
        $queryParams = [];

        if (!empty($keyword)) {
            $where[] = "(u.name LIKE ? OR u.email LIKE ? OR a.action LIKE ? OR a.entity_type LIKE ? OR a.ip_address LIKE ?)";
            $kw = '%' . $keyword . '%';
            $queryParams = array_merge($queryParams, [$kw, $kw, $kw, $kw, $kw]);
        }

        if (!empty($action)) {
            $where[] = "a.action = ?";
            $queryParams[] = $action;
        }

        if (!empty($entityType)) {
            $where[] = "a.entity_type = ?";
            $queryParams[] = $entityType;
        }

        $whereClause = implode(' AND ', $where);

        // Hitung total
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($queryParams);
        $totalLogs = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($totalLogs / $limit);

        // Query data
        $stmt = $pdo->prepare("
            SELECT a.*, u.name AS user_name, u.email AS user_email, r.label AS role_label
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE {$whereClause}
            ORDER BY a.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($queryParams);
        $logs = $stmt->fetchAll();

        // Distinct actions untuk opsi filter dropdown
        $actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(\PDO::FETCH_COLUMN);

        $this->render('admin/audit_logs', [
            'pageTitle'  => 'Audit Trail Sistem — Admin Campus Find',
            'logs'       => $logs,
            'actions'    => $actions,
            'keyword'    => $keyword,
            'action'     => $action,
            'entityType' => $entityType,
            'page'       => $page,
            'totalPages' => $totalPages,
            'totalLogs'  => $totalLogs,
            'user'       => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * GET /admin/chat-history
     * Pemantauan Riwayat Percakapan Chatbot AI C-Find (Prompt 36C)
     */
    public function cfindHistory(array $params = []): void
    {
        AuthMiddleware::requireRole('admin');

        $role = trim($_GET['role'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');
        $userSearch = trim($_GET['user'] ?? '');
        $keyword = trim($_GET['keyword'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;

        $filters = [
            'role'      => $role,
            'status'    => $status,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'user'      => $userSearch,
            'keyword'   => $keyword,
        ];

        $historyService = new CFindHistoryService();
        $historyData = $historyService->getPaginatedHistory($filters, $page, $limit);

        // Buat string query untuk paginasi yang mempertahankan parameter filter
        $queryParams = $_GET;
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);

        $currentUser = AuthMiddleware::user();

        $this->render('admin/chat_history', [
            'pageTitle'    => 'Riwayat Chat AI — Lost & Found Center',
            'user'         => $currentUser,
            'historyData'  => $historyData,
            'filters'      => $filters,
            'queryString'  => $queryString,
        ], 'app_shell');
    }

    /**
     * GET /admin/chat-history/{id}
     * Detail Percakapan Chatbot AI C-Find (Hanya-Baca) (Prompt 36C)
     */
    public function cfindHistoryDetail(array $params = []): void
    {
        AuthMiddleware::requireRole('admin');
        $currentUser = AuthMiddleware::user();
        $adminId = (int)($currentUser['id'] ?? 0);

        $id = (int)($params['id'] ?? 0);
        $historyService = new CFindHistoryService();
        $detail = $historyService->getDetailById($id);

        if (!$detail) {
            if ($this->isAjax() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                $this->json(['success' => false, 'error' => 'Percakapan tidak ditemukan.'], 404);
            }
            $this->flash('danger', 'Percakapan tidak ditemukan.');
            $this->redirect('/admin/chat-history');
        }

        // Catat Audit Log: pembukaan detail dicatat di audit log (admin, ID percakapan, waktu) TANPA isi pesan
        AuditLogService::log(
            'VIEW_CFIND_CHAT_DETAIL',
            $adminId,
            'cfind_chat_history',
            $id,
            ['conversation_id' => $id]
        );

        if ($this->isAjax() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            $this->json([
                'success' => true,
                'data'    => $detail,
            ]);
        }

        $this->render('admin/chat_history_detail', [
            'pageTitle' => "Detail Chat AI #{$id} — Lost & Found Center",
            'user'      => $currentUser,
            'detail'    => $detail,
        ], 'app_shell');
    }

    /**
     * GET /admin/settings
     * Halaman Pengaturan Sistem (AI & Notifikasi)
     */
    public function settings(array $params = []): void
    {
        $settings = SettingService::getAll();
        $cfindService = new \App\Services\CFindService();
        $cfindBudgetInfo = $cfindService->getDailyBudgetInfo();

        // Unified AI Configuration Center (Task 14 & Task 2B)
        $defaultAssignment   = \App\Services\AiProviderManager::getDefaultAssignment();
        $rawDefaultModels    = \App\Services\AiProviderManager::getCompatibleModels($defaultAssignment['provider'], 'cfind');
        $defaultModels       = \App\Services\AiProviderManager::filterModelsForChatAssignment($rawDefaultModels, $defaultAssignment['model']);
        $providerConnections = \App\Services\AiProviderManager::getAllProviderConnections();
        $registeredProviders = \App\Services\AiProviderManager::getRegisteredProviders();
        $assignmentCfind     = \App\Services\AiProviderManager::getFeatureAssignment('cfind');
        $assignmentMatching  = \App\Services\AiProviderManager::getFeatureAssignment('matching');
        $rawCfindModels      = \App\Services\AiProviderManager::getCompatibleModels($assignmentCfind['provider'], 'cfind');
        $cfindModels         = \App\Services\AiProviderManager::filterModelsForChatAssignment($rawCfindModels, $assignmentCfind['model']);
        $rawMatchingModels   = \App\Services\AiProviderManager::getCompatibleModels($assignmentMatching['provider'], 'matching');
        $matchingModels      = \App\Services\AiProviderManager::filterModelsForChatAssignment($rawMatchingModels, $assignmentMatching['model']);

        $this->render('admin/settings', [
            'pageTitle'           => 'Pengaturan Sistem & Konfigurasi AI — Admin Campus Find',
            'settings'            => $settings,
            'user'                => AuthMiddleware::user(),
            'cfindBudgetInfo'     => $cfindBudgetInfo,
            'defaultAssignment'   => $defaultAssignment,
            'defaultModels'       => $defaultModels,
            'providerConnections' => $providerConnections,
            'registeredProviders' => $registeredProviders,
            'assignmentCfind'     => $assignmentCfind,
            'assignmentMatching'  => $assignmentMatching,
            'cfindModels'         => $cfindModels,
            'matchingModels'      => $matchingModels,
        ], 'app_shell');
    }

    /**
     * POST /admin/settings/reset-cfind-budget
     * Reset penghitung penggunaan token harian C-Find (Prompt 29D)
     */
    public function resetCfindBudget(array $params = []): void
    {
        CsrfMiddleware::validate();

        $cfindService = new \App\Services\CFindService();
        $cfindService->resetDailyBudget();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => 'Penghitung penggunaan token harian C-Find berhasil direset ke 0.',
            'budget'  => $cfindService->getDailyBudgetInfo(),
        ]);
        exit;
    }


    /**
     * POST /admin/settings
     * Simpan Perubahan Pengaturan Sistem
     */
    public function updateSettings(array $params = []): void
    {
        CsrfMiddleware::validate();

        $currentUser = AuthMiddleware::user();
        $userId = $currentUser['id'] ?? null;

        $result = SettingService::updateSettings($_POST, $userId);

        if ($result['success']) {
            $count = count($result['updated_keys']);
            $this->setFlash('success', "Pengaturan sistem berhasil diperbarui ({$count} kunci tersimpan).");
        } else {
            $errText = implode(' ', $result['errors']);
            $this->setFlash('error', "Terjadi kesalahan saat menyimpan pengaturan: {$errText}");
        }

        $this->redirect('/admin/settings');
    }

    /**
     * GET/POST /admin/ai/models
     * Dapatkan model kompatibel dinamis untuk provider & fitur tertentu
     */
    public function getAiModels(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');

            $provider = trim((string)($_GET['provider_id'] ?? $_GET['provider'] ?? $_POST['provider_id'] ?? $_POST['provider'] ?? 'nvidia_nim'));
            $feature = trim((string)($_GET['feature'] ?? $_POST['feature'] ?? 'cfind'));
            $forceRefresh = !empty($_GET['refresh']) || !empty($_POST['refresh']);

            $rawModels = \App\Services\AiProviderManager::getCompatibleModels($provider, $feature, $forceRefresh);
            $savedModel = \App\Services\AiProviderManager::getSavedModelForFeatureOrProvider($provider, $feature);
            $models = \App\Services\AiProviderManager::filterModelsForChatAssignment($rawModels, $savedModel);

            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json([
                'success'  => true,
                'provider' => $provider,
                'feature'  => $feature,
                'models'   => $models,
                'count'    => count($models),
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:getAiModels] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Gagal memuat model provider.',
                'models'  => [],
                'count'   => 0,
            ], 500);
        }
    }

    /**
     * POST /admin/ai/provider/save
     * Simpan kredensial & URL koneksi provider AI secara aman
     */
    public function saveAiProviderConnection(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $provider = trim((string)($_POST['provider_id'] ?? $_POST['provider'] ?? ''));
            $apiKey = isset($_POST['api_key']) ? (string)$_POST['api_key'] : null;
            $baseUrl = isset($_POST['base_url']) ? (string)$_POST['base_url'] : null;
            $name = isset($_POST['name']) ? trim((string)$_POST['name']) : null;

            $currentUser = AuthMiddleware::user();
            $userId = $currentUser['id'] ?? null;

            if ($name !== null && $name !== '' && \App\Services\AiProviderManager::isCustomProvider($provider)) {
                \App\Services\AiProviderManager::updateCustomProviderMetadata($provider, $name, null, $userId);
            }

            $result = \App\Services\AiProviderManager::saveProviderConnection($provider, $apiKey, $baseUrl, $userId);

            $statusCode = !empty($result['success']) ? 200 : 422;
            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result, $statusCode);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:saveAiProviderConnection] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Gagal menyimpan kredensial provider: Terjadi kesalahan internal.',
            ], 500);
        }
    }

    /**
     * POST /admin/ai/provider/custom/add
     * Tambah custom provider OpenAI-compatible
     */
    public function addCustomAiProvider(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $name = trim((string)($_POST['name'] ?? ''));
            $baseUrl = trim((string)($_POST['base_url'] ?? ''));
            $apiKey = isset($_POST['api_key']) ? (string)$_POST['api_key'] : null;

            $currentUser = AuthMiddleware::user();
            $userId = $currentUser['id'] ?? null;

            $result = \App\Services\AiProviderManager::addCustomProvider($name, $baseUrl, $apiKey, $userId);

            $statusCode = !empty($result['success']) ? 200 : 422;
            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result, $statusCode);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:addCustomAiProvider] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Gagal menambahkan provider custom: Terjadi kesalahan internal.',
            ], 500);
        }
    }

    /**
     * POST /admin/ai/provider/custom/delete
     * Hapus custom provider OpenAI-compatible
     */
    public function deleteCustomAiProvider(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $providerId = trim((string)($_POST['provider_id'] ?? ''));

            $currentUser = AuthMiddleware::user();
            $userId = $currentUser['id'] ?? null;

            $result = \App\Services\AiProviderManager::deleteCustomProvider($providerId, $userId);

            $statusCode = !empty($result['success']) ? 200 : 422;
            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result, $statusCode);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:deleteCustomAiProvider] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Gagal menghapus provider custom: Terjadi kesalahan internal.',
            ], 500);
        }
    }

    /**
     * POST /admin/ai/provider/delete-key
     * Hapus key tersimpan di database untuk provider tertentu
     */
    public function deleteAiProviderKey(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $provider = trim((string)($_POST['provider_id'] ?? $_POST['provider'] ?? ''));

            $currentUser = AuthMiddleware::user();
            $userId = $currentUser['id'] ?? null;

            $result = \App\Services\AiProviderManager::deleteProviderKey($provider, $userId);

            $statusCode = !empty($result['success']) ? 200 : 422;
            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result, $statusCode);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:deleteAiProviderKey] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Gagal menghapus kunci provider: Terjadi kesalahan internal.',
            ], 500);
        }
    }

    /**
     * POST /admin/ai/provider/test
     * Tes koneksi nyata ke provider dengan inferensi kecil
     */
    public function testAiProviderConnection(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $provider = trim((string)($_POST['provider_id'] ?? $_POST['provider'] ?? ''));
            $model = !empty($_POST['model']) ? trim((string)$_POST['model']) : null;
            $apiKey = !empty($_POST['api_key']) ? trim((string)$_POST['api_key']) : null;

            $result = \App\Services\AiProviderManager::testProviderConnection($provider, $model, $apiKey);

            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:testAiProviderConnection] ' . $e->getMessage());
            $this->json([
                'success'     => false,
                'message'     => 'Uji koneksi gagal: Terjadi kesalahan internal.',
                'http_status' => 500,
                'duration_sec'=> 0,
            ], 500);
        }
    }

    /**
     * POST /admin/ai/feature/save
     * Simpan penugasan provider & model untuk fitur AI (C-Find atau Matching)
     */
    public function saveAiFeatureAssignment(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $feature = trim((string)($_POST['feature'] ?? ''));
            $provider = trim((string)($_POST['provider_id'] ?? $_POST['provider'] ?? ''));
            $model = trim((string)($_POST['model_id'] ?? $_POST['model'] ?? ''));

            $currentUser = AuthMiddleware::user();
            $userId = $currentUser['id'] ?? null;

            $result = \App\Services\AiProviderManager::saveFeatureAssignment($feature, $provider, $model, $userId);

            $statusCode = !empty($result['success']) ? 200 : 422;
            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result, $statusCode);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:saveAiFeatureAssignment] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Gagal menyimpan penugasan fitur: Terjadi kesalahan internal.',
            ], 500);
        }
    }

    /**
     * POST /admin/ai/feature/test
     * Uji konfigurasi fitur AI aktif (C-Find atau Matching)
     */
    public function testAiFeatureConfiguration(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $feature = trim((string)($_POST['feature'] ?? ''));

            if (!in_array($feature, ['cfind', 'matching'], true)) {
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                $this->json(['success' => false, 'message' => 'Fitur tidak valid.'], 400);
                return;
            }

            $result = \App\Services\AiProviderManager::testFeatureConfiguration($feature);

            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:testAiFeatureConfiguration] ' . $e->getMessage());
            $this->json([
                'success'     => false,
                'message'     => 'Uji konfigurasi fitur gagal: Terjadi kesalahan internal.',
                'http_status' => 500,
            ], 500);
        }
    }

    /**
     * POST /admin/ai/default/save
     * Simpan penugasan Model Default sistem (Task 2B)
     */
    public function saveAiDefaultAssignment(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $provider = trim((string)($_POST['provider_id'] ?? $_POST['provider'] ?? ''));
            $model = trim((string)($_POST['model_id'] ?? $_POST['model'] ?? ''));

            $currentUser = AuthMiddleware::user();
            $userId = $currentUser['id'] ?? null;

            $result = \App\Services\AiProviderManager::saveDefaultAssignment($provider, $model, $userId);

            $statusCode = !empty($result['success']) ? 200 : 422;
            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result, $statusCode);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:saveAiDefaultAssignment] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Gagal menyimpan model default: Terjadi kesalahan internal.',
            ], 500);
        }
    }

    /**
     * POST /admin/ai/default/test
     * Uji koneksi Model Default sistem (Task 2B)
     */
    public function testAiDefaultConnection(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $default = \App\Services\AiProviderManager::getDefaultAssignment();
            $result = \App\Services\AiProviderManager::testChatInference($default['provider'], $default['model']);
            $result['provider_name'] = $default['provider_name'];

            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:testAiDefaultConnection] ' . $e->getMessage());
            $this->json([
                'success'     => false,
                'message'     => 'Uji model default gagal: Terjadi kesalahan internal.',
                'http_status' => 500,
            ], 500);
        }
    }

    /**
     * POST /admin/ai/feature/revert-default
     * Kembalikan penugasan fitur mengikuti Model Default (Task 2B)
     */
    public function revertAiFeatureToDefault(array $params = []): void
    {
        ob_start();
        try {
            AuthMiddleware::requireRole('admin');
            CsrfMiddleware::validate();

            $feature = trim((string)($_POST['feature'] ?? ''));
            if (!in_array($feature, ['cfind', 'matching'], true)) {
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                $this->json(['success' => false, 'message' => 'Fitur tidak valid.'], 400);
                return;
            }

            $currentUser = AuthMiddleware::user();
            $userId = $currentUser['id'] ?? null;

            $result = \App\Services\AiProviderManager::revertFeatureToDefault($feature, $userId);

            if (ob_get_level() > 0) {
                ob_clean();
            }
            $this->json($result);
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'JSON:')) {
                throw $e;
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            error_log('[AdminController:revertAiFeatureToDefault] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Gagal mengembalikan penugasan fitur: Terjadi kesalahan internal.',
            ], 500);
        }
    }

    /**
     * GET /admin/notification-settings
     * Halaman Manajemen Pengaturan Notifikasi & Antrean Outbox (Prompt 20F)
     */
    public function notificationSettings(array $params = []): void
    {
        $settings = SettingService::getAll();
        $stats    = \App\Services\Notifications\NotificationOutboxService::getStats();

        $pdo = Database::getInstance()->getPdo();

        // 1. Ambil seluruh templat notifikasi
        $templates = $pdo->query("SELECT * FROM notification_templates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

        // 2. Ambil antrean outbox dengan filter & paginasi
        $statusFilter = $_GET['status'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $whereClause = "";
        $bindParams = [];
        if (!empty($statusFilter) && in_array($statusFilter, ['pending', 'sending', 'sent', 'failed', 'cancelled'], true)) {
            $whereClause = "WHERE no.status = ?";
            $bindParams[] = $statusFilter;
        }

        $countSql = "SELECT COUNT(*) FROM notification_outbox no {$whereClause}";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($bindParams);
        $totalOutbox = (int) $stmtCount->fetchColumn();
        $totalPages = max(1, (int) ceil($totalOutbox / $limit));

        $listSql = "
            SELECT no.*, u.name as user_name 
            FROM notification_outbox no
            LEFT JOIN users u ON u.id = no.user_id
            {$whereClause}
            ORDER BY no.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmtList = $pdo->prepare($listSql);
        $stmtList->execute($bindParams);
        $outboxList = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        $currentAuthUser = AuthMiddleware::user();
        $adminId = (int) ($currentAuthUser['id'] ?? 0);

        // Ambil data segar admin saat ini langsung dari database (bukan dari sesi lama atau templat)
        $stmtFreshAdmin = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
        $stmtFreshAdmin->execute([$adminId]);
        $freshAdmin = $stmtFreshAdmin->fetch(PDO::FETCH_ASSOC) ?: $currentAuthUser;

        // Ambil alamat terakhir yang diingat
        $lastTestEmail = $_SESSION['last_test_email'] ?? SettingService::get('notif_last_test_email_' . $adminId, null);
        $defaultTestEmail = !empty($lastTestEmail) ? $lastTestEmail : ($freshAdmin['email'] ?? '');

        // Jejak Keputusan & Kesiapan Penerima
        $decisions = \App\Services\Notifications\NotificationDecisionService::getRecentDecisions(50, $_GET['decision_filter'] ?? null);
        $readinessList = \App\Services\Notifications\NotificationDecisionService::getRecipientReadinessList(100);

        $flashSuccess = $this->getFlash('success');
        $flashError   = $this->getFlash('error');

        $this->render('admin/notification_settings', [
            'pageTitle'        => 'Pengaturan Notifikasi & Outbox — Admin Campus Find',
            'settings'         => $settings,
            'stats'            => $stats,
            'templates'        => $templates,
            'outboxList'       => $outboxList,
            'statusFilter'     => $statusFilter,
            'currentPage'      => $page,
            'totalPages'       => $totalPages,
            'totalOutbox'      => $totalOutbox,
            'user'             => $freshAdmin,
            'defaultTestEmail' => $defaultTestEmail,
            'decisions'        => $decisions,
            'readinessList'    => $readinessList,
            'flashSuccess'     => is_string($flashSuccess) ? $flashSuccess : null,
            'flashError'       => is_string($flashError) ? $flashError : null,
        ], 'app_shell');
    }

    /**
     * POST /admin/notification-settings
     * Simpan Perubahan Pengaturan Notifikasi & Templat
     */
    public function updateNotificationSettings(array $params = []): void
    {
        CsrfMiddleware::validate();

        $currentUser = AuthMiddleware::user();
        $userId = (int) ($currentUser['id'] ?? 0);

        // 1. Simpan System Settings (Password terenkripsi, audit log tercatat tanpa nilai rahasia)
        $result = SettingService::updateSettings($_POST, $userId);

        // 2. Simpan perubahan template jika ada
        if (!empty($_POST['templates']) && is_array($_POST['templates'])) {
            $templateValidationErrors = [];

            // Validasi seluruh placeholder yang dikirim
            foreach ($_POST['templates'] as $key => $tpl) {
                if (!empty($tpl['subject']) || !empty($tpl['body_html'])) {
                    $valResult = \App\Services\Notifications\NotificationTemplateService::validateTemplatePlaceholders(
                        $key,
                        (string) ($tpl['subject'] ?? ''),
                        (string) ($tpl['body_html'] ?? ''),
                        (string) ($tpl['body_text'] ?? '')
                    );
                    if (!$valResult['valid']) {
                        $templateValidationErrors = array_merge($templateValidationErrors, $valResult['errors']);
                    }
                }
            }

            if (!empty($templateValidationErrors)) {
                $err = implode('<br>', $templateValidationErrors);
                $this->setFlash('error', "Gagal menyimpan templat:<br>{$err}");
                $this->redirect('/admin/notification-settings');
                return;
            }

            $pdo = Database::getInstance()->getPdo();
            $stmtTpl = $pdo->prepare("
                UPDATE notification_templates 
                SET subject = ?, body_html = ?, body_text = ?, updated_at = NOW() 
                WHERE template_key = ?
            ");

            foreach ($_POST['templates'] as $key => $tpl) {
                if (!empty($tpl['subject']) && !empty($tpl['body_html'])) {
                    $stmtTpl->execute([
                        trim($tpl['subject']),
                        trim($tpl['body_html']),
                        trim($tpl['body_text'] ?? strip_tags($tpl['body_html'])),
                        $key,
                    ]);
                }
            }
        }

        if ($result['success']) {
            $this->setFlash('success', 'Pengaturan notifikasi email dan templat berhasil disimpan.');
        } else {
            $err = implode(' ', $result['errors'] ?? []);
            $this->setFlash('error', "Gagal menyimpan pengaturan: {$err}");
        }

        $this->redirect('/admin/notification-settings');
    }

    /**
     * POST /admin/notification-settings/allowlist/add-user
     * Tambah alamat email pengguna ke NOTIFY_ALLOWLIST untuk kasus khusus (Prompt 34C)
     * Dilengkapi konfirmasi Admin dan pencatatan audit log.
     */
    public function addRecipientToAllowlist(array $params = []): void
    {
        AuthMiddleware::requireRole('admin');
        CsrfMiddleware::validate();

        $appEnv = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development'));
        if ($appEnv === 'production') {
            $msg = 'NOTIFY_ALLOWLIST tidak berlaku pada lingkungan produksi. Seluruh pengiriman produksi tunduk pada syarat verifikasi OTP dan opt-in pengguna.';
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => $msg], 400);
            }
            $this->flash('danger', $msg);
            $this->redirectBack('/admin/notification-settings');
            return;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $targetUser = $this->userModel->findWithRole($userId);

        if (!$targetUser) {
            $msg = 'Pengguna tidak ditemukan.';
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => $msg], 404);
            }
            $this->flash('danger', $msg);
            $this->redirectBack('/admin/notification-settings');
            return;
        }

        $targetEmail = strtolower(trim((string)($targetUser['email'] ?? '')));

        if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            $msg = "Format alamat email pengguna '{$targetEmail}' tidak valid.";
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => $msg], 422);
            }
            $this->flash('danger', $msg);
            $this->redirectBack('/admin/notification-settings');
            return;
        }

        // Domain reservasi (.test, .example, .invalid, .localhost) DILARANG masuk allowlist di semua lingkungan
        if (\App\Services\Notifications\MailGateway::isReservedOrExampleDomain($targetEmail)) {
            $msg = "Alamat email '{$targetEmail}' menggunakan domain reservasi/contoh (.test, .example, .invalid, .localhost) yang diblokir permanen dan tidak dapat ditambahkan ke allowlist.";
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => $msg], 422);
            }
            $this->flash('danger', $msg);
            $this->redirectBack('/admin/notification-settings');
            return;
        }

        // Ambil daftar allowlist yang ada
        $currentAllowlist = \App\Services\Notifications\MailGateway::getAllowlistEmails();
        if (!in_array($targetEmail, $currentAllowlist, true)) {
            $currentAllowlist[] = $targetEmail;
        }

        $newAllowlistStr = implode(', ', $currentAllowlist);
        SettingService::set('notif_allowlist', $newAllowlistStr);

        $currentAdmin = AuthMiddleware::user();
        $adminId = (int)($currentAdmin['id'] ?? 0);

        // Audit Log: Catat aksi penambahan ke allowlist
        AuditLogService::log('ALLOWLIST_USER_ADDED', $adminId, 'users', $userId, [
            'target_user_id' => $userId,
            'email'          => \App\Services\OtpService::maskEmail($targetEmail),
            'raw_email'      => $targetEmail,
            'user_name'      => $targetUser['name'],
            'env'            => $appEnv,
        ]);

        $masked = \App\Services\OtpService::maskEmail($targetEmail);
        $successMsg = "Alamat email {$masked} milik pengguna '{$targetUser['name']}' berhasil ditambahkan ke NOTIFY_ALLOWLIST kasus khusus.";

        if ($this->isAjax()) {
            $this->json([
                'success'   => true,
                'message'   => $successMsg,
                'allowlist' => $newAllowlistStr,
                'user_id'   => $userId,
            ]);
        }

        $this->flash('success', $successMsg);
        $this->redirectBack('/admin/notification-settings');
    }

    /**
     * POST /admin/notification-settings/test-connection
     * Uji Koneksi Live ke Server SMTP (AJAX endpoint)
     */
    public function testSmtpConnection(array $params = []): void
    {
        CsrfMiddleware::validate();

        $configOverride = null;
        if (!empty($_POST['notif_smtp_host'])) {
            $configOverride = [
                'host'         => trim((string)$_POST['notif_smtp_host']),
                'port'         => (int)($_POST['notif_smtp_port'] ?? 587),
                'encryption'   => trim((string)($_POST['notif_smtp_encryption'] ?? 'tls')),
                'username'     => trim((string)($_POST['notif_smtp_username'] ?? '')),
                'password'     => !empty($_POST['notif_smtp_password']) ? trim((string)$_POST['notif_smtp_password']) : (string)SettingService::get('notif_smtp_password', ''),
                'from_address' => trim((string)($_POST['notif_smtp_from_address'] ?? 'no-reply@campusfind.ac.id')),
                'from_name'    => trim((string)($_POST['notif_smtp_from_name'] ?? 'Campus Find L&F')),
            ];
        }

        $driver = new \App\Services\Notifications\EmailSmtpDriver($configOverride);
        $res = $driver->testConnection($configOverride);

        $this->json($res);
    }

    /**
     * POST /admin/notification-settings/test-email
     * Kirim email uji coba ke alamat tujuan (default: email Admin yang sedang login) (AJAX endpoint)
     */
    public function sendTestEmail(array $params = []): void
    {
        CsrfMiddleware::validate();

        $currentUser = AuthMiddleware::user();
        $adminId = (int) ($currentUser['id'] ?? 0);

        // Ambil data segar admin saat ini langsung dari database (bukan dari sesi lama)
        $pdo = Database::getInstance()->getPdo();
        $stmtFresh = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
        $stmtFresh->execute([$adminId]);
        $freshAdmin = $stmtFresh->fetch(PDO::FETCH_ASSOC) ?: $currentUser;
        $adminEmail = $freshAdmin['email'] ?? null;

        if (empty($adminEmail)) {
            $this->json(['success' => false, 'message' => 'Alamat email Admin tidak ditemukan dalam database.'], 400);
            return;
        }

        // Ambil alamat tujuan dari POST, fallback ke email Admin dari database jika kosong
        $rawTarget = trim((string)($_POST['target_email'] ?? $_POST['recipient'] ?? ''));
        $targetEmail = !empty($rawTarget) ? $rawTarget : $adminEmail;

        if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            $this->json([
                'success' => false,
                'message' => "Format alamat email tujuan tidak valid: '{$targetEmail}'.",
            ], 422);
            return;
        }

        // Simpan alamat tujuan terakhir agar diingat untuk pengujian berikutnya
        $_SESSION['last_test_email'] = $targetEmail;
        SettingService::set('notif_last_test_email_' . $adminId, $targetEmail);

        $appUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://campusfind-clean.test', '/');

        // Bentuk outbox item untuk email uji
        $outboxItem = [
            'id'           => 0,
            'user_id'      => $adminId,
            'recipient'    => $targetEmail,
            'template_key' => 'test_email',
            'payload'      => [
                'title'       => 'Uji Coba Pengiriman Email SMTP',
                'message'     => 'Ini adalah email uji coba dari sistem Campus Find untuk memastikan konfigurasi SMTP Anda berfungsi dengan baik.',
                'url'         => '/admin/notification-settings',
                'user_name'   => $currentUser['name'] ?? 'Administrator',
            ],
        ];

        // Satu pintu pengiriman lewat MailGateway
        $res = \App\Services\Notifications\MailGateway::send($outboxItem);

        // Periksa peringatan Gmail SMTP (host smtp.gmail.com namun From Address != SMTP Username)
        $warning = null;
        $driver = \App\Services\Notifications\MailGateway::getDriver();
        $config = method_exists($driver, 'getConfig') ? $driver->getConfig() : [];
        $host = strtolower($config['host'] ?? '');
        if (str_contains($host, 'smtp.gmail.com')) {
            $fromAddress = strtolower($config['from_address'] ?? '');
            $smtpUsername = strtolower($config['username'] ?? '');
            if (!empty($smtpUsername) && !empty($fromAddress) && $fromAddress !== $smtpUsername) {
                $warning = "Perhatian: Host SMTP menggunakan smtp.gmail.com namun Alamat Pengirim ({$config['from_address']}) berbeda dari SMTP Username ({$config['username']}). Gmail dapat menolak pengiriman atau secara otomatis mengubah alamat pengirim menjadi akun Google yang diautentikasi.";
            }
        }

        if (!empty($res['success'])) {
            $this->json([
                'success'    => true,
                'message'    => "Email uji coba berhasil dikirimkan ke {$targetEmail}.",
                'message_id' => $res['message_id'] ?? null,
                'target'     => $targetEmail,
                'warning'    => $warning,
            ]);
        } elseif (!empty($res['blocked'])) {
            // Bila diblokir oleh NOTIFY_ALLOWLIST, tampilkan alasannya dengan jelas
            $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';
            $err = $res['error'] ?? "Alamat email {$targetEmail} diblokir oleh NOTIFY_ALLOWLIST.";
            $this->json([
                'success' => false,
                'blocked' => true,
                'message' => $err,
                'reason'  => "Alamat email '{$targetEmail}' tidak terdaftar dalam NOTIFY_ALLOWLIST pada lingkungan non-produksi ({$appEnv}). Tambahkan alamat ini atau wildcard domain (contoh: *@domain.com) pada kolom NOTIFY_ALLOWLIST untuk mengizinkan pengiriman.",
                'target'  => $targetEmail,
                'warning' => $warning,
            ], 422);
        } else {
            $err = $res['error'] ?? 'Pengiriman email gagal tanpa keterangan.';
            $this->json([
                'success' => false,
                'blocked' => false,
                'message' => "Gagal mengirim email uji: {$err}",
                'target'  => $targetEmail,
                'warning' => $warning,
            ], 422);
        }
    }

    /**
     * POST /admin/notification-settings/retry/{id}
     * Kirim ulang item outbox yang gagal atau dibatalkan
     */
    public function retryOutboxItem(array $params = []): void
    {
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $ok = \App\Services\Notifications\NotificationOutboxService::retryItem($id);

        $msg = $ok
            ? "Item antrean #{$id} berhasil dijadwalkan ulang untuk dikirim."
            : "Gagal menjadwalkan ulang item #{$id}. Pastikan item berstatus failed atau cancelled.";

        if ($this->isAjax()) {
            $this->json([
                'ok'      => $ok,
                'success' => $ok,
                'message' => $msg,
            ], $ok ? 200 : 422);
            return;
        }

        if ($ok) {
            $this->setFlash('success', $msg);
        } else {
            $this->setFlash('error', $msg);
        }

        $this->redirectBack('/admin/notification-settings#tab-outbox');
    }

    /**
     * POST /admin/notification-settings/reset-circuit
     * Reset Circuit Breaker notifikasi
     */
    public function resetCircuitBreaker(array $params = []): void
    {
        CsrfMiddleware::validate();

        \App\Services\Notifications\NotificationOutboxService::resetCircuitBreaker();
        $msg = 'Circuit breaker berhasil di-reset ke status normal.';

        if ($this->isAjax()) {
            $this->json([
                'ok'      => true,
                'success' => true,
                'message' => $msg,
            ]);
            return;
        }

        $this->setFlash('success', $msg);
        $this->redirect('/admin/notification-settings');
    }

    /**
     * POST /admin/notification-settings/process-now
     * Trigger worker manual dari UI Admin (Demo / Pengembangan)
     * Mengembalikan respons JSON murni {ok, sent, failed, blocked, skipped, message}
     */
    public function processOutboxNow(array $params = []): void
    {
        CsrfMiddleware::validate();

        // 1. Rate Limit: Batasi eksekusi tombol manual (maksimal 1x per 5 detik per sesi admin)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();
        $lastRun = (int) ($_SESSION['notif_worker_last_run'] ?? 0);
        $rateLimitWindow = 5; // 5 detik
        if (($now - $lastRun) < $rateLimitWindow) {
            $remaining = $rateLimitWindow - ($now - $lastRun);
            $this->json([
                'ok'      => false,
                'sent'    => 0,
                'failed'  => 0,
                'blocked' => 0,
                'skipped' => 0,
                'message' => "Terlalu banyak permintaan. Harap tunggu {$remaining} detik sebelum menjalankan worker kembali.",
            ], 429);
            return;
        }
        $_SESSION['notif_worker_last_run'] = $now;

        // 2. Batas per putaran (Batch size): Default 20, batas maksimal 50
        $requestedBatch = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 20;
        $batchSize = max(1, min(50, $requestedBatch));

        $res = \App\Services\Notifications\NotificationOutboxService::processOutbox($batchSize);

        if (!empty($res['circuit_breaker'])) {
            $this->json([
                'ok'      => false,
                'sent'    => 0,
                'failed'  => 0,
                'blocked' => 0,
                'skipped' => 0,
                'message' => 'Circuit breaker sedang aktif! Pengiriman dihentikan sementara karena kegagalan beruntun. Silakan reset circuit breaker terlebih dahulu.',
            ], 422);
            return;
        }

        $sent = (int) ($res['sent'] ?? 0);
        $failed = (int) ($res['failed'] ?? 0);
        $blocked = (int) ($res['blocked'] ?? 0);
        $skipped = (int) (($res['skipped'] ?? 0) ?: ($res['postponed'] ?? 0));
        $processed = (int) ($res['processed'] ?? 0);

        if ($processed === 0 && $skipped === 0) {
            $message = 'Tidak ada antrean notifikasi yang perlu diproses saat ini.';
        } else {
            $details = [];
            if ($sent > 0) $details[] = "{$sent} terkirim";
            if ($failed > 0) $details[] = "{$failed} gagal";
            if ($blocked > 0) $details[] = "{$blocked} diblokir";
            if ($skipped > 0) $details[] = "{$skipped} dilewati/ditunda";
            $detailStr = !empty($details) ? implode(', ', $details) : '0 item diproses';
            $message = "Worker berhasil dijalankan ({$detailStr}).";
        }

        $this->json([
            'ok'      => true,
            'sent'    => $sent,
            'failed'  => $failed,
            'blocked' => $blocked,
            'skipped' => $skipped,
            'message' => $message,
        ], 200);
    }

    /**
     * GET /admin/users/import
     * Halaman Impor Data Civitas dari Excel/CSV (Prompt 31)
     */
    public function importCivitas(array $params = []): void
    {
        $previewData = $_SESSION['civitas_import_preview'] ?? null;
        $lastResult  = $_SESSION['civitas_import_last_result'] ?? null;

        $flashSuccess = $this->getFlash('success');
        $flashError   = $this->getFlash('error');

        $this->render('admin/import_civitas', [
            'pageTitle'    => 'Impor Data Civitas — Campus Find Admin',
            'previewData'  => $previewData,
            'lastResult'   => $lastResult,
            'flashSuccess' => is_string($flashSuccess) ? $flashSuccess : null,
            'flashError'   => is_string($flashError) ? $flashError : null,
        ], 'app_shell');
    }

    /**
     * GET /admin/users/import/template
     * Unduh templat resmi Excel (.xlsx) untuk impor Civitas
     */
    public function downloadCivitasTemplate(array $params = []): void
    {
        if (!class_exists('ZipArchive')) {
            $this->setFlash('error', 'Ekstensi PHP ZipArchive (ext-zip) tidak aktif di server web. Harap aktifkan extension=zip di php.ini.');
            $this->redirect('/admin/users/import');
            return;
        }

        $spreadsheet = \App\Services\CivitasImportService::generateTemplate();
        $filename = 'templat_impor_civitas_' . date('Ymd') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * POST /admin/users/import/preview
     * Unggah berkas dan tampilkan pratinjau validasi (Langkah A)
     */
    public function uploadCivitasImport(array $params = []): void
    {
        CsrfMiddleware::validate();

        $file = $_FILES['import_file'] ?? null;
        if (!$file) {
            $this->setFlash('error', 'Tidak ada berkas yang dipilih.');
            $this->redirect('/admin/users/import');
            return;
        }

        $res = \App\Services\CivitasImportService::parseUpload($file);

        if (!$res['success']) {
            $this->setFlash('error', $res['error'] ?? 'Gagal memproses berkas impor.');
            $this->redirect('/admin/users/import');
            return;
        }

        $this->setFlash('success', "Berkas berhasil dianalisis: {$res['preview']['valid_count']} valid, {$res['preview']['duplicate_count']} duplikat, {$res['preview']['error_count']} galat.");
        $this->redirect('/admin/users/import');
    }

    /**
     * POST /admin/users/import/confirm
     * Konfirmasi penyimpanan data civitas ke database (Langkah B)
     */
    public function confirmCivitasImport(array $params = []): void
    {
        CsrfMiddleware::validate();

        $previewId = trim((string)($_POST['preview_id'] ?? ''));
        $mode = trim((string)($_POST['mode'] ?? 'skip'));

        $currentUser = AuthMiddleware::user();
        $adminUserId = (int) ($currentUser['id'] ?? 1);

        $res = \App\Services\CivitasImportService::commitImport($previewId, $mode, $adminUserId);

        if (!$res['success']) {
            $this->setFlash('error', $res['error'] ?? 'Gagal menyimpan data impor.');
            $this->redirect('/admin/users/import');
            return;
        }

        $r = $res['result'];
        $this->setFlash('success', "Impor data civitas berhasil disimpan! {$r['created_count']} akun baru dibuat (status belum aktif), {$r['updated_count']} diperbarui, {$r['skipped_count']} dilewati, {$r['failed_count']} gagal.");
        $this->redirect('/admin/users/import');
    }

    /**
     * POST /admin/users/import/cancel
     * Batalkan pratinjau impor yang sedang aktif
     */
    public function cancelCivitasImport(array $params = []): void
    {
        CsrfMiddleware::validate();
        unset($_SESSION['civitas_import_preview']);
        $this->setFlash('success', 'Pratinjau impor berhasil dibatalkan.');
        $this->redirect('/admin/users/import');
    }

    /**
     * GET /admin/users/import/report
     * Unduh laporan hasil eksekusi impor (.xlsx)
     */
    public function downloadCivitasImportReport(array $params = []): void
    {
        if (!class_exists('ZipArchive')) {
            $this->setFlash('error', 'Ekstensi PHP ZipArchive (ext-zip) tidak aktif di server web. Harap aktifkan extension=zip di php.ini.');
            $this->redirect('/admin/users/import');
            return;
        }

        $lastResult = $_SESSION['civitas_import_last_result'] ?? null;
        if (!$lastResult) {
            $this->setFlash('error', 'Belum ada riwayat hasil impor yang dapat diunduh.');
            $this->redirect('/admin/users/import');
            return;
        }

        $spreadsheet = \App\Services\CivitasImportService::generateResultReport($lastResult);
        $filename = 'laporan_hasil_impor_civitas_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
