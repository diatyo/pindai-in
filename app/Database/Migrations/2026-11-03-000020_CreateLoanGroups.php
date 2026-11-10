<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLoanGroups extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'group_number' => ['type' => 'VARCHAR', 'constraint' => 30],
            'request_key' => ['type' => 'VARCHAR', 'constraint' => 32],
            'created_by' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('group_number');
        $this->forge->addUniqueKey(['created_by', 'request_key']);
        $this->forge->createTable('loan_groups', true, ['ENGINE' => 'InnoDB']);

        if (! $this->db->fieldExists('loan_group_id', 'loans')) {
            $groupTable = $this->db->escapeIdentifiers($this->db->prefixTable('loan_groups'));
            if ($this->db->DBDriver === 'SQLite3') {
                $this->forge->addColumn('loans', ['loan_group_id INTEGER NULL REFERENCES ' . $groupTable . ' (id)']);
            } else {
                $loans = $this->db->escapeIdentifiers($this->db->prefixTable('loans'));
                $this->db->query('ALTER TABLE ' . $loans . ' ADD COLUMN loan_group_id INT UNSIGNED NULL, '
                    . 'ADD CONSTRAINT loans_loan_group_fk FOREIGN KEY (loan_group_id) REFERENCES ' . $groupTable . ' (id)');
            }
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if ($this->db->table('loan_groups')->countAllResults() > 0) {
            throw new \RuntimeException('Riwayat peminjaman kelompok sudah tercatat. Pulihkan kode tanpa menghapus riwayat.');
        }
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropForeignKey('loans', 'loans_loan_group_fk');
        }
        $this->forge->dropColumn('loans', 'loan_group_id');
        $this->forge->dropTable('loan_groups', true);
        $this->db->resetDataCache();
    }
}
