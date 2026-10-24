<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\UserSessionRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class AdminRevokeSessionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserSessionRepositoryInterface $sessions)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof AdminRevokeSession);

        $session = $this->sessions->findById($command->sessionId);

        // Zugehörigkeits-Prüfung gegen die in der URL referenzierte
        // Ziel-Benutzer-ID (siehe AdminRevokeSession-Klassen-Doc) — bewusst
        // derselbe generische Fehler, egal ob die Sitzung gar nicht existiert
        // oder einem anderen Benutzer gehört, analog RevokeSessionHandler.
        if ($session === null || $session->userId !== $command->targetUserId) {
            throw new NotFoundException('Sitzung wurde nicht gefunden.');
        }

        $this->sessions->deleteById($session->id);

        return null;
    }
}
