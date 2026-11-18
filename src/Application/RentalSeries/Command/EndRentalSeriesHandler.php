<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\RentalSeries\RentalCopier;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\RentalSeries\RentalSeriesRepositoryInterface;
use DateTimeImmutable;

/** @return array{deleted: int, detached: int} */
final class EndRentalSeriesHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SeriesSupport $support,
        private readonly RentalSeriesRepositoryInterface $seriesRepository,
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalCopier $copier,
        private readonly ?DateTimeImmutable $today = null,
    ) {
    }

    public function handle(CommandInterface $command): array
    {
        assert($command instanceof EndRentalSeries);
        $series = $this->support->series($command->seriesId);
        $today = ($this->today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $deleted = 0;
        $detached = 0;
        foreach ($this->support->occurrences($series->id) as $occurrence) {
            if ($occurrence->startDate->setTime(0, 0) <= $today) {
                continue;
            }
            if ($command->deleteFutureOccurrences && !$this->copier->isStarted($occurrence)) {
                $this->rentals->delete($occurrence->id);
                $deleted++;
                continue;
            }
            if (!$command->deleteFutureOccurrences) {
                $occurrence->seriesId = null;
                $occurrence->seriesIndex = null;
                $occurrence->seriesCustomized = false;
                $occurrence->updatedAt = new DateTimeImmutable();
                $this->rentals->save($occurrence);
                $detached++;
            }
        }
        $series->endedAt = new DateTimeImmutable();
        $series->updatedAt = $series->endedAt;
        $this->seriesRepository->save($series);

        return ['deleted' => $deleted, 'detached' => $detached];
    }
}
