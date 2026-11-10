<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateItems extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'item_code'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'category'         => ['type' => 'VARCHAR', 'constraint' => 60],
            'item_name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'brand'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'model'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'serial_number'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'acquisition_date' => ['type' => 'DATE', 'null' => true],
            'location'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'specifications'   => ['type' => 'TEXT', 'null' => true],
            'status'           => ['type' => 'ENUM', 'constraint' => ['available', 'borrowed', 'damaged', 'in_handling'], 'default' => 'available'],
            'condition'        => ['type' => 'ENUM', 'constraint' => ['good', 'minor_damage', 'major_damage'], 'default' => 'good'],
            'physical_photo'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'serial_photo'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'qr_filename'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('item_code');
        $this->forge->addUniqueKey('serial_number');
        $this->forge->createTable('items');
    }

    public function down()
    {
        $this->forge->dropTable('items');
    }
}