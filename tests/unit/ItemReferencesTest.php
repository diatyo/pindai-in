<?php

use App\Controllers\Admin\References;
use App\Controllers\Petugas\Items;
use App\Database\Migrations\CreateItemReferences;
use App\Models\ItemReferenceModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\Services;

require_once APPPATH . 'Database/Migrations/2026-11-02-000015_CreateItemReferences.php';

final class ItemReferencesTest extends CIUnitTestCase
{
    private BaseConnection $referenceDb;
    private ItemReferenceModel $references;

    protected function setUp(): void
    {
        parent::setUp();
        $this->referenceDb = Database::connect();
        if ($this->referenceDb->DBDriver !== 'SQLite3' || $this->referenceDb->database !== ':memory:') {
            throw new RuntimeException('Reference tests require SQLite :memory:.');
        }
        foreach (['item_references', 'items', 'activity_logs', 'settings'] as $table) {
            Database::forge()->dropTable($table, true);
        }
        $this->referenceDb->query('CREATE TABLE db_items (id INTEGER PRIMARY KEY AUTOINCREMENT, item_code TEXT, category TEXT, location TEXT, is_active INTEGER, status TEXT, condition TEXT)');
        $this->referenceDb->query('CREATE TABLE db_activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, user_name TEXT, role TEXT, action TEXT, module TEXT, object TEXT, detail TEXT, ip_address TEXT, created_at TEXT)');
        $this->referenceDb->query('CREATE TABLE db_settings (id INTEGER PRIMARY KEY, setting_key TEXT, setting_value TEXT)');
        $this->referenceDb->table('items')->insertBatch([
            ['item_code' => 'REF-001', 'category' => 'Kategori Lama', 'location' => 'Gudang Lama', 'is_active' => 1, 'status' => 'available', 'condition' => 'good'],
            ['item_code' => 'REF-002', 'category' => 'Kategori Lama', 'location' => 'Gudang Lama', 'is_active' => 0, 'status' => 'disabled', 'condition' => 'minor_damage'],
        ]);
        (new CreateItemReferences(Database::forge()))->up();
        $this->referenceDb->resetTransStatus();
        $this->references = model(ItemReferenceModel::class);
        Services::resetSingle('renderer');
        helper(['app', 'activity', 'form', 'url', 'qr']);
        session()->set(['role' => 'admin', 'user_id' => 1, 'full_name' => 'Admin Uji', 'is_logged_in' => true]);
    }

    private function request(string $method, array $data = [], array $query = []): IncomingRequest
    {
        Services::resetSingle('validation');
        session()->remove(['success', 'error', 'errors']);
        $request = new IncomingRequest(config('App'), new SiteURI(config('App'), 'admin/references'), null, new UserAgent());
        $request->setMethod($method);
        $request->setGlobal('post', $data);
        $request->setGlobal('get', $query);
        $request->setGlobal('request', $method === 'POST' ? $data : $query);
        Services::injectMock('request', $request);
        Services::injectMock('uri', $request->getUri());
        return $request;
    }

    public function testMigrationKeepsExistingItemsAndAddsDefaultsAndLegacyNames(): void
    {
        self::assertContains('Kategori Lama', $this->references->names('category'));
        self::assertContains('Elektronik', $this->references->names('category'));
        self::assertContains('Gudang Lama', $this->references->names('location'));
        self::assertSame(2, $this->referenceDb->table('items')->where('category', 'Kategori Lama')->countAllResults());
        self::assertSame('disabled', $this->referenceDb->table('items')->where('id', 2)->get()->getRow()->status);
        $before = $this->referenceDb->table('item_references')->countAllResults();
        (new CreateItemReferences(Database::forge()))->up();
        self::assertSame($before, $this->referenceDb->table('item_references')->countAllResults());
    }

    public function testNewEntriesAppearInActualItemFormWithEscapedNames(): void
    {
        $this->references->saveEntry(null, 'category', 'Alat, Praktikum & <Sains>');
        $this->references->saveEntry(null, 'location', "Lab O'Brien");
        $this->referenceDb->table('item_references')->where('name', 'Aksesoris')->update(['sort_order' => 9999]);
        session()->set('role', 'petugas');
        $controller = new Items();
        $controller->initController($this->request('GET'), service('response'), service('logger'));
        $html = $controller->create();
        self::assertStringContainsString('Alat, Praktikum &amp; &lt;Sains&gt;', $html);
        $document = new DOMDocument();
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $categories = array_map(static fn ($node) => $node->getAttribute('value'), iterator_to_array($xpath->query('//select[@name="category"]/option')));
        $locations = array_map(static fn ($node) => $node->getAttribute('value'), iterator_to_array($xpath->query('//select[@name="location"]/option')));
        self::assertSame('Aksesoris', $categories[1]); // Legacy numeric priority must be ignored.
        self::assertContains('Alat, Praktikum & <Sains>', $categories);
        self::assertContains("Lab O'Brien", $locations);
    }

    public function testDuplicateNamesIncludeInactiveEntries(): void
    {
        $id = $this->references->saveEntry(null, 'category', 'Peralatan Khusus');
        $this->references->toggleEntry($id);
        $this->expectException(DomainException::class);
        $this->references->saveEntry(null, 'category', ' peralatan khusus ');
    }

    public function testInactiveChoiceCanOnlyBeRetainedByExistingItem(): void
    {
        $row = $this->references->where('type', 'category')->where('name', 'Kategori Lama')->first();
        $this->references->toggleEntry((int) $row->id);
        self::assertNotContains('Kategori Lama', $this->references->names('category'));
        self::assertContains('Kategori Lama', $this->references->names('category', 'Kategori Lama'));
        $this->references->withSelection('Kategori Lama', 'Gudang Lama', function ($selection) {
            $values = $this->references->selectionValues($selection, (object) ['category' => 'Kategori Lama', 'location' => 'Gudang Lama']);
            self::assertSame('Kategori Lama', $values['category']);
        });
        $this->expectException(DomainException::class);
        $this->references->withSelection('Kategori Lama', 'Gudang Lama', fn ($selection) => $this->references->selectionValues($selection));
    }

    public function testRenameUpdatesActiveAndArchivedItemsAndPreservesTheirStatus(): void
    {
        $row = $this->references->where('type', 'category')->where('name', 'Kategori Lama')->first();
        $this->references->saveEntry((int) $row->id, 'category', 'Kategori Baru');
        self::assertSame(2, $this->referenceDb->table('items')->where('category', 'Kategori Baru')->countAllResults());
        self::assertSame('disabled', $this->referenceDb->table('items')->where('id', 2)->get()->getRow()->status);
        self::assertSame(2, $this->references->usageCounts('category')['kategori baru']);
        self::assertNotContains('Kategori Lama', $this->references->names('category'));
        self::assertSame(1, $this->referenceDb->table('activity_logs')->where('action', 'referensi_diubah')->countAllResults());
    }

    public function testReferencedEntryCannotBeDeleted(): void
    {
        $this->referenceDb->table('items')->where('is_active', 1)->delete(); // An archived item still counts.
        $row = $this->references->where('type', 'location')->where('name', 'Gudang Lama')->first();
        $this->expectException(DomainException::class);
        $this->references->deleteUnused((int) $row->id);
    }

    public function testUnusedEntryCanBeDeletedAndTypesHaveSeparateNames(): void
    {
        $categoryId = $this->references->saveEntry(null, 'category', 'Nama Sama');
        $locationId = $this->references->saveEntry(null, 'location', 'Nama Sama');
        $this->references->deleteUnused($categoryId);
        self::assertNull($this->references->find($categoryId));
        self::assertNotNull($this->references->find($locationId));
    }

    public function testOldFormCannotRecreateReferenceAfterRename(): void
    {
        $row = $this->references->where('type', 'location')->where('name', 'Gudang Lama')->first();
        $this->references->saveEntry((int) $row->id, 'location', 'Gudang Baru');
        $this->expectException(DomainException::class);
        $this->references->withSelection('Kategori Lama', 'Gudang Lama', fn ($selection) => $this->references->selectionValues($selection));
    }

    public function testRenameFailureRollsBackReferenceAndInventory(): void
    {
        $row = $this->references->where('type', 'category')->where('name', 'Kategori Lama')->first();
        $this->referenceDb->query("CREATE TRIGGER fail_reference_log BEFORE INSERT ON db_activity_logs BEGIN SELECT RAISE(ABORT, 'planned reference test failure'); END");
        try {
            $this->references->saveEntry((int) $row->id, 'category', 'Nama Gagal');
            self::fail('Failed logging must roll back the rename.');
        } catch (RuntimeException $e) {
            self::assertSame('Kategori Lama', $this->references->find($row->id)->name);
            self::assertSame(2, $this->referenceDb->table('items')->where('category', 'Kategori Lama')->countAllResults());
            self::assertSame(0, $this->referenceDb->table('items')->where('category', 'Nama Gagal')->countAllResults());
        }
    }

    public function testControllerRejectsOverlongCategoryAndSavesLocation(): void
    {
        $controller = new References();
        $controller->initController($this->request('POST', ['type' => 'category', 'name' => str_repeat('A', 61)]), service('response'), service('logger'));
        $controller->save();
        self::assertNotNull(session()->getFlashdata('errors'));
        $controller->initController($this->request('POST', ['type' => 'location', 'name' => 'Ruang Baru']), service('response'), service('logger'));
        $controller->save();
        self::assertNotNull(session()->getFlashdata('success'));
        self::assertContains('Ruang Baru', $this->references->names('location'));
    }

    public function testAdminPageRendersBothTabsAndUsage(): void
    {
        $this->referenceDb->table('item_references')->where('name', 'Gudang Lama')->update(['sort_order' => 9999]);
        $controller = new References();
        $controller->initController($this->request('GET', [], ['type' => 'location']), service('response'), service('logger'));
        $html = $controller->index();
        self::assertStringContainsString('Kategori Barang', $html);
        self::assertStringContainsString('Lokasi Penyimpanan', $html);
        self::assertStringContainsString('Gudang Lama', $html);
        self::assertStringContainsString('Digunakan 2 unit', $html);
        self::assertStringContainsString('name="name"', $html);
        self::assertStringNotContainsString('name="sort_order"', $html);
        $document = new DOMDocument();
        @$document->loadHTML($html);
        self::assertSame('Gudang Lama', (new DOMXPath($document))->query('//div[@class="reference-record"]//h3')->item(0)->textContent);
    }
}
