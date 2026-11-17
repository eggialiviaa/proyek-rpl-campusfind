<?php

declare(strict_types=1);

namespace Core;

use App\Middleware\CsrfMiddleware;

/**
 * BaseController — Abstract base untuk semua Controller
 * Menyediakan render view, redirect, flash message, dan response helpers
 */
abstract class BaseController
{
    /**
     * Render view dengan base layout
     *
     * @param string               $view  Path view relatif dari app/Views/ (misal 'home/index')
     * @param array<string, mixed> $data  Data yang di-extract ke view
     * @param string               $layout Layout yang digunakan (default: 'main')
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        // Extract data agar tersedia sebagai variabel di view
        extract($data, EXTR_SKIP);

        // Definisi path
        $viewFile   = BASE_PATH . '/app/Views/' . $view . '.php';
        $layoutFile = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';

        if (!file_exists($viewFile)) {
            $this->abort(404);
            return;
        }

        // Buffer content dari view
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Render layout dengan content yang sudah di-buffer
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * Redirect ke URL tertentu
     */
    protected function redirect(string $url): never
    {
        if (defined('CAMPUS_FIND_TESTING') && CAMPUS_FIND_TESTING) {
            $_SESSION['__test_redirect'] = $url;
            throw new \RuntimeException("REDIRECT:" . $url);
        }
        header('Location: ' . $url);
        exit;
    }

    /**
     * Redirect kembali ke halaman asal (return_to / referer / default) dengan validasi path internal
     * Mempertahankan parameter filter, pencarian, dan nomor halaman (Prompt 34A)
     */
    protected function redirectBack(string $default = '/dashboard'): never
    {
        $target = $_POST['return_to'] ?? $_GET['return_to'] ?? null;
        if (empty($target) && !empty($_SERVER['HTTP_REFERER'])) {
            $referer = (string)$_SERVER['HTTP_REFERER'];
            $refPath = parse_url($referer, PHP_URL_PATH);
            $refQuery = parse_url($referer, PHP_URL_QUERY);
            if (!empty($refPath) && str_starts_with($refPath, '/')) {
                $target = $refPath . ($refQuery ? ('?' . $refQuery) : '');
            }
        }

        $safeUrl = \App\Middleware\AuthMiddleware::sanitizeIntendedUrl($target, $default);
        $this->redirect($safeUrl);
    }

    /**
     * Kirim JSON response
     *
     * @param mixed $data
     */
    protected function json(mixed $data, int $status = 200): never
    {
        if (defined('CAMPUS_FIND_TESTING') && CAMPUS_FIND_TESTING) {
            $_SESSION['__test_json'] = ['data' => $data, 'status' => $status];
            throw new \RuntimeException("JSON:" . json_encode($data));
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Set flash message ke session.
     * Menjamin pesan selalu bertipe string skalar.
     *
     * @param string $type    Tipe: 'success' | 'error' | 'warning' | 'info'
     * @param mixed  $message Pesan yang ditampilkan (jika array, otomatis digabung)
     */
    protected function setFlash(string $type, mixed $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $normalizedType = ($type === 'danger') ? 'error' : $type;
        if (is_array($message)) {
            $message = implode(' ', array_filter($message, 'is_scalar'));
        }
        $_SESSION['_flash'][$normalizedType] = (string)$message;
    }

    /**
     * Alias untuk setFlash()
     */
    protected function flash(string $type, mixed $message): void
    {
        $this->setFlash($type, $message);
    }

    /**
     * Ambil dan hapus flash message dari session.
     * Jika $type diberikan, kembalikan pesan bertipe string untuk tipe tersebut (atau null) dan hapus tipe itu.
     * Jika $type null, kembalikan seluruh array flash dan bersihkan session flash.
     *
     * @param string|null $type Tipe: 'success' | 'error' | 'warning' | 'info' | null
     * @return array<string, string>|string|null
     */
    protected function getFlash(?string $type = null): array|string|null
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $flash = $_SESSION['_flash'] ?? [];

        if ($type !== null) {
            $normalizedType = ($type === 'danger') ? 'error' : $type;
            $msg = $flash[$normalizedType] ?? null;
            if (is_array($msg)) {
                $msg = implode(' ', array_filter($msg, 'is_scalar'));
            }
            unset($_SESSION['_flash'][$normalizedType]);
            return $msg !== null ? (string)$msg : null;
        }

        unset($_SESSION['_flash']);
        return is_array($flash) ? $flash : [];
    }

    /**
     * Abort dengan HTTP status code (404, 403, dsb.)
     */
    protected function abort(int $code = 404): never
    {
        if (!headers_sent()) {
            http_response_code($code);
        }
        if (defined('CAMPUS_FIND_TESTING') && CAMPUS_FIND_TESTING) {
            throw new \RuntimeException("ABORT:" . $code);
        }
        $errorView = BASE_PATH . '/app/Views/errors/' . $code . '.php';
        if (file_exists($errorView)) {
            require $errorView;
        } else {
            echo "<h1>Error {$code}</h1>";
        }
        exit;
    }

    /**
     * Ambil CSRF token (shortcut ke CsrfMiddleware)
     */
    protected function csrfToken(): string
    {
        return CsrfMiddleware::getToken();
    }

    /**
     * Ambil input dari POST/GET, dengan sanitasi dasar
     */
    protected function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Ambil raw input POST (untuk password — jangan di-escape)
     */
    protected function rawInput(string $key, string $default = ''): string
    {
        return trim((string) ($_POST[$key] ?? $default));
    }

    /**
     * Cek apakah request saat ini adalah AJAX atau mengharapkan JSON
     */
    protected function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json'))
            || (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/'));
    }
}

