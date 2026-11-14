<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Validation\ValidationRules;
use RuntimeException;
use Throwable;

class Users extends BaseController
{
    public function index(): string
    {
        return $this->renderPage();
    }

    public function edit(int $id): string
    {
        return $this->renderPage($this->findUser($id));
    }

    private function renderPage(?User $editing = null): string
    {
        $users = auth()->getProvider()->withIdentities()->withGroups()->orderBy('id', 'DESC')->findAll();

        return service('twig')->render('admin/users.html.twig', [
            'users' => $users,
            'editing' => $editing,
            'currentUserId' => (int) auth()->user()->id,
            'error' => session()->getFlashdata('error'),
            'values' => session()->getFlashdata('userValues') ?? ($editing === null ? [] : [
                'username' => $editing->username,
                'email' => $editing->getEmail(),
                'role' => $editing->inGroup('admin', 'superadmin') ? 'admin' : 'jury',
            ]),
            'errors' => session()->getFlashdata('userErrors') ?? [],
            'success' => session()->getFlashdata('success'),
        ]);
    }

    public function create(): RedirectResponse
    {
        $values = [
            'username' => trim((string) $this->request->getPost('username')),
            'email' => strtolower(trim((string) $this->request->getPost('email'))),
            'role' => $this->request->getPost('role'),
        ];
        $data = $values + $this->request->getPost(['password', 'password_confirm']);
        $authConfig = config('Auth');
        $validation = new ValidationRules();
        $usernameRules = $authConfig->usernameValidationRules;
        $usernameRules['rules'][] = 'is_unique[' . $authConfig->tables['users'] . '.username]';
        $emailRules = $authConfig->emailValidationRules;
        $emailRules['rules'][] = 'is_unique[' . $authConfig->tables['identities'] . '.secret]';

        if (! $this->validateData($data, [
            'username' => $usernameRules,
            'email' => $emailRules,
            'role' => ['label' => 'Role', 'rules' => 'required|in_list[jury,admin]'],
            'password' => $validation->getPasswordRules(),
            'password_confirm' => $validation->getPasswordConfirmRules(),
        ], [], $authConfig->DBGroup)) {
            return $this->failed($values, $this->validator->getErrors());
        }

        $users = auth()->getProvider();
        $user = $users->createNewUser([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'active' => 1,
        ]);
        // Validate against the new user's details, not the signed-in administrator.
        $passwordResult = service('passwords')->check($data['password'], $user);
        if (! $passwordResult->isOK()) {
            return $this->failed($values, ['password' => $passwordResult->reason()]);
        }

        $db = db_connect($authConfig->DBGroup);
        $db->transBegin();
        try {
            $users->save($user);
            $user = $users->findById($users->getInsertID());
            $user->addGroup($data['role']);
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new RuntimeException('User creation transaction failed.');
            }
        } catch (Throwable) {
            $db->transRollback();
            log_message('error', 'Admin user creation failed.');

            return $this->failed($values, ['user' => 'The user could not be created. Please try again.']);
        }

        return redirect()->to(site_url('admin/users'))->with('success', 'The user has been created.');
    }

    public function update(int $id): RedirectResponse
    {
        $user = $this->findUser($id);
        $values = [
            'username' => trim((string) $this->request->getPost('username')),
            'email' => strtolower(trim((string) $this->request->getPost('email'))),
            'role' => $this->request->getPost('role'),
        ];
        $config = config('Auth');
        $usernameRules = $config->usernameValidationRules;
        $usernameRules['rules'][] = 'is_unique[' . $config->tables['users'] . '.username,id,' . $id . ']';
        $emailRules = $config->emailValidationRules;
        $identityId = $user->getEmailIdentity()?->id ?? 0;
        $emailRules['rules'][] = 'is_unique[' . $config->tables['identities'] . '.secret,id,' . $identityId . ']';

        if (! $this->validateData($values, [
            'username' => $usernameRules,
            'email' => $emailRules,
            'role' => ['label' => 'Role', 'rules' => 'required|in_list[jury,admin]'],
        ], [], $config->DBGroup)) {
            return $this->failed($values, $this->validator->getErrors(), $id);
        }

        // Keep an administrator from removing their own access to user management.
        if ((int) auth()->user()->id === $id && $values['role'] !== 'admin') {
            return $this->failed($values, ['role' => 'You cannot remove your own administrator role.'], $id);
        }

        $db = db_connect($config->DBGroup);
        $db->transBegin();
        try {
            $user->username = $values['username'];
            $user->email = $values['email'];
            auth()->getProvider()->save($user);
            $user->syncGroups($values['role']);
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new RuntimeException('User update transaction failed.');
            }
        } catch (Throwable) {
            $db->transRollback();
            log_message('error', 'Admin user update failed.');

            return $this->failed($values, ['user' => 'The user could not be updated. Please try again.'], $id);
        }

        return redirect()->to(site_url('admin/users'))->with('success', 'The user has been updated.');
    }

    public function confirmDelete(int $id): string
    {
        return service('twig')->render('admin/delete-user.html.twig', [
            'user' => $this->findUser($id),
            'isSelf' => (int) auth()->user()->id === $id,
        ]);
    }

    public function delete(int $id): RedirectResponse
    {
        $this->findUser($id);
        if ((int) auth()->user()->id === $id) {
            return redirect()->to(site_url('admin/users'))->with('error', 'You cannot delete your own administrator account.');
        }

        // Shield soft-deletes users so existing nominations keep their references.
        try {
            if (! auth()->getProvider()->delete($id)) {
                throw new RuntimeException('User deletion failed.');
            }
        } catch (Throwable) {
            log_message('error', 'Admin user deletion failed.');

            return redirect()->to(site_url('admin/users'))->with('error', 'The user could not be deleted. Please try again.');
        }

        return redirect()->to(site_url('admin/users'))->with('success', 'The user has been deleted.');
    }

    private function findUser(int $id): User
    {
        $user = auth()->getProvider()->findById($id);
        if ($user === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $user;
    }

    private function failed(array $values, array $errors, ?int $id = null): RedirectResponse
    {
        return redirect()->to(site_url($id === null ? 'admin/users' : 'admin/users/' . $id . '/edit'))
            ->with('userValues', $values)
            ->with('userErrors', $errors);
    }
}
