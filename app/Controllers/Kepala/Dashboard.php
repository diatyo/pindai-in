<?php

namespace App\Controllers\Kepala;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        $monthLoans = (int) $db->table('loans')->where('borrowed_at >=', $monthStart)->where('borrowed_at <=', $monthEnd)->countAllResults();
        $completed = (int) $db->table('loans')->where('returned_at IS NOT NULL')->countAllResults();

        // hitung tepat waktu: returned_at <= due_at
        $lateReturns = (int) ($db->query('SELECT COUNT(*) AS n FROM loans WHERE returned_at IS NOT NULL AND returned_at > due_at')->getRow()->n ?? 0);
        $onTime = $completed - $lateReturns;
        $onTimePct = $completed > 0 ? round(($onTime / $completed) * 100) : 0;

        $data = [
            'total_units'    => (int) $db->table('items')->where('is_active', 1)->countAllResults(),
            'available'      => (int) $db->table('items')->where('status', 'available')->where('is_active', 1)->countAllResults(),
            'borrowed'       => (int) $db->table('items')->where('status', 'borrowed')->where('is_active', 1)->countAllResults(),
            'damaged'        => (int) $db->table('items')->whereIn('status', ['damaged', 'in_handling'])->where('is_active', 1)->countAllResults(),
            'month_loans'    => $monthLoans,
            'on_time_pct'    => $onTimePct,
            'need_handling'  => (int) $db->table('damage_reports')->whereIn('status', ['reported', 'in_handling'])->countAllResults(),
            'incomplete' => (int) $db->table('items')->where('status', 'incomplete')->where('is_active', 1)->countAllResults(),
        ];
        [$data['available_end'], $data['borrowed_end']] = donut_stops($data['total_units'], $data['available'], $data['borrowed']);

        // tren 7 hari terakhir
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $trend[$d] = (int) $db->table('loans')->where('borrowed_at >=', $d . ' 00:00:00')->where('borrowed_at <=', $d . ' 23:59:59')->countAllResults();
        }
        $data['trend'] = $trend;

        // barang paling sering dipinjam
        $data['top_items'] = $db->table('loans')
            ->select('items.item_name, COUNT(loans.id) AS total')
            ->join('items', 'items.id = loans.item_id')
            ->groupBy('items.id, items.item_name')
            ->orderBy('total', 'DESC')->limit(4)->get()->getResult();

        $data['unhandled'] = $db->table('damage_reports')
            ->select('damage_reports.*, items.item_code, items.item_name')
            ->join('items', 'items.id = damage_reports.item_id')
            ->where('damage_reports.status', 'reported')
            ->limit(5)->get()->getResult();

        return view('kepala/dashboard', $data + ['title' => 'Dasbor']);
    }
}
