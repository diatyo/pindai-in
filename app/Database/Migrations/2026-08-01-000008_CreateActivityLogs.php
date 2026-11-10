<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'user_name'  => ['type' => 'VARCHAR', 'constraint' => 120],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 120],
            'module'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'object'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'detail'     => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->createTable('activity_logs');
    }

    public function down()
    {
        $this->forge->dropTable('activity_logs');
    }
}