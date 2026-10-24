<?php

declare(strict_types=1);

namespace App\Domain\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function existsByEmail(string $email, ?string $excludingId = null): bool;

    /** @return User[] sortiert nach E-Mail-Adresse, höchstens $limit Zeilen ab $offset */
    public function findAll(int $limit, int $offset): array;

    /** Gesamtanzahl aller Benutzer (für die Paginierung). */
    public function countAll(): int;

    public function delete(string $id): void;

    /** Anzahl aktiver Benutzer mit dem Recht Administration (Löschsperre, siehe DeleteUserHandler). */
    public function countActiveAdministrators(?string $excludingId = null): int;
}
