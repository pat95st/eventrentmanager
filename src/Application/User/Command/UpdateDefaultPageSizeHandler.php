<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Http\Pagination;
use DateTimeImmutable;

final class UpdateDefaultPageSizeHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateDefaultPageSize);

        if (!Pagination::isValidPageSize($command->pageSize)) {
            throw new ValidationException(['pageSize' => 'Bitte eine der angebotenen Seitengrößen auswählen.']);
        }

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        $user->defaultPageSize = $command->pageSize;
        $user->updatedAt = new DateTimeImmutable();

        $this->users->save($user);

        return null;
    }
}
