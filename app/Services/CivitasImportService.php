<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PDO;
use Throwable;

/**
 * CivitasImportService — Layanan Impor Massal Data Civitas dari Excel/CSV (Prompt 31)
 *
 * Mengimplementasikan:
 * - Unduh templat resmi (.xlsx)
 * - Validasi berkas ketat (ekstensi, MIME, ukuran maks 2 MB, maks 1000 baris)
 * - Deteksi berkas palsu (non-zip/bukan OpenXML)
 * - Netralisasi injeksi formula (=, +, -, @)
 * - Alur dua langkah: (a) Pratinjau tanpa menyimpan, (b) Konfirmasi transaksional
 * - Mode impor: lewati duplikat (skip) atau perbarui data (update)
 * - Akun baru: role Civitas (3), status belum aktif (0), TANPA password terkirim/tampil
 * - Persetujuan notifikasi DEFAULT mati (0)
 * - Masking email & nomor telepon untuk perlindungan privasi
 * - Audit log ringkas tanpa data pribadi
 */
class CivitasImportService
{
    public const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB
    public const MAX_DATA_ROWS = 1000;

    /**
     * Hasilkan file template Excel (.xlsx) resmi untuk impor Civitas
     */
    public static function generateTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Templat Impor Civitas');

        // Header Kolom
        $headers = [
            'A1' => 'NIM',
            'B1' => 'Nama',
            'C1' => 'Email',
            'D1' => 'Program Studi',
            'E1' => 'Angkatan',
            'F1' => 'No WhatsApp',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Styling Header (Modern Indigo Dark)
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Slate 800
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ];
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Contoh Baris 2 & 3 (Data Dummy Panduan)
        $sampleData = [
            [
                'nim'           => '230101001',
                'name'          => 'Budi Santoso',
                'email'         => 'budi.santoso@students.campus.ac.id',
                'study_program' => 'Teknik Informatika',
                'batch_year'    => '2023',
                'phone'         => '081234567890',
            ],
            [
                'nim'           => '230101002',
                'name'          => 'Siti Rahmawati',
                'email'         => 'siti.rahmawati@students.campus.ac.id',
                'study_program' => 'Sistem Informasi',
                'batch_year'    => '2023',
                'phone'         => '081298765432',
            ],
        ];

        $rowNum = 2;
        foreach ($sampleData as $row) {
            $sheet->setCellValueExplicit('A' . $rowNum, $row['nim'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $rowNum, $row['name']);
            $sheet->setCellValue('C' . $rowNum, $row['email']);
            $sheet->setCellValue('D' . $rowNum, $row['study_program']);
            $sheet->setCellValueExplicit('E' . $rowNum, $row['batch_year'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F' . $rowNum, $row['phone'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getRowDimension($rowNum)->setRowHeight(22);
            $rowNum++;
        }

        // Petunjuk pengisian di baris bawah
        $sheet->setCellValue('A5', '* CATATAN PENGISIAN:');
        $sheet->setCellValue('A6', '1. Kolom WAJIB diisi: NIM, Nama, Email.');
        $sheet->setCellValue('A7', '2. Kolom OPSIONAL: Program Studi, Angkatan, No WhatsApp.');
        $sheet->setCellValue('A8', '3. Hapus baris contoh sebelum mengunggah file jika tidak diperlukan.');
        $sheet->setCellValue('A9', '4. Format file yang didukung: .xlsx atau .csv (Maksimal 1000 baris data, ukuran maks 2 MB).');

        $noteStyle = [
            'font' => ['italic' => true, 'color' => ['rgb' => '64748B'], 'size' => 10],
        ];
        $sheet->getStyle('A5:A9')->applyFromArray($noteStyle);

        // Auto width kolom A-F
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Validasi berkas upload dan ekstrak ke pratinjau (Langkah A)
     *
     * @param array $file Array $_FILES['file']
     * @return array{success: bool, error?: string, preview?: array}
     */
    public static function parseUpload(array $file): array
    {
        // 1. Validasi upload PHP
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            return [
                'success' => false,
                'error'   => self::getUploadErrorMessage((int) $code),
            ];
        }

        // 2. Validasi ukuran file (Maks 2 MB)
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            return [
                'success' => false,
                'error'   => 'Ukuran file melebihi batas maksimal 2 MB (terbaca ' . round($size / 1024 / 1024, 2) . ' MB).',
            ];
        }

        // 3. Validasi ekstensi
        $origName = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            return [
                'success' => false,
                'error'   => 'Format file tidak didukung. Harap unggah file dengan format .xlsx atau .csv.',
            ];
        }

        // 4. Validasi MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
                'application/x-zip-compressed',
            ],
            'csv' => [
                'text/csv',
                'text/plain',
                'application/csv',
                'text/x-csv',
                'application/vnd.ms-excel',
            ],
        ];

        if (!in_array($mime, $allowedMimes[$ext] ?? [], true)) {
            return [
                'success' => false,
                'error'   => "Tipe MIME file ({$mime}) tidak sesuai dengan ekstensi .{$ext}.",
            ];
        }

        // 5. Simpan berkas sementara ke direktori storage non-publik
        $storageDir = BASE_PATH . '/storage/temp_imports';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }

        $tempFileName = 'import_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $tempFilePath = $storageDir . '/' . $tempFileName;

        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $tempFilePath)
            : copy($file['tmp_name'], $tempFilePath);

        if (!$moved) {
            return [
                'success' => false,
                'error'   => 'Gagal memindahkan file upload ke direktori sementara.',
            ];
        }

        try {
            // 6. Muat file menggunakan PhpSpreadsheet
            $readerType = ($ext === 'csv') ? 'Csv' : 'Xlsx';
            $reader = IOFactory::createReader($readerType);

            if ($ext === 'xlsx') {
                if (!class_exists('ZipArchive')) {
                    @unlink($tempFilePath);
                    return [
                        'success' => false,
                        'error'   => 'Ekstensi PHP ZipArchive (ext-zip) belum aktif di server web. Harap aktifkan extension=zip pada php.ini dan muat ulang web server, atau simpan dan unggah berkas Anda dalam format .CSV.',
                    ];
                }

                // Pastikan format OpenXML ZIP valid (deteksi file palsu)
                if (!$reader->canRead($tempFilePath)) {
                    @unlink($tempFilePath);
                    return [
                        'success' => false,
                        'error'   => 'Berkas bukan file Excel .xlsx yang valid atau file rusak.',
                    ];
                }
            }

            $spreadsheet = $reader->load($tempFilePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rowsData = $sheet->toArray(null, true, false, false);
        } catch (Throwable $e) {
            @unlink($tempFilePath);
            return [
                'success' => false,
                'error'   => 'Gagal membaca isi spreadsheet: ' . $e->getMessage(),
            ];
        } finally {
            // Hapus file fisik segera setelah selesai dimuat ke memori
            if (file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }
        }

        if (empty($rowsData)) {
            return [
                'success' => false,
                'error'   => 'File kosong, tidak ada baris data yang ditemukan.',
            ];
        }

        // 7. Analisis Baris Header (Baris 1)
        $headerRow = array_shift($rowsData);
        $columnMap = self::detectHeaderColumns($headerRow);

        if ($columnMap['nim'] === null || $columnMap['name'] === null || $columnMap['email'] === null) {
            $missing = [];
            if ($columnMap['nim'] === null) $missing[] = 'NIM';
            if ($columnMap['name'] === null) $missing[] = 'Nama';
            if ($columnMap['email'] === null) $missing[] = 'Email';

            return [
                'success' => false,
                'error'   => 'Kolom WAJIB tidak lengkap. File harus memiliki kolom: ' . implode(', ', $missing) . '.',
            ];
        }

        // 8. Filter baris kosong & validasi batas maksimal 1000 baris data
        $validRows = [];
        foreach ($rowsData as $idx => $r) {
            // Abaikan jika seluruh kolom kosong atau hanya baris catatan/panduan
            $allEmpty = true;
            foreach ($r as $val) {
                if ($val !== null && trim((string)$val) !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) {
                continue;
            }

            // Jika baris pertama kolom NIM diawali bintang/catatan, lewati
            $firstCol = trim((string)($r[$columnMap['nim']] ?? ''));
            if (str_starts_with($firstCol, '*') || str_starts_with(strtolower($firstCol), 'catatan')) {
                continue;
            }

            $validRows[] = [
                'raw_index' => $idx + 2, // nomor baris di spreadsheet (1-indexed + header)
                'data'      => $r,
            ];
        }

        $totalRowsCount = count($validRows);
        if ($totalRowsCount === 0) {
            return [
                'success' => false,
                'error'   => 'Tidak ditemukan baris data civitas yang valid pada file.',
            ];
        }

        if ($totalRowsCount > self::MAX_DATA_ROWS) {
            return [
                'success' => false,
                'error'   => "Jumlah baris data ({$totalRowsCount}) melebihi batas maksimal " . self::MAX_DATA_ROWS . " baris.",
            ];
        }

        // 9. Validasi Baris demi Baris, Cek Duplikat File & Cek Duplikat Database
        $pdo = Database::getInstance()->getPdo();
        $seenNimsInFile   = [];
        $seenEmailsInFile = [];

        // Ambil NIM dan Email yang sudah ada di database untuk lookup cepat
        $existingNims = $pdo->query("SELECT identity_number, id FROM users WHERE identity_number IS NOT NULL AND identity_number != ''")
                            ->fetchAll(PDO::FETCH_KEY_PAIR);
        $existingEmails = $pdo->query("SELECT LOWER(email), id FROM users WHERE email IS NOT NULL AND email != ''")
                              ->fetchAll(PDO::FETCH_KEY_PAIR);

        $parsedRows = [];
        $validCount = 0;
        $duplicateCount = 0;
        $errorCount = 0;

        foreach ($validRows as $item) {
            $rowNum = $item['raw_index'];
            $r = $item['data'];

            // Netralisasi formula pada setiap sel input
            $nim          = self::sanitizeFormulaValue(trim((string) ($r[$columnMap['nim']] ?? '')));
            $name         = self::sanitizeFormulaValue(trim((string) ($r[$columnMap['name']] ?? '')));
            $email        = self::sanitizeFormulaValue(trim((string) ($r[$columnMap['email']] ?? '')));
            $studyProgram = $columnMap['study_program'] !== null ? self::sanitizeFormulaValue(trim((string) ($r[$columnMap['study_program']] ?? ''))) : '';
            $batchYear    = $columnMap['batch_year'] !== null ? self::sanitizeFormulaValue(trim((string) ($r[$columnMap['batch_year']] ?? ''))) : '';
            $phone        = $columnMap['phone'] !== null ? self::sanitizeFormulaValue(trim((string) ($r[$columnMap['phone']] ?? ''))) : '';

            $rowErrors = [];

            // A. Validasi Kolom Wajib
            if ($nim === '') {
                $rowErrors[] = 'NIM tidak boleh kosong';
            } elseif (strlen($nim) < 3 || strlen($nim) > 50) {
                $rowErrors[] = 'Panjang NIM harus 3-50 karakter';
            }

            if ($name === '') {
                $rowErrors[] = 'Nama tidak boleh kosong';
            } elseif (mb_strlen($name) > 100) {
                $rowErrors[] = 'Nama maksimal 100 karakter';
            }

            if ($email === '') {
                $rowErrors[] = 'Email tidak boleh kosong';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'Format email tidak valid';
            }

            // B. Validasi Kolom Opsional
            if ($phone !== '' && strlen($phone) > 30) {
                $rowErrors[] = 'Nomor telepon/WhatsApp maksimal 30 karakter';
            }
            if ($batchYear !== '' && strlen($batchYear) > 10) {
                $rowErrors[] = 'Angkatan maksimal 10 karakter';
            }
            if ($studyProgram !== '' && mb_strlen($studyProgram) > 100) {
                $rowErrors[] = 'Program Studi maksimal 100 karakter';
            }

            $status = 'valid';
            $statusReason = '';
            $dbUserId = null;

            // Jika ada error validasi format/kosong
            if (!empty($rowErrors)) {
                $status = 'error';
                $statusReason = implode(', ', $rowErrors);
                $errorCount++;
            } else {
                // C. Cek Duplikat di Dalam File Sendiri
                $nimKey = strtolower($nim);
                $emailKey = strtolower($email);

                if (isset($seenNimsInFile[$nimKey])) {
                    $status = 'duplicate_file';
                    $statusReason = "NIM ganda di dalam file (sama dengan baris {$seenNimsInFile[$nimKey]})";
                    $duplicateCount++;
                } elseif (isset($seenEmailsInFile[$emailKey])) {
                    $status = 'duplicate_file';
                    $statusReason = "Email ganda di dalam file (sama dengan baris {$seenEmailsInFile[$emailKey]})";
                    $duplicateCount++;
                } else {
                    $seenNimsInFile[$nimKey] = $rowNum;
                    $seenEmailsInFile[$emailKey] = $rowNum;

                    // D. Cek Duplikat di Basis Data
                    if (isset($existingNims[$nim])) {
                        $status = 'duplicate_db';
                        $dbUserId = (int) $existingNims[$nim];
                        $statusReason = "NIM sudah terdaftar di sistem (User ID: {$dbUserId})";
                        $duplicateCount++;
                    } elseif (isset($existingEmails[$emailKey])) {
                        $status = 'duplicate_db';
                        $dbUserId = (int) $existingEmails[$emailKey];
                        $statusReason = "Email sudah terdaftar di sistem (User ID: {$dbUserId})";
                        $duplicateCount++;
                    } else {
                        $status = 'valid';
                        $statusReason = 'Siap didaftarkan sebagai civitas baru';
                        $validCount++;
                    }
                }
            }

            $parsedRows[] = [
                'row_num'        => $rowNum,
                'nim'            => $nim,
                'name'           => $name,
                'email'          => $email,
                'email_masked'   => self::maskEmail($email),
                'study_program'  => $studyProgram,
                'batch_year'     => $batchYear,
                'phone'          => $phone,
                'phone_masked'   => self::maskPhone($phone),
                'status'         => $status,
                'status_reason'  => $statusReason,
                'db_user_id'     => $dbUserId,
            ];
        }

        $previewId = 'prev_' . bin2hex(random_bytes(16));

        $previewData = [
            'preview_id'      => $previewId,
            'filename'        => htmlspecialchars($origName, ENT_QUOTES, 'UTF-8'),
            'total_rows'      => count($parsedRows),
            'valid_count'     => $validCount,
            'duplicate_count' => $duplicateCount,
            'error_count'     => $errorCount,
            'rows'            => $parsedRows,
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        // Simpan pratinjau di session server
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['civitas_import_preview'] = $previewData;

        return [
            'success' => true,
            'preview' => $previewData,
        ];
    }

    /**
     * Konfirmasi dan eksekusi penyimpanan ke basis data (Langkah B)
     *
     * @param string $previewId Token verifikasi sesi
     * @param string $mode 'skip' (lewati duplikat) atau 'update' (perbarui yang sudah ada)
     * @param int $adminUserId User ID administrator yang mengeksekusi
     * @return array{success: bool, error?: string, result?: array}
     */
    public static function commitImport(string $previewId, string $mode = 'skip', int $adminUserId = 1): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $previewData = $_SESSION['civitas_import_preview'] ?? null;
        if (!$previewData || ($previewData['preview_id'] ?? '') !== $previewId) {
            return [
                'success' => false,
                'error'   => 'Sesi pratinjau impor telah kedaluwarsa atau tidak valid. Silakan unggah ulang file.',
            ];
        }

        $mode = ($mode === 'update') ? 'update' : 'skip';
        $rows = $previewData['rows'] ?? [];

        if (empty($rows)) {
            return [
                'success' => false,
                'error'   => 'Tidak ada data baris yang dapat diproses.',
            ];
        }

        $pdo = Database::getInstance()->getPdo();

        // Ambil role_id untuk civitas (biasanya 3)
        $stmtRole = $pdo->query("SELECT id FROM roles WHERE name = 'civitas' LIMIT 1");
        $civitasRoleId = (int) ($stmtRole->fetchColumn() ?: 3);

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $failedCount  = 0;

        $executionResults = [];

        try {
            // Satu transaksi database utuh (Rollback jika fatal)
            $pdo->beginTransaction();

            $stmtInsert = $pdo->prepare("
                INSERT INTO users (
                    role_id, name, email, identity_number, study_program, batch_year, phone,
                    password_hash, is_active, email_verified_at, email_notifications_enabled,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, 0, NULL, 0,
                    NOW(), NOW()
                )
            ");

            $stmtUpdate = $pdo->prepare("
                UPDATE users SET
                    name = ?,
                    study_program = IF(? != '', ?, study_program),
                    batch_year = IF(? != '', ?, batch_year),
                    phone = IF(? != '', ?, phone),
                    updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($rows as $item) {
                $status = $item['status'];
                $nim    = $item['nim'];
                $name   = $item['name'];
                $email  = $item['email'];
                $prodi  = $item['study_program'];
                $batch  = $item['batch_year'];
                $phone  = $item['phone'];

                $rowResult = [
                    'row_num'        => $item['row_num'],
                    'nim'            => $nim,
                    'name'           => $name,
                    'email'          => $email,
                    'study_program'  => $prodi,
                    'batch_year'     => $batch,
                    'phone'          => $phone,
                    'action'         => '',
                    'message'        => '',
                ];

                if ($status === 'valid') {
                    // Buat akun baru Civitas (belum aktif, password acak tanpa dikirim/tampil)
                    $secureRandomHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);

                    $stmtInsert->execute([
                        $civitasRoleId,
                        $name,
                        $email,
                        $nim,
                        $prodi ?: null,
                        $batch ?: null,
                        $phone ?: null,
                        $secureRandomHash,
                    ]);

                    $createdCount++;
                    $rowResult['action'] = 'created';
                    $rowResult['message'] = 'Akun Civitas baru berhasil didaftarkan (Status: Belum Aktif)';
                } elseif ($status === 'duplicate_db') {
                    if ($mode === 'update' && !empty($item['db_user_id'])) {
                        // Perbarui data yang ada
                        $targetId = (int) $item['db_user_id'];
                        $stmtUpdate->execute([
                            $name,
                            $prodi, $prodi ?: null,
                            $batch, $batch ?: null,
                            $phone, $phone ?: null,
                            $targetId,
                        ]);

                        $updatedCount++;
                        $rowResult['action'] = 'updated';
                        $rowResult['message'] = "Data pengguna berhasil diperbarui (User ID: {$targetId})";
                    } else {
                        $skippedCount++;
                        $rowResult['action'] = 'skipped';
                        $rowResult['message'] = 'Dilewati (sudah terdaftar di database)';
                    }
                } elseif ($status === 'duplicate_file') {
                    $skippedCount++;
                    $rowResult['action'] = 'skipped';
                    $rowResult['message'] = 'Dilewati (duplikat ganda di dalam file)';
                } else {
                    $failedCount++;
                    $rowResult['action'] = 'failed';
                    $rowResult['message'] = 'Gagal: ' . ($item['status_reason'] ?? 'Galat validasi data');
                }

                $executionResults[] = $rowResult;
            }

            // Catat ke Audit Log TANPA data pribadi mahasiswa
            AuditLogService::log(
                'IMPORT_CIVITAS',
                $adminUserId,
                'users',
                null,
                [
                    'mode'           => $mode,
                    'total_rows'     => count($rows),
                    'created_count'  => $createdCount,
                    'updated_count'  => $updatedCount,
                    'skipped_count'  => $skippedCount,
                    'failed_count'   => $failedCount,
                ]
            );

            $pdo->commit();

            // Hapus pratinjau session setelah sukses commit
            unset($_SESSION['civitas_import_preview']);

            $summaryResult = [
                'mode'           => $mode,
                'total'          => count($rows),
                'created_count'  => $createdCount,
                'updated_count'  => $updatedCount,
                'skipped_count'  => $skippedCount,
                'failed_count'   => $failedCount,
                'results'        => $executionResults,
                'executed_at'    => date('Y-m-d H:i:s'),
            ];

            // Simpan laporan hasil terakhir di session untuk dapat diunduh
            $_SESSION['civitas_import_last_result'] = $summaryResult;

            return [
                'success' => true,
                'result'  => $summaryResult,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[CivitasImportService::commitImport] Rollback exception: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => 'Terjadi kesalahan sistem saat menyimpan ke database: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Hasilkan file Excel (.xlsx) laporan hasil eksekusi impor
     */
    public static function generateResultReport(array $resultData): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Hasil Impor Civitas');

        // Header Ringkasan
        $sheet->setCellValue('A1', 'LAPORAN HASIL IMPOR DATA CIVITAS');
        $sheet->setCellValue('A2', 'Waktu Eksekusi: ' . ($resultData['executed_at'] ?? date('Y-m-d H:i:s')));
        $sheet->setCellValue('A3', 'Mode Impor: ' . (($resultData['mode'] ?? 'skip') === 'update' ? 'Perbarui Data Yang Ada' : 'Lewati Duplikat'));
        $sheet->setCellValue('A4', 'Total Baris: ' . ($resultData['total'] ?? 0) . ' | Dibuat: ' . ($resultData['created_count'] ?? 0) . ' | Diperbarui: ' . ($resultData['updated_count'] ?? 0) . ' | Dilewati: ' . ($resultData['skipped_count'] ?? 0) . ' | Gagal: ' . ($resultData['failed_count'] ?? 0));

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A4')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

        // Header Tabel Detail
        $headers = [
            'A6' => 'No Baris',
            'B6' => 'NIM',
            'C6' => 'Nama',
            'D6' => 'Email',
            'E6' => 'Program Studi',
            'F6' => 'Angkatan',
            'G6' => 'No WhatsApp',
            'H6' => 'Status Tindakan',
            'I6' => 'Keterangan Hasil',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]],
        ];
        $sheet->getStyle('A6:I6')->applyFromArray($headerStyle);
        $sheet->getRowDimension(6)->setRowHeight(24);

        $rowNum = 7;
        $results = $resultData['results'] ?? [];

        foreach ($results as $item) {
            $sheet->setCellValue('A' . $rowNum, $item['row_num'] ?? ($rowNum - 6));
            $sheet->setCellValueExplicit('B' . $rowNum, self::escapeFormulaForExport((string)($item['nim'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $rowNum, self::escapeFormulaForExport((string)($item['name'] ?? '')));
            $sheet->setCellValue('D' . $rowNum, self::escapeFormulaForExport((string)($item['email'] ?? '')));
            $sheet->setCellValue('E' . $rowNum, self::escapeFormulaForExport((string)($item['study_program'] ?? '')));
            $sheet->setCellValueExplicit('F' . $rowNum, self::escapeFormulaForExport((string)($item['batch_year'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('G' . $rowNum, self::escapeFormulaForExport((string)($item['phone'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $actionText = match ($item['action'] ?? '') {
                'created' => 'BERHASIL DIBUAT',
                'updated' => 'DIPERBARUI',
                'skipped' => 'DILEWATI',
                'failed'  => 'GAGAL',
                default   => strtoupper((string)($item['action'] ?? '')),
            };

            $sheet->setCellValue('H' . $rowNum, $actionText);
            $sheet->setCellValue('I' . $rowNum, $item['message'] ?? '');

            // Styling warna status
            $actionColor = match ($item['action'] ?? '') {
                'created' => 'D1FAE5', // light green
                'updated' => 'DBEAFE', // light blue
                'skipped' => 'FEF3C7', // light yellow
                'failed'  => 'FEE2E2', // light red
                default   => 'FFFFFF',
            };
            $sheet->getStyle('H' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($actionColor);

            $rowNum++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Netralisasi potensi injeksi formula Excel (=, +, -, @, \t, \r)
     */
    public static function sanitizeFormulaValue(mixed $val): string
    {
        if ($val === null) {
            return '';
        }

        $str = (string) $val;
        if ($str === '') {
            return '';
        }

        // Karakter berbahaya pemicu eksekusi formula di Excel/Calc
        $dangerousLeading = ['=', '+', '-', '@', "\t", "\r"];

        // Periksa apakah diawali karakter formula berbahaya
        $firstChar = mb_substr($str, 0, 1, 'UTF-8');
        if (in_array($firstChar, $dangerousLeading, true)) {
            // Netralkan dengan kutip tunggal di depan agar dianggap sebagai literal teks murni
            return "'" . $str;
        }

        return $str;
    }

    /**
     * Escape formula saat mengekspor ke spreadsheet
     */
    public static function escapeFormulaForExport(string $str): string
    {
        $firstChar = mb_substr($str, 0, 1, 'UTF-8');
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }
        return $str;
    }

    /**
     * Samarkan alamat email (privasi tampilan publik/daftar)
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $user = $parts[0];
        $domain = $parts[1];

        $maskedUser = strlen($user) <= 2
            ? substr($user, 0, 1) . '***'
            : substr($user, 0, 1) . '***' . substr($user, -1);

        $domainParts = explode('.', $domain);
        $maskedDomain = strlen($domainParts[0]) <= 2
            ? substr($domainParts[0], 0, 1) . '***'
            : substr($domainParts[0], 0, 1) . '***' . substr($domainParts[0], -1);

        $ext = count($domainParts) > 1 ? '.' . implode('.', array_slice($domainParts, 1)) : '';

        return $maskedUser . '@' . $maskedDomain . $ext;
    }

    /**
     * Samarkan nomor telepon/WhatsApp
     */
    public static function maskPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned) < 6) {
            return $phone ? '******' : '-';
        }

        $len = strlen($cleaned);
        $prefix = substr($cleaned, 0, 4);
        $suffix = substr($cleaned, -3);
        $middle = str_repeat('*', max(3, $len - 7));

        return $prefix . $middle . $suffix;
    }

    /**
     * Deteksi indeks kolom header secara fleksibel
     */
    private static function detectHeaderColumns(array $headerRow): array
    {
        $map = [
            'nim'           => null,
            'name'          => null,
            'email'         => null,
            'study_program' => null,
            'batch_year'    => null,
            'phone'         => null,
        ];

        foreach ($headerRow as $colIdx => $colName) {
            if ($colName === null) {
                continue;
            }
            $clean = strtolower(trim((string)$colName));
            $clean = preg_replace('/[^a-z0-9]/', '', $clean);

            if ($map['nim'] === null && in_array($clean, ['nim', 'npm', 'noidentitas', 'nomoridentitas', 'nrp', 'id', 'identitynumber'], true)) {
                $map['nim'] = $colIdx;
                continue;
            }

            if ($map['name'] === null && in_array($clean, ['nama', 'namalengkap', 'name', 'fullname'], true)) {
                $map['name'] = $colIdx;
                continue;
            }

            if ($map['email'] === null && in_array($clean, ['email', 'emailkampus', 'alamatemail', 'mail'], true)) {
                $map['email'] = $colIdx;
                continue;
            }

            if ($map['study_program'] === null && in_array($clean, ['programstudi', 'prodi', 'jurusan', 'studyprogram', 'department'], true)) {
                $map['study_program'] = $colIdx;
                continue;
            }

            if ($map['batch_year'] === null && in_array($clean, ['angkatan', 'tahun', 'tahunmasuk', 'batch', 'year', 'batchyear'], true)) {
                $map['batch_year'] = $colIdx;
                continue;
            }

            if ($map['phone'] === null && in_array($clean, ['nowhatsapp', 'whatsapp', 'wa', 'nohp', 'nomorhp', 'telepon', 'notelp', 'phone', 'telephone'], true)) {
                $map['phone'] = $colIdx;
                continue;
            }
        }

        return $map;
    }

    /**
     * Terjemahan pesan error upload bawaan PHP
     */
    private static function getUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi upload_max_filesize di konfigurasi PHP.',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi MAX_FILE_SIZE pada form HTML.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terunggah sebagian. Silakan coba kembali.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih untuk diunggah.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori sementara server tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke media penyimpanan server.',
            UPLOAD_ERR_EXTENSION  => 'Proses upload dihentikan oleh ekstensi PHP.',
            default               => 'Terjadi kesalahan saat mengunggah file (Kode: ' . $code . ').',
        };
    }
}
