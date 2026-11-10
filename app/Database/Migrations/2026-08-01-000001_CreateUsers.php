<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'full_name'     => ['type' => 'VARCHAR', 'constraint' => 120],
            'username'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'          => ['type' => 'ENUM', 'constraint' => ['admin', 'petugas', 'kepala_sekolah'], 'default' => 'petugas'],
            'position'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_login_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('users');
    }

    public function down()
    {
        $this->forge->dropTable('users');
    }
}