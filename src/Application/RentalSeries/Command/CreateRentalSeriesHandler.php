<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\RentalSeries\OccurrenceChecker;
use App\Application\RentalSeries\RentalCopier;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\RentalSeries\CopyScope;
use App\Domain\RentalSeries\RecurrenceRule;
use App\Domain\RentalSeries\RecurrenceType;
use App\Domain\RentalSeries\RentalSeries;
use App\Domain\RentalSeries\RentalSeriesRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/** @return string id of the new series */
final class CreateRentalSeriesHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SeriesSupport $support,
        private readonly RentalSeriesRepositoryInterface $seriesRepository,
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalCopier $copier,
        private readonly OccurrenceChecker $checker,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateRentalSeries);
        $source = $this->support->rental($command->sourceRentalId);
        if ($source->seriesId !== null) {
            throw new ValidationException(['series' => 'Diese Veranstaltung gehört bereits zu einer Serie.']);
        }
        $anchor = $source->startDate->setTime(0, 0);
        $rule = RecurrenceRule::fromInput($command->ruleInput, $anchor);
        $scopes = CopyScope::fromValues($command->scopes);

        $errors = [];
        $planned = [];
        foreach ($command->rows as $i => $row) {
            if (!$row['include']) {
                continue;
            }
            $start = SeriesSupport::parseDate($row['start']);
            $end = SeriesSupport::parseDate($row['end']);
            $label = 'Zeile ' . ($i + 1);
            if ($start === null || $end === null) {
                $errors['row_' . $i] = "{$label}: Bitte gültige Daten angeben.";
                continue;
            }
            if ($end < $start) {
                $errors['row_' . $i] = "{$label}: Das Enddatum liegt vor dem Startdatum.";
                continue;
            }
            if ($start <= $anchor) {
                $errors['row_' . $i] = "{$label}: Der Termin muss nach der Ursprungsveranstaltung ({$anchor->format('d.m.Y')}) liegen.";
                continue;
            }
            $key = $start->format('Y-m-d');
            if (isset($planned[$key])) {
                $errors['row_' . $i] = "{$label}: Am {$start->format('d.m.Y')} ist bereits ein Termin geplant.";
                continue;
            }
            $blocking = $this->checker->check(OccurrenceChecker::candidate($source, $start, $end), [], [$source->id])['blocking'];
            if ($blocking !== null) {
                $errors['row_' . $i] = "{$label} ({$start->format('d.m.Y')}): {$blocking}";
                continue;
            }
            $location = trim($row['location']);
            $planned[$key] = [
                'start' => $start,
                'end' => $end,
                'location' => $location !== trim((string) $source->eventLocation) ? $location : null,
                'customized' => $row['start'] !== $row['default_start'] || $row['end'] !== $row['default_end'],
            ];
        }
        if ($errors === [] && $planned === []) {
            $errors['rows'] = 'Bitte mindestens einen weiteren Termin auswählen.';
        }
        if (count($planned) > RecurrenceRule::MAX_OCCURRENCES) {
            $errors['rows'] = 'Es können höchstens ' . RecurrenceRule::MAX_OCCURRENCES . ' Termine auf einmal angelegt werden.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        ksort($planned);

        if ($rule->type === RecurrenceType::Manual) {
            $rule = $rule->withManualDates(array_keys($planned));
        }
        $now = new DateTimeImmutable();
        $template = trim($command->nameTemplate);
        $series = new RentalSeries(Uuid::v4(), $rule, $anchor, $template !== '' ? mb_substr($template, 0, 255) : null, $source->id, null, $now, $now);
        $this->seriesRepository->save($series);

        $baseName = $source->eventName;
        $source->seriesId = $series->id;
        $source->seriesIndex = 1;
        $source->eventName = $series->nameFor($baseName, $source->startDate, 1);
        $source->updatedAt = $now;
        $this->rentals->save($source);

        $index = 2;
        foreach ($planned as $occurrence) {
            $this->copier->createOccurrence(
                $source,
                $series,
                $baseName,
                $occurrence['start'],
                $occurrence['end'],
                $occurrence['location'],
                $index++,
                $scopes,
                $occurrence['customized'],
            );
        }

        return $series->id;
    }
}
