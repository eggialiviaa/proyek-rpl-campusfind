<?php

declare(strict_types=1);

namespace App\Services\CFind;

use App\Services\ItemCatalogService;
use Core\Database;
use PDO;
use Throwable;

/**
 * QueryCompiler — Kompilator Rencana Semantik ke SQL Ter-parameter & Eksekutor Aman (Prompt 29)
 *
 * Mengubah AST rencana query yang sudah tervalidasi menjadi prepared statement SQL,
 * menerapkan paksa filter isolasi role/user_id, dan mengeksekusi dengan guard SELECT murni.
 */
class QueryCompiler
{
    private PDO $pdo;
    private SemanticSchema $schema;
    private ItemCatalogService $catalogService;

    public function __construct(PDO $pdo, SemanticSchema $schema, ?ItemCatalogService $catalogService = null)
    {
        $this->pdo = $pdo;
        $this->schema = $schema;
        $this->catalogService = $catalogService ?? new ItemCatalogService($this->pdo);
    }

    /**
     * Eksekusi seluruh query dalam rencana tervalidasi
     *
     * @param array $validatedPlan
     * @param string $role
     * @param int|null $userId
     * @return array<int, array{entity: string, label: string, aggregate: string, count?: int, columns: array, rows: array}>
     */
    public function executePlan(array $validatedPlan, string $role, ?int $userId): array
    {
        $results = [];

        foreach ($validatedPlan['queries'] as $q) {
            $entityKey = $q['entity'];
            $entityDef = $this->schema->getEntityDefinition($entityKey);

            // Penanganan Khusus Virtual Entity (Katalog Publik Cari Barang)
            if (!empty($entityDef['virtual']) && $entityKey === 'public_catalog') {
                $results[] = $this->executePublicCatalogQuery($q);
                continue;
            }

            $compiled = $this->compileSingleQuery($q, $role, $userId);
            $results[] = $this->runCompiledSql($compiled, $q);
        }

        return $results;
    }

    /**
     * Kompilasi 1 query semantik menjadi SQL ter-parameter
     */
    private function compileSingleQuery(array $query, string $role, ?int $userId): array
    {
        $entityKey = $query['entity'];
        $entityDef = $this->schema->getEntityDefinition($entityKey);

        $table = $entityDef['table'];
        $joins = $entityDef['joins'] ?? [];
        $fieldsDef = $entityDef['fields'] ?? [];

        $params = [];
        $whereClauses = [];

        // 1. SELECT clause & Agregat
        $isCountOnly = ($query['aggregate'] === 'count' && empty($query['group_by']));
        $selectClauses = [];
        $columnLabels = [];

        if ($isCountOnly) {
            $selectClauses[] = "COUNT(*) AS total_count";
            $columnLabels['total_count'] = 'Total';
        } else {
            // Group by columns
            foreach ($query['group_by'] ?? [] as $gField) {
                if (isset($fieldsDef[$gField])) {
                    $colExpr = $fieldsDef[$gField]['column'] ?? "{$table}.{$gField}";
                    $selectClauses[] = "{$colExpr} AS `{$gField}`";
                    $columnLabels[$gField] = $fieldsDef[$gField]['label'] ?? $gField;
                }
            }

            if ($query['aggregate'] === 'count' && !empty($query['group_by'])) {
                $selectClauses[] = "COUNT(*) AS `total_count`";
                $columnLabels['total_count'] = 'Jumlah';
            } else {
                foreach ($query['select'] as $selField) {
                    if (isset($fieldsDef[$selField])) {
                        $colExpr = $fieldsDef[$selField]['column'] ?? "{$table}.{$selField}";
                        $selectClauses[] = "{$colExpr} AS `{$selField}`";
                        $columnLabels[$selField] = $fieldsDef[$selField]['label'] ?? $selField;
                    }
                }
            }
        }

        if (empty($selectClauses)) {
            $selectClauses[] = "{$table}.id";
            $columnLabels['id'] = 'ID';
        }

        // 2. JOIN clauses
        $joinSql = '';
        foreach ($joins as $alias => $jDef) {
            $jTable = $jDef['table'];
            $fKey = $jDef['foreign_key'];
            $tKey = $jDef['target_key'];
            $type = $jDef['type'] ?? 'LEFT';
            $joinSql .= " {$type} JOIN `{$jTable}` AS `{$alias}` ON `{$table}`.`{$fKey}` = `{$alias}`.`{$tKey}`";
        }

        // 3. Paksa Filter Row-Level Security per Role
        $roleScope = $entityDef['row_scope'][$role] ?? '1=1';
        if ($roleScope !== '1=1') {
            if (str_contains($roleScope, ':session_user_id')) {
                $whereClauses[] = str_replace(':session_user_id', '?', $roleScope);
                $params[] = $userId ?? 0;
            } else {
                $whereClauses[] = $roleScope;
            }
        }

        // 4. Default Filter dari Entitas (misal items status = 'stored')
        if (!empty($entityDef['default_filter'])) {
            $whereClauses[] = $entityDef['default_filter'];
        }

        // 5. User-Supplied Filters dari Planner
        foreach ($query['filters'] ?? [] as $flt) {
            $fName = $flt['field'];
            $op = strtoupper($flt['operator']);
            $val = $flt['value'];

            if (!isset($fieldsDef[$fName])) {
                continue;
            }

            $colExpr = $fieldsDef[$fName]['column'] ?? "{$table}.{$fName}";

            if ($op === 'BETWEEN' && is_array($val) && count($val) === 2) {
                $whereClauses[] = "{$colExpr} BETWEEN ? AND ?";
                $params[] = $val[0];
                $params[] = $val[1];
            } elseif ($op === 'IN' && is_array($val)) {
                $placeholders = implode(',', array_fill(0, count($val), '?'));
                $whereClauses[] = "{$colExpr} IN ({$placeholders})";
                foreach ($val as $v) $params[] = $v;
            } else {
                $whereClauses[] = "{$colExpr} {$op} ?";
                $params[] = $val;
            }
        }

        $sql = "SELECT " . implode(', ', $selectClauses) . " FROM `{$table}`" . $joinSql;

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        // 6. Group By
        if (!empty($query['group_by'])) {
            $groupExprs = [];
            foreach ($query['group_by'] as $g) {
                if (isset($fieldsDef[$g])) {
                    $groupExprs[] = $fieldsDef[$g]['column'] ?? "{$table}.{$g}";
                }
            }
            if (!empty($groupExprs)) {
                $sql .= " GROUP BY " . implode(', ', $groupExprs);
            }
        }

        // 7. ORDER BY
        if (!$isCountOnly) {
            $orderClauses = [];
            foreach ($query['order'] ?? [] as $ord) {
                $oField = $ord['field'];
                $dir = strtoupper($ord['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

                if ($oField === 'count' || $oField === 'total_count') {
                    $orderClauses[] = "total_count {$dir}";
                } elseif (isset($fieldsDef[$oField])) {
                    $colExpr = $fieldsDef[$oField]['column'] ?? "{$table}.{$oField}";
                    $orderClauses[] = "{$colExpr} {$dir}";
                }
            }

            if (!empty($orderClauses)) {
                $sql .= " ORDER BY " . implode(', ', $orderClauses);
            } elseif (!empty($entityDef['default_order'])) {
                $sql .= " ORDER BY " . $entityDef['default_order'];
            }
        }

        $baseSqlWithoutLimit = $sql;

        // 8. LIMIT (maksimal 50 baris untuk tampilan tabel frontend)
        if (!$isCountOnly) {
            $limit = min((int)($query['limit'] ?? 50), 50);
            $sql .= " LIMIT {$limit}";
        }

        return [
            'sql'           => $sql,
            'count_sql'     => $isCountOnly ? null : "SELECT COUNT(*) FROM ({$baseSqlWithoutLimit}) AS _cnt_tbl",
            'params'        => $params,
            'column_labels' => $columnLabels,
            'is_count'      => $isCountOnly,
        ];
    }

    /**
     * Eksekusi SQL yang sudah dikompilasi
     */
    private function runCompiledSql(array $compiled, array $query): array
    {
        $sql = $compiled['sql'];
        $params = $compiled['params'];

        // Enforce SELECT ONLY
        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            return [
                'entity'    => $query['entity'],
                'label'     => $query['label'] ?? $query['entity'],
                'aggregate' => $query['aggregate'] ?? 'none',
                'columns'   => [],
                'rows'      => [],
                'error'     => 'Query ditolak: hanya operasi SELECT yang diizinkan.',
            ];
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ($compiled['is_count']) {
                $count = (int) $stmt->fetchColumn();
                return [
                    'entity'    => $query['entity'],
                    'label'     => $query['label'] ?? $query['entity'],
                    'aggregate' => 'count',
                    'count'     => $count,
                    'columns'   => ['total_count' => 'Total'],
                    'rows'      => [['total_count' => $count]],
                    'total'     => $count,
                ];
            }

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalCount = count($rows);
            if (!empty($compiled['count_sql'])) {
                try {
                    $cStmt = $this->pdo->prepare($compiled['count_sql']);
                    $cStmt->execute($params);
                    $totalCount = (int) $cStmt->fetchColumn();
                } catch (Throwable $e) {
                    $totalCount = count($rows);
                }
            }

            return [
                'entity'    => $query['entity'],
                'label'     => $query['label'] ?? $query['entity'],
                'aggregate' => $query['aggregate'] ?? 'none',
                'columns'   => $compiled['column_labels'],
                'rows'      => $rows,
                'total'     => $totalCount,
            ];
        } catch (Throwable $e) {
            return [
                'entity'    => $query['entity'],
                'label'     => $query['label'] ?? $query['entity'],
                'aggregate' => $query['aggregate'] ?? 'none',
                'columns'   => [],
                'rows'      => [],
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Eksekusi virtual entity public_catalog melalui ItemCatalogService
     */
    private function executePublicCatalogQuery(array $query): array
    {
        $isCount = ($query['aggregate'] === 'count');

        if ($isCount) {
            $count = $this->catalogService->getPublicCatalogCount();
            return [
                'entity'    => 'public_catalog',
                'label'     => 'Laporan Aktif di Katalog Publik',
                'aggregate' => 'count',
                'count'     => $count,
                'columns'   => ['total_count' => 'Total'],
                'rows'      => [['total_count' => $count]],
            ];
        }

        $limit = min((int)($query['limit'] ?? 10), 50);
        $items = $this->catalogService->searchPublicCatalog([], $limit, 0);

        $rows = [];
        foreach ($items as $it) {
            $rows[] = [
                'title'         => $it['title'] ?? '-',
                'type'          => ($it['type'] ?? '') === 'lost' ? 'Barang Hilang' : 'Barang Temuan',
                'category_name' => $it['category_name'] ?? '-',
                'location_name' => $it['location_name'] ?? '-',
                'status'        => $it['status'] ?? '-',
            ];
        }

        return [
            'entity'    => 'public_catalog',
            'label'     => 'Katalog Barang Publik',
            'aggregate' => 'none',
            'columns'   => [
                'title'         => 'Nama Barang',
                'type'          => 'Tipe Laporan',
                'category_name' => 'Kategori',
                'location_name' => 'Lokasi',
                'status'        => 'Status',
            ],
            'rows'      => $rows,
        ];
    }
}
