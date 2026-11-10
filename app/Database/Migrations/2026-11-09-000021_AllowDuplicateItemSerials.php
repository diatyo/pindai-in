<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowDuplicateItemSerials extends Migration
{
    public function up()
    {
        foreach ($this->db->getIndexData('items') as $index) {
            if ($index->fields === ['serial_number'] && $index->type === 'UNIQUE') {
                if (! $this->forge->dropKey('items', $index->name, false)) {
                    throw new \RuntimeException('Batas unik nomor seri gagal diubah.');
                }
            }
        }
        $this->db->resetDataCache();
        foreach ($this->db->getIndexData('items') as $index) {
            if ($index->fields === ['serial_number']) return;
        }
        $this->forge->addKey('serial_number', false, false, 'items_serial_lookup');
        if (! $this->forge->processIndexes('items')) {
            throw new \RuntimeException('Indeks pencarian nomor seri gagal dibuat.');
        }
    }

    public function down()
    {
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('items'));
        if ($this->db->query('SELECT serial_number FROM ' . $table . ' WHERE serial_number IS NOT NULL GROUP BY serial_number HAVING COUNT(*) > 1 LIMIT 1')->getNumRows()) {
            throw new \RuntimeException('Nomor seri duplikat sudah digunakan. Pertahankan riwayat barang.');
        }
        $this->forge->addUniqueKey('serial_number', 'items_serial_unique');
        $this->forge->processIndexes('items');
    }
}
