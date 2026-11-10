<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/** Small, retryable batches: accounts and their audit rows commit together. */
class BorrowerAccountGenerator
{
    public const BATCH_SIZE = 20;

    public function __construct(private BaseConnection $db, private ?\Closure $hasher = null)
    {
    }

    public function generate(array $ids): array
    {
        helper('activity');
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        if (! $ids || min($ids) < 1 || count($ids) > self::BATCH_SIZE) {
            throw new \InvalidArgumentException('Pilih 1–20 data per permintaan.');
        }
        $created = $skipped = [];
        if (! $this->db->transBegin()) throw new \RuntimeException('Transaksi tidak tersedia.');
        try {
            // The same borrower cannot be generated twice by concurrent administrators.
            $query = $this->db->table('borrowers')->whereIn('id', $ids)->orderBy('id')->getCompiledSelect();
            $borrowers = $this->db->query($query . ' FOR UPDATE')->getResult();
            $found = [];
            foreach ($borrowers as $borrower) {
                $found[] = (int) $borrower->id;
                $row = ['id' => (int) $borrower->id, 'name' => $borrower->name];
                $username = trim((string) $borrower->identity_number);
                $reason = null;
                if (! $borrower->is_active) $reason = 'Data peminjam nonaktif';
                elseif ($this->db->table('users')->where('borrower_id', $borrower->id)->countAllResults()) $reason = 'Sudah memiliki akun';
                elseif ($username === '' || ! preg_match('/\A[a-zA-Z0-9._-]{1,60}\z/D', $username)) $reason = 'NIP/NISN tidak valid';
                elseif ($this->db->table('users')->where('username', $username)->countAllResults()) $reason = 'NIP/NISN sudah dipakai akun lain';
                if ($reason !== null) {
                    $skipped[] = $row + ['reason' => $reason];
                    continue;
                }
                $hash = $this->hasher ? ($this->hasher)($username) : password_hash($username, PASSWORD_DEFAULT);
                $type = $borrower->type === 'teacher' ? 'Guru' : 'Siswa';
                if (! $this->db->table('users')->insert([
                    'full_name' => $borrower->name, 'username' => $username, 'password_hash' => $hash,
                    'must_change_password' => 1, 'role' => 'peminjam', 'borrower_id' => $borrower->id,
                    'position' => $type, 'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ])) throw new \RuntimeException('Akun gagal disimpan.');
                log_activity('akun_peminjam_dibuat', 'Akun', $username, 'Akun Peminjam ' . $type . ' dibuat otomatis');
                $created[] = $row + ['type' => $type, 'username' => $username];
            }
            foreach (array_diff($ids, $found) as $id) $skipped[] = ['id' => $id, 'name' => 'Data #' . $id, 'reason' => 'Data peminjam tidak ditemukan'];
            if (! $this->db->transStatus() || ! $this->db->transCommit()) throw new \RuntimeException('Akun dan audit gagal disimpan.');
            return ['created' => $created, 'skipped' => $skipped];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
