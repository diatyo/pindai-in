<?php

declare(strict_types=1);

// Standalone test with synthetic OAuth responses. Never reads the application's data directory.
if (PHP_SAPI !== 'cli') exit(1);

$fixture = sys_get_temp_dir() . '/pindai-oauth-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
define('WRITEPATH', $fixture . '/');
define('FCPATH', dirname(__DIR__) . '/public/');

function config(string $name): object
{
    return (object) ['baseURL' => 'http://127.0.0.1:48037/', 'appTimezone' => 'Asia/Jakarta'];
}

function env(string $name, mixed $default = null): mixed
{
    return match ($name) {
        'backup.driveClientId' => 'fixture.apps.googleusercontent.com',
        'backup.driveClientSecret' => 'fixture-secret',
        default => $default,
    };
}

function check(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}

foreach (['BackupStorage', 'DriveOAuthVault', 'DriveOAuthLink', 'DriveOAuthBroker', 'BackupDrive', 'BackupJobs', 'BackupSchedule'] as $class) {
    require dirname(__DIR__) . '/app/Libraries/' . $class . '.php';
}

use App\Libraries\BackupDrive;
use App\Libraries\BackupJobs;
use App\Libraries\BackupStorage;
use App\Libraries\DriveOAuthBroker;
use App\Libraries\DriveOAuthLink;
use App\Libraries\DriveOAuthVault;

try {
    $owner = 'owner-demo';
    $issueRefresh = true;
    $revoked = false;
    $refreshCount = 0;
    $challenge = '';
    $broker = new DriveOAuthBroker(static function ($method, $url, $headers, $body) use (&$owner, &$issueRefresh, &$revoked, &$refreshCount, &$challenge): array {
        if ($method === 'GET' && str_contains($url, '/about?')) {
            return ['status' => 200, 'data' => ['user' => ['permissionId' => $owner, 'emailAddress' => 'fixture@example.test']]];
        }
        check($url === 'https://oauth2.googleapis.com/token', 'Unexpected OAuth destination.');
        parse_str($body, $form);
        check($form['client_secret'] === 'fixture-secret', 'Web secret missing on central exchange.');
        if ($form['grant_type'] === 'refresh_token') {
            $refreshCount++;
            check($form['refresh_token'] === 'mock-refresh-token', 'Previous refresh token was lost.');
            return $revoked
                ? ['status' => 400, 'data' => ['error' => 'invalid_grant']]
                : ['status' => 200, 'data' => ['access_token' => 'mock-access-refreshed', 'expires_in' => 0]];
        }
        check($form['redirect_uri'] === DriveOAuthBroker::CALLBACK, 'Google callback should always be the HTTPS hosting URL.');
        $verifier = rtrim(strtr(base64_encode(hash('sha256', $form['code_verifier'], true)), '+/', '-_'), '=');
        check($verifier === $challenge && $form['code'] === 'fixture-code', 'PKCE exchange mismatch.');
        $token = ['access_token' => 'mock-access-token', 'expires_in' => 0, 'scope' => DriveOAuthBroker::SCOPE];
        if ($issueRefresh) $token['refresh_token'] = 'mock-refresh-token';
        return ['status' => 200, 'data' => $token];
    });
    $local = new DriveOAuthLink($broker->channel(...));
    $prepare = static function () use ($local, $broker, &$challenge): array {
        $pair = $local->begin('fixture');
        parse_str(parse_url($pair['url'], PHP_URL_QUERY), $params);
        $flow = $broker->launch($params['id'], $params['ticket']);
        parse_str(parse_url($flow['url'], PHP_URL_QUERY), $auth);
        check($auth['redirect_uri'] === DriveOAuthBroker::CALLBACK, 'Wrong consent callback.');
        check($auth['access_type'] === 'offline' && $auth['code_challenge_method'] === 'S256', 'Wrong OAuth parameters.');
        check($auth['prompt'] === 'select_account consent', 'Google account selection should be available during reconnect.');
        check($auth['scope'] === DriveOAuthBroker::SCOPE, 'Unexpected Google permissions.');
        check(! str_contains($flow['url'], 'fixture-secret'), 'Web secret exposed in browser URL.');
        $challenge = $auth['code_challenge'];
        return [$pair['id'], $flow['state']];
    };

    [$id, $state] = $prepare();
    check($local->poll($id) === 'waiting', 'Waiting status missing.');
    try {
        $broker->finish($id, str_repeat('0', 64), 'fixture-code');
        throw new RuntimeException('Mismatched state accepted.');
    } catch (DomainException) {
    }
    check($broker->finish($id, $state, 'fixture-code'), 'Google exchange failed.');
    try {
        $broker->channel('claim', str_repeat('a', 64), $id);
        throw new RuntimeException('A different installation claimed the authorization.');
    } catch (DomainException) {
    }
    check($local->poll($id) === 'connected', 'Installation could not claim token.');
    try {
        $broker->finish($id, $state, 'fixture-code');
        throw new RuntimeException('Callback replay accepted.');
    } catch (DomainException) {
    }
    check($local->access() === 'mock-access-refreshed' && $refreshCount === 1, 'Access token was not refreshed.');

    $issueRefresh = false;
    [$id, $state] = $prepare();
    $broker->finish($id, $state, 'fixture-code');
    check($local->poll($id) === 'connected' && $local->access() === 'mock-access-refreshed', 'Reconnect discarded refresh token.');
    $owner = 'another-account';
    [$otherId, $otherState] = $prepare();
    $broker->finish($otherId, $otherState, 'fixture-code');
    check($local->poll($otherId) === 'reconnect' && DriveOAuthLink::status()['owner'] === 'owner-demo', 'Another account replaced the original grant without a refresh token.');
    $owner = 'owner-demo';
    foreach (glob(BackupStorage::directory() . '*.json') as $file) {
        check(! str_contains(file_get_contents($file), 'mock-refresh-token')
            && ! str_contains(file_get_contents($file), 'mock-access-'), 'Token was persisted in plaintext.');
    }

    $archive = 'pindai-backup-20261111-000000-0123456789ab.pindai';
    $payload = 'synthetic archive bytes';
    file_put_contents(BackupStorage::path($archive), $payload);
    BackupStorage::save('upload-' . $archive, ['temporary' => true, 'owner' => $owner]);
    $drive = new BackupDrive(static function ($method, $url, $auth, $body) use ($payload): array {
        check($auth === ['Authorization: Bearer mock-access-refreshed'], 'Drive upload did not get the refreshed token.');
        if (str_contains($url, '/generateIds')) return ['status' => 200, 'body' => '{"ids":["fixture-file"]}'];
        if ($method === 'POST') return ['status' => 200, 'headers' => ['location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=fixture']];
        check($method === 'PUT' && $body === $payload, 'Upload bytes changed.');
        return ['status' => 200, 'body' => json_encode(['id' => 'fixture-file', 'size' => strlen($payload), 'md5Checksum' => md5($payload)])];
    }, $local);
    $jobs = new BackupJobs($drive);
    check($jobs->send($archive) && ! is_file(BackupStorage::path($archive)), 'Confirmed Drive upload did not clean up its temporary file.');
    check($jobs->files()[0]['driveId'] === 'fixture-file', 'Drive backup history disappeared.');

    $driveMetadata = [
        'id' => 'hosting-backup', 'name' => $archive, 'mimeType' => 'application/zip', 'size' => (string) strlen($payload),
        'md5Checksum' => md5($payload), 'createdTime' => '2026-11-11T10:26:51Z',
        'trashed' => false, 'capabilities' => ['canDownload' => true],
    ];
    $downloadBytes = $payload;
    $reader = new BackupDrive(static function ($method, $url, $auth, $body, $headers, $write) use ($driveMetadata, &$downloadBytes): array {
        check($method === 'GET' && $auth === ['Authorization: Bearer mock-access-refreshed'], 'Drive read did not use the connected account.');
        if (str_contains($url, '/files?')) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            check(str_contains($query['q'], 'trashed = false') && str_contains($query['q'], 'pindai-backup-'), 'Unexpected archive search.');
            return ['status' => 200, 'body' => json_encode(['files' => [
                $driveMetadata, array_replace($driveMetadata, ['name' => 'unrelated.zip']),
            ]])];
        }
        check(str_contains($url, '/files/hosting-backup?'), 'Unexpected archive ID.');
        if ($write !== null) {
            check($write(substr($downloadBytes, 0, 5)) === 5 && $write(substr($downloadBytes, 5)) === strlen($downloadBytes) - 5, 'Stream write failed.');
            return ['status' => 200, 'body' => ''];
        }
        return ['status' => 200, 'body' => json_encode($driveMetadata)];
    }, $local);
    $listing = $reader->archives();
    check(count($listing['files']) === 1 && $listing['files'][0]['id'] === 'hosting-backup', 'Drive history did not include the backup created by another installation.');
    $download = BackupStorage::temporary();
    try {
        $reader->downloadArchive('hosting-backup', $download);
        check(file_get_contents($download) === $payload, 'Drive download bytes did not match.');
        $downloadBytes = str_repeat('x', strlen($payload));
        try {
            $reader->downloadArchive('hosting-backup', $download);
            throw new RuntimeException('Corrupted Drive archive was accepted.');
        } catch (DomainException $e) {
            check(str_contains($e->getMessage(), 'belum lengkap'), 'Unexpected validation failure.');
        }
    } finally {
        unlink($download);
    }

    $revoked = true;
    try {
        $local->access();
        throw new RuntimeException('Revoked token accepted.');
    } catch (DomainException) {
        check(DriveOAuthLink::status()['reconnect'], 'Revoked access did not require reconnect.');
    }
    [$deniedId, $deniedState] = $prepare();
    check(! $broker->finish($deniedId, $deniedState, '') && $local->poll($deniedId) === 'denied', 'Cancellation handling failed.');
    $revoked = false;
    $issueRefresh = true;
    [$newId, $newState] = $prepare();
    $broker->finish($newId, $newState, 'fixture-code');
    check($local->poll($newId) === 'connected', 'Reauthorization after revocation failed.');
    $record = DriveOAuthVault::read('drive-oauth-installation');
    $oldPending = 'pindai-backup-20261111-000000-111111111111.pindai';
    $currentPending = 'pindai-backup-20261111-000000-222222222222.pindai';
    file_put_contents(BackupStorage::path($oldPending), $payload);
    BackupStorage::save('upload-' . $oldPending, ['temporary' => true, 'owner' => $owner]);
    $owner = 'another-account';
    [$switchId, $switchState] = $prepare();
    $broker->finish($switchId, $switchState, 'fixture-code');
    check($local->poll($switchId) === 'connected' && DriveOAuthLink::status()['owner'] === $owner, 'Could not switch Google accounts.');
    $rows = array_column($jobs->files(), null, 'name');
    check(! $rows[$oldPending]['pending'], 'Another account upload prevents starting a backup.');
    file_put_contents(BackupStorage::path($currentPending), $payload);
    BackupStorage::save('upload-' . $currentPending, ['temporary' => true, 'owner' => $owner]);
    $result = $jobs->create();
    check($result['name'] === $currentPending && $result['sent'] && ! $result['new'], 'Did not resume the pending upload for the current account.');
    check(file_get_contents(BackupStorage::path($oldPending)) === $payload, 'Switching accounts modified the previous account backup.');

    [$pendingId, $pendingState] = $prepare();
    $broker->finish($pendingId, $pendingState, 'fixture-code');
    $broker->channel('disconnect', str_repeat('a', 64), $pendingId);
    check(DriveOAuthVault::read('oauth-pair-' . $pendingId)['stage'] === 'approved', 'Different installation canceled the pending approval.');
    $local->disconnect();
    check(! DriveOAuthLink::status()['connected'], 'Local disconnect failed.');
    check($broker->channel('access', $record['key'], $switchId)['status'] === 'reconnect', 'Central disconnect failed.');
    check(DriveOAuthVault::read('oauth-pair-' . $pendingId) === [], 'Disconnect left a pending authorization active.');
    check(array_column($jobs->files(), null, 'name')[$archive]['driveId'] === 'fixture-file', 'Disconnect changed backup history.');
    echo "PASS OAuth state/PKCE, isolated claims, encrypted tokens, refresh/revoke, Drive upload, account switch and disconnect.\n";
} finally {
    if (is_dir($fixture . '/backups')) {
        foreach (glob($fixture . '/backups/*') as $file) unlink($file);
        rmdir($fixture . '/backups');
    }
    rmdir($fixture);
}
