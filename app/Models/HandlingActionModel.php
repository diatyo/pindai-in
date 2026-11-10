<?php

namespace App\Models;

use CodeIgniter\Model;

class HandlingActionModel extends Model
{
    protected $table = 'handling_actions';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['damage_report_id', 'action_date', 'action_description', 'executor_name', 'result_condition', 'status'];
    protected $useTimestamps = true;

    public function withDetails(?string $status = 'all', ?string $q = null)
    {
        $this->select('handling_actions.*, damage_reports.description AS damage_description, damage_reports.item_id, damage_reports.status AS report_status, items.item_code, items.item_name')
            ->join('damage_reports', 'damage_reports.id = handling_actions.damage_report_id')
            ->join('items', 'items.id = damage_reports.item_id');
        if ($q) {
            $this->groupStart()->like('items.item_code', $q)->orLike('handling_actions.action_description', $q)->groupEnd();
        }
        if ($status && $status !== 'all') {
            $this->where('handling_actions.status', $status);
        }
        return $this->orderBy('handling_actions.action_date', 'DESC');
    }
}