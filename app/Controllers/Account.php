<?php

namespace App\Controllers;

class Account extends BaseController
{
    public function password()
    {
        if (strtolower((string) $this->request->getMethod()) === 'post') {
            session()->remove('_ci_old_input');
            $rules = [
                'current_password' => ['label' => 'Kata sandi saat ini', 'rules' => 'required'],
                'new_password' => ['label' => 'Kata sandi baru', 'rules' => 'required|min_length[8]'],
                'password_confirm' => [
                    'label' => 'Konfirmasi kata sandi',
                    'rules' => 'required|matches[new_password]',
                    'errors' => ['matches' => 'Konfirmasi kata sandi tidak sama dengan kata sandi baru.'],
                ],
            ];

            if (! $this->validate($rules)) {
                return redirect()->back()->with('errors', $this->validator->getErrors());
            }

            $model = model('App\Models\UserModel');
            $currentPassword = (string) $this->request->getPost('current_password');
            $newPassword = (string) $this->request->getPost('new_password');
            $db = \Config\Database::connect();
            try {
                if (! $db->transBegin()) {
                    throw new \RuntimeException('Transaksi perubahan sandi tidak tersedia.');
                }
                $table = $db->escapeIdentifiers($db->prefixTable('users'));
                $lock = $db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
                $query = $db->query('SELECT * FROM ' . $table . ' WHERE id = ?' . $lock, [(int) session()->get('user_id')]);
                if ($query === false) {
                    throw new \RuntimeException('Akun gagal dikunci.');
                }
                $user = $query->getRow();
                if (! $user || ! $user->is_active || (int) $user->auth_version !== (int) session()->get('auth_version')) {
                    service('authSession')->revoke();
                    throw new \DomainException('Akses akun berubah. Silakan masuk kembali.');
                }
                if (! password_verify($currentPassword, $user->password_hash)) {
                    throw new \DomainException('Kata sandi saat ini tidak sesuai.');
                }
                if (password_verify($newPassword, $user->password_hash)) {
                    throw new \DomainException('Kata sandi baru harus berbeda dari kata sandi saat ini.');
                }
                if (! $model->update($user->id, [
                    'password_hash'        => password_hash($newPassword, PASSWORD_DEFAULT),
                    'must_change_password' => 0,
                ])) {
                    throw new \RuntimeException('Kata sandi gagal disimpan.');
                }
                $updated = $model->find($user->id);
                $borrower = $updated->role === 'peminjam' && $updated->borrower_id
                    ? model('App\Models\BorrowerModel')->find((int) $updated->borrower_id) : null;
                log_activity('password_diubah', 'Akun', $user->username, 'Kata sandi akun diubah; sesi lain dicabut.');
                if (! $db->transStatus() || ! $db->transCommit()) {
                    throw new \RuntimeException('Perubahan sandi gagal disimpan.');
                }
            } catch (\DomainException $e) {
                $db->transRollback();
                if (session()->get('is_logged_in')) {
                    log_activity('perubahan_sandi_ditolak', 'Akun', (string) session()->get('username'), 'Perubahan kata sandi ditolak saat pemeriksaan akun.');
                }
                return redirect()->to(session()->get('is_logged_in') ? '/account/password' : '/login')->with('error', $e->getMessage());
            } catch (\Throwable $e) {
                $db->transRollback();
                log_message('error', 'Perubahan sandi gagal: {message}', ['message' => $e->getMessage()]);
                return redirect()->to('/account/password')->with('error', 'Kata sandi gagal disimpan. Silakan coba lagi.');
            }
            service('authSession')->establish($updated, $borrower);
            return redirect()->to(role_home($updated->role))->with('success', 'Kata sandi berhasil diubah. Sesi pada perangkat lain telah dicabut.');
        }

        return view('account/password', [
            'title'  => 'Ubah Kata Sandi',
            'forced' => (bool) session()->get('must_change_password'),
        ]);
    }
}
