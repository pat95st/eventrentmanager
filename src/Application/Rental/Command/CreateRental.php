<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

final class CreateRental implements CommandInterface
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $startDate, // 'Y-m-d'
        public readonly string $endDate,   // 'Y-m-d'
        public readonly string $eventName,
        public readonly string $eventLocation, // Freitext; wird im Handler ggf. einem gespeicherten Veranstaltungsort zugeordnet
        public readonly string $deploymentDays, // als String aus dem Formular, wird im Handler geprüft
    ) {
    }
}
