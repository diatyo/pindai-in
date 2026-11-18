<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class DamageReportModel extends Model
{
    public const OPEN_STATUSES = ['reported', 'in_handling'];

    protected $table = 'damage_reports';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['report_number', 'item_id', 'loan_id', 'discovered_at', 'damage_level', 'description', 'damage_photo', 'notes', 'status', 'reported_by'];
    protected $useTimestamps = true;

    public function damagedUnitsWithoutCurrentReport(): array
    {
        return $this->db->table('items')->select('id, item_code, item_name, status, condition')
            ->where('is_active', 1)
            ->whereIn('status', ['damaged', 'in_handling'])
            ->whereNotIn('id', static fn (BaseBuilder $builder) => $builder
                ->select('item_id')
                ->from('damage_reports')
                ->whereIn('status', [...self::OPEN_STATUSES, 'unrepairable']))
            ->orderBy('item_code', 'ASC')
            ->get()
            ->getResult();
    }

    public function withDetails(?string $status = 'all', ?string $q = null)
    {
        $this->select('damage_reports.*, items.item_code, items.item_name, items.brand, items.model, items.condition AS item_condition, users.full_name AS reported_by_name')
            ->join('items', 'items.id = damage_reports.item_id')
            ->join('users', 'users.id = damage_reports.reported_by', 'left');
        if ($q) {
            $this->groupStart()->like('items.item_code', $q)->orLike('damage_reports.description', $q)->groupEnd();
        }
        if ($status === 'open') {
            $this->whereIn('damage_reports.status', self::OPEN_STATUSES);
        } elseif ($status && $status !== 'all') {
            $this->where('damage_reports.status', $status);
        }
        return $this->orderBy('damage_reports.discovered_at', 'DESC');
    }
}
