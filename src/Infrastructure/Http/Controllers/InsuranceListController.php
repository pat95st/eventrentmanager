<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Insurance\Command\EstimateCurrentValue;
use App\Application\Insurance\Command\EstimateMissingCurrentValues;
use App\Application\Insurance\Command\EstimationSummary;
use App\Application\Insurance\Command\SaveInsuranceExportFields;
use App\Application\Insurance\Estimation\CurrentValueEstimationException;
use App\Application\Insurance\Estimation\CurrentValueEstimatorInterface;
use App\Application\Insurance\Query\GetRentalInsuranceList;
use App\Application\Insurance\Query\RentalInsuranceList;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetCompanySettings;
use App\Domain\Exception\NotFoundException;
use App\Domain\Insurance\CurrentValueRepositoryInterface;
use App\Domain\Insurance\InsuranceField;
use App\Infrastructure\Csv\InsuranceListCsvExporter;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Infrastructure\Pdf\InsuranceListPdfGenerator;

/**
 * Versicherungsliste einer Veranstaltung (Migration 143): Übersicht, KI-
 * Schätzung fehlender Zeitwerte sowie CSV-/PDF-Export mit frei wählbaren
 * Spalten. Die Zeitwerte selbst werden an den Inventarnummern gepflegt
 * (Inventarnummer bearbeiten) bzw. als fester Wert am Artikel.
 */
final class InsuranceListController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly CurrentValueRepositoryInterface $currentValues,
        private readonly CurrentValueEstimatorInterface $estimator,
        private readonly InsuranceListCsvExporter $csvExporter,
        private readonly InsuranceListPdfGenerator $pdfGenerator,
    ) {
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');
        $includeUnissued = $request->query('unissued', '1') === '1';

        try {
            $list = $this->queries->ask(new GetRentalInsuranceList($id, $includeUnissued));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        assert($list instanceof RentalInsuranceList);

        echo View::page('rentals/insurance-list', [
            'list' => $list,
            'includeUnissued' => $includeUnissued,
            'selectedFields' => $this->currentValues->exportFields(),
            'aiAvailable' => $this->estimator->isAvailable(),
        ], activeNav: 'rentals', title: 'Versicherungsliste: ' . $list->rental->eventName);
    }

    public function exportCsv(Request $request): void
    {
        $this->export($request, 'csv');
    }

    public function exportPdf(Request $request): void
    {
        $this->export($request, 'pdf');
    }

    /** Schätzt alle fehlenden Zeitwerte der ausgegebenen Exemplare per KI. */
    public function estimateMissing(Request $request): void
    {
        $id = $request->param('id');
        @set_time_limit(300);

        try {
            $summary = $this->commands->dispatch(new EstimateMissingCurrentValues($id));
            assert($summary instanceof EstimationSummary);
            if ($summary->estimated === 0 && $summary->failed === 0 && $summary->remaining === 0) {
                Flash::success('Alle ausgegebenen Geräte haben bereits einen Zeitwert.');
            } else {
                $message = $summary->estimated . ' Zeitwert(e) per KI geschätzt.';
                if ($summary->remaining > 0) {
                    $message .= ' ' . $summary->remaining . ' weitere Gerät(e) wegen des Anfragelimits übersprungen – bitte erneut ausführen.';
                }
                Flash::success($message);
            }
            if ($summary->errors !== []) {
                Flash::error($summary->failed . ' Schätzung(en) fehlgeschlagen: ' . implode(' · ', array_slice($summary->errors, 0, 3)));
            }
        } catch (CurrentValueEstimationException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect($this->listUrl($id, $request));
    }

    /** POST /inventurnummern/{id}/zeitwert/ki — Zeitwert eines Exemplars per KI schätzen. */
    public function estimateInventoryNumberValue(Request $request): void
    {
        $id = $request->param('id');
        try {
            $inventoryNumber = $this->commands->dispatch(new EstimateCurrentValue($id));
            Flash::success('KI-Schätzung gespeichert: ' . number_format((float) $inventoryNumber->currentValue, 2, ',', '.') . ' €' . ($inventoryNumber->currentValueNote !== null ? ' – ' . $inventoryNumber->currentValueNote : ''));
        } catch (CurrentValueEstimationException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect($this->backUrl($request, '/inventurnummern/' . $id));
    }

    private function export(Request $request, string $format): void
    {
        $id = $request->param('id');
        $rawFields = $request->query['fields'] ?? null;
        $fields = is_array($rawFields) ? InsuranceField::fromList(array_filter($rawFields, 'is_scalar')) : $this->currentValues->exportFields();
        if ($fields === []) {
            Flash::error('Bitte mindestens eine Spalte für den Export auswählen.');
            Response::redirect($this->listUrl($id, $request));

            return;
        }
        if (is_array($rawFields) && $request->query('remember') === '1') {
            $this->commands->dispatch(new SaveInsuranceExportFields(array_map(static fn (InsuranceField $f) => $f->value, $fields)));
        }

        try {
            $list = $this->queries->ask(new GetRentalInsuranceList($id, $request->query('unissued', '1') === '1'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        assert($list instanceof RentalInsuranceList);

        $basename = 'Versicherungsliste-' . $this->sanitizeFilename($list->rental->eventName !== '' ? $list->rental->eventName : $list->rental->id);
        if ($format === 'csv') {
            Response::download($this->csvExporter->generate($list, $fields), $basename . '.csv', 'text/csv; charset=UTF-8');

            return;
        }

        $companySettings = $this->queries->ask(new GetCompanySettings());
        Response::inlinePdf($this->pdfGenerator->generate($list, $fields, $companySettings), $basename . '.pdf');
    }

    private function listUrl(string $rentalId, Request $request): string
    {
        return '/veranstaltungen/' . rawurlencode($rentalId) . '/versicherungsliste' . ($request->input('unissued') === '0' ? '?unissued=0' : '');
    }

    /** Nur relative Pfade innerhalb der Anwendung zulassen (kein Open Redirect). */
    private function backUrl(Request $request, string $fallback): string
    {
        $back = (string) $request->input('back', '');

        return preg_match('#^/(?!/)[A-Za-z0-9/_\-?=&.%]*$#', $back) === 1 ? $back : $fallback;
    }

    private function sanitizeFilename(string $value): string
    {
        $normalized = trim(preg_replace('/[^A-Za-z0-9_\-]+/', '-', $value) ?? '', '-');

        return $normalized !== '' ? $normalized : 'Veranstaltung';
    }
}
