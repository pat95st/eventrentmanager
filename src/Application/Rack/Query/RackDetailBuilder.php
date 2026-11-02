<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rack\Rack;
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
    ) {
    }

    public function build(Rack $rack): RackDetail
    {
        $slots = [];
        foreach ($this->racks->findSlots($rack->id) as $slot) {
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
            ];
        }

        $fixedDevice = null;
        $issuedIn = null;
        if ($rack->fixedInventoryNumberId !== null) {
            $device = $this->device($rack->fixedInventoryNumberId);
            if ($device !== null) {
                $fixedDevice = ['inventoryNumberId' => $rack->fixedInventoryNumberId, 'number' => $device['number'], 'label' => $device['label']];
            }
            foreach ($this->issuedItems->findByInventoryNumber($rack->fixedInventoryNumberId) as $issued) {
                if ($issued->returnedAt === null) {
                    $rental = $this->rentals->findById($issued->rentalId);
                    $issuedIn = ['rentalId' => $issued->rentalId, 'eventName' => $rental?->eventName ?? 'Veranstaltung'];
                    break;
                }
            }
        }

        return new RackDetail($rack, $this->manufacturer($rack->manufacturerId), $slots, $fixedDevice, $issuedIn);
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
