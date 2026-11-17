<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * CsrfMiddleware — Perlindungan CSRF berbasis token session
 *
 * Penggunaan di View (form):
 *   <input type="hidden" name="_csrf_token" value="<?= CsrfMiddleware::getToken() ?>">
 *
 * Penggunaan di Controller (validasi POST):
 *   CsrfMiddleware::validate();
 */
class CsrfMiddleware
{
    private const TOKEN_KEY  = '_csrf_token';
    private const TOKEN_BITS = 32; // bytes → 64 hex chars

    /**
     * Generate token baru dan simpan ke session.
     * Jika token sudah ada, gunakan yang lama (tidak regenerate setiap request).
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(self::TOKEN_BITS));
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Ambil token dari session (generate jika belum ada)
     */
    public static function getToken(): string
    {
        return self::generateToken();
    }

    /**
     * Render hidden input element untuk CSRF token di form HTML
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    /**
     * Validasi token dari request POST
     * Jika invalid → kirim response 403 dan hentikan eksekusi
     */
    public static function validate(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? '';
        $requestToken = $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? '';

        // Gunakan hash_equals untuk mencegah timing attack
        if (empty($sessionToken) || !hash_equals($sessionToken, $requestToken)) {
            http_response_code(403);

            if (defined('CAMPUS_FIND_TESTING') && CAMPUS_FIND_TESTING) {
                $_SESSION['__test_csrf_blocked'] = true;
                throw new \RuntimeException("CSRF_BLOCKED:403");
            }

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (isset($_SERVER['HTTP_ACCEPT']) && str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json'))
                || (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/'));

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok'      => false,
                    'success' => false,
                    'sent'    => 0,
                    'failed'  => 0,
                    'blocked' => 0,
                    'skipped' => 0,
                    'error'   => 'Invalid CSRF Token',
                    'message' => 'Sesi Anda telah kedaluwarsa atau token CSRF tidak valid. Silakan muat ulang halaman.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $errorView = BASE_PATH . '/app/Views/errors/403.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo '<h1>403 — Akses ditolak (invalid CSRF token)</h1>';
            }
            exit;
        }
    }

    /**
     * Validasi token dari request, kembalikan boolean (tanpa exit)
     */
    public static function isValid(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? '';
        $requestToken = $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? '';

        return !empty($sessionToken) && hash_equals($sessionToken, $requestToken);
    }

    /**
     * Regenerate token (gunakan setelah login/logout untuk rotasi token)
     */
    public static function regenerate(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(self::TOKEN_BITS));
        return $_SESSION[self::TOKEN_KEY];
    }
}
