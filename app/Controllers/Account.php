<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Validation\ValidationRules;

class Account extends BaseController
{
    public function index(): string
    {
        $user = auth()->user();

        return service('twig')->render('account.html.twig', [
            'email'    => $user?->getEmail(),
            'canTestSmtp' => $user?->inGroup('admin', 'superadmin') ?? false,
            'username' => $user?->username,
            'success'  => session()->getFlashdata('success'),
            'error'    => session()->getFlashdata('error'),
            'passwordErrors' => session()->getFlashdata('passwordErrors') ?? [],
        ]);
    }

    public function changePassword(): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to(site_url('login'));
        }

        $rules = new ValidationRules();
        $data = $this->request->getPost(['current_password', 'password', 'password_confirm']);

        if (! $this->validateData($data, [
            'current_password' => ['label' => 'Current password', 'rules' => 'required'],
            'password' => $rules->getPasswordRules(),
            'password_confirm' => $rules->getPasswordConfirmRules(),
        ])) {
            return redirect()->to(site_url('account'))->with('passwordErrors', $this->validator->getErrors());
        }

        $passwords = service('passwords');
        $hash = $user->getPasswordHash();
        if ($hash === null || ! $passwords->verify($data['current_password'], $hash)) {
            return redirect()->to(site_url('account'))->with('passwordErrors', [
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $result = $passwords->check($data['password'], $user);
        if (! $result->isOK()) {
            return redirect()->to(site_url('account'))->with('passwordErrors', [
                'password' => $result->reason(),
            ]);
        }

        $user->password = $data['password'];
        auth()->getProvider()->save($user);
        session()->regenerate(true);

        return redirect()->to(site_url('account'))->with('success', 'Your password has been changed.');
    }

    public function sendTestEmail(): RedirectResponse
    {
        $user  = auth()->user();
        $email = service('email');
        $email->setTo((string) $user?->getEmail());
        $email->setSubject('SMTP-Test erfolgreich');
        $email->setMessage(service('twig')->render('emails/test.html.twig', [
            'username' => $user?->username,
        ]));

        if (! $email->send()) {
            log_message('error', 'SMTP test email could not be sent.');

            return redirect()->back()->with('error', 'Die Testmail konnte nicht versendet werden.');
        }

        return redirect()->back()->with('success', 'Die Testmail wurde versendet.');
    }
}
