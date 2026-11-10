<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Exceptions\CLIException;

/** Creates the first account without the destructive demonstration seeder. */
class InstallInitialize extends BaseCommand
{
    protected $group = 'Pindai.in';
    protected $name = 'install:initialize';
    protected $description = 'Membuat admin pertama pada database kosong (JSON lewat stdin).';

    public function run(array $params)
    {
        $db = null;
        try {
            $input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
            $school = trim((string) ($input['school_name'] ?? ''));
            $name = trim((string) ($input['full_name'] ?? ''));
            $username = trim((string) ($input['username'] ?? ''));
            $password = (string) ($input['password'] ?? '');
            if ($school === '' || mb_strlen($school) > 160 || $name === '' || mb_strlen($name) > 120
                || ! preg_match('/\A[A-Za-z0-9._-]{3,60}\z/', $username) || strlen($password) < 12 || strlen($password) > 128) {
                throw new \DomainException('Isi sekolah dan nama admin; username 3–60 karakter; kata sandi 12–128 karakter.');
            }
            $db = \Config\Database::connect();
            foreach (['users', 'items', 'borrowers', 'loans', 'settings'] as $table) {
                if ($db->table($table)->countAllResults() !== 0) {
                    throw new \DomainException('Inisialisasi ditolak: database sudah berisi data. Tidak ada data yang diubah.');
                }
            }
            $db->transBegin();
            $now = date('Y-m-d H:i:s');
            $db->table('users')->insert([
                'full_name' => $name, 'username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin', 'position' => 'Administrator', 'is_active' => 1, 'must_change_password' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $adminId = $db->insertID();
            foreach ([
                'school_name' => $school, 'school_address' => '', 'school_phone' => '', 'help_contact' => '',
                'kepala_sekolah_name' => '', 'kepala_sekolah_nip' => '',
                'letterhead_school_name' => $school, 'letterhead_government' => '', 'letterhead_department' => '',
                'letterhead_accreditation' => '', 'letterhead_contact' => '', 'letterhead_website' => '',
            ] as $key => $value) {
                $db->table('settings')->insert(['setting_key' => $key, 'setting_value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
            $db->table('activity_logs')->insert([
                'user_id' => $adminId, 'user_name' => $name, 'role' => 'admin', 'action' => 'instalasi_disiapkan',
                'module' => 'Sistem', 'object' => $school, 'detail' => 'Instalasi baru dan akun admin pertama dibuat. Tanpa data contoh.',
                'ip_address' => 'cli', 'created_at' => $now,
            ]);
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new \RuntimeException('Penyimpanan instalasi gagal.');
            }
            CLI::write('Instalasi baru berhasil disiapkan.');
        } catch (\Throwable $error) {
            $db?->transRollback();
            CLI::error($error instanceof \DomainException ? $error->getMessage() : 'Inisialisasi gagal. Periksa log lokal.');
            throw new CLIException('Instalasi belum selesai.', 1);
        }
    }
}
