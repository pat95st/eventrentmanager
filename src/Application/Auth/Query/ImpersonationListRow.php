<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Domain\Auth\Impersonation;
use DateTimeImmutable;

/**
 * View-Modell einer Protokollzeile: trägt die aufgelösten Anzeigenamen von
 * Administrator/Zielbenutzer mit, damit die View selbst keine Repositories
 * aufrufen muss (analog zu anderen "List*"-Handlern im Projekt, die
 * zugehörige Entitäten bereits im Handler auflösen, z. B. ListRmasHandler).
 */
final class ImpersonationListRow
{
    public function __construct(
        public readonly Impersonation $impersonation,
        // null, falls der Benutzer inzwischen gelöscht wurde (users.id ist
        // ON DELETE CASCADE, daher sollte das praktisch nicht vorkommen,
        // aber die View soll nicht brechen, falls doch).
        public readonly ?string $adminDisplayName,
        public readonly ?string $targetDisplayName,
    ) {
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->impersonation->startedAt;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->impersonation->endedAt;
    }

    public function isActive(): bool
    {
        return $this->impersonation->isActive();
    }
}
