<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAccessoryChecklists extends Migration
{
    public function up()
    {
        $this->db->resetDataCache();
        $id = ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true];
        $integer = ['type' => 'INT', 'unsigned' => true];
        $this->forge->addField([
            'id' => $id, 'item_id' => $integer,
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'quantity' => $integer + ['default' => 1],
            'is_required' => ['type' => 'TINYINT', 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('item_id');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('item_accessories', true);

        $this->forge->addField([
            'id' => $id, 'loan_id' => $integer,
            'source_accessory_id' => $integer + ['null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'quantity' => $integer,
            'is_required' => ['type' => 'TINYINT', 'default' => 0],
            'quantity_returned' => $integer + ['default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('loan_id');
        // source_accessory_id deliberately has no FK: master changes cannot alter a historical snapshot.
        $this->forge->addForeignKey('loan_id', 'loans', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('loan_accessories', true);

        $this->forge->addField([
            'id' => $id, 'loan_accessory_id' => $integer,
            'quantity' => $integer,
            'kind' => ['type' => 'VARCHAR', 'constraint' => 20],
            'note' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'user_id' => $integer + ['null' => true],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'request_key' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('request_key', false, true);
        $this->forge->addForeignKey('loan_accessory_id', 'loan_accessories', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('loan_accessory_events', true);
        if (! $this->db->fieldExists('accessory_checklist', 'loans')) {
            $this->forge->addColumn('loans', ['accessory_checklist' => ['type' => 'TINYINT', 'default' => 0]]);
        }
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->modifyColumn('items', ['status' => ['type' => 'ENUM',
                'constraint' => ['available', 'borrowed', 'damaged', 'in_handling', 'disabled', 'incomplete'], 'default' => 'available']]);
        }
        $this->db->resetDataCache();
    }

    public function down()
    {
        if ($this->db->table('loan_accessories')->countAllResults() > 0
            || $this->db->table('item_accessories')->countAllResults() > 0
            || $this->db->table('loans')->where('accessory_checklist', 1)->countAllResults() > 0) {
            throw new \RuntimeException('Rollback ditolak: data pemeriksaan sudah digunakan. Pulihkan kode tanpa menghapus riwayat.');
        }
        $this->forge->dropTable('loan_accessory_events', true);
        $this->forge->dropTable('loan_accessories', true);
        $this->forge->dropTable('item_accessories', true);
        $this->forge->dropColumn('loans', 'accessory_checklist');
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->modifyColumn('items', ['status' => ['type' => 'ENUM',
                'constraint' => ['available', 'borrowed', 'damaged', 'in_handling', 'disabled'], 'default' => 'available']]);
        }
    }
}
