<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RenameAdminPosition extends Migration
{
    public function up()
    {
        $users = $this->db->prefixTable('users');
        $this->db->query("UPDATE `{$users}` SET `position` = REPLACE(`position`, 'Admin Dapodik', 'Admin') WHERE `position` LIKE '%Admin Dapodik%'");
    }

    public function down()
    {
        $users = $this->db->prefixTable('users');
        $this->db->query("UPDATE `{$users}` SET `position` = REPLACE(`position`, 'Admin', 'Admin Dapodik') WHERE `position` = 'Admin'");
    }
}