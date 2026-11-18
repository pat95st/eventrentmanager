<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\RentalSeries\RentalCopier;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Exception\ValidationException;
use App\Domain\RentalSeries\CopyScope;

/** @return array{copied: string[], skipped: string[]} names of the target rentals */
final class CopyRentalDataHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SeriesSupport $support,
        private readonly RentalCopier $copier,
    ) {
    }

    public function handle(CommandInterface $command): array
    {
        assert($command instanceof CopyRentalData);
        $scopes = CopyScope::fromValues($command->scopes);
        if ($scopes === []) {
            throw new ValidationException(['scopes' => 'Bitte mindestens einen Bereich zum Übernehmen auswählen.']);
        }
        $targetIds = array_values(array_unique(array_diff($command->targetRentalIds, [$command->sourceRentalId])));
        if ($targetIds === []) {
            throw new ValidationException(['targets' => 'Bitte mindestens einen Termin auswählen.']);
        }
        $source = $this->support->rental($command->sourceRentalId);

        $copied = [];
        $skipped = [];
        foreach ($targetIds as $targetId) {
            $target = $this->support->rental($targetId);
            if ($source->seriesId === null || $target->seriesId !== $source->seriesId) {
                throw new ValidationException(['targets' => 'Daten können nur zwischen Terminen derselben Serie übernommen werden.']);
            }
            $label = $target->eventName . ' (' . $target->startDate->format('d.m.Y') . ')';
            if ($this->copier->isStarted($target)) {
                $skipped[] = $label;
                continue;
            }
            $this->copier->copy($source, $target, $scopes);
            $copied[] = $label;
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }
}
