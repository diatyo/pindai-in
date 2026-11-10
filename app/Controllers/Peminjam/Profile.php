<?php

namespace App\Controllers\Peminjam;

use App\Controllers\BaseController;

class Profile extends BaseController
{
    public function index()
    {
        $borrower = model('App\Models\BorrowerModel')->find((int) session()->get('borrower_id'));
        if (! $borrower) {
            return redirect()->to('/logout');
        }

        return view('peminjam/profile', ['title' => 'Profil Saya', 'borrower' => $borrower]);
    }
}
