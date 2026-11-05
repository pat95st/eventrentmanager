<?php

declare(strict_types=1);

namespace App\Domain\Technician;

interface RentalTechnicianRepositoryInterface
{
    public function findById(string $id): ?RentalTechnician;

    /** @return RentalTechnician[] */
    public function findByRental(string $rentalId): array;

    public function findByRentalAndUser(string $rentalId, string $userId): ?RentalTechnician;

    /** @return RentalTechnician[] alle Einträge eines Technikers (neueste Veranstaltung zuerst ist Sache des Aufrufers) */
    public function findByUser(string $userId): array;

    /** @return RentalTechnician[] unbeantwortete Anfragen (status requested) */
    public function findOpenRequests(): array;

    public function save(RentalTechnician $rentalTechnician): void;

    public function delete(string $id): void;
}
