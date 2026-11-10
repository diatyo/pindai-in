<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;
use App\Libraries\DocumentNumbers;
use App\Models\ItemModel;
use App\Models\ItemReferenceModel;

class Items extends BaseController
{
    public function index()
    {
        $q = (string) $this->request->getGet('q');
        $status = (string) $this->request->getGet('status');
        $category = (string) $this->request->getGet('category');

        $items = model('App\Models\ItemModel')->withStats($q ?: null, $status ?: null, $category ?: null)
            ->paginate(per_page(10));

        return view('petugas/items', [
            'title'        => 'Data Barang',
            'items'        => $items,
            'pager'        => model('App\Models\ItemModel')->pager,
            'q'            => $q,
            'status'       => $status,
            'category'     => $category,
            'categories'   => model(ItemReferenceModel::class)->names('category', null, true),
            'archivedCount' => model('App\Models\ItemModel')->withStats($q ?: null, 'archived', $category ?: null)->countAllResults(),
            'statusCounts' => model('App\Models\ItemModel')->statusCounts($q ?: null, $category ?: null),
        ]);
    }

    public function archive(int $id)
    {
        return $this->setArchived($id, true);
    }

    public function restore(int $id)
    {
        return $this->setArchived($id, false);
    }

    private function setArchived(int $id, bool $archived)
    {
        $items = model('App\Models\ItemModel');
        $item = $items->find($id);
        if (! $item) {
            return redirect()->to('/petugas/items')->with('error', 'Unit tidak ditemukan.');
        }
        $target = $archived ? '/petugas/items' : '/petugas/items?status=archived';
        $active = $archived ? 0 : 1;
        if ((int) $item->is_active === $active && ($archived ? $item->status === 'disabled' : $item->status !== 'disabled')) {
            return redirect()->to($target)->with('info', $archived ? 'Barang sudah diarsipkan.' : 'Barang sudah aktif.');
        }
        try {
            $items->withLockedItem($id, function ($item) use ($items, $id, $active, $archived) {
                $updated = $items->synchronizeState($item, ['is_active' => $active]);
                log_status_history($id, $item->status, $updated->status, $archived ? 'Barang diarsipkan.' : 'Barang diaktifkan kembali berdasarkan transaksi dan kondisi terkini.');
                log_activity($archived ? 'barang_diarsipkan' : 'barang_diaktifkan', 'Barang', $item->item_code,
                    $archived ? 'Barang diarsipkan dan tidak dapat dipinjam. Riwayat transaksi tetap tersimpan.' : 'Barang diaktifkan kembali dari arsip.');
            });
        } catch (\Throwable $e) {
            log_message('error', 'Gagal mengubah arsip unit {id}: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            return redirect()->to($target)->with('error', 'Perubahan arsip gagal disimpan. Silakan coba lagi.');
        }
        return redirect()->to($target)->with('success', $archived ? 'Barang berhasil diarsipkan dan tidak dapat dipinjam.' : 'Barang berhasil diaktifkan kembali.');
    }

    public function create()
    {
        return view('petugas/item_form', [
            'title'      => 'Tambah Barang',
            'mode'       => 'create',
            'categories' => model(ItemReferenceModel::class)->names('category'),
            'locations' => model(ItemReferenceModel::class)->names('location'),
            'accessories' => [],
        ]);
    }

    public function store()
    {
        $serialStatus = $this->request->getPost('serial_status') ?? 'known';
        $rules = [
            'category' => ['label' => 'Kategori', 'rules' => 'required|max_length[60]'],
            'item_name' => ['label' => 'Nama barang', 'rules' => 'required|regex_match[/\S/]|max_length[150]'],
            'brand' => ['label' => 'Merek', 'rules' => 'required|regex_match[/\S/]|max_length[100]'],
            'model' => ['label' => 'Model', 'rules' => 'required|regex_match[/\S/]|max_length[100]'],
            'acquisition_date' => ['label' => 'Tanggal pengadaan', 'rules' => 'required|valid_date[Y-m-d]'],
            'specifications' => ['label' => 'Spesifikasi', 'rules' => 'required|regex_match[/\S/]'],
            'physical_photo' => ['label' => 'Foto fisik', 'rules' => 'uploaded[physical_photo]', 'errors' => ['uploaded' => 'Foto fisik wajib diunggah.']],
            'location' => ['label' => 'Lokasi', 'rules' => 'required|max_length[100]'],
            'condition' => ['label' => 'Kondisi', 'rules' => 'required|in_list[good,minor_damage,major_damage]'],
        ] + $this->serialRules($serialStatus);

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $physicalPhoto = $serialPhoto = null;
        try {
            $physicalPhoto = upload_photo($this->request->getFile('physical_photo'), 'uploads', 'fisik');
            if ($serialStatus === 'known') {
                $serialPhoto = upload_photo($this->request->getFile('serial_photo'), 'uploads', 'serial');
            }
        } catch (\RuntimeException $e) {
            delete_photo($physicalPhoto);
            delete_photo($serialPhoto);
            return redirect()->back()->withInput()->with('errors', ['photo' => $e->getMessage()]);
        }

        $db = \Config\Database::connect();
        $itemCode = '';

        try {
            if (! $db->transBegin()) {
                throw new \RuntimeException('Transaksi database tidak dapat dimulai.');
            }
            $references = model(ItemReferenceModel::class);
            $values = $references->withSelection((string) $this->request->getPost('category'), (string) $this->request->getPost('location'),
                static fn ($selection) => $references->selectionValues($selection));
            $itemCode = (new DocumentNumbers($db))->next('item');
            $this->ensureSerialConfirmed();
            $itemModel = model('App\Models\ItemModel');
            $itemId = $itemModel->insert([
                'item_code'        => $itemCode,
                'category'         => $values['category'],
                'item_name'        => $this->request->getPost('item_name'),
                'brand'            => $this->request->getPost('brand') ?: null,
                'model'            => $this->request->getPost('model') ?: null,
                'serial_number'    => $serialStatus === 'known' ? $this->request->getPost('serial_number') : null,
                'serial_status'    => $serialStatus,
                'acquisition_date' => $this->request->getPost('acquisition_date') ?: null,
                'location'         => $values['location'],
                'specifications'   => $this->request->getPost('specifications') ?: null,
                'status'           => 'available',
                'condition'        => $this->request->getPost('condition'),
                'physical_photo'   => $physicalPhoto,
                'serial_photo'     => $serialPhoto,
                'qr_filename'      => qr_filename($itemCode),
                'is_active'        => 1,
            ]);
            if (! $itemId) {
                throw new \RuntimeException(implode(' ', $itemModel->errors()) ?: 'Data unit gagal ditulis ke database.');
            }

            $accessoryDetail = (new \App\Libraries\AccessoryChecklist())->replaceTemplate((int) $itemId, $this->request->getPost('accessories'));
            $created = $itemModel->synchronizeState($itemModel->find($itemId));
            log_status_history((int) $itemId, 'available', $created->status, 'Data unit dibuat.');
            log_activity('barang_dibuat', 'Barang', $itemCode, 'Nama barang: ' . $created->item_name
                . "\nNomor seri: " . ItemModel::serialLabel($created) . "\nLokasi: " . $created->location
                . "\nKondisi: " . condition_label($created->condition)
                . ($this->request->getPost('serial_duplicate_confirmed') ? "\nPenggunaan nomor seri sama dikonfirmasi petugas." : '')
                . ($accessoryDetail !== '' ? "\n" . $accessoryDetail : ''));
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new \RuntimeException('Transaksi database gagal.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            delete_photo($physicalPhoto);
            delete_photo($serialPhoto);
            log_message('error', 'Gagal menyimpan unit {code}: {message}', ['code' => $itemCode, 'message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('errors', ['store' => $e instanceof \DomainException ? $e->getMessage() : 'Unit gagal disimpan. Silakan coba lagi.']);
        }

        try {
            generate_qr_file($itemCode);
        } catch (\Throwable $e) {
            log_message('error', 'File QR unit {code} gagal dibuat setelah simpan: {message}', ['code' => $itemCode, 'message' => $e->getMessage()]);
        }
        return redirect()->to('/petugas/items/' . $itemId)->with('success', 'Unit berhasil dibuat. QR tersedia di detail barang.');
    }

    public function show(int $id)
    {
        $item = model('App\Models\ItemModel')->find($id);
        if (! $item) {
            return redirect()->to('/petugas/items')->with('error', 'Unit tidak ditemukan.');
        }
        $activeLoan = model('App\Models\LoanModel')->activeLoanByItem($id);
        $activeBorrower = $activeLoan ? model('App\Models\BorrowerModel')->find($activeLoan->borrower_id) : null;
        $histories = model('App\Models\StatusHistoryModel')
            ->select("item_status_history.*, CASE WHEN item_status_history.damage_report_id IS NOT NULL AND item_status_history.status_to = 'damaged' AND item_status_history.status_from NOT IN ('damaged', 'in_handling') THEN COALESCE(damage_reports.damage_photo, loans.return_photo) WHEN item_status_history.status_from = 'borrowed' AND loans.returned_at IS NOT NULL THEN loans.return_photo ELSE NULL END AS event_photo", false)
            ->join('loans', 'loans.id = item_status_history.loan_id', 'left')
            ->join('damage_reports', 'damage_reports.id = item_status_history.damage_report_id', 'left')
            ->where('item_status_history.item_id', $id)
            ->orderBy('item_status_history.created_at', 'DESC')
            ->limit(20)
            ->findAll();
        $lastBorrower = model('App\Models\ItemModel')->lastBorrower($id);
        $damageReports = model('App\Models\DamageReportModel')->where('item_id', $id)->orderBy('discovered_at', 'DESC')->findAll();
        $listParams = $this->request->getGet('daftar');
        $listBackUrl = null;
        if (is_array($listParams)) {
            $listParams = array_intersect_key($listParams, array_flip(['q', 'status', 'category', 'per_page', 'page']));
            $query = http_build_query(array_filter($listParams, 'is_string'));
            $listBackUrl = base_url('/petugas/items') . ($query !== '' ? '?' . $query : '') . '#inventory-unit-' . $id;
        }

        return view('petugas/item_detail', [
            'title'         => 'Detail Unit ' . $item->item_code,
            'item'          => $item,
            'active_loan'   => $activeLoan,
            'active_borrower' => $activeBorrower,
            'histories'     => $histories,
            'last_borrower' => $lastBorrower,
            'damage_reports'=> $damageReports,
            'accessories' => (new \App\Libraries\AccessoryChecklist())->template($id),
            'accessory_pending' => model(\App\Models\LoanAccessoryModel::class)->pending()->where('l.item_id', $id)->findAll(),
            'listBackUrl'   => $listBackUrl,
        ]);
    }

    public function edit(int $id)
    {
        $item = model('App\Models\ItemModel')->find($id);
        if (! $item) {
            return redirect()->to('/petugas/items')->with('error', 'Unit tidak ditemukan.');
        }
        return view('petugas/item_form', [
            'title'      => 'Ubah Barang ' . $item->item_code,
            'mode'       => 'edit',
            'accessories' => (new \App\Libraries\AccessoryChecklist())->template($id),
            'item'       => $item,
            'categories' => model(ItemReferenceModel::class)->names('category', $item->category),
            'locations' => model(ItemReferenceModel::class)->names('location', $item->location),
            'inactive_category' => ! in_array($item->category, model(ItemReferenceModel::class)->names('category'), true),
            'inactive_location' => ! in_array($item->location, model(ItemReferenceModel::class)->names('location'), true),
        ]);
    }

    public function update(int $id)
    {
        $item = model('App\Models\ItemModel')->find($id);
        if (! $item) {
            return redirect()->to('/petugas/items')->with('error', 'Unit tidak ditemukan.');
        }

        $serialStatus = $this->request->getPost('serial_status') ?? 'known';
        $rules = [
            'category' => ['label' => 'Kategori', 'rules' => 'required|max_length[60]'],
            'item_name' => ['label' => 'Nama barang', 'rules' => 'required|regex_match[/\S/]|max_length[150]'],
            'brand' => ['label' => 'Merek', 'rules' => 'required|regex_match[/\S/]|max_length[100]'],
            'model' => ['label' => 'Model', 'rules' => 'required|regex_match[/\S/]|max_length[100]'],
            'acquisition_date' => ['label' => 'Tanggal pengadaan', 'rules' => 'required|valid_date[Y-m-d]'],
            'specifications' => ['label' => 'Spesifikasi', 'rules' => 'required|regex_match[/\S/]'],
            'location' => ['label' => 'Lokasi', 'rules' => 'required|max_length[100]'],
            'condition' => ['label' => 'Kondisi', 'rules' => 'required|in_list[good,minor_damage,major_damage]'],
        ] + $this->serialRules($serialStatus, $item);

        if (empty($item->physical_photo)) {
            $rules['physical_photo'] = ['label' => 'Foto fisik', 'rules' => 'uploaded[physical_photo]', 'errors' => ['uploaded' => 'Foto fisik wajib diunggah.']];
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $newPhysicalPhoto = $newSerialPhoto = null;
        try {
            $newPhysicalPhoto = upload_photo($this->request->getFile('physical_photo'), 'uploads', $item->item_code . '-fisik');
            if ($serialStatus === 'known') {
                $newSerialPhoto = upload_photo($this->request->getFile('serial_photo'), 'uploads', $item->item_code . '-serial');
            }
        } catch (\RuntimeException $e) {
            delete_photo($newPhysicalPhoto);
            delete_photo($newSerialPhoto);
            return redirect()->back()->withInput()->with('errors', ['photo' => $e->getMessage()]);
        }
        $data = [
            'category'         => $this->request->getPost('category'),
            'item_name'        => $this->request->getPost('item_name'),
            'brand'            => $this->request->getPost('brand') ?: null,
            'model'            => $this->request->getPost('model') ?: null,
            'serial_number'    => $serialStatus === 'known' ? $this->request->getPost('serial_number') : null,
            'serial_status'    => $serialStatus,
            'acquisition_date' => $this->request->getPost('acquisition_date') ?: null,
            'location'         => $this->request->getPost('location'),
            'specifications'   => $this->request->getPost('specifications') ?: null,
            'condition'        => $this->request->getPost('condition'),
        ];

        $labels = [
            'category'         => 'Kategori',
            'item_name'        => 'Nama unit',
            'brand'            => 'Merek',
            'model'            => 'Model',
            'serial_number'    => 'Nomor seri',
            'serial_status'    => 'Keterangan nomor seri',
            'acquisition_date' => 'Tanggal perolehan',
            'location'         => 'Lokasi',
            'specifications'   => 'Spesifikasi',
            'condition'        => 'Kondisi',
            'status'           => 'Status',
        ];

        try {
            $items = model('App\Models\ItemModel');
            $references = model(ItemReferenceModel::class);
            $previous = $references->withSelection((string) $data['category'], (string) $data['location'], function ($selection) use ($references, $items, $id, $data, $labels, $newPhysicalPhoto, $newSerialPhoto) {
                return $items->withLockedItem($id, function ($current) use ($selection, $references, $items, $id, $data, $labels, $newPhysicalPhoto, $newSerialPhoto) {
                    $this->ensureSerialConfirmed($id);
                    $changes = array_replace($data, $references->selectionValues($selection, $current));
                    $changes['physical_photo'] = $newPhysicalPhoto ?? $current->physical_photo;
                    $changes['serial_photo'] = null;
                    if ($data['serial_status'] === 'known') {
                        if (! $newSerialPhoto && (empty($current->serial_photo) || $data['serial_number'] !== $current->serial_number)) {
                            throw new \DomainException('Unggah foto yang sesuai dengan nomor seri.');
                        }
                        $changes['serial_photo'] = $newSerialPhoto ?? $current->serial_photo;
                    }
                    $accessoryDetail = '';
                    if ($this->request->getPost('accessory_editor') === '1') {
                        $accessoryDetail = (new \App\Libraries\AccessoryChecklist())->replaceTemplate($id, $this->request->getPost('accessories'));
                    }
                    $updated = $items->synchronizeState($current, $changes);
                    $applied = array_replace($changes, ['status' => $updated->status, 'condition' => $updated->condition]);
                    $detail = activity_changes($current, array_diff_key($applied, ['physical_photo' => 1, 'serial_photo' => 1]), $labels, [
                        'condition' => static fn ($v) => condition_label((string) $v),
                        'serial_status' => static fn ($v) => ItemModel::SERIAL_STATUS_LABELS[$v] ?? '—',
                        'status' => static fn ($v) => item_status_label((string) $v),
                    ]);
                    if ($current->status !== $updated->status || $current->condition !== $updated->condition) {
                        log_status_history($id, $current->status, $updated->status, 'Kondisi unit diperbarui; status mengikuti transaksi dan laporan kerusakan terkini.');
                    }
                    if ($accessoryDetail !== '') {
                        $detail = $detail === 'Tidak ada perubahan' ? $accessoryDetail : $detail . "\n" . $accessoryDetail;
                    }
                    $photoChanges = [];
                    if ($newPhysicalPhoto) $photoChanges[] = 'Foto fisik diganti.';
                    if ($newSerialPhoto) $photoChanges[] = 'Foto nomor seri diganti.';
                    elseif ($current->serial_photo && $changes['serial_photo'] === null) $photoChanges[] = 'Foto nomor seri dilepas.';
                    if ($this->request->getPost('serial_duplicate_confirmed')) $photoChanges[] = 'Penggunaan nomor seri sama dikonfirmasi petugas.';
                    if ($photoChanges) {
                        $detail = ($detail === 'Tidak ada perubahan' ? '' : $detail . "\n") . implode("\n", $photoChanges);
                    }
                    log_activity('barang_diperbarui', 'Barang', $current->item_code, $detail);
                    return $current;
                });
            });
        } catch (\Throwable $e) {
            delete_photo($newPhysicalPhoto);
            delete_photo($newSerialPhoto);
            log_message('error', 'Gagal memperbarui unit {id}: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e instanceof \DomainException ? $e->getMessage() : 'Data unit gagal disimpan. Silakan coba lagi.');
        }
        $serialPhotoChanged = $newSerialPhoto !== null || $serialStatus !== 'known';
        if ($newPhysicalPhoto && ($serialPhotoChanged || $previous->physical_photo !== $previous->serial_photo)) {
            delete_photo($previous->physical_photo);
        }
        if ($serialPhotoChanged && $previous->serial_photo !== $previous->physical_photo) {
            delete_photo($previous->serial_photo);
        }

        return redirect()->to('/petugas/items/' . $id)->with('success', 'Data unit berhasil diperbarui.');
    }

    private function serialRules(mixed $serialStatus, ?object $item = null): array
    {
        $rules = [
            'serial_status' => [
                'label' => 'Keterangan nomor seri',
                'rules' => 'if_exist|in_list[' . implode(',', array_keys(ItemModel::SERIAL_STATUS_LABELS)) . ']',
                'errors' => ['in_list' => 'Pilih keterangan nomor seri yang tersedia.'],
            ],
        ];
        if ($serialStatus !== 'known') {
            return $rules;
        }
        $serial = $this->request->getPost('serial_number');
        $confirmed = is_string($serial) && $serial !== '' && $this->request->getPost('serial_duplicate_confirmed') === $serial;
        $unique = $confirmed ? '' : ($item ? '|is_unique[items.serial_number,id,' . (int) $item->id . ']' : '|is_unique[items.serial_number]');
        $rules['serial_number'] = [
            'label' => 'Nomor seri',
            'rules' => 'required|regex_match[/\S/]|max_length[100]' . $unique,
            'errors' => ['is_unique' => 'Nomor seri sudah digunakan. Konfirmasikan penggunaan nomor seri yang sama sebelum menyimpan.'],
        ];
        if (empty($item->serial_photo) || $this->request->getPost('serial_number') !== $item->serial_number) {
            $rules['serial_photo'] = [
                'label' => 'Foto nomor seri',
                'rules' => 'uploaded[serial_photo]',
                'errors' => ['uploaded' => 'Unggah foto yang sesuai dengan nomor seri.'],
            ];
        }
        return $rules;
    }

    public function checkSerial()
    {
        $serial = $this->request->getGet('serial');
        $id = filter_var($this->request->getGet('exclude'), FILTER_VALIDATE_INT);
        if (! is_string($serial) || trim($serial) === '' || mb_strlen($serial) > 100) {
            return $this->response->setStatusCode(422)->setJSON(['message' => 'Nomor seri tidak valid.']);
        }
        $items = model(ItemModel::class)->select('item_code, item_name')->where('serial_number', $serial);
        if ($id > 0) {
            $items->where('id !=', $id);
        }
        return $this->response->setJSON(['matches' => $items->orderBy('id')->findAll(3)]);
    }

    private function ensureSerialConfirmed(?int $id = null): void
    {
        if (($this->request->getPost('serial_status') ?? 'known') !== 'known') {
            return;
        }
        $serial = $this->request->getPost('serial_number');
        $items = model(ItemModel::class)->where('serial_number', $serial);
        if ($id !== null) {
            $items->where('id !=', $id);
        }
        if ($items->first() && $this->request->getPost('serial_duplicate_confirmed') !== $serial) {
            throw new \DomainException('Nomor seri sudah digunakan. Periksa kembali dan konfirmasikan sebelum menyimpan.');
        }
    }

    public function regenerateQr(int $id)
    {
        $item = model('App\Models\ItemModel')->find($id);
        if (! $item) {
            return $this->response->setJSON(['ok' => false, 'message' => 'Unit tidak ditemukan.']);
        }
        $file = generate_qr_file($item->item_code);
        model('App\Models\ItemModel')->update($id, ['qr_filename' => $file]);
        log_activity('qr_dibuat_ulang', 'Barang', $item->item_code, 'QR Code dibuat ulang');
        return $this->response->setJSON(['ok' => true, 'message' => 'QR Code berhasil dibuat ulang.', 'qr' => base_url('qr/' . $file)]);
    }

    public function qrImage(int $id)
    {
        $item = model('App\Models\ItemModel')->find($id);
        if (! $item) {
            return redirect()->to('/petugas/items');
        }
        $file = stored_qr_filename($item->item_code, $item->qr_filename);
        $png = $file ? @file_get_contents(FCPATH . 'qr/' . $file) : false;
        if ($png === false) {
            $png = qr_builder($item->item_code)->build()->getString();
        }
        return $this->response->setContentType('image/png')
            ->setHeader('Content-Disposition', 'inline; filename="' . ($file ?? qr_filename($item->item_code)) . '"')
            ->setBody($png);
    }

    public function qrLabel(string $code)
    {
        $item = model('App\Models\ItemModel')->where('item_code', $code)->first();
        if (! $item) {
            return redirect()->to('/petugas/items')->with('error', 'Unit tidak ditemukan.');
        }
        return view('petugas/qr_label', ['item' => $item]);
    }

    public function qrLabels()
    {
        $ids = [];
        foreach ((array) $this->request->getGet('item_ids') as $id) {
            if (is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }
        $ids = array_values(array_unique($ids));

        if ($ids === [] || count($ids) > 100) {
            return redirect()->to('/petugas/items')->with('error', 'Pilih 1 sampai 100 unit untuk dicetak.');
        }

        $items = model('App\Models\ItemModel')
            ->whereIn('id', $ids)
            ->where('is_active', 1)
            ->orderBy('item_code', 'ASC')
            ->findAll();

        if ($items === []) {
            return redirect()->to('/petugas/items')->with('error', 'Unit terpilih tidak ditemukan.');
        }

        log_activity('qr_dicetak', 'Barang', count($items) . ' unit', 'Cetak label QR: ' . implode(', ', array_map(static fn ($it) => $it->item_code, $items)));

        return view('petugas/qr_labels', ['items' => $items]);
    }

    public function qrPdf(int $id)
    {
        $item = model('App\Models\ItemModel')->find($id);
        if (! $item) {
            return redirect()->to('/petugas/items');
        }
        log_activity('qr_dicetak', 'Barang', $item->item_code, 'Cetak label QR (PDF)');
        $html = view('petugas/qr_label', ['item' => $item, 'pdf' => true]);
        return $this->renderPdf($html, 'QR-' . $item->item_code . '.pdf', 'landscape');
    }

    public function history(int $id)
    {
        $item = model('App\Models\ItemModel')->find($id);
        if (! $item) {
            return redirect()->to('/petugas/items')->with('error', 'Unit tidak ditemukan.');
        }
        $histories = model('App\Models\StatusHistoryModel')->where('item_id', $id)->orderBy('created_at', 'DESC')->findAll();
        $loans = model('App\Models\LoanModel')->withDetails('all', $item->item_code)->limit(200)->find();

        return view('petugas/item_history', [
            'title'     => 'Riwayat Unit ' . $item->item_code,
            'item'      => $item,
            'histories' => $histories,
            'loans'     => $loans,
        ]);
    }

    protected function renderPdf(string $html, string $filename, string $orientation = 'portrait')
    {
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => false]);
        exit;
    }
}
