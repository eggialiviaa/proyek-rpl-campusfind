<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Core\Database;
use PDO;

/**
 * ProfileController — Pengelolaan Profil Pengguna & Preferensi Notifikasi (Prompt 20C)
 */
class ProfileController extends BaseController
{
    /**
     * GET /profile
     * Tampilkan halaman profil pengguna dan preferensi notifikasi
     */
    public function index(array $params = []): void
    {
        AuthMiddleware::require();

        $sessionUser = AuthMiddleware::user();
        $userId = (int) $sessionUser['id'];

        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name, r.label as role_label 
            FROM users u 
            LEFT JOIN roles r ON r.id = u.role_id 
            WHERE u.id = ? 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->redirect('/login');
            return;
        }

        // Ambil riwayat outbox 5 terakhir milik pengguna
        $stmtOutbox = $pdo->prepare("
            SELECT id, template_key, status, error_reason, sent_at, created_at 
            FROM notification_outbox 
            WHERE user_id = ? 
            ORDER BY id DESC 
            LIMIT 5
        ");
        $stmtOutbox->execute([$userId]);
        $recentOutbox = $stmtOutbox->fetchAll(PDO::FETCH_ASSOC);

        $flashSuccess = $_SESSION['flash_success'] ?? $_SESSION['_flash']['success'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? $_SESSION['_flash']['error'] ?? $_SESSION['_flash']['danger'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        if (isset($_SESSION['_flash']['success'])) unset($_SESSION['_flash']['success']);
        if (isset($_SESSION['_flash']['error'])) unset($_SESSION['_flash']['error']);
        if (isset($_SESSION['_flash']['danger'])) unset($_SESSION['_flash']['danger']);

        $emailChangeInProgress = $_SESSION['email_change_in_progress'] ?? null;
        $emailVerificationInProgress = $_SESSION['email_verification_in_progress'] ?? null;

        $this->render('profile/index', [
            'pageTitle'                   => 'Profil Pengguna — Campus Find',
            'user'                        => $user,
            'recentOutbox'                => $recentOutbox,
            'emailChangeInProgress'       => $emailChangeInProgress,
            'emailVerificationInProgress' => $emailVerificationInProgress,
            'flashSuccess'                => is_string($flashSuccess) ? $flashSuccess : (is_array($flashSuccess) ? implode(' ', array_filter($flashSuccess, 'is_scalar')) : null),
            'flashError'                  => is_string($flashError) ? $flashError : (is_array($flashError) ? implode(' ', array_filter($flashError, 'is_scalar')) : null),
        ], 'app_shell');
    }

    /**
     * POST /profile/notifications
     * Simpan preferensi persetujuan (opt-in / opt-out) notifikasi email
     */
    public function updateNotificationPreferences(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $sessionUser = AuthMiddleware::user();
        $userId = (int) $sessionUser['id'];

        $enabled = isset($_POST['email_notifications_enabled']) && ($_POST['email_notifications_enabled'] === '1' || $_POST['email_notifications_enabled'] === 'on');

        $pdo = Database::getInstance()->getPdo();

        if ($enabled) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET email_notifications_enabled = 1,
                    email_consent_at = NOW(),
                    email_consent_text_version = 'v1.0',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $_SESSION['flash_success'] = 'Notifikasi email berhasil diaktifkan. Anda akan menerima pembaruan status laporan dan klaim.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET email_notifications_enabled = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $_SESSION['flash_success'] = 'Notifikasi email berhasil dinonaktifkan. Anda hanya akan menerima notifikasi di dalam aplikasi (in-app).';
        }

        $this->redirect('/profile');
    }

    /**
     * POST /profile/phone
     * Civitas memperbarui nomor WhatsApp sendiri
     */
    public function updatePhone(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $sessionUser = AuthMiddleware::user();
        $userId = (int) $sessionUser['id'];
        $phone = trim((string) ($_POST['phone'] ?? ''));

        try {
            $res = \App\Services\AccountManagementService::civitasUpdatePhone($userId, $phone);
            $_SESSION['flash_success'] = $res['message'];
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log('[ProfileController::updatePhone] Galat: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Terjadi kesalahan sistem saat memperbarui nomor WhatsApp.';
        }

        $this->redirect('/profile');
    }

    /**
     * POST /profile/email/request-otp
     * Civitas meminta OTP ke email BARU dengan konfirmasi kata sandi saat ini
     */
    public function requestEmailChangeOtp(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $sessionUser = AuthMiddleware::user();
        $userId = (int) $sessionUser['id'];

        $newEmail = trim((string) ($_POST['new_email'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

        try {
            $res = \App\Services\AccountManagementService::civitasRequestEmailChange($userId, $newEmail, $currentPassword, $ip, $ua);

            if ($res['success']) {
                $_SESSION['email_change_in_progress'] = [
                    'target_email' => $res['target_email'],
                    'email_masked' => $res['email_masked'],
                    'requested_at' => time(),
                ];
                $_SESSION['flash_success'] = $res['message'];
            } else {
                $_SESSION['flash_error'] = $res['message'];
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (\Throwable $e) {
            error_log('[ProfileController::requestEmailChangeOtp] Galat: ' . $e->getMessage());
            $errMsg = 'Terjadi kesalahan sistem saat memproses permintaan perubahan email.';
            $_SESSION['flash_error'] = $errMsg;

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $errMsg], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $this->redirect('/profile');
    }

    /**
     * POST /profile/email/verify-otp
     * Civitas memasukkan OTP yang dikirimkan ke email baru
     */
    public function verifyEmailChangeOtp(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $sessionUser = AuthMiddleware::user();
        $userId = (int) $sessionUser['id'];
        $otpCode = trim((string) ($_POST['otp_code'] ?? ''));

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

        try {
            $res = \App\Services\AccountManagementService::civitasConfirmEmailChange($userId, $otpCode);

            if ($res['success']) {
                unset($_SESSION['email_change_in_progress']);
                // Perbarui sesi login
                if (isset($_SESSION['user'])) {
                    $_SESSION['user']['email'] = $res['new_email'];
                }
                $_SESSION['flash_success'] = $res['message'];
            } else {
                $_SESSION['flash_error'] = $res['message'];
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (\Throwable $e) {
            error_log('[ProfileController::verifyEmailChangeOtp] Galat: ' . $e->getMessage());
            $errMsg = 'Terjadi kesalahan sistem saat memverifikasi kode OTP.';
            $_SESSION['flash_error'] = $errMsg;

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $errMsg], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $this->redirect('/profile');
    }

    /**
     * POST /profile/email/cancel
     * Batalkan proses perubahan email yang sedang menunggu OTP
     */
    public function cancelEmailChange(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        unset($_SESSION['email_change_in_progress']);
        $_SESSION['flash_success'] = 'Permintaan perubahan email dibatalkan.';
        $this->redirect('/profile');
    }

    /**
     * POST /profile/email/verify/request-otp
     * Minta kode OTP untuk verifikasi alamat email saat ini
     */
    public function requestCurrentEmailVerificationOtp(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $sessionUser = AuthMiddleware::user();
        $userId = (int) $sessionUser['id'];

        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare("SELECT id, name, email, email_verified_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->redirect('/login');
            return;
        }

        if (!empty($user['email_verified_at'])) {
            $_SESSION['flash_success'] = 'Alamat email Anda sudah berstatus terverifikasi.';
            $this->redirect('/profile');
            return;
        }

        $res = \App\Services\OtpService::generate($userId, 'email_verification', $user['email']);

        if (!empty($res['success'])) {
            $_SESSION['email_verification_in_progress'] = [
                'email'        => $user['email'],
                'email_masked' => $res['email_masked'],
                'expires_at'   => $res['expires_at'],
            ];
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'] ?? 'Gagal mengirimkan kode verifikasi email.';
        }

        $this->redirect('/profile');
    }

    /**
     * POST /profile/email/verify/confirm
     * Konfirmasi kode OTP untuk menandai alamat email saat ini terverifikasi
     */
    public function verifyCurrentEmailOtp(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $sessionUser = AuthMiddleware::user();
        $userId = (int) $sessionUser['id'];
        $otpCode = trim((string) ($_POST['otp_code'] ?? ''));

        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare("SELECT id, name, email, email_verified_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->redirect('/login');
            return;
        }

        $res = \App\Services\OtpService::verify($userId, 'email_verification', $otpCode);

        if (!empty($res['success'])) {
            $pdo->prepare("UPDATE users SET email_verified_at = NOW(), email_verified_by_otp = 1, updated_at = NOW() WHERE id = ?")
                ->execute([$userId]);

            \App\Services\AuditLogService::log('EMAIL_VERIFIED_VIA_OTP', $userId, 'users', $userId, [
                'email' => \App\Services\OtpService::maskEmail($user['email']),
            ]);

            unset($_SESSION['email_verification_in_progress']);

            if (isset($_SESSION['auth_user'])) {
                $_SESSION['auth_user']['email_verified_at'] = date('Y-m-d H:i:s');
            }

            $_SESSION['flash_success'] = 'Selamat! Alamat email Anda berhasil diverifikasi. Notifikasi email kini siap dikirimkan ke kotak masuk Anda.';
        } else {
            $_SESSION['flash_error'] = $res['error'] ?? 'Kode verifikasi yang Anda masukkan tidak valid atau telah kedaluwarsa.';
        }

        $this->redirect('/profile');
    }

    /**
     * POST /profile/email/verify/cancel
     * Batalkan sesi verifikasi kode OTP email saat ini
     */
    public function cancelCurrentEmailVerification(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        unset($_SESSION['email_verification_in_progress']);
        $_SESSION['flash_success'] = 'Sesi verifikasi email dibatalkan.';
        $this->redirect('/profile');
    }

    /**
     * POST /profile/password
     * Ubah kata sandi pengguna (Prompt 35B):
     * - Kata sandi saat ini, baru, ulangi
     * - Validasi kecocokan saat ini, aturan kekuatan, konfirmasi
     * - Hash BCRYPT
     * - Invalidate sesi lain (increment session_version)
     * - Email pemberitahuan keamanan bila email terverifikasi
     * - Audit log tanpa mencatat nilai password
     * - Pesan hasil dipusatkan (tengah)
     */
    public function updatePassword(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $sessionUser = AuthMiddleware::user();
        $userId = (int) $sessionUser['id'];

        $currentPassword         = (string) ($_POST['current_password'] ?? '');
        $newPassword             = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirmation = (string) ($_POST['new_password_confirmation'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirmation === '') {
            $this->setFlash('error', 'Semua bidang kata sandi (saat ini, baru, dan konfirmasi) wajib diisi.');
            $this->redirect('/profile');
            return;
        }

        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->redirect('/login');
            return;
        }

        // 1. Verifikasi kata sandi saat ini
        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            \App\Services\AuditLogService::log('PASSWORD_CHANGE_FAILED', $userId, 'users', $userId, [
                'reason' => 'current_password_mismatch',
                'role'   => $user['role_name'] ?? 'unknown',
            ]);
            $this->setFlash('error', 'Kata sandi saat ini yang Anda masukkan salah.');
            $this->redirect('/profile');
            return;
        }

        // 2. Verifikasi kata sandi baru tidak sama dengan yang lama
        if ($currentPassword === $newPassword || password_verify($newPassword, (string) $user['password_hash'])) {
            $this->setFlash('error', 'Kata sandi baru tidak boleh sama dengan kata sandi saat ini.');
            $this->redirect('/profile');
            return;
        }

        // 3. Verifikasi konfirmasi kata sandi
        if ($newPassword !== $newPasswordConfirmation) {
            $this->setFlash('error', 'Konfirmasi kata sandi baru tidak cocok.');
            $this->redirect('/profile');
            return;
        }

        // 4. Validasi aturan kekuatan kata sandi
        $strength = \App\Services\OtpService::validatePasswordStrength($newPassword);
        if (!$strength['valid']) {
            $this->setFlash('error', $strength['message'] ?? 'Kata sandi baru belum memenuhi standar kekuatan yang ditentukan.');
            $this->redirect('/profile');
            return;
        }

        // 5. Hash dan simpan, sekaligus akhiri sesi lain (increment session_version)
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare("
            UPDATE users SET 
                password_hash = ?,
                session_version = session_version + 1,
                must_change_password = 0,
                temp_password_expires_at = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$newHash, $userId]);

        // Perbarui session_version sesi aktif saat ini
        $newVer = (int) $pdo->query("SELECT session_version FROM users WHERE id = {$userId}")->fetchColumn();
        if (isset($_SESSION['auth_user'])) {
            $_SESSION['auth_user']['session_version'] = $newVer;
        }
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        // 6. Audit log tanpa nilai password
        \App\Services\AuditLogService::log('PASSWORD_CHANGED_PROFILE', $userId, 'users', $userId, [
            'role' => $user['role_name'] ?? 'unknown',
        ]);

        // 7. Email pemberitahuan keamanan bila terverifikasi
        if (!empty($user['email_verified_at'])) {
            try {
                \App\Services\Notifications\NotificationOutboxService::enqueue(
                    $userId,
                    'security_password_changed',
                    [
                        'user_name'  => $user['name'],
                        'changed_at' => date('d F Y, H:i') . ' WIB',
                        'app_name'   => 'Campus Find',
                        'app_url'    => rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://campusfind-clean.test', '/'),
                        'action_url' => rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://campusfind-clean.test', '/') . '/forgot-password',
                    ],
                    'email',
                    'pwd_changed_' . $userId . '_' . time()
                );
            } catch (\Throwable $e) {
                error_log('[ProfileController::updatePassword] Gagal mengirim notifikasi keamanan: ' . $e->getMessage());
            }
        }

        $this->setFlash('success', 'Kata sandi akun Anda berhasil diperbarui. Seluruh sesi login di perangkat lain telah diakhiri demi keamanan.');
        $this->redirect('/profile');
    }
}
