<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

final class AddRentalItem implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $articleId,
        public readonly string $quantity, // als String aus dem Formular, wird im Handler geprüft/konvertiert
    ) {
    }
}
