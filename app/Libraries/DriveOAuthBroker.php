<?php

namespace App\Libraries;

use DomainException;

final class DriveOAuthBroker
{
    public const ORIGIN = 'https://pindaiin.my.id';
    public const CALLBACK = self::ORIGIN . '/admin/backups/drive/callback';
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';
    public const LIFETIME = 600;
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct(private readonly ?\Closure $transport = null)
    {
    }

    public static function isHost(): bool
    {
        return rtrim(config('App')->baseURL, '/') === self::ORIGIN;
    }

    public static function enabled(): bool
    {
        return self::isHost() && self::supported()
            && (string) env('backup.driveClientId', '') !== ''
            && (string) env('backup.driveClientSecret', '') !== '';
    }

    public static function supported(): bool
    {
        return function_exists('curl_init') && function_exists('openssl_encrypt');
    }

    public function channel(string $action, string $key, string $id = '', string $source = ''): array
    {
        if (! self::valid($key)) throw new DomainException('Permintaan sambungan tidak valid.');
        $link = hash('sha256', $key);
        return match ($action) {
            'begin' => $this->begin($link, hash('sha256', $source)),
            'claim' => $this->claim($link, $id),
            'access' => $this->access($link, $id),
            'disconnect' => $this->disconnect($link, $id),
            default => throw new DomainException('Permintaan sambungan tidak valid.'),
        };
    }

    private function begin(string $link, string $source): array
    {
        $lock = BackupStorage::lock('drive-oauth-pairs', LOCK_EX);
        try {
            $total = 0;
            $fromSource = 0;
            $now = time();
            foreach (glob(BackupStorage::directory() . 'oauth-pair-*.json') ?: [] as $path) {
                $name = basename($path, '.json');
                $pair = DriveOAuthVault::read($name);
                if (($pair['expires'] ?? 0) <= $now) {
                    DriveOAuthVault::forget($name);
                    continue;
                }
                $total++;
                if ($pair['source'] === $source) $fromSource++;
            }
            if ($total >= 200 || $fromSource >= 12) {
                throw new DomainException('Terlalu banyak percobaan sambungan. Coba lagi beberapa menit.', 429);
            }
            $id = bin2hex(random_bytes(32));
            $ticket = bin2hex(random_bytes(32));
            DriveOAuthVault::save('oauth-pair-' . $id, [
                'link' => $link, 'source' => $source, 'ticket' => hash('sha256', $ticket),
                'expires' => $now + self::LIFETIME, 'stage' => 'new',
            ]);
            return ['id' => $id, 'ticket' => $ticket];
        } finally {
            fclose($lock);
        }
    }

    public function launch(string $id, string $ticket): array
    {
        $lock = BackupStorage::lock('drive-oauth-pairs', LOCK_EX);
        try {
            $pair = $this->pair($id);
            if ($pair['stage'] !== 'new' || ! self::valid($ticket)
                || ! hash_equals($pair['ticket'], hash('sha256', $ticket))) {
                throw new DomainException('Tautan persetujuan sudah dipakai. Mulai lagi dari Pindai.in.');
            }
            $state = bin2hex(random_bytes(32));
            $verifier = bin2hex(random_bytes(32));
            unset($pair['ticket']);
            $pair += ['state' => hash('sha256', $state), 'verifier' => $verifier];
            $pair['stage'] = 'waiting';
            DriveOAuthVault::save('oauth-pair-' . $id, $pair);
            $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => (string) env('backup.driveClientId', ''), 'redirect_uri' => self::CALLBACK,
                'response_type' => 'code', 'scope' => self::SCOPE, 'state' => $state,
                'access_type' => 'offline', 'prompt' => 'select_account consent',
                'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
                'code_challenge_method' => 'S256',
            ], '', '&', PHP_QUERY_RFC3986);
            return ['state' => $state, 'url' => $url];
        } finally {
            fclose($lock);
        }
    }

    public function finish(string $id, string $state, string $code): bool
    {
        $lock = BackupStorage::lock('drive-oauth-pairs', LOCK_EX);
        try {
            $pair = $this->pair($id);
            if ($pair['stage'] !== 'waiting' || ! hash_equals($pair['state'], hash('sha256', $state))) {
                throw new DomainException('Sesi persetujuan Google Drive tidak cocok.');
            }
            $verifier = $pair['verifier'];
            unset($pair['state'], $pair['verifier']);
            $pair['stage'] = $code === '' ? 'denied' : 'exchanging';
            DriveOAuthVault::save('oauth-pair-' . $id, $pair);
        } finally {
            fclose($lock);
        }
        if ($code === '') return false;

        try {
            $tokens = $this->token([
                'grant_type' => 'authorization_code', 'code' => $code,
                'code_verifier' => $verifier, 'redirect_uri' => self::CALLBACK,
            ]);
            $identity = $this->google('GET', 'https://www.googleapis.com/drive/v3/about?fields=user(permissionId,emailAddress)',
                ['Authorization: Bearer ' . $tokens['access_token']]);
            $owner = $identity['data']['user']['permissionId'] ?? '';
            if ($identity['status'] !== 200 || ! is_string($owner) || $owner === '') {
                throw new DomainException('Akun Drive tidak dikenali.');
            }
            $pair['google'] = $this->grant($tokens) + [
                'owner' => $owner, 'email' => (string) ($identity['data']['user']['emailAddress'] ?? ''),
            ];
            $pair['stage'] = 'approved';
        } catch (\Throwable $e) {
            // OAuth requests and provider payloads can contain credentials.
            $pair['stage'] = 'failed';
        }
        $lock = BackupStorage::lock('drive-oauth-pairs', LOCK_EX);
        try {
            $this->pair($id);
            DriveOAuthVault::save('oauth-pair-' . $id, $pair);
        } finally {
            fclose($lock);
        }
        return $pair['stage'] === 'approved';
    }

    private function claim(string $link, string $id): array
    {
        $pair = $this->pair($id);
        if (! hash_equals($pair['link'], $link)) throw new DomainException('Sesi persetujuan tidak cocok.');
        if ($pair['stage'] !== 'approved' && $pair['stage'] !== 'claimed') return ['status' => $pair['stage']];

        $lock = $this->linkLock($link);
        try {
            $pairLock = BackupStorage::lock('drive-oauth-pairs', LOCK_EX);
            $pair = $this->pair($id);
            $name = 'oauth-link-' . $link;
            $current = DriveOAuthVault::read($name);
            if (($current['id'] ?? '') !== $id) {
                if ($pair['stage'] === 'claimed' || ($current['started'] ?? 0) > $pair['expires']) {
                    return ['status' => 'superseded'];
                }
                $grant = $pair['google'];
                if ($grant['refresh'] === '' && ($current['owner'] ?? '') === $grant['owner'] && empty($current['invalid'])) {
                    $grant['refresh'] = $current['refresh'];
                }
                if ($grant['refresh'] === '') return ['status' => 'reconnect'];
                $current = $grant + ['id' => $id, 'started' => $pair['expires']];
                DriveOAuthVault::save($name, $current);
            }
            // Keep the claim retryable without retaining a second copy of the Google tokens.
            unset($pair['google']);
            $pair['stage'] = 'claimed';
            DriveOAuthVault::save('oauth-pair-' . $id, $pair);
            return ['status' => 'connected', 'owner' => $current['owner'], 'email' => $current['email']];
        } finally {
            if (isset($pairLock)) fclose($pairLock);
            fclose($lock);
        }
    }

    private function access(string $link, string $id): array
    {
        $name = 'oauth-link-' . $link;
        if (! self::valid($id) || ! is_file(BackupStorage::directory() . $name . '.json')) return ['status' => 'reconnect'];
        $lock = $this->linkLock($link);
        try {
            $current = DriveOAuthVault::read($name);
            if (($current['id'] ?? '') !== $id || ! empty($current['invalid'])) return ['status' => 'reconnect'];
            if ($current['expiry'] <= time() + 90) {
                $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $current['refresh']], true);
                if ($token === []) {
                    unset($current['access'], $current['refresh']);
                    $current['invalid'] = true;
                    DriveOAuthVault::save($name, $current);
                    return ['status' => 'reconnect'];
                }
                $grant = $this->grant($token);
                if ($grant['refresh'] === '') $grant['refresh'] = $current['refresh'];
                $current = $grant + $current;
                DriveOAuthVault::save($name, $current);
            }
            return ['status' => 'connected', 'access' => $current['access']];
        } finally {
            fclose($lock);
        }
    }

    private function disconnect(string $link, string $id): array
    {
        if (! self::valid($id)) return ['status' => 'disconnected'];
        $name = 'oauth-link-' . $link;
        $lock = $this->linkLock($link);
        try {
            $pairLock = BackupStorage::lock('drive-oauth-pairs', LOCK_EX);
            $pairName = 'oauth-pair-' . $id;
            $pair = DriveOAuthVault::read($pairName);
            if (isset($pair['link']) && hash_equals($pair['link'], $link)) DriveOAuthVault::forget($pairName);
            $current = DriveOAuthVault::read($name);
            if (($current['id'] ?? '') === $id) DriveOAuthVault::forget($name);
            return ['status' => 'disconnected'];
        } finally {
            if (isset($pairLock)) fclose($pairLock);
            fclose($lock);
        }
    }

    private function linkLock(string $link)
    {
        // A fixed number of locks bounds disk use, including after disconnect.
        return BackupStorage::lock('drive-oauth-link-' . substr($link, 0, 2), LOCK_EX);
    }

    private function pair(string $id): array
    {
        $pair = self::valid($id) ? DriveOAuthVault::read('oauth-pair-' . $id) : [];
        if (($pair['expires'] ?? 0) <= time()) throw new DomainException('Sesi persetujuan Google Drive berakhir.', 410);
        return $pair;
    }

    public static function valid(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }

    private function grant(array $tokens): array
    {
        return [
            'access' => $tokens['access_token'], 'refresh' => (string) ($tokens['refresh_token'] ?? ''),
            'expiry' => time() + max(0, (int) ($tokens['expires_in'] ?? 0)),
        ];
    }

    private function token(array $form, bool $refresh = false): array
    {
        $response = $this->google('POST', self::TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query($form + [
                'client_id' => (string) env('backup.driveClientId', ''),
                'client_secret' => (string) env('backup.driveClientSecret', ''),
            ]));
        $tokens = $response['data'];
        if ($refresh && ($tokens['error'] ?? '') === 'invalid_grant') return [];
        if ($response['status'] !== 200 || ! is_string($tokens['access_token'] ?? null) || $tokens['access_token'] === '') {
            throw new DomainException('Google belum dapat memberikan akses Drive. Coba lagi.');
        }
        if (isset($tokens['scope']) && (! is_string($tokens['scope']) || ! in_array(self::SCOPE, explode(' ', $tokens['scope']), true))) {
            throw new DomainException('Izin untuk menyimpan file ke Drive belum diberikan.');
        }
        return $tokens;
    }

    private function google(string $method, string $url, array $headers, ?string $body = null): array
    {
        return ($this->transport ?? DriveOAuthLink::request(...))($method, $url, $headers, $body);
    }
}
