<?php

use App\Controllers\Account;
use App\Controllers\Admin\Users;
use App\Controllers\Auth;
use App\Database\Migrations\AddAuthSecurity;
use App\Filters\AuthFilter;
use App\Libraries\LoginLimiter;
use App\Models\BorrowerModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\AuthSecurity;
use Config\Database;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;

require_once APPPATH . 'Database/Migrations/2026-11-02-000016_AddAuthSecurity.php';

final class AuthSecurityTest extends CIUnitTestCase
{
    private BaseConnection $authDb;
    private int $clockTime = 1800000000;
    private LoginLimiter $limiter;
    private const PASSWORD = 'Initial-test-password';

    protected function setUp(): void
    {
        parent::setUp();
        $this->authDb = Database::connect();
        if ($this->authDb->DBDriver !== 'SQLite3' || $this->authDb->database !== ':memory:') {
            throw new RuntimeException('Auth tests require an isolated in-memory database.');
        }
        foreach (['auth_login_limits', 'users', 'borrowers', 'activity_logs', 'settings'] as $table) {
            Database::forge()->dropTable($table, true);
        }
        $this->authDb->query('CREATE TABLE db_users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, full_name TEXT, password_hash TEXT, role TEXT, borrower_id INTEGER, is_active INTEGER, position TEXT, must_change_password INTEGER DEFAULT 0, last_login_at TEXT, created_at TEXT, updated_at TEXT)');
        $this->authDb->query('CREATE TABLE db_borrowers (id INTEGER PRIMARY KEY, name TEXT, type TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $this->authDb->query('CREATE TABLE db_activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, user_name TEXT, role TEXT, action TEXT, module TEXT, object TEXT, detail TEXT, ip_address TEXT, created_at TEXT)');
        $this->authDb->query('CREATE TABLE db_settings (id INTEGER PRIMARY KEY, setting_key TEXT, setting_value TEXT)');
        (new AddAuthSecurity(Database::forge()))->up();
        $this->authDb->resetTransStatus();
        foreach ([1 => 'admin', 2 => 'petugas', 3 => 'peminjam'] as $id => $role) {
            $this->authDb->table('users')->insert([
                'id' => $id, 'username' => 'user' . $id, 'full_name' => 'Test ' . $role,
                'role' => $role, 'borrower_id' => $role === 'peminjam' ? 1 : null, 'is_active' => 1,
                'password_hash' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
            ]);
        }
        $this->authDb->table('borrowers')->insert(['id' => 1, 'name' => 'Test Student', 'type' => 'student', 'is_active' => 1]);
        Services::resetSingle('authSession');
        Services::resetSingle('renderer');
        $this->setLimiter();
        helper(['app', 'activity', 'form', 'url']);
    }

    private function setLimiter(?AuthSecurity $policy = null): void
    {
        $this->limiter = new LoginLimiter($this->authDb, $policy ?? new AuthSecurity(), fn () => $this->clockTime);
        Services::injectMock('loginLimiter', $this->limiter);
    }

    private function request(string $method, string $path = 'login', array $data = [], string $ip = '192.0.2.10'): IncomingRequest
    {
        Services::resetSingle('validation');
        Services::resetSingle('response');
        Services::resetSingle('redirectresponse');
        Services::resetSingle('renderer');
        session()->remove(['error', 'success', 'login_error', 'login_username', 'info']);
        $request = new IncomingRequest(config('App'), new SiteURI(config('App'), $path), null, new UserAgent());
        $request->setMethod($method);
        $request->setGlobal('server', ['REMOTE_ADDR' => $ip]);
        $request->setGlobal('post', $data);
        $request->setGlobal('request', $data);
        Services::injectMock('request', $request);
        Services::injectMock('uri', $request->getUri());
        return $request;
    }

    private function postLogin(string $username = 'user2', string $password = self::PASSWORD, string $ip = '192.0.2.10')
    {
        $controller = new Auth();
        $controller->initController($this->request('POST', 'login', ['username' => $username, 'password' => $password], $ip), service('response'), service('logger'));
        return $controller->login();
    }

    private function establish(int $id = 2): array
    {
        $user = model(UserModel::class)->find($id);
        $borrower = $user->borrower_id ? model(BorrowerModel::class)->find((int) $user->borrower_id) : null;
        service('authSession')->establish($user, $borrower);
        return session()->get();
    }

    private function restoreSession(array $data): void
    {
        session()->remove(array_keys(session()->get()));
        session()->set($data);
    }

    public function testValidLoginStoresVersionAndNoPassword(): void
    {
        $this->postLogin();
        self::assertTrue(session()->get('is_logged_in'));
        self::assertSame(1, session()->get('auth_version'));
        self::assertNotNull(service('authSession')->currentUser());
        self::assertStringNotContainsString(self::PASSWORD, serialize(session()->get()));
    }

    public static function revocationChanges(): array
    {
        return [
            'disabled' => [['is_active' => 0]],
            'role' => [['role' => 'kepala_sekolah']],
            'binding' => [['borrower_id' => 9]],
            'username' => [['username' => 'renamed-user']],
            'password reset' => [['password_hash' => 'different-password-hash', 'must_change_password' => 1]],
        ];
    }

    #[DataProvider('revocationChanges')]
    public function testSecurityChangesRevokeOldSessions(array $changes): void
    {
        $this->establish();
        model(UserModel::class)->update(2, $changes);
        self::assertSame(2, (int) model(UserModel::class)->find(2)->auth_version);
        self::assertNull(service('authSession')->currentUser());
        self::assertFalse((bool) session()->get('is_logged_in'));
    }

    public function testRapidDisableAndReenableStillRevokesOldSession(): void
    {
        $this->establish();
        model(UserModel::class)->update(2, ['is_active' => 0]);
        model(UserModel::class)->update(2, ['is_active' => 1]);
        self::assertSame(3, (int) model(UserModel::class)->find(2)->auth_version);
        self::assertNull(service('authSession')->currentUser());
    }

    public function testSameRoleAndOrdinaryProfileUpdatesDoNotRevokeSessions(): void
    {
        $this->establish();
        model(UserModel::class)->update(2, ['role' => 'petugas', 'borrower_id' => null, 'username' => 'user2', 'full_name' => 'New Name']);
        model(UserModel::class)->update(2, ['last_login_at' => date('Y-m-d H:i:s')]);
        self::assertSame(1, (int) model(UserModel::class)->find(2)->auth_version);
        self::assertNotNull(service('authSession')->currentUser());
        self::assertSame('New Name', session()->get('full_name'));
    }

    public function testRealAdminPasswordResetRevokesExistingPetugasSession(): void
    {
        $old = $this->establish(2);
        $this->establish(1);
        $controller = new Users();
        $controller->initController($this->request('POST', 'admin/users/2/reset-password'), service('response'), service('logger'));
        $controller->resetPassword(2);
        self::assertSame(2, (int) model(UserModel::class)->find(2)->auth_version);
        $log = $this->authDb->table('activity_logs')->where('action', 'password_direset')->get()->getRow();
        self::assertSame(1, (int) $log->user_id);
        self::assertSame('admin', $log->role);
        self::assertSame('192.0.2.10', $log->ip_address);
        self::assertSame('user2', $log->object);
        self::assertStringNotContainsString(session()->get('temporary_password')['password'], $log->detail);
        $this->restoreSession($old);
        self::assertNull(service('authSession')->currentUser());
    }

    public function testFailedAuditRollsBackAdminPasswordReset(): void
    {
        $this->establish(1);
        $before = model(UserModel::class)->find(2);
        $this->authDb->query("CREATE TRIGGER fail_reset_audit BEFORE INSERT ON db_activity_logs BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        $controller = new Users();
        $controller->initController($this->request('POST', 'admin/users/2/reset-password'), service('response'), service('logger'));
        $controller->resetPassword(2);
        $after = model(UserModel::class)->find(2);
        self::assertSame($before->password_hash, $after->password_hash);
        self::assertSame($before->auth_version, $after->auth_version);
        self::assertNull(session()->get('temporary_password'));
        self::assertNotNull(session()->getFlashdata('error'));
    }

    public function testFailedAuditRollsBackAccountAndBorrowerStatus(): void
    {
        $this->establish(1);
        $this->authDb->query("CREATE TRIGGER fail_toggle_audit BEFORE INSERT ON db_activity_logs BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        $controller = new Users();
        $controller->initController($this->request('POST', 'admin/users/3/toggle'), service('response'), service('logger'));
        $controller->toggleActive(3);
        self::assertSame(1, (int) model(UserModel::class)->find(3)->is_active);
        self::assertSame(1, (int) model(BorrowerModel::class)->find(1)->is_active);
        self::assertNull(session()->getFlashdata('success'));
    }

    public function testFailedAuditRollsBackEveryGeneratedAccount(): void
    {
        $this->authDb->query('ALTER TABLE db_borrowers ADD COLUMN identity_number TEXT');
        $this->authDb->table('borrowers')->insertBatch([
            ['id' => 2, 'name' => 'Siswa A', 'type' => 'student', 'identity_number' => '1234567890', 'is_active' => 1],
            ['id' => 3, 'name' => 'Siswa B', 'type' => 'student', 'identity_number' => '1234567891', 'is_active' => 1],
        ]);
        $this->establish(1);
        $this->authDb->query("CREATE TRIGGER fail_bulk_audit BEFORE INSERT ON db_activity_logs WHEN NEW.object = '1234567891' BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        $controller = new Users();
        $controller->initController($this->request('POST', 'admin/users/generate', ['borrower_ids' => [2, 3]]), service('response'), service('logger'));
        $controller->generateBorrowers();
        self::assertSame(3, $this->authDb->table('users')->countAllResults());
        self::assertSame(0, $this->authDb->table('activity_logs')->countAllResults());
        self::assertNull(session()->get('generated_credentials'));
    }

    public function testFailedLoginAuditCannotLeaveAnAuthenticatedSession(): void
    {
        $this->authDb->query("CREATE TRIGGER fail_login_audit BEFORE INSERT ON db_activity_logs BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        $response = $this->postLogin();
        self::assertSame(503, $response->getStatusCode());
        self::assertEmpty(session()->get('is_logged_in'));
        self::assertNull(model(UserModel::class)->find(2)->last_login_at);
    }

    public function testDeniedRoleAccessIsAuditedWithoutQuerySecrets(): void
    {
        $this->establish(2);
        $request = $this->request('GET', 'admin/users?password=SECRET');
        (new \App\Filters\RoleFilter())->before($request, ['admin']);
        $log = $this->authDb->table('activity_logs')->get()->getRow();
        self::assertSame('akses_ditolak', $log->action);
        self::assertSame(2, (int) $log->user_id);
        self::assertStringContainsString('admin/users', $log->detail);
        self::assertStringNotContainsString('SECRET', $log->detail);
    }

    public function testSettingsAuditContainsChangesAndRollsBackOnFailure(): void
    {
        $this->authDb->query('ALTER TABLE db_settings ADD COLUMN created_at TEXT');
        $this->authDb->query('ALTER TABLE db_settings ADD COLUMN updated_at TEXT');
        $this->authDb->table('settings')->insert(['setting_key' => 'school_name', 'setting_value' => 'Sekolah Lama']);
        $this->establish(1);
        $controller = new \App\Controllers\Admin\Settings();
        $controller->initController($this->request('POST', 'admin/settings', ['school_name' => 'Sekolah Baru']), service('response'), service('logger'));
        $controller->save();
        $log = $this->authDb->table('activity_logs')->get()->getRow();
        self::assertStringContainsString('Sekolah Lama → Sekolah Baru', $log->detail);
        self::assertSame(1, (int) $log->user_id);
        $this->authDb->query("CREATE TRIGGER fail_settings_audit BEFORE INSERT ON db_activity_logs BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
        $controller->initController($this->request('POST', 'admin/settings', ['school_name' => 'Gagal Disimpan']), service('response'), service('logger'));
        $controller->save();
        self::assertSame('Sekolah Baru', $this->authDb->table('settings')->where('setting_key', 'school_name')->get()->getRow()->setting_value);
        self::assertSame(1, $this->authDb->table('activity_logs')->countAllResults());
    }

    public function testRejectedPasswordChangeIsAuditedWithoutPasswordValues(): void
    {
        $this->establish(2);
        $controller = new Account();
        $controller->initController($this->request('POST', 'account/password', [
            'current_password' => 'Wrong-Secret', 'new_password' => 'New-Secret-456', 'password_confirm' => 'New-Secret-456',
        ]), service('response'), service('logger'));
        $controller->password();
        $log = $this->authDb->table('activity_logs')->where('action', 'perubahan_sandi_ditolak')->get()->getRow();
        self::assertNotNull($log);
        self::assertSame(2, (int) $log->user_id);
        self::assertStringNotContainsString('Wrong-Secret', $log->detail);
        self::assertStringNotContainsString('New-Secret', $log->detail);
    }

    public function testBorrowerArchiveAndRestoreRevokesPortalSession(): void
    {
        $this->establish(3);
        model(BorrowerModel::class)->update(1, ['is_active' => 0]);
        model(BorrowerModel::class)->update(1, ['is_active' => 1]);
        self::assertNull(service('authSession')->currentUser());
    }

    public function testLegacyAndDeletedUserSessionsAreRejectedByFilter(): void
    {
        session()->set(['is_logged_in' => true, 'user_id' => 2, 'role' => 'petugas']);
        $request = $this->request('GET', 'petugas/items');
        self::assertNotNull((new AuthFilter())->before($request));
        self::assertFalse((bool) session()->get('is_logged_in'));
        $this->establish();
        model(UserModel::class)->delete(2);
        self::assertNotNull((new AuthFilter())->before($request));
    }

    public function testFailedLoginDoesNotKeepPasswordInOldInput(): void
    {
        session()->setFlashdata('_ci_old_input', ['post' => ['password' => 'Old-secret']]);
        $this->postLogin('user2', 'Wrong-secret');
        self::assertNull(session()->get('_ci_old_input'));
        self::assertStringNotContainsString('Wrong-secret', serialize(session()->get()));
        self::assertSame('user2', session()->getFlashdata('login_username'));
    }

    public function testAccountLimitReturns429RetryAfterAndExpiresWithoutExtension(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postLogin('user2', 'Wrong-password');
        }
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('300', $response->getHeaderLine('Retry-After'));
        self::assertStringNotContainsString('Wrong-password', $response->getBody());
        $this->clockTime += 60;
        $response = $this->postLogin('user2', self::PASSWORD, '192.0.2.22');
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('240', $response->getHeaderLine('Retry-After'));
        self::assertFalse((bool) session()->get('is_logged_in'));
        $this->clockTime += 240;
        $this->postLogin();
        self::assertTrue(session()->get('is_logged_in'));
    }

    public function testUnknownAccountHasSameLimitAndUsernameCaseIsNormalized(): void
    {
        $failed = static fn () => ['ok' => false];
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->attempt($i % 2 ? 'MISSING' : ' missing ', '192.0.2.' . ($i + 1), $failed);
        }
        $called = false;
        $outcome = $this->limiter->attempt('Missing', '192.0.2.99', function () use (&$called) { $called = true; return ['ok' => true]; });
        self::assertTrue($outcome['blocked']);
        self::assertFalse($called);
    }

    public function testIpLimitStopsUsernameRotationAndDoesNotAllocateMoreAccountRows(): void
    {
        $policy = new AuthSecurity();
        $policy->ipMaxFailures = 3;
        $this->setLimiter($policy);
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt('fake' . $i, '192.0.2.10', static fn () => ['ok' => false]);
        }
        $outcome = $this->limiter->attempt('fake-more', '192.0.2.10', static fn () => ['ok' => true]);
        self::assertTrue($outcome['blocked']);
        self::assertSame(4, $this->authDb->table('auth_login_limits')->countAllResults());
    }

    public function testSuccessClearsOnlyAccountFailuresAndDoesNotClearSharedIpFailures(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->limiter->attempt('user2', '192.0.2.10', static fn () => ['ok' => false]);
        }
        $success = $this->limiter->attempt('user2', '192.0.2.10', static fn () => ['ok' => true]);
        self::assertFalse($success['blocked']);
        self::assertSame(0, (int) $this->authDb->table('auth_login_limits')->where('scope', 'account')->get()->getRow()->failures);
        self::assertSame(4, (int) $this->authDb->table('auth_login_limits')->where('scope', 'ip')->get()->getRow()->failures);
    }

    public function testFailureWindowAndRetentionExpire(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->limiter->attempt('old-user', '192.0.2.10', static fn () => ['ok' => false]);
        }
        $this->clockTime += 900;
        $this->limiter->attempt('old-user', '192.0.2.10', static fn () => ['ok' => false]);
        self::assertSame(1, (int) $this->authDb->table('auth_login_limits')->where('scope', 'account')->get()->getRow()->failures);
        $this->clockTime += 900 + 86401;
        $this->limiter->attempt('new-user', '192.0.2.99', static fn () => ['ok' => false]);
        self::assertSame(2, $this->authDb->table('auth_login_limits')->countAllResults());
    }

    public function testMissingLimiterStorageFailsClosed(): void
    {
        Database::forge()->dropTable('auth_login_limits');
        $response = $this->postLogin();
        self::assertSame(503, $response->getStatusCode());
        self::assertFalse((bool) session()->get('is_logged_in'));
    }

    public function testOwnPasswordChangeKeepsCurrentSessionAndRevokesAnotherDevice(): void
    {
        $old = $this->establish();
        $controller = new Account();
        $controller->initController($this->request('POST', 'account/password', [
            'current_password' => self::PASSWORD, 'new_password' => 'New-secure-password', 'password_confirm' => 'New-secure-password',
        ]), service('response'), service('logger'));
        $controller->password();
        self::assertNotNull(session()->getFlashdata('success'));
        self::assertSame(2, session()->get('auth_version'));
        self::assertNotNull(service('authSession')->currentUser());
        self::assertTrue(password_verify('New-secure-password', model(UserModel::class)->find(2)->password_hash));
        self::assertNull(session()->get('_ci_old_input'));
        $this->restoreSession($old);
        self::assertNull(service('authSession')->currentUser());
    }

    public function testForcedPasswordChangeUsesCurrentDatabaseFlagAndAllowsPasswordRoute(): void
    {
        $this->authDb->table('users')->where('id', 2)->update(['must_change_password' => 1]);
        $this->establish();
        $request = $this->request('GET', 'petugas/items');
        self::assertNotNull((new AuthFilter())->before($request));
        $request = $this->request('GET', 'account/password');
        self::assertNull((new AuthFilter())->before($request));
    }
}
