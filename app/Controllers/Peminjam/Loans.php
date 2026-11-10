<?php

namespace App\Controllers\Peminjam;

use App\Controllers\BaseController;

class Loans extends BaseController
{
    public function active()
    {
        return $this->listing('open', 'Sedang Dipinjam', 'Barang yang sedang berada di tangan Anda.');
    }

    public function history()
    {
        return $this->listing('all', 'Riwayat Peminjaman', 'Seluruh transaksi peminjaman atas nama Anda.');
    }

    private function listing(string $scope, string $title, string $subtitle)
    {
        $borrowerId = (int) session()->get('borrower_id');
        $model = model('App\Models\LoanModel');
        $q = (string) $this->request->getGet('q');

        $model->withDetails('all', $q ?: null)->where('loans.borrower_id', $borrowerId);
        if ($scope === 'open') {
            $model->where('loans.returned_at IS NULL');
        }
        $loans = $model->paginate(per_page(10));

        return view('peminjam/loans', [
            'title'    => $title,
            'subtitle' => $subtitle,
            'scope'    => $scope,
            'loans'    => $loans,
            'pager'    => $model->pager,
            'q'        => $q,
        ]);
    }

    public function show(int $id)
    {
        $borrowerId = (int) session()->get('borrower_id');
        $loan = model('App\Models\LoanModel')
            ->select('loans.*, items.item_code, items.item_name, items.brand, items.model, items.physical_photo, borrowers.name AS borrower_name, borrowers.type AS borrower_type, borrowers.identity_number, borrowers.class_name, users.full_name AS processed_by_name')
            ->join('items', 'items.id = loans.item_id')
            ->join('borrowers', 'borrowers.id = loans.borrower_id')
            ->join('users', 'users.id = loans.processed_by', 'left')
            ->where('loans.id', $id)
            ->where('loans.borrower_id', $borrowerId)
            ->first();

        if (! $loan) {
            return redirect()->to('/peminjam/history')->with('error', 'Transaksi tidak ditemukan.');
        }

        return view('peminjam/loan_detail', ['title' => 'Detail Peminjaman', 'loan' => $loan]);
    }
}
