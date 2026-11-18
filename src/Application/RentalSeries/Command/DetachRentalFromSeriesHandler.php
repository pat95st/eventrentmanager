<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\RentalSeries\RentalCopier;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use DateTimeImmutable;

/** @return string id of the series the rental belonged to */
final class DetachRentalFromSeriesHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SeriesSupport $support,
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalCopier $copier,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof DetachRentalFromSeries);
        $rental = $this->support->rental($command->rentalId);
        $seriesId = $rental->seriesId ?? throw new ValidationException(['series' => 'Diese Veranstaltung gehört zu keiner Serie.']);
        $rental->seriesId = null;
        $rental->seriesIndex = null;
        $rental->seriesCustomized = false;
        $rental->updatedAt = new DateTimeImmutable();
        $this->rentals->save($rental);
        $this->support->renumber($this->support->series($seriesId), fn ($r) => $this->copier->isStarted($r));

        return $seriesId;
    }
}
