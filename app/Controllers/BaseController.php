<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $helpers = ['url', 'form', 'app', 'qr', 'activity'];

    /** Keep administrative changes and their audit records in one transaction. */
    protected function auditedChange(callable $operation)
    {
        $db = \Config\Database::connect();
        try {
            if (! $db->transBegin()) throw new \RuntimeException('Transaksi tidak tersedia.');
            $result = $operation();
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new \RuntimeException('Perubahan dan catatan audit gagal disimpan.');
            }
            return $result;
        } catch (\Throwable $e) {
            $db->transRollback();
            session()->remove(['success', 'generated_credentials', 'temporary_password']);
            log_message('error', 'Perubahan administratif dibatalkan: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Perubahan gagal disimpan. Silakan coba lagi.');
        }
    }

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        date_default_timezone_set((string) env('app.appTimezone', 'Asia/Jakarta'));
    }
}
