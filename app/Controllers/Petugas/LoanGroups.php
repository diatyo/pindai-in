<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;
use App\Libraries\AccessoryChecklist;
use App\Libraries\DocumentNumbers;
use App\Libraries\LoanCheckout;
use App\Models\BorrowerModel;
use App\Models\ItemModel;
use App\Models\LoanModel;
use Config\Database;
use DomainException;
use RuntimeException;

class LoanGroups extends BaseController
{
    private const MAX_UNITS = 40;
    private const CREATE_URL = '/petugas/loans/groups/create';

    public function create()
    {
        $old = session()->get('_ci_old_input')['post'] ?? [];
        $requestKey = $old['request_key'] ?? null;
        $borrower = null;
        $drafts = [];
        if (is_string($requestKey) && preg_match('/\A[a-f0-9]{32}\z/', $requestKey)) {
            $borrowerId = filter_var($old['borrower_id'] ?? null, FILTER_VALIDATE_INT);
            $borrower = $borrowerId > 0 ? model(BorrowerModel::class)->find($borrowerId) : null;
            try {
                $drafts = $this->decodeUnits($old['group_units'] ?? null);
            } catch (DomainException) {
                // A malformed draft cannot supply unit or checklist data.
            }
        } else {
            $requestKey = bin2hex(random_bytes(16));
        }
        $items = model(ItemModel::class);
        $checklists = new AccessoryChecklist();
        $units = [];
        $found = $drafts ? array_column($items->find(array_keys($drafts)), null, 'id') : [];
        foreach ($drafts as $id => $draft) {
            if (isset($found[$id])) {
                $units[] = $this->unitData($found[$id], $items, $checklists, $draft);
            }
        }
        return view('petugas/loan_create', [
            'title' => 'Peminjaman Kelompok', 'isGroup' => true, 'requestKey' => $requestKey,
            'groupUnits' => $units, 'maxUnits' => self::MAX_UNITS,
            'borrower' => ! empty($borrower->is_active) ? $borrower : null,
        ]);
    }

    public function lookup()
    {
        $input = $this->request->getGet('code');
        $code = is_string($input) ? qr_unit_code($input) : '';
        if ($code === '' || strlen($code) > 30) {
            return $this->response->setStatusCode(422)->setJSON(['message' => 'Masukkan kode unit yang valid.']);
        }
        $items = model(ItemModel::class);
        $item = $items->where('item_code', $code)->first();
        if (! $item) {
            return $this->response->setStatusCode(404)->setJSON(['message' => 'Kode unit tidak ditemukan.']);
        }
        $data = $this->unitData($item, $items, new AccessoryChecklist());
        if ($data['blocked']) {
            return $this->response->setStatusCode(409)->setJSON(['message' => $item->item_name . ' · ' . $data['blocked']]);
        }
        return $this->response->setJSON(['id' => (int) $item->id, 'name' => $item->item_name, 'html' => view('partials/loan_group_unit', $data)]);
    }

    public function store()
    {
        $post = $this->request->getPost();
        $staffId = (int) session()->get('user_id');
        $requestKey = $post['request_key'] ?? null;
        if (! is_string($requestKey) || ! preg_match('/\A[a-f0-9]{32}\z/', $requestKey)) {
            return redirect()->to(self::CREATE_URL)->withInput()->with('error', 'Buka kembali formulir peminjaman kelompok.');
        }
        $db = Database::connect();
        $existingGroup = static fn () => $db->table('loan_groups')->where('created_by', $staffId)->where('request_key', $requestKey)->get()->getRow();
        if ($existing = $existingGroup()) {
            return redirect()->to('/petugas/loans/groups/' . $existing->id);
        }
        if (! $this->validate(LoanCheckout::DETAIL_RULES)) {
            return redirect()->to(self::CREATE_URL)->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $drafts = $this->decodeUnits($post['group_units'] ?? null);
            if (count($drafts) < 2) {
                throw new DomainException('Tambahkan minimal dua unit untuk peminjaman kelompok.');
            }
            if (($post['accessory_confirmed'] ?? null) !== '1') {
                throw new DomainException('Konfirmasikan pemeriksaan semua unit dan kelengkapannya.');
            }
            $borrower = model(BorrowerModel::class)->find((int) $post['borrower_id']);
            if (empty($borrower->is_active)) {
                throw new DomainException('Peminjam tidak ditemukan atau sudah nonaktif.');
            }
            $dueAt = $post['due_date'] . ' ' . $post['due_time'] . ':00';
            $items = model(ItemModel::class);
            $groupId = $items->withLockedItems(array_keys($drafts), function (array $locked) use ($db, $items, $drafts, $borrower, $staffId, $requestKey, $dueAt, $post) {
                if (strtotime($dueAt) <= time()) {
                    throw new DomainException('Rencana kembali harus setelah waktu peminjaman.');
                }
                $checkout = new LoanCheckout($db, $items);
                $inspections = [];
                foreach ($drafts as $id => $draft) {
                    try {
                        if ($locked[$id]->status !== 'available') {
                            throw new DomainException('Unit sedang ' . item_status_label($locked[$id]->status) . '.');
                        }
                        $inspections[$id] = $checkout->inspect($locked[$id], [
                            'accessory_checklist' => '1', 'accessory_confirmed' => '1',
                            'accessory_template_hash' => $draft['accessory_template_hash'] ?? null,
                            'accessory_sent' => $draft['accessory_sent'] ?? [],
                            'extra_accessories' => $draft['extra_accessories'] ?? [],
                        ]);
                    } catch (DomainException $e) {
                        throw new DomainException($locked[$id]->item_name . ' (' . $locked[$id]->item_code . '): ' . $e->getMessage());
                    }
                }

                $number = (new DocumentNumbers($db))->next('loan_group');
                if (! $db->table('loan_groups')->insert(['group_number' => $number, 'request_key' => $requestKey, 'created_by' => $staffId])) {
                    throw new RuntimeException('Kelompok gagal disimpan.');
                }
                $groupId = (int) $db->insertID();
                $details = [
                    'loan_group_id' => $groupId, 'borrowed_at' => date('Y-m-d H:i:s'), 'due_at' => $dueAt,
                    'purpose' => $post['purpose'], 'usage_location' => $post['usage_location'], 'completeness_out' => 'lengkap',
                ];
                foreach ($inspections as $id => $inspection) {
                    $checkout->record($locked[$id], $borrower, $details, $inspection);
                }
                log_activity('peminjaman_kelompok_dibuat', 'Peminjaman', $number, count($inspections) . ' unit · Peminjam: ' . $borrower->name);
                return $groupId;
            });
        } catch (\Throwable $e) {
            if ($existing = $existingGroup()) {
                return redirect()->to('/petugas/loans/groups/' . $existing->id);
            }
            if ($e instanceof DomainException) {
                return redirect()->to(self::CREATE_URL)->withInput()->with('error', $e->getMessage());
            }
            log_message('error', 'Gagal menyimpan peminjaman kelompok: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(self::CREATE_URL)->withInput()->with('error', 'Peminjaman kelompok gagal disimpan. Silakan coba lagi.');
        }
        return redirect()->to('/petugas/loans/groups/' . $groupId)->with('success', 'Peminjaman kelompok berhasil disimpan.');
    }

    public function show(int $id)
    {
        $db = Database::connect();
        $group = $db->table('loan_groups')->where('id', $id)->get()->getRow();
        if (! $group) {
            return redirect()->to('/petugas/loans')->with('error', 'Peminjaman kelompok tidak ditemukan.');
        }
        $loans = model(LoanModel::class)->withDetails()->where('loans.loan_group_id', $id)->orderBy('items.item_code')->findAll();
        if ($loans === []) {
            return redirect()->to('/petugas/loans')->with('error', 'Peminjaman kelompok tidak memiliki unit.');
        }
        $accessories = [];
        $rows = $db->table('loan_accessories')->whereIn('loan_id', array_column($loans, 'id'))->orderBy('id')->get()->getResultArray();
        foreach ($rows as $row) {
            $accessories[$row['loan_id']][] = $row;
        }
        return view('petugas/loan_group_detail', ['group' => $group, 'loans' => $loans, 'accessories' => $accessories]);
    }

    private function decodeUnits(mixed $raw): array
    {
        if (! is_string($raw) || strlen($raw) > 750000) {
            throw new DomainException('Daftar unit tidak valid.');
        }
        $units = json_decode($raw, true, 10);
        if (! is_array($units) || count($units) > self::MAX_UNITS) {
            throw new DomainException('Daftar unit tidak valid atau melebihi ' . self::MAX_UNITS . ' unit.');
        }
        foreach ($units as $id => $unit) {
            if (! is_int($id) || $id < 1 || ! is_array($unit)) {
                throw new DomainException('Daftar unit tidak valid.');
            }
        }
        return $units;
    }

    private function unitData(object $item, ItemModel $items, AccessoryChecklist $checklists, array $draft = []): array
    {
        $state = $items->stateFor($item)['status'];
        $status = $state !== 'available' ? $state : $item->status;
        $accessories = $checklists->template((int) $item->id);
        $hash = $checklists->templateHash((int) $item->id, $accessories);
        $stale = $draft !== [] && ($draft['accessory_template_hash'] ?? null) !== $hash;
        return [
            'item' => $item, 'blocked' => $status === 'available' ? '' : 'Unit sedang ' . item_status_label($status) . '.',
            'accessories' => $accessories, 'accessory_template_hash' => $hash,
            'draft' => $stale ? array_diff_key($draft, ['accessory_sent' => true]) : $draft, 'stale' => $stale,
        ];
    }
}
