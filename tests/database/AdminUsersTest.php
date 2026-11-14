<?php

use App\Controllers\Admin\Users;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Filters\PermissionFilter;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class AdminUsersTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $namespace = ['CodeIgniter\\Shield', 'CodeIgniter\\Settings'];
    protected $migrateOnce = false;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['auth', 'url', 'form']);
        service('validation')->reset();
    }

    private function makeUser(string $role, string $username = 'existing'): User
    {
        $users = auth()->getProvider();
        $user = $users->createNewUser([
            'username' => $username,
            'email' => $username . '@example.com',
            'password' => 'Q8!pR3#vN9@tW5k',
            'active' => 1,
        ]);
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($role);

        return $user;
    }

    private function submit(array $overrides = []): void
    {
        $this->actingAs($this->makeUser('admin'));
        $request = service('request');
        $request->setGlobal('post', array_replace([
            'username' => 'new.user',
            'email' => 'new.user@example.org',
            'role' => 'jury',
            'password' => 'V7!rQ9#nL2@xT8z',
            'password_confirm' => 'V7!rQ9#nL2@xT8z',
        ], $overrides));
        $controller = new Users();
        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->create();
        $this->assertSame(site_url('admin/users'), $response->getHeaderLine('Location'));
        $this->assertNull(session()->get('_ci_old_input'));
        $values = session()->get('userValues') ?? [];
        $this->assertArrayNotHasKey('password', $values);
        $this->assertArrayNotHasKey('password_confirm', $values);
    }

    public function testCreatesActiveJuryAccountWithHashedPassword(): void
    {
        $this->submit(['active' => 0, 'groups' => ['superadmin']]);
        $user = auth()->getProvider()->findByCredentials(['email' => 'new.user@example.org']);
        $this->assertNotNull($user);
        $this->assertTrue($user->isActivated());
        $this->assertSame(['jury'], $user->getGroups());
        $this->assertTrue(service('passwords')->verify('V7!rQ9#nL2@xT8z', $user->getPasswordHash()));
        $this->assertSame('The user has been created.', session()->getFlashdata('success'));
    }

    public function testCreatesAdminWhoCanManageOtherAdmins(): void
    {
        $this->submit(['role' => 'admin']);
        $user = auth()->getProvider()->findByCredentials(['email' => 'new.user@example.org']);
        $this->assertSame(['admin'], $user->getGroups());
        $this->assertTrue($user->can('users.manage-admins'));
    }

    public function testOtherRolesCannotBeCreated(): void
    {
        $this->submit(['role' => 'superadmin']);
        $this->assertArrayHasKey('role', session()->getFlashdata('userErrors'));
        $this->assertSame(1, auth()->getProvider()->countAllResults());
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->submit(['email' => 'EXISTING@example.com']);
        $this->assertArrayHasKey('email', session()->getFlashdata('userErrors'));
        $this->assertSame(1, auth()->getProvider()->countAllResults());
    }

    public function testDuplicateUsernameIsRejected(): void
    {
        $this->submit(['username' => 'existing']);
        $this->assertArrayHasKey('username', session()->getFlashdata('userErrors'));
        $this->assertSame(1, auth()->getProvider()->countAllResults());
    }

    public function testPasswordConfirmationIsRequired(): void
    {
        $this->submit(['password_confirm' => 'different']);
        $this->assertArrayHasKey('password_confirm', session()->getFlashdata('userErrors'));
        $this->assertSame(1, auth()->getProvider()->countAllResults());
    }

    public function testPasswordCannotContainNewUsersPersonalDetails(): void
    {
        $this->submit(['password' => 'new.user@example.org', 'password_confirm' => 'new.user@example.org']);
        $this->assertArrayHasKey('password', session()->getFlashdata('userErrors'));
        $this->assertSame(1, auth()->getProvider()->countAllResults());
    }

    public function testOnlyAdministratorsCanAccessUserManagement(): void
    {
        $filter = new PermissionFilter();
        foreach (['jury', 'user', 'developer'] as $role) {
            $this->actingAs($this->makeUser($role, $role));
            $this->assertNotNull($filter->before(service('request'), ['users.manage-admins']));
            auth()->logout();
        }
        $this->actingAs($this->makeUser('admin', 'administrator'));
        $this->assertNull($filter->before(service('request'), ['users.manage-admins']));
    }

    public function testListShowsEmailsAndRolesWithoutPasswordHashes(): void
    {
        $user = $this->makeUser('admin');
        $this->actingAs($user);
        $controller = new Users();
        $html = $controller->index();
        $this->assertStringContainsString('existing@example.com', $html);
        $this->assertStringContainsString('admin', $html);
        $this->assertStringContainsString('name="csrf_test_name"', $html);
        $this->assertStringNotContainsString($user->getPasswordHash(), $html);
    }

    public function testPublicRegistrationRemainsDisabled(): void
    {
        $this->assertFalse(config('Auth')->allowRegistration);
    }
}
