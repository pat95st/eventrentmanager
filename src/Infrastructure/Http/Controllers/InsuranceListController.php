<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Insurance\Command\EstimateCurrentValue;
use App\Application\Insurance\Command\EstimateMissingCurrentValues;
use App\Application\Insurance\Command\EstimationSummary;
use App\Application\Insurance\Command\SaveInsuranceExportFields;
use App\Application\Insurance\Command\SetArticleFixedCurrentValue;
use App\Application\Insurance\Command\SetInventoryNumberCurrentValue;
use App\Application\Insurance\Estimation\CurrentValueEstimationException;
use App\Application\Insurance\Estimation\CurrentValueEstimatorInterface;
use App\Application\Insurance\Query\GetRentalInsuranceList;
use App\Application\Insurance\Query\RentalInsuranceList;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetCompanySettings;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Insurance\CurrentValueRepositoryInterface;
use App\Domain\Insurance\InsuranceField;
use App\Infrastructure\Csv\InsuranceListCsvExporter;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Infrastructure\Pdf\InsuranceListPdfGenerator;

/**
 * Versicherungsliste einer Veranstaltung (Migration 143): Übersicht mit
 * pflegbaren Zeitwerten, KI-Schätzung sowie CSV-/PDF-Export mit frei
 * wählbaren Spalten.
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

        $issuedIds = array_values(array_filter(array_map(static fn ($r) => $r->inventoryNumberId, $list->rows)));
        $articles = [];
        foreach ($list->rows as $row) {
            $articles[$row->articleId] ??= ['name' => $row->manufacturerModel, 'category' => $row->category, 'count' => 0];
            $articles[$row->articleId]['count']++;
        }

        echo View::page('rentals/insurance-list', [
            'list' => $list,
            'includeUnissued' => $includeUnissued,
            'selectedFields' => $this->currentValues->exportFields(),
            'itemValues' => $this->currentValues->forInventoryNumbers($issuedIds),
            'articles' => $articles,
            'fixedValues' => $this->currentValues->articleFixedValues(array_keys($articles)),
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

    /** Speichert geänderte Zeitwerte je Inventarnummer und feste Zeitwerte je Artikel. */
    public function saveValues(Request $request): void
    {
        $id = $request->param('id');
        $errors = [];
        $saved = 0;

        foreach ($this->changedValues($request, 'values') as $inventoryNumberId => $value) {
            try {
                $this->commands->dispatch(new SetInventoryNumberCurrentValue($inventoryNumberId, $value));
                $saved++;
            } catch (ValidationException | NotFoundException $e) {
                $errors[] = $e instanceof ValidationException ? implode(' ', $e->errors()) : $e->getMessage();
            }
        }
        foreach ($this->changedValues($request, 'fixed') as $articleId => $value) {
            try {
                $this->commands->dispatch(new SetArticleFixedCurrentValue($articleId, $value));
                $saved++;
            } catch (ValidationException | NotFoundException $e) {
                $errors[] = $e instanceof ValidationException ? implode(' ', $e->errors()) : $e->getMessage();
            }
        }

        if ($errors !== []) {
            Flash::error(implode(' ', array_unique($errors)));
        }
        if ($saved > 0 || $errors === []) {
            Flash::success($saved === 0 ? 'Keine Änderungen.' : $saved . ($saved === 1 ? ' Zeitwert gespeichert.' : ' Zeitwerte gespeichert.'));
        }

        Response::redirect($this->listUrl($id, $request));
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

    /** POST /inventurnummern/{id}/zeitwert — händisch (Detailseite der Inventarnummer). */
    public function saveInventoryNumberValue(Request $request): void
    {
        $id = $request->param('id');
        try {
            $this->commands->dispatch(new SetInventoryNumberCurrentValue($id, $request->input('current_value')));
            Flash::success('Zeitwert gespeichert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect($this->backUrl($request, '/inventurnummern/' . $id));
    }

    /** POST /inventurnummern/{id}/zeitwert/ki — Zeitwert eines Exemplars per KI schätzen. */
    public function estimateInventoryNumberValue(Request $request): void
    {
        $id = $request->param('id');
        try {
            $value = $this->commands->dispatch(new EstimateCurrentValue($id));
            Flash::success('KI-Schätzung gespeichert: ' . number_format((float) $value->value, 2, ',', '.') . ' €' . ($value->note !== null ? ' – ' . $value->note : ''));
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

    /**
     * Nur Felder, deren Wert sich gegenüber dem beim Laden angezeigten Wert
     * (Hidden-Feld "<name>_orig") geändert hat — so wird z. B. eine
     * unveränderte KI-Schätzung nicht beim Speichern zur händischen Angabe.
     *
     * @return array<string, string|null>
     */
    private function changedValues(Request $request, string $name): array
    {
        $values = $request->post[$name] ?? [];
        $originals = $request->post[$name . '_orig'] ?? [];
        if (!is_array($values)) {
            return [];
        }
        $changed = [];
        foreach ($values as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            $original = is_array($originals) && isset($originals[$key]) && is_scalar($originals[$key]) ? trim((string) $originals[$key]) : '';
            if ($value !== $original) {
                $changed[$key] = $value !== '' ? $value : null;
            }
        }

        return $changed;
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
