<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Tour\TourSavedAddress;
use App\Domain\Tour\TourStop;
use App\Domain\Tour\TourStopItem;
use App\Domain\Tour\TourStopTechnician;

final class InMemoryTourRepository implements TourRepositoryInterface
{
    /** @var array<string, TourStop> */
    public array $stops = [];
    /** @var array<string, TourStopItem> */
    public array $items = [];
    /** @var array<string, TourStopTechnician> */
    public array $technicians = [];
    /** @var array<string, TourSavedAddress> */
    public array $addresses = [];
    /** @var array<string, array<string, int>> rental id => article id => extra */
    public array $extras = [];
    /** @var array<string, ?string> minute program id => stop id */
    public array $programs = [];

    public function findStopsByRental(string $rentalId): array
    {
        return array_values(array_filter($this->stops, static fn (TourStop $s) => $s->rentalId === $rentalId));
    }

    public function findStop(string $id): ?TourStop
    {
        return $this->stops[$id] ?? null;
    }

    public function saveStop(TourStop $stop): void
    {
        $this->stops[$stop->id] = $stop;
    }

    public function deleteStop(string $id): void
    {
        unset($this->stops[$id]);
        $this->items = array_filter($this->items, static fn (TourStopItem $i) => $i->stopId !== $id);
        $this->technicians = array_filter($this->technicians, static fn (TourStopTechnician $t) => $t->stopId !== $id);
    }

    public function findItemsByRental(string $rentalId): array
    {
        return array_values(array_filter($this->items, fn (TourStopItem $i) => ($this->stops[$i->stopId] ?? null)?->rentalId === $rentalId));
    }

    public function findItem(string $stopId, string $articleId): ?TourStopItem
    {
        foreach ($this->items as $item) {
            if ($item->stopId === $stopId && $item->articleId === $articleId) {
                return $item;
            }
        }

        return null;
    }

    public function saveItem(TourStopItem $item): void
    {
        $this->items[$item->id] = $item;
    }

    public function deleteItem(string $id): void
    {
        unset($this->items[$id]);
    }

    public function findTechniciansByRental(string $rentalId): array
    {
        return array_values(array_filter($this->technicians, fn (TourStopTechnician $t) => ($this->stops[$t->stopId] ?? null)?->rentalId === $rentalId));
    }

    public function saveTechnician(TourStopTechnician $technician): void
    {
        $this->technicians[$technician->id] = $technician;
    }

    public function deleteTechnician(string $id): void
    {
        unset($this->technicians[$id]);
    }

    public function findSavedAddresses(): array
    {
        return array_values($this->addresses);
    }

    public function findSavedAddress(string $id): ?TourSavedAddress
    {
        return $this->addresses[$id] ?? null;
    }

    public function saveSavedAddress(TourSavedAddress $address): void
    {
        $this->addresses[$address->id] = $address;
    }

    public function syncExtraQuantities(string $rentalId, array $extraByArticle): void
    {
        $this->extras[$rentalId] = array_filter($extraByArticle, static fn (int $q) => $q > 0);
    }

    public function minuteProgramsByStop(string $rentalId): array
    {
        $result = [];
        foreach ($this->programs as $programId => $stopId) {
            if ($stopId !== null) {
                $result[$stopId][] = ['id' => $programId, 'name' => 'Programm'];
            }
        }

        return $result;
    }

    public function assignMinuteProgram(string $minuteProgramId, ?string $stopId): void
    {
        $this->programs[$minuteProgramId] = $stopId;
    }
}
