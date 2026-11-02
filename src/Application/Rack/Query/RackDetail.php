<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Rack\Rack;

/**
 * Rack mit aufgelösten Einbauten (Migration 115).
 *
 * $slots: je Einbau slotId, inventoryNumberId, number, label ("Hersteller
 * Artikel"), startUnit, endUnit, heightUnits.
 * $fixedDevice: fest zugeordnetes Gerät (number, label) oder null.
 * $issuedIn: Veranstaltung, in der das fest zugeordnete Gerät gerade
 * ausgegeben ist (das Rack ist dann mit unterwegs), sonst null.
 */
final class RackDetail
{
    /**
     * @param list<array{slotId: string, inventoryNumberId: string, number: string, label: string, startUnit: int, endUnit: int, heightUnits: int}> $slots
     * @param array{inventoryNumberId: string, number: string, label: string}|null $fixedDevice
     * @param array{rentalId: string, eventName: string}|null $issuedIn
     */
    public function __construct(
        public readonly Rack $rack,
        public readonly ?Manufacturer $manufacturer,
        public readonly array $slots,
        public readonly ?array $fixedDevice,
        public readonly ?array $issuedIn,
    ) {
    }

    public function usedUnits(): int
    {
        return array_sum(array_map(static fn (array $slot) => $slot['heightUnits'], $this->slots));
    }

    /** @return array<int, array{slotId: string, number: string, label: string, startUnit: int, endUnit: int, heightUnits: int}|null> HE => Einbau, der dort beginnt (null = frei, belegte Folge-HE fehlen) */
    public function unitMap(): array
    {
        $map = [];
        $byStart = [];
        foreach ($this->slots as $slot) {
            $byStart[$slot['startUnit']] = $slot;
        }
        for ($unit = 1; $unit <= $this->rack->heightUnits; $unit++) {
            if (isset($byStart[$unit])) {
                $map[$unit] = $byStart[$unit];
                $unit = $byStart[$unit]['endUnit'];
                continue;
            }
            $map[$unit] = null;
        }

        return $map;
    }
}
