<?php

declare(strict_types=1);

namespace App\Domain\Technician;

interface TechnicianProfileRepositoryInterface
{
    public function find(string $userId): ?TechnicianProfile;

    public function save(TechnicianProfile $profile): void;
}
