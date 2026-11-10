<?php

namespace App\Models;

use CodeIgniter\Model;

class LoanModel extends Model
{
    protected $table = 'loans';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['loan_number', 'loan_group_id', 'item_id', 'borrower_id', 'borrowed_at', 'due_at', 'purpose', 'usage_location', 'condition_out', 'completeness_out', 'processed_by', 'returned_at', 'condition_in', 'completeness_in', 'return_photo', 'return_notes', 'returned_by', 'accessory_checklist'];
    protected $useTimestamps = true;

    public function withDetails(?string $status = 'all', ?string $q = null, ?string $type = null, ?string $from = null, ?string $to = null, bool $includeGroups = false)
    {
        $this->select('loans.*, items.item_code, items.item_name, items.brand, items.model, borrowers.name AS borrower_name, borrowers.type AS borrower_type, borrowers.class_name, users.full_name AS processed_by_name')
            ->join('items', 'items.id = loans.item_id')
            ->join('borrowers', 'borrowers.id = loans.borrower_id')
            ->join('users', 'users.id = loans.processed_by', 'left');
        if ($includeGroups) {
            $this->select('loan_groups.group_number')->join('loan_groups', 'loan_groups.id = loans.loan_group_id', 'left');
        }

        if ($q) {
            $this->groupStart()
                ->like('items.item_code', $q)->orLike('items.item_name', $q)
                ->orLike('borrowers.name', $q);
            if ($includeGroups) {
                $this->orLike('loan_groups.group_number', $q);
            }
            $this->groupEnd();
        }
        if ($type && $type !== 'all') {
            $this->where('borrowers.type', $type);
        }
        if ($from) {
            $this->where('loans.borrowed_at >=', $from . ' 00:00:00');
        }
        if ($to) {
            $this->where('loans.borrowed_at <=', $to . ' 23:59:59');
        }
        if ($status === 'ongoing') {
            $this->where('loans.returned_at IS NULL');
        } elseif ($status === 'active') {
            $this->where('loans.returned_at IS NULL');
            $this->where('loans.due_at >=', date('Y-m-d H:i:s'));
        } elseif ($status === 'late') {
            $this->where('loans.returned_at IS NULL');
            $this->where('loans.due_at <', date('Y-m-d H:i:s'));
        } elseif ($status === 'completed') {
            $this->where('loans.returned_at IS NOT NULL');
        }
        return $this->orderBy('loans.borrowed_at', 'DESC');
    }

    public function activeLoanByItem(int $itemId): ?object
    {
        return $this->where('item_id', $itemId)->where('returned_at IS NULL')->orderBy('borrowed_at', 'DESC')->first();
    }
}
