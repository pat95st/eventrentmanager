<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\RentalSeries\OccurrenceChecker;
use App\Application\RentalSeries\RentalCopier;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Exception\ValidationException;
use App\Domain\RentalSeries\CopyScope;
use App\Domain\RentalSeries\RecurrenceCalculator;
use App\Domain\RentalSeries\RecurrenceRule;
use App\Domain\RentalSeries\RecurrenceType;
use App\Domain\RentalSeries\RentalSeriesRepositoryInterface;
use DateTimeImmutable;

/** @return array{created: int, skipped: string[]} */
final class ExtendRentalSeriesHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SeriesSupport $support,
        private readonly RentalSeriesRepositoryInterface $seriesRepository,
        private readonly RecurrenceCalculator $calculator,
        private readonly RentalCopier $copier,
        private readonly OccurrenceChecker $checker,
    ) {
    }

    public function handle(CommandInterface $command): array
    {
        assert($command instanceof ExtendRentalSeries);
        $series = $this->support->series($command->seriesId);
        if ($series->isEnded()) {
            throw new ValidationException(['series' => 'Die Serie wurde beendet und kann nicht verlängert werden.']);
        }
        $occurrences = $this->support->occurrences($series->id);
        if ($occurrences === []) {
            throw new ValidationException(['series' => 'Die Serie hat keine Termine mehr, von denen Daten übernommen werden können.']);
        }
        $last = end($occurrences);
        $lastDate = $last->startDate->setTime(0, 0);

        if ($series->rule->type === RecurrenceType::Manual) {
            $extra = RecurrenceRule::fromInput(['type' => 'manual', 'manual_dates' => $command->manualDates], $lastDate);
            $dates = array_map(static fn (string $d) => new DateTimeImmutable($d), array_slice($extra->manualDates, 0, RecurrenceRule::MAX_OCCURRENCES));
            $newRule = $series->rule->withManualDates(array_merge($series->rule->manualDates, $extra->manualDates));
        } elseif ($command->endMode === 'count') {
            $count = filter_var(trim($command->count), FILTER_VALIDATE_INT);
            if ($count === false || $count < 1 || $count > RecurrenceRule::MAX_OCCURRENCES) {
                throw new ValidationException(['count' => 'Bitte zwischen 1 und ' . RecurrenceRule::MAX_OCCURRENCES . ' weitere Termine angeben.']);
            }
            $dates = $this->calculator->datesAfter($series->rule->withEnd(null, null), $series->anchorDate, $lastDate, $count);
            $newRule = $dates !== [] ? $series->rule->withEnd(end($dates), null) : $series->rule;
        } else {
            $endsOn = SeriesSupport::parseDate($command->endsOn);
            if ($endsOn === null || $endsOn <= $lastDate) {
                throw new ValidationException(['endsOn' => 'Bitte ein Enddatum nach dem letzten Termin (' . $lastDate->format('d.m.Y') . ') angeben.']);
            }
            if ($endsOn > $lastDate->modify('+' . RecurrenceRule::MAX_YEARS . ' years')) {
                throw new ValidationException(['endsOn' => 'Höchstens ' . RecurrenceRule::MAX_YEARS . ' Jahre nach dem letzten Termin.']);
            }
            $newRule = $series->rule->withEnd($endsOn, null);
            $dates = $this->calculator->datesAfter($newRule, $series->anchorDate, $lastDate);
        }
        if ($dates === []) {
            throw new ValidationException(['series' => 'Im gewählten Zeitraum ergibt sich kein weiterer Termin.']);
        }

        $scopes = CopyScope::fromValues($command->scopes);
        $skipped = [];
        $created = 0;
        $index = max(array_map(static fn ($r) => $r->seriesIndex ?? 0, $occurrences)) + 1;
        $previous = $last;
        foreach ($dates as $date) {
            $blocking = $this->checker->check(OccurrenceChecker::candidate($previous, $date), [], [$previous->id])['blocking'];
            if ($blocking !== null) {
                $skipped[] = $date->format('d.m.Y') . ': ' . $blocking;
                continue;
            }
            $previous = $this->copier->createOccurrence($previous, $series, $previous->eventName, $date, null, null, $index++, $scopes);
            $created++;
        }

        $series->rule = $newRule;
        $series->updatedAt = new DateTimeImmutable();
        $this->seriesRepository->save($series);

        return ['created' => $created, 'skipped' => $skipped];
    }
}
