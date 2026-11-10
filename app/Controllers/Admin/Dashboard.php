<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();

        $data = [
            'total_users'  => (int) $db->table('users')->countAllResults(),
            'active_users' => (int) $db->table('users')->where('is_active', 1)->countAllResults(),
            'inactive_users' => (int) $db->table('users')->where('is_active', 0)->countAllResults(),
            'teachers'     => (int) $db->table('borrowers')->where('type', 'teacher')->countAllResults(),
            'students'     => (int) $db->table('borrowers')->where('type', 'student')->countAllResults(),
        ];

        $data['inactive_account'] = $db->table('users')->where('is_active', 0)->get()->getFirstRow();
        $data['recent_activities'] = $db->table('activity_logs')->orderBy('created_at', 'DESC')->limit(3)->get()->getResult();
        $data['last_master_update'] = $db->table('activity_logs')->whereIn('module', ['Akun', 'Guru', 'Siswa'])->orderBy('created_at', 'DESC')->get(1)->getFirstRow()?->created_at;
        $data['users_by_role'] = $db->table('users')->select('role, COUNT(*) AS total')->groupBy('role')->get()->getResult();

        return view('admin/dashboard', $data + ['title' => 'Dasbor']);
    }
}