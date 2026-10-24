<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Api\ApiTokenRepositoryInterface;
use App\Domain\Api\IpAllowlist;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;

final class UpdateApiTokenHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ApiTokenRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateApiToken);

        $token = $this->repository->findById($command->id);
        if ($token === null) {
            throw new NotFoundException("Token \"{$command->id}\" wurde nicht gefunden.");
        }

        $name = trim((string) $command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für das Token angeben.']);
        }

        $ipAllowlist = trim((string) $command->ipAllowlist);
        if ($ipAllowlist !== '') {
            $error = IpAllowlist::validationError($ipAllowlist);
            if ($error !== null) {
                throw new ValidationException(['ipAllowlist' => $error]);
            }
        }

        $token->name = $name;
        $token->ipAllowlist = $ipAllowlist !== '' ? $ipAllowlist : null;
        $this->repository->save($token);

        return null;
    }
}
