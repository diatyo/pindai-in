<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $wasLoggedIn = (bool) session()->get('is_logged_in');
        try {
            $user = service('authSession')->currentUser();
        } catch (\Throwable $e) {
            log_message('error', 'Pemeriksaan sesi gagal: {message}', ['message' => $e->getMessage()]);
            return service('response')->setStatusCode(503)->setHeader('Cache-Control', 'no-store')
                ->setBody('Layanan sementara tidak tersedia. Silakan coba lagi.');
        }
        if (! $user) {
            return redirect()->to('/login')->with('error', $wasLoggedIn
                ? 'Sesi berakhir atau akses akun berubah. Silakan masuk kembali.' : 'Silakan masuk terlebih dahulu.');
        }
        $uri = $request->getUri();
        $path = trim(method_exists($uri, 'getRoutePath') ? $uri->getRoutePath() : $uri->getPath(), '/');
        if (session()->get('must_change_password') && ! in_array($path, ['account/password', 'logout'], true)) {
            return redirect()->to('/account/password')->with('info', 'Ganti kata sandi sementara sebelum melanjutkan.');
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'no-store, private')->setHeader('Pragma', 'no-cache');
    }
}
