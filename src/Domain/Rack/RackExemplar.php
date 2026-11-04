<?php

declare(strict_types=1);

namespace App\Domain\Rack;

use DateTimeImmutable;

/**
 * Ein physisches Exemplar eines Rack-Typs (Migration 124), analog
 * CaseInventoryNumber bei Cases: eigene Rack-Nummer (RACK-0001 …), eigene
 * Inventurnummer (HERSTELLER-KÜRZEL-0001), eigene Bestückung (RackSlot) und
 * optional ein fest zugeordnetes Gerät, mit dem das Rack bei der Ausgabe
 * automatisch mitgeht.
 */
final class RackExemplar
{
    public function __construct(
        public readonly string $id,
        public readonly string $rackId,
        public string $rackNumber,
        public string $inventoryNumber,
        public ?string $fixedInventoryNumberId,
        public ?DateTimeImmutable $acquisitionDate,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
