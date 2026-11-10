<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;

class Handling extends BaseController
{
    public function index()
    {
        $status = (string) $this->request->getGet('status');
        $q = (string) $this->request->getGet('q');
        $itemId = (int) $this->request->getGet('item');
        $params = array_filter([
            'status' => $status === 'all' ? '' : $status,
            'q'      => $q,
            'item'   => $itemId,
        ], static fn ($value): bool => $value !== '' && $value !== 0);

        return redirect()->to('/petugas/damages' . ($params ? '?' . http_build_query($params) : ''));
    }

    public function create(int $reportId)
    {
        $report = model('App\Models\DamageReportModel')->select('damage_reports.*, items.item_code, items.item_name, items.brand, items.model, items.status AS item_status, items.condition AS item_condition')
            ->join('items', 'items.id = damage_reports.item_id')
            ->where('damage_reports.id', $reportId)->first();

        if (! $report) {
            return redirect()->to('/petugas/damages')->with('error', 'Laporan tidak ditemukan.');
        }

        $actions = model('App\Models\HandlingActionModel')->where('damage_report_id', $reportId)->orderBy('action_date', 'DESC')->findAll();

        return view('petugas/handling_form', [
            'title'   => 'Keterangan Penanganan',
            'report'  => $report,
            'actions' => $actions,
        ]);
    }

    public function store()
    {
        $actionStatus = (string) $this->request->getPost('status');
        $rules = [
            'damage_report_id' => ['label' => 'Laporan kerusakan', 'rules' => 'required|is_natural_no_zero'],
            'action_date' => ['label' => 'Tanggal tindakan', 'rules' => 'required|valid_date'],
            'action_description' => [
                'label'  => 'Keterangan',
                'rules'  => 'required',
                'errors' => ['required' => 'Keterangan wajib diisi.'],
            ],
            'executor_name' => ['label' => 'Nama pelaksana', 'rules' => 'required|max_length[120]'],
            'status' => ['label' => 'Status penanganan', 'rules' => 'required|in_list[in_progress,completed,unrepairable]'],
        ];
        if ($actionStatus === 'completed') {
            $rules['result_condition'] = ['label' => 'Hasil kondisi', 'rules' => 'required|in_list[good,minor_damage,major_damage]'];
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $reportId = (int) $this->request->getPost('damage_report_id');
        $report = model('App\Models\DamageReportModel')->find($reportId);
        if (! $report) {
            return redirect()->to('/petugas/damages')->with('error', 'Laporan tidak ditemukan.');
        }

        $resultCondition = $actionStatus === 'completed'
            ? $this->request->getPost('result_condition')
            : null;

        $items = model('App\Models\ItemModel');
        try {
            $items->withLockedItem((int) $report->item_id, function ($item) use ($items, $reportId, $actionStatus, $resultCondition) {
                // Re-read after the unit lock: another request may have closed this report.
                $report = model('App\Models\DamageReportModel')->find($reportId);
                if (! $report || (int) $report->item_id !== (int) $item->id
                    || ! in_array($report->status, ['reported', 'in_handling'], true)) {
                    throw new \DomainException('Laporan ini sudah selesai atau tidak dapat diperbarui.');
                }
                $actionId = model('App\Models\HandlingActionModel')->insert([
                    'damage_report_id'   => $reportId,
                    'action_date'        => $this->request->getPost('action_date'),
                    'action_description' => $this->request->getPost('action_description'),
                    'executor_name'      => $this->request->getPost('executor_name'),
                    'result_condition'   => $resultCondition ?: null,
                    'status'             => $actionStatus,
                ]);
                $reportStatus = $actionStatus === 'in_progress' ? 'in_handling' : $actionStatus;
                if (! $actionId || ! model('App\Models\DamageReportModel')->update($reportId, ['status' => $reportStatus])) {
                    throw new \RuntimeException('Penanganan gagal disimpan.');
                }
                $changes = $actionStatus === 'completed' ? ['condition' => $resultCondition] : [];
                $updated = $items->synchronizeState($item, $changes);
                $note = match ($actionStatus) {
                    'completed' => 'Penanganan selesai; hasil pemeriksaan: ' . condition_label($resultCondition),
                    'unrepairable' => 'Tidak dapat diperbaiki: ' . (string) $this->request->getPost('action_description'),
                    default => 'Keterangan penanganan: ' . (string) $this->request->getPost('action_description'),
                };
                log_status_history((int) $item->id, $item->status, $updated->status, $note, null, $reportId);
                log_activity('tindakan_penanganan', 'Penanganan', $item->item_code, 'Laporan #' . $report->id
                    . "\nPelaksana: " . (string) $this->request->getPost('executor_name')
                    . "\nTindakan: " . (string) $this->request->getPost('action_description')
                    . "\nHasil: " . condition_label((string) $this->request->getPost('result_condition'))
                    . "\nStatus: " . (string) $this->request->getPost('status'));
            });
        } catch (\DomainException $e) {
            return redirect()->to('/petugas/handling/' . $reportId)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', 'Gagal menyimpan penanganan {id}: {message}', ['id' => $reportId, 'message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Penanganan gagal disimpan. Silakan coba lagi.');
        }

        return redirect()->to('/petugas/handling/' . $reportId)->with('success', 'Keterangan penanganan berhasil disimpan.');
    }

    public function show(int $id)
    {
        $report = model('App\Models\DamageReportModel')->select('damage_reports.*, items.item_code, items.item_name, items.brand, items.model, items.status AS item_status, items.condition AS item_condition, users.full_name AS reported_by_name')
            ->join('items', 'items.id = damage_reports.item_id')
            ->join('users', 'users.id = damage_reports.reported_by', 'left')
            ->where('damage_reports.id', $id)->first();

        if (! $report) {
            return redirect()->to('/petugas/damages')->with('error', 'Laporan tidak ditemukan.');
        }

        $actions = model('App\Models\HandlingActionModel')->where('damage_report_id', $id)->orderBy('action_date', 'ASC')->findAll();

        return view('petugas/handling_detail', [
            'title'   => 'Detail Penanganan',
            'report'  => $report,
            'actions' => $actions,
        ]);
    }
}
