<?php

namespace App\Controllers\Peminjam;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $borrowerId = (int) session()->get('borrower_id');
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $active = (int) $db->table('loans')->where('borrower_id', $borrowerId)->where('returned_at IS NULL')->countAllResults();
        $late = (int) $db->table('loans')->where('borrower_id', $borrowerId)->where('returned_at IS NULL')->where('due_at <', $now)->countAllResults();
        $total = (int) $db->table('loans')->where('borrower_id', $borrowerId)->countAllResults();

        $activeLoans = model('App\Models\LoanModel')->withDetails('all')->where('loans.borrower_id', $borrowerId)
            ->where('loans.returned_at IS NULL')->findAll(5);
        $recent = model('App\Models\LoanModel')->withDetails('all')->where('loans.borrower_id', $borrowerId)->findAll(5);

        return view('peminjam/dashboard', [
            'title'        => 'Beranda',
            'active'       => $active,
            'late'         => $late,
            'total'        => $total,
            'active_loans' => $activeLoans,
            'recent'       => $recent,
        ]);
    }
}
