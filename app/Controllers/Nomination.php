<?php

namespace App\Controllers;

use App\Models\NominationDocumentModel;
use App\Models\NominationModel;
use App\Models\NominationSettingsModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class Nomination extends BaseController
{
    /**
     * @var array<string, array{label: string, rules: string}>
     */
    private const RULES = [
        'category'                    => ['label' => 'Award category', 'rules' => 'required|in_list[application,leadership]'],
        'nominee_first_name'          => ['label' => 'Nominee first name', 'rules' => 'required|max_length[100]'],
        'nominee_last_name'           => ['label' => 'Nominee last name', 'rules' => 'required|max_length[100]'],
        'nominee_title'               => ['label' => 'Nominee title', 'rules' => 'required|max_length[150]'],
        'nominee_organization'        => ['label' => 'Nominee organization', 'rules' => 'required|max_length[180]'],
        'nominee_email'               => ['label' => 'Nominee email', 'rules' => 'permit_empty|valid_email|max_length[254]'],
        'nominee_phone'               => ['label' => 'Nominee phone', 'rules' => 'permit_empty|max_length[60]'],
        'nominee_country'             => ['label' => 'Nominee country', 'rules' => 'required|max_length[100]'],
        'nominee_biography'           => ['label' => 'Career biography', 'rules' => 'required|min_length[50]|max_length[5000]'],
        'nominee_contribution'        => ['label' => 'Contribution to robotics', 'rules' => 'required|min_length[50]|max_length[5000]'],
        'nominee_industry_service'    => ['label' => 'Service to the robotics industry', 'rules' => 'required|min_length[50]|max_length[5000]'],
        'nominee_innovation'          => ['label' => 'Innovation and impact', 'rules' => 'required|min_length[50]|max_length[5000]'],
        'reference_name'              => ['label' => 'Reference name', 'rules' => 'required|max_length[200]'],
        'reference_organization'      => ['label' => 'Reference organization', 'rules' => 'required|max_length[180]'],
        'reference_email'             => ['label' => 'Reference email', 'rules' => 'required|valid_email|max_length[254]'],
        'nominator_first_name'        => ['label' => 'Nominator first name', 'rules' => 'required|max_length[100]'],
        'nominator_last_name'         => ['label' => 'Nominator last name', 'rules' => 'required|max_length[100]'],
        'nominator_title'             => ['label' => 'Nominator title', 'rules' => 'required|max_length[150]'],
        'nominator_organization'      => ['label' => 'Nominator organization', 'rules' => 'required|max_length[180]'],
        'nominator_email'             => ['label' => 'Nominator email', 'rules' => 'required|valid_email|max_length[254]'],
        'nominator_phone'             => ['label' => 'Nominator phone', 'rules' => 'required|max_length[60]'],
        'relationship_to_nominee'     => ['label' => 'Relationship to nominee', 'rules' => 'required|max_length[500]'],
        'supporting_links'            => ['label' => 'Supporting links', 'rules' => 'permit_empty|max_length[4000]'],
        'eligibility_confirmed'       => ['label' => 'Eligibility confirmation', 'rules' => 'required|in_list[1]'],
        'attendance_confirmed'        => ['label' => 'Attendance confirmation', 'rules' => 'required|in_list[1]'],
        'privacy_consent'             => ['label' => 'Privacy consent', 'rules' => 'required|in_list[1]'],
    ];

    private const MAX_DOCUMENTS = 3;

    private const MAX_DOCUMENT_SIZE = 20 * 1024 * 1024;

    private const ALLOWED_UPLOAD_TYPES = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'mp4'  => ['video/mp4', 'application/mp4'],
        'mov'  => ['video/quicktime'],
        'webm' => ['video/webm'],
        'avi'  => ['video/x-msvideo', 'video/avi', 'video/msvideo'],
        'zip'  => ['application/zip'],
    ];

    public function form(): string
    {
        return $this->renderForm();
    }

    public function create(): ResponseInterface|RedirectResponse
    {
        $values    = $this->request->getPost();
        $documents = $this->uploadedDocuments();

        if (! (new NominationSettingsModel())->isOpen()) {
            return $this->response
                ->setStatusCode(403)
                ->setBody($this->renderForm($values));
        }

        $errors = [];
        if (! $this->validateData($values, self::RULES)) {
            $errors = $this->validator->getErrors();
        }

        $documentError = $this->validateDocuments($documents);
        if ($documentError !== null) {
            $errors['documents'] = $documentError;
        }

        if ($errors !== []) {
            return $this->response
                ->setStatusCode(422)
                ->setBody($this->renderForm($values, $errors));
        }

        $userId      = auth()->user()?->id;
        $db          = db_connect();
        $storedFiles = [];
        $directory   = null;
        $db->transBegin();

        try {
            $model        = new NominationModel();
            $nominationId = $model->insert($this->nominationData($values, $userId !== null ? (int) $userId : null), true);
            if ($nominationId === false) {
                throw new RuntimeException('The nomination could not be stored.');
            }

            if ($documents !== []) {
                $directory = WRITEPATH . 'uploads/nominations/' . $nominationId;
                if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                    throw new RuntimeException('The upload directory could not be created.');
                }

                $documentModel = new NominationDocumentModel();
                foreach ($documents as $document) {
                    $storedName   = $document->getRandomName();
                    $originalName = mb_substr(basename($document->getClientName()), 0, 255);
                    $mimeType     = $document->getMimeType();
                    $size         = $document->getSize();

                    if (! $document->move($directory, $storedName)) {
                        throw new RuntimeException('An uploaded document could not be stored.');
                    }

                    $storedFiles[] = $directory . DIRECTORY_SEPARATOR . $storedName;
                    if ($documentModel->insert([
                        'nomination_id' => (int) $nominationId,
                        'original_name' => $originalName,
                        'stored_name'   => $storedName,
                        'mime_type'     => $mimeType,
                        'size_bytes'    => $size,
                        'created_at'    => date('Y-m-d H:i:s'),
                    ]) === false) {
                        throw new RuntimeException('The document metadata could not be stored.');
                    }
                }
            }

            if (! $db->transCommit()) {
                throw new RuntimeException('The nomination transaction could not be committed.');
            }
        } catch (Throwable $exception) {
            $db->transRollback();
            $this->removeStoredFiles($storedFiles, $directory);
            log_message('error', 'Nomination upload failed: {message}', ['message' => $exception->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setBody($this->renderForm($values, ['documents' => 'The nomination could not be stored. Please try again.']));
        }

        $mailSent = $this->sendConfirmation($values, (int) $nominationId);

        return redirect()
            ->to(site_url('thank-you'))
            ->with('nominationSubmitted', true)
            ->with('nominationId', $nominationId)
            ->with('confirmationMailSent', $mailSent);
    }

    public function thankYou(): string|RedirectResponse
    {
        if (! session()->getFlashdata('nominationSubmitted')) {
            return redirect()->to(site_url('/'));
        }

        return service('twig')->render('nomination/thank-you.html.twig', [
            'nominationId' => session()->getFlashdata('nominationId'),
            'mailSent'     => session()->getFlashdata('confirmationMailSent'),
        ]);
    }

    /**
     * @param array<string, mixed>  $values
     * @param array<string, string> $errors
     */
    private function renderForm(array $values = [], array $errors = []): string
    {
        $settingsModel = new NominationSettingsModel();
        $settings      = $settingsModel->current();

        return service('twig')->render('nomination/form.html.twig', [
            'values'          => $values,
            'errors'          => $errors,
            'period'          => $settings,
            'nominationsOpen' => $settingsModel->isOpen($settings),
        ]);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function nominationData(array $values, ?int $userId): array
    {
        $fields = [
            'category',
            'nominee_first_name',
            'nominee_last_name',
            'nominee_title',
            'nominee_organization',
            'nominee_email',
            'nominee_phone',
            'nominee_country',
            'nominee_biography',
            'nominee_contribution',
            'nominee_industry_service',
            'nominee_innovation',
            'reference_name',
            'reference_organization',
            'reference_email',
            'nominator_first_name',
            'nominator_last_name',
            'nominator_title',
            'nominator_organization',
            'nominator_email',
            'nominator_phone',
            'relationship_to_nominee',
            'supporting_links',
        ];

        $data = ['user_id' => $userId];
        foreach ($fields as $field) {
            $data[$field] = trim((string) ($values[$field] ?? ''));
        }

        $data['eligibility_confirmed'] = 1;
        $data['attendance_confirmed']  = 1;
        $data['privacy_consent']       = 1;

        return $data;
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedDocuments(): array
    {
        $documents = $this->request->getFileMultiple('documents') ?? [];

        return array_values(array_filter(
            $documents,
            static fn (UploadedFile $document): bool => $document->getError() !== UPLOAD_ERR_NO_FILE,
        ));
    }

    /**
     * @param list<UploadedFile> $documents
     */
    private function validateDocuments(array $documents): ?string
    {
        if (count($documents) > self::MAX_DOCUMENTS) {
            return 'You may upload a maximum of 3 documents.';
        }

        foreach ($documents as $document) {
            $name = basename($document->getClientName());

            if (in_array($document->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                return 'Each document may be no larger than 20 MB.';
            }

            if (! $document->isValid()) {
                return 'The document "' . $name . '" could not be uploaded.';
            }

            if ($document->getSize() > self::MAX_DOCUMENT_SIZE) {
                return 'Each document may be no larger than 20 MB.';
            }

            $extension = strtolower($document->getClientExtension());
            $mimeType  = $document->getMimeType();
            if (! isset(self::ALLOWED_UPLOAD_TYPES[$extension])
                || ! in_array($mimeType, self::ALLOWED_UPLOAD_TYPES[$extension], true)
            ) {
                return 'Only PDF, DOC, DOCX, ODT, MP4, MOV, WebM, AVI and ZIP files are allowed.';
            }
        }

        return null;
    }

    /**
     * @param list<string> $storedFiles
     */
    private function removeStoredFiles(array $storedFiles, ?string $directory): void
    {
        foreach ($storedFiles as $storedFile) {
            if (is_file($storedFile)) {
                unlink($storedFile);
            }
        }

        if ($directory !== null && is_dir($directory)) {
            rmdir($directory);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function sendConfirmation(array $values, int $nominationId): bool
    {
        $documents = (new NominationDocumentModel())
            ->where('nomination_id', $nominationId)
            ->orderBy('id', 'ASC')
            ->findAll();

        foreach ($documents as &$document) {
            $size = (int) $document['size_bytes'];
            $document['size_label'] = $size >= 1024 * 1024
                ? number_format($size / (1024 * 1024), 1) . ' MB'
                : number_format(max($size / 1024, 0.1), 1) . ' KB';
        }
        unset($document);

        $email = service('email');
        $email->setTo((string) $values['nominator_email']);
        $email->setSubject('IFR Award nomination received');
        $email->setMessage(service('twig')->render('emails/nomination-confirmation.html.twig', [
            'nominationId' => $nominationId,
            'nominator'    => trim($values['nominator_first_name'] . ' ' . $values['nominator_last_name']),
            'nominee'      => trim($values['nominee_first_name'] . ' ' . $values['nominee_last_name']),
            'category'     => ucfirst((string) $values['category']),
            'data'         => $values,
            'documents'    => $documents,
        ]));

        if (! $email->send()) {
            log_message('error', 'Nomination confirmation email could not be sent for submission {id}.', [
                'id' => $nominationId,
            ]);

            return false;
        }

        return true;
    }
}
