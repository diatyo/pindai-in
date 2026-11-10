<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAuthSecurity extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('auth_version', 'users')) {
            $this->forge->addColumn('users', ['auth_version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1]]);
        }
        if (! $this->db->tableExists('auth_login_limits')) {
            $this->forge->addField([
                'bucket_key' => ['type' => 'VARCHAR', 'constraint' => 64],
                'scope' => ['type' => 'VARCHAR', 'constraint' => 10],
                'failures' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'window_started' => ['type' => 'BIGINT'],
                'blocked_until' => ['type' => 'BIGINT', 'default' => 0],
                'expires_at' => ['type' => 'BIGINT'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('bucket_key', true);
            $this->forge->addKey('expires_at');
            $this->forge->createTable('auth_login_limits');
        }
    }

    public function down()
    {
        $this->forge->dropTable('auth_login_limits', true);
        if ($this->db->fieldExists('auth_version', 'users')) {
            $this->forge->dropColumn('users', 'auth_version');
        }
    }
}
