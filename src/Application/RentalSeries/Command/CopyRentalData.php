<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandInterface;

/**
 * "Aus vorherigem Termin übernehmen" / "Auf folgende Termine übertragen":
 * replaces the selected areas of the target occurrences with the data of
 * the source occurrence (all rentals of the same series).
 */
final class CopyRentalData implements CommandInterface
{
    /**
     * @param string[] $targetRentalIds
     * @param string[] $scopes CopyScope values
     */
    public function __construct(
        public readonly string $sourceRentalId,
        public readonly array $targetRentalIds,
        public readonly array $scopes,
    ) {
    }
}
