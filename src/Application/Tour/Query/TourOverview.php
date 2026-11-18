<?php

declare(strict_types=1);

namespace App\Application\Tour\Query;

use App\Domain\Rental\Rental;
use App\Domain\Tour\TourSavedAddress;

final class TourOverview
{
    /**
     * @param TourStopView[] $entries stops and waypoints in driving order
     * @param array<string, int> $base base list
     * @param array<string, int> $startLoad loaded at the start (base + whole-tour additions)
     * @param array<string, int> $extraForIssue issuable on top of the base list
     * @param array<string, string> $articleNames
     * @param array<string, string> $tourCrew user id => name of the tour's technicians
     * @param array<string, string> $technicians user id => name of all active technicians
     * @param TourSavedAddress[] $savedAddresses
     * @param array<string, string> $warehouses id => name
     * @param array<string, string> $articleOptions id => display name of rentable articles
     */
    public function __construct(
        public readonly Rental $rental,
        public readonly array $entries,
        public readonly array $base,
        public readonly array $startLoad,
        public readonly array $extraForIssue,
        public readonly array $articleNames,
        public readonly array $tourCrew,
        public readonly array $technicians,
        public readonly array $savedAddresses,
        public readonly array $warehouses,
        public readonly array $articleOptions,
    ) {
    }

    /** @return TourStopView[] */
    public function stops(): array
    {
        return array_values(array_filter($this->entries, static fn (TourStopView $e) => $e->stop->isStop()));
    }

    /** @return TourStopView[] */
    public function waypoints(): array
    {
        return array_values(array_filter($this->entries, static fn (TourStopView $e) => !$e->stop->isStop()));
    }

    public function articleName(string $id): string
    {
        return $this->articleNames[$id] ?? 'Artikel';
    }
}
