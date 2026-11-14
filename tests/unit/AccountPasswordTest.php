<?php

use App\Controllers\Account;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class AccountPasswordTest extends CIUnitTestCase
{
    private function runChange(array $data, bool $shouldSave = false, bool $signedIn = true): void
    {
        helper(['auth', 'url']);
        $user = new User([
            'id' => 42,
            'username' => 'account-owner',
            'email' => 'owner@example.com',
            'password_hash' => password_hash('Current-93!Secret', PASSWORD_DEFAULT),
        ]);
        $provider = $this->getMockBuilder(UserModel::class)
            ->disableOriginalConstructor()->onlyMethods(['save'])->getMock();
        $provider->expects($shouldSave ? $this->once() : $this->never())
            ->method('save')->with($this->callback(function (User $saved) use ($user, $data): bool {
                return $saved === $user && $saved->password === $data['password'];
            }))->willReturn(true);
        $auth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()->onlyMethods(['user', 'getProvider'])->getMock();
        $auth->method('user')->willReturn($signedIn ? $user : null);
        $auth->method('getProvider')->willReturn($provider);
        Services::injectMock('auth', $auth);

        session()->remove(['passwordErrors', 'success', '_ci_old_input']);
        service('validation')->reset();
        $request = service('request');
        $request->setGlobal('post', $data);
        $controller = new Account();
        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->changePassword();
        $this->assertSame(site_url($signedIn ? 'account' : 'login'), $response->getHeaderLine('Location'));
        $this->assertNull(session()->get('_ci_old_input'));
    }

    public function testIncorrectCurrentPasswordCannotChangePassword(): void
    {
        $this->runChange(['current_password' => 'wrong', 'password' => 'V7!rQ9#nL2@xT8z', 'password_confirm' => 'V7!rQ9#nL2@xT8z']);
        $this->assertArrayHasKey('current_password', session()->getFlashdata('passwordErrors'));
    }

    public function testConfirmationMustMatch(): void
    {
        $this->runChange(['current_password' => 'Current-93!Secret', 'password' => 'V7!rQ9#nL2@xT8z', 'password_confirm' => 'different']);
        $this->assertArrayHasKey('password_confirm', session()->getFlashdata('passwordErrors'));
    }

    public function testWeakPasswordIsRejected(): void
    {
        $this->runChange(['current_password' => 'Current-93!Secret', 'password' => 'abc', 'password_confirm' => 'abc']);
        $this->assertArrayHasKey('password', session()->getFlashdata('passwordErrors'));
    }

    public function testMissingPasswordsAreRejected(): void
    {
        $this->runChange([]);
        $this->assertCount(3, session()->getFlashdata('passwordErrors'));
    }

    public function testValidChangeSavesOnlyTheSignedInUser(): void
    {
        $this->runChange(['current_password' => 'Current-93!Secret', 'password' => 'V7!rQ9#nL2@xT8z', 'password_confirm' => 'V7!rQ9#nL2@xT8z', 'user_id' => 99], true);
        $this->assertSame('Your password has been changed.', session()->getFlashdata('success'));
    }

    public function testGuestCannotChangePassword(): void
    {
        $this->runChange([], false, false);
    }
}
