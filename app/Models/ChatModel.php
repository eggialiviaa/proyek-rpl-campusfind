<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use App\Services\ChatService;
use PDO;
use Throwable;

/**
 * ChatModel — Model perpesanan internal antara Civitas dan Petugas L&F
 */
class ChatModel extends BaseModel
{
    protected string $table = 'chat_rooms';
    protected PDO    $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->pdo;
    }

    /**
     * Dapatkan atau buat ruang chat baru untuk sebuah klaim
     */
    public function getOrCreateRoomForClaim(int $claimId, int $userId, string $userRole): ?array
    {
        // Admin tidak berpartisipasi dalam perpesanan internal
        if ($userRole === 'admin') {
            return null;
        }

        // 1. Cek apakah klaim valid
        $stmtClaim = $this->db->prepare("
            SELECT c.*, i.title AS item_title, i.item_code, u.name AS claimant_name
            FROM claims c
            LEFT JOIN items i ON c.item_id = i.id
            JOIN users u ON c.claimant_user_id = u.id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmtClaim->execute([$claimId]);
        $claim = $stmtClaim->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            return null;
        }

        // 2. Cek apakah sudah ada room untuk klaim ini
        $stmtRoom = $this->db->prepare("SELECT * FROM chat_rooms WHERE claim_id = ? LIMIT 1");
        $stmtRoom->execute([$claimId]);
        $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);

        // Jika belum ada room klaim langsung, cek apakah sudah ada room laporan terkait klaim ini
        if (!$room) {
            $lostReportId = !empty($claim['lost_report_id']) ? (int) $claim['lost_report_id'] : 0;
            $foundReportId = !empty($claim['found_report_id']) ? (int) $claim['found_report_id'] : 0;

            if ($lostReportId > 0) {
                $stmtRepRoom = $this->db->prepare("SELECT * FROM chat_rooms WHERE lost_report_id = ? LIMIT 1");
                $stmtRepRoom->execute([$lostReportId]);
                $room = $stmtRepRoom->fetch(PDO::FETCH_ASSOC);
            }

            if (!$room && $foundReportId > 0) {
                $stmtRepRoom = $this->db->prepare("SELECT * FROM chat_rooms WHERE found_report_id = ? LIMIT 1");
                $stmtRepRoom->execute([$foundReportId]);
                $room = $stmtRepRoom->fetch(PDO::FETCH_ASSOC);
            }

            if (!$room && !empty($claim['item_id'])) {
                $stmtFnd = $this->db->prepare("SELECT id FROM found_reports WHERE item_id = ? LIMIT 1");
                $stmtFnd->execute([(int) $claim['item_id']]);
                $fndId = $stmtFnd->fetchColumn();
                if ($fndId) {
                    $stmtRepRoom = $this->db->prepare("SELECT * FROM chat_rooms WHERE found_report_id = ? LIMIT 1");
                    $stmtRepRoom->execute([(int) $fndId]);
                    $room = $stmtRepRoom->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($room) {
                // Tautkan room laporan yang sudah ada dengan klaim ini
                $updStmt = $this->db->prepare("UPDATE chat_rooms SET claim_id = ? WHERE id = ?");
                $updStmt->execute([$claimId, $room['id']]);
            }
        }

        if ($room) {
            $roomId = (int) $room['id'];
            $this->ensureParticipant($roomId, $userId, $userRole);
            return $this->getRoomDetail($roomId);
        }

        // 3. Buat room baru jika belum ada
        $roomCode = 'CHAT-CLM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $lostReportId = !empty($claim['lost_report_id']) ? (int) $claim['lost_report_id'] : null;
            $foundReportId = !empty($claim['found_report_id']) ? (int) $claim['found_report_id'] : null;
            if ($foundReportId === null && !empty($claim['item_id'])) {
                $stmtF = $this->db->prepare("SELECT id FROM found_reports WHERE item_id = ? LIMIT 1");
                $stmtF->execute([(int) $claim['item_id']]);
                $fId = $stmtF->fetchColumn();
                if ($fId) {
                    $foundReportId = (int) $fId;
                }
            }

            $stmtInsert = $this->db->prepare("
                INSERT INTO chat_rooms (room_code, claim_id, lost_report_id, found_report_id, created_by_user_id, status, last_message_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?)
            ");
            $stmtInsert->execute([$roomCode, $claimId, $lostReportId, $foundReportId, $userId, $now, $now, $now]);
            $roomId = (int) $this->db->lastInsertId();

            // Daftarkan pemilik klaim sebagai partisipan
            $claimantId = (int) $claim['claimant_user_id'];
            $stmtPart1 = $this->db->prepare("
                INSERT IGNORE INTO chat_participants (room_id, user_id, role_in_room, last_read_at, created_at)
                VALUES (?, ?, 'claimant', ?, ?)
            ");
            $stmtPart1->execute([$roomId, $claimantId, $now, $now]);

            // Jika yang membuat adalah petugas, daftarkan juga
            if ($userId !== $claimantId) {
                $stmtPart2 = $this->db->prepare("
                    INSERT IGNORE INTO chat_participants (room_id, user_id, role_in_room, last_read_at, created_at)
                    VALUES (?, ?, 'petugas', ?, ?)
                ");
                $stmtPart2->execute([$roomId, $userId, $now, $now]);
            }

            // Buat pesan sistem inisialisasi awal
            $initMsg = "Ruang percakapan dibuka untuk pembahasan klaim #{$claim['claim_number']} ({$claim['item_title']}).";
            $stmtMsg = $this->db->prepare("
                INSERT INTO chat_messages (room_id, sender_user_id, message_type, message, is_read, created_at)
                VALUES (?, ?, 'system_event', ?, 1, ?)
            ");
            $stmtMsg->execute([$roomId, $userId, $initMsg, $now]);

            $this->db->commit();
            return $this->getRoomDetail($roomId);
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('[ChatModel::getOrCreateRoomForClaim] Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Dapatkan atau buat ruang chat baru untuk laporan (lost/found)
     * Prompt 34D:
     * - Satu ruang per laporan per pemilik.
     * - Jika laporan punya klaim aktif, tautkan ke ruang chat klaim yang sudah ada (jangan buat duplikat).
     * - Admin tidak ikut.
     */
    public function getOrCreateRoomForReport(string $type, int $reportId, int $userId, string $userRole): ?array
    {
        // Admin tidak berpartisipasi dalam perpesanan internal
        if ($userRole === 'admin') {
            return null;
        }

        $col = $type === 'found' ? 'found_report_id' : 'lost_report_id';
        $tbl = $type === 'found' ? 'found_reports' : 'lost_reports';

        $stmtRep = $this->db->prepare("
            SELECT id, title, report_number, reporter_user_id" . ($type === 'found' ? ', item_id' : '') . "
            FROM {$tbl} 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmtRep->execute([$reportId]);
        $report = $stmtRep->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            return null;
        }

        // TAUTAN KLAIM AKTIF:
        // Jika laporan punya klaim aktif, tautkan ke ruang chat klaim yang sudah ada, jangan buat duplikat
        $activeClaim = null;
        if ($type === 'lost') {
            $stmtClaim = $this->db->prepare("
                SELECT id FROM claims 
                WHERE lost_report_id = ? 
                  AND status NOT IN ('rejected', 'cancelled', 'closed_duplicate')
                ORDER BY id DESC LIMIT 1
            ");
            $stmtClaim->execute([$reportId]);
            $activeClaim = $stmtClaim->fetch(PDO::FETCH_ASSOC);
        } else {
            $itemId = !empty($report['item_id']) ? (int) $report['item_id'] : 0;
            if ($itemId > 0) {
                $stmtClaim = $this->db->prepare("
                    SELECT id FROM claims 
                    WHERE (found_report_id = ? OR item_id = ?) 
                      AND status NOT IN ('rejected', 'cancelled', 'closed_duplicate')
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtClaim->execute([$reportId, $itemId]);
            } else {
                $stmtClaim = $this->db->prepare("
                    SELECT id FROM claims 
                    WHERE found_report_id = ? 
                      AND status NOT IN ('rejected', 'cancelled', 'closed_duplicate')
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtClaim->execute([$reportId]);
            }
            $activeClaim = $stmtClaim->fetch(PDO::FETCH_ASSOC);
        }

        if ($activeClaim && !empty($activeClaim['id'])) {
            // Langsung tautkan dan kembalikan ruang chat klaim
            return $this->getOrCreateRoomForClaim((int) $activeClaim['id'], $userId, $userRole);
        }

        // Jika tidak ada klaim aktif, gunakan/buat ruang chat terikat laporan (satu ruang per laporan per pemilik)
        $stmtRoom = $this->db->prepare("SELECT * FROM chat_rooms WHERE {$col} = ? LIMIT 1");
        $stmtRoom->execute([$reportId]);
        $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);

        if ($room) {
            $roomId = (int) $room['id'];
            $this->ensureParticipant($roomId, $userId, $userRole);
            return $this->getRoomDetail($roomId);
        }

        $prefix = $type === 'found' ? 'FND' : 'LST';
        $roomCode = "CHAT-{$prefix}-" . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $stmtInsert = $this->db->prepare("
                INSERT INTO chat_rooms (room_code, {$col}, created_by_user_id, status, last_message_at, created_at, updated_at)
                VALUES (?, ?, ?, 'active', ?, ?, ?)
            ");
            $stmtInsert->execute([$roomCode, $reportId, $userId, $now, $now, $now]);
            $roomId = (int) $this->db->lastInsertId();

            $reporterId = (int) $report['reporter_user_id'];
            $stmtPart1 = $this->db->prepare("
                INSERT IGNORE INTO chat_participants (room_id, user_id, role_in_room, last_read_at, created_at)
                VALUES (?, ?, 'reporter', ?, ?)
            ");
            $stmtPart1->execute([$roomId, $reporterId, $now, $now]);

            if ($userId !== $reporterId) {
                $stmtPart2 = $this->db->prepare("
                    INSERT IGNORE INTO chat_participants (room_id, user_id, role_in_room, last_read_at, created_at)
                    VALUES (?, ?, 'petugas', ?, ?)
                ");
                $stmtPart2->execute([$roomId, $userId, $now, $now]);
            }

            $initMsg = "Ruang percakapan resmi dibuka terkait laporan #{$report['report_number']} ({$report['title']}). Semua Petugas L&F berwenang melayani diskusi ini.";
            $stmtMsg = $this->db->prepare("
                INSERT INTO chat_messages (room_id, sender_user_id, message_type, message, is_read, created_at)
                VALUES (?, ?, 'system_event', ?, 1, ?)
            ");
            $stmtMsg->execute([$roomId, $userId, $initMsg, $now]);

            $this->db->commit();
            return $this->getRoomDetail($roomId);
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('[ChatModel::getOrCreateRoomForReport] Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Pastikan user terdaftar sebagai partisipan room
     */
    public function ensureParticipant(int $roomId, int $userId, string $userRole): void
    {
        $roleInRoom = 'petugas';
        if ($userRole === 'admin') {
            $roleInRoom = 'admin';
        } elseif ($userRole === 'civitas') {
            $roleInRoom = 'claimant';
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO chat_participants (room_id, user_id, role_in_room, last_read_at, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$roomId, $userId, $roleInRoom, $now, $now]);
    }

    /**
     * Ambil detail lengkap room beserta relasi claim/report
     */
    public function getRoomDetail(int $roomId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cr.*,
                   c.claim_number, c.status AS claim_status, c.claimant_user_id,
                   i.title AS item_title, i.item_code, i.primary_image AS item_image,
                   lr.report_number AS lost_report_number, lr.title AS lost_report_title, lr.status AS lost_status, lr.reporter_user_id AS lost_reporter_id,
                   fr.report_number AS found_report_number, fr.title AS found_report_title, fr.status AS found_status, fr.reporter_user_id AS found_reporter_id
            FROM chat_rooms cr
            LEFT JOIN claims c ON cr.claim_id = c.id
            LEFT JOIN items i ON c.item_id = i.id
            LEFT JOIN lost_reports lr ON cr.lost_report_id = lr.id
            LEFT JOIN found_reports fr ON cr.found_report_id = fr.id
            WHERE cr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            return null;
        }

        // Tentukan judul dan konteks display
        if (!empty($room['claim_id'])) {
            $room['context_title'] = "Klaim #{$room['claim_number']} — " . ($room['item_title'] ?? 'Barang Temuan');
            $room['context_subtitle'] = "Kode Barang: " . ($room['item_code'] ?? '-');
            $room['context_status'] = $room['claim_status'] ?? 'pending';
            $room['context_url'] = "/claims/{$room['claim_id']}";
            $room['owner_user_id'] = (int) $room['claimant_user_id'];
        } elseif (!empty($room['lost_report_id'])) {
            $room['context_title'] = "Laporan Hilang #{$room['lost_report_number']} — " . $room['lost_report_title'];
            $room['context_subtitle'] = "Laporan Kehilangan Civitas";
            $room['context_status'] = $room['lost_status'] ?? 'published';
            $room['context_url'] = "/reports/lost/{$room['lost_report_id']}";
            $room['owner_user_id'] = (int) $room['lost_reporter_id'];
        } elseif (!empty($room['found_report_id'])) {
            $room['context_title'] = "Laporan Temuan #{$room['found_report_number']} — " . $room['found_report_title'];
            $room['context_subtitle'] = "Laporan Penemuan Barang";
            $room['context_status'] = $room['found_status'] ?? 'published';
            $room['context_url'] = "/reports/found/{$room['found_report_id']}";
            $room['owner_user_id'] = (int) $room['found_reporter_id'];
        } else {
            $room['context_title'] = "Percakapan #{$room['room_code']}";
            $room['context_subtitle'] = "Layanan Lost & Found";
            $room['context_status'] = $room['status'];
            $room['context_url'] = "/chats";
            $room['owner_user_id'] = (int) $room['created_by_user_id'];
        }

        return $room;
    }

    /**
     * Cek apakah user memiliki hak akses ke room ini
     * Aturan Prompt 34D:
     * 1. Admin TIDAK IKUT dalam obrolan chat (selalu false).
     * 2. Semua Petugas aktif berwenang mengakses dan membalas room (true).
     * 3. Civitas HANYA boleh mengakses ruang klaim/laporannya sendiri.
     */
    public function isUserAuthorized(int $roomId, int $userId, string $userRole): bool
    {
        if ($userRole === 'admin') {
            return false;
        }

        if ($userRole === 'petugas') {
            return true;
        }

        $room = $this->getRoomDetail($roomId);
        if (!$room) {
            return false;
        }

        // Cek apakah user adalah owner dari entitas terkait
        if ((int) ($room['owner_user_id'] ?? 0) === $userId) {
            return true;
        }

        // Cek tabel partisipan
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM chat_participants WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$roomId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Ambil daftar ruang chat aktif milik pengguna
     * Admin tidak ikut (mengembalikan array kosong)
     */
    public function getUserRooms(int $userId, string $userRole): array
    {
        if ($userRole === 'admin') {
            return [];
        }

        if ($userRole === 'petugas') {
            // Petugas melihat semua room yang aktif
            $sql = "
                SELECT cr.*,
                       c.claim_number, c.status AS claim_status,
                       i.title AS item_title, i.item_code,
                       u_claim.name AS claimant_name,
                       lr.report_number AS lost_report_number, lr.title AS lost_report_title,
                       u_lost.name AS lost_reporter_name,
                       fr.report_number AS found_report_number, fr.title AS found_report_title,
                       u_found.name AS found_reporter_name,
                       (
                           SELECT message FROM chat_messages 
                           WHERE room_id = cr.id ORDER BY id DESC LIMIT 1
                       ) AS latest_message,
                       (
                           SELECT created_at FROM chat_messages 
                           WHERE room_id = cr.id ORDER BY id DESC LIMIT 1
                       ) AS latest_message_time,
                       (
                           SELECT COUNT(*) FROM chat_messages cm 
                           WHERE cm.room_id = cr.id 
                             AND cm.sender_user_id != ? 
                             AND cm.is_read = 0
                       ) AS unread_count
                FROM chat_rooms cr
                LEFT JOIN claims c ON cr.claim_id = c.id
                LEFT JOIN items i ON c.item_id = i.id
                LEFT JOIN users u_claim ON c.claimant_user_id = u_claim.id
                LEFT JOIN lost_reports lr ON cr.lost_report_id = lr.id
                LEFT JOIN users u_lost ON lr.reporter_user_id = u_lost.id
                LEFT JOIN found_reports fr ON cr.found_report_id = fr.id
                LEFT JOIN users u_found ON fr.reporter_user_id = u_found.id
                ORDER BY cr.last_message_at DESC, cr.id DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
        } else {
            // Civitas hanya melihat room miliknya
            $sql = "
                SELECT cr.*,
                       c.claim_number, c.status AS claim_status,
                       i.title AS item_title, i.item_code,
                       lr.report_number AS lost_report_number, lr.title AS lost_report_title,
                       fr.report_number AS found_report_number, fr.title AS found_report_title,
                       (
                           SELECT message FROM chat_messages 
                           WHERE room_id = cr.id ORDER BY id DESC LIMIT 1
                       ) AS latest_message,
                       (
                           SELECT created_at FROM chat_messages 
                           WHERE room_id = cr.id ORDER BY id DESC LIMIT 1
                       ) AS latest_message_time,
                       (
                           SELECT COUNT(*) FROM chat_messages cm 
                           WHERE cm.room_id = cr.id 
                             AND cm.sender_user_id != ? 
                             AND cm.is_read = 0
                       ) AS unread_count
                FROM chat_rooms cr
                JOIN chat_participants cp ON cr.id = cp.room_id AND cp.user_id = ?
                LEFT JOIN claims c ON cr.claim_id = c.id
                LEFT JOIN items i ON c.item_id = i.id
                LEFT JOIN lost_reports lr ON cr.lost_report_id = lr.id
                LEFT JOIN found_reports fr ON cr.found_report_id = fr.id
                ORDER BY cr.last_message_at DESC, cr.id DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $userId]);
        }

        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format metadata display untuk setiap room
        foreach ($rooms as &$r) {
            if (!empty($r['claim_id'])) {
                $r['display_title'] = "Klaim #" . ($r['claim_number'] ?? $r['claim_id']);
                $r['display_subtitle'] = $r['item_title'] ?? 'Barang Temuan';
                $r['participant_name'] = $r['claimant_name'] ?? 'Civitas';
            } elseif (!empty($r['lost_report_id'])) {
                $r['display_title'] = "Laporan Hilang #" . ($r['lost_report_number'] ?? $r['lost_report_id']);
                $r['display_subtitle'] = $r['lost_report_title'] ?? ($r['item_title'] ?? 'Barang Hilang');
                $r['participant_name'] = $r['lost_reporter_name'] ?? 'Pelapor';
            } else {
                $r['display_title'] = "Laporan Temuan #" . ($r['found_report_number'] ?? $r['found_report_id']);
                $r['display_subtitle'] = $r['found_report_title'] ?? ($r['item_title'] ?? 'Barang Temuan');
                $r['participant_name'] = $r['found_reporter_name'] ?? 'Penemu';
            }
        }

        return $rooms;
    }

    /**
     * Ambil pesan-pesan dalam room
     * Mendukung paginasi dan filter after_id untuk live polling
     */
    public function getMessages(int $roomId, ?int $afterId = null, int $limit = 50): array
    {
        $sql = "
            SELECT cm.*,
                   u.name AS sender_name,
                   r.name AS sender_role,
                   r.label AS sender_role_label
            FROM chat_messages cm
            JOIN users u ON cm.sender_user_id = u.id
            JOIN roles r ON u.role_id = r.id
            WHERE cm.room_id = ?
        ";
        $params = [$roomId];

        if ($afterId !== null && $afterId > 0) {
            $sql .= " AND cm.id > ?";
            $params[] = $afterId;
        }

        $sql .= " ORDER BY cm.id ASC LIMIT " . (int) $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Simpan pesan baru ke dalam room
     */
    public function storeMessage(int $roomId, int $senderId, string $message, string $messageType = 'text'): ?array
    {
        $cleanMessage = ChatService::sanitizeMessage($message);
        $validationErr = ChatService::validateMessage($cleanMessage);

        if ($validationErr !== null) {
            return null;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            // 1. Simpan pesan
            $stmt = $this->db->prepare("
                INSERT INTO chat_messages (room_id, sender_user_id, message_type, message, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, ?)
            ");
            $stmt->execute([$roomId, $senderId, $messageType, $cleanMessage, $now]);
            $msgId = (int) $this->db->lastInsertId();

            // 2. Perbarui last_message_at pada room
            $stmtRoom = $this->db->prepare("UPDATE chat_rooms SET last_message_at = ?, updated_at = ? WHERE id = ?");
            $stmtRoom->execute([$now, $now, $roomId]);

            // 3. Perbarui last_read_at pada pengirim
            $stmtPart = $this->db->prepare("
                UPDATE chat_participants SET last_read_at = ? WHERE room_id = ? AND user_id = ?
            ");
            $stmtPart->execute([$now, $roomId, $senderId]);

            $this->db->commit();

            // 4. Ambil pesan lengkap beserta relasi user
            $stmtFull = $this->db->prepare("
                SELECT cm.*, u.name AS sender_name, r.name AS sender_role, r.label AS sender_role_label
                FROM chat_messages cm
                JOIN users u ON cm.sender_user_id = u.id
                JOIN roles r ON u.role_id = r.id
                WHERE cm.id = ?
                LIMIT 1
            ");
            $stmtFull->execute([$msgId]);
            $fullMessage = $stmtFull->fetch(PDO::FETCH_ASSOC);

            // 5. Ambil data room & daftar penerima notifikasi
            $room = $this->getRoomDetail($roomId);
            $recipients = $this->getRoomRecipientUserIds($roomId, $senderId);

            // 6. Picu service hook & audit log
            ChatService::onMessageSent($fullMessage, $room, $recipients);

            return $fullMessage;
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('[ChatModel::storeMessage] Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Dapatkan daftar user_id partisipan selain pengirim (untuk target notifikasi)
     */
    public function getRoomRecipientUserIds(int $roomId, int $senderId): array
    {
        // 1. Periksa role pengirim pesan
        $stmtSender = $this->db->prepare("
            SELECT u.id, r.name AS role_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmtSender->execute([$senderId]);
        $sender = $stmtSender->fetch(PDO::FETCH_ASSOC);
        $isStaff = in_array($sender['role_name'] ?? '', ['petugas', 'admin'], true);

        if ($isStaff) {
            // Pengirim adalah Petugas/Admin -> Pihak lawan HANYA Civitas (pemilik klaim / pelapor)
            $stmtCivitas = $this->db->prepare("
                SELECT cp.user_id 
                FROM chat_participants cp
                JOIN users u ON cp.user_id = u.id
                JOIN roles r ON u.role_id = r.id
                WHERE cp.room_id = ? AND cp.user_id != ? AND r.name = 'civitas'
            ");
            $stmtCivitas->execute([$roomId, $senderId]);
            $civitasIds = $stmtCivitas->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($civitasIds)) {
                return array_map('intval', array_unique($civitasIds));
            }

            // Fallback jika belum di chat_participants: ambil dari metadata room (pemilik klaim/laporan)
            $stmtRoom = $this->db->prepare("
                SELECT c.claimant_user_id, lr.reporter_user_id AS lost_reporter_id, fr.reporter_user_id AS found_reporter_id
                FROM chat_rooms cr
                LEFT JOIN claims c ON cr.claim_id = c.id
                LEFT JOIN lost_reports lr ON cr.lost_report_id = lr.id
                LEFT JOIN found_reports fr ON cr.found_report_id = fr.id
                WHERE cr.id = ?
                LIMIT 1
            ");
            $stmtRoom->execute([$roomId]);
            $roomMeta = $stmtRoom->fetch(PDO::FETCH_ASSOC);
            $opposingUserId = (int) ($roomMeta['claimant_user_id'] ?? $roomMeta['lost_reporter_id'] ?? $roomMeta['found_reporter_id'] ?? 0);
            if ($opposingUserId > 0 && $opposingUserId !== $senderId) {
                return [$opposingUserId];
            }

            return [];
        }

        // Pengirim adalah Civitas -> Pihak lawan adalah Petugas L&F (Admin tidak ikut)
        // 1. Cari petugas yang sudah bergabung di ruang chat ini
        $stmtStaffPart = $this->db->prepare("
            SELECT cp.user_id 
            FROM chat_participants cp
            JOIN users u ON cp.user_id = u.id
            JOIN roles r ON u.role_id = r.id
            WHERE cp.room_id = ? AND cp.user_id != ? AND r.name = 'petugas'
        ");
        $stmtStaffPart->execute([$roomId, $senderId]);
        $staffIds = $stmtStaffPart->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($staffIds)) {
            return array_map('intval', array_unique($staffIds));
        }

        // 2. Jika belum ada staf di room, fallback ke seluruh Petugas aktif kampus
        $stmtPetugas = $this->db->prepare("
            SELECT u.id FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE r.name = 'petugas' AND u.is_active = 1 AND u.id != ?
        ");
        $stmtPetugas->execute([$senderId]);
        $petugasIds = $stmtPetugas->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', array_unique($petugasIds));
    }

    /**
     * Tandai semua pesan di sebuah room sebagai terbaca oleh user
     */
    public function markAsRead(int $roomId, int $userId): int
    {
        $now = date('Y-m-d H:i:s');

        // Perbarui waktu baca partisipan
        $stmtPart = $this->db->prepare("
            UPDATE chat_participants SET last_read_at = ? WHERE room_id = ? AND user_id = ?
        ");
        $stmtPart->execute([$now, $roomId, $userId]);

        // Tandai pesan dari orang lain sebagai read
        $stmtMsg = $this->db->prepare("
            UPDATE chat_messages 
            SET is_read = 1, read_at = ? 
            WHERE room_id = ? AND sender_user_id != ? AND is_read = 0
        ");
        $stmtMsg->execute([$now, $roomId, $userId]);
        return $stmtMsg->rowCount();
    }

    /**
     * Hitung total pesan yang belum terbaca untuk navigasi/navbar
     * Admin tidak ikut (selalu 0)
     */
    public function getTotalUnreadCount(int $userId, string $userRole): int
    {
        if ($userRole === 'admin') {
            return 0;
        }

        if ($userRole === 'petugas') {
            // Petugas menghitung pesan dari user non-staff yang belum dibaca
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM chat_messages cm
                JOIN users u ON cm.sender_user_id = u.id
                JOIN roles r ON u.role_id = r.id
                WHERE r.name = 'civitas' AND cm.is_read = 0
            ");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        }

        // Civitas hanya menghitung pesan dari room miliknya yang dikirim orang lain
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM chat_messages cm
            JOIN chat_participants cp ON cm.room_id = cp.room_id AND cp.user_id = ?
            WHERE cm.sender_user_id != ? AND cm.is_read = 0
        ");
        $stmt->execute([$userId, $userId]);
        return (int) $stmt->fetchColumn();
    }
}
