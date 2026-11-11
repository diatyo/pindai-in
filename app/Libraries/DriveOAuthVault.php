<?php

namespace App\Libraries;

use RuntimeException;

/** OAuth records are kept outside the document root and excluded from .pindai archives. */
final class DriveOAuthVault
{
    public static function read(string $name): array
    {
        $record = BackupStorage::state($name);
        if ($record === []) return [];

        $sealed = base64_decode($record['sealed'] ?? '', true);
        if ($sealed === false || strlen($sealed) <= 28) {
            throw new RuntimeException('Status sambungan Google Drive tidak valid.');
        }
        $plain = openssl_decrypt(substr($sealed, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA,
            substr($sealed, 0, 12), substr($sealed, 12, 16), $name);
        if ($plain === false) throw new RuntimeException('Status sambungan Google Drive tidak dapat dibuka.');

        return json_decode($plain, true, 16, JSON_THROW_ON_ERROR);
    }

    public static function save(string $name, array $record): void
    {
        $nonce = random_bytes(12);
        $ciphertext = openssl_encrypt(json_encode($record, JSON_THROW_ON_ERROR), 'aes-256-gcm',
            self::key(), OPENSSL_RAW_DATA, $nonce, $tag, $name, 16);
        if ($ciphertext === false) throw new RuntimeException('Status sambungan Google Drive gagal dienkripsi.');

        BackupStorage::save($name, ['sealed' => base64_encode($nonce . $tag . $ciphertext)]);
    }

    public static function forget(string $name): void
    {
        $path = BackupStorage::directory() . $name . '.json';
        if (is_file($path) && ! unlink($path)) throw new RuntimeException('Status Google Drive gagal dihapus.');
    }

    private static function key(): string
    {
        $key = BackupStorage::state('drive-oauth-key');
        if ($key === []) {
            $lock = BackupStorage::lock('drive-oauth-key', LOCK_EX);
            try {
                $key = BackupStorage::state('drive-oauth-key');
                if ($key === []) {
                    $key = ['key' => bin2hex(random_bytes(32))];
                    BackupStorage::save('drive-oauth-key', $key);
                }
            } finally {
                fclose($lock);
            }
        }
        if (! is_string($key['key'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $key['key'])) {
            throw new RuntimeException('Kunci penyimpanan Google Drive tidak valid.');
        }
        return hex2bin($key['key']);
    }
}
