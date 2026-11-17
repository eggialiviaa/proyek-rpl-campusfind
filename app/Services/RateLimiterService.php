<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PDO;

/**
 * RateLimiterService — Pengelolaan proteksi brute-force & login lockout
 *
 * Aturan:
 * - Maksimal 5 percobaan gagal berturut-turut
 * - Lockout selama 300 detik (5 menit)
 * - Reset otomatis jika login berhasil
 */
class RateLimiterService
{
    private const MAX_ATTEMPTS   = 5;
    private const LOCKOUT_SECONDS = 300; // 5 menit

    /**
     * Periksa apakah IP / Email saat ini sedang dalam masa lockout
     *
     * @return array{is_locked: bool, remaining_seconds: int, attempts: int}
     */
    public static function checkLockout(string $ip, string $email): array
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('
            SELECT attempts, last_attempt_at, locked_until 
            FROM login_attempts 
            WHERE ip_address = ? AND email = ?
            LIMIT 1
        ');
        $stmt->execute([$ip, strtolower(trim($email))]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['is_locked' => false, 'remaining_seconds' => 0, 'attempts' => 0];
        }

        $now = time();
        if (!empty($record['locked_until'])) {
            $lockedUntil = strtotime($record['locked_until']);
            if ($lockedUntil > $now) {
                // Masih terkunci
                return [
                    'is_locked'         => true,
                    'remaining_seconds' => $lockedUntil - $now,
                    'attempts'          => (int) $record['attempts'],
                ];
            } else {
                // Masa lockout sudah lewat -> reset record
                self::reset($ip, $email);
                return ['is_locked' => false, 'remaining_seconds' => 0, 'attempts' => 0];
            }
        }

        return [
            'is_locked'         => false,
            'remaining_seconds' => 0,
            'attempts'          => (int) $record['attempts'],
        ];
    }

    /**
     * Catat percobaan login gagal
     *
     * @return array{attempts: int, is_locked: bool, remaining_seconds: int, remaining_attempts: int}
     */
    public static function recordFailure(string $ip, string $email): array
    {
        $pdo = Database::getInstance()->getPdo();
        $email = strtolower(trim($email));

        $stmt = $pdo->prepare('
            SELECT id, attempts, locked_until 
            FROM login_attempts 
            WHERE ip_address = ? AND email = ?
            LIMIT 1
        ');
        $stmt->execute([$ip, $email]);
        $record = $stmt->fetch();

        $now = date('Y-m-d H:i:s');

        if ($record) {
            $newAttempts = (int) $record['attempts'] + 1;
            $lockedUntil = null;

            if ($newAttempts >= self::MAX_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_SECONDS);
            }

            $update = $pdo->prepare('
                UPDATE login_attempts 
                SET attempts = ?, last_attempt_at = ?, locked_until = ?
                WHERE id = ?
            ');
            $update->execute([$newAttempts, $now, $lockedUntil, $record['id']]);
        } else {
            $newAttempts = 1;
            $lockedUntil = null;
            $insert = $pdo->prepare('
                INSERT INTO login_attempts (ip_address, email, attempts, last_attempt_at, locked_until)
                VALUES (?, ?, ?, ?, ?)
            ');
            $insert->execute([$ip, $email, $newAttempts, $now, $lockedUntil]);
        }

        $isLocked = $newAttempts >= self::MAX_ATTEMPTS;
        $remainingAttempts = max(0, self::MAX_ATTEMPTS - $newAttempts);

        return [
            'attempts'           => $newAttempts,
            'is_locked'          => $isLocked,
            'remaining_seconds'  => $isLocked ? self::LOCKOUT_SECONDS : 0,
            'remaining_attempts' => $remainingAttempts,
        ];
    }

    /**
     * Reset / bersihkan percobaan login setelah login berhasil
     */
    public static function reset(string $ip, string $email): void
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ? AND email = ?');
        $stmt->execute([$ip, strtolower(trim($email))]);
    }
}
