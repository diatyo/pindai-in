<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;
use App\Libraries\DocumentNumbers;

class Damages extends BaseController
{
    public function index()
    {
        $status = (string) $this->request->getGet('status');
        $q = (string) $this->request->getGet('q');
        $itemId = (int) $this->request->getGet('item');

        $model = model('App\Models\DamageReportModel');
        $reports = $model->withDetails($status ?: 'all', $q ?: null);
        if ($itemId > 0) {
            $reports->where('damage_reports.item_id', $itemId);
        }
        $reports = $reports->paginate(per_page(10));

        $counts = model('App\Models\DamageReportModel')
            ->select('status, COUNT(*) AS total')->groupBy('status')->findAll();
        $statusCounts = ['all' => 0, 'reported' => 0, 'in_handling' => 0, 'completed' => 0, 'unrepairable' => 0];
        foreach ($counts as $row) {
            $statusCounts[$row->status] = (int) $row->total;
            $statusCounts['all'] += (int) $row->total;
        }

        return view('petugas/damages', [
            'title'   => 'Kerusakan & Penanganan',
            'reports' => $reports,
            'pager'   => $model->pager,
            'status'  => $status,
            'q'       => $q,
            'item_id' => $itemId,
            'status_counts' => $statusCounts,
        ]);
    }

    public function create(int $itemId = 0, int $loanId = 0)
    {
        $items = [];
        if ($itemId > 0) {
            $item = model('App\Models\ItemModel')->find($itemId);
            if ($item) {
                $items[] = $item;
            }
        } else {
            $items = model('App\Models\ItemModel')->where('is_active', 1)->orderBy('item_code', 'ASC')->findAll();
        }

        $loan = null;
        if ($loanId > 0) {
            $loan = model('App\Models\LoanModel')->select('loans.*, items.item_code, items.item_name, borrowers.name AS borrower_name')
                ->join('items', 'items.id = loans.item_id')
                ->join('borrowers', 'borrowers.id = loans.borrower_id')
                ->where('loans.id', $loanId)->first();
        }

        return view('petugas/damage_form', [
            'title'    => 'Lapor Kerusakan',
            'items'    => $items,
            'preset_item_id' => $itemId,
            'loan'     => $loan,
            'categories' => [],
        ]);
    }

    public function store()
    {
        $rules = [
            'item_id' => ['label' => 'Unit barang', 'rules' => 'required|is_natural_no_zero'],
            'discovered_at' => ['label' => 'Waktu ditemukan', 'rules' => 'required|valid_date'],
            'damage_level' => ['label' => 'Tingkat kerusakan', 'rules' => 'required|in_list[minor_damage,major_damage]'],
            'description' => ['label' => 'Deskripsi kerusakan', 'rules' => 'required|max_length[255]'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $item = model('App\Models\ItemModel')->find((int) $this->request->getPost('item_id'));
        if (! $item) {
            return redirect()->to('/petugas/damages')->with('error', 'Unit tidak ditemukan.');
        }

        try {
            $photo = upload_photo($this->request->getFile('damage_photo'));
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('errors', ['photo' => $e->getMessage()]);
        }

        $items = model('App\Models\ItemModel');
        try {
            $reportId = $items->withLockedItem((int) $item->id, function ($item) use ($items, $photo) {
                $loanId = (int) $this->request->getPost('loan_id');
                if ($this->request->getPost('loan_id')) {
                    $loan = model('App\Models\LoanModel')->find($loanId);
                    if (! $loan || (int) $loan->item_id !== (int) $item->id) {
                        throw new \DomainException('Transaksi peminjaman tidak sesuai dengan unit yang dilaporkan.');
                    }
                }
                $db = \Config\Database::connect();
                $reportNumber = (new DocumentNumbers($db))->next('damage');
                $reportId = model('App\Models\DamageReportModel')->insert([
                    'report_number'  => $reportNumber,
                    'item_id'        => $item->id,
                    'loan_id'        => $loanId ?: null,
                    'discovered_at'  => date('Y-m-d H:i:s', strtotime((string) $this->request->getPost('discovered_at'))),
                    'damage_level'   => $this->request->getPost('damage_level'),
                    'description'    => $this->request->getPost('description'),
                    'damage_photo'   => $photo,
                    'notes'          => $this->request->getPost('notes') ?: null,
                    'status'         => 'reported',
                    'reported_by'    => (int) session()->get('user_id'),
                ]);
                if (! $reportId) {
                    throw new \RuntimeException('Laporan kerusakan gagal disimpan.');
                }
                $updated = $items->synchronizeState($item);
                log_status_history((int) $item->id, $item->status, $updated->status, 'Kerusakan dilaporkan: ' . (string) $this->request->getPost('description'), $loanId ?: null, (int) $reportId);
                log_activity('kerusakan_dilaporkan', 'Kerusakan', $item->item_code, 'Laporan ' . $reportNumber
                    . "\nTingkat kerusakan: " . condition_label((string) $this->request->getPost('damage_level'))
                    . "\n" . (string) $this->request->getPost('description'));
                return $reportId;
            });
        } catch (\DomainException $e) {
            delete_photo($photo);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            delete_photo($photo);
            log_message('error', 'Gagal menyimpan kerusakan: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Laporan kerusakan gagal disimpan. Silakan coba lagi.');
        }

        return redirect()->to('/petugas/damages/' . $reportId)->with('success', 'Laporan kerusakan berhasil disimpan.');
    }

    public function show(int $id)
    {
        $report = model('App\Models\DamageReportModel')->select('damage_reports.*, items.item_code, items.item_name, items.brand, items.model, items.location, items.condition AS item_condition, users.full_name AS reported_by_name, loans.loan_number, loans.loan_group_id, borrowers.name AS borrower_name')
            ->join('items', 'items.id = damage_reports.item_id')
            ->join('users', 'users.id = damage_reports.reported_by', 'left')
            ->join('loans', 'loans.id = damage_reports.loan_id', 'left')
            ->join('borrowers', 'borrowers.id = loans.borrower_id', 'left')
            ->where('damage_reports.id', $id)->first();

        if (! $report) {
            return redirect()->to('/petugas/damages')->with('error', 'Laporan tidak ditemukan.');
        }

        $actions = model('App\Models\HandlingActionModel')->where('damage_report_id', $id)->orderBy('action_date', 'ASC')->findAll();

        return view('petugas/damage_detail', [
            'title'   => 'Detail Kerusakan',
            'report'  => $report,
            'actions' => $actions,
        ]);
    }
}
