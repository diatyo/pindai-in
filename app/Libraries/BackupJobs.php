<?php

namespace App\Libraries;

use DomainException;

final class BackupJobs
{
    public function __construct(private readonly BackupDrive $drive = new BackupDrive())
    {
    }

    public function create(): array
    {
        $lock = BackupStorage::lock('job', LOCK_EX);
        try {
            $owner = BackupDrive::connection()['owner'] ?? '';
            if ($owner === '') throw new DomainException('Hubungkan akun Google Drive untuk membuat backup.');
            $this->clearDelivered();
            foreach ($this->uploads() as $name => $state) {
                if (! empty($state['temporary']) && empty($state['completed']) && $this->canResume($name, $state, $owner)) {
                    return ['name' => $name, 'sent' => $this->sendNow($name), 'new' => false];
                }
            }
            $now = BackupSchedule::now();
            $name = $this->name($now);
            BackupStorage::save('upload-' . $name, [
                'temporary' => true, 'time' => $now->getTimestamp(),
                'owner' => $owner,
            ]);
            return ['name' => $name, 'sent' => $this->sendNow($name), 'new' => true];
        } finally {
            fclose($lock);
        }
    }

    public function restore(string $source, string $actor): string
    {
        $lock = BackupStorage::lock('job', LOCK_EX);
        try {
            $backup = BackupArchive::configured();
            $safety = $this->name(BackupSchedule::now(), 'sebelum-pulih');
            $backup->restore($source, static function () use ($backup, $safety): void {
                $backup->create(BackupStorage::path($safety), allowMissingMedia: true);
            }, $actor);
            return $safety;
        } finally {
            fclose($lock);
        }
    }

    public function retryPending(): void
    {
        $dataLock = BackupStorage::lock('data', LOCK_SH);
        try {
            $jobLock = BackupStorage::lock('job', LOCK_EX);
            try {
                $this->clearDelivered();
                $owner = BackupDrive::connection()['owner'] ?? '';
                $now = time();
                foreach ($this->uploads() as $name => $state) {
                    if (! empty($state['completed']) || ($state['after'] ?? 0) > $now || ! $this->canResume($name, $state, $owner)) continue;
                    if ($this->upload($name, $state, $now, 20)) $this->audit('backup_diunggah_ke_drive', $name);
                    break;
                }
            } finally {
                fclose($jobLock);
            }
        } finally {
            fclose($dataLock);
        }
    }

    public function files(): array
    {
        $uploads = iterator_to_array($this->uploads());
        $owner = BackupDrive::connection()['owner'] ?? '';
        $files = [];
        foreach (glob(BackupStorage::directory() . 'pindai-*') ?: [] as $file) {
            $name = basename($file);
            try {
                BackupStorage::existing($name);
            } catch (DomainException) {
                continue;
            }
            $files[$name] = $this->fileRow($name, $uploads[$name] ?? [], $owner, $file);
        }
        foreach (array_diff_key($uploads, $files) as $name => $state) {
            if (! empty($state['temporary'])) $files[$name] = $this->fileRow($name, $state, $owner);
        }
        usort($files, static fn ($left, $right) => $right['time'] <=> $left['time']);
        return $files;
    }

    public function localFile(string $name): string
    {
        $file = BackupStorage::existing($name);
        if (! empty(BackupStorage::state('upload-' . $name)['temporary'])) {
            throw new DomainException('Cadangan ini tersedia melalui Google Drive setelah pengiriman selesai.');
        }
        return $file;
    }

    public function send(string $name): bool
    {
        $lock = BackupStorage::lock('job', LOCK_EX);
        try {
            return $this->sendNow($name);
        } finally {
            fclose($lock);
        }
    }

    private function uploads(): iterable
    {
        foreach (glob(BackupStorage::directory() . 'upload-pindai-*.json') ?: [] as $file) {
            $key = basename($file, '.json');
            $name = substr($key, strlen('upload-'));
            try {
                BackupStorage::path($name);
            } catch (DomainException $e) {
                continue;
            }
            yield $name => BackupStorage::state($key);
        }
    }

    private function fileRow(string $name, array $state, string $owner, ?string $file = null): array
    {
        $temporary = ! empty($state['temporary']);
        $completed = ! empty($state['completed']);
        $lost = $temporary && ! $completed && $file === null && ! empty($state['id']);
        $otherAccount = $owner !== '' && ! $this->belongsToDrive($state, $owner);
        return [
            'name' => $name, 'time' => $state['time'] ?? ($file ? filemtime($file) : 0),
            'bytes' => $state['bytes'] ?? ($file ? filesize($file) : 0),
            'local' => ! $temporary && $file !== null,
            'pending' => $temporary && ! $completed && ! $lost && ! $otherAccount,
            'drive' => match (true) {
                $completed => 'Tersimpan di Google Drive',
                $lost => 'File sementara hilang',
                $otherAccount => 'Menunggu akun Google Drive sebelumnya',
                $state !== [] => 'Menunggu pengiriman',
                default => 'Tersimpan di server',
            },
            'driveId' => $completed ? ($state['id'] ?? null) : null, 'error' => $state['error'] ?? null,
        ];
    }

    private function canResume(string $name, array $state, string $owner): bool
    {
        if (! $this->belongsToDrive($state, $owner)) return false;
        $file = BackupStorage::path($name);
        return (is_file($file) && ! is_link($file)) || (! empty($state['temporary']) && empty($state['id']));
    }

    private function belongsToDrive(array $state, string $owner): bool
    {
        return $owner !== '' && ($state['owner'] ?? $owner) === $owner;
    }

    private function sendNow(string $name): bool
    {
        BackupStorage::path($name);
        $state = BackupStorage::state('upload-' . $name);
        if (! $state) {
            BackupStorage::existing($name);
            $state = ['owner' => BackupDrive::connection()['owner'] ?? null];
            BackupStorage::save('upload-' . $name, $state);
        }
        return ! empty($state['completed']) || $this->upload($name, $state, time(), 10);
    }

    private function upload(string $name, array $state, int $now, int $seconds): bool
    {
        $key = 'upload-' . $name;
        try {
            if (BackupDrive::connection() === []) {
                throw new DomainException('Hubungkan Google Drive untuk melanjutkan pengiriman.');
            }
            $path = BackupStorage::path($name);
            if (! empty($state['temporary']) && empty($state['id']) && ! file_exists($path) && ! is_link($path)) {
                BackupStorage::clearTemporaryFiles();
                BackupArchive::configured()->create($path);
            }
            $source = BackupStorage::existing($name);
            $state['time'] = filemtime($source);
            $state['bytes'] = filesize($source);
            $state['completed'] = $this->drive->upload($source, $state, static fn ($data) => BackupStorage::save($key, $data), $seconds);
            $state['attempts'] = 0;
            $state['after'] = 0;
            unset($state['error']);
            if ($state['completed']) unset($state['url']);
        } catch (\Throwable $e) {
            $state['attempts'] = min(6, (int) ($state['attempts'] ?? 0) + 1);
            $state['after'] = $now + min(300, 60 * (2 ** ($state['attempts'] - 1)));
            $state['error'] = $e instanceof DomainException ? $e->getMessage() : 'Pengiriman ke Google Drive tertunda.';
        }
        BackupStorage::save($key, $state);
        if (! empty($state['completed']) && ! empty($state['temporary']) && ! $this->removeDeliveredFile($name)) {
            $state['error'] = 'Backup tersimpan di Google Drive. File sementara masih menunggu dibersihkan.';
            BackupStorage::save($key, $state);
        }
        return ! empty($state['completed']);
    }

    private function clearDelivered(): void
    {
        foreach ($this->uploads() as $name => $state) {
            if (empty($state['completed']) || empty($state['temporary'])) continue;
            if (! $this->removeDeliveredFile($name)) {
                throw new DomainException('Backup sudah ada di Drive. Pembersihan file sementara tertunda. Coba lagi sebentar.');
            }
            if (isset($state['error'])) {
                unset($state['error']);
                BackupStorage::save('upload-' . $name, $state);
            }
        }
    }

    private function removeDeliveredFile(string $name): bool
    {
        $path = BackupStorage::path($name);
        return ! is_link($path) && (! is_file($path) || @unlink($path));
    }

    private function name(\DateTimeImmutable $now, string $kind = 'backup'): string
    {
        return 'pindai-' . $kind . '-' . $now->format('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.pindai';
    }

    private function audit(string $action, string $name): void
    {
        try {
            if (! \Config\Database::connect()->table('activity_logs')->insert([
                'user_name' => 'Sistem', 'action' => $action, 'module' => 'Backup', 'object' => $name,
                'ip_address' => 'cli', 'created_at' => BackupSchedule::now()->format('Y-m-d H:i:s'),
            ])) throw new \RuntimeException('Gagal menyimpan log backup.');
        } catch (\Throwable $e) {
            log_message('error', 'Audit worker backup gagal disimpan untuk aksi {action}.', ['action' => $action]);
        }
    }
}
