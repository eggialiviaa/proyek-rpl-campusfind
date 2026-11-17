<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * AuthMiddleware — Middleware Autentikasi dan Role-Based Access Control (RBAC)
 *
 * Mendukung:
 * - Pemeriksaan status login
 * - Guard otorisasi per role (Admin, Petugas, Civitas)
 * - Pencegahan session fixation
 * - Redirection URL intended
 */
class AuthMiddleware
{
    private const SESSION_KEY = 'auth_user';
    private static ?array $cachedCurrentUser = null;

    /**
     * Bersihkan cache pengguna in-memory (berguna untuk testing atau pembaruan sesi)
     */
    public static function clearUserCache(): void
    {
        self::$cachedCurrentUser = null;
    }

    /**
     * Dapatkan durasi kedaluwarsa sesi (inactivity timeout) dalam detik dengan fallback defensif
     */
    public static function getSessionTimeout(): int
    {
        $raw = $_ENV['SESSION_LIFETIME'] ?? getenv('SESSION_LIFETIME');
        return (!empty($raw) && (int)$raw > 0) ? (int)$raw : 7200;
    }

    /**
     * Periksa apakah pengguna telah login
     */
    public static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        // Session expiration / inactivity timeout check
        $timeout = self::getSessionTimeout();
        if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $timeout)) {
            self::logout();
            $_SESSION['session_timed_out'] = true;
            return false;
        }
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Wajibkan user login — Tangani unauthenticated (Redirect ke /login atau respon 401 JSON)
     */
    public static function require(): void
    {
        if (!self::isLoggedIn()) {
            self::handleUnauthenticated($_SERVER['REQUEST_URI'] ?? '/dashboard');
            return;
        }

        // Prompt 20B: Pengguna yang diberi password sementara wajib menggantinya sebelum mengakses fitur lain
        if (!empty($_SESSION['must_change_password'])) {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
            if ($uri !== '/change-password-mandatory' && $uri !== '/logout') {
                if (defined('CAMPUS_FIND_TESTING') && CAMPUS_FIND_TESTING) {
                    $_SESSION['__test_redirect'] = '/change-password-mandatory';
                    throw new \RuntimeException("REDIRECT:/change-password-mandatory");
                }
                header('Location: /change-password-mandatory');
                exit;
            }
        }
    }

    /**
     * Alias untuk require() agar kompatibel dengan seluruh controller
     */
    public static function requireAuth(): void
    {
        self::require();
    }

    /**
     * Cek apakah flag PUBLIC_BROWSE_ENABLED aktif di environment
     */
    public static function isPublicBrowseEnabled(): bool
    {
        $val = $_ENV['PUBLIC_BROWSE_ENABLED'] ?? getenv('PUBLIC_BROWSE_ENABLED') ?? false;
        if (is_bool($val)) {
            return $val;
        }
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Evaluasi apakah suatu route dapat diakses oleh publik/tamu (Allowlist Route Publik)
     */
    public static function isPublicRoute(string $method, string $uri): bool
    {
        $method = strtoupper($method);
        // Bersihkan query string dan normalize trailing slash
        $path = strtok($uri, '?');
        if (is_string($path) && $path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        if ($path === '' || $path === false) {
            $path = '/';
        }

        // 1. Core Public Routes (Method & Path Exact Match, Tanpa Wildcard)
        // TASK 02C: /activate adalah halaman informasi publik. Rute aktivasi POST mandiri dinonaktifkan dari publik.
        $publicGet = [
            '/',
            '/login',
            '/forgot-password',
            '/forgot-password/verify',
            '/activate',
            '/cara-kerja',
            '/logout',
            '/api/cfind/status',
            '/api/cfind/history',
        ];

        $publicPost = [
            '/login',
            '/forgot-password',
            '/forgot-password/verify',
            '/auth/resend-otp',
            '/activate',
            '/activate/verify',
            '/logout',
            '/api/cfind/chat',
            '/api/cfind/reset',
        ];

        if ($method === 'GET' && in_array($path, $publicGet, true)) {
            return true;
        }
        if ($method === 'POST' && in_array($path, $publicPost, true)) {
            return true;
        }

        // 2. Mode PUBLIC_BROWSE_ENABLED (Jika aktif, tamu boleh melihat daftar & detail barang publik)
        if (self::isPublicBrowseEnabled() && $method === 'GET') {
            // Daftar barang hilang & temuan, serta pencarian publik
            if (in_array($path, ['/search', '/reports/lost', '/reports/found'], true)) {
                return true;
            }
            // Detail barang publik (hanya numerik ID)
            if (preg_match('#^/reports/lost/\d+$#', $path) || preg_match('#^/reports/found/\d+$#', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Periksa apakah request diizinkan untuk tamu / belum login
     */
    public static function isRouteAllowedForGuest(string $method, string $uri): bool
    {
        if (self::isLoggedIn()) {
            return true;
        }
        return self::isPublicRoute($method, $uri);
    }

    /**
     * Deteksi apakah request berasal dari endpoint API / AJAX / Polling
     */
    public static function isApiRequest(?string $uri = null): bool
    {
        $uri = $uri ?? ($_SERVER['REQUEST_URI'] ?? '');

        // Path berawalan /api/
        if (str_starts_with($uri, '/api/')) {
            return true;
        }

        // AJAX request (X-Requested-With: XMLHttpRequest)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        // Header Accept: application/json
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains(strtolower($accept), 'application/json')) {
            return true;
        }

        // Endpoint polling / status chat / notifikasi
        if (preg_match('#^/(chats/\d+/poll|chats/unread-count|notifications/unread-count|notifications/recent)#', $uri)) {
            return true;
        }

        return false;
    }

    /**
     * Tangani request yang belum login (Tamu membuka route terproteksi)
     */
    public static function handleUnauthenticated(string $uri = '/dashboard'): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        // Akses berkas unggahan oleh tamu ditolak dengan 403 Forbidden
        if (str_starts_with($uri, '/uploads/')) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            echo 'Forbidden: Login diperlukan untuk mengakses gambar atau berkas ini.';
            exit;
        }

        if (self::isApiRequest($uri)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'error'    => 'Unauthorized',
                'message'  => 'Silakan login terlebih dahulu untuk mengakses layanan ini.',
                'redirect' => '/login',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        self::setIntendedUrl($uri);

        // Bedakan secara benar:
        // 1. Session yang benar-benar aktif sebelumnya dan kedaluwarsa karena timeout
        if (!empty($_SESSION['session_timed_out'])) {
            unset($_SESSION['session_timed_out']);
            $_SESSION['_flash']['warning'] = 'Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan masuk kembali.';
        } else {
            // 2. Pengunjung tamu / belum login mencoba membuka route terproteksi
            $_SESSION['_flash']['info'] = 'Silakan masuk terlebih dahulu untuk mengakses layanan ini.';
        }

        if (defined('CAMPUS_FIND_TESTING') && CAMPUS_FIND_TESTING) {
            $_SESSION['__test_redirect'] = '/login';
            throw new \RuntimeException("REDIRECT:/login");
        }

        header('Location: /login');
        exit;
    }


    /**
     * Wajibkan role tertentu (RBAC Guard)
     *
     * @param string|array<string> $roles Role yang diizinkan (misal: 'admin', 'petugas', ['admin', 'petugas'])
     */
    public static function requireRole(string|array $roles): void
    {
        // 1. Wajibkan login terlebih dahulu
        self::require();

        $user = self::user();
        $userRole = $user['role_name'] ?? $user['role'] ?? '';
        $allowed = (array) $roles;

        // 2. Verifikasi kesesuaian role
        if (!in_array($userRole, $allowed, true)) {
            if (defined('CAMPUS_FIND_TESTING') && CAMPUS_FIND_TESTING) {
                $_SESSION['__test_forbidden'] = true;
                throw new \RuntimeException("FORBIDDEN:{$userRole}");
            }

            http_response_code(403);

            if (self::isApiRequest($_SERVER['REQUEST_URI'] ?? '')) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'ok'      => false,
                    'success' => false,
                    'sent'    => 0,
                    'failed'  => 0,
                    'blocked' => 0,
                    'skipped' => 0,
                    'error'   => 'Forbidden',
                    'message' => 'Akses ditolak. Anda tidak memiliki izin untuk tindakan ini.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $requiredRole = implode(', ', $allowed);
            $currentRole  = $userRole ?: 'Guest';
            $pageTitle    = '403 — Akses Ditolak | Campus Find';

            $errorView = BASE_PATH . '/app/Views/errors/403.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo "<h1>403 Forbidden</h1><p>Akses ditolak. Dibutuhkan role: {$requiredRole}. Role Anda saat ini: {$currentRole}.</p>";
            }
            exit;
        }
    }

    /**
     * Ambil data pengguna login saat ini (selalu diselaraskan dari basis data)
     *
     * @param bool $forceFresh Paksa query ulang ke database mengabaikan cache per-request
     * @return array<string, mixed>|null
     */
    public static function user(bool $forceFresh = false): ?array
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $sessionUser = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($sessionUser) || empty($sessionUser['id'])) {
            self::$cachedCurrentUser = null;
            return null;
        }

        $userId = (int) $sessionUser['id'];

        // Jika cache per-request sudah ada dan tidak dipaksa fresh, kembalikan cache
        if (!$forceFresh && self::$cachedCurrentUser !== null && (int)(self::$cachedCurrentUser['id'] ?? 0) === $userId) {
            return self::$cachedCurrentUser;
        }

        // Query data segar dari basis data (single source of truth)
        try {
            $pdo = \Core\Database::getInstance()->getPdo();
            $stmt = $pdo->prepare("
                SELECT u.id, u.role_id, u.name, u.email, u.phone, u.identity_number, 
                       u.study_program, u.batch_year, u.avatar, u.is_active, 
                       u.must_change_password, u.session_version, u.email_verified_at, 
                       u.email_notifications_enabled, u.email_consent_at,
                       u.created_at, u.updated_at,
                       r.name AS role_name, r.name AS role, r.label AS role_label
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $freshUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($freshUser && (int)$freshUser['is_active'] === 1) {
                // Prompt 35B: Invalidate other sessions bila session_version di database telah ditingkatkan (mis. ganti password di sesi lain)
                $dbSessionVer = (int)($freshUser['session_version'] ?? 1);
                $currentSessionVer = (int)($sessionUser['session_version'] ?? 1);
                if (isset($sessionUser['session_version']) && $dbSessionVer !== $currentSessionVer) {
                    self::logout();
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        $_SESSION['_flash']['warning'] = 'Sesi Anda telah berakhir karena kata sandi akun telah diubah dari perangkat lain. Silakan masuk kembali.';
                    }
                    return null;
                }
                // Pertahankan flag sesi khusus jika ada (misal demo user)
                if (isset($sessionUser['is_demo'])) {
                    $freshUser['is_demo'] = $sessionUser['is_demo'];
                }

                // Sinkronkan ke $_SESSION dan cache in-memory
                $_SESSION[self::SESSION_KEY] = $freshUser;
                self::$cachedCurrentUser    = $freshUser;

                if (!empty($freshUser['must_change_password'])) {
                    $_SESSION['must_change_password'] = true;
                } else {
                    unset($_SESSION['must_change_password']);
                }

                return $freshUser;
            } elseif ($freshUser && (int)$freshUser['is_active'] === 0) {
                // Pengguna telah dinonaktifkan oleh Admin
                self::logout();
                return null;
            }
        } catch (\Throwable $e) {
            error_log('[AuthMiddleware::user] Gagal merefresh data pengguna dari DB: ' . $e->getMessage());
        }

        // Fallback ke data sesi jika koneksi DB bermasalah
        self::$cachedCurrentUser = $sessionUser;
        return $sessionUser;
    }

    /**
     * Segarkan data sesi pengguna dari basis data secara manual
     *
     * @param int|null $userId Jika diberikan, hanya refresh jika user yang sedang login memiliki ID tersebut
     */
    public static function refreshUser(?int $userId = null): ?array
    {
        self::clearUserCache();
        if ($userId === null) {
            return self::user(true);
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $sessionUser = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_array($sessionUser) && (int)($sessionUser['id'] ?? 0) === $userId) {
            return self::user(true);
        }

        return null;
    }

    /**
     * Ambil nama role pengguna saat ini
     */
    public static function role(): ?string
    {
        $user = self::user();
        return $user['role_name'] ?? $user['role'] ?? null;
    }

    /**
     * Periksa apakah pengguna memiliki salah satu role yang ditentukan
     */
    public static function hasRole(string|array $roles): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }
        $currentRole = self::role();
        $allowed = (array) $roles;
        return in_array($currentRole, $allowed, true);
    }

    /**
     * Daftarkan session login user dengan perlindungan Session Fixation
     *
     * @param array<string, mixed> $userData
     */
    public static function login(array $userData): void
    {
        self::$cachedCurrentUser = null;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        // Regenerate session ID untuk keamanan
        if (!headers_sent()) {
            @session_regenerate_id(true);
        }

        // Jangan pernah simpan hash password di session
        unset($userData['password_hash']);

        $_SESSION[self::SESSION_KEY] = $userData;
        $_SESSION['last_activity']  = time();
        $_SESSION['_sidebar_fresh_login'] = true;

        // Reset riwayat chat C-Find sesi lama agar tidak bocor ke akun baru
        unset($_SESSION['cfind_history'], $_SESSION['cfind_history_owner']);
    }

    /**
     * Logout pengguna dan hapus data session auth
     */
    public static function logout(): void
    {
        self::$cachedCurrentUser = null;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        unset($_SESSION[self::SESSION_KEY]);
        unset($_SESSION['last_activity']);
        unset($_SESSION['_intended']);
        unset($_SESSION['session_timed_out']);
        unset($_SESSION['_sidebar_fresh_login']);
        unset($_SESSION['cfind_history'], $_SESSION['cfind_history_owner']);
        unset($_SESSION['cfind_display_history'], $_SESSION['cfind_model_context']);
        unset($_SESSION['cfind_last_plan'], $_SESSION['cfind_last_result'], $_SESSION['cfind_last_query'], $_SESSION['cfind_last_context']);

        // Regenerate ID setelah logout
        if (!headers_sent()) {
            @session_regenerate_id(true);
        }
    }


    /**
     * Validasi URL tujuan agar aman dari serangan Open Redirect
     * Hanya menerima internal path aplikasi (diawali '/' tunggal, bukan protocol-relative '//')
     */
    public static function sanitizeIntendedUrl(?string $url, string $default = '/dashboard'): string
    {
        if ($url === null || $url === '') {
            return $default;
        }

        // Hapus karakter kontrol (CRLF, null bytes, tab)
        $url = trim(str_replace(["\r", "\n", "\0", "\t"], '', $url));

        // Tolak URL dengan skema eksternal (http:, https:, javascript:, data:, etc.)
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $url)) {
            return $default;
        }

        // Pastikan diawali dengan satu '/' dan bukan '//' atau '/\' atau '\\'
        if (!str_starts_with($url, '/') || str_starts_with($url, '//') || str_starts_with($url, '/\\') || str_starts_with($url, '\\')) {
            return $default;
        }

        // Parse path untuk memastikan path internal yang valid
        $parsedPath = parse_url($url, PHP_URL_PATH);
        if (!is_string($parsedPath) || !str_starts_with($parsedPath, '/')) {
            return $default;
        }

        // Hindari loop redirect ke halaman login atau logout
        if (in_array(rtrim($parsedPath, '/'), ['/login', '/logout'], true)) {
            return $default;
        }

        return $url;
    }

    /**
     * Simpan intended URL setelah divalidasi ke session
     */
    public static function setIntendedUrl(string $url): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $clean = self::sanitizeIntendedUrl($url, '');
        if ($clean !== '') {
            $_SESSION['_intended'] = $clean;
        }
    }

    /**
     * Ambil intended URL setelah login, fallback ke default (aman dari open redirect)
     */
    public static function intended(string $default = '/dashboard'): string
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $url = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);

        return self::sanitizeIntendedUrl($url, $default);
    }
}
