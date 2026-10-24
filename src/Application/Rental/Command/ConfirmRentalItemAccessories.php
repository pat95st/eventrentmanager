<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

/** Bestätigt "Vollständigkeit geprüft" für das Zubehör eines Postens (Modal auf der Ausgabe-Checkliste). */
final class ConfirmRentalItemAccessories implements CommandInterface
{
    public function __construct(public readonly string $rentalItemId)
    {
    }
}
