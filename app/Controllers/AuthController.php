<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AuthMiddleware;
use App\Models\UserModel;
use App\Services\RateLimiterService;
use App\Services\AuditLogService;

/**
 * AuthController — Pengelolaan Autentikasi Pengguna (Login, Logout, & Session)
 */
class AuthController extends BaseController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * GET /login
     * Tampilkan formulir login
     */
    public function loginForm(array $params = []): void
    {
        // Jika sudah login, langsung arahkan ke dashboard
        if (AuthMiddleware::isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $email = trim($_GET['email'] ?? '');
        $lockInfo = $email !== '' ? RateLimiterService::checkLockout($ip, $email) : ['is_locked' => false, 'remaining_seconds' => 0];

        $landingCssPath = BASE_PATH . '/public/css/landing.css';
        $landingCssVer  = file_exists($landingCssPath) ? (string) filemtime($landingCssPath) : (string) time();

        $loginJsPath = BASE_PATH . '/public/js/login-scene.js';
        $loginJsVer  = file_exists($loginJsPath) ? (string) filemtime($loginJsPath) : (string) time();

        $this->render('auth/login', [
            'pageTitle'       => 'Masuk — Campus Find',
            'metaDescription' => 'Masuk ke akun Campus Find untuk mengakses layanan Lost & Found kampus.',
            'csrfToken'       => CsrfMiddleware::getToken(),
            'lockInfo'        => $lockInfo,
            'hideCFind'       => true,
            'isLandingPage'   => true,
            'extraCss'        => '<link rel="stylesheet" href="/css/landing.css?v=' . $landingCssVer . '">',
            'extraJs'         => '<script src="/js/login-scene.js?v=' . $loginJsVer . '"></script>',
        ]);
    }

    /**
     * POST /login
     * Proses validasi dan autentikasi login pengguna
     */
    public function login(array $params = []): void
    {
        // 1. Validasi CSRF Token
        CsrfMiddleware::validate();

        // 2. Ambil identitas (Email atau NIM/NIP) & password
        $identity = trim($_POST['identity'] ?? $_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // PENTING: Server mengabaikan field role apa pun dari request client (Network/DevTools).
        // Role HANYA ditentukan dari data user di database.

        // 3. Validasi input dasar
        if (empty($identity) || empty($password)) {
            $_SESSION['_old_input'] = ['identity' => $identity];
            $_SESSION['forgot_password_prefill'] = $identity;
            $_SESSION['login_inline_error'] = 'Silakan masukkan NIM/NIP/email dan kata sandi Anda.';
            $this->setFlash('error', 'Silakan masukkan NIM/NIP/email dan kata sandi Anda.');
            $this->redirect('/login');
            return;
        }

        // 4. Periksa Rate Limiting / Lockout (Maksimal 5x gagal -> 5 menit lock)
        $lockStatus = RateLimiterService::checkLockout($ip, $identity);
        if ($lockStatus['is_locked']) {
            $minutes = (int) ceil($lockStatus['remaining_seconds'] / 60);
            $seconds = $lockStatus['remaining_seconds'] % 60;
            $timeText = "{$minutes} menit" . ($seconds > 0 ? " {$seconds} detik" : '');
            
            $_SESSION['_old_input'] = ['identity' => $identity];
            $_SESSION['forgot_password_prefill'] = $identity;
            $_SESSION['login_inline_error'] = "⛔ Terlalu banyak percobaan gagal. Akun/perangkat terkunci sementara selama {$timeText} demi keamanan.";
            $this->setFlash(
                'error',
                "⛔ Terlalu banyak percobaan gagal. Akun/perangkat ini terkunci sementara selama {$timeText} demi keamanan."
            );
            $this->redirect('/login');
            return;
        }

        // 5. Cari data user di database berdasarkan Email ATAU NIM
        $user = $this->userModel->findByIdentity($identity);

        // Akun yang dihapus permanen ditolak secara ketat (Section 4 Rule 4 & Section 8 Scenario 12)
        if ($user && !empty($user['deleted_at'])) {
            RateLimiterService::recordFailure($ip, $identity);
            AuditLogService::log('LOGIN_FAILED', (int)$user['id'], 'users', (int)$user['id'], [
                'identity' => $identity,
                'reason'   => 'account_deleted',
            ]);
            $_SESSION['_old_input'] = ['identity' => $identity];
            $_SESSION['login_inline_error'] = 'Akun ini telah dihapus permanen dan tidak dapat digunakan kembali.';
            $this->setFlash('error', 'Akun ini telah dihapus permanen dan tidak dapat digunakan kembali.');
            $this->redirect('/login');
            return;
        }

        // 6. Kondisi 2 & 5: Akun belum aktif (is_active = 0) atau dinonaktifkan Administrator
        if ($user && (int) $user['is_active'] === 0 && password_verify($password, $user['password_hash'])) {
            $_SESSION['_old_input'] = ['identity' => $identity];
            $_SESSION['forgot_password_prefill'] = $identity;
            $this->setFlash('warning', 'Akun Anda belum mendapatkan akses login. Silakan hubungi Administrator kampus.');
            $this->redirect('/login');
            return;
        }

        // 7. Kondisi 1: Kredensial salah (tidak membedakan NIM/email tidak terdaftar dari kata sandi salah)
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Catat kegagalan ke RateLimiter
            $failResult = RateLimiterService::recordFailure($ip, $identity);

            // Audit log percobaan gagal (tanpa menyimpan password)
            AuditLogService::log('LOGIN_FAILED', $user ? (int) $user['id'] : null, 'users', $user ? (int) $user['id'] : null, [
                'identity' => $identity,
                'attempts' => $failResult['attempts'],
            ]);

            // Preservasi identitas dan prefill Lupa Password (TASK 02C & 02D)
            $_SESSION['_old_input'] = ['identity' => $identity];
            if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['forgot_password_prefill'] = $identity;
            } else {
                unset($_SESSION['forgot_password_prefill']);
            }

            if ($failResult['is_locked']) {
                $remMin = (int) ceil(($failResult['remaining_seconds'] ?? 300) / 60);
                $_SESSION['login_inline_error'] = "⛔ Batas 5 kali percobaan gagal tercapai. Akun/perangkat terkunci sementara selama {$remMin} menit.";
                $this->setFlash(
                    'error',
                    "⛔ Batas 5 kali percobaan gagal tercapai. Akun/perangkat terkunci sementara selama {$remMin} menit."
                );
            } else {
                $_SESSION['login_inline_error'] = 'NIM/NIP/email atau kata sandi tidak sesuai.';
                $this->setFlash(
                    'error',
                    'NIM/NIP/email atau kata sandi tidak sesuai.'
                );
            }

            $this->redirect('/login');
            return;
        }

        // Pengaman status akun aktif
        if ((int) $user['is_active'] === 0) {
            $_SESSION['_old_input'] = ['identity' => $identity];
            if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['forgot_password_prefill'] = $identity;
            } else {
                unset($_SESSION['forgot_password_prefill']);
            }
            $this->setFlash('warning', 'Akun Anda belum mendapatkan akses login. Silakan hubungi Administrator kampus.');
            $this->redirect('/login');
            return;
        }

        // Kondisi 3: Cek apakah kata sandi sementara sudah kedaluwarsa (masa berlaku 7 hari)
        if (!empty($user['must_change_password']) && !empty($user['temp_password_expires_at'])) {
            if (strtotime((string)$user['temp_password_expires_at']) < time()) {
                $_SESSION['_old_input'] = ['identity' => $identity];
                if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['forgot_password_prefill'] = $identity;
                } else {
                    unset($_SESSION['forgot_password_prefill']);
                }
                $this->setFlash('error', 'Kata sandi sementara telah kedaluwarsa. Silakan gunakan Lupa Password apabila email Anda memenuhi syarat verifikasi, atau hubungi Administrator.');
                $this->redirect('/login');
                return;
            }
        }

        // 8. Login Berhasil: Reset Rate Limiter & Daftarkan Session
        RateLimiterService::reset($ip, $identity);
        unset($_SESSION['_old_input'], $_SESSION['forgot_password_prefill'], $_SESSION['login_inline_error']);

        // Role HANYA diambil dari database ($user['role_name'])
        $userData = [
            'id'              => (int) $user['id'],
            'name'            => $user['name'],
            'email'           => $user['email'],
            'role'            => $user['role_name'],
            'role_name'       => $user['role_name'],
            'role_label'      => $user['role_label'],
            'phone'           => $user['phone'] ?? null,
            'identity_number' => $user['identity_number'] ?? null,
            'avatar'          => $user['avatar'] ?? null,
            'session_version' => (int) ($user['session_version'] ?? 1),
            'logged_in_at'    => date('Y-m-d H:i:s'),
        ];

        AuthMiddleware::login($userData);

        // 9. Catat Audit Log Login Berhasil
        AuditLogService::log('LOGIN_SUCCESS', (int) $user['id'], 'users', (int) $user['id'], [
            'email' => $user['email'],
            'role'  => $user['role_name'],
        ]);

        // 10. Periksa apakah pengguna wajib mengganti kata sandi sementara (Prompt 20B)
        if (!empty($user['must_change_password'])) {
            $_SESSION['must_change_password'] = true;
            $this->setFlash('warning', 'Anda masuk menggunakan kata sandi sementara. Silakan buat kata sandi baru Anda terlebih dahulu.');
            $this->redirect('/change-password-mandatory');
            return;
        }

        // Format salam berdasarkan waktu sistem (WIB / Asia/Jakarta)
        $hour = (int) date('H');
        $salam = ($hour >= 4 && $hour < 11) ? 'pagi' : (($hour >= 11 && $hour < 15) ? 'siang' : (($hour >= 15 && $hour < 18) ? 'sore' : 'malam'));
        $this->setFlash('success', "Selamat {$salam}, {$user['name']}! Anda berhasil masuk.");

        // 11. Redirect ke intended URL atau dashboard sesuai role
        $targetUrl = AuthMiddleware::intended('/dashboard');
        $this->redirect($targetUrl);
    }

    /**
     * GET /logout & POST /logout
     * Proses keluar dari sistem (dengan validasi CSRF jika via POST)
     */
    public function logout(array $params = []): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfMiddleware::validate();
        }

        $currentUser = AuthMiddleware::user();
        if ($currentUser) {
            AuditLogService::log('LOGOUT', (int) $currentUser['id'], 'users', (int) $currentUser['id'], [
                'email' => $currentUser['email'],
            ]);
        }

        AuthMiddleware::logout();
        unset($_SESSION['must_change_password']);
        CsrfMiddleware::regenerate();

        $this->setFlash('info', 'Anda telah berhasil keluar dari sistem.');
        $this->redirect('/login');
    }

    // ─────────────────────────────────────────────────────────────
    // ALUR AKTIVASI AKUN HASIL IMPOR EXCEL (Prompt 20B)
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /activate
     * Formulir permintaan aktivasi akun civitas
     */
    public function activateForm(array $params = []): void
    {
        if (AuthMiddleware::isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $this->render('auth/activate', [
            'pageTitle'       => 'Aktivasi Akun Civitas — Campus Find',
            'metaDescription' => 'Aktivasi akun civitas kampus yang terdaftar untuk mulai menggunakan Campus Find.',
            'csrfToken'       => CsrfMiddleware::getToken(),
            'hideCFind'       => true,
        ]);
    }

    /**
     * POST /activate
     * Public self-activation diblokir secara ketat (Kebijakan Resmi TASK 02C).
     * Akun hanya dapat dibuat dan diaktivasi oleh Administrator.
     * Kode legacy dipertahankan untuk alur administratif terotorisasi.
     */
    public function sendActivationOtp(array $params = []): void
    {
        CsrfMiddleware::validate();

        // TASK 02C Part A: Tamu / publik tidak dapat mengaktivasi akun sendiri
        $isAuthorized = (AuthMiddleware::isLoggedIn() && (AuthMiddleware::user()['role'] ?? '') === 'admin')
            || (defined('CAMPUS_FIND_TESTING') && CAMPUS_FIND_TESTING && !empty($_SESSION['__testing_allow_legacy_activation']));

        if (!$isAuthorized) {
            $identity = trim((string)($_POST['identity'] ?? ''));
            AuditLogService::log('PUBLIC_ACTIVATION_BLOCKED', null, 'auth', null, [
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'identity' => $identity,
            ]);

            if (AuthMiddleware::isApiRequest()) {
                $this->json([
                    'success' => false,
                    'error'   => 'Aktivasi mandiri tidak tersedia. Untuk mendapatkan akses Campus Find, silakan hubungi Administrator atau staf kampus.',
                ], 403);
                return;
            }

            $this->setFlash('warning', 'Untuk mendapatkan akses Campus Find, silakan hubungi Administrator atau staf kampus.');
            $this->redirect('/activate');
            return;
        }

        // Alur administratif terotorisasi (legacy activation logic)
        $identity = trim((string)($_POST['identity'] ?? ''));
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (empty($identity)) {
            $this->setFlash('error', 'Silakan masukkan NIM atau email terdaftar Anda.');
            $this->redirect('/activate');
            return;
        }

        $genericMessage = 'Jika identitas atau email yang Anda masukkan terdaftar dalam sistem dan membutuhkan aktivasi, kode verifikasi OTP 6 digit telah dikirimkan ke alamat email kampus Anda. Kode berlaku selama 5 menit.';
        $user = $this->userModel->findByIdentity($identity);

        if ($user && ($user['role_name'] ?? '') === 'civitas' && (int)($user['is_active'] ?? 0) === 0 && empty($user['deleted_at'])) {
            $res = \App\Services\OtpService::generate((int)$user['id'], 'activation', $ip);
            if (!empty($res['cooldown'])) {
                $this->setFlash('warning', $res['message']);
                $_SESSION['otp_auth'] = [
                    'user_id'   => (int)$user['id'],
                    'purpose'   => 'activation',
                    'identity'  => $identity,
                    'masked'    => $res['email_masked'] ?? null,
                ];
                $this->redirect('/activate/verify');
                return;
            }

            if (empty($res['success'])) {
                $this->setFlash('error', $res['message'] ?? 'Pengiriman kode verifikasi gagal. Silakan coba beberapa saat lagi.');
                $this->redirect('/activate');
                return;
            }

            $_SESSION['otp_auth'] = [
                'user_id'   => (int)$user['id'],
                'purpose'   => 'activation',
                'identity'  => $identity,
                'masked'    => $res['email_masked'] ?? null,
            ];
        } else {
            AuditLogService::log('ACTIVATION_REQUEST_SKIPPED', $user ? (int)$user['id'] : null, 'users', $user ? (int)$user['id'] : null, [
                'identity' => $identity,
                'reason'   => !$user ? 'user_not_found' : (!empty($user['deleted_at']) ? 'user_deleted' : (((int)$user['is_active'] === 1) ? 'already_active' : 'not_civitas_role')),
            ]);
            $_SESSION['otp_auth'] = [
                'user_id'   => 0,
                'purpose'   => 'activation',
                'identity'  => $identity,
                'masked'    => null,
            ];
        }

        $this->setFlash('info', $genericMessage);
        $this->redirect('/activate/verify');
    }

    /**
     * GET /activate/verify
     * Halaman verifikasi mandiri ditutup untuk umum
     */
    public function activateVerifyForm(array $params = []): void
    {
        if (!AuthMiddleware::isLoggedIn() || (AuthMiddleware::user()['role'] ?? '') !== 'admin') {
            $this->setFlash('warning', 'Untuk mendapatkan akses Campus Find, silakan hubungi Administrator atau staf kampus.');
            $this->redirect('/activate');
            return;
        }

        if (empty($_SESSION['otp_auth']) || ($_SESSION['otp_auth']['purpose'] ?? '') !== 'activation') {
            $this->redirect('/activate');
            return;
        }

        $this->render('auth/activate_verify', [
            'pageTitle'       => 'Verifikasi OTP Aktivasi Akun — Campus Find',
            'metaDescription' => 'Masukkan kode OTP 6 digit dan buat kata sandi baru Anda.',
            'csrfToken'       => CsrfMiddleware::getToken(),
            'identity'        => $_SESSION['otp_auth']['identity'] ?? '',
            'maskedEmail'     => $_SESSION['otp_auth']['masked'] ?? null,
            'hideCFind'       => true,
        ]);
    }

    /**
     * POST /activate/verify
     * Verifikasi kode OTP aktivasi mandiri diblokir untuk publik (TASK 02C)
     */
    public function processActivation(array $params = []): void
    {
        CsrfMiddleware::validate();

        // TASK 02C Part A: Tamu / publik tidak dapat mengaktivasi akun sendiri
        $isAuthorized = (AuthMiddleware::isLoggedIn() && (AuthMiddleware::user()['role'] ?? '') === 'admin')
            || (defined('CAMPUS_FIND_TESTING') && CAMPUS_FIND_TESTING && !empty($_SESSION['__testing_allow_legacy_activation']));

        if (!$isAuthorized) {
            AuditLogService::log('PUBLIC_ACTIVATION_BLOCKED', null, 'auth', null, [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);

            if (AuthMiddleware::isApiRequest()) {
                $this->json([
                    'success' => false,
                    'error'   => 'Aktivasi mandiri tidak tersedia. Untuk mendapatkan akses Campus Find, silakan hubungi Administrator atau staf kampus.',
                ], 403);
                return;
            }

            $this->setFlash('warning', 'Untuk mendapatkan akses Campus Find, silakan hubungi Administrator atau staf kampus.');
            $this->redirect('/activate');
            return;
        }

        $otpAuth = $_SESSION['otp_auth'] ?? null;
        if (empty($otpAuth) || ($otpAuth['purpose'] ?? '') !== 'activation') {
            $this->setFlash('error', 'Sesi verifikasi telah kedaluwarsa. Silakan mulai kembali.');
            $this->redirect('/activate');
            return;
        }

        $userId          = (int)($otpAuth['user_id'] ?? 0);
        $otpCode         = trim((string)($_POST['otp_code'] ?? ''));
        $password        = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');
        $notifConsent    = !empty($_POST['email_notifications_consent']);

        if (empty($otpCode) || empty($password)) {
            $this->setFlash('error', 'Silakan masukkan kode OTP dan buat kata sandi baru.');
            $this->redirect('/activate/verify');
            return;
        }

        if ($password !== $passwordConfirm) {
            $this->setFlash('error', 'Konfirmasi kata sandi tidak cocok.');
            $this->redirect('/activate/verify');
            return;
        }

        // 1. Validasi Aturan Kekuatan Kata Sandi
        $strength = \App\Services\OtpService::validatePasswordStrength($password);
        if (!$strength['valid']) {
            $this->setFlash('error', $strength['message']);
            $this->redirect('/activate/verify');
            return;
        }

        // 2. Jika akun dummy
        if ($userId <= 0) {
            $this->setFlash('error', 'Kode verifikasi tidak valid atau telah kedaluwarsa.');
            $this->redirect('/activate/verify');
            return;
        }

        // 3. Verifikasi OTP
        $verifyRes = \App\Services\OtpService::verify($userId, 'activation', $otpCode);
        if (!$verifyRes['success']) {
            $this->setFlash('error', $verifyRes['error'] ?? 'Kode verifikasi tidak valid.');
            $this->redirect('/activate/verify');
            return;
        }

        // 4. Update data pengguna
        $pdo = \Core\Database::getInstance()->getPdo();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $consentAt = $notifConsent ? date('Y-m-d H:i:s') : null;
        $consentVer = $notifConsent ? 'v1.0' : null;
        $consentEnabled = $notifConsent ? 1 : 0;

        try {
            $pdo->beginTransaction();
            $sql = "
                UPDATE users SET 
                    password_hash = ?,
                    is_active = 1,
                    email_verified_at = NOW(),
                    email_verified_by_otp = 1,
                    must_change_password = 0,
                    temp_password_expires_at = NULL,
                    session_version = COALESCE(session_version, 1) + 1,
                    email_notifications_enabled = ?,
                    email_consent_at = ?,
                    email_consent_text_version = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hash, $consentEnabled, $consentAt, $consentVer, $userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[processActivation] DB error: ' . $e->getMessage());
            $this->setFlash('error', 'Terjadi kesalahan sistem saat mengaktifkan akun. Silakan coba lagi.');
            $this->redirect('/activate/verify');
            return;
        }

        AuditLogService::log('ACCOUNT_ACTIVATED', $userId, 'users', $userId, [
            'email_notifications_enabled' => $consentEnabled,
        ]);

        unset($_SESSION['otp_auth']);
        $this->setFlash('success', 'Akun berhasil diaktifkan oleh Administrator! Pengguna dapat masuk dengan kata sandi baru.');
        $this->redirect('/login');
    }

    // ─────────────────────────────────────────────────────────────
    // ALUR LUPA KATA SANDI (Prompt 20B / TASK 02C)
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /forgot-password
     * Formulir permintaan reset kata sandi (Mendukung prefill identitas dari gagal login)
     */
    public function forgotPasswordForm(array $params = []): void
    {
        if (AuthMiddleware::isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        // Prefill identitas dari form login yang gagal sebelumnya (TASK 02C & 02D)
        // HANYA jika pengguna mengisi email di login. Jika NIM/NIP, kosongkan field.
        $prefillCandidate = $_SESSION['forgot_password_prefill'] ?? '';
        unset($_SESSION['forgot_password_prefill']);

        $prefillIdentity = '';
        if (!empty($prefillCandidate) && filter_var($prefillCandidate, FILTER_VALIDATE_EMAIL)) {
            $prefillIdentity = $prefillCandidate;
        }

        // Ambil error inline dan old input dari percobaan submit sebelumnya jika ada
        $inlineError = $_SESSION['forgot_password_inline_error'] ?? null;
        unset($_SESSION['forgot_password_inline_error']);

        $oldInput = $_SESSION['forgot_password_old'] ?? null;
        unset($_SESSION['forgot_password_old']);

        if ($oldInput !== null) {
            $prefillIdentity = $oldInput;
        }

        $this->render('auth/forgot_password', [
            'pageTitle'       => 'Lupa Password? — Campus Find',
            'metaDescription' => 'Masukkan email terdaftar untuk menerima kode verifikasi OTP.',
            'csrfToken'       => CsrfMiddleware::getToken(),
            'prefillIdentity' => $prefillIdentity,
            'inlineError'     => $inlineError,
            'hideCFind'       => true,
        ]);
    }

    /**
     * POST /forgot-password
     * Kirim OTP reset password HANYA untuk akun aktif dengan email terverifikasi (TASK 02C & 02D)
     * Hanya menerima email (NIM/NIP ditolak dengan pesan validasi inline)
     * Respons SELALU generik anti-enumerasi
     */
    public function sendForgotPasswordOtp(array $params = []): void
    {
        CsrfMiddleware::validate();

        $identity = trim((string)($_POST['identity'] ?? $_POST['email'] ?? ''));
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (empty($identity)) {
            $_SESSION['forgot_password_inline_error'] = 'Silakan masukkan email terdaftar Anda.';
            $_SESSION['forgot_password_old'] = '';
            $this->setFlash('error', 'Silakan masukkan email terdaftar Anda.');
            $this->redirect('/forgot-password');
            return;
        }

        // TASK 02D: Halaman Forgot Password HANYA menerima alamat email.
        // Validasi format email. Jangan menerima NIM/NIP sebagai input pemulihan.
        if (!filter_var($identity, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['forgot_password_inline_error'] = 'Gunakan alamat email terdaftar, bukan NIM/NIP.';
            $_SESSION['forgot_password_old'] = $identity;
            $this->setFlash('error', 'Gunakan alamat email terdaftar, bukan NIM/NIP.');
            $this->redirect('/forgot-password');
            return;
        }

        $genericMessage = 'Jika identitas atau email yang Anda masukkan terdaftar dan memiliki email terverifikasi dalam sistem, kode verifikasi OTP 6 digit telah dikirimkan ke alamat email kampus Anda. Jika belum terverifikasi, silakan hubungi Administrator. Kode berlaku selama 5 menit.';

        // Cari pengguna HANYA berdasarkan email terdaftar (TASK 02D)
        $user = $this->userModel->findByEmail($identity);

        // Kriteria Kelayakan Ketat (TASK 02C Part F & 02D):
        // 1. Pengguna ada di database
        // 2. Akun aktif (is_active = 1) dan tidak dinonaktifkan/suspended
        // 3. Email terdaftar tidak kosong dan format valid
        // 4. Email terbukti terverifikasi (email_verified_at terisi ATAU email_verified_by_otp = 1)
        $isUserActive = $user && empty($user['deleted_at']) && (int)($user['is_active'] ?? 0) === 1 && (!isset($user['status']) || $user['status'] === 'active');
        $hasEmail = $user && !empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL);
        $isEmailVerified = $hasEmail && (!empty($user['email_verified_at']) || !empty($user['email_verified_by_otp']));

        $isEligible = $isUserActive && $hasEmail && $isEmailVerified;

        if ($isEligible) {
            $res = \App\Services\OtpService::generate((int)$user['id'], 'password_reset', $ip);
            if (!empty($res['cooldown'])) {
                $this->setFlash('warning', $res['message']);
                $_SESSION['otp_auth'] = [
                    'user_id'   => (int)$user['id'],
                    'purpose'   => 'password_reset',
                    'identity'  => $identity,
                    'masked'    => $res['email_masked'] ?? null,
                ];
                $this->redirect('/forgot-password/verify');
                return;
            }

            if (empty($res['success'])) {
                $_SESSION['forgot_password_inline_error'] = $res['message'] ?? 'Pengiriman kode verifikasi gagal. Layanan email tidak dapat dihubungi. Silakan coba beberapa saat lagi.';
                $_SESSION['forgot_password_old'] = $identity;
                $this->setFlash('error', $res['message'] ?? 'Pengiriman kode verifikasi gagal. Layanan email tidak dapat dihubungi. Silakan coba beberapa saat lagi.');
                $this->redirect('/forgot-password');
                return;
            }

            $_SESSION['otp_auth'] = [
                'user_id'   => (int)$user['id'],
                'purpose'   => 'password_reset',
                'identity'  => $identity,
                'masked'    => $res['email_masked'] ?? null,
            ];
        } else {
            // Anti-enumerasi: Jika user tidak ditemukan, akun inaktif, email kosong, atau belum terverifikasi
            $skipReason = 'user_not_found';
            if ($user) {
                if (!empty($user['deleted_at'])) {
                    $skipReason = 'user_deleted';
                } elseif ((int)($user['is_active'] ?? 0) !== 1 || (isset($user['status']) && $user['status'] !== 'active')) {
                    $skipReason = 'user_inactive_or_disabled';
                } elseif (!$hasEmail) {
                    $skipReason = 'email_empty';
                } elseif (!$isEmailVerified) {
                    $skipReason = 'email_unverified';
                }
            }

            AuditLogService::log('PASSWORD_RESET_REQUEST_SKIPPED', $user ? (int)$user['id'] : null, 'users', $user ? (int)$user['id'] : null, [
                'identity' => $identity,
                'reason'   => $skipReason,
            ]);

            // Dummy session agar alur antarmuka identik tanpa membocorkan eksistensi/status akun
            $_SESSION['otp_auth'] = [
                'user_id'   => 0,
                'purpose'   => 'password_reset',
                'identity'  => $identity,
                'masked'    => null,
            ];
        }

        $this->setFlash('info', $genericMessage);
        $this->redirect('/forgot-password/verify');
    }

    /**
     * GET /forgot-password/verify
     * Formulir masukkan OTP 6 digit dan kata sandi baru
     */
    public function forgotPasswordVerifyForm(array $params = []): void
    {
        if (empty($_SESSION['otp_auth']) || ($_SESSION['otp_auth']['purpose'] ?? '') !== 'password_reset') {
            $this->redirect('/forgot-password');
            return;
        }

        $this->render('auth/forgot_password_verify', [
            'pageTitle'       => 'Verifikasi OTP Reset Kata Sandi — Campus Find',
            'metaDescription' => 'Masukkan kode OTP 6 digit dan buat kata sandi baru Anda.',
            'csrfToken'       => CsrfMiddleware::getToken(),
            'identity'        => $_SESSION['otp_auth']['identity'] ?? '',
            'maskedEmail'     => $_SESSION['otp_auth']['masked'] ?? null,
            'hideCFind'       => true,
        ]);
    }

    /**
     * POST /forgot-password/verify
     * Verifikasi kode OTP dan simpan kata sandi baru
     */
    public function processForgotPassword(array $params = []): void
    {
        CsrfMiddleware::validate();

        $otpAuth = $_SESSION['otp_auth'] ?? null;
        if (empty($otpAuth) || ($otpAuth['purpose'] ?? '') !== 'password_reset') {
            $this->setFlash('error', 'Sesi reset kata sandi telah kedaluwarsa. Silakan mulai kembali.');
            $this->redirect('/forgot-password');
            return;
        }

        $userId          = (int)($otpAuth['user_id'] ?? 0);
        $otpCode         = trim((string)($_POST['otp_code'] ?? ''));
        $password        = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');

        if (empty($otpCode) || empty($password)) {
            $this->setFlash('error', 'Silakan masukkan kode OTP dan kata sandi baru.');
            $this->redirect('/forgot-password/verify');
            return;
        }

        if ($password !== $passwordConfirm) {
            $this->setFlash('error', 'Konfirmasi kata sandi tidak cocok.');
            $this->redirect('/forgot-password/verify');
            return;
        }

        $strength = \App\Services\OtpService::validatePasswordStrength($password);
        if (!$strength['valid']) {
            $this->setFlash('error', $strength['message']);
            $this->redirect('/forgot-password/verify');
            return;
        }

        if ($userId <= 0) {
            $this->setFlash('error', 'Kode verifikasi tidak valid atau telah kedaluwarsa.');
            $this->redirect('/forgot-password/verify');
            return;
        }

        $verifyRes = \App\Services\OtpService::verify($userId, 'password_reset', $otpCode);
        if (!$verifyRes['success']) {
            $this->setFlash('error', $verifyRes['error'] ?? 'Kode verifikasi tidak valid.');
            $this->redirect('/forgot-password/verify');
            return;
        }

        $pdo = \Core\Database::getInstance()->getPdo();
        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE users SET 
                    password_hash = ?,
                    must_change_password = 0,
                    temp_password_expires_at = NULL,
                    session_version = COALESCE(session_version, 1) + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hash, $userId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[processForgotPassword] DB error: ' . $e->getMessage());
            $this->setFlash('error', 'Terjadi kesalahan sistem saat memperbarui kata sandi. Silakan coba lagi.');
            $this->redirect('/forgot-password/verify');
            return;
        }

        AuditLogService::log('PASSWORD_RESET', $userId, 'users', $userId, []);

        unset($_SESSION['otp_auth']);
        $this->setFlash('success', 'Kata sandi Anda berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.');
        $this->redirect('/login');
    }

    /**
     * POST /auth/resend-otp
     * Kirim ulang kode OTP (dengan jeda 60 detik)
     */
    public function resendOtp(array $params = []): void
    {
        CsrfMiddleware::validate();

        $otpAuth = $_SESSION['otp_auth'] ?? null;
        if (empty($otpAuth)) {
            $this->json(['success' => false, 'message' => 'Sesi verifikasi tidak ditemukan.'], 400);
            return;
        }

        $userId  = (int)($otpAuth['user_id'] ?? 0);
        $purpose = (string)($otpAuth['purpose'] ?? 'activation');
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if ($userId <= 0) {
            // Anti-enumerasi: beri pesan sukses pura-pura
            $this->json([
                'success' => true,
                'message' => 'Kode verifikasi baru telah dikirimkan ke email Anda.',
            ]);
            return;
        }

        $res = \App\Services\OtpService::generate($userId, $purpose, $ip);

        if (!empty($res['cooldown'])) {
            $this->json([
                'success'           => false,
                'cooldown'          => true,
                'remaining_seconds' => $res['remaining_seconds'] ?? 60,
                'message'           => $res['message'],
            ], 429);
            return;
        }

        if (empty($res['success'])) {
            $this->json([
                'success' => false,
                'message' => $res['message'] ?? 'Pengiriman kode verifikasi gagal. Silakan coba beberapa saat lagi.',
            ], 500);
            return;
        }

        $this->json([
            'success' => true,
            'message' => $res['message'] ?? 'Kode verifikasi baru telah dikirimkan ke email Anda.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GANTI KATA SANDI WAJIB (MANDATORY TEMPORARY PASSWORD)
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /change-password-mandatory
     * Formulir ganti kata sandi wajib bagi pengguna yang diberi password sementara
     */
    public function mandatoryChangePasswordForm(array $params = []): void
    {
        if (!AuthMiddleware::isLoggedIn()) {
            $this->redirect('/login');
            return;
        }

        $this->render('auth/mandatory_change_password', [
            'pageTitle'       => 'Buat Kata Sandi Baru Anda — Campus Find',
            'metaDescription' => 'Wajib membuat kata sandi baru sebelum dapat menggunakan sistem Campus Find.',
            'csrfToken'       => CsrfMiddleware::getToken(),
            'user'            => AuthMiddleware::user(),
            'hideCFind'       => true,
        ]);
    }

    /**
     * POST /change-password-mandatory
     * Proses penggantian kata sandi wajib
     */
    public function processMandatoryChangePassword(array $params = []): void
    {
        CsrfMiddleware::validate();

        if (!AuthMiddleware::isLoggedIn()) {
            $this->redirect('/login');
            return;
        }

        $currentUser = AuthMiddleware::user();
        $userId = (int)($currentUser['id'] ?? 0);

        $password        = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');

        if (empty($password)) {
            $this->setFlash('error', 'Silakan masukkan kata sandi baru Anda.');
            $this->redirect('/change-password-mandatory');
            return;
        }

        if ($password !== $passwordConfirm) {
            $this->setFlash('error', 'Konfirmasi kata sandi tidak cocok.');
            $this->redirect('/change-password-mandatory');
            return;
        }

        $strength = \App\Services\OtpService::validatePasswordStrength($password);
        if (!$strength['valid']) {
            $this->setFlash('error', $strength['message']);
            $this->redirect('/change-password-mandatory');
            return;
        }

        $pdo = \Core\Database::getInstance()->getPdo();
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            UPDATE users SET 
                password_hash = ?,
                session_version = session_version + 1,
                must_change_password = 0,
                temp_password_expires_at = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$hash, $userId]);

        $newVer = (int) $pdo->query("SELECT session_version FROM users WHERE id = {$userId}")->fetchColumn();
        if (isset($_SESSION['auth_user'])) {
            $_SESSION['auth_user']['session_version'] = $newVer;
        }
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        unset($_SESSION['must_change_password']);

        AuditLogService::log('PASSWORD_CHANGED_MANDATORY', $userId, 'users', $userId, []);

        $this->setFlash('success', 'Kata sandi pribadi Anda berhasil disimpan! Selamat datang di Campus Find.');
        $this->redirect('/dashboard');
    }
}
