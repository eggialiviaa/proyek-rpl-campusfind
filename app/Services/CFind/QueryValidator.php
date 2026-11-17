<?php

declare(strict_types=1);

namespace App\Services\CFind;

/**
 * QueryValidator — Validator Rencana Semantik Backend C-Find (Prompt 29)
 *
 * Menerapkan validasi JSON schema ketat:
 * 1. Menolak entitas atau kolom di luar hak akses role.
 * 2. Menolak field terlarang (password, token, bukti, catatan internal, dsb).
 * 3. Membatasi maksimal 3 query dan maksimal 50 baris data.
 * 4. Menyelesaikan rentang waktu simbolik (today, this_week) ke timestamp Asia/Jakarta.
 */
class QueryValidator
{
    private SemanticSchema $schema;

    public function __construct(SemanticSchema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Validasi rencana query secara menyeluruh
     *
     * @param array $plan
     * @param string $role
     * @return array{valid: bool, validated_plan?: array, error?: string, reason?: string}
     */
    public function validate(array $plan, string $role): array
    {
        if (!isset($plan['queries']) || !is_array($plan['queries'])) {
            return [
                'valid'  => false,
                'error'  => 'Rencana tidak memiliki daftar query yang valid.',
                'reason' => 'invalid_schema',
            ];
        }

        $queries = $plan['queries'];

        // Aturan Batas Maksimal Query
        if (count($queries) === 0) {
            return [
                'valid'  => false,
                'error'  => 'Permintaan tidak menghasilkan rencana query yang dapat dieksekusi.',
                'reason' => 'empty_queries',
            ];
        }

        if (count($queries) > 3) {
            return [
                'valid'  => false,
                'error'  => 'Rencana melebihi batas maksimal 3 query per pertanyaan.',
                'reason' => 'limit_exceeded',
            ];
        }

        $validatedQueries = [];

        foreach ($queries as $idx => $q) {
            if (!isset($q['entity']) || !is_string($q['entity'])) {
                return [
                    'valid'  => false,
                    'error'  => "Query index {$idx} tidak memiliki atribut entitas yang valid.",
                    'reason' => 'invalid_schema',
                ];
            }

            $entity = trim($q['entity']);

            // 1. Cek Hak Akses Entitas per Role
            if (!$this->schema->isEntityAllowed($role, $entity)) {
                return [
                    'valid'  => false,
                    'error'  => "Akses ke data entitas '{$entity}' berada di luar hak akses peran Anda.",
                    'reason' => 'denied_role',
                ];
            }

            $entityDef = $this->schema->getEntityDefinition($entity);
            if (!$entityDef) {
                return [
                    'valid'  => false,
                    'error'  => "Entitas '{$entity}' tidak terdaftar dalam skema sistem.",
                    'reason' => 'unknown_entity',
                ];
            }

            // 2. Validasi Kolom Select
            $selectFields = $q['select'] ?? [];
            if (!is_array($selectFields)) {
                $selectFields = [$selectFields];
            }

            $validSelect = [];
            foreach ($selectFields as $f) {
                $fieldStr = (string)$f;
                if ($this->schema->isForbidden($fieldStr)) {
                    return [
                        'valid'  => false,
                        'error'  => "Permintaan mengakses kolom terlarang ('{$fieldStr}').",
                        'reason' => 'forbidden_field',
                    ];
                }

                // Cek kolom di entitas (khusus non-virtual)
                if (empty($entityDef['virtual'])) {
                    if (!$this->schema->isFieldAllowed($role, $entity, $fieldStr) && $fieldStr !== '*' && $fieldStr !== 'count') {
                        return [
                            'valid'  => false,
                            'error'  => "Kolom '{$fieldStr}' pada entitas '{$entity}' tidak diizinkan untuk peran Anda.",
                            'reason' => 'denied_field',
                        ];
                    }
                }
                $validSelect[] = $fieldStr;
            }

            // 3. Validasi Filter & Resolusi Waktu Simbolik
            $filters = $q['filters'] ?? [];
            $validFilters = [];

            if (is_array($filters)) {
                foreach ($filters as $flt) {
                    if (!isset($flt['field']) || !isset($flt['operator'])) {
                        continue;
                    }

                    $fName = (string)$flt['field'];
                    if ($this->schema->isForbidden($fName)) {
                        return [
                            'valid'  => false,
                            'error'  => "Filter mengakses kolom terlarang ('{$fName}').",
                            'reason' => 'forbidden_field',
                        ];
                    }

                    $op = strtoupper(trim((string)$flt['operator']));
                    $allowedOps = ['=', '!=', '<', '<=', '>', '>=', 'IN', 'LIKE', 'DATE_RANGE', 'BETWEEN'];
                    if (!in_array($op, $allowedOps, true)) {
                        $op = '=';
                    }

                    $val = $flt['value'] ?? ($flt['range'] ?? null);

                    // Selesaikan rentang waktu simbolik jika ada
                    if ($op === 'DATE_RANGE' || in_array($val, ['today', 'hari_ini', 'yesterday', 'this_week', 'last_7_days', 'this_month'], true)) {
                        $dateBounds = $this->schema->resolveDateRange((string)$val);
                        if ($dateBounds['start'] !== null && $dateBounds['end'] !== null) {
                            $validFilters[] = [
                                'field'    => $fName,
                                'operator' => 'BETWEEN',
                                'value'    => [$dateBounds['start'], $dateBounds['end']],
                            ];
                            continue;
                        }
                    }

                    $validFilters[] = [
                        'field'    => $fName,
                        'operator' => $op,
                        'value'    => $val,
                    ];
                }
            }

            // 4. Validasi Group By
            $groupBy = $q['group_by'] ?? [];
            $validGroupBy = [];
            if (is_array($groupBy)) {
                foreach ($groupBy as $g) {
                    $gName = (string)$g;
                    if (!$this->schema->isForbidden($gName)) {
                        $validGroupBy[] = $gName;
                    }
                }
            }

            // 5. Validasi Limit (Default 10, Maksimal 50)
            $limit = isset($q['limit']) ? max(1, min((int)$q['limit'], 50)) : 10;

            // 6. Validasi Order
            $order = $q['order'] ?? [];

            $validatedQueries[] = [
                'entity'    => $entity,
                'select'    => $validSelect,
                'filters'   => $validFilters,
                'aggregate' => $q['aggregate'] ?? 'none',
                'group_by'  => $validGroupBy,
                'order'     => $order,
                'limit'     => $limit,
                'label'     => $q['label'] ?? ($entityDef['label'] ?? $entity),
            ];
        }

        $validatedPlan = $plan;
        $validatedPlan['queries'] = $validatedQueries;
        $validatedPlan['format']  = $plan['format'] ?? 'list';

        return [
            'valid'          => true,
            'validated_plan' => $validatedPlan,
        ];
    }
}
