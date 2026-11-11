<?php

namespace App\Controllers\Petugas;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;

class Activity extends BaseController
{
    public function index()
    {
        $q = ActivityLogModel::normalizeKeyword($this->request->getGet('q'));
        $module = $this->request->getGet('module');
        $module = is_string($module) ? trim($module) : '';

        $userId = (int) session()->get('user_id');
        $logs = model(ActivityLogModel::class)->filtered($q, $module ?: null, null, null, 100, $userId)
            ->where('role', 'petugas')
            ->paginate(per_page(10));

        return view('petugas/activity', [
            'title'  => 'Riwayat Aktivitas',
            'logs'   => $logs,
            'pager'  => model(ActivityLogModel::class)->pager,
            'q'      => $q,
            'module' => $module,
        ]);
    }
}
