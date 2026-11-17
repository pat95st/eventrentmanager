<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

final class UpdateConfirmBypassHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateConfirmBypass);

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        $user->confirmBypassEnabled = $command->enabled;
        $user->updatedAt = new DateTimeImmutable();

        $this->users->save($user);

        return null;
    }
}
