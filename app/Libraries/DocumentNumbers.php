<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class DocumentNumbers
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function next(string $kind): string
    {
        if ($this->db->transDepth < 1) {
            throw new RuntimeException('Nomor harus diambil dalam transaksi penyimpanan.');
        }

        [$prefix, $source, $column, $width] = match ($kind) {
            'item' => ['UNT-', 'items', 'item_code', 6],
            'loan' => ['PMJ-' . date('Ym') . '-', 'loans', 'loan_number', 3],
            'loan_group' => ['PMG-' . date('Ym') . '-', 'loan_groups', 'group_number', 3],
            'damage' => ['DR-' . date('Ym') . '-', 'damage_reports', 'report_number', 3],
        };

        $sqlite = $this->db->DBDriver === 'SQLite3';
        $column = $this->db->escapeIdentifiers($column);
        $suffix = 'SUBSTR(' . $column . ', ' . (strlen($prefix) + 1) . ')';
        $cast = $sqlite ? 'INTEGER' : 'UNSIGNED';
        $legacy = $this->db->table($source)
            ->select("MAX(CAST({$suffix} AS {$cast})) AS highest", false)
            ->like($column, $prefix, 'after');
        if ($sqlite) {
            $legacy->where("{$suffix} <> '' AND {$suffix} NOT GLOB '*[^0-9]*'", null, false);
        } else {
            $legacy->where("{$suffix} REGEXP '^[0-9]+$'", null, false);
        }
        $highest = (int) ($legacy->get()->getRow()->highest ?? 0);
        if ($highest >= PHP_INT_MAX) {
            throw new RuntimeException('Rentang nomor sudah habis.');
        }

        // The upsert holds the prefix row lock until the caller commits or rolls back.
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('document_sequences'));
        $update = $sqlite
            ? 'ON CONFLICT(prefix) DO UPDATE SET sequence_value = MAX(sequence_value + 1, ?)'
            : 'ON DUPLICATE KEY UPDATE sequence_value = GREATEST(sequence_value + 1, ?)';
        if ($this->db->query("INSERT INTO {$table} (prefix, sequence_value) VALUES (?, ?) {$update}", [$prefix, $highest + 1, $highest + 1]) === false) {
            throw new RuntimeException('Nomor transaksi gagal diambil.');
        }
        $value = $this->db->table('document_sequences')->where('prefix', $prefix)->get()->getRow()->sequence_value;
        return $prefix . str_pad((string) $value, $width, '0', STR_PAD_LEFT);
    }
}
