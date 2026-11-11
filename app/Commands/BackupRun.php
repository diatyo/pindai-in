<?php

namespace App\Commands;

use App\Libraries\BackupJobs;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class BackupRun extends BaseCommand
{
    protected $group = 'Pindai.in';
    protected $name = 'backup:run';
    protected $description = 'Melanjutkan pengiriman backup yang tertunda ke Google Drive.';

    public function run(array $params)
    {
        try {
            (new BackupJobs())->retryPending();
            return EXIT_SUCCESS;
        } catch (\DomainException $e) {
            return EXIT_SUCCESS;
        } catch (\Throwable $e) {
            CLI::error('Worker backup gagal dijalankan. Periksa izin folder writable/backups.');
            return EXIT_ERROR;
        }
    }
}
