<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Technician\RentalTechnician;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;

final class InMemoryRentalTechnicianRepository implements RentalTechnicianRepositoryInterface
{
    /** @var array<string, RentalTechnician> */
    public array $assignments = [];

    public function findById(string $id): ?RentalTechnician
    {
        return $this->assignments[$id] ?? null;
    }

    public function findByRental(string $rentalId): array
    {
        return array_values(array_filter($this->assignments, static fn (RentalTechnician $a) => $a->rentalId === $rentalId));
    }

    public function findByRentalAndUser(string $rentalId, string $userId): ?RentalTechnician
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment->rentalId === $rentalId && $assignment->userId === $userId) {
                return $assignment;
            }
        }

        return null;
    }

    public function findByUser(string $userId): array
    {
        return array_values(array_filter($this->assignments, static fn (RentalTechnician $a) => $a->userId === $userId));
    }

    public function findOpenRequests(): array
    {
        return array_values(array_filter($this->assignments, static fn (RentalTechnician $a) => $a->status === RentalTechnicianStatus::Requested));
    }

    public function save(RentalTechnician $rentalTechnician): void
    {
        $this->assignments[$rentalTechnician->id] = $rentalTechnician;
    }

    public function delete(string $id): void
    {
        unset($this->assignments[$id]);
    }
}
