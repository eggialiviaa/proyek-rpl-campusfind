<?php

declare(strict_types=1);

namespace App\Services;

/**
 * MessageCatalog — Katalog Pesan Pengguna Terpusat (Bahasa Indonesia)
 *
 * Menghasilkan pesan yang jelas, spesifik, dan memuat 3 elemen utama:
 * 1. Apa yang terjadi (tindakan berhasil dieksekusi)
 * 2. Pada siapa atau objek apa tindakan tersebut diterapkan
 * 3. Apa langkah selanjutnya atau dampak yang harus diperhatikan
 *
 * Mencegah pesan generik ("Berhasil!", "Sukses!") agar pengalaman pengguna seragam dan informatif.
 */
class MessageCatalog
{
    /**
     * Pesan pembaruan data pengguna oleh Admin
     */
    public static function userUpdated(string $name, bool $emailChanged, ?string $maskedEmail = null, array $changedFields = []): string
    {
        $fieldMap = [
            'name'            => 'nama',
            'email'           => 'email',
            'identity_number' => 'NIM',
            'phone'           => 'No WhatsApp',
            'study_program'   => 'program studi',
            'batch_year'      => 'angkatan',
        ];

        $labels = [];
        foreach ($changedFields as $f) {
            $labels[] = $fieldMap[$f] ?? $f;
        }

        $fieldsText = '';
        if (!empty($labels)) {
            if (count($labels) === 1) {
                $fieldsText = $labels[0];
            } else {
                $last = array_pop($labels);
                $fieldsText = implode(', ', $labels) . ' dan ' . $last;
            }
        }

        $prefix = $fieldsText !== '' 
            ? "Data akun {$name} diperbarui: {$fieldsText}." 
            : "Data akun {$name} berhasil diperbarui.";

        if ($emailChanged && $maskedEmail !== null) {
            return "{$prefix} Alamat email diubah menjadi {$maskedEmail} dan perlu diverifikasi ulang sebelum dapat menerima notifikasi.";
        }

        return "{$prefix} Perubahan telah langsung aktif di sistem.";
    }

    /**
     * Pesan pengiriman ulang kode OTP aktivasi
     */
    public static function activationResent(string $name, string $maskedEmail): string
    {
        return "Kode OTP aktivasi akun {$name} telah dikirimkan ke {$maskedEmail}. Kode berlaku selama 5 menit untuk menyelesaikan aktivasi.";
    }

    /**
     * Pesan pembuatan kata sandi sementara
     */
    public static function tempPasswordCreated(string $name, string $tempPassword): string
    {
        return "Kata sandi sementara untuk akun {$name} berhasil dibuat: {$tempPassword}. Pengguna diwajibkan mengganti kata sandi ini saat login berikutnya.";
    }

    /**
     * Pesan verifikasi email manual oleh Admin (Local dev)
     */
    public static function manualEmailVerified(string $name, string $maskedEmail): string
    {
        return "Alamat email {$maskedEmail} untuk akun {$name} berhasil ditandai sebagai terverifikasi. Akun kini siap menerima email notifikasi.";
    }

    /**
     * Pesan perubahan status pengguna
     */
    public static function userStatusChanged(string $name, string $status): string
    {
        $statusLabels = [
            'active'    => 'Aktif',
            'inactive'  => 'Nonaktif',
            'suspended' => 'Ditangguhkan',
        ];
        $label = $statusLabels[$status] ?? ucfirst($status);
        return "Status akun {$name} berhasil diubah menjadi {$label}. Hak akses pengguna telah disesuaikan di seluruh sistem.";
    }

    /**
     * Pesan selesai impor data civitas
     */
    public static function civitasImported(int $successCount, int $skippedCount = 0): string
    {
        if ($skippedCount > 0) {
            return "Impor data civitas selesai diproses. Sebanyak {$successCount} akun civitas baru berhasil disimpan ke basis data, sedangkan {$skippedCount} baris data dilewati.";
        }
        return "Impor data civitas selesai diproses. Seluruh {$successCount} akun civitas berhasil disimpan ke basis data dengan aman.";
    }

    /**
     * Pesan penyimpanan pengaturan notifikasi
     */
    public static function notificationSettingsSaved(): string
    {
        return "Pengaturan pengiriman notifikasi, kuota harian, allowlist, dan templat email berhasil diperbarui dan langsung diterapkan.";
    }

    /**
     * Pesan pengujian pengiriman email SMTP
     */
    public static function emailTestSent(string $maskedEmail): string
    {
        return "Email uji coba berhasil dikirimkan ke {$maskedEmail} melalui koneksi server SMTP. Silakan periksa kotak masuk atau spam.";
    }

    /**
     * Pesan reset circuit breaker
     */
    public static function circuitBreakerReset(): string
    {
        return "Circuit breaker pengiriman email berhasil disetel ulang ke kondisi Normal (tertutup). Antrean notifikasi siap memproses pengiriman kembali.";
    }

    /**
     * Pesan reset anggaran token C-Find
     */
    public static function cfindBudgetReset(): string
    {
        return "Penghitung penggunaan token AI Campus Find hari ini telah disetel ulang ke 0. Kuota harian kembali tersedia penuh.";
    }

    /**
     * Pesan verifikasi OTP email profil civitas
     */
    public static function profileEmailVerified(string $maskedEmail): string
    {
        return "Alamat email Anda ({$maskedEmail}) telah berhasil diverifikasi. Notifikasi laporan dan klaim akan otomatis dikirimkan ke email ini.";
    }

    /**
     * Pesan pengiriman OTP pergantian email civitas
     */
    public static function profileChangeEmailOtpSent(string $maskedEmail): string
    {
        return "Kode verifikasi OTP 6 digit telah dikirimkan ke alamat email baru ({$maskedEmail}). Masukkan kode tersebut dalam 5 menit untuk merampungkan pergantian.";
    }

    /**
     * Pesan teks tersalin ke clipboard (info ringan)
     */
    public static function copiedToClipboard(string $itemName = 'Teks'): string
    {
        return "{$itemName} berhasil disalin ke papan klip.";
    }
}
