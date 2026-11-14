<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Nomination::form');
$routes->post('/', 'Nomination::create');
$routes->get('thank-you', 'Nomination::thankYou');
$routes->get('nominate', 'Nomination::form');
$routes->post('nominate', 'Nomination::create');
$routes->get('nominate/thank-you', 'Nomination::thankYou');

$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('account', 'Account::index');
    $routes->post('account/password', 'Account::changePassword');
    $routes->post('account/test-email', 'Account::sendTestEmail');
});

$routes->group('admin', ['filter' => 'permission:admin.access'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Admin\\Dashboard::index');
    $routes->post('nomination-period', 'Admin\\Dashboard::updatePeriod');
});

$routes->group('admin', ['filter' => 'permission:users.manage-admins'], static function (RouteCollection $routes): void {
    $routes->get('users', 'Admin\\Users::index');
    $routes->post('users', 'Admin\\Users::create');
});

$routes->group('admin', ['filter' => 'permission:award.review'], static function (RouteCollection $routes): void {
    $routes->get('nominations', 'Admin\\Nominations::index');
    $routes->get('nominations/(:num)', 'Admin\\Nominations::show/$1');
    $routes->get('nominations/(:num)/documents/(:num)', 'Admin\\Nominations::download/$1/$2');
});

service('auth')->routes($routes, ['except' => ['register']]);
