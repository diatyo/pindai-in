<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemReferenceModel extends Model
{
    protected $table = 'item_references';
    protected $returnType = 'object';
    protected $allowedFields = ['type', 'name', 'name_key', 'is_active'];
    protected $useTimestamps = true;

    public const DEFAULTS = [
        'category' => ['Elektronik', 'Perangkat Komputer', 'Jaringan & Multimedia', 'Aksesoris', 'Meubelair', 'Alat Peraga Pendidikan', 'Alat Laboratorium', 'Alat Olahraga', 'Alat Kesenian', 'Alat Kebersihan', 'Perlengkapan Kantor', 'Buku & Perpustakaan', 'Lainnya'],
        'location' => ['Ruang Sarana', 'Ruang Multimedia', 'Laboratorium IPA', 'Ruang TU', 'Ruang Kelas', 'Perpustakaan', 'Lainnya'],
    ];

    public static function nameKey(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }

    public static function fieldFor(string $type): string
    {
        return match ($type) {
            'category' => 'category',
            'location' => 'location',
            default => throw new \DomainException('Jenis referensi tidak valid.'),
        };
    }

    public function names(string $type, ?string $current = null, bool $includeInactive = false): array
    {
        self::fieldFor($type);
        $builder = $this->db->table($this->table)->where('type', $type);
        if (! $includeInactive) {
            $builder->groupStart()->where('is_active', 1);
            if ($current !== null && $current !== '') {
                $builder->orWhere('name_key', self::nameKey($current));
            }
            $builder->groupEnd();
        }
        $names = array_column($builder->orderBy('name_key')->get()->getResultArray(), 'name');
        if ($current !== null && $current !== '' && ! in_array($current, $names, true)) {
            $names[] = $current;
            usort($names, static fn ($left, $right) => strcmp(self::nameKey($left), self::nameKey($right)));
        }
        return $names;
    }

    public function usageCounts(string $type): array
    {
        $field = self::fieldFor($type);
        $counts = [];
        foreach ($this->db->table('items')->select($field . ', COUNT(*) AS total')->groupBy($field)->get()->getResult() as $row) {
            $key = self::nameKey((string) $row->$field);
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->total;
        }
        return $counts;
    }

    private function matchingValues(string $type, string $key): array
    {
        $field = self::fieldFor($type);
        $values = [];
        foreach ($this->db->table('items')->select($field)->distinct()->get()->getResult() as $row) {
            if (self::nameKey((string) $row->$field) === $key) {
                $values[] = $row->$field;
            }
        }
        return $values;
    }

    private function transaction(callable $operation)
    {
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('Transaksi referensi tidak dapat dimulai.');
        }
        try {
            $result = $operation();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new \RuntimeException('Perubahan referensi gagal disimpan.');
            }
            return $result;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function lockedRow(string $where, array $bindings): object
    {
        $table = $this->db->escapeIdentifiers($this->db->prefixTable($this->table));
        $lock = $this->db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
        $query = $this->db->query('SELECT * FROM ' . $table . ' WHERE ' . $where . $lock, $bindings);
        if ($query === false) {
            throw new \RuntimeException('Referensi sedang diproses. Silakan coba lagi.');
        }
        return $query->getRow() ?? throw new \DomainException('Referensi tidak ditemukan atau telah berubah. Muat ulang halaman.');
    }

    /** Lock references before locking a unit, consistently across rename and item writes. */
    public function withSelection(string $category, string $location, callable $operation)
    {
        return $this->transaction(function () use ($category, $location, $operation) {
            $selection = [];
            foreach (['category' => $category, 'location' => $location] as $type => $name) {
                $selection[$type] = $this->lockedRow('type = ? AND name_key = ?', [$type, self::nameKey($name)]);
            }
            return $operation($selection);
        });
    }

    public function selectionValues(array $selection, ?object $current = null): array
    {
        $values = [];
        foreach (['category', 'location'] as $type) {
            $reference = $selection[$type];
            if (! $reference->is_active && ($current === null || self::nameKey((string) $current->$type) !== $reference->name_key)) {
                throw new \DomainException(($type === 'category' ? 'Kategori' : 'Lokasi') . ' sudah nonaktif. Pilih referensi yang aktif.');
            }
            $values[$type] = $reference->name;
        }
        return $values;
    }

    public function saveEntry(?int $id, string $type, string $name): int
    {
        self::fieldFor($type);
        $name = trim($name);
        $limit = $type === 'category' ? 60 : 100;
        if ($name === '' || mb_strlen($name) > $limit) {
            throw new \DomainException('Nama referensi tidak valid.');
        }
        return $this->transaction(function () use ($id, $type, $name) {
            $previous = $id ? $this->lockedRow('id = ?', [$id]) : null;
            if ($previous && $previous->type !== $type) {
                throw new \DomainException('Jenis referensi tidak dapat diubah.');
            }
            $key = self::nameKey($name);
            $duplicate = $this->db->table($this->table)->where('type', $type)->where('name_key', $key);
            if ($id) {
                $duplicate->where('id !=', $id);
            }
            if ($duplicate->countAllResults() > 0) {
                throw new \DomainException('Nama tersebut sudah terdaftar, termasuk pada referensi nonaktif.');
            }
            $data = ['type' => $type, 'name' => $name, 'name_key' => $key];
            $affected = 0;
            if ($previous) {
                $values = $this->matchingValues($type, $previous->name_key);
                if ($values !== [] && $previous->name !== $name) {
                    if (! $this->db->table('items')->whereIn(self::fieldFor($type), $values)->update([self::fieldFor($type) => $name])) {
                        throw new \RuntimeException('Nama referensi pada barang gagal diperbarui.');
                    }
                    $affected = $this->db->affectedRows();
                }
                $saved = $this->update($id, $data);
            } else {
                $id = (int) $this->insert($data + ['is_active' => 1]);
                $saved = $id > 0;
            }
            if (! $saved) {
                throw new \RuntimeException('Referensi gagal disimpan.');
            }
            log_activity($previous ? 'referensi_diubah' : 'referensi_ditambah', 'Referensi', $name,
                ($type === 'category' ? 'Kategori' : 'Lokasi') . ($previous ? ': ' . $previous->name . ' → ' . $name : ' ditambahkan') . ' · ' . $affected . ' unit diperbarui');
            return $id;
        });
    }

    public function toggleEntry(int $id): object
    {
        return $this->transaction(function () use ($id) {
            $row = $this->lockedRow('id = ?', [$id]);
            $active = $row->is_active ? 0 : 1;
            if (! $this->update($id, ['is_active' => $active])) {
                throw new \RuntimeException('Status referensi gagal disimpan.');
            }
            log_activity($active ? 'referensi_diaktifkan' : 'referensi_dinonaktifkan', 'Referensi', $row->name, $row->type);
            return (object) array_replace((array) $row, ['is_active' => $active]);
        });
    }

    public function deleteUnused(int $id): object
    {
        return $this->transaction(function () use ($id) {
            $row = $this->lockedRow('id = ?', [$id]);
            if ($this->matchingValues($row->type, $row->name_key) !== []) {
                throw new \DomainException('Referensi sudah digunakan oleh barang. Nonaktifkan agar riwayat tetap tersimpan.');
            }
            if (! $this->delete($id)) {
                throw new \RuntimeException('Referensi gagal dihapus.');
            }
            log_activity('referensi_dihapus', 'Referensi', $row->name, $row->type);
            return $row;
        });
    }
}
