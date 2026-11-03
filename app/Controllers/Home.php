<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        $user = auth()->user();

        return service('twig')->render('home.html.twig', [
            'loggedIn' => $user !== null,
            'username' => $user?->username,
        ]);
    }
}
