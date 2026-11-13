<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\NominationModel;
use App\Models\NominationSettingsModel;
use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;

class Dashboard extends BaseController
{
    private const SETTINGS_RULES = [
        'starts_on' => ['label' => 'Start date', 'rules' => 'required|valid_date[Y-m-d]'],
        'ends_on'   => ['label' => 'End date', 'rules' => 'required|valid_date[Y-m-d]'],
        'attendance_confirmation_text' => ['label' => 'Attendance confirmation text', 'rules' => 'required|max_length[2000]'],
    ];

    public function index(): string
    {
        $settingsModel = new NominationSettingsModel();
        $settings      = $settingsModel->current();
        $values        = session()->getFlashdata('values') ?? $settings;
        $currentCount  = 0;

        if ($settings['starts_on'] !== null && $settings['ends_on'] !== null) {
            $currentCount = (new NominationModel())
                ->where('created_at >=', $settings['starts_on'] . ' 00:00:00')
                ->where('created_at <=', $settings['ends_on'] . ' 23:59:59')
                ->countAllResults();
        }

        return service('twig')->render('admin/dashboard.html.twig', [
            'settings'     => $settings,
            'values'       => $values,
            'periodOpen'   => $settingsModel->isOpen($settings),
            'currentCount' => $currentCount,
            'totalCount'   => (new NominationModel())->countAllResults(),
            'success'      => session()->getFlashdata('success'),
            'errors'       => session()->getFlashdata('errors') ?? [],
        ]);
    }

    public function updatePeriod(): RedirectResponse
    {
        $values = [
            'starts_on' => trim((string) $this->request->getPost('starts_on')),
            'ends_on'   => trim((string) $this->request->getPost('ends_on')),
            'attendance_confirmation_text' => trim((string) $this->request->getPost('attendance_confirmation_text')),
        ];
        $errors = [];
        if (! $this->validateData($values, self::SETTINGS_RULES)) {
            $errors = $this->validator->getErrors();
        } elseif (new DateTimeImmutable($values['starts_on']) > new DateTimeImmutable($values['ends_on'])) {
            $errors['ends_on'] = 'The end date must be on or after the start date.';
        }

        if ($errors !== []) {
            return redirect()->back()
                ->with('errors', $errors)
                ->with('values', $values);
        }

        $saved = (new NominationSettingsModel())->update(1, [
            'starts_on'  => $values['starts_on'],
            'ends_on'    => $values['ends_on'],
            'attendance_confirmation_text' => $values['attendance_confirmation_text'],
            'updated_by' => (int) auth()->id(),
        ]);

        if (! $saved) {
            return redirect()->back()
                ->with('errors', ['settings' => 'The form settings could not be saved. Please try again.'])
                ->with('values', $values);
        }

        return redirect()->to(site_url('admin'))
            ->with('success', 'The application form settings have been updated.');
    }
}
