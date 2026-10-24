<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

final class GetUserHandler implements QueryHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(QueryInterface $query): User
    {
        assert($query instanceof GetUser);

        $user = $this->users->findById($query->id);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$query->id}\" wurde nicht gefunden.");
        }

        return $user;
    }
}
