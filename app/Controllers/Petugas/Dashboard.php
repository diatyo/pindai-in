<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        $data = [
            'total'          => (int) $db->table('items')->where('is_active', 1)->countAllResults(),
            'available'      => (int) $db->table('items')->where('status', 'available')->where('is_active', 1)->countAllResults(),
            'borrowed'       => (int) $db->table('items')->where('status', 'borrowed')->where('is_active', 1)->countAllResults(),
            'in_handling'    => (int) $db->table('items')->whereIn('status', ['damaged', 'in_handling'])->where('is_active', 1)->countAllResults(),
            'late'           => (int) $db->table('loans')->where('returned_at IS NULL')->where('due_at <', $now)->countAllResults(),
            'due_today'      => (int) $db->table('loans')->where('returned_at IS NULL')->where('due_at >=', $today . ' 00:00:00')->where('due_at <=', $today . ' 23:59:59')->countAllResults(),
            'returned_today' => (int) $db->table('loans')->where('returned_at >=', $today . ' 00:00:00')->countAllResults(),
            'damage_reports' => (int) $db->table('damage_reports')->where('status', 'reported')->countAllResults(),
            'accessory_pending_count' => model(\App\Models\LoanAccessoryModel::class)->pending()->countAllResults(),
            'incomplete' => (int) $db->table('items')->where('status', 'incomplete')->where('is_active', 1)->countAllResults(),
        ];
        [$data['available_end'], $data['borrowed_end']] = donut_stops($data['total'], $data['available'], $data['borrowed']);

        $data['recent_activities'] = model(ActivityLogModel::class)
            ->filtered(limit: 5, userId: (int) session()->get('user_id'))
            ->where('role', 'petugas')
            ->findAll();

        $data['active_loans'] = $db->table('loans')
            ->select('loans.*, items.item_code, items.item_name, items.brand, items.model, borrowers.name AS borrower_name, borrowers.type AS borrower_type')
            ->join('items', 'items.id = loans.item_id')
            ->join('borrowers', 'borrowers.id = loans.borrower_id')
            ->where('loans.returned_at IS NULL')
            ->orderBy('loans.due_at', 'ASC')
            ->get()->getResult();

        $data['pending_handling'] = $db->table('items')
            ->select('items.id AS item_id, items.item_code, items.item_name, items.brand, items.model, items.status AS item_status, dr.id AS report_id, dr.status AS report_status, dr.description, dr.discovered_at')
            ->join('(SELECT d1.* FROM damage_reports d1 JOIN (SELECT item_id, MAX(discovered_at) AS max_at FROM damage_reports GROUP BY item_id) d2 ON d2.item_id = d1.item_id AND d2.max_at = d1.discovered_at) dr', 'dr.item_id = items.id', 'left')
            ->whereIn('items.status', ['damaged', 'in_handling'])
            ->where('items.is_active', 1)
            ->orderBy('COALESCE(dr.discovered_at, items.created_at)', 'ASC', false)
            ->get()->getResult();

        return view('petugas/dashboard', $data + ['title' => 'Dasbor']);
    }
}
