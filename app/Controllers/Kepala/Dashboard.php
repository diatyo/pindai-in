<?php

namespace App\Controllers\Kepala;

use App\Controllers\BaseController;
use App\Models\DamageReportModel;
use DateTimeImmutable;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $now = new DateTimeImmutable();
        $today = $now->setTime(0, 0);
        $monthStart = $today->modify('first day of this month');
        $monthEnd = $today->modify('last day of this month');
        $weekStart = $today->modify('-6 days');

        $itemsByStatus = $db->table('items')->select('status, COUNT(*) AS total', false)
            ->where('is_active', 1)->groupBy('status')->get()->getResultArray();
        $statusCounts = array_column($itemsByStatus, 'total', 'status');
        $statuses = [
            'available'  => (int) ($statusCounts['available'] ?? 0),
            'borrowed'   => (int) ($statusCounts['borrowed'] ?? 0),
            'incomplete' => (int) ($statusCounts['incomplete'] ?? 0),
            'damaged'    => (int) ($statusCounts['damaged'] ?? 0) + (int) ($statusCounts['in_handling'] ?? 0),
        ];
        $totalUnits = array_sum($statusCounts);

        $monthLoans = $db->table('loans')
            ->where('borrowed_at >=', $monthStart->format('Y-m-d H:i:s'))
            ->where('borrowed_at <', $monthEnd->modify('+1 day')->format('Y-m-d H:i:s'));
        $returns = $db->table('loans')
            ->select('COUNT(*) AS completed, COUNT(CASE WHEN returned_at <= due_at THEN 1 END) AS on_time', false)
            ->where('returned_at IS NOT NULL')->get()->getRow();
        $completed = (int) $returns->completed;
        $onTime = (int) $returns->on_time;

        $dailyLoans = $db->table('loans')->select('DATE(borrowed_at) AS day, COUNT(*) AS total', false)
            ->where('borrowed_at >=', $weekStart->format('Y-m-d H:i:s'))
            ->where('borrowed_at <', $today->modify('+1 day')->format('Y-m-d H:i:s'))
            ->groupBy('DATE(borrowed_at)', false)->get()->getResultArray();
        $dailyCounts = array_column($dailyLoans, 'total', 'day');
        $trend = [];
        for ($day = $weekStart; $day <= $today; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $trend[$date] = (int) ($dailyCounts[$date] ?? 0);
        }

        return view('kepala/dashboard', [
            'total_units'  => $totalUnits,
            'statuses'     => $statuses + ['other' => $totalUnits - array_sum($statuses)],
            'month_start'  => $monthStart->format('Y-m-d'),
            'month_end'    => $monthEnd->format('Y-m-d'),
            'today'        => $today->format('Y-m-d'),
            'month_loans'  => $monthLoans->countAllResults(false),
            'completed'    => $completed,
            'on_time'      => $onTime,
            'on_time_pct'  => $completed > 0 ? (int) round($onTime * 100 / $completed) : null,
            'late_loans'   => $db->table('loans')->where('returned_at IS NULL')
                ->where('due_at <', $now->format('Y-m-d H:i:s'))->countAllResults(),
            'open_reports' => $db->table('damage_reports')->whereIn('status', DamageReportModel::OPEN_STATUSES)->countAllResults(),
            'trend'        => $trend,
            'top_items'    => $monthLoans->select('items.id, items.item_code, items.item_name, COUNT(loans.id) AS total', false)
                ->join('items', 'items.id = loans.item_id')
                ->groupBy('items.id, items.item_code, items.item_name')->orderBy('total', 'DESC')->orderBy('items.item_code')
                ->limit(4)->get()->getResult(),
        ]);
    }
}
