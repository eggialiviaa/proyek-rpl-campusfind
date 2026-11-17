<?php

declare(strict_types=1);

namespace App\Services;

/**
 * FileUploadService — Layanan upload file gambar yang aman
 *
 * Fitur Keamanan:
 * - Validasi MIME-type riil menggunakan PHP FileInfo
 * - Validasi whitelist ekstensi file
 * - Batasan ukuran file (maksimal 3MB)
 * - Pembuatan nama file acak (UUID / randomized crypto hex) — tidak mempercayai filename dari user
 * - Pencegahan path traversal & directory containment
 */
class FileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_FILE_SIZE = 3 * 1024 * 1024; // 3 MB

    /**
     * Upload dan simpan file gambar
     *
     * @param array  $file     Array $_FILES['input_name']
     * @param string $subDir   Subdirektori di dalam public/uploads/ (misal: 'lost_reports')
     * @return array{success: bool, path: ?string, error: ?string}
     */
    public static function uploadImage(array $file, string $subDir = 'lost_reports'): array
    {
        // 1. Periksa apakah file diupload
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'path' => null, 'error' => null];
        }

        // 2. Periksa error bawaan PHP upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'path' => null, 'error' => 'Terjadi kesalahan sistem saat mengunggah file. (Kode: ' . $file['error'] . ')'];
        }

        // 3. Validasi ukuran file
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['success' => false, 'path' => null, 'error' => 'Ukuran file foto terlalu besar. Maksimal 3 MB.'];
        }

        // 4. Validasi MIME Type secara riil (bukan dari ekstensi header client)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            return [
                'success' => false, 
                'path'    => null, 
                'error'   => 'Format file tidak diizinkan. Hanya format JPG, PNG, dan WebP yang diperbolehkan.'
            ];
        }

        // 5. Buat nama file acak yang aman (mencegah filename injection / overwriting)
        $extension = self::ALLOWED_MIME_TYPES[$mimeType];
        $prefix = str_starts_with($subDir, 'found') ? 'found_' : (str_starts_with($subDir, 'lost') ? 'lost_' : 'img_');
        $safeFileName = $prefix . bin2hex(random_bytes(12)) . '.' . $extension;

        // 6. Siapkan direktori penyimpanan non-publik
        $targetDir = BASE_PATH . '/storage/uploads/' . trim($subDir, '/');
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return ['success' => false, 'path' => null, 'error' => 'Gagal membuat folder penyimpanan upload di server.'];
            }
        }

        // 7. Pindahkan file dari temp
        $destination = $targetDir . '/' . $safeFileName;
        $moved = is_uploaded_file($file['tmp_name']) 
            ? move_uploaded_file($file['tmp_name'], $destination) 
            : copy($file['tmp_name'], $destination);

        if (!$moved) {
            return ['success' => false, 'path' => null, 'error' => 'Gagal memindahkan file ke penyimpanan server.'];
        }

        // Return path relatif untuk disimpan di database
        $relativePath = '/uploads/' . trim($subDir, '/') . '/' . $safeFileName;
        return ['success' => true, 'path' => $relativePath, 'error' => null];
    }

    /**
     * Upload berkas bukti klaim kepemilikan (Foto atau Dokumen PDF, maks 5MB)
     *
     * @param array $file Array $_FILES['evidence']
     * @return array{success: bool, path: ?string, error: ?string, original_name: ?string, type: ?string}
     */
    public static function uploadClaimEvidence(array $file): array
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'path' => null, 'error' => null, 'original_name' => null, 'type' => null];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'path' => null, 'error' => 'Gagal mengunggah file bukti klaim (Error code: ' . $file['error'] . ')', 'original_name' => null, 'type' => null];
        }

        // Maks 5 MB
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'path' => null, 'error' => 'Ukuran file bukti klaim maksimal 5 MB.', 'original_name' => null, 'type' => null];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedTypes = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
        ];

        if (!array_key_exists($mimeType, $allowedTypes)) {
            return ['success' => false, 'path' => null, 'error' => 'Format file bukti hanya boleh JPG, PNG, WebP, atau PDF.', 'original_name' => null, 'type' => null];
        }

        $extension = $allowedTypes[$mimeType];
        $safeFileName = 'evid_' . bin2hex(random_bytes(12)) . '.' . $extension;
        $targetDir = BASE_PATH . '/storage/uploads/claims';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $destination = $targetDir . '/' . $safeFileName;
        $moved = is_uploaded_file($file['tmp_name']) 
            ? move_uploaded_file($file['tmp_name'], $destination) 
            : copy($file['tmp_name'], $destination);

        if (!$moved) {
            return ['success' => false, 'path' => null, 'error' => 'Gagal menyimpan file bukti di server.', 'original_name' => null, 'type' => null];
        }

        return [
            'success'       => true,
            'path'          => '/uploads/claims/' . $safeFileName,
            'original_name' => htmlspecialchars($file['name'] ?? 'Bukti Klaim', ENT_QUOTES, 'UTF-8'),
            'type'          => $mimeType,
            'error'         => null
        ];
    }

    /**
     * Hapus file gambar dari server jika ada
     */
    public static function deleteFile(?string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }

        // Bersihkan path untuk mencegah path traversal
        $cleaned = str_replace(['../', '..\\'], '', $relativePath);
        $storagePath = BASE_PATH . '/storage' . $cleaned;
        $publicPath = BASE_PATH . '/public' . $cleaned;

        if (file_exists($storagePath) && is_file($storagePath)) {
            @unlink($storagePath);
        }
        if (file_exists($publicPath) && is_file($publicPath)) {
            @unlink($publicPath);
        }
    }
}
