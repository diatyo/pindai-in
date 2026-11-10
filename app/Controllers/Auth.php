<?php

namespace App\Controllers;

class Auth extends BaseController
{
    public function index()
    {
        return $this->login();
    }

    public function login()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        if (session()->get('is_logged_in')) {
            try {
                $current = service('authSession')->currentUser();
            } catch (\Throwable $e) {
                return $this->unavailable($e);
            }
            if ($current) {
                return redirect()->to(session()->get('must_change_password') ? '/account/password' : role_home($current->role));
            }
            session()->setFlashdata('login_error', 'Sesi berakhir atau akses akun berubah. Silakan masuk kembali.');
        }
        if (strtolower((string) $this->request->getMethod()) === 'post') {
            session()->remove('_ci_old_input');
            $usernameInput = $this->request->getPost('username');
            $passwordInput = $this->request->getPost('password');
            $username = is_string($usernameInput) ? trim($usernameInput) : '';
            $password = is_string($passwordInput) ? $passwordInput : '';

            if ($username === '' || $password === '') {
                return $this->failedLogin($username, 'Nama pengguna dan kata sandi wajib diisi.');
            }
            if (mb_strlen($username) > 60 || strlen($password) > 4096) {
                return $this->failedLogin('', 'Nama pengguna atau kata sandi tidak sesuai.');
            }
            try {
                $outcome = service('loginLimiter')->attempt($username, $this->request->getIPAddress(), function () use ($username, $password) {
                    $user = model('App\Models\UserModel')->where('username', $username)->first();
                    // Fixed valid dummy hash keeps unknown usernames on the password-verification path.
                    $hash = $user->password_hash ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
                    if (! password_verify($password, $hash) || ! $user) {
                        return ['ok' => false, 'message' => 'Nama pengguna atau kata sandi tidak sesuai.', 'evaluated' => true];
                    }
                    if (! $user->is_active) {
                        return ['ok' => false, 'message' => 'Akun dinonaktifkan. Silakan hubungi Admin.', 'evaluated' => true];
                    }
                    $borrower = null;
                    if ($user->role === 'peminjam') {
                        $borrower = $user->borrower_id ? model('App\Models\BorrowerModel')->find((int) $user->borrower_id) : null;
                        if (! $borrower || ! $borrower->is_active) {
                            return ['ok' => false, 'message' => 'Akun peminjam belum terhubung ke data guru/siswa aktif. Hubungi Admin.', 'evaluated' => true];
                        }
                    }
                    return ['ok' => true, 'user' => $user, 'borrower' => $borrower, 'evaluated' => true];
                });
            } catch (\Throwable $e) {
                return $this->unavailable($e);
            }
            if ($outcome['blocked']) {
                log_activity('login_dibatasi', 'Sesi', $username, 'Percobaan masuk ditolak karena jeda login sementara.');
                $retry = (int) $outcome['retry_after'];
                return $this->response->setStatusCode(429)->setHeader('Retry-After', (string) $retry)
                    ->setBody(view('auth/login', ['title' => 'Masuk', 'usernameValue' => $username,
                        'loginError' => 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . $retry . ' detik.']));
            }
            if (! $outcome['ok']) {
                log_activity('login_gagal', 'Sesi', $username, 'Login tidak berhasil.');
                return $this->failedLogin($username, $outcome['message']);
            }
            $user = $outcome['user'];
            $db = \Config\Database::connect();
            try {
                if (! $db->transBegin()) throw new \RuntimeException('Transaksi login tidak tersedia.');
                if (! model('App\Models\UserModel')->update($user->id, ['last_login_at' => date('Y-m-d H:i:s')])) {
                    throw new \RuntimeException('Waktu login gagal disimpan.');
                }
                service('authSession')->establish($user, $outcome['borrower']);
                log_activity('login', 'Sesi', $user->username, 'Login berhasil');
                if (! $db->transStatus() || ! $db->transCommit()) throw new \RuntimeException('Audit login gagal disimpan.');
            } catch (\Throwable $e) {
                $db->transRollback();
                service('authSession')->revoke();
                return $this->unavailable($e);
            }

            return redirect()->to($user->must_change_password ? '/account/password' : role_home($user->role));
        }

        return view('auth/login', ['title' => 'Masuk']);
    }

    private function failedLogin(string $username, string $message)
    {
        session()->setFlashdata('login_username', $username);
        return redirect()->to('/login')->with('login_error', $message);
    }

    private function unavailable(\Throwable $e)
    {
        log_message('error', 'Layanan login gagal: {message}', ['message' => $e->getMessage()]);
        return $this->response->setStatusCode(503)->setBody(view('auth/login', ['title' => 'Masuk',
            'loginError' => 'Layanan masuk sementara tidak tersedia. Silakan coba lagi.']));
    }

    public function logout()
    {
        if (session()->get('is_logged_in')) {
            try {
                log_activity('logout', 'Sesi', (string) session()->get('username'), 'Logout');
            } catch (\Throwable $e) {
                log_message('error', 'Audit logout gagal disimpan.');
            }
        }
        session()->destroy();
        return redirect()->to('/login')->with('success', 'Anda telah keluar dari sistem.');
    }

    public function forgot()
    {
        return view('auth/forgot', ['title' => 'Lupa Kata Sandi']);
    }
}
