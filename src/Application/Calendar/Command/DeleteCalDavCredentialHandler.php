<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Calendar\CalDavCredentialRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteCalDavCredentialHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CalDavCredentialRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCalDavCredential);

        $credential = $this->repository->findById($command->id);
        // Absichtlich dieselbe NotFoundException für "existiert nicht" UND
        // "gehört einem anderen User" — sonst ließe sich per Fehlermeldung
        // erraten, ob eine fremde Credential-ID existiert.
        if ($credential === null || $credential->userId !== $command->userId) {
            throw new NotFoundException('Zugangsdaten wurden nicht gefunden.');
        }

        $this->repository->delete($credential->id);

        return null;
    }
}
