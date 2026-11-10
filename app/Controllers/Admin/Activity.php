<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;

class Activity extends BaseController
{
    public function index()
    {
        $q = ActivityLogModel::normalizeKeyword($this->request->getGet('q'));
        $module = (string) $this->request->getGet('module');
        $from = (string) $this->request->getGet('from');
        $to = (string) $this->request->getGet('to');

        $logs = model(ActivityLogModel::class)->filtered($q, $module ?: null, $from ?: null, $to ?: null, 500)
            ->paginate(per_page(10));

        return view('admin/activity', [
            'title'  => 'Riwayat Aktivitas',
            'logs'   => $logs,
            'pager'  => model(ActivityLogModel::class)->pager,
            'q'      => $q,
            'module' => $module,
            'from'   => $from,
            'to'     => $to,
        ]);
    }

    public function pdf()
    {
        $q = ActivityLogModel::normalizeKeyword($this->request->getGet('q'));
        $module = (string) $this->request->getGet('module');
        $from = (string) $this->request->getGet('from');
        $to = (string) $this->request->getGet('to');

        $logs = model(ActivityLogModel::class)->filtered($q, $module ?: null, $from ?: null, $to ?: null, 1000)->find();

        log_activity('log_dicetak', 'Konfigurasi', 'Riwayat Aktivitas', 'Cetak PDF log audit (' . count($logs) . ' baris)');

        $html = view('admin/activity_print', [
            'logs' => $logs,
            'from' => $from,
            'to'   => $to,
        ]);

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('Riwayat-Aktivitas.pdf', ['Attachment' => false]);
        exit;
    }
}
