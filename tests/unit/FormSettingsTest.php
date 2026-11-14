<?php

use App\Controllers\Admin\Settings;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class FormSettingsTest extends CIUnitTestCase
{
    public function testAttendanceTextIsRequiredAndLimited(): void
    {
        $rules = (new ReflectionClass(Settings::class))->getConstant('SETTINGS_RULES');
        $values = ['starts_on' => '2027-01-01', 'ends_on' => '2027-03-31'];
        $validation = Services::validation();

        foreach (['', str_repeat('x', 2001)] as $text) {
            $validation->reset()->setRules($rules);
            $this->assertFalse($validation->run($values + ['attendance_confirmation_text' => $text]));
            $this->assertArrayHasKey('attendance_confirmation_text', $validation->getErrors());
        }

        $validation->reset()->setRules($rules);
        $this->assertTrue($validation->run($values + ['attendance_confirmation_text' => str_repeat('x', 2000)]));
    }

    public function testCustomTextAppearsInFormAdminAndEmail(): void
    {
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['layout.html.twig' => '{% block content %}{% endblock %}']),
            new FilesystemLoader(APPPATH . 'Views'),
        ]), ['autoescape' => 'html']);
        $twig->addFunction(new TwigFunction('site_url', static fn ($path) => '/' . $path));
        $twig->addFunction(new TwigFunction('csrf_field', static fn () => ''));

        $text = 'Attend the session on 12 May 2028 & the ceremony on 20 June 2028. <b>Confirm</b>';
        $settings = [
            'starts_on' => '2028-01-01',
            'ends_on' => '2028-03-31',
            'attendance_confirmation_text' => $text,
        ];
        $context = [
            'errors' => [], 'values' => $settings, 'settings' => $settings,
            'period' => $settings, 'nominationsOpen' => true,
            'attendanceConfirmationText' => $text, 'data' => [], 'documents' => [],
        ];

        foreach (['nomination/form.html.twig', 'admin/settings.html.twig', 'emails/nomination-confirmation.html.twig'] as $template) {
            $html = $twig->render($template, $context);
            $this->assertStringContainsString('12 May 2028 &amp; the ceremony on 20 June 2028.', $html);
            $this->assertStringContainsString('&lt;b&gt;Confirm&lt;/b&gt;', $html);
            $this->assertStringNotContainsString('19 April 2027', $html);
        }
    }
}
