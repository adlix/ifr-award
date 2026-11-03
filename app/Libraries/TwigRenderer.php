<?php

namespace App\Libraries;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class TwigRenderer
{
    private Environment $twig;

    public function __construct()
    {
        $loader = new FilesystemLoader(APPPATH . 'Views');
        $cache  = ENVIRONMENT === 'production' ? WRITEPATH . 'cache/twig' : false;

        $this->twig = new Environment($loader, [
            'cache'       => $cache,
            'auto_reload' => ENVIRONMENT !== 'production',
            'autoescape'  => 'html',
        ]);
        $this->twig->addFunction(new TwigFunction('site_url', site_url(...)));
        $this->twig->addFunction(new TwigFunction('csrf_field', csrf_field(...), [
            'is_safe' => ['html'],
        ]));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $user = auth()->user();
        $data += [
            'loggedIn'       => $user !== null,
            'currentUsername' => $user?->username,
            'isAdmin'        => $user?->can('admin.access') ?? false,
            'isReviewer'     => $user?->can('award.review') ?? false,
        ];

        return $this->twig->render($template, $data);
    }
}
