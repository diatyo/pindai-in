<?php

namespace App\Libraries;

use DomainException;
use RuntimeException;

final class BackupStorage
{
    public static function directory(): string
    {
        $path = WRITEPATH . 'backups';
        if (is_link($path) || (! is_dir($path) && ! @mkdir($path, 0700) && ! is_dir($path))) {
            throw new RuntimeException('Folder backup tidak dapat digunakan.');
        }
        $real = realpath($path);
        if ($real === false) throw new RuntimeException('Folder backup tidak dapat digunakan.');
        $absolute = DIRECTORY_SEPARATOR === '\\' ? strtolower($real) : $real;
        foreach (array_filter([FCPATH, $_SERVER['DOCUMENT_ROOT'] ?? '']) as $root) {
            $public = realpath($root);
            if ($public === false) continue;
            $public = DIRECTORY_SEPARATOR === '\\' ? strtolower($public) : $public;
            if ($absolute === $public || str_starts_with($absolute, rtrim($public, '/\\') . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Folder backup harus berada di luar folder publik.');
            }
        }
        return $real . DIRECTORY_SEPARATOR;
    }

    public static function lock(string $name, int $mode)
    {
        // Keep the web/CLI restore lock independent of the backup folder.
        $path = $name === 'data' ? WRITEPATH . 'logs/backup-restore.lock' : self::directory() . $name . '.lock';
        $file = fopen($path, 'c+b');
        if ($file === false) throw new RuntimeException('Kunci backup tidak dapat dibuka.');
        if (! flock($file, $mode | LOCK_NB)) {
            fclose($file);
            throw new DomainException('Proses backup atau pemulihan sedang berjalan. Coba lagi setelah selesai.');
        }
        return $file;
    }

    public static function path(string $name): string
    {
        if (! self::validName($name)) {
            throw new DomainException('Nama file backup tidak valid.');
        }
        return self::directory() . $name;
    }

    public static function validName(string $name): bool
    {
        return preg_match('/\Apindai-(?:backup|sebelum-pulih)-\d{8}-\d{6}-[a-f0-9]{12}\.(?:pindai|zip)\z/', $name) === 1;
    }

    public static function existing(string $name): string
    {
        $file = self::path($name);
        if (is_link($file) || ! is_file($file)) throw new DomainException('File backup tidak ditemukan.');
        return $file;
    }

    public static function state(string $name): array
    {
        $file = self::directory() . $name . '.json';
        if (! is_file($file)) return [];
        return json_decode(file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
    }

    public static function save(string $name, array $data): void
    {
        $file = self::directory() . $name . '.json';
        $temp = self::temporary();
        try {
            $content = json_encode($data, JSON_THROW_ON_ERROR);
            if (file_put_contents($temp, $content) !== strlen($content) || ! rename($temp, $file)) {
                throw new RuntimeException('Status backup gagal disimpan.');
            }
        } finally {
            if (is_file($temp)) unlink($temp);
        }
    }

    public static function temporary(): string
    {
        $directory = self::directory();
        $temp = tempnam($directory, 'pdb');
        if ($temp === false) throw new RuntimeException('File sementara backup tidak dapat dibuat.');
        if (dirname($temp) . DIRECTORY_SEPARATOR !== $directory) {
            unlink($temp);
            throw new RuntimeException('Folder sementara backup tidak dapat ditulis.');
        }
        chmod($temp, 0600);
        return $temp;
    }

    /** Called with the backup job lock held. */
    public static function clearTemporaryFiles(): void
    {
        foreach (glob(self::directory() . 'pdb*') ?: [] as $file) {
            if (! is_link($file) && is_file($file) && ! @unlink($file)) {
                throw new RuntimeException('File sementara backup gagal dibersihkan.');
            }
        }
    }
}
