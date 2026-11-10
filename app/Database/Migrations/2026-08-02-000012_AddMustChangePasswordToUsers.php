<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMustChangePasswordToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'must_change_password');
    }
}
