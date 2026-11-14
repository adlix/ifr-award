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
    $routes->post('account/test-email', 'Account::sendTestEmail', ['filter' => 'group:admin,superadmin']);
});

$routes->group('admin', ['filter' => 'group:admin,superadmin'], static function (RouteCollection $routes): void {
    $routes->get('settings', 'Admin\\Settings::index');
    $routes->post('settings', 'Admin\\Settings::updatePeriod');
    $routes->post('nomination-period', 'Admin\\Settings::updatePeriod');
});

$routes->group('admin', ['filter' => 'permission:users.manage-admins'], static function (RouteCollection $routes): void {
    $routes->get('users', 'Admin\\Users::index');
    $routes->post('users', 'Admin\\Users::create');
    $routes->get('users/(:num)/edit', 'Admin\\Users::edit/$1');
    $routes->post('users/(:num)', 'Admin\\Users::update/$1');
    $routes->get('users/(:num)/delete', 'Admin\\Users::confirmDelete/$1');
    $routes->post('users/(:num)/delete', 'Admin\\Users::delete/$1');
});

$routes->group('admin', ['filter' => 'permission:award.review'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Admin\\Nominations::index');
    $routes->get('nominations', 'Admin\\Nominations::index');
    $routes->get('nominations/(:num)', 'Admin\\Nominations::show/$1');
    $routes->get('nominations/(:num)/documents/(:num)', 'Admin\\Nominations::download/$1/$2');
});

service('auth')->routes($routes, ['except' => ['register']]);
