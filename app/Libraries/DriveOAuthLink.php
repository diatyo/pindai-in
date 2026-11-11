<?php

namespace App\Libraries;

use DomainException;

final class DriveOAuthLink
{
    private const RECORD = 'drive-oauth-installation';

    public function __construct(private readonly ?\Closure $channel = null)
    {
    }

    public static function ready(): bool
    {
        return DriveOAuthBroker::supported() && (! DriveOAuthBroker::isHost() || DriveOAuthBroker::enabled());
    }

    public static function status(): array
    {
        $record = DriveOAuthVault::read(self::RECORD);
        return [
            'connected' => isset($record['id']) && empty($record['invalid']),
            'reconnect' => ! empty($record['invalid']),
            'owner' => $record['owner'] ?? '', 'email' => $record['email'] ?? '',
        ];
    }

    public function begin(string $source): array
    {
        if (! self::ready()) throw new DomainException('Layanan sambungan Google Drive belum siap.');
        $lock = BackupStorage::lock('job', LOCK_EX);
        try {
            $record = DriveOAuthVault::read(self::RECORD);
            $record['key'] ??= bin2hex(random_bytes(32));
            $pair = $this->send('begin', $record['key'], '', $source);
            $id = $pair['id'] ?? '';
            $ticket = $pair['ticket'] ?? '';
            if (! DriveOAuthBroker::valid($id) || ! DriveOAuthBroker::valid($ticket)) {
                throw new DomainException('Layanan sambungan Google Drive belum siap.');
            }
            $record['pending'] = $id;
            DriveOAuthVault::save(self::RECORD, $record);
            return ['id' => $id, 'url' => DriveOAuthBroker::ORIGIN . '/drive-oauth/start?' . http_build_query(['id' => $id, 'ticket' => $ticket])];
        } finally {
            fclose($lock);
        }
    }

    public function poll(string $id): string
    {
        $lock = BackupStorage::lock('job', LOCK_EX);
        try {
            $record = DriveOAuthVault::read(self::RECORD);
            if (($record['pending'] ?? '') !== $id) return 'superseded';
            $result = $this->send('claim', $record['key'], $id);
            $status = $result['status'];
            if ($status === 'connected') {
                $record = ['key' => $record['key'], 'id' => $id, 'owner' => $result['owner'], 'email' => $result['email']];
                DriveOAuthVault::save(self::RECORD, $record);
            }
            return $status;
        } finally {
            fclose($lock);
        }
    }

    public function access(): string
    {
        // BackupJobs already serializes uploads and link changes with the job lock.
        $record = DriveOAuthVault::read(self::RECORD);
        if (! isset($record['id']) || ! empty($record['invalid'])) {
            throw new DomainException('Hubungkan kembali akun Google Drive.');
        }
        $result = $this->send('access', $record['key'], $record['id']);
        if (($result['status'] ?? '') === 'reconnect') {
            self::markInvalid();
            throw new DomainException('Izin Google Drive berakhir atau dicabut. Hubungkan ulang akun.');
        }
        $token = $result['access'] ?? '';
        if (! is_string($token) || strlen($token) > 4096 || ! preg_match('#\A[A-Za-z0-9._~+/-]+={0,2}\z#', $token)) {
            throw new DomainException('Layanan belum dapat memberikan akses Drive. Coba lagi.');
        }
        return $token;
    }

    public static function markInvalid(): void
    {
        $record = DriveOAuthVault::read(self::RECORD);
        if (isset($record['id'])) {
            $record['invalid'] = true;
            DriveOAuthVault::save(self::RECORD, $record);
        }
    }

    public function disconnect(): void
    {
        $lock = BackupStorage::lock('job', LOCK_EX);
        try {
            $record = DriveOAuthVault::read(self::RECORD);
            // A claim may already have committed remotely even if the browser lost the response.
            foreach (array_unique(array_filter([$record['pending'] ?? '', $record['id'] ?? ''])) as $id) {
                $this->send('disconnect', $record['key'], $id);
            }
            DriveOAuthVault::forget(self::RECORD);
        } finally {
            fclose($lock);
        }
    }

    private function send(string $action, string $key, string $id, string $source = ''): array
    {
        if ($this->channel !== null) return ($this->channel)($action, $key, $id, $source);
        if (DriveOAuthBroker::isHost()) {
            if (! DriveOAuthBroker::enabled()) throw new DomainException('Layanan sambungan Google Drive belum siap.');
            return (new DriveOAuthBroker())->channel($action, $key, $id, $source);
        }
        $response = self::request('POST', DriveOAuthBroker::ORIGIN . '/drive-oauth/channel', ['Content-Type: application/json'],
            json_encode(['action' => $action, 'key' => $key, 'id' => $id], JSON_THROW_ON_ERROR));
        if ($response['status'] === 404 && $action === 'begin') {
            throw new DomainException('Layanan OAuth Google Drive di pindaiin.my.id belum aktif.');
        }
        if ($response['status'] === 410 && $action === 'claim') return ['status' => 'expired'];
        if ($response['status'] !== 200) throw new DomainException('Layanan sambungan Google Drive belum dapat merespons. Coba lagi.');
        return $response['data'];
    }

    public static function request(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        $response = '';
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 65536) return 0;
                $response .= $chunk;
                return strlen($chunk);
            },
        ]);
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        if (curl_exec($curl) === false) throw new DomainException('Koneksi ke layanan Google Drive terputus. Coba lagi.');
        return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'data' => json_decode($response, true) ?: []];
    }
}
