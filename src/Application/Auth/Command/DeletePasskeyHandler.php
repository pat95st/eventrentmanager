<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\WebauthnCredentialRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeletePasskeyHandler implements CommandHandlerInterface
{
    public function __construct(private readonly WebauthnCredentialRepositoryInterface $credentials)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeletePasskey);

        $credential = $this->credentials->findById($command->passkeyId);

        // Absichtlich derselbe "nicht gefunden"-Fehler, egal ob der Passkey
        // gar nicht existiert oder einem anderen Benutzer gehört — ein
        // Benutzer darf ausschließlich seine eigenen Passkeys löschen
        // (Ownership-Prüfung hier im Handler, nicht nur durch Ausblenden
        // des Löschen-Buttons in der UI).
        if ($credential === null || $credential->userId !== $command->requestingUserId) {
            throw new NotFoundException('Passkey wurde nicht gefunden.');
        }

        $this->credentials->delete($credential->id);

        return null;
    }
}
