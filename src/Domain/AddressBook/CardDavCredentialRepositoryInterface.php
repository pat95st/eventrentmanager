<?php

declare(strict_types=1);

namespace App\Domain\AddressBook;

use DateTimeImmutable;

interface CardDavCredentialRepositoryInterface
{
    public function save(CardDavCredential $credential): void;

    public function findById(string $id): ?CardDavCredential;

    public function findByTokenHash(string $tokenHash): ?CardDavCredential;

    /** @return CardDavCredential[] sortiert nach Erstelldatum absteigend */
    public function findByUser(string $userId): array;

    public function touchLastUsed(string $id, DateTimeImmutable $at): void;

    public function delete(string $id): void;
}
