<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBorrowerPortalRole extends Migration
{
    public function up()
    {
        $users = $this->db->prefixTable('users');
        $borrowers = $this->db->prefixTable('borrowers');

        $this->db->query("ALTER TABLE `{$users}` MODIFY `role` ENUM('admin','petugas','kepala_sekolah','peminjam') NOT NULL DEFAULT 'petugas'");
        $this->db->query("ALTER TABLE `{$users}` ADD COLUMN `borrower_id` INT(11) UNSIGNED NULL AFTER `role`");
        $this->db->query("ALTER TABLE `{$users}` ADD UNIQUE KEY `users_borrower_id` (`borrower_id`)");
        $this->db->query("ALTER TABLE `{$users}` ADD CONSTRAINT `users_borrower_id_foreign` FOREIGN KEY (`borrower_id`) REFERENCES `{$borrowers}`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE");
    }

    public function down()
    {
        $users = $this->db->prefixTable('users');

        $this->db->query("ALTER TABLE `{$users}` DROP FOREIGN KEY `users_borrower_id_foreign`");
        $this->db->query("ALTER TABLE `{$users}` DROP INDEX `users_borrower_id`");
        $this->db->query("ALTER TABLE `{$users}` DROP COLUMN `borrower_id`");
        $this->db->query("DELETE FROM `{$users}` WHERE `role` = 'peminjam'");
        $this->db->query("ALTER TABLE `{$users}` MODIFY `role` ENUM('admin','petugas','kepala_sekolah') NOT NULL DEFAULT 'petugas'");
    }
}
