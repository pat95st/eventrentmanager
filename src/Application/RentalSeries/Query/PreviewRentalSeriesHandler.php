<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\RentalSeries\OccurrenceChecker;
use App\Application\RentalSeries\PlannedMaterialReadModelInterface;
use App\Application\RentalSeries\SeriesPreviewRow;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Exception\ValidationException;
use App\Domain\RentalSeries\RecurrenceCalculator;
use App\Domain\RentalSeries\RecurrenceRule;
use App\Domain\RentalSeries\RentalSeries;
use DateTimeImmutable;

final class PreviewRentalSeriesHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly SeriesSupport $support,
        private readonly RecurrenceCalculator $calculator,
        private readonly OccurrenceChecker $checker,
        private readonly ?PlannedMaterialReadModelInterface $planned = null,
    ) {
    }

    public function handle(QueryInterface $query): RentalSeriesPreview
    {
        assert($query instanceof PreviewRentalSeries);
        $source = $this->support->rental($query->sourceRentalId);
        if ($source->seriesId !== null) {
            throw new ValidationException(['series' => 'Diese Veranstaltung gehört bereits zu einer Serie.']);
        }
        $anchor = $source->startDate->setTime(0, 0);
        $rule = RecurrenceRule::fromInput($query->ruleInput, $anchor);
        $dates = array_slice(
            $this->calculator->sequence($rule, $anchor, $anchor->modify('+' . RecurrenceRule::MAX_YEARS . ' years'), RecurrenceRule::MAX_OCCURRENCES + 1),
            1,
        );
        if ($dates === []) {
            throw new ValidationException(['type' => 'Mit diesem Rhythmus ergibt sich kein weiterer Termin.']);
        }

        $template = trim($query->nameTemplate);
        $naming = new RentalSeries('preview', $rule, $anchor, $template !== '' ? $template : null, $source->id, null, new DateTimeImmutable(), new DateTimeImmutable());
        $material = $this->planned?->plannedByRental($source->id) ?? [];

        $rows = [];
        foreach ($dates as $i => $date) {
            $candidate = OccurrenceChecker::candidate($source, $date);
            $check = $this->checker->check($candidate, $material, [$source->id]);
            $rows[] = new SeriesPreviewRow(
                startDate: $candidate->startDate,
                endDate: $candidate->endDate,
                name: $naming->nameFor($source->eventName, $date, $i + 2),
                location: (string) $source->eventLocation,
                warnings: $check['warnings'],
                conflicts: $check['conflicts'],
                suggestSkip: $check['holiday'] || $check['blocking'] !== null,
            );
        }

        return new RentalSeriesPreview($source, $rule, $rows);
    }
}
