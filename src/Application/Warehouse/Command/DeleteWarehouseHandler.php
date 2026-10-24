<?php

declare(strict_types=1);

namespace App\Application\Warehouse\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Warehouse\WarehouseRepositoryInterface;

/**
 * Bewusst OHNE "wird noch verwendet"-Sperre (anders als DeleteVenueHandler):
 * Rental::$pickupWarehouseId/$returnWarehouseId referenzieren einen Lagerort
 * nur lose (FOREIGN KEY ... ON DELETE SET NULL, siehe
 * 055_add_warehouses.sql) — ein gelöschter Lagerort lässt bestehende
 * Vermietungen also einfach ohne Lagerort-Zuordnung zurück, statt das
 * Löschen zu blockieren.
 */
final class DeleteWarehouseHandler implements CommandHandlerInterface
{
    public function __construct(private readonly WarehouseRepositoryInterface $warehouses)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteWarehouse);

        $warehouse = $this->warehouses->findById($command->id);
        if ($warehouse === null) {
            throw new NotFoundException("Lagerort \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->warehouses->delete($command->id);

        return null;
    }
}
