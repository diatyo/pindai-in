<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SimplifyAdminRole extends Migration
{
    public function up()
    {
        $users = $this->db->prefixTable('users');
        $logs = $this->db->prefixTable('activity_logs');
        $settings = $this->db->prefixTable('settings');

        $this->db->query("ALTER TABLE `{$users}` MODIFY `role` ENUM('admin_dapodik','admin','petugas','kepala_sekolah') NOT NULL DEFAULT 'petugas'");
        $this->db->query("UPDATE `{$users}` SET `role` = 'admin' WHERE `role` = 'admin_dapodik'");
        $this->db->query("ALTER TABLE `{$users}` MODIFY `role` ENUM('admin','petugas','kepala_sekolah') NOT NULL DEFAULT 'petugas'");
        $this->db->query("UPDATE `{$logs}` SET `role` = 'admin' WHERE `role` = 'admin_dapodik'");
        $this->db->query("UPDATE `{$settings}` SET `setting_value` = REPLACE(`setting_value`, 'Admin Dapodik', 'Admin') WHERE `setting_key` = 'help_contact'");
    }

    public function down()
    {
        $users = $this->db->prefixTable('users');
        $logs = $this->db->prefixTable('activity_logs');
        $settings = $this->db->prefixTable('settings');

        $this->db->query("ALTER TABLE `{$users}` MODIFY `role` ENUM('admin','admin_dapodik','petugas','kepala_sekolah') NOT NULL DEFAULT 'petugas'");
        $this->db->query("UPDATE `{$users}` SET `role` = 'admin_dapodik' WHERE `role` = 'admin'");
        $this->db->query("ALTER TABLE `{$users}` MODIFY `role` ENUM('admin_dapodik','petugas','kepala_sekolah') NOT NULL DEFAULT 'petugas'");
        $this->db->query("UPDATE `{$logs}` SET `role` = 'admin_dapodik' WHERE `role` = 'admin'");
        $this->db->query("UPDATE `{$settings}` SET `setting_value` = REPLACE(`setting_value`, 'Hubungi Admin (', 'Hubungi Admin Dapodik (') WHERE `setting_key` = 'help_contact'");
    }
}