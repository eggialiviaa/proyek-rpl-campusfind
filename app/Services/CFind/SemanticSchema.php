<?php

declare(strict_types=1);

namespace App\Services\CFind;

use DateTimeImmutable;
use DateTimeZone;

/**
 * SemanticSchema — Lapisan Akses Skema Semantik per Role (Prompt 29)
 *
 * Mengatur isolasi entitas & kolom per role, deskripsi semantik untuk AI Planner,
 * dan penyelesaian rentang waktu simbolik menggunakan timezone Asia/Jakarta.
 */
class SemanticSchema
{
    private array $schema;
    private DateTimeZone $timezone;

    public function __construct(?array $schema = null)
    {
        $this->schema = $schema ?? require dirname(__DIR__, 2) . '/config/cfind_schema.php';
        $this->timezone = new DateTimeZone('Asia/Jakarta');
    }

    /**
     * Dapatkan daftar entitas yang diizinkan untuk role tertentu
     *
     * @param string $role
     * @return array<string>
     */
    public function getAllowedEntities(string $role): array
    {
        $normalizedRole = strtolower(trim($role));
        return $this->schema['role_permissions'][$normalizedRole] ?? [];
    }

    /**
     * Cek apakah suatu entitas diizinkan untuk role tertentu
     */
    public function isEntityAllowed(string $role, string $entity): bool
    {
        $allowed = $this->getAllowedEntities($role);
        return in_array($entity, $allowed, true);
    }

    /**
     * Dapatkan definisi entitas
     */
    public function getEntityDefinition(string $entity): ?array
    {
        if (!isset($this->schema['entities'][$entity])) {
            return null;
        }

        $def = $this->schema['entities'][$entity];
        if (isset($def['extends']) && isset($this->schema['entities'][$def['extends']])) {
            $parent = $this->schema['entities'][$def['extends']];
            $def = array_replace_recursive($parent, $def);
        }

        return $def;
    }

    /**
     * Cek apakah field terlarang (blacklist absolut)
     */
    public function isForbidden(string $field): bool
    {
        $clean = strtolower(trim($field));
        $forbidden = $this->schema['forbidden_fields'] ?? [];
        foreach ($forbidden as $bad) {
            if ($clean === $bad || str_contains($clean, $bad)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cek apakah field diizinkan untuk entitas dan role tertentu
     */
    public function isFieldAllowed(string $role, string $entity, string $field): bool
    {
        if ($this->isForbidden($field)) {
            return false;
        }

        if (!$this->isEntityAllowed($role, $entity)) {
            return false;
        }

        $def = $this->getEntityDefinition($entity);
        if (!$def || !isset($def['fields'][$field])) {
            return false;
        }

        $fieldDef = $def['fields'][$field];
        if (isset($fieldDef['restricted_to'])) {
            $normalizedRole = strtolower(trim($role));
            if (!in_array($normalizedRole, $fieldDef['restricted_to'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Buat deskripsi ringkas skema semantik khusus role untuk prompt AI Planner (0 data baris)
     */
    public function getRoleSchemaPromptDescription(string $role): string
    {
        $allowedEntities = $this->getAllowedEntities($role);
        if (empty($allowedEntities)) {
            return "Role Anda (Tamu/Guest) tidak memiliki hak akses data operasional.";
        }

        $lines = [];
        $lines[] = "Daftar entitas dan field yang diizinkan untuk role '{$role}':";

        foreach ($allowedEntities as $entKey) {
            $def = $this->getEntityDefinition($entKey);
            if (!$def) continue;

            $label = $def['label'] ?? $entKey;
            $desc = $def['description'] ?? '';
            $lines[] = "- Entity: `{$entKey}` ({$label}" . ($desc ? " — {$desc}" : "") . ")";

            $fieldsDesc = [];
            foreach (($def['fields'] ?? []) as $fKey => $fDef) {
                if (!$this->isFieldAllowed($role, $entKey, $fKey)) {
                    continue;
                }
                $type = $fDef['type'] ?? 'string';
                $fLabel = $fDef['label'] ?? $fKey;
                $synonyms = !empty($fDef['synonyms']) ? " [sinonim: " . implode(', ', $fDef['synonyms']) . "]" : "";
                $fieldsDesc[] = "  * `{$fKey}` ({$type}): {$fLabel}{$synonyms}";
            }
            $lines = array_merge($lines, $fieldsDesc);
        }

        return implode("\n", $lines);
    }

    /**
     * Hitung rentang waktu simbolik menggunakan timezone Asia/Jakarta
     *
     * @param string $range 'today'|'yesterday'|'this_week'|'last_7_days'|'this_month'|'all'
     * @return array{start: ?string, end: ?string}
     */
    public function resolveDateRange(string $range): array
    {
        $now = new DateTimeImmutable('now', $this->timezone);

        return match (strtolower(trim($range))) {
            'today', 'hari_ini' => [
                'start' => $now->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                'end'   => $now->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            ],
            'yesterday', 'kemarin' => [
                'start' => $now->modify('-1 day')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                'end'   => $now->modify('-1 day')->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            ],
            'this_week', 'minggu_ini' => [
                'start' => $now->modify('this week monday')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                'end'   => $now->modify('this week sunday')->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            ],
            'last_7_days', '7_hari_terakhir' => [
                'start' => $now->modify('-7 days')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                'end'   => $now->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            ],
            'this_month', 'bulan_ini' => [
                'start' => $now->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                'end'   => $now->modify('last day of this month')->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            ],
            default => [
                'start' => null,
                'end'   => null,
            ],
        };
    }
}
