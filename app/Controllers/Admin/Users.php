<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Validation\ValidationRules;
use RuntimeException;
use Throwable;

class Users extends BaseController
{
    public function index(): string
    {
        $users = auth()->getProvider()->withIdentities()->withGroups()->orderBy('id', 'DESC')->findAll();

        return service('twig')->render('admin/users.html.twig', [
            'users' => $users,
            'values' => session()->getFlashdata('userValues') ?? [],
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

    private function failed(array $values, array $errors): RedirectResponse
    {
        return redirect()->to(site_url('admin/users'))
            ->with('userValues', $values)
            ->with('userErrors', $errors);
    }
}
