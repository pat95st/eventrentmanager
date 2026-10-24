<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\UserRepositoryInterface;

final class DeleteUserHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteUser);

        $user = $this->users->findById($command->id);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($user->isAdministrator() && $user->isActive && $this->users->countActiveAdministrators(excludingId: $user->id) === 0) {
            throw new ConflictException('Der letzte aktive Benutzer mit dem Recht "Administration" kann nicht gelöscht werden.');
        }

        $this->users->delete($command->id);

        return null;
    }
}
