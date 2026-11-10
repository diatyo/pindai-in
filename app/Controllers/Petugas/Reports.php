<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;

class Reports extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $type = (string) $this->request->getGet('type');
        if (! in_array($type, ['loans', 'damages', 'handling'], true)) {
            $type = 'loans';
        }
        $from = (string) $this->request->getGet('from');
        $to = (string) $this->request->getGet('to');
        $status = (string) $this->request->getGet('status');
        $category = (string) $this->request->getGet('category');

        $rows = [];
        $summary = [];

        $pager = null;
        if ($type === 'loans') {
            $makeBuilder = static function () use ($status, $from, $to, $category) {
                $builder = model('App\Models\LoanModel')->withDetails($status ?: 'all', null, null, $from ?: null, $to ?: null);
                if ($category && $category !== 'all') {
                    $builder->where('items.category', $category);
                }
                return $builder;
            };
            $rows = $makeBuilder()->paginate(per_page(10));
            $pager = model('App\Models\LoanModel')->pager;
            $summary = ['total' => 0, 'completed' => 0, 'active' => 0, 'late' => 0];
            foreach ($makeBuilder()->limit(5000)->find() as $row) {
                $summary['total']++;
                if ($row->returned_at) {
                    $summary['completed']++;
                } elseif ($row->due_at < date('Y-m-d H:i:s')) {
                    $summary['late']++;
                } else {
                    $summary['active']++;
                }
            }
        } elseif ($type === 'damages') {
            $makeBuilder = static function () use ($status, $from, $to, $category) {
                $builder = model('App\Models\DamageReportModel')->withDetails($status ?: 'all');
                if ($category && $category !== 'all') {
                    $builder->where('items.category', $category);
                }
                if ($from) {
                    $builder->where('damage_reports.discovered_at >=', $from . ' 00:00:00');
                }
                if ($to) {
                    $builder->where('damage_reports.discovered_at <=', $to . ' 23:59:59');
                }
                return $builder;
            };
            $rows = $makeBuilder()->paginate(per_page(10));
            $pager = model('App\Models\DamageReportModel')->pager;
            $summary = ['total' => 0, 'reported' => 0, 'in_handling' => 0, 'completed' => 0, 'unrepairable' => 0];
            foreach ($makeBuilder()->limit(5000)->find() as $row) {
                $summary['total']++;
                if (isset($summary[$row->status])) {
                    $summary[$row->status]++;
                }
            }
        } elseif ($type === 'handling') {
            $makeBuilder = static function () use ($status, $from, $to, $category) {
                $builder = model('App\Models\HandlingActionModel')->withDetails($status ?: 'all');
                if ($category && $category !== 'all') {
                    $builder->where('items.category', $category);
                }
                if ($from) {
                    $builder->where('handling_actions.action_date >=', $from);
                }
                if ($to) {
                    $builder->where('handling_actions.action_date <=', $to);
                }
                return $builder;
            };
            $rows = $makeBuilder()->paginate(per_page(10));
            $pager = model('App\Models\HandlingActionModel')->pager;
            $summary = ['total' => 0, 'in_progress' => 0, 'completed' => 0, 'unrepairable' => 0];
            foreach ($makeBuilder()->limit(5000)->find() as $row) {
                $summary['total']++;
                if (isset($summary[$row->status])) {
                    $summary[$row->status]++;
                }
            }
        }

        $categories = $db->table('items')->select('category')->distinct()->orderBy('category')->get()->getResult();

        return view('petugas/reports', [
            'title'      => 'Laporan Operasional',
            'type'       => $type,
            'rows'       => $rows,
            'pager'      => $pager,
            'summary'    => $summary,
            'from'       => $from,
            'to'         => $to,
            'status'     => $status,
            'category'   => $category,
            'categories' => $categories,
        ]);
    }

    public function printReport()
    {
        $type = (string) $this->request->getGet('type');
        if (! in_array($type, ['loans', 'damages', 'handling'], true)) {
            $type = 'loans';
        }
        $from = (string) $this->request->getGet('from');
        $to = (string) $this->request->getGet('to');
        $status = (string) $this->request->getGet('status');
        $category = (string) $this->request->getGet('category');

        $builder = null;
        if ($type === 'damages') {
            $builder = model('App\Models\DamageReportModel')->withDetails($status ?: 'all');
            if ($category && $category !== 'all') {
                $builder->where('items.category', $category);
            }
            if ($from) {
                $builder->where('damage_reports.discovered_at >=', $from . ' 00:00:00');
            }
            if ($to) {
                $builder->where('damage_reports.discovered_at <=', $to . ' 23:59:59');
            }
        } elseif ($type === 'handling') {
            $builder = model('App\Models\HandlingActionModel')->withDetails($status ?: 'all');
            if ($category && $category !== 'all') {
                $builder->where('items.category', $category);
            }
            if ($from) {
                $builder->where('handling_actions.action_date >=', $from);
            }
            if ($to) {
                $builder->where('handling_actions.action_date <=', $to);
            }
        } else {
            $builder = model('App\Models\LoanModel')->withDetails($status ?: 'all', null, null, $from ?: null, $to ?: null);
            if ($category && $category !== 'all') {
                $builder->where('items.category', $category);
            }
        }

        $rows = $builder->limit(1000)->find();

        $labelMap = ['loans' => 'Peminjaman', 'damages' => 'Kerusakan', 'handling' => 'Penanganan'];
        log_activity('laporan_dicetak', 'Laporan', $labelMap[$type], 'Cetak PDF laporan ' . strtolower($labelMap[$type]) . ' (' . count($rows) . ' data)');

        $html = view('petugas/report_print', [
            'type' => $type,
            'rows' => $rows,
            'from' => $from,
            'to'   => $to,
            'status' => $status,
            'category' => $category,
        ]);

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $labels = ['loans' => 'Peminjaman', 'damages' => 'Kerusakan', 'handling' => 'Penanganan'];
        $dompdf->stream('Laporan-' . $labels[$type] . '.pdf', ['Attachment' => false]);
        exit;
    }

    public function pdf()
    {
        return $this->printReport();
    }
}
