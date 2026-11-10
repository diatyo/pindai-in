<?php

namespace App\Controllers\Kepala;

use App\Controllers\BaseController;

class Items extends BaseController
{
    public function index()
    {
        $q = (string) $this->request->getGet('q');
        $status = (string) $this->request->getGet('status');
        $category = (string) $this->request->getGet('category');

        $items = model('App\Models\ItemModel')->withStats($q ?: null, $status ?: null, $category ?: null)
            ->paginate(per_page(10));

        $categories = \Config\Database::connect()->table('items')->select('category')->distinct()->orderBy('category')->get()->getResult();

        return view('kepala/items', [
            'title'      => 'Data Barang',
            'items'      => $items,
            'pager'      => model('App\Models\ItemModel')->pager,
            'q'          => $q,
            'status'     => $status,
            'category'   => $category,
            'categories' => $categories,
        ]);
    }

    public function show(int $id)
    {
        $item = model('App\Models\ItemModel')->find($id);
        if (! $item) {
            return redirect()->to('/kepala/items')->with('error', 'Unit tidak ditemukan.');
        }
        $histories = model('App\Models\StatusHistoryModel')
            ->select("item_status_history.*, CASE WHEN item_status_history.damage_report_id IS NOT NULL AND item_status_history.status_to = 'damaged' AND item_status_history.status_from NOT IN ('damaged', 'in_handling') THEN COALESCE(damage_reports.damage_photo, loans.return_photo) WHEN item_status_history.status_from = 'borrowed' AND loans.returned_at IS NOT NULL THEN loans.return_photo ELSE NULL END AS event_photo", false)
            ->join('loans', 'loans.id = item_status_history.loan_id', 'left')
            ->join('damage_reports', 'damage_reports.id = item_status_history.damage_report_id', 'left')
            ->where('item_status_history.item_id', $id)
            ->orderBy('item_status_history.created_at', 'DESC')
            ->limit(20)
            ->findAll();
        $lastBorrower = model('App\Models\ItemModel')->lastBorrower($id);

        return view('petugas/item_detail', [
            'title'         => 'Detail Unit ' . $item->item_code,
            'item'          => $item,
            'histories'     => $histories,
            'last_borrower' => $lastBorrower,
            'readOnly'      => true,
            'accessory_pending' => model(\App\Models\LoanAccessoryModel::class)->pending()->where('l.item_id', $id)->findAll(),
        ]);
    }
}
