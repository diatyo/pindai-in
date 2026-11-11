<?php

namespace App\Filters;

use App\Libraries\BackupArchive;
use App\Libraries\BackupStorage;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class BackupRestoreLock implements FilterInterface
{
    public static bool $exclusive = false;

    public function before(RequestInterface $request, $arguments = null)
    {
        if (is_cli() || ! BackupArchive::available()) return null;

        $route = trim($request->getUri()->getRoutePath(), '/');
        $restore = strtoupper($request->getMethod()) === 'POST' && $route === 'admin/backups/restore';
        try {
            $lock = BackupStorage::lock('data', $restore ? LOCK_EX : LOCK_SH);
            self::$exclusive = $restore;
            register_shutdown_function(static function () use ($lock): void {
                self::$exclusive = false;
                fclose($lock);
            });
        } catch (\Throwable $e) {
            return service('response')->setStatusCode(503)->setHeader('Cache-Control', 'no-store')
                ->setHeader('Retry-After', '10')->setBody('Sistem sedang digunakan atau dipulihkan. Silakan coba lagi.');
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
