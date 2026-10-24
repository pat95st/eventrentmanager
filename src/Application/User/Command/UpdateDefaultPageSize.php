<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

/**
 * Ändert die Standard-Seitengröße für paginierte Listen (Artikel,
 * Veranstaltungen, Kunden, ...) des angemeldeten Benutzers. Der Controller
 * übergibt hier ausschließlich die eigene userId (aus CurrentUser::get()),
 * niemals eine aus dem Request gelesene ID — dadurch kann jeder Benutzer
 * ohne besonderes Recht nur seine eigene Einstellung ändern.
 */
final class UpdateDefaultPageSize implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly int $pageSize,
    ) {
    }
}
