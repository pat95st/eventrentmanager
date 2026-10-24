<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

use DateTimeImmutable;

interface CalDavCredentialRepositoryInterface
{
    public function save(CalDavCredential $credential): void;

    public function findById(string $id): ?CalDavCredential;

    public function findByTokenHash(string $tokenHash): ?CalDavCredential;

    /** @return CalDavCredential[] sortiert nach Erstelldatum absteigend */
    public function findByUser(string $userId): array;

    public function touchLastUsed(string $id, DateTimeImmutable $at): void;

    public function delete(string $id): void;
}
