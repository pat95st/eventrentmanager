<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Domain\Rack\RackExemplar;
use App\Domain\Rack\RackWidth;

/**
 * Rack-Exemplar mit aufgelöster Bestückung (Migration 124).
 *
 * $slots: je Einbau slotId, inventoryNumberId, number, label ("Hersteller
 * Artikel"), startUnit, endUnit, heightUnits und side (Migration 128:
 * 'left' | 'right' bei einem 9,5-Zoll-Gerät im 19-Zoll-Rack, sonst null =
 * ganze Breite).
 * $fixedDevice: fest zugeordnetes Gerät (number, label) oder null.
 * $issuedIn: Veranstaltung, in der das fest zugeordnete Gerät gerade
 * ausgegeben ist (das Rack ist dann mit unterwegs), sonst null.
 */
final class RackExemplarDetail
{
    /**
     * @param list<array{slotId: string, inventoryNumberId: string, number: string, label: string, startUnit: int, endUnit: int, heightUnits: int, side?: ?string}> $slots
     * @param array{inventoryNumberId: string, number: string, label: string}|null $fixedDevice
     * @param array{rentalId: string, eventName: string}|null $issuedIn
     */
    public function __construct(
        public readonly RackExemplar $exemplar,
        public readonly int $heightUnits,
        public readonly array $slots,
        public readonly ?array $fixedDevice,
        public readonly ?array $issuedIn,
        public readonly RackWidth $width = RackWidth::Full,
    ) {
    }

    /** Kann das Rack zwei Geräte nebeneinander aufnehmen (19 Zoll)? */
    public function hasHalves(): bool
    {
        return $this->width === RackWidth::Full;
    }

    /**
     * Belegung je HE und Hälfte: [HE => ['left' => bool, 'right' => bool]].
     *
     * @return array<int, array{left: bool, right: bool}>
     */
    private function occupancy(): array
    {
        $map = [];
        for ($unit = 1; $unit <= $this->heightUnits; $unit++) {
            $map[$unit] = ['left' => false, 'right' => false];
        }
        foreach ($this->slots as $slot) {
            $side = $slot['side'] ?? null;
            for ($unit = $slot['startUnit']; $unit <= min($slot['endUnit'], $this->heightUnits); $unit++) {
                if ($side === null || $side === 'left') {
                    $map[$unit]['left'] = true;
                }
                if ($side === null || $side === 'right') {
                    $map[$unit]['right'] = true;
                }
            }
        }

        return $map;
    }

    /** Anzahl HE mit mindestens einem eingebauten Gerät. */
    public function usedUnits(): int
    {
        return count(array_filter($this->occupancy(), static fn (array $u) => $u['left'] || $u['right']));
    }

    /** Anzahl komplett freier HE. */
    public function freeUnits(): int
    {
        return $this->heightUnits - $this->usedUnits();
    }

    /** Anzahl HE, in denen bei einem 19-Zoll-Rack noch eine Hälfte (9,5 Zoll) frei ist. */
    public function halfFreeUnits(): int
    {
        if (!$this->hasHalves()) {
            return 0;
        }

        return count(array_filter($this->occupancy(), static fn (array $u) => $u['left'] !== $u['right']));
    }

    /**
     * Zellen für die Darstellung als Raster (Zeile = HE, Spalten = Hälften):
     * je Zelle row (Start-HE), span (Anzahl HE), column ('full' | 'left' |
     * 'right') und slot (Einbau oder null = frei). Freie HE werden einzeln
     * ausgegeben; sind bei einem 19-Zoll-Rack beide Hälften frei, als eine
     * Zelle über die ganze Breite.
     *
     * @return list<array{row: int, span: int, column: string, slot: ?array}>
     */
    public function cells(): array
    {
        $cells = [];
        foreach ($this->slots as $slot) {
            $cells[] = ['row' => $slot['startUnit'], 'span' => $slot['heightUnits'], 'column' => $slot['side'] ?? 'full', 'slot' => $slot];
        }
        foreach ($this->occupancy() as $unit => $used) {
            if (!$used['left'] && !$used['right']) {
                $cells[] = ['row' => $unit, 'span' => 1, 'column' => 'full', 'slot' => null];
            } elseif ($this->hasHalves()) {
                if (!$used['left']) {
                    $cells[] = ['row' => $unit, 'span' => 1, 'column' => 'left', 'slot' => null];
                }
                if (!$used['right']) {
                    $cells[] = ['row' => $unit, 'span' => 1, 'column' => 'right', 'slot' => null];
                }
            }
        }
        usort($cells, static fn (array $a, array $b) => [$a['row'], $a['column'] === 'right' ? 1 : 0] <=> [$b['row'], $b['column'] === 'right' ? 1 : 0]);

        return $cells;
    }

    /** @deprecated Nur noch für ältere Aufrufer; die Ansicht nutzt cells(). */
    public function unitMap(): array
    {
        $map = [];
        $byStart = [];
        foreach ($this->slots as $slot) {
            $byStart[$slot['startUnit']] ??= $slot;
        }
        for ($unit = 1; $unit <= $this->heightUnits; $unit++) {
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
