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

    private function updateUser(User $user, array $changes = []): void
    {
        $request = service('request');
        $request->setGlobal('post', array_replace([
            'username' => $user->username,
            'email' => $user->getEmail(),
            'role' => 'jury',
        ], $changes));
        $controller = new Users();
        $controller->initController($request, service('response'), service('logger'));
        $controller->update((int) $user->id);
    }

    public function testUpdatesEmailUsernameAndRoleWithoutChangingPassword(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $user = $this->makeUser('jury', 'juror');
        $hash = $user->getPasswordHash();
        $this->updateUser($user, ['username' => 'updated', 'email' => 'UPDATED@example.org', 'role' => 'admin', 'password' => 'ignored']);
        $updated = auth()->getProvider()->findById($user->id);
        $this->assertSame('updated', $updated->username);
        $this->assertSame('updated@example.org', $updated->getEmail());
        $this->assertSame(['admin'], $updated->getGroups());
        $this->assertSame($hash, $updated->getPasswordHash());
        $this->assertSame('The user has been updated.', session()->getFlashdata('success'));
    }

    public function testUnchangedDetailsCanBeSavedAndRoleCanBeChangedAlone(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $user = $this->makeUser('jury', 'juror');
        $this->updateUser($user, ['role' => 'admin']);
        $this->assertSame(['admin'], auth()->getProvider()->findById($user->id)->getGroups());
        $this->assertNull(session()->getFlashdata('userErrors'));
    }

    public function testUpdateRejectsDuplicateDetailsAndInvalidRole(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $user = $this->makeUser('jury', 'juror');
        $this->updateUser($user, ['username' => 'existing', 'email' => 'existing@example.com', 'role' => 'superadmin']);
        $errors = session()->getFlashdata('userErrors');
        foreach (['username', 'email', 'role'] as $field) {
            $this->assertArrayHasKey($field, $errors);
        }
        $this->assertSame('juror', auth()->getProvider()->findById($user->id)->username);
    }

    public function testAdministratorCannotRemoveOwnRole(): void
    {
        $user = $this->makeUser('admin');
        $this->actingAs($user);
        $this->updateUser($user, ['role' => 'jury']);
        $this->assertArrayHasKey('role', session()->getFlashdata('userErrors'));
        $this->assertSame(['admin'], auth()->getProvider()->findById($user->id)->getGroups());
    }

    public function testEditFormIsPrefilledAndDoesNotRequirePassword(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $user = $this->makeUser('jury', 'juror');
        $html = (new Users())->edit((int) $user->id);
        $this->assertStringContainsString('value="juror"', $html);
        $this->assertStringContainsString('value="juror@example.com"', $html);
        $this->assertStringContainsString('Save changes', $html);
        $this->assertStringNotContainsString('name="password"', $html);
    }

    public function testUnknownUserReturnsNotFound(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        (new Users())->edit(99999);
    }

    public function testDeleteRemovesAccountFromActiveUsersAndKeepsHistory(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $user = $this->makeUser('jury', 'juror');
        (new Users())->delete((int) $user->id);
        $this->assertNull(auth()->getProvider()->findById($user->id));
        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'juror@example.com']));
        $this->assertNotNull(auth()->getProvider()->withDeleted()->find($user->id));
        $this->assertSame('The user has been deleted.', session()->getFlashdata('success'));
    }

    public function testAdministratorCannotDeleteOwnAccount(): void
    {
        $user = $this->makeUser('admin');
        $this->actingAs($user);
        (new Users())->delete((int) $user->id);
        $this->assertNotNull(auth()->getProvider()->findById($user->id));
        $this->assertNotNull(session()->getFlashdata('error'));
    }

    public function testDeleteRequiresConfirmationWithCsrfToken(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $user = $this->makeUser('jury', 'juror');
        $html = (new Users())->confirmDelete((int) $user->id);
        $this->assertStringContainsString('juror@example.com', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="csrf_test_name"', $html);
        $this->assertNotNull(auth()->getProvider()->findById($user->id));
    }

    public function testSmtpTestIsVisibleAndAccessibleOnlyForAdministrators(): void
    {
        $filter = new \CodeIgniter\Shield\Filters\GroupFilter();
        foreach (['jury', 'user', 'developer', 'admin', 'superadmin'] as $role) {
            $this->actingAs($this->makeUser($role, $role));
            $html = (new \App\Controllers\Account())->index();
            $allowed = in_array($role, ['admin', 'superadmin'], true);
            $this->assertSame($allowed, str_contains($html, 'action="' . site_url('account/test-email') . '"'));
            $this->assertSame($allowed, $filter->before(service('request'), ['admin', 'superadmin']) === null);
            $this->assertStringContainsString('href="' . site_url('logout') . '"', $html);
            auth()->logout();
        }
    }

    public function testAccountLogoutEndsTheSession(): void
    {
        $this->actingAs($this->makeUser('jury'));
        $this->assertTrue(auth()->loggedIn());
        (new \CodeIgniter\Shield\Controllers\LoginController())->logoutAction();
        $this->assertFalse(auth()->loggedIn());
    }

    public function testPublicRegistrationRemainsDisabled(): void
    {
        $this->assertFalse(config('Auth')->allowRegistration);
    }
}
