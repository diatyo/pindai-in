<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLoans extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'loan_number'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'item_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'borrower_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'borrowed_at'      => ['type' => 'DATETIME'],
            'due_at'           => ['type' => 'DATETIME'],
            'purpose'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'usage_location'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'condition_out'    => ['type' => 'ENUM', 'constraint' => ['good', 'minor_damage', 'major_damage'], 'default' => 'good'],
            'completeness_out' => ['type' => 'ENUM', 'constraint' => ['lengkap', 'tidak_lengkap'], 'default' => 'lengkap'],
            'processed_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'returned_at'      => ['type' => 'DATETIME', 'null' => true],
            'condition_in'     => ['type' => 'ENUM', 'constraint' => ['good', 'minor_damage', 'major_damage'], 'null' => true],
            'completeness_in'  => ['type' => 'ENUM', 'constraint' => ['lengkap', 'tidak_lengkap'], 'null' => true],
            'return_photo'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'return_notes'     => ['type' => 'TEXT', 'null' => true],
            'returned_by'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('loan_number');
        $this->forge->addKey('item_id');
        $this->forge->addKey('borrower_id');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('borrower_id', 'borrowers', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('processed_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('returned_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('loans');
    }

    public function down()
    {
        $this->forge->dropTable('loans');
    }
}