<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use DateTimeImmutable;

final class DisconnectStorageTargetOAuthHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DocumentStorageTargetRepositoryInterface $targets)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DisconnectStorageTargetOAuth);

        $target = $this->targets->findById($command->targetId);
        if ($target === null) {
            throw new NotFoundException("Ziellaufwerk \"{$command->targetId}\" wurde nicht gefunden.");
        }

        $target->disconnectOAuth();
        $target->updatedAt = new DateTimeImmutable();
        $this->targets->save($target);

        return null;
    }
}
