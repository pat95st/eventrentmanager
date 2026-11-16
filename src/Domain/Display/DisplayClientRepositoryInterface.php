<?php

declare(strict_types=1);

namespace App\Domain\Display;

use DateTimeImmutable;

interface DisplayClientRepositoryInterface
{
    /** @return DisplayClient[] sorted by role, then name */
    public function findAll(): array;

    public function findById(string $id): ?DisplayClient;

    public function findByTokenHash(string $hash): ?DisplayClient;

    public function save(DisplayClient $client): void;

    public function delete(string $id): void;

    public function touch(string $id, DateTimeImmutable $at, ?string $ip, ?string $version): void;
}
