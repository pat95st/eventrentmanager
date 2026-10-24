<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface UserSessionRepositoryInterface
{
    /** Legt die Zeile an, oder aktualisiert last_seen_at (+ user_agent/ip_address), falls die id bereits existiert. */
    public function upsert(UserSession $session): void;

    /** @return UserSession[] Nach last_seen_at absteigend (zuletzt aktiv zuerst). */
    public function findByUser(string $userId): array;

    public function findById(string $id): ?UserSession;

    public function deleteById(string $id): void;

    public function deleteAllForUserExcept(string $userId, string $exceptId): void;

    /** Beendet ALLE Sitzungen eines Benutzers, ohne Ausnahme (Admin-Aktion, siehe AdminRevokeAllSessionsHandler). */
    public function deleteAllForUser(string $userId): void;
}
