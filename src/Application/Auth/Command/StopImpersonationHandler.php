<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\ImpersonationRepositoryInterface;
use DateTimeImmutable;

final class StopImpersonationHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ImpersonationRepositoryInterface $impersonations)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof StopImpersonation);

        $impersonation = $this->impersonations->findById($command->impersonationId);

        // Bewusst kein Fehler, falls der Datensatz nicht (mehr) existiert
        // oder bereits beendet ist — das Beenden der Session-seitigen
        // Impersonation (Session::stopImpersonation()) darf davon nicht
        // abhängen, das Protokoll ist rein informativ.
        if ($impersonation === null || !$impersonation->isActive()) {
            return null;
        }

        $impersonation->endedAt = new DateTimeImmutable();
        $this->impersonations->save($impersonation);

        return null;
    }
}
