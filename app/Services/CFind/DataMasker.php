<?php

declare(strict_types=1);

namespace App\Services\CFind;

/**
 * DataMasker — Helper keamanan untuk penyamaran data sensitif (PII)
 * Menjamin tidak ada password, secret, bukti klaim, atau identitas pribadi yang dikirim ke AI.
 */
class DataMasker
{
    /** Daftar field yang HARUS selalu dibuang dari data yang dikirim ke AI */
    public const DISALLOWED_FIELDS = [
        'password',
        'password_hash',
        'api_key',
        'secret',
        'token',
        'ownership_proof_description',
        'claim_reason',
        'unique_features',
        'private_details',
        'internal_notes',
        'reviewer_notes',
        'rejection_reason',
        'handover_notes',
        'phone',
        'identity_number',
        'payload', // pada audit_logs jika mengandung data mentah
    ];

    /**
     * Samarkan nama menjadi format anonim aman (misal "User #12" atau inisial)
     */
    public static function maskName(?string $name, ?int $userId = null): string
    {
        if ($userId !== null) {
            return "User #{$userId}";
        }

        if (empty($name)) {
            return 'Pengguna Kampus';
        }

        $parts = preg_split('/\s+/u', trim($name));
        if (empty($parts)) {
            return 'Pengguna';
        }

        $first = $parts[0];
        if (mb_strlen($first) <= 2) {
            return $first . '***';
        }

        return mb_substr($first, 0, 2) . str_repeat('*', max(2, mb_strlen($first) - 2));
    }

    /**
     * Samarkan email (misal "alivi***@univ.ac.id")
     */
    public static function maskEmail(?string $email): string
    {
        if (empty($email) || !str_contains($email, '@')) {
            return '***@***.***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $len = mb_strlen($local);

        if ($len <= 2) {
            $maskedLocal = $local . '***';
        } else {
            $maskedLocal = mb_substr($local, 0, 2) . str_repeat('*', min(6, $len - 2));
        }

        return $maskedLocal . '@' . $domain;
    }

    /**
     * Bersihkan satu baris data asosiatif dari field terlarang dan sanitasi string
     *
     * @param array<string, mixed> $row
     * @param array<string> $extraDisallowed
     * @return array<string, mixed>
     */
    public static function sanitizeRow(array $row, array $extraDisallowed = []): array
    {
        $disallowed = array_merge(self::DISALLOWED_FIELDS, $extraDisallowed);
        $clean = [];

        foreach ($row as $key => $val) {
            $lowerKey = strtolower((string)$key);
            if (in_array($lowerKey, $disallowed, true)) {
                continue;
            }

            if (is_string($val)) {
                // Potong string panjang agar tidak memakan token berlebihan
                $val = trim($val);
                if (mb_strlen($val) > 200) {
                    $val = mb_substr($val, 0, 197) . '...';
                }
            }

            $clean[$key] = $val;
        }

        return $clean;
    }

    /**
     * Bungkus teks dari input pengguna sebagai data tidak tepercaya (Untrusted Data Block)
     * Mencegah prompt injection yang disisipkan di dalam judul atau deskripsi barang.
     */
    public static function wrapUntrusted(string $label, string $text): string
    {
        // Netralkan karakter pengontrol instruksi prompt
        $sanitized = str_replace(["\r\n", "\r"], "\n", $text);
        $sanitized = preg_replace('/(\n{3,})/', "\n\n", $sanitized);
        $sanitized = strip_tags((string)$sanitized);

        return "{$label}: [DATA_PENGGUNA: \"{$sanitized}\"]";
    }
}
