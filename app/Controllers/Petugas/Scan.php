<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;

class Scan extends BaseController
{
    public function index()
    {
        return view('petugas/scan', ['title' => 'Scan QR Code']);
    }

    public function lookup()
    {
        $code = qr_unit_code((string) $this->request->getPost('code'));

        if ($code === '' || strlen($code) > 30) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'      => false,
                'message' => 'Kode unit kosong atau tidak valid.',
            ]);
        }

        $item = model('App\Models\ItemModel')->where('item_code', $code)->where('is_active', 1)->first();

        if (! $item) {
            return $this->response->setStatusCode(404)->setJSON([
                'ok'      => false,
                'message' => 'Kode unit tidak ditemukan. Periksa kembali QR atau kode yang dimasukkan.',
            ]);
        }

        $activeLoan = model('App\Models\LoanModel')->activeLoanByItem((int) $item->id);
        $damageReport = model('App\Models\DamageReportModel')
            ->where('item_id', $item->id)
            ->whereIn('status', ['reported', 'in_handling', 'unrepairable'])
            ->orderBy('discovered_at', 'DESC')
            ->first();
        $effectiveStatus = $activeLoan ? 'borrowed' : $item->status;

        return $this->response->setJSON([
            'ok'           => true,
            'item_code'    => $item->item_code,
            'item_name'    => $item->item_name,
            'status_label' => item_status_label($effectiveStatus),
            'redirect'     => $this->redirectFor($item, $activeLoan, $damageReport),
        ]);
    }

    private function redirectFor(object $item, ?object $activeLoan, ?object $damageReport): string
    {
        if ($activeLoan) {
            return '/petugas/items/' . $item->id;
        }

        return match ($item->status) {
            'damaged'     => $damageReport ? '/petugas/damages/' . $damageReport->id : '/petugas/damages/create/' . $item->id,
            'in_handling' => $damageReport ? '/petugas/handling/' . $damageReport->id : '/petugas/damages?item=' . $item->id,
            default       => '/petugas/items/' . $item->id,
        };
    }
}
