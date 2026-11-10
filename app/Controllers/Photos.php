<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Photos extends BaseController
{
    public function show(string $filename)
    {
        $file = stored_photo_file($filename);
        if ($file === null || ! $this->mayView('uploads/' . $filename)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $mime = @getimagesize($file)['mime'] ?? null;
        $contents = in_array($mime, ['image/webp', 'image/png', 'image/jpeg', 'image/avif', 'image/gif'], true)
            ? @file_get_contents($file)
            : false;
        if ($contents === false) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setContentType($mime)
            ->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($contents);
    }

    private function mayView(string $path): bool
    {
        $role = session()->get('role');
        $db = \Config\Database::connect();
        if ($role === 'peminjam') {
            $borrowerId = (int) session()->get('borrower_id');
            if ($borrowerId <= 0) {
                return false;
            }
            $item = $db->table('loans')
                ->select('items.physical_photo')
                ->join('items', 'items.id = loans.item_id')
                ->where('loans.borrower_id', $borrowerId)
                ->where('items.physical_photo', $path)
                ->get(1)->getRow();
            return ($item->physical_photo ?? null) === $path;
        }

        if (! in_array($role, ['petugas', 'kepala_sekolah'], true)) {
            return false;
        }
        return $db->table('items')->select('id')->where('physical_photo', $path)->orWhere('serial_photo', $path)->get(1)->getRow()
            || $db->table('loans')->select('id')->where('return_photo', $path)->get(1)->getRow()
            || $db->table('damage_reports')->select('id')->where('damage_photo', $path)->get(1)->getRow();
    }
}
