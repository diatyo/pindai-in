<?php

namespace App\Models;

use CodeIgniter\Model;

class BorrowerModel extends Model
{
    protected $table = 'borrowers';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['type', 'identity_number', 'name', 'gender', 'class_name', 'phone', 'is_active'];
    protected $useTimestamps = true;
    protected $afterUpdate = ['revokeArchivedBorrowerSessions'];

    protected function revokeArchivedBorrowerSessions(array $event): array
    {
        if (! empty($event['result']) && ! empty($event['id'])
            && array_key_exists('is_active', $event['data']) && ! $event['data']['is_active']) {
            if (! $this->db->table('users')->whereIn('borrower_id', (array) $event['id'])
                ->set('auth_version', 'auth_version + 1', false)->update()) {
                throw new \RuntimeException('Sesi akun peminjam gagal dicabut.');
            }
        }
        return $event;
    }

    public function teachers(?string $q = null, ?string $gender = null, int $limit = 500)
    {
        $this->where('type', 'teacher');
        if ($q) {
            $this->groupStart()->like('name', $q)->orLike('identity_number', $q)->groupEnd();
        }
        if ($gender) {
            $this->where('gender', $gender);
        }
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->limit($limit)->findAll();
    }

    public function students(?string $q = null, ?string $class = null, int $limit = 500)
    {
        $this->where('type', 'student');
        if ($q) {
            $this->groupStart()->like('name', $q)->orLike('identity_number', $q)->groupEnd();
        }
        if ($class) {
            $this->where('class_name', $class);
        }
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->limit($limit)->findAll();
    }

    public function activeStudentClasses(): array
    {
        $rows = $this->db->table($this->table)->select('class_name')->distinct()
            ->where('type', 'student')->where('is_active', 1)->where('class_name IS NOT NULL', null, false)
            ->get()->getResultArray();
        $classes = array_values(array_filter(array_column($rows, 'class_name'), static fn ($name) => trim($name) !== ''));
        $grade = static function (string $name): int {
            preg_match('/^(VIII|VII|IX|7|8|9)(?=[\s.\-\/]|$)/i', trim($name), $match);
            return match (strtoupper($match[1] ?? '')) {
                'VII', '7' => 7,
                'VIII', '8' => 8,
                'IX', '9' => 9,
                default => 10,
            };
        };
        usort($classes, static fn ($a, $b) => ($grade($a) <=> $grade($b)) ?: strnatcasecmp($a, $b));
        return $classes;
    }
    /**
     * Guru/siswa aktif beserta id akun peminjam yang sudah terhubung (null bila belum).
     * Dipakai admin untuk memilih pemilik akun peminjam.
     */
    public function available(): array
    {
        return $this->select('borrowers.*, users.id AS linked_user_id')
            ->join('users', 'users.borrower_id = borrowers.id', 'left')
            ->where('borrowers.is_active', 1)
            ->orderBy('borrowers.type', 'ASC')->orderBy('borrowers.name', 'ASC')
            ->findAll();
    }
}
