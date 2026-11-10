<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHandlingActions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'damage_report_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'action_date'        => ['type' => 'DATE'],
            'action_description' => ['type' => 'TEXT'],
            'executor_name'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'result_condition'   => ['type' => 'ENUM', 'constraint' => ['good', 'minor_damage', 'major_damage'], 'null' => true],
            'status'             => ['type' => 'ENUM', 'constraint' => ['in_progress', 'completed', 'unrepairable'], 'default' => 'in_progress'],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('damage_report_id');
        $this->forge->addForeignKey('damage_report_id', 'damage_reports', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('handling_actions');
    }

    public function down()
    {
        $this->forge->dropTable('handling_actions');
    }
}