<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Filters\BackupRestoreLock;
use App\Libraries\BackupArchive;
use App\Libraries\BackupDrive;
use App\Libraries\BackupJobs;
use App\Libraries\BackupStorage;
use App\Libraries\DriveOAuthBroker;
use App\Libraries\DriveOAuthLink;
use CodeIgniter\Exceptions\PageNotFoundException;
use DomainException;

final class Backups extends BaseController
{
    public function index()
    {
        if ($this->request->getGet('driveArchives') === '1') return $this->driveArchives();
        $pending = session()->get('driveOAuthPending');
        return view('admin/backups', [
            'files' => (new BackupJobs())->files(),
            'ready' => BackupArchive::available(), 'driveReady' => DriveOAuthLink::ready(),
            'drive' => DriveOAuthLink::status(),
            'driveWaiting' => ($pending['expires'] ?? 0) > time(),
        ]);
    }

    private function driveArchives()
    {
        $response = $this->response->setHeader('Cache-Control', 'no-store');
        try {
            $lock = BackupStorage::lock('job', LOCK_EX);
            try {
                $this->requireDrive();
                return $response->setJSON((new BackupDrive())->archives());
            } finally {
                fclose($lock);
            }
        } catch (\Throwable $e) {
            return $response->setStatusCode(503)->setJSON(['message' => $e instanceof DomainException
                ? $e->getMessage() : 'Daftar arsip Google Drive belum dapat dimuat.']);
        }
    }

    public function create()
    {
        return $this->attempt(function (): string {
            $this->requireDrive();
            $result = (new BackupJobs())->create();
            $message = $this->driveResult($result['sent']);
            log_activity($result['new'] ? 'backup_dibuat' : 'unggahan_backup_dicoba_ulang', 'Backup', $result['name'], $message);
            return $message;
        });
    }

    public function download(string $name)
    {
        try {
            $file = (new BackupJobs())->localFile($name);
            log_activity('backup_diunduh', 'Backup', $name);
            return $this->response->download($file, null)->setFileName($name);
        } catch (DomainException $e) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    public function queue(string $name)
    {
        return $this->attempt(function () use ($name): string {
            $this->requireDrive();
            $sent = (new BackupJobs())->send($name);
            $message = $this->driveResult($sent);
            log_activity($sent ? 'backup_diunggah_ke_drive' : 'unggahan_backup_diminta', 'Backup', $name, $message);
            return $message;
        });
    }

    public function restore()
    {
        $temporary = null;
        $name = $this->request->getPost('backup') ?? '';
        $driveId = $this->request->getPost('drive_backup') ?? '';
        try {
            if (! BackupRestoreLock::$exclusive) throw new DomainException('Pemulihan memerlukan akses eksklusif. Coba lagi.');
            $admin = service('authSession')->currentUser();
            $password = $this->request->getPost('password');
            if ($this->request->getPost('confirm') !== 'PULIHKAN' || ! $admin || ! is_string($password)
                || ! password_verify($password, $admin->password_hash)) throw new DomainException('Konfirmasi pemulihan atau kata sandi Admin tidak cocok.');

            $uploaded = $this->request->getFile('archive');
            $hasUpload = $uploaded && $uploaded->getError() !== UPLOAD_ERR_NO_FILE;
            if (! is_string($name) || ! is_string($driveId)
                || (int) $hasUpload + (int) ($name !== '') + (int) ($driveId !== '') !== 1) {
                throw new DomainException('Pilih satu sumber backup untuk dipulihkan.');
            }
            if ($hasUpload) {
                if (! $uploaded->isValid()) throw new DomainException('Unggahan gagal. Periksa ukuran file backup dan batas unggahan server.');
                $path = $uploaded->getTempName();
            } elseif ($driveId !== '') {
                $this->requireDrive();
                $temporary = BackupStorage::temporary();
                (new BackupDrive())->downloadArchive($driveId, $temporary);
                $path = $temporary;
            } else {
                $path = (new BackupJobs())->localFile($name);
            }
            $safety = (new BackupJobs())->restore($path, (string) $admin->full_name);
            session()->remove(array_keys(session()->get()));
            session()->regenerate(true);
            return redirect()->to('/login')->with('success', 'Pemulihan selesai. Masuk dengan akun dari backup. Cadangan sebelumnya: ' . $safety);
        } catch (\Throwable $e) {
            $selection = match (true) {
                is_string($driveId) && preg_match('/\A[A-Za-z0-9_-]{1,200}\z/', $driveId) === 1 => ['source' => 'drive', 'archive' => $driveId],
                is_string($name) && BackupStorage::validName($name) => ['source' => 'server', 'archive' => $name],
                default => ['source' => 'device', 'archive' => ''],
            };
            return $this->failure($e)->with('backupRestoreSelection', $selection);
        } finally {
            if ($temporary !== null) unlink($temporary);
        }
    }

    public function connectDrive()
    {
        try {
            $pair = (new DriveOAuthLink())->begin($this->request->getIPAddress());
            session()->set('driveOAuthPending', ['id' => $pair['id'], 'expires' => time() + DriveOAuthBroker::LIFETIME]);
            return $this->driveJson(['url' => $pair['url']]);
        } catch (\Throwable $e) {
            return $this->driveJson(['message' => $e instanceof DomainException ? $e->getMessage()
                : 'Sambungan Google Drive belum dapat dimulai. Coba lagi.'], 503);
        }
    }

    public function pollDrive()
    {
        try {
            $pending = session()->get('driveOAuthPending');
            if (($pending['expires'] ?? 0) <= time()) {
                session()->remove('driveOAuthPending');
                return $this->driveJson(['status' => 'expired']);
            }
            $status = (new DriveOAuthLink())->poll($pending['id']);
            if (! in_array($status, ['new', 'waiting', 'exchanging'], true)) session()->remove('driveOAuthPending');
            if ($status === 'connected') {
                log_activity('drive_backup_dihubungkan', 'Backup');
                session()->setFlashdata('success', 'Akun Google Drive berhasil dihubungkan.');
            }
            return $this->driveJson(['status' => $status]);
        } catch (\Throwable $e) {
            if ($e instanceof DomainException && $e->getCode() === 410) {
                session()->remove('driveOAuthPending');
                return $this->driveJson(['status' => 'expired']);
            }
            return $this->driveJson(['status' => 'retry'], 503);
        }
    }

    public function disconnectDrive()
    {
        return $this->attempt(function (): string {
            (new DriveOAuthLink())->disconnect();
            session()->remove('driveOAuthPending');
            log_activity('drive_backup_diputus', 'Backup');
            return 'Sambungan Google Drive pada instalasi ini telah diputus.';
        });
    }

    private function driveJson(array $data, int $status = 200)
    {
        return $this->response->setHeader('Cache-Control', 'no-store')->setStatusCode($status)
            ->setJSON($data + ['csrf' => csrf_hash()]);
    }

    private function requireDrive(): void
    {
        if (BackupDrive::connection() === []) {
            throw new DomainException('Hubungkan akun Google Drive terlebih dahulu.');
        }
    }

    private function driveResult(bool $sent): string
    {
        return $sent ? 'Backup berhasil disimpan di Google Drive.'
            : 'Pengiriman ke Google Drive tertunda. Tekan Coba kirim lagi untuk melanjutkan backup yang sama.';
    }

    private function attempt(callable $operation)
    {
        try {
            return redirect()->to('/admin/backups')->with('success', $operation());
        } catch (\Throwable $e) {
            return $this->failure($e);
        }
    }

    private function failure(\Throwable $e, string $returnTo = '/admin/backups')
    {
        if (! $e instanceof DomainException) log_message('error', 'Operasi backup gagal ({type}).', ['type' => $e::class]);
        return redirect()->to($returnTo)->with('error', $e instanceof DomainException
            ? $e->getMessage() : 'Proses backup gagal. Periksa folder penyimpanan dan file yang dipilih.');
    }
}
