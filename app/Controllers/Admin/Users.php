<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Users extends BaseController
{
    public function index()
    {
        $tab = $this->tab();
        $q = trim((string) $this->request->getGet('q'));
        $status = (string) $this->request->getGet('status');
        $model = model('App\Models\UserModel');
        if ($q !== '') {
            $model->groupStart()->like('users.full_name', $q)->orLike('users.username', $q)->groupEnd();
        }
        if ($status === 'active') {
            $model->where('users.is_active', 1);
        } elseif ($status === 'inactive') {
            $model->where('users.is_active', 0);
        }
        if ($tab === 'peminjam') {
            $model->where('users.role', 'peminjam');
        } else {
            $model->where('users.role !=', 'peminjam');
        }

        $users = $model->select('users.*, borrowers.name AS borrower_name, borrowers.type AS borrower_type, borrowers.identity_number AS borrower_identity')
            ->join('borrowers', 'borrowers.id = users.borrower_id', 'left')
            ->orderBy('users.full_name', 'ASC')->paginate(per_page(10));

        return view('admin/users', [
            'title' => 'Akun Pengguna',
            'users' => $users,
            'pager' => $model->pager,
            'q' => $q,
            'tab' => $tab,
            'status' => $status,
            'borrowers' => $tab === 'peminjam' ? model('App\Models\BorrowerModel')->available() : [],
        ]);
    }

    /** Form manual hanya untuk akun operasional. */
    public function store()
    {
        return $this->auditedChange(function () {
            $rules = [
                'full_name' => ['label' => 'Nama lengkap', 'rules' => 'required|regex_match[/\S/]|max_length[120]'],
                'position' => ['label' => 'Jabatan', 'rules' => 'required|regex_match[/\S/]|max_length[120]'],
                'username' => [
                    'label'  => 'Nama pengguna',
                    'rules'  => 'required|regex_match[/^[a-zA-Z0-9._-]+$/]|min_length[3]|max_length[60]|is_unique[users.username]',
                    'errors' => ['regex_match' => 'Nama pengguna hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung tanpa spasi.'],
                ],
                'role' => ['label' => 'Peran', 'rules' => 'required|in_list[admin,petugas,kepala_sekolah]'],
                'password' => ['label' => 'Kata sandi', 'rules' => 'required|regex_match[/\S/]|min_length[6]'],
            ];
            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            $role = (string) $this->request->getPost('role');
            if (! model('App\Models\UserModel')->insert([
                'full_name' => trim((string) $this->request->getPost('full_name')),
                'username' => trim((string) $this->request->getPost('username')),
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'must_change_password' => 1,
                'role' => $role,
                'borrower_id' => null,
                'position' => $this->request->getPost('position') ?: null,
                'is_active' => 1,
            ])) {
                throw new \RuntimeException('Data gagal disimpan.');
            }

            log_activity('akun_dibuat', 'Akun', (string) $this->request->getPost('username'), 'Akun ' . role_label($role) . ' dibuat');
            return redirect()->to('/admin/users?tab=utama')->with('success', 'Akun baru berhasil dibuat.');
        });
    }

    /** Generator akun guru/siswa: username awal selalu NIP atau NISN. */
    public function generateBorrowers()
    {
        return $this->auditedChange(function () {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->request->getPost('borrower_ids')))));
            if (! $ids) {
                return redirect()->to('/admin/users?tab=peminjam')->with('error', 'Pilih minimal satu guru atau siswa.');
            }
            if (count($ids) > 100) {
                return redirect()->to('/admin/users?tab=peminjam')->with('error', 'Maksimal 100 akun dapat dibuat dalam satu proses.');
            }

            $db = \Config\Database::connect();
            $borrowers = $db->table('borrowers')
                ->select('borrowers.*')
                ->join('users', 'users.borrower_id = borrowers.id', 'left')
                ->whereIn('borrowers.id', $ids)
                ->where('borrowers.is_active', 1)
                ->where('users.id IS NULL', null, false)
                ->get()->getResult();

            if (count($borrowers) !== count($ids)) {
                return redirect()->to('/admin/users?tab=peminjam')->with('error', 'Sebagian data sudah memiliki akun, nonaktif, atau tidak ditemukan. Muat ulang halaman lalu pilih kembali.');
            }

            $seenUsernames = [];
            foreach ($borrowers as $borrower) {
                $username = trim((string) $borrower->identity_number);
                if ($username === '' || mb_strlen($username) > 60) {
                    return redirect()->to('/admin/users?tab=peminjam')->with('error', 'NIP/NISN ' . $borrower->name . ' tidak valid untuk dijadikan username.');
                }
                if (isset($seenUsernames[$username]) || $db->table('users')->where('username', $username)->countAllResults() > 0) {
                    return redirect()->to('/admin/users?tab=peminjam')->with('error', 'Username NIP/NISN ' . $username . ' sudah dipakai akun lain.');
                }
                $seenUsernames[$username] = true;
            }

            $credentials = [];
            foreach ($borrowers as $borrower) {
                $username = trim((string) $borrower->identity_number);
                $password = $username; // Password awal sama dengan NIP/NISN agar mudah dibagikan; wajib diganti saat login pertama.
                $type = $borrower->type === 'teacher' ? 'Guru' : 'Siswa';

                if (! model('App\Models\UserModel')->insert([
                    'full_name' => $borrower->name,
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'must_change_password' => 1,
                    'role' => 'peminjam',
                    'borrower_id' => (int) $borrower->id,
                    'position' => $type,
                    'is_active' => 1,
                ])) {

                    throw new \RuntimeException('Data gagal disimpan.');

                }
                $credentials[] = ['name' => $borrower->name, 'type' => $type, 'username' => $username, 'password' => $password];
                log_activity('akun_peminjam_dibuat', 'Akun', $username, 'Akun Peminjam ' . $type . ' dibuat otomatis');
            }

            return redirect()->to('/admin/users?tab=peminjam')->with('generated_credentials', $credentials);
        });
    }

    public function generateBorrowersBatch()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $ids = $this->request->getPost('borrower_ids');
        if (! is_array($ids) || ! $ids || count($ids) > \App\Libraries\BorrowerAccountGenerator::BATCH_SIZE
            || array_filter($ids, static fn ($id) => ! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1)) {
            return $this->response->setStatusCode(422)->setJSON(['message' => 'Daftar peminjam tidak valid. Maksimal 20 data per permintaan.', 'csrf' => csrf_hash()]);
        }
        try {
            $result = (new \App\Libraries\BorrowerAccountGenerator(\Config\Database::connect()))->generate($ids);
            return $this->response->setJSON($result + ['csrf' => csrf_hash()]);
        } catch (\Throwable $e) {
            log_message('error', 'Batch pembuatan akun dibatalkan: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['message' => 'Bagian ini belum tersimpan. Coba lanjutkan; akun yang sudah ada akan dilewati.', 'csrf' => csrf_hash()]);
        }
    }

    public function update(int $id)
    {
        return $this->auditedChange(function () use ($id) {
            $user = model('App\Models\UserModel')->find($id);
            if (! $user) {
                return redirect()->to('/admin/users')->with('error', 'Akun tidak ditemukan.');
            }
            if ($user->role === 'peminjam') {
                return redirect()->to('/admin/users?tab=peminjam')->with('error', 'Username akun peminjam tidak dapat diubah karena mengikuti NIP/NISN dari Data Guru/Siswa.');
            }

            $rules = [
                'full_name' => ['label' => 'Nama lengkap', 'rules' => 'required|max_length[120]'],
                'username' => [
                    'label'  => 'Nama pengguna',
                    'rules'  => 'required|regex_match[/^[a-zA-Z0-9._-]+$/]|min_length[3]|max_length[60]|is_unique[users.username,id,' . $id . ']',
                    'errors' => ['regex_match' => 'Nama pengguna hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung tanpa spasi.'],
                ],
                'role' => ['label' => 'Peran', 'rules' => 'required|in_list[admin,petugas,kepala_sekolah]'],
            ];
            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            $data = [
                'full_name' => trim((string) $this->request->getPost('full_name')),
                'username' => trim((string) $this->request->getPost('username')),
                'role' => (string) $this->request->getPost('role'),
                'borrower_id' => null,
                'position' => $this->request->getPost('position') ?: null,
            ];

            $detail = activity_changes($user, array_diff_key($data, ['borrower_id' => 1]), [
                'full_name' => 'Nama',
                'username' => 'Username',
                'role' => 'Peran',
                'position' => 'Jabatan',
            ], ['role' => static fn ($v) => $v === '' || $v === null ? '(kosong)' : role_label((string) $v)]);

            if (! model('App\Models\UserModel')->update($id, $data)) {

                throw new \RuntimeException('Data gagal disimpan.');

            }

            log_activity('akun_diperbarui', 'Akun', (string) $this->request->getPost('username'), $detail);
            return redirect()->to('/admin/users?tab=utama')->with('success', 'Data akun berhasil diperbarui.');
        });
    }

    public function resetPassword(int $id)
    {
        return $this->auditedChange(function () use ($id) {
            $user = model('App\Models\UserModel')->find($id);
            if (! $user) {
                return redirect()->to('/admin/users')->with('error', 'Akun tidak ditemukan.');
            }

            $temp = $this->temporaryPassword();
            if ($user->role === 'peminjam') {
                $borrower = $user->borrower_id ? model('App\Models\BorrowerModel')->find((int) $user->borrower_id) : null;
                $temp = trim((string) ($borrower->identity_number ?? ''));
                if ($temp === '') {
                    return redirect()->to('/admin/users?tab=peminjam')->with('error', 'NIP/NISN akun peminjam tidak ditemukan. Periksa kembali data Guru/Siswa.');
                }
            }
            if (! model('App\Models\UserModel')->update($id, ['password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'must_change_password' => 1])) {
                throw new \RuntimeException('Data gagal disimpan.');
            }
            log_activity('password_direset', 'Akun', $user->username, $user->role === 'peminjam' ? 'Kata sandi direset menjadi NIP/NISN' : 'Kata sandi sementara dibuat');

            return redirect()->to($this->tabPath($user))->with('temporary_password', ['user_name' => $user->full_name, 'password' => $temp]);
        });
    }

    public function toggleActive(int $id)
    {
        return $this->auditedChange(function () use ($id) {
            $user = model('App\Models\UserModel')->find($id);
            if (! $user) {
                return redirect()->to('/admin/users')->with('error', 'Akun tidak ditemukan.');
            }
            $new = $user->is_active ? 0 : 1;
            if (! model('App\Models\UserModel')->update($id, ['is_active' => $new])) {
                throw new \RuntimeException('Data gagal disimpan.');
            }
            if ($user->role === 'peminjam' && $user->borrower_id) {
                if (! model('App\Models\BorrowerModel')->update((int) $user->borrower_id, ['is_active' => $new])) {
                    throw new \RuntimeException('Data gagal disimpan.');
                }
            }
            log_activity($new ? 'akun_diaktifkan' : 'akun_dinonaktifkan', 'Akun', $user->username,
                'Status akun: ' . ($user->is_active ? 'Aktif' : 'Nonaktif') . ' → ' . ($new ? 'Aktif' : 'Nonaktif')
                . ($user->role === 'peminjam' && $user->borrower_id ? ' · Status data peminjam terkait ikut diperbarui.' : ''));

            return redirect()->to($this->tabPath($user))->with('success', 'Status akun ' . $user->full_name . ' diubah menjadi ' . ($new ? 'aktif' : 'nonaktif') . '.');
        });
    }

    /**
     * Hapus akun: bila memiliki riwayat transaksi dinonaktifkan (arsip),
     * bila belum pernah dipakai dihapus permanen. Selaras dengan Data Guru/Siswa.
     */
    public function delete(int $id)
    {
        return $this->auditedChange(function () use ($id) {
            $user = model('App\Models\UserModel')->find($id);
            if (! $user) {
                return redirect()->to('/admin/users')->with('error', 'Akun tidak ditemukan.');
            }
            if ((int) $id === (int) session()->get('user_id')) {
                return redirect()->to($this->tabPath($user))->with('error', 'Anda tidak dapat menghapus akun sendiri.');
            }

            $db = \Config\Database::connect();
            $used = (int) $db->table('loans')->where('processed_by', $id)->countAllResults()
                + (int) $db->table('loans')->where('returned_by', $id)->countAllResults()
                + (int) $db->table('damage_reports')->where('reported_by', $id)->countAllResults();
            if ($used === 0 && $user->role === 'peminjam' && $user->borrower_id) {
                $used += (int) $db->table('loans')->where('borrower_id', $user->borrower_id)->countAllResults();
            }

            if ($used > 0) {
                if (! model('App\Models\UserModel')->update($id, ['is_active' => 0])) {
                    throw new \RuntimeException('Data gagal disimpan.');
                }
                log_activity('akun_dinonaktifkan', 'Akun', $user->username, 'Dinonaktifkan karena memiliki riwayat transaksi');
                return redirect()->to($this->tabPath($user))->with('success', 'Akun ' . $user->full_name . ' dinonaktifkan karena memiliki riwayat transaksi.');
            }

            if (! model('App\Models\UserModel')->delete($id)) {

                throw new \RuntimeException('Data gagal disimpan.');

            }
            log_activity('akun_dihapus', 'Akun', $user->username, 'Akun dihapus permanen');
            return redirect()->to($this->tabPath($user))->with('success', 'Akun ' . $user->full_name . ' berhasil dihapus.');
        });
    }

    private function tab(): string
    {
        return $this->request->getGet('tab') === 'peminjam' ? 'peminjam' : 'utama';
    }

    private function tabPath(object $user): string
    {
        return '/admin/users?tab=' . ($user->role === 'peminjam' ? 'peminjam' : 'utama');
    }

    private function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $password = '';
        for ($index = 0; $index < 12; $index++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $password;
    }
}
