<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

final class UpdateRental implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $eventName,
        public readonly string $eventLocation, // Freitext; wird im Handler ggf. einem gespeicherten Veranstaltungsort zugeordnet
        public readonly string $deploymentDays,
        // Migration 130: exklusiv (andere Vermietungen blockieren), Abholung/
        // Rückgabe (Pflicht bei Betriebsurlaub, 'Y-m-d\TH:i' oder leer) und
        // Bestätigung des Betriebsurlaub-Hinweises.
        public readonly bool $blocksOtherRentals = false,
        public readonly string $pickupAt = '',
        public readonly string $returnAt = '',
        public readonly bool $holidayConfirmed = false,
    ) {
    }
}
