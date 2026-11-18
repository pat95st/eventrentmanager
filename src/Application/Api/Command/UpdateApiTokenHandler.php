<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Api\ApiTokenRepositoryInterface;
use App\Domain\Exception\NotFoundException;

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

        $input = ApiTokenInput::validate($command->name, $command->ipAllowlist, $command->scopes, $command->rateLimitPerMinute);
        $token->name = $input['name'];
        $token->ipAllowlist = $input['ipAllowlist'];
        $token->scopes = $input['scopes'];
        $token->readOnly = $command->readOnly;
        $token->rateLimitPerMinute = $input['rateLimit'];
        $this->repository->save($token);

        return null;
    }
}
