<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\NominationDocumentModel;
use App\Models\NominationModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class Nominations extends BaseController
{
    public function index(): string
    {
        $nominationModel = new NominationModel();
        $submittedDates  = $nominationModel
            ->select('created_at')
            ->where('created_at IS NOT NULL', null, false)
            ->findAll();
        $periods = [];

        foreach ($submittedDates as $submittedDate) {
            $year = (int) substr((string) $submittedDate['created_at'], 0, 4);
            if ($year > 0) {
                $periods[$year] = $year;
            }
        }

        krsort($periods);
        $periods        = array_values($periods);
        $requestedYear  = (int) $this->request->getGet('period');
        $selectedPeriod = in_array($requestedYear, $periods, true)
            ? $requestedYear
            : ($periods[0] ?? null);
        $nominations = [];

        if ($selectedPeriod !== null) {
            $nominations = $nominationModel
                ->select('id, category, solution_name, nominee_first_name, nominee_last_name, nominee_title, nominee_organization, nominee_country, nominator_first_name, nominator_last_name, created_at')
                ->where('created_at >=', $selectedPeriod . '-01-01 00:00:00')
                ->where('created_at <', ($selectedPeriod + 1) . '-01-01 00:00:00')
                ->orderBy('created_at', 'DESC')
                ->findAll();

            $documentCounts = $this->documentCounts(array_column($nominations, 'id'));
            foreach ($nominations as &$nomination) {
                $nomination['category_label'] = $nomination['category'] ? ucfirst($nomination['category']) : null;
                $nomination['document_count'] = $documentCounts[(int) $nomination['id']] ?? 0;
            }
            unset($nomination);
        }

        return service('twig')->render('admin/nominations/index.html.twig', [
            'periods'        => $periods,
            'selectedPeriod' => $selectedPeriod,
            'nominations'    => $nominations,
        ]);
    }

    public function show(int $nominationId): string
    {
        $nomination = (new NominationModel())->find($nominationId);
        if ($nomination === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $nomination['category_label'] = $nomination['category'] ? ucfirst($nomination['category']) : null;
        $documents = (new NominationDocumentModel())
            ->where('nomination_id', $nominationId)
            ->orderBy('id', 'ASC')
            ->findAll();

        foreach ($documents as &$document) {
            $document['size_label'] = $this->formatFileSize((int) $document['size_bytes']);
        }
        unset($document);

        return service('twig')->render('admin/nominations/show.html.twig', [
            'nomination' => $nomination,
            'documents'  => $documents,
        ]);
    }

    public function download(int $nominationId, int $documentId): ResponseInterface
    {
        $document = (new NominationDocumentModel())
            ->where('id', $documentId)
            ->where('nomination_id', $nominationId)
            ->first();

        if ($document === null || basename((string) $document['stored_name']) !== $document['stored_name']) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = WRITEPATH . 'uploads/nominations/' . $nominationId . '/' . $document['stored_name'];
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->download($path, null)
            ->setFileName((string) $document['original_name']);
    }

    /**
     * @param list<int|string> $nominationIds
     *
     * @return array<int, int>
     */
    private function documentCounts(array $nominationIds): array
    {
        if ($nominationIds === []) {
            return [];
        }

        $rows = (new NominationDocumentModel())
            ->select('nomination_id, COUNT(*) AS document_count')
            ->whereIn('nomination_id', $nominationIds)
            ->groupBy('nomination_id')
            ->findAll();
        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['nomination_id']] = (int) $row['document_count'];
        }

        return $counts;
    }

    private function formatFileSize(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? number_format($bytes / (1024 * 1024), 1) . ' MB'
            : number_format(max($bytes / 1024, 0.1), 1) . ' KB';
    }
}
