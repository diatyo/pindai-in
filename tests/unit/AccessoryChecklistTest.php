<?php

use App\Controllers\Petugas\Accessories;
use App\Controllers\Petugas\Loans;
use App\Database\Migrations\AddAccessoryChecklists;
use App\Libraries\AccessoryChecklist;
use App\Models\ItemModel;
use App\Models\LoanModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\Services;

require_once APPPATH . 'Database/Migrations/2026-11-02-000017_AddAccessoryChecklists.php';
require_once APPPATH . 'Database/Migrations/2026-11-03-000019_CreateDocumentSequences.php';

final class AccessoryChecklistTest extends CIUnitTestCase
{
    private BaseConnection $accessoryDb;
    private AccessoryChecklist $lists;
    private ItemModel $items;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessoryDb = Database::connect();
        if ($this->accessoryDb->DBDriver !== 'SQLite3' || $this->accessoryDb->database !== ':memory:') {
            throw new RuntimeException('Accessory tests must use a private in-memory database.');
        }
        foreach (['loan_accessory_events', 'loan_accessories', 'item_accessories'] as $table) { Database::forge()->dropTable($table, true); }
        $schemas = [
            'items' => 'item_code TEXT, item_name TEXT, brand TEXT, model TEXT, physical_photo TEXT, status TEXT, condition TEXT, is_active INTEGER',
            'loans' => 'loan_number TEXT UNIQUE, item_id INTEGER, borrower_id INTEGER, borrowed_at TEXT, due_at TEXT, purpose TEXT, usage_location TEXT, condition_out TEXT, completeness_out TEXT, processed_by INTEGER, returned_at TEXT, condition_in TEXT, completeness_in TEXT, return_photo TEXT, return_notes TEXT, returned_by INTEGER',
            'borrowers' => 'name TEXT, type TEXT, identity_number TEXT, is_active INTEGER',
            'damage_reports' => 'item_id INTEGER, status TEXT, damage_level TEXT',
            'activity_logs' => 'user_id INTEGER, user_name TEXT, role TEXT, action TEXT, module TEXT, object TEXT, detail TEXT, ip_address TEXT',
            'item_status_history' => 'item_id INTEGER, status_from TEXT, status_to TEXT, note TEXT, loan_id INTEGER, damage_report_id INTEGER, changed_by INTEGER, changed_by_name TEXT',
            'settings' => 'setting_key TEXT, setting_value TEXT',
        ];
        foreach ($schemas as $table => $columns) {
            Database::forge()->dropTable($table, true);
            $this->accessoryDb->query('CREATE TABLE ' . $this->accessoryDb->escapeIdentifiers($this->accessoryDb->prefixTable($table)) . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, ' . $columns . ', created_at TEXT, updated_at TEXT)');
        }
        (new AddAccessoryChecklists(Database::forge()))->up();
        (new \App\Database\Migrations\CreateDocumentSequences(Database::forge()))->up();
        $this->accessoryDb->resetTransStatus();
        $this->accessoryDb->table('items')->insertBatch([
            ['id' => 1, 'item_code' => 'ACC-001', 'item_name' => 'Chromebook', 'status' => 'available', 'condition' => 'good', 'is_active' => 1],
            ['id' => 2, 'item_code' => 'ACC-002', 'item_name' => 'Proyektor', 'status' => 'available', 'condition' => 'good', 'is_active' => 1],
        ]);
        $this->accessoryDb->table('borrowers')->insert(['id' => 1, 'name' => 'Guru Uji', 'type' => 'teacher', 'is_active' => 1]);
        $this->lists = new AccessoryChecklist($this->accessoryDb);
        $this->items = model(ItemModel::class);
        helper(['app', 'activity', 'form', 'url']);
        Services::resetSingle('renderer');
        session()->set(['is_logged_in' => true, 'role' => 'petugas', 'user_id' => 1, 'full_name' => 'Petugas Uji']);
    }

    private function template(int $quantity = 1, bool $required = true): array
    {
        $this->items->withLockedItem(1, fn () => $this->lists->replaceTemplate(1, [
            ['name' => 'Charger', 'quantity' => (string) $quantity, 'is_required' => $required ? '1' : '0'],
            ['name' => 'Tas', 'quantity' => '1'],
        ]));
        return $this->lists->template(1);
    }

    protected function tearDown(): void
    {
        foreach (['loan_accessory_events', 'loan_accessories', 'item_accessories', 'document_sequences'] as $table) {
            Database::forge()->dropTable($table, true);
        }
        parent::tearDown();
    }

    private function checkoutPost(array $template, array $extra = []): array
    {
        return ['accessory_checklist' => '1', 'accessory_confirmed' => '1',
            'accessory_template_hash' => $this->lists->templateHash(1),
            'accessory_sent' => [(string) $template[0]['id'] => '1'], 'extra_accessories' => $extra];
    }

    private function loan(array $post, bool $returned = false): int
    {
        return $this->items->withLockedItem(1, function ($item) use ($post, $returned) {
            $checkout = $this->lists->checkout(1, $post);
            $id = model(LoanModel::class)->insert(['loan_number' => 'ACC-' . bin2hex(random_bytes(5)), 'item_id' => 1,
                'borrower_id' => 1, 'borrowed_at' => date('Y-m-d H:i:s'), 'due_at' => date('Y-m-d H:i:s', time() + 3600),
                'returned_at' => $returned ? date('Y-m-d H:i:s') : null, 'accessory_checklist' => $checkout['enabled']]);
            $this->lists->saveCheckout((int) $id, $checkout['rows']);
            $this->items->synchronizeState($item);
            return (int) $id;
        });
    }

    private function post(string $class, string $method, array $post, array $args = [])
    {
        Services::resetSingle('validation'); Services::resetSingle('response'); Services::resetSingle('redirectresponse');
        session()->remove(['error', 'success', 'errors']);
        $request = new IncomingRequest(config('App'), new SiteURI(config('App'), 'petugas/loans'), null, new UserAgent());
        $request->setMethod('POST')->setGlobal('post', $post)->setGlobal('request', $post);
        Services::injectMock('request', $request);
        $controller = new $class();
        $controller->initController($request, service('response'), service('logger'));
        return $controller->$method(...$args);
    }

    public function testOptionalNotSentIsNotRequestedOnReturnAndExtrasDoNotChangeMaster(): void
    {
        $template = $this->template();
        $id = $this->loan($this->checkoutPost($template, [['name' => 'Kabel HDMI', 'quantity' => '2', 'sent' => '1']]));
        self::assertSame(['Charger', 'Kabel HDMI'], array_column($this->lists->forLoan($id), 'name'));
        self::assertSame(['Charger', 'Tas'], array_column($this->lists->template(1), 'name'));
        self::assertSame(1, (int) model(LoanModel::class)->find($id)->accessory_checklist);
        self::assertSame('borrowed', $this->items->find(1)->status);
    }

    public function testMasterEditsNeverChangeHistoricalNamesQuantitiesOrRequirements(): void
    {
        $template = $this->template(2);
        $id = $this->loan($this->checkoutPost($template));
        $before = $this->lists->forLoan($id);
        $this->lists->replaceTemplate(1, [['name' => 'Adaptor baru', 'quantity' => '1']]);
        self::assertSame($before, $this->lists->forLoan($id));
        self::assertSame(2, (int) $before[0]['quantity']);
        self::assertSame(1, (int) $before[0]['is_required']);
    }

    public function testRequiredAccessoryCannotBeOmitted(): void
    {
        $post = $this->checkoutPost($this->template()); $post['accessory_sent'] = [];
        $this->expectException(DomainException::class);
        $this->lists->checkout(1, $post);
    }

    public function testStaleFormCannotUseNewTemplateSilently(): void
    {
        $post = $this->checkoutPost($this->template());
        $this->lists->replaceTemplate(1, [['name' => 'Charger pengganti', 'quantity' => '1']]);
        $this->expectException(DomainException::class);
        $this->lists->checkout(1, $post);
    }

    public function testForeignAccessoryCannotBeInjected(): void
    {
        $post = $this->checkoutPost($this->template());
        $this->lists->replaceTemplate(2, [['name' => 'Remote', 'quantity' => '1']]);
        $post['accessory_sent'][(string) $this->lists->template(2)[0]['id']] = '1';
        $this->expectException(DomainException::class);
        $this->lists->checkout(1, $post);
    }

    public function testDuplicateExtraCannotInflateAnExistingAccessory(): void
    {
        $post = $this->checkoutPost($this->template(), [['name' => ' charger ', 'quantity' => '1', 'sent' => '1']]);
        $this->expectException(DomainException::class);
        $this->lists->checkout(1, $post);
    }

    public function testUnconfirmedExtraCannotBeSaved(): void
    {
        $post = $this->checkoutPost($this->template(), [['name' => 'Kabel HDMI', 'quantity' => '1']]);
        $this->expectException(DomainException::class);
        $this->lists->checkout(1, $post);
    }

    public function testLegacyClientCannotBypassAnExistingMasterChecklist(): void
    {
        $this->template();
        $this->expectException(DomainException::class);
        $this->lists->checkout(1, []);
    }

    public function testOldLoanWithoutChecklistKeepsLegacyFlag(): void
    {
        $id = $this->loan([]);
        self::assertSame(0, (int) model(LoanModel::class)->find($id)->accessory_checklist);
        self::assertSame([], $this->lists->forLoan($id));
    }

    public function testMissingRequiredReturnHoldsUnitAndPartialResolutionPreservesOriginalInspection(): void
    {
        $template = $this->template(3);
        $id = $this->loan($this->checkoutPost($template));
        $row = $this->lists->forLoan($id)[0];
        $this->items->withLockedItem(1, function ($item) use ($id, $row) {
            $inspection = $this->lists->inspectReturn($id, ['accessory_return_confirmed' => '1', 'accessory_returned' => [$row['id'] => '1']]);
            self::assertSame('tidak_lengkap', $inspection['completeness']);
            $this->lists->saveReturn($inspection['rows']);
            model(LoanModel::class)->update($id, ['returned_at' => date('Y-m-d H:i:s'), 'completeness_in' => $inspection['completeness']]);
            $this->items->synchronizeState($item);
        });
        self::assertSame('incomplete', $this->items->find(1)->status);
        foreach (['returned', 'replacement'] as $kind) {
            $post = ['quantity' => '1', 'kind' => $kind, 'note' => 'Diterima dan diperiksa', 'request_key' => bin2hex(random_bytes(16))];
            $this->post(Accessories::class, 'resolve', $post, [$id, (int) $row['id']]);
            self::assertNull(session()->getFlashdata('error'));
            // Repeated submissions must never count a receipt twice.
            $this->post(Accessories::class, 'resolve', $post, [$id, (int) $row['id']]);
        }
        self::assertSame('available', $this->items->find(1)->status);
        self::assertSame(3, (int) $this->lists->forLoan($id)[0]['quantity_returned']);
        self::assertCount(3, $this->lists->events($id));
        self::assertSame('tidak_lengkap', model(LoanModel::class)->find($id)->completeness_in);
    }

    public function testMissingOptionalAccessoryDoesNotBlockUnit(): void
    {
        $id = $this->loan($this->checkoutPost($this->template(1, false)), true);
        self::assertSame('available', $this->items->find(1)->status);
        self::assertSame(1, model(\App\Models\LoanAccessoryModel::class)->pending()->countAllResults());
    }

    public function testResolutionCannotReleaseArchivedDamagedOrCurrentlyBorrowedUnit(): void
    {
        foreach (['disabled', 'damaged', 'borrowed'] as $expected) {
            $id = $this->loan($this->checkoutPost($this->template()), true);
            $accessoryId = (int) $this->lists->forLoan($id)[0]['id'];
            if ($expected === 'disabled') { $this->accessoryDb->table('items')->where('id', 1)->update(['is_active' => 0, 'status' => 'disabled']); }
            if ($expected === 'damaged') { $this->accessoryDb->table('damage_reports')->insert(['item_id' => 1, 'status' => 'reported', 'damage_level' => 'minor_damage']); }
            if ($expected === 'borrowed') { $this->accessoryDb->table('loans')->insert(['loan_number' => 'ACTIVE', 'item_id' => 1]); }
            $this->post(Accessories::class, 'resolve', ['quantity' => '1', 'kind' => 'returned', 'note' => 'Diterima', 'request_key' => bin2hex(random_bytes(16))], [$id, $accessoryId]);
            self::assertSame($expected, $this->items->find(1)->status);
            $this->accessoryDb->table('items')->where('id', 1)->update(['is_active' => 1, 'status' => 'available', 'condition' => 'good']);
            $this->accessoryDb->table('damage_reports')->emptyTable();
        }
    }

    public function testReturnCannotInjectAnotherLoansAccessory(): void
    {
        $id = $this->loan($this->checkoutPost($this->template()));
        $this->expectException(DomainException::class);
        $this->lists->inspectReturn($id, ['accessory_return_confirmed' => '1', 'accessory_returned' => ['9999' => '1']]);
    }

    public function testTooManyReturnedCannotBeSaved(): void
    {
        $id = $this->loan($this->checkoutPost($this->template()));
        $row = $this->lists->forLoan($id)[0];
        $this->expectException(DomainException::class);
        $this->lists->inspectReturn($id, ['accessory_return_confirmed' => '1', 'accessory_returned' => [$row['id'] => '2']]);
    }

    public function testRealLoanControllerSavesChecklistAndRejectsReusedForm(): void
    {
        $post = $this->checkoutPost($this->template(), [['name' => 'Kabel HDMI', 'quantity' => '1', 'sent' => '1']]) + [
            'item_id' => '1', 'borrower_id' => '1', 'due_date' => date('Y-m-d', time() + 86400), 'due_time' => '16:00',
            'purpose' => 'Pembelajaran', 'usage_location' => 'VIII A', 'condition_out' => 'good', 'completeness_out' => 'lengkap'];
        $this->post(Loans::class, 'store', $post);
        self::assertNull(session()->getFlashdata('error'));
        self::assertSame(1, $this->accessoryDb->table('loans')->countAllResults());
        self::assertCount(2, $this->lists->forLoan(1));
        $this->post(Loans::class, 'store', $post);
        self::assertSame(1, $this->accessoryDb->table('loans')->countAllResults());
    }

    public function testMissingReturnCannotBeHiddenByForgingCompleteSummary(): void
    {
        $id = $this->loan($this->checkoutPost($this->template()));
        $this->post(Loans::class, 'returnStore', ['condition_in' => 'good', 'completeness_in' => 'lengkap',
            'accessory_return_confirmed' => '1', 'return_notes' => 'Charger belum kembali'], [$id]);
        self::assertSame('Foto pemeriksaan wajib diunggah untuk barang yang tidak lengkap.', session()->getFlashdata('error'));
        self::assertNull(model(LoanModel::class)->find($id)->returned_at);
        self::assertSame('borrowed', $this->items->find(1)->status);
    }

    public function testFailedSnapshotWriteRollsBackLoanAndUnit(): void
    {
        $post = $this->checkoutPost($this->template());
        $this->accessoryDb->query("CREATE TRIGGER db_accessory_fail BEFORE INSERT ON db_loan_accessories BEGIN SELECT RAISE(FAIL, 'fixture failure'); END");
        try { $this->loan($post); self::fail('Write failure must abort.'); } catch (Throwable $e) {
            self::assertSame(0, $this->accessoryDb->table('loans')->countAllResults());
            self::assertSame('available', $this->items->find(1)->status);
        }
    }

    public function testRepeatMigrationPreservesSnapshotsAndCounters(): void
    {
        $id = $this->loan($this->checkoutPost($this->template()));
        $before = $this->lists->forLoan($id);
        (new AddAccessoryChecklists(Database::forge()))->up();
        self::assertSame($before, $this->lists->forLoan($id));
        self::assertSame(1, (int) model(LoanModel::class)->find($id)->accessory_checklist);
    }

    public function testInvalidMasterInputNeverDeletesTheExistingList(): void
    {
        $before = $this->template();
        $invalid = [
            [['name' => '', 'quantity' => '1']], [['name' => str_repeat('A', 81), 'quantity' => '1']],
            [['name' => 'Charger', 'quantity' => '0']], [['name' => 'Charger', 'quantity' => '100']],
            [['name' => 'Charger', 'quantity' => '1.5']], [['name' => 'Charger', 'quantity' => ['1']]],
            [['name' => 'Tas', 'quantity' => '1'], ['name' => ' tas ', 'quantity' => '1']],
            array_fill(0, 31, ['name' => 'Tas', 'quantity' => '1']),
        ];
        foreach ($invalid as $input) {
            try { $this->lists->replaceTemplate(1, $input); self::fail('Invalid master must be rejected.'); }
            catch (DomainException $e) { self::assertSame($before, $this->lists->template(1)); }
        }
    }

    public function testResolvingOneDebtCannotClearAnotherTransactionsDebt(): void
    {
        $post = $this->checkoutPost($this->template());
        $one = $this->loan($post, true); $two = $this->loan($post, true);
        $row = $this->lists->forLoan($one)[0];
        $this->post(Accessories::class, 'resolve', ['quantity' => '1', 'kind' => 'returned', 'note' => 'Diterima', 'request_key' => bin2hex(random_bytes(16))], [$one, (int) $row['id']]);
        self::assertSame('incomplete', $this->items->find(1)->status);
        self::assertSame(0, (int) $this->lists->forLoan($two)[0]['quantity_returned']);
    }

    public function testForeignTransactionCannotResolveAnotherAccessory(): void
    {
        $post = $this->checkoutPost($this->template());
        $one = $this->loan($post, true); $two = $this->loan($post, true);
        $row = $this->lists->forLoan($one)[0];
        $this->post(Accessories::class, 'resolve', ['quantity' => '1', 'kind' => 'returned', 'note' => 'Diterima', 'request_key' => bin2hex(random_bytes(16))], [$two, (int) $row['id']]);
        self::assertNotEmpty(session()->getFlashdata('error'));
        self::assertSame(0, (int) $this->lists->forLoan($one)[0]['quantity_returned']);
    }

    public function testFailedFollowupAuditRollsBackReceiptAndItemState(): void
    {
        $id = $this->loan($this->checkoutPost($this->template()), true);
        $row = $this->lists->forLoan($id)[0];
        $this->accessoryDb->query("CREATE TRIGGER db_receipt_fail BEFORE INSERT ON db_loan_accessory_events BEGIN SELECT RAISE(FAIL, 'fixture failure'); END");
        $this->post(Accessories::class, 'resolve', ['quantity' => '1', 'kind' => 'returned', 'note' => 'Diterima', 'request_key' => bin2hex(random_bytes(16))], [$id, (int) $row['id']]);
        self::assertNotEmpty(session()->getFlashdata('error'));
        self::assertSame(0, (int) $this->lists->forLoan($id)[0]['quantity_returned']);
        self::assertSame('incomplete', $this->items->find(1)->status);
    }
}
