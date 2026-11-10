<?php

use App\Database\Migrations\CreateActivityLogs;
use App\Models\ActivityLogModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

require_once APPPATH . 'Database/Migrations/2026-08-01-000008_CreateActivityLogs.php';

final class ActivitySearchTest extends CIUnitTestCase
{
    private BaseConnection $activityDb;
    private ActivityLogModel $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activityDb = Database::connect('tests', false);
        if ($this->activityDb->DBDriver !== 'SQLite3' || $this->activityDb->database !== ':memory:') {
            throw new RuntimeException('Activity search tests require an isolated SQLite database.');
        }
        (new CreateActivityLogs(Database::forge($this->activityDb)))->up();
        $this->logs = new ActivityLogModel($this->activityDb);
        $rows = [
            [1, 'Barang', 'barang_diperbarui', 'UNT-000003', "Nama unit: CHROMEBOOK → ADVAN Chromebook\nModel: Chromebook 116"],
            [2, 'Barang', 'barang_diperbarui', 'UNT-000004', 'Nama unit: Chromebook akun lain'],
            [1, 'Peminjaman', 'peminjaman_dibuat', 'UNT-000003', 'Chromebook diserahkan'],
            [1, 'Barang', 'barang_dibuat', 'UNT-000005', 'Progres 100%'],
            [1, 'Barang', 'barang_dibuat', 'UNT-000006', 'Progres 100x'],
        ];
        foreach ($rows as [$userId, $module, $action, $object, $detail]) {
            $this->activityDb->table('activity_logs')->insert([
                'user_id' => $userId, 'user_name' => 'Petugas ' . $userId,
                'module' => $module, 'action' => $action, 'object' => $object,
                'detail' => $detail, 'created_at' => '2026-11-02 13:34:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->activityDb->close();
        parent::tearDown();
    }

    public function testSearchFindsWordsInDifferentFieldsAndKeepsAccountAndModuleFilters(): void
    {
        $results = $this->logs->filtered("  chromebook\tDIPERBARUI ", 'Barang', userId: 1)->find();
        self::assertSame(['UNT-000003'], array_column($results, 'object'));
        self::assertSame([1], array_map('intval', array_column($results, 'user_id')));
        self::assertSame([], $this->logs->filtered('chromebook DIPERBARUI', 'Peminjaman', userId: 1)->find());
    }

    public function testAuditSearchStillCombinesDateAndModuleAndSortsEqualTimestamps(): void
    {
        $results = $this->logs->filtered('chromebook', 'Barang', '2026-11-02', '2026-11-02')->find();
        self::assertSame(['UNT-000004', 'UNT-000003'], array_column($results, 'object'));
        self::assertSame([], $this->logs->filtered('chromebook', 'Barang', '2026-11-03')->find());
    }

    public function testPercentIsLiteralAndZeroIsSearchable(): void
    {
        self::assertSame(['UNT-000005'], array_column($this->logs->filtered('100%')->find(), 'object'));
        self::assertCount(5, $this->logs->filtered('0')->find());
        self::assertSame([], $this->logs->filtered('0 tidakada')->find());
    }
}
