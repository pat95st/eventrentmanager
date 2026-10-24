<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\UserSessionRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class RevokeSessionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserSessionRepositoryInterface $sessions)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RevokeSession);

        $session = $this->sessions->findById($command->sessionId);

        // Ownership-Prüfung hier im Handler, nicht nur durch Ausblenden des
        // Buttons in der UI — analog zu DeletePasskeyHandler. Absichtlich
        // derselbe generische Fehler, egal ob die Sitzung gar nicht existiert
        // oder einem anderen Benutzer gehört.
        if ($session === null || $session->userId !== $command->requestingUserId) {
            throw new NotFoundException('Sitzung wurde nicht gefunden.');
        }

        $this->sessions->deleteById($session->id);

        return null;
    }
}
