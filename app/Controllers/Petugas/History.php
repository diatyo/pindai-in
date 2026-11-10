<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;

class History extends BaseController
{
    public function index()
    {
        $model = model('App\Models\StatusHistoryModel');
        $histories = $model->select('item_status_history.*, items.item_code, items.item_name')
            ->join('items', 'items.id = item_status_history.item_id')
            ->orderBy('item_status_history.created_at', 'DESC')
            ->paginate(per_page(10));

        return view('petugas/item_history_all', [
            'title'     => 'Riwayat Unit',
            'histories' => $histories,
            'pager'     => $model->pager,
        ]);
    }
}