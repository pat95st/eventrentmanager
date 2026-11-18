<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\RentalSeries\Command\ChangeRentalSeriesRule;
use App\Application\RentalSeries\Command\CopyRentalData;
use App\Application\RentalSeries\Command\CreateRentalSeries;
use App\Application\RentalSeries\Command\DetachRentalFromSeries;
use App\Application\RentalSeries\Command\EndRentalSeries;
use App\Application\RentalSeries\Command\ExtendRentalSeries;
use App\Application\RentalSeries\Query\GetRentalSeries;
use App\Application\RentalSeries\Query\GetRentalSeriesContext;
use App\Application\RentalSeries\Query\PreviewRentalCopy;
use App\Application\RentalSeries\Query\PreviewRentalSeries;
use App\Application\RentalSeries\Query\RentalSeriesContext;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\RentalSeries\CopyScope;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Recurring events (migration 176): create a series from a rental, series
 * overview, extend, change rhythm, end, detach and take data over between
 * occurrences.
 */
final class RentalSeriesController
{
    private const RULE_KEYS = ['type', 'interval', 'month_day', 'week_of_month', 'weekday', 'manual_dates', 'end_mode', 'ends_on', 'occurrence_count'];

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly RentalRepositoryInterface $rentals,
    ) {
    }

    public function create(Request $request): void
    {
        $rental = $this->rentals->findById($request->param('id'));
        if ($rental === null) {
            Response::notFound('Die Veranstaltung wurde nicht gefunden.');

            return;
        }
        if ($rental->seriesId !== null) {
            Response::redirect('/serien/' . $rental->seriesId);

            return;
        }
        echo View::page('rentals/series/create', [
            'rental' => $rental,
            'rule' => ['type' => 'weekly', 'interval' => '1', 'weekdays' => [$rental->startDate->format('N')], 'end_mode' => 'count', 'occurrence_count' => '10'],
            'nameTemplate' => '',
            'errors' => [],
        ], activeNav: 'rentals', title: 'Wiederkehrend machen – ' . $rental->eventName);
    }

    public function preview(Request $request): void
    {
        $rentalId = $request->param('id');
        $rule = $this->ruleInput($request);
        $nameTemplate = $request->input('name_template', '') ?? '';
        try {
            $preview = $this->queries->ask(new PreviewRentalSeries($rentalId, $rule, $nameTemplate));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ValidationException $e) {
            $rental = $this->rentals->findById($rentalId);
            if ($rental === null) {
                Response::notFound();

                return;
            }
            echo View::page('rentals/series/create', ['rental' => $rental, 'rule' => $rule, 'nameTemplate' => $nameTemplate, 'errors' => $e->errors()], activeNav: 'rentals', title: 'Wiederkehrend machen – ' . $rental->eventName);

            return;
        }
        echo View::page('rentals/series/preview', [
            'preview' => $preview,
            'ruleInput' => $rule,
            'nameTemplate' => $nameTemplate,
            'scopes' => array_map(static fn (CopyScope $s) => $s->value, CopyScope::defaults()),
            'rowsInput' => null,
            'errors' => [],
        ], activeNav: 'rentals', title: 'Vorschau der Termine – ' . $preview->source->eventName);
    }

    public function store(Request $request): void
    {
        $rentalId = $request->param('id');
        $rule = $this->ruleInput($request);
        $nameTemplate = $request->input('name_template', '') ?? '';
        $scopes = $request->inputArray('scopes');
        $rows = [];
        foreach (is_array($request->post['rows'] ?? null) ? $request->post['rows'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = static fn (string $key): string => is_scalar($row[$key] ?? null) ? trim((string) $row[$key]) : '';
            $rows[] = [
                'start' => $value('start'),
                'end' => $value('end'),
                'location' => $value('location'),
                'include' => $value('include') === '1',
                'default_start' => $value('default_start'),
                'default_end' => $value('default_end'),
            ];
        }

        try {
            $seriesId = (string) $this->commands->dispatch(new CreateRentalSeries($rentalId, $rule, $nameTemplate, $scopes, $rows));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ValidationException $e) {
            try {
                $preview = $this->queries->ask(new PreviewRentalSeries($rentalId, $rule, $nameTemplate));
            } catch (ValidationException) {
                Flash::error(implode(' ', $e->errors()));
                Response::redirect('/veranstaltungen/' . $rentalId . '/serie/neu');

                return;
            }
            echo View::page('rentals/series/preview', [
                'preview' => $preview,
                'ruleInput' => $rule,
                'nameTemplate' => $nameTemplate,
                'scopes' => $scopes,
                'rowsInput' => $rows,
                'errors' => $e->errors(),
            ], activeNav: 'rentals', title: 'Vorschau der Termine – ' . $preview->source->eventName);

            return;
        }
        $count = count(array_filter($rows, static fn ($r) => $r['include']));
        Flash::success("Serie angelegt: {$count} weitere Termine wurden erstellt.");
        Response::redirect('/serien/' . $seriesId);
    }

    public function show(Request $request): void
    {
        try {
            $overview = $this->queries->ask(new GetRentalSeries($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        $first = $overview->occurrences[0]['rental'] ?? null;
        echo View::page('rentals/series/show', ['overview' => $overview], activeNav: 'rentals', title: 'Serie – ' . ($first?->eventName ?? ''));
    }

    public function extend(Request $request): void
    {
        $seriesId = $request->param('id');
        try {
            $result = $this->commands->dispatch(new ExtendRentalSeries(
                $seriesId,
                $request->input('end_mode', 'count') ?? 'count',
                $request->input('ends_on', '') ?? '',
                $request->input('count', '') ?? '',
                $request->input('manual_dates', '') ?? '',
                $request->inputArray('scopes'),
            ));
            Flash::success($result['created'] === 1 ? '1 weiterer Termin wurde erstellt.' : $result['created'] . ' weitere Termine wurden erstellt.');
            if ($result['skipped'] !== []) {
                Flash::error('Übersprungen: ' . implode(' · ', $result['skipped']));
            }
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }
        Response::redirect('/serien/' . $seriesId);
    }

    public function editRule(Request $request): void
    {
        try {
            $overview = $this->queries->ask(new GetRentalSeries($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        $rule = $overview->series->rule;
        $nextDate = null;
        foreach ($overview->occurrences as $row) {
            if ($row['rental']->startDate > new \DateTimeImmutable('today')) {
                $nextDate = $row['rental']->startDate;
                break;
            }
        }
        echo View::page('rentals/series/rule', [
            'overview' => $overview,
            'firstDate' => ($nextDate ?? new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
            'rule' => [
                'type' => $rule->type->value,
                'interval' => (string) $rule->interval,
                'weekdays' => array_map('strval', $rule->weekdays),
                'month_day' => (string) ($rule->monthDay ?? ''),
                'week_of_month' => (string) ($rule->weekOfMonth ?? ''),
                'weekday' => (string) ($rule->weekday ?? ''),
                'manual_dates' => implode("\n", array_map(static fn (string $d) => (new \DateTimeImmutable($d))->format('d.m.Y'), $rule->manualDates)),
                'end_mode' => $rule->occurrenceCount !== null ? 'count' : 'date',
                'ends_on' => $rule->endsOn?->format('Y-m-d') ?? '',
                'occurrence_count' => (string) ($rule->occurrenceCount ?? ''),
            ],
            'errors' => [],
        ], activeNav: 'rentals', title: 'Rhythmus ändern');
    }

    public function updateRule(Request $request): void
    {
        $seriesId = $request->param('id');
        $rule = $this->ruleInput($request);
        $firstDate = $request->input('first_date', '') ?? '';
        try {
            $result = $this->commands->dispatch(new ChangeRentalSeriesRule($seriesId, $firstDate, $rule));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ValidationException $e) {
            $overview = $this->queries->ask(new GetRentalSeries($seriesId));
            echo View::page('rentals/series/rule', ['overview' => $overview, 'firstDate' => $firstDate, 'rule' => $rule, 'errors' => $e->errors()], activeNav: 'rentals', title: 'Rhythmus ändern');

            return;
        }
        Flash::success(sprintf(
            'Rhythmus geändert: %d Termine verschoben, %d neu angelegt, %d gelöscht, %d unverändert (begonnen oder von Hand angepasst).',
            $result['moved'],
            $result['created'],
            $result['deleted'],
            $result['kept'],
        ));
        Response::redirect('/serien/' . $seriesId);
    }

    public function end(Request $request): void
    {
        $seriesId = $request->param('id');
        try {
            $result = $this->commands->dispatch(new EndRentalSeries($seriesId, $request->input('delete_future') === '1'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Flash::success($request->input('delete_future') === '1'
            ? "Serie beendet, {$result['deleted']} zukünftige Termine gelöscht."
            : "Serie beendet, {$result['detached']} zukünftige Termine bleiben als normale Veranstaltungen bestehen.");
        Response::redirect('/serien/' . $seriesId);
    }

    public function detach(Request $request): void
    {
        $rentalId = $request->param('id');
        try {
            $this->commands->dispatch(new DetachRentalFromSeries($rentalId));
            Flash::success('Die Veranstaltung wurde aus der Serie gelöst.');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }
        Response::redirect('/veranstaltungen/' . $rentalId);
    }

    /** Form: choose areas (and, for "folgende", the target occurrences). */
    public function copyForm(Request $request): void
    {
        $rentalId = $request->param('id');
        $direction = $request->query('richtung') === 'folgende' ? 'folgende' : 'vorher';
        $context = $this->context($rentalId);
        if ($context === null) {
            return;
        }
        if ($direction === 'vorher' && $context->previous === null) {
            Flash::error('Dies ist der erste Termin der Serie – es gibt keinen vorherigen Termin.');
            Response::redirect('/veranstaltungen/' . $rentalId);

            return;
        }
        if ($direction === 'folgende' && $context->following === []) {
            Flash::error('Dies ist der letzte Termin der Serie – es gibt keine folgenden Termine.');
            Response::redirect('/veranstaltungen/' . $rentalId);

            return;
        }
        echo View::page('rentals/series/copy', [
            'rental' => $this->rentals->findById($rentalId),
            'context' => $context,
            'direction' => $direction,
            'scopes' => array_map(static fn (CopyScope $s) => $s->value, CopyScope::defaults()),
            'targets' => array_map(static fn ($r) => $r->id, $context->following),
            'errors' => [],
        ], activeNav: 'rentals', title: $direction === 'vorher' ? 'Aus vorherigem Termin übernehmen' : 'Auf folgende Termine übertragen');
    }

    public function copyPreview(Request $request): void
    {
        $rentalId = $request->param('id');
        $direction = $request->input('richtung') === 'folgende' ? 'folgende' : 'vorher';
        $context = $this->context($rentalId);
        if ($context === null) {
            return;
        }
        [$sourceId, $targetIds] = $this->copyEnds($rentalId, $direction, $context, $request->inputArray('targets'));
        $scopes = $request->inputArray('scopes');
        try {
            $preview = $this->queries->ask(new PreviewRentalCopy($sourceId, $targetIds, $scopes));
        } catch (ValidationException $e) {
            echo View::page('rentals/series/copy', [
                'rental' => $this->rentals->findById($rentalId),
                'context' => $context,
                'direction' => $direction,
                'scopes' => $scopes,
                'targets' => $targetIds,
                'errors' => $e->errors(),
            ], activeNav: 'rentals', title: 'Daten übernehmen');

            return;
        }
        echo View::page('rentals/series/copy-preview', ['preview' => $preview, 'rentalId' => $rentalId, 'direction' => $direction], activeNav: 'rentals', title: 'Vorschau: Daten übernehmen');
    }

    public function copy(Request $request): void
    {
        $rentalId = $request->param('id');
        $direction = $request->input('richtung') === 'folgende' ? 'folgende' : 'vorher';
        $context = $this->context($rentalId);
        if ($context === null) {
            return;
        }
        [$sourceId, $targetIds] = $this->copyEnds($rentalId, $direction, $context, $request->inputArray('targets'));
        try {
            $result = $this->commands->dispatch(new CopyRentalData($sourceId, $targetIds, $request->inputArray('scopes')));
            if ($result['copied'] !== []) {
                Flash::success('Daten übernommen in: ' . implode(', ', $result['copied']) . '.');
            }
            if ($result['skipped'] !== []) {
                Flash::error('Nicht geändert, weil bereits Material ausgegeben wurde: ' . implode(', ', $result['skipped']) . '.');
            }
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }
        Response::redirect('/veranstaltungen/' . $rentalId);
    }

    /**
     * @param string[] $selectedTargets
     * @return array{0: string, 1: string[]} source id, target ids
     */
    private function copyEnds(string $rentalId, string $direction, RentalSeriesContext $context, array $selectedTargets): array
    {
        if ($direction === 'vorher') {
            return [$context->previous?->id ?? '', [$rentalId]];
        }
        $allowed = array_map(static fn ($r) => $r->id, $context->following);

        return [$rentalId, array_values(array_intersect($selectedTargets, $allowed))];
    }

    private function context(string $rentalId): ?RentalSeriesContext
    {
        $context = $this->queries->ask(new GetRentalSeriesContext($rentalId));
        if (!$context instanceof RentalSeriesContext) {
            Flash::error('Diese Veranstaltung gehört zu keiner Serie.');
            Response::redirect('/veranstaltungen/' . $rentalId);

            return null;
        }

        return $context;
    }

    /** @return array<string, mixed> */
    private function ruleInput(Request $request): array
    {
        $rule = [];
        foreach (self::RULE_KEYS as $key) {
            $rule[$key] = $request->input($key, '') ?? '';
        }
        $rule['weekdays'] = $request->inputArray('weekdays');

        return $rule;
    }
}
