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

    /**
     * Alle aktiven Benutzer, sortiert nach Nachname/Vorname (Fallback
     * E-Mail-Adresse für Altdaten ohne Namen) — ungepaginiert, für
     * Auswahllisten (z. B. Mitarbeiter je Arbeitszeit-Position, siehe
     * InvoiceLaborItem::$userId/OfferLaborItem::$userId), bei denen alle
     * Optionen auf einmal gebraucht werden statt einer Seite.
     *
     * @return User[]
     */
    public function findAllActive(): array;

    /** Gesamtanzahl aller Benutzer (für die Paginierung). */
    public function countAll(): int;

    public function delete(string $id): void;

    /** Anzahl aktiver Benutzer mit dem Recht Administration (Löschsperre, siehe DeleteUserHandler). */
    public function countActiveAdministrators(?string $excludingId = null): int;
}
