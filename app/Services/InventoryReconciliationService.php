<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PDO;
use Throwable;
use App\Models\ItemModel;

/**
 * InventoryReconciliationService
 *
 * Layanan rekonsiliasi dan verifikasi integritas status inventaris kanonik (Task 12B).
 * Mengidentifikasi dan memulihkan inkonsistensi antara serah terima fisik (handover)
 * dan status penyimpanan fisik di gudang secara aman, atomik, dan idempoten.
 */
class InventoryReconciliationService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getPdo();
    }

    /**
     * Pindai seluruh record inventaris yang inkonsisten (Read-Only)
     *
     * Kriteria:
     * - Klaim terkait terkonfirmasi selesai (status = 'completed' DAN handed_over_at IS NOT NULL)
     * - Namun status fisik item belum 'returned' ATAU masih tercatat menempati rak (storage_location_id IS NOT NULL)
     *
     * @return array<int, array<string, mixed>>
     */
    public function scanInconsistentRecords(): array
    {
        $sql = "
            SELECT 
                i.id AS inventory_id,
                i.item_code,
                i.title AS item_title,
                i.status AS physical_status,
                i.storage_location_id,
                sl.code AS rack_code,
                sl.name AS rack_name,
                c.id AS claim_id,
                c.claim_number,
                c.status AS claim_status,
                c.handed_over_at,
                c.handed_over_by_user_id,
                c.handover_notes,
                fr.id AS found_report_id,
                fr.report_number AS found_report_number,
                fr.status AS found_report_status,
                lr.id AS lost_report_id,
                lr.report_number AS lost_report_number,
                lr.status AS lost_report_status
            FROM items i
            LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
            LEFT JOIN found_reports fr ON fr.item_id = i.id
            LEFT JOIN claims c ON (c.item_id = i.id OR (c.found_report_id IS NOT NULL AND c.found_report_id = fr.id))
            LEFT JOIN lost_reports lr ON (lr.resolved_item_id = i.id OR (c.lost_report_id IS NOT NULL AND lr.id = c.lost_report_id))
            WHERE c.status = 'completed'
              AND c.handed_over_at IS NOT NULL
              AND (i.status != 'returned' OR i.storage_location_id IS NOT NULL)
            ORDER BY i.id ASC
        ";

        $stmt = $this->pdo->query($sql);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($records as $r) {
            $results[] = [
                'inventory_id'           => (int) $r['inventory_id'],
                'inventory_code'         => $r['item_code'],
                'item_title'             => $r['item_title'],
                'current_physical_status'=> $r['physical_status'],
                'storage_location_id'    => $r['storage_location_id'] ? (int) $r['storage_location_id'] : null,
                'rack_code'              => $r['rack_code'],
                'rack_name'              => $r['rack_name'],
                'related_claim_id'       => (int) $r['claim_id'],
                'related_claim_number'   => $r['claim_number'],
                'claim_status'           => $r['claim_status'],
                'handover_date'          => $r['handed_over_at'],
                'handover_officer_id'    => $r['handed_over_by_user_id'] ? (int) $r['handed_over_by_user_id'] : null,
                'handover_notes'         => $r['handover_notes'],
                'found_report_id'        => $r['found_report_id'] ? (int) $r['found_report_id'] : null,
                'found_report_number'    => $r['found_report_number'],
                'found_report_status'    => $r['found_report_status'],
                'lost_report_id'         => $r['lost_report_id'] ? (int) $r['lost_report_id'] : null,
                'lost_report_number'     => $r['lost_report_number'],
                'lost_report_status'     => $r['lost_report_status'],
                'handover_confirmed'     => true,
                'proposed_correction'    => [
                    'set_status'          => 'returned',
                    'set_storage_location'=> null,
                    'set_returned_at'     => $r['handed_over_at'] ?: date('Y-m-d H:i:s'),
                    'link_claim_item_id'  => (int) $r['inventory_id'],
                    'link_lost_item_id'   => (int) $r['inventory_id'],
                ],
            ];
        }

        return $results;
    }

    /**
     * Jalankan rekonsiliasi pada satu item spesifik
     *
     * @param int $itemId
     * @param bool $dryRun Default true untuk keamanan
     * @param int|null $authorizedUserId ID Petugas / Admin yang menjalankan rekonsiliasi
     * @return array{success: bool, dry_run: bool, message: string, details?: array<string, mixed>}
     */
    public function reconcileItem(int $itemId, bool $dryRun = true, ?int $authorizedUserId = null): array
    {
        // 1. Ambil data item
        $stmtItem = $this->pdo->prepare("SELECT * FROM items WHERE id = ?");
        $stmtItem->execute([$itemId]);
        $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return [
                'success' => false,
                'dry_run' => $dryRun,
                'message' => "Item dengan ID {$itemId} tidak ditemukan.",
            ];
        }

        // 2. Cari klaim terkait yang sudah selesai
        $stmtClaim = $this->pdo->prepare("
            SELECT c.* 
            FROM claims c
            LEFT JOIN found_reports fr ON c.found_report_id = fr.id
            WHERE (c.item_id = ? OR (c.found_report_id IS NOT NULL AND fr.item_id = ?))
              AND c.status = 'completed'
              AND c.handed_over_at IS NOT NULL
            ORDER BY c.id DESC 
            LIMIT 1
        ");
        $stmtClaim->execute([$itemId, $itemId]);
        $claim = $stmtClaim->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            return [
                'success' => false,
                'dry_run' => $dryRun,
                'message' => "Item ID {$itemId} tidak memenuhi syarat rekonsiliasi: tidak ditemukan transaksi serah terima klaim berstatus completed.",
            ];
        }

        // Cek apakah item sudah sinkron
        if ($item['status'] === 'returned' && $item['storage_location_id'] === null) {
            return [
                'success' => true,
                'dry_run' => $dryRun,
                'message' => "Item ID {$itemId} sudah sinkron (berstatus returned dan rak telah dilepaskan).",
                'details' => [
                    'item_id'     => $itemId,
                    'item_code'   => $item['item_code'],
                    'status'      => 'returned',
                    'returned_at' => $item['returned_at'],
                ],
            ];
        }

        $handoverDate = $claim['handed_over_at'] ?: date('Y-m-d H:i:s');
        $officerId = $authorizedUserId ?? (int) ($claim['handed_over_by_user_id'] ?? 2);

        // Jika Dry Run: jangan lakukan mutasi
        if ($dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'message' => "DRY RUN: Item ID {$itemId} ({$item['item_code']}) terverifikasi memenuhi syarat rekonsiliasi. Tidak ada data yang diubah.",
                'details' => [
                    'item_id'             => $itemId,
                    'item_code'           => $item['item_code'],
                    'old_status'          => $item['status'],
                    'new_status'          => 'returned',
                    'old_storage_id'      => $item['storage_location_id'],
                    'new_storage_id'      => null,
                    'handover_date'       => $handoverDate,
                    'claim_id'            => (int) $claim['id'],
                    'claim_number'        => $claim['claim_number'],
                ],
            ];
        }

        // Apply Mode: Eksekusi transaksi atomik
        $this->pdo->beginTransaction();
        try {
            // Lock row item
            $stmtLock = $this->pdo->prepare("SELECT id, status, storage_location_id FROM items WHERE id = ? FOR UPDATE");
            $stmtLock->execute([$itemId]);
            $lockedItem = $stmtLock->fetch(PDO::FETCH_ASSOC);

            $oldStatus = $lockedItem['status'] ?? $item['status'];
            $oldStorage = $lockedItem['storage_location_id'] ?? $item['storage_location_id'];

            // 1. Update items: status = 'returned', returned_at, storage_location_id = NULL
            $stmtUpd = $this->pdo->prepare("
                UPDATE items SET 
                    status = 'returned',
                    returned_at = COALESCE(returned_at, ?),
                    storage_location_id = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpd->execute([$handoverDate, $itemId]);

            // 2. Hubungkan claims.item_id jika masih NULL
            if (empty($claim['item_id'])) {
                $this->pdo->prepare("UPDATE claims SET item_id = ? WHERE id = ?")->execute([$itemId, $claim['id']]);
            }

            // 3. Hubungkan lost_reports.resolved_item_id jika masih NULL
            if (!empty($claim['lost_report_id'])) {
                $this->pdo->prepare("
                    UPDATE lost_reports 
                    SET status = 'resolved', 
                        resolved_item_id = COALESCE(resolved_item_id, ?), 
                        updated_at = NOW() 
                    WHERE id = ?
                ")->execute([$itemId, $claim['lost_report_id']]);
            }

            // 4. Catat status history item
            StatusHistoryService::record(
                'item',
                $itemId,
                $oldStatus,
                'returned',
                $officerId,
                "Rekonsiliasi serah terima: barang telah diserahkan ke pemilik via klaim {$claim['claim_number']} (rak {$oldStorage} dilepaskan)"
            );

            // 5. Catat audit log permanen
            AuditLogService::log(
                'INVENTORY_RECONCILIATION_APPLIED',
                $officerId,
                'items',
                $itemId,
                [
                    'item_code'      => $item['item_code'],
                    'claim_id'       => $claim['id'],
                    'claim_number'   => $claim['claim_number'],
                    'old_status'     => $oldStatus,
                    'new_status'     => 'returned',
                    'released_rack'  => $oldStorage,
                    'handover_date'  => $handoverDate,
                ]
            );

            $this->pdo->commit();

            return [
                'success' => true,
                'dry_run' => false,
                'message' => "Item ID {$itemId} ({$item['item_code']}) berhasil direkonsiliasi: status diperbarui menjadi 'returned' dan rak #{$oldStorage} dilepaskan.",
                'details' => [
                    'item_id'             => $itemId,
                    'item_code'           => $item['item_code'],
                    'old_status'          => $oldStatus,
                    'new_status'          => 'returned',
                    'released_storage_id' => $oldStorage,
                    'handover_date'       => $handoverDate,
                    'claim_id'            => (int) $claim['id'],
                    'claim_number'        => $claim['claim_number'],
                ],
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'dry_run' => false,
                'message' => "Gagal merekonsiliasi item ID {$itemId}: " . $e->getMessage(),
            ];
        }
    }
}

