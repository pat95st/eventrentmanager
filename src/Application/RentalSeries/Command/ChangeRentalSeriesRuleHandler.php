<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\RentalSeries\RentalCopier;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\RentalSeries\CopyScope;
use App\Domain\RentalSeries\RecurrenceCalculator;
use App\Domain\RentalSeries\RecurrenceRule;
use App\Domain\RentalSeries\RentalSeriesRepositoryInterface;
use DateTimeImmutable;

/** @return array{moved: int, created: int, deleted: int, kept: int} */
final class ChangeRentalSeriesRuleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SeriesSupport $support,
        private readonly RentalSeriesRepositoryInterface $seriesRepository,
        private readonly RentalRepositoryInterface $rentals,
        private readonly RecurrenceCalculator $calculator,
        private readonly RentalCopier $copier,
        private readonly ?DateTimeImmutable $today = null,
    ) {
    }

    public function handle(CommandInterface $command): array
    {
        assert($command instanceof ChangeRentalSeriesRule);
        $series = $this->support->series($command->seriesId);
        if ($series->isEnded()) {
            throw new ValidationException(['series' => 'Die Serie wurde beendet.']);
        }
        $today = ($this->today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $anchor = SeriesSupport::parseDate($command->firstDate);
        if ($anchor === null || $anchor <= $today) {
            throw new ValidationException(['firstDate' => 'Bitte den ersten Termin nach neuem Rhythmus angeben (nach heute).']);
        }
        $rule = RecurrenceRule::fromInput($command->ruleInput, $anchor);

        $occurrences = $this->support->occurrences($series->id);
        $fixedDates = [];
        $movable = [];
        $kept = 0;
        foreach ($occurrences as $occurrence) {
            if ($occurrence->startDate->setTime(0, 0) < $anchor) {
                continue;
            }
            if ($occurrence->seriesCustomized || $this->copier->isStarted($occurrence)) {
                $fixedDates[$occurrence->startDate->format('Y-m-d')] = true;
                $kept++;
            } else {
                $movable[] = $occurrence;
            }
        }

        $dates = array_values(array_filter(
            $this->calculator->sequence($rule, $anchor, $anchor->modify('+' . RecurrenceRule::MAX_YEARS . ' years'), RecurrenceRule::MAX_OCCURRENCES),
            static fn (DateTimeImmutable $d): bool => !isset($fixedDates[$d->format('Y-m-d')]),
        ));

        $moved = 0;
        $created = 0;
        $deleted = 0;
        $baseName = $occurrences !== [] ? $occurrences[0]->eventName : '';
        foreach ($dates as $i => $date) {
            if (isset($movable[$i])) {
                $this->copier->moveOccurrence($movable[$i], $date, $series, $baseName);
                $moved++;
                continue;
            }
            $current = $this->support->occurrences($series->id);
            $previous = $this->support->previousOf($current, $date) ?? ($current[0] ?? null);
            if ($previous === null) {
                break;
            }
            $this->copier->createOccurrence($previous, $series, $previous->eventName, $date, null, null, 9999, CopyScope::defaults());
            $created++;
        }
        for ($i = count($dates); $i < count($movable); $i++) {
            $this->rentals->delete($movable[$i]->id);
            $deleted++;
        }

        $series->rule = $rule;
        $series->anchorDate = $anchor;
        $series->updatedAt = new DateTimeImmutable();
        $this->seriesRepository->save($series);
        $this->support->renumber($series, fn ($r) => $this->copier->isStarted($r));

        return ['moved' => $moved, 'created' => $created, 'deleted' => $deleted, 'kept' => $kept];
    }
}
