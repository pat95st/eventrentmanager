<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

use DateTimeImmutable;

/** Filter of the logbook list (migration 184); null = no restriction. */
final class LogbookFilter
{
    public function __construct(
        public readonly ?DateTimeImmutable $from = null,
        public readonly ?DateTimeImmutable $to = null,
        public readonly ?string $vehicleId = null,
        public readonly ?string $driverUserId = null,
        public readonly ?LogbookCategory $category = null,
        public readonly ?string $rentalId = null,
        public readonly ?bool $external = null,
        public readonly ?LogbookStatus $status = null,
        // Only trips of this user (recorded by or for them): drivers without "Fahrtenbuch verwalten".
        public readonly ?string $ownUserId = null,
        public readonly bool $includeDeleted = false,
    ) {
    }
}
