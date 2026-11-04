<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rack\Rack;
use App\Domain\Rack\RackExemplar;
use App\Domain\Rack\RackRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;

/** Gemeinsam von GetRackHandler und ListRacksHandler genutzt. */
final class RackDetailBuilder
{
    /** @var array<string, \App\Domain\Manufacturer\Manufacturer|null> */
    private array $manufacturerCache = [];

    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly RentalRepositoryInterface $rentals,
        private readonly ?\App\Domain\Numbering\DocumentNumberingSettingsRepositoryInterface $numbering = null,
    ) {
    }

    public function build(Rack $rack): RackDetail
    {
        $exemplars = $this->racks->findExemplars($rack->id);
        $manufacturer = $this->manufacturer($rack->manufacturerId);

        $nextInventoryNumber = $manufacturer?->prefix !== null && $rack->prefix !== null
            ? \App\Domain\Rack\RackInventoryNumberGenerator::nextNumber($exemplars, $manufacturer->prefix, $rack->prefix)
            : null;

        return new RackDetail(
            $rack,
            $manufacturer,
            array_map(fn (RackExemplar $exemplar) => $this->exemplar($rack, $exemplar), $exemplars),
            $nextInventoryNumber,
            $this->nextRackNumber(),
        );
    }

    private function exemplar(Rack $rack, RackExemplar $exemplar): RackExemplarDetail
    {
        $slots = [];
        foreach ($this->racks->findSlots($exemplar->id) as $slot) {
            $device = $this->device($slot->inventoryNumberId);
            if ($device === null) {
                continue;
            }
            $height = max(1, $device['heightUnits']);
            $slots[] = [
                'slotId' => $slot->id,
                'inventoryNumberId' => $slot->inventoryNumberId,
                'number' => $device['number'],
                'label' => $device['label'],
                'startUnit' => $slot->startUnit,
                'endUnit' => $slot->startUnit + $height - 1,
                'heightUnits' => $height,
                'side' => $slot->side?->value, // Migration 128: 'left' | 'right' | null (ganze Breite)
            ];
        }

        // Von oben nach unten, bei gleicher HE links vor rechts (9,5-Zoll-Geräte, Migration 128).
        usort($slots, static fn (array $a, array $b) => [$a['startUnit'], $a['side'] === 'right' ? 1 : 0] <=> [$b['startUnit'], $b['side'] === 'right' ? 1 : 0]);

        $fixedDevice = null;
        $issuedIn = null;
        if ($exemplar->fixedInventoryNumberId !== null) {
            $device = $this->device($exemplar->fixedInventoryNumberId);
            if ($device !== null) {
                $fixedDevice = ['inventoryNumberId' => $exemplar->fixedInventoryNumberId, 'number' => $device['number'], 'label' => $device['label']];
            }
            foreach ($this->issuedItems->findByInventoryNumber($exemplar->fixedInventoryNumberId) as $issued) {
                if ($issued->returnedAt === null) {
                    $rental = $this->rentals->findById($issued->rentalId);
                    $issuedIn = ['rentalId' => $issued->rentalId, 'eventName' => $rental?->eventName ?? 'Veranstaltung'];
                    break;
                }
            }
        }

        return new RackExemplarDetail($exemplar, $rack->heightUnits, $slots, $fixedDevice, $issuedIn, $rack->width);
    }

    /** Vorschau der nächsten Rack-Nummer (RACK-0001 …), null ohne Migration 120/124. */
    private function nextRackNumber(): ?string
    {
        if ($this->numbering === null) {
            return null;
        }
        try {
            $settings = $this->numbering->findByType(\App\Domain\Numbering\NumberedDocumentType::Rack);
            $year = \App\Domain\Numbering\DocumentNumberFormatter::previewYear($settings);

            return \App\Domain\Numbering\DocumentNumberFormatter::format($settings, $year, $this->numbering->getNextNumber(\App\Domain\Numbering\NumberedDocumentType::Rack, $year));
        } catch (\RuntimeException) {
            return null;
        }
    }

    /** @return array{number: string, label: string, heightUnits: int}|null */
    private function device(string $inventoryNumberId): ?array
    {
        $inventoryNumber = $this->inventoryNumbers->findById($inventoryNumberId);
        if ($inventoryNumber === null) {
            return null;
        }
        $article = $this->articles->findById($inventoryNumber->articleId);

        return [
            'number' => $inventoryNumber->number,
            'label' => $article !== null ? ArticleDisplayName::of($article, $this->manufacturer($article->manufacturerId)) : '?',
            'heightUnits' => (int) ($article?->heightUnits ?? 1),
        ];
    }

    private function manufacturer(?string $id): ?\App\Domain\Manufacturer\Manufacturer
    {
        if ($id === null) {
            return null;
        }
        if (!array_key_exists($id, $this->manufacturerCache)) {
            $this->manufacturerCache[$id] = $this->manufacturers->findById($id);
        }

        return $this->manufacturerCache[$id];
    }
}
