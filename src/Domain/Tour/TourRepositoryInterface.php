<?php

declare(strict_types=1);

namespace App\Domain\Tour;

/** Persistence of stops, stop deviations and saved addresses of tours. */
interface TourRepositoryInterface
{
    /** @return TourStop[] stops and waypoints of a tour (unordered, see TourPlan) */
    public function findStopsByRental(string $rentalId): array;

    public function findStop(string $id): ?TourStop;

    public function saveStop(TourStop $stop): void;

    public function deleteStop(string $id): void;

    /** @return TourStopItem[] all material deviations of a tour */
    public function findItemsByRental(string $rentalId): array;

    public function findItem(string $stopId, string $articleId): ?TourStopItem;

    public function saveItem(TourStopItem $item): void;

    public function deleteItem(string $id): void;

    /** @return TourStopTechnician[] all crew deviations of a tour */
    public function findTechniciansByRental(string $rentalId): array;

    public function saveTechnician(TourStopTechnician $technician): void;

    public function deleteTechnician(string $id): void;

    /** @return TourSavedAddress[] sorted by label */
    public function findSavedAddresses(): array;

    public function findSavedAddress(string $id): ?TourSavedAddress;

    public function saveSavedAddress(TourSavedAddress $address): void;

    /**
     * Writes rental_items.tour_extra_quantity for the tour; articles that
     * only exist as extras get a rental item with quantity 0, extras that
     * dropped to 0 are reset (and empty rows without issued pieces removed).
     *
     * @param array<string, int> $extraByArticle
     */
    public function syncExtraQuantities(string $rentalId, array $extraByArticle): void;

    /** @return array<string, list<array{id: string, name: string}>> stop id => minute programs */
    public function minuteProgramsByStop(string $rentalId): array;

    public function assignMinuteProgram(string $minuteProgramId, ?string $stopId): void;
}
