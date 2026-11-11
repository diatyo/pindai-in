<?php

namespace App\Libraries;

use DomainException;

final class BackupDrive
{
    private const API = 'https://www.googleapis.com/drive/v3';
    private const CHUNK = 4 * 1024 * 1024;
    private const ARCHIVE_FIELDS = 'id,name,size,createdTime,md5Checksum,mimeType,trashed,capabilities(canDownload)';
    private ?float $uploadDeadline = null;

    public function __construct(private readonly ?\Closure $transport = null, private readonly ?DriveOAuthLink $link = null)
    {
    }

    public static function connection(): array
    {
        $status = DriveOAuthLink::status();
        return $status['connected'] ? $status : [];
    }

    public function archives(): array
    {
        $query = http_build_query([
            'q' => "trashed = false and mimeType = 'application/zip' and (name contains 'pindai-backup-' or name contains 'pindai-sebelum-pulih-')",
            'spaces' => 'drive',
            'orderBy' => 'createdTime desc',
            'pageSize' => 50,
            'fields' => 'nextPageToken,files(' . self::ARCHIVE_FIELDS . ')',
        ], '', '&', PHP_QUERY_RFC3986);
        $data = $this->data($this->request('GET', self::API . '/files?' . $query, $this->authorization()));
        $files = [];
        foreach ($data['files'] ?? [] as $file) {
            if (! $this->isArchive($file)) continue;
            $files[] = [
                'id' => $file['id'],
                'name' => $file['name'],
                'bytes' => (int) $file['size'],
                'time' => strtotime($file['createdTime'] ?? '') ?: 0,
            ];
        }
        return ['files' => $files, 'hasMore' => ! empty($data['nextPageToken'])];
    }

    /** Download into the caller's private temporary file and verify the archive before use. */
    public function downloadArchive(string $id, string $destination): void
    {
        if (! preg_match('/\A[A-Za-z0-9_-]{1,200}\z/', $id)) {
            throw new DomainException('File backup Google Drive tidak valid.');
        }
        $auth = $this->authorization();
        $url = self::API . '/files/' . rawurlencode($id);
        $reply = $this->request('GET', $url . '?fields=' . rawurlencode(self::ARCHIVE_FIELDS), $auth);
        if (in_array($reply['status'], [403, 404], true)) {
            throw new DomainException('File tidak tersedia untuk akun Google Drive yang terhubung.');
        }
        $archive = $this->data($reply);
        if (! $this->isArchive($archive) || $archive['id'] !== $id) {
            throw new DomainException('File ini bukan arsip backup Pindai.in yang dapat diunduh.');
        }
        $size = (int) $archive['size'];
        $stream = fopen($destination, 'wb');
        if ($stream === false) throw new DomainException('File sementara untuk pemulihan tidak dapat dibuat.');
        try {
            $remaining = $size;
            $reply = $this->request('GET', $url . '?alt=media', $auth, write: static function (string $bytes) use ($stream, &$remaining): int {
                $length = strlen($bytes);
                if ($length > $remaining) return 0;
                $written = fwrite($stream, $bytes);
                if ($written !== $length) return 0;
                $remaining -= $written;
                return $written;
            });
        } finally {
            fclose($stream);
        }
        if ($reply['status'] !== 200 || $remaining !== 0
            || ! hash_equals($archive['md5Checksum'], (string) md5_file($destination))) {
            throw new DomainException('Isi file dari Drive belum lengkap. Coba unduh lagi sebelum memulihkan.');
        }
    }

    private function isArchive(mixed $file): bool
    {
        return is_array($file)
            && is_string($file['id'] ?? null) && preg_match('/\A[A-Za-z0-9_-]{1,200}\z/', $file['id'])
            && is_string($file['name'] ?? null) && BackupStorage::validName($file['name'])
            && ($file['mimeType'] ?? '') === 'application/zip' && empty($file['trashed'])
            && ($file['capabilities']['canDownload'] ?? false) === true
            && is_numeric($file['size'] ?? null) && (int) $file['size'] > 0
            && is_string($file['md5Checksum'] ?? null) && preg_match('/\A[a-f0-9]{32}\z/', $file['md5Checksum']);
    }

    private function authorization(): array
    {
        return ['Authorization: Bearer ' . ($this->link ?? new DriveOAuthLink())->access()];
    }

    /** Returns true once Drive confirms the entire file. Persists the resumable session before sending bytes. */
    public function upload(string $file, array &$job, callable $persist, int $seconds = 20): bool
    {
        $this->uploadDeadline = microtime(true) + $seconds;
        try {
            return $this->uploadWithinDeadline($file, $job, $persist);
        } finally {
            $this->uploadDeadline = null;
        }
    }

    private function uploadWithinDeadline(string $file, array &$job, callable $persist): bool
    {
        $drive = self::connection();
        if ($drive === []) throw new DomainException('Hubungkan akun Google Drive untuk melanjutkan.');
        if (($job['owner'] ?? $drive['owner']) !== $drive['owner']) throw new DomainException('Hubungkan kembali akun Google Drive yang dipakai saat backup dimulai.');
        $job['owner'] = $drive['owner'];
        $auth = $this->authorization();
        $size = filesize($file);
        if ($size === false || $size <= 0) throw new DomainException('File backup kosong atau tidak dapat dibaca.');

        if (empty($job['id'])) {
            $ids = $this->data($this->request('GET', self::API . '/files/generateIds?count=1&space=drive', $auth));
            $job['id'] = $ids['ids'][0] ?? throw new DomainException('Google Drive belum memberikan ID file.');
            $persist($job);
        }
        $offset = 0;
        if (! empty($job['url'])) {
            $reply = $this->request('PUT', $this->sessionUrl($job['url']), $auth, '', ['Content-Range: bytes */' . $size]);
            if (in_array($reply['status'], [200, 201], true)) return $this->complete($reply, $job, $file, $size);
            if (in_array($reply['status'], [404, 410], true)) {
                unset($job['url']);
                $persist($job);
            } else {
                $offset = $this->offset($reply, $size);
            }
        }
        if (empty($job['url'])) {
            $reply = $this->request('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,size,md5Checksum', $auth,
                json_encode(['id' => $job['id'], 'name' => basename($file), 'mimeType' => 'application/zip'], JSON_THROW_ON_ERROR),
                ['Content-Type: application/json', 'X-Upload-Content-Type: application/zip', 'X-Upload-Content-Length: ' . $size]);
            if ($reply['status'] === 409) {
                $metadata = $this->request('GET', self::API . '/files/' . rawurlencode($job['id']) . '?fields=id,size,md5Checksum', $auth);
                return $this->complete($metadata, $job, $file, $size);
            }
            if ($reply['status'] !== 200 || empty($reply['headers']['location'])) throw new DomainException('Google Drive belum dapat memulai unggahan.');
            $job['url'] = $this->sessionUrl($reply['headers']['location']);
            $persist($job);
        }

        $stream = fopen($file, 'rb');
        if ($stream === false) throw new DomainException('File backup tidak bisa dibaca.');
        try {
            do {
                $length = min(self::CHUNK, $size - $offset);
                if (fseek($stream, $offset) !== 0) throw new DomainException('File backup tidak bisa dilanjutkan.');
                $chunk = fread($stream, $length);
                if ($chunk === false || strlen($chunk) !== $length) throw new DomainException('File backup berubah saat diunggah.');
                $reply = $this->request('PUT', $this->sessionUrl($job['url']), $auth, $chunk, [
                    'Content-Type: application/zip', 'Content-Range: bytes ' . $offset . '-' . ($offset + $length - 1) . '/' . $size,
                ]);
                if (in_array($reply['status'], [200, 201], true)) return $this->complete($reply, $job, $file, $size);
                $next = $this->offset($reply, $size);
                if ($next <= $offset) return false;
                $offset = $next;
            } while (microtime(true) < $this->uploadDeadline);
            return false;
        } finally {
            fclose($stream);
        }
    }

    private function complete(array $reply, array $job, string $file, int $size): bool
    {
        $data = $this->data($reply);
        if (($data['id'] ?? '') !== $job['id'] || (int) ($data['size'] ?? 0) !== $size
            || ! hash_equals((string) md5_file($file), (string) ($data['md5Checksum'] ?? ''))) {
            throw new DomainException('Google Drive belum mengonfirmasi file backup lengkap.');
        }
        return true;
    }

    private function offset(array $reply, int $size): int
    {
        if ($reply['status'] !== 308) throw new DomainException('Google Drive belum dapat menerima backup.');
        $range = $reply['headers']['range'] ?? '';
        if ($range === '') return 0;
        if (! preg_match('/\Abytes=0-(\d+)\z/', $range, $match) || (int) $match[1] >= $size - 1) {
            throw new DomainException('Posisi unggahan dari Google Drive belum valid.');
        }
        return (int) $match[1] + 1;
    }

    private function sessionUrl(string $url): string
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'www.googleapis.com'
            || isset($parts['user']) || isset($parts['port']) || ! str_starts_with($parts['path'] ?? '', '/upload/drive/v3/files')) {
            throw new DomainException('Alamat sesi unggahan Google Drive tidak valid.');
        }
        return $url;
    }

    private function data(array $reply): array
    {
        if (! in_array($reply['status'], [200, 201], true)) throw new DomainException('Google Drive belum tersedia atau akses akun perlu dihubungkan ulang.');
        $data = json_decode($reply['body'], true);
        if (! is_array($data)) throw new DomainException('Respons Google Drive tidak dapat dibaca.');
        return $data;
    }

    private function request(string $method, string $url, array $auth, ?string $body = null, array $headers = [], ?callable $write = null): array
    {
        $timeout = $write === null ? 20000 : 60000;
        if ($this->uploadDeadline !== null) {
            $timeout = min($timeout, (int) (($this->uploadDeadline - microtime(true)) * 1000));
        }
        if ($timeout <= 0) throw new DomainException('Pengiriman ke Google Drive masih menunggu.');
        $reply = $this->transport === null
            ? $this->send($method, $url, $auth, $body, $headers, $timeout, $write)
            : ($this->transport)($method, $url, $auth, $body, $headers, $write);
        if ($reply['status'] === 401) {
            DriveOAuthLink::markInvalid();
            throw new DomainException('Izin Google Drive berakhir atau dicabut. Hubungkan ulang akun.');
        }
        return $reply;
    }

    private function send(string $method, string $url, array $auth, ?string $body, array $headers, int $timeout, ?callable $write): array
    {
        $received = [];
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => array_merge($auth, $headers),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => min(5000, $timeout), CURLOPT_TIMEOUT_MS => $timeout,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$received): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $received[strtolower($name)] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        if ($write !== null) {
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($curl, CURLOPT_WRITEFUNCTION, static fn ($curl, string $chunk): int => $write($chunk));
        }
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        unset($curl);
        if ($response === false) throw new DomainException('Koneksi ke Google Drive terputus.');
        return ['status' => $status, 'headers' => $received, 'body' => is_string($response) ? $response : ''];
    }
}
