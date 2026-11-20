<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\TravelCost\DocumentTravelCostService;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\TravelCost\TravelCostDocumentType;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Travel cost section of offers, order confirmations and invoices
 * (migration 182): edit page, take over from the tour, switch the mode and
 * remove. The document type comes from the route (see bootstrap.php).
 */
final class DocumentTravelCostController
{
    public function __construct(private readonly DocumentTravelCostService $service)
    {
    }

    public function edit(Request $request, TravelCostDocumentType $type): void
    {
        $id = $request->param('id');
        try {
            $document = $this->service->document($type, $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        $section = $this->service->find($type, $id);
        if ($section === null || !$document['editable']) {
            Response::redirect($this->documentUrl($type, $id));

            return;
        }
        echo View::page('documents/travel-costs', [
            'type' => $type,
            'documentId' => $id,
            'document' => $document,
            'section' => $section,
            'errors' => [],
            'old' => null,
        ], activeNav: $this->nav($type), title: 'Fahrtkosten – ' . $type->label() . ' ' . $document['number']);
    }

    public function save(Request $request, TravelCostDocumentType $type): void
    {
        $id = $request->param('id');
        $dates = $request->inputArray('line_date');
        $descriptions = $request->inputArray('line_description');
        $details = $request->inputArray('line_detail');
        $kms = $request->inputArray('line_km');
        $rates = $request->inputArray('line_rate');
        $rows = [];
        foreach ($descriptions as $i => $description) {
            $rows[] = ['date' => $dates[$i] ?? '', 'description' => $description, 'detail' => $details[$i] ?? '', 'km' => $kms[$i] ?? '', 'rate' => $rates[$i] ?? ''];
        }
        try {
            $this->service->saveLines($type, $id, $rows);
            Flash::success('Die Fahrtkosten wurden gespeichert.');
        } catch (ValidationException $e) {
            $document = $this->service->document($type, $id);
            $section = $this->service->find($type, $id);
            if ($section === null) {
                Response::redirect($this->documentUrl($type, $id));

                return;
            }
            echo View::page('documents/travel-costs', [
                'type' => $type,
                'documentId' => $id,
                'document' => $document,
                'section' => $section,
                'errors' => $e->errors(),
                'old' => $rows,
            ], activeNav: $this->nav($type), title: 'Fahrtkosten – ' . $type->label() . ' ' . $document['number']);

            return;
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect($this->documentUrl($type, $id));
    }

    public function importFromTour(Request $request, TravelCostDocumentType $type): void
    {
        $id = $request->param('id');
        $this->run(fn () => $this->service->importFromTour($type, $id), 'Die Fahrtkosten wurden aus der Tour übernommen.');
        Response::redirect($this->documentUrl($type, $id));
    }

    public function setMode(Request $request, TravelCostDocumentType $type): void
    {
        $id = $request->param('id');
        $this->run(fn () => $this->service->setMode($type, $id, $request->input('mode', '') ?? ''), 'Die Darstellung der Fahrtkosten wurde umgestellt.');
        Response::redirect('/' . $type->pathSegment() . '/' . $id . '/fahrtkosten');
    }

    public function remove(Request $request, TravelCostDocumentType $type): void
    {
        $id = $request->param('id');
        $this->run(fn () => $this->service->remove($type, $id), 'Die Fahrtkosten wurden aus dem Dokument entfernt.');
        Response::redirect($this->documentUrl($type, $id));
    }

    private function run(callable $work, string $success): void
    {
        try {
            $work();
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', array_unique($e->errors())));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
    }

    private function documentUrl(TravelCostDocumentType $type, string $id): string
    {
        return '/' . $type->pathSegment() . '/' . $id;
    }

    private function nav(TravelCostDocumentType $type): string
    {
        return match ($type) {
            TravelCostDocumentType::Offer => 'offers',
            TravelCostDocumentType::OrderConfirmation => 'order_confirmations',
            TravelCostDocumentType::Invoice => 'invoices',
        };
    }
}
