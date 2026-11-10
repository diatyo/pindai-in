<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;
use App\Libraries\AccessoryChecklist;
use App\Models\ItemModel;
use App\Models\LoanAccessoryModel;
use App\Models\LoanModel;

class Accessories extends BaseController
{
    public function index()
    {
        $model = model(LoanAccessoryModel::class);
        return view('petugas/accessory_pending', ['title' => 'Tindak Lanjut Kelengkapan',
            'pending' => $model->pending()->paginate(per_page(10)), 'pager' => $model->pager]);
    }

    public function resolve(int $loanId, int $accessoryId)
    {
        $loan = model(LoanModel::class)->find($loanId);
        $target = '/petugas/loans/' . $loanId;
        if (! $loan) { return redirect()->to('/petugas/accessories')->with('error', 'Transaksi tidak ditemukan.'); }
        try {
            $items = model(ItemModel::class);
            $changed = $items->withLockedItem((int) $loan->item_id, function ($item) use ($items, $loanId, $accessoryId) {
                $changed = (new AccessoryChecklist())->resolve($loanId, $accessoryId, $this->request->getPost());
                if ($changed) {
                    $updated = $items->synchronizeState($item);
                    log_status_history((int) $item->id, $item->status, $updated->status,
                        'Tindak lanjut kelengkapan: ' . trim((string) $this->request->getPost('note')), $loanId);
                    $accessory = model(LoanAccessoryModel::class)->find($accessoryId);
                    log_activity('kelengkapan_diselesaikan', 'Peminjaman', $item->item_code, 'Transaksi #' . $loanId
                        . "\n" . $accessory->name . ' × ' . (int) $this->request->getPost('quantity')
                        . ($this->request->getPost('kind') === 'replacement' ? ' (diganti)' : ' (dikembalikan)')
                        . "\nCatatan: " . trim((string) $this->request->getPost('note')));
                }
                return $changed;
            });
            return redirect()->to($target)->with('success', $changed ? 'Tindak lanjut disimpan. Kesiapan unit dihitung kembali.' : 'Tindak lanjut ini sudah tersimpan; tidak dicatat ulang.');
        } catch (\DomainException $e) {
            return redirect()->to($target)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', 'Tindak lanjut kelengkapan gagal: {message}', ['message' => $e->getMessage()]);
            return redirect()->to($target)->with('error', 'Tindak lanjut gagal disimpan. Silakan coba lagi.');
        }
    }
}
