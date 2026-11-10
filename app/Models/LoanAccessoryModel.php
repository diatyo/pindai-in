<?php

namespace App\Models;

use CodeIgniter\Model;

class LoanAccessoryModel extends Model
{
    protected $table = 'loan_accessories';
    protected $returnType = 'object';

    public function pending()
    {
        $table = $this->db->escapeIdentifiers($this->db->prefixTable($this->table));
        return $this->select('loan_accessories.*, l.loan_number, l.item_id, l.returned_at, i.item_code, i.item_name, b.name AS borrower_name')
            ->join('loans l', 'l.id = loan_accessories.loan_id')->join('items i', 'i.id = l.item_id')
            ->join('borrowers b', 'b.id = l.borrower_id')->where('l.returned_at IS NOT NULL')
            ->where($table . '.quantity_returned < ' . $table . '.quantity', null, false)
            ->orderBy('l.returned_at')->orderBy('loan_accessories.id');
    }
}
