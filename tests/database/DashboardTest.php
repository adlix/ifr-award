<?php

use App\Controllers\Admin\Nominations;
use App\Controllers\Admin\Settings;
use App\Models\NominationSettingsModel;
use CodeIgniter\Shield\Filters\GroupFilter;
use CodeIgniter\Shield\Filters\PermissionFilter;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class DashboardTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $namespace = ['CodeIgniter\\Shield', 'CodeIgniter\\Settings', 'App'];
    protected $migrateOnce = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('nomination_documents')->emptyTable();
        $this->db->table('nominations')->emptyTable();
    }

    private function signIn(string $role): void
    {
        helper(['auth', 'url', 'form']);
        $users = auth()->getProvider();
        $name = uniqid('reviewer');
        $user = $users->createNewUser(['username' => $name, 'email' => $name . '@example.com', 'password' => 'Q8!pR3#vN9@tW5k', 'active' => 1]);
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($role);
        $this->actingAs($user);
    }

    public function testJuryDashboardCombinesStatsAndFilteredNominationsWithoutSettings(): void
    {
        $this->signIn('jury');
        (new NominationSettingsModel())->update(1, ['starts_on' => '2028-01-01', 'ends_on' => '2028-12-31']);
        foreach (['2027-12-31 23:59:59', '2028-01-01 00:00:00', '2028-12-31 23:59:59', '2029-01-01 00:00:00'] as $date) {
            $this->db->table('nominations')->insert([
                'nominee_first_name' => 'Test', 'nominee_last_name' => 'Candidate',
                'nominee_title' => 'Director', 'nominee_organization' => 'Robotics',
                'nominee_country' => 'Germany', 'created_at' => $date,
            ]);
        }
        $request = service('request');
        $request->setGlobal('get', ['period' => '2027']);
        $controller = new Nominations();
        $controller->initController($request, service('response'), service('logger'));
        $html = $controller->index();
        $this->assertStringContainsString('<h1>Nomination dashboard</h1>', $html);
        $this->assertStringContainsString('<strong class="stat-number">2</strong>', $html);
        $this->assertStringContainsString('<strong class="stat-number">4</strong>', $html);
        $this->assertStringContainsString('1</strong> nomination', $html);
        $this->assertStringContainsString('2027', $html);
        $this->assertStringNotContainsString('href="' . site_url('admin/settings') . '"', $html);
        $this->assertStringNotContainsString('name="starts_on"', $html);
        $this->assertNull((new PermissionFilter())->before($request, ['award.review']));
        $this->assertNotNull((new GroupFilter())->before($request, ['admin', 'superadmin']));
    }

    public function testAdminSettingsAreSeparateAndSavingReturnsToSettings(): void
    {
        $this->signIn('admin');
        $request = service('request');
        $this->assertNull((new GroupFilter())->before($request, ['admin', 'superadmin']));
        $controller = new Settings();
        $controller->initController($request, service('response'), service('logger'));
        $html = $controller->index();
        $this->assertStringContainsString('Application form settings', $html);
        $this->assertStringContainsString('href="' . site_url('admin/settings') . '"', $html);
        $this->assertStringContainsString('action="' . site_url('admin/settings') . '"', $html);
        $this->assertStringNotContainsString('stat-card', $html);
        service('validation')->reset();
        $request->setGlobal('post', ['starts_on' => '2028-01-01', 'ends_on' => '2028-12-31', 'attendance_confirmation_text' => 'Attend both events.']);
        $response = $controller->updatePeriod();
        $this->assertSame(site_url('admin/settings'), $response->getHeaderLine('Location'));
        $this->assertSame('Attend both events.', (new NominationSettingsModel())->current()['attendance_confirmation_text']);
    }

    public function testSuperadminCanAccessTheSharedDashboard(): void
    {
        $this->signIn('superadmin');
        $this->assertNull((new PermissionFilter())->before(service('request'), ['award.review']));
    }

    public function testDashboardWithoutDatesShowsNoConfiguredPeriod(): void
    {
        $this->signIn('jury');
        (new NominationSettingsModel())->delete(1);
        $request = service('request');
        $request->setGlobal('get', []);
        $controller = new Nominations();
        $controller->initController($request, service('response'), service('logger'));
        $html = $controller->index();
        $this->assertStringContainsString('No submission period configured', $html);
        $this->assertStringContainsString('No nominations yet', $html);
        $this->assertStringContainsString('Closed', $html);
    }
}
