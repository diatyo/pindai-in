<?php

namespace App\Models;

use CodeIgniter\Model;

class StatusHistoryModel extends Model
{
    protected $table = 'item_status_history';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['item_id', 'status_from', 'status_to', 'note', 'loan_id', 'damage_report_id', 'changed_by', 'changed_by_name'];
    protected $useTimestamps = false;
}