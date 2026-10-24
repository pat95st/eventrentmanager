<?php

declare(strict_types=1);

namespace App\Domain\Venue;

interface VenueRepositoryInterface
{
    public function save(Venue $venue): void;

    public function findById(string $id): ?Venue;

    /** Sucht einen Veranstaltungsort mit exakt übereinstimmendem Namen (Groß-/Kleinschreibung wird ignoriert). */
    public function findByName(string $name): ?Venue;

    /**
     * @param int|null $limit null = ohne LIMIT (z. B. für die Veranstaltungsort-Auswahl im Veranstaltungsformular).
     * @return Venue[] alphabetisch nach Name
     */
    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie findAll(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countAll(?string $search = null): int;

    public function isUsedByRentals(string $id): bool;

    public function delete(string $id): void;
}
