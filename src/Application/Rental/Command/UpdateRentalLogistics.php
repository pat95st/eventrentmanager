<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

/**
 * Eigenständiges Kommando statt Teil von UpdateRental: die
 * Transport-/Logistik-Zuordnung (Lagerorte + Termine für Ausgabe und
 * Rücklieferung) ist optionale Nebendaten, die unabhängig von den
 * Kopfdaten (Kunde, Zeitraum, Veranstaltungsort) auf der Detailseite
 * gepflegt werden (siehe rentals/show.php, Abschnitt "Logistik").
 */
final class UpdateRentalLogistics implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly ?string $pickupWarehouseId,
        public readonly ?string $pickupAt,
        public readonly ?string $returnWarehouseId,
        public readonly ?string $returnAt,
        public readonly ?string $transportNote,
    ) {
    }
}
