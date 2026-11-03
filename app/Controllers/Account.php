<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Account extends BaseController
{
    public function index(): string
    {
        $user = auth()->user();

        return service('twig')->render('account.html.twig', [
            'email'    => $user?->getEmail(),
            'username' => $user?->username,
            'success'  => session()->getFlashdata('success'),
            'error'    => session()->getFlashdata('error'),
        ]);
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
