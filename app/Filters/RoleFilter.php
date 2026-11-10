<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $role = session()->get('role');
        if (! $role) {
            return redirect()->to('/login')->with('error', 'Sesi berakhir. Silakan masuk kembali.');
        }
        if ($arguments === null || ! in_array($role, $arguments, true)) {
            helper('activity');
            log_activity('akses_ditolak', 'Sesi', (string) session()->get('username'),
                $request->getMethod() . ' /' . $request->getUri()->getPath());
            return redirect()->to($this->home($role))->with('error', 'Anda tidak memiliki hak akses ke halaman tersebut.');
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function home(string $role): string
    {
        return match ($role) {
            'admin' => '/admin',
            'petugas'       => '/petugas',
            'kepala_sekolah'=> '/kepala',
            'peminjam'      => '/peminjam',
            default         => '/login',
        };
    }
}
