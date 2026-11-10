<?php

use App\Controllers\Petugas\Damages;
use App\Controllers\Petugas\Handling;
use App\Controllers\Petugas\Items;
use App\Controllers\Petugas\Loans;
use App\Models\ItemModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;

require_once APPPATH . 'Database/Migrations/2026-11-02-000017_AddAccessoryChecklists.php';
require_once APPPATH . 'Database/Migrations/2026-11-03-000019_CreateDocumentSequences.php';

/** Actual controllers/models against a private in-memory database, never the school DB. */
final class ItemStateTest extends CIUnitTestCase
{
    private BaseConnection $stateDb;
    private ItemModel $items;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateDb = Database::connect();
        if ($this->stateDb->DBDriver !== 'SQLite3' || $this->stateDb->database !== ':memory:') {
            throw new RuntimeException('ItemStateTest requires an isolated SQLite :memory: database.');
        }
        foreach (['loan_accessory_events', 'loan_accessories', 'item_accessories', 'document_sequences'] as $table) {
            Database::forge()->dropTable($table, true);
        }
        $schemas = [
            'items' => 'item_code TEXT UNIQUE, item_name TEXT, category TEXT, brand TEXT, model TEXT, serial_number TEXT UNIQUE, serial_status TEXT, acquisition_date TEXT, location TEXT, specifications TEXT, status TEXT, condition TEXT, is_active INTEGER, physical_photo TEXT, serial_photo TEXT, qr_filename TEXT',
            'loans' => 'loan_number TEXT UNIQUE, item_id INTEGER, borrower_id INTEGER, borrowed_at TEXT, due_at TEXT, purpose TEXT, usage_location TEXT, condition_out TEXT, completeness_out TEXT, processed_by INTEGER, returned_at TEXT, condition_in TEXT, completeness_in TEXT, return_photo TEXT, return_notes TEXT, returned_by INTEGER',
            'damage_reports' => 'report_number TEXT UNIQUE, item_id INTEGER, loan_id INTEGER, discovered_at TEXT, damage_level TEXT, description TEXT, damage_photo TEXT, notes TEXT, status TEXT, reported_by INTEGER',
            'handling_actions' => 'damage_report_id INTEGER, action_date TEXT, action_description TEXT, executor_name TEXT, result_condition TEXT, status TEXT',
            'item_status_history' => 'item_id INTEGER, status_from TEXT, status_to TEXT, note TEXT, loan_id INTEGER, damage_report_id INTEGER, changed_by INTEGER, changed_by_name TEXT',
            'activity_logs' => 'user_id INTEGER, user_name TEXT, role TEXT, action TEXT, module TEXT, object TEXT, detail TEXT, ip_address TEXT',
            'borrowers' => 'name TEXT, type TEXT, identity_number TEXT, is_active INTEGER',
            'item_references' => 'type TEXT, name TEXT, name_key TEXT, sort_order INTEGER, is_active INTEGER',
        ];
        foreach ($schemas as $table => $columns) {
            $name = $this->stateDb->escapeIdentifiers($this->stateDb->prefixTable($table));
            $this->stateDb->query('DROP TABLE IF EXISTS ' . $name);
            $this->stateDb->query('CREATE TABLE ' . $name . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, ' . $columns . ', created_at TEXT, updated_at TEXT)');
        }
        (new \App\Database\Migrations\AddAccessoryChecklists(Database::forge()))->up();
        (new \App\Database\Migrations\CreateDocumentSequences(Database::forge()))->up();
        $this->stateDb->resetTransStatus();
        foreach (\App\Models\ItemReferenceModel::DEFAULTS as $type => $names) {
            foreach ($names as $order => $name) {
                $this->stateDb->table('item_references')->insert(['type' => $type, 'name' => $name,
                    'name_key' => \App\Models\ItemReferenceModel::nameKey($name), 'sort_order' => $order, 'is_active' => 1]);
            }
        }
        $this->items = model(ItemModel::class);
        $this->stateDb->table('items')->insert([
            'id' => 1, 'item_code' => 'TEST-001', 'item_name' => 'Unit uji', 'is_active' => 1, 'serial_number' => 'SN-001',
            'condition' => 'minor_damage', 'status' => 'damaged', 'physical_photo' => 'saved.webp', 'serial_photo' => 'serial.webp',
        ]);
        $this->stateDb->table('borrowers')->insert(['id' => 1, 'name' => 'Peminjam uji', 'type' => 'student', 'is_active' => 1]);
        session()->set(['is_logged_in' => true, 'user_id' => 1, 'full_name' => 'Petugas uji', 'role' => 'petugas']);
    }

    public function testDocumentNumbersContinueAfterHighestLegacyNumberAndRollBackWithSave(): void
    {
        $prefix = 'PMJ-' . date('Ym') . '-';
        foreach (['001', '009', 'RUSAK'] as $suffix) {
            $this->stateDb->table('loans')->insert(['loan_number' => $prefix . $suffix]);
        }
        $numbers = new \App\Libraries\DocumentNumbers($this->stateDb);
        self::assertTrue($this->stateDb->transBegin());
        self::assertSame($prefix . '010', $numbers->next('loan'));
        self::assertTrue($this->stateDb->transRollback());

        self::assertTrue($this->stateDb->transBegin());
        self::assertSame($prefix . '010', $numbers->next('loan'));
        self::assertTrue($this->stateDb->transCommit());
        self::assertSame(3, $this->stateDb->table('loans')->countAllResults());
    }

    private function post(string $controller, string $method, array $data, array $args = [])
    {
        session()->remove(['success', 'error', 'errors']);
        Services::resetSingle('validation');
        $request = new IncomingRequest(config('App'), new SiteURI(config('App'), 'petugas/test'), null, new UserAgent());
        $request->setMethod('POST');
        $request->setGlobal('post', $data);
        $request->setGlobal('request', $data);
        Services::injectMock('request', $request);
        $instance = new $controller();
        $instance->initController($request, service('response'), service('logger'));
        return $instance->$method(...$args);
    }

    private function report(string $status = 'reported', string $level = 'minor_damage', int $itemId = 1): int
    {
        $this->stateDb->table('damage_reports')->insert([
            'item_id' => $itemId, 'status' => $status, 'damage_level' => $level,
            'report_number' => 'TEST-' . bin2hex(random_bytes(4)),
        ]);
        return (int) $this->stateDb->insertID();
    }

    private function loan(int $itemId = 1): int
    {
        $this->stateDb->table('loans')->insert(['item_id' => $itemId, 'borrower_id' => 1, 'returned_at' => null]);
        return (int) $this->stateDb->insertID();
    }

    private function complete(int $reportId, string $condition = 'good'): void
    {
        $this->post(Handling::class, 'store', [
            'damage_report_id' => (string) $reportId, 'status' => 'completed', 'result_condition' => $condition,
            'action_date' => '2026-11-02', 'action_description' => 'Pemeriksaan selesai', 'executor_name' => 'Teknisi uji',
        ]);
    }

    public static function completionCases(): array
    {
        return [
            'all resolved' => [null, false, true, 'good', 'available', 'good'],
            'active loan' => [null, true, true, 'good', 'borrowed', 'good'],
            'another reported defect' => ['reported', false, true, 'good', 'damaged', 'major_damage'],
            'another repair in progress' => ['in_handling', false, true, 'good', 'in_handling', 'major_damage'],
            'another unrepairable defect' => ['unrepairable', false, true, 'good', 'damaged', 'major_damage'],
            'archived' => [null, false, false, 'good', 'disabled', 'good'],
            'not fully repaired' => [null, false, true, 'minor_damage', 'damaged', 'minor_damage'],
            'loan and another defect' => ['reported', true, true, 'good', 'borrowed', 'major_damage'],
        ];
    }

    #[DataProvider('completionCases')]
    public function testCompletingOneReportRespectsAllUnitFacts(?string $other, bool $loan, bool $active, string $result, string $status, string $condition): void
    {
        $id = $this->report();
        if ($other) {
            $this->report($other, 'major_damage');
        }
        if ($loan) {
            $this->loan();
        }
        $this->items->update(1, ['is_active' => (int) $active, 'status' => $active ? 'damaged' : 'disabled']);
        $this->complete($id, $result);
        self::assertNotNull(session()->getFlashdata('success'));
        self::assertSame($status, $this->items->find(1)->status);
        self::assertSame($condition, $this->items->find(1)->condition);
        self::assertSame('completed', $this->stateDb->table('damage_reports')->where('id', $id)->get()->getRow()->status);
        self::assertSame(1, $this->stateDb->table('handling_actions')->countAllResults());
        self::assertSame($status, $this->stateDb->table('item_status_history')->get()->getRow()->status_to);
    }

    public function testClosedReportCannotBeSubmittedAgain(): void
    {
        foreach (['completed', 'unrepairable'] as $status) {
            $id = $this->report($status);
            $this->complete($id);
            self::assertNotNull(session()->getFlashdata('error'));
            self::assertSame($status, $this->stateDb->table('damage_reports')->where('id', $id)->get()->getRow()->status);
        }
        self::assertSame(0, $this->stateDb->table('handling_actions')->countAllResults());
        self::assertSame('damaged', $this->items->find(1)->status);
    }

    public function testFailedAuditWriteRollsBackActionReportAndItem(): void
    {
        Services::injectMock('logger', $this->createMock(\Psr\Log\LoggerInterface::class));
        $id = $this->report();
        $logs = $this->stateDb->escapeIdentifiers($this->stateDb->prefixTable('activity_logs'));
        $this->stateDb->query("CREATE TRIGGER fail_audit BEFORE INSERT ON $logs BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $this->complete($id);
        self::assertNotNull(session()->getFlashdata('error'));
        self::assertNull(session()->getFlashdata('success'));
        self::assertSame(0, $this->stateDb->table('handling_actions')->countAllResults());
        self::assertSame(0, $this->stateDb->table('item_status_history')->countAllResults());
        self::assertSame('reported', $this->stateDb->table('damage_reports')->where('id', $id)->get()->getRow()->status);
        self::assertSame('minor_damage', $this->items->find(1)->condition);
        self::assertSame('damaged', $this->items->find(1)->status);
    }

    public function testDamageWhileBorrowedKeepsLoanAndWorstCondition(): void
    {
        $this->loan();
        $this->report('reported', 'major_damage');
        $this->post(Damages::class, 'store', [
            'item_id' => '1', 'discovered_at' => '2026-11-02 09:00:00',
            'damage_level' => 'minor_damage', 'description' => 'Kerusakan lain',
        ]);
        self::assertNotNull(session()->getFlashdata('success'));
        self::assertSame('borrowed', $this->items->find(1)->status);
        self::assertSame('major_damage', $this->items->find(1)->condition);
    }

    public function testDamageCannotLinkLoanOfAnotherItem(): void
    {
        $loanId = $this->loan(2);
        $this->post(Damages::class, 'store', [
            'item_id' => '1', 'loan_id' => (string) $loanId, 'discovered_at' => '2026-11-02 09:00:00',
            'damage_level' => 'minor_damage', 'description' => 'Salah transaksi',
        ]);
        self::assertNotNull(session()->getFlashdata('error'));
        self::assertSame(0, $this->stateDb->table('damage_reports')->countAllResults());
    }

    public function testGoodReturnDoesNotClearOtherDamage(): void
    {
        $id = $this->loan();
        $this->report('in_handling', 'major_damage');
        $this->items->update(1, ['status' => 'borrowed']);
        $this->post(Loans::class, 'returnStore', ['condition_in' => 'good', 'completeness_in' => 'lengkap'], [$id]);
        self::assertNotNull(session()->getFlashdata('success'));
        self::assertNotNull($this->stateDb->table('loans')->where('id', $id)->get()->getRow()->returned_at);
        self::assertSame('in_handling', $this->items->find(1)->status);
        self::assertSame('major_damage', $this->items->find(1)->condition);
    }

    public function testStaleAvailableFlagDoesNotAllowNewLoan(): void
    {
        $this->items->update(1, ['status' => 'available', 'condition' => 'good']);
        $this->report();
        $this->post(Loans::class, 'store', [
            'item_id' => '1', 'borrower_id' => '1', 'due_date' => date('Y-m-d', time() + 86400), 'due_time' => '16:00',
            'purpose' => 'Pembelajaran', 'usage_location' => 'Kelas', 'condition_out' => 'good', 'completeness_out' => 'lengkap',
        ]);
        self::assertNotNull(session()->getFlashdata('error'));
        self::assertSame(0, $this->stateDb->table('loans')->countAllResults());
    }

    public function testNormalLoanAndReturnAndRepeatedSubmissions(): void
    {
        $this->items->update(1, ['status' => 'available', 'condition' => 'good']);
        $data = [
            'item_id' => '1', 'borrower_id' => '1', 'due_date' => date('Y-m-d', time() + 86400), 'due_time' => '16:00',
            'purpose' => 'Pembelajaran', 'usage_location' => 'Kelas', 'condition_out' => 'major_damage', 'completeness_out' => 'lengkap',
        ];
        $this->post(Loans::class, 'store', $data);
        self::assertNotNull(session()->getFlashdata('success'));
        self::assertSame('borrowed', $this->items->find(1)->status);
        $loan = $this->stateDb->table('loans')->get()->getRow();
        self::assertSame('good', $loan->condition_out); // Ignore manipulated hidden input.
        $this->post(Loans::class, 'store', $data);
        self::assertNotNull(session()->getFlashdata('error'));
        self::assertSame(1, $this->stateDb->table('loans')->countAllResults());
        $this->post(Loans::class, 'returnStore', ['condition_in' => 'good', 'completeness_in' => 'lengkap'], [(int) $loan->id]);
        self::assertNotNull(session()->getFlashdata('success'));
        self::assertSame('available', $this->items->find(1)->status);
        $historyCount = $this->stateDb->table('item_status_history')->countAllResults();
        $this->post(Loans::class, 'returnStore', ['condition_in' => 'good', 'completeness_in' => 'lengkap'], [(int) $loan->id]);
        self::assertNotNull(session()->getFlashdata('error'));
        self::assertSame($historyCount, $this->stateDb->table('item_status_history')->countAllResults());
    }

    public function testLastReportCompletionRestoresAvailability(): void
    {
        $first = $this->report();
        $second = $this->report('in_handling', 'major_damage');
        $this->complete($first);
        self::assertSame('in_handling', $this->items->find(1)->status);
        $this->complete($second);
        self::assertNotNull(session()->getFlashdata('success'));
        self::assertSame('available', $this->items->find(1)->status);
        self::assertSame('good', $this->items->find(1)->condition);
    }

    public function testArchiveAndRestoreRespectAllReportsAndActiveLoan(): void
    {
        $this->report('in_handling', 'major_damage');
        $this->report('reported'); // A newer report must not hide the ongoing repair.
        $this->post(Items::class, 'archive', [], [1]);
        self::assertSame('disabled', $this->items->find(1)->status);
        $this->post(Items::class, 'restore', [], [1]);
        self::assertSame('in_handling', $this->items->find(1)->status);
        $this->loan();
        $this->post(Items::class, 'archive', [], [1]);
        $this->post(Items::class, 'restore', [], [1]);
        self::assertSame('borrowed', $this->items->find(1)->status);
    }

    public function testAccessoryOnlyEditsHaveBeforeAfterAuditDetails(): void
    {
        $payload = [
            'category' => 'Elektronik', 'item_name' => 'Unit uji', 'serial_number' => 'SN-001',
            'brand' => 'Merek', 'model' => 'Model', 'acquisition_date' => '2026-01-01',
            'specifications' => 'Spesifikasi', 'location' => 'Ruang TU', 'condition' => 'good',
            'accessory_editor' => '1',
        ];
        $lists = new \App\Libraries\AccessoryChecklist($this->stateDb);
        foreach ([
            [['name' => 'Charger', 'quantity' => '1', 'is_required' => '1']],
            [['name' => 'Kabel HDMI', 'quantity' => '2', 'is_required' => '0']],
            [],
        ] as $index => $accessories) {
            $before = $lists->template(1);
            $this->post(Items::class, 'update', $payload + ['accessories' => $accessories], [1]);
            self::assertNotNull(session()->getFlashdata('success'));
            $log = $this->stateDb->table('activity_logs')->orderBy('id', 'DESC')->get()->getRow();
            self::assertSame('barang_diperbarui', $log->action);
            self::assertSame('TEST-001', $log->object);
            self::assertSame('1', (string) $log->user_id);
            self::assertStringContainsString('Kelengkapan sebelumnya', $log->detail);
            self::assertStringContainsString('Kelengkapan sekarang', $log->detail);
            if ($index === 0) {
                self::assertStringContainsString('Charger × 1 (wajib)', $log->detail);
            } elseif ($index === 1) {
                self::assertStringContainsString('Charger × 1 (wajib)', $log->detail);
                self::assertStringContainsString('Kabel HDMI × 2 (opsional)', $log->detail);
                $snapshot = $lists->template(1);
                $this->post(Items::class, 'update', $payload + ['accessories' => $accessories], [1]);
                self::assertSame($snapshot, $lists->template(1));
                $unchanged = $this->stateDb->table('activity_logs')->orderBy('id', 'DESC')->get()->getRow();
                self::assertStringNotContainsString('Kelengkapan sebelumnya', $unchanged->detail);
            } else {
                self::assertNotEmpty($before);
                self::assertSame([], $lists->template(1));
                self::assertStringContainsString('(tidak ada kelengkapan)', $log->detail);
            }
        }
    }

    public function testFailedAccessoryAuditRollsBackTheTemplate(): void
    {
        $lists = new \App\Libraries\AccessoryChecklist($this->stateDb);
        $lists->replaceTemplate(1, [['name' => 'Charger', 'quantity' => '1']]);
        $before = $lists->template(1);
        $logs = $this->stateDb->escapeIdentifiers($this->stateDb->prefixTable('activity_logs'));
        $this->stateDb->query("CREATE TRIGGER fail_accessory_audit BEFORE INSERT ON $logs BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $this->post(Items::class, 'update', [
            'category' => 'Elektronik', 'item_name' => 'Unit uji', 'serial_number' => 'SN-001',
            'brand' => 'Merek', 'model' => 'Model', 'acquisition_date' => '2026-01-01',
            'specifications' => 'Spesifikasi', 'location' => 'Ruang TU', 'condition' => 'good',
            'accessory_editor' => '1', 'accessories' => [['name' => 'Remote', 'quantity' => '2']],
        ], [1]);
        self::assertNull(session()->getFlashdata('success'));
        self::assertSame($before, $lists->template(1));
        self::assertSame(0, $this->stateDb->table('activity_logs')->countAllResults());
    }

    public function testEditingConditionCannotBypassOpenDamageOrActiveLoan(): void
    {
        $this->loan();
        $this->report('reported', 'major_damage');
        $this->post(Items::class, 'update', [
            'category' => 'Elektronik', 'item_name' => 'Unit uji', 'serial_number' => 'SN-001',
            'brand' => 'Merek', 'model' => 'Model', 'acquisition_date' => '2026-01-01',
            'specifications' => 'Spesifikasi', 'location' => 'Ruang TU', 'condition' => 'good', 'status' => 'available',
        ], [1]);
        self::assertNotNull(session()->getFlashdata('success'));
        self::assertSame('borrowed', $this->items->find(1)->status);
        self::assertSame('major_damage', $this->items->find(1)->condition);
    }
}
