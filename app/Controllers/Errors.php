<?php

namespace App\Controllers;

class Errors extends BaseController
{
    public function show404()
    {
        return $this->response->setStatusCode(404)->setBody(
            view('errors/error_page', ['code' => 404, 'title' => 'Halaman Tidak Ditemukan', 'message' => 'Halaman yang Anda cari tidak tersedia atau telah dipindahkan.'])
        );
    }

    public function forbidden()
    {
        return $this->response->setStatusCode(403)->setBody(
            view('errors/error_page', ['code' => 403, 'title' => 'Akses Ditolak', 'message' => 'Anda tidak memiliki hak akses ke halaman ini.'])
        );
    }
}