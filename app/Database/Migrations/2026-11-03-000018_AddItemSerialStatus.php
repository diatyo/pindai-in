<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddItemSerialStatus extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('serial_status', 'items')) {
            $this->forge->addColumn('items', [
                'serial_status' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            ]);
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if (! $this->db->fieldExists('serial_status', 'items')) {
            return;
        }
        if ($this->db->table('items')->where('serial_status IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new \RuntimeException('Status nomor seri sudah digunakan. Pulihkan kode tanpa menghapus kolom ini.');
        }
        $this->forge->dropColumn('items', 'serial_status');
        $this->db->resetDataCache();
    }
}
