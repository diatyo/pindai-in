<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBorrowers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'type'            => ['type' => 'ENUM', 'constraint' => ['teacher', 'student'], 'default' => 'teacher'],
            'identity_number' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 120],
            'gender'          => ['type' => 'ENUM', 'constraint' => ['Laki-laki', 'Perempuan'], 'null' => true],
            'class_name'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'phone'           => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'is_active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['type', 'identity_number']);
        $this->forge->createTable('borrowers');
    }

    public function down()
    {
        $this->forge->dropTable('borrowers');
    }
}