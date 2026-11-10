<?php

namespace App\Controllers\Kepala;

use App\Controllers\BaseController;

class Reports extends BaseController
{
    public function damage(int $id)
    {
        $report = model('App\Models\DamageReportModel')->select('damage_reports.*, items.item_code, items.item_name, items.brand, items.model, items.location, items.condition AS item_condition, users.full_name AS reported_by_name, loans.loan_number, borrowers.name AS borrower_name')
            ->join('items', 'items.id = damage_reports.item_id')
            ->join('users', 'users.id = damage_reports.reported_by', 'left')
            ->join('loans', 'loans.id = damage_reports.loan_id', 'left')
            ->join('borrowers', 'borrowers.id = loans.borrower_id', 'left')
            ->where('damage_reports.id', $id)
            ->first();

        if (! $report) {
            return redirect()->to('/kepala/reports/damages')->with('error', 'Laporan tidak ditemukan.');
        }

        $actions = model('App\Models\HandlingActionModel')->where('damage_report_id', $id)->orderBy('action_date', 'ASC')->findAll();

        return view('petugas/damage_detail', [
            'title'         => 'Detail Kerusakan',
            'report'        => $report,
            'actions'       => $actions,
            'readOnly'      => true,
            'itemDetailUrl' => base_url('/kepala/items/' . $report->item_id),
        ]);
    }

    public function loan(int $id)
    {
        $loan = model('App\Models\LoanModel')->select('loans.*, items.item_code, items.item_name, items.brand, items.model, items.physical_photo, items.status AS item_status, borrowers.name AS borrower_name, borrowers.type AS borrower_type, borrowers.identity_number, borrowers.class_name, users.full_name AS processed_by_name')
            ->join('items', 'items.id = loans.item_id')
            ->join('borrowers', 'borrowers.id = loans.borrower_id')
            ->join('users', 'users.id = loans.processed_by', 'left')
            ->where('loans.id', $id)
            ->first();

        if (! $loan) {
            return redirect()->to('/kepala/reports/loans')->with('error', 'Transaksi tidak ditemukan.');
        }

        return view('petugas/loan_detail', [
            'title'         => 'Detail Peminjaman',
            'loan'          => $loan,
            'readOnly'      => true,
            'itemDetailUrl' => base_url('/kepala/items/' . $loan->item_id),
        ]);
    }

    public function loans()
    {
        $q = (string) $this->request->getGet('q');
        $status = (string) $this->request->getGet('status');
        $type = (string) $this->request->getGet('type');
        $from = (string) $this->request->getGet('from');
        $to = (string) $this->request->getGet('to');

        $loans = model('App\Models\LoanModel')->withDetails($status ?: 'all', $q ?: null, $type ?: null, $from ?: null, $to ?: null)
            ->paginate(per_page(10));

        $db = \Config\Database::connect();
        $summary = [
            'total'     => (int) $db->table('loans')->countAllResults(),
            'completed' => (int) $db->table('loans')->where('returned_at IS NOT NULL')->countAllResults(),
            'active'    => (int) $db->table('loans')->where('returned_at IS NULL')->where('due_at >=', date('Y-m-d H:i:s'))->countAllResults(),
            'late'      => (int) $db->table('loans')->where('returned_at IS NULL')->where('due_at <', date('Y-m-d H:i:s'))->countAllResults(),
        ];

        return view('kepala/report_loans', [
            'title'   => 'Laporan Peminjaman',
            'loans'   => $loans,
            'pager'   => model('App\Models\LoanModel')->pager,
            'summary' => $summary,
            'q'       => $q,
            'status'  => $status,
            'type'    => $type,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    public function damages()
    {
        $q = (string) $this->request->getGet('q');
        $status = (string) $this->request->getGet('status');

        $reports = model('App\Models\DamageReportModel')->withDetails($status ?: 'all', $q ?: null)
            ->paginate(per_page(10));

        return view('kepala/report_damages', [
            'title'   => 'Laporan Kerusakan',
            'reports' => $reports,
            'pager'   => model('App\Models\DamageReportModel')->pager,
            'q'       => $q,
            'status'  => $status,
        ]);
    }

    public function handling()
    {
        $q = (string) $this->request->getGet('q');
        $status = (string) $this->request->getGet('status');

        $actions = model('App\Models\HandlingActionModel')->withDetails($status ?: 'all', $q ?: null)
            ->paginate(per_page(10));

        return view('kepala/report_handling', [
            'title'   => 'Riwayat Penanganan',
            'actions' => $actions,
            'pager'   => model('App\Models\HandlingActionModel')->pager,
            'q'       => $q,
            'status'  => $status,
        ]);
    }

    public function activity()
    {
        $q = \App\Models\ActivityLogModel::normalizeKeyword($this->request->getGet('q'));
        $module = (string) $this->request->getGet('module');

        $from = (string) $this->request->getGet('from');
        $to = (string) $this->request->getGet('to');

        $logs = model('App\Models\ActivityLogModel')->filtered($q, $module ?: null, $from ?: null, $to ?: null, 500)
            ->paginate(per_page(10));

        return view('kepala/report_activity', [
            'title'  => 'Riwayat Aktivitas',
            'logs'   => $logs,
            'pager'  => model('App\Models\ActivityLogModel')->pager,
            'q'      => $q,
            'module' => $module,
            'from'   => $from,
            'to'     => $to,
        ]);
    }

    public function pdf()
    {
        $type = (string) $this->request->getGet('type');
        $from = (string) $this->request->getGet('from');
        $to = (string) $this->request->getGet('to');
        $status = (string) $this->request->getGet('status');

        $rows = [];
        if ($type === 'damages') {
            $rows = model('App\Models\DamageReportModel')->withDetails($status ?: 'all')->limit(1000)->find();
        } elseif ($type === 'handling') {
            $rows = model('App\Models\HandlingActionModel')->withDetails($status ?: 'all')->limit(1000)->find();
        } else {
            $type = 'loans';
            $rows = model('App\Models\LoanModel')->withDetails($status ?: 'all', null, null, $from ?: null, $to ?: null)->limit(1000)->find();
        }

        $labelMap = ['loans' => 'Peminjaman', 'damages' => 'Kerusakan', 'handling' => 'Penanganan'];
        log_activity('laporan_dicetak', 'Laporan', $labelMap[$type] ?? ucfirst($type), 'Cetak PDF laporan ' . strtolower($labelMap[$type] ?? $type) . ' (' . count($rows) . ' data)');

        $html = view('petugas/report_print', ['type' => $type, 'rows' => $rows, 'from' => $type === 'loans' ? $from : '', 'to' => $type === 'loans' ? $to : '', 'status' => $status]);

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('Laporan-' . ucfirst($type) . '.pdf', ['Attachment' => false]);
        exit;
    }
}
