<?php

declare(strict_types=1);

// Real HTTP/browser transactions run against a brand-new local test DB, never pindai_in.
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
$root = dirname(__DIR__);
foreach (['build', 'public/uploads', 'public/qr'] as $directory) {
    if (! is_dir($root . '/' . $directory)) mkdir($root . '/' . $directory, 0700, true);
}
$config = config('Database')->default;
if (! in_array($config['hostname'], ['localhost', '127.0.0.1', '::1'], true)) { throw new RuntimeException('Only local MySQL is permitted.'); }
$admin = new mysqli($config['hostname'], $config['username'], $config['password'], '', (int) ($config['port'] ?: 3306));
$token = bin2hex(random_bytes(6));
$name = 'pindai_acc_test_' . $token;
$run = $root . '/build/accessory-browser-' . $token;
mkdir($run, 0777, true); mkdir($run . '/session');
$db = null; $servers = []; $created = false; $exit = 1;
$fixtureImage = $root . '/public/uploads/accessory-fixture-' . $token . '.png';
$fixtureSerial = $root . '/public/uploads/accessory-serial-fixture-' . $token . '.png';
$fixtureCodeBase = 800000000000000 + hexdec($token);
$newFixtureCode = 'UNT-' . ($fixtureCodeBase + 1);
$fixtureQrs = [];
for ($offset = 1; $offset <= 3; $offset++) {
    $code = 'UNT-' . ($fixtureCodeBase + $offset);
    $fixtureQrs[$code] = $root . '/public/qr/qr_' . strtolower($code) . '.png';
}
try {
    $admin->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $created = true;
    $testConfig = array_replace($config, ['database' => $name, 'DBPrefix' => '']);
    config('Database')->tests = config('Database')->default = $testConfig;
    $db = \Config\Database::connect('tests');
    $forge = \Config\Database::forge($db);
    $db->query("SET SESSION sql_mode='STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY'");
    $files = glob(APPPATH . 'Database/Migrations/*.php'); sort($files);
    foreach ($files as $file) {
        require_once $file;
        $base = basename($file, '.php');
        $class = 'App\\Database\\Migrations\\' . substr($base, strpos($base, '_') + 1);
        (new $class($forge))->up();
    }
    $db->table('borrowers')->insert(['id' => 1, 'name' => 'Guru Uji', 'type' => 'teacher', 'identity_number' => '9999999999', 'is_active' => 1]);
    foreach ([1 => 'petugas', 2 => 'kepala_sekolah', 3 => 'admin', 4 => 'peminjam'] as $id => $role) {
        $db->table('users')->insert(['id' => $id, 'username' => 'acc_' . $role, 'full_name' => 'Pengguna Uji ' . $role,
            'password_hash' => password_hash('Accessory-fixture-password', PASSWORD_BCRYPT, ['cost' => 4]),
            'role' => $role, 'borrower_id' => $role === 'peminjam' ? 1 : null, 'is_active' => 1, 'must_change_password' => 0]);
    }
    $db->table('users')->insert(['id' => 5, 'username' => 'acc_petugas2', 'full_name' => 'Petugas Uji Kedua',
        'password_hash' => password_hash('Accessory-fixture-password', PASSWORD_BCRYPT, ['cost' => 4]),
        'role' => 'petugas', 'is_active' => 1, 'must_change_password' => 0]);
    $image = imagecreatetruecolor(240, 160); imagefill($image, 0, 0, imagecolorallocate($image, 120, 140, 160));
    imagepng($image, $fixtureImage); imagedestroy($image);
    if (! copy($fixtureImage, $fixtureSerial)) { throw new RuntimeException('Serial fixture could not be copied.'); }
    foreach ([1 => 'Laptop Dapodik LOQ-15IAX9E', 2 => 'Unit Lama'] as $id => $label) {
        $db->table('items')->insert(['id' => $id, 'item_code' => $id === 2 ? 'UNT-' . $fixtureCodeBase : 'ACC-001', 'item_name' => $label,
            'category' => 'Perangkat Komputer', 'brand' => 'Uji', 'model' => 'LOQ-15IAX9E', 'serial_number' => 'SERIAL-' . $id,
            'acquisition_date' => '2026-01-01', 'specifications' => 'Fixture browser, bukan data operasional.', 'location' => 'Ruang Sarana',
            'status' => 'available', 'condition' => 'good', 'is_active' => 1,
            'physical_photo' => 'uploads/' . basename($fixtureImage), 'serial_photo' => 'uploads/' . basename($id === 2 ? $fixtureImage : $fixtureSerial)]);
    }
    for ($index = 1; $index <= 9; $index++) {
        $db->table('items')->insert([
            'item_code' => 'ZZM-00' . $index,
            'item_name' => match ($index) {
                2 => 'Chromebook 03',
                5 => 'Chromebook 01',
                8 => 'Perangkat Multimedia Portabel 08',
                9 => 'Perangkat Multimedia dengan Nama Inventaris yang Sangat Panjang',
                default => 'Perangkat Multimedia ' . $index,
            },
            'brand' => match ($index) { 2, 5 => 'Acer', 9 => 'Epson', default => null },
            'model' => match ($index) { 2 => 'C734', 9 => 'EB-X06', default => null },
            'category' => 'Jaringan & Multimedia', 'location' => 'Ruang Multimedia', 'status' => 'available', 'condition' => 'good', 'is_active' => 1,
            'physical_photo' => $index === 1 ? 'uploads/accessory-missing-' . $token . '.png' : null,
        ]);
    }
    $db->table('items')->insert(['item_code' => 'ZZM-ARSIP', 'item_name' => 'Unit Arsip Uji',
        'category' => 'Jaringan & Multimedia', 'location' => 'Ruang Multimedia', 'status' => 'disabled', 'condition' => 'good', 'is_active' => 0]);
    $db->table('items')->where('id', 4)->update(['status' => 'borrowed']);
    $db->table('item_accessories')->insert(['item_id' => 5, 'name' => 'Adaptor unit', 'quantity' => 1, 'is_required' => 1]);
    for ($index = 0; $index < 12; $index++) {
        $borrowed = time() - ($index + 2) * 86400;
        $db->table('loans')->insert([
            'loan_number' => 'UJI-RIWAYAT-' . $index, 'item_id' => $index === 0 ? 4 : 2, 'borrower_id' => 1,
            'borrowed_at' => date('Y-m-d H:i:s', $borrowed), 'due_at' => date('Y-m-d H:i:s', $borrowed + 14400),
            'purpose' => 'Riwayat uji daftar', 'usage_location' => 'Ruang Sarana', 'processed_by' => 1,
            'returned_at' => $index === 0 ? null : date('Y-m-d H:i:s', $borrowed + 7200),
        ]);
    }
    foreach ($fixtureQrs as $path) {
        if (is_file($path)) { throw new RuntimeException('QR test path already exists.'); }
    }
    $startServer = static function (string $log) use ($root, $run, $name, $config, &$servers): string {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr($address, strrpos($address, ':') + 1);
        $baseURL = 'http://127.0.0.1:' . $port;
        $overrides = [
            'CI_ENVIRONMENT' => 'production', 'database.default.database' => $name,
            'database.default.hostname' => (string) $config['hostname'],
            'database.default.username' => (string) $config['username'],
            'database.default.password' => (string) $config['password'],
            'database.default.port' => (string) ($config['port'] ?: 3306),
            'database.default.DSN' => '', 'database.default.DBPrefix' => '',
            'app.baseURL' => $baseURL . '/', 'app.forceGlobalSecureRequests' => 'false', 'app.autoLocalBaseURL' => 'false',
            'cookie.secure' => 'false', 'cookie.domain' => '', 'session.savePath' => $run . '/session',
        ];
        $environment = array_replace(getenv(), $overrides, ['PINDAI_ACCESSORY_TEST_OVERRIDES' => json_encode($overrides)]);
        $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/tests/accessory-test-router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $run . '/' . $log, 'a'], 2 => ['file', $run . '/' . $log, 'a']], $pipes, $root, $environment);
        if (! is_resource($server)) {
            throw new RuntimeException('Test server could not start.');
        }
        $servers[] = $server;
        fclose($pipes[0]);
        for ($i = 0; $i < 40; $i++) {
            $probe = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if ($probe) {
                fclose($probe);
                return $baseURL;
            }
            usleep(100000);
        }
        throw new RuntimeException('Test server did not start.');
    };
    $classesOnly = in_array('--classes-only', $argv, true);
    $tabletOnly = in_array('--tablet-only', $argv, true);
    $accountsOnly = in_array('--accounts-only', $argv, true);
    if ($accountsOnly) {
        $students = [];
        for ($index = 1; $index <= 1005; $index++) {
            $students[] = ['name' => 'Siswa Massal ' . $index, 'type' => 'student', 'identity_number' => '8833' . str_pad((string) $index, 8, '0', STR_PAD_LEFT), 'class_name' => 'VII.1', 'is_active' => 1];
        }
        $db->table('borrowers')->insertBatch($students);
        for ($index = 1; $index <= 41; $index++) $db->table('borrowers')->insert(['name' => 'Guru Massal ' . $index, 'type' => 'teacher', 'identity_number' => '8822' . str_pad((string) $index, 8, '0', STR_PAD_LEFT), 'is_active' => 1]);
        $db->table('borrowers')->insert(['name' => 'Siswa nonaktif', 'type' => 'student', 'identity_number' => '883300009999', 'is_active' => 0]);
        $db->table('users')->insert(['username' => '883300001005', 'full_name' => 'Konflik username uji', 'password_hash' => 'dummy', 'role' => 'petugas', 'is_active' => 1]);
    }
    if ($classesOnly) {
        foreach ([['VII.5', 1], ['VII.1', 1], ['VIII.1', 1], ['IX.9', 1], ['VIII.99', 0]] as $index => [$class, $active]) {
            $db->table('borrowers')->insert(['name' => 'Siswa kelas ' . $class, 'type' => 'student', 'identity_number' => '123456789' . $index,
                'class_name' => $class, 'is_active' => $active]);
        }
    }
    $baseURL = $startServer('server.log');
    $peerURL = $startServer('peer.log');
    $input = $run . '/fixture.json';
    file_put_contents($input, json_encode(['baseURL' => $baseURL, 'peerURL' => $peerURL, 'run' => $run, 'image' => $fixtureImage]));
    $serialOnly = in_array('--serial-only', $argv, true);
    $desktopOnly = in_array('--desktop-only', $argv, true);
    $letterheadOnly = in_array('--letterhead-only', $argv, true);
    $browserScript = $desktopOnly ? '/tests/inventory-desktop-browser.cjs' : ($serialOnly ? '/tests/item-duplicate-serial-browser.cjs' : '/tests/accessory-browser-smoke.cjs');
    if ($classesOnly) $browserScript = '/tests/borrower-classes-browser.cjs';
    if ($tabletOnly) $browserScript = '/tests/loan-tablet-browser.cjs';
    if ($letterheadOnly) $browserScript = '/tests/school-letterhead-browser.cjs';
    if ($accountsOnly) $browserScript = '/tests/borrower-generator-browser.cjs';
    $node = proc_open(['C:/Program Files/nodejs/node.exe', $root . $browserScript, $input],
        [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $nodePipes, $root);
    fclose($nodePipes[0]);
    if (proc_close($node) !== 0) { throw new RuntimeException('Browser flow failed; inspect ' . basename($run)); }
    if ($accountsOnly) {
        $ids = array_map('intval', array_column($db->table('borrowers')->select('id')->where('type', 'student')->where('is_active', 1)->orderBy('id')->get()->getResultArray(), 'id'));
        $generator = new \App\Libraries\BorrowerAccountGenerator($db, static fn ($password) => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]));
        $new = $skippedCount = 0;
        foreach (array_chunk($ids, 20) as $batch) { $result = $generator->generate($batch); $new += count($result['created']); $skippedCount += count($result['skipped']); }
        if ($new !== 1004 || $skippedCount !== 1) throw new RuntimeException('1005-student generation did not preserve username conflicts.');
        foreach (array_chunk($ids, 20) as $batch) {
            $result = $generator->generate($batch);
            if ($result['created'] !== [] || count($result['skipped']) !== count($batch)) throw new RuntimeException('Retry duplicated student accounts.');
        }
        $sample = $db->table('users')->where('username', '883300000001')->get()->getRow();
        if (! $sample || ! password_verify($sample->username, $sample->password_hash) || ! $sample->must_change_password) throw new RuntimeException('Initial password/first-login requirement mismatch.');
        if ($db->table('activity_logs')->where('action', 'akun_peminjam_dibuat')->countAllResults() !== 1045) throw new RuntimeException('Bulk accounts must each have an audit record.');
        $db->table('borrowers')->insert(['name' => 'Rollback audit uji', 'type' => 'student', 'identity_number' => '883300008888', 'is_active' => 1]);
        $rollbackId = $db->insertID();
        $db->query("CREATE TRIGGER fail_test_account_audit BEFORE INSERT ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test audit failure'");
        $auditFailed = false;
        try { $generator->generate([$rollbackId]); }
        catch (\Throwable $error) { $auditFailed = true; }
        if (! $auditFailed || $db->table('users')->where('borrower_id', $rollbackId)->countAllResults()) throw new RuntimeException('Audit failure must reject the account and roll it back.');
        $db->query('DROP TRIGGER fail_test_account_audit');
        echo "PASS 1005-student batches, 41-teacher browser flow, existing-account skips, replay, initial passwords, 1045 audit rows and audit failure rollback.\n";
    } elseif ($letterheadOnly) {
        helper(['app', 'url']);
        $report = view('admin/activity_print', ['logs' => [], 'from' => '', 'to' => '']);
        foreach (['letterhead_government' => '12.5pt;font-weight:400', 'letterhead_department' => '15pt;font-weight:700', 'letterhead_contact' => '10pt;font-weight:700'] as $key => $style) {
            if (! str_contains($report, 'data-letterhead-line="' . $key . '" style="font-size:' . $style . '"')) throw new RuntimeException('Saved letterhead style not applied to the actual report: ' . $key);
        }
        echo "PASS persisted per-line font sizes and bold settings are used by the actual audit report template.\n";
    } elseif ($desktopOnly || $classesOnly || $tabletOnly) {
        echo "Read-only inventory desktop layout verification OK.\n";
    } elseif ($serialOnly) {
        if ($db->table('items')->where('serial_number', 'SERIAL-1')->countAllResults() !== 3) {
            throw new RuntimeException('Duplicate serial create/edit did not persist.');
        }
        echo "Duplicate serial confirmation and database persistence OK.\n";
    } else {
    $group = $db->table('loan_groups')->get()->getRow();
    $members = $group ? $db->table('loans')->where('loan_group_id', $group->id)->orderBy('item_id')->get()->getResultArray() : [];
    if ($db->table('loan_groups')->countAllResults() !== 1 || array_map('intval', array_column($members, 'item_id')) !== [5, 6]
        || ! $members[0]['returned_at'] || $members[1]['returned_at']) {
        throw new RuntimeException('Group membership or partial return did not persist.');
    }
    $groupExtras = $db->table('loan_accessories')->where('loan_id', $members[0]['id'])->orderBy('id')->get()->getResultArray();
    if (array_column($groupExtras, 'name') !== ['Adaptor unit', 'Kabel kelompok'] || (int) $groupExtras[1]['is_required'] !== 0) {
        throw new RuntimeException('Group accessory snapshots did not persist per unit.');
    }
    $loan = $db->table('loans')->where('item_id', 1)->get()->getRow();
    $rows = $db->table('loan_accessories')->where('loan_id', $loan->id)->orderBy('id')->get()->getResultArray();
    if (array_column($rows, 'name') !== ['Charger', 'Kabel HDMI'] || (int) $rows[0]['quantity_returned'] !== 1
        || (int) $rows[1]['quantity_returned'] !== 2 || (int) $rows[1]['is_required'] !== 0) {
        throw new RuntimeException('Browser submissions did not persist exact accessory snapshots/counts.');
    }
    if ($loan->completeness_in !== 'tidak_lengkap' || $db->table('items')->where('id', 1)->get()->getRow()->status !== 'available') {
        throw new RuntimeException('Historical inspection or final item state mismatch.');
    }
    if ($db->table('loan_accessory_events')->whereIn('loan_accessory_id', array_column($rows, 'id'))->countAllResults() !== 4) {
        throw new RuntimeException('Follow-up receipts duplicated or missing.');
    }
    if (! $loan->return_photo || ! is_file(WRITEPATH . $loan->return_photo) || is_file($root . '/public/' . $loan->return_photo)) {
        throw new RuntimeException('New return photos must live outside public/.');
    }
    $serialFixture = $db->table('items')->where('item_code', $newFixtureCode)->get()->getRow();
    if (! $serialFixture || $serialFixture->serial_status !== 'unreadable' || $serialFixture->serial_number !== null
        || $serialFixture->serial_photo !== null || ! $serialFixture->physical_photo) {
        throw new RuntimeException('The optional serial evidence did not persist correctly.');
    }
    $parallelUnits = $db->table('items')->whereIn('item_code', array_keys($fixtureQrs))->where('id !=', $serialFixture->id)->get()->getResult();
    if (count($parallelUnits) !== 2) { throw new RuntimeException('Two concurrent item inserts did not commit.'); }
    $parallelIds = array_column($parallelUnits, 'id');
    $parallelLoans = $db->table('loans')->whereIn('item_id', $parallelIds)->get()->getResult();
    $parallelReports = $db->table('damage_reports')->whereIn('item_id', $parallelIds)->get()->getResult();
    foreach ([[$parallelLoans, 'loan_number', 'PMJ'], [$parallelReports, 'report_number', 'DR']] as [$records, $field, $prefix]) {
        $numbers = array_column($records, $field);
        if (count(array_unique($numbers)) !== 2 || count(preg_grep('/^' . $prefix . '-[0-9]{6}-[0-9]{3,}$/D', $numbers)) !== 2) {
            throw new RuntimeException('Concurrent ' . $prefix . ' saves must use distinct numbers in the original format.');
        }
    }
    if (count(array_unique(array_column($parallelLoans, 'processed_by'))) !== 2
        || count(array_filter($parallelReports, static fn ($report) => $report->loan_id !== null)) !== 1) {
        throw new RuntimeException('Concurrent fixtures must cover two staff accounts and both damage flows.');
    }
    echo "Parallel item, loan, and manual/return damage saves committed with unique numbers.\n";
    echo "MySQL persistence, immutable inspection, and final availability OK.\n";
    }
    echo 'Screenshots: ' . $run . "\n";
    $exit = 0;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
} finally {
    foreach ($servers as $server) { proc_terminate($server); proc_close($server); }
    if ($db) {
        helper('app');
        foreach ($db->table('settings')->whereIn('setting_key', ['letterhead_city_logo', 'letterhead_school_logo'])->get()->getResult() as $logo) {
            $logoFile = \App\Libraries\SchoolLetterhead::logoFile($logo->setting_value);
            if ($logoFile && is_file($logoFile)) unlink($logoFile);
        }
        foreach ($db->table('items')->whereIn('item_code', array_keys($fixtureQrs))->get()->getResult() as $createdItem) {
            delete_photo($createdItem->physical_photo);
            delete_photo($createdItem->serial_photo);
            $path = $fixtureQrs[$createdItem->item_code];
            if (is_file($path)) { unlink($path); }
        }
        foreach ($db->table('loans')->where('return_photo IS NOT NULL')->get()->getResult() as $loanPhoto) {
            delete_photo($loanPhoto->return_photo);
        }
        $db->close();
    }
    if (is_file($fixtureImage)) { unlink($fixtureImage); }
    if (is_file($fixtureSerial)) { unlink($fixtureSerial); }
    if ($created && preg_match('/\Apindai_acc_test_[a-f0-9]{12}\z/', $name)) { $admin->query('DROP DATABASE `' . $name . '`'); }
    $admin->close();
}
exit($exit);
