<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentSequences extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'prefix' => ['type' => 'VARCHAR', 'constraint' => 20],
            'sequence_value' => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addKey('prefix', true);
        $attributes = $this->db->DBDriver === 'SQLite3' ? [] : ['ENGINE' => 'InnoDB'];
        $this->forge->createTable('document_sequences', true, $attributes);
    }

    public function down()
    {
        $this->forge->dropTable('document_sequences', true);
    }
}
