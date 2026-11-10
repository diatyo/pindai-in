<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Legacy demo entry point intentionally refuses to alter any database. */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        throw new \RuntimeException('Seeder demo dinonaktifkan. Gunakan php spark install:initialize untuk menyiapkan database kosong dengan akun Anda sendiri.');
    }
}
