<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;
use App\Libraries\DocumentNumbers;
use App\Libraries\LoanCheckout;

class Loans extends BaseController
{
    public function index()
    {
        $status = (string) $this->request->getGet('status');
        $q = (string) $this->request->getGet('q');

        $model = model('App\Models\LoanModel');
        $loans = $model->withDetails($status ?: 'all', $q ?: null, includeGroups: true)
            ->select('items.category, items.physical_photo')
            ->paginate(per_page(10));

        $statusCounts = [];
        foreach (['all', 'ongoing', 'late', 'completed'] as $scope) {
            $statusCounts[$scope] = $model->withDetails($scope, $q ?: null, includeGroups: true)->countAllResults();
        }

        return view('petugas/loans', [
            'title'  => 'Peminjaman',
            'loans'  => $loans,
            'pager'  => $model->pager,
            'status' => $status,
            'q'      => $q,
            'status_counts' => $statusCounts,
        ]);
    }

    public function create(int $itemId)
    {
        $item = model('App\Models\ItemModel')->find($itemId);
        if (! $item || ! $item->is_active) {
            return redirect()->to('/petugas/items')->with('error', 'Unit tidak ditemukan.');
        }
        if ($item->status !== 'available') {
            return redirect()->to('/petugas/items/' . $itemId)->with('error', 'Unit ini sedang ' . item_status_label($item->status) . ' dan tidak dapat dipinjam.');
        }
        $activeLoan = model('App\Models\LoanModel')->activeLoanByItem($itemId);
        if ($activeLoan) {
            return redirect()->to('/petugas/loans/' . $activeLoan->id)->with('error', 'Unit ini masih memiliki transaksi aktif.');
        }
        $state = model('App\Models\ItemModel')->stateFor($item);
        if ($state['status'] !== 'available') {
            return redirect()->to('/petugas/items/' . $itemId)->with('error', 'Unit ini belum dapat dipinjam karena kondisi atau laporan kerusakannya.');
        }

        $oldPost = session()->get('_ci_old_input')['post'] ?? [];
        $borrower = null;
        if (filter_var($oldPost['item_id'] ?? null, FILTER_VALIDATE_INT) === $itemId) {
            $borrowerId = filter_var($oldPost['borrower_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($borrowerId !== false) {
                $borrower = model('App\Models\BorrowerModel')->find($borrowerId);
            }
        }

        return view('petugas/loan_create', [
            'title' => 'Peminjaman Baru',
            'item'  => $item,
            'borrower' => ! empty($borrower->is_active) ? $borrower : null,
            'accessories' => (new \App\Libraries\AccessoryChecklist())->template($itemId),
            'accessory_template_hash' => (new \App\Libraries\AccessoryChecklist())->templateHash($itemId),
        ]);
    }

    public function searchBorrowers()
    {
        $type = (string) $this->request->getGet('type');
        $q = (string) $this->request->getGet('q');
        $class = (string) $this->request->getGet('class');

        $model = model('App\Models\BorrowerModel');
        $rows = $type === 'student'
            ? $model->students($q ?: null, $class ?: null)
            : $model->teachers($q ?: null);

        $out = array_map(static function ($b) {
            return [
                'id'              => (int) $b->id,
                'name'            => $b->name,
                'identity_number' => $b->identity_number,
                'type'            => $b->type,
                'class_name'      => $b->class_name,
                'gender'          => $b->gender,
                'meta'            => ($b->type === 'teacher' ? 'NIP ' : 'NISN ') . $b->identity_number . ($b->class_name ? ' · ' . $b->class_name : ''),
            ];
        }, $rows);

        return $this->response->setJSON($out);
    }

    public function store()
    {
        $itemId = (int) $this->request->getPost('item_id');
        $borrowerId = (int) $this->request->getPost('borrower_id');
        $dueDate = trim((string) $this->request->getPost('due_date'));
        $dueTime = trim((string) $this->request->getPost('due_time'));

        $rules = [
            'item_id' => ['label' => 'Unit barang', 'rules' => 'required|is_natural_no_zero'],
            'condition_out' => ['label' => 'Kondisi awal', 'rules' => 'required|in_list[good,minor_damage,major_damage]'],
            'completeness_out' => ['label' => 'Kelengkapan awal', 'rules' => 'required|in_list[lengkap,tidak_lengkap]'],
        ] + LoanCheckout::DETAIL_RULES;

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $item = model('App\Models\ItemModel')->find($itemId);
        $borrower = model('App\Models\BorrowerModel')->find($borrowerId);

        if (! $item || ! $item->is_active) {
            return redirect()->to('/petugas/items')->with('error', 'Unit tidak ditemukan.');
        }
        if ($item->status !== 'available') {
            return redirect()->to('/petugas/items/' . $itemId)->with('error', 'Unit sedang ' . item_status_label($item->status) . '.');
        }
        if (! $borrower || ! $borrower->is_active) {
            return redirect()->back()->withInput()->with('error', 'Peminjam tidak ditemukan.');
        }

        $items = model('App\Models\ItemModel');
        try {
            $loanId = $items->withLockedItem($itemId, function ($item) use ($items, $borrower, $dueDate, $dueTime) {
                $checkout = new LoanCheckout(\Config\Database::connect(), $items);
                $checklist = $checkout->inspect($item, $this->request->getPost());
                if (strtotime($dueDate . ' ' . $dueTime . ':00') <= time()) {
                    throw new \DomainException('Rencana kembali harus setelah waktu peminjaman.');
                }
                return $checkout->record($item, $borrower, [
                    'borrowed_at'      => date('Y-m-d H:i:s'),
                    'due_at'           => $dueDate . ' ' . $dueTime . ':00',
                    'purpose'          => $this->request->getPost('purpose'),
                    'usage_location'   => $this->request->getPost('usage_location'),
                    'completeness_out' => $this->request->getPost('completeness_out'),
                ], $checklist);
            });
        } catch (\DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', 'Gagal menyimpan peminjaman: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Peminjaman gagal disimpan. Silakan coba lagi.');
        }

        return redirect()->to('/petugas/loans/' . $loanId . '/success')->with('success', 'Peminjaman berhasil disimpan.');
    }

    public function show(int $id)
    {
        $loan = model('App\Models\LoanModel')->select('loans.*, items.item_code, items.item_name, items.brand, items.model, items.physical_photo, items.status AS item_status, borrowers.name AS borrower_name, borrowers.type AS borrower_type, borrowers.identity_number, borrowers.class_name, users.full_name AS processed_by_name')
            ->join('items', 'items.id = loans.item_id')
            ->join('borrowers', 'borrowers.id = loans.borrower_id')
            ->join('users', 'users.id = loans.processed_by', 'left')
            ->where('loans.id', $id)->first();

        if (! $loan) {
            return redirect()->to('/petugas/loans')->with('error', 'Transaksi tidak ditemukan.');
        }

        $listParams = $this->request->getGet('daftar');
        $listBackUrl = null;
        if (is_array($listParams)) {
            $listParams = array_intersect_key($listParams, array_flip(['q', 'status', 'per_page', 'page']));
            $query = http_build_query(array_filter($listParams, 'is_string'));
            $listBackUrl = base_url('/petugas/loans') . ($query !== '' ? '?' . $query : '') . '#inventory-loan-' . $id;
        }

        return view('petugas/loan_detail', ['title' => 'Detail Peminjaman', 'loan' => $loan, 'listBackUrl' => $listBackUrl]);
    }

    public function success(int $id)
    {
        $loan = model('App\Models\LoanModel')->select('loans.*, items.item_code, items.item_name, items.brand, items.model, borrowers.name AS borrower_name, borrowers.type AS borrower_type')
            ->join('items', 'items.id = loans.item_id')
            ->join('borrowers', 'borrowers.id = loans.borrower_id')
            ->where('loans.id', $id)->first();

        if (! $loan) {
            return redirect()->to('/petugas/loans');
        }
        return view('petugas/loan_success', ['title' => 'Transaksi Berhasil', 'loan' => $loan]);
    }

    public function returnForm(int $id)
    {
        $loan = model('App\Models\LoanModel')->select('loans.*, items.item_code, items.item_name, items.brand, items.model, items.status AS item_status, borrowers.name AS borrower_name, borrowers.type AS borrower_type, borrowers.identity_number')
            ->join('items', 'items.id = loans.item_id')
            ->join('borrowers', 'borrowers.id = loans.borrower_id')
            ->where('loans.id', $id)->first();

        if (! $loan) {
            return redirect()->to('/petugas/loans')->with('error', 'Transaksi tidak ditemukan.');
        }
        if ($loan->returned_at) {
            return redirect()->to('/petugas/loans/' . $id)->with('info', 'Transaksi ini sudah dikembalikan.');
        }

        return view('petugas/loan_return', ['title' => 'Pengembalian Barang', 'loan' => $loan]);
    }

    public function returnStore(int $id)
    {
        $loan = model('App\Models\LoanModel')->select('loans.*, items.item_code, items.item_name')
            ->join('items', 'items.id = loans.item_id')
            ->where('loans.id', $id)->first();

        if (! $loan || $loan->returned_at) {
            return redirect()->to('/petugas/loans')->with('error', 'Transaksi tidak ditemukan atau sudah selesai.');
        }

        $condition = (string) $this->request->getPost('condition_in');
        $completeness = (string) $this->request->getPost('completeness_in');
        $inspection = null;
        if (! empty($loan->accessory_checklist)) {
            try {
                $inspection = (new \App\Libraries\AccessoryChecklist())->inspectReturn($id, $this->request->getPost());
                $completeness = $inspection['completeness'];
            } catch (\DomainException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }
        $returnNotes = trim((string) $this->request->getPost('return_notes'));
        $returnPhoto = $this->request->getFile('return_photo');

        if (! in_array($condition, ['good', 'minor_damage', 'major_damage'], true)) {
            return redirect()->back()->with('error', 'Kondisi saat kembali tidak valid.');
        }
        if (! in_array($completeness, ['lengkap', 'tidak_lengkap'], true)) {
            return redirect()->back()->with('error', 'Kelengkapan saat kembali tidak valid.');
        }
        if ($completeness === 'tidak_lengkap' && $returnNotes === '') {
            return redirect()->back()->withInput()->with('error', 'Catatan pemeriksaan wajib diisi untuk barang yang tidak lengkap.');
        }
        if ($condition !== 'good' && $returnNotes === '') {
            return redirect()->back()->withInput()->with('error', 'Catatan pemeriksaan wajib diisi untuk barang yang rusak.');
        }
        if ($condition !== 'good' && (! $returnPhoto || $returnPhoto->getError() === UPLOAD_ERR_NO_FILE)) {
            return redirect()->back()->withInput()->with('error', 'Foto pemeriksaan wajib diunggah untuk barang yang rusak.');
        }
        if ($completeness === 'tidak_lengkap' && (! $returnPhoto || $returnPhoto->getError() === UPLOAD_ERR_NO_FILE)) {
            return redirect()->back()->withInput()->with('error', 'Foto pemeriksaan wajib diunggah untuk barang yang tidak lengkap.');
        }

        try {
            $photo = upload_photo($returnPhoto);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $items = model('App\Models\ItemModel');
        try {
            $reportId = $items->withLockedItem((int) $loan->item_id, function ($item) use ($items, $id, $condition, $completeness, $returnNotes, $photo, $inspection) {
                $loan = model('App\Models\LoanModel')->find($id);
                if (! $loan || $loan->returned_at || (int) $loan->item_id !== (int) $item->id) {
                    throw new \DomainException('Transaksi tidak ditemukan atau sudah selesai.');
                }
                if ($inspection !== null) {
                    (new \App\Libraries\AccessoryChecklist())->saveReturn($inspection['rows']);
                }
                $saved = model('App\Models\LoanModel')->update($id, [
                    'returned_at'      => date('Y-m-d H:i:s'),
                    'condition_in'     => $condition,
                    'completeness_in'  => $completeness,
                    'return_photo'     => $photo,
                    'return_notes'     => $returnNotes ?: null,
                    'returned_by'      => (int) session()->get('user_id'),
                ]);
                if (! $saved) {
                    throw new \RuntimeException('Pengembalian gagal disimpan.');
                }
                $isDamaged = $condition !== 'good';
                $completenessLabel = $completeness === 'lengkap' ? 'lengkap' : 'tidak lengkap';
                $returnDetail = 'Kondisi ' . strtolower(condition_label($condition)) . ' dan ' . $completenessLabel;

                $reportId = null;
                if ($isDamaged) {
                    $db = \Config\Database::connect();
                    $reportNumber = (new DocumentNumbers($db))->next('damage');
                    $reportId = model('App\Models\DamageReportModel')->insert([
                        'report_number' => $reportNumber,
                        'item_id'       => (int) $loan->item_id,
                        'loan_id'       => (int) $id,
                        'discovered_at' => date('Y-m-d H:i:s'),
                        'damage_level'  => $condition,
                        'description'   => $returnNotes,
                        'damage_photo'  => $photo,
                        'status'        => 'reported',
                        'reported_by'   => (int) session()->get('user_id'),
                    ]);
                    if (! $reportId) {
                        throw new \RuntimeException('Laporan kerusakan pengembalian gagal disimpan.');
                    }
                }
                $updated = $items->synchronizeState($item, ['condition' => $condition]);
                log_status_history(
                    (int) $loan->item_id,
                    $item->status,
                    $updated->status,
                    'Dikembalikan dengan ' . $returnDetail,
                    (int) $id,
                    $reportId ? (int) $reportId : null
                );
                log_activity('pengembalian', 'Peminjaman', $item->item_code, 'Transaksi ' . $loan->loan_number . "\n" . ucfirst($returnDetail)
                    . ($inspection !== null ? "\nKelengkapan dikembalikan\n" . \App\Libraries\AccessoryChecklist::describeRows($inspection['rows'], true) : '')
                    . ($returnNotes !== '' ? "\nCatatan: " . $returnNotes : ''));
                if ($reportId) {
                    log_activity('kerusakan_dilaporkan', 'Kerusakan', $item->item_code, $returnNotes);
                }
                return $reportId;
            });
        } catch (\DomainException $e) {
            delete_photo($photo);
            return redirect()->to('/petugas/loans/' . $id)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            delete_photo($photo);
            log_message('error', 'Gagal menyimpan pengembalian {id}: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Pengembalian gagal disimpan. Silakan coba lagi.');
        }

        if ($reportId) {
            return redirect()->to('/petugas/damages/' . $reportId)->with('success', 'Pengembalian dicatat dan laporan kerusakan dibuat otomatis.');
        }

        if (! empty($loan->loan_group_id)) {
            return redirect()->to('/petugas/loans/groups/' . $loan->loan_group_id)->with('success', 'Pengembalian unit berhasil dicatat.');
        }

        return redirect()->to('/petugas/loans/' . $id . '/success?type=return')->with('success', 'Pengembalian berhasil dicatat.');
    }

    public function activeByItem(int $itemId)
    {
        $loan = model('App\Models\LoanModel')->activeLoanByItem($itemId);
        if (! $loan) {
            return $this->response->setJSON(['ok' => false]);
        }
        return $this->response->setJSON(['ok' => true, 'id' => (int) $loan->id]);
    }
}
