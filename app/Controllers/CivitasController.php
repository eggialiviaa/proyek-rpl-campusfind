<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use Core\Database;

/**
 * CivitasController — Khusus Civitas Akademika (Mahasiswa, Dosen, Tendik)
 */
class CivitasController extends BaseController
{
    public function __construct()
    {
        // Guard ketat backend: hanya civitas (atau admin/petugas) yang diizinkan
        AuthMiddleware::require();
    }

    /**
     * GET /civitas/my-reports
     * Daftar laporan kehilangan & penemuan milik pengguna
     */
    public function myReports(array $params = []): void
    {
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];
        $pdo = Database::getInstance()->getPdo();

        $stmtLost = $pdo->prepare("
            SELECT lr.*, c.name AS category_name, l.name AS location_name 
            FROM lost_reports lr
            JOIN categories c ON lr.category_id = c.id
            JOIN locations l ON lr.last_seen_location_id = l.id
            WHERE lr.reporter_user_id = ?
            ORDER BY lr.id DESC
        ");
        $stmtLost->execute([$userId]);
        $lostReports = $stmtLost->fetchAll();

        $stmtFound = $pdo->prepare("
            SELECT fr.*, c.name AS category_name, l.name AS location_name 
            FROM found_reports fr
            JOIN categories c ON fr.category_id = c.id
            JOIN locations l ON fr.location_id = l.id
            WHERE fr.reporter_user_id = ?
            ORDER BY fr.id DESC
        ");
        $stmtFound->execute([$userId]);
        $foundReports = $stmtFound->fetchAll();

        $this->render('civitas/my_reports', [
            'pageTitle'    => 'Laporan Saya — Campus Find',
            'lostReports'  => $lostReports,
            'foundReports' => $foundReports,
            'user'         => $user,
        ], 'app_shell');
    }
}
