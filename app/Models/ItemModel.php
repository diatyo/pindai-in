<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemModel extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['item_code', 'category', 'item_name', 'brand', 'model', 'serial_number', 'serial_status', 'acquisition_date', 'location', 'specifications', 'status', 'condition', 'physical_photo', 'serial_photo', 'qr_filename', 'is_active'];

    public const SERIAL_STATUS_LABELS = [
        'known' => 'Ada dan terbaca',
        'missing' => 'Tidak ada nomor seri',
        'unreadable' => 'Nomor seri tidak terbaca',
    ];

    public static function serialLabel(object $item): string
    {
        $status = $item->serial_status ?? 'known';
        if ($status === 'known') {
            return (string) ($item->serial_number ?? '—');
        }
        return self::SERIAL_STATUS_LABELS[$status] ?? '—';
    }
    protected $useTimestamps = true;

    public function withStats(?string $q = null, ?string $status = null, ?string $category = null)
    {
        if ($q) {
            $this->groupStart()
                ->like('item_code', $q)->orLike('item_name', $q)->orLike('serial_number', $q)->orLike('location', $q)
                ->groupEnd();
        }
        if ($status && $status !== 'all' && $status !== 'archived') {
            $this->where('status', $status);
        }
        if ($category && $category !== 'all') {
            $this->where('category', $category);
        }
        return $this->where('is_active', $status === 'archived' ? 0 : 1)->orderBy('item_code', 'ASC');
    }

    /** Operational workflows must never reactivate an archived item. */
    public static function operationalStatus(object $item, string $nextStatus): string
    {
        return ! $item->is_active || $item->status === 'disabled' ? 'disabled' : $nextStatus;
    }

    /** Serialize every operational change for a unit, including its related records. */
    public function withLockedItem(int $itemId, callable $operation)
    {
        return $this->withLockedItems([$itemId], static fn (array $items) => $operation($items[$itemId]));
    }

    /** Acquire all unit locks before checking or writing any related records. */
    public function withLockedItems(array $itemIds, callable $operation)
    {
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('Transaksi database tidak dapat dimulai.');
        }
        try {
            $table = $this->db->escapeIdentifiers($this->db->prefixTable($this->table));
            // SQLite is used by isolated tests; production uses MySQL/MariaDB row locks.
            $lock = $this->db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
            $placeholders = implode(', ', array_fill(0, count($itemIds), '?'));
            $query = $this->db->query('SELECT * FROM ' . $table . ' WHERE id IN (' . $placeholders . ') ORDER BY id' . $lock, $itemIds);
            if ($query === false) {
                $error = $this->db->error();
                throw new \RuntimeException('Unit gagal dikunci. Silakan coba lagi.', (int) $error['code']);
            }
            $items = array_column($query->getResult(), null, 'id');
            if (count($items) !== count($itemIds)) {
                throw new \DomainException('Ada unit yang tidak ditemukan. Periksa kembali daftar unit.');
            }
            $result = $operation($items);
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new \RuntimeException('Perubahan unit gagal disimpan.');
            }
            return $result;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** One source of truth: archive, active loan, unresolved damage, physical condition. */
    public function stateFor(object $item, array $changes = []): array
    {
        $current = (object) array_replace((array) $item, $changes);
        $condition = $current->condition;
        $levels = ['good' => 0, 'minor_damage' => 1, 'major_damage' => 2];
        $reports = $this->db->table('damage_reports')->where('item_id', $item->id)
            ->whereIn('status', ['reported', 'in_handling', 'unrepairable'])->get()->getResult();
        $handling = $unrepairable = false;
        foreach ($reports as $report) {
            if ($levels[$report->damage_level] > $levels[$condition]) {
                $condition = $report->damage_level;
            }
            $handling = $handling || $report->status === 'in_handling';
            $unrepairable = $unrepairable || $report->status === 'unrepairable';
        }
        $borrowed = $this->db->table('loans')->where('item_id', $item->id)
            ->where('returned_at', null)->countAllResults() > 0;
        $incomplete = (new \App\Libraries\AccessoryChecklist($this->db))->hasBlockingMissing((int) $item->id);
        $disabled = ! $current->is_active
            || ($current->status === 'disabled' && ! array_key_exists('is_active', $changes));
        $status = match (true) {
            $disabled => 'disabled',
            $borrowed => 'borrowed',
            $unrepairable => 'damaged',
            $handling => 'in_handling',
            $reports !== [] || $condition !== 'good' => 'damaged',
            $incomplete => 'incomplete',
            default => 'available',
        };
        return ['status' => $status, 'condition' => $condition];
    }

    /** Call after related changes, inside withLockedItem(), so all facts are current. */
    public function synchronizeState(object $item, array $changes = []): object
    {
        $data = array_replace($changes, $this->stateFor($item, $changes));
        if (! $this->update($item->id, $data)) {
            throw new \RuntimeException('Status unit gagal disimpan.');
        }
        return (object) array_replace((array) $item, $data);
    }

    public function lastBorrower(int $itemId): ?object
    {
        return $this->db->table('loans')
            ->select('loans.*, borrowers.name AS borrower_name, borrowers.type AS borrower_type')
            ->join('borrowers', 'borrowers.id = loans.borrower_id', 'left')
            ->where('loans.item_id', $itemId)
            ->orderBy('loans.borrowed_at', 'DESC')
            ->get()->getFirstRow();
    }
    public function statusCounts(?string $q = null, ?string $category = null): array
    {
        $builder = $this->db->table('items')->where('is_active', 1);
        if ($q) {
            $builder->groupStart()
                ->like('item_code', $q)->orLike('item_name', $q)->orLike('serial_number', $q)->orLike('location', $q)
                ->groupEnd();
        }
        if ($category && $category !== 'all') {
            $builder->where('category', $category);
        }
        $row = $builder->select("COUNT(*) AS total, SUM(status = 'available') AS available, SUM(status = 'borrowed') AS borrowed, SUM(status = 'damaged') AS damaged, SUM(status = 'in_handling') AS in_handling, SUM(status = 'incomplete') AS incomplete", false)
            ->get()->getFirstRow();

        return [
            ''            => (int) ($row->total ?? 0),
            'available'   => (int) ($row->available ?? 0),
            'borrowed'    => (int) ($row->borrowed ?? 0),
            'damaged'     => (int) ($row->damaged ?? 0),
            'in_handling' => (int) ($row->in_handling ?? 0),
            'incomplete'  => (int) ($row->incomplete ?? 0),
        ];
    }
}
