<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDamageReports extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'report_number'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'item_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'loan_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'discovered_at'  => ['type' => 'DATETIME'],
            'damage_level'   => ['type' => 'ENUM', 'constraint' => ['minor_damage', 'major_damage'], 'default' => 'minor_damage'],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'damage_photo'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notes'          => ['type' => 'TEXT', 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['reported', 'in_handling', 'completed', 'unrepairable'], 'default' => 'reported'],
            'reported_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('report_number');
        $this->forge->addKey('item_id');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('loan_id', 'loans', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('reported_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('damage_reports');
    }

    public function down()
    {
        $this->forge->dropTable('damage_reports');
    }
}
