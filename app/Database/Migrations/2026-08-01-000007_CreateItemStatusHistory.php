<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateItemStatusHistory extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'item_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status_from'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'status_to'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'note'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'loan_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'damage_report_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'changed_by'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'changed_by_name'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('item_id');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('loan_id', 'loans', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('damage_report_id', 'damage_reports', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('item_status_history');
    }

    public function down()
    {
        $this->forge->dropTable('item_status_history');
    }
}